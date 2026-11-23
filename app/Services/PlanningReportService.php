<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\ExamAssignment;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PlanningReportService - Erstellt detaillierte Planungs-Reports
 * 
 * Analysiert:
 * - Harte Constraints (Fehler)
 * - Weiche Constraints (Warnungen)
 * - Statistiken
 * - Hinweise für Nacharbeit
 */
class PlanningReportService
{
    private array $softConstraintViolations = [];
    private array $hardConstraintViolations = [];
    private array $statistics = [];
    private array $hints = [];

    /**
     * Erstellt den vollständigen Planungs-Report
     */
    public function generateReport(ExamPeriod $examPeriod, array $planningResult): array
    {
        $this->reset();

        // Analysiere Ergebnisse
        $this->analyzeHardConstraints($planningResult);
        $this->analyzeSoftConstraints($examPeriod, $planningResult);
        $this->calculateStatistics($examPeriod);
        $this->generateHints($examPeriod);
        
        return [
            'summary' => $this->buildSummary($examPeriod, $planningResult),
            'hard_constraints' => $this->hardConstraintViolations,
            'soft_constraints' => $this->softConstraintViolations,
            'statistics' => $this->statistics,
            'hints' => $this->hints,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Erstellt die Zusammenfassung
     */
    private function buildSummary(ExamPeriod $examPeriod, array $planningResult): array
    {
        /** @var \Illuminate\Support\Collection<int, Student> $studentsWithExams */
        $studentsWithExams = $examPeriod->students()->with('exams')->get();
        $totalExams = $studentsWithExams->sum(fn(Student $s) => $s->exams->count());
            
        $scheduled = $planningResult['scheduled'] ?? $planningResult['stats']['exams_scheduled'] ?? 0;
        
        return [
            'total_exams' => $totalExams,
            'scheduled' => $scheduled,
            'not_scheduled' => $totalExams - $scheduled,
            'soft_violations_count' => count($this->softConstraintViolations),
            'hard_violations_count' => count($this->hardConstraintViolations),
            'assessors_missing' => $this->countMissingAssessors($examPeriod),
            'success_rate' => $totalExams > 0 
                ? round(($scheduled / $totalExams) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Analysiert Verletzungen harter Constraints
     */
    private function analyzeHardConstraints(array $planningResult): void
    {
        foreach ($planningResult['warnings'] ?? [] as $warning) {
            if (($warning['severity'] ?? '') === 'error') {
                $this->hardConstraintViolations[] = [
                    'type' => $warning['type'] ?? 'unknown',
                    'message' => $warning['message'] ?? 'Unbekannter Fehler',
                    'details' => $warning['details'] ?? null,
                ];
            }
        }
    }

    /**
     * Analysiert Verletzungen weicher Constraints
     */
    private function analyzeSoftConstraints(ExamPeriod $examPeriod, array $planningResult): void
    {
        $this->checkFridayExams($examPeriod);
        $this->checkUnavailableDays($examPeriod);
        $this->checkHighWorkload($examPeriod);
        $this->checkInterruptedBlocks($examPeriod);
        
        foreach ($planningResult['warnings'] ?? [] as $warning) {
            if (($warning['severity'] ?? '') === 'warning') {
                $this->softConstraintViolations[] = [
                    'code' => $warning['type'] ?? 'unknown',
                    'message' => $warning['message'] ?? 'Unbekannte Warnung',
                    'severity' => 'warning',
                    'details' => $warning['details'] ?? null,
                ];
            }
        }
    }

    /**
     * Prüft auf Freitags-Prüfungen (W4)
     */
    private function checkFridayExams(ExamPeriod $examPeriod): void
    {
        $fridayExams = ExamAssignment::whereHas('studentExam.student', fn($q) => 
            $q->where('exam_period_id', $examPeriod->id)
        )
        ->whereHas('timeSlot', function($q) {
            // Freitag (ISO 5); portabel für MariaDB und SQLite
            $treiber = (new \App\Models\TimeSlot())->getConnection()->getDriverName();
            $q->whereRaw(\App\Support\Sql::wochentagIst($treiber, 'date', 5));
        })
        ->count();
        
        if ($fridayExams > 0) {
            $this->softConstraintViolations[] = [
                'code' => 'W4_FRIDAY_USED',
                'message' => "{$fridayExams} Prüfung(en) am Freitag geplant",
                'severity' => 'info',
                'count' => $fridayExams,
            ];
        }
    }

    /**
     * Prüft Prüfer an freien Tagen (W5)
     */
    private function checkUnavailableDays(ExamPeriod $examPeriod): void
    {
        $assignments = ExamAssignment::whereHas('studentExam.student', fn($q) => 
            $q->where('exam_period_id', $examPeriod->id)
        )
        ->with(['studentExam.examiner', 'timeSlot'])
        ->get();
        
        foreach ($assignments as $assignment) {
            $examiner = $assignment->studentExam->examiner ?? null;
            if (!$examiner) continue;
            
            $date = $assignment->timeSlot->date;
            if (!$examiner->isAvailable($date)) {
                $this->softConstraintViolations[] = [
                    'code' => 'W5_UNAVAILABLE_DAY',
                    'message' => "Prüfer {$examiner->shortcode} am " . 
                                Carbon::parse($date)->format('d.m.Y') . " (freier Tag)",
                    'severity' => 'warning',
                    'teacher_id' => $examiner->id,
                ];
            }
        }
    }

    /**
     * Prüft hohe Tagesbelastung (W6)
     */
    private function checkHighWorkload(ExamPeriod $examPeriod, int $threshold = 8): void
    {
        $assignments = ExamAssignment::whereHas('studentExam.student', fn($q) => 
            $q->where('exam_period_id', $examPeriod->id)
        )
        ->with(['studentExam.examiner', 'timeSlot'])
        ->get();
        
        $workloadByTeacherDay = $assignments->groupBy(function($a) {
            $examinerId = $a->studentExam->examiner_id ?? 0;
            $date = Carbon::parse($a->timeSlot->date)->toDateString();
            return "{$examinerId}_{$date}";
        })->filter(fn($group) => $group->count() > $threshold);
        
        foreach ($workloadByTeacherDay as $key => $group) {
            $examiner = $group->first()->studentExam->examiner ?? null;
            if (!$examiner) continue;
            
            $date = Carbon::parse($group->first()->timeSlot->date)->format('d.m.Y');
            $count = $group->count();
            
            $this->softConstraintViolations[] = [
                'code' => 'W6_HIGH_WORKLOAD',
                'message' => "Prüfer {$examiner->shortcode}: {$count} Prüfungen am {$date}",
                'severity' => 'warning',
                'teacher_id' => $examiner->id,
                'count' => $count,
            ];
        }
    }

    /**
     * Prüft unterbrochene Prüferblöcke (W7)
     */
    private function checkInterruptedBlocks(ExamPeriod $examPeriod): void
    {
        // Gruppiere nach coupling_id statt theme_group
        $blocks = ExamAssignment::whereHas('studentExam.student', fn($q) => 
            $q->where('exam_period_id', $examPeriod->id)
        )
        ->whereHas('studentExam', fn($q) => $q->whereNotNull('coupling_id'))
        ->with(['timeSlot', 'studentExam'])
        ->get()
        ->groupBy('studentExam.coupling_id');
        
        foreach ($blocks as $couplingId => $assignments) {
            $dates = $assignments->pluck('timeSlot.date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->unique();
            
            if ($dates->count() > 1) {
                $this->softConstraintViolations[] = [
                    'code' => 'W7_BLOCK_INTERRUPTED',
                    'message' => "Gekoppelter Block auf {$dates->count()} Tage verteilt",
                    'severity' => 'info',
                    'coupling_id' => $couplingId,
                ];
            }
        }
    }

    /**
     * Berechnet Statistiken
     */
    private function calculateStatistics(ExamPeriod $examPeriod): void
    {
        $assignments = ExamAssignment::whereHas('studentExam.student', fn($q) => 
            $q->where('exam_period_id', $examPeriod->id)
        )->with(['timeSlot', 'studentExam.examiner', 'examRoom'])->get();
        
        // Nach Wochentagen
        $this->statistics['by_weekday'] = $assignments
            ->groupBy(fn($a) => Carbon::parse($a->timeSlot->date)->locale('de')->dayName)
            ->map(fn($g) => $g->count())
            ->toArray();
        
        // Nach Zeitslots
        $this->statistics['by_slot'] = $assignments
            ->groupBy(fn($a) => "Slot " . $a->timeSlot->slot_number)
            ->map(fn($g) => $g->count())
            ->toArray();
        
        // Nach Lehrer
        $this->statistics['by_teacher'] = $assignments
            ->groupBy(fn($a) => $a->studentExam->examiner->shortcode ?? 'unbekannt')
            ->map(fn($g) => $g->count())
            ->sortDesc()
            ->toArray();
    }

    /**
     * Generiert Hinweise für manuelle Nacharbeit
     */
    private function generateHints(ExamPeriod $examPeriod): void
    {
        $missingAssessors = $this->countMissingAssessors($examPeriod);
        
        if ($missingAssessors > 0) {
            $this->hints[] = [
                'type' => 'action_required',
                'message' => "{$missingAssessors} Prüfungen benötigen noch einen Beisitzer",
                'priority' => 'high',
            ];
        }
        
        $fridayViolations = collect($this->softConstraintViolations)
            ->where('code', 'W4_FRIDAY_USED')
            ->first();
            
        if ($fridayViolations && ($fridayViolations['count'] ?? 0) > 5) {
            $this->hints[] = [
                'type' => 'optimization',
                'message' => "Viele Freitags-Prüfungen - manuelle Umverteilung prüfen?",
                'priority' => 'low',
            ];
        }
        
        $unavailableCount = collect($this->softConstraintViolations)
            ->where('code', 'W5_UNAVAILABLE_DAY')
            ->count();
            
        if ($unavailableCount > 0) {
            $this->hints[] = [
                'type' => 'review',
                'message' => "{$unavailableCount} Prüfer an freien Tagen - bitte prüfen",
                'priority' => 'medium',
            ];
        }
    }

    /**
     * Zählt Prüfungen ohne Beisitzer
     * WICHTIG: assessor_id ist jetzt in StudentExam, nicht in ExamAssignment!
     */
    private function countMissingAssessors(ExamPeriod $examPeriod): int
    {
        return StudentExam::imPruefungszeitraum($examPeriod)
            ->whereHas('assignment') // Nur geplante Prüfungen
            ->whereNull('assessor_id')
            ->count();
    }

    /**
     * Setzt den Service zurück
     */
    private function reset(): void
    {
        $this->softConstraintViolations = [];
        $this->hardConstraintViolations = [];
        $this->statistics = [];
        $this->hints = [];
    }
}
