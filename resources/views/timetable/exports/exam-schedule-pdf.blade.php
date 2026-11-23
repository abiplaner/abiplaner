<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Klausurplan {{ $period->name }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 8pt; /* Reduced from 10pt */
            color: #1a202c;
        }
        .header {
            margin-bottom: 10px; /* Reduced from 20px */
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 14pt; /* Reduced from 18pt */
        }
        .header p {
            margin: 2px 0 0;
            color: #4a5568;
            font-size: 9pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #cbd5e0;
            padding: 2px; /* Reduced from 4px */
            vertical-align: top;
            font-size: 7.5pt; /* Reduced from 9pt */
        }
        th {
            background-color: #edf2f7;
            font-weight: bold;
            text-align: left;
            padding: 4px 2px;
        }
        tr {
            height: 26px; /* Fixed height for rows */
        }
        .week-cell {
            background-color: #ffffff; /* White background */
            color: #1a202c; /* Dark text */
            text-align: center;
            vertical-align: middle;
            font-size: 7pt;
            width: 60px;
        }
        .week-date {
            font-weight: bold;
            white-space: nowrap;
        }
        /* ... */
        .date {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f7fafc;
            padding: 1px;
        }
        .exam-entry {
            background-color: #ebf8ff;
            border-left: 2px solid #4299e1; /* Thinner border */
            padding: 1px 2px;
            margin-bottom: 2px;
            page-break-inside: avoid;
        }
        .exam-entry.highlighted {
            background-color: #ffffcc; /* Light yellow */
            border-left: 2px solid #eab308; /* Yellow-500 */
        }
        .exam-courses {
            font-weight: bold;
            font-size: 7pt;
        }
        .exam-details {
            font-size: 6.5pt;
            color: #4a5568;
        }
        .event-entry {
            background-color: #edf2f7;
            color: #718096;
            padding: 1px 2px;
            margin-bottom: 1px;
            font-style: italic;
            font-size: 7pt;
        }
        .holiday-week {
            text-align: center;
            vertical-align: middle;
            padding: 5px;
            background-color: #e2e8f0; /* Darker gray */
            color: #4a5568;
            font-weight: bold;
            font-size: 9pt;
            letter-spacing: 0.5px;
        }
        .cell-content {
            height: 38px; /* Slightly less than row height to allow for borders/padding */
            max-height: 24px;
            overflow: hidden;
        }
        /* Page margins setup for DomPDF */
        @page {
            margin: 0.5cm;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; padding-bottom: 5px;">
            Klausurplan: {{ $period->name }} 
            <span style="font-weight: normal; font-size: 14px; color: #555; margin-left: 10px;">
                {{ $schoolYear }} | Stand: {{ date('d.m.Y') }}
            </span>
        </h1>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 60px;">Woche</th>
                <th>Montag</th>
                <th>Dienstag</th>
                <th>Mittwoch</th>
                <th>Donnerstag</th>
                <th>Freitag</th>
            </tr>
        </thead>
        <tbody>
            @php
                $skipUntilIndex = -1;
            @endphp

            @foreach($weeks as $index => $week)
                @php
                    if ($index <= $skipUntilIndex) {
                        continue;
                    }

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
                    
                    // Create merged label
                    $weekLabel = count($mergedWeeks) > 1 
                        ? $mergedWeeks[0]['start']->format('d.m.') . ' bis ' . $mergedWeeks[count($mergedWeeks)-1]['end']->format('d.m.')
                        : $week['start']->format('d.m.') . ' - ' . $week['end']->format('d.m.');

                    // Mehrtägige Prüfungsblöcke mit weiteren Prüfungen im Zeitraum: Block als
                    // Kopfzeile, die übrigen Prüfungen in einer zweiten Zeile je Tageszelle.
                    $blockMitUnterzeile = [];
                    if (!$fullWeekEvent) {
                        for ($s = 0; $s < 5 && isset($week['days'][$s]); $s++) {
                            foreach ($week['days'][$s]['exams'] as $blockExam) {
                                if (!$blockExam->isMultiDay() || !$blockExam->exam_date->isSameDay($week['days'][$s]['date'])) {
                                    continue;
                                }
                                for ($t = $s; $t < 5 && isset($week['days'][$t]) && $week['days'][$t]['exams']->contains('id', $blockExam->id); $t++) {
                                    if ($week['days'][$t]['exams']->contains(fn($e) => $e->id !== $blockExam->id)) {
                                        $blockMitUnterzeile[$s] = true;
                                    }
                                }
                            }
                        }
                    }
                    $hatUnterzeile = !empty($blockMitUnterzeile);
                    $unterzeile = [];
                @endphp

                <tr style="page-break-inside: avoid;">
                    {{-- Left Column: Week Info --}}
                    <td class="week-cell" @if($hatUnterzeile) rowspan="2" @endif>
                        <div class="week-date">
                            {{ $weekLabel }}
                        </div>
                    </td>

                    @if($fullWeekEvent)
                        <td colspan="5" class="holiday-week">
                            {{ $fullWeekEvent->name ?? $fullWeekEvent->title }}
                        </td>
                    @else
                        @php $skipDayIndex = -1; @endphp
                        @for($i = 0; $i < 5; $i++)
                            @if($i <= $skipDayIndex)
                                @continue
                            @endif

                            @if(!isset($week['days'][$i]))
                                <td class="border border-gray-300"></td>
                                @continue
                            @endif

                            @php
                                $day = $week['days'][$i];
                                $colspan = 1;
                                $mergedEvent = null;
                                $mergedMultiDayExam = null;
                                
                                // 1. Check for multi-day exams (Priority)
                                if ($day['exams']->isNotEmpty()) {
                                    foreach ($day['exams'] as $exam) {
                                        // Only merge if exam is multi-day AND this is the first day
                                        if (!$exam->isMultiDay() || !$exam->exam_date->isSameDay($day['date'])) {
                                            continue;
                                        }
                                        
                                        // Calculate potential colspan
                                        $tempColspan = 1;
                                        for ($j = $i + 1; $j < 5; $j++) {
                                            if (isset($week['days'][$j]) && $week['days'][$j]['exams']->contains('id', $exam->id)) {
                                                $tempColspan++;
                                            } else {
                                                break;
                                            }
                                        }
                                        
                                        if ($tempColspan > 1 && $tempColspan > $colspan) {
                                            $colspan = $tempColspan;
                                            $mergedMultiDayExam = $exam;
                                            $skipDayIndex = $i + $colspan - 1;
                                        }
                                    }
                                }

                                // 2. Check for multi-day school events (independent of exams)
                                if ($day['school_events']->isNotEmpty()) {
                                    foreach ($day['school_events'] as $event) {
                                        if (!$event->end_date || $event->start_date->isSameDay($event->end_date)) {
                                            continue;
                                        }
                                        
                                        // Calculate potential colspan
                                        $tempColspan = 1;
                                        for ($j = $i + 1; $j < 5; $j++) {
                                            if (isset($week['days'][$j]) && $week['days'][$j]['school_events']->contains('id', $event->id)) {
                                                $tempColspan++;
                                            } else {
                                                break;
                                            }
                                        }
                                        
                                        // Always set mergedEvent if found (even if exam exists), but only use colspan if no exam
                                        if ($tempColspan > 1 && !$mergedEvent) {
                                            $mergedEvent = $event;
                                            
                                            // Only update main colspan if we don't have a multi-day exam priority
                                            if (!$mergedMultiDayExam) {
                                                $colspan = $tempColspan;
                                                $skipDayIndex = $i + $colspan - 1;
                                            }
                                        }
                                    }
                                }

                                // Check for content in merged period
                                $hasExamsInMergedPeriod = false;
                                $hasGlobalEventsInMergedPeriod = false;
                                
                                if ($mergedEvent) {
                                    for ($k = 0; $k < $colspan; $k++) {
                                        if (isset($week['days'][$i + $k])) {
                                            if ($week['days'][$i + $k]['exams']->isNotEmpty()) $hasExamsInMergedPeriod = true;
                                            if ($week['days'][$i + $k]['global_events']->isNotEmpty()) $hasGlobalEventsInMergedPeriod = true;
                                        }
                                    }
                                }
                                
                                // Compact if merged event is present AND no exams/global events in that period
                                $isCompact = ($mergedEvent && !$hasExamsInMergedPeriod && !$hasGlobalEventsInMergedPeriod);

                                // Weitere Prüfungen im Zeitraum eines mehrtägigen Blocks dürfen nicht verdeckt werden
                                // (sie stehen dann in einer zweiten Zeile in ihrer eigenen Tageszelle)
                                $otherExamsInMergedSpan = $mergedMultiDayExam && isset($blockMitUnterzeile[$i]);
                                if ($otherExamsInMergedSpan) {
                                    for ($k = 0; $k < $colspan; $k++) {
                                        if (isset($week['days'][$i + $k])) {
                                            $unterzeile[$i + $k] = [$week['days'][$i + $k], $mergedMultiDayExam];
                                        }
                                    }
                                }
                                
                                // Styles
                                $bgStyle = 'background-color: #ffffff;';
                                $contentAlign = 'vertical-align: top;';
                                $textColor = 'color: #1a202c;';
                                
                                if ($mergedMultiDayExam) {
                                    $bgStyle = 'background-color: #ebf8ff;'; // Blue-50
                                    
                                    if ($mergedMultiDayExam->is_highlighted) {
                                        $bgStyle = 'background-color: #fefcbf;'; // Yellow-100 for highlighted
                                    }
                                    
                                    $contentAlign = 'vertical-align: middle; text-align: center;';
                                    $textColor = 'color: #2c5282; font-weight: bold;';
                                } elseif ($mergedEvent) {
                                    $bgStyle = match($mergedEvent->type ?? '') {
                                        'veranstaltung' => 'background-color: #fefcbf;', // yellow
                                        'exkursion' => 'background-color: #f0fff4;', // green
                                        default => 'background-color: #ebf8ff;' // blue
                                    };
                                    
                                    if ($isCompact) {
                                        $contentAlign = 'vertical-align: middle; text-align: center;';
                                        $textColor = 'color: #2d3748; font-weight: bold;';
                                    }
                                } elseif ($day['global_events']->isNotEmpty()) {
                                    // Highlight if holiday/public holiday (though full week usually handles holidays)
                                    $bgStyle = 'background-color: #fff5f5;'; // red tint
                                }
                            @endphp

                            <td colspan="{{ $colspan }}" @if($hatUnterzeile && !$otherExamsInMergedSpan) rowspan="2" @endif class="week-cell" style="{{ $bgStyle }} {{ $contentAlign }} {{ $textColor }}">
                                <div class="cell-content">
                                    
                                    {{-- MERGED EXAM OR EVENT HEADER --}}
                                    @if($mergedMultiDayExam)
                                        @php
                                            $examTypeLabel = match($mergedMultiDayExam->exam_type) {
                                                'kolloquium' => 'Prakt./Mdl. Prüfung',
                                                'muendlich' => 'Mdl. Schulaufgabe',
                                                default => 'Prüfung'
                                            };
                                        @endphp
                                        <div style="font-size: 10px; font-weight: bold; margin-bottom: 2px; color: #2b6cb0;">
                                             {{ $examTypeLabel }} {{ implode(', ', $mergedMultiDayExam->course_codes) }}
                                             
                                             @php
                                                 // Collect all events in this timeframe
                                                 $concurrentEvents = collect();
                                                 if ($mergedEvent) {
                                                     $concurrentEvents->push($mergedEvent);
                                                 }
                                                 
                                                 for ($k = 0; $k < $colspan; $k++) {
                                                     if (isset($week['days'][$i + $k])) {
                                                         foreach ($week['days'][$i + $k]['school_events'] as $evt) {
                                                             if (!$concurrentEvents->contains('id', $evt->id)) {
                                                                 $concurrentEvents->push($evt);
                                                             }
                                                         }
                                                     }
                                                 }
                                             @endphp

                                             @foreach($concurrentEvents as $evt)
                                                <span style="color: #a0aec0; margin: 0 4px;">•</span>
                                                <span style="{{ match($evt->type) {
                                                    'veranstaltung' => 'color: #b7791f;',
                                                    'exkursion' => 'color: #2f855a;',
                                                    default => 'color: #4a5568;'
                                                } }}">
                                                    {{ $evt->name }}
                                                    @if(!$evt->end_date || $evt->start_date->isSameDay($evt->end_date))
                                                        <span style="font-weight: normal; font-size: 9px;">({{ $evt->start_date->format('d.m.') }})</span>
                                                    @endif
                                                </span>
                                             @endforeach
                                        </div>
                                        @if($mergedMultiDayExam->notes)
                                            @php
                                                // Filter out the conflict message for oral exams
                                                $displayNotes = $mergedMultiDayExam->notes;
                                                $isMuendlich = in_array($mergedMultiDayExam->exam_type, ['muendlich', 'kolloquium']);
                                                if ($isMuendlich) {
                                                    $displayNotes = str_replace('⚠️ Konflikt: Kein Unterricht an diesem Wochentag!', '', $displayNotes);
                                                }
                                            @endphp
                                            @if(trim($displayNotes))
                                                <div style="font-size: 9px; font-style: italic; color: #4a5568;">
                                                    {{ trim($displayNotes) }}
                                                </div>
                                            @endif
                                        @endif
                                    @elseif($mergedEvent)
                                        <div style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">
                                            {{ $mergedEvent->name ?? $mergedEvent->title }}
                                        </div>
                                        @if($mergedEvent->description)
                                            <div style="font-size: 9px; font-style: italic; color: #4a5568;">
                                                {{ $mergedEvent->description }}
                                            </div>
                                        @endif
                                    @endif

                                    {{-- CONTENT LOOP --}}
                                    @if(!$mergedMultiDayExam && !$isCompact)
                                        @for($k = 0; $k < $colspan; $k++)
                                            @php 
                                                $currentDayIndex = $i + $k;
                                                if (!isset($week['days'][$currentDayIndex])) continue;
                                                $currentDay = $week['days'][$currentDayIndex];
                                            @endphp
                                            
                                            <div style="margin-bottom: 4px;">
                                                {{-- Sub-date if merged --}}
                                                @if($colspan > 1)
                                                    <span style="font-size: 9px; font-weight: bold; color: #cbd5e0;">
                                                        {{ $currentDay['date']->format('d.m.') }}:
                                                    </span>
                                                @endif

                                                {{-- EXAMS --}}
                                                @foreach($currentDay['exams'] as $exam)
                                                    @include('timetable.exports.partials.exam-eintrag-pdf', ['exam' => $exam])
                                                @endforeach


                                                {{-- GLOBAL EVENTS --}}
                                                @foreach($currentDay['global_events'] as $gEvent)
                                                    <div style="color: #805ad5; font-size: 10px; font-style: italic; margin-bottom: 1px;">
                                                        {{ $gEvent->title }}
                                                    </div>
                                                @endforeach

                                                {{-- SCHOOL EVENTS (Single Day or non-merged) --}}
                                                @foreach($currentDay['school_events'] as $sEvent)
                                                    @if($mergedEvent && $sEvent->id === $mergedEvent->id)
                                                        @continue
                                                    @endif
                                                    <div style="background-color: #ebf8ff; color: #2c5282; padding: 2px 4px; border-radius: 2px; font-size: 10px; margin-bottom: 1px;">
                                                        {{ $sEvent->name ?? $sEvent->title }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endfor
                                    @endif
                                </div>
                            </td>
                        @endfor
                    @endif
                </tr>

                @if($hatUnterzeile)
                    {{-- Zweite Zeile: die übrigen Prüfungen unter einem mehrtägigen Block, je in ihrer Tageszelle --}}
                    <tr>
                        @foreach($unterzeile as [$currentDay, $blockExam])
                            <td class="week-cell" style="background-color: #ffffff; vertical-align: top; color: #1a202c;">
                                <div class="cell-content">
                                    @foreach($currentDay['exams']->reject(fn($e) => $e->id === $blockExam->id) as $exam)
                                        @include('timetable.exports.partials.exam-eintrag-pdf', ['exam' => $exam])
                                    @endforeach
                                    @foreach($currentDay['global_events'] as $gEvent)
                                        <div style="color: #805ad5; font-size: 10px; font-style: italic; margin-bottom: 1px;">
                                            {{ $gEvent->title }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
