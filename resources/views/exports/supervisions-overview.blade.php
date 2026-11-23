<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Aufsichtsplan – Gesamtübersicht</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #333; margin: 0; padding: 0; }
        h1 { font-size: 12px; margin: 0 0 2px 0; }
        .day-header { font-size: 11px; font-weight: bold; margin: 0 0 3px 0; border-bottom: 2px solid #374151; padding-bottom: 2px; }
        .day-meta { font-size: 8.5px; color: #555; margin-bottom: 5px; }
        .day-block { page-break-after: always; margin-bottom: 0; padding: 8px; }
        .day-block:last-child { page-break-after: avoid; }
        .grid-block { margin-bottom: 10px; }
        .grid-header { font-size: 9px; font-weight: bold; padding: 3px 5px; margin-bottom: 0;
                       border-left: 3px solid #6b7280; background: #f3f4f6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        th, td { border: 1px solid #d1d5db; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background-color: #f9fafb; font-weight: bold; font-size: 8px; }
        .col-pos { width: 12%; }
        .room-name { font-weight: bold; color: #1d4ed8; font-size: 8.5px; }
        .course-info { font-size: 7px; color: #4b5563; }
        .badge { display: inline-block; padding: 1px 3px; border-radius: 2px; font-size: 7.5px; font-weight: bold; margin: 1px; }
        .badge-q13       { background: #ccfbf1; border: 1px solid #5eead4; color: #0f766e; }
        .badge-free-plus { background: #dcfce7; border: 1px solid #86efac; color: #15803d; }
        .badge-free      { background: #dbeafe; border: 1px solid #93c5fd; color: #1d4ed8; }
        .badge-lesson    { background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; }
        .badge-unknown   { background: #f3f4f6; border: 1px solid #d1d5db; color: #374151; }
        .col-corridor { font-weight: bold; color: #6b21a8; }
        .col-reserve  { font-weight: bold; color: #b45309; }
        .col-extra    { font-weight: bold; color: #9a3412; }
        .slot-time  { font-weight: bold; white-space: nowrap; font-size: 8px; }
        .slot-label { font-size: 7px; color: #6b7280; }
        .empty-hint { font-style: italic; color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>
@php
    $catCss = [
        'q13_lesson'         => 'badge-q13',
        'free_already_there' => 'badge-free-plus',
        'free'               => 'badge-free',
        'has_lesson'         => 'badge-lesson',
        'unknown'            => 'badge-unknown',
    ];
    $teacherName = fn($teacher) => ($teacher->lastname ?? '?') . ', ' . substr($teacher->firstname ?? '', 0, 1) . '.';
@endphp

@foreach($dayBlocks as $dayString => $dayData)
@php
    $carbon     = \Carbon\Carbon::parse($dayString)->locale('de');
    $subjectStr = $dayData['subjectString'];
    $assignments          = $dayData['assignments'];
    $corridorSupervisions = $dayData['corridorSupervisions'];
    $reserveSupervisions  = $dayData['reserveSupervisions'];
    $centralTypes         = $dayData['centralTypes'];
    $centralSupervisions  = $dayData['centralSupervisions'];
    $hasForeignLanguage   = $dayData['hasForeignLanguage'];
    $teacherCategoryMaps  = $dayData['teacherCategoryMaps'];

    $assignmentsByRoom = $assignments->groupBy('room_id');
    $slotsByGridId     = $timeSlots->groupBy(fn($s) => $s->written_exam_supervision_grid_id ?? 0);

    $roomsByGrid = [];
    foreach ($assignmentsByRoom as $roomAssignments) {
        $fa  = $roomAssignments->first();
        $gid = $fa?->written_exam_supervision_grid_id ?? $defaultGridId ?? 0;
        $roomsByGrid[$gid][] = ['room' => $fa->room, 'assignments' => $roomAssignments];
    }

    $allGridIds = collect(array_keys($roomsByGrid))
        ->merge($slotsByGridId->keys())
        ->unique()
        ->sortBy(fn($id) => $id == $defaultGridId ? 0 : 1)
        ->values();

    $defaultGrid      = $grids->firstWhere('is_default', true) ?? $grids->first();
    $defaultSlots     = $slotsByGridId->get($defaultGrid?->id ?? 0, collect())->sortBy('slot_number');
    $defaultSlotRows  = $defaultSlots->isNotEmpty() ? $defaultSlots : collect([null]);
@endphp
<div class="day-block">
    <div class="day-header">{{ $carbon->isoFormat('dddd, DD. MMMM YYYY') }}</div>
    <div class="day-meta">{{ $subjectStr }}
        @if($dayData['timeRange'])<span style="margin-left:8px;">{{ $dayData['timeRange'] }}</span>@endif
    </div>

    {{-- Pro Raster ein Block --}}
    @foreach($allGridIds as $gid)
        @php
            $grid      = $grids->firstWhere('id', $gid);
            $gridSlots = $slotsByGridId->get($gid, collect())->sortBy('slot_number');
            $gridRooms = $roomsByGrid[$gid] ?? [];
            $slotRows  = $gridSlots->isNotEmpty() ? $gridSlots : collect([null]);
            if (($grid?->is_foreign_language) && !$hasForeignLanguage) continue;
        @endphp
        <div class="grid-block">
            <div class="grid-header">{{ $grid?->name ?? 'Standard-Raster' }}
                <span style="font-weight:normal; color:#6b7280;"> – {{ count($gridRooms) }} Räume</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th class="col-pos">Raum</th>
                        @if($gridSlots->isEmpty())
                            <th><span class="empty-hint">keine Zeitslots</span></th>
                        @else
                            @foreach($gridSlots as $slot)
                                <th>
                                    <div class="slot-time">{{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}</div>
                                    @if($slot->label)<div class="slot-label">{{ $slot->label }}</div>@endif
                                </th>
                            @endforeach
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($gridRooms as $roomData)
                        <tr>
                            <td>
                                <div class="room-name">{{ $roomData['room']->room_number ?? 'Raum '.$roomData['room']->id }}</div>
                                @foreach($roomData['assignments'] as $a)
                                    <div class="course-info">{{ $a->course->course_code ?? '?' }}@if($a->course->teacher) ({{ $a->course->teacher->shortcode }})@endif</div>
                                @endforeach
                            </td>
                            @foreach($slotRows as $slot)
                                @php
                                    $cell    = $roomData['assignments']->flatMap(fn($a) => $a->supervisions)
                                        ->filter(fn($s) => $slot
                                            ? $s->written_exam_supervision_time_slot_id === $slot->id
                                            : $s->written_exam_supervision_time_slot_id === null);
                                    $slotKey = $slot?->id ?? 'none';
                                @endphp
                                <td>
                                    @foreach($cell as $s)
                                        @php $cat = $teacherCategoryMaps[$slotKey][$s->teacher_id] ?? 'unknown'; @endphp
                                        <span class="badge {{ $catCss[$cat] ?? 'badge-unknown' }}">{{ $teacherName($s->teacher) }}</span>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- Gangaufsicht & Reserve --}}
    <div class="grid-block">
        <div class="grid-header" style="border-left-color:#7c3aed;">Gangaufsicht &amp; Reserve</div>
        <table>
            <thead>
                <tr>
                    <th class="col-pos">Position</th>
                    @foreach($defaultSlotRows as $slot)
                        <th>
                            @if($slot)<div class="slot-time">{{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}</div>
                                @if($slot->label)<div class="slot-label">{{ $slot->label }}</div>@endif
                            @else<span class="empty-hint">ohne Zeitslot</span>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><div class="col-corridor">Gangaufsicht</div></td>
                    @foreach($defaultSlotRows as $slot)
                        @php
                            $cell    = $corridorSupervisions->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null);
                            $slotKey = $slot?->id ?? 'none';
                        @endphp
                        <td>@foreach($cell as $s)<span class="badge {{ $catCss[$teacherCategoryMaps[$slotKey][$s->teacher_id] ?? 'unknown'] ?? 'badge-unknown' }}">{{ $teacherName($s->teacher) }}</span>@endforeach</td>
                    @endforeach
                </tr>
                <tr>
                    <td><div class="col-reserve">Reserve (LZ)</div></td>
                    @foreach($defaultSlotRows as $slot)
                        @php
                            $cell    = $reserveSupervisions->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null);
                            $slotKey = $slot?->id ?? 'none';
                        @endphp
                        <td>@foreach($cell as $s)<span class="badge {{ $catCss[$teacherCategoryMaps[$slotKey][$s->teacher_id] ?? 'unknown'] ?? 'badge-unknown' }}">{{ $teacherName($s->teacher) }}</span>@endforeach</td>
                    @endforeach
                </tr>

                {{-- Zusätzliche Positionen --}}
                @foreach($centralTypes as $type)
                    <tr>
                        <td><div class="col-extra">{{ $type->name }}</div></td>
                        @foreach($defaultSlotRows as $slot)
                            @php
                                $cell    = ($centralSupervisions->get($type->id) ?? collect())->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null);
                                $slotKey = $slot?->id ?? 'none';
                            @endphp
                            <td>@foreach($cell as $s)<span class="badge {{ $catCss[$teacherCategoryMaps[$slotKey][$s->teacher_id] ?? 'unknown'] ?? 'badge-unknown' }}">{{ $teacherName($s->teacher) }}</span>@endforeach</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach
</body>
</html>
