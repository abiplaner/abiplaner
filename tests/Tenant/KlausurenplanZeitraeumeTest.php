<?php

namespace Tests\Tenant;

use App\Models\ExamSchedulePeriod;
use App\Models\GlobalCalendarEvent;
use App\Models\School;
use App\Models\User;
use App\Services\KlausurenplanZeitraeumeService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die vier Klausurenplan-Zeiträume eines Schuljahres (21.09.2026).
 *
 * Gemeldet: /timetable/exam-schedule zeigt „Kein Klausurenplan gefunden". Seit
 * die Seite das laufende Schuljahr vorwählt (2026/27), gab es dafür keine
 * Zeiträume — sie existierten nur für 2025/26, von Hand eingetragen, und die
 * Anwendung hat keine Maske dafür. Die vier Zeiträume gibt es aber jedes Jahr;
 * ihre Grenzen ergeben sich aus dem Terminkalender.
 *
 * Die Tests benutzen das Schuljahr 2040/41, damit sie die echten Termine der
 * zentralen Datenbank nicht berühren. Globale Termine rollt keine Transaktion
 * zurück; tearDown löscht sie über den Zeitraum des Testschuljahres.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit tests/Tenant/KlausurenplanZeitraeumeTest.php
 */
class KlausurenplanZeitraeumeTest extends TenantTestCase
{
    private const JAHR = '2040/41';
    private const PLANNER_EMAIL = 'planner-zeitraeume-9999@test.invalid';

    protected School $school;
    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loescheTestTermine();
        User::where('email', self::PLANNER_EMAIL)->delete();

