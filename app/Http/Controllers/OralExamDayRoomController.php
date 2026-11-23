<?php

namespace App\Http\Controllers;

use App\Services\CurrentExamPeriodService;
use App\Models\OralExamDayRoom;
use App\Models\Room;
use Illuminate\Http\Request;

class OralExamDayRoomController extends Controller
{
    /**
     * Sentinel-Datum für globale Voreinstellungen
     * (kein echter Prüfungstag, wird nur als Default-Marker verwendet)
     */
    private const GLOBAL_DATE = '1900-01-01';

    /**
     * Matrix-Übersicht: Räume × Tage
     */
    public function index()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        // Alle aktiven Räume (für die Auswahl oben)
        $allRooms = Room::where('is_active', true)
            ->orderBy('room_type')
            ->orderBy('room_number')
            ->get();

        // Kolloquiums-Tage aus time_slots, aufgeteilt nach Woche
        $allDates = \App\Models\TimeSlot::where('exam_period_id', $examPeriod->id)
            ->select('date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date');

        $dates1 = $allDates->filter(
            fn($d) => $d->between($examPeriod->week_1_start, $examPeriod->week_1_end)
        )->values();

        $dates2 = $allDates->filter(
            fn($d) => $d->between($examPeriod->week_2_start, $examPeriod->week_2_end)
        )->values();

        // Alle Einträge laden (globale Defaults + tagesspezifische)
        $allEntries = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->with('room')
            ->get();

        // Globale Defaults: [room_id => OralExamDayRoom]
        $globalDefaults = $allEntries
            ->filter(fn($e) => $e->date->format('Y-m-d') === self::GLOBAL_DATE)
            ->keyBy('room_id');

        // Verfügbare Räume = Räume mit globalem Default-Eintrag
        $availableRoomIds = $globalDefaults->keys()->all();

        // Alle aktiven Räume sortiert: erst nach globaler Rolle (exam→prep→waiting→keine),
        // dann nach Raumnummer
        $roleOrder = ['exam' => 0, 'preparation' => 1, 'waiting' => 2];
        $allRooms = $allRooms->sort(function ($a, $b) use ($globalDefaults, $roleOrder) {
            $entryA  = $globalDefaults[$a->id] ?? null;
            $entryB  = $globalDefaults[$b->id] ?? null;
            $roleA   = $roleOrder[$entryA ? $entryA->room_role : ''] ?? 3;
            $roleB   = $roleOrder[$entryB ? $entryB->room_role : ''] ?? 3;
            if ($roleA !== $roleB) return $roleA - $roleB;
            return strcmp($a->room_number, $b->room_number);
        })->values();

        // Tagesspezifische Einträge: [date_string][room_id] => OralExamDayRoom
        $dayEntries = $allEntries
            ->filter(fn($e) => $e->date->format('Y-m-d') !== self::GLOBAL_DATE)
            ->groupBy(fn($e) => $e->date->format('Y-m-d'))
            ->map(fn($g) => $g->keyBy('room_id'));

        return view('planning.rooms', compact(
            'examPeriod',
            'allRooms',
            'dates1',
            'dates2',
            'globalDefaults',
            'availableRoomIds',
            'dayEntries',
        ));
    }

    /**
     * AJAX: Rolle setzen / entfernen
     * POST body: { date, room_id, room_role }
     *   date      = 'global' → globaler Default (Sentinel)
     *             = 'YYYY-MM-DD' → tagesspezifisch
     *   room_role = null → Eintrag löschen
     *
     * Sonderfall: date='global', room_role=null
     *   → globalen Default + ALLE tagesspezifischen Einträge für diesen Raum löschen
     */
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'date'      => 'required|string',
            'room_id'   => 'required|exists:rooms,id',
            'room_role' => 'nullable|in:exam,preparation,waiting,corridor',
        ]);

        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();
        $roomId     = $validated['room_id'];
        $role       = $validated['room_role'] ?? null;
        $dateStr    = $validated['date'] === 'global' ? self::GLOBAL_DATE : $validated['date'];

        if ($role === null) {
            OralExamDayRoom::where([
                'exam_period_id' => $examPeriod->id,
                'date'           => $dateStr,
                'room_id'        => $roomId,
            ])->delete();

            // Globalen Default entfernen → auch alle tagesspezifischen löschen
            if ($dateStr === self::GLOBAL_DATE) {
                OralExamDayRoom::where([
                    'exam_period_id' => $examPeriod->id,
                    'room_id'        => $roomId,
                ])->delete();
            }

            return response()->json(['status' => 'removed']);
        }

        $entry = OralExamDayRoom::updateOrCreate(
            [
                'exam_period_id' => $examPeriod->id,
                'date'           => $dateStr,
                'room_id'        => $roomId,
            ],
            ['room_role' => $role]
        );

        return response()->json([
            'status'    => 'ok',
            'room_role' => $entry->room_role,
        ]);
    }
}
