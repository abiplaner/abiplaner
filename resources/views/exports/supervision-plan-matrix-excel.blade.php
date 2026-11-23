<table>
    {{-- Titelzeilen --}}
    <thead>
        <tr>
            <th colspan="20" style="font-size:14px; font-weight:bold;">
                Aufsichtsplan: {{ $subjectString }}
            </th>
        </tr>
        <tr>
            <th colspan="20" style="font-size:12px; font-weight:bold;">
                {{ \Carbon\Carbon::parse($writtenExamDate->exam_date)->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}
            </th>
        </tr>
        <tr><th></th></tr>
    </thead>
    <tbody>

        {{-- Pro Raster ein Block --}}
        @foreach($allGridIds as $gid)
            @php
                $grid      = $grids->firstWhere('id', $gid);
                $gridSlots = $slotsByGridId->get($gid, collect())->sortBy('slot_number');
                $gridRooms = $roomsByGrid[$gid] ?? [];
                $slotRows  = $gridSlots->isNotEmpty() ? $gridSlots : collect([null]);
            @endphp

            {{-- Raster-Überschrift --}}
            <tr>
                <td colspan="20" style="font-weight:bold; background:#e5e7eb;">
                    {{ $grid?->name ?? 'Standard-Raster' }}
                </td>
            </tr>
            {{-- Spalten-Header --}}
            <tr>
                <td style="font-weight:bold;">Raum</td>
                @if($gridSlots->isEmpty())
                    <td style="font-style:italic; color:#9ca3af;">(keine Zeitslots)</td>
                @else
                    @foreach($gridSlots as $slot)
                        <td style="font-weight:bold;">{{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}@if($slot->label) ({{ $slot->label }})@endif</td>
                    @endforeach
                @endif
            </tr>
            {{-- Raumzeilen --}}
            @foreach($gridRooms as $roomData)
                <tr>
                    <td>Raum {{ $roomData['room']->room_number ?? $roomData['room']->id }} {{ $roomData['courseString'] }}</td>
                    @foreach($slotRows as $slot)
                        @php $cell = $roomData['assignments']->flatMap(fn($a) => $a->supervisions)->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null); @endphp
                        <td>{{ $cell->map(fn($s) => $s->teacher->shortcode ?? '?')->implode(', ') }}</td>
                    @endforeach
                </tr>
            @endforeach
            <tr><td></td></tr>
        @endforeach

        {{-- Gangaufsicht & Reserve --}}
        <tr>
            <td colspan="20" style="font-weight:bold; background:#e5e7eb;">
                Gangaufsicht &amp; Reserve – Raster: {{ $defaultGrid?->name ?? 'Standard' }}
            </td>
        </tr>
        <tr>
            <td style="font-weight:bold;">Position</td>
            @foreach($defaultSlotRows as $slot)
                <td style="font-weight:bold;">@if($slot){{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}@else ohne Zeitslot@endif</td>
            @endforeach
        </tr>
        <tr>
            <td style="font-weight:bold;">Gangaufsicht</td>
            @foreach($defaultSlotRows as $slot)
                @php $cell = $corridorSupervisions->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null); @endphp
                <td>{{ $cell->map(fn($s) => $s->teacher->shortcode ?? '?')->implode(', ') }}</td>
            @endforeach
        </tr>
        <tr>
            <td style="font-weight:bold;">Reserve (Lehrerzimmer)</td>
            @foreach($defaultSlotRows as $slot)
                @php $cell = $reserveSupervisions->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null); @endphp
                <td>{{ $cell->map(fn($s) => $s->teacher->shortcode ?? '?')->implode(', ') }}</td>
            @endforeach
        </tr>

        {{-- Zusätzliche Positionen --}}
        @if($centralTypes->isNotEmpty())
            <tr><td></td></tr>
            <tr>
                <td colspan="20" style="font-weight:bold; background:#e5e7eb;">Zusätzliche Positionen</td>
            </tr>
            <tr>
                <td style="font-weight:bold;">Position</td>
                @foreach($allSlots as $slot)
                    <td style="font-weight:bold;">@if($slot){{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}@if($slot->label) ({{ $slot->label }})@endif@else ohne Zeitslot@endif</td>
                @endforeach
            </tr>
            @foreach($centralTypes as $type)
                <tr>
                    <td style="font-weight:bold;">{{ $type->name }}</td>
                    @foreach($allSlots as $slot)
                        @php $cell = ($centralSupervisions->get($type->id) ?? collect())->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null); @endphp
                        <td>{{ $cell->map(fn($s) => $s->teacher->shortcode ?? '?')->implode(', ') }}</td>
                    @endforeach
                </tr>
            @endforeach
        @endif

    </tbody>
</table>
