<?php

namespace App\Http\Controllers;

use App\Models\AbsenceImport;
use App\Models\Student;
use App\Services\AbsenceEvaluationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Übersicht importierter Absenzen je Schüler (Roadmap 8.1/8.2).
 */
class AbsenceController extends Controller
{
    public function __construct(
        private AbsenceEvaluationService $evaluation
    ) {}

    public function index()
    {
        $lastImport = AbsenceImport::orderByDesc('created_at')->first();
        $daysSinceImport = $lastImport ? (int) $lastImport->created_at->diffInDays(now()) : null;
        $thresholds = $this->evaluation->thresholds();

        $lastImportsByGrade = [];
        foreach (['13', '12'] as $g) {
            $lastImportsByGrade[$g] = AbsenceImport::whereHas('absences.student', fn ($q) => $q->where('grade', $g))
                ->orderByDesc('created_at')
                ->first();
        }

        $tableRows = $this->buildTableRows($thresholds);

        return view('absences.index', compact('tableRows', 'lastImport', 'daysSinceImport', 'lastImportsByGrade', 'thresholds'));
    }

    /**
     * PDF-Export der "Aktion nötig"-Schüler (Roadmap 8.2), gefiltert nach
     * Jahrgangsstufe (?grade=12|13, wie der Reiter auf der Übersichtsseite).
     */
    public function actionNeededPdf(Request $request)
    {
        $grade = $request->query('grade', '13');
        $thresholds = $this->evaluation->thresholds();

        $rows = $this->buildTableRows($thresholds)
            ->filter(fn(array $row) => $row['grade'] === $grade && $this->needsAction($row))
            ->map(fn(array $row) => $row + ['reasons' => $this->actionReasons($row)])
            ->sortBy('name')
            ->values();

        $pdf = Pdf::loadView('exports.absences-action-needed', [
            'rows' => $rows,
            'grade' => $grade,
            'thresholds' => $thresholds,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Absenzen_Aktion_noetig_Q' . $grade . '_' . now()->format('Y-m-d') . '.pdf');
    }

    public function show(Student $student)
    {
        $absences = $student->absences()->orderByDesc('date_from')->orderByDesc('id')->get();
        $measures = $student->absenceMeasures()->get();
        $attestpflicht = $this->evaluation->attestpflichtStatus($student);
        $statistics = $this->evaluation->detailStatistics($student);
        $openAttest = $this->evaluation->openAttestSummary($student);

        // Vorjahres-Kennzahlen (Modul 1.1, Phase 3) — nach dem
        // Schuljahreswechsel per Namensabgleich zugeordnet
        $carryover = \App\Models\AbsenceCarryover::where('matched_student_id', $student->id)
            ->orderByDesc('source_school_year')
            ->first();

        return view('absences.show', compact('student', 'absences', 'measures', 'attestpflicht', 'statistics', 'openAttest', 'carryover'));
    }

    /**
     * Flache, primitive Struktur je Schüler — Basis sowohl für die
     * Such-/Sortiertabelle (Alpine.js) als auch für den PDF-Export.
     */
    private function buildTableRows(array $thresholds): Collection
    {
        // Perioden-Audit (Modul 1.1): nur Schüler der gewählten Periode —
        // sonst tauchten nach dem Jahreswechsel die Alt-Jahrgänge (mit
        // geleerten Absenzen) doppelt in der Liste auf.
        $students = Student::abiturGrades()
            ->where('exam_period_id', app(\App\Services\CurrentExamPeriodService::class)->currentId())
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        // Vorjahres-Kennzahlen (Modul 1.1, Phase 3, optionaler Hinweis-Badge):
        // zugeordnete Carryovers einmal laden; keyBy behält bei mehreren
        // Schuljahren durch die aufsteigende Sortierung das neueste.
        $carryovers = \App\Models\AbsenceCarryover::whereIn('matched_student_id', $students->pluck('id'))
            ->orderBy('source_school_year')
            ->get()
            ->keyBy('matched_student_id');

        return $students->map(function (Student $student) use ($thresholds, $carryovers) {
            $resetDates = $this->evaluation->resetDates($student);
            $metrics = $this->evaluation->evaluate($student, $thresholds, $resetDates);
            $attestpflicht = $this->evaluation->attestpflichtStatus($student);
            $openAttest = $this->evaluation->openAttestSummary($student);
            $vorjahr = $this->vorjahrBadge($carryovers->get($student->id), $thresholds);

            return [
                'name' => "{$student->lastname}, {$student->firstname}",
                'lastname' => $student->lastname,
                'firstname' => $student->firstname,
                'class_name' => $student->class_name ?? '',
                'grade' => $student->grade,
                'krank_days' => $metrics['krank_days'],
                'krank_percent' => $metrics['krank_percent'],
                'unentschuldigt_count' => $metrics['unentschuldigt_count'],
                'verspaetet_count' => $metrics['verspaetet_count'],
                'attestpflicht_active' => $attestpflicht['active'],
                'attestpflicht_since' => $attestpflicht['since']?->format('d.m.Y'),
                'open_attest_count' => $openAttest['count'],
                'open_attest_overdue' => $openAttest['overdue'],
                'auffaellig_krankheit' => in_array('krankheit', $metrics['auffaellig'], true),
                'auffaellig_unentschuldigt' => in_array('unentschuldigt', $metrics['auffaellig'], true),
                'auffaellig_verspaetung' => in_array('verspaetung', $metrics['auffaellig'], true),
                'vorjahr_auffaellig' => $vorjahr['auffaellig'],
                'vorjahr_title' => $vorjahr['title'],
                'url' => route('absences.show', $student),
            ];
        })->values();
    }

    /**
     * Gleiche Regel wie isActionNeeded() im Frontend (absences/index.blade.php):
     * überschrittener Schwellenwert oder überfälliges Attest.
     */
    private function needsAction(array $row): bool
    {
        return $row['auffaellig_krankheit'] || $row['auffaellig_unentschuldigt'] ||
               $row['auffaellig_verspaetung'] || $row['open_attest_overdue'];
    }

    /**
     * Gründe für "Aktion nötig" als Anzeigetexte (gleiche Regeln wie
     * reasons() im Frontend).
     *
     * @return array<int, string>
     */
    private function actionReasons(array $row): array
    {
        $reasons = [];
        if ($row['auffaellig_krankheit']) {
            $pct = $row['krank_percent'] !== null
                ? ' (' . number_format((float) $row['krank_percent'], 1, ',', '.') . ' %)'
                : '';
            $reasons[] = 'Krankheit: ' . $row['krank_days'] . ' Tage' . $pct;
        }
        if ($row['auffaellig_unentschuldigt']) {
            $reasons[] = 'Unentschuldigt: ' . $row['unentschuldigt_count'] . '×';
        }
        if ($row['auffaellig_verspaetung']) {
            $reasons[] = 'Verspätung: ' . $row['verspaetet_count'] . '×';
        }
        if ($row['open_attest_overdue']) {
            $reasons[] = 'Attest überfällig (' . $row['open_attest_count'] . ' offen)';
        }

        return $reasons;
    }

    /**
     * Hinweis-Badge bei auffälligen Vorjahreswerten (Modul 1.1, Phase 3,
     * optional) — Schwellenwerte aus Modul 8.2 wiederverwendet. Reiner
     * Hinweis, fließt bewusst NICHT in "Aktion nötig" ein (Vorjahreswerte
     * begründen im neuen Schuljahr noch keinen Handlungsbedarf).
     *
     * Einheiten-Hinweis: das Carryover führt unentschuldigte STUNDEN, der
     * 8.2-Schwellenwert zählt VORFÄLLE. Da jeder Vorfall mindestens eine
     * Stunde umfasst, ist der Vergleich Stunden >= Schwellenwert die
     * konservativere (eher anschlagende) Näherung — für einen reinen
     * Hinweis-Badge ausreichend.
     *
     * @param array{krank_percent: float, unentschuldigt_count: int, verspaetet_count: int, schultage_bisher: int, schultage_automatisch: bool} $thresholds
     * @return array{auffaellig: bool, title: string}
     */
    private function vorjahrBadge(?\App\Models\AbsenceCarryover $carryover, array $thresholds): array
    {
        if ($carryover === null) {
            return ['auffaellig' => false, 'title' => ''];
        }

        $hints = [];

        if ($carryover->sick_percent !== null && $carryover->sick_percent >= $thresholds['krank_percent']) {
            $hints[] = 'Krankheit ' . number_format($carryover->sick_percent, 1, ',', '.') . ' %';
        }
        if ($carryover->unexcused_hours >= $thresholds['unentschuldigt_count']) {
            $hints[] = $carryover->unexcused_hours . ' unentschuldigte Std.';
        }
        if ($carryover->tardiness_count >= $thresholds['verspaetet_count']) {
            $hints[] = $carryover->tardiness_count . ' Verspätungen';
        }
        if ($carryover->had_attest_requirement) {
            $hints[] = 'Attestpflicht bestand';
        }

        if ($hints === []) {
            return ['auffaellig' => false, 'title' => ''];
        }

        return [
            'auffaellig' => true,
            'title' => 'Vorjahr (' . $carryover->source_school_year . ') auffällig: ' . implode(', ', $hints),
        ];
    }
}
