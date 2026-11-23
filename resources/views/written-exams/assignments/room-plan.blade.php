<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Raumweiter Sitzplan') }}: {{ $room->room_number }}{{ $isHv ? ' (Hörverstehen)' : '' }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ $writtenExamDate->slot->name }}
                    | {{ $writtenExamDate->exam_date->format('d.m.Y') }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ $assignments->unique('course_id')->count() }} Kurs(e): 
                    @foreach($assignments->unique('course_id') as $assignment)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-{{ $courseColors[$assignment->course_id] }}-100 text-{{ $courseColors[$assignment->course_id] }}-800 dark:bg-{{ $courseColors[$assignment->course_id] }}-900/30 dark:text-{{ $courseColors[$assignment->course_id] }}-300">
                            {{ $assignment->course->course_code }}
                            @if($assignment->is_hoerverstaendnis)
                                <span class="ml-0.5 px-1 rounded bg-teal-200 dark:bg-teal-800 text-teal-800 dark:text-teal-200 font-bold">🎧 HV</span>
                            @endif
                        </span>
                    @endforeach
                </p>
            </div>
            <a href="{{ route('written-exams.dates.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg shadow-md transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('Zurück') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"
             x-data="roomSeatingPlanEditor(
                {{ $writtenExamDate->id }},
                {{ $room->id }},
                {{ json_encode($assignments->pluck('id')->toArray()) }},
                {{ json_encode($students) }},
                {{ json_encode($seats) }},
                {{ json_encode($seatAssignments) }},
                {{ $template->rows }},
                {{ $template->columns }},
                {{ json_encode($courseColors) }},
                {{ json_encode($template->door_positions ?? []) }},
                {{ $isHv ? 'true' : 'false' }},
                '{{ $template->orientation ?? 'top' }}',
                {{ json_encode($externeBewerber ?? []) }}
             )">

            <!-- Info und Aktionen -->
            <div class="bg-white dark:bg-gray-800 overflow-visible shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $room->room_number }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Layout:') }} {{ $template->name }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span x-text="getAssignedCount()"></span> / {{ $template->seats->whereIn('seat_type', ['available', 'reserve'])->count() }} {{ __('Plätze belegt') }}
                                | <span x-text="getUnassignedCount()"></span> {{ __('Schüler ohne Platz') }}
                            </p>
                        </div>
                        
                        <!-- Kurs-Legende -->
                        @php
                            $studentsByCourse = collect($students)->groupBy('course_id');
                        @endphp
                        <div class="flex flex-col gap-2">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Kurse:') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($assignments->unique('course_id') as $assignment)
                                    @php $courseStudentCount = $studentsByCourse->get($assignment->course_id, collect())->count(); @endphp
                                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-{{ $courseColors[$assignment->course_id] }}-100 dark:bg-{{ $courseColors[$assignment->course_id] }}-900/30 border border-{{ $courseColors[$assignment->course_id] }}-300 dark:border-{{ $courseColors[$assignment->course_id] }}-700 rounded-lg">
                                        <div class="w-3 h-3 rounded-full bg-{{ $courseColors[$assignment->course_id] }}-600"></div>
                                        <span class="text-sm font-medium text-{{ $courseColors[$assignment->course_id] }}-900 dark:text-{{ $courseColors[$assignment->course_id] }}-100">
                                            {{ $assignment->course->course_code }}
                                            @if($assignment->is_hoerverstaendnis)
                                                <span class="ml-1 px-1 py-0.5 rounded text-xs bg-teal-200 dark:bg-teal-800 text-teal-800 dark:text-teal-200 font-bold">🎧 HV</span>
                                            @endif
                                        </span>
                                        <span class="text-xs text-{{ $courseColors[$assignment->course_id] }}-700 dark:text-{{ $courseColors[$assignment->course_id] }}-300">
                                            ({{ $courseStudentCount }} {{ __('Schüler') }})
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Aktions-Buttons -->
                    <div class="flex gap-2 flex-wrap pt-4 border-t border-gray-200 dark:border-gray-700">
                        <!-- Auto-Assign Button → öffnet Strategie-Modal -->
                        <button @click="showAutoAssignModal = true"
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ __('Auto-Zuweisung') }}
                        </button>

                        <!-- Layout zurücksetzen & neu zuweisen -->
                        <div class="inline-block relative">
                            <button x-show="!confirmingReset" @click="confirmingReset = true"
                                    style="background-color:#f59e0b;"
                                    @mouseenter="$el.style.backgroundColor='#d97706'"
                                    @mouseleave="$el.style.backgroundColor='#f59e0b'"
                                    class="inline-flex items-center px-4 py-2 text-white font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {{ __('Neu zuweisen') }}
                            </button>
                            <div x-show="confirmingReset" class="flex items-center space-x-2" style="display:none;">
                                <button @click="resetAndAutoAssign(); confirmingReset = false"
                                        style="background-color:#d97706;"
                                        @mouseenter="$el.style.backgroundColor='#b45309'"
                                        @mouseleave="$el.style.backgroundColor='#d97706'"
                                        class="inline-flex items-center px-4 py-2 text-white font-bold rounded-lg transition-colors">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    {{ __('Wirklich zurücksetzen?') }}
                                </button>
                                <button @click="confirmingReset = false"
                                        class="inline-flex items-center px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 rounded-lg transition-colors">
                                    {{ __('Abbrechen') }}
                                </button>
                            </div>
                        </div>

                        <!-- Clear-All UI -->
                        <div class="inline-block relative ml-2">
                            <button x-show="!confirmingClearAll" @click="confirmingClearAll = true" 
                                    class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                {{ __('Alle entfernen') }}
                            </button>
                            <div x-show="confirmingClearAll" class="flex items-center space-x-2" style="display: none;">
                                <button @click="clearAllAssignments(); confirmingClearAll = false" 
                                        class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition-colors shadow-sm">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    {{ __('Wirklich Löschen') }}
                                </button>
                                <button @click="confirmingClearAll = false" 
                                        class="inline-flex items-center px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 rounded-lg transition-colors">
                                    {{ __('Abbrechen') }}
                                </button>
                            </div>
                        </div>
                        
                        <!-- Export-Dropdown -->
                        <div class="relative inline-block text-left" x-data="{ open: false }">
                            <button @click="open = !open" type="button"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                </svg>
                                {{ __('Exportieren') }}
                                <svg class="w-4 h-4 ml-1" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" 
                                 @click.away="open = false"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                 style="display: none;">
                                <div class="py-1">
                                    <a href="{{ route('written-exams.exports.room.seating-plan', [$writtenExamDate, $room]) }}?view=front{{ $isHv ? '&hv=1' : '' }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                        </svg>
                                        {{ __('Sitzplan vorne (PDF)') }}
                                    </a>
                                    <a href="{{ route('written-exams.exports.room.seating-plan', [$writtenExamDate, $room]) }}?view=back{{ $isHv ? '&hv=1' : '' }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                        </svg>
                                        {{ __('Sitzplan hinten (PDF)') }}
                                    </a>
                                    <a href="{{ route('written-exams.exports.room.seat-cards', [$writtenExamDate, $room]) }}{{ $isHv ? '?hv=1' : '' }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                        </svg>
                                        {{ __('Platzkarten (PDF)') }}
                                    </a>
                                    <a href="{{ route('written-exams.exports.room.student-list', [$writtenExamDate, $room]) }}{{ $isHv ? '?hv=1' : '' }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        {{ __('Schülerliste (PDF)') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hauptansicht: Schülerliste nach Kursen + Sitzplan -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                
                <!-- Schülerliste (Sidebar) - Gruppiert nach Kursen -->
                <div class="md:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg sticky top-4">
                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                {{ __('Schüler') }}
                                <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 rounded">
                                    <span x-text="getUnassignedCount()"></span>
                                </span>
                            </h3>
                            
                            <div class="space-y-4 max-h-[70vh] overflow-y-auto">
                                <!-- Nach Kursen gruppiert -->
                                <template x-for="course in getCourses()" :key="'course-' + course.id">
                                    <div>
                                        <div class="mb-2 px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs font-medium">
                                            <span x-text="course.code"></span>
                                            <span class="text-gray-600 dark:text-gray-400">
                                                (<span x-text="getUnassignedCountForCourse(course.id)"></span> / <span x-text="course.total"></span>)
                                            </span>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <template x-for="student in getUnassignedStudentsForCourse(course.id)" :key="'student-' + student.id">
                                                <div :draggable="true"
                                                     @dragstart="handleDragStart($event, student)"
                                                     @dragend="handleDragEnd($event)"
                                                     :class="getStudentCardClass(student.course_id)"
                                                     class="p-3 border-2 rounded-lg cursor-move transition-colors">
                                                    <div class="flex items-start justify-between">
                                                        <div class="flex-1">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="student.name"></p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="student.external_id ? 'ID: ' + student.external_id : ''"></p>
                                                        </div>
                                                        <svg class="w-5 h-5" :class="'text-' + courseColors[student.course_id] + '-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                
                                <!-- Wenn alle zugewiesen -->
                                <template x-if="getUnassignedCount() === 0">
                                    <div class="text-center py-8 text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <p class="mt-2 text-sm">{{ __('Alle Schüler zugewiesen!') }}</p>
                                    </div>
                                </template>
                            </div>

                            <!-- Externe Bewerber -->
                            <template x-if="externe.length > 0">
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        📋 {{ __('Externe Bewerber') }}
                                        <span class="text-xs font-normal text-gray-500" x-text="'(' + unassignedExterne.length + ' offen)'"></span>
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                        {{ __('Bewerber mit schriftlicher Prüfung an diesem Tag.') }}
                                    </p>
                                    <div class="space-y-2">
                                        <template x-for="bewerber in unassignedExterne" :key="'ext-' + bewerber.external_applicant_id">
                                            <div draggable="true"
                                                 @dragstart="handleDragStart($event, bewerber)"
                                                 @dragend="handleDragEnd($event)"
                                                 class="p-3 border-2 border-slate-400 bg-slate-100 dark:bg-slate-700 dark:border-slate-500 rounded-lg cursor-move transition-colors">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100" x-text="bewerber.name"></p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            📋 {{ __('extern') }}
                                                            <template x-if="bewerber.has_compensation">
                                                                <span class="ml-1 px-1 rounded bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 font-bold"
                                                                      x-text="'+' + bewerber.nta_percent + '%'"></span>
                                                            </template>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="unassignedExterne.length === 0">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Alle externen Bewerber sitzen.') }}</p>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Gastschüler-Bereich -->
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    🏫 {{ __('Gastschüler') }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                    {{ __('Schüler eines anderen Gymnasiums ohne eigenen Datensatz.') }}
                                </p>
                                <input type="text"
                                       x-model="guestName"
                                       @keydown.enter.prevent="addGuestStudent()"
                                       placeholder="{{ __('Nachname, Vorname') }}"
                                       class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 mb-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button @click="addGuestStudent()"
                                        :disabled="!guestName.trim()"
                                        class="w-full px-3 py-2 text-sm font-medium text-white bg-gray-500 hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg transition-colors">
                                    {{ __('In Warteliste aufnehmen') }}
                                </button>

                                <!-- Gast-Warteliste -->
                                <template x-if="pendingGuests.length > 0">
                                    <div class="mt-3 space-y-2">
                                        <template x-for="(guest, idx) in pendingGuests" :key="'guest-' + idx">
                                            <div draggable="true"
                                                 @dragstart="handleDragStart($event, guest)"
                                                 @dragend="handleDragEnd($event)"
                                                 class="p-3 border-2 border-gray-400 bg-gray-100 dark:bg-gray-600 dark:border-gray-500 rounded-lg cursor-move transition-colors">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100" x-text="guest.name"></p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">🏫 {{ __('Gastschüler') }}</p>
                                                    </div>
                                                    <button @click="pendingGuests.splice(idx, 1)"
                                                            class="text-red-400 hover:text-red-600 text-lg leading-none"
                                                            title="{{ __('Entfernen') }}">×</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sitzplan-Grid -->
                <div class="md:col-span-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('Sitzplan') }}</h3>
                            
                            <!-- Legende -->
                            <div class="mb-4 flex flex-wrap gap-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-green-50 dark:bg-green-900/20 border-2 border-green-300"></div>
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Verfügbar') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-blue-100 dark:bg-blue-900/30 border-2 border-blue-400"></div>
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Belegt') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-red-50 dark:bg-red-900/20 border-2 border-red-300"></div>
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Blockiert') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-gray-50 dark:bg-gray-700 border-2 border-gray-300"></div>
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Gang') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-400 flex items-center justify-center text-xs font-bold text-amber-700">R</div>
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Reserve') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-400 flex items-center justify-center text-xs font-bold text-blue-700">P</div>
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Lehrerpult') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-amber-600 font-bold text-sm">⏱</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Nachteilsausgleich') }}</span>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <div class="inline-block min-w-full">

                                    <!-- Pult-Indikator OBEN -->
                                    <div x-show="pultSide === 'top'" style="display:none;" class="flex items-center gap-2 mb-2">
                                        <div class="w-8"></div>
                                        <div class="w-10"></div>
                                        <div class="flex-1 flex items-center justify-center gap-2 py-1 px-3 rounded bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold">
                                            <span>🎓</span><span>{{ __('Pult / Tafel') }}</span>
                                        </div>
                                        <div class="w-10"></div>
                                    </div>

                                    <!-- Türen OBEN -->
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-8"></div><!-- Reihen-Label -->
                                        <div class="w-10"></div><!-- links -->
                                        <template x-for="col in columns" :key="'door-top-' + col">
                                            <div class="w-24 h-10 flex flex-col items-center justify-center text-sm font-bold rounded"
                                                 :class="hasDoor('top', col) ? 'text-orange-500 bg-orange-50 dark:bg-orange-900/20 border border-orange-300' : 'text-transparent'">
                                                <span x-show="hasDoor('top', col)" class="text-xs font-bold leading-none">Türe</span>
                                            </div>
                                        </template>
                                        <div class="w-10"></div><!-- rechts -->
                                    </div>


                                    <template x-for="row in rowsReversed" :key="'row-' + row">
                                        <div class="flex gap-2 mb-2">
                                            <!-- Zeilen-Nummer -->
                                            <div class="w-8 flex items-center justify-center text-xs font-medium text-gray-500 dark:text-gray-400">
                                                <span x-text="String(row).padStart(2, '0')"></span>
                                            </div>
                                            <!-- Türe LINKS -->
                                            <div class="w-10 h-24 flex flex-col items-center justify-center text-sm font-bold rounded"
                                                 :class="hasDoor('left', row) ? 'text-orange-500 bg-orange-50 dark:bg-orange-900/20 border border-orange-300' : 'text-transparent'">
                                                <span x-show="hasDoor('left', row)" class="text-xs font-bold leading-none">Türe</span>
                                            </div>
                                            <!-- Sitzplätze -->
                                            <template x-for="col in columns" :key="'col-' + col">
                                                <div @drop="handleDrop($event, row, col)"
                                                     @dragover.prevent="handleDragOver($event, row, col)"
                                                     @dragleave="handleDragLeave($event)"
                                                     :draggable="!!getAssignedStudent(row, col)"
                                                     @dragstart="getAssignedStudent(row, col) && handleSeatDragStart($event, row, col)"
                                                     @dragend="handleDragEnd($event)"
                                                     :class="getSeatClass(row, col)"
                                                     class="w-24 h-24 rounded border-2 flex flex-col items-center justify-center transition-all relative group"
                                                     :data-droppable="(getSeat(row, col)?.seat_type === 'available' || getSeat(row, col)?.seat_type === 'reserve') && !getAssignedStudent(row, col) ? 'true' : 'false'">
                                                    
                                                    <!-- Platznummer -->
                                                    <span class="text-xs font-medium" 
                                                          :class="getSeatTextClass(row, col)"
                                                          x-text="getSeatNumber(row, col)"></span>
                                                    
                                                    <!-- Schüler (wenn zugewiesen) -->
                                                    <template x-if="getAssignedStudent(row, col)">
                                                        <div class="text-center mt-1 px-1 w-full">
                                                            <div class="absolute top-1 left-1 w-2 h-2 rounded-full"
                                                                 :class="getAssignedStudent(row, col)?.is_guest ? 'bg-gray-500' : 'bg-' + getCourseColor(row, col) + '-600'"></div>
                                                            <template x-if="getAssignedStudent(row, col)?.has_compensation">
                                                                <span class="absolute top-0.5 right-5 text-[10px] text-amber-600 font-bold leading-none" title="Nachteilsausgleich">⏱</span>
                                                            </template>
                                                            <p class="text-xs font-medium leading-tight"
                                                               :class="getAssignedStudent(row, col)?.is_guest ? 'text-gray-700 dark:text-gray-200' : 'text-' + getCourseColor(row, col) + '-900 dark:text-' + getCourseColor(row, col) + '-100'"
                                                               x-text="getAssignedStudent(row, col)?.name"></p>
                                                            <p class="text-[10px] leading-tight mt-0.5"
                                                               :class="getAssignedStudent(row, col)?.is_guest ? 'text-gray-500 dark:text-gray-400' : 'text-' + getCourseColor(row, col) + '-700 dark:text-' + getCourseColor(row, col) + '-300'"
                                                               x-text="getAssignedStudent(row, col)?.is_extern ? 'extern' : (getAssignedStudent(row, col)?.is_guest ? 'Gast' : getAssignedStudent(row, col)?.course_code)"></p>
                                                            <button @click="removeAssignment(row, col)"
                                                                    class="absolute top-1 right-1 w-4 h-4 bg-red-500 hover:bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-xs">
                                                                ×
                                                            </button>
                                                        </div>
                                                    </template>
                                                    
                                                    <!-- Icon (wenn leer) -->
                                                    <template x-if="!getAssignedStudent(row, col)">
                                                        <span class="text-xs mt-1" 
                                                              :class="getSeatTextClass(row, col)"
                                                              x-text="getSeatIcon(row, col)"></span>
                                                    </template>
                                                </div>
                                            </template>
                                            <!-- Türe RECHTS -->
                                            <div class="w-10 h-24 flex flex-col items-center justify-center text-sm font-bold rounded"
                                                 :class="hasDoor('right', row) ? 'text-orange-500 bg-orange-50 dark:bg-orange-900/20 border border-orange-300' : 'text-transparent'">
                                                <span x-show="hasDoor('right', row)" class="text-xs font-bold leading-none">Türe</span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Türen UNTEN -->
                                    <div class="flex items-center gap-2 mt-1">
                                        <div class="w-8"></div>
                                        <div class="w-10"></div><!-- links -->
                                        <template x-for="col in columns" :key="'door-bottom-' + col">
                                            <div class="w-24 h-10 flex flex-col items-center justify-center text-sm font-bold rounded"
                                                 :class="hasDoor('bottom', col) ? 'text-orange-500 bg-orange-50 dark:bg-orange-900/20 border border-orange-300' : 'text-transparent'">
                                                <span x-show="hasDoor('bottom', col)" class="text-xs font-bold leading-none">Türe</span>
                                            </div>
                                        </template>
                                        <div class="w-10"></div><!-- rechts -->
                                    </div>

                                    <!-- Pult-Indikator UNTEN -->
                                    <div x-show="pultSide === 'bottom'" style="display:none;" class="flex items-center gap-2 mt-2">
                                        <div class="w-8"></div>
                                        <div class="w-10"></div>
                                        <div class="flex-1 flex items-center justify-center gap-2 py-1 px-3 rounded bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold">
                                            <span>🎓</span><span>{{ __('Pult / Tafel') }}</span>
                                        </div>
                                        <div class="w-10"></div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        {{-- HINWEIS: Dieses div (x-data-Container) bleibt bis nach dem Modal offen --}}

        {{-- Strategie-Modal für Auto-Zuweisung --}}
        <div x-show="showAutoAssignModal"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
             style="display:none;">
            <div @click.away="showAutoAssignModal = false"
                 class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ __('Auto-Zuweisung') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                    {{ __('Wähle eine Strategie für die automatische Platzzuweisung:') }}
                </p>

                <div class="space-y-3 mb-6">
                    <!-- Strategie: Standard -->
                    <label class="flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                           :class="selectedStrategy === 'standard'
                               ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                               : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'">
                        <input type="radio" name="strategy" value="standard"
                               x-model="selectedStrategy" class="mt-0.5 text-green-600">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">📋 {{ __('Standard') }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ __('Schüler nach Kurs sortiert, innerhalb des Kurses alphabetisch. Kurse sitzen jeweils zusammen.') }}
                            </div>
                        </div>
                    </label>

                    <!-- Strategie: Max-Abstand -->
                    <label class="flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                           :class="selectedStrategy === 'max_distance'
                               ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                               : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'">
                        <input type="radio" name="strategy" value="max_distance"
                               x-model="selectedStrategy" class="mt-0.5 text-green-600">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">↔ {{ __('Maximale Abstände') }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ __('Schüler werden gleichmäßig im Raum verteilt – bei 10 Schülern und 20 Plätzen bleibt zwischen je zwei Schülern ein Platz frei.') }}
                            </div>
                        </div>
                    </label>

                    <!-- Strategie: Nachteilsausgleich separat -->
                    <label class="flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                           :class="selectedStrategy === 'compensation_separate'
                               ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                               : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'">
                        <input type="radio" name="strategy" value="compensation_separate"
                               x-model="selectedStrategy" class="mt-0.5 text-green-600">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">⏱ {{ __('Nachteilsausgleich separat') }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ __('Schüler ohne Nachteilsausgleich zuerst, danach Schüler mit Zeitverlängerung in den hinteren Reihen.') }}
                            </div>
                        </div>
                    </label>
                </div>

                <div class="flex justify-end gap-3">
                    <button @click="showAutoAssignModal = false"
                            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        {{ __('Abbrechen') }}
                    </button>
                    <button @click="autoAssignSeats(); showAutoAssignModal = false"
                            class="px-4 py-2 text-sm text-white bg-green-600 hover:bg-green-700 font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        {{ __('Zuweisen') }}
                    </button>
                </div>
            </div>
        </div>
        {{-- Ende x-data-Container --}}
    </div>

    @push('scripts')
    <script @cspNonce>
        function roomSeatingPlanEditor(examDateId, roomId, assignmentIds, initialStudents, initialSeats, initialAssignments, rows, columns, courseColors, initialDoors, isHv, initialOrientation, initialExterne) {
            const baseUrl = '/written-exams/dates/' + examDateId;
            const hvParam = isHv ? '?hv=1' : '';
            return {
                examDateId: examDateId,
                roomId: roomId,
                assignmentIds: assignmentIds,
                students: initialStudents,
                seats: initialSeats,
                seatAssignments: initialAssignments,
                rows: rows,
                columns: columns,
                courseColors: courseColors,
                courseColors: courseColors,
                doors: Array.isArray(initialDoors) ? initialDoors : [],
                orientation: initialOrientation || 'top',
                draggedStudent: null,
                dragSourceSeat: null,   // { row, col } wenn Drag von Platz startet
                confirmingAutoAssign: false,
                confirmingClearAll: false,
                confirmingReset: false,
                showAutoAssignModal: false,
                selectedStrategy: '{{ $savedStrategy }}',
                guestName: '',
                guestAssignmentId: null,
                pendingGuests: [],
                // Externe Bewerber mit schriftlicher Prüfung an diesem Tag.
                // Sie stehen fest — anders als Gäste werden sie nicht getippt.
                externe: Array.isArray(initialExterne) ? initialExterne : [],

                get rowsReversed() {
                    const arr = Array.from({ length: this.rows }, (_, i) => i + 1);
                    return this.orientation === 'bottom' ? arr.reverse() : arr;
                },

                // Auf welcher Seite der Anzeige befindet sich das Pult tatsächlich?
                // Basiert auf der echten teacher_desk-Position, nicht nur auf orientation.
                get pultSide() {
                    const deskRows = this.seats
                        .filter(s => s.seat_type === 'teacher_desk')
                        .map(s => s.row_number);
                    if (deskRows.length === 0) {
                        return this.orientation === 'bottom' ? 'bottom' : 'top';
                    }
                    const displayed = this.rowsReversed;
                    const mid = displayed.length / 2;
                    const inBottom = deskRows.filter(r => displayed.indexOf(r) >= mid).length;
                    return inBottom >= deskRows.length / 2 ? 'bottom' : 'top';
                },
                
                init() {
                    console.log('Room Seating Plan Editor initialized');
                    console.log('Room ID:', this.roomId);
                    console.log('Assignments:', this.assignmentIds.length);
                    console.log('Students:', this.students.length);
                    console.log('Seats:', this.seats.length);
                    console.log('Initial Assignments:', this.seatAssignments.length);
                },
                
                getCourses() {
                    const courseMap = new Map();
                    this.students.forEach(student => {
                        if (!courseMap.has(student.course_id)) {
                            courseMap.set(student.course_id, {
                                id: student.course_id,
                                code: student.course_code,
                                total: this.students.filter(s => s.course_id === student.course_id).length
                            });
                        }
                    });
                    return Array.from(courseMap.values());
                },
                
                get unassignedStudents() {
                    const assignedStudentIds = this.seatAssignments.map(a => a.student_id);
                    return this.students.filter(s => !assignedStudentIds.includes(s.id));
                },

                get unassignedExterne() {
                    const gesetzt = this.seatAssignments
                        .map(a => a.external_applicant_id)
                        .filter(id => id !== null && id !== undefined);
                    return this.externe.filter(b => !gesetzt.includes(b.external_applicant_id));
                },
                
                getUnassignedStudentsForCourse(courseId) {
                    return this.unassignedStudents.filter(s => s.course_id === courseId);
                },
                
                getUnassignedCountForCourse(courseId) {
                    return this.getUnassignedStudentsForCourse(courseId).length;
                },
                
                getStudentCardClass(courseId) {
                    const color = this.courseColors[courseId];
                    return `bg-${color}-50 dark:bg-${color}-900/20 border-${color}-200 dark:border-${color}-800 hover:bg-${color}-100 dark:hover:bg-${color}-900/30`;
                },
                
                getSeat(row, col) {
                    return this.seats.find(s => s.row_number === row && s.column_number === col);
                },
                
                getSeatNumber(row, col) {
                    const seat = this.getSeat(row, col);
                    return seat?.seat_number || String(row).padStart(2, '0') + '-' + String(col).padStart(2, '0');
                },
                
                getAssignedStudent(row, col) {
                    const seat = this.getSeat(row, col);
                    if (!seat) return null;
                    
                    const assignment = this.seatAssignments.find(a => a.seat_id === seat.id);
                    if (!assignment) return null;
                    
                    // Externer Bewerber: hat einen Datensatz, aber keinen Kurs.
                    if (assignment.external_applicant_id) {
                        const bewerber = this.externe.find(b => b.external_applicant_id === assignment.external_applicant_id);
                        return {
                            id: null,
                            external_applicant_id: assignment.external_applicant_id,
                            name: bewerber?.name || assignment.extern_name || '?',
                            external_id: null,
                            course_id: assignment.course_id,
                            course_code: assignment.course_code || '',
                            assignment_id: assignment.assignment_id,
                            has_compensation: bewerber?.has_compensation || false,
                            nta_percent: bewerber?.nta_percent ?? null,
                            is_guest: true,   // fürs Aussehen: grau wie ein Gast
                            is_extern: true,
                        };
                    }

                    // Gastschüler (kein student_id, aber guest_name)
                    if (assignment.guest_name) {
                        return {
                            id: null,
                            name: assignment.guest_name,
                            external_id: null,
                            course_id: assignment.course_id,
                            course_code: assignment.course_code || '',
                            assignment_id: assignment.assignment_id,
                            has_compensation: false,
                            is_guest: true,
                        };
                    }
                    
                    return this.students.find(s => s.id === assignment.student_id);
                },
                
                getCourseColor(row, col) {
                    const seat = this.getSeat(row, col);
                    if (!seat) return 'gray';
                    
                    const assignment = this.seatAssignments.find(a => a.seat_id === seat.id);
                    if (!assignment) return 'gray';
                    
                    return this.courseColors[assignment.course_id] || 'gray';
                },
                
                hasDoor(side, index) {
                    return this.doors.some(d => d.side === side && d.index === index);
                },

                getSeatClass(row, col) {
                    const seat = this.getSeat(row, col);
                    if (!seat) return 'bg-white dark:bg-gray-800 border-gray-300';
                    
                    const student = this.getAssignedStudent(row, col);
                    const baseClasses = 'border-gray-300 dark:border-gray-600 ';
                    
                    if (student) {
                        if (student.is_guest) {
                            return baseClasses + 'bg-gray-200 dark:bg-gray-600 border-gray-400 dark:border-gray-500 cursor-grab';
                        }
                        const courseColor = this.getCourseColor(row, col);
                        return baseClasses + `bg-${courseColor}-100 dark:bg-${courseColor}-900/30 border-${courseColor}-400 dark:border-${courseColor}-600 cursor-grab`;
                    }
                    
                    switch(seat.seat_type) {
                        case 'available':
                            return baseClasses + 'bg-green-50 dark:bg-green-900/20 border-green-300 dark:border-green-700 cursor-pointer hover:bg-green-100 dark:hover:bg-green-900/30';
                        case 'teacher_desk':
                            return baseClasses + 'bg-blue-50 dark:bg-blue-900/20 border-blue-400 dark:border-blue-600 cursor-not-allowed';
                        case 'blocked':
                            return baseClasses + 'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700 cursor-not-allowed';
                        case 'aisle':
                            return baseClasses + 'bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 cursor-not-allowed';
                        case 'reserve':
                            return baseClasses + 'bg-amber-50 dark:bg-amber-900/20 border-amber-400 dark:border-amber-600 cursor-pointer hover:bg-amber-100 dark:hover:bg-amber-900/30';
                        default:
                            return baseClasses + 'bg-white dark:bg-gray-800';
                    }
                },
                
                getSeatTextClass(row, col) {
                    const seat = this.getSeat(row, col);
                    if (!seat) return 'text-gray-900 dark:text-gray-100';
                    
                    const student = this.getAssignedStudent(row, col);
                    if (student) {
                        const courseColor = this.getCourseColor(row, col);
                        return `text-${courseColor}-900 dark:text-${courseColor}-100`;
                    }
                    
                    switch(seat.seat_type) {
                        case 'available': return 'text-green-800 dark:text-green-300';
                        case 'teacher_desk': return 'text-blue-700 dark:text-blue-300';
                        case 'blocked': return 'text-red-800 dark:text-red-300';
                        case 'aisle': return 'text-gray-500 dark:text-gray-400';
                        case 'reserve': return 'text-amber-700 dark:text-amber-300';
                        default: return 'text-gray-900 dark:text-gray-100';
                    }
                },
                
                getSeatIcon(row, col) {
                    const seat = this.getSeat(row, col);
                    if (!seat) return '';
                    
                    switch(seat.seat_type) {
                        case 'available':     return '○';
                        case 'teacher_desk':  return 'Pult';
                        case 'blocked':       return '✕';
                        case 'aisle':         return '—';
                        case 'reserve':       return 'R';
                        default:              return '';
                    }
                },
                
                getAssignedCount() {
                    return this.seatAssignments.length;
                },
                
                getUnassignedCount() {
                    return this.unassignedStudents.length;
                },

                addGuestStudent() {
                    const name = this.guestName.trim();
                    if (!name) return;
                    // Gastschüler-Objekt: is_guest=true, assignment_id = erstes Assignment des Raums
                    this.pendingGuests.push({
                        id: null,
                        name: name,
                        external_id: null,
                        course_id: null,
                        course_code: '',
                        assignment_id: this.assignmentIds[0],
                        has_compensation: false,
                        is_guest: true,
                    });
                    this.guestName = '';
                },
                
                // Drag & Drop Handlers
                // Drag start von Sidebar-Karte
                handleDragStart(event, student) {
                    this.draggedStudent = student;
                    this.dragSourceSeat = null;
                    event.dataTransfer.effectAllowed = 'move';
                    event.target.style.opacity = '0.5';
                },

                // Drag start von besetztem Platz
                handleSeatDragStart(event, row, col) {
                    const student = this.getAssignedStudent(row, col);
                    if (!student) return;
                    this.draggedStudent = student;
                    this.dragSourceSeat = { row, col };
                    event.dataTransfer.effectAllowed = 'move';
                    event.currentTarget.style.opacity = '0.5';
                },
                
                handleDragEnd(event) {
                    event.target.style.opacity = '1';
                    event.currentTarget && (event.currentTarget.style.opacity = '1');
                },
                
                handleDragOver(event, row, col) {
                    const seat = this.getSeat(row, col);
                    if (!seat || (seat.seat_type !== 'available' && seat.seat_type !== 'reserve')) return;
                    
                    // Ziel darf nicht der Quell-Platz sein
                    if (this.dragSourceSeat && this.dragSourceSeat.row === row && this.dragSourceSeat.col === col) return;
                    
                    // Ziel muss entweder leer oder (beim Tausch, falls gewünscht) belegt sein
                    // Hier: nur auf leere Plätze droppen (kein Tausch)
                    if (this.getAssignedStudent(row, col)) return;
                    
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                    event.currentTarget.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
                },
                
                handleDragLeave(event) {
                    event.currentTarget.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
                },
                
                handleDrop(event, row, col) {
                    event.preventDefault();
                    event.currentTarget.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
                    
                    if (!this.draggedStudent) return;
                    
                    const seat = this.getSeat(row, col);
                    if (!seat || (seat.seat_type !== 'available' && seat.seat_type !== 'reserve')) {
                        alert('Dieser Platz ist nicht verfügbar!');
                        return;
                    }
                    
                    if (this.getAssignedStudent(row, col)) {
                        alert('Dieser Platz ist bereits belegt!');
                        return;
                    }

                    if (this.dragSourceSeat) {
                        // Verschiebung von Platz zu Platz: erst Quelle entfernen, dann neu zuweisen
                        this.moveSeatAssignment(this.draggedStudent, this.dragSourceSeat, seat.id);
                    } else {
                        // Zuweisung von Sidebar
                        this.assignStudentToSeat(this.draggedStudent, seat.id);
                    }
                    this.draggedStudent = null;
                    this.dragSourceSeat = null;
                },
                
                // AJAX Funktionen
                assignStudentToSeat(student, seatId) {
                    fetch(`${baseUrl}/assignments/${student.assignment_id}/assign-seat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            student_id: student.id || null,
                            external_applicant_id: student.external_applicant_id || null,
                            guest_name: (student.is_guest && !student.is_extern) ? student.name : null,
                            seat_id: seatId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Lokale Aktualisierung
                            this.seatAssignments.push({
                                student_id:    student.id || null,
                                external_applicant_id: student.external_applicant_id || null,
                                extern_name:   student.is_extern ? student.name : null,
                                guest_name:    (student.is_guest && !student.is_extern) ? student.name : null,
                                seat_id:       seatId,
                                assignment_id: student.assignment_id,
                                course_id:     student.course_id,
                                course_code:   student.course_code || '',
                            });
                            // Gastschüler aus Warteliste entfernen
                            if (student.is_guest && !student.is_extern) {
                                const idx = this.pendingGuests.findIndex(g => g.name === student.name);
                                if (idx > -1) this.pendingGuests.splice(idx, 1);
                            }
                        } else {
                            alert('Fehler beim Zuweisen: ' + (data.message || 'Unbekannter Fehler'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Zuweisen des Platzes');
                    });
                },
                
                removeAssignment(row, col) {
                    const seat = this.getSeat(row, col);
                    if (!seat) return;
                    
                    const assignment = this.seatAssignments.find(a => a.seat_id === seat.id);
                    if (!assignment) return;
                    
                    if (!confirm('Zuweisung wirklich entfernen?')) return;
                    
                    fetch(`${baseUrl}/assignments/${assignment.assignment_id}/remove-assignment`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            seat_id: seat.id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Lokale Aktualisierung
                            const index = this.seatAssignments.findIndex(a => a.seat_id === seat.id);
                            if (index > -1) {
                                this.seatAssignments.splice(index, 1);
                            }
                        } else {
                            alert('Fehler beim Entfernen');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Entfernen der Zuweisung');
                    });
                },
                
                // Verschiebung von Platz zu Platz
                async moveSeatAssignment(student, sourceSeat, targetSeatId) {
                    const sourceSeatObj = this.getSeat(sourceSeat.row, sourceSeat.col);
                    if (!sourceSeatObj) return;

                    const assignment = this.seatAssignments.find(a => a.seat_id === sourceSeatObj.id);
                    if (!assignment) return;

                    try {
                        // Quelle entfernen
                        const removeResp = await fetch(`${baseUrl}/assignments/${assignment.assignment_id}/remove-assignment`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ seat_id: sourceSeatObj.id })
                        });
                        const removeData = await removeResp.json();
                        if (!removeData.success) { alert('Fehler beim Entfernen'); return; }

                        // Lokal entfernen
                        const idx = this.seatAssignments.findIndex(a => a.seat_id === sourceSeatObj.id);
                        if (idx > -1) this.seatAssignments.splice(idx, 1);

                        // Neu zuweisen
                        await this.assignStudentToSeat(student, targetSeatId);
                    } catch (e) {
                        console.error('Fehler beim Verschieben:', e);
                        alert('Fehler beim Verschieben des Schülers');
                    }
                },

                autoAssignSeats() {
                    if (this.getUnassignedCount() === 0) {
                        alert('Alle Schüler sind bereits zugewiesen!');
                        return;
                    }
                    
                    
                    // if (!confirm(`${this.getUnassignedCount()} Schüler aus allen Kursen automatisch zuweisen?`)) {
                    //     return;
                    // }
                    
                    fetch(`${baseUrl}/rooms/${this.roomId}/auto-assign${hvParam}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ strategy: this.selectedStrategy })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Seite neu laden für vollständige Aktualisierung
                            window.location.reload();
                        } else {
                            alert('Fehler bei der Auto-Zuweisung: ' + (data.message || 'Unbekannter Fehler'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler bei der Auto-Zuweisung');
                    });
                },
                
                clearAllAssignments() {
                    if (this.seatAssignments.length === 0) {
                        alert('Es gibt keine Zuweisungen zum Entfernen!');
                        return;
                    }
                    
                    
                    // if (!confirm('Wirklich ALLE Zuweisungen aller Kurse entfernen?')) {
                    //     return;
                    // }
                    
                    fetch(`${baseUrl}/rooms/${this.roomId}/clear-assignments${hvParam}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Lokale Aktualisierung
                            this.seatAssignments = [];
                        } else {
                            alert('Fehler beim Entfernen aller Zuweisungen');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Entfernen aller Zuweisungen');
                    });
                },

                // Alle Zuweisungen löschen und direkt neu (Standard) zuweisen
                async resetAndAutoAssign() {
                    try {
                        // 1. Alle Zuweisungen löschen
                        const clearResp = await fetch(`${baseUrl}/rooms/${this.roomId}/clear-assignments${hvParam}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const clearData = await clearResp.json();
                        if (!clearData.success) {
                            alert('Fehler beim Zurücksetzen der Zuweisungen');
                            return;
                        }

                        // 2. Auto-Assign mit Standard-Strategie
                        const assignResp = await fetch(`${baseUrl}/rooms/${this.roomId}/auto-assign${hvParam}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ strategy: this.selectedStrategy })
                        });
                        const assignData = await assignResp.json();
                        if (assignData.success) {
                            window.location.reload();
                        } else {
                            alert('Fehler bei der Auto-Zuweisung: ' + (assignData.message || 'Unbekannter Fehler'));
                        }
                    } catch (e) {
                        console.error('Fehler beim Zurücksetzen:', e);
                        alert('Fehler beim Zurücksetzen und Neu-Zuweisen');
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
