<?php

namespace App\Http\Controllers;

use App\Models\AbsenceCarryover;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\CurrentExamPeriodService;
use App\Services\YearChangeWizardService;
use Illuminate\Http\Request;

/**
 * Geführter Schuljahreswechsel (Modul 1.1, Phase 4).
 *
 * Dünner Controller: die gesamte Ablauflogik (State, Schrittfreigabe,
 * Aktionen) liegt im YearChangeWizardService. Hier nur Request-Validierung,
 * Datensammlung für die Views und Fehlerbehandlung.
 */
class YearChangeWizardController extends Controller
{
    public function __construct(
        private YearChangeWizardService $wizard,
        private CurrentExamPeriodService $currentPeriod,
    ) {}

    /**
     * Wizard-Übersicht: Startseite (kein laufender Wechsel) oder die
     * 9-Schritte-Checkliste mit dem aktiven Schritt.
     */
    public function index()
    {
        if (!$this->wizard->isRunning()) {
            return view('year-change.index', [
                'running' => false,
                'state' => $this->wizard->state(),
                'preflight' => $this->wizard->preflight(),
                'isArchiveView' => $this->currentPeriod->isArchiveView(),
            ]);
        }

        return view('year-change.index', array_merge(
            [
                'running' => true,
                'state' => $this->wizard->state(),
                'preflight' => null,
                'isArchiveView' => $this->currentPeriod->isArchiveView(),
            ],
            $this->stepViewData()
        ));
    }

    /** Neuen Wechsel starten (fixiert Quell-/Ziel-Periode im State). */
    public function start()
    {
        try {
            $this->wizard->start();
        } catch (\Exception $e) {
            return redirect()->route('year-change.index')->with('error', $e->getMessage());
        }

        return redirect()->route('year-change.index')
            ->with('success', 'Schuljahreswechsel gestartet. Bitte die Schritte der Reihe nach abarbeiten.');
    }

    /**
     * Serverseitigen Schritt ausführen (2 Backup, 3 Einfrieren,
     * 4 Rohdaten löschen, 6 Auto-Zuordnung, 7 Vorlagen kopieren).
     */
    public function run(Request $request, int $step)
    {
        if (!in_array($step, YearChangeWizardService::RUNNABLE_STEPS, true)) {
            abort(404);
        }

        // Destruktiver Schritt 4: explizite Bestätigung erzwingen
        if ($step === 4) {
            $request->validate(
                ['confirm_delete' => 'accepted'],
                ['confirm_delete.accepted' => 'Bitte bestätigen Sie das unwiderrufliche Löschen der Absenz-Rohdaten.']
            );
        }

        try {
            $result = $this->wizard->runStep($step);
        } catch (\Exception $e) {
            return redirect()->route('year-change.index')->with('error', $e->getMessage());
        }

        return redirect()->route('year-change.index')
            ->with('success', $this->successMessage($step, $result));
    }

    /**
     * Zeitslots und Vorlagen aus der Vorperiode übernehmen, ohne einen
     * Wechsel zu starten (für bereits ohne Wizard vollzogene Wechsel).
     */
    public function copyTemplates()
    {
        try {
            $result = $this->wizard->copyFromPreviousPeriod();
        } catch (\Exception $e) {
            return redirect()->route('year-change.index')->with('error', $e->getMessage());
        }

        return redirect()->route('year-change.index')
            ->with('success', $this->successMessage(7, $result));
    }

    /** Manuellen Schritt abschließen (1, 5, 6, 8). */
    public function complete(int $step)
    {
        if (!in_array($step, YearChangeWizardService::CONFIRMABLE_STEPS, true)) {
            abort(404);
        }

        try {
            $this->wizard->completeStep($step);
        } catch (\Exception $e) {
            return redirect()->route('year-change.index')->with('error', $e->getMessage());
        }

        return redirect()->route('year-change.index')
            ->with('success', "Schritt {$step} ({$this->wizard->stepTitle($step)}) abgeschlossen.");
    }

    /** Manuelle Zuordnung aus der Klärungsliste (Schritt 6). */
    public function manualMatch(Request $request)
    {
        $validated = $request->validate([
            'carryover_id' => 'required|integer',
            'student_id' => 'required|integer',
        ]);

        try {
            $carryover = $this->wizard->manualMatch(
                (int) $validated['carryover_id'],
                (int) $validated['student_id']
            );
        } catch (\Exception $e) {
            return redirect()->route('year-change.index')->with('error', $e->getMessage());
        }

        return redirect()->route('year-change.index')
            ->with('success', "Vorjahreswerte von {$carryover->firstname} {$carryover->lastname} manuell zugeordnet.");
    }

