<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\SupervisionTimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisionTimeSlotController extends Controller
{
    public function index(ExamPeriod $examPeriod)
    {
        $timeSlots = SupervisionTimeSlot::where('exam_period_id', $examPeriod->id)
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get()
            ->groupBy('date');

        return view('supervision-time-slots.index', compact('examPeriod', 'timeSlots'));
    }

    public function store(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'date'                => 'required|date',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i|after:start_time',
            'slot_number'         => 'nullable|integer|min:1',
            'supervision_grid_id' => 'nullable|exists:supervision_grids,id',
        ]);

        if (!isset($validated['slot_number'])) {
            $maxSlot = SupervisionTimeSlot::where('exam_period_id', $examPeriod->id)
                ->where('date', $validated['date'])
                ->max('slot_number');
            $validated['slot_number'] = ($maxSlot ?? 0) + 1;
        }

        SupervisionTimeSlot::create([
            'exam_period_id'      => $examPeriod->id,
            'supervision_grid_id' => $validated['supervision_grid_id'] ?? null,
            'date'                => $validated['date'],
            'start_time'          => $validated['start_time'],
            'end_time'            => $validated['end_time'],
            'slot_number'         => $validated['slot_number'],
        ]);

        return back()->with('success', 'Zeitslot erfolgreich erstellt');
    }

    public function update(Request $request, ExamPeriod $examPeriod, SupervisionTimeSlot $supervisionTimeSlot)
    {
        // Prüfen ob Zeitslot zum ExamPeriod gehört
        if ($supervisionTimeSlot->exam_period_id !== $examPeriod->id) {
            return back()->withErrors(['error' => 'Zeitslot gehört nicht zu diesem Prüfungszeitraum']);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_number' => 'required|integer|min:1',
        ]);

        $supervisionTimeSlot->update($validated);

        return back()->with('success', 'Zeitslot erfolgreich aktualisiert');
    }

    public function destroy(ExamPeriod $examPeriod, SupervisionTimeSlot $supervisionTimeSlot)
    {
        // Prüfen ob Zeitslot zum ExamPeriod gehört
        if ($supervisionTimeSlot->exam_period_id !== $examPeriod->id) {
            return back()->withErrors(['error' => 'Zeitslot gehört nicht zu diesem Prüfungszeitraum']);
        }

        // Prüfen ob Aufsichten zugewiesen sind
        if ($supervisionTimeSlot->supervisions()->count() > 0) {
            return back()->withErrors(['error' => 'Zeitslot kann nicht gelöscht werden, da noch Aufsichten zugewiesen sind']);
        }

        $supervisionTimeSlot->delete();

        return back()->with('success', 'Zeitslot erfolgreich gelöscht');
    }

    /**
     * Generiere Zeitslots für alle Tage des Prüfungszeitraums
     */
    public function generate(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i|after:start_time',
            'duration_minutes'    => 'required|integer|min:15|max:480',
            'week'                => 'required|in:1,2,both',
            'supervision_grid_id' => 'nullable|exists:supervision_grids,id',
        ]);

        try {
            DB::transaction(function() use ($examPeriod, $validated) {
                // Datumsbereiche bestimmen
                $dates = [];
                
                if ($validated['week'] === '1' || $validated['week'] === 'both') {
                    $start = new \DateTime($examPeriod->week_1_start);
                    $end = new \DateTime($examPeriod->week_1_end);
                    while ($start <= $end) {
                        $dates[] = $start->format('Y-m-d');
                        $start->modify('+1 day');
                    }
                }
                
                if ($validated['week'] === '2' || $validated['week'] === 'both') {
                    $start = new \DateTime($examPeriod->week_2_start);
                    $end = new \DateTime($examPeriod->week_2_end);
                    while ($start <= $end) {
                        $dates[] = $start->format('Y-m-d');
                        $start->modify('+1 day');
                    }
                }

                // Zeitslots für jeden Tag generieren
                foreach ($dates as $date) {
                    $currentTime = new \DateTime($validated['start_time']);
                    $endTime = new \DateTime($validated['end_time']);
                    $slotNumber = 1;

                    while ($currentTime < $endTime) {
                        $slotEnd = clone $currentTime;
                        $slotEnd->modify('+' . $validated['duration_minutes'] . ' minutes');

                        if ($slotEnd > $endTime) {
                            $slotEnd = $endTime;
                        }

                        SupervisionTimeSlot::create([
                            'exam_period_id'      => $examPeriod->id,
                            'supervision_grid_id' => $validated['supervision_grid_id'] ?? null,
                            'date'                => $date,
                            'start_time'          => $currentTime->format('H:i'),
                            'end_time'            => $slotEnd->format('H:i'),
                            'slot_number'         => $slotNumber,
                        ]);

                        $currentTime = $slotEnd;
                        $slotNumber++;
                    }
                }
            });

            return back()->with('success', 'Zeitslots erfolgreich generiert');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Generieren: ' . $e->getMessage()]);
        }
    }

    /**
     * Zeitslots von einem Tag auf andere Tage kopieren
     */
    public function copy(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'source_date' => 'required|date',
            'target_dates' => 'required|array',
            'target_dates.*' => 'date',
        ]);

        try {
            DB::transaction(function() use ($examPeriod, $validated) {
                // Quell-Zeitslots holen
                $sourceSlots = SupervisionTimeSlot::where('exam_period_id', $examPeriod->id)
                    ->where('date', $validated['source_date'])
                    ->orderBy('slot_number')
                    ->get();

                if ($sourceSlots->isEmpty()) {
                    throw new \Exception('Keine Zeitslots am Quelldatum gefunden');
                }

                // Auf Ziel-Tage kopieren
                foreach ($validated['target_dates'] as $targetDate) {
                    // Bestehende Zeitslots am Zieldatum löschen (optional)
                    SupervisionTimeSlot::where('exam_period_id', $examPeriod->id)
                        ->where('date', $targetDate)
                        ->whereDoesntHave('supervisions') // Nur leere löschen
                        ->delete();

                    // Kopiere jeden Zeitslot
                    foreach ($sourceSlots as $sourceSlot) {
                        SupervisionTimeSlot::create([
                            'exam_period_id' => $examPeriod->id,
                            'date' => $targetDate,
                            'start_time' => $sourceSlot->start_time,
                            'end_time' => $sourceSlot->end_time,
                            'slot_number' => $sourceSlot->slot_number,
                            'notes' => $sourceSlot->notes,
                        ]);
                    }
                }
            });

            $targetCount = count($validated['target_dates']);
            return back()->with('success', "Zeitslots erfolgreich auf {$targetCount} Tag(e) kopiert");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Kopieren: ' . $e->getMessage()]);
        }
    }

    /**
     * Alle Zeitslots für einen Prüfungszeitraum löschen
     */
    public function destroyAll(ExamPeriod $examPeriod)
    {
        // Prüfen ob Aufsichten zugewiesen sind
        $hasSupervisions = SupervisionTimeSlot::where('exam_period_id', $examPeriod->id)
            ->whereHas('supervisions')
            ->exists();

        if ($hasSupervisions) {
            return back()->withErrors(['error' => 'Zeitslots können nicht gelöscht werden, da noch Aufsichten zugewiesen sind']);
        }

        SupervisionTimeSlot::where('exam_period_id', $examPeriod->id)->delete();

        return back()->with('success', 'Alle Zeitslots erfolgreich gelöscht');
    }
}
