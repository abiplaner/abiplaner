<?php

namespace Tests\Tenant;

use App\Models\CourseTrack;
use App\Models\ExamScheduleEntry;
use App\Models\ExamSchedulePeriod;
use App\Models\School;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Klausurenplan: Kurzarbeiten zusätzlich zur Klausur.
 *
 * Gewünscht (27.09.2026): Die Mathematiklehrer schreiben gemeinsam eine
 * Kurzarbeit, die zusätzlich zur Klausur im Klausurenplan stehen soll —
 * womöglich auch andere Schienen. Regeln (mit Wilfried abgestimmt):
 * je Kurs und Zeitraum eine Klausur und höchstens eine Kurzarbeit; am
 * selben Tag darf ein Kurs nie zweimal stehen. Angelegt wird über „+ KA"
 * in der Tageszelle, auch für bereits verplante Schienen.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit tests/Tenant/KlausurplanKurzarbeitTest.php
 */
class KlausurplanKurzarbeitTest extends TenantTestCase
{
    private const JAHR = '2040/41';
    private const PLANNER_EMAIL = 'planner-kurzarbeit-9999@test.invalid';

    protected School $school;
    protected User $planner;
    protected ExamSchedulePeriod $period;
    protected CourseTrack $schiene;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();

        $this->school = $this->createTestSchool();

        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);


        $this->period = ExamSchedulePeriod::create([
            'name' => 'Q13/1', 'school_year' => self::JAHR,
            'start_date' => '2040-10-08', 'end_date' => '2041-01-25', 'is_active' => true,
        ]);

        $this->schiene = CourseTrack::create([
            'school_year' => self::JAHR,
            'grade' => 'Q13',
            'name' => 'Mathe-Test',
            'course_codes' => ['2mka1', '2mka2'],
            'time_slots' => [['day_short' => 'Mi', 'periods' => [3, 4]]],
        ]);
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function trackPayload(): array
    {
        return [
            'courses' => ['2mka1', '2mka2'],
            'number' => $this->schiene->id,
            'period' => 3,
            'day_period_ranges' => [3 => ['start' => 3, 'end' => 4]],
            'all_time_slots' => 'Mi 3./4',
        ];
    }

    private function eintragen(string $datum, string $art): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->planner)->postJson(route('timetable.exam-schedule.assign-track'), [
            'period_id' => $this->period->id,
            'track' => $this->trackPayload(),
            'exam_date' => $datum,
            'exam_type' => $art,
            'is_conflict' => false,
        ]);
    }

    #[Test]
    public function kurzarbeit_zusaetzlich_zur_klausur_wird_eingetragen(): void
    {
        $this->eintragen('2040-11-14', 'schriftlich')->assertOk();
        $this->eintragen('2040-12-05', 'kurzarbeit')->assertOk();

        $this->assertSame(1, ExamScheduleEntry::where('exam_type', 'schriftlich')->count());
        $ka = ExamScheduleEntry::where('exam_type', 'kurzarbeit')->sole();
        $this->assertSame('2040-12-05', $ka->exam_date->format('Y-m-d'));
        $this->assertNull($ka->exam_end_date);
        $this->assertSame(3, $ka->start_period);
        $this->assertSame(4, $ka->end_period);
    }

    #[Test]
    public function eine_zweite_kurzarbeit_wird_abgelehnt(): void
    {
        $this->eintragen('2040-11-14', 'kurzarbeit')->assertOk();
        $this->eintragen('2040-12-05', 'kurzarbeit')->assertStatus(409);

        $this->assertSame(1, ExamScheduleEntry::count());
    }

    #[Test]
    public function kurzarbeit_am_tag_der_klausur_wird_abgelehnt(): void
    {
        $this->eintragen('2040-11-14', 'schriftlich')->assertOk();
        $this->eintragen('2040-11-14', 'kurzarbeit')->assertStatus(409);

        $this->assertSame(1, ExamScheduleEntry::count());
    }

    #[Test]
    public function die_klausur_bleibt_nach_einer_kurzarbeit_moeglich_aber_nur_einmal(): void
    {
        $this->eintragen('2040-11-14', 'kurzarbeit')->assertOk();
        $this->eintragen('2040-12-05', 'schriftlich')->assertOk();
        $this->eintragen('2040-12-12', 'schriftlich')->assertStatus(409);

        $this->assertSame(2, ExamScheduleEntry::count());
    }

    #[Test]
    public function schiene_mit_kurzarbeit_bleibt_in_der_seitenleiste_und_ka_wird_angezeigt(): void
    {
        $this->eintragen('2040-11-14', 'kurzarbeit')->assertOk();

        $html = $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q13/1']))
            ->assertOk()
            ->assertSee('+ KA')
            ->getContent();

        // Die Schiene ist noch ziehbar (Klausur fehlt) …
        $this->assertStringContainsString('title="Ziehen auf Klausurtermin"', $html);
        $this->assertMatchesRegularExpression('/Mathe-Test\s*•/', $html);

        // … und die Kurzarbeit steht als „KA" am Mittwoch
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $zelle = (new \DOMXPath($dom))->query('//td[@data-date="2040-11-14"]')->item(0);
        $this->assertNotNull($zelle);
        $this->assertMatchesRegularExpression('/KA\s+2mka1, 2mka2/', $zelle->textContent);
    }

    #[Test]
    public function verplante_schiene_steht_weiter_zur_auswahl_fuer_die_kurzarbeit(): void
    {
        $this->eintragen('2040-11-14', 'schriftlich')->assertOk();

        $response = $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q13/1']))
            ->assertOk();

        // aus der Seitenleiste verschwunden …
        $this->assertDoesNotMatchRegularExpression('/Mathe-Test\s*•/', $response->getContent());
        // … aber für „+ KA" wählbar
        $schienen = $response->viewData('kurzarbeitSchienen');
        $this->assertTrue($schienen->contains('name', 'Mathe-Test'));
    }

    #[Test]
    public function pdf_export_mit_kurzarbeit_liefert_gueltiges_pdf(): void
    {
        $this->eintragen('2040-11-14', 'kurzarbeit')->assertOk();

        $response = $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule.export-pdf', ['school_year' => self::JAHR, 'period' => 'Q13/1']));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
