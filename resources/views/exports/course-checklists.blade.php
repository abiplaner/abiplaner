<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kurslisten zur Endkontrolle</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #222;
            margin: 0;
        }
        .day-header {
            background: #1e3a5f;
            color: #fff;
            padding: 7px 10px;
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .day-meta {
            font-size: 8.5pt;
            color: #555;
            margin-bottom: 14px;
        }
        .course-block {
            page-break-inside: avoid;
            margin-bottom: 18px;
            border: 1px solid #d1d5db;
        }
        .course-header {
            background: #374151;
            color: #fff;
            padding: 5px 10px;
            font-size: 10pt;
            font-weight: bold;
        }
        .course-meta {
            background: #f3f4f6;
            padding: 4px 10px;
            font-size: 8pt;
            color: #374151;
            border-bottom: 1px solid #d1d5db;
        }
        .course-meta span { margin-right: 18px; }
        .no-room { color: #b91c1c; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #e5e7eb;
            color: #374151;
            padding: 4px 8px;
            text-align: left;
            font-size: 8pt;
            font-weight: bold;
            border-bottom: 1px solid #d1d5db;
        }
        tbody td {
            padding: 4px 8px;
            font-size: 8.5pt;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        .checkbox-cell { width: 22px; text-align: center; }
        .check-box {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1.5px solid #374151;
        }
        .nta-badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
            padding: 0 4px;
            font-size: 7pt;
            margin-left: 4px;
        }
        .footer { margin-top: 10px; font-size: 7.5pt; color: #9ca3af; text-align: right; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

@php $firstDay = true; @endphp

@foreach($dateGroups as $dayData)
    @if(!$firstDay)<div class="page-break"></div>@endif
    @php $firstDay = false; @endphp

    <div class="day-header">
        {{ \Carbon\Carbon::parse($dayData['date'])->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}
    </div>
    <p class="day-meta">
        @foreach($dayData['slots'] as $slotLabel)
            {{ $slotLabel }}@if(!$loop->last) &nbsp;|&nbsp; @endif
        @endforeach
    </p>

    @foreach($dayData['courses'] as $courseData)
        <div class="course-block">
            <div class="course-header">
                {{ $courseData['course_code'] }}
                &nbsp;–&nbsp;
                {{ $courseData['subject_name'] }}
                <span style="font-weight:normal;font-size:8.5pt;">({{ $courseData['level_label'] }})</span>
            </div>
            <div class="course-meta">
                <span><strong>Erstprüfer:</strong> {{ $courseData['examiner'] }}</span>
                <span><strong>Zweitkorrektor:</strong> {{ $courseData['second_corrector'] }}</span>
                <span><strong>Raum:</strong>
                    @if($courseData['room'] === '–')
                        <span class="no-room">Kein Raum zugewiesen</span>
                    @else
                        {{ $courseData['room'] }}
                    @endif
                </span>
                <span><strong>Prüfungszeit:</strong> {{ $courseData['start_time'] }}–{{ $courseData['end_time'] }} Uhr</span>
                <span><strong>Schüler:</strong> {{ count($courseData['students']) }}</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell"><span class="check-box"></span></th>
                        <th style="width:5%">Nr.</th>
                        <th style="width:42%">Nachname</th>
                        <th style="width:38%">Vorname</th>
                        <th style="width:15%">Bemerkung</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($courseData['students'] as $i => $student)
                        <tr>
                            <td class="checkbox-cell"><span class="check-box"></span></td>
                            <td>{{ $i + 1 }}.</td>
                            <td>
                                {{ $student['lastname'] }}
                                @if($student['nta'])<span class="nta-badge">NTA</span>@endif
                            </td>
                            <td>{{ $student['firstname'] }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

@endforeach

<p class="footer">abiplaner &middot; Endkontrolle Unterlagen &middot; Jahrgang {{ $examYear }} &middot; {{ now()->format('d.m.Y') }}</p>

</body>
</html>
