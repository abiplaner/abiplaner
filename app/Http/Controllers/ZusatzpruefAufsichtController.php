<?php

namespace App\Http\Controllers;

use App\Models\ZusatzpruefAufsicht;
use Illuminate\Http\Request;

class ZusatzpruefAufsichtController extends Controller
{
    /**
     * Aufsicht anlegen oder aktualisieren (eine pro Slot + Raum).
     * teacher_id leer/null → bestehenden Eintrag löschen.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'zusatzpruef_day_id'          => 'required|exists:zusatzpruef_days,id',
            'zusatzpruef_day_room_id'     => 'required|exists:zusatzpruef_day_rooms,id',
            'zusatzpruef_day_timeslot_id' => 'required|exists:zusatzpruef_day_timeslots,id',
            'teacher_id'                  => 'nullable|exists:teachers,id',
        ]);

        $key = [
            'zusatzpruef_day_timeslot_id' => $validated['zusatzpruef_day_timeslot_id'],
            'zusatzpruef_day_room_id'     => $validated['zusatzpruef_day_room_id'],
        ];

        if (empty($validated['teacher_id'])) {
            ZusatzpruefAufsicht::where($key)->delete();
            return response()->json(['id' => null, 'teacher_id' => null, 'label' => '']);
        }

        $aufsicht = ZusatzpruefAufsicht::updateOrCreate($key, [
            'zusatzpruef_day_id' => $validated['zusatzpruef_day_id'],
            'teacher_id'         => $validated['teacher_id'],
        ]);

        $aufsicht->load('teacher');
        $label = trim(($aufsicht->teacher->shortcode ?? '') . ' ' . ($aufsicht->teacher->lastname ?? ''));

        return response()->json([
            'id'         => $aufsicht->id,
            'teacher_id' => $aufsicht->teacher_id,
            'label'      => $label,
        ]);
    }

    public function destroy(ZusatzpruefAufsicht $zusatzpruefAufsicht)
    {
        $zusatzpruefAufsicht->delete();
        return response()->json(['success' => true]);
    }
}
