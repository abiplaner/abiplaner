<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Schriftliche Prüfungstermine
                @if($activeYear)
                    <span class="ml-2 text-base font-normal text-gray-500 dark:text-gray-400">{{ $activeYear }}</span>
                @endif
            </h2>
            <div class="flex items-center gap-2">
                @if($availableYears->count() > 1)
                    <form method="GET" action="{{ route('written-exams.dates.index') }}">
                        <select name="year" data-submit-on-change
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-md px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                            @foreach($availableYears as $yr)
                                <option value="{{ $yr }}" @selected($yr == $activeYear)>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                @can('viewAny', \App\Models\WrittenExamYearDate::class)
                    <a href="{{ route('written-exams.exports.dates-overview-pdf') }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Terminübersicht PDF
                    </a>
                    <a href="{{ route('written-exams.exports.dates-overview-excel') }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Terminübersicht Excel
                    </a>
                    <a href="{{ route('written-exams.exports.dates-overview-course-checklists') }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        Kurslisten Endkontrolle
                    </a>
                    <a href="{{ route('written-exams.exports.all-rooms-seating-plans-pdf') }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded transition-colors"
                       style="background-color:#ede9fe; color:#6d28d9;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                        Alle Sitzpläne PDF
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-3">

            @if(session('success'))
                <div class="px-4 py-3 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Statistik-Übersicht --}}
            @if($statsTotal > 0)
                @php
                    $roomPct  = round($statsWithRoom  / $statsTotal * 100);
                    $seatPct  = round($statsWithSeats / $statsTotal * 100);
                    $roomDone = $statsWithRoom  === $statsTotal;
                    $seatDone = $statsWithSeats === $statsTotal;
                @endphp
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg px-5 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Planungsstand {{ $activeYear }}
                        </h3>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $statsTotal }} Kurs-Slots</span>
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        {{-- Räume --}}
                        <div>
                            <div class="flex items-end justify-between mb-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                    </svg>
                                    Räume zugeordnet
                                </span>
                                <span class="text-sm font-semibold {{ $roomDone ? 'text-green-600 dark:text-green-400' : 'text-amber-500 dark:text-amber-400' }}">
                                    {{ $statsWithRoom }}&thinsp;/&thinsp;{{ $statsTotal }}
                                </span>
                            </div>
                            <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                     style="width: {{ $roomPct }}%; background-color: {{ $roomDone ? '#22c55e' : '#fbbf24' }}"></div>
                            </div>
                            @if(!$roomDone)
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                    {{ $statsTotal - $statsWithRoom }} Kurs{{ ($statsTotal - $statsWithRoom) !== 1 ? 'e' : '' }} noch ohne Raum
                                </p>
                            @else
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">Alle Räume zugeordnet ✓</p>
                            @endif
                        </div>
                        {{-- Sitzpläne --}}
                        <div>
                            <div class="flex items-end justify-between mb-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Sitzplätze vergeben
                                </span>
                                <span class="text-sm font-semibold {{ $seatDone ? 'text-green-600 dark:text-green-400' : ($statsWithSeats > 0 ? 'text-blue-500 dark:text-blue-400' : 'text-gray-400') }}">
                                    {{ $statsWithSeats }}&thinsp;/&thinsp;{{ $statsTotal }}
                                </span>
                            </div>
                            <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                     style="width: {{ $seatPct }}%; background-color: {{ $seatDone ? '#22c55e' : '#60a5fa' }}"></div>
                            </div>
                            @if(!$seatDone)
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                    {{ $statsTotal - $statsWithSeats }} Kurs{{ ($statsTotal - $statsWithSeats) !== 1 ? 'e' : '' }} noch ohne Sitzplan
                                </p>
                            @else
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">Alle Sitzpläne vergeben ✓</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if($datesByDay->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-10 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-lg font-medium">Keine Prüfungstermine vorhanden</p>
                    <p class="text-sm mt-1 text-gray-400">Super-Admins können Termine über die Slot-Verwaltung anlegen.</p>
                </div>
            @else
                @foreach($datesByDay as $dayKey => $dayDates)
                    @php
                        $dayCarbon       = \Carbon\Carbon::createFromFormat('Y-m-d', $dayKey);
                        $dayLabel        = $dayCarbon->locale('de')->isoFormat('dddd, DD. MMMM YYYY');
                        $daySubjectNames = $dayDates
                            ->flatMap(fn($d) => ($eligibleCoursesByDate[$d->id] ?? collect())
                                ->filter(fn($c) => ($writtenCountByCourse[$c->id] ?? 0) > 0)
                                ->map(fn($c) => $c->subject?->name)
                                ->filter()
                            )
                            ->unique()->sort()->values();

                        // Tageszählung: Schüler (unique Kurse, keine Doppelzählung bei Sammelslots)
                        $dayStudentTotal = $dayDates
                            ->flatMap(fn($d) => ($eligibleCoursesByDate[$d->id] ?? collect())
                                ->filter(fn($c) => ($writtenCountByCourse[$c->id] ?? 0) > 0)
                            )
                            ->unique('id')
                            ->sum(fn($c) => $writtenCountByCourse[$c->id] ?? 0);

                        // Räume und HV-Räume (unique room_ids getrennt)
                        $dayRoomIds   = collect();
                        $dayHvRoomIds = collect();
                        foreach ($dayDates as $_d) {
                            foreach ($assignmentsByDate->get($_d->id, collect()) as $_a) {
                                if ($_a->room_id) {
                                    if ($_a->is_hoerverstaendnis) {
                                        $dayHvRoomIds->push($_a->room_id);
                                    } else {
                                        $dayRoomIds->push($_a->room_id);
                                    }
                                }
                            }
                        }
                        $dayRoomCount   = $dayRoomIds->unique()->count();
                        $dayHvRoomCount = $dayHvRoomIds->unique()->count();
                    @endphp
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        {{-- Tag-Header --}}
                        <div class="px-4 py-2.5 bg-gray-50 dark:bg-gray-700/60 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-wrap">
                                <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="font-semibold text-gray-800 dark:text-gray-100 text-sm">{{ $dayLabel }}</span>
                                {{-- Fach-Badges --}}
                                @foreach($daySubjectNames as $subjName)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $subjName }}
                                    </span>
                                @endforeach
                                {{-- Trennstrich --}}
                                @if($dayStudentTotal > 0 || $dayRoomCount > 0)
                                    <span class="text-gray-300 dark:text-gray-600 text-xs">|</span>
                                @endif
                                {{-- Schüleranzahl --}}
                                @if($dayStudentTotal > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ $dayStudentTotal }} Schüler
                                    </span>
                                @endif
                                {{-- Reguläre Räume --}}
                                @if($dayRoomCount > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                        </svg>
                                        {{ $dayRoomCount }} {{ $dayRoomCount === 1 ? 'Raum' : 'Räume' }}
                                    </span>
                                @endif
                                {{-- HV-Räume (nur wenn vorhanden) --}}
                                @if($dayHvRoomCount > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-orange-600 dark:text-orange-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                        </svg>
                                        +{{ $dayHvRoomCount }} HV
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                @php
                                    // Exporte-Dropdown: prüfen ob dieser Tag Räume mit Sitzplänen hat
                                    $dayHasExports = false;
                                    foreach ($dayDates as $_ed) {
                                        if (($assignmentsByDate->get($_ed->id) ?? collect())
                                            ->filter(fn($_a) => $_a->room_id && empty($_a->student_ids) && ($_a->seat_assignments_count ?? 0) > 0)
                                            ->isNotEmpty()) {
                                            $dayHasExports = true;
                                            break;
                                        }
                                    }
                                @endphp
                                @if($dayHasExports)
                                    @can('viewAny', \App\Models\WrittenExamYearDate::class)
                                        <div class="relative" x-data="{ open: false, btnRect: {} }">
                                            <button @click="open = !open; if(open) btnRect = $el.getBoundingClientRect()"
                                                    class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-md transition-colors"
                                                    style="background-color:#ede9fe; color:#6d28d9;"
                                                    @mouseenter="$el.style.backgroundColor='#ddd6fe'"
                                                    @mouseleave="$el.style.backgroundColor='#ede9fe'">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                                Exporte
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                            <template x-teleport="body">
                                            <div x-show="open"
                                                 @click.away="open = false"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                 :style="`position:fixed; top:${btnRect.bottom + 4}px; right:${window.innerWidth - btnRect.right}px; z-index:9999;`"
                                                 class="w-56 origin-top-right rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5"
                                                 style="display: none;">
                                                {{-- Tagesweite kombinierte Sitzplan-Exporte (alle Räume) --}}
                                                <div class="px-3 py-1.5 bg-gray-50 dark:bg-gray-700/60 border-b border-gray-200 dark:border-gray-600">
                                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Alle Räume – kombiniert</span>
                                                </div>
                                                <div class="py-1">
                                                    <a href="{{ route('written-exams.exports.day-seating-plans-pdf', $dayKey) }}"
                                                       class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700" style="color:#6d28d9;">
                                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                                        </svg>
                                                        Sitzpläne (PDF)
                                                    </a>
                                                    <a href="{{ route('written-exams.exports.day-seat-cards-pdf', $dayKey) }}"
                                                       class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700" style="color:#6d28d9;">
                                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                                        </svg>
                                                        Platzkarten (PDF)
                                                    </a>
                                                    <a href="{{ route('written-exams.exports.day-student-lists-pdf', $dayKey) }}"
                                                       class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700" style="color:#6d28d9;">
                                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        Schülerlisten (PDF)
                                                    </a>
                                                </div>
                                                {{-- Terminweite Aufsichts- und Raumübersicht pro Slot --}}
                                                @foreach($dayDates as $_ed)
                                                    @php
                                                        $_hasAssignments = ($assignmentsByDate->get($_ed->id) ?? collect())
                                                            ->filter(fn($_a) => $_a->room_id && empty($_a->student_ids))
                                                            ->isNotEmpty();
                                                    @endphp
                                                    @if($_hasAssignments)
                                                        <div class="border-t border-gray-100 dark:border-gray-700">
                                                            @if($dayDates->count() > 1)
                                                                <div class="px-3 py-1 bg-gray-50 dark:bg-gray-700/60">
                                                                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">
                                                                        {{ \Carbon\Carbon::parse($_ed->slot->start_time)->format('H:i') }}
                                                                        &middot; {{ $_ed->slot->requirement_level === 'grundlegend' ? 'gA' : 'eA' }}
                                                                    </span>
                                                                </div>
                                                            @else
                                                                <div class="px-3 pt-1.5">
                                                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Terminweite Exporte</span>
                                                                </div>
                                                            @endif
                                                            <div class="py-1">
                                                                <a href="{{ route('written-exams.exports.supervision-lists', $_ed->id) }}"
                                                                   class="flex items-center gap-2 px-3 py-2 text-xs text-blue-600 dark:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                                                    </svg>
                                                                    Aufsichtslisten (PDF)
                                                                </a>
                                                                <a href="{{ route('written-exams.exports.room-overview', $_ed->id) }}"
                                                                   class="flex items-center gap-2 px-3 py-2 text-xs text-blue-600 dark:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                                                    </svg>
                                                                    Raumübersicht (PDF)
                                                                </a>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                            </template>
                                        </div>
                                    @endcan
                                @endif
                                @unlessarchive
                                @can('create', \App\Models\WrittenExamAssignment::class)
                                    <a href="{{ route('written-exams.dates.day-assign', $dayKey) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-md bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                        Räume & Schüler zuordnen
                                    </a>
                                @endcan
                                @endarchive
                            </div>
                        </div>

                        {{-- Slots des Tages --}}
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($dayDates as $date)
                                @php
                                    $slot            = $date->slot;
                                    $eligible        = $eligibleCoursesByDate[$date->id] ?? collect();
                                    $dateAssignments = $assignmentsByDate->get($date->id, collect());
                                @endphp
                                @if($eligible->isEmpty()) @continue @endif
                                @php
                                    $startTime  = \Carbon\Carbon::parse($slot->start_time)->format('H:i');
                                    $endTime    = \Carbon\Carbon::parse($slot->end_time)->format('H:i');
                                    $levelLabel = $slot->requirement_level === 'grundlegend' ? 'gA' : 'eA';
                                    $levelColor = $slot->requirement_level === 'grundlegend'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300';
                                @endphp
                                @php
                                    $slotSubjectNames = $eligible
                                        ->filter(fn($c) => ($writtenCountByCourse[$c->id] ?? 0) > 0)
                                        ->map(fn($c) => $c->subject?->name)
                                        ->filter()->unique()->sort()->values();
                                    if (str_contains($slot->name, '–') && $slotSubjectNames->isNotEmpty()) {
                                        $slotNameBase    = trim(explode('–', $slot->name)[0]);
                                        $slotDisplayName = $slotNameBase . ' – ' . $slotSubjectNames->join(', ');
                                    } else {
                                        $slotDisplayName = $slot->name;
                                    }
                                @endphp

                                <div class="px-4 py-3">
                                    {{-- Slot-Info-Zeile --}}
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <span class="text-xs font-mono font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">
                                            {{ $startTime }}–{{ $endTime }}
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $slotDisplayName }}</span>
                                        <span class="text-xs px-1.5 py-0.5 rounded {{ $levelColor }}">{{ $levelLabel }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $slot->getDurationMinutes() }}&thinsp;Min.</span>
                                    </div>

                                    {{-- Kurs-Chips; NTA-Assignments nicht als eigener Chip, sondern als Badge am Ziel-Chip --}}
                                    @if($eligible->isEmpty())
                                        <p class="text-xs text-gray-400 dark:text-gray-500 italic">Keine passenden Kurse.</p>
                                    @else
                                        @php
                                            // NTA-Assignments nach Raum gruppiert (für Badge am Ziel-Chip)
                                            $ntaByRoom = $dateAssignments
                                                ->filter(fn($a) => !empty($a->student_ids) && $a->room_id)
                                                ->groupBy('room_id');
                                        @endphp
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($eligible as $course)
                                                @php $cnt = $writtenCountByCourse[$course->id] ?? 0; @endphp
                                                @if($cnt === 0) @continue @endif
                                                @php
                                                    // Nur Nicht-NTA-Assignments als eigene Chips (NTA = student_ids gesetzt)
                                                    $courseAssignments = $dateAssignments
                                                        ->where('course_id', $course->id)
                                                        ->filter(fn($a) => empty($a->student_ids))
                                                        ->values();
                                                @endphp
                                                @if($courseAssignments->isEmpty())
                                                    <span
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-xs bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800"
                                                        title="Kein Raum zugewiesen">
                                                        <span class="font-mono font-semibold">{{ $course->course_code }}</span>
                                                        <span class="opacity-70">({{ $cnt }})</span>
                                                    </span>
                                                @else
                                                    @foreach($courseAssignments as $assignment)
                                                        @php
                                                            $isHv     = $assignment->is_hoerverstaendnis;
                                                            $hasRoom  = (bool) $assignment->room_id;
                                                            $hasSeats = $hasRoom && ($assignment->seat_assignments_count ?? 0) > 0;
                                                        @endphp
                                                        @php
                                                            if ($isHv) {
                                                                $chipColor = $hasSeats
                                                                    ? 'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-700'
                                                                    : ($hasRoom
                                                                        ? 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/20 dark:text-orange-300 dark:border-orange-800'
                                                                        : 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800');
                                                            } else {
                                                                $chipColor = $hasSeats
                                                                    ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
                                                                    : ($hasRoom
                                                                        ? 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700'
                                                                        : 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800');
                                                            }
                                                            $typeLabel = $isHv ? ' HV' : '';
                                                            $chipTag   = $hasRoom ? 'a' : 'span';
                                                            $chipHref  = $hasRoom
                                                                ? route('written-exams.room-seating-plan', [$date->id, $assignment->room_id]) . ($isHv ? '?hv=1' : '')
                                                                : null;
                                                            // NTA-Gäste aus anderen Kursen im selben Raum
                                                            $ntaGuests = $hasRoom
                                                                ? ($ntaByRoom->get($assignment->room_id) ?? collect())
                                                                    ->filter(fn($a) => $a->course_id !== $course->id)
                                                                : collect();
                                                            $ntaGuestInfo = $ntaGuests->map(fn($a) => ($a->course?->course_code ?? '?') . ' (' . count($a->student_ids ?? []) . ')')->join(', ');
                                                            $chipTitle = $hasSeats
                                                                ? 'Raum: ' . ($assignment->room?->room_number ?? '') . $typeLabel . ' · ' . $assignment->seat_assignments_count . ' Sitzplatz/Sitzplätze vergeben' . ($ntaGuestInfo ? ' · NTA-Gäste: ' . $ntaGuestInfo : '')
                                                                : ($hasRoom
                                                                    ? 'Raum: ' . ($assignment->room?->room_number ?? '') . $typeLabel . ' (Sitzplan fehlt)' . ($ntaGuestInfo ? ' · NTA-Gäste: ' . $ntaGuestInfo : '')
                                                                    : 'Kein Raum zugewiesen');
                                                        @endphp
                                                        <{{ $chipTag }}
                                                            @if($chipHref) href="{{ $chipHref }}" @endif
                                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-xs {{ $chipColor }} {{ $hasRoom ? 'hover:opacity-80 transition-opacity' : '' }}"
                                                            title="{{ $chipTitle }}">
                                                            <span class="font-mono font-semibold">{{ $course->course_code }}</span>
                                                            <span class="opacity-70">({{ $cnt }})</span>
                                                            @if($hasRoom)
                                                                <span class="opacity-60">· {{ $assignment->room?->room_number }}{{ $typeLabel }}</span>
                                                            @endif
                                                            @if($hasSeats)
                                                                <svg class="w-2.5 h-2.5 opacity-60" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @elseif($hasRoom)
                                                                <svg class="w-2.5 h-2.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01"/>
                                                                </svg>
                                                            @endif
                                                            @foreach($ntaGuests as $ntaA)
                                                                <span class="ml-0.5 px-1 py-0 rounded text-xs font-semibold bg-violet-200 text-violet-800 dark:bg-violet-800/40 dark:text-violet-300"
                                                                      title="NTA: {{ $ntaA->course?->course_code }} ({{ count($ntaA->student_ids ?? []) }} Schüler) sitzt in diesem Raum">
                                                                    +NTA {{ $ntaA->course?->course_code }}
                                                                </span>
                                                            @endforeach
                                                        </{{ $chipTag }}>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
