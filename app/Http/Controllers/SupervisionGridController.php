<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\Room;
use App\Models\RoomSupervisionGrid;
use App\Models\SupervisionGrid;
use App\Models\SupervisionTimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisionGridController extends Controller
{
    /**
     * Alle Raster für einen Prüfungszeitraum zurückgeben (JSON für Alpine.js)
     */
    public function index(ExamPeriod $examPeriod)
    {
        $grids = SupervisionGrid::where('exam_period_id', $examPeriod->id)
            ->withCount('timeSlots')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json($grids);
    }

    /**
     * Neues Raster anlegen
     */
    public function store(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|max:20',
            'is_default'  => 'boolean',
        ]);

        $grid = SupervisionGrid::create([
            'exam_period_id' => $examPeriod->id,
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'color'          => $validated['color'] ?? 'blue',
            'is_default'     => $validated['is_default'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'grid'    => $grid,
        ]);
    }

    /**
     * Raster aktualisieren
     */
    public function update(Request $request, ExamPeriod $examPeriod, SupervisionGrid $supervisionGrid)
    {
        if ($supervisionGrid->exam_period_id !== $examPeriod->id) {
            return response()->json(['success' => false, 'message' => 'Raster gehört nicht zu diesem Prüfungszeitraum'], 422);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|max:20',
        ]);

        $supervisionGrid->update($validated);

        return response()->json(['success' => true, 'grid' => $supervisionGrid]);
    }

    /**
     * Raster löschen (nur wenn keine Zeitslots zugewiesen)
     */
    public function destroy(ExamPeriod $examPeriod, SupervisionGrid $supervisionGrid)
    {
        if ($supervisionGrid->exam_period_id !== $examPeriod->id) {
            return response()->json(['success' => false, 'message' => 'Raster gehört nicht zu diesem Prüfungszeitraum'], 422);
        }

        if ($supervisionGrid->is_default) {
            return response()->json(['success' => false, 'message' => 'Das Standard-Raster kann nicht gelöscht werden'], 422);
        }

        if ($supervisionGrid->timeSlots()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Raster hat noch Zeitslots und kann nicht gelöscht werden'], 422);
        }

        $supervisionGrid->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Raum-Raster-Zuweisung für einen bestimmten Tag setzen
     */
    public function assignRoom(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'room_id'             => 'required|exists:rooms,id',
            'date'                => 'required|date',
            'supervision_grid_id' => 'required|exists:supervision_grids,id',
        ]);

        // Sicherstellen, dass das Raster zum ExamPeriod gehört
        $grid = SupervisionGrid::findOrFail($validated['supervision_grid_id']);
        if ($grid->exam_period_id !== $examPeriod->id) {
            return response()->json(['success' => false, 'message' => 'Raster gehört nicht zu diesem Prüfungszeitraum'], 422);
        }

        $assignment = RoomSupervisionGrid::updateOrCreate(
            [
                'room_id' => $validated['room_id'],
                'date'    => $validated['date'],
            ],
            ['supervision_grid_id' => $validated['supervision_grid_id']]
        );

        return response()->json([
            'success'    => true,
            'assignment' => $assignment->load('supervisionGrid'),
        ]);
    }

    /**
     * Alle Raum-Raster-Zuweisungen für eine Woche zurückgeben (JSON)
     * Gibt Map zurück: { "room_id_date": grid_id }
     */
    public function getRoomAssignments(Request $request, ExamPeriod $examPeriod)
    {
        $request->validate([
            'dates' => 'required|array',
            'dates.*' => 'date',
        ]);

        $assignments = RoomSupervisionGrid::whereIn('date', $request->input('dates'))
            ->whereHas('supervisionGrid', fn($q) => $q->where('exam_period_id', $examPeriod->id))
            ->with('supervisionGrid')
            ->get()
            ->mapWithKeys(fn($a) => [
                $a->room_id . '_' . $a->date->format('Y-m-d') => [
                    'grid_id'    => $a->supervision_grid_id,
                    'grid_name'  => $a->supervisionGrid->name,
                    'grid_color' => $a->supervisionGrid->color,
                ]
            ]);

        return response()->json($assignments);
    }
}
