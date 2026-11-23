<?php

namespace App\Http\Controllers;

use App\Models\WrittenExamSupervisionGrid;
use App\Models\WrittenExamSupervisionTimeSlot;
use Illuminate\Http\Request;

class WrittenExamSupervisionTimeSlotController extends Controller
{
    /**
     * Alle Zeitslots für schriftliche Prüfungs-Aufsichten anzeigen.
     */
    public function index()
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        $timeSlots = WrittenExamSupervisionTimeSlot::with('grid')
            ->orderBy('slot_number')->get();

        $grids = WrittenExamSupervisionGrid::orderByDesc('is_default')
            ->orderBy('name')->get();

        return view('written-exams.supervisions.time-slots', compact('timeSlots', 'grids'));
    }

    /**
     * Neuen Zeitslot anlegen.
     */
    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $validated = $request->validate([
            'start_time'                       => 'required|date_format:H:i',
            'end_time'                         => 'required|date_format:H:i|after:start_time',
            'label'                            => 'nullable|string|max:100',
            'notes'                            => 'nullable|string|max:255',
            'written_exam_supervision_grid_id' => 'nullable|exists:written_exam_supervision_grids,id',
        ]);

        // Die Nummer zaehlt innerhalb des Rasters, nicht ueber alle hinweg.
        // Global war sie nur, weil der UNIQUE-Index es erzwang — der stammte
        // aus der Zeit vor den Rastern (behoben 31.08.2026).
        $maxSlot = WrittenExamSupervisionTimeSlot::query()
            ->where('written_exam_supervision_grid_id', $validated['written_exam_supervision_grid_id'] ?? null)
            ->max('slot_number');

        $validated['slot_number'] = ($maxSlot ?? 0) + 1;

        WrittenExamSupervisionTimeSlot::create($validated);

        return back()->with('success', 'Zeitslot erfolgreich angelegt.');
    }

    /**
     * Zeitslot aktualisieren.
     */
    public function update(Request $request, WrittenExamSupervisionTimeSlot $writtenExamSupervisionTimeSlot)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $validated = $request->validate([
            'start_time'                       => 'required|date_format:H:i',
            'end_time'                         => 'required|date_format:H:i|after:start_time',
            'label'                            => 'nullable|string|max:100',
            'notes'                            => 'nullable|string|max:255',
            'written_exam_supervision_grid_id' => 'nullable|exists:written_exam_supervision_grids,id',
        ]);

        $writtenExamSupervisionTimeSlot->update($validated);

        return back()->with('success', 'Zeitslot aktualisiert.');
    }

    /**
     * Zeitslot löschen und Nummerierung neu vergeben.
     */
    public function destroy(WrittenExamSupervisionTimeSlot $writtenExamSupervisionTimeSlot)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $raster = $writtenExamSupervisionTimeSlot->written_exam_supervision_grid_id;

        $writtenExamSupervisionTimeSlot->delete();

        // Nur das betroffene Raster neu durchnummerieren. Vorher lief die
        // Schleife ueber alle Slots aller Raster — ein Loeschen im ersten
        // Raster verschob damit die Nummern im zweiten. (31.08.2026)
        WrittenExamSupervisionTimeSlot::query()
            ->where('written_exam_supervision_grid_id', $raster)
            ->orderBy('slot_number')
            ->get()
            ->each(function ($slot, $index) {
                $slot->update(['slot_number' => $index + 1]);
            });

        return back()->with('success', 'Zeitslot gelöscht.');
    }
}
