<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kolloquiumsliste - {{ $examPeriod->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            line-height: 1.3;
        }
        .header {
            margin-bottom: 12px;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
        }
        h1 {
            font-size: 14pt;
            margin: 0 0 3px 0;
        }
        .school-info {
            font-size: 8pt;
            color: #666;
        }
        .course {
            page-break-inside: avoid;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 3px;
            overflow: hidden;
        }
        .course-header {
            background-color: #f5f5f5;
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }
        .course-title {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 2px;
        }
        .course-meta {
            font-size: 8pt;
            color: #666;
        }
        .course-body {
            padding: 8px;
        }
        .stats {
            margin-bottom: 8px;
            padding: 4px 6px;
            background-color: #fafafa;
            border-radius: 2px;
            font-size: 8pt;
        }
        .stats-item {
            display: inline-block;
            margin-right: 15px;
        }
        .stats-label {
            font-weight: bold;
            color: #555;
        }
        .coupling-section {
            margin-bottom: 10px;
        }
        .coupling-header {
            background-color: #e3f2fd;
            padding: 4px 6px;
            font-weight: bold;
            font-size: 8pt;
            color: #1976d2;
            border-left: 3px solid #1976d2;
            margin-bottom: 4px;
        }
        .coupling-students {
            padding-left: 10px;
        }
        .student-row {
            padding: 3px 0;
            border-bottom: 1px dotted #eee;
            font-size: 8pt;
        }
        .student-row:last-child {
            border-bottom: none;
        }
        .student-name {
            font-weight: bold;
            color: #333;
        }
        .student-assessor {
            color: #666;
            font-size: 7pt;
            margin-left: 8px;
        }
        .uncoupled-section {
            margin-top: 8px;
        }
        .uncoupled-header {
            font-weight: bold;
            font-size: 8pt;
            color: #666;
            padding: 3px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 4px;
        }
        .footer {
            position: fixed;
            bottom: 5mm;
            left: 10mm;
            right: 10mm;
            font-size: 7pt;
            color: #999;
            text-align: right;
            border-top: 1px solid #eee;
            padding-top: 3px;
        }
        .no-students {
            text-align: center;
            padding: 15px;
            color: #999;
            font-style: italic;
            font-size: 8pt;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Kolloquiumsliste mit Kopplungen</h1>
    <div class="school-info">
        {{ $examPeriod->name }}
    </div>
</div>

@foreach($courses as $index => $course)
    <div class="course">
        <div class="course-header">
            <div class="course-title">
                {{ $course['course_code'] }} – {{ $course['subject_name'] }}
                @if($course['subject_code'])
                    ({{ $course['subject_code'] }})
                @endif
            </div>
            <div class="course-meta">
                Erstprüfer: {{ $course['teacher_name'] }} ({{ $course['teacher_shortcode'] }})
                @if($course['level'])
                    | Stufe: {{ $course['level'] }}
                @endif
            </div>
        </div>
        
        <div class="course-body">
            @if($course['student_count'] > 0)
                <div class="stats">
                    <span class="stats-item">
                        <span class="stats-label">Kolloquien gesamt:</span> {{ $course['student_count'] }}
                    </span>
                    @if($course['coupled_count'] > 0)
                        <span class="stats-item">
                            <span class="stats-label">Davon gekoppelt:</span> {{ $course['coupled_count'] }}
                        </span>
                        <span class="stats-item">
                            <span class="stats-label">Kopplungen:</span> {{ $course['couplings']->count() }}
                        </span>
                    @endif
                </div>

                @if($course['couplings']->isNotEmpty())
                    <div class="coupling-section">
                        @foreach($course['couplings'] as $couplingId => $coupledStudents)
                            <div style="margin-bottom: 8px;">
                                <div class="coupling-header">
                                    Kopplung {{ $loop->iteration }} ({{ $coupledStudents->count() }} Kolloquien)
                                </div>
                                <div class="coupling-students">
                                    @foreach($coupledStudents as $student)
                                        <div class="student-row">
                                            <span class="student-name">{{ $student['student_name'] }}</span>
                                            <span class="student-assessor">
                                                Beisitzer: {{ $student['assessor_shortcode'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @php
                    $uncoupledStudents = $course['students']->filter(fn($s) => !$s['is_coupled']);
                @endphp

                @if($uncoupledStudents->isNotEmpty())
                    <div class="uncoupled-section">
                        <div class="uncoupled-header">
                            Einzelne Kolloquien ({{ $uncoupledStudents->count() }})
                        </div>
                        @foreach($uncoupledStudents as $student)
                            <div class="student-row">
                                <span class="student-name">{{ $student['student_name'] }}</span>
                                <span class="student-assessor">
                                    Beisitzer: {{ $student['assessor_shortcode'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="no-students">Keine Kolloquien in diesem Kurs</div>
            @endif
        </div>
    </div>

    @if(($index + 1) % 3 == 0 && $index + 1 < $courses->count())
        <div class="page-break"></div>
    @endif
@endforeach

<div class="footer">
    Erstellt am {{ $exportDate->format('d.m.Y H:i') }} Uhr | Seite <span class="pagenum"></span>
</div>

</body>
</html>
