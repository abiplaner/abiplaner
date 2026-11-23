<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mündliche Zusatzprüfungen – {{ $examPeriod->year }}</title>
    <style>
        @page { margin: 20mm 0 10mm 0; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.25;
            color: #111;
            margin: 0 7mm 0 7mm;
        }

        /* ── Wiederholender Seitenkopf ── */
        .running-header {
            position: fixed;
            top: -20mm; left: 0; right: 0;
            height: 18mm;
            background: #fff;
            text-align: center;
        }
        .school-name { font-size: 9.5pt; font-weight: bold; color: #1e3a5f; }
        .plan-title  { font-size: 11pt;  font-weight: bold; color: #1e3a5f; margin: 1px 0; }
        .plan-meta   { font-size: 6.5pt; color: #555; }

        /* ── Seitenfußzeile ── */
        .page-footer {
            position: fixed;
            bottom: -10mm; left: 0; right: 0;
            height: 8mm; padding: 1mm 7mm;
            font-size: 6pt; color: #9ca3af; text-align: center;
            border-top: 1px solid #e5e7eb; background: #fff;
        }

        /* ── Tagesblöcke ── */
        .day-block       { margin-bottom: 10px; margin-top: 6px; }
        .day-block-break { page-break-before: always; margin-top: 6px; margin-bottom: 10px; }
        .day-header {
            background: #1e3a5f; color: #fff; font-size: 9pt; font-weight: bold;
            padding: 3px 7px; border-radius: 2px 2px 0 0;
        }

        /* ── Prüfungstabelle ── */
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead th {
            background: #f3f4f6; color: #1f2937;
            padding: 4px 3px; border: 1px solid #d1d5db;
            text-align: center; vertical-align: middle;
        }
        thead th.time-th {
            background: #e5e7eb; color: #111;
            font-size: 7pt; font-weight: bold;
        }
        .th-room { font-weight: bold; font-size: 8.5pt; color: #1e3a5f; }
        tbody td { padding: 3px 4px; border: 1px solid #d1d5db; vertical-align: top; text-align: center; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        tbody tr:nth-child(odd)  td { background: #fff; }
        td.time-cell {
            background: #eff6ff !important;
            font-weight: bold; font-size: 7pt;
            color: #1e3a5f; white-space: nowrap;
            vertical-align: middle;
        }
        .s-name    { font-weight: bold; font-size: 7.5pt; color: #111; display: block; }
        .s-fach    { font-size: 6.5pt; color: #374151; display: block; margin-top: 1px; }
        .s-pruefer { font-size: 6pt; color: #6b7280; display: block; margin-top: 1px; }
        .s-nta     { font-weight: bold; font-size: 6.5pt; color: #b45309; vertical-align: super; }
        .s-absent-name  { font-weight: bold; font-size: 7.5pt; color: #9ca3af; text-decoration: line-through; display: block; }
        .s-absent-badge { font-size: 6pt; color: #b91c1c; display: block; margin-top: 1px; }
        .empty-c { color: #ccc; font-size: 7pt; }

        /* ── Aufsichten-Tabelle (unterer Bereich je Tag) ── */
        .aufsichten-section {
            margin-top: 6px;
            border: 1px solid #bfdbfe;
            border-radius: 0 0 2px 2px;
        }
        .aufsichten-header {
            background: #dbeafe; color: #1e3a5f;
            font-size: 7pt; font-weight: bold;
            padding: 3px 7px;
        }
        .aufsichten-table { width: 100%; border-collapse: collapse; }
        .aufsichten-table td {
            padding: 3px 7px; border: 1px solid #bfdbfe;
            font-size: 7pt;
            background: #eff6ff !important;
        }
        .aufsichten-table td.auf-room { font-weight: bold; color: #1e3a5f; width: 18%; }
        .aufsichten-table td.auf-time { color: #1e3a5f; width: 20%; white-space: nowrap; }
        .aufsichten-table td.auf-names { color: #1f2937; }

        /* ── Fußnoten ── */
        .footnotes {
            font-size: 6.5pt; color: #333; margin-top: 2px;
            padding: 2px 5px; border: 1px solid #e5e7eb; background: #f9fafb;
        }

        /* ── Schlusstext ── */
        .schlusstext-section {
            margin-top: 14mm;
            padding: 5px 7px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 7.5pt;
            color: #374151;
        }
        .schlusstext-section p  { margin: 0 0 4px 0; }
        .schlusstext-section ul { margin: 0 0 4px 0; padding-left: 14px; }
        .schlusstext-section li { margin-bottom: 2px; }

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
        <div class="plan-title">Abitur {{ $examPeriod->year }} &ndash; Mündliche Zusatzprüfungen</div>
        <div class="plan-meta">Stand: {{ \Carbon\Carbon::now()->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm') }} Uhr</div>
    </div>
</div>

{{-- ══ Seitenfußzeile ══ --}}
<div class="page-footer">
    {{ $school?->name ?? '' }}
    &nbsp;|&nbsp; Mündliche Zusatzprüfungen Abitur {{ $examPeriod->year }}
    &nbsp;|&nbsp; Erstellt: {{ now()->format('d.m.Y H:i') }} Uhr
</div>

@php
/**
 * Lehreranzeige: "Nachname, V." – einheitlich für Prüfer und Aufsichten.
 */
function zpTeacherName(?\App\Models\Teacher $teacher): string {
    if (!$teacher) return '?';
    $name = $teacher->lastname ?? '?';
    $fn   = $teacher->firstname ?? '';
    if ($fn !== '') {
        $name .= ', ' . strtoupper(mb_substr($fn, 0, 1)) . '.';
    }
    return $name;
}
@endphp

@if(empty($dayData))
    <div class="no-days">Keine platzierten Prüfungen gefunden.</div>
@else
    @foreach($dayData as $data)
        @php
            $day            = $data['day'];
            $timeslots      = $data['timeslots'];
            $dayRooms       = $data['dayRooms'];
            $lookup         = $data['lookup'];
            $ntaExtra       = $data['ntaExtra'];
            $ntaValueToStar = $data['ntaValueToStar'];

            // Spaltenbreiten
            $numRooms = count($dayRooms);
            $tPct   = 9;
            $rPct   = round((100 - 2 * $tPct) / max($numRooms, 1), 2);
            $tStyle = "width:{$tPct}%";
            $rStyle = "width:{$rPct}%";

            // Tagbezeichnung
            $dayTitle = $day->label
                ? $day->label . ', ' . $day->date->format('d.m.Y')
                : $day->date->translatedFormat('l, d.m.Y');

            // ── Aufsichten: gruppiert nach Vorbereitungsraum → Liste von [Zeit, Lehrer]
            // Start = timeslot.start_time − 10 min
            // Ende  = timeslot.start_time + max(prep_duration_minutes) dieses Slots
            $aufByPrepRoom = []; // prep_room_number → [ ['time'=>'HH:MM–HH:MM', 'names'=>'...'], ... ]
            foreach ($timeslots as $_ts) {
                $_prepStart = substr((string) $_ts->start_time, 0, 5);
                [$_ph, $_pm] = explode(':', $_prepStart);
                $_baseMin = (int)$_ph * 60 + (int)$_pm;

                // Aufsicht beginnt 10 Min. vor Vorbereitungsbeginn
                $_aufStartMin = $_baseMin - 10;
                $_aufStart    = sprintf('%02d:%02d', intdiv($_aufStartMin, 60), $_aufStartMin % 60);

                // Aufsichten dieses Timeslots nach Vorbereitungsraum gruppieren
                $_byRoom = [];
                foreach ($_ts->aufsichten as $_auf) {
                    $_prepNr = $_auf->dayRoom?->prepRoom?->room_number;
                    if (!$_prepNr) continue;
                    $_byRoom[$_prepNr][] = zpTeacherName($_auf->teacher);
                }

                foreach ($_byRoom as $_prepNr => $_names) {
                    // Längste Vorbereitungszeit aller Assignments in diesem Slot
                    // (berücksichtigt NTA-Verlängerungen)
                    $_maxPrepMin = 20;
                    foreach ($dayRooms as $_dr) {
                        if ($_dr->prepRoom?->room_number !== $_prepNr) continue;
                        $_asgn = $lookup[$_dr->id][$_prepStart] ?? null;
                        if ($_asgn && $_asgn->prep_duration_minutes > $_maxPrepMin) {
                            $_maxPrepMin = $_asgn->prep_duration_minutes;
                        }
                    }

                    $_aufEndMin = $_baseMin + $_maxPrepMin;
                    $_aufEnd    = sprintf('%02d:%02d', intdiv($_aufEndMin, 60), $_aufEndMin % 60);

                    $aufByPrepRoom[$_prepNr][] = [
                        'time'  => $_aufStart . '–' . $_aufEnd,
                        'names' => implode(', ', array_unique($_names)),
                    ];
                }
            }
            ksort($aufByPrepRoom);
        @endphp

        @if($timeslots->isEmpty() && $numRooms === 0)
            @continue
        @endif

        {{-- Neue Seite ab 2. Tag --}}
        <div class="{{ $loop->first ? 'day-block' : 'day-block-break' }}">

            {{-- Tag-Header --}}
            <div class="day-header">{{ $dayTitle }}</div>

            {{-- Prüfungstabelle --}}
            <table>
                <thead>
                    <tr>
                        <th class="time-th" style="{{ $tStyle }}">
                            <div>Vorber.-</div><div>zeit</div>
                        </th>
                        <th class="time-th" style="{{ $tStyle }}">
                            <div>Prüfungs-</div><div>zeit</div>
                        </th>
                        @foreach($dayRooms as $dr)
                            <th style="{{ $rStyle }}">
                                <div class="th-room">{{ $dr->examRoom?->room_number ?? '?' }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if($timeslots->isEmpty())
                        <tr>
                            <td colspan="{{ 2 + $numRooms }}" style="text-align:center;color:#9ca3af;padding:8px;">
                                Keine Zeitslots konfiguriert.
                            </td>
                        </tr>
                    @else
                        @foreach($timeslots as $slot)
                            @php
                                $prepStart = substr((string) $slot->start_time, 0, 5);
                                [$ph, $pm] = explode(':', $prepStart);
                                $prepEndMin   = (int)$ph * 60 + (int)$pm + 20;
                                $examStartMin = $prepEndMin;
                                $examEndMin   = $examStartMin + 20;
                                $prepEnd   = sprintf('%02d:%02d', intdiv($prepEndMin, 60),   $prepEndMin   % 60);
                                $examStart = sprintf('%02d:%02d', intdiv($examStartMin, 60), $examStartMin % 60);
                                $examEnd   = sprintf('%02d:%02d', intdiv($examEndMin, 60),   $examEndMin   % 60);
                            @endphp
                            <tr>
                                <td class="time-cell" style="{{ $tStyle }}">{{ $prepStart }}&ndash;{{ $prepEnd }}</td>
                                <td class="time-cell" style="{{ $tStyle }}">{{ $examStart }}&ndash;{{ $examEnd }}</td>
                                @foreach($dayRooms as $dr)
                                    @php
                                        $asgn    = $lookup[$dr->id][$prepStart] ?? null;
                                        $extra   = $asgn ? ($ntaExtra[$asgn->id] ?? 0) : 0;
                                        $ntaStar = $extra > 0 ? ($ntaValueToStar[$extra] ?? '*') : null;
                                    @endphp
                                    <td style="{{ $rStyle }}">
                                        @if($asgn)
                                            @php
                                                $zp       = $asgn->zusatzpruefung;
                                                // Prüfling ist Schüler/in oder externe/r Bewerber/in.
                                                $dispName = $zp->prueflingsKurzname();
                                                $isAbsent = $zp->absent;

                                                $subject = $zp->studentExam->getSubjectName() ?? '';
                                                // Externe haben keinen Kurs; getSubjectLabel() gäbe
                                                // sonst noch einmal den Fachnamen zurück.
                                                $kurs    = $zp->istExtern() ? '' : $zp->studentExam->getSubjectLabel();

                                                $erstStr = $zp->pruefer
                                                    ->where('role', 'erstpruefer')
                                                    ->map(fn($p) => zpTeacherName($p->teacher))
                                                    ->join(', ');
                                                $zweitStr = $zp->pruefer
                                                    ->where('role', 'zweitkorrektor')
                                                    ->map(fn($p) => zpTeacherName($p->teacher))
                                                    ->join(', ');
                                                $prueferStr = $erstStr;
                                                if ($zweitStr) $prueferStr .= ' / ' . $zweitStr;
                                            @endphp
                                            @if($isAbsent)
                                                <span class="s-absent-name">{{ $dispName }}</span>
                                                <span class="s-absent-badge">nicht angetreten</span>
                                            @else
                                                <span class="s-name">{{ $dispName }}{!! $ntaStar ? ' <span class="s-nta">' . e($ntaStar) . '</span>' : '' !!}</span>
                                                @if($subject || $kurs)
                                                    <span class="s-fach">{{ $subject }}@if($kurs) ({{ $kurs }})@endif</span>
                                                @endif
                                                @php
                                                    $zpExcl  = $zp->excluded_semester;
                                                    $zpFocus = $zp->focus_semester;
                                                    $zpIsMath = $zp->studentExam->istMathematik();
                                                    $semLbls = ['12_1'=>'AA 12/1','12_2'=>'AA 12/2','13_1'=>'AA 13/1','13_2'=>'AA 13/2'];
                                                    if ($zpIsMath && $zpExcl) {
                                                        $zpInfo = ucfirst($zpExcl) . ' gestr.';
                                                    } elseif (!$zpIsMath && $zpExcl && isset($semLbls[$zpExcl])) {
                                                        $zpInfo = $semLbls[$zpExcl] . ' gestr.';
                                                        if ($zpFocus && isset($semLbls[$zpFocus])) {
                                                            $zpInfo .= ' / ' . $semLbls[$zpFocus] . ' SP';
                                                        }
                                                    } else {
                                                        $zpInfo = null;
                                                    }
                                                @endphp
                                                @if($zpInfo)
                                                    <span class="s-pruefer" style="color:#92400e;">{{ $zpInfo }}</span>
                                                @endif
                                                @if($prueferStr)
                                                    <span class="s-pruefer">{{ $prueferStr }}</span>
                                                @endif
                                            @endif
                                        @else
                                            <span class="empty-c">&ndash;</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>

            {{-- Fußnoten NTA --}}
            @if(!empty($ntaValueToStar))
                <div class="footnotes">
                    @foreach($ntaValueToStar as $min => $star)
                        <strong>{{ $star }}</strong> Zeitzuschlag {{ $min }} Min. (NTA){{ !$loop->last ? '&nbsp;&nbsp;|&nbsp;&nbsp;' : '' }}
                    @endforeach
                </div>
            @endif

            {{-- Aufsichten Vorbereitungsräume mit Zeiten --}}
            @if(!empty($aufByPrepRoom))
                <div class="aufsichten-section">
                    <div class="aufsichten-header">Aufsichten Vorbereitungsräume</div>
                    <table class="aufsichten-table">
                        @foreach($aufByPrepRoom as $prepNr => $rows)
                            @foreach($rows as $idx => $row)
                                <tr>
                                    @if($idx === 0)
                                        <td class="auf-room" rowspan="{{ count($rows) }}">Raum {{ $prepNr }}</td>
                                    @endif
                                    <td class="auf-time">{{ $row['time'] }} Uhr</td>
                                    <td class="auf-names">{{ $row['names'] }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </table>
                </div>
            @endif

        </div>{{-- /.day-block --}}
    @endforeach

    {{-- ══ Schlusstext ══ --}}
    @if(!empty($schlusstext))
        <div class="schlusstext-section">
            {!! $schlusstext !!}
        </div>
    @endif
@endif

</body>
</html>
