<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\ExamAssignment;
use App\Models\StudentExam;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;

class ValidationService
{
    /**
     * Validiert alle Constraints für einen Prüfungszeitraum
     */
    public function validateExamPeriod(ExamPeriod $examPeriod): array
    {
        $errors = [];
        $warnings = [];
        
        // 1. Prüfe ob alle Schüler genau 2 Prüfungen haben (K1 + K2)
        $this->validateStudentExamCount($examPeriod, $errors);
        
        // 2. Prüfe ob jede Prüfung einen Beisitzer hat
        $this->validateAssessors($examPeriod, $warnings);
        
        // 3. Prüfe ob Schüler nicht mehrfach in einer Woche geprüft werden
        $this->validateStudentWeekConstraints($examPeriod, $errors);
        
        // 4. Prüfe Lehrer-Doppelbelegungen (als Prüfer oder Beisitzer)
        $this->validateTeacherTimeSlotConflicts($examPeriod, $errors);
        
        // 5. Prüfe Raum-Konflikte
        $this->validateRoomConflicts($examPeriod, $errors);
        
        // 6. Prüfe Lehrer-Verfügbarkeit
        $this->validateTeacherAvailability($examPeriod, $warnings);
        
        // 7. Prüfe Workload-Verteilung
        $this->validateTeacherWorkload($examPeriod, $warnings);
        
        $valid = count($errors) === 0;
        
        return [
            'valid' => $valid,
            'summary' => $valid 
                ? '✅ Alle Prüfungen sind gültig geplant!' 
                : '❌ Es wurden ' . count($errors) . ' Fehler gefunden.',
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => [
                // Nicht über imPruefungszeitraum(), weil hier zusätzlich die
                // Jahrgangsstufe 11 ausgenommen wird — eine Bedingung, die es
                // nur am Schüler gibt. Externe Bewerber haben keine
                // Jahrgangsstufe und zählen deshalb immer mit.
                'total_exams' => StudentExam::where(function ($q) use ($examPeriod) {
                    $q->whereHas('student', function ($sq) use ($examPeriod) {
                        $sq->where('exam_period_id', $examPeriod->id)
                           ->where('grade', '!=', '11');
                    })->orWhereHas(
                        'externalExamSubject.externalApplicant',
                        fn ($sq) => $sq->where('exam_period_id', $examPeriod->id)
                    );
                })->where('exam_type', 'oral')->count(),
                'assigned_exams' => ExamAssignment::whereHas(
                    'studentExam',
                    fn ($q) => $q->imPruefungszeitraum($examPeriod)->where('exam_type', 'oral')
                )->count(), // Nur mündliche Prüfungen
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
            ]
        ];
    }
    
    /**
     * Prüft ob alle Schüler genau 2 Prüfungen haben
     */
    private function validateStudentExamCount(ExamPeriod $examPeriod, array &$errors): void
    {
        $studentsWithWrongCount = DB::table('students')
            ->leftJoin('student_exams', function($join) {
                $join->on('students.id', '=', 'student_exams.student_id')
                     ->where('student_exams.exam_type', '=', 'oral'); // Nur mündliche Prüfungen zählen
            })
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('students.grade', '!=', '11')
            ->groupBy('students.id', 'students.firstname', 'students.lastname')
            ->havingRaw('COUNT(student_exams.id) != 2')
            ->select('students.id', 'students.firstname', 'students.lastname', DB::raw('COUNT(student_exams.id) as exam_count'))
            ->get();
        
        foreach ($studentsWithWrongCount as $student) {
            $errors[] = [
                'type' => 'student_exam_count',
                'message' => "Schüler {$student->lastname}, {$student->firstname} hat {$student->exam_count} statt 2 Prüfungen"
            ];
        }
    }
    
