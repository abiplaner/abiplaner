<?php

namespace App\Http\Controllers;

use App\Services\CurrentExamPeriodService;
use App\Models\Room;
use App\Models\ZusatzpruefAssignment;
use App\Models\ZusatzpruefDay;
use App\Models\ZusatzpruefDayRoom;
use App\Models\ZusatzpruefDayTimeslot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZusatzpruefDayController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'       => 'required|date',
            'label'      => 'nullable|string|max:100',
            'start_time' => 'required|date_format:H:i',
        ]);

        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $day = ZusatzpruefDay::create([
            'exam_period_id' => $examPeriod->id,
            'date'           => $validated['date'],
            'label'          => $validated['label'] ?? null,
            'start_time'     => $validated['start_time'],
        ]);

        return back()->with('success', 'Tag angelegt.');
    }

    public function update(Request $request, ZusatzpruefDay $zusatzpruefDay)
    {
        $validated = $request->validate([
            'label'      => 'nullable|string|max:100',
            'start_time' => 'required|date_format:H:i',
        ]);

        $zusatzpruefDay->update($validated);

        return back()->with('success', 'Tag aktualisiert.');
    }

    public function destroy(ZusatzpruefDay $zusatzpruefDay)
    {
        // Nur löschen wenn keine Prüfungen platziert sind
        $placed = $zusatzpruefDay->assignments()->exists();
        if ($placed) {
            return back()->with('error', 'Tag kann nicht gelöscht werden – es sind noch Prüfungen platziert.');
        }

        $zusatzpruefDay->delete();
        return back()->with('success', 'Tag gelöscht.');
    }

    /**
     * Prüfungsräume + Vorbereitungsräume für einen Tag setzen.
     * body: { exam_room_ids: [1, 2, ...], prep_room_ids: [3, 4, ...]|null }
     *
     * Speichert für jeden Prüfungsraum eine Zeile; hat der Tag mehrere
     * Vorbereitungsräume, werden alle Kombinationen angelegt (kartesisches
     * Produkt). Hat er keinen, wird prep_room_id = null gesetzt.
     */
    public function setRooms(Request $request, ZusatzpruefDay $zusatzpruefDay)
    {
        $validated = $request->validate([
            'exam_room_ids'   => 'required|array|min:1',
            'exam_room_ids.*' => 'exists:rooms,id',
            'prep_room_ids'   => 'nullable|array',
            'prep_room_ids.*' => 'exists:rooms,id',
        ]);

        $examRoomIds = $validated['exam_room_ids'];
        $prepRoomIds = $validated['prep_room_ids'] ?? [];

        try {
            DB::transaction(function () use ($zusatzpruefDay, $examRoomIds, $prepRoomIds) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, ZusatzpruefDayRoom> $existing */
                $existing = $zusatzpruefDay->dayRooms()->get();

                $sharedPrepId       = !empty($prepRoomIds) ? (int) $prepRoomIds[0] : null;
                $handledExamRoomIds = collect();

                foreach ($existing as $row) {
                    if (in_array((int) $row->exam_room_id, array_map('intval', $examRoomIds))) {
                        if ($row->prep_room_id !== $sharedPrepId) {
                            $row->update(['prep_room_id' => $sharedPrepId]);
                        }
                        $handledExamRoomIds->push((int) $row->exam_room_id);
                    } else {
                        // Aufsichten-Check vor Löschung
                        if ($row->aufsichten()->exists()) {
                            throw new \RuntimeException(
                                'Raum ' . ($row->examRoom->room_number ?? $row->exam_room_id)
                                . ' kann nicht entfernt werden – es sind noch Aufsichten eingetragen.'
                            );
                        }
                        /** @var \Illuminate\Database\Eloquent\Collection<int, ZusatzpruefAssignment> $assignments */
                        $assignments = $row->assignments()->with('zusatzpruefung')->get();
                        foreach ($assignments as $assignment) {
                            $assignment->zusatzpruefung->update(['status' => 'pending']);
                            $assignment->delete();
                        }
                        $row->delete();
                    }
                }

                foreach ($examRoomIds as $i => $examRoomId) {
                    if (!$handledExamRoomIds->contains((int) $examRoomId)) {
                        $zusatzpruefDay->dayRooms()->create([
                            'exam_room_id' => (int) $examRoomId,
                            'prep_room_id' => $sharedPrepId,
                            'sort_order'   => $i,
                        ]);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Räume gespeichert.');
    }

    /**
     * Zeitslot für einen Tag anlegen.
     * POST /zusatzpruefungen/days/{day}/timeslots
     * body: { start_time: 'HH:MM' }
     */
    public function storeTimeslot(Request $request, ZusatzpruefDay $zusatzpruefDay): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'start_time' => 'required|date_format:H:i',
        ]);

        $zusatzpruefDay->timeslots()->firstOrCreate([
            'start_time' => $validated['start_time'],
        ]);

        return back()->with('success', 'Zeitslot angelegt.')->with('reopen_timeslot_modal', true);
    }

    /**
     * Zeitslot löschen — nur möglich, wenn keine Prüfung auf diesen Slot zeigt.
     * DELETE /zusatzpruefungen/days/timeslots/{timeslot}
     */
    public function destroyTimeslot(ZusatzpruefDayTimeslot $timeslot): \Illuminate\Http\RedirectResponse
    {
        $inUse = ZusatzpruefAssignment::where('zusatzpruef_day_id', $timeslot->zusatzpruef_day_id)
            ->where('start_time', $timeslot->start_time)
            ->exists();

        if ($inUse) {
            return back()->with('error', 'Zeitslot kann nicht gelöscht werden – es sind noch Prüfungen platziert.')
                         ->with('reopen_timeslot_modal', true);
        }

        // Aufsichten für diesen Slot prüfen
        if ($timeslot->aufsichten()->exists()) {
            return back()->with('error', 'Zeitslot kann nicht gelöscht werden – es sind noch Aufsichten eingetragen. Bitte zuerst die Aufsichten entfernen.')
                         ->with('reopen_timeslot_modal', true);
        }

        try {
            $timeslot->delete();
        } catch (\Exception) {
            return back()->with('error', 'Zeitslot kann nicht gelöscht werden – es sind noch abhängige Einträge vorhanden.')
                         ->with('reopen_timeslot_modal', true);
        }

        return back()->with('success', 'Zeitslot gelöscht.')->with('reopen_timeslot_modal', true);
    }
}