    /** Abschlussbericht (Schritt 9). */
    public function report()
    {
        $state = $this->wizard->state();

        if ($state === null) {
            return redirect()->route('year-change.index')
                ->with('error', 'Kein Schuljahreswechsel gestartet.');
        }

        return view('year-change.report', array_merge(
            [
                'state' => $state,
                'wizard' => $this->wizard,
                'sourcePeriod' => $this->wizard->sourcePeriod(),
                'targetPeriod' => $this->wizard->targetPeriod(),
            ],
            $this->reportViewData()
        ));
    }

    /** Wechsel abschließen (setzt completed_at, Schritt 9). */
    public function finish()
    {
        try {
            $this->wizard->finish();
        } catch (\Exception $e) {
            return redirect()->route('year-change.report')->with('error', $e->getMessage());
        }

        return redirect()->route('year-change.report')
            ->with('success', 'Schuljahreswechsel abgeschlossen.');
    }

    /** Laufenden Wechsel abbrechen (State löschen, Daten bleiben). */
    public function reset(Request $request)
    {
        $request->validate(
            ['confirm_reset' => 'accepted'],
            ['confirm_reset.accepted' => 'Bitte bestätigen Sie das Zurücksetzen des Wizards.']
        );

        $this->wizard->reset();

        return redirect()->route('year-change.index')
            ->with('success', 'Wizard zurückgesetzt. Bereits ausgeführte Schritte bleiben wirksam.');
    }

    // =========================================================================
    // Private Hilfsmethoden
    // =========================================================================

    /**
     * Anzeigedaten für die Schritt-Checkliste.
     *
     * @return array<string, mixed>
     */
    private function stepViewData(): array
    {
        $sourcePeriod = $this->wizard->sourcePeriod();
        $targetPeriod = $this->wizard->targetPeriod();
        $sourceSchoolYear = $this->wizard->sourceSchoolYear();
        $currentStep = $this->wizard->currentStep();

        $data = [
            'wizard' => $this->wizard,
            'sourcePeriod' => $sourcePeriod,
            'targetPeriod' => $targetPeriod,
            'sourceSchoolYear' => $sourceSchoolYear,
            'currentStep' => $currentStep,
            'sourceStudentCount' => $sourcePeriod !== null
                ? Student::where('exam_period_id', $sourcePeriod->id)->count()
                : 0,
            'targetStudentsByGrade' => [],
            'unmatchedCarryovers' => collect(),
            'leaverCarryovers' => collect(),
            'targetStudents' => collect(),
            'carryoverStats' => null,
            'teacherStats' => null,
        ];

        if ($targetPeriod === null) {
            return $data;
        }

        // Schritt 5: Live-Zähler der Ziel-Periode pro Jahrgangsstufe
        $data['targetStudentsByGrade'] = Student::where('exam_period_id', $targetPeriod->id)
            ->selectRaw('grade, COUNT(*) as count')
            ->groupBy('grade')
            ->orderBy('grade')
            ->pluck('count', 'grade')
            ->toArray();

        // Schritt 6: Klärungsliste + Schüler-Auswahl für manuelle Zuordnung
        //
        // Getrennt nach verbleibenden Jahrgängen und Abgängern: Der
        // Abiturjahrgang hinterlässt Carryovers, zu denen es im neuen Jahr
        // nichts mehr zu finden gibt. Ungetrennt bestünde die Liste fast nur
        // aus ihnen und die wenigen echten Fälle gingen darin unter.
        if ($currentStep === 6 && $this->wizard->stepAvailable(6)) {
            $unmatchedBase = AbsenceCarryover::where('source_school_year', $sourceSchoolYear)
                ->whereNull('matched_student_id')
                ->orderBy('lastname')
                ->orderBy('firstname');

            $data['unmatchedCarryovers'] = (clone $unmatchedBase)->fromContinuingGrades()->get();
            $data['leaverCarryovers'] = (clone $unmatchedBase)->fromGraduatingGrade()->get();

            $data['targetStudents'] = Student::where('exam_period_id', $targetPeriod->id)
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(['id', 'firstname', 'lastname', 'grade']);
        }

        // Schritt 6/9: Zuordnungs-Statistik (live)
        $carryoverBase = AbsenceCarryover::where('source_school_year', $sourceSchoolYear);
        $unmatchedStats = (clone $carryoverBase)->whereNull('matched_student_id');
        $data['carryoverStats'] = [
            'total' => (clone $carryoverBase)->count(),
            'matched' => (clone $carryoverBase)->whereNotNull('matched_student_id')->count(),
            'unmatched' => (clone $unmatchedStats)->count(),
            // Offene Fälle, die wirklich Arbeit bedeuten: alles ohne den
            // abgehenden Abiturjahrgang.
            'open' => (clone $unmatchedStats)->fromContinuingGrades()->count(),
            'leavers' => (clone $unmatchedStats)->fromGraduatingGrade()->count(),
        ];

        // Schritt 8: Lehrer-Zahlen (live)
        $data['teacherStats'] = [
            'active' => Teacher::where('is_active', true)->count(),
            'inactive' => Teacher::where('is_active', false)->count(),
        ];

        return $data;
    }

