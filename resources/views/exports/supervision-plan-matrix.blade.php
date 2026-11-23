<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Aufsichtsplan {{ $subjectString }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #333; margin: 0; padding: 0; }
        h1 { font-size: 13px; margin: 0 0 2px 0; }
        .meta { font-size: 10px; color: #444; margin-bottom: 6px; font-weight: bold; }
        .grid-block { margin-bottom: 14px; }
        .grid-header { font-size: 10px; font-weight: bold; padding: 4px 6px; margin-bottom: 0;
                       border-left: 4px solid #6b7280; background: #f3f4f6; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background-color: #f9fafb; font-weight: bold; font-size: 8.5px; }
        .col-pos { width: 14%; }
        .room-name { font-weight: bold; color: #1d4ed8; font-size: 9px; }
        .course-info { font-size: 7.5px; color: #4b5563; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 8px; font-weight: bold; margin: 1px; }
        /* Kategorie-Farben */
        .badge-q13       { background: #ccfbf1; border: 1px solid #5eead4; color: #0f766e; }
        .badge-free-plus { background: #dcfce7; border: 1px solid #86efac; color: #15803d; }
        .badge-free      { background: #dbeafe; border: 1px solid #93c5fd; color: #1d4ed8; }
        .badge-lesson    { background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; }
        .badge-unknown   { background: #f3f4f6; border: 1px solid #d1d5db; color: #374151; }
        .col-corridor { font-weight: bold; color: #6b21a8; font-size: 8.5px; }
        .col-reserve  { font-weight: bold; color: #b45309; font-size: 8.5px; }
        .col-extra    { font-weight: bold; color: #9a3412; font-size: 8.5px; }
        .slot-time { font-weight: bold; white-space: nowrap; }
        .slot-label { font-size: 7.5px; color: #6b7280; }
        /* Legende */
        .legend { display: table; margin-bottom: 8px; font-size: 7.5px; }
        .legend-item { display: table-cell; padding: 2px 6px; border-radius: 3px; margin-right: 4px; font-weight: bold; border: 1px solid; }
    </style>
</head>
<body>
    <h1>Aufsichtsplan: {{ $subjectString }}</h1>
    <div class="meta">{{ \Carbon\Carbon::parse($writtenExamDate->exam_date)->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}</div>

    {{-- Legende --}}
    <div style="margin-bottom:8px; font-size:7.5px;">
        <strong>Legende:</strong>&nbsp;
        <span class="badge badge-q13">Nachname, V. &nbsp;Q13-Unterricht</span>&nbsp;
        <span class="badge badge-free-plus">Nachname, V. &nbsp;Freistunde (vorher da)</span>&nbsp;
        <span class="badge badge-free">Nachname, V. &nbsp;Freistunde</span>&nbsp;
        <span class="badge badge-lesson">Nachname, V. &nbsp;⚠ Anderer Unterricht</span>&nbsp;
        <span class="badge badge-unknown">Nachname, V. &nbsp;Kein Stundenplan</span>
    </div>

    @php
        $catCss = [
            'q13_lesson'         => 'badge-q13',
            'free_already_there' => 'badge-free-plus',
            'free'               => 'badge-free',
            'has_lesson'         => 'badge-lesson',
            'unknown'            => 'badge-unknown',
        ];

        // Lehrername-Hilfsfunktion
        $teacherName = fn($teacher) => ($teacher->lastname ?? '?') . ', ' . substr($teacher->firstname ?? '', 0, 1) . '.';

        $slotsByGridId = $timeSlots->groupBy(fn($s) => $s->written_exam_supervision_grid_id ?? 0);
        $roomsByGrid   = [];
        foreach ($assignmentsByRoom as $roomId => $roomAssignments) {
            $fa  = $roomAssignments->first();
            $gid = $fa?->written_exam_supervision_grid_id ?? $defaultGridId ?? 0;
            $roomsByGrid[$gid][] = [
                'room'        => $fa->room,
                'assignments' => $roomAssignments,
            ];
        }
        $allGridIds = collect(array_keys($roomsByGrid))
            ->merge($slotsByGridId->keys())
            ->unique()
            ->sortBy(fn($id) => $id == $defaultGridId ? 0 : 1)
            ->values();

        $defaultGrid   = $grids->firstWhere('is_default', true) ?? $grids->first();
        $defaultSlots  = $slotsByGridId->get($defaultGrid?->id ?? 0, collect())->sortBy('slot_number');
        $defaultSlotRows = $defaultSlots->isNotEmpty() ? $defaultSlots : collect([null]);
    @endphp

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
                <span style="font-weight:normal; color:#6b7280;"> – {{ $gridSlots->count() }} Zeitslots, {{ count($gridRooms) }} Räume</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th class="col-pos">Raum</th>
                        @if($gridSlots->isEmpty())
                            <th><span style="font-style:italic; color:#9ca3af;">keine Zeitslots</span></th>
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
                        @php $firstAssignment = $roomData['assignments']->first(); @endphp
                        <tr>
                            <td>
                                <div class="room-name">{{ $roomData['room']->room_number ?? 'Raum '.$roomData['room']->id }}</div>
                                @foreach($roomData['assignments'] as $a)
                                    @php
                                        $startTimeStr = $a->examDate->getStartTime()->format('H:i');
                                        $endTimeParts = collect($a->seatAssignments)->map(fn($sa) => $a->examDate->getEndTime($sa->nachteilsausgleichProzent() ?: null)->format('H:i'))->unique()->implode(' / ');
                                        if (!$endTimeParts) $endTimeParts = '?';
                                    @endphp
                                    <div class="course-info">{{ $a->course->course_code ?? '?' }}@if($a->course->teacher) ({{ $a->course->teacher->shortcode }})@endif [{{ $startTimeStr }}–{{ $endTimeParts }}]</div>
                                @endforeach
                            </td>
                            @foreach($slotRows as $slot)
                                @php
                                    $cell = $roomData['assignments']->flatMap(fn($a) => $a->supervisions)->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null);
                                    $slotKey = $slot?->id ?? 'none';
                                @endphp
                                <td>
                                    @foreach($cell as $s)
                                        @php
                                            $cat = $teacherCategoryMaps[$slotKey][$s->teacher_id] ?? 'unknown';
                                            $css = $catCss[$cat] ?? 'badge-unknown';
                                        @endphp
                                        <span class="badge {{ $css }}" title="{{ $cat }}">{{ $teacherName($s->teacher) }}</span>
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
        <div class="grid-header" style="border-left-color:#7c3aed;">Gangaufsicht &amp; Reserve
            <span style="font-weight:normal; color:#6b7280;"> – Raster: {{ $defaultGrid?->name ?? 'Standard' }}</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th class="col-pos">Position</th>
                    @foreach($defaultSlotRows as $slot)
                        <th>
                            @if($slot)
                                <div class="slot-time">{{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}</div>
                                @if($slot->label)<div class="slot-label">{{ $slot->label }}</div>@endif
                            @else
                                <span style="font-style:italic; color:#9ca3af;">ohne Zeitslot</span>
                            @endif
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
                        <td>
                            @foreach($cell as $s)
                                @php $cat = $teacherCategoryMaps[$slotKey][$s->teacher_id] ?? 'unknown'; @endphp
                                <span class="badge {{ $catCss[$cat] ?? 'badge-unknown' }}">{{ $teacherName($s->teacher) }}</span>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td><div class="col-reserve">Reserve</div><div class="slot-label">Lehrerzimmer</div></td>
                    @foreach($defaultSlotRows as $slot)
                        @php
                            $cell    = $reserveSupervisions->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null);
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
            </tbody>
        </table>
    </div>

    {{-- Zusätzliche Positionen --}}
    @if($centralTypes->isNotEmpty())
        <div class="grid-block">
            <div class="grid-header">Zusätzliche Positionen</div>
            <table>
                <thead>
                    <tr>
                        <th class="col-pos">Position</th>
                        @foreach($timeSlots->isNotEmpty() ? $timeSlots : collect([null]) as $slot)
                            <th>
                                @if($slot)
                                    <div class="slot-time">{{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}</div>
                                    @if($slot->grid)<div class="slot-label">{{ $slot->grid->name }}</div>@endif
                                @else
                                    ohne Zeitslot
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($centralTypes as $type)
                        <tr>
                            <td><div class="col-extra">{{ $type->name }}</div></td>
                            @foreach($timeSlots->isNotEmpty() ? $timeSlots : collect([null]) as $slot)
                                @php
                                    $cell    = ($centralSupervisions->get($type->id) ?? collect())->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null);
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
    @endif

</body>
</html>
