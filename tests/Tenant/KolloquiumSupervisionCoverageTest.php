<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\OralExamDayRoom;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Supervision;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Services\KolloquiumSupervisionCoverageService;

/**
 * Modul 4.4 – Aufsichten-Kontrolle für Kolloquien: Aufsichten hängen direkt an
 * den echten Prüfungsslots (App\Models\TimeSlot) – es gibt kein eigenes
 * Zeitraster. Je Prüfungsslot mit aktiver Prüfung ist eine Aufsicht für Gang
 * und genutzten Vorbereitungsraum nötig.
 *
 * Warteraum nur bei Kopplungen (analog zu den hochgestellten Wartezeit-Ziffern
 * im Kolloquiumsplan-Export) – überlappt die Wartespanne mehrere Prüfungsslots,
 * wird jeder überlappende Slot markiert; der eigene Slot des wartenden
 * Prüflings selbst nie. Tests laufen gegen die echte Tenant-DB der Testschule
 * 9999.
 */
class KolloquiumSupervisionCoverageTest extends TenantTestCase
{
    private int $studentSeq = 0;

    private function makeAssignment(
        ExamPeriod $examPeriod,
        string $date,
        Room $examRoom,
        Room $prepRoom,
        string $prepStart,
        string $prepEnd,
        string $examStart,
        string $examEnd,
        ?string $couplingId = null
    ): ExamAssignment {
        $this->studentSeq++;

        $timeSlot = TimeSlot::create([
            'exam_period_id'    => $examPeriod->id,
            'date'              => $date,
            'slot_number'       => $this->studentSeq,
            'prep_start_time'   => $prepStart,
            'prep_end_time'     => $prepEnd,
            'exam_start_time'   => $examStart,
            'exam_end_time'     => $examEnd,
            'preference_weight' => 100,
            'is_active'         => true,
        ]);

        $teacher = Teacher::create(['shortcode' => 'C' . $this->studentSeq, 'firstname' => 'Cov', 'lastname' => 'Erage' . $this->studentSeq]);
        $course = Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'covtest' . $this->studentSeq,
            'teacher_id'     => $teacher->id,
            'level'          => 'grundlegend',
        ]);
        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'COV-' . $this->studentSeq,
            'grade'          => '13',
            'firstname'      => 'Test',
            'lastname'       => 'Schueler' . $this->studentSeq,
        ]);
        $studentExam = StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $teacher->id,
            'exam_type'   => 'oral',
            'coupling_id' => $couplingId,
        ]);

        return ExamAssignment::create([
            'student_exam_id' => $studentExam->id,
            'exam_week'       => 1,
            'time_slot_id'    => $timeSlot->id,
            'exam_room_id'    => $examRoom->id,
            'prep_room_id'    => $prepRoom->id,
            'status'          => 'planned',
        ]);
    }

    public function test_je_pruefungsslot_braucht_es_gang_und_vorbereitungsraum_aufsicht(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $date       = $examPeriod->week_1_start->format('Y-m-d');

        $examRoom = Room::create(['room_number' => 'COV-EXAM', 'room_type' => 'exam', 'is_active' => true]);
        $prepRoom = Room::create(['room_number' => 'COV-PREP', 'room_type' => 'exam', 'is_active' => true]);
        $corridor = Room::create(['room_number' => 'COV-GANG', 'room_type' => 'exam', 'is_active' => true]);

        OralExamDayRoom::create(['exam_period_id' => $examPeriod->id, 'date' => $date, 'room_id' => $prepRoom->id, 'room_role' => 'preparation']);
        OralExamDayRoom::create(['exam_period_id' => $examPeriod->id, 'date' => $date, 'room_id' => $corridor->id, 'room_role' => 'corridor']);

        $assignment = $this->makeAssignment($examPeriod, $date, $examRoom, $prepRoom, '13:00:00', '13:20:00', '13:20:00', '13:40:00');

        $coverage = (new KolloquiumSupervisionCoverageService())->compute($examPeriod);

        $this->assertSame(2, $coverage['uncoveredCount'], 'Gang + Vorbereitungsraum fehlen für diesen Slot.');
        $this->assertTrue($coverage['missingCellKeys'][$assignment->time_slot_id . '_' . $prepRoom->id . '_preparation'] ?? false);
        $this->assertTrue($coverage['missingCellKeys'][$assignment->time_slot_id . '_' . $corridor->id . '_corridor'] ?? false);

    }

    public function test_ohne_kopplung_entsteht_kein_warteraum_bedarf(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $date       = $examPeriod->week_1_start->format('Y-m-d');

        $examRoom = Room::create(['room_number' => 'COV2-EXAM', 'room_type' => 'exam', 'is_active' => true]);
        $prepRoom = Room::create(['room_number' => 'COV2-PREP', 'room_type' => 'exam', 'is_active' => true]);
        $waitRoom = Room::create(['room_number' => 'COV2-WAIT', 'room_type' => 'exam', 'is_active' => true]);

        OralExamDayRoom::create(['exam_period_id' => $examPeriod->id, 'date' => $date, 'room_id' => $waitRoom->id, 'room_role' => 'waiting']);

        $this->makeAssignment($examPeriod, $date, $examRoom, $prepRoom, '13:00:00', '13:20:00', '13:20:00', '13:40:00');

        $coverage = (new KolloquiumSupervisionCoverageService())->compute($examPeriod);

        $waitingNeeds = collect(array_keys($coverage['neededCellKeys']))->filter(fn ($k) => str_ends_with($k, '_waiting'));
        $this->assertCount(0, $waitingNeeds, 'Ohne Kopplung darf kein Warteraum-Bedarf entstehen.');

    }

    public function test_warteraum_bedarf_ueberlappt_spaetere_slots_nicht_den_eigenen(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $date       = $examPeriod->week_1_start->format('Y-m-d');

        $examRoom = Room::create(['room_number' => 'COV3-EXAM', 'room_type' => 'exam', 'is_active' => true]);
        $prepRoom = Room::create(['room_number' => 'COV3-PREP', 'room_type' => 'exam', 'is_active' => true]);
        $waitRoom = Room::create(['room_number' => 'COV3-WAIT', 'room_type' => 'exam', 'is_active' => true]);

        OralExamDayRoom::create(['exam_period_id' => $examPeriod->id, 'date' => $date, 'room_id' => $waitRoom->id, 'room_role' => 'waiting']);

        $couplingId = 'coupling-test-1';

        // A: Teil der Kopplung, endet um 13:35 (früh fertig).
        $assignmentA = $this->makeAssignment($examPeriod, $date, $examRoom, $prepRoom, '13:00:00', '13:20:00', '13:20:00', '13:35:00', $couplingId);
        // B: NICHT gekoppelt, aber zeitlich dazwischen (13:30–14:10) – überlappt A's Wartespanne.
        $assignmentB = $this->makeAssignment($examPeriod, $date, $examRoom, $prepRoom, '13:30:00', '13:50:00', '13:50:00', '14:10:00');
        // C: Teil der Kopplung, letzter der Gruppe – Vorbereitung beginnt um 14:00 (Zielzeitpunkt für A).
        $assignmentC = $this->makeAssignment($examPeriod, $date, $examRoom, $prepRoom, '14:00:00', '14:20:00', '14:20:00', '14:40:00', $couplingId);

        $coverage = (new KolloquiumSupervisionCoverageService())->compute($examPeriod);
        $needed   = $coverage['neededCellKeys'];

        $this->assertArrayNotHasKey(
            $assignmentA->time_slot_id . '_' . $waitRoom->id . '_waiting',
            $needed,
            'A wartet erst NACH dem eigenen Prüfungsende, nicht während des eigenen Slots.'
        );
        $this->assertTrue(
            $needed[$assignmentB->time_slot_id . '_' . $waitRoom->id . '_waiting'] ?? false,
            'B\'s Slot (13:30–14:10) überlappt A\'s Wartespanne (13:35–14:00) und muss daher Warteraum-Bedarf zeigen, obwohl B selbst nicht gekoppelt ist.'
        );
        $this->assertArrayNotHasKey(
            $assignmentC->time_slot_id . '_' . $waitRoom->id . '_waiting',
            $needed,
            'C ist der letzte der Gruppe (Ziel-Zeitpunkt) und braucht selbst keinen Warteraum-Bedarf.'
        );

    }

    public function test_eingetragene_aufsicht_reduziert_fehlbestand_und_ueberzaehlige_wird_erkannt(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $date       = $examPeriod->week_1_start->format('Y-m-d');

        $examRoom      = Room::create(['room_number' => 'COV4-EXAM', 'room_type' => 'exam', 'is_active' => true]);
        $prepRoom      = Room::create(['room_number' => 'COV4-PREP', 'room_type' => 'exam', 'is_active' => true]);
        $unrelatedRoom = Room::create(['room_number' => 'COV4-UNUSED', 'room_type' => 'exam', 'is_active' => true]);

        OralExamDayRoom::create(['exam_period_id' => $examPeriod->id, 'date' => $date, 'room_id' => $prepRoom->id, 'room_role' => 'preparation']);

        $assignment = $this->makeAssignment($examPeriod, $date, $examRoom, $prepRoom, '13:00:00', '13:20:00', '13:20:00', '13:40:00');

        $teacher = Teacher::create(['shortcode' => 'SUP', 'firstname' => 'Super', 'lastname' => 'Visor']);

        // Deckt den tatsächlichen Bedarf (Vorbereitungsraum am echten Prüfungsslot)
        Supervision::create([
            'time_slot_id'      => $assignment->time_slot_id,
            'room_id'           => $prepRoom->id,
            'teacher_id'        => $teacher->id,
            'supervision_type'  => 'preparation',
        ]);

        // Überflüssig: Aufsicht für einen Raum, der an diesem Slot nicht als
        // Vorbereitungsraum genutzt wird
        Supervision::create([
            'time_slot_id'      => $assignment->time_slot_id,
            'room_id'           => $unrelatedRoom->id,
            'teacher_id'        => $teacher->id,
            'supervision_type'  => 'preparation',
        ]);

        $coverage = (new KolloquiumSupervisionCoverageService())->compute($examPeriod);

        $this->assertSame(0, $coverage['uncoveredCount'], 'Der einzige Bedarf (Vorbereitungsraum, kein Gang-Ort konfiguriert) ist gedeckt.');
        $this->assertSame(1, $coverage['orphanedCount'], 'Die Aufsicht im ungenutzten Raum muss als überflüssig erkannt werden.');

    }
}
