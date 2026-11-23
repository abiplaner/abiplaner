@php
    /*
     * Kolloquiumsplan – orientiert am echten Prüfungsplan-Format
     * Annotationen:
     *   Ziffern  → Warteraum (Koppelgruppe muss warten)
     *   Sterne   → Zeitzuschlag (NTA)
     *   X        → Musik-Kolloquium (+5 min)
     */
    $supTypeLabels = [
        'preparation' => 'Vorbereitungsraum',
        'corridor'    => 'Gangaufsicht',
        'waiting'     => 'Warteraum',
    ];
    $supTypeOrder = ['preparation', 'corridor', 'waiting'];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kolloquiumsplan – {{ $examPeriod->name }}</title>
    <style>
        /*
         * DomPDF-Hinweis: position:fixed / top:X ist relativ zum Content-Bereich
         * (NACH dem @page-Rand). Daher muss top negativ sein, damit das Element
         * in den @page-Rand hineinragt und auf jeder Seite sichtbar ist.
         */
        @page { margin: 22mm 0 10mm 0; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.25;
            color: #111;
            margin: 0 7mm 0 7mm;
        }

        /* ── Wiederholender Header (top negativ → in @page-Rand, jede Seite) ── */
        .running-header {
            position: fixed;
            top: -22mm; left: 0; right: 0;
            height: 20mm;
            background: #fff;
            text-align: center;
        }
        .school-name { font-size: 10pt; font-weight: bold; color: #1e3a5f; }
        .plan-title  { font-size: 12pt; font-weight: bold; color: #1e3a5f; margin: 1px 0; }
        .plan-meta   { font-size: 6.5pt; color: #555; }

        /* ── Tagesblöcke ── */
        .day-block   { margin-bottom: 12px; margin-top: 6px; page-break-inside: avoid; }
        .day-header  {
            background: #1e3a5f; color: #fff; font-size: 9pt; font-weight: bold;
            padding: 4px 7px; border-radius: 2px 2px 0 0;
        }
        .day-aufsichten { display: none; } /* ersetzt durch sup-table */
        .day-rooms {
            background: #f0f4f8; font-size: 12pt; font-weight: bold; color: #333;
            padding: 4px 7px; border: 1px solid #cdd5df; border-top: none; margin-bottom: 2px;
        }

        /* ── Tabelle ── */
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead th {
            background: #f3f4f6; color: #1f2937;
            padding: 4px 3px; border: 1px solid #d1d5db;
            text-align: center; vertical-align: top;
            word-break: break-word; overflow-wrap: break-word;
        }
        thead th.time-th {
            background: #e5e7eb; color: #111;
            font-size: 7pt; font-weight: bold;
        }
        .th-room { font-weight: bold; font-size: 8pt; color: #1e3a5f; margin-bottom: 3px; }
        .th-team {
            font-size: 6pt; color: #374151; line-height: 1.4;
            border-top: 1px solid #9ca3af; margin-top: 3px; padding-top: 2px;
        }
        .th-team:first-of-type { border-top: none; margin-top: 0; padding-top: 0; }
        .th-name {
            font-size: 6pt; color: #374151;
            word-break: break-word; overflow-wrap: break-word;
        }
        tbody td { padding: 3px 3px; border: 1px solid #d1d5db; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        tbody tr:nth-child(odd)  td { background: #fff; }
        td.time-cell {
            background: #eff6ff !important;
            font-weight: bold; font-size: 7pt;
            color: #1e3a5f; text-align: center; white-space: nowrap;
        }
        .s-name   { font-weight: bold; font-size: 6.5pt; color: #111; }
        .s-course { font-size: 6pt; color: #374151; display: block; }
        .s-wait   { font-weight: bold; font-size: 6.5pt; color: #1d4ed8; vertical-align: super; }
        .s-nta    { font-weight: bold; font-size: 6.5pt; color: #b45309; vertical-align: super; }
        .s-music  { font-weight: bold; font-size: 6.5pt; color: #7c3aed; vertical-align: super; }
        .s-ext    { font-size: 6pt; color: #7c3aed; }
        .empty-c  { color: #bbb; text-align: center; font-size: 7pt; }
        .footnotes {
            font-size: 6.5pt; color: #333; margin-top: 2px;
            padding: 2px 5px; border: 1px solid #e5e7eb; background: #f9fafb;
        }
        /* ── Aufsichts-Tabelle (unterhalb der Prüfungstabelle) ── */
        .sup-table-wrap { margin-top: 4px; }
        .sup-table-label {
            font-size: 8pt; font-weight: bold; color: #1e3a5f;
            background: #dbeafe; padding: 3px 6px;
            border: 1px solid #93c5fd; border-bottom: none;
            border-radius: 2px 2px 0 0;
        }
        .sup-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sup-table th {
            background: #eff6ff; color: #1e3a5f; font-size: 8pt; font-weight: bold;
            padding: 3px 5px; border: 1px solid #bfdbfe; text-align: center;
        }
        .sup-table td {
            font-size: 8pt; color: #374151;
            padding: 3px 5px; border: 1px solid #dbeafe; vertical-align: top;
            word-break: break-word;
        }
        .sup-table td.sup-time {
            background: #eff6ff; font-weight: bold; color: #1e3a5f;
            text-align: center; white-space: nowrap; width: 14%;
        }
        .sup-nn { color: #9ca3af; font-style: italic; }
        .schlusstext-page { page-break-before: always; padding-top: 4mm; }
        .schlusstext {
            padding: 8px 10px;
            border: 1px solid #d1d5db; background: #f9fafb;
            font-size: 8pt; color: #333; line-height: 1.6;
        }
        .page-footer {
            position: fixed;
            bottom: -10mm; left: 0; right: 0;
            height: 8mm; padding: 1mm 7mm;
            font-size: 6pt; color: #9ca3af; text-align: center;
            border-top: 1px solid #e5e7eb; background: #fff;
        }
        .no-days { text-align: center; color: #9ca3af; padding: 40px; font-size: 11pt; }
    </style>
</head>
<body>

{{-- ══ Wiederholender Seitenkopf ══ --}}
<div class="running-header">
    <div style="padding-top: 3mm; text-align: center;">
        @if($school)
            <div class="school-name">{{ $school->name }}</div>
        @endif
        <div class="plan-title">Colloquium {{ $examPeriod->name }}</div>
        <div class="plan-meta">
            Woche&nbsp;1:&nbsp;{{ $examPeriod->week_1_start->format('d.m.Y') }}–{{ $examPeriod->week_1_end->format('d.m.Y') }}
            &nbsp;|&nbsp;
            Woche&nbsp;2:&nbsp;{{ $examPeriod->week_2_start->format('d.m.Y') }}–{{ $examPeriod->week_2_end->format('d.m.Y') }}
            &nbsp;|&nbsp;
            Stand:&nbsp;{{ now()->format('d.m.Y H:i') }}&nbsp;Uhr
        </div>
    </div>
</div>

{{-- ══ Seitenfußzeile ══ --}}
<div class="page-footer">
    {{ $school?->name ?? '' }}
    &nbsp;|&nbsp; Kolloquiumsplan {{ $examPeriod->name }}
    &nbsp;|&nbsp; Erstellt: {{ now()->format('d.m.Y H:i') }} Uhr
</div>

@if(empty($days))
    <div class="no-days">Keine geplanten Prüfungen gefunden.</div>
@else
    @foreach($days as $day)
        @php
            $date      = $day['date'];
            $dateStr   = $day['dateStr'];
            $prepRooms = $day['prepRooms'];
            $waitRooms = $day['waitRooms'];
            $timeSlots = $day['timeSlots'];
            $daySupSlots = $supervisionSlotsByDate[$dateStr] ?? [];

            // ── Spalten aufbauen ───────────────────────────────────────────
            $columns = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $se        = $asgn->studentExam;
                    $roomId    = $asgn->examRoom?->id ?? 'no_room';
                    $courseKey = $se->isExternalApplicant()
                        ? 'ext_' . $se->examiner_id . '_' . ($se->getSubjectName() ?? '?')
                        : $se->examiner_id . '_' . $se->course_id;
                    if (!isset($columns[$roomId])) {
                        $columns[$roomId] = ['room' => $asgn->examRoom, 'courses' => []];
                    }
                    if (!isset($columns[$roomId]['courses'][$courseKey])) {
                        $columns[$roomId]['courses'][$courseKey] = [
                            'subjectLabel' => $se->isExternalApplicant()
                                ? ($se->getSubjectName() ?? '?')
                                : ($se->course->course_code ?? '?'),
                            'examiner'  => $se->examiner,
                            'assessors' => collect(),
                        ];
                    }
                    if ($se->assessor && !$columns[$roomId]['courses'][$courseKey]['assessors']->contains('id', $se->assessor->id)) {
                        $columns[$roomId]['courses'][$courseKey]['assessors']->push($se->assessor);
                    }
                }
            }
            uasort($columns, fn($a, $b) => strcmp(
                $a['room']?->room_number ?? 'ZZZ',
                $b['room']?->room_number ?? 'ZZZ'
            ));

            // ── Lookup [roomId][slotId] ───────────────────────────────────
            $lookup = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $lookup[$asgn->examRoom?->id ?? 'no_room'][$slot->id] = $asgn;
                }
            }
        @endphp
        @php
            // ── Warteraum-Annotationen ─────────────────────────────────────
            $couplingLastPrep = [];
            $couplingCount    = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $cid = $asgn->studentExam->coupling_id;
                    if ($cid) {
                        $couplingCount[$cid] = ($couplingCount[$cid] ?? 0) + 1;
                        $t = (string)$slot->prep_start_time;
                        if (!isset($couplingLastPrep[$cid]) || $t > $couplingLastPrep[$cid]) {
                            $couplingLastPrep[$cid] = $t;
                        }
                    }
                }
            }
            $relevantCouplings = array_filter($couplingCount, fn($c) => $c > 1);
            $waitTarget = [];
            foreach ($timeSlots as $slot) {
                $myExamEnd = (string)$slot->exam_end_time;
                $myPrep    = (string)$slot->prep_start_time;
                foreach ($slot->examAssignments as $asgn) {
                    $cid = $asgn->studentExam->coupling_id;
                    $waitTarget[$asgn->id] = null;
                    if ($cid && isset($relevantCouplings[$cid])) {
                        $lastPrep = $couplingLastPrep[$cid];
                        if ($myPrep < $lastPrep && $lastPrep > $myExamEnd) {
                            $waitTarget[$asgn->id] = $lastPrep;
                        }
                    }
                }
            }
            $usedWaitTimes = array_values(array_unique(array_filter(array_values($waitTarget))));
            sort($usedWaitTimes);
            $waitTimeToNum = [];
            foreach ($usedWaitTimes as $i => $t) { $waitTimeToNum[$t] = $i + 1; }
            $waitAnnot = [];
            foreach ($waitTarget as $asgnId => $target) {
                $waitAnnot[$asgnId] = $target !== null ? ($waitTimeToNum[$target] ?? null) : null;
            }

            // ── NTA-Annotationen ───────────────────────────────────────────
            $ntaMinutes = [];
            foreach ($timeSlots as $slot) {
                $prepMin = (int)((strtotime((string)$slot->prep_end_time)
                              - strtotime((string)$slot->prep_start_time)) / 60);
                foreach ($slot->examAssignments as $asgn) {
                    $se    = $asgn->studentExam;
                    // Nachteilsausgleich hängt am Prüfling; auch ein externer
                    // Bewerber kann einen haben.
                    $extra = $se->kompensierteDauer($prepMin) - $prepMin;
                    $ntaMinutes[$asgn->id] = $extra;
                }
            }
            $ntaDistinct = array_values(array_unique(array_filter(array_values($ntaMinutes))));
            sort($ntaDistinct);
            $starSymbols    = ['*', '**', '***', '****'];
            $ntaValueToStar = [];
            foreach ($ntaDistinct as $i => $val) { $ntaValueToStar[$val] = $starSymbols[$i] ?? str_repeat('*', $i + 1); }

            // ── Musik-Annotationen ─────────────────────────────────────────
            $musicAnnot = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $code = strtolower($asgn->studentExam->course?->course_code ?? '');
                    $musicAnnot[$asgn->id] = str_contains($code, 'mu');
                }
            }

            // ── Fußnoten ───────────────────────────────────────────────────
            $waitFootnotes = [];
            foreach ($waitTimeToNum as $t => $num) { $waitFootnotes[$num] = substr((string)$t, 0, 5); }
            ksort($waitFootnotes);
            $hasMusic     = in_array(true, $musicAnnot, true);
            $hasFootnotes = !empty($waitFootnotes) || !empty($ntaValueToStar) || $hasMusic;

            // ── Spaltenbreiten ─────────────────────────────────────────────
            $numCols = count($columns);
            $tPct    = 9;
            $cPct    = round((100 - 2 * $tPct) / max($numCols, 1), 2);
            $tStyle  = "width:{$tPct}%";
            $cStyle  = "width:{$cPct}%";
        @endphp

        @if(empty($columns))
            @continue
        @endif

        <div class="day-block">
            {{-- Tag-Header --}}
            <div class="day-header">
                {{ $date->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                &nbsp;&nbsp;(Woche {{ $day['week'] }})
            </div>

            {{-- Aufsichten werden als eigene Tabelle unterhalb der Prüfungstabelle angezeigt --}}

            {{-- Vorbereitungs-/Warterauminfo --}}
            @php
                $roomParts = [];
                if ($prepRooms->isNotEmpty()) {
                    $roomParts[] = 'Vorbereitungsraum: ' . $prepRooms->pluck('room_number')->join(', ');
                }
                if ($waitRooms->isNotEmpty()) {
                    $roomParts[] = 'Warteraum: ' . $waitRooms->pluck('room_number')->join(', ');
                }
            @endphp
            @if($roomParts)
                <div class="day-rooms">{{ implode('  |  ', $roomParts) }}</div>
            @endif

            {{-- Prüfungstabelle --}}
            <table>
                <thead>
                    <tr>
                        <th class="time-th" style="{{ $tStyle }}"><div>Vorbereitungs-</div><div>zeit</div></th>
                        <th class="time-th" style="{{ $tStyle }}"><div>Prüfungs-</div><div>zeit</div></th>
                        @foreach($columns as $roomId => $col)
                            <th style="{{ $cStyle }}">
                                <div class="th-room">{{ $col['room']?->room_number ?? '?' }}</div>
                                @foreach($col['courses'] as $courseInfo)
                                    <div class="th-team">
                                        <div class="th-name">{{ $courseInfo['subjectLabel'] }}</div>
                                        <div class="th-name">{{ $courseInfo['examiner']?->lastname ?? '–' }}</div>
                                        @foreach($courseInfo['assessors'] as $assessor)
                                            <div class="th-name">{{ $assessor->lastname }}</div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeSlots as $slot)
                        <tr>
                            <td class="time-cell" style="{{ $tStyle }}">
                                {{ substr((string)$slot->prep_start_time, 0, 5) }}&ndash;{{ substr((string)$slot->prep_end_time, 0, 5) }}
                            </td>
                            <td class="time-cell" style="{{ $tStyle }}">
                                {{ substr((string)$slot->exam_start_time, 0, 5) }}&ndash;{{ substr((string)$slot->exam_end_time, 0, 5) }}
                            </td>
                            @foreach($columns as $roomId => $col)
                                @php
                                    $asgn     = $lookup[$roomId][$slot->id] ?? null;
                                    $waitNum  = $asgn ? ($waitAnnot[$asgn->id]  ?? null) : null;
                                    $ntaExtra = $asgn ? ($ntaMinutes[$asgn->id] ?? 0)    : 0;
                                    $ntaStar  = $ntaExtra > 0 ? ($ntaValueToStar[$ntaExtra] ?? '*') : null;
                                    $isMusic  = $asgn ? ($musicAnnot[$asgn->id] ?? false) : false;
                                @endphp
                                <td style="{{ $cStyle }}">
                                    @if($asgn)
                                        @php
                                            $se = $asgn->studentExam;
                                            $courseLabel = $se->isExternalApplicant()
                                                ? ($se->getSubjectName() ?? '?')
                                                : ($se->course->course_code ?? '?');
                                            if ($se->isExternalApplicant()) {
                                                $displayName = $se->getStudentName();
                                            } else {
                                                $ln = $se->student?->lastname  ?? '?';
                                                $fn = $se->student?->firstname ?? '';
                                                $displayName = $fn
                                                    ? $ln . ', ' . strtoupper(substr($fn, 0, 1)) . '.'
                                                    : $ln;
                                            }
                                        @endphp
                                        <span class="s-name">{{ $displayName }}</span>{!! $waitNum ? '<span class="s-wait">' . $waitNum . '</span>' : '' !!}{!! $ntaStar ? '<span class="s-nta">' . $ntaStar . '</span>' : '' !!}{!! $isMusic ? '<span class="s-music">X</span>' : '' !!}<br><span class="s-course">{{ $courseLabel }}</span>
                                        @if($se->isExternalApplicant())
                                            <br><span class="s-ext">(extern)</span>
                                        @endif
                                    @else
                                        <span class="empty-c">–</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Fußnoten --}}
            @if($hasFootnotes)
                <div class="footnotes">
                    @foreach($waitFootnotes as $num => $timeStr)
                        <strong>{{ $num }}</strong>: Wartezeit bis {{ str_replace(':', '.', $timeStr) }} Uhr{!! (!$loop->last || !empty($ntaValueToStar) || $hasMusic) ? ' &nbsp;|&nbsp; ' : '' !!}
                    @endforeach
                    @foreach($ntaValueToStar as $min => $star)
                        <strong>{{ $star }}</strong> Vorbereitungszeit {{ $min }} Min. länger (NTA){!! !$loop->last || $hasMusic ? ' &nbsp;|&nbsp; ' : '' !!}
                    @endforeach
                    @if($hasMusic)
                        <strong style="color:#7c3aed">X</strong> Vorbereitungszeit 5 Min. länger (Musik-Kolloquium)
                    @endif
                </div>
            @endif

            {{-- Aufsichts-Tabelle ─────────────────────────────────────── --}}
            @if(!empty($daySupSlots) && ($showAufsichten ?? true))
                <div class="sup-table-wrap">
                    <div class="sup-table-label">Aufsichten</div>
                    <table class="sup-table">
                        <thead>
                            <tr>
                                <th style="width:14%">Zeitraum</th>
                                <th style="width:29%">Vorbereitungsraum</th>
                                <th style="width:28%">Warteraum</th>
                                <th style="width:29%">Gangaufsicht</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($daySupSlots as $supSlot)
                                <tr>
                                    <td class="sup-time">{{ $supSlot['start'] }}–{{ $supSlot['end'] }}</td>
                                    @foreach(['preparation', 'waiting', 'corridor'] as $type)
                                        @php
                                            $teachers = $supSlot['types'][$type] ?? [];
                                            $cell = !empty($teachers)
                                                ? collect($teachers)->map(fn($t) => $t->lastname)->join(', ')
                                                : null;
                                        @endphp
                                        <td>
                                            @if($cell)
                                                {{ $cell }}
                                            @else
                                                <span class="sup-nn">N.N.</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>{{-- /.day-block --}}
    @endforeach
@endif

{{-- ══ Schlusstext – eigene Seite ══ --}}
@if(!empty($schlusstext))
    <div class="schlusstext-page">
        <div class="schlusstext">
            {!! $schlusstext !!}
        </div>
    </div>
@endif

</body>
</html>
