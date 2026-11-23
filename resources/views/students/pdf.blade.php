<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Schülerliste - {{ $examPeriod->name }}</title>
    <style>
        @page {
            margin: 100px 30px 50px 30px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 5px;
            margin-top: 0;
        }
        h2 {
            font-size: 14px;
            margin-top: 0;
            margin-bottom: 20px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #4A5568;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }
        td {
            padding: 6px 5px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .exam-info {
            margin-bottom: 3px;
        }
        .course {
            font-weight: bold;
        }
        .detail {
            color: #666;
            font-size: 8px;
        }
        .status-planned {
            color: #10b981;
            font-size: 8px;
        }
        .status-unplanned {
            color: #f59e0b;
            font-size: 8px;
        }
        .disability-compensation {
            color: #dc2626;
            font-size: 8px;
            font-weight: bold;
            margin-top: 2px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
        header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 60px;
        }
    </style>
</head>
<body>
    <header>
        <h1>Schülerliste</h1>
        <h2>{{ $examPeriod->name }} - {{ $school->name }}</h2>
    </header>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Nr.</th>
                <th style="width: 20%;">Name</th>
                @php
                    $maxExams = collect($students)->max(function($student) {
                        return count($student['exams']);
                    });
                    $examWidth = 75 / max($maxExams, 1);
                @endphp
                @for($i = 0; $i < $maxExams; $i++)
                    <th style="width: {{ $examWidth }}%;">Pruefung {{ $i + 1 }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach($students as $student)
                <tr>
                    <td>{{ $student['external_id'] }}</td>
                    <td>
                        {{ $student['full_name'] }}
                        @if(isset($student['disability_compensation_percent']) && $student['disability_compensation_percent'])
                            <div class="disability-compensation">
                                Nachteilsausgleich: +{{ $student['disability_compensation_percent'] }}%
                            </div>
                        @endif
                    </td>
                    
                    @for($i = 0; $i < $maxExams; $i++)
                        @php
                            $exam = $student['exams'][$i] ?? null;
                        @endphp
                        <td>
                            @if($exam)
                                <div class="exam-info">
                                    <span class="course">
                                        {{ $exam['subject'] }}
                                        @if($exam['type'] === 'oral')
                                            <strong>[M]</strong>
                                        @else
                                            <strong>[S]</strong>
                                        @endif
                                    </span>
                                </div>
                                @if($exam['course_code'])
                                    <div class="detail">
                                        Kurs: {{ $exam['course_code'] }}
                                    </div>
                                @endif
                                @if($exam['type'] === 'oral' && $exam['examiner'] !== '-')
                                    <div class="detail">
                                        Pruefer: {{ $exam['examiner'] }}
                                    </div>
                                @endif
                                @if($exam['is_assigned'])
                                    <div class="status-planned">
                                        [X] {{ $exam['date_formatted'] }} 
                                        um {{ substr($exam['time'], 0, 5) }} Uhr
                                    </div>
                                    <div class="detail">
                                        Raum: {{ $exam['room'] }}
                                        @if($exam['type'] === 'written' && isset($exam['seat']))
                                            | Platz {{ $exam['seat'] }}
                                        @endif
                                    </div>
                                @else
                                    <div class="status-unplanned">[ ] Nicht geplant</div>
                                @endif
                            @else
                                <span class="detail">-</span>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr
    </div>
</body>
</html>
