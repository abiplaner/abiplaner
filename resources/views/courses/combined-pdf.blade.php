<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fachausschüsse & Prüfungsstatistik – {{ $examPeriod->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 12mm 18mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; color: #222; }

        /* ---- Gemeinsame Stile ---- */
        h1 { font-size: 13pt; margin: 0 0 2px 0; }
        .subtitle { font-size: 8pt; color: #666; margin-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        thead th {
            background-color: #1F3864; color: #fff;
            padding: 3px 6px; text-align: left; font-weight: bold;
        }
        thead th.center { text-align: center; }
        tbody tr:nth-child(even) { background-color: #f5f5f5; }
        tbody td { padding: 2px 6px; border-bottom: 1px solid #e0e0e0; vertical-align: middle; line-height: 1.2; }
        tbody td.center { text-align: center; }
        tbody td.right { text-align: right; }

        .badge {
            display: inline-block; background-color: #1F3864; color: #fff;
            border-radius: 10px; padding: 1px 7px; font-size: 7.5pt;
            font-weight: bold; min-width: 18px; text-align: center;
        }
        .not-assigned { color: #aaa; font-style: italic; }

        .summary {
            margin-top: 8px; padding: 4px 8px;
            background-color: #f0f4ff; border-left: 3px solid #1F3864; font-size: 8pt;
        }

        /* ---- Seitenumbruch ---- */
        .page-break { page-break-after: always; }

        /* ---- Abschnitts-Header ---- */
        .section-header {
            border-bottom: 2px solid #1F3864; padding-bottom: 4px; margin-bottom: 8px;
        }

        /* ---- Statistik-Tabelle ---- */
        .stats-total td { font-weight: bold; background-color: #e9e9e9 !important; border-top: 1.5px solid #999; }
    </style>
</head>
<body>

{{-- =========================================================
     SEITE 1: Fachausschüsse Schriftliche Abiturprüfungen
     ========================================================= --}}

<div class="section-header">
    <h1>Fachausschüsse – Schriftliche Abiturprüfungen</h1>
    <div class="subtitle">{{ $examPeriod->name }} &nbsp;|&nbsp; Stand: {{ $exportDate->format('d.m.Y') }}</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:14%">Kurs</th>
            <th class="center" style="width:10%">Anzahl</th>
            <th style="width:25%">Erstprüfer</th>
            <th>Zweitkorrektor</th>
        </tr>
    </thead>
    <tbody>
        @foreach($writtenCourses as $course)
            <tr>
                <td><strong>{{ $course['course_code'] }}</strong></td>
                <td class="center"><span class="badge">{{ $course['written_count'] }}</span></td>
                <td>
                    @if($course['teacher_name'])
                        {{ $course['teacher_shortcode'] }}
                    @else
                        <span class="not-assigned">---</span>
                    @endif
                </td>
                <td>
                    @if($course['second_correctors']->isNotEmpty())
                        {{ $course['second_correctors']->map(fn($sc) => $sc['shortcode'] . ($course['second_correctors']->count() > 1 ? ' ('.$sc['count'].')' : ''))->implode(', ') }}
                    @else
                        <span class="not-assigned">---</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <strong>Gesamt:</strong> {{ $writtenCourses->count() }} Kurse &nbsp;|&nbsp;
    <strong>Schriftliche Prüfungen:</strong> {{ $writtenCourses->sum('written_count') }}
</div>

<div class="page-break"></div>

{{-- =========================================================
     SEITE 2: Fachausschüsse Kolloquien
     ========================================================= --}}

<div class="section-header">
    <h1>Fachausschüsse – Kolloquien</h1>
    <div class="subtitle">{{ $examPeriod->name }} &nbsp;|&nbsp; Stand: {{ $exportDate->format('d.m.Y') }}</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:14%">Kurs</th>
            <th class="center" style="width:10%">Anzahl</th>
            <th style="width:25%">Erstprüfer</th>
            <th>Beisitzer</th>
        </tr>
    </thead>
    <tbody>
        @foreach($oralCourses as $course)
            <tr>
                <td><strong>{{ $course['course_code'] }}</strong></td>
                <td class="center"><span class="badge">{{ $course['oral_count'] }}</span></td>
                <td>{{ $course['teacher_shortcode'] ?: '---' }}</td>
                <td>
                    @if(!empty($course['assessor_str']))
                        {{ $course['assessor_str'] }}
                    @else
                        <span class="not-assigned">---</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <strong>Gesamt:</strong> {{ $oralCourses->count() }} Kurse &nbsp;|&nbsp;
    <strong>Kolloquien:</strong> {{ $oralCourses->sum('oral_count') }}
</div>

<div class="page-break"></div>

{{-- =========================================================
     SEITE 3: Prüfungsstatistik
     ========================================================= --}}

<div class="section-header">
    <h1>Prüfungsstatistik</h1>
    <div class="subtitle">{{ $examPeriod->name }} &nbsp;|&nbsp; Stand: {{ $exportDate->format('d.m.Y') }}</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:28%">Name</th>
            <th class="center" style="width:18%">Schriftl. Erstprüfer</th>
            <th class="center" style="width:17%">Zweitkorrektor</th>
            <th class="center" style="width:16%">Koll. Erstprüfer</th>
            <th class="center" style="width:12%">Beisitzer</th>
            <th class="center" style="width:9%">Sum.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($stats as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="center">{{ $row['written_first'] ?: '' }}</td>
                <td class="center">{{ $row['written_second'] ?: '' }}</td>
                <td class="center">{{ $row['oral_first'] ?: '' }}</td>
                <td class="center">{{ $row['oral_assessor'] ?: '' }}</td>
                <td class="center"><strong>{{ $row['total'] }}</strong></td>
            </tr>
        @endforeach
        <tr class="stats-total">
            <td>Gesamt</td>
            <td class="center">{{ $stats->sum('written_first') }}</td>
            <td class="center">{{ $stats->sum('written_second') }}</td>
            <td class="center">{{ $stats->sum('oral_first') }}</td>
            <td class="center">{{ $stats->sum('oral_assessor') }}</td>
            <td class="center">{{ $stats->sum('total') }}</td>
        </tr>
    </tbody>
</table>

</body>
</html>
