<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Prüfungszimmerplan – {{ $examPeriod->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #1f2937;
        }

        /* ── Seitenumbruch je Raum-Tag ─────────── */
        .page {
            page-break-before: always;
            padding: 18px 20px 14px;
        }
        .page:first-child {
            page-break-before: avoid;
        }

        /* ── Kopfzeile ─────────────────────────── */
        .header {
            border-bottom: 3px solid #1e3a5f;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .school-name {
            font-size: 9pt;
            color: #6b7280;
        }
        .plan-label {
            font-size: 8pt;
            color: #9ca3af;
            text-align: right;
        }
        .room-title {
            font-size: 16pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-top: 4px;
        }
        .date-line {
            font-size: 11pt;
            color: #374151;
            margin-top: 2px;
        }

        /* ── Raum-Info-Badges ──────────────────── */
        .room-info {
            margin-bottom: 12px;
            font-size: 8.5pt;
            color: #374151;
        }
        .badge {
            display: inline-block;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            padding: 1px 7px;
            border-radius: 10px;
            margin-right: 6px;
            font-size: 8pt;
        }

        /* ── Prüfungstabelle ───────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        thead th {
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #374151;
        }
        thead th.center { text-align: center; }

        tbody tr:nth-child(even) td { background-color: #f8fafc; }
        tbody tr:nth-child(odd)  td { background-color: #ffffff; }

        tbody td {
            padding: 8px 8px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }
        td.time-cell {
            background-color: #eff6ff !important;
            font-weight: bold;
            font-size: 8.5pt;
            color: #1e3a5f;
            white-space: nowrap;
            text-align: center;
            width: 80px;
        }
        td.time-sub {
            font-size: 7.5pt;
            font-weight: normal;
            color: #6b7280;
        }
        .student-name {
            font-weight: bold;
            font-size: 10pt;
        }
        .subject-line {
            font-size: 8.5pt;
            color: #374151;
            margin-top: 2px;
        }
        .teacher-block {
            margin-top: 5px;
            font-size: 8.5pt;
            color: #4b5563;
            border-top: 1px dashed #e5e7eb;
            padding-top: 4px;
        }
        .teacher-label {
            font-size: 7.5pt;
            color: #9ca3af;
            display: inline-block;
            width: 55px;
        }
        .no-assessor {
            color: #ea580c;
            font-weight: bold;
        }
        .external-badge {
            font-size: 7pt;
            background-color: #ede9fe;
            color: #7c3aed;
            padding: 0 4px;
            border-radius: 3px;
            margin-left: 4px;
        }
        td.check-cell {
            text-align: center;
            width: 36px;
            font-size: 14pt;
            color: #d1d5db;
        }
        td.notes-cell {
            width: 130px;
            font-size: 7.5pt;
            color: #9ca3af;
        }

        /* ── Unterschrift-Bereich ──────────────── */
        .signature-area {
            margin-top: 20px;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
        }
        .signature-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: #374151;
            margin-bottom: 10px;
        }
        .signature-grid {
            width: 100%;
        }
        .sig-box {
            display: inline-block;
            width: 48%;
            vertical-align: top;
        }
        .sig-line {
            border-bottom: 1px solid #374151;
            height: 28px;
            margin-bottom: 3px;
        }
        .sig-label {
            font-size: 7.5pt;
            color: #6b7280;
        }
        .page-footer {
            margin-top: 10px;
            font-size: 7pt;
            color: #9ca3af;
            text-align: right;
        }
    </style>