    /**
     * Prüft ob alle Prüfungen Beisitzer haben
     */
    private function validateAssessors(ExamPeriod $examPeriod, array &$warnings): void
    {
        $examsWithoutAssessor = StudentExam::imPruefungszeitraum($examPeriod)
            ->where('exam_type', 'oral') // Nur mündliche Prüfungen
            ->whereNull('assessor_id')
            ->with('student', 'course.subject', 'externalExamSubject.subject', 'externalExamSubject.externalApplicant')
            ->get();
        
        if ($examsWithoutAssessor->count() > 0) {
            $warnings[] = [
                'type' => 'missing_assessor',
                'message' => $examsWithoutAssessor->count() . ' Prüfung(en) haben noch keinen Beisitzer zugewiesen',
                // Über getStudentName()/getSubjectName() statt über student und
                // course: beide sind für externe Bewerber null, der direkte
                // Zugriff hätte die Validierung mit einem 500er beendet,
                // sobald ein externer Prüfling ohne Beisitzer dasteht.
                'details' => $examsWithoutAssessor->take(5)->map(
                    fn (StudentExam $exam): string => ($exam->getStudentName())
                        . ' (' . ($exam->getSubjectName() ?? '–') . ')'
                )->toArray()
            ];
        }
    }
    
    /**
     * Prüft ob Schüler nicht mehrfach in einer Woche geprüft werden
     */
    private function validateStudentWeekConstraints(ExamPeriod $examPeriod, array &$errors): void
    {
        // Prüfe Woche 1
        $week1Conflicts = $this->findStudentWeekConflicts($examPeriod, 1);
        foreach ($week1Conflicts as $conflict) {
            $errors[] = [
                'type' => 'student_week_conflict',
                'message' => "Schüler {$conflict->lastname}, {$conflict->firstname} hat mehrere Prüfungen in Woche 1 ({$conflict->assignments_count} Zuweisungen)"
            ];
        }
        
        // Prüfe Woche 2
        $week2Conflicts = $this->findStudentWeekConflicts($examPeriod, 2);
        foreach ($week2Conflicts as $conflict) {
            $errors[] = [
                'type' => 'student_week_conflict',
                'message' => "Schüler {$conflict->lastname}, {$conflict->firstname} hat mehrere Prüfungen in Woche 2 ({$conflict->assignments_count} Zuweisungen)"
            ];
        }
    }
    
    private function findStudentWeekConflicts(ExamPeriod $examPeriod, int $week): \Illuminate\Support\Collection
    {
        $weekStart = $week === 1 ? $examPeriod->week_1_start : $examPeriod->week_2_start;
        $weekEnd = $week === 1 ? $examPeriod->week_1_end : $examPeriod->week_2_end;
        
        return DB::table('students')
            ->join('student_exams', 'students.id', '=', 'student_exams.student_id')
            ->join('exam_assignments', 'student_exams.id', '=', 'exam_assignments.student_exam_id')
            ->join('time_slots', 'exam_assignments.time_slot_id', '=', 'time_slots.id')
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('student_exams.exam_type', 'oral') // Nur mündliche Prüfungen
            ->whereBetween('time_slots.date', [$weekStart, $weekEnd])
            ->groupBy('students.id', 'students.firstname', 'students.lastname')
            ->havingRaw('COUNT(exam_assignments.id) > 1')
            ->select('students.id', 'students.firstname', 'students.lastname', DB::raw('COUNT(exam_assignments.id) as assignments_count'))
            ->get();
    }
    