    /**
     * Live-Zahlen für den Abschlussbericht.
     *
     * @return array<string, mixed>
     */
    private function reportViewData(): array
    {
        $targetPeriod = $this->wizard->targetPeriod();
        $sourceSchoolYear = null;

        try {
            $sourceSchoolYear = $this->wizard->sourceSchoolYear();
        } catch (\Exception) {
            // abgeschlossener/unvollständiger State ohne Schuljahr — Bericht zeigt dann keine Zuordnungszahlen
        }

        $carryoverStats = null;
        $unmatchedCarryovers = collect();

        $leaverCarryovers = collect();

        if ($sourceSchoolYear !== null) {
            $base = AbsenceCarryover::where('source_school_year', $sourceSchoolYear);
            $unmatched = (clone $base)->whereNull('matched_student_id');

            $carryoverStats = [
                'total' => (clone $base)->count(),
                'matched_auto' => (clone $base)->where('match_method', 'auto')->count(),
                'matched_manual' => (clone $base)->where('match_method', 'manuell')->count(),
                'unmatched' => (clone $unmatched)->count(),
                'open' => (clone $unmatched)->fromContinuingGrades()->count(),
                'leavers' => (clone $unmatched)->fromGraduatingGrade()->count(),
            ];

            $unmatchedCarryovers = (clone $unmatched)->fromContinuingGrades()
                ->orderBy('lastname')->orderBy('firstname')->get();
            $leaverCarryovers = (clone $unmatched)->fromGraduatingGrade()
                ->orderBy('lastname')->orderBy('firstname')->get();
        }

        return [
            'carryoverStats' => $carryoverStats,
            'unmatchedCarryovers' => $unmatchedCarryovers,
            'leaverCarryovers' => $leaverCarryovers,
            'targetStudentsByGrade' => $targetPeriod !== null
                ? Student::where('exam_period_id', $targetPeriod->id)
                    ->selectRaw('grade, COUNT(*) as count')
                    ->groupBy('grade')->orderBy('grade')
                    ->pluck('count', 'grade')->toArray()
                : [],
            'teacherStats' => [
                'active' => Teacher::where('is_active', true)->count(),
                'inactive' => Teacher::where('is_active', false)->count(),
            ],
        ];
    }

    /**
     * Erfolgsmeldung mit den Ergebniszahlen eines ausgeführten Schritts.
     *
     * @param array<string, mixed> $result
     */
    private function successMessage(int $step, array $result): string
    {
        return match ($step) {
            2 => "Backup erstellt: {$result['filename']}",
            3 => "Kennzahlen von {$result['frozen']} Schülern eingefroren"
                . ($result['purged_old_carryovers'] > 0
                    ? " ({$result['purged_old_carryovers']} Vor-Vorjahres-Einträge gelöscht)."
                    : '.'),
            4 => "Rohdaten gelöscht: {$result['deleted_absences']} Absenzen, "
                . "{$result['deleted_measures']} Maßnahmen, {$result['deleted_imports']} Import-Protokolle.",
            6 => "Automatische Zuordnung: {$result['matched_now']} neu zugeordnet, "
                . "{$result['unmatched']} verbleiben in der Klärungsliste.",
            7 => "Übernommen: {$result['slot_templates_copied']} Zeitslot-Vorlagen, "
                . "{$result['time_slots_copied']} Kolloquiums-Zeitslots, "
                . "{$result['supervision_grids_copied']} Aufsichtsraster, "
                . "{$result['supervision_time_slots_copied']} Aufsichts-Zeitslots, "
                . "{$result['supervision_templates_copied']} Aufsichtsvorlagen"
                . (($result['slot_templates_skipped'] + $result['supervision_templates_skipped']) > 0
                    ? " ({$result['slot_templates_skipped']}/{$result['supervision_templates_skipped']} bereits vorhanden, übersprungen)."
                    : '.'),
            default => "Schritt {$step} ausgeführt.",
        };
    }
}
