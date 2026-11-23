<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Klausurenplan Q12/Q13
            </h2>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('timetable.events.index') }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                    📅 Schultermine verwalten
                </a>
                <a href="{{ route('timetable.tracks.index', ['school_year' => $schoolYear]) }}" class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">
                    Schienenplanung
                </a>
                <a href="{{ route('timetable.view') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700">
                    Stundenpläne
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="examScheduleDragDrop()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Filter --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                <form method="GET" action="{{ route('timetable.exam-schedule') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    
                    {{-- Halbjahr Auswahl --}}
                    <div>
                        <span class="text-sm text-gray-500 mr-2">Zeitraum:</span>
                        <select class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1"
                                data-navigate-on-change
                                data-param="period"
                                data-url="{{ route('timetable.exam-schedule', ['school_year' => $schoolYear]) }}">
                            @foreach($availablePeriods as $p)
                                <option value="{{ $p->name }}" {{ $periodName == $p->name ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                            @if($availablePeriods->isEmpty())
                                <option value="">Keine Zeiträume gefunden</option>
                            @endif
                        </select>

                        <a href="{{ route('timetable.exam-schedule.export-pdf', ['school_year' => $schoolYear, 'period' => $periodName]) }}" 
                           class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                           target="_blank">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            PDF Export
                        </a>
                    </div>

                    {{-- Schuljahr --}}
                    <div>
                        <label for="school_year" class="block text-sm font-medium text-gray-700 mb-2">Schuljahr</label>
                        <select name="school_year" id="school_year" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(\App\Support\SchoolYear::options($schoolYear) as $jahr)
                                <option value="{{ $jahr }}" {{ $schoolYear === $jahr ? 'selected' : '' }}>{{ $jahr }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Button --}}
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Filtern
                        </button>
                    </div>
                </form>
            </div>

            @if(!$period)
                {{-- Keine Daten vorhanden --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">Kein Klausurenplan gefunden</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Für das Schuljahr {{ $schoolYear }} 
                        @if($periodName)
                            ({{ $periodName }})
                        @endif
                        wurde noch kein Klausurenplan angelegt.
                    </p>
                    @if(!empty($fehlendeTermine))
                        <p class="mt-2 text-sm text-gray-600">
                            Die Zeiträume (Q12/1, Q12/2, Q13/1, Q13/2) leitet die Anwendung aus dem Terminkalender ab.
                            Dafür fehlen im Schuljahr {{ $schoolYear }} noch: {{ implode(', ', $fehlendeTermine) }}.
                        </p>
                    @endif
                    <div class="mt-4 flex flex-wrap justify-center gap-3">
                        <a href="{{ route('timetable.tracks.index', ['school_year' => $schoolYear]) }}" class="inline-flex items-center px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                            Schienenplanung öffnen
                        </a>
                        <a href="{{ route('timetable.view') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                            Stundenpläne öffnen
                        </a>
                    </div>
                </div>
            @else
                {{-- Main Layout: Schmale Sidebar links + Klausurenplan rechts --}}
                <div class="flex gap-4">
                    
                    {{-- Sidebar: Erkannte Schienen (schmal, links) --}}
                    <div class="w-64 flex-shrink-0">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg sticky top-6">
                            <div class="p-3 border-b border-gray-200 bg-indigo-50">
                                <h3 class="font-semibold text-xs text-gray-700">🎯 Schienen</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Drag & Drop</p>
                            </div>
                            
                            <div class="p-2 space-y-3 max-h-[calc(100vh-250px)] overflow-y-auto">
                                @if($courseTracks->isEmpty())
                                    <p class="text-xs text-gray-500 italic px-2">Keine Schienen erkannt</p>
                                @else
                                    @foreach($courseTracks as $grade => $gradeData)
                                        <div class="space-y-2 border-b border-gray-200 pb-3 last:border-b-0">
                                            <h4 class="text-xs font-bold text-indigo-600 uppercase tracking-wider px-2 py-1 bg-indigo-50 rounded">
                                                {{ $grade }}
                                            </h4>
                                            
                                            @foreach($gradeData as $dayOfWeek => $dayData)
                                                @foreach($dayData['tracks'] as $track)
                                                    @php
                                                        // Für Q12/2: Blende Original-Schienen mit modernen Fremdsprachen aus
                                                        // Zeige nur die "(mdl.)" und "(schriftl.)" Schienen
                                                        $shouldHideTrack = false;
                                                        
                                                        if ($periodName === 'Q12/2') {
                                                            // Prüfe ob Track-Name NICHT mit "(mdl.)" oder "(schriftl.)" endet
                                                            $trackName = $track['name'];
                                                            $isSplitTrack = str_ends_with($trackName, '(mdl.)') || 
                                                                           str_ends_with($trackName, '(schriftl.)');
                                                            
                                                            if (!$isSplitTrack) {
                                                                // Prüfe ob Track moderne Fremdsprachen enthält
                                                                $courses = $track['courses'] ?? [];
                                                                $hasModernFL = false;
                                                                $modernFLSubjects = ['e', 'f', 'it', 'sp', 'E', 'F', 'IT', 'SP'];
                                                                
                                                                foreach ($courses as $courseCode) {
                                                                    // Pattern: 1e1, 1f2, 1it1, 1sp1 (lowercase)
                                                                    if (preg_match('/^\d+([a-z]+)\d+$/i', $courseCode, $matches)) {
                                                                        $subject = strtolower($matches[1]);
                                                                        if (in_array($subject, array_map('strtolower', $modernFLSubjects))) {
                                                                            $hasModernFL = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                    // Pattern: 1E-L, 1F-L1, 1IT-L (uppercase)
                                                                    if (preg_match('/^\d+([A-Z]+)-L\d*$/i', $courseCode, $matches)) {
                                                                        $subject = strtoupper($matches[1]);
                                                                        if (in_array($subject, array_map('strtoupper', $modernFLSubjects))) {
                                                                            $hasModernFL = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                // Blende Track aus wenn er moderne FS enthält
                                                                $shouldHideTrack = $hasModernFL;
                                                            }
                                                        }
                                                    @endphp
                                                    
                                                    @if(!$shouldHideTrack)
                                                        <div 
                                                            class="p-2 bg-gradient-to-br from-indigo-50 to-purple-50 border border-indigo-200 rounded cursor-move hover:shadow-md hover:border-indigo-400 transition-all duration-200"
                                                            draggable="true"
                                                            x-on:dragstart="dragStart($event, {{ json_encode($track) }})"
                                                            x-on:dragend="dragEnd($event)"
                                                            title="Ziehen auf Klausurtermin"
                                                        >
                                                            <div class="text-xs font-semibold text-gray-700 mb-1">
                                                                {{ $track['name'] }} @if($track['all_time_slots']) • {{ $track['all_time_slots'] }} @endif
                                                            </div>
                                                            
                                                            <div class="text-xs text-gray-500 line-clamp-2">
                                                                {{ implode(', ', $track['courses_display'] ?? $track['courses']) }}
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @endforeach
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    {{-- Main Content: Klausurenplan (flexibel, rechts) --}}
                    <div class="flex-1 space-y-4">
                        
                        {{-- Legende --}}
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                            <h3 class="text-sm font-semibold mb-3">Legende</h3>
                            <div class="grid grid-cols-2 md:grid-cols-7 gap-3 text-sm">
                                <div class="flex items-center"><span class="w-4 h-4 bg-blue-200 border border-blue-300 rounded mr-2"></span> Klausurtermin</div>
                                <div class="flex items-center"><span class="w-4 h-4 bg-emerald-50 border-l-4 border-emerald-500 rounded mr-2"></span> Kurzarbeit (KA)</div>
                                <div class="flex items-center"><span class="w-4 h-4 bg-red-50 border-l-4 border-red-500 rounded mr-2"></span> Konflikt</div>
                                <div class="flex items-center"><span class="w-4 h-4 bg-gray-200 border border-gray-300 rounded mr-2"></span> Ferien</div>
                                <div class="flex items-center"><span class="w-4 h-4 bg-green-100 border border-green-300 rounded mr-2"></span> Exkursion</div>
                                <div class="flex items-center"><span class="w-4 h-4 bg-yellow-100 border border-yellow-300 rounded mr-2"></span> Veranstaltung</div>
                                <div class="flex items-center"><span class="w-4 h-4 bg-red-100 border border-red-300 rounded mr-2"></span> Feiertag</div>
                            </div>
                        </div>

                        {{-- Klausurenplan Tabelle --}}
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4">
                                    Klausurplan {{ $period->name }} - {{ $period->school_year }}
                                </h3>
                                
                                <div class="overflow-x-auto">
                                    <table class="min-w-full border-collapse border border-gray-300 text-xs">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="border border-gray-300 px-2 py-2 text-left font-semibold w-32">Woche</th>
                                                <th class="border border-gray-300 px-2 py-2 text-left font-semibold">Montag</th>
                                                <th class="border border-gray-300 px-2 py-2 text-left font-semibold">Dienstag</th>
                                                <th class="border border-gray-300 px-2 py-2 text-left font-semibold">Mittwoch</th>
                                                <th class="border border-gray-300 px-2 py-2 text-left font-semibold">Donnerstag</th>
                                                <th class="border border-gray-300 px-2 py-2 text-left font-semibold">Freitag</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $skipUntilIndex = -1;
                                            @endphp
                                            
                                            @foreach($weeks as $index => $week)
                                                @if($index <= $skipUntilIndex)
                                                    @continue
                                                @endif
                                                
                                                @php
                                                    $fullWeekEvent = $week['full_week_event_data'] ?? null;
                                                    
                                                    // Check if we can merge consecutive vacation weeks
                                                    $mergedWeeks = [$week];
                                                    if ($fullWeekEvent) {
                                                        for ($i = $index + 1; $i < count($weeks); $i++) {
                                                            $nextWeek = $weeks[$i];
                                                            $nextFullWeekEvent = $nextWeek['full_week_event_data'] ?? null;
                                                            
                                                            if ($nextFullWeekEvent && $nextFullWeekEvent->id === $fullWeekEvent->id) {
                                                                $mergedWeeks[] = $nextWeek;
                                                                $skipUntilIndex = $i;
                                                            } else {
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Create merged label if multiple weeks
                                                    $weekLabel = count($mergedWeeks) > 1 
                                                        ? $mergedWeeks[0]['label'] . ' bis ' . $mergedWeeks[count($mergedWeeks)-1]['label']
                                                        : $week['label'];
                                                    // Simplify: "13.10. – 17.10. bis 20.10. – 24.10." → "13.10. bis 24.10."
                                                    if (count($mergedWeeks) > 1) {
                                                        $firstStart = $mergedWeeks[0]['start']->format('d.m.');
                                                        $lastEnd = $mergedWeeks[count($mergedWeeks)-1]['end']->format('d.m.');
                                                        $weekLabel = $firstStart . ' bis ' . $lastEnd;
                                                    }
                                                @endphp
                                                
                                                @if($fullWeekEvent)
                                                    {{-- Entire week(s) is a holiday/vacation --}}
                                                    <tr class="bg-gray-200">
                                                        <td class="border border-gray-300 px-2 py-2 font-medium">{{ $weekLabel }}</td>
                                                        <td class="border border-gray-300 px-2 py-2 text-center font-semibold" colspan="5">
                                                            {{ $fullWeekEvent->title }}
                                                        </td>
                                                    </tr>

                                                @else
                                                    {{-- Regular week with individual days --}}
                                                    @php
                                                        // Mehrtägige Prüfungsblöcke, in deren Zeitraum weitere Prüfungen
                                                        // liegen: Der Block läuft als Kopfzeile über die Tage, die übrigen
                                                        // Prüfungen stehen in einer zweiten Zeile in ihrer Tageszelle.
                                                        // Schlüssel: Index des ersten Blocktags.
                                                        $blockMitUnterzeile = [];
                                                        for ($s = 0; $s < count($week['days']); $s++) {
                                                            foreach ($week['days'][$s]['exams'] as $blockExam) {
                                                                if (!$blockExam->isMultiDay() || !$blockExam->exam_date->isSameDay($week['days'][$s]['date'])) {
                                                                    continue;
                                                                }
                                                                for ($t = $s; $t < count($week['days']) && $week['days'][$t]['exams']->contains('id', $blockExam->id); $t++) {
                                                                    if ($week['days'][$t]['exams']->contains(fn($e) => $e->id !== $blockExam->id)) {
                                                                        $blockMitUnterzeile[$s] = true;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        $hatUnterzeile = !empty($blockMitUnterzeile);
                                                        $unterzeile = []; // [Tagesindex => [Tag, Block-Prüfung]]
                                                    @endphp
                                                    <tr>
                                                        <td class="border border-gray-300 px-2 py-2 font-medium align-top" @if($hatUnterzeile) rowspan="2" @endif>{{ $week['label'] }}</td>
                                                        
                                                        @for($i = 0; $i < count($week['days']); $i++)
                                                            @php
                                                                $day = $week['days'][$i];
                                                                $colspan = 1;
                                                                $mergedEvent = null;
                                                                $mergedMultiDayExam = null;
                                                                
                                                                // Check for multi-day exams FIRST
                                                                if ($day['exams']->isNotEmpty()) {
                                                                    foreach ($day['exams'] as $exam) {
                                                                        // Only merge if exam is multi-day AND this is the first day
                                                                        if (!$exam->isMultiDay() || !$exam->exam_date->isSameDay($day['date'])) {
                                                                            continue;
                                                                        }
                                                                        
                                                                        // Calculate how many days this exam spans in this week
                                                                        $currentSpan = 1;
                                                                        for ($j = $i + 1; $j < count($week['days']); $j++) {
                                                                            if ($week['days'][$j]['exams']->contains('id', $exam->id)) {
                                                                                $currentSpan++;
                                                                            } else {
                                                                                break;
                                                                            }
                                                                        }
                                                                        
                                                                        // Use the longest spanning exam
                                                                        if ($currentSpan > $colspan) {
                                                                            $colspan = $currentSpan;
                                                                            $mergedMultiDayExam = $exam;
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                // Also check for multi-day school events (independent of exams)
                                                                if ($day['school_events']->isNotEmpty()) {
                                                                    foreach ($day['school_events'] as $event) {
                                                                        // Only merge if event spans multiple days
                                                                        if (!$event->end_date || $event->start_date->isSameDay($event->end_date)) {
                                                                            continue;
                                                                        }
                                                                        
                                                                        $currentSpan = 1;
                                                                        for ($j = $i + 1; $j < count($week['days']); $j++) {
                                                                            if ($week['days'][$j]['school_events']->contains('id', $event->id)) {
                                                                                $currentSpan++;
                                                                            } else {
                                                                                break;
                                                                            }
                                                                        }
                                                                        
                                                                        // Always set mergedEvent if it's multi-day (independent of colspan)
                                                                        // We want to show both exam AND event
                                                                        if ($currentSpan > 1 && !$mergedEvent) {
                                                                            $mergedEvent = $event;
                                                                        }
                                                                        
                                                                        // Update colspan if event spans more days
                                                                        if ($currentSpan > $colspan) {
                                                                            $colspan = $currentSpan;
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                // Check for exams in the merged period
                                                                $hasExamsInMergedPeriod = false;
                                                                if ($mergedEvent) {
                                                                    for ($j = 0; $j < $colspan; $j++) {
                                                                        if (isset($week['days'][$i + $j]) && $week['days'][$i + $j]['exams']->isNotEmpty()) {
                                                                            $hasExamsInMergedPeriod = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                // Determine Background Class
                                                                $bgClass = '';
                                                                $hasGlobalEvent = $day['global_events']->isNotEmpty();
                                                                $hasSchoolEvent = $day['school_events']->isNotEmpty();
                                                                $hasExam = $day['exams']->isNotEmpty();

                                                                if ($mergedMultiDayExam) {
                                                                    // Multi-day exam gets blue background
                                                                    $bgClass = 'bg-blue-100';
                                                                } elseif ($mergedEvent) {
                                                                    $bgClass = match($mergedEvent->type) {
                                                                        'veranstaltung' => 'bg-yellow-50', 
                                                                        'exkursion' => 'bg-green-50',
                                                                        'pruefung' => 'bg-purple-50',
                                                                        default => 'bg-blue-50'
                                                                    };
                                                                } elseif ($hasGlobalEvent) {
                                                                    $event = $day['global_events']->first();
                                                                    $bgClass = $event->event_type === 'public_holiday' ? 'bg-red-100' : 'bg-gray-200';
                                                                } elseif ($hasSchoolEvent) {
                                                                    $event = $day['school_events']->first();
                                                                    $bgClass = match($event->type) {
                                                                        'veranstaltung' => 'bg-yellow-100',
                                                                        'exkursion' => 'bg-green-100',
                                                                        default => 'bg-blue-50'
                                                                    };
                                                                } elseif ($hasExam) {
                                                                    $bgClass = 'bg-blue-50';
                                                                }
                                                                
                                                                // Weitere Prüfungen im Zeitraum eines mehrtägigen Blocks (z. B. eine
                                                                // Klausur während der Prakt./Mdl. Prüfungen) dürfen nicht verdeckt werden.
                                                                // Sie stehen dann in einer zweiten Zeile in ihrer eigenen Tageszelle.
                                                                $otherExamsInMergedSpan = $mergedMultiDayExam && isset($blockMitUnterzeile[$i]);
                                                                if ($otherExamsInMergedSpan) {
                                                                    for ($j = 0; $j < $colspan; $j++) {
                                                                        if (isset($week['days'][$i + $j])) {
                                                                            $unterzeile[$i + $j] = [$week['days'][$i + $j], $mergedMultiDayExam];
                                                                        }
                                                                    }
                                                                }

                                                                // Center align for merged multi-day exams or events without exams
                                                                $alignClass = ($mergedMultiDayExam || ($mergedEvent && !$hasExamsInMergedPeriod && !$hasGlobalEvent)) ? 'align-middle text-center' : 'align-top';
                                                            @endphp
                                                            
                                                            <td 
                                                                class="border border-gray-300 px-2 py-2 {{ $alignClass }} {{ $bgClass }} drop-zone relative"
                                                                colspan="{{ $colspan }}"
                                                                @if($hatUnterzeile && !$otherExamsInMergedSpan) rowspan="2" @endif
                                                                x-on:dragover.prevent="dragOver($event)"
                                                                x-on:dragleave.prevent="dragLeave($event)"
                                                                x-on:drop.prevent="drop($event, '{{ $day['date']->format('Y-m-d') }}')"
                                                                data-date="{{ $day['date']->format('Y-m-d') }}"
                                                            >
                                                                {{-- Multi-Day Exam Block (like vacation display) --}}
                                                                @if($mergedMultiDayExam)
                                                                    @php
                                                                        $isHighlighted = $mergedMultiDayExam->is_highlighted ?? false;
                                                                        $examTypeLabel = match($mergedMultiDayExam->exam_type) {
                                                                            'kolloquium' => 'Prakt./Mdl. Prüfung',
                                                                            'muendlich' => 'Mdl. Schulaufgabe',
                                                                            default => 'Prüfung'
                                                                        };
                                                                    @endphp
                                                                    <div class="relative group">
                                                                        <div class="font-bold text-blue-900">
                                                                            {{ $examTypeLabel }} {{ implode(', ', $mergedMultiDayExam->course_codes) }}
                                                                            
                                                                            @php
                                                                                // Collect all events in this timeframe (merged multi-day AND single-day)
                                                                                $concurrentEvents = collect();
                                                                                if ($mergedEvent) {
                                                                                    $concurrentEvents->push($mergedEvent);
                                                                                }
                                                                                
                                                                                // Check for other events in the covered days
                                                                                for ($k = 0; $k < $colspan; $k++) {
                                                                                    if (isset($week['days'][$i + $k])) {
                                                                                        foreach ($week['days'][$i + $k]['school_events'] as $evt) {
                                                                                            // Avoid duplicates
                                                                                            if (!$concurrentEvents->contains('id', $evt->id)) {
                                                                                                $concurrentEvents->push($evt);
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            @endphp

                                                                            @foreach($concurrentEvents as $evt)
                                                                                <span class="text-gray-400 mx-2">•</span>
                                                                                <span class="{{ match($evt->type) {
                                                                                    'veranstaltung' => 'text-yellow-700',
                                                                                    'exkursion' => 'text-green-700',
                                                                                    default => 'text-gray-700'
                                                                                } }}">
                                                                                    {{ $evt->name }}
                                                                                    @if(!$evt->end_date || $evt->start_date->isSameDay($evt->end_date))
                                                                                        <span class="text-xs font-normal">({{ $evt->start_date->format('d.m.') }})</span>
                                                                                    @endif
                                                                                </span>
                                                                            @endforeach
                                                                        </div>
                                                                        @if($mergedMultiDayExam->notes)
                                                                            <div class="text-xs text-blue-800 italic mt-0.5">
                                                                                {{ $mergedMultiDayExam->notes }}
                                                                            </div>
                                                                        @endif
                                                                        
                                                                        {{-- Actions --}}
                                                                        <div class="absolute top-1 right-1 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                                                            <button type="button" 
                                                                                x-on:click.stop="openCommentModal({{ $mergedMultiDayExam->id }}, '{{ addslashes($mergedMultiDayExam->notes ?? '') }}')" 
                                                                                class="bg-white bg-opacity-75 rounded p-0.5 text-gray-500 hover:text-blue-600 transition-colors" 
                                                                                title="Kommentar">
                                                                                <svg class="w-4 h-4 {{ $mergedMultiDayExam->notes ? 'text-blue-600 fill-current' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                                                            </button>
                                                                            <input type="checkbox" 
                                                                                class="cursor-pointer w-4 h-4 text-yellow-600 bg-white bg-opacity-75 border-gray-300 rounded focus:ring-yellow-500" 
                                                                                x-on:click.stop="toggleHighlight({{ $mergedMultiDayExam->id }}, $event.target.checked)" 
                                                                                {{ $isHighlighted ? 'checked' : '' }} 
                                                                                title="Als Änderung markieren" />
                                                                            <button type="button" 
                                                                                class="text-gray-400 hover:text-red-600 transition-colors bg-white bg-opacity-75 rounded-full p-0.5" 
                                                                                x-on:click.stop="deleteAssignment({{ $mergedMultiDayExam->id }})" 
                                                                                title="Löschen">
                                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                
                                                                {{-- Merged Event Header (only if no merged exam) --}}
                                                                @elseif($mergedEvent)
                                                                    @if(!$hasExamsInMergedPeriod)
                                                                        {{-- Compact display (like vacation) --}}
                                                                        <div class="font-bold {{ match($mergedEvent->type) {
                                                                            'veranstaltung' => 'text-yellow-800',
                                                                            'exkursion' => 'text-green-800',
                                                                            default => 'text-gray-800'
                                                                        } }}">
                                                                            {{ $mergedEvent->name }}
                                                                        </div>
                                                                        @if($mergedEvent->description)
                                                                            <div class="text-xs text-gray-600 mt-0.5">{{ $mergedEvent->description }}</div>
                                                                        @endif
                                                                    @else
                                                                        {{-- Detailed display with exams below --}}
                                                                        <div class="mb-2 p-1.5 bg-white bg-opacity-60 rounded border border-gray-200 text-center">
                                                                            <div class="font-bold text-sm {{ match($mergedEvent->type) {
                                                                                'veranstaltung' => 'text-yellow-800',
                                                                                'exkursion' => 'text-green-800',
                                                                                default => 'text-gray-800'
                                                                            } }}">
                                                                                {{ $mergedEvent->name }}
                                                                            </div>
                                                                            @if($mergedEvent->description)
                                                                                <div class="text-xs text-gray-600 mt-0.5">{{ $mergedEvent->description }}</div>
                                                                            @endif
                                                                            {{-- Compact date range for header --}}
                                                                            <div class="text-xs text-gray-500">
                                                                                {{ $day['date']->format('d.m.') }} - {{ $week['days'][$i + $colspan - 1]['date']->format('d.m.') }}
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                @endif

                                                                {{-- Content Loop (Single Day or Merged Days) --}}
                                                                @if(!$mergedMultiDayExam)
                                                                    {{-- Only show individual exams if NOT displaying a merged multi-day exam --}}
                                                                    @for($k = 0; $k < $colspan; $k++)
                                                                        @php
                                                                            $currentDayIndex = $i + $k;
                                                                            $currentDay = $week['days'][$currentDayIndex];
                                                                        @endphp
                                                                        
                                                                        {{-- If merged, show date header for inner days if there are exams --}}
                                                                        @if($colspan > 1 && $currentDay['exams']->isNotEmpty())
                                                                            <div class="text-xs font-semibold text-gray-500 mt-2 mb-1 border-b border-gray-200">
                                                                                {{ $currentDay['date']->format('d.m.') }} ({{ $currentDay['date']->locale('de')->dayName }})
                                                                            </div>
                                                                        @endif

                                                                        {{-- Global Events (Feiertage) --}}
                                                                        @foreach($currentDay['global_events'] as $event)
                                                                            <div class="font-medium text-red-700 mb-1">
                                                                                {{ $event->title }}
                                                                            </div>
                                                                        @endforeach
                                                                        
                                                                        {{-- Exam Entries --}}
                                                                        @foreach($currentDay['exams'] as $exam)
                                                                            @include('timetable.partials.exam-eintrag', ['exam' => $exam, 'currentDay' => $currentDay])
                                                                        @endforeach
                                                                    
                                                                    {{-- Regular School Events (If not merged, or other concurrent events) --}}
                                                                    @foreach($currentDay['school_events'] as $event)
                                                                        {{-- If this is the merged event, skip it here as it is shown at top --}}
                                                                        @if($mergedEvent && $event->id === $mergedEvent->id)
                                                                            @continue
                                                                        @endif
                                                                        
                                                                        <div class="font-medium {{ match($event->type) {
                                                                            'veranstaltung' => 'text-yellow-700',
                                                                            'exkursion' => 'text-green-700',
                                                                            default => 'text-gray-700'
                                                                        } }}">
                                                                            {{ $event->name }}
                                                                        </div>
                                                                        @if($event->description)
                                                                            <div class="text-gray-600 mt-0.5">{{ $event->description }}</div>
                                                                        @endif
                                                                    @endforeach

                                                                    @include('timetable.partials.kurzarbeit-knopf', ['datum' => $currentDay['date']->format('Y-m-d')])
                                                                @endfor
                                                            @elseif(!$otherExamsInMergedSpan)
                                                                {{-- Mehrtägiger Block ohne Unterzeile: Kurzarbeit über den Block anlegen (Datum im Dialog wählbar) --}}
                                                                @include('timetable.partials.kurzarbeit-knopf', ['datum' => $day['date']->format('Y-m-d')])
                                                            @endif
                                                            </td>
                                                            
                                                            @php
                                                                // Skip indices covered by colspan
                                                                $i += $colspan - 1;
                                                            @endphp
                                                        @endfor
                                                    </tr>

                                                    @if($hatUnterzeile)
                                                        {{-- Zweite Zeile: die übrigen Prüfungen unter einem mehrtägigen Block, je in ihrer Tageszelle --}}
                                                        <tr>
                                                            @foreach($unterzeile as [$currentDay, $blockExam])
                                                                @php
                                                                    $dayExams = $currentDay['exams']->reject(fn($e) => $e->id === $blockExam->id);
                                                                @endphp
                                                                <td
                                                                    class="border border-gray-300 px-2 py-2 align-top {{ $dayExams->isNotEmpty() ? 'bg-blue-50' : '' }} drop-zone relative"
                                                                    x-on:dragover.prevent="dragOver($event)"
                                                                    x-on:dragleave.prevent="dragLeave($event)"
                                                                    x-on:drop.prevent="drop($event, '{{ $currentDay['date']->format('Y-m-d') }}')"
                                                                    data-date="{{ $currentDay['date']->format('Y-m-d') }}"
                                                                >
                                                                    @foreach($currentDay['global_events'] as $event)
                                                                        <div class="font-medium text-red-700 mb-1">
                                                                            {{ $event->title }}
                                                                        </div>
                                                                    @endforeach
                                                                    @foreach($dayExams as $exam)
                                                                        @include('timetable.partials.exam-eintrag', ['exam' => $exam, 'currentDay' => $currentDay])
                                                                    @endforeach
                                                                    @include('timetable.partials.kurzarbeit-knopf', ['datum' => $currentDay['date']->format('Y-m-d')])
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endif
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>

                        {{-- Info Box --}}
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                            <strong>💡 Hinweis:</strong> 
                            <ul class="list-disc ml-5 mt-2">
                                <li>Kurse mit "3" am Anfang (z.B. 3inf1, 3l1) sind jahrgangsübergreifend (Q12+Q13)</li>
                                <li>"-L" kennzeichnet Leistungskurse (erhöhtes Anforderungsniveau)</li>
                                <li>Zeitangaben wie (1/2) = 1. und 2. Stunde, (3-5) = 3. bis 5. Stunde</li>
                                <li class="text-indigo-700 font-semibold">Schienen per Drag & Drop auf Klausurtermine ziehen</li>
                                <li class="text-emerald-700 font-semibold">Kurzarbeit: in der Tageszelle auf „+ KA" klicken (erscheint beim Überfahren) und die Schiene wählen – auch bereits verplante Schienen</li>
                                <li class="text-red-700 font-semibold">Konflikte werden rot markiert, wenn Schiene an "falschem" Wochentag liegt</li>
                                <li class="text-gray-600 text-xs">W-Seminare und bestimmte Fächer (smw, ins, eko, itko, voc) sind ausgeblendet</li>
                            </ul>
                        </div>
                        
                    </div> {{-- End lg:col-span-10 --}}
                    
                </div> {{-- End grid cols-12 --}}
            @endif

        </div>

        {{-- Modal: Kommentar --}}
        <div x-show="showCommentModal"
             x-cloak
             @keydown.escape.window="closeCommentModal()"
             class="fixed inset-0 z-50 overflow-y-auto"
             style="display: none;">
            
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                 @click="closeCommentModal()"></div>
            
            <!-- Modal content -->
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6"
                     @click.stop>
                    
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Kommentar bearbeiten
                    </h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Notiz / Kommentar
                        </label>
                        <textarea x-model="commentData.notes"
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  rows="4"
                                  placeholder="Geben Sie hier einen Kommentar ein..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button"
                                @click="closeCommentModal()"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Abbrechen
                        </button>
                        <button type="button"
                                @click="saveComment()"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Prüfungsdetails --}}
        <div x-show="showAssignModal"
             x-cloak
             @keydown.escape.window="cancelAssignment()"
             class="fixed inset-0 z-50 overflow-y-auto"
             style="display: none;">
            
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                 @click="cancelAssignment()"></div>
            
            <!-- Modal content -->
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6"
                     @click.stop>
                    
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Prüfung zuweisen
                    </h3>
                    
                    <!-- Warning if conflict -->
                    <div x-show="modalData.isConflict"
                         class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-sm text-yellow-800">
                             <strong>Achtung:</strong> Diese Schiene hat normalerweise an diesem Wochentag keinen Unterricht!
                        </p>
                    </div>
                    
                    <!-- Date display -->
                    <div class="mb-4">
                        <span class="text-sm font-medium text-gray-700">Datum:</span>
                        <span class="text-sm text-gray-900" x-text="new Date(modalData.date).toLocaleDateString('de-DE', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})"></span>
                    </div>
                    
                    <!-- Exam Type Selection -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Prüfungsart
                        </label>
                        <select x-model="modalData.exam_type"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="schriftlich">Schriftliche Prüfung</option>
                            <option value="muendlich">Mündliche Schulaufgabe</option>
                            <option value="kolloquium">Praktische oder mündliche Prüfung</option>
                        </select>
                    </div>
                    
                    <!-- End Date (only for oral exams) -->
                    <div x-show="modalData.exam_type === 'muendlich' || modalData.exam_type === 'kolloquium'"
                         class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Enddatum (optional)
                        </label>
                        <input type="date"
                               x-model="modalData.exam_end_date"
                               :min="modalData.date"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">
                            Leer lassen für eintägige Prüfung
                        </p>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 mt-6">
                        <button @click="cancelAssignment()"
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Abbrechen
                        </button>
                        <button @click="confirmAssignment()"
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Zuweisen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Kurzarbeit eintragen --}}
        <div x-show="showKurzarbeitModal"
             x-cloak
             @keydown.escape.window="showKurzarbeitModal = false"
             class="fixed inset-0 z-50 overflow-y-auto"
             style="display: none;">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                 @click="showKurzarbeitModal = false"></div>
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.stop>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Kurzarbeit eintragen</h3>
                    <p class="text-xs text-gray-500 mb-4">Zusätzlich zur Klausur; je Kurs und Zeitraum höchstens eine Kurzarbeit.</p>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Schiene</label>
                        <select x-model="kurzarbeitData.trackId"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">– Schiene wählen –</option>
                            <template x-for="schiene in kurzarbeitSchienen" :key="schiene.id">
                                <option :value="schiene.id" x-text="schiene.name + ' – ' + (schiene.courses_display || schiene.courses).join(', ')"></option>
                            </template>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Datum</label>
                        <input type="date" x-model="kurzarbeitData.date"
                               min="{{ $period?->start_date?->format('Y-m-d') }}" max="{{ $period?->end_date?->format('Y-m-d') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div x-show="kurzarbeitKonflikt()"
                         class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-sm text-yellow-800">
                            <strong>Achtung:</strong> Diese Schiene hat an diesem Wochentag normalerweise keinen Unterricht!
                        </p>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showKurzarbeitModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Abbrechen
                        </button>
                        <button type="button" @click="confirmKurzarbeit()"
                                :disabled="!kurzarbeitData.trackId || !kurzarbeitData.date"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700 disabled:opacity-50">
                            Eintragen
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Alpine.js Drag & Drop Logic --}}
    <script @cspNonce>
        function examScheduleDragDrop() {
            return {
                draggedTrack: null,
                draggedEntry: null, // For moving assignments
                showAssignModal: false,
                showCommentModal: false,
                modalData: {
                    track: null,
                    date: null,
                    isConflict: false,
                    exam_type: 'schriftlich',
                    exam_end_date: null,
                },
                commentData: {
                    entryId: null,
                    notes: ''
                },
                showKurzarbeitModal: false,
                kurzarbeitSchienen: @json($kurzarbeitSchienen ?? []),
                kurzarbeitData: {
                    trackId: '',
                    date: null,
                },

                // Wochentag im eigenen Schema (1 = Mo … 5 = Fr) zu einem Datum 'Y-m-d'
                wochentag(date) {
                    const d = new Date(date + 'T12:00:00').getDay();
                    return d === 0 ? 7 : d;
                },

                openKurzarbeitModal(date) {
                    this.kurzarbeitData = { trackId: '', date: date };
                    this.showKurzarbeitModal = true;
                },

                gewaehlteKurzarbeitSchiene() {
                    return this.kurzarbeitSchienen.find(s => String(s.id) === String(this.kurzarbeitData.trackId)) || null;
                },

                kurzarbeitKonflikt() {
                    const schiene = this.gewaehlteKurzarbeitSchiene();
                    if (!schiene || !this.kurzarbeitData.date) {
                        return false;
                    }
                    const tag = this.wochentag(this.kurzarbeitData.date);
                    return !(schiene.valid_days || []).some(d => parseInt(d) === tag);
                },

                confirmKurzarbeit() {
                    const schiene = this.gewaehlteKurzarbeitSchiene();
                    if (!schiene || !this.kurzarbeitData.date) {
                        return;
                    }
                    const tag = this.wochentag(this.kurzarbeitData.date);
                    let stunde = schiene.period;
                    if (schiene.day_period_ranges && schiene.day_period_ranges[tag]) {
                        stunde = schiene.day_period_ranges[tag].start || schiene.period;
                    }
                    this.showKurzarbeitModal = false;
                    this.saveTrackAssignment(
                        { ...schiene, period: stunde },
                        this.kurzarbeitData.date,
                        this.kurzarbeitKonflikt(),
                        'kurzarbeit',
                        null
                    );
                },
                
                dragStart(event, track) {
                    this.draggedTrack = track;
                    this.draggedEntry = null;
                    event.target.style.opacity = '0.5';
                    // Allow moving
                    event.dataTransfer.effectAllowed = 'copy'; 
                },

                dragStartEntry(event, entry) {
                    this.draggedEntry = entry;
                    this.draggedTrack = null;
                    event.target.style.opacity = '0.5';
                    event.dataTransfer.effectAllowed = 'move';
                    event.stopPropagation(); // Prevent bubbling to parent
                },
                
                dragEnd(event) {
                    event.target.style.opacity = '1';
                },
                
                dragOver(event) {
                    event.currentTarget.classList.add('bg-indigo-100', 'border-indigo-400', 'border-2');
                    // Important for allowing drop
                    event.dataTransfer.dropEffect = this.draggedEntry ? 'move' : 'copy';
                },
                
                dragLeave(event) {
                    event.currentTarget.classList.remove('bg-indigo-100', 'border-indigo-400', 'border-2');
                },
                
                drop(event, date) {
                    event.currentTarget.classList.remove('bg-indigo-100', 'border-indigo-400', 'border-2');
                    
                    // Case 1: Moving an existing assignment
                    if (this.draggedEntry) {
                        this.moveAssignment(this.draggedEntry, date);
                        this.draggedEntry = null;
                        return;
                    }

                    // Case 2: Assigning a new track
                    if (!this.draggedTrack) {
                        return;
                    }
                    
                    // Validate day of week
                    const dropDate = new Date(date);
                    const dayOfWeek = dropDate.getDay(); // 0 = Sunday, 1 = Monday, ..., 5 = Friday
                    const dayMapping = {0: 7, 1: 1, 2: 2, 3: 3, 4: 4, 5: 5, 6: 6}; // Convert JS day to our day numbering
                    const droppedDay = dayMapping[dayOfWeek];
                    
                    const validDays = this.draggedTrack.valid_days || [];
                    
                    // Determine correct period for this day
                    let targetPeriod = this.draggedTrack.period; // Fallback
                    let targetPeriodEnd = this.draggedTrack.period; // Fallback for end period
                    
                    if (this.draggedTrack.day_period_ranges && this.draggedTrack.day_period_ranges[droppedDay]) {
                        targetPeriod = this.draggedTrack.day_period_ranges[droppedDay].start || this.draggedTrack.period;
                        targetPeriodEnd = this.draggedTrack.day_period_ranges[droppedDay].end || targetPeriod;
                    }

                    // Ensure loose comparison or cast to int
                    const isValidDay = validDays.some(d => parseInt(d) === parseInt(droppedDay));
                    
                    // Use a modified track object with the correct period for this day
                    const trackToSave = { ...this.draggedTrack, period: targetPeriod };

                    // Open modal with pre-filled data
                    this.modalData = {
                        track: trackToSave,
                        date: date,
                        isConflict: !isValidDay,
                        exam_type: 'schriftlich',
                        exam_end_date: null,
                    };
                    this.showAssignModal = true;
                    
                    this.draggedTrack = null;
                },
                
                async saveTrackAssignment(track, date, isConflict = false, examType = 'schriftlich', examEndDate = null) {
                    try {
                        const response = await fetch('{{ route("timetable.exam-schedule.assign-track") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                period_id: {{ $period->id ?? 'null' }},
                                track: track,
                                exam_date: date,
                                exam_end_date: examEndDate,
                                exam_type: examType,
                                is_conflict: isConflict,
                            }),
                        });
                        
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            const error = await response.json();
                            
                            // Special handling for collision errors (409 Conflict)
                            if (response.status === 409) {
                                const message = error.message || 'Ein oder mehrere Kurse sind bereits zugewiesen.';
                                alert('KOLLISION\n\n' + message + '\n\nBitte löschen Sie zuerst die bestehende Zuweisung oder wählen Sie einen anderen Termin.');
                            } else {
                                alert('Fehler beim Zuweisen der Schiene: ' + (error.message || 'Unbekannter Fehler'));
                            }
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Fehler beim Zuweisen der Schiene: ' + error.message);
                    }
                },

                async deleteAssignment(entryId) {
                    if (!confirm('Möchten Sie diesen Klausurtermin wirklich löschen?')) {
                        return;
                    }

                    try {
                        const response = await fetch('/timetable/exam-schedule/' + entryId, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });
                        
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            alert('Fehler beim Löschen.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Fehler beim Löschen: ' + error.message);
                    }
                },
                
                async toggleHighlight(entryId, isChecked) {
                    try {
                        const response = await fetch('/timetable/exam-schedule/' + entryId + '/toggle-highlight', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });
                        
                        if (response.ok) {
                            // Optionally just update the UI without reload, but reload ensures consistency
                            window.location.reload();
                        } else {
                            alert('Fehler beim Aktualisieren der Hervorhebung.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Fehler: ' + error.message);
                    }
                },

                async moveAssignment(entry, newDate) {
                    // Start moving immediately
                    try {
                        const response = await fetch('/timetable/exam-schedule/' + entry.id + '/move', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                new_date: newDate
                            }),
                        });
                        
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            const error = await response.json();
                            
                            // Special handling for collision errors (409 Conflict)
                            if (response.status === 409) {
                                const message = error.message || 'Ein oder mehrere Kurse sind bereits am Zieltermin zugewiesen.';
                                alert('⚠️ KOLLISION BEIM VERSCHIEBEN\n\n' + message + '\n\nBitte löschen Sie zuerst die bestehende Zuweisung am Zieltermin oder wählen Sie einen anderen Termin.');
                            } else {
                                alert('Fehler beim Verschieben: ' + (error.message || 'Unbekannter Fehler'));
                            }
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Fehler beim Verschieben: ' + error.message);
                    }
                },

                confirmAssignment() {
                    // Validate end date if provided
                    if (this.modalData.exam_end_date && new Date(this.modalData.exam_end_date) < new Date(this.modalData.date)) {
                        alert('Das Enddatum muss nach dem Startdatum liegen!');
                        return;
                    }

                    // For oral exams, we ignore the "no lesson" conflict because they are scheduled individually
                    let isConflict = this.modalData.isConflict;
                    if (this.modalData.exam_type === 'muendlich' || this.modalData.exam_type === 'kolloquium') {
                        isConflict = false;
                    }

                    this.saveTrackAssignment(
                        this.modalData.track,
                        this.modalData.date,
                        isConflict,
                        this.modalData.exam_type,
                        this.modalData.exam_end_date
                    );
                    
                    this.showAssignModal = false;
                },

                cancelAssignment() {
                    this.showAssignModal = false;
                    this.modalData = {
                        track: null,
                        date: null,
                        isConflict: false,
                        exam_type: 'schriftlich',
                        exam_end_date: null,
                    };
                },

                openCommentModal(entryId, notes) {
                    this.commentData.entryId = entryId;
                    this.commentData.notes = notes;
                    this.showCommentModal = true;
                },

                closeCommentModal() {
                    this.showCommentModal = false;
                    this.commentData = {
                        entryId: null,
                        notes: ''
                    };
                },

                async saveComment() {
                    if (!this.commentData.entryId) return;

                    try {
                        const response = await fetch('/timetable/exam-schedule/' + this.commentData.entryId + '/notes', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                notes: this.commentData.notes
                            }),
                        });
                        
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            alert('Fehler beim Speichern des Kommentars.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Fehler beim Speichern: ' + error.message);
                    }
                }
            };
        }
    </script>

    {{-- Drop Zone Styling --}}
    <style>
        [x-cloak] {
            display: none !important;
        }

        .drop-zone {
            transition: all 0.2s ease;
        }
        
        .drop-zone:hover {
            cursor: pointer;
        }

        /* „+ KA" nur beim Überfahren der Tageszelle zeigen */
        .drop-zone .ka-knopf {
            opacity: 0;
            transition: opacity 0.15s ease;
        }

        .drop-zone:hover .ka-knopf,
        .ka-knopf:focus {
            opacity: 1;
        }
        
        [draggable="true"] {
            user-select: none;
            -webkit-user-drag: element;
        }
        
        [draggable="true"]:active {
            cursor: grabbing;
        }
        
        /* Line clamp utility for compact display */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Smooth scrolling for sidebar */
        .overflow-y-auto {
            scrollbar-width: thin;
            scrollbar-color: rgba(99, 102, 241, 0.3) transparent;
        }
        
        .overflow-y-auto::-webkit-scrollbar {
            width: 6px;
        }
        
        .overflow-y-auto::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .overflow-y-auto::-webkit-scrollbar-thumb {
            background-color: rgba(99, 102, 241, 0.3);
            border-radius: 3px;
        }
        
        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background-color: rgba(99, 102, 241, 0.5);
        }
    </style>
</x-app-layout>
