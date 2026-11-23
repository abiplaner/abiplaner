<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Aufsichten – Schriftliche Prüfungen') }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('written-exams.exports.supervisions-signature-sheets-pdf') }}"
                   target="_blank"
                   class="inline-flex items-center px-4 py-2 bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/30 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 text-sm font-medium rounded-lg transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    PDF Unterschriftsbögen
                </a>
                <a href="{{ route('written-exams.exports.supervisions-overview-pdf') }}"
                   target="_blank"
                   class="inline-flex items-center px-4 py-2 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 text-sm font-medium rounded-lg transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF Gesamtplan
                </a>
                <a href="{{ route('written-exams.exports.supervisions-overview-excel') }}"
                   class="inline-flex items-center px-4 py-2 bg-green-100 hover:bg-green-200 dark:bg-green-900/30 dark:hover:bg-green-900/50 text-green-700 dark:text-green-300 text-sm font-medium rounded-lg transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Excel Gesamtplan
                </a>
                <a href="{{ route('written-exams.exports.supervisions-teacher-list-pdf') }}"
                   target="_blank"
                   class="inline-flex items-center px-4 py-2 bg-orange-100 hover:bg-orange-200 dark:bg-orange-900/30 dark:hover:bg-orange-900/50 text-orange-700 dark:text-orange-300 text-sm font-medium rounded-lg transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    PDF Lehrkräfte
                </a>
                <a href="{{ route('written-exams.supervisions.time-slots.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Zeitslots verwalten
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if($examDates->isEmpty())
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-lg font-medium">Noch keine Prüfungstermine vorhanden.</p>
                            <p class="mt-1 text-sm">Prüfungstermine können unter <strong>Abitur → Termine &amp; Planung</strong> angelegt werden.</p>
                        </div>
                    @else

                        {{-- Statistik-Kacheln --}}
                        <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200">Prüfungstermine</h3>
                                <p class="text-3xl font-bold text-blue-900 dark:text-blue-100">{{ $datesByDate->count() }}</p>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                                <h3 class="text-sm font-semibold text-green-800 dark:text-green-200">Raumaufsichten</h3>
                                <p class="text-3xl font-bold text-green-900 dark:text-green-100">{{ $examDates->sum('supervision_room_count') }}</p>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                                <h3 class="text-sm font-semibold text-purple-800 dark:text-purple-200">Gangaufsichten</h3>
                                <p class="text-3xl font-bold text-purple-900 dark:text-purple-100">{{ $examDates->sum('supervision_corridor_count') }}</p>
                            </div>
                            @php
                                $incomplete = $datesByDate->filter(function($dayDates) {
                                    $allAssignments = $dayDates->flatMap(fn($d) => $d->assignments);
                                    $totalRooms = $allAssignments->pluck('room_id')->filter()->unique()->count();
                                    $totalRoomSupervisions = $dayDates->sum('supervision_room_count');
                                    return $totalRooms > 0 && $totalRoomSupervisions < $totalRooms * 2;
                                })->count();
                            @endphp
                            <div class="{{ $incomplete > 0 ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'bg-green-50 dark:bg-green-900/20' }} p-4 rounded-lg">
                                <h3 class="text-sm font-semibold {{ $incomplete > 0 ? 'text-yellow-800 dark:text-yellow-200' : 'text-green-800 dark:text-green-200' }}">Unvollständig</h3>
                                <p class="text-3xl font-bold {{ $incomplete > 0 ? 'text-yellow-900 dark:text-yellow-100' : 'text-green-900 dark:text-green-100' }}">{{ $incomplete }}</p>
                                @if($incomplete === 0)
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Alle vollständig
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Tabelle --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Datum</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Uhrzeit</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fach</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Räume</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Raumaufsichten</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gangaufsichten</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($datesByDate as $dayString => $dayDates)
                                        @php
                                            $firstDate  = $dayDates->first();
                                            
                                            // Aggregierte Statistiken dieses Tages
                                            $allAssignments = $dayDates->flatMap(fn($d) => $d->assignments);
                                            $totalRooms = $allAssignments->pluck('room_id')->filter()->unique()->count();
                                            $totalRoomSupervisions = $dayDates->sum('supervision_room_count');
                                            $totalCorridorSupervisions = $dayDates->sum('supervision_corridor_count');
                                            
                                            $hasRooms   = $totalRooms > 0;
                                            $roomOk     = $hasRooms && $totalRoomSupervisions >= $totalRooms * 2;
                                            $carbon     = \Carbon\Carbon::parse($dayString)->locale('de');
                                            
                                            // Alle Fächer des Tages sammeln
                                            $subjects   = $dayDates->flatMap(fn($d) => $d->slot?->subjects ?? collect())->unique('id');
                                            $isSammeltermin = $subjects->count() > 1;
                                            $level = $firstDate->slot?->requirement_level === 'grundlegend' ? 'gA' : 'eA';
                                            
                                            // Verschiedene Startzeiten prüfen
                                            $startTimes = $dayDates->map(fn($d) => \Carbon\Carbon::parse($d->slot?->start_time)->format('H:i'))->unique()->sort();
                                            $endTimes = $dayDates->map(fn($d) => \Carbon\Carbon::parse($d->slot?->end_time)->format('H:i'))->unique()->sort();
                                            
                                            if ($startTimes->count() > 1 || $endTimes->count() > 1) {
                                                $timeString = 'ab ' . $startTimes->first() . ' Uhr';
                                            } else {
                                                $timeString = $startTimes->first() . ' Uhr';
                                            }
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            {{-- Datum --}}
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ $carbon->isoFormat('DD.MM.YYYY') }}
                                                </div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                                    {{ $carbon->isoFormat('dddd') }}
                                                </div>
                                            </td>

                                            {{-- Uhrzeit --}}
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                {{ $timeString }}
                                            </td>

                                            {{-- Fach --}}
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                @if($isSammeltermin)
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">(kein Fach)</span>
                                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Sammeltermin für weitere Fächer ({{ $level }})</div>
                                                @else
                                                    @if($subjects->isNotEmpty())
                                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $subjects->pluck('name')->join(', ') }}
                                                        </span>
                                                    @else
                                                        <span class="text-sm text-gray-400 dark:text-gray-500">(kein Fach)</span>
                                                    @endif
                                                @endif
                                            </td>

                                            {{-- Räume --}}
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                                                    {{ $hasRooms ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500' }}">
                                                    {{ $totalRooms }}
                                                </span>
                                            </td>

                                            {{-- Raumaufsichten --}}
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                                                    {{ !$hasRooms ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500'
                                                        : ($roomOk ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                                   : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300') }}">
                                                    {{ $totalRoomSupervisions }}
                                                </span>
                                            </td>

                                            {{-- Gangaufsichten --}}
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                                    {{ $totalCorridorSupervisions }}
                                                </span>
                                            </td>

                                            {{-- Status --}}
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                @if(!$hasRooms)
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                        Keine Räume
                                                    </span>
                                                @elseif($roomOk)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                        Vollständig
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                        Unvollständig
                                                    </span>
                                                @endif
                                            </td>

                                            {{-- Aktionen --}}
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    {{-- Planen --}}
                                                    <a href="{{ route('written-exams.supervisions.plan', $firstDate) }}"
                                                       title="Aufsichten planen"
                                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-700 dark:bg-blue-900/30 dark:hover:bg-blue-900/60 dark:text-blue-300 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </a>
                                                    {{-- Übersicht --}}
                                                    <a href="{{ route('written-exams.supervisions.show', $firstDate) }}"
                                                       title="Übersicht"
                                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
