<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Alle Sitzpläne {{ $examYear }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: 1.3; color: #333; margin: 8px; }
        .header { text-align: center; margin-bottom: 5px; padding-bottom: 4px; border-bottom: 2px solid #444; }
        .header h1 { font-size: 12pt; margin: 0 0 1px 0; color: #000; }
        .header .sub { font-size: 8pt; color: #555; margin: 1px 0; }
        .exam-info { font-size: 11pt; font-weight: bold; color: #000; margin: 0 0 3px 0; }
        .course-legend { margin-bottom: 4px; padding: 2px 6px; background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 3px; font-size: 7.5pt; }
        .course-badge { display: inline-block; padding: 1px 5px; margin: 1px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .hv-badge { display: inline-block; padding: 1px 4px; margin-left: 3px; border-radius: 3px;
                    font-size: 6.5pt; font-weight: bold; background: #ccfbf1; color: #0f766e; border: 1px solid #5eead4; }
        .course-blue   { background:#dbeafe; color:#1e40af; border:1px solid #60a5fa; }
        .course-green  { background:#d1fae5; color:#065f46; border:1px solid #34d399; }
        .course-purple { background:#e9d5ff; color:#6b21a8; border:1px solid #a78bfa; }
        .course-orange { background:#fed7aa; color:#9a3412; border:1px solid #fb923c; }
        .course-pink   { background:#fce7f3; color:#9d174d; border:1px solid #f472b6; }
        .course-indigo { background:#e0e7ff; color:#3730a3; border:1px solid #818cf8; }
        .seating-table { border-collapse: separate; border-spacing: 15px 25px; width: 100%; page-break-inside: avoid; }
        .seating-table td { text-align: center; vertical-align: middle; padding: 2px 1px;
                            font-size: 6.5pt; border: 1px solid #ddd; border-radius: 2px;
                            height: 44px; overflow: hidden; }
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
                          font-size:6pt; padding:1px; line-height:1.1; }
        td.door-tb      { background:#fff3e0; border:2px solid #fb923c; color:#c2410c; font-weight:bold;
                          font-size:6pt; height:10px; padding:1px; }
        td.no-door      { background:transparent; border:none; }
        .legend { margin-top:5px; padding:3px 6px; background:#f9f9f9; border:1px solid #ddd; border-radius:3px; }
        .legend table { border-collapse:collapse; }
        .legend td { border:none; padding:1px 4px; font-size:6.5pt; white-space:nowrap; }
        .lc { display:inline-block; width:10px; height:10px; border:1px solid #999; vertical-align:middle; margin-right:2px; }
        .lc.available { background:#f0fdf4; border-color:#86efac; }
        .lc.blocked   { background:#fee2e2; border-color:#fca5a5; }
        .lc.teacher-desk { background:#dbeafe; border-color:#60a5fa; }
        .lc.aisle     { background:#f9fafb; border-color:#e5e7eb; border-style:dashed; }
        .lc.occ-blue  { background:#dbeafe; border-color:#60a5fa; }
        .lc.occ-green { background:#d1fae5; border-color:#34d399; }
        .lc.occ-purple{ background:#e9d5ff; border-color:#a78bfa; }
        .lc.occ-orange{ background:#fed7aa; border-color:#fb923c; }
        .lc.occ-pink  { background:#fce7f3; border-color:#f472b6; }
        .lc.occ-indigo{ background:#e0e7ff; border-color:#818cf8; }
        .page-break { page-break-before: always; }
        .board-label { text-align:center; font-size:7pt; font-weight:bold; color:#888;
                       letter-spacing:2px; margin-bottom:2px; }
    </style>
</head>
<body>

@php
function buildSeatTdAll($cell, $seatNumbers) {
    $seat = $cell['seat'];
    $asgn = $cell['assignment'];
    $seatNum = ($seat && isset($seatNumbers[$seat->id])) ? $seatNumbers[$seat->id] : null;
    if (!$seat || $seat->seat_type === 'blocked') {
        return ['cls' => 'blocked', 'body' => '✕'];
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
              . '<div class="s-course">(' . ($isExtern ? 'extern' : 'Gast') . ')</div>'
            : '<div class="s-name">' . e($asgn['student']->lastname) . ',<br>' . e($asgn['student']->firstname) . '</div>'
              . '<div class="s-course">(' . e($asgn['course']->course_code) . ')</div>';
        $cls  = $isGuest ? 'occ-gray' : 'occ-' . $asgn['color'];
        $body = '<div class="seat-nr">Platz ' . $seatNum . '</div>' . $nameHtml;
        return ['cls' => $cls, 'body' => $body];
    }
    $body = '<div class="seat-nr">Platz ' . $seatNum . '</div><span style="color:#bbb;">frei</span>';
    return ['cls' => 'available', 'body' => $body];
}
@endphp

@foreach($pages as $pageIndex => $page)
@php
    $layout      = $page['layout'];
    $grid        = $page['grid'];
    $assignments = $page['assignments'];
    $courseColors= $page['courseColors'];
    $seatNumbers = $page['seatNumbers'];
    $room        = $page['room'];
    $yd          = $page['writtenExamDate'];

    $doorPositions  = $layout->door_positions ?? [];
    $topDoors       = collect($doorPositions)->where('side', 'top')->pluck('index')->toArray();
    $bottomDoors    = collect($doorPositions)->where('side', 'bottom')->pluck('index')->toArray();
    $leftDoors      = collect($doorPositions)->where('side', 'left')->pluck('index')->toArray();
    $rightDoors     = collect($doorPositions)->where('side', 'right')->pluck('index')->toArray();
    $hasSideDoors   = count($leftDoors) > 0 || count($rightDoors) > 0;
    $hasTopDoors    = count($topDoors) > 0;
    $hasBottomDoors = count($bottomDoors) > 0;
    $colRange       = range(1, $layout->columns);

    $doorColPct     = 5;
    $totalDoorPct   = $hasSideDoors ? 2 * $doorColPct : 0;
    $seatColPct     = round((100 - $totalDoorPct) / $layout->columns, 2);
    $lastSeatColPct = round(100 - $totalDoorPct - ($layout->columns - 1) * $seatColPct, 2);

    $presentLevels = $assignments->map(fn($a) => $a->course->level)->unique();
    $hasGa = $presentLevels->contains('grundlegend');
    $hasEa = $presentLevels->contains('erhoeht');
    $levelStr = ($hasGa && $hasEa) ? 'gA/eA' : ($hasEa ? 'eA' : ($hasGa ? 'gA' : ''));

    // Fächer aus den tatsächlichen Kursen im Raum ermitteln
    $roomSubjectNames = $assignments
        ->map(fn($a) => $a->course->subject?->name)
        ->filter()->unique()->values();
    if ($roomSubjectNames->isNotEmpty()) {
        $subjectPart = $roomSubjectNames->join(' – ');
    } else {
        $subjectPart = preg_replace('/ (eA|gA|erhöht|erhoeht|grundlegend)$/i', '', $yd->slot->name);
    }
    $examInfoLine = trim($subjectPart . ($levelStr ? ' ' . $levelStr : ''))
        . ' &nbsp;|&nbsp; '
        . $yd->exam_date->locale('de')->translatedFormat('l, d. F Y')
        . ' | ' . \Carbon\Carbon::parse($yd->slot->start_time)->format('H:i') . ' Uhr';

    $reversedColRange = array_reverse($colRange);
    $reversedRowKeys  = array_reverse(array_keys($grid));

    $regularCount = $assignments->sum(fn($a) => $a->seatAssignments->whereNotNull('student_id')->count());
    $externCount  = $assignments->sum(fn($a) => $a->seatAssignments->whereNotNull('external_applicant_id')->count());
    $guestCount   = $assignments->sum(fn($a) => $a->seatAssignments->filter(fn($sa) => $sa->istGast())->count());
@endphp

@if($pageIndex > 0)
<div class="page-break"></div>
@endif

<div class="header">
    <div class="exam-info">{!! $examInfoLine !!}</div>
    <h1>Sitzplan &ndash; Raum {{ $room->room_number }}</h1>
    <div class="sub">
        <strong>Belegt:</strong> {{ $regularCount }} Sch&uuml;ler{{ $externCount > 0 ? ' + ' . $externCount . ' extern' : '' }}{{ $guestCount > 0 ? ' + ' . $guestCount . ' Gast/G&auml;ste' : '' }}
        &nbsp;&nbsp;<strong>Vorlage:</strong> {{ $layout->name }}
    </div>
</div>

<div class="course-legend">
    <strong>Kurse:</strong>
    @php $planColors = ['blue','green','purple','orange','pink','indigo']; @endphp
    @foreach($assignments as $assignment)
        @php $color = $planColors[$loop->index % count($planColors)]; @endphp
        <span class="course-badge course-{{ $color }}">
            {{ $assignment->course->course_code }}
            @if($assignment->is_hoerverstaendnis)<span class="hv-badge">HV</span>@endif
            @if($assignment->course->teacher) ({{ $assignment->course->teacher->shortcode }})@endif
            &ndash; {{ $assignment->seatAssignments->count() }} Sch&uuml;ler
        </span>
    @endforeach
</div>

{{-- Ansicht von vorne --}}
<table class="seating-table">
@if($hasTopDoors)
    <tr>
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
        @foreach($colRange as $c)
            @php $isDoor = in_array($c, $topDoors); @endphp
            <td class="{{ $isDoor ? 'door-tb' : 'no-door' }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{{ $isDoor ? 'Türe' : '' }}</td>
        @endforeach
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
    </tr>
@endif
@foreach($grid as $rowNumber => $rowCols)
    <tr>
        @if($hasSideDoors)
            @php $isLeft = in_array($rowNumber, $leftDoors); @endphp
            <td class="{{ $isLeft ? 'door-side' : 'no-door' }}" style="width:{{ $doorColPct }}%">{!! $isLeft ? 'T<br>ü<br>r<br>e' : '' !!}</td>
        @endif
        @foreach($rowCols as $colNumber => $cell)
            @php $td = buildSeatTdAll($cell, $seatNumbers); @endphp
            <td class="{{ $td['cls'] }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{!! $td['body'] !!}</td>
        @endforeach
        @if($hasSideDoors)
            @php $isRight = in_array($rowNumber, $rightDoors); @endphp
            <td class="{{ $isRight ? 'door-side' : 'no-door' }}" style="width:{{ $doorColPct }}%">{!! $isRight ? 'T<br>ü<br>r<br>e' : '' !!}</td>
        @endif
    </tr>
@endforeach
@if($hasBottomDoors)
    <tr>
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
        @foreach($colRange as $c)
            @php $isDoor = in_array($c, $bottomDoors); @endphp
            <td class="{{ $isDoor ? 'door-tb' : 'no-door' }}" style="width:{{ $loop->last ? $lastSeatColPct : $seatColPct }}%">{{ $isDoor ? 'Türe' : '' }}</td>
        @endforeach
        @if($hasSideDoors)<td class="no-door" style="width:{{ $doorColPct }}%"></td>@endif
    </tr>
@endif
</table>

<div class="legend">
    <table><tr>
        @foreach($assignments as $a)
            @php $c = $planColors[$loop->index % count($planColors)]; @endphp
            <td><span class="lc occ-{{ $c }}"></span>{{ $a->course->course_code }}{{ $a->is_hoerverstaendnis ? ' [HV]' : '' }}</td>
        @endforeach
        <td><span class="lc available"></span> Frei</td>
        <td><span class="lc blocked"></span> Blockiert</td>
        <td><span class="lc teacher-desk"></span> Pult</td>
        <td><span class="lc aisle"></span> Gang</td>
    </tr></table>
</div>

@endforeach

<div style="text-align:center; font-size:6pt; color:#aaa; margin-top:10px; border-top:1px solid #ddd; padding-top:4px;">
    Exportiert am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr &nbsp;&middot;&nbsp; {{ $examYear }}
</div>

</body>
</html>