    /**
     * Prüft Lehrer-Doppelbelegungen zur gleichen Zeit
     */
    private function validateTeacherTimeSlotConflicts(ExamPeriod $examPeriod, array &$errors): void
    {
        // Konflikte als Beisitzer
        $assessorConflicts = DB::table('exam_assignments as ea1')
            ->join('student_exams as se1', 'ea1.student_exam_id', '=', 'se1.id')
            ->join('students as s1', 'se1.student_id', '=', 's1.id')
            ->join('exam_assignments as ea2', function($join) {
                $join->on('ea1.time_slot_id', '=', 'ea2.time_slot_id')
                     ->on('ea1.id', '!=', 'ea2.id');
            })
            ->join('student_exams as se2', function($join) {
                $join->on('ea2.student_exam_id', '=', 'se2.id')
                     ->on('se1.assessor_id', '=', 'se2.assessor_id');
            })
            ->join('teachers', 'se1.assessor_id', '=', 'teachers.id')
            ->join('time_slots', 'ea1.time_slot_id', '=', 'time_slots.id')
            ->where('s1.exam_period_id', $examPeriod->id)
            ->where('se1.exam_type', 'oral') // Nur mündliche Prüfungen
            ->where('se2.exam_type', 'oral') // Nur mündliche Prüfungen
            ->whereNotNull('se1.assessor_id')
            ->select(
                'teachers.shortcode',
                'time_slots.date',
                'time_slots.exam_start_time',
                DB::raw('COUNT(*) as conflict_count')
            )
            ->groupBy('teachers.id', 'teachers.shortcode', 'time_slots.date', 'time_slots.exam_start_time')
            ->get();
        
        foreach ($assessorConflicts as $conflict) {
            $errors[] = [
                'type' => 'teacher_double_booking',
                'message' => "Beisitzer {$conflict->shortcode} ist mehrfach eingeteilt am " . 
                           date('d.m.Y', strtotime($conflict->date)) . " um {$conflict->exam_start_time} " .
                           "({$conflict->conflict_count} Konflikte)"
            ];
        }
        
        // Konflikte als Prüfer
        $examinerConflicts = DB::table('exam_assignments as ea1')
            ->join('student_exams as se1', 'ea1.student_exam_id', '=', 'se1.id')
            ->join('students as s1', 'se1.student_id', '=', 's1.id')
            ->join('exam_assignments as ea2', function($join) {
                $join->on('ea1.time_slot_id', '=', 'ea2.time_slot_id')
                     ->on('ea1.id', '!=', 'ea2.id');
            })
            ->join('student_exams as se2', function($join) {
                $join->on('ea2.student_exam_id', '=', 'se2.id')
                     ->on('se1.examiner_id', '=', 'se2.examiner_id');
            })
            ->join('teachers', 'se1.examiner_id', '=', 'teachers.id')
            ->join('time_slots', 'ea1.time_slot_id', '=', 'time_slots.id')
            ->where('s1.exam_period_id', $examPeriod->id)
            ->where('se1.exam_type', 'oral') // Nur mündliche Prüfungen
            ->where('se2.exam_type', 'oral') // Nur mündliche Prüfungen
            ->select(
                'teachers.shortcode',
                'time_slots.date',
                'time_slots.exam_start_time',
                DB::raw('COUNT(*) as conflict_count')
            )
            ->groupBy('teachers.id', 'teachers.shortcode', 'time_slots.date', 'time_slots.exam_start_time')
            ->get();
        
        foreach ($examinerConflicts as $conflict) {
            $errors[] = [
                'type' => 'teacher_double_booking',
                'message' => "Prüfer {$conflict->shortcode} ist mehrfach eingeteilt am " . 
                           date('d.m.Y', strtotime($conflict->date)) . " um {$conflict->exam_start_time} " .
                           "({$conflict->conflict_count} Konflikte)"
            ];
        }
    }
    
