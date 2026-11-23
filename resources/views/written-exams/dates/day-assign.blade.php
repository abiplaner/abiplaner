<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Kurs-Raum-Zuordnung
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $dayCarbon->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}
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

    <div class="py-8" x-data="dayAssign()">
        @php
            // Schülerdaten für das Split-Modal (inkl. NTA-Schüler, alphabetisch sortiert)
            $courseStudentsData = [];
            $courseCodes = [];
            foreach ($eligibleByDate as $_dateId => $_courses) {
                foreach ($_courses as $_course) {
                    $courseCodes[$_course->id] = $_course->course_code;
                    if (!isset($courseStudentsData[$_course->id])) {
                        $courseStudentsData[$_course->id] = $_course->writtenStudents
                            ->sortBy('lastname')
                            ->map(fn($s) => [
                                'id'          => $s->id,
                                'name'        => $s->lastname . ', ' . $s->firstname,
                                'is_nta'      => $s->hasDisabilityCompensation(),
                                'nta_percent' => $s->disability_compensation_percent,
                            ])
                            ->values()
                            ->all();
                    }
                }
            }
        @endphp
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">


            {{-- Hinweis bestehende Zuordnungen --}}
            @php $totalExisting = collect($assignmentsByDate)->flatten()->count(); @endphp
            @if($totalExisting > 0)
                <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-400 px-4 py-3 rounded-lg text-sm text-amber-800 dark:text-amber-200 flex items-start gap-2">
                    <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span><strong>Hinweis:</strong> Für diesen Tag sind bereits {{ $totalExisting }} Zuordnung(en) vorhanden. Beim Speichern werden diese vollständig ersetzt. Vorhandene Sitzplatzzuweisungen gehen dabei verloren.</span>
                </div>
            @endif

            {{-- ================================================================
                 Pro Slot: Kursübersicht + Schüler
            ================================================================ --}}
            @foreach($yearDates as $date)
                @php
                    $slot        = $date->slot;
                    $eligible    = $eligibleByDate[$date->id] ?? collect();
                @endphp
                @if($eligible->isEmpty()) @continue @endif
                @php
                    $startTime   = \Carbon\Carbon::parse($slot->start_time)->format('H:i');
                    $endTime     = \Carbon\Carbon::parse($slot->end_time)->format('H:i');
                    $levelLabel  = $slot->requirement_level === 'grundlegend' ? 'gA' : 'eA';
                    $levelColor  = $slot->requirement_level === 'grundlegend'
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300';
                    $presel      = $preselectedByDate[$date->id] ?? ['rooms' => [], 'courses' => [], 'nta' => []];

                    // Fachweise Zusammenfassung
                    $bySubject = $eligible->groupBy(fn($c) => $c->subject?->name ?? 'Sonstiges')
                        ->map(fn($g) => [
                            'courses'  => $g->count(),
                            'students' => $g->sum(fn($c) => $c->writtenStudents->count()),
                            'nta'      => $g->sum(fn($c) => $c->writtenStudents->filter(fn($s) => $s->hasDisabilityCompensation())->count()),
                        ]);
                    $ntaStudents = $eligible->flatMap(fn($c) =>
                        $c->writtenStudents->filter(fn($s) => $s->hasDisabilityCompensation())
                            ->map(fn($s) => ['student' => $s, 'course_code' => $c->course_code, 'course_id' => $c->id])
                    )->sortBy('student.lastname');
                @endphp

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    {{-- Slot-Header --}}
                    <div class="px-5 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600 flex items-center gap-3">
                        <span class="text-xs font-mono font-bold text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-gray-600 px-2 py-0.5 rounded">{{ $startTime }}–{{ $endTime }}</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $slot->name }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded {{ $levelColor }}">{{ $levelLabel }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $slot->getDurationMinutes() }}&thinsp;Min.</span>
                    </div>

                    <div class="p-5">
                        @if($eligible->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Keine passenden Kurse für diesen Slot.</p>
                        @else
                            {{-- Kurs-Kacheln --}}
                            <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-9 xl:grid-cols-11 gap-2 mb-4">
                                @foreach($eligible as $course)
                                    @php
                                        $regCount = $course->writtenStudents->filter(fn($s) => !$s->hasDisabilityCompensation())->count();
                                        $ntaCount = $course->writtenStudents->filter(fn($s) =>  $s->hasDisabilityCompensation())->count();
                                        $preselRoom = collect($presel['courses'])->flatten()->contains($course->id)
                                            ? collect($presel['courses'])->filter(fn($ids) => in_array($course->id, $ids))->keys()->first()
                                            : null;
                                    @endphp
                                    @if($regCount > 0)
                                        <div class="p-2 border rounded text-center text-xs"
                                             :class="isSplit({{ $date->id }}, {{ $course->id }})
                                                 ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-600'
                                                 : (getCourseRoom({{ $date->id }}, {{ $course->id }}) ? 'bg-gray-100 dark:bg-gray-900/50 border-gray-300 dark:border-gray-600' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800')">
                                            <div class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $course->course_code }}</div>
                                            <div class="text-gray-500 dark:text-gray-400">{{ $regCount }}&thinsp;Schül.</div>
                                            {{-- Raum-Label (normal) oder Split-Indikator --}}
                                            <div x-show="!isSplit({{ $date->id }}, {{ $course->id }})"
                                                 class="text-gray-400 dark:text-gray-500 truncate text-xs"
                                                 x-text="getRoomLabel(getCourseRoom({{ $date->id }}, {{ $course->id }}))"></div>
                                            <div x-show="isSplit({{ $date->id }}, {{ $course->id }})"
                                                 class="text-blue-500 dark:text-blue-400 text-xs font-semibold">
                                                ✂ <span x-text="getSplitGroups({{ $date->id }}, {{ $course->id }}).length"></span>&thinsp;Gr.
                                            </div>
                                            {{-- Split-Button --}}
                                            <button @click.prevent="openSplitModal({{ $date->id }}, {{ $course->id }}, '{{ $course->course_code }}')"
                                                    class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-xs rounded transition-colors"
                                                    :class="isSplit({{ $date->id }}, {{ $course->id }})
                                                        ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 hover:bg-blue-200'
                                                        : 'text-gray-400 hover:text-blue-600 dark:text-gray-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20'"
                                                    title="Kurs aufteilen">
                                                ✂
                                            </button>
                                            {{-- Aufteilung aufheben (direkt auf der Kachel) --}}
                                            <template x-if="isSplit({{ $date->id }}, {{ $course->id }})">
                                                <button @click.prevent="smRemoveSplit({{ $date->id }}, {{ $course->id }})"
                                                        class="mt-1 inline-flex items-center px-1.5 py-0.5 text-xs rounded text-red-500 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors"
                                                        title="Aufteilung aufheben">
                                                    ✕
                                                </button>
                                            </template>
                                        </div>
                                    @endif
                                    {{-- NTA-Schüler (ausblenden wenn bereits in Split-Gruppe) --}}
                                    @foreach($course->writtenStudents->filter(fn($s) => $s->hasDisabilityCompensation()) as $ntaStu)
                                        @php $ntaKey = 'nta_' . $course->id . '_' . $ntaStu->id; @endphp
                                        <div class="p-2 border-2 border-amber-300 dark:border-amber-700 rounded text-center text-xs bg-amber-50 dark:bg-amber-900/20"
                                             x-show="!isNtaInSplit({{ $date->id }}, {{ $course->id }}, {{ $ntaStu->id }})"
                                             title="+{{ $ntaStu->disability_compensation_percent }}% NTA">
                                            <div class="font-semibold text-amber-900 dark:text-amber-200 truncate">{{ $course->course_code }}</div>
                                            <div class="text-amber-700 dark:text-amber-400 truncate">{{ $ntaStu->lastname }}</div>
                                            <span class="inline-block mt-0.5 px-1 rounded bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 font-bold">+{{ $ntaStu->disability_compensation_percent }}%</span>
                                            {{-- Zugewiesener NTA-Raum --}}
                                            <div class="text-gray-400 dark:text-gray-500 truncate text-xs mt-0.5"
                                                 x-text="getRoomLabel(getNtaStudentRoom({{ $date->id }}, '{{ $ntaKey }}'))"></div>
                                            {{-- NTA-Zuweisung aufheben --}}
                                            <template x-if="getNtaStudentRoom({{ $date->id }}, '{{ $ntaKey }}') !== null">
                                                <button @click.prevent="removeNtaAssignment({{ $date->id }}, '{{ $ntaKey }}')"
                                                        class="mt-1 inline-flex items-center px-1.5 py-0.5 text-xs rounded text-red-500 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors"
                                                        title="NTA-Raumzuweisung aufheben">
                                                    ✕
                                                </button>
                                            </template>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>

                            {{-- Fach-Zusammenfassung --}}
                            <div class="overflow-x-auto mb-4">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-700 text-left">
                                            <th class="px-3 py-1.5 font-medium text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600">Fach</th>
                                            <th class="px-3 py-1.5 font-medium text-gray-600 dark:text-gray-300 border-b text-right">Kurse</th>
                                            <th class="px-3 py-1.5 font-medium text-gray-600 dark:text-gray-300 border-b text-right">Schüler</th>
                                            <th class="px-3 py-1.5 font-medium text-amber-600 dark:text-amber-400 border-b text-right" title="Nachteilsausgleich">NTA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bySubject as $subName => $data)
                                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                                <td class="px-3 py-1.5 text-gray-800 dark:text-gray-200 font-medium">{{ $subName }}</td>
                                                <td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">{{ $data['courses'] }}</td>
                                                <td class="px-3 py-1.5 text-right font-semibold text-gray-900 dark:text-gray-100">{{ $data['students'] }}</td>
                                                <td class="px-3 py-1.5 text-right">
                                                    @if($data['nta'] > 0)
                                                        <span class="px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">{{ $data['nta'] }}</span>
                                                    @else
                                                        <span class="text-gray-300 dark:text-gray-600">–</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-gray-50 dark:bg-gray-700 font-semibold text-xs">
                                            <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">Gesamt</td>
                                            <td class="px-3 py-1.5 text-right text-gray-700 dark:text-gray-300">{{ $bySubject->sum('courses') }}</td>
                                            <td class="px-3 py-1.5 text-right text-gray-900 dark:text-gray-100">{{ $bySubject->sum('students') }}</td>
                                            <td class="px-3 py-1.5 text-right text-amber-700 dark:text-amber-400">{{ $bySubject->sum('nta') ?: '–' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- NTA-Detailtabelle --}}
                            @if($ntaStudents->isNotEmpty())
                                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg mb-4">
                                    <h4 class="text-xs font-semibold text-amber-800 dark:text-amber-200 mb-2">Schüler mit Nachteilsausgleich – ggf. eigenen Raum einplanen</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                                        @foreach($ntaStudents as $entry)
                                            @php $s = $entry['student']; @endphp
                                            <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded px-2 py-1 text-xs">
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $s->lastname }}, {{ $s->firstname }}</span>
                                                <span class="text-gray-500 dark:text-gray-400 mx-1">{{ $entry['course_code'] }}</span>
                                                <span class="px-1.5 py-0.5 rounded bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 font-bold">+{{ $s->disability_compensation_percent }}%</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                        @endif
                    </div>
                </div>
            @endforeach

            {{-- ================================================================
                 Räume mit Kurszuordnung
            ================================================================ --}}
            <form @submit.prevent="submitForm" id="day-assign-form"
                  action="{{ route('written-exams.dates.day-store', $dayCarbon->format('Y-m-d')) }}"
                  method="POST">
                @csrf

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Räume und Kurszuordnung</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="totalAssignments()"></span> Zuordnung(en) konfiguriert
                        </span>
                    </div>

                    @if($rooms->isEmpty())
                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                            <p>Keine Prüfungsräume (room_type = exam) angelegt.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($rooms as $room)
                                @php
                                    $occ      = $roomOccupancy[$room->id] ?? ['occupied' => 0, 'total' => 0, 'free' => 0, 'assignments' => collect()];
                                    $capacity = $occ['total'];
                                @endphp

                                <div class="p-4 transition-colors"
                                     :class="selectedRooms.has({{ $room->id }}) ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''">

                                    {{-- Raum-Zeile --}}
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox"
                                               :checked="selectedRooms.has({{ $room->id }})"
                                               @change="toggleRoom({{ $room->id }})"
                                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <div class="flex items-center gap-3 flex-1">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $room->room_number }}</span>
                                            @if($capacity > 0)
                                                <span class="text-xs px-2 py-0.5 rounded-full"
                                                      :class="{{ $room->id }} in [
                                                          ...Object.entries(state).flatMap(([did, rooms]) =>
                                                              Object.entries(rooms).flatMap(([rid, c]) => c.courses.size > 0 ? [parseInt(rid)] : [])
                                                          )
                                                      ] ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'">
                                                    {{ $capacity }} Plätze
                                                </span>
                                            @else
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                    Kein Sitzplan
                                                </span>
                                            @endif
                                        </div>
                                    </label>

                                    {{-- Kurs-Auswahl pro Slot (nur wenn Raum ausgewählt) --}}
                                    <div x-show="selectedRooms.has({{ $room->id }})" x-transition class="ml-7 mt-3 space-y-4">

                                        @foreach($yearDates as $date)
                                            @php
                                                $slot     = $date->slot;
                                                $eligible = $eligibleByDate[$date->id] ?? collect();
                                                $regCourses = $eligible->filter(fn($c) =>
                                                    $c->writtenStudents->filter(fn($s) => !$s->hasDisabilityCompensation())->count() > 0
                                                );
                                                $levelLabel = $slot->requirement_level === 'grundlegend' ? 'gA' : 'eA';
                                            @endphp

                                            @if($regCourses->isEmpty()) @continue @endif

                                            <div>
                                                {{-- Slot-Label --}}
                                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">
                                                    {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                                                    · {{ $slot->name }}
                                                    <span class="ml-1 font-normal">({{ $levelLabel }})</span>
                                                </p>

                                                {{-- Kurs-Checkboxen --}}
                                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-1.5">
                                                    @foreach($regCourses as $course)
                                                        @php
                                                            $regCount = $course->writtenStudents->filter(fn($s) => !$s->hasDisabilityCompensation())->count();
                                                        @endphp
                                                        <label class="flex items-center gap-1.5 px-2 py-1.5 border rounded text-xs cursor-pointer transition-colors"
                                                               :class="isSplit({{ $date->id }}, {{ $course->id }})
                                                                   ? 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 opacity-60 cursor-default'
                                                                   : (getRoomDateCourses({{ $room->id }}, {{ $date->id }}).has({{ $course->id }})
                                                                       ? 'border-green-500 bg-green-50 dark:bg-green-900/20 dark:border-green-600'
                                                                       : 'border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-700')">
                                                            <input type="checkbox"
                                                                   :checked="getRoomDateCourses({{ $room->id }}, {{ $date->id }}).has({{ $course->id }})"
                                                                   :disabled="isSplit({{ $date->id }}, {{ $course->id }})"
                                                                   @change="toggleCourse({{ $room->id }}, {{ $date->id }}, {{ $course->id }})"
                                                                   class="w-3.5 h-3.5 text-green-600 border-gray-300 rounded focus:ring-green-500 shrink-0">
                                                            <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $course->course_code }}</span>
                                                            <span x-show="!isSplit({{ $date->id }}, {{ $course->id }})" class="text-gray-400 dark:text-gray-500">({{ $regCount }})</span>
                                                            <span x-show="isSplit({{ $date->id }}, {{ $course->id }})" class="text-blue-500 dark:text-blue-400 text-xs">✂</span>
                                                        </label>
                                                    @endforeach
                                                </div>

                                                {{-- Split-Gruppen-Indikator für diesen Raum + Slot --}}
                                                <template x-if="getSplitGroupsForRoom({{ $date->id }}, {{ $room->id }}).length > 0">
                                                    <div class="mt-1.5 pt-1.5 border-t border-blue-200 dark:border-blue-800">
                                                        <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">Aufgeteilte Gruppen in diesem Raum:</p>
                                                        <template x-for="sg in getSplitGroupsForRoom({{ $date->id }}, {{ $room->id }})" :key="sg.label">
                                                            <div class="flex items-center gap-2 px-2 py-1 mb-0.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded text-xs">
                                                                <span class="text-blue-600 dark:text-blue-400">✂</span>
                                                                <span class="font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="sg.label"></span>
                                                                <span class="text-gray-500 dark:text-gray-400" x-text="'(' + sg.studentCount + ' Schüler)'"></span>
                                                                <button @click.prevent="openSplitModal({{ $date->id }}, sg.courseId, sg.courseCode)"
                                                                        class="ml-auto text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-xs"
                                                                        title="Aufteilung bearbeiten">✏</button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>

                                                {{-- NTA --}}
                                                @php
                                                    $ntaCourses = $eligible->filter(fn($c) =>
                                                        $c->writtenStudents->some(fn($s) => $s->hasDisabilityCompensation())
                                                    );
                                                @endphp
                                                @if($ntaCourses->isNotEmpty())
                                                    <div class="mt-1.5 pt-1.5 border-t border-amber-200 dark:border-amber-800">
                                                        <p class="text-xs text-amber-600 dark:text-amber-400 mb-1">NTA-Schüler separat:</p>
                                                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-1.5">
                                                            @foreach($ntaCourses as $course)
                                                                @foreach($course->writtenStudents->filter(fn($s) => $s->hasDisabilityCompensation()) as $ntaStu)
                                                                    @php $ntaKey = 'nta_' . $course->id . '_' . $ntaStu->id; @endphp
                                                                    <label class="flex items-center gap-1.5 px-2 py-1.5 border-2 rounded text-xs cursor-pointer transition-colors"
                                                                           x-show="!isNtaInSplit({{ $date->id }}, {{ $course->id }}, {{ $ntaStu->id }})"
                                                                           :class="getRoomDateNta({{ $room->id }}, {{ $date->id }}).has('{{ $ntaKey }}')
                                                                               ? 'border-amber-500 bg-amber-100 dark:bg-amber-900/40'
                                                                               : 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10 hover:border-amber-400'">
                                                                        <input type="checkbox"
                                                                               :checked="getRoomDateNta({{ $room->id }}, {{ $date->id }}).has('{{ $ntaKey }}')"
                                                                               @change="toggleNta({{ $room->id }}, {{ $date->id }}, '{{ $ntaKey }}', {{ $course->id }}, {{ $ntaStu->id }})"
                                                                               class="w-3.5 h-3.5 text-amber-600 border-amber-300 rounded focus:ring-amber-500 shrink-0">
                                                                        <span class="font-mono font-semibold text-amber-900 dark:text-amber-200">{{ $course->course_code }}</span>
                                                                        <span class="text-amber-700 dark:text-amber-400 truncate">{{ $ntaStu->lastname }}</span>
                                                                        <span class="ml-auto font-bold text-amber-700 dark:text-amber-400">+{{ $ntaStu->disability_compensation_percent }}%</span>
                                                                    </label>
                                                                @endforeach
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ================================================================
                     Hörverstehen-Räume (Fremdsprachen)
                ================================================================ --}}
                @php
                    $hvYearDates = $yearDates->filter(function($d) use ($eligibleByDate, $foreignLanguageSubjectIds) {
                        return ($eligibleByDate[$d->id] ?? collect())->some(
                            fn($c) => $foreignLanguageSubjectIds->contains($c->subject_id)
                        );
                    });
                @endphp

                @if($hvYearDates->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-teal-600 dark:text-teal-400 text-lg">🎧</span>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Hörverstehen-Räume (Fremdsprachen)</h3>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Separater Raum für den Hörverstehen-Teil (ca. 30 Min.)</span>
                    </div>

                    @if($rooms->isEmpty())
                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                            <p>Keine Prüfungsräume angelegt.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($rooms as $room)
                                <div class="p-4 transition-colors"
                                     :class="selectedHvRooms.has({{ $room->id }}) ? 'bg-teal-50/40 dark:bg-teal-900/10' : ''">

                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox"
                                               :checked="selectedHvRooms.has({{ $room->id }})"
                                               @change="toggleHvRoom({{ $room->id }})"
                                               class="w-4 h-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                                        <div class="flex items-center gap-3 flex-1">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $room->room_number }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">HV-Raum</span>
                                        </div>
                                    </label>

                                    <div x-show="selectedHvRooms.has({{ $room->id }})" x-transition class="ml-7 mt-3 space-y-4">
                                        @foreach($hvYearDates as $date)
                                            @php
                                                $slot      = $date->slot;
                                                $flCourses = ($eligibleByDate[$date->id] ?? collect())
                                                    ->filter(fn($c) => $foreignLanguageSubjectIds->contains($c->subject_id)
                                                        && $c->writtenStudents->filter(fn($s) => !$s->hasDisabilityCompensation())->count() > 0);
                                            @endphp
                                            @if($flCourses->isEmpty()) @continue @endif

                                            <div>
                                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">
                                                    {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                                                    · {{ $slot->name }}
                                                    <span class="ml-1.5 px-1.5 py-0.5 rounded bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 font-medium">🎧 HV</span>
                                                </p>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-1.5">
                                                    @foreach($flCourses as $course)
                                                        @php $regCount = $course->writtenStudents->filter(fn($s) => !$s->hasDisabilityCompensation())->count(); @endphp
                                                        <label class="flex items-center gap-1.5 px-2 py-1.5 border rounded text-xs cursor-pointer transition-colors"
                                                               :class="getHvRoomCourses({{ $room->id }}, {{ $date->id }}).has({{ $course->id }})
                                                                   ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20 dark:border-teal-600'
                                                                   : 'border-gray-200 dark:border-gray-700 hover:border-teal-300 dark:hover:border-teal-700'">
                                                            <input type="checkbox"
                                                                   :checked="getHvRoomCourses({{ $room->id }}, {{ $date->id }}).has({{ $course->id }})"
                                                                   @change="toggleHvCourse({{ $room->id }}, {{ $date->id }}, {{ $course->id }})"
                                                                   class="w-3.5 h-3.5 text-teal-600 border-gray-300 rounded focus:ring-teal-500 shrink-0">
                                                            <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $course->course_code }}</span>
                                                            <span class="text-gray-400 dark:text-gray-500">({{ $regCount }})</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif

                {{-- Submit --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg px-5 py-4 flex items-center justify-between">                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <span x-text="totalAssignments()"></span> Zuordnung(en) werden gespeichert
                        @if($totalExisting > 0)
                            <span class="ml-1 text-amber-600 dark:text-amber-400">(ersetzt {{ $totalExisting }} bestehende)</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('written-exams.dates.index') }}"
                           class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition-colors">
                            Abbrechen
                        </a>
                        <button type="submit"
                                :disabled="totalAssignments() === 0"
                                :class="totalAssignments() === 0 ? 'opacity-50 cursor-not-allowed bg-blue-600' : 'bg-blue-600 hover:bg-blue-700'"
                                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold rounded-lg text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Zuordnungen speichern
                        </button>
                    </div>
                </div>

                <div id="hidden-inputs"></div>
            </form>

        {{-- ================================================================
             Split-Modal: Kurs auf mehrere Räume aufteilen
        ================================================================ --}}
    <div x-show="smOpen"
         style="display:none"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="smOpen = false">
        <div class="absolute inset-0 bg-black/50" @click="smOpen = false"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">

            {{-- Modal-Header --}}
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between shrink-0">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="text-blue-600">✂</span>
                        Kurs aufteilen:&nbsp;<span class="font-mono" x-text="smCourseCode"></span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        <span x-text="smStudents().length"></span> Schüler gesamt –
                        <span :class="smUnassignedCount() > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-green-600 dark:text-green-400 font-semibold'">
                            <span x-text="smUnassignedCount() > 0 ? smUnassignedCount() + ' noch nicht zugeordnet' : 'alle zugeordnet ✓'"></span>
                        </span>
                    </p>
                </div>
                <button @click="smOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Anzahl Gruppen + Auto-Aufteilen --}}
            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-600 flex flex-wrap items-center gap-4 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Gruppen:</span>
                    <template x-for="n in [2,3,4,5,6]">
                        <button @click.prevent="smChangeNumGroups(n)"
                                :class="smNumGroups === n ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                class="w-7 h-7 text-xs font-bold rounded transition-colors"
                                x-text="n"></button>
                    </template>
                </div>
                <button @click.prevent="smAutoSplit()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 transition-colors">
                    ⚡ Auto-Aufteilen (alphabetisch)
                </button>
            </div>

            {{-- Gruppen-Konfiguration: Label + Raum --}}
            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-600 shrink-0 overflow-x-auto">
                <div class="flex gap-3 min-w-max">
                    <template x-for="(group, idx) in smGroups" :key="idx">
                        <div class="flex flex-col gap-1.5 w-40">
                            <div class="text-xs font-bold text-center px-1 py-0.5 rounded"
                                 :class="['bg-blue-100 text-blue-800','bg-emerald-100 text-emerald-800','bg-purple-100 text-purple-800','bg-orange-100 text-orange-800','bg-pink-100 text-pink-800','bg-indigo-100 text-indigo-800'][idx % 6]">
                                Gruppe <span x-text="idx + 1"></span>
                                <span class="font-normal" x-text="'(' + smStudents().filter(s => smAssignments[s.id] === idx).length + ' Schüler)'"></span>
                            </div>
                            <input type="text" x-model="smGroups[idx].label"
                                   class="w-full px-2 py-1 text-xs font-mono border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                   placeholder="Bezeichnung">
                            <select x-model="smGroups[idx].roomId"
                                    class="w-full px-2 py-1 text-xs border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">Raum wählen…</option>
                                <template x-for="room in smRoomsList" :key="room.id">
                                    <option :value="room.id" x-text="room.label"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Schülerliste --}}
            <div class="flex-1 overflow-y-auto px-5 py-2">
                <template x-for="student in smStudents()" :key="student.id">
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700/40 text-sm">
                        <span class="flex-1 text-gray-900 dark:text-gray-100 truncate" x-text="student.name"></span>
                        <template x-if="student.is_nta">
                            <span class="px-1.5 py-0.5 text-xs rounded bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 font-bold shrink-0"
                                  x-text="'+' + student.nta_percent + '%'"
                                  title="Nachteilsausgleich"></span>
                        </template>
                        <div class="flex gap-1 shrink-0">
                            <template x-for="(group, idx) in smGroups" :key="idx">
                                <button @click.prevent="smAssignStudent(student.id, idx)"
                                        :class="smAssignments[student.id] === idx
                                            ? ['bg-blue-600 text-white border-blue-600','bg-emerald-600 text-white border-emerald-600','bg-purple-600 text-white border-purple-600','bg-orange-500 text-white border-orange-500','bg-pink-600 text-white border-pink-600','bg-indigo-600 text-white border-indigo-600'][idx % 6]
                                            : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-600 hover:border-blue-400'"
                                        class="w-7 h-6 text-xs font-bold rounded border transition-colors"
                                        :title="group.label"
                                        x-text="idx + 1"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Modal-Footer --}}
            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between shrink-0">
                <div>
                    <template x-if="isSplit(smDateId, smCourseId)">
                        <button @click.prevent="smRemoveSplit(smDateId, smCourseId); smOpen = false"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 transition-colors">
                            Aufteilung aufheben
                        </button>
                    </template>
                </div>
                <div class="flex items-center gap-3">
                    <button @click.prevent="smOpen = false"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition-colors">
                        Abbrechen
                    </button>
                    <button @click.prevent="smConfirm()"
                            :disabled="!smIsValid()"
                            :class="smIsValid() ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer' : 'bg-blue-400 cursor-not-allowed'"
                            class="px-4 py-1.5 text-xs font-semibold rounded-lg text-white transition-colors">
                        Übernehmen
                    </button>
                </div>
            </div>
        </div>
    </div>

    </div>
