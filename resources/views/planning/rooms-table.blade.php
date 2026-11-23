{{--
    Partial: Matrix-Tabelle für eine Woche
    Variablen: $dates (Collection), $matrixRooms, $globalDefaults, $dayEntries
--}}
@if($matrixRooms->isEmpty())
    <div class="px-6 py-4 text-sm text-gray-500 italic">Keine Räume ausgewählt.</div>
@else
<div class="overflow-x-auto">
    <table class="divide-y divide-gray-200 w-full" style="min-width: max-content;">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 min-w-[140px]">
                    Raum
                </th>
                @foreach($dates as $date)
                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[130px]">
                    {{ \Carbon\Carbon::parse($date)->locale('de')->isoFormat('dd D.M.') }}
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
            @foreach($matrixRooms as $room)
            @php
                $globalEntry  = $globalDefaults[$room->id] ?? null;
                $globalRole   = $globalEntry?->room_role;
                $isAvailable  = isset($globalDefaults[$room->id]);
            @endphp
            <tr data-matrix-room="{{ $room->id }}"
                class="hover:bg-gray-50"
                style="{{ $isAvailable ? '' : 'display:none' }}">
                <td class="px-4 py-3 text-sm font-semibold text-gray-900 sticky left-0 bg-white z-10 border-r border-gray-100">
                    {{ $room->room_number }}
                    {{-- Globaler Default als Referenz --}}
                    @if($globalRole)
                    @php
                        $gLabel = match($globalRole) { 'exam'=>'P', 'preparation'=>'V', 'waiting'=>'W', 'corridor'=>'G', default=>'?' };
                        $gBg    = match($globalRole) { 'exam'=>'#2563eb', 'preparation'=>'#ca8a04', 'waiting'=>'#16a34a', 'corridor'=>'#475569', default=>'#999' };
                    @endphp
                    <span class="inline-block mt-1 text-xs px-1.5 py-0.5 rounded text-white" style="background:{{ $gBg }}; font-size:10px">
                        Standard: {{ $gLabel }}
                    </span>
                    @endif
                </td>
                @foreach($dates as $date)
                @php
                    $dateStr   = \Carbon\Carbon::parse($date)->format('Y-m-d');
                    $dayEntry  = $dayEntries[$dateStr][$room->id] ?? null;
                    $dayRole   = $dayEntry?->room_role;
                    // Anzeigerolle: tagesspezifisch → global → keine
                    $showRole  = $dayRole ?? $globalRole;
                    $inherited = ($dayRole === null && $globalRole !== null);
                @endphp
                <td class="px-3 py-3 text-center">
                    <div class="inline-flex flex-col items-center gap-1">
                        <div class="inline-flex rounded gap-px @archive pointer-events-none @endarchive"
                             data-day-group
                             data-active-role="{{ $dayRole ?? '' }}">

                            @php
                                $roles = ['exam', 'preparation', 'waiting', 'corridor'];
                                $bgs   = ['#2563eb', '#ca8a04', '#16a34a', '#475569'];
                                $labels = ['P', 'V', 'W', 'G'];
                                $corners = ['rounded-l', '', '', 'rounded-r'];
                                $borders = ['border', 'border-t border-b', 'border-t border-b', 'border'];
                            @endphp
                            @foreach($roles as $i => $r)
                            <button type="button"
                                    title="{{ ['P'=>'Prüfungsraum','V'=>'Vorbereitungsraum','W'=>'Warteraum','G'=>'Gang'][$labels[$i]] }}"
                                    data-day-role="{{ $r }}" data-date="{{ $dateStr }}" data-room="{{ $room->id }}"
                                    class="day-role-btn px-2 py-1 text-xs {{ $corners[$i] }} {{ $borders[$i] }} transition"
                                    style="{{ $dayRole === $r
                                        ? "background:{$bgs[$i]};color:#fff;border-color:{$bgs[$i]};font-weight:600"
                                        : 'background:#fff;color:#6b7280;border-color:#d1d5db' }}">
                                {{ $labels[$i] }}
                            </button>
                            @endforeach
                        </div>
                        {{-- Hinweis "aus Standard" wenn tagesspezifisch nichts gesetzt ist --}}
                        @if($inherited)
                        <span class="inherited-badge text-gray-400" style="font-size:10px; line-height:1.2">
                            ↑ Standard
                        </span>
                        @else
                        <span class="inherited-badge text-gray-400" style="font-size:10px; line-height:1.2; display:none">
                            ↑ Standard
                        </span>
                        @endif
                    </div>
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
