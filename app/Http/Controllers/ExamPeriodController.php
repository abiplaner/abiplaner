<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Services\ActivityLogger;
use App\Services\CurrentExamPeriodService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExamPeriodController extends Controller
{
    use AuthorizesRequests;

    /**
     * Perioden-Umschalter (Modul 1.1): gewählte Periode für den
     * angemeldeten Nutzer in der Session speichern.
     */
    public function switchPeriod(Request $request, CurrentExamPeriodService $periodService)
    {
        $validated = $request->validate([
            'exam_period_id' => 'required|integer',
        ]);

        $examPeriod = ExamPeriod::findOrFail((int) $validated['exam_period_id']);

        $periodService->switchTo($examPeriod);

        if ($periodService->isArchiveView()) {
            return redirect()->route('dashboard')
                ->with('info', "Archivansicht Abitur {$examPeriod->year} — schreibgeschützt.");
        }

        return redirect()->route('dashboard')
            ->with('success', "Prüfungsperiode Abitur {$examPeriod->year} ausgewählt.");
    }

    /**
     * Perioden-Umschalter: zurück zur aktuellen (neuesten) Periode.
     */
    public function switchReset(CurrentExamPeriodService $periodService)
    {
        $periodService->reset();

        $current = $periodService->current();
        $label = $current !== null ? "Abitur {$current->year}" : 'aktuelle Periode';

        return redirect()->route('dashboard')
            ->with('success', "Zurück zur aktuellen Periode ({$label}).");
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', ExamPeriod::class);
        
        $examPeriods = ExamPeriod::latest('year')
            ->latest('created_at')
            ->paginate(15);
        
        return view('admin.exam-periods.index', compact('examPeriods'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', ExamPeriod::class);
        
        return view('exam-periods.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', ExamPeriod::class);
        
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'year' => 'required|integer|min:2024|max:2099',
            'week_1_start' => 'required|date',
            'week_1_end' => 'required|date|after:week_1_start',
            'week_2_start' => 'required|date|after:week_1_end',
            'week_2_end' => 'required|date|after:week_2_start',
        ]);
        
        $examPeriod = ExamPeriod::create($validated);
        
        ActivityLogger::log(
            'exam_period_created',
            'ExamPeriod',
            $examPeriod->id,
            "Prüfungszeitraum '{$examPeriod->name}' erstellt"
        );
        
        return redirect()
            ->route('admin.exam-periods.index')
            ->with('success', 'Prüfungszeitraum erfolgreich erstellt!');
    }

    // show() ist am 30.08.2026 entfallen — mit der Route und der Maske
    // exam-periods/show.blade.php.
    //
    // Die Methode lud Mandanten-Beziehungen (students, courses, timeSlots,
    // conflicts) und las auth()->user()->school. Erreichbar war sie nur ueber
    // admin.exam-periods.show, und der Super-Admin-Bereich laeuft ohne
    // Mandanten: Der Aufruf endete in "Database connection [tenant] not
    // configured", die Maske ausserdem in einer RouteNotFoundException auf
    // exam-periods.index. Was zu einem Zeitraum einer Schule gehoert, steht im
    // Dashboard und in der Planung des Mandanten.

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ExamPeriod $examPeriod)
    {
        $this->authorize('update', $examPeriod);
        
        return view('exam-periods.edit', compact('examPeriod'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExamPeriod $examPeriod)
    {
        $this->authorize('update', $examPeriod);
        
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'year' => 'required|integer|min:2024|max:2099',
            'week_1_start' => 'required|date',
            'week_1_end' => 'required|date|after:week_1_start',
            'week_2_start' => 'required|date|after:week_1_end',
            'week_2_end' => 'required|date|after:week_2_start',
        ]);
        
        $examPeriod->update($validated);
        
        ActivityLogger::log(
            'exam_period_updated',
            'ExamPeriod',
            $examPeriod->id,
            "Prüfungszeitraum '{$examPeriod->name}' aktualisiert"
        );
        
        return redirect()
            ->route('admin.exam-periods.index')
            ->with('success', 'Prüfungszeitraum erfolgreich aktualisiert!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExamPeriod $examPeriod)
    {
        $this->authorize('delete', $examPeriod);
        
        $name = $examPeriod->name;
        $examPeriod->delete();
        
        ActivityLogger::log(
            'exam_period_deleted',
            'ExamPeriod',
            $examPeriod->id,
            "Prüfungszeitraum '{$name}' gelöscht"
        );
        
        return redirect()
            ->route('admin.exam-periods.index')
            ->with('success', 'Prüfungszeitraum erfolgreich gelöscht!');
    }
    
    /**
     * Manuelles Neu-Generieren der Zeitslots
     * 
     * TODO: Diese Funktion benötigt schulspezifische Konfiguration für:
     * - prep_start_time (Startzeit der Vorbereitungsräume)
     * - exams_per_day (Anzahl Prüfungen pro Tag)
     * - break_duration_minutes (Pausenzeit zwischen Slots)
     * Diese Werte müssen entweder in School oder einer separaten Konfigurationstabelle gespeichert werden.
     */
    public function regenerateTimeSlots(ExamPeriod $examPeriod)
    {
        // Der frühere Rumpf stand hier auskommentiert und ist am 30.08.2026
        // entfallen: Er las students.exam_period_id und rief
        // $examPeriod->generateTimeSlots() — beides gibt es nicht mehr. Wer die
        // Generierung wiederbelebt, schreibt sie gegen das heutige Schema neu,
        // nicht gegen diesen Stand. Der Weg über die Slot-Vorlagen ist der
        // lebende.
        return redirect()
            ->back()
            ->with('error', 'Die automatische Zeitslot-Generierung ist momentan deaktiviert. Bitte erstellen Sie Zeitslots manuell.');
    }
    
    /**
     * Aktiviere einen Prüfungszeitraum (setze alle anderen auf inaktiv)
     * DEPRECATED: Da exam_periods jetzt global sind ohne status, ist diese Methode nicht mehr relevant.
     * Der neueste ExamPeriod wird automatisch als aktiv betrachtet.
     */
    public function activate(ExamPeriod $examPeriod)
    {
        $this->authorize('update', $examPeriod);
        
        // Keine Aktion mehr nötig - der neueste ExamPeriod gilt als aktiv
        
        ActivityLogger::log(
            'exam_period_activated',
            'ExamPeriod',
            $examPeriod->id,
            "Prüfungszeitraum '{$examPeriod->name}' aktiviert"
        );
        
        return redirect()
            ->back()
            ->with('success', "Prüfungszeitraum '{$examPeriod->name}' wurde aktiviert!");
    }
}