    /**
     * Prüft Raum-Konflikte
     */
    private function validateRoomConflicts(ExamPeriod $examPeriod, array &$errors): void
    {
        $roomConflicts = DB::table('exam_assignments as ea1')
            ->join('student_exams as se1', 'ea1.student_exam_id', '=', 'se1.id')
            ->join('students', 'se1.student_id', '=', 'students.id')
            ->join('exam_assignments as ea2', function($join) {
                $join->on('ea1.time_slot_id', '=', 'ea2.time_slot_id')
                     ->on('ea1.exam_room_id', '=', 'ea2.exam_room_id')
                     ->on('ea1.id', '!=', 'ea2.id');
            })
            ->join('student_exams as se2', 'ea2.student_exam_id', '=', 'se2.id')
            ->join('rooms', 'ea1.exam_room_id', '=', 'rooms.id')
            ->join('time_slots', 'ea1.time_slot_id', '=', 'time_slots.id')
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('se1.exam_type', 'oral') // Nur mündliche Prüfungen
            ->where('se2.exam_type', 'oral') // Nur mündliche Prüfungen
            ->select(
                'rooms.room_number',
                'time_slots.date',
                'time_slots.exam_start_time',
                DB::raw('COUNT(*) as conflict_count')
            )
            ->groupBy('rooms.id', 'rooms.room_number', 'time_slots.date', 'time_slots.exam_start_time')
            ->get();
        
        foreach ($roomConflicts as $conflict) {
            $errors[] = [
                'type' => 'room_conflict',
                'message' => "Raum {$conflict->room_number} ist mehrfach belegt am " . 
                           date('d.m.Y', strtotime($conflict->date)) . " um {$conflict->exam_start_time} " .
                           "({$conflict->conflict_count} Konflikte)"
            ];
        }
    }
    
    /**
     * Prüft ob Lehrer an nicht-verfügbaren Tagen eingeteilt sind
     */
    private function validateTeacherAvailability(ExamPeriod $examPeriod, array &$warnings): void
    {
        $unavailabilityConflicts = DB::table('exam_assignments')
            ->join('student_exams', 'exam_assignments.student_exam_id', '=', 'student_exams.id')
            ->join('students', 'student_exams.student_id', '=', 'students.id')
            ->join('time_slots', 'exam_assignments.time_slot_id', '=', 'time_slots.id')
            ->join('teachers', 'student_exams.assessor_id', '=', 'teachers.id')
            ->join('teacher_unavailability', function($join) {
                $join->on('teachers.id', '=', 'teacher_unavailability.teacher_id')
                     ->on('time_slots.date', '=', 'teacher_unavailability.date');
            })
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('student_exams.exam_type', 'oral') // Nur mündliche Prüfungen
            ->whereNotNull('student_exams.assessor_id')
            ->select(
                'teachers.shortcode',
                'time_slots.date',
                'teacher_unavailability.reason'
            )
            ->distinct()
            ->get();
        
        foreach ($unavailabilityConflicts as $conflict) {
            $warnings[] = [
                'type' => 'teacher_unavailable',
                'message' => "Lehrer {$conflict->shortcode} ist eingeteilt am " . 
                           date('d.m.Y', strtotime($conflict->date)) . 
                           " (markiert als: {$conflict->reason})"
            ];
        }
    }
    
    /**
     * Prüft Workload-Verteilung der Lehrer
     */
    private function validateTeacherWorkload(ExamPeriod $examPeriod, array &$warnings): void
    {
        // Finde Lehrer mit besonders hoher Last an einem Tag
        $highWorkload = DB::table('exam_assignments')
            ->join('student_exams', 'exam_assignments.student_exam_id', '=', 'student_exams.id')
            ->join('students', 'student_exams.student_id', '=', 'students.id')
            ->join('time_slots', 'exam_assignments.time_slot_id', '=', 'time_slots.id')
            ->join('teachers', 'student_exams.assessor_id', '=', 'teachers.id')
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('student_exams.exam_type', 'oral') // Nur mündliche Prüfungen
            ->whereNotNull('student_exams.assessor_id')
            ->groupBy('teachers.id', 'teachers.shortcode', 'time_slots.date')
            ->havingRaw('COUNT(*) > 5')
            ->select(
                'teachers.shortcode',
                'time_slots.date',
                DB::raw('COUNT(*) as exams_count')
            )
            ->get();
        
        foreach ($highWorkload as $load) {
            $warnings[] = [
                'type' => 'high_workload',
                'message' => "Lehrer {$load->shortcode} hat {$load->exams_count} Prüfungen am " . 
                           date('d.m.Y', strtotime($load->date)) . " (empfohlen: max. 5)"
            ];
        }
    }
}
