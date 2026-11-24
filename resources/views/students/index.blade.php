<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Schülerliste') }} Q{{ $stufe }} - {{ $examPeriod->name }}
            </h2>
            <div class="flex space-x-3">
                @unlessarchive
                <a href="{{ route('students.create') }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Neuer Schüler
                </a>
                <a href="{{ route('import.form') }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Importieren
                </a>
                @endarchive
                <a href="{{ route('students.export.pdf', ['stufe' => $stufe]) }}" 
                   class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    PDF
                </a>
                <a href="{{ route('students.export.excel', ['stufe' => $stufe]) }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Excel
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12"
         x-data="{
            search: '',
            subject: '',
            activeTab: 'all',
            visibleCount: {{ $students->count() }},
            matchesRow(el) {
                if (this.activeTab === 'disability' && el.dataset.disability !== '1') return false;
                const q    = this.search.toLowerCase().trim();
                const subj = this.subject.toLowerCase();
                const name      = (el.dataset.name || '').toLowerCase();
                const subjects  = (el.dataset.subjects || '').toLowerCase();
                const courses   = (el.dataset.courses || '').toLowerCase();
                const examiners = (el.dataset.examiners || '').toLowerCase();
                if (subj && !subjects.includes(subj)) return false;
                if (q && !name.includes(q) && !subjects.includes(q) && !courses.includes(q) && !examiners.includes(q)) return false;
                return true;
            },
            updateCount() {
                this.$nextTick(() => {
                    this.visibleCount = Array.from(
                        this.$el.querySelectorAll('tbody tr[data-name]')
                    ).filter(row => this.matchesRow(row)).length;
                });
            }
         }"
         x-init="$watch('search',    () => updateCount());
                  $watch('subject',   () => updateCount());
                  $watch('activeTab', () => updateCount());">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <x-stufen-umschalter route="students.index" :stufe="$stufe" :anzahl="$anzahlJeStufe" />

            {{-- Warnungen --}}
            @if(!empty($studentsWithProblems))
                <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 rounded-r-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                Prüfungszuordnungs-Probleme ({{ count($studentsWithProblems) }} Schüler)
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <p class="mb-2">Folgende Schüler haben nicht die korrekte Anzahl an Prüfungen zugeordnet:</p>
                                <div class="space-y-1 max-h-64 overflow-y-auto">
                                    @foreach($studentsWithProblems as $problem)
                                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-2 rounded border border-yellow-200 dark:border-yellow-800">
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $problem['external_id'] }} - {{ $problem['student_name'] }}
                                                </span>
                                                <span class="text-gray-600 dark:text-gray-400">→</span>
                                                <span class="text-yellow-800 dark:text-yellow-300">{{ $problem['message'] }}</span>
                                            </div>
                                            <a href="{{ route('students.edit', $problem['student_id']) }}" 
                                               class="ml-4 px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-md flex-shrink-0">
                                                Bearbeiten
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($students->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium">Keine Schüler gefunden</h3>
                        <p class="mt-2">Bitte importieren Sie zunächst Schülerdaten.</p>
                        <a href="{{ route('import.form') }}" 
                           class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Daten importieren
                        </a>
                    </div>
                </div>
            @else

                {{-- Tabs + Filter --}}
                <div class="mb-4 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4">
                    <div class="flex flex-wrap items-center gap-3">

                        {{-- Tab-Buttons --}}
                        <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 flex-shrink-0">
                            <button @click="activeTab = 'all'"
                                    :class="activeTab === 'all' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                    class="px-4 py-2 text-sm font-medium transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Alle Schüler
                                <span class="bg-white/20 dark:bg-black/20 px-1.5 py-0.5 rounded text-xs">{{ $students->count() }}</span>
                            </button>
                            <button @click="activeTab = 'disability'"
                                    :class="activeTab === 'disability' ? 'bg-orange-500 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                    class="px-4 py-2 text-sm font-medium transition-colors flex items-center gap-2 border-l border-gray-300 dark:border-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Nachteilsausgleich
                                @php $disabilityCount = $students->filter(fn($s) => $s['disability_compensation_percent'])->count(); @endphp
                                <span class="bg-white/20 dark:bg-black/20 px-1.5 py-0.5 rounded text-xs">{{ $disabilityCount }}</span>
                            </button>
                        </div>

                        {{-- Suchfeld --}}
                        <div class="relative flex-1 min-w-48">
                            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text"
                                   x-model.debounce.200ms="search"
                                   placeholder="Name, Kurs oder Fach suchen…"
                                   class="pl-9 pr-4 py-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        {{-- Fach-Filter --}}
                        <div class="flex-shrink-0">
                            <select x-model="subject"
                                    class="border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Alle Fächer</option>
                                @foreach($filterSubjects as $fach)
                                    <option value="{{ $fach }}">{{ $fach }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Reset --}}
                        <button x-show="search !== '' || subject !== ''"
                                @click="search = ''; subject = ''"
                                class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Filter zurücksetzen
                        </button>

                        {{-- Trefferzahl --}}
                        <div class="ml-auto flex-shrink-0 text-sm text-gray-500 dark:text-gray-400">
                            <span x-text="visibleCount" class="font-semibold text-gray-800 dark:text-gray-200"></span>
                            <span x-text="visibleCount === 1 ? ' Schüler' : ' Schüler'"></span>
                            <template x-if="visibleCount < {{ $students->count() }}">
                                <span class="text-gray-400 dark:text-gray-500"> von {{ $students->count() }}</span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Nachteilsausgleich-Hinweis (nur wenn Tab aktiv) --}}
                <div x-show="activeTab === 'disability'" x-cloak
                     class="mb-4 bg-orange-50 dark:bg-orange-900/20 border-l-4 border-orange-400 p-3 rounded-r-lg text-sm text-orange-800 dark:text-orange-200 flex items-start gap-2">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        Schüler mit Nachteilsausgleich erhalten verlängerte Prüfungszeiten. 
                        Die angezeigte Endzeit ist bereits die <strong>verlängerte</strong> Zeit.
                    </span>
                </div>

                {{-- Kompakte Statistiken --}}
                <div class="mb-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-3">
                            <div class="flex items-center justify-around divide-x divide-gray-200 dark:divide-gray-700">
                                <div class="flex-1 flex items-center justify-center space-x-2 px-3">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Schüler gesamt</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $students->count() }}</p>
                                    </div>
                                </div>
                                <div class="flex-1 flex items-center justify-center space-x-2 px-3">
                                    <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Nachteilsausgleich</p>
                                        <p class="text-lg font-bold text-orange-600">{{ $disabilityCount }}</p>
                                    </div>
                                </div>
                                <div class="flex-1 px-3">
                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-1.5 py-0.5 rounded text-xs">M</span> Mündlich
                                    </p>
                                    <div class="flex space-x-3 text-xs">
                                        <div><span class="text-gray-500">Ges.</span> <span class="font-bold text-gray-900 dark:text-gray-100 ml-1">{{ $students->sum(fn($s) => collect($s['exams'])->where('type', 'oral')->count()) }}</span></div>
                                        <div><span class="text-gray-500">Gepl.</span> <span class="font-bold text-green-600 ml-1">{{ $students->sum(fn($s) => collect($s['exams'])->where('type', 'oral')->where('is_assigned', true)->count()) }}</span></div>
                                        <div><span class="text-gray-500">Off.</span> <span class="font-bold text-orange-600 ml-1">{{ $students->sum(fn($s) => collect($s['exams'])->where('type', 'oral')->where('is_assigned', false)->count()) }}</span></div>
                                    </div>
                                </div>
                                @php $hasWrittenExams = $students->sum(fn($s) => collect($s['exams'])->where('type', 'written')->count()) > 0; @endphp
                                @if($hasWrittenExams)
                                <div class="flex-1 px-3">
                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        <span class="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 px-1.5 py-0.5 rounded text-xs">S</span> Schriftlich
                                    </p>
                                    <div class="flex space-x-3 text-xs">
                                        <div><span class="text-gray-500">Ges.</span> <span class="font-bold text-gray-900 dark:text-gray-100 ml-1">{{ $students->sum(fn($s) => collect($s['exams'])->where('type', 'written')->count()) }}</span></div>
                                        <div><span class="text-gray-500">Gepl.</span> <span class="font-bold text-green-600 ml-1">{{ $students->sum(fn($s) => collect($s['exams'])->where('type', 'written')->where('is_assigned', true)->count()) }}</span></div>
                                        <div><span class="text-gray-500">Off.</span> <span class="font-bold text-orange-600 ml-1">{{ $students->sum(fn($s) => collect($s['exams'])->where('type', 'written')->where('is_assigned', false)->count()) }}</span></div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tabelle --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        @php
                            $maxWritten = $students->max(fn($s) => collect($s['exams'])->where('type', 'written')->count());
                            $maxOral    = $students->max(fn($s) => collect($s['exams'])->where('type', 'oral')->count());
                        @endphp

                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    @for($i = 0; $i < $maxWritten; $i++)
                                        <th class="px-2 py-2 text-left text-xs font-medium text-purple-600 dark:text-purple-400 uppercase tracking-wider whitespace-nowrap">
                                            <span class="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 px-1.5 py-0.5 rounded text-xs mr-1">S</span>S{{ $i + 1 }}
                                        </th>
                                    @endfor
                                    @for($i = 0; $i < $maxOral; $i++)
                                        <th class="px-2 py-2 text-left text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wider whitespace-nowrap">
                                            <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-1.5 py-0.5 rounded text-xs mr-1">M</span>K{{ $i + 1 }}
                                        </th>
                                    @endfor
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @php $rowIndex = 0; @endphp
                                @foreach($students as $student)
                                    @php
                                        $subjectsList  = collect($student['exams'])->pluck('subject')->unique()->join('|');
                                        $coursesList   = collect($student['exams'])->pluck('course_code')->unique()->join('|');
                                        $examinersList = collect($student['exams'])->pluck('examiner')->filter()->unique()->join('|');
                                        $hasDisability = $student['disability_compensation_percent'] ? '1' : '0';
                                        $rowIndex++;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                        x-show="matchesRow($el)"
                                        x-cloak
                                        data-name="{{ strtolower($student['full_name']) }}"
                                        data-subjects="{{ strtolower($subjectsList) }}"
                                        data-courses="{{ strtolower($coursesList) }}"
                                        data-examiners="{{ strtolower($examinersList) }}"
                                        data-disability="{{ $hasDisability }}">

                                        {{-- Laufende Nummer --}}
                                        <td class="px-2 py-2 whitespace-nowrap text-gray-400 dark:text-gray-500 font-mono text-xs">
                                            {{ $rowIndex }}
                                        </td>

                                        {{-- Name --}}
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student['full_name'] }}</div>
                                            @if($student['disability_compensation_percent'])
                                                <div class="mt-0.5">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300">
                                                        +{{ $student['disability_compensation_percent'] }}% Nachteilsausgleich
                                                    </span>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Prüfungs-Spalten --}}
                                        @php
                                            $writtenExams = collect($student['exams'])->where('type', 'written')->values();
                                            $oralExams    = collect($student['exams'])->where('type', 'oral')->values();
                                        @endphp

                                        @for($i = 0; $i < $maxWritten; $i++)
                                            @php $exam = $writtenExams[$i] ?? null; @endphp
                                            <td class="px-2 py-2" style="min-width: 185px; max-width: 210px;">
                                                @if($exam)
                                                    <div class="space-y-0.5">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $exam['subject'] }}
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">S</span>
                                                        </div>
                                                        <div class="text-gray-500 dark:text-gray-400">
                                                            {{ $exam['course_code'] }}
                                                            @if(isset($exam['examiner']) && $exam['examiner'] !== 'n/a' && $exam['examiner'] !== '-')
                                                                | {{ $exam['examiner'] }}
                                                            @endif
                                                        </div>
                                                        @if($exam['is_assigned'])
                                                            <div class="text-green-700 dark:text-green-300">✓ {{ $exam['date_formatted'] }}</div>
                                                            <div class="text-gray-600 dark:text-gray-400">
                                                                {{ $exam['time'] }}
                                                                @if(isset($exam['end_time']) && $exam['end_time'] !== '-')
                                                                    – {{ $exam['end_time'] }}
                                                                    @if(isset($exam['end_time_base']) && $exam['end_time_base'])
                                                                        <span class="text-orange-500 dark:text-orange-400">(Basis: {{ $exam['end_time_base'] }})</span>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                            <div class="text-gray-500 dark:text-gray-400">
                                                                @if($exam['room'] && $exam['room'] !== '-') {{ $exam['room'] }} @endif
                                                                @if($exam['seat']) · Platz #{{ $exam['seat'] }} @endif
                                                            </div>
                                                        @else
                                                            <div class="text-orange-600 dark:text-orange-400">⏱ Nicht geplant</div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">–</span>
                                                @endif
                                            </td>
                                        @endfor

                                        @for($i = 0; $i < $maxOral; $i++)
                                            @php $exam = $oralExams[$i] ?? null; @endphp
                                            <td class="px-2 py-2" style="min-width: 175px; max-width: 200px;">
                                                @if($exam)
                                                    <div class="space-y-0.5">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $exam['subject'] }}
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">M</span>
                                                        </div>
                                                        <div class="text-gray-500 dark:text-gray-400">
                                                            {{ $exam['course_code'] }}
                                                            @if(isset($exam['examiner']) && $exam['examiner'] !== 'n/a' && $exam['examiner'] !== '-')
                                                                | {{ $exam['examiner'] }}
                                                            @endif
                                                        </div>
                                                        @if($exam['is_assigned'])
                                                            <div class="text-green-700 dark:text-green-300">✓ {{ $exam['date_formatted'] }}</div>
                                                            <div class="text-gray-500 dark:text-gray-400">{{ substr($exam['time'], 0, 5) }} | {{ $exam['room'] }}</div>
                                                        @else
                                                            <div class="text-orange-600 dark:text-orange-400">⏱ Nicht geplant</div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">–</span>
                                                @endif
                                            </td>
                                        @endfor

                                        {{-- Aktionen --}}
                                        <td class="px-2 py-2 whitespace-nowrap text-right">
                                            @unlessarchive
                                            <div class="flex justify-end space-x-1">
                                                <a href="{{ route('students.edit', $student['id']) }}" 
                                                   class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="Bearbeiten">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                                <form method="POST" 
                                                      action="{{ route('students.destroy', $student['id']) }}" 
                                                      data-confirm="Sind Sie sicher, dass Sie {{ $student['full_name'] }} löschen möchten?"
                                                      class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Löschen">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                            @endarchive
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Keine Ergebnisse nach Filter --}}
                        <div class="p-8 text-center text-gray-400 dark:text-gray-500 text-sm hidden" id="no-results-hint">
                            Keine Schüler entsprechen dem Filter.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