</head>
<body>
@foreach($pages as $page)
<div class="page">

    {{-- ── Kopfzeile ── --}}
    <div class="header">
        <div class="header-top">
            <div>
                @if($school)
                    <div class="school-name">{{ $school->name }}</div>
                @endif
                <div class="room-title">Prüfungsraum {{ $page['room']->room_number }}</div>
                <div class="date-line">
                    {{ $page['date']->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                    &nbsp;·&nbsp; Woche {{ $page['week'] }}
                </div>
            </div>
            <div class="plan-label">
                Kolloquiumsplan {{ $examPeriod->name }}<br>
                Stand: {{ now()->format('d.m.Y H:i') }} Uhr
            </div>
        </div>
    </div>

    {{-- ── Nebenräume-Info ── --}}
    @if($page['prepRooms']->isNotEmpty() || $page['waitRooms']->isNotEmpty())
        <div class="room-info">
            @foreach($page['prepRooms'] as $r)
                <span class="badge">📚 {{ $r->room_number }} (Vorb.)</span>
            @endforeach
            @foreach($page['waitRooms'] as $r)
                <span class="badge">⏳ {{ $r->room_number }} (Warten)</span>
            @endforeach
        </div>
    @endif

    {{-- ── Prüfungstabelle ── --}}
    <table>
        <thead>
            <tr>
                <th class="center" style="width:80px;">Vorb.-Zeit</th>
                <th class="center" style="width:80px;">Prüfungszeit</th>
                <th>Prüfling</th>
                <th style="width:130px;">Prüfer / Beisitzer</th>
                <th class="center" style="width:36px;">✓</th>
                <th style="width:130px;">Anmerkungen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($page['slots'] as $slotData)
                @php
                    $slot = $slotData['slot'];
                    $asgn = $slotData['assignment'];
                @endphp
                <tr>
                    {{-- Vorbereitungszeit --}}
                    <td class="time-cell">
                        {{ $slot->prep_start_time }}<br>
                        <span class="time-sub">bis {{ $slot->prep_end_time }}</span>
                    </td>
                    {{-- Prüfungszeit --}}
                    <td class="time-cell">
                        {{ $slot->exam_start_time }}<br>
                        <span class="time-sub">bis {{ $slot->exam_end_time }}</span>
                    </td>
                    {{-- Prüfling & Fach --}}
                    <td>
                        @if($asgn)
                            @php $se = $asgn->studentExam; @endphp
                            <div class="student-name">
                                {{ $se->getStudentName() }}
                                @if($se->isExternalApplicant())
                                    <span class="external-badge">extern</span>
                                @endif
                            </div>
                            <div class="subject-line">
                                @if($se->isExternalApplicant())
                                    {{ $se->getSubjectName() ?? '–' }}
                                @else
                                    {{ $se->course->subject->name ?? '–' }}
                                    ({{ $se->course->course_code ?? '–' }})
                                @endif
                            </div>
                        @else
                            <span style="color:#d1d5db;">–</span>
                        @endif
                    </td>
                    {{-- Prüfer & Beisitzer --}}
                    <td>
                        @if($asgn)
                            @php $se = $asgn->studentExam; @endphp
                            <div class="teacher-block">
                                <div>
                                    <span class="teacher-label">Prüfer:</span>
                                    @if($se->examiner)
                                        {{ $se->examiner->lastname }}, {{ $se->examiner->firstname }}
                                        <span style="color:#6b7280; font-size:7.5pt;">({{ $se->examiner->shortcode }})</span>
                                    @else
                                        <span style="color:#9ca3af;">–</span>
                                    @endif
                                </div>
                                <div style="margin-top:3px;">
                                    <span class="teacher-label">Beisitzer:</span>
                                    @if($se->assessor)
                                        {{ $se->assessor->lastname }}, {{ $se->assessor->firstname }}
                                        <span style="color:#6b7280; font-size:7.5pt;">({{ $se->assessor->shortcode }})</span>
                                    @else
                                        <span class="no-assessor">⚠ nicht besetzt</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </td>
                    {{-- Abhaken --}}
                    <td class="check-cell">☐</td>
                    {{-- Anmerkungen --}}
                    <td class="notes-cell">&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-footer">
        Kolloquiumsplan {{ $examPeriod->name }} · {{ $school?->name ?? '' }} · Seite {{ $loop->index + 1 }} / {{ count($pages) }}
    </div>

</div>
@endforeach
</body>
</html>
