<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $date->slot->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $date->exam_date->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}
                    · {{ \Carbon\Carbon::parse($date->slot->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($date->slot->end_time)->format('H:i') }}
                    · {{ $date->slot->getDurationMinutes() }} Min.
                </p>
            </div>
            <a href="{{ route('written-exams.dates.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">


            {{-- Slot-Info --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Slot-Details</h3>
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Niveau</dt>
                        <dd class="mt-0.5 font-semibold">
                            @if($date->slot->requirement_level === 'grundlegend')
                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">gA</span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">eA</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Fächer</dt>
                        <dd class="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">
                            {{ $date->slot->subjects->pluck('name')->join(', ') ?: '–' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Prüfungsjahr</dt>
                        <dd class="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">{{ $date->exam_year }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Art</dt>
                        <dd class="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">
                            {{ $date->slot->subjects->count() > 1 ? 'Sammeltermin' : 'Einzelfachtermin' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Fehlende Kurse --}}
            @if($missingCourses->isNotEmpty())
                <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-400 px-4 py-3 rounded-lg text-sm text-amber-800 dark:text-amber-200">
                    <p class="font-semibold mb-1">Noch nicht zugeordnete Kurse:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($missingCourses as $course)
                            <span class="px-2 py-0.5 rounded border border-amber-300 dark:border-amber-700 bg-white dark:bg-gray-800 font-mono text-xs">
                                {{ $course->course_code }} ({{ $course->writtenStudents->count() }})
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Raumzuordnungen --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                        Raumzuordnungen
                        <span class="ml-2 text-xs font-normal text-gray-500 dark:text-gray-400">({{ $assignments->count() }})</span>
                    </h3>
                    @unlessarchive
                    @can('create', \App\Models\WrittenExamAssignment::class)
                        <a href="{{ route('written-exams.dates.day-assign', $date->exam_date->format('Y-m-d')) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Tages-Zuordnung bearbeiten
                        </a>
                    @endcan
                    @endarchive
                </div>

                @if($assignments->isEmpty())
                    <div class="p-10 text-center text-gray-400 dark:text-gray-500 text-sm">
                        Noch keine Raumzuordnungen für diesen Slot.
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($assignmentsByRoom as $roomId => $roomAssignments)
                            @php
                                $room = $roomAssignments->first()->room;
                                $totalAssigned = $roomAssignments->sum('seat_assignments_count');
                                $totalStudents = $roomAssignments->sum(fn($a) => $a->course?->writtenStudents->count() ?? 0);
                            @endphp
                            <div class="px-5 py-3 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100 text-sm">{{ $room?->room_number ?? 'Raum #'.$roomId }}</span>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($roomAssignments as $a)
                                            @if($a->course)
                                                <span class="px-1.5 py-0.5 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                                    {{ $a->course->course_code }}
                                                    @if($a->student_ids) <span class="text-amber-600">NTA</span> @endif
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $totalAssigned }}/{{ $totalStudents }} Sitzplätze
                                    </span>
                                    @if($room)
                                        <a href="{{ route('written-exams.room-seating-plan', [$date->id, $room->id]) }}"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900/30 dark:hover:bg-green-900/50 dark:text-green-300 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                            Sitzplan
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
