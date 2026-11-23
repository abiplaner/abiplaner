<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\OralExamDayRoom;
use App\Models\Room;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Services\OptimizationService;
use Carbon\Carbon;

/**
 * Die Auto-Planung und der externe Bewerber mit vier muendlichen Pruefungen.
 *
 * Der Befund (30.08.2026): Die Wochenzuweisung hielt in
 * `studentWeekAssignment[$schluessel][$woche]` genau *eine* Pruefung je Woche
 * fest. Fuer einen regulaeren Schueler mit zwei Kolloquien ist das die Regel;
 * fuer einen externen Bewerber mit vier muendlichen Pruefungen nach § 61
 * Abs. 3 war es eine Obergrenze: Die dritte und vierte Pruefung fielen mit der
 * Warnung "beide Wochen bereits belegt" aus der Planung. Bemerkt hat das
 * niemand, weil die betroffenen Bewerber von Hand verplant wurden.
 *
 * An die Stelle der Wochenregel tritt fuer sie die Tagesregel C9
 * (§ 61 Abs. 3 S. 9): hoechstens eine muendliche Pruefung an einem Tag.
 *
 * optimize() schreibt nicht in die DB; die Zusicherungen laufen auf dem
 * zurueckgegebenen Schedule-Array.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter AutoplanungExternerBewerber
 */
class AutoplanungExternerBewerberTest extends TenantTestCase
{
    /**
     * Legt Raum, Tages-Whitelist und Zeitslots an. Gibt den Pruefungsraum
     * zurueck. Nachgebaut aus TeacherDayBundlingTest — dieselbe Infrastruktur
     * braucht jede Optimierer-Probe.
     *
     * @param list<string> $dates
     */
    private function infrastruktur(ExamPeriod $examPeriod, array $dates, int $slotsProTag = 6): Room
    {
        $examRoom = Room::create(['room_number' => 'EXT-EXAM', 'room_type' => 'exam', 'is_active' => true]);

        OralExamDayRoom::create([
            'exam_period_id' => $examPeriod->id,
            'date'           => '1900-01-01',
            'room_id'        => $examRoom->id,
            'room_role'      => 'exam',
        ]);

        foreach ($dates as $date) {
            OralExamDayRoom::create([
                'exam_period_id' => $examPeriod->id,
                'date'           => $date,
                'room_id'        => $examRoom->id,
                'room_role'      => 'exam',
            ]);

            for ($slotNumber = 1; $slotNumber <= $slotsProTag; $slotNumber++) {
                $prepStart = Carbon::parse($date . ' 08:00')->addMinutes(($slotNumber - 1) * 60);
                TimeSlot::create([
                    'exam_period_id'    => $examPeriod->id,
                    'date'              => $date,
                    'slot_number'       => $slotNumber,
                    'prep_start_time'   => $prepStart->format('H:i:s'),
                    'prep_end_time'     => $prepStart->copy()->addMinutes(30)->format('H:i:s'),
                    'exam_start_time'   => $prepStart->copy()->addMinutes(30)->format('H:i:s'),
                    'exam_end_time'     => $prepStart->copy()->addMinutes(60)->format('H:i:s'),
                    'preference_weight' => 100,
                    'is_active'         => true,
                ]);
            }
        }

        return $examRoom;
    }

