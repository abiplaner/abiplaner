<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Schriftliche Prüfungen - {{ $examPeriod->name }}</title>
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
            color: #222;
        }
        .header {
            margin-bottom: 14px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }
        h1 {
            font-size: 14pt;
            margin: 0 0 3px 0;
        }
        .subtitle {
            font-size: 8pt;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        thead tr {
            background-color: #333;
            color: #fff;
        }
        thead th {
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
            white-space: nowrap;
        }
        thead th.center {
            text-align: center;
        }
        tbody tr:nth-child(even) {
            background-color: #f5f5f5;
        }
        tbody tr:hover {
            background-color: #eef3ff;
        }
        tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        tbody td.center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            background-color: #1a56db;
            color: #fff;
            border-radius: 10px;
            padding: 1px 8px;
            font-size: 8pt;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }
        .level {
            font-size: 7.5pt;
            color: #666;
            margin-top: 1px;
        }
        .not-assigned {
            color: #aaa;
            font-style: italic;
        }
        .second-corrector-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .second-corrector-list li + li {
            margin-top: 2px;
        }
        .summary {
            margin-top: 14px;
            padding: 6px 10px;
            background-color: #f0f4ff;
            border-left: 3px solid #1a56db;
            font-size: 8pt;
        }
        .summary strong {
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 5mm;
            left: 10mm;
            right: 10mm;
            font-size: 7pt;
            color: #999;
            text-align: right;
            border-top: 1px solid #ddd;
            padding-top: 3px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Schriftliche Prüfungen – Zweitkorrektoren</h1>
    <div class="subtitle">{{ $examPeriod->name }} | Erstellt am {{ $exportDate->format('d.m.Y H:i') }} Uhr</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 12%">Kurscode</th>
            <th style="width: 25%">Fach</th>
            <th style="width: 22%">Erstprüfer</th>
            <th style="width: 28%">Zweitkorrektor(en)</th>
            <th class="center" style="width: 13%">Schriftl. Prüfungen</th>
        </tr>
    </thead>
    <tbody>
        @foreach($courses as $course)
            <tr>
                <td>
                    <strong>{{ $course['course_code'] }}</strong>
                    @if($course['level'])
                        <div class="level">{{ $course['level_display'] }}</div>
                    @endif
                </td>
                <td>{{ $course['subject_name'] }}</td>
                <td>
                    @if($course['teacher_name'])
                        {{ $course['teacher_name'] }}
                        @if($course['teacher_shortcode'])
                            <span style="color:#888">({{ $course['teacher_shortcode'] }})</span>
                        @endif
                    @else
                        <span class="not-assigned">Nicht zugewiesen</span>
                    @endif
                </td>
                <td>
                    @if($course['second_correctors']->isEmpty())
                        <span class="not-assigned">Nicht zugeordnet</span>
                    @else
                        <ul class="second-corrector-list">
                            @foreach($course['second_correctors'] as $sc)
                                <li>{{ $sc['name'] }} <span style="color:#888">({{ $sc['shortcode'] }})</span></li>
                            @endforeach
                        </ul>
                    @endif
                </td>
                <td class="center">
                    <span class="badge">{{ $course['written_count'] }}</span>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <strong>Gesamt:</strong> {{ $courses->count() }} Kurse mit schriftlichen Prüfungen |
    <strong>Schriftliche Prüfungen gesamt:</strong> {{ $courses->sum('written_count') }}
</div>

<div class="footer">
    Seite <span class="pagenum"></span>
</div>

</body>
</html>