</div>

    @push('scripts')
    <script @cspNonce>
    document.addEventListener('alpine:init', () => {
        Alpine.data('dayAssign', () => {

            // Vorauswahl aus PHP
            const preselected      = @json($preselectedByDate);
            const roomLabels       = @json($rooms->pluck('room_number', 'id')->toArray());
            const courseStudents   = @json($courseStudentsData);   // courseId -> [{id, name}]
            const courseCodes      = @json($courseCodes);           // courseId -> code
            const roomsList        = @json($rooms->map(fn($r) => ['id' => $r->id, 'label' => $r->room_number . ($r->name ? ' – ' . $r->name : '')])->values());
            // state[dateId][roomId] = { courses: Set, nta: Map<key, {courseId,studentId}> }
            const initialState = {};
            const initialRooms = new Set();
            // splits[dateId][courseId] = [ {label, roomId, studentIds:[]}, ... ]
            const initialSplits = {};

            Object.entries(preselected).forEach(([dateId, data]) => {
                data.rooms.forEach(rid => initialRooms.add(rid));
                if (!initialState[dateId]) initialState[dateId] = {};
                Object.entries(data.courses || {}).forEach(([rid, courseIds]) => {
                    const ridInt = parseInt(rid);
                    if (!initialState[dateId][ridInt]) initialState[dateId][ridInt] = { courses: new Set(), nta: new Map() };
                    courseIds.forEach(cid => initialState[dateId][ridInt].courses.add(cid));
                });
                Object.entries(data.nta || {}).forEach(([rid, ntaList]) => {
                    const ridInt = parseInt(rid);
                    if (!initialState[dateId][ridInt]) initialState[dateId][ridInt] = { courses: new Set(), nta: new Map() };
                    ntaList.forEach(e => initialState[dateId][ridInt].nta.set(e.key, { courseId: e.courseId, studentId: e.studentId }));
                });
                // Split-Vorauswahl
                if (data.splits) {
                    if (!initialSplits[dateId]) initialSplits[dateId] = {};
                    Object.entries(data.splits).forEach(([courseId, groups]) => {
                        initialSplits[dateId][courseId] = groups.map(g => ({
                            label: g.label, roomId: String(g.roomId), studentIds: g.studentIds
                        }));
                        groups.forEach(g => initialRooms.add(g.roomId));
                    });
                }
            });

            // HV-State: hvState[dateId][roomId] = Set<courseId>
            const initialHvState = {};
            const initialHvRooms = new Set();
            Object.entries(preselected).forEach(([dateId, data]) => {
                (data.hvRooms || []).forEach(rid => initialHvRooms.add(rid));
                if (!initialHvState[dateId]) initialHvState[dateId] = {};
                Object.entries(data.hvRoomCourses || {}).forEach(([rid, courseIds]) => {
                    const ridInt = parseInt(rid);
                    if (!initialHvState[dateId][ridInt]) initialHvState[dateId][ridInt] = new Set();
                    courseIds.forEach(cid => initialHvState[dateId][ridInt].add(cid));
                });
            });

            return {
                selectedRooms: initialRooms,
                state: initialState,   // dateId -> roomId -> {courses, nta}
                splits: initialSplits, // dateId -> courseId -> [{label, roomId, studentIds}]

                // HV-State
                selectedHvRooms: initialHvRooms,
                hvState: initialHvState, // dateId -> roomId -> Set<courseId>

                // --- Split-Modal State ---
                smOpen: false,
                smDateId: null,
                smCourseId: null,
                smCourseCode: '',
                smNumGroups: 2,
                smGroups: [],        // [{label, roomId}]
                smAssignments: {},   // studentId -> groupIdx
                smRoomsList: roomsList,

                getRoomDateCourses(roomId, dateId) {
                    return this.state[dateId]?.[roomId]?.courses || new Set();
                },
                getRoomDateNta(roomId, dateId) {
                    return this.state[dateId]?.[roomId]?.nta || new Map();
                },
                getCourseRoom(dateId, courseId) {
                    if (this.isSplit(dateId, courseId)) return null;
                    for (const [rid, data] of Object.entries(this.state[dateId] || {})) {
                        if (data.courses.has(courseId)) return parseInt(rid);
                    }
                    return null;
                },
                getRoomLabel(roomId) {
                    if (!roomId) return '';
                    return roomLabels[roomId] || '';
                },

                toggleRoom(roomId) {
                    if (this.selectedRooms.has(roomId)) {
                        this.selectedRooms.delete(roomId);
                        Object.values(this.state).forEach(rooms => delete rooms[roomId]);
                        // Split-Gruppen die auf diesen Raum zeigen, nicht löschen —
                        // nur entfernen wenn Raum NICHT mehr in irgendeiner Gruppe vorkommt
                    } else {
                        this.selectedRooms.add(roomId);
                    }
                    this.selectedRooms = new Set(this.selectedRooms);
                },

                ensureState(roomId, dateId) {
                    if (!this.state[dateId]) this.state[dateId] = {};
                    if (!this.state[dateId][roomId]) this.state[dateId][roomId] = { courses: new Set(), nta: new Map() };
                },

                toggleCourse(roomId, dateId, courseId) {
                    this.ensureState(roomId, dateId);
                    const courses = this.state[dateId][roomId].courses;
                    if (courses.has(courseId)) {
                        courses.delete(courseId);
                    } else {
                        // Sicherstellen, dass Kurs nicht in anderem Raum (selber Slot) steckt
                        Object.entries(this.state[dateId] || {}).forEach(([rid, data]) => {
                            if (parseInt(rid) !== roomId) data.courses.delete(courseId);
                        });
                        courses.add(courseId);
                    }
                    this.state = { ...this.state }; // Reaktivität
                },

                toggleNta(roomId, dateId, ntaKey, courseId, studentId) {
                    this.ensureState(roomId, dateId);
                    const nta = this.state[dateId][roomId].nta;
                    if (nta.has(ntaKey)) {
                        nta.delete(ntaKey);
                    } else {
                        // NTA-Schüler ggf. aus anderem Raum entfernen
                        Object.entries(this.state[dateId] || {}).forEach(([rid, data]) => {
                            if (parseInt(rid) !== roomId) data.nta.delete(ntaKey);
                        });
                        nta.set(ntaKey, { courseId, studentId });
                    }
                    this.state = { ...this.state };
                },

                // ---- HV-Methoden ----
                toggleHvRoom(roomId) {
                    if (this.selectedHvRooms.has(roomId)) {
                        this.selectedHvRooms.delete(roomId);
                        Object.values(this.hvState).forEach(rooms => delete rooms[roomId]);
                    } else {
                        this.selectedHvRooms.add(roomId);
                    }
                    this.selectedHvRooms = new Set(this.selectedHvRooms);
                },
                ensureHvState(roomId, dateId) {
                    if (!this.hvState[dateId]) this.hvState[dateId] = {};
                    if (!this.hvState[dateId][roomId]) this.hvState[dateId][roomId] = new Set();
                },
                getHvRoomCourses(roomId, dateId) {
                    return this.hvState[dateId]?.[roomId] || new Set();
                },
                toggleHvCourse(roomId, dateId, courseId) {
                    this.ensureHvState(roomId, dateId);
                    const courses = this.hvState[dateId][roomId];
                    if (courses.has(courseId)) {
                        courses.delete(courseId);
                    } else {
                        // Kurs aus anderem HV-Raum (selber Slot) entfernen
                        Object.entries(this.hvState[dateId] || {}).forEach(([rid, c]) => {
                            if (parseInt(rid) !== roomId) c.delete(courseId);
                        });
                        courses.add(courseId);
                    }
                    this.hvState = { ...this.hvState };
                },

                totalAssignments() {
                    let n = 0;
                    Object.values(this.state).forEach(rooms => {
                        Object.values(rooms).forEach(({ courses, nta }) => {
                            n += courses.size;
                            const courseIds = new Set([...nta.values()].map(v => v.courseId));
                            n += courseIds.size;
                        });
                    });
                    // Splits zählen
                    Object.values(this.splits).forEach(courses => {
                        Object.values(courses).forEach(groups => { n += groups.length; });
                    });
                    // HV zählen
                    Object.values(this.hvState).forEach(rooms => {
                        Object.values(rooms).forEach(courses => { n += courses.size; });
                    });
                    return n;
                },

                // ---- Split-Hilfsmethoden ----
                isSplit(dateId, courseId) {
                    return !!(this.splits[dateId]?.[courseId]?.length);
                },
                isNtaInSplit(dateId, courseId, studentId) {
                    if (!this.isSplit(dateId, courseId)) return false;
                    const groups = this.splits[dateId]?.[courseId] || [];
                    return groups.some(g => (g.studentIds || []).map(Number).includes(Number(studentId)));
                },
                getNtaStudentRoom(dateId, ntaKey) {
                    for (const [rid, data] of Object.entries(this.state[dateId] || {})) {
                        if (data.nta && data.nta.has(ntaKey)) return parseInt(rid);
                    }
                    return null;
                },
                removeNtaAssignment(dateId, ntaKey) {
                    Object.values(this.state[dateId] || {}).forEach(data => {
                        if (data.nta) data.nta.delete(ntaKey);
                    });
                    this.state = { ...this.state };
                },
                getSplitGroups(dateId, courseId) {
                    return this.splits[dateId]?.[courseId] || [];
                },
                getSplitGroupsForRoom(dateId, roomId) {
                    const result = [];
                    const dateSplits = this.splits[dateId] || {};
                    Object.entries(dateSplits).forEach(([courseId, groups]) => {
                        groups.forEach(g => {
                            if (String(g.roomId) === String(roomId)) {
                                result.push({
                                    label: g.label,
                                    courseId: parseInt(courseId),
                                    courseCode: courseCodes[courseId] || courseId,
                                    studentCount: g.studentIds.length,
                                });
                            }
                        });
                    });
                    return result;
                },
                smRemoveSplit(dateId, courseId) {
                    if (this.splits[dateId]) {
                        delete this.splits[dateId][courseId];
                        this.splits = { ...this.splits };
                    }
                },

                // ---- Split-Modal Methoden ----
                openSplitModal(dateId, courseId, courseCode) {
                    this.smDateId   = dateId;
                    this.smCourseId = courseId;
                    this.smCourseCode = courseCode;
                    // Vorhandene Gruppen laden oder Defaults
                    const existing = this.splits[dateId]?.[courseId];
                    if (existing?.length) {
                        this.smNumGroups = existing.length;
                        this.smGroups    = existing.map(g => ({ label: g.label, roomId: String(g.roomId) }));
                        // Assignments aus studentIds rekonstruieren
                        this.smAssignments = {};
                        existing.forEach((g, idx) => {
                            g.studentIds.forEach(sid => { this.smAssignments[sid] = idx; });
                        });
                    } else {
                        this.smNumGroups = 2;
                        this.smInitGroups(courseCode, 2);
                        this.smAssignments = {};
                    }
                    this.smOpen = true;
                },
                smInitGroups(courseCode, n) {
                    this.smGroups = Array.from({ length: n }, (_, i) => ({
                        label: `${courseCode}-${i + 1}`,
                        roomId: '',
                    }));
                },
                smChangeNumGroups(n) {
                    this.smNumGroups = n;
                    const current = this.smGroups.slice(0, n);
                    while (current.length < n) {
                        current.push({ label: `${this.smCourseCode}-${current.length + 1}`, roomId: '' });
                    }
                    this.smGroups = current;
                    // Zuweisungen auf n-1 begrenzen
                    Object.keys(this.smAssignments).forEach(sid => {
                        if (this.smAssignments[sid] >= n) delete this.smAssignments[sid];
                    });
                    this.smAssignments = { ...this.smAssignments };
                },
                smStudents() {
                    return courseStudents[this.smCourseId] || [];
                },
                smAssignStudent(studentId, groupIdx) {
                    if (this.smAssignments[studentId] === groupIdx) {
                        delete this.smAssignments[studentId];
                    } else {
                        this.smAssignments[studentId] = groupIdx;
                    }
                    this.smAssignments = { ...this.smAssignments };
                },
                smUnassignedCount() {
                    return this.smStudents().filter(s => this.smAssignments[s.id] === undefined).length;
                },
                smAutoSplit() {
                    const students = this.smStudents();
                    const n = this.smNumGroups;
                    const newAssign = {};
                    students.forEach((s, i) => { newAssign[s.id] = i % n; });
                    this.smAssignments = newAssign;
                },
                smIsValid() {
                    if (this.smUnassignedCount() > 0) return false;
                    for (const g of this.smGroups) {
                        if (!g.label.trim() || !g.roomId) return false;
                    }
                    return true;
                },
                smConfirm() {
                    if (!this.smIsValid()) return;
                    const dateId   = this.smDateId;
                    const courseId = this.smCourseId;
                    // Split-Gruppen aus Assignments bauen
                    const groups = this.smGroups.map((g, idx) => ({
                        label: g.label.trim(),
                        roomId: g.roomId,
                        studentIds: this.smStudents()
                            .filter(s => this.smAssignments[s.id] === idx)
                            .map(s => s.id),
                    }));
                    if (!this.splits[dateId]) this.splits[dateId] = {};
                    this.splits[dateId][courseId] = groups;
                    this.splits = { ...this.splits };
                    // Kurs aus normalem state entfernen (falls vorher normal zugewiesen)
                    Object.values(this.state[dateId] || {}).forEach(d => d.courses.delete(courseId));
                    // Räume der Gruppen zu selectedRooms hinzufügen
                    groups.forEach(g => {
                        const rid = parseInt(g.roomId);
                        if (!this.selectedRooms.has(rid)) {
                            this.selectedRooms.add(rid);
                            this.selectedRooms = new Set(this.selectedRooms);
                        }
                    });
                    this.smOpen = false;
                },

                submitForm() {
                    if (this.totalAssignments() === 0) return;
                    const form      = document.getElementById('day-assign-form');
                    const container = document.getElementById('hidden-inputs');
                    container.innerHTML = '';

                    let slotIdx = 0;
                    Object.entries(this.state).forEach(([dateId, rooms]) => {
                        let hasData = false;
                        Object.entries(rooms).forEach(([roomId, { courses, nta }]) => {
                            if (courses.size === 0 && nta.size === 0) return;
                            hasData = true;
                        });
                        if (!hasData) return;

                        // date_id
                        const inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = `slots[${slotIdx}][date_id]`;
                        inp.value = dateId;
                        container.appendChild(inp);

                        let aIdx = 0;
                        Object.entries(rooms).forEach(([roomId, { courses, nta }]) => {
                            if (courses.size > 0) {
                                const ri = document.createElement('input');
                                ri.type = 'hidden';
                                ri.name = `slots[${slotIdx}][assignments][${aIdx}][room_id]`;
                                ri.value = roomId;
                                container.appendChild(ri);
                                courses.forEach(cid => {
                                    const ci = document.createElement('input');
                                    ci.type = 'hidden';
                                    ci.name = `slots[${slotIdx}][assignments][${aIdx}][course_ids][]`;
                                    ci.value = cid;
                                    container.appendChild(ci);
                                });
                                aIdx++;
                            }
                            // NTA: nach courseId gruppieren
                            if (nta.size > 0) {
                                const byCourse = new Map();
                                nta.forEach(({ courseId, studentId }) => {
                                    if (!byCourse.has(courseId)) byCourse.set(courseId, []);
                                    byCourse.get(courseId).push(studentId);
                                });
                                let ntaIdx = 0;
                                byCourse.forEach((sids, cid) => {
                                    const fields = [
                                        ['room_id', roomId],
                                        ['course_id', cid],
                                    ];
                                    fields.forEach(([k, v]) => {
                                        const fi = document.createElement('input');
                                        fi.type = 'hidden';
                                        fi.name = `slots[${slotIdx}][nta_assignments][${ntaIdx}][${k}]`;
                                        fi.value = v;
                                        container.appendChild(fi);
                                    });
                                    sids.forEach(sid => {
                                        const si = document.createElement('input');
                                        si.type = 'hidden';
                                        si.name = `slots[${slotIdx}][nta_assignments][${ntaIdx}][student_ids][]`;
                                        si.value = sid;
                                        container.appendChild(si);
                                    });
                                    ntaIdx++;
                                });
                            }
                        });
                        slotIdx++;
                    });

                    // Split-Assignments serialisieren
                    // Wir hängen sie an bestehende slotIdx-Einträge – oder öffnen neue
                    // Strategie: pro dateId ein Eintrag, splits als eigener Schlüssel
                    Object.entries(this.splits).forEach(([dateId, courses]) => {
                        if (!Object.keys(courses).length) return;
                        // slotIdx-Eintrag für diese dateId suchen oder neu erstellen
                        // Einfach: neuen slotIdx-Eintrag anlegen (Backend akzeptiert mehrere pro dateId)
                        const dateInput = document.createElement('input');
                        dateInput.type = 'hidden';
                        dateInput.name = `slots[${slotIdx}][date_id]`;
                        dateInput.value = dateId;
                        container.appendChild(dateInput);

                        let spIdx = 0;
                        Object.entries(courses).forEach(([courseId, groups]) => {
                            groups.forEach(g => {
                                const fields = [
                                    ['room_id',     g.roomId],
                                    ['course_id',   courseId],
                                    ['split_label', g.label],
                                ];
                                fields.forEach(([k, v]) => {
                                    const fi = document.createElement('input');
                                    fi.type  = 'hidden';
                                    fi.name  = `slots[${slotIdx}][split_assignments][${spIdx}][${k}]`;
                                    fi.value = v;
                                    container.appendChild(fi);
                                });
                                g.studentIds.forEach(sid => {
                                    const si = document.createElement('input');
                                    si.type  = 'hidden';
                                    si.name  = `slots[${slotIdx}][split_assignments][${spIdx}][student_ids][]`;
                                    si.value = sid;
                                    container.appendChild(si);
                                });
                                spIdx++;
                            });
                        });
                        slotIdx++;
                    });

                    // HV-Assignments serialisieren
                    Object.entries(this.hvState).forEach(([dateId, rooms]) => {
                        const hasHv = Object.values(rooms).some(c => c.size > 0);
                        if (!hasHv) return;

                        const dateInput = document.createElement('input');
                        dateInput.type  = 'hidden';
                        dateInput.name  = `slots[${slotIdx}][date_id]`;
                        dateInput.value = dateId;
                        container.appendChild(dateInput);

                        let hvIdx = 0;
                        Object.entries(rooms).forEach(([roomId, courseSet]) => {
                            if (courseSet.size === 0) return;
                            const ri  = document.createElement('input');
                            ri.type   = 'hidden';
                            ri.name   = `slots[${slotIdx}][hv_assignments][${hvIdx}][room_id]`;
                            ri.value  = roomId;
                            container.appendChild(ri);
                            courseSet.forEach(cid => {
                                const ci  = document.createElement('input');
                                ci.type   = 'hidden';
                                ci.name   = `slots[${slotIdx}][hv_assignments][${hvIdx}][course_ids][]`;
                                ci.value  = cid;
                                container.appendChild(ci);
                            });
                            hvIdx++;
                        });
                        slotIdx++;
                    });

                    form.submit();
                }
            };
        });
    });
    </script>
    @endpush
</x-app-layout>
