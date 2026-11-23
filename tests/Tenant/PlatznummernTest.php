<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\Room;
use App\Models\RoomSeatingTemplate;
use App\Models\SeatingTemplate;
use App\Models\SeatingTemplateSeat;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\WrittenExamAssignment;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die fortlaufenden Platznummern eines Raumlayouts.
 *
 * Sie standen bis zum 27.08.2026 fünfmal gleichlautend im ExportController.
 * Wer einen Export ausserhalb dieser fünf Stellen schrieb, griff stattdessen
 * auf `seating_template_seats.seat_number` zu — **eine Spalte, die es weder
 * lokal noch in der Produktion gibt**. Das war kein Fehler, sondern ein leerer
 * String: „Platz " ohne Zahl, auf Sitzkarten, Sitzplänen, Schülerlisten und im
 * Nachteilsausgleich-Kasten der Aufsichtsliste.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter Platznummern
 */
class PlatznummernTest extends TenantTestCase
{
    private function layout(): SeatingTemplate
    {
        // 3 Reihen à 4 Spalten. Reihe 1: Pult, Gang, regulär, regulär.
        // Reihe 2: vier reguläre. Reihe 3: blockiert und zwei Reserveplätze.
        $template = SeatingTemplate::create(['name' => 'Nummern-Test', 'rows' => 3, 'columns' => 4]);

        $plan = [
            1 => ['teacher_desk', 'aisle', 'available', 'available'],
            2 => ['available', 'available', 'available', 'available'],
            3 => ['blocked', 'reserve', 'reserve', 'blocked'],
        ];

        foreach ($plan as $reihe => $spalten) {
            foreach ($spalten as $index => $typ) {
                SeatingTemplateSeat::create([
                    'template_id' => $template->id,
                    'row_number' => $reihe,
                    'column_number' => $index + 1,
                    'seat_type' => $typ,
                ]);
            }
        }

        return $template->fresh();
    }

    private function platz(SeatingTemplate $template, int $reihe, int $spalte): SeatingTemplateSeat
    {
        return SeatingTemplateSeat::where('template_id', $template->id)
            ->where('row_number', $reihe)
            ->where('column_number', $spalte)
            ->firstOrFail();
    }

    #[Test]
    public function nummeriert_werden_nur_besetzbare_plaetze(): void
    {
        $school = $this->createTestSchool();

        $template = $this->layout();
        $nummern = $template->platznummern();

        $this->assertCount(8, $nummern, 'Sechs reguläre Plätze und zwei Reserveplätze.');
        $this->assertArrayNotHasKey($this->platz($template, 1, 1)->id, $nummern, 'Das Pult bekommt keine Nummer.');
        $this->assertArrayNotHasKey($this->platz($template, 1, 2)->id, $nummern, 'Ein Gang bekommt keine Nummer.');
        $this->assertArrayNotHasKey($this->platz($template, 3, 1)->id, $nummern, 'Ein blockiertes Feld bekommt keine Nummer.');

    }

    #[Test]
    public function gezaehlt_wird_zeilenweise_von_links_nach_rechts(): void
    {
        $school = $this->createTestSchool();

        $template = $this->layout();
        $nummern = $template->platznummern();

        $this->assertSame(1, $nummern[$this->platz($template, 1, 3)->id]);
        $this->assertSame(2, $nummern[$this->platz($template, 1, 4)->id]);
        $this->assertSame(3, $nummern[$this->platz($template, 2, 1)->id]);
        $this->assertSame(6, $nummern[$this->platz($template, 2, 4)->id]);

    }

    #[Test]
    public function reserveplaetze_kommen_nach_den_regulaeren(): void
    {
        $school = $this->createTestSchool();

        $template = $this->layout();
        $nummern = $template->platznummern();

        // Die Reserveplätze stehen in Reihe 3, also räumlich hinter allen
        // regulären — hier zählt aber die Art, nicht die Reihe: Reserve kommt
        // grundsätzlich zuletzt, auch wenn sie weiter vorn im Raum liegt.
        $this->assertSame(7, $nummern[$this->platz($template, 3, 2)->id]);
        $this->assertSame(8, $nummern[$this->platz($template, 3, 3)->id]);

    }

    #[Test]
    public function eine_zuweisung_ohne_vorlage_liefert_keine_nummern(): void
    {
        $school = $this->createTestSchool();

        $raumOhneVorlage = Room::create(['room_number' => 'ONV', 'room_type' => 'exam', 'is_active' => true]);
        $lehrkraft = Teacher::create(['shortcode' => 'PNT', 'firstname' => 'Test', 'lastname' => 'Nummern']);
        $kurs = Course::create([
            'exam_period_id' => 1,
            'subject_id' => Subject::query()->value('id'),
            'course_code' => 'pn1',
            'teacher_id' => $lehrkraft->id,
            'level' => 'grundlegend',
        ]);

        $zuweisung = WrittenExamAssignment::create([
            'written_exam_year_date_id' => 1,
            'course_id' => $kurs->id,
            'room_id' => $raumOhneVorlage->id,
            'is_hoerverstaendnis' => false,
        ]);

        $this->assertSame([], $zuweisung->platznummern());

    }

    #[Test]
    public function die_zuweisung_reicht_die_nummern_des_raumlayouts_durch(): void
    {
        $school = $this->createTestSchool();

        $template = $this->layout();
        $raum = Room::create(['room_number' => 'MNV', 'room_type' => 'exam', 'is_active' => true]);
        RoomSeatingTemplate::create([
            'room_id' => $raum->id,
            'variant' => RoomSeatingTemplate::VARIANT_STANDARD,
            'seating_template_id' => $template->id,
        ]);

        $lehrkraft = Teacher::create(['shortcode' => 'PN2', 'firstname' => 'Test', 'lastname' => 'Nummern2']);
        $kurs = Course::create([
            'exam_period_id' => 1,
            'subject_id' => Subject::query()->value('id'),
            'course_code' => 'pn2',
            'teacher_id' => $lehrkraft->id,
            'level' => 'grundlegend',
        ]);

        $zuweisung = WrittenExamAssignment::create([
            'written_exam_year_date_id' => 1,
            'course_id' => $kurs->id,
            'room_id' => $raum->id,
            'is_hoerverstaendnis' => false,
        ]);

        $this->assertSame($template->platznummern(), $zuweisung->platznummern());

    }
}
