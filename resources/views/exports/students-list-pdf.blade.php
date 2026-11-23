<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Schülerliste - {{ $examPeriod->name }}</title>
    @include('exports.partials.pdf-base-styles')
    <style>
        /* View-spezifisch: kompaktere Schrift + nicht umbrechende Zellen */
        body { font-size: 7pt; }
        table.pdf-table { font-size: 7pt; }
        table.pdf-table tbody { page-break-inside: avoid; }
        table.pdf-table tbody tr:first-child td { border-top: 1px solid #6b7280; }
        table.pdf-table tbody tr td { white-space: nowrap; }

        /* Wochen-Badges (zwei Prüfungswochen) */
        .week-badge {
            font-size: 6.5pt;
            padding: 0 3px;
            border-radius: 2px;
            font-weight: bold;
            color: #fff;
        }
        .week-1 { background-color: #1e40af; }   /* Akzent-Blau */
        .week-2 { background-color: #1d4ed8; }   /* helleres Blau */
    </style>
</head>
<body>
    <div class="pdf-header">
        <h1>Schülerliste – Mündliche Prüfungen</h1>
        <div class="pdf-subtitle">{{ $examPeriod->name }}{{ $school ? ' – ' . $school->name : '' }}</div>
        <div class="pdf-subtitle">
            Woche&nbsp;1: {{ $examPeriod->week_1_start->locale('de')->translatedFormat('D, d.m.Y') }} – {{ $examPeriod->week_1_end->locale('de')->translatedFormat('D, d.m.Y') }}
            &nbsp;|&nbsp;
            Woche&nbsp;2: {{ $examPeriod->week_2_start->locale('de')->translatedFormat('D, d.m.Y') }} – {{ $examPeriod->week_2_end->locale('de')->translatedFormat('D, d.m.Y') }}
        </div>
        <div class="pdf-subtitle">Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr</div>
    </div>

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width:20%">Schüler/-in</th>
                <th style="width:8%">Fach</th>
                <th style="width:13%">Datum</th>
                <th style="width:7%">Vorb.</th>
                <th style="width:7%">Prüfung</th>
                <th style="width:8%">Raum</th>
                <th style="width:16%">Prüfer</th>
                <th style="width:14%">Beisitzer</th>
                <th style="width:7%">Woche</th>
            </tr>
        </thead>

        {{-- Pro Schüler ein <tbody> mit page-break-inside: avoid --}}
        @forelse($students as $index => $student)
            @php
                $exams = $student->exams
                    ->filter(fn($se) => $se->exam_type === 'oral')
                    ->sortBy(fn($se) => $se->assignment?->timeSlot?->date?->format('Y-m-d H:i') ?? '9999')
                    ->values();
                $rowClass = $index % 2 === 0 ? '' : 'pdf-even';
            @endphp

            <tbody class="{{ $rowClass }}">
                @forelse($exams as $se)
                    <tr>
                        <td class="pdf-bold">{{ $loop->first ? $student->lastname . ', ' . mb_substr($student->firstname, 0, 1) . '.' : '' }}</td>
                        <td>{{ $se->course?->subject?->code ?? '—' }}</td>
                        <td>{{ $se->assignment?->timeSlot?->date?->locale('de')->translatedFormat('D d.m.y') ?? '—' }}</td>
                        <td>
                            @php
                                $slot = $se->assignment?->timeSlot;
                                $prepDisplay = $slot ? \Carbon\Carbon::createFromTimeString($slot->prep_start_time)->format('H:i') : '—';
                                if ($slot && $student->disability_compensation_percent > 0) {
                                    $prepMin  = (strtotime($slot->prep_end_time) - strtotime($slot->prep_start_time)) / 60;
                                    $ntaMin   = $student->getCompensatedDuration((int)$prepMin);
                                    $prepDisplay = \Carbon\Carbon::createFromTimeString($slot->exam_start_time)->subMinutes($ntaMin)->format('H:i');
                                }
                            @endphp
                            {{ $prepDisplay }}
                        </td>
                        <td>{{ $se->assignment?->timeSlot?->exam_start_time ? \Carbon\Carbon::createFromTimeString($se->assignment->timeSlot->exam_start_time)->format('H:i') : '—' }}</td>
                        <td>{{ $se->assignment?->examRoom?->room_number ?? '—' }}</td>
                        <td>{{ $se->examiner ? $se->examiner->lastname . ', ' . mb_substr($se->examiner->firstname, 0, 1) . '.' : '—' }}</td>
                        <td>{{ $se->assessor ? $se->assessor->lastname . ', ' . mb_substr($se->assessor->firstname, 0, 1) . '.' : '—' }}</td>
                        <td style="text-align:center">
                            @if($se->assignment)
                                <span class="week-badge week-{{ $se->assignment->exam_week }}">W{{ $se->assignment->exam_week }}</span>
                            @else
                                <span class="pdf-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="pdf-bold">{{ $student->lastname }}, {{ mb_substr($student->firstname, 0, 1) }}.</td>
                        <td colspan="8" class="pdf-muted">Keine Prüfung eingetragen</td>
                    </tr>
                @endforelse
            </tbody>
        @empty
            <tbody>
                <tr><td colspan="9" class="pdf-muted" style="padding:12px">Keine Schüler vorhanden.</td></tr>
            </tbody>
        @endforelse
    </table>

</body>
</html>
