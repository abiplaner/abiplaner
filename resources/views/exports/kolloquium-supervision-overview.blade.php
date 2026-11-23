<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Aufsichtsplan Kolloquium</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',sans-serif; font-size:8pt; line-height:1.35; color:#111; }

@page { size:A4 portrait; margin:0; }

body { margin:10mm 12mm 0 12mm; }

/* Fußzeile – auf jeder Seite */
.page-footer {
    position:fixed; bottom:0; left:0; right:0; height:58mm;
    border-top:1.5px solid #1e3a5f; padding:2.5mm 12mm 2mm 12mm;
    font-size:6.5pt; background:#fff;
}
.notes-grid { display:table; width:100%; table-layout:fixed; }
.notes-col  { display:table-cell; width:33.33%; padding:0 3mm 0 0; vertical-align:top; }
.notes-col:last-child { padding-right:0; }
.notes-label { font-weight:bold; font-size:7pt; margin-bottom:2px; }
.notes-label.gang { color:#6d28d9; }
.notes-label.prep { color:#92400e; }
.notes-label.wait { color:#15803d; }
.notes-text p  { margin:0 0 2px; }
.notes-text ul, .notes-text ol { margin:0 0 2px 12px; }
.footer-meta { margin-top:2mm; font-size:6pt; color:#9ca3af;
               display:table; width:100%; }
.footer-meta-left  { display:table-cell; text-align:left; }
.footer-meta-right { display:table-cell; text-align:right; }

.coverage-warning {
    margin:3px 0 6px; padding:2.5mm 3mm; border:1px solid #f59e0b;
    background:#fffbeb; border-radius:2px; font-size:6.5pt; color:#92400e;
}
.coverage-warning strong { display:block; margin-bottom:1px; font-size:7pt; }
.coverage-warning ul { margin:0 0 0 12px; }

/* Wochenseiten */
.week-page { page-break-after:always; padding-bottom:62mm; }
.week-page:last-child { page-break-after:avoid; }

/* Seitenheader */
.page-header { border-bottom:2px solid #1e3a5f; margin-bottom:5px; padding-bottom:3px; }
.header-row  { display:table; width:100%; }
.header-left { display:table-cell; }
.header-right { display:table-cell; text-align:right;
                color:#666; font-size:7pt; vertical-align:bottom; }
.week-title  { font-size:12pt; font-weight:bold; color:#1e3a5f; }
.week-sub    { font-size:7pt; color:#555; margin-top:1px; }

/* Haupttabelle */
table.plan { width:100%; border-collapse:collapse; font-size:7.5pt; }
table.plan th {
    background:#1e3a5f; color:#fff; padding:4px 5px;
    text-align:center; border:1px solid #1e3a5f; font-size:7pt;
}
table.plan th.tag-th  { width:16%; text-align:left; background:#2d4f7a; }
table.plan th.gang-th { width:28%; background:#5b21b6; }
table.plan th.prep-th { width:28%; background:#92400e; }
table.plan th.wait-th { width:28%; background:#15803d; }
table.plan td { padding:3px 5px; border:1px solid #d1d5db; vertical-align:top; }
table.plan td.tag-td {
    font-weight:bold; font-size:8pt; background:#f0f4fa;
    color:#1e3a5f; vertical-align:middle; text-align:left;
}
.day-name { font-size:8pt; }
.day-date { font-size:6.5pt; color:#555; font-weight:normal; }
.slot-entry { margin-bottom:4px; padding-bottom:3px;
              border-bottom:1px dotted #e5e7eb; }
.slot-entry:last-child { margin-bottom:0; padding-bottom:0; border-bottom:none; }
.slot-time  { font-size:6.5pt; color:#374151; font-weight:bold; }
.slot-room  { font-size:6.5pt; color:#6b7280; }
.teacher    { font-weight:bold; color:#1d4ed8; font-size:8pt; }
.teacher-loc { font-size:6pt; color:#9ca3af; }
.nobody     { color:#d1d5db; font-style:italic; font-size:6.5pt; }
.no-room    { color:#d1d5db; font-style:italic; font-size:6.5pt; }
</style>
</head>
<body>

{{-- PHP-Hilfsfunktionen --}}
@php
$findSup = function(int $slotId, int $roomId, string $type) use ($supervisions) {
    return $supervisions->first(
        fn($s) => $s->time_slot_id     === $slotId
               && $s->room_id          === $roomId
               && $s->supervision_type === $type
    );
};
$freeForSlot = function(int $slotId, $free) {
    return $free->filter(fn($s) => $s->time_slot_id === $slotId);
};
@endphp

{{-- FUSSZEILE (fixed → jede Seite) --}}
<div class="page-footer">
    <div class="notes-grid">
        <div class="notes-col">
            <div class="notes-label gang">🟣 Gangaufsicht</div>
            <div class="notes-text">{!! $notes['gangaufsicht'] ?: '<p>–</p>' !!}</div>
        </div>
        <div class="notes-col">
            <div class="notes-label prep">🟡 Vorbereitungsraum</div>
            <div class="notes-text">{!! $notes['vorbereitung'] ?: '<p>–</p>' !!}</div>
        </div>
        <div class="notes-col">
            <div class="notes-label wait">🟢 Warteraum</div>
            <div class="notes-text">{!! $notes['warteraum'] ?: '<p>–</p>' !!}</div>
        </div>
    </div>
    <div class="footer-meta">
        <span class="footer-meta-left">{{ $schoolName }}</span>
        <span class="footer-meta-right">Erstellt: {{ now()->format('d.m.Y H:i') }} Uhr</span>
    </div>
</div>

{{-- WOCHENSEITEN --}}
@foreach([
    ['dates'=>$week1Dates,'num'=>1,'start'=>$examPeriod->week_1_start,'end'=>$examPeriod->week_1_end],
    ['dates'=>$week2Dates,'num'=>2,'start'=>$examPeriod->week_2_start,'end'=>$examPeriod->week_2_end],
] as $week)
@php $activeDates = $week['dates']->filter(fn($d) => isset($dayBlocks[$d]))->values(); @endphp
@if($activeDates->isEmpty()) @continue @endif
<div class="week-page">

<div class="page-header">
    <div class="header-row">
        <div class="header-left">
            <div class="week-title">Aufsichtsplan Kolloquium &ndash; {{ $week['num'] }}. Prüfungswoche</div>
            <div class="week-sub">
                {{ $week['start']->locale('de')->isoFormat('DD. MMMM') }}
                &ndash; {{ $week['end']->locale('de')->isoFormat('DD. MMMM YYYY') }}
                &nbsp;|&nbsp; {{ $examPeriod->name }}
            </div>
        </div>
        <div class="header-right">{{ $schoolName }}</div>
    </div>
</div>

@if($loop->first && !empty($coverageWarnings))
<div class="coverage-warning">
    <strong>⚠ Aufsichten-Kontrolle: {{ count($coverageWarnings) }} Punkt(e) zu prüfen</strong>
    <ul>
        @foreach($coverageWarnings as $w)
            <li>{{ $w }}</li>
        @endforeach
    </ul>
</div>
@endif

<table class="plan">
<thead>
    <tr>
        <th class="tag-th">Tag</th>
        <th class="gang-th">🟣 Gangaufsicht</th>
        <th class="prep-th">🟡 Vorbereitungsraum</th>
        <th class="wait-th">🟢 Warteraum</th>
    </tr>
</thead>
<tbody>

@foreach($activeDates as $dateStr)
@php
    $carbon   = \Carbon\Carbon::parse($dateStr)->locale('de');
    $day      = $dayBlocks[$dateStr];
    $slots    = $day['slots'];
    $prepRooms     = $day['prepRooms'];
    $waitRooms     = $day['waitRooms'];
    $corridorRooms = $day['corridorRooms'];
    $freeSups      = $day['freeCorridorSups'];
@endphp
<tr>
    {{-- Tag --}}
    <td class="tag-td">
        <div class="day-name">{{ $carbon->isoFormat('dddd') }}</div>
        <div class="day-date">{{ $carbon->isoFormat('DD. MMMM YYYY') }}</div>
    </td>

    {{-- Gangaufsicht --}}
    <td>
        @foreach($slots as $slot)
        <div class="slot-entry">
            <div class="slot-time">{{ substr($slot->prep_start_time,0,5) }}&ndash;{{ substr($slot->exam_end_time,0,5) }} Uhr</div>
            @php
                $gang = collect();
                foreach ($corridorRooms as $odr) {
                    $s = $findSup($slot->id, $odr->room_id, 'corridor');
                    if ($s && $s->teacher) $gang->push(['code'=>$s->teacher->shortcode,'loc'=>$odr->room->room_number]);
                }
                foreach ($freeForSlot($slot->id, $freeSups) as $s) {
                    if ($s->teacher) $gang->push(['code'=>$s->teacher->shortcode,'loc'=>$s->location_text ?: '']);
                }
            @endphp
            @if($gang->isNotEmpty())
                @foreach($gang as $t)
                    <span class="teacher">{{ $t['code'] }}</span>@if($t['loc']) <span class="teacher-loc">({{ $t['loc'] }})</span>@endif
                    @if(!$loop->last) &nbsp; @endif
                @endforeach
            @else
                <span class="nobody">&mdash;</span>
            @endif
        </div>
        @endforeach
    </td>

    {{-- Vorbereitungsraum --}}
    <td>
        @foreach($slots as $slot)
        <div class="slot-entry">
            <div class="slot-time">{{ substr($slot->prep_start_time,0,5) }}&ndash;{{ substr($slot->prep_end_time,0,5) }} Uhr</div>
            @if($prepRooms->isNotEmpty())
                @foreach($prepRooms as $odr)
                    @php $s = $findSup($slot->id, $odr->room_id, 'preparation'); @endphp
                    <span class="slot-room">{{ $odr->room->room_number }}:</span>
                    @if($s && $s->teacher)
                        <span class="teacher">{{ $s->teacher->shortcode }}</span>
                    @else
                        <span class="nobody">&mdash;</span>
                    @endif
                    @if(!$loop->last) &nbsp; @endif
                @endforeach
            @else
                <span class="no-room">kein Raum</span>
            @endif
        </div>
        @endforeach
    </td>

    {{-- Warteraum --}}
    <td>
        @foreach($slots as $slot)
        <div class="slot-entry">
            <div class="slot-time">{{ substr($slot->exam_end_time,0,5) }} Uhr</div>
            @if($waitRooms->isNotEmpty())
                @foreach($waitRooms as $odr)
                    @php $s = $findSup($slot->id, $odr->room_id, 'waiting'); @endphp
                    <span class="slot-room">{{ $odr->room->room_number }}:</span>
                    @if($s && $s->teacher)
                        <span class="teacher">{{ $s->teacher->shortcode }}</span>
                    @else
                        <span class="nobody">&mdash;</span>
                    @endif
                    @if(!$loop->last) &nbsp; @endif
                @endforeach
            @else
                <span class="no-room">kein Raum</span>
            @endif
        </div>
        @endforeach
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>{{-- .week-page --}}
@endforeach

</body>
</html>