        $this->school = $this->createTestSchool();

        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

    }

    protected function tearDown(): void
    {
        $this->loescheTestTermine();
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    private function loescheTestTermine(): void
    {
        GlobalCalendarEvent::whereBetween('event_date', ['2040-08-01', '2041-07-31'])->delete();
    }

    /**
     * Die Termine, aus denen sich die Grenzen ergeben. Wochentage 2040/41:
     * Kurswahl Mo 08.10., Zeugnis 13/1 Fr 25.01., Zeugnis 12/1 Fr 15.02.,
     * erste schriftliche Abiturprüfung Mi 17.04., Zeugnis 12/2 Fr 26.07.
     *
     * @param list<string> $ohne Schlüssel der Termine, die fehlen sollen
     */
    private function termine(array $ohne = []): void
    {
        $alle = [
            'kurswahl' => ['Änderung der Kurswahl mit Genehmigung der Schulleitung', '2040-10-08', 'deadline'],
            'zeugnis13' => ['Zeugnis AA 13/1', '2041-01-25', 'administration'],
            'zeugnis121' => ['Zeugnis AA 12/1', '2041-02-15', 'administration'],
            'abitur' => ['Schriftliche Abiturprüfung Deutsch', '2041-04-17', 'deadline'],
            'abitur2' => ['Schriftliche Abiturprüfung Mathematik', '2041-04-24', 'deadline'],
            'zeugnis122' => ['Zeugnis AA 12/2', '2041-07-26', 'administration'],
        ];

        foreach ($alle as $schluessel => [$titel, $datum, $typ]) {
            if (in_array($schluessel, $ohne, true)) {
                continue;
            }

            GlobalCalendarEvent::create([
                'title' => $titel,
                'event_date' => $datum,
                'event_type' => $typ,
                'grade_levels' => [12, 13],
                'color' => '#3B82F6',
                'is_recurring' => false,
                'priority' => 5,
            ]);
        }
    }

    /** @return array<string, ExamSchedulePeriod> */
    private function zeitraeume(): array
    {
        return ExamSchedulePeriod::where('school_year', self::JAHR)->get()->keyBy('name')->all();
    }

    #[Test]
    public function die_vier_zeitraeume_ergeben_sich_aus_dem_terminkalender(): void
    {
        $this->termine();

        $neu = app(KlausurenplanZeitraeumeService::class)->anlegen(self::JAHR);

        $this->assertSame(4, $neu);

        $z = $this->zeitraeume();
        $this->assertSame(['Q12/1', 'Q12/2', 'Q13/1', 'Q13/2'], collect(array_keys($z))->sort()->values()->all());

        // Q12/1 und Q13/1 beginnen mit der Kurswahländerung.
        $this->assertSame('2040-10-08', $z['Q12/1']->start_date->toDateString());
        $this->assertSame('2040-10-08', $z['Q13/1']->start_date->toDateString());

        // Q12/1 endet mit dem Zeugnis 12/1, Q13/1 schon mit dem Zeugnis 13/1.
        $this->assertSame('2041-02-15', $z['Q12/1']->end_date->toDateString());
        $this->assertSame('2041-01-25', $z['Q13/1']->end_date->toDateString());

        // Das zweite Halbjahr beginnt am nächsten Schultag — das Wochenende zählt nicht.
        $this->assertSame('2041-02-18', $z['Q12/2']->start_date->toDateString());
        $this->assertSame('2041-01-28', $z['Q13/2']->start_date->toDateString());

        // Q12/2 endet mit dem Zeugnis 12/2, Q13/2 am Freitag der Woche der
        // ersten schriftlichen Abiturprüfung (nicht der letzten).
        $this->assertSame('2041-07-26', $z['Q12/2']->end_date->toDateString());
        $this->assertSame('2041-04-19', $z['Q13/2']->end_date->toDateString());
    }

    #[Test]
    public function nur_q12_1_ist_vorgewaehlt(): void
    {
        $this->termine();

        app(KlausurenplanZeitraeumeService::class)->anlegen(self::JAHR);

        $aktiv = collect($this->zeitraeume())->filter(fn ($p) => $p->is_active)->keys()->all();
        $this->assertSame(['Q12/1'], $aktiv);
    }

    #[Test]
    public function ohne_die_noetigen_termine_wird_nichts_geraten(): void
    {
        $this->termine(ohne: ['zeugnis13', 'abitur', 'abitur2']);

        $service = app(KlausurenplanZeitraeumeService::class);

        $this->assertSame(0, $service->anlegen(self::JAHR));
        $this->assertSame([], $this->zeitraeume());

        $fehlend = $service->ableiten(self::JAHR)['fehlend'];
        $this->assertCount(2, $fehlend);
        $this->assertStringContainsString('Zeugnis AA 13/1', $fehlend[0]);
        $this->assertStringContainsString('Abiturprüfungen', $fehlend[1]);
    }

    #[Test]
    public function vorhandene_zeitraeume_bleiben_unberuehrt_und_es_entsteht_nichts_doppelt(): void
    {
        $this->termine();

        // Von Hand angelegt, mit anderen Daten als die Ableitung.
        ExamSchedulePeriod::create([
            'name' => 'Q13/1', 'school_year' => self::JAHR,
            'start_date' => '2040-10-01', 'end_date' => '2041-02-01', 'is_active' => false,
        ]);

        $service = app(KlausurenplanZeitraeumeService::class);

        $this->assertSame(3, $service->anlegen(self::JAHR), 'Nur die drei fehlenden werden angelegt.');
        $this->assertSame(0, $service->anlegen(self::JAHR), 'Ein zweiter Lauf legt nichts an.');

        $this->assertCount(4, $this->zeitraeume());
        $this->assertSame('2041-02-01', $this->zeitraeume()['Q13/1']->end_date->toDateString());
    }

    #[Test]
    public function ein_ungueltiges_schuljahr_legt_nichts_an(): void
    {
        $service = app(KlausurenplanZeitraeumeService::class);

        $this->assertSame(0, $service->anlegen('irgendwas'));
        $this->assertSame([], $service->ableiten('irgendwas')['zeitraeume']);
        $this->assertSame(0, ExamSchedulePeriod::where('school_year', 'irgendwas')->count());
    }

    #[Test]
    public function die_seite_legt_die_zeitraeume_beim_ersten_aufruf_an(): void
    {
        $this->termine();

        $this->assertSame(0, ExamSchedulePeriod::where('school_year', self::JAHR)->count());

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR]))
            ->assertOk()
            ->assertDontSee('Kein Klausurenplan gefunden')
            ->assertSee('Q12/1');

        $this->assertSame(4, ExamSchedulePeriod::where('school_year', self::JAHR)->count());
    }

    #[Test]
    public function die_seite_nennt_die_fehlenden_termine_statt_nur_keinen_plan_zu_melden(): void
    {
        $this->termine(ohne: ['zeugnis122']);

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR]))
            ->assertOk()
            ->assertSee('Kein Klausurenplan gefunden')
            ->assertSee('Zeugnis AA 12/2');

        $this->assertSame(0, ExamSchedulePeriod::where('school_year', self::JAHR)->count());
    }
}
