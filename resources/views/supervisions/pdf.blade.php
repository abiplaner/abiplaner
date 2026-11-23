<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Aufsichtsplan - {{ $examPeriod->name }}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',sans-serif; font-size:8pt; line-height:1.3; }

/* Jeder Tag auf einer eigenen Seite */
.day-page { page-break-after: always; }
.day-page:last-child { page-break-after: avoid; }

.page-header { border-bottom:2px solid #1f2937; margin-bottom:6px; padding-bottom:4px; }
.page-header-top { display:flex; justify-content:space-between; align-items:baseline; }
.school-name { font-size:8pt; color:#6b7280; }
.period-name { font-size:8pt; color:#6b7280; }
.day-title { font-size:13pt; font-weight:bold; color:#111827; margin-top:2px; }

/* Raster-Abschnitt */
.grid-section { margin-bottom:8px; }
.grid-header {
    font-size:8pt; font-weight:bold; padding:3px 6px;
    margin-bottom:3px; border-left:4px solid;
    display:flex; align-items:center; gap:6px;
}
.grid-header.blue   { background:#eff6ff; border-color:#3b82f6; color:#1d4ed8; }
.grid-header.green  { background:#f0fdf4; border-color:#22c55e; color:#15803d; }
.grid-header.orange { background:#fff7ed; border-color:#f97316; color:#c2410c; }
.grid-header.purple { background:#faf5ff; border-color:#a855f7; color:#7e22ce; }
.grid-header.red    { background:#fef2f2; border-color:#ef4444; color:#b91c1c; }
.grid-header.yellow { background:#fefce8; border-color:#eab308; color:#854d0e; }

table { width:100%; border-collapse:collapse; font-size:7pt; margin-bottom:4px; }
th { background:#374151; color:white; padding:3px 4px; text-align:left;
     border:1px solid #1f2937; font-size:7pt; white-space:nowrap; }
th.time-th { width:52px; text-align:center; }
td { padding:2px 3px; border:1px solid #d1d5db; vertical-align:top; }
td.time-td { width:52px; font-weight:bold; background:#f9fafb; text-align:center;
              font-size:7.5pt; white-space:nowrap; }

.room-block { margin-bottom:2px; }
.room-label { font-size:6pt; color:#6b7280; font-weight:bold; }
.teacher-code { font-size:8pt; font-weight:bold; color:#1d4ed8; }
.empty { color:#9ca3af; font-style:italic; font-size:6pt; }
.no-match { color:#d1d5db; text-align:center; font-size:7pt; }

/* Nicht-Prüfungsräume unten */
.aux-section { margin-top:6px; }
.aux-header { font-size:8pt; font-weight:bold; color:#374151;
              padding:2px 4px; background:#f3f4f6; margin-bottom:3px; }

.footer { position:fixed; bottom:0; width:100%; text-align:right;
          font-size:6.5pt; color:#9ca3af; border-top:1px solid #e5e7eb; padding:2px 4px; }

@page { size: A4 landscape; margin:10mm 10mm 14mm 10mm; }
</style>
</head>
<body>

@php
    use Carbon\Carbon;

    $dayNames   = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    $allDates   = collect(array_merge($week1Dates->toArray(), $week2Dates->toArray()));
    $examRooms  = $rooms->where('room_type','exam');
    $auxTypes   = ['preparation'=>'Vorbereitung','waiting'=>'Warteraum','lockdown'=>'Absperrung','corridor'=>'Gang'];

    // Fallback: wenn Raum kein Raster hat → Standard-Raster
    $getGridIdForRoom = function(int $roomId, string $date) use ($roomGridAssignments, $defaultGridId): ?int {
        return $roomGridAssignments[$roomId . '_' . $date] ?? $defaultGridId;
    };
@endphp

@foreach($allDates as $date)
@php
    $dateObj      = Carbon::parse($date);
    $dayLabel     = $dayNames[$dateObj->dayOfWeek] . ', ' . $dateObj->format('d.m.Y');
    $slotsForDate = $timeSlots->where('date', $date)->sortBy('slot_number');
    $weekNum      = $week1Dates->contains($date) ? 1 : 2;
@endphp
<div class="day-page">

    {{-- Seitenkopf --}}
    <div class="page-header">
        <div class="page-header-top">
            <span class="school-name">{{ $schoolName }}</span>
            <span class="period-name">{{ $examPeriod->name }} &nbsp;·&nbsp; Woche {{ $weekNum }}</span>
        </div>
        <div class="day-title">Aufsichtsplan &nbsp;–&nbsp; {{ $dayLabel }}</div>
    </div>

    {{-- Prüfungsräume: Pro Raster eine Tabelle --}}
    @foreach($grids as $grid)
    @php
        // Welche Prüfungsräume haben heute dieses Raster?
        $gridRooms = $examRooms->filter(
            fn($r) => $getGridIdForRoom($r->id, $date) === $grid->id
        );
        // Welche Slots gehören zu diesem Raster?
        $gridSlots = $slotsForDate->filter(
            fn($s) => ($s->supervision_grid_id ?? $defaultGridId) === $grid->id
        );
        if ($gridRooms->isEmpty() && $gridSlots->isEmpty()) continue;
    @endphp
    <div class="grid-section">
        <div class="grid-header {{ $grid->color ?? 'blue' }}">
            {{ $grid->name }}
            @if($grid->is_default)<span style="font-weight:normal;font-size:6.5pt;">(Standard)</span>@endif
        </div>

        @if($gridSlots->isNotEmpty() && $gridRooms->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th class="time-th">Zeit</th>
                    @foreach($gridRooms as $room)
                        <th>{{ $room->room_number }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($gridSlots as $slot)
                <tr>
                    <td class="time-td">{{ substr($slot->start_time,0,5) }}<br>–<br>{{ substr($slot->end_time,0,5) }}</td>
                    @foreach($gridRooms as $room)
                    <td>
                        @php
                            $sup = $slot->supervisions
                                ->where('room_id', $room->id)
                                ->where('supervision_type', 'exam')
                                ->first();
                        @endphp
                        @if($sup?->teacher)
                            <span class="teacher-code">{{ $sup->teacher->shortcode }}</span>
                        @else
                            <span class="empty">—</span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @elseif($gridRooms->isEmpty())
            <p style="font-size:7pt;color:#6b7280;padding:2px 4px;">Keine Räume für dieses Raster an diesem Tag zugewiesen.</p>
        @else
            <p style="font-size:7pt;color:#6b7280;padding:2px 4px;">Keine Zeitslots für dieses Raster definiert.</p>
        @endif
    </div>
    @endforeach

    {{-- Nicht-Prüfungsräume (Vorbereitung, Warteraum, etc.) --}}
    @php
        $hasAux = false;
        foreach(array_keys($auxTypes) as $t) {
            if($rooms->where('room_type',$t)->count() > 0) { $hasAux = true; break; }
        }
    @endphp
    @if($hasAux)
    <div class="aux-section">
        <div class="aux-header">Weitere Aufsichten</div>
        <table>
            <thead>
                <tr>
                    <th class="time-th">Zeit</th>
                    @foreach($auxTypes as $type => $label)
                        @if($rooms->where('room_type',$type)->count() > 0)
                            @foreach($rooms->where('room_type',$type) as $room)
                                <th>{{ $label }}: {{ $room->room_number }}</th>
                            @endforeach
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($slotsForDate as $slot)
                <tr>
                    <td class="time-td">{{ substr($slot->start_time,0,5) }}<br>–<br>{{ substr($slot->end_time,0,5) }}</td>
                    @foreach($auxTypes as $type => $label)
                        @foreach($rooms->where('room_type',$type) as $room)
                        <td>
                            @php $sup = $slot->supervisions->where('room_id',$room->id)->where('supervision_type',$type)->first(); @endphp
                            @if($sup?->teacher)
                                <span class="teacher-code">{{ $sup->teacher->shortcode }}</span>
                            @else
                                <span class="empty">—</span>
                            @endif
                        </td>
                        @endforeach
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>{{-- .day-page --}}
@endforeach

<div class="footer">Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr</div>
</body>
</html>
