<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Vorbereitungsraum-Listen – {{ $examPeriod->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #1f2937; }
        .page { page-break-before: always; padding: 16px 18px 12px; }
        .page:first-child { page-break-before: avoid; }

        /* ── Kopfzeile ── */
        .header { border-bottom: 3px solid #854d0e; padding-bottom: 8px; margin-bottom: 10px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .school-name { font-size: 8.5pt; color: #6b7280; }
        .plan-label  { font-size: 7.5pt; color: #9ca3af; text-align: right; }
        .room-title  { font-size: 14pt; font-weight: bold; color: #854d0e; margin-top: 3px; }
        .date-line   { font-size: 10pt; color: #374151; margin-top: 2px; }

        /* ── Info-Box ── */
        .info-box {
            background-color: #fefce8; border: 1px solid #fde047;
            border-radius: 3px; padding: 5px 8px; margin-bottom: 8px;
            font-size: 8pt; color: #713f12;
        }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; margin-right: 4px; font-size: 7.5pt; }
        .badge-exam { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-wait { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        /* ── Tabelle ── */
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        thead th {
            background-color: #854d0e; color: #fff; font-weight: bold;
            padding: 5px 6px; text-align: left; border: 1px solid #6b3a09; font-size: 8pt;
        }
        thead th.center { text-align: center; }

        /* ── Zeitblock-Trennzeile ── */
        tr.slot-header td {
            background-color: #fef3c7;
            border-top: 2px solid #b45309;
            border-bottom: 1px solid #d97706;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8pt;
            color: #78350f;
        }
        tr.slot-header-first td {
            background-color: #fef3c7;
            border-top: 1px solid #d97706;
            border-bottom: 1px solid #d97706;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8pt;
            color: #78350f;
        }

        /* ── Schülerzeilen ── */
        tbody.slot-group { page-break-inside: avoid; }
        tbody tr.student-row:nth-child(odd)  td { background-color: #ffffff; }
        tbody tr.student-row:nth-child(even) td { background-color: #fefce8; }
        tbody tr.student-row td {
            padding: 2px 5px;
            border: 1px solid #e5e7eb;
            border-top: none;
            vertical-align: middle;
            white-space: nowrap;
            line-height: 1.15;
        }
        tbody tr.student-row td.wrap { white-space: normal; }

        .student-name   { font-weight: bold; font-size: 8pt; }
        .external-badge { font-size: 6pt; background-color: #ede9fe; color: #7c3aed; padding: 0 2px; border-radius: 2px; }
        .exam-room-badge { display: inline-block; background-color: #dbeafe; color: #1e40af;
                           border-radius: 3px; padding: 0 4px; font-size: 7.5pt; }
        .no-assessor { color: #ea580c; font-weight: bold; font-size: 7.5pt; }
        td.check-cell { text-align: center; font-size: 11pt; color: #d1d5db; width: 24px; }

        /* ── Unterschrifts-Bereich ── */
        .supervision-area {
            margin-top: 14px; border: 1px solid #d1d5db; border-radius: 3px;
            padding: 10px 12px 8px; background-color: #f9fafb;
        }
        .supervision-title { font-size: 8pt; font-weight: bold; color: #374151; margin-bottom: 12px; }
        .sig-row { display: flex; gap: 24px; }
        .sig-col { flex: 1; }
        .sig-label-top    { font-size: 7pt; color: #6b7280; margin-bottom: 18px; }
        .sig-line         { border-bottom: 1px solid #374151; margin-bottom: 3px; }
        .sig-label-bottom { font-size: 7pt; color: #6b7280; }
        .page-footer {
            margin-top: 8px; font-size: 7pt; color: #9ca3af;
            text-align: right; border-top: 1px solid #e5e7eb; padding-top: 4px;
        }
        .empty-note { padding: 12px; text-align: center; color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
@foreach($pages as $page)
<div class="page">

    {{-- Kopfzeile --}}
    <div class="header">
        <div class="header-top">
            <div>
                @if($school)<div class="school-name">{{ $school->name }}</div>@endif
                <div class="room-title">Vorbereitungsraum {{ $page['prepRoom']->room_number }}</div>
                <div class="date-line">
                    {{ $page['date']->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                    &nbsp;·&nbsp; Woche {{ $page['week'] }}
                </div>
            </div>
            <div class="plan-label">
                Vorbereitungsraum-Liste {{ $examPeriod->name }}<br>
                Stand: {{ now()->format('d.m.Y H:i') }} Uhr
            </div>
        </div>
    </div>

    {{-- Info-Box --}}
    @if($page['examRooms']->isNotEmpty() || $page['waitRooms']->isNotEmpty())
        <div class="info-box">
            <strong>Prüfungsräume:</strong>
            @foreach($page['examRooms'] as $r)
                <span class="badge badge-exam">{{ $r->room_number }}</span>
            @endforeach
            @if($page['waitRooms']->isNotEmpty())
                &nbsp;&nbsp;<strong>Warteraum:</strong>
                @foreach($page['waitRooms'] as $r)
                    <span class="badge badge-wait">{{ $r->room_number }}</span>
                @endforeach
            @endif
        </div>
    @endif

    {{-- Prüflings-Tabelle, nach Vorbereitungszeit gruppiert --}}
    @php
        $entries = $page['entries'];
        $grouped = [];
        foreach ($entries as $entry) {
            $key = $entry['slot']->prep_start_time . '–' . $entry['slot']->prep_end_time;
            $grouped[$key][] = $entry;
        }
    @endphp

    @if(empty($entries))
        <div class="empty-note">Keine Prüflinge für diesen Vorbereitungsraum an diesem Tag.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:38%;">Prüfling · Fach</th>
                    <th style="width:20%;">Prüfer / Beisitzer</th>
                    <th class="center" style="width:12%;">Prüfungsraum</th>
                    <th class="center" style="width:6%;">✓</th>
                    <th style="width:24%;">Anmerkungen</th>
                </tr>
            </thead>
            @php $firstGroup = true; @endphp
            @foreach($grouped as $timeLabel => $groupEntries)
                {{-- Jeder Zeitslot bekommt seinen eigenen tbody → page-break-inside:avoid greift --}}
                <tbody class="slot-group">
                    <tr class="{{ $firstGroup ? 'slot-header-first' : 'slot-header' }}">
                        <td colspan="5">{{ $timeLabel }} Uhr</td>
                    </tr>
                    @php $firstGroup = false; @endphp

                    @foreach($groupEntries as $entry)
                        @php
                            $slot = $entry['slot'];
                            $asgn = $entry['assignment'];
                            $se   = $asgn->studentExam;
                            $name = $se->getStudentName();
                            $fach = $se->isExternalApplicant()
                                 ? ($se->getSubjectName() ?? '–')
                                 : (($se->course->subject->code ?? '–') . ' (' . ($se->course->course_code ?? '–') . ')');
                        @endphp
                        <tr class="student-row">
                            <td class="wrap">
                                <span class="student-name">{{ $name }}</span>
                                @if($se->isExternalApplicant())
                                    <span class="external-badge">extern</span>
                                @endif
                                <span style="color:#6b7280;font-size:7.5pt;"> · {{ $fach }}</span>
                                @if($se->hatNachteilsausgleich())
                                    @php
                                        $prepMin  = (strtotime($slot->prep_end_time) - strtotime($slot->prep_start_time)) / 60;
                                        $ntaMin   = $se->kompensierteDauer((int)$prepMin);
                                        $ntaStart = \Carbon\Carbon::createFromTimeString($slot->exam_start_time)->subMinutes($ntaMin)->format('H:i');
                                    @endphp
                                    <span style="font-size:6.5pt;background:#ede9fe;color:#7c3aed;padding:0 3px;border-radius:2px;white-space:nowrap;">NTA: Vorb. ab {{ $ntaStart }}</span>
                                @endif
                            </td>
                            <td style="font-size:8pt;">
                                P:&nbsp;{{ $se->examiner?->shortcode ?? '–' }}
                                &nbsp;&nbsp;
                                @if($se->assessor)
                                    B:&nbsp;{{ $se->assessor->shortcode }}
                                @else
                                    <span class="no-assessor">⚠ kein B.</span>
                                @endif
                            </td>
                            <td style="text-align:center;">
                                @if($asgn->examRoom)
                                    <span class="exam-room-badge">{{ $asgn->examRoom->room_number }}</span>
                                @else<span style="color:#d1d5db;">–</span>@endif
                            </td>
                            <td class="check-cell">☐</td>
                            <td style="font-size:7.5pt;color:#9ca3af;">&nbsp;</td>
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    @endif

    {{-- Aufsicht-Unterschriftsbereich --}}
    <div class="supervision-area">
        <div class="supervision-title">Bestätigung der Aufsicht führenden Lehrkraft</div>
        <div class="sig-row">
            <div class="sig-col">
                <div class="sig-label-top">Name der Aufsicht (in Druckbuchstaben)</div>
                <div class="sig-line"></div>
                <div class="sig-label-bottom">&nbsp;</div>
            </div>
            <div class="sig-col">
                <div class="sig-label-top">Unterschrift</div>
                <div class="sig-line"></div>
                <div class="sig-label-bottom">&nbsp;</div>
            </div>
            <div class="sig-col" style="flex:0 0 110px;">
                <div class="sig-label-top">Datum / Uhrzeit</div>
                <div class="sig-line"></div>
                <div class="sig-label-bottom">&nbsp;</div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        Vorbereitungsraum-Liste {{ $examPeriod->name }}
        @if($school) · {{ $school->name }}@endif
        · Seite {{ $loop->index + 1 }} / {{ count($pages) }}
    </div>

</div>
@endforeach
</body>
</html>
