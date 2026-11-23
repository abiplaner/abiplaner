<?php

namespace App\Http\Controllers;

use App\Services\CurrentExamPeriodService;
use App\Services\PlanningSimulationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SimulationController — Modul 4.6 Optimierungssimulation.
 *
 * Reine Entscheidungsunterstützung: zeigt, welche Ressourcen-Kombinationen
 * (Prüfungstage × Slots/Tag × Räume) machbar und günstig sind. Schreibt NICHTS
 * in den Bestand – das Anlegen echter Slots/Räume erfolgt danach wie gewohnt
 * über die bestehenden Oberflächen (Wilfried, 09.07.2026).
 */
class SimulationController extends Controller
{
    protected function ensureCanPlan(): void
    {
        if (!auth()->user()->isPlanner()) {
            abort(403, 'Sie haben keine Berechtigung für diese Aktion.');
        }
    }

    /**
     * Eingabeformular: schulspezifische Bandbreiten + Nachfrage-Übersicht.
     */
    public function index(PlanningSimulationService $simulation)
    {
        $this->ensureCanPlan();

        $examPeriod = app(CurrentExamPeriodService::class)->current();
        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        $ranges = $simulation->loadRanges();
        $demand = $simulation->analyzeDemand($examPeriod);

        return view('planning.simulation.index', [
            'examPeriod' => $examPeriod,
            'ranges'     => $ranges,
            'demand'     => $demand,
            'result'     => null,
        ]);
    }

    /**
     * Führt die Suche aus und zeigt die drei Vorschläge. Speichert die
     * eingegebenen Bandbreiten als neue schulspezifische Defaults.
     */
    public function run(Request $request, PlanningSimulationService $simulation)
    {
        $this->ensureCanPlan();

        $examPeriod = app(CurrentExamPeriodService::class)->current();
        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        $validated = $request->validate([
            'days_min'    => ['required', 'integer', 'min:1', 'max:60'],
            'days_max'    => ['required', 'integer', 'min:1', 'max:60', 'gte:days_min'],
            'slots_min'   => ['required', 'integer', 'min:1', 'max:20'],
            'slots_max'   => ['required', 'integer', 'min:1', 'max:20', 'gte:slots_min'],
            'rooms_min'   => ['required', 'integer', 'min:1', 'max:50'],
            'rooms_max'   => ['required', 'integer', 'min:1', 'max:50', 'gte:rooms_min'],
        ]);

        $ranges = [
            'days'          => [(int) $validated['days_min'], (int) $validated['days_max']],
            'slots_per_day' => [(int) $validated['slots_min'], (int) $validated['slots_max']],
            'rooms'         => [(int) $validated['rooms_min'], (int) $validated['rooms_max']],
        ];

        // Eingegebene Bandbreiten als schulspezifische Defaults merken.
        $simulation->saveRanges($ranges);

        $result = $simulation->search($examPeriod, $ranges);
        $demand = $result['demand'];

        return view('planning.simulation.index', [
            'examPeriod' => $examPeriod,
            'ranges'     => $ranges,
            'demand'     => $demand,
            'result'     => $result,
        ]);
    }

    /**
     * Vollständiger Report als PDF: Bedarfsanalyse, Vergleich der Vorschläge
     * und je Vorschlag eine exemplarische Belegung (Detailplan).
     *
     * Nutzt die zuletzt gespeicherten Bandbreiten der Schule und führt die
     * Suche erneut aus (der Export ist eine GET-Route; die Vorschläge werden
     * nicht zwischengespeichert). Schreibt nichts in den Bestand.
     */
    public function exportPdf(PlanningSimulationService $simulation)
    {
        $this->ensureCanPlan();

        $examPeriod = app(CurrentExamPeriodService::class)->current();
        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        try {
            $ranges = $simulation->loadRanges();
            $result = $simulation->search($examPeriod, $ranges);

            $pdf = Pdf::loadView('exports.simulation-report', [
                'examPeriod' => $examPeriod,
                'ranges'     => $ranges,
                'demand'     => $result['demand'],
                'result'     => $result,
            ])->setPaper('a4', 'portrait');

            $filename = sprintf(
                'Simulation_Report_%s_%s.pdf',
                str_replace(['/', '\\', ' '], '-', $examPeriod->name),
                now()->format('Y-m-d')
            );

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Fehler beim PDF-Export der Optimierungssimulation', [
                'exam_period_id' => $examPeriod->id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->route('planning.simulation.index')
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }
}
