<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kursübersicht mit Kolloquien') }} - {{ $examPeriod->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if($courses->isEmpty())
                        <div class="text-center py-8 text-gray-500">
                            <p class="text-lg">Keine Kurse gefunden.</p>
                            <p class="mt-2">Bitte importieren Sie zunächst Kursdaten.</p>
                        </div>
                    @else
                        <!-- Export-Buttons -->
                        <div class="mb-4 flex justify-end gap-3 flex-wrap">
                            <a href="{{ route('courses.export-combined-excel') }}" 
                               class="inline-flex items-center px-4 py-2 font-semibold rounded-lg shadow-md transition-colors duration-150"
                               style="background-color: #15803d; color: #ffffff;">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Fachausschüsse als Excel
                            </a>
                            <a href="{{ route('courses.export-combined-pdf') }}" 
                               class="inline-flex items-center px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Fachausschüsse als PDF
                            </a>
                            <a href="{{ route('courses.export-written-pdf') }}" 
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Schriftliche Prüfungen als PDF
                            </a>
                            <a href="{{ route('courses.export-pdf') }}" 
                               class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Kolloquiumsliste als PDF (mit Kopplungen)
                            </a>
                        </div>

                        <!-- Statistiken -->
                        <div class="mb-4 gap-2" style="display:grid; grid-template-columns: repeat(6, minmax(0, 1fr))">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                                <h3 class="text-xs font-semibold text-blue-800 dark:text-blue-200">Kurse</h3>
                                <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $courses->count() }}</p>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                <h3 class="text-xs font-semibold text-green-800 dark:text-green-200">Kolloquien</h3>
                                <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $courses->sum('colloquium_count') }}</p>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg">
                                <h3 class="text-xs font-semibold text-purple-800 dark:text-purple-200">Ø Koll./Kurs</h3>
                                <p class="text-2xl font-bold text-purple-900 dark:text-purple-100">
                                    {{ $courses->count() > 0 ? number_format($courses->sum('colloquium_count') / $courses->count(), 1) : '0' }}
                                </p>
                            </div>
                            @php
                                $examsWithoutAssessor = 0;
                                foreach ($courses as $course) {
                                    foreach ($course['students'] as $student) {
                                        if ($student['has_oral_exam'] && empty($student['assessor_id'])) {
                                            $examsWithoutAssessor++;
                                        }
                                    }
                                }
                                $coursesWithoutSecondCorrectorCount = 0;
                                foreach ($courses as $course) {
                                    if ($course['written_count'] > 0 && empty($course['second_corrector_id'])) {
                                        $coursesWithoutSecondCorrectorCount++;
                                    }
                                }
                                // Kopplungs-Statistiken (nur sinnvoll bei ≥2 Kolloquien im Kurs)
                                $coursesWithOralExams = $courses->filter(fn($c) => $c['colloquium_count'] > 1)->count();
                                $coursesWithAnyCoupling = $courses->filter(fn($c) => $c['colloquium_count'] > 1 && $c['has_couplings'])->count();
                                $totalCoupledStudents = $courses->filter(fn($c) => $c['colloquium_count'] > 1)->sum('coupled_count');
                                $totalOralStudents = $courses->filter(fn($c) => $c['colloquium_count'] > 1)->sum('colloquium_count');
                            @endphp
                            <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg">
                                <h3 class="text-xs font-semibold text-orange-800 dark:text-orange-200">Ohne Beisitzer</h3>
                                <p class="text-2xl font-bold text-orange-900 dark:text-orange-100">{{ $examsWithoutAssessor }}</p>
                                @if($examsWithoutAssessor > 0)
                                    <p class="text-xs text-orange-600 dark:text-orange-400 mt-0.5">
                                        {{ number_format(($examsWithoutAssessor / max($courses->sum('colloquium_count'), 1)) * 100, 1) }}% offen
                                    </p>
                                @else
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-0.5 flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                        Alle zugeordnet
                                    </p>
                                @endif
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg">
                                <h3 class="text-xs font-semibold text-red-800 dark:text-red-200">Ohne Zweitprüfer</h3>
                                <p class="text-2xl font-bold text-red-900 dark:text-red-100">{{ $coursesWithoutSecondCorrectorCount }}</p>
                                @if($coursesWithoutSecondCorrectorCount > 0)
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">Kurse offen</p>
                                @else
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-0.5 flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                        Alle zugeordnet
                                    </p>
                                @endif
                            </div>
                            {{-- Kopplungs-Kachel --}}
                            <div class="bg-violet-50 dark:bg-violet-900/20 p-3 rounded-lg">
                                <h3 class="text-xs font-semibold text-violet-800 dark:text-violet-200">Kopplungen</h3>
                                <p class="text-2xl font-bold text-violet-900 dark:text-violet-100">
                                    {{ $coursesWithAnyCoupling }}<span class="text-sm font-normal text-violet-600 dark:text-violet-400">/{{ $coursesWithOralExams }}</span>
                                </p>
                                @if($totalOralStudents > 0)
                                    @if($totalCoupledStudents === 0)
                                        <p class="text-xs text-violet-600 dark:text-violet-400 mt-0.5">Noch keine Kopplungen</p>
                                    @else
                                        <p class="text-xs text-violet-600 dark:text-violet-400 mt-0.5">
                                            {{ $totalCoupledStudents }}/{{ $totalOralStudents }} Koll. gekoppelt
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <!-- Kursliste -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Kurscode (Anzahl Schüler)
                                        </th>
                                        <th scope="col" class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">
                                            Fach
                                        </th>
                                        <th scope="col" class="px-1 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-24">
                                            Erstprüfer
                                        </th>
                                        <th scope="col" class="px-1 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-24">
                                            Zweitkorrektor
                                        </th>
                                        <th scope="col" class="px-1 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Schr.
                                        </th>
                                        <th scope="col" class="px-1 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-24">
                                            Beisitzer
                                        </th>
                                        <th scope="col" class="px-1 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Koll.
                                        </th>
                                        <th scope="col" class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Aktionen
                                        </th>
                                        <th scope="col" class="px-1 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($courses as $course)
                                        <!-- Kurs-Zeile -->
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-2 py-1 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <div class="flex items-center">
                                                    @if($course['student_count'] > 0)
                                                        <button 
                                                            type="button"
                                                            class="toggle-students mr-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                                                            data-course-id="{{ $course['id'] }}">
                                                            <svg class="w-5 h-5 transition-transform arrow-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                    <span class="font-medium">{{ $course['course_code'] }}</span>
                                                    <span class="text-gray-500 dark:text-gray-400 font-normal ml-1">({{ $course['student_count'] }})</span>
                                                    @if($course['has_couplings'])
                                                        <span class="ml-1.5 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300" title="{{ $course['coupling_groups']->count() }} Kopplung(en)">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                            </svg>{{ $course['coupling_groups']->count() }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-2 py-1 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                <span class="font-medium cursor-default"
                                                      title="{{ $course['subject_name'] }}">
                                                    {{ $course['subject_code'] ?: $course['subject_name'] }}
                                                </span>
                                            </td>
                                            {{-- Erstprüfer --}}
                                            <td class="px-1 py-1 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                @if(auth()->user()->isPlanner())
                                                    <select data-course-id="{{ $course['id'] }}"
                                                            data-subject-id="{{ $course['subject_id'] }}"
                                                            class="teacher-select text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-0.5 px-1">
                                                        <option value="">--</option>
                                                        @foreach($teachers as $teacher)
                                                            @if(in_array($course['subject_id'], $teacher['subject_ids']))
                                                                <option value="{{ $teacher['id'] }}"
                                                                    data-shortcode="{{ $teacher['shortcode'] }}"
                                                                    @if($course['teacher_id'] == $teacher['id']) selected @endif>
                                                                    {{ $teacher['shortcode'] }} – {{ preg_replace('/(\s)(\S)\S+$/', '$1$2.', $teacher['name']) }} ({{ $teacher['workload'] }})
                                                                </option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-xs font-medium">{{ $course['teacher_shortcode'] ?: '--' }}</span>
                                                @endif
                                            </td>
                                            {{-- Zweitkorrektor (schriftlich) --}}
                                            <td class="px-1 py-1 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                @php
                                                    $secondCorrectorShortcode = $course['second_corrector_id']
                                                        ? collect($teachers)->firstWhere('id', $course['second_corrector_id'])['shortcode'] ?? '--'
                                                        : '--';
                                                @endphp
                                                @if($course['written_count'] > 0)
                                                    @if(auth()->user()->isPlanner())
                                                        <select data-course-id="{{ $course['id'] }}"
                                                                data-subject-id="{{ $course['subject_id'] }}"
                                                                class="second-corrector-select text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-0.5 px-1">
                                                            <option value="">--</option>
                                                            @foreach($teachers as $teacher)
                                                                @if(in_array($course['subject_id'], $teacher['subject_ids']))
                                                                    <option
                                                                        value="{{ $teacher['id'] }}"
                                                                        data-shortcode="{{ $teacher['shortcode'] }}"
                                                                        @if($course['second_corrector_id'] == $teacher['id']) selected @endif>
                                                                        {{ $teacher['shortcode'] }} - {{ preg_replace('/(\s)(\S)\S+$/', '$1$2.', $teacher['name']) }} ({{ $teacher['workload'] }})
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <span class="text-xs font-medium">{{ $secondCorrectorShortcode }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600 text-xs">–</span>
                                                @endif
                                            </td>
                                            {{-- Anzahl schriftlich --}}
                                            <td class="px-1 py-1 whitespace-nowrap text-center">
                                                @if($course['written_count'] > 0)
                                                    <span class="px-2 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                        {{ $course['written_count'] }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                        0
                                                    </span>
                                                @endif
                                            </td>
                                            {{-- Beisitzer --}}
                                            <td class="px-1 py-1 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                @php
                                                    $assessorShortcode = $course['assessor_id']
                                                        ? collect($teachers)->firstWhere('id', $course['assessor_id'])['shortcode'] ?? '--'
                                                        : '--';
                                                @endphp
                                                @if($course['colloquium_count'] > 0)
                                                    @if(auth()->user()->isPlanner())
                                                        <select data-course-id="{{ $course['id'] }}"
                                                                data-subject-id="{{ $course['subject_id'] }}"
                                                                class="assessor-select text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-0.5 px-1">
                                                            <option value="">--</option>
                                                            @foreach($teachers as $teacher)
                                                                @if(in_array($course['subject_id'], $teacher['subject_ids']))
                                                                    <option value="{{ $teacher['id'] }}"
                                                                        data-shortcode="{{ $teacher['shortcode'] }}"
                                                                        @if($course['assessor_id'] == $teacher['id']) selected @endif>
                                                                        {{ $teacher['shortcode'] }} - {{ preg_replace('/(\s)(\S)\S+$/', '$1$2.', $teacher['name']) }} ({{ $teacher['workload'] }})
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <span class="text-xs font-medium">{{ $assessorShortcode }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600 text-xs">–</span>
                                                @endif
                                            </td>
                                            {{-- Anzahl Kolloquien --}}
                                            <td class="px-1 py-1 whitespace-nowrap text-center">
                                                @if($course['colloquium_count'] > 0)
                                                    <span class="px-2 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                        {{ $course['colloquium_count'] }}
                                                        @if($course['all_assessors_assigned'])
                                                            <svg class="ml-1 w-4 h-4 text-green-600 dark:text-green-400 status-check" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                            </svg>
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                        0
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-1 py-1 whitespace-nowrap text-center">
                                                @if($course['colloquium_count'] > 1)
                                                    @php
                                                        $allCoupled = $course['has_couplings'] && $course['uncoupled_count'] === 0;
                                                        $partlyCoupled = $course['has_couplings'] && $course['uncoupled_count'] > 0;
                                                    @endphp
                                                    <a href="{{ route('couplings.index', ['course' => $course['id']]) }}"
                                                       title="{{ $allCoupled ? 'Alle Kolloquien gekoppelt' : ($partlyCoupled ? $course['uncoupled_count'].' ungekoppelt' : 'Keine Kopplungen') }}"
                                                       class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium
                                                           {{ $allCoupled
                                                               ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/50'
                                                               : ($partlyCoupled
                                                                   ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-900/50'
                                                                   : 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-900/50') }}">
                                                        @if($allCoupled)
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                                        @elseif($partlyCoupled)
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                                        @endif
                                                        Koppeln
                                                        @if($course['coupling_groups']->count() > 0)
                                                            <span class="ml-0.5 opacity-75">({{ $course['coupling_groups']->count() }})</span>
                                                        @endif
                                                    </a>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600 text-xs">–</span>
                                                @endif
                                            </td>
                                            @if(auth()->user()->isPlanner())
                                            <td class="px-1 py-1 whitespace-nowrap text-center">
                                                @unlessarchive
                                                <a href="{{ route('courses.edit', $course['id']) }}"
                                                   title="Kurs bearbeiten"
                                                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:text-indigo-400 dark:hover:bg-indigo-900/30 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                                @endarchive
                                            </td>
                                            @else
                                            <td></td>
                                            @endif
                                        </tr>
                                        
                                        <!-- Schüler-Liste (ausgeblendet) -->
                                        @if($course['student_count'] > 0)
                                            <tr class="student-list hidden" data-course-id="{{ $course['id'] }}">
                                                <td colspan="9" class="px-3 py-2 bg-gray-50 dark:bg-gray-900">
                                                    <div class="max-w-5xl">
                                                        @if($course['has_couplings'])
                                                            <div class="mb-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm">
                                                                <span class="font-semibold text-blue-800 dark:text-blue-200">
                                                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                                    </svg>
                                                                    Kopplungen in diesem Kurs:
                                                                </span>
                                                                <span class="text-blue-700 dark:text-blue-300">
                                                                    {{ $course['coupled_count'] }} von {{ $course['colloquium_count'] }} Kolloquien sind gekoppelt
                                                                </span>
                                                            </div>
                                                        @endif
                                                        
                                                        <table class="min-w-full">
                                                            <thead>
                                                                <tr class="text-xs text-gray-500 dark:text-gray-400">
                                                                    <th class="px-3 py-2 text-left">Schüler</th>
                                                                    <th class="px-3 py-2 text-left">Prüfungsart</th>
                                                                    <th class="px-3 py-2 text-left">Beisitzer (individuell)</th>
                                                                    <th class="px-3 py-2 text-left">Zweitkorrektor (individuell)</th>
                                                                    <th class="px-3 py-2 text-center">Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                                @php
                                                                    // Definiere Farben für Kopplungsgruppen (gleiche wie auf Kopplungsseite)
                                                                    $couplingColors = [
                                                                        'bg-blue-100 dark:bg-blue-900/30 border-blue-300 dark:border-blue-700',
                                                                        'bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-700',
                                                                        'bg-purple-100 dark:bg-purple-900/30 border-purple-300 dark:border-purple-700',
                                                                        'bg-yellow-100 dark:bg-yellow-900/30 border-yellow-300 dark:border-yellow-700',
                                                                        'bg-pink-100 dark:bg-pink-900/30 border-pink-300 dark:border-pink-700',
                                                                        'bg-indigo-100 dark:bg-indigo-900/30 border-indigo-300 dark:border-indigo-700',
                                                                        'bg-red-100 dark:bg-red-900/30 border-red-300 dark:border-red-700',
                                                                        'bg-teal-100 dark:bg-teal-900/30 border-teal-300 dark:border-teal-700',
                                                                        'bg-orange-100 dark:bg-orange-900/30 border-orange-300 dark:border-orange-700',
                                                                        'bg-cyan-100 dark:bg-cyan-900/30 border-cyan-300 dark:border-cyan-700',
                                                                    ];
                                                                    $colorIndex = 0;
                                                                    $couplingColorMap = [];
                                                                    
                                                                    // Weise jeder Kopplungs-ID eine Farbe zu
                                                                    foreach($course['coupling_groups'] as $couplingId => $group) {
                                                                        $couplingColorMap[$couplingId] = $couplingColors[$colorIndex % count($couplingColors)];
                                                                        $colorIndex++;
                                                                    }
                                                                @endphp
                                                                
                                                                @foreach($course['students'] as $student)
                                                                    @php
                                                                        $rowClasses = 'transition-colors duration-150';
                                                                        if ($student['is_coupled']) {
                                                                            // Füge Hintergrund- und Border-Farben hinzu
                                                                            $rowClasses .= ' ' . $couplingColorMap[$student['coupling_id']] . ' border-l-4';
                                                                        } elseif (!$student['has_oral_exam']) {
                                                                            // Schüler ohne Kolloquium ausgegraut
                                                                            $rowClasses .= ' opacity-60';
                                                                        }
                                                                    @endphp
                                                                    <tr class="{{ $rowClasses }}">
                                                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                            {{ $student['student_name'] }}
                                                                            @if($student['is_coupled'])
                                                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                                                    </svg>
                                                                                    Gekoppelt
                                                                                </span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                                                            {{ $student['exam_type_display'] }}
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm">
                                                                            @if($student['has_oral_exam'])
                                                                                <select 
                                                                                    data-student-exam-id="{{ $student['id'] }}"
                                                                                    data-subject-id="{{ $course['subject_id'] }}"
                                                                                    data-is-coupled="{{ $student['is_coupled'] ? 'true' : 'false' }}"
                                                                                    data-coupling-id="{{ $student['is_coupled'] ? $student['coupling_id'] : '' }}"
                                                                                    class="student-assessor-select block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                                                    @if(!auth()->user()->isPlanner()) disabled @endif>
                                                                                    <option value="">-- Nicht zugeordnet --</option>
                                                                                    @foreach($teachers as $teacher)
                                                                                        @if(in_array($course['subject_id'], $teacher['subject_ids']))
                                                                                            <option 
                                                                                                value="{{ $teacher['id'] }}"
                                                                                                @if($student['assessor_id'] == $teacher['id']) selected @endif>
                                                                                                {{ $teacher['shortcode'] }} - {{ $teacher['name'] }}
                                                                                            </option>
                                                                                        @endif
                                                                                    @endforeach
                                                                                </select>
                                                                            @else
                                                                                <span class="text-gray-400 dark:text-gray-500 text-sm italic">–</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm">
                                                                            @if($student['has_written_exam'])
                                                                                <select 
                                                                                    data-student-exam-id="{{ $student['id'] }}"
                                                                                    data-subject-id="{{ $course['subject_id'] }}"
                                                                                    class="student-second-corrector-select block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                                                    @if(!auth()->user()->isPlanner()) disabled @endif>
                                                                                    <option value="">-- Nicht zugeordnet --</option>
                                                                                    @foreach($teachers as $teacher)
                                                                                        @if(in_array($course['subject_id'], $teacher['subject_ids']))
                                                                                            <option 
                                                                                                value="{{ $teacher['id'] }}"
                                                                                                @if($student['second_corrector_id'] == $teacher['id']) selected @endif>
                                                                                                {{ $teacher['shortcode'] }} - {{ $teacher['name'] }}
                                                                                            </option>
                                                                                        @endif
                                                                                    @endforeach
                                                                                </select>
                                                                            @else
                                                                                <span class="text-gray-400 dark:text-gray-500 text-sm italic">–</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-3 py-2 text-center">
                                                                            @if($student['is_coupled'])
                                                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                                    Gruppe {{ array_search($student['coupling_id'], array_keys($couplingColorMap)) + 1 }}
                                                                                </span>
                                                                            @else
                                                                                <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script @cspNonce>
        document.addEventListener('DOMContentLoaded', function() {
            const examPeriodId = {{ $examPeriod->id }};
            
            // Funktion zum Aktualisieren der "Ohne Beisitzer" Kachel (nur oral exams)
            function updateUnassignedCount() {
                const allStudentSelects = document.querySelectorAll('.student-assessor-select');
                let unassignedCount = 0;
                
                allStudentSelects.forEach(select => {
                    if (!select.value) {
                        unassignedCount++;
                    }
                });
                
                // Update die Kachel
                const countElement = document.querySelector('.bg-orange-50 .text-3xl');
                const statusElement = document.querySelector('.bg-orange-50 .text-xs');
                
                if (countElement) {
                    countElement.textContent = unassignedCount;
                }
                
                if (statusElement) {
                    const totalOralExams = allStudentSelects.length;
                    if (unassignedCount > 0 && totalOralExams > 0) {
                        const percentage = ((unassignedCount / totalOralExams) * 100).toFixed(1);
                        statusElement.className = 'text-xs text-orange-600 dark:text-orange-400 mt-1';
                        statusElement.innerHTML = `${percentage}% offen`;
                    } else {
                        statusElement.className = 'text-xs text-green-600 dark:text-green-400 mt-1 flex items-center';
                        statusElement.innerHTML = `
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Alle zugeordnet
                        `;
                    }
                }
            }

            // Funktion zum Aktualisieren der "Ohne Zweitprüfer" Kachel
            function updateWithoutSecondCorrectorCount() {
                const courseSelects = document.querySelectorAll('.second-corrector-select');
                let unassignedCount = 0;
                courseSelects.forEach(select => {
                    if (!select.value) {
                        unassignedCount++;
                    }
                });

                const countElement = document.querySelector('.bg-red-50 .text-3xl');
                const statusElement = document.querySelector('.bg-red-50 .text-xs');

                if (countElement) {
                    countElement.textContent = unassignedCount;
                }
                if (statusElement) {
                    if (unassignedCount > 0) {
                        statusElement.className = 'text-xs text-red-600 dark:text-red-400 mt-1';
                        statusElement.innerHTML = 'Kurse ohne Zweitkorrektor';
                    } else {
                        statusElement.className = 'text-xs text-green-600 dark:text-green-400 mt-1 flex items-center';
                        statusElement.innerHTML = `
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Alle zugeordnet
                        `;
                    }
                }
            }
            
            // Hilfsfunktion: Status-Icon für einen Kurs aktualisieren (nur oral exams)
            function updateCourseStatusIcon(courseId) {
                const studentList = document.querySelector(`.student-list[data-course-id="${courseId}"]`);
                if (!studentList) return;
                
                // Prüfe, ob alle oral exam Schüler einen Beisitzer haben
                const studentSelects = studentList.querySelectorAll('.student-assessor-select');
                const allAssigned = Array.from(studentSelects).every(select => select.value !== '');
                
                // Finde die Kolloquienzahl-Anzeige
                const courseRow = document.querySelector(`tr:has(.toggle-students[data-course-id="${courseId}"])`);
                if (!courseRow) return;
                
                const colloquiumCell = courseRow.querySelector('td:nth-child(5) span.bg-blue-100');
                if (!colloquiumCell) return;
                
                // Entferne altes Häkchen wenn vorhanden
                const existingCheck = colloquiumCell.querySelector('.status-check');
                if (existingCheck) {
                    existingCheck.remove();
                }
                
                // Füge neues Häkchen hinzu wenn alle zugeordnet sind
                if (allAssigned && studentSelects.length > 0) {
                    const checkIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    checkIcon.setAttribute('class', 'ml-1 w-4 h-4 text-green-600 dark:text-green-400 status-check');
                    checkIcon.setAttribute('fill', 'currentColor');
                    checkIcon.setAttribute('viewBox', '0 0 20 20');
                    checkIcon.innerHTML = '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />';
                    
                    colloquiumCell.appendChild(checkIcon);
                }
            }
            
            // Prüft ob alle Beisitzer innerhalb einer Koppelgruppe übereinstimmen
            function checkCouplingAssessorConsistency(courseId) {
                const studentList = document.querySelector(`.student-list[data-course-id="${courseId}"]`);
                if (!studentList) return;

                // Beisitzer-Selects nach coupling_id gruppieren
                const groups = {};
                studentList.querySelectorAll('.student-assessor-select[data-coupling-id]').forEach(select => {
                    const cid = select.dataset.couplingId;
                    if (!cid) return;
                    if (!groups[cid]) groups[cid] = [];
                    groups[cid].push(select);
                });

                Object.values(groups).forEach(selects => {
                    if (selects.length < 2) return;
                    const values = selects.map(s => s.value);
                    const allSame = values.every(v => v === values[0]);

                    selects.forEach(select => {
                        const td = select.closest('td');
                        const existingWarn = td.querySelector('.assessor-mismatch-warning');

                        if (allSame) {
                            select.style.outline = '';
                            if (existingWarn) existingWarn.remove();
                        } else {
                            select.style.outline = '2px solid #f97316';
                            select.style.outlineOffset = '1px';
                            if (!existingWarn) {
                                const warn = document.createElement('span');
                                warn.className = 'assessor-mismatch-warning';
                                warn.title = 'Beisitzer stimmt nicht mit der gesamten Koppelgruppe überein';
                                warn.style.cssText = 'display:inline-flex;align-items:center;margin-left:4px;color:#f97316;';
                                warn.innerHTML = '<svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
                                td.appendChild(warn);
                            }
                        }
                    });
                });
            }

            // Toggle-Funktion für Schülerlisten
            document.querySelectorAll('.toggle-students').forEach(button => {
                button.addEventListener('click', function() {
                    const courseId = this.dataset.courseId;
                    const studentList = document.querySelector(`.student-list[data-course-id="${courseId}"]`);
                    const arrow = this.querySelector('.arrow-icon');
                    
                    if (studentList.classList.contains('hidden')) {
                        studentList.classList.remove('hidden');
                        arrow.style.transform = 'rotate(180deg)';
                        // Beisitzer-Konsistenz prüfen beim Aufklappen
                        checkCouplingAssessorConsistency(courseId);
                    } else {
                        studentList.classList.add('hidden');
                        arrow.style.transform = 'rotate(0deg)';
                    }
                });
            });

            // Kursleiter-Änderung (teacher-select)
            document.querySelectorAll('.teacher-select').forEach(select => {
                select.addEventListener('change', function() {
                    const courseId = this.dataset.courseId;
                    const teacherId = this.value || null;

                    this.disabled = true;
                    this.style.opacity = '0.5';

                    fetch(`/courses/${courseId}/update-teacher`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ teacher_id: teacherId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Grünes Feedback
                            const originalBg = this.style.backgroundColor;
                            this.style.backgroundColor = '#d1fae5';
                            setTimeout(() => { this.style.backgroundColor = originalBg; }, 1000);
                        } else {
                            alert('Fehler beim Ändern des Kursleiters');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Ändern des Kursleiters');
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.style.opacity = '1';
                    });
                });
            });

            // Bulk-Zuordnung (Kurs-Level) - nur für oral exams
            const assessorSelects = document.querySelectorAll('.assessor-select');
            assessorSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const courseId = this.dataset.courseId;
                    const assessorId = this.value || null;
                    
                    // Visual feedback
                    this.disabled = true;
                    this.style.opacity = '0.5';
                    
                    fetch(`/courses/${courseId}/assign-assessor`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ assessor_id: assessorId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Success feedback
                            const originalBg = this.style.backgroundColor;
                            this.style.backgroundColor = '#d1fae5'; // green-100
                            
                            // Update alle Schüler-Dropdowns in diesem Kurs (nur die mit oral exams)
                            const studentList = document.querySelector(`.student-list[data-course-id="${courseId}"]`);
                            if (studentList) {
                                studentList.querySelectorAll('.student-assessor-select').forEach(studentSelect => {
                                    studentSelect.value = assessorId || '';
                                });
                            }
                            
                            // Update Status-Icon
                            updateCourseStatusIcon(courseId);

                            // Kopplungs-Konsistenz prüfen (nach Bulk-Assign alle gleich → kein Konflikt)
                            checkCouplingAssessorConsistency(courseId);
                            
                            // Update "Ohne Beisitzer" Kachel
                            updateUnassignedCount();
                            
                            setTimeout(() => {
                                this.style.backgroundColor = originalBg;
                            }, 1000);
                        } else {
                            alert('Fehler beim Zuordnen des Beisitzers');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Zuordnen des Beisitzers');
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.style.opacity = '1';
                    });
                });
            });
            
            // Einzelzuordnung (Schüler-Level) - nur für oral exams
            const studentAssessorSelects = document.querySelectorAll('.student-assessor-select');
            studentAssessorSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const studentExamId = this.dataset.studentExamId;
                    const assessorId = this.value || null;
                    
                    // Finde die courseId aus der Eltern-Row
                    const studentRow = this.closest('tr');
                    const studentListRow = studentRow.closest('.student-list');
                    const courseId = studentListRow ? studentListRow.dataset.courseId : null;
                    
                    // Visual feedback
                    this.disabled = true;
                    this.style.opacity = '0.5';
                    
                    fetch(`/student-exams/${studentExamId}/assign-assessor`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ assessor_id: assessorId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Success feedback
                            const originalBg = this.style.backgroundColor;
                            this.style.backgroundColor = '#d1fae5'; // green-100
                            
                            // Update Status-Icon
                            if (courseId) {
                                updateCourseStatusIcon(courseId);
                                // Kopplungs-Konsistenz prüfen
                                checkCouplingAssessorConsistency(courseId);
                            }
                            
                            // Update "Ohne Beisitzer" Kachel
                            updateUnassignedCount();
                            
                            setTimeout(() => {
                                this.style.backgroundColor = originalBg;
                            }, 1000);
                        } else {
                            alert('Fehler beim Zuordnen des Beisitzers');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Zuordnen des Beisitzers');
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.style.opacity = '1';
                    });
                });
            });

            // Zweitkorrektor-Zuordnung (Kurs-Level) - für schriftliche Prüfungen
            document.querySelectorAll('.second-corrector-select').forEach(select => {
                select.addEventListener('change', function() {
                    const courseId = this.dataset.courseId;
                    const secondCorrectorId = this.value || null;

                    this.disabled = true;
                    this.style.opacity = '0.5';

                    fetch(`/courses/${courseId}/assign-second-corrector`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ second_corrector_id: secondCorrectorId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const originalBg = this.style.backgroundColor;
                            this.style.backgroundColor = '#d1fae5';

                            // Alle Schüler-Dropdowns für schriftliche Prüfungen synchronisieren
                            const studentList = document.querySelector(`.student-list[data-course-id="${courseId}"]`);
                            if (studentList) {
                                studentList.querySelectorAll('.student-second-corrector-select').forEach(studentSelect => {
                                    studentSelect.value = secondCorrectorId || '';
                                });
                            }

                            // "Ohne Zweitprüfer" Kachel aktualisieren
                            updateWithoutSecondCorrectorCount();

                            setTimeout(() => { this.style.backgroundColor = originalBg; }, 1000);
                        } else {
                            alert('Fehler beim Zuordnen des Zweitkorrektors');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Zuordnen des Zweitkorrektors');
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.style.opacity = '1';
                    });
                });
            });

            // Individuelle Zweitkorrektor-Zuweisung (Schüler-Level)
            document.querySelectorAll('.student-second-corrector-select').forEach(select => {
                select.addEventListener('change', function() {
                    const studentExamId = this.dataset.studentExamId;
                    const secondCorrectorId = this.value || null;

                    this.disabled = true;
                    this.style.opacity = '0.5';

                    fetch(`/student-exams/${studentExamId}/assign-second-corrector`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ second_corrector_id: secondCorrectorId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const originalBg = this.style.backgroundColor;
                            this.style.backgroundColor = '#d1fae5';
                            setTimeout(() => { this.style.backgroundColor = originalBg; }, 1000);
                        } else {
                            alert('Fehler beim Zuordnen des Zweitkorrektors');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Fehler beim Zuordnen des Zweitkorrektors');
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.style.opacity = '1';
                    });
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
