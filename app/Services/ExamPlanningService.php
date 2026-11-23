<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\StudentExam;
use App\Models\ExamAssignment;
use App\Models\TimeSlot;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExamPlanningService
{
    public function __construct(
        private ConflictDetectionService $conflictService,
        private OptimizationService $optimizationService,
        private ?PlanningReportService $reportService = null
    ) {
        $this->reportService = $reportService ?? new PlanningReportService();
    }

    /**
     * Automatische Planung mit Optimierung
     */
    public function autoSchedule(ExamPeriod $examPeriod, array $options = []): array
    {
        Log::channel('single')->info("ExamPlanningService::autoSchedule gestartet", [
            'exam_period_id' => $examPeriod->id
        ]);

        try {
            DB::beginTransaction();
            
            // Optional: Bestehende Zuweisungen löschen
            if ($options['clear_existing'] ?? false) {
                $this->clearSchedule($examPeriod);
            }
            
            // 1. Validierung
            $this->validateExamPeriod($examPeriod);

            // 2. Zeit-Slots generieren (falls noch nicht vorhanden)
            $this->generateTimeSlots($examPeriod);

            // 3. Optimierungsalgorithmus ausführen (neue Version ohne themeGroups Parameter)
            $result = $this->optimizationService->optimize($examPeriod, $options);

            // 4. Zuweisungen speichern
            $savedCount = $this->saveAssignments($result['assignments']);

            // 5. Konflikte prüfen
            $conflicts = $this->conflictService->detectAll($examPeriod);
            
            DB::commit();
            
            // 6. Report generieren
            $report = $this->reportService->generateReport($examPeriod, [
                'scheduled' => $savedCount,
                'warnings' => $result['warnings'],
                'stats' => $result['stats'],
            ]);
            
            Log::channel('single')->info("Autoplanung abgeschlossen", [
                'scheduled' => $savedCount
            ]);

            return [
                'success' => count($conflicts['errors']) === 0,
                'conflicts' => $conflicts,
                'stats' => $this->getScheduleStats($examPeriod),
                'scheduled_count' => $savedCount,
                'warnings' => $result['warnings'],
                'report' => $report,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('single')->error("Autoplanung fehlgeschlagen: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Fehler bei der Autoplanung: ' . $e->getMessage(),
                'conflicts' => ['errors' => [['message' => $e->getMessage()]], 'warnings' => []],
                'warnings' => [],
                'scheduled_count' => 0,
            ];
        }
    }

    /**
     * Generiert Zeitslots für den Prüfungszeitraum
     * 
     * TODO: Diese Funktion benötigt schulspezifische Konfiguration.
     * Deaktiviert nach Entfernung von prep_start_time, exams_per_day, break_duration_minutes aus exam_periods.
     *
     * @phpstan-ignore void.pure
     */
    private function generateTimeSlots(ExamPeriod $examPeriod): void
    {
        // TEMPORÄR DEAKTIVIERT - benötigt schulspezifische Konfiguration
        return;
        
        /* ORIGINAL CODE - benötigt fehlende Felder
        if (TimeSlot::where('exam_period_id', $examPeriod->id)->exists()) {
            return;
        }

        DB::transaction(function() use ($examPeriod) {
            $dates = [];

            // Woche 1
            $current = Carbon::parse($examPeriod->week_1_start);
            $end = Carbon::parse($examPeriod->week_1_end);
            while ($current <= $end) {
                if ($current->isWeekday()) {
                    $dates[] = $current->copy();
                }
                $current->addDay();
            }

            // Woche 2
            $current = Carbon::parse($examPeriod->week_2_start);
            $end = Carbon::parse($examPeriod->week_2_end);
            while ($current <= $end) {
                if ($current->isWeekday()) {
                    $dates[] = $current->copy();
                }
                $current->addDay();
            }

            foreach ($dates as $date) {
                $this->createSlotsForDay($examPeriod, $date);
            }
        });
        */
    }

    /**
     * Validiert einen Prüfungszeitraum
     */
    private function validateExamPeriod(ExamPeriod $examPeriod): void
    {
        $studentCount = $examPeriod->students()->count();
        if ($studentCount === 0) {
            throw new \Exception('Keine Schüler im Prüfungszeitraum vorhanden.');
        }

        $roomCount = Room::where('is_active', true)->count();
        if ($roomCount === 0) {
            throw new \Exception('Keine aktiven Räume vorhanden.');
        }

        $teacherCount = \App\Models\Teacher::count();
        if ($teacherCount === 0) {
            throw new \Exception('Keine Lehrkräfte vorhanden.');
        }
    }

    /**
     * Speichert die Zuweisungen in der Datenbank
     */
    private function saveAssignments(array $assignments): int
    {
        $count = 0;
        DB::transaction(function() use ($assignments, &$count) {
            foreach ($assignments as $assignment) {
                // Aktives (nicht-abgesagtes) Assignment finden und updaten, sonst neu anlegen
                $existing = ExamAssignment::where('student_exam_id', $assignment['student_exam_id'])
                    ->where('status', '!=', 'cancelled')
                    ->first();

                if ($existing) {
                    $existing->update([
                        'exam_week'    => $assignment['exam_week'],
                        'time_slot_id' => $assignment['time_slot_id'],
                        'exam_room_id' => $assignment['exam_room_id'],
                        'prep_room_id' => $assignment['prep_room_id'],
                        'status'       => 'planned',
                        'notes'        => ($assignment['coupling_conflict'] ?? false)
                                            ? 'Kopplung aufgetrennt' : null,
                    ]);
                } else {
                    ExamAssignment::create([
                        'student_exam_id' => $assignment['student_exam_id'],
                        'exam_week'    => $assignment['exam_week'],
                        'time_slot_id' => $assignment['time_slot_id'],
                        'exam_room_id' => $assignment['exam_room_id'],
                        'prep_room_id' => $assignment['prep_room_id'],
                        'status'       => 'planned',
                        'notes'        => ($assignment['coupling_conflict'] ?? false)
                                            ? 'Kopplung aufgetrennt' : null,
                    ]);
                }
                
                $count++;
            }
        });
        return $count;
    }

    /**
     * Ermittelt Statistiken für einen Prüfungszeitraum
     */
    private function getScheduleStats(ExamPeriod $examPeriod): array
    {
        // NUR MÜNDLICHE PRÜFUNGEN (Kolloquien) - schriftliche werden separat geplant.
        // Über imPruefungszeitraum(): externe Bewerber haben vier mündliche
        // Prüfungen (Anlage 14, Fächer 5–8) und gehören in beide Zahlen.
        $totalExams = StudentExam::imPruefungszeitraum($examPeriod)
            ->where('exam_type', 'oral')
            ->count();

        $scheduledExams = StudentExam::imPruefungszeitraum($examPeriod)
            ->where('exam_type', 'oral')
            ->whereHas('assignment')
            ->count();

        $conflicts = $this->conflictService->detectAll($examPeriod);

        $teacherDayStats = $this->getTeacherDayStats($examPeriod);

        return [
            'total_exams' => $totalExams,
            'scheduled_exams' => $scheduledExams,
            'unscheduled_exams' => $totalExams - $scheduledExams,
            'error_count' => count($conflicts['errors']),
            'warning_count' => count($conflicts['warnings']),
            'completion_rate' => $totalExams > 0 ? round(($scheduledExams / $totalExams) * 100, 1) : 0,
            'teacher_days_avg' => $teacherDayStats['avg'],
            'teacher_days_max' => $teacherDayStats['max'],
            'teachers_involved' => $teacherDayStats['teachers'],
            'teacher_free_days_used' => $teacherDayStats['free_days_used'],
        ];
    }

    /**
     * Modul 4.3: Kennzahlen "Prüfungstage pro Lehrkraft".
     *
     * Zählt für jede beteiligte Lehrkraft (als Prüfer ODER Beisitzer) die Anzahl
     * unterschiedlicher Prüfungstage über alle geplanten Kolloquien – die zentrale
     * Messgröße für die Tagesbündelung.
     *
     * Zusätzlich (Teilpunkt "freie Tage"): Anzahl der (Lehrkraft, Tag)-Paare,
     * bei denen der Prüfungstag auf einen stundenplanfreien Wochentag der
     * Lehrkraft fällt. 0 auch dann, wenn keine Stundenplandaten vorliegen.
     *
     * @return array{avg: float, max: int, teachers: int, free_days_used: int}
     */
    private function getTeacherDayStats(ExamPeriod $examPeriod): array
    {
        $assignments = ExamAssignment::whereHas('studentExam.student', function($q) use ($examPeriod) {
            $q->where('exam_period_id', $examPeriod->id);
        })
        ->whereHas('studentExam', fn($q) => $q->where('exam_type', 'oral'))
        ->with(['studentExam', 'timeSlot'])
        ->get();

        // teacher_id => Set von Prüfungstagen ('Y-m-d' => true)
        $daysByTeacher = [];
        foreach ($assignments as $assignment) {
            if (!$assignment->timeSlot || !$assignment->studentExam) {
                continue;
            }
            $date = Carbon::parse($assignment->timeSlot->date)->format('Y-m-d');

            $daysByTeacher[$assignment->studentExam->examiner_id][$date] = true;
            if ($assignment->studentExam->assessor_id) {
                $daysByTeacher[$assignment->studentExam->assessor_id][$date] = true;
            }
        }

        if (empty($daysByTeacher)) {
            return ['avg' => 0.0, 'max' => 0, 'teachers' => 0, 'free_days_used' => 0];
        }

        $dayCounts = array_map('count', $daysByTeacher);

        // Teilpunkt "freie Tage": Prüfungstage an stundenplanfreien Wochentagen zählen
        $freeWeekdays = $examPeriod->week_1_start
            ? \App\Models\TimetableEntry::freeWeekdaysByTeacherForDate(Carbon::parse($examPeriod->week_1_start))
            : [];
        $freeDaysUsed = 0;
        foreach ($daysByTeacher as $teacherId => $dates) {
            if (!isset($freeWeekdays[$teacherId])) {
                continue;
            }
            foreach (array_keys($dates) as $date) {
                $weekday = (int) date('N', (int) strtotime((string) $date));
                if (isset($freeWeekdays[$teacherId][$weekday])) {
                    $freeDaysUsed++;
                }
            }
        }

        return [
            'avg' => round(array_sum($dayCounts) / count($dayCounts), 1),
            'max' => max($dayCounts),
            'teachers' => count($dayCounts),
            'free_days_used' => $freeDaysUsed,
        ];
    }

    // getConflicts() ist am 30.08.2026 entfallen: Es reichte nur an
    // ConflictDetectionService::detectAll() durch und wurde nirgends gerufen.
    // Die gleichnamige Methode im PlanningController ist eine eigene mit
    // eigener Abfrage — sie hat diese hier nie benutzt.

    /**
     * Löscht alle NICHT-FIXIERTEN Zuweisungen für einen Prüfungszeitraum
     * Fixierte Prüfungen (is_locked = true) bleiben erhalten!
     */
    public function clearSchedule(ExamPeriod $examPeriod): int
    {
        // Über imPruefungszeitraum(): mit `whereHas('student', …)` blieben die
        // Termine externer Prüflinge beim Leeren des Plans stehen. Der nächste
        // Planungslauf wäre dann gegen Zuweisungen gelaufen, die er weder sah
        // noch entfernen konnte.
        return ExamAssignment::whereHas(
            'studentExam',
            fn ($q) => $q->imPruefungszeitraum($examPeriod)
        )
        ->where('is_locked', false) // NUR nicht-fixierte löschen!
        ->delete();
    }

    // clearScheduleForce() ist am 30.08.2026 entfallen — auch fixierte
    // Zuweisungen zu löschen rief nie jemand auf. Das Zurücksetzen der Planung
    // läuft über PlanningController::resetAssignments bzw. resetPlanning; die
    // Schwester clearSchedule() bleibt, sie kommt aus autoSchedule().
}
