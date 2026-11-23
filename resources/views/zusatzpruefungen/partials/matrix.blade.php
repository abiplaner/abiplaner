{{-- Zeit-Raum-Matrix für Zusatzprüfungen – eine Tabelle pro Tag --}}
@php
    $dayOrderColors = [
        1 => ['cardBg' => '#dbeafe', 'cardText' => '#1d4ed8', 'cardBorder' => '#93c5fd', 'headerBg' => '#eff6ff', 'headerBorder' => '#bfdbfe', 'tableBg' => '#f8faff'],
        2 => ['cardBg' => '#dcfce7', 'cardText' => '#15803d', 'cardBorder' => '#86efac', 'headerBg' => '#f0fdf4', 'headerBorder' => '#bbf7d0', 'tableBg' => '#fafffe'],
        3 => ['cardBg' => '#f3e8ff', 'cardText' => '#7e22ce', 'cardBorder' => '#d8b4fe', 'headerBg' => '#faf5ff', 'headerBorder' => '#e9d5ff', 'tableBg' => '#fdfaff'],
    ];
@endphp
@foreach($days as $dayIndex => $day)
    @php
        $dayNum    = $dayIndex + 1;
        $dayColor  = $dayOrderColors[$dayNum] ?? null;

        // Eindeutige Prüfungsräume dieses Tages (für Spalten)
        $examRooms = $day->dayRooms->sortBy('examRoom.room_number')
            ->pluck('examRoom')->filter()->unique('id')->values();

        // Konfigurierte Zeitslots dieses Tages (sortiert nach Uhrzeit)
        $timeslots = $day->timeslots->sortBy('start_time')->values();

        // Platzierte Prüfungen dieses Tages
        $dayPlaced = $placed->get($day->id, collect());
    @endphp

    <div class="border rounded-lg shadow-sm overflow-hidden mb-4"
         style="border-color: {{ $dayColor ? $dayColor['cardBorder'] : '#e5e7eb' }}; background: {{ $dayColor ? $dayColor['tableBg'] : '#ffffff' }}">

        {{-- Tag-Header --}}
        <div class="px-3 py-2 border-b text-sm font-semibold"
             style="background: {{ $dayColor ? $dayColor['headerBg'] : '#f9fafb' }}; border-color: {{ $dayColor ? $dayColor['headerBorder'] : '#e5e7eb' }}; color: {{ $dayColor ? $dayColor['cardText'] : '#374151' }}">
            @if($dayColor)
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold mr-1.5"
                      style="background: {{ $dayColor['cardBg'] }}; color: {{ $dayColor['cardText'] }}; border: 1px solid {{ $dayColor['cardBorder'] }}">{{ $dayNum }}</span>
            @endif
            {{ $day->date->locale('de')->translatedFormat('l, d.m.Y') }}
            @if($day->label) – {{ $day->label }} @endif
        </div>

        @if($examRooms->isEmpty())
            <p class="px-3 py-4 text-sm text-gray-400">Kein Prüfungsraum konfiguriert.</p>
        @elseif($timeslots->isEmpty())
            <p class="px-3 py-4 text-sm text-gray-400">Keine Zeitslots angelegt – bitte im ⚙-Dialog konfigurieren.</p>
        @else
            <table class="border-collapse text-xs w-full">
                <thead>
                    <tr style="background: {{ $dayColor ? $dayColor['headerBg'] : '#f9fafb' }}">
                        <th class="border border-gray-200 px-2 py-1 text-left text-gray-500 w-32">Zeit</th>
                        @foreach($examRooms as $room)
                            <th class="border border-gray-200 px-2 py-1 text-center text-gray-600 min-w-32">
                                {{ $room->room_number }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeslots as $ts)
                        @php $slotTime = $ts->start_time; @endphp
                        <tr>
                            <td class="border border-gray-200 px-2 py-1.5 text-gray-500 text-right whitespace-nowrap align-top">
                                @php
                                    [$h, $m] = explode(':', $slotTime);
                                    $prepStart = $slotTime;
                                    $prepEnd   = sprintf('%02d:%02d', intdiv((int)$h * 60 + (int)$m + 20, 60), ((int)$h * 60 + (int)$m + 20) % 60);
                                    $examEnd   = sprintf('%02d:%02d', intdiv((int)$h * 60 + (int)$m + 40, 60), ((int)$h * 60 + (int)$m + 40) % 60);
                                @endphp
                                <div class="text-gray-400" style="font-size:10px">Vorbereitung</div>
                                <div class="font-mono text-xs font-semibold text-gray-600">{{ $prepStart }}–{{ $prepEnd }}</div>
                                <div class="text-gray-400 mt-1" style="font-size:10px">Prüfung</div>
                                <div class="font-mono text-xs font-semibold text-gray-700">{{ $prepEnd }}–{{ $examEnd }}</div>
                            </td>
                            @foreach($examRooms as $room)
                                @php
                                    $dayRoomIds = $day->dayRooms
                                        ->where('exam_room_id', $room->id)
                                        ->pluck('id');

                                    $match = $dayPlaced->first(function($p) use ($dayRoomIds, $slotTime) {
                                        return $p->assignment
                                            && $dayRoomIds->contains($p->assignment->zusatzpruef_day_room_id)
                                            && $p->assignment->start_time === $slotTime;
                                    });
                                @endphp
                                <td class="border border-gray-200 align-top p-0">
                                    @if($match)
                                        @php
                                            $ms           = $match->student;
                                            $mSub         = $match->studentExam?->getSubjectName() ?? '–';
                                            // Nachteilsausgleich hängt am Prüfling — Schüler wie
                                            // externer Bewerber.
                                            $mNta         = $match->hatNachteilsausgleich();
                                            $mPruefer     = $match->pruefer->sortBy('sort_order');
                                            $mErstpruefer = $mPruefer->firstWhere('role', 'erstpruefer')?->teacher;
                                            $mZweitkorr   = $mPruefer->firstWhere('role', 'zweitkorrektor')?->teacher;
                                            $mGast        = $mPruefer->firstWhere('role', 'gast')?->teacher;
                                            $mPData = [
                                                'id'        => $match->id,
                                                'subject'   => $mSub,
                                                'subjectId' => $match->studentExam?->fachAusKatalog()?->id,
                                                'student'   => $match->prueflingsName(),
                                                'nta'       => $mNta,
                                                'ntaPct'    => $match->nachteilsausgleichProzent(),
                                                'slot'      => $match->assignment ? [
                                                    'date'       => $match->assignment->day?->date?->locale('de')->translatedFormat('l, d.m.Y'),
                                                    'prepStart'  => substr($match->assignment->start_time, 0, 5),
                                                    'examStart'  => (function() use ($match) {
                                                        [$h, $m] = explode(':', $match->assignment->start_time);
                                                        $min = (int)$h * 60 + (int)$m + $match->assignment->prep_duration_minutes;
                                                        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
                                                    })(),
                                                    'examEnd'    => $match->assignment->endTime(),
                                                    'examRoom'   => $match->assignment->dayRoom?->examRoom?->room_number ?? '–',
                                                    'prepRoom'   => $match->assignment->dayRoom?->prepRoom?->room_number ?? null,
                                                ] : null,
                                                'pruefer'   => $match->pruefer->map(fn($p) => [
                                                    'teacher_id'   => $p->teacher_id,
                                                    'role'         => $p->role,
                                                    'teacher_name' => $p->teacher ? $p->teacher->lastname . ', ' . mb_substr($p->teacher->firstname, 0, 1) . '. (' . $p->teacher->shortcode . ')' : '–',
                                                ])->values()->toArray(),
                                            ];
                                            $mSortOrder  = $match->sort_order ?? 0;
                                            $mOrderColors = [
                                                1 => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'border' => '#93c5fd'],
                                                2 => ['bg' => '#dcfce7', 'text' => '#15803d', 'border' => '#86efac'],
                                                3 => ['bg' => '#f3e8ff', 'text' => '#7e22ce', 'border' => '#d8b4fe'],
                                            ];
                                            $mOrderColor = ($mSortOrder >= 1 && $mSortOrder <= 3) ? $mOrderColors[$mSortOrder] : null;
                                        @endphp
                                        @php $mAbsent = $match->absent; @endphp
                                        <div class="zp-card border rounded m-0.5 p-1.5 {{ $mNta ? 'nta-card' : '' }} {{ $mAbsent ? 'bg-gray-100 border-gray-300 opacity-70' : '' }}"
                                             draggable="{{ $mAbsent ? 'false' : 'true' }}"
                                             @if(!$mAbsent) @dragstart="onDragStart($event, {{ $match->id }})" @endif
                                             style="{{ !$mAbsent && $mOrderColor ? 'background:' . $mOrderColor['bg'] . '; border-color:' . $mOrderColor['border'] . '; border-left: 3px solid ' . $mOrderColor['text'] . ';' : '' }}">
                                            <div class="flex items-start justify-between gap-1">
                                                <div>
                                                    <div class="flex items-center gap-1 mb-0.5">
                                                        @if($mOrderColor && !$mAbsent)
                                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full text-xs font-bold leading-none shrink-0"
                                                                  style="background: {{ $mOrderColor['bg'] }}; color: {{ $mOrderColor['text'] }}; border: 1px solid {{ $mOrderColor['border'] }}">{{ $mSortOrder }}</span>
                                                        @endif
                                                        <div class="font-semibold {{ $mAbsent ? 'line-through text-gray-400' : 'text-gray-800' }}">{{ $match->prueflingsName() }}@if($match->istExtern())<span class="ml-1 text-purple-700" style="font-size:10px" title="Externer Bewerber nach § 61 GSO">ext</span>@endif</div>
                                                    </div>
                                                    <div class="{{ $mAbsent ? 'line-through text-gray-400' : 'text-gray-600' }}">{{ $mSub }}</div>
                                                    @if($mAbsent)
                                                        <span class="inline-block px-1.5 py-0.5 rounded text-red-700 font-semibold"
                                                              style="font-size:10px; background:#fee2e2">nicht angetreten</span>
                                                    @endif
                                                    @if($mNta && !$mAbsent)
                                                        <span class="bg-yellow-100 text-yellow-700 px-1 rounded"
                                                              style="font-size:10px">NTA +{{ $match->nachteilsausgleichProzent() }}%</span>
                                                    @endif
                                                    @if($match->improvement_only && !$mAbsent)
                                                        <span class="bg-emerald-100 text-emerald-700 px-1 rounded"
                                                              style="font-size:10px" title="Nur zur Notenverbesserung">🎯 Verb.</span>
                                                    @endif
                                                    @if($mErstpruefer)
                                                        @php
                                                            $zLabel = $mZweitkorr
                                                                ? $mZweitkorr->lastname . ', ' . mb_substr($mZweitkorr->firstname, 0, 1) . '.'
                                                                : 'N.N.';
                                                        @endphp
                                                        <div class="{{ $mAbsent ? 'text-gray-300' : 'text-gray-500' }} mt-0.5">
                                                            1: {{ $mErstpruefer->lastname }}, {{ mb_substr($mErstpruefer->firstname, 0, 1) }}.
                                                            &nbsp;·&nbsp;2: {{ $zLabel }}
                                                            @if($mGast)
                                                                &nbsp;·&nbsp;3: {{ $mGast->lastname }}, {{ mb_substr($mGast->firstname, 0, 1) }}.
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div class="{{ $mAbsent ? 'text-gray-300' : 'text-gray-400' }} mt-0.5">
                                                        {{ $match->assignment->start_time }} – {{ $match->assignment->endTime() }}
                                                    </div>
                                                </div>
                                                <div class="flex flex-col gap-1 shrink-0">
                                                    @if(!$mAbsent)
                                                        <button @click.stop="openDrawer({{ json_encode($mPData) }})"
                                                                class="text-blue-400 hover:text-blue-600" title="Prüfer">✏</button>
                                                    @endif
                                                    <button @click.stop="toggleAbsent({{ $match->id }})"
                                                            class="{{ $mAbsent ? 'text-green-500 hover:text-green-700' : 'text-orange-400 hover:text-orange-600' }}"
                                                            title="{{ $mAbsent ? 'Anwesenheit wiederherstellen' : 'Nicht angetreten markieren' }}">{{ $mAbsent ? '↩' : '⊘' }}</button>
                                                    @if(!$mAbsent)
                                                        <button @click.stop="unplace({{ $match->id }})"
                                                                class="text-red-400 hover:text-red-600" title="Zurück in Sidebar">×</button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        @php
                                            $dropRoomId = $day->dayRooms
                                                ->where('exam_room_id', $room->id)
                                                ->first()?->id;
                                        @endphp
                                        <div class="zp-drop-cell h-11 rounded m-0.5"
                                             data-day-id="{{ $day->id }}"
                                             data-start-time="{{ substr((string) $slotTime, 0, 5) }}"
                                             @dragover="onDragOver($event)"
                                             @dragleave="onDragLeave($event)"
                                             @drop="onDrop($event, {{ $day->id }}, {{ $dropRoomId ?? 0 }}, '{{ $slotTime }}')">
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach
