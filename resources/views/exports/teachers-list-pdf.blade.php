<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Lehrerliste - {{ $examPeriod->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7pt;
            line-height: 1.15;
            color: #333;
        }
        
        h1 {
            font-size: 12pt;
            margin-bottom: 2px;
            color: #1a1a1a;
        }
        
        h2 {
            font-size: 10pt;
            margin-top: 8px;
            margin-bottom: 4px;
            color: #2563eb;
            border-bottom: 1px solid #2563eb;
            padding-bottom: 1px;
        }
        
        h3 {
            font-size: 8pt;
            margin-top: 6px;
            margin-bottom: 3px;
            color: #4b5563;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 6px;
        }
        
        .subtitle {
            font-size: 7pt;
            color: #666;
            margin-top: 2px;
        }
        
        .teacher-section {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        
        .teacher-header {
            background-color: #f3f4f6;
            padding: 3px 5px;
            margin-bottom: 3px;
            border-left: 3px solid #2563eb;
        }
        
        .teacher-name {
            font-size: 9pt;
            font-weight: bold;
            color: #1a1a1a;
        }
        
        .teacher-code {
            font-size: 7pt;
            color: #666;
            margin-left: 6px;
        }
        
        .stats {
            font-size: 6.5pt;
            color: #666;
            margin-top: 1px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 7pt;
        }
        
        th {
            background-color: #e5e7eb;
            color: #1f2937;
            padding: 2px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #d1d5db;
        }
        
        td {
            padding: 1px 4px;
            border: 1px solid #e5e7eb;
        }
        
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .date-cell {
            white-space: nowrap;
            width: 70px;
        }
        
        .time-cell {
            white-space: nowrap;
            width: 38px;
        }
        
        .room-cell {
            white-space: nowrap;
            width: 30px;
            text-align: center;
        }
        
        .subject-cell {
            width: 70px;
        }
        
        .role-examiner {
            color: #dc2626;
            font-weight: bold;
        }
        
        .role-assessor {
            color: #2563eb;
        }
        
        .no-exams {
            color: #6b7280;
            font-style: italic;
            padding: 10px;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
        }
        
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Lehrerliste - Prüfungsplanung</h1>
        <div class="subtitle">{{ $examPeriod->name }}{{ $school ? ' - ' . $school->name : '' }}</div>
        <div class="subtitle">
            Woche 1: {{ $examPeriod->week_1_start->locale('de')->translatedFormat('D, d.m.Y') }} - {{ $examPeriod->week_1_end->locale('de')->translatedFormat('D, d.m.Y') }} | 
            Woche 2: {{ $examPeriod->week_2_start->locale('de')->translatedFormat('D, d.m.Y') }} - {{ $examPeriod->week_2_end->locale('de')->translatedFormat('D, d.m.Y') }}
        </div>
    </div>

    @foreach($teachers as $teacher)
        <div class="teacher-section">
            <div class="teacher-header">
                <div>
                    <span class="teacher-name">{{ $teacher->full_name }}</span>
                    <span class="teacher-code">({{ $teacher->shortcode }})</span>
                </div>
                <div class="stats">
                    Als Prüfer: {{ $teacher->examsAsExaminer->count() }} | 
                    Als Beisitzer: {{ $teacher->examsAsAssessor->count() }} | 
                    Gesamt: {{ $teacher->totalExams }}
                </div>
            </div>

            @if($teacher->examsAsExaminer->count() > 0)
                <h3>Als Prüfer ({{ $teacher->examsAsExaminer->count() }})</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="date-cell">Datum</th>
                            <th class="time-cell">Zeit</th>
                            <th>Schüler</th>
                            <th class="subject-cell">Fach</th>
                            <th>Beisitzer</th>
                            <th class="room-cell">Raum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teacher->examsAsExaminer as $assignment)
                            <tr>
                                <td class="date-cell">{{ $assignment->timeSlot?->date?->locale('de')->translatedFormat('D, d.m.Y') ?? '—' }}</td>
                                <td class="time-cell">{{ $assignment->timeSlot?->exam_start_time ?? '—' }}</td>
                                <td>{{ $assignment->studentExam->getStudentName() }} <span style="color:#6b7280">({{ $assignment->studentExam->getSubjectLabel() }})</span></td>
                                <td class="subject-cell">{{ $assignment->studentExam->course?->subject?->code ?? '—' }}</td>
                                <td>{{ $assignment->studentExam->assessor ? $assignment->studentExam->assessor->shortcode : '—' }}</td>
                                <td class="room-cell">{{ $assignment->examRoom?->room_number ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($teacher->examsAsAssessor->count() > 0)
                <h3>Als Beisitzer ({{ $teacher->examsAsAssessor->count() }})</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="date-cell">Datum</th>
                            <th class="time-cell">Zeit</th>
                            <th>Schüler</th>
                            <th class="subject-cell">Fach</th>
                            <th>Prüfer</th>
                            <th class="room-cell">Raum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teacher->examsAsAssessor as $assignment)
                            <tr>
                                <td class="date-cell">{{ $assignment->timeSlot?->date?->locale('de')->translatedFormat('D, d.m.Y') ?? '—' }}</td>
                                <td class="time-cell">{{ $assignment->timeSlot?->exam_start_time ?? '—' }}</td>
                                <td>{{ $assignment->studentExam->getStudentName() }} <span style="color:#6b7280">({{ $assignment->studentExam->getSubjectLabel() }})</span></td>
                                <td class="subject-cell">{{ $assignment->studentExam->course?->subject?->code ?? '—' }}</td>
                                <td>{{ $assignment->studentExam->examiner?->shortcode ?? '—' }}</td>
                                <td class="room-cell">{{ $assignment->examRoom?->room_number ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    @if($teachers->count() === 0)
        <div class="no-exams">
            Keine Prüfungen geplant.
        </div>
    @endif

    <div class="footer">
        Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr | Seite <span class="page-number"></span>
    </div>
</body>
</html>
