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
use App\Models\TimetableEntry;
use App\Services\OptimizationService;
use Carbon\Carbon;

/**
 * Modul 4.3, Teilpunkt "freie Tage" – Kolloquien möglichst nicht an
 * stundenplanfreien Wochentagen einer Lehrkraft einplanen.
 *
 * Datenquelle sind die importierten Stundenpläne (TimetableEntry, siehe
 * /teachers/{id}/timetable). calculateSlotScore vergibt +120 Penalty für
 * Prüfer UND Beisitzer (gleiche Höhe), wenn der Slot-Tag auf einen
 * stundenplanfreien Wochentag fällt – als weiche Präferenz unterhalb der
 * Tages-Affinität (−1000/−300). Lehrkräfte ohne Stundenplandaten sind
 * ausgenommen ("keine Daten" ≠ "ganze Woche frei").
 *
 * Tests laufen gegen die echte Tenant-DB der Testschule 9999, analog zu
 * TeacherDayBundlingTest. optimize() schreibt nicht in die DB, Assertions
 * laufen auf dem zurückgegebenen Schedule-Array.
 */
class TeacherFreeDayTest extends TenantTestCase
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
        $examRoom = Room::create(['room_number' => 'F43-EXAM', 'room_type' => 'exam', 'is_active' => true]);

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
            'course_code'    => 'f43k' . $this->seq,
            'teacher_id'     => $examiner->id,
            'level'          => 'grundlegend',
        ]);

        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'F43-' . $this->seq,
            'grade'          => '13',
            'firstname'      => 'Frei',
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
     * Hinterlegt Stundenplaneinträge für die übergebenen Wochentage (1=Mo…5=Fr)
     * im Schuljahr, das loadTeacherFreeWeekdays aus dem Prüfungszeitraum
     * ableitet. Alle anderen Wochentage gelten damit als stundenplanfrei.
     *
     * @param list<int> $busyWeekdays
     */
    private function giveTimetable(Teacher $teacher, ExamPeriod $examPeriod, array $busyWeekdays): void
    {
        $schoolYear = TimetableEntry::schoolYearForDate(Carbon::parse($examPeriod->week_1_start));

        foreach ($busyWeekdays as $weekday) {
            TimetableEntry::create([
                'teacher_id'  => $teacher->id,
                'class_name'  => 'Q12',
                'day_of_week' => $weekday,
                'period'      => 1,
                'school_year' => $schoolYear,
            ]);
        }
    }

    /**
     * Kernfall: Der Prüfer hat nur am Wochentag von Tag 2 Unterricht, Tag 1
     * ist stundenplanfrei. Ohne Penalty würde die Prüfung auf Tag 1 fallen
     * (frühester Slot bei gleichem preference_weight, Slots sind nach
     * Datum/Slot-Nummer sortiert). Mit der Penalty (+120 auf Tag 1) weicht
     * sie auf den Unterrichtstag aus.
     */
    public function test_pruefung_meidet_stundenplanfreien_tag(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');
        $weekday2 = Carbon::parse($day2)->isoWeekday();

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $examiner = Teacher::create(['shortcode' => 'F43A', 'firstname' => 'Frei', 'lastname' => 'Prüfer']);
        $this->giveTimetable($examiner, $examPeriod, [$weekday2]);
        $exam = $this->makeOralExam($examPeriod, $examiner);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $assignment = collect($result['assignments'])->firstWhere('student_exam_id', $exam->id);
        $this->assertNotNull($assignment, 'Die Prüfung muss geplant sein.');

        $slot = TimeSlot::findOrFail($assignment['time_slot_id']);
        $this->assertSame($day2, Carbon::parse($slot->date)->toDateString(),
            'Die Prüfung muss auf den Unterrichtstag (Tag 2) ausweichen – Tag 1 ist stundenplanfrei.');

    }

    /**
     * Fallback-Sicherheit: Für Lehrkräfte OHNE Stundenplandaten greift keine
     * Penalty – "keine Daten" darf nicht als "ganze Woche frei" gelten.
     * Eine andere Lehrkraft hat Daten im selben Schuljahr, damit der Fall
     * "Tabelle nicht leer, aber diese Lehrkraft fehlt" getestet wird.
     */
    public function test_ohne_stundenplandaten_greift_keine_penalty(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');
        $weekday2 = Carbon::parse($day2)->isoWeekday();

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $other = Teacher::create(['shortcode' => 'F43X', 'firstname' => 'Mit', 'lastname' => 'Daten']);
        $this->giveTimetable($other, $examPeriod, [$weekday2]);

        $examiner = Teacher::create(['shortcode' => 'F43B', 'firstname' => 'Ohne', 'lastname' => 'Daten']);
        $exam = $this->makeOralExam($examPeriod, $examiner);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $assignment = collect($result['assignments'])->firstWhere('student_exam_id', $exam->id);
        $this->assertNotNull($assignment, 'Die Prüfung muss geplant sein.');

        $slot = TimeSlot::findOrFail($assignment['time_slot_id']);
        $this->assertSame($day1, Carbon::parse($slot->date)->toDateString(),
            'Ohne Stundenplandaten darf keine Penalty greifen – die Prüfung bleibt auf dem frühesten Tag.');

    }

    /**
     * Weiche Präferenz, kein hartes Verbot: Ist nur der stundenplanfreie Tag
     * verfügbar, wird die Prüfung trotzdem dort eingeplant (Lehrkräfte haben
     * während des Kolloquiums kein Anrecht auf freie Tage – Dienstpflicht).
     */
    public function test_freier_tag_wird_belegt_wenn_keine_alternative(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $weekday1 = Carbon::parse($day1)->isoWeekday();
        // Unterricht an einem ANDEREN Wochentag → Tag 1 ist stundenplanfrei
        $busyWeekday = $weekday1 === 1 ? 2 : 1;

        $this->setupPlanningInfrastructure($examPeriod, [$day1]);

        $examiner = Teacher::create(['shortcode' => 'F43C', 'firstname' => 'Muss', 'lastname' => 'Kommen']);
        $this->giveTimetable($examiner, $examPeriod, [$busyWeekday]);
        $exam = $this->makeOralExam($examPeriod, $examiner);

        $result = app(OptimizationService::class)->optimize($examPeriod, ['school_id' => 1]);

        $assignment = collect($result['assignments'])->firstWhere('student_exam_id', $exam->id);
        $this->assertNotNull($assignment, 'Die Prüfung muss trotz stundenplanfreiem Tag geplant sein (weiche Präferenz).');

        $slot = TimeSlot::findOrFail($assignment['time_slot_id']);
        $this->assertSame($day1, Carbon::parse($slot->date)->toDateString());

    }

    /**
     * Bündelung dominiert: Hat die Lehrkraft an ihrem stundenplanfreien Tag
     * bereits einen (fixierten) Beisitzertermin, wird ihre eigene Prüfung
     * ebenfalls dorthin gelegt – sie kommt dann sowieso, und die
     * Tages-Affinität (−1000) schlägt die Free-Day-Penalty (die in diesem
     * Fall zudem entfällt, weil die Lehrkraft dort schon eingeplant ist).
     */
    public function test_buendelung_dominiert_freien_tag(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');
        $weekday1 = Carbon::parse($day1)->isoWeekday();

        $examRoom = $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        // Unterricht nur am Wochentag von Tag 1 → Tag 2 ist stundenplanfrei
        $teacher = Teacher::create(['shortcode' => 'F43D', 'firstname' => 'Bündel', 'lastname' => 'Trotzdem']);
        $this->giveTimetable($teacher, $examPeriod, [$weekday1]);

        $otherExaminer = Teacher::create(['shortcode' => 'F43E', 'firstname' => 'Fremd', 'lastname' => 'Prüfer']);

        // Fixierte Prüfung an Tag 2 (dem freien Tag): $teacher ist dort Beisitzer
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
            'Die eigene Prüfung muss trotz stundenplanfreiem Tag 2 dorthin gebündelt werden (Beisitzertermin).');

    }

    /**
     * Abschaltbarkeit: Mit respect_free_days=false greift die Penalty nicht –
     * gleiche Ausgangslage wie im Kernfall, aber die Prüfung bleibt auf dem
     * frühesten (stundenplanfreien) Tag.
     */
    public function test_deaktivierte_option_ignoriert_freie_tage(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $day1 = $examPeriod->week_1_start->format('Y-m-d');
        $day2 = $examPeriod->week_1_start->copy()->addDay()->format('Y-m-d');
        $weekday2 = Carbon::parse($day2)->isoWeekday();

        $this->setupPlanningInfrastructure($examPeriod, [$day1, $day2]);

        $examiner = Teacher::create(['shortcode' => 'F43F', 'firstname' => 'Aus', 'lastname' => 'Geschaltet']);
        $this->giveTimetable($examiner, $examPeriod, [$weekday2]);
        $exam = $this->makeOralExam($examPeriod, $examiner);

        $result = app(OptimizationService::class)->optimize($examPeriod, [
            'school_id'         => 1,
            'respect_free_days' => false,
        ]);

        $assignment = collect($result['assignments'])->firstWhere('student_exam_id', $exam->id);
        $this->assertNotNull($assignment, 'Die Prüfung muss geplant sein.');

        $slot = TimeSlot::findOrFail($assignment['time_slot_id']);
        $this->assertSame($day1, Carbon::parse($slot->date)->toDateString(),
            'Mit deaktivierter Option darf keine Penalty greifen – die Prüfung bleibt auf dem frühesten Tag.');

    }
}
