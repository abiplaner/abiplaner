{{--
    Ein Prüfungswochen-Block des Planungsrasters: alle Prüfungstage der Woche
    $woche, je Tag die Matrix Zeitslots × Räume.

    Bis zum 31.08.2026 stand dieser Block zweimal in _week_grid.blade.php,
    einmal je Woche — 245 zeilengleiche Zeilen. Sie waren schon
    auseinandergelaufen: In der einen Kopie fehlte ein erklärender Kommentar,
    in der anderen ein anderer. Beides harmlos, aber es zeigt, wohin das führt.

    Erwartet aus der aufrufenden Maske: $woche, $examPeriod, $timeSlots,
    $examRoomsByDate, $prepRoomsByDate, $waitRoomsByDate, $globalExamRooms,
    $couplingColorMap — @include reicht sie durch.
--}}
@foreach($examPeriod->getExamDates($woche) as $date)
    <div class="mb-8">
        @php
            $dateStr      = $date->format('Y-m-d');
            $daySlots     = $timeSlots[$dateStr] ?? collect();
            $dayExamRooms = $examRoomsByDate[$dateStr] ?? $globalExamRooms;
            $dayPrepRooms = $prepRoomsByDate[$dateStr] ?? collect();
            $dayWaitRooms = $waitRoomsByDate[$dateStr] ?? collect();
        @endphp
        <h4 class="font-semibold mb-1 text-lg">
            {{ $date->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
        </h4>
        {{-- Vorbereitungs-/Warteräume als Info --}}
        <div class="flex flex-wrap gap-2 mb-3 text-xs">
            @foreach($dayExamRooms as $r)
                <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800">🏫 {{ $r->room_number }}</span>
            @endforeach
            @foreach($dayPrepRooms as $r)
                <span class="inline-flex items-center px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">📚 {{ $r->room_number }} (Vorbereitung)</span>
            @endforeach
            @foreach($dayWaitRooms as $r)
                <span class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 text-green-800">⏳ {{ $r->room_number }} (Warten)</span>
            @endforeach
            @if($dayExamRooms->isEmpty())
                <a href="{{ route('planning.rooms.index') }}" class="text-orange-600 hover:underline">
                    ⚠ Keine Prüfungsräume zugewiesen – Räume &amp; Tage konfigurieren
                </a>
            @endif
        </div>
        
        @if($daySlots->isNotEmpty())
            <div class="border rounded-lg overflow-x-auto">
                <table class="divide-y divide-gray-200" style="min-width: max-content;">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">
                                Zeit
                            </th>
                            @foreach($dayExamRooms as $room)
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">
                                    Raum {{ $room->room_number }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($daySlots as $slot)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-white z-10">
                                    <div class="text-sm">{{ $slot->exam_start_time }}</div>
                                    <div class="text-xs text-gray-500">Slot {{ $slot->slot_number }}</div>
                                </td>
                                @foreach($dayExamRooms as $room)
                                    @php
                                        // Aktives und abgesagtes Assignment getrennt ermitteln
                                        $activeAssignment = $slot->examAssignments
                                            ->where('exam_room_id', $room->id)
                                            ->where('status', '!=', 'cancelled')
                                            ->first();
                                        $cancelledAssignment = $slot->examAssignments
                                            ->where('exam_room_id', $room->id)
                                            ->where('status', 'cancelled')
                                            ->where('cancelled_reason', 'illness')
                                            ->first();
                                        $assignment = $activeAssignment; // Rückwärtskompatibilität
                                    @endphp
                                    <td class="px-2 py-2">
                                        <div class="time-slot-cell min-h-[80px] border-2 border-dashed border-gray-200 rounded p-2 hover:border-blue-400 transition"
                                             data-slot-id="{{ $slot->id }}"
                                             data-room-id="{{ $room->id }}">

                                            {{-- Abgesagter Termin (Erkrankung) --}}
                                            @if($cancelledAssignment)
                                                <div class="mb-1 border border-dashed border-red-300 rounded p-1.5 bg-gray-50 opacity-70"
                                                     data-assignment-id="{{ $cancelledAssignment->id }}"
                                                     data-status="cancelled">
                                                    <div class="flex justify-between items-start gap-1">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-xs font-semibold text-gray-400 line-through truncate">
                                                                {{ $cancelledAssignment->studentExam->getStudentName() }}
                                                            </div>
                                                            <div class="text-xs text-gray-400 line-through">
                                                                {{ $cancelledAssignment->studentExam->getSubjectLabel() }}
                                                            </div>
                                                            <span class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                                                🤒 Erkrankt
                                                            </span>
                                                        </div>
                                                        @unlessarchive
                                                        <div class="flex flex-col gap-0.5 flex-shrink-0">
                                                            <button data-aktion="revert-illness" data-assignment="{{ $cancelledAssignment->id }}" data-period="{{ $examPeriod->id }}"
                                                                    class="text-gray-400 hover:text-green-600"
                                                                    title="Erkrankungs-Markierung rückgängig machen">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6M3 10l6-6"/>
                                                                </svg>
                                                            </button>
                                                            <button data-aktion="open-outside-period" data-assignment="{{ $cancelledAssignment->id }}" data-period="{{ $examPeriod->id }}"
                                                                    class="text-gray-400 hover:text-blue-600"
                                                                    title="Nachtermin außerhalb der Prüfungswochen ansetzen">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        @endarchive
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Aktiver Termin --}}
                                            @if($assignment)
                                                @php
                                                    $couplingId = $assignment->studentExam->coupling_id ?? null;
                                                    $couplingBg = $couplingId ? ($couplingColorMap[$couplingId] ?? null) : null;
                                                    if ($assignment->is_locked) { $cardRing = 'ring-2 ring-blue-400'; $cardBg = $couplingBg ? '' : 'bg-blue-50'; }
                                                    elseif ($assignment->studentExam->isExternalApplicant()) { $cardRing = 'ring-2 ring-purple-300'; $cardBg = $couplingBg ? '' : 'bg-purple-50'; }
                                                    elseif (!$assignment->studentExam->assessor) { $cardRing = 'ring-2 ring-orange-300'; $cardBg = $couplingBg ? '' : 'bg-orange-50'; }
                                                    else { $cardRing = ''; $cardBg = $couplingBg ? '' : 'bg-white'; }
                                                @endphp
                                                <div class="assignment-card border-l-4 rounded p-2 shadow-sm {{ $cardBg }} {{ $cardRing }}"
                                                     style="border-color: {{ $assignment->studentExam->fachAusKatalog()?->color ?? '#999' }}; cursor: {{ $assignment->is_locked ? 'default' : 'grab' }}{{ $couplingBg ? '; background-color: ' . $couplingBg : '' }}"
                                                     data-assignment-id="{{ $assignment->id }}"
                                                     data-student-exam-id="{{ $assignment->studentExam->id }}"
                                                     data-examiner-id="{{ $assignment->studentExam->examiner_id }}"
                                                     data-assessor-id="{{ $assignment->studentExam->assessor_id ?? '' }}"
                                                     data-student-id="{{ $assignment->studentExam->student_id ?? '' }}"
                                                     data-is-locked="{{ $assignment->is_locked ? 'true' : 'false' }}"
                                                     data-lock-reason="{{ $assignment->lock_reason ?? '' }}"
                                                     data-status="{{ $assignment->status }}"
                                                     data-replaces-id="{{ $assignment->replaces_id ?? '' }}"
                                                     draggable="{{ $assignment->is_locked ? 'false' : 'true' }}">
                                                    <div class="flex justify-between items-start">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-sm font-semibold truncate flex items-center gap-1">
                                                                @if($assignment->is_locked)
                                                                    <svg class="w-3 h-3 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                                @if($assignment->studentExam->isExternalApplicant())
                                                                    <span class="text-purple-600 flex-shrink-0" title="Externer Bewerber">📝</span>
                                                                @endif
                                                                @if($assignment->notes === 'Kopplung aufgetrennt')
                                                                    <span class="flex-shrink-0 text-amber-600" title="Kopplung aufgetrennt">⚠🔗</span>
                                                                @elseif($couplingBg)
                                                                    <span class="flex-shrink-0" title="Gekoppelte Prüfung">🔗</span>
                                                                @endif
                                                                @if($assignment->replaces_id)
                                                                    <span class="flex-shrink-0 text-orange-600" title="Nachtermin (Erkrankung)">🔄</span>
                                                                @endif
                                                                {{ $assignment->studentExam->getStudentName() }}
                                                            </div>
                                                            <div class="text-xs text-gray-600">
                                                                {{ $assignment->studentExam->getSubjectLabel() }}
                                                            </div>
                                                            @if($assignment->replaces_id)
                                                                <div class="mt-0.5">
                                                                    <span class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">
                                                                        Nachtermin
                                                                    </span>
                                                                </div>
                                                            @endif
                                                            <div class="text-xs mt-1">
                                                                @if($assignment->studentExam->examiner)
                                                                    <div class="text-gray-500">P: {{ $assignment->studentExam->examiner->shortcode }}</div>
                                                                @else
                                                                    <div class="text-orange-600 font-semibold">⚠ Kein Prüfer!</div>
                                                                @endif
                                                                @if($assignment->studentExam->assessor)
                                                                    <div class="text-gray-500">B: {{ $assignment->studentExam->assessor->shortcode }}</div>
                                                                @else
                                                                    <div class="text-orange-600 font-semibold">⚠ Kein Beisitzer!</div>
                                                                @endif
                                                                @if($assignment->is_locked && $assignment->lock_reason)
                                                                    <div class="text-blue-600 text-xs mt-1 italic">🔒 {{ $assignment->lock_reason }}</div>
                                                                @endif
                                                                @if($assignment->studentExam->hatNachteilsausgleich())
                                                                    @php
                                                                        $prepMin  = (strtotime($slot->prep_end_time) - strtotime($slot->prep_start_time)) / 60;
                                                                        $ntaMin   = $assignment->studentExam->kompensierteDauer((int)$prepMin);
                                                                        $ntaStart = \Carbon\Carbon::createFromTimeString($slot->exam_start_time)->subMinutes($ntaMin)->format('H:i');
                                                                    @endphp
                                                                    <div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                                                        NTA: Vorb. ab {{ $ntaStart }}
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @unlessarchive
                                                        <div class="flex flex-col gap-1 ml-1 flex-shrink-0">
                                                            <button data-aktion="toggle-lock" data-assignment="{{ $assignment->id }}" data-period="{{ $examPeriod->id }}"
                                                                    class="{{ $assignment->is_locked ? 'text-blue-600 hover:text-blue-800' : 'text-gray-400 hover:text-blue-600' }}"
                                                                    title="{{ $assignment->is_locked ? 'Fixierung aufheben' : 'Prüfung fixieren' }}">
                                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                    @if($assignment->is_locked)
                                                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                                    @else
                                                                        <path d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z"/>
                                                                    @endif
                                                                </svg>
                                                            </button>
                                                            {{-- Erkrankungs-Button --}}
                                                            @if(!$assignment->is_locked)
                                                                <button data-aktion="cancel-illness" data-assignment="{{ $assignment->id }}" data-period="{{ $examPeriod->id }}"
                                                                        class="text-gray-400 hover:text-red-600"
                                                                        title="Termin wegen Erkrankung absagen (Prüfung in Seitenleiste)">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-7 4h16a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                    </svg>
                                                                </button>
                                                                <button data-aktion="swap-week" data-assignment="{{ $assignment->id }}" data-period="{{ $examPeriod->id }}" data-week="{{ $assignment->exam_week }}"
                                                                        class="text-indigo-500 hover:text-indigo-700"
                                                                        title="In andere Woche verschieben (Woche {{ $assignment->exam_week === 1 ? 2 : 1 }})">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/>
                                                                    </svg>
                                                                </button>
                                                                <button data-aktion="remove-assignment" data-assignment="{{ $assignment->id }}"
                                                                        class="text-red-600 hover:text-red-800"
                                                                        title="Zuweisung entfernen">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            @endif
                                                        </div>
                                                        @endarchive
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500 text-center py-4 bg-gray-50 rounded">
                Keine Zeitslots für diesen Tag
            </p>
        @endif
    </div>
@endforeach