    /**
     * Ein externer Bewerber mit vier muendlichen Pruefungen, je eigener
     * Pruefer (sonst faende die Blockbildung sie zusammen).
     *
     * @param  list<string> $faecher
     * @return list<StudentExam>
     */
    private function bewerberMitPruefungen(ExamPeriod $examPeriod, string $nachname, array $faecher): array
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => $nachname]);

        $pruefungen = [];
        foreach ($faecher as $nummer => $fach) {
            $fachId = Subject::where('name', $fach)->value('id');
            $this->assertNotNull($fachId, "Fach {$fach} fehlt im Katalog.");

            $pruefungsfach = ExternalExamSubject::create([
                'external_applicant_id' => $bewerber->id,
                'subject_id'            => $fachId,
                'exam_part'             => 'part2_oral',
                'subject_number'        => $nummer + 1,
            ]);

            $pruefer = Teacher::create([
                'shortcode' => 'AE' . substr($nachname, 0, 1) . $nummer,
                'firstname' => 'Auto',
                'lastname'  => 'Pruefer' . $nummer,
            ]);

            $pruefungen[] = StudentExam::create([
                'external_exam_subject_id' => $pruefungsfach->id,
                'exam_type'                => 'oral',
                'examiner_id'              => $pruefer->id,
            ]);
        }

        return $pruefungen;
    }

    /**
     * Der eigentliche Befund: Vorher kamen zwei der vier Pruefungen an, weil
     * die Wochenzuweisung nach der ersten Pruefung je Woche dichtmachte.
     * Jetzt muessen alle vier stehen.
     */
    public function test_alle_vier_pruefungen_eines_externen_werden_geplant(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $tage = [
            $examPeriod->week_1_start->format('Y-m-d'),
            $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d'),
            $examPeriod->week_1_start->copy()->addDays(2)->format('Y-m-d'),
            $examPeriod->week_1_start->copy()->addDays(3)->format('Y-m-d'),
        ];
        $this->infrastruktur($examPeriod, $tage);

        $pruefungen = $this->bewerberMitPruefungen(
            $examPeriod,
            'Vierfach',
            ['Physik', 'Geschichte', 'Englisch', 'Biologie']
        );

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $geplanteIds = array_column($result['assignments'], 'student_exam_id');
        foreach ($pruefungen as $pruefung) {
            $this->assertContains(
                $pruefung->id,
                $geplanteIds,
                "Pruefung {$pruefung->id} ({$pruefung->getSubjectLabel()}) wurde nicht geplant."
            );
        }

    }

    /**
     * Und sie liegen an vier verschiedenen Tagen — § 61 Abs. 3 S. 9.
     *
     * Diese Zusicherung ist die haertere: Die Pruefung darueber waere auch
     * gruen, wenn der Optimierer alle vier auf denselben Tag legte.
     */
    public function test_die_vier_pruefungen_liegen_an_verschiedenen_tagen(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $tage = [
            $examPeriod->week_1_start->format('Y-m-d'),
            $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d'),
            $examPeriod->week_1_start->copy()->addDays(2)->format('Y-m-d'),
            $examPeriod->week_1_start->copy()->addDays(3)->format('Y-m-d'),
        ];
        $this->infrastruktur($examPeriod, $tage);

        $pruefungen = $this->bewerberMitPruefungen(
            $examPeriod,
            'Tagestreu',
            ['Physik', 'Geschichte', 'Englisch', 'Biologie']
        );
        $ids = array_map(static fn (StudentExam $e): int => $e->id, $pruefungen);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $slotIds = [];
        foreach ($result['assignments'] as $zuweisung) {
            if (in_array($zuweisung['student_exam_id'], $ids, true)) {
                $slotIds[] = $zuweisung['time_slot_id'];
            }
        }

        $this->assertCount(4, $slotIds, 'Alle vier Pruefungen muessen einen Termin haben.');

        $tageDerPruefungen = TimeSlot::whereIn('id', $slotIds)
            ->get()
            ->map(static fn (TimeSlot $s): string => Carbon::parse($s->date)->toDateString())
            ->unique()
            ->values();

        $this->assertCount(
            4,
            $tageDerPruefungen,
            'Nach § 61 Abs. 3 S. 9 darf hoechstens eine muendliche Pruefung auf einen Tag fallen; '
                . 'gefunden: ' . $tageDerPruefungen->implode(', ')
        );

    }

    /**
     * Gegenprobe zur Tagesregel: Stehen nur zwei Tage zur Verfuegung, sind
     * vier Pruefungen an einem Bewerber nicht unterzubringen. Der Optimierer
     * darf sie dann nicht doch stapeln — er meldet stattdessen, was offen
     * bleibt.
     *
     * Ohne diese Probe koennte die Tagesregel unwirksam sein und beide Tests
     * darueber blieben gruen, weil dort ohnehin genug Tage da sind.
     */
    public function test_bei_zu_wenigen_tagen_bleiben_pruefungen_offen_statt_sich_zu_stapeln(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $tage = [
            $examPeriod->week_1_start->format('Y-m-d'),
            $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d'),
        ];
        $this->infrastruktur($examPeriod, $tage);

        $pruefungen = $this->bewerberMitPruefungen(
            $examPeriod,
            'Engpass',
            ['Physik', 'Geschichte', 'Englisch', 'Biologie']
        );
        $ids = array_map(static fn (StudentExam $e): int => $e->id, $pruefungen);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $slotIds = [];
        foreach ($result['assignments'] as $zuweisung) {
            if (in_array($zuweisung['student_exam_id'], $ids, true)) {
                $slotIds[] = $zuweisung['time_slot_id'];
            }
        }

        $this->assertLessThanOrEqual(
            2,
            count($slotIds),
            'Bei zwei Tagen duerfen hoechstens zwei der vier Pruefungen einen Termin bekommen.'
        );

        $tageDerPruefungen = TimeSlot::whereIn('id', $slotIds)
            ->get()
            ->map(static fn (TimeSlot $s): string => Carbon::parse($s->date)->toDateString())
            ->unique();

        $this->assertCount(
            count($slotIds),
            $tageDerPruefungen,
            'Auch im Engpass darf kein Tag zwei Pruefungen desselben Bewerbers tragen.'
        );

    }

    /**
     * Die Tagesregel gilt je Bewerber. Zwei Bewerber duerfen sich einen Tag
     * teilen — sonst waere die Sperre viel zu weit gefasst und ein Jahrgang
     * mit mehreren Externen liesse sich gar nicht mehr planen.
     */
    public function test_zwei_bewerber_teilen_sich_einen_tag(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $tag = $examPeriod->week_1_start->format('Y-m-d');
        $this->infrastruktur($examPeriod, [$tag]);

        $einer   = $this->bewerberMitPruefungen($examPeriod, 'Einer', ['Physik']);
        $anderer = $this->bewerberMitPruefungen($examPeriod, 'Zweiter', ['Geschichte']);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);
        $geplanteIds = array_column($result['assignments'], 'student_exam_id');

        $this->assertContains($einer[0]->id, $geplanteIds);
        $this->assertContains($anderer[0]->id, $geplanteIds);

    }
}
