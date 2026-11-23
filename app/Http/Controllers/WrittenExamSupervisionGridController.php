<?php

namespace App\Http\Controllers;

use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamSupervisionGrid;
use App\Models\WrittenExamSupervisionTimeSlot;
use Illuminate\Http\Request;

class WrittenExamSupervisionGridController extends Controller
{
    /** Alle Raster zurückgeben (JSON) */
    public function index()
    {
        $grids = WrittenExamSupervisionGrid::withCount('timeSlots')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json($grids);
    }

    /** Neues Raster anlegen */
    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'description'         => 'nullable|string|max:500',
            'color'               => 'nullable|string|max:20',
            'is_foreign_language' => 'nullable|boolean',
        ]);

        $grid = WrittenExamSupervisionGrid::create([
            'name'                => $validated['name'],
            'description'         => $validated['description'] ?? null,
            'color'               => $validated['color'] ?? 'blue',
            'is_default'          => false,
            'is_foreign_language' => (bool) ($validated['is_foreign_language'] ?? false),
        ]);

        return response()->json(['success' => true, 'grid' => $grid->loadCount('timeSlots')]);
    }

    /** Raster aktualisieren */
    public function update(Request $request, WrittenExamSupervisionGrid $writtenExamSupervisionGrid)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'description'         => 'nullable|string|max:500',
            'color'               => 'nullable|string|max:20',
            'is_foreign_language' => 'nullable|boolean',
        ]);

        $writtenExamSupervisionGrid->update($validated);

        return response()->json(['success' => true, 'grid' => $writtenExamSupervisionGrid]);
    }

    /** Raster löschen */
    public function destroy(WrittenExamSupervisionGrid $writtenExamSupervisionGrid)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        if ($writtenExamSupervisionGrid->is_default) {
            return response()->json(['success' => false, 'message' => 'Das Standard-Raster kann nicht gelöscht werden'], 422);
        }
        if ($writtenExamSupervisionGrid->timeSlots()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Raster hat noch Zeitslots – bitte zuerst löschen oder umbuchen'], 422);
        }

        $writtenExamSupervisionGrid->delete();

        return response()->json(['success' => true]);
    }

    /** Raster mit allen Zeitslots kopieren */
    public function copy(Request $request, WrittenExamSupervisionGrid $writtenExamSupervisionGrid)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        $newGrid = WrittenExamSupervisionGrid::create([
            'name'        => $validated['name'],
            'description' => $writtenExamSupervisionGrid->description,
            'color'       => $validated['color'] ?? $writtenExamSupervisionGrid->color,
            'is_default'  => false,
        ]);

        // Alle Zeitslots des Quell-Rasters kopieren. Die Nummern beginnen im
        // neuen Raster wieder bei 1 — seit dem 31.08.2026 zaehlt slot_number je
        // Raster. Die Kopie sieht damit aus wie die Vorlage, statt bei einer
        // fortlaufenden globalen Nummer weiterzumachen.
        $nummer = 0;
        /** @var \App\Models\WrittenExamSupervisionTimeSlot $slot */
        foreach ($writtenExamSupervisionGrid->timeSlots()->orderBy('slot_number')->get() as $slot) {
            WrittenExamSupervisionTimeSlot::create([
                'slot_number'                      => ++$nummer,
                'written_exam_supervision_grid_id' => $newGrid->id,
                'start_time'                       => $slot->start_time,
                'end_time'                         => $slot->end_time,
                'label'                            => $slot->label,
                'notes'                            => $slot->notes,
            ]);
        }

        return response()->json([
            'success'        => true,
            'grid'           => $newGrid->loadCount('timeSlots'),
            'slots'          => $newGrid->timeSlots()->orderBy('slot_number')->get(),
        ]);
    }

    /**
     * Raster einem WrittenExamAssignment (Raum) zuweisen.
     * Ein Assignment entspricht genau einem Raum an einem Prüfungstag.
     */
    public function assignRoom(Request $request)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $validated = $request->validate([
            'written_exam_assignment_id'         => 'required|exists:written_exam_assignments,id',
            'written_exam_supervision_grid_id'   => 'required|exists:written_exam_supervision_grids,id',
        ]);

        $assignment = WrittenExamAssignment::findOrFail($validated['written_exam_assignment_id']);
        $assignment->update([
            'written_exam_supervision_grid_id' => $validated['written_exam_supervision_grid_id'],
        ]);

        return response()->json([
            'success' => true,
            'assignment_id' => $assignment->id,
            'grid_id'       => $assignment->written_exam_supervision_grid_id,
        ]);
    }

    /**
     * Zeitslot einem Raster zuweisen (PATCH)
     */
    public function assignTimeSlot(Request $request, WrittenExamSupervisionTimeSlot $writtenExamSupervisionTimeSlot)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $validated = $request->validate([
            'written_exam_supervision_grid_id' => 'required|exists:written_exam_supervision_grids,id',
        ]);

        $writtenExamSupervisionTimeSlot->update($validated);

        return response()->json(['success' => true]);
    }
}
