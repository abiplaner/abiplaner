<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Sitzplan - Raum {{ $room->room_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: 1.3; color: #333; margin: 8px; }
        .header { text-align: center; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 2px solid #444; }
        .header h1 { font-size: 13pt; margin: 0 0 2px 0; color: #000; }
        .header .sub { font-size: 8pt; color: #555; margin: 1px 0; }
        .info-box { background: #f9f9f9; border: 1px solid #ddd; padding: 3px 8px; margin-bottom: 5px; border-radius: 3px; }
        .info-box p { margin: 1px 0; font-size: 8pt; }
        .course-legend { margin-bottom: 5px; padding: 6px 10px; background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 3px; font-size: 10pt; }
        .course-legend table { border-collapse: collapse; width: 100%; }
        .course-legend td { border: none; padding: 0; vertical-align: middle; }
        .course-legend .label-cell { white-space: nowrap; padding-right: 8px; font-weight: bold; font-size: 10pt; }
        .course-badge { display: inline-block; padding: 4px 12px; margin: 2px 3px; border-radius: 4px; font-size: 10pt; font-weight: bold; vertical-align: middle; }
        .hv-badge { display: inline-block; padding: 1px 4px; margin-left: 3px; border-radius: 3px;
                    font-size: 6.5pt; font-weight: bold; background: #ccfbf1; color: #0f766e; border: 1px solid #5eead4; }
        .course-blue   { background:#dbeafe; color:#1e40af; border:1px solid #60a5fa; }
        .course-green  { background:#d1fae5; color:#065f46; border:1px solid #34d399; }
        .course-purple { background:#e9d5ff; color:#6b21a8; border:1px solid #a78bfa; }
        .course-orange { background:#fed7aa; color:#9a3412; border:1px solid #fb923c; }
        .course-pink   { background:#fce7f3; color:#9d174d; border:1px solid #f472b6; }
        .course-indigo { background:#e0e7ff; color:#3730a3; border:1px solid #818cf8; }
        .exam-info { font-size: 13pt; font-weight: bold; color: #000; margin: 0 0 4px 0; }
        .view-title { font-size: 10pt; font-weight: bold; color: #1e40af; margin: 6px 0 3px 0;
                      padding: 2px 6px; background: #eff6ff; border-left: 3px solid #1e40af; }
        .board-box { text-align:center; padding:2px 4px; background:#1e3a8a; color:#fff;
                     font-weight:bold; font-size:7.5pt; border-radius:3px; margin-bottom:3px; }
        .door-box  { text-align:center; padding:2px 4px; background:#6b7280; color:#fff;
                     font-weight:bold; font-size:7.5pt; border-radius:3px; margin-top:3px; }
        .hint { text-align:center; font-size:6.5pt; color:#888; margin:2px 0 4px 0; font-style:italic; }
        .seating-table { border-collapse: separate; border-spacing: 20px 30px; width: 100%; page-break-inside: avoid; }
        .seating-table td { text-align: center; vertical-align: middle; padding: 2px 1px;
                            font-size: 6.5pt; border: 1px solid #ddd; border-radius: 2px;
                            height: 48px; overflow: hidden; }
        .seat-nr  { font-size: 5.5pt; color: #888; font-weight: bold; }
        .s-name   { font-size: 6.5pt; font-weight: bold; line-height: 1.2; }
        .s-course { font-size: 5.5pt; color: #555; margin-top: 1px; }
        td.available    { background:#f0fdf4; border-color:#86efac; }
        td.occ-blue     { background:#dbeafe; border-color:#60a5fa; }
        td.occ-green    { background:#d1fae5; border-color:#34d399; }
        td.occ-purple   { background:#e9d5ff; border-color:#a78bfa; }
        td.occ-orange   { background:#fed7aa; border-color:#fb923c; }
        td.occ-pink     { background:#fce7f3; border-color:#f472b6; }
        td.occ-indigo   { background:#e0e7ff; border-color:#818cf8; }
        td.occ-gray     { background:#e5e7eb; border-color:#9ca3af; }
        td.blocked      { background:#fee2e2; border-color:#fca5a5; font-size:6pt; color:#b91c1c; }
        td.teacher-desk { background:#dbeafe; border-color:#60a5fa; font-weight:bold; font-size:6pt; color:#1e40af; }
        td.aisle        { background:#f9fafb; border-color:#e5e7eb; border-style:dashed; }
        td.door-side    { background:#fff3e0; border:2px solid #fb923c; color:#c2410c; font-weight:bold;
                          font-size:6pt; padding:1px; line-height: 1.1; }
        td.door-tb      { background:#fff3e0; border:2px solid #fb923c; color:#c2410c; font-weight:bold;
                          font-size:6pt; height:10px; padding:1px; }
        td.no-door      { background:transparent; border:none; }
        .legend { margin-top:6px; padding:4px 8px; background:#f9f9f9; border:1px solid #ddd; border-radius:3px; }
        .legend h3 { font-size:8pt; margin:0 0 3px 0; }
        .legend table { border-collapse:collapse; }
        .legend td { border:none; padding:1px 5px; font-size:7pt; white-space:nowrap; }
        .lc { display:inline-block; width:12px; height:12px; border:1px solid #999; vertical-align:middle; margin-right:3px; }
        .lc.available    { background:#f0fdf4; border-color:#86efac; }
        .lc.blocked      { background:#fee2e2; border-color:#fca5a5; }
        .lc.teacher-desk { background:#dbeafe; border-color:#60a5fa; }
        .lc.aisle        { background:#f9fafb; border-color:#e5e7eb; border-style:dashed; }
        .lc.occ-blue     { background:#dbeafe; border-color:#60a5fa; }
        .lc.occ-green    { background:#d1fae5; border-color:#34d399; }
        .lc.occ-purple   { background:#e9d5ff; border-color:#a78bfa; }
        .lc.occ-orange   { background:#fed7aa; border-color:#fb923c; }
        .lc.occ-pink     { background:#fce7f3; border-color:#f472b6; }
        .lc.occ-indigo   { background:#e0e7ff; border-color:#818cf8; }
        .footer { text-align:center; font-size:6.5pt; color:#aaa; margin-top:8px; border-top:1px solid #ddd; padding-top:4px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

@php
    $doorPositions  = $layout->door_positions ?? [];
    $topDoors       = collect($doorPositions)->where('side', 'top')->pluck('index')->toArray();
    $bottomDoors    = collect($doorPositions)->where('side', 'bottom')->pluck('index')->toArray();
    $leftDoors      = collect($doorPositions)->where('side', 'left')->pluck('index')->toArray();
    $rightDoors     = collect($doorPositions)->where('side', 'right')->pluck('index')->toArray();
    $hasSideDoors   = count($leftDoors) > 0 || count($rightDoors) > 0;
    $hasTopDoors    = count($topDoors) > 0;
    $hasBottomDoors = count($bottomDoors) > 0;
    $colRange       = range(1, $layout->columns);

    // Orientierung des Raums berücksichtigen
    $roomOrientation = $layout->orientation ?? 'top';  // 'top' | 'bottom'
    $isBottomOrientation = ($roomOrientation === 'bottom');

    // Spaltenbreiten
    $doorColPct = 5;
    $totalDoorPct = $hasSideDoors ? 2 * $doorColPct : 0;
    $seatColPct = round((100 - $totalDoorPct) / $layout->columns, 2);
    $lastSeatColPct = round(100 - $totalDoorPct - ($layout->columns - 1) * $seatColPct, 2);

    // Level-String aus Assignments dynamisch ableiten (gA/eA für gemischte Räume)
    $presentLevels = $assignments->map(fn($a) => $a->course->level)->unique();
    $hasGa = $presentLevels->contains('grundlegend');
    $hasEa = $presentLevels->contains('erhoeht');
    if ($hasGa && $hasEa) {
        $levelStr = 'gA/eA';
    } elseif ($hasEa) {
        $levelStr = 'eA';
    } elseif ($hasGa) {
        $levelStr = 'gA';
    } else {
        $levelStr = '';
    }
    // Fächer aus den tatsächlichen Kursen im Raum ermitteln (statt Slot-Name)
    $roomSubjectNames = $assignments
        ->map(fn($a) => $a->course->subject?->name)
        ->filter()
        ->unique()
        ->values();

    if ($roomSubjectNames->isNotEmpty()) {
        $subjectPart = $roomSubjectNames->join(' – ');
    } else {
        // Fallback auf Slot-Name ohne Level-Suffix
        $subjectPart = preg_replace('/ (eA|gA|erhöht|erhoeht|grundlegend)$/i', '', $writtenExamDate->slot->name);
    }
    $examInfoLine = trim($subjectPart . ($levelStr ? ' ' . $levelStr : ''))
        . ' &nbsp;|&nbsp; '
        . $writtenExamDate->date->locale('de')->translatedFormat('l, d. F Y')
        . ' | ' . $writtenExamDate->getStartTime()->format('H:i') . ' Uhr';

    // Pre-build legend items to avoid @if inside @foreach in table context
    $legendColors = ['blue', 'green', 'purple', 'orange', 'pink', 'indigo'];
    $legendItems = [];
    foreach ($assignments as $legendIdx => $asgn) {
        $c = $legendColors[$legendIdx % count($legendColors)];
        $label = $asgn->course->course_code;
        if ($asgn->is_hoerverstaendnis) {
            $label .= ' [HV]';
        }
        if ($asgn->course->teacher) {
            $label .= ' (' . $asgn->course->teacher->shortcode . ')';
        }
        $legendItems[] = ['color' => $c, 'label' => $label, 'hv' => $asgn->is_hoerverstaendnis];
    }

    // Helper: build seat TD html
    function buildSeatTd($cell, $seatNumbers, $assignment = null) {
        $seat = $cell['seat'];
        $asgn = $cell['assignment'];
        $seatNum = ($seat && isset($seatNumbers[$seat->id])) ? $seatNumbers[$seat->id] : null;
        if (!$seat || $seat->seat_type === 'blocked') {
            return ['cls' => 'blocked', 'body' => 'Blockiert'];
        }
        if ($seat->seat_type === 'teacher_desk') {
            return ['cls' => 'teacher-desk', 'body' => 'Pult'];
        }
        if ($seat->seat_type === 'aisle') {
            return ['cls' => 'aisle', 'body' => ''];
        }
        if ($asgn) {
            // Grau steht für „kein eigener Kursschüler" — Gast wie externer Bewerber.
            $isExtern = isset($asgn['student']->is_extern) && $asgn['student']->is_extern;
            $isGuest = $isExtern || (isset($asgn['student']->is_guest) && $asgn['student']->is_guest);
            $nameHtml = $isGuest
                ? '<div class="s-name" style="color:#555;">' . e($asgn['student']->lastname)
                  . ($asgn['student']->firstname ? ',<br>' . e($asgn['student']->firstname) : '') . '</div>'
                  . '<div class="s-course" style="color:#888;">(' . ($isExtern ? 'extern' : 'Gast') . ')</div>'
                : '<div class="s-name">' . e($asgn['student']->lastname) . ',<br>' . e($asgn['student']->firstname) . '</div>'
                  . '<div class="s-course">(' . e($asgn['course']->course_code) . ')</div>';
            $cls = $isGuest ? 'occ-gray' : 'occ-' . $asgn['color'];
            $body = '<div class="seat-nr">Platz ' . $seatNum . '</div>' . $nameHtml;
            return ['cls' => $cls, 'body' => $body];
        }
        $body = '<div class="seat-nr">Platz ' . $seatNum . '</div><span style="color:#bbb;">frei</span>';
        return ['cls' => 'available', 'body' => $body];
    }
@endphp

{{-- ======= ANSICHT VON VORNE (Blick vom Pult auf die Schüler) ======= --}}
@if(($exportView ?? 'front') === 'front')
@php
    // Seite 1: Reihenfolge so, dass das Pult oben erscheint (= Lehrer schaut hinein).
    // orientation='top'    → Reihe 1 oben  → aufsteigend
    // orientation='bottom' → Reihe N oben  → absteigend (grid umgekehrt)
    $page1RowKeys = $isBottomOrientation
        ? array_reverse(array_keys($grid))
        : array_keys($grid);
    // Türe-Zeile oben auf Seite 1 = physisch auf der Pult-Seite
    $page1TopDoors    = $isBottomOrientation ? $bottomDoors : $topDoors;
    $page1BottomDoors = $isBottomOrientation ? $topDoors    : $bottomDoors;
    $page1HasTop    = count($page1TopDoors) > 0;
    $page1HasBottom = count($page1BottomDoors) > 0;
    // Linke/Rechte Türen: bei orientation='bottom' bleibt links=links, rechts=rechts
    $page1LeftDoors  = $leftDoors;
    $page1RightDoors = $rightDoors;
@endphp
<div class="header">
    <div class="exam-info">{!! $examInfoLine !!}</div>
    <h1>Sitzplan &ndash; Raum {{ $room->room_number }}</h1>
</div>

<div class="info-box">
    <p>
        <strong>Raum:</strong> {{ $room->room_number }}
        &nbsp;&nbsp;<strong>Kapazit&auml;t:</strong> {{ $layout->getTotalCapacity() }} Pl&auml;tze
        @php
            $regularCount = $assignments->sum(fn($a) => $a->seatAssignments->whereNotNull('student_id')->count());
            $externCount  = $assignments->sum(fn($a) => $a->seatAssignments->whereNotNull('external_applicant_id')->count());
            $guestCount   = $assignments->sum(fn($a) => $a->seatAssignments->filter(fn($sa) => $sa->istGast())->count());
        @endphp
        &nbsp;&nbsp;<strong>Belegt:</strong> {{ $regularCount }} Sch&uuml;ler{{ $externCount > 0 ? ' + ' . $externCount . ' extern' : '' }}{{ $guestCount > 0 ? ' + ' . $guestCount . ' Gast' . ($guestCount > 1 ? '&#32;(Gäste)' : '') : '' }}
    </p>
</div>

<div class="course-legend">
    <table><tr>
        <td class="label-cell">Kurse:</td>
        <td>
            @foreach($assignments as $assignment)
                @php $color = $legendColors[$loop->index % count($legendColors)]; @endphp
                <span class="course-badge course-{{ $color }}">
                    {{ $assignment->course->course_code }}
                    @if($assignment->is_hoerverstaendnis)
                        <span class="hv-badge">HV</span>
                    @endif
                    @if($assignment->course->teacher)
                        ({{ $assignment->course->teacher->shortcode }})
                    @endif
                    &ndash; {{ $assignment->seatAssignments->whereNotNull('student_id')->count() }} Sch&uuml;ler
                </span>
            @endforeach
        </td>
    </tr></table>
</div>

<table class="seating-table">
@if($page1HasTop)
    <tr>
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
        @foreach($colRange as $c)
            @php $isDoor = in_array($c, $page1TopDoors); @endphp
            <td class="{{ $isDoor ? 'door-tb' : 'no-door' }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{{ $isDoor ? 'Türe' : '' }}</td>
        @endforeach
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
    </tr>
@endif

@foreach($page1RowKeys as $rowNumber)
    @php $rowCols = $grid[$rowNumber]; @endphp
    <tr>
        @if($hasSideDoors)
            @php $isLeft = in_array($rowNumber, $page1LeftDoors); @endphp
            <td class="{{ $isLeft ? 'door-side' : 'no-door' }}" style="width:{{ $doorColPct }}%">{!! $isLeft ? 'T<br>ü<br>r<br>e' : '' !!}</td>
        @endif
        @foreach($rowCols as $colNumber => $cell)
            @php $td = buildSeatTd($cell, $seatNumbers); @endphp
            <td class="{{ $td['cls'] }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{!! $td['body'] !!}</td>
        @endforeach
        @if($hasSideDoors)
            @php $isRight = in_array($rowNumber, $page1RightDoors); @endphp
            <td class="{{ $isRight ? 'door-side' : 'no-door' }}" style="width:{{ $doorColPct }}%">{!! $isRight ? 'T<br>ü<br>r<br>e' : '' !!}</td>
        @endif
    </tr>
@endforeach

@if($page1HasBottom)
    <tr>
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
        @foreach($colRange as $c)
            @php $isDoor = in_array($c, $page1BottomDoors); @endphp
            <td class="{{ $isDoor ? 'door-tb' : 'no-door' }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{{ $isDoor ? 'Türe' : '' }}</td>
        @endforeach
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
    </tr>
@endif
</table>

<div class="legend">
    <h3>Legende</h3>
    <table><tr>
        @foreach($legendItems as $li)
            <td><span class="lc occ-{{ $li['color'] }}"></span>{{ $li['label'] }}</td>
        @endforeach
        <td><span class="lc available"></span> Frei</td>
        <td><span class="lc blocked"></span> Blockiert</td>
        <td><span class="lc teacher-desk"></span> Lehrerpult</td>
        <td><span class="lc aisle"></span> Gang</td>
    </tr></table>
</div>


@endif

{{-- ======= ANSICHT VON HINTEN ======= --}}
@if(($exportView ?? 'front') === 'back')

<div class="header">
    <div class="exam-info">{!! $examInfoLine !!}</div>
    <h1>Sitzplan &ndash; Raum {{ $room->room_number }}</h1>
</div>

<div class="course-legend">
    <table><tr>
        <td class="label-cell">Kurse:</td>
        <td>
            @foreach($assignments as $assignment)
                @php $color = $legendColors[$loop->index % count($legendColors)]; @endphp
                <span class="course-badge course-{{ $color }}">
                    {{ $assignment->course->course_code }}
                    @if($assignment->is_hoerverstaendnis)
                        <span class="hv-badge">HV</span>
                    @endif
                    @if($assignment->course->teacher)
                        ({{ $assignment->course->teacher->shortcode }})
                    @endif
                    &ndash; {{ $assignment->seatAssignments->whereNotNull('student_id')->count() }} Sch&uuml;ler
                </span>
            @endforeach
        </td>
    </tr></table>
</div>

@php
    $reversedColRange = array_reverse($colRange);
    // Seite 2: Gegenüber Seite 1 – Pult erscheint jetzt unten auf der Seite
    $page2RowKeys     = $isBottomOrientation ? array_keys($grid) : array_reverse(array_keys($grid));
    $page2TopDoors    = $isBottomOrientation ? $topDoors    : $bottomDoors;
    $page2BottomDoors = $isBottomOrientation ? $bottomDoors : $topDoors;
    $page2LeftDoors   = $rightDoors;   // gespiegelt (von hinten)
    $page2RightDoors  = $leftDoors;    // gespiegelt (von hinten)
    $page2HasTop    = count($page2TopDoors) > 0;
    $page2HasBottom = count($page2BottomDoors) > 0;
@endphp

<table class="seating-table">
@if($page2HasTop)
    <tr>
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
        @foreach($reversedColRange as $c)
            @php $isDoor = in_array($c, $page2TopDoors); @endphp
            <td class="{{ $isDoor ? 'door-tb' : 'no-door' }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{{ $isDoor ? 'Türe' : '' }}</td>
        @endforeach
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
    </tr>
@endif
@foreach($page2RowKeys as $rowNumber)
    @php $reversedColKeys = array_reverse(array_keys($grid[$rowNumber])); @endphp
    <tr>
        @if($hasSideDoors)
            @php $isLeft = in_array($rowNumber, $page2LeftDoors); @endphp
            <td class="{{ $isLeft ? 'door-side' : 'no-door' }}" style="width:{{ $doorColPct }}%">{!! $isLeft ? 'T<br>ü<br>r<br>e' : '' !!}</td>
        @endif
        @foreach($reversedColKeys as $colNumber)
            @php $td = buildSeatTd($grid[$rowNumber][$colNumber], $seatNumbers); @endphp
            <td class="{{ $td['cls'] }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{!! $td['body'] !!}</td>
        @endforeach
        @if($hasSideDoors)
            @php $isRight = in_array($rowNumber, $page2RightDoors); @endphp
            <td class="{{ $isRight ? 'door-side' : 'no-door' }}" style="width:{{ $doorColPct }}%">{!! $isRight ? 'T<br>ü<br>r<br>e' : '' !!}</td>
        @endif
    </tr>
@endforeach

@if($page2HasBottom)
    <tr>
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
        @foreach($reversedColRange as $c)
            @php $isDoor = in_array($c, $page2BottomDoors); @endphp
            <td class="{{ $isDoor ? 'door-tb' : 'no-door' }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{{ $isDoor ? 'Türe' : '' }}</td>
        @endforeach
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
    </tr>
@endif
</table>

<div class="legend">
    <h3>Legende</h3>
    <table><tr>
        @foreach($legendItems as $li)
            <td><span class="lc occ-{{ $li['color'] }}"></span>{{ $li['label'] }}</td>
        @endforeach
        <td><span class="lc available"></span> Frei</td>
        <td><span class="lc blocked"></span> Blockiert</td>
        <td><span class="lc teacher-desk"></span> Lehrerpult</td>
        <td><span class="lc aisle"></span> Gang</td>
    </tr></table>
</div>

@endif

<div class="footer">Exportiert am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr</div>

</body>
</html>
