<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\OralExamDayRoom;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentBlockedDay;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Services\OptimizationService;
use Carbon\Carbon;

/**
 * Modul 4.3 – Prüfungstage pro Lehrkraft bündeln.
 *
 * Hintergrund: Lehrer haben sich beschwert, dass ihre Kolloquien über viele
 * Tage verstreut waren. Die Tages-Affinität in calculateSlotScore, die
 * B1-Blocksortierung, die Wochen-Kohärenz und der Kompaktierungs-Pass
 * (Phase 9.5) sollen Prüfungen derselben Lehrkraft auf möglichst wenige Tage
 * (und dort möglichst am Stück) legen.
 *
 * Tests laufen gegen die echte Tenant-DB der Testschule 9999. Der
 * OptimizationService wird direkt aufgerufen (optimize() schreibt nicht in die
 * DB, Assertions laufen auf dem zurückgegebenen Schedule-Array) – nur für den
 * Fixierungs-Test wird ein echtes ExamAssignment angelegt.
 */
class TeacherDayBundlingTest extends TenantTestCase
{
    private int $seq = 0;

    /**
     * Legt Räume, Zeitslots (6 je Tag) und die OralExamDayRoom-Konfiguration
     * für die übergebenen Tage an. Gibt den Prüfungsraum zurück.
     *
     * @param list<string> $dates
     */
    private function setupPlanningInfrastructure(ExamPeriod $examPeriod, array $dates): Room
    {
        $examRoom = Room::create(['room_number' => 'B43-EXAM', 'room_type' => 'exam', 'is_active' => true]);

        // Globaler Default-Prüfungsraum (Sentinel-Datum) → loadRooms()
        OralExamDayRoom::create([
            'exam_period_id' => $examPeriod->id,
            'date'           => '1900-01-01',
            'room_id'        => $examRoom->id,
            'room_role'      => 'exam',
        ]);

        foreach ($dates as $date) {
            // Tagesspezifischer Eintrag → whitelistet den Tag in loadTimeSlots()
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

    /**
     * Legt eine mündliche Prüfung (eigener Kurs, eigener Schüler) für den
     * übergebenen Prüfer an.
     */
    private function makeOralExam(ExamPeriod $examPeriod, Teacher $examiner, ?Teacher $assessor = null): StudentExam
    {
        $this->seq++;

        $course = Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'b43k' . $this->seq,
            'teacher_id'     => $examiner->id,
            'level'          => 'grundlegend',
        ]);

        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'B43-' . $this->seq,
            'grade'          => '13',
            'firstname'      => 'Bündel',
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
     * Ohne Tages-Affinität würde die Day-Load-Penalty (+8 je Prüfung am Tag)
     * die zweite und dritte Prüfung desselben Prüfers auf den jeweils leereren
     * Tag schieben. Mit Modul 4.3 landen alle drei Prüfungen (drei verschiedene
     * Kurse → drei getrennte Prüferblöcke) am selben Tag und am Stück.
     */
    public function test_pruefungen_desselben_pruefers_aus_verschiedenen_kursen_landen_am_selben_tag_am_stueck(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $examiner = Teacher::create(['shortcode' => 'B43A', 'firstname' => 'Bündel', 'lastname' => 'Prüfer']);
        $this->makeOralExam($examPeriod, $examiner);
        $this->makeOralExam($examPeriod, $examiner);
        $this->makeOralExam($examPeriod, $examiner);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $this->assertCount(3, $result['assignments'], 'Alle drei Prüfungen müssen geplant sein.');

        $slots = TimeSlot::whereIn('id', array_column($result['assignments'], 'time_slot_id'))->get();
        $dates = $slots->map(fn($s) => Carbon::parse($s->date)->toDateString())->unique();
        $this->assertCount(1, $dates, 'Alle Prüfungen des Prüfers müssen am selben Tag liegen.');

        $slotNumbers = $slots->pluck('slot_number')->sort()->values()->all();
        $this->assertSame(range($slotNumbers[0], $slotNumbers[0] + 2), $slotNumbers,
            'Die Prüfungen müssen in aufeinanderfolgenden Slots ("am Stück") liegen.');

    }

    /**
     * Die Tages-Affinität berücksichtigt auch Beisitzer-Einsätze: Hat eine
     * Lehrkraft bereits einen (fixierten) Beisitzertermin an Tag 2, soll ihre
     * eigene Prüfung ebenfalls auf Tag 2 gelegt werden – obwohl Tag 1 nach
     * Day-Load-Penalty günstiger wäre.
     */
    public function test_beisitzer_einsatz_zieht_eigene_pruefung_auf_denselben_tag(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');

        $examRoom = $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $teacher = Teacher::create(['shortcode' => 'B43B', 'firstname' => 'Beisitz', 'lastname' => 'Prüfer']);
        $otherExaminer = Teacher::create(['shortcode' => 'B43C', 'firstname' => 'Fremd', 'lastname' => 'Prüfer']);

        // Fixierte Prüfung an Tag 2: $teacher ist dort Beisitzer
        $lockedExam = $this->makeOralExam($examPeriod, $otherExaminer, $teacher);
        $lockedSlot = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->whereDate('date', $day2)->where('slot_number', 1)->firstOrFail();
        ExamAssignment::create([
            'student_exam_id' => $lockedExam->id,
            'exam_week'       => 1,
            'time_slot_id'    => $lockedSlot->id,
            'exam_room_id'    => $examRoom->id,
            'prep_room_id'    => $examRoom->id,
            'status'          => 'planned',
            'is_locked'       => true,
        ]);

        // Eigene Prüfung von $teacher (als Prüfer)
        $ownExam = $this->makeOralExam($examPeriod, $teacher);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $ownAssignment = collect($result['assignments'])->firstWhere('student_exam_id', $ownExam->id);
        $this->assertNotNull($ownAssignment, 'Die eigene Prüfung muss geplant sein.');

        $ownSlot = TimeSlot::findOrFail($ownAssignment['time_slot_id']);
        $this->assertSame($day2, Carbon::parse($ownSlot->date)->toDateString(),
            'Die eigene Prüfung muss am Tag des Beisitzertermins liegen.');

    }

    /**
     * Kompaktierungs-Pass (Phase 9.5): Wird eine Prüfung wegen eines
     * Schüler-Sperrtags auf einen anderen Tag gezwungen, soll der Post-Pass
     * die übrige Prüfung der Lehrkraft nachziehen, sodass am Ende beide am
     * selben Tag liegen.
     */
    public function test_kompaktierung_zieht_verstreute_pruefung_auf_den_starken_tag_nach(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $examiner = Teacher::create(['shortcode' => 'B43D', 'firstname' => 'Kompakt', 'lastname' => 'Prüfer']);
        $examA = $this->makeOralExam($examPeriod, $examiner);
        $examB = $this->makeOralExam($examPeriod, $examiner);

        // Schüler von Prüfung B sperrt Tag 1 → B kann nur an Tag 2 liegen
        StudentBlockedDay::create([
            'student_id' => $examB->student_id,
            'date'       => $day1,
            'reason'     => 'Testsperre Modul 4.3',
        ]);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $this->assertCount(2, $result['assignments'], 'Beide Prüfungen müssen geplant sein.');

        $slots = TimeSlot::whereIn('id', array_column($result['assignments'], 'time_slot_id'))->get();
        $dates = $slots->map(fn($s) => Carbon::parse($s->date)->toDateString())->unique()->values();

        $this->assertCount(1, $dates, 'Nach der Kompaktierung müssen beide Prüfungen am selben Tag liegen.');
        $this->assertSame($day2, $dates->first(),
            'Der gemeinsame Tag muss Tag 2 sein (Tag 1 ist für einen Schüler gesperrt).');

    }

    /**
     * Abschaltbarkeit: Mit bundle_teacher_days=false läuft die Planung ohne
     * Tages-Affinität durch (Smoke-Test – altes Verhalten bleibt wählbar).
     */
    public function test_planung_funktioniert_auch_mit_deaktivierter_buendelung(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = $this->leererPruefungszeitraum();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $examiner = Teacher::create(['shortcode' => 'B43E', 'firstname' => 'Alt', 'lastname' => 'Prüfer']);
        $this->makeOralExam($examPeriod, $examiner);
        $this->makeOralExam($examPeriod, $examiner);
        $this->makeOralExam($examPeriod, $examiner);

        $result = app(OptimizationService::class)->optimize($examPeriod, [
            'school_id'           => 1,
            'bundle_teacher_days' => false,
        ]);

        $this->assertCount(3, $result['assignments'], 'Alle Prüfungen müssen auch ohne Bündelung geplant sein.');

    }
}
