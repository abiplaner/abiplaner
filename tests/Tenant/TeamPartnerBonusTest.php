<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\OralExamDayRoom;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Services\OptimizationService;
use Carbon\Carbon;

/**
 * Modul 4.5 – Zeitliche Kopplung von Prüfungsteams (auch ohne inhaltliche
 * Kopplung, Abgrenzung zu 4.7/coupling_id).
 *
 * Kriterium (Rücksprache Wilfried, 08.07.2026): Zwei Lehrkräfte gelten als
 * Team-Partner, wenn sie bei mindestens einer noch ungeplanten (nicht
 * fixierten) Kolloquiumsprüfung gemeinsam als Prüfer+Beisitzer auftreten –
 * unabhängig von der Rolle (auch vertauscht) und unabhängig vom Kurs. Der
 * Optimizer bevorzugt dann einen gemeinsamen Tag (Score-Bonus in
 * calculateSlotScore, analog zur Tages-Affinität aus 4.3, aber schwächer).
 * Rein automatische Erkennung, keine manuelle Kopplungs-UI, keine
 * Persistierung.
 *
 * Tests laufen gegen die echte Tenant-DB der Testschule 9999.
 */
class TeamPartnerBonusTest extends TenantTestCase
{
    private int $seq = 0;

    /**
     * @param list<string> $dates
     */
    private function setupPlanningInfrastructure(ExamPeriod $examPeriod, array $dates): Room
    {
        $examRoom = Room::create(['room_number' => 'B45-EXAM', 'room_type' => 'exam', 'is_active' => true]);

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

            for ($slotNumber = 1; $slotNumber <= 6; $slotNumber++) {
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

    private function makeOralExam(ExamPeriod $examPeriod, Teacher $examiner, ?Teacher $assessor = null): StudentExam
    {
        $this->seq++;

        $course = Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'b45k' . $this->seq,
            'teacher_id'     => $examiner->id,
            'level'          => 'grundlegend',
        ]);

        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'B45-' . $this->seq,
            'grade'          => '13',
            'firstname'      => 'Partner',
            'lastname'       => 'Schueler' . $this->seq,
        ]);

        return StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $examiner->id,
            'assessor_id' => $assessor?->id,
            'exam_type'   => 'oral',
        ]);
    }

    /**
     * Kernszenario: X prüft/Y assistiert bei Prüfung A (Kurs 1), Y prüft/X
     * assistiert bei Prüfung B (Kurs 2) – vertauschte Rollen, verschiedene
     * Kurse, keine coupling_id. Ohne Modul 4.5 würde die Day-Load-Penalty
     * (+8/Prüfung) die zweite Prüfung auf den leereren Tag 2 schieben (X und Y
     * sind jeweils nur einmal am eigenen Tag 1 "bekannt", die Tages-Affinität
     * aus 4.3 greift nicht kursübergreifend über verschiedene Prüfer). Mit dem
     * Team-Partner-Bonus sollen beide Prüfungen am selben Tag landen.
     */
    public function test_vertauschte_rollen_werden_als_team_partner_erkannt_und_gebuendelt(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $x = Teacher::create(['shortcode' => 'B45X', 'firstname' => 'Xaver', 'lastname' => 'Partner']);
        $y = Teacher::create(['shortcode' => 'B45Y', 'firstname' => 'Yvonne', 'lastname' => 'Partner']);

        // Prüfung A: X prüft, Y assistiert (Kurs 1)
        $examA = $this->makeOralExam($examPeriod, $x, $y);
        // Prüfung B: Y prüft, X assistiert (Kurs 2, vertauschte Rollen)
        $examB = $this->makeOralExam($examPeriod, $y, $x);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $this->assertCount(2, $result['assignments'], 'Beide Prüfungen müssen geplant sein.');

        $slotA = TimeSlot::findOrFail(
            collect($result['assignments'])->firstWhere('student_exam_id', $examA->id)['time_slot_id']
        );
        $slotB = TimeSlot::findOrFail(
            collect($result['assignments'])->firstWhere('student_exam_id', $examB->id)['time_slot_id']
        );

        $this->assertSame(
            Carbon::parse($slotA->date)->toDateString(),
            Carbon::parse($slotB->date)->toDateString(),
            'Team-Partner-Prüfungen mit vertauschten Rollen müssen am selben Tag liegen.'
        );

    }

    /**
     * Gleiche Rollenkombination (nicht vertauscht): X prüft/Y assistiert bei A
     * UND bei B (anderer Kurs). Zählt laut Rücksprache ebenfalls als
     * Team-Partner-Paar.
     */
    public function test_gleiche_rollenkombination_in_zwei_kursen_wird_ebenfalls_gebuendelt(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $x = Teacher::create(['shortcode' => 'B45P', 'firstname' => 'Paul', 'lastname' => 'Partner']);
        $y = Teacher::create(['shortcode' => 'B45Q', 'firstname' => 'Quirin', 'lastname' => 'Partner']);

        $examA = $this->makeOralExam($examPeriod, $x, $y);
        $examB = $this->makeOralExam($examPeriod, $x, $y);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $slotA = TimeSlot::findOrFail(
            collect($result['assignments'])->firstWhere('student_exam_id', $examA->id)['time_slot_id']
        );
        $slotB = TimeSlot::findOrFail(
            collect($result['assignments'])->firstWhere('student_exam_id', $examB->id)['time_slot_id']
        );

        $this->assertSame(
            Carbon::parse($slotA->date)->toDateString(),
            Carbon::parse($slotB->date)->toDateString(),
            'Gleiches Paar in gleicher Rollenkombination muss ebenfalls am selben Tag liegen.'
        );

    }

    /**
     * Abgrenzung: Eine bereits FIXIERTE Prüfung darf kein Team-Partner-Paar
     * begründen (Rücksprache: Bonus nur, solange beide Prüfungen noch nicht
     * anderweitig fixiert sind – loadTeamPartners() nutzt bewusst
     * whereDoesntHave('assignment')).
     *
     * Aufbau: X ist fixiert an Tag 1 mit Beisitzer Y verplant, dazu füllen
     * fünf weitere fixierte Prüfungen Tag 1 komplett auf (macht Tag 2 über die
     * Day-Load-Penalty klar attraktiver). Eine neue, komplett unbeteiligte
     * Prüfung von Z hat keinerlei Vorgeschichte mit X/Y – ohne Team-Partner-
     * Bonus muss sie daher auf Tag 2 ausweichen.
     */
    public function test_fixierte_pruefung_begruendet_kein_team_partner_paar(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');

        $examRoom = $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $x = Teacher::create(['shortcode' => 'B45F', 'firstname' => 'Fix', 'lastname' => 'Partner']);
        $y = Teacher::create(['shortcode' => 'B45G', 'firstname' => 'Gerd', 'lastname' => 'Partner']);
        $z = Teacher::create(['shortcode' => 'B45Z', 'firstname' => 'Zora', 'lastname' => 'Fremd']);

        // Fixierte Prüfung an Tag 1: X prüft, Y assistiert.
        $lockedExam = $this->makeOralExam($examPeriod, $x, $y);
        $lockedSlot = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->whereDate('date', $day1)->where('slot_number', 1)->firstOrFail();
        ExamAssignment::create([
            'student_exam_id' => $lockedExam->id,
            'exam_week'       => 1,
            'time_slot_id'    => $lockedSlot->id,
            'exam_room_id'    => $examRoom->id,
            'prep_room_id'    => $examRoom->id,
            'status'          => 'planned',
            'is_locked'       => true,
        ]);

        // Fünf weitere, thematisch unbeteiligte fixierte Prüfungen füllen
        // Tag 1 auf, damit Tag 2 für eine NEUE, unverbundene Prüfung über die
        // Day-Load-Penalty klar attraktiver ist (kein Team-Partner-Bonus →
        // Day-Load entscheidet).
        $filler = Teacher::create(['shortcode' => 'B45H', 'firstname' => 'Hugo', 'lastname' => 'Fueller']);
        for ($i = 0; $i < 5; $i++) {
            $fillExam = $this->makeOralExam($examPeriod, $filler);
            $fillSlot = TimeSlot::where('exam_period_id', $examPeriod->id)
                ->whereDate('date', $day1)->where('slot_number', $i + 2)->firstOrFail();
            ExamAssignment::create([
                'student_exam_id' => $fillExam->id,
                'exam_week'       => 1,
                'time_slot_id'    => $fillSlot->id,
                'exam_room_id'    => $examRoom->id,
                'prep_room_id'    => $examRoom->id,
                'status'          => 'planned',
                'is_locked'       => true,
            ]);
        }

        // Neue, ungeplante Prüfung von Z – völlig unbeteiligt an X/Y, hat
        // selbst keinerlei Vorgeschichte an Tag 1.
        $newExam = $this->makeOralExam($examPeriod, $z);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $newAssignment = collect($result['assignments'])->firstWhere('student_exam_id', $newExam->id);
        $this->assertNotNull($newAssignment, 'Die neue Prüfung muss geplant sein.');

        $newSlot = TimeSlot::findOrFail($newAssignment['time_slot_id']);
        $this->assertSame(
            $day2,
            Carbon::parse($newSlot->date)->toDateString(),
            'Ohne Team-Partner-Bezug muss die unbeteiligte Prüfung auf den leereren Tag 2 ausweichen.'
        );

    }
}
