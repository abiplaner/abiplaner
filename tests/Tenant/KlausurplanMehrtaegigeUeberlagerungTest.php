<?php

namespace Tests\Tenant;

use App\Models\ExamScheduleEntry;
use App\Models\ExamSchedulePeriod;
use App\Models\School;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Klausurplan (/timetable/exam-schedule): Ein mehrtägiger Prüfungsblock
 * (z. B. „Prakt./Mdl. Prüfung" Mo–Fr) darf eine einzelne Klausur in
 * derselben Woche nicht verdecken.
 *
 * Gemeldet (27.09.2026): Im Plan der Q13/1 wurde in der Woche 11.01.–15.01.
 * die Psychologie-Klausur von den Prakt./Mdl. Prüfungen überdeckt. Ursache:
 * Die Zelle eines mehrtägigen Blocks (colspan) zeigte nur den Block selbst
 * und blendete alle übrigen Prüfungen der überspannten Tage aus.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit tests/Tenant/KlausurplanMehrtaegigeUeberlagerungTest.php
 */
class KlausurplanMehrtaegigeUeberlagerungTest extends TenantTestCase
{
    private const JAHR = '2040/41';
    private const PLANNER_EMAIL = 'planner-ueberlagerung-9999@test.invalid';

    protected School $school;
    protected User $planner;

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


        $period = ExamSchedulePeriod::create([
            'name' => 'Q13/1', 'school_year' => self::JAHR,
            'start_date' => '2040-10-08', 'end_date' => '2041-01-25', 'is_active' => true,
        ]);

        // Mo 07.01.2041 – Fr 11.01.2041
        ExamScheduleEntry::create([
            'exam_schedule_period_id' => $period->id,
            'course_codes' => '2kolloq-test',
            'subject' => 'Kolloquium',
            'exam_type' => 'kolloquium',
            'exam_date' => '2041-01-07',
            'exam_end_date' => '2041-01-11',
            'start_period' => 0,
            'end_period' => 0,
        ]);

        // Mi 09.01.2041 – mitten im Block
        ExamScheduleEntry::create([
            'exam_schedule_period_id' => $period->id,
            'course_codes' => '2psy-test',
            'subject' => 'Psychologie',
            'exam_type' => 'schriftlich',
            'exam_date' => '2041-01-09',
            'start_period' => 3,
            'end_period' => 4,
        ]);
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    #[Test]
    public function klausur_im_zeitraum_eines_mehrtaegigen_blocks_bleibt_sichtbar(): void
    {
        $html = $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q13/1']))
            ->assertOk()
            ->assertSee('Prakt./Mdl. Prüfung')
            ->assertSee('2kolloq-test')
            ->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        // Der Block läuft als eine Zelle über Mo–Fr ...
        $block = $xpath->query('//td[@data-date="2041-01-07"][@colspan="5"]');
        $this->assertSame(1, $block->length);
        $this->assertStringContainsString('2kolloq-test', $block->item(0)->textContent);
        $this->assertStringNotContainsString('2psy-test', $block->item(0)->textContent);

        // ... die Klausur steht in einer eigenen Zelle genau am Mittwoch,
        // als gewöhnlicher Eintrag (nicht mit den Blocktagen dazwischen).
        $mittwoch = $xpath->query('//td[@data-date="2041-01-09"][not(@colspan)]');
        $this->assertSame(1, $mittwoch->length);
        $this->assertStringContainsString('2psy-test', $mittwoch->item(0)->textContent);
        foreach (['2041-01-07', '2041-01-08', '2041-01-10', '2041-01-11'] as $tag) {
            $zelle = $xpath->query('//td[@data-date="' . $tag . '"][not(@colspan)]');
            $this->assertSame(1, $zelle->length, $tag);
            $this->assertStringNotContainsString('2psy-test', $zelle->item(0)->textContent, $tag);
        }
    }

    #[Test]
    public function ohne_weitere_pruefung_bleibt_der_block_einzeilig(): void
    {
        ExamScheduleEntry::where('course_codes', '2psy-test')->delete();

        $html = $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q13/1']))
            ->assertOk()
            ->assertSee('2kolloq-test')
            ->getContent();

        $this->assertStringNotContainsString('rowspan="2"', $html);
    }

    #[Test]
    public function pdf_export_mit_ueberlagerung_liefert_gueltiges_pdf(): void
    {
        $response = $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule.export-pdf', ['school_year' => self::JAHR, 'period' => 'Q13/1']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
