@php
    $weekdays = ['Monday'=>'Montag','Tuesday'=>'Dienstag','Wednesday'=>'Mittwoch',
                 'Thursday'=>'Donnerstag','Friday'=>'Freitag','Saturday'=>'Samstag','Sunday'=>'Sonntag'];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Abwesenheiten Lehrkräfte</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            margin: 20px;
            color: #1a1a1a;
        }
        h1 {
            font-size: 15pt;
            margin-bottom: 4px;
            border-bottom: 2px solid #374151;
            padding-bottom: 6px;
        }
        .meta {
            font-size: 9pt;
            color: #6b7280;
            margin-bottom: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background-color: #374151;
            color: #fff;
            text-align: left;
            padding: 6px 8px;
            font-size: 9pt;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        tbody td {
            padding: 5px 8px;
            font-size: 9.5pt;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .teacher-col {
            font-weight: bold;
            white-space: nowrap;
        }
        .date-col {
            white-space: nowrap;
        }
        .empty {
            font-style: italic;
            color: #9ca3af;
            padding: 20px 8px;
        }
        .footer {
            margin-top: 20px;
            font-size: 8pt;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
        }
    </style>
</head>
<body>

<h1>Abwesenheiten Lehrkräfte – Kolloquiumsplanung</h1>
<div class="meta">
    @if($activeExamPeriod)
        Prüfungszeitraum: <strong>{{ $activeExamPeriod->name }} ({{ $activeExamPeriod->year }})</strong>
        @if($activeExamPeriod->week_1_start)
            &nbsp;·&nbsp; Woche 1: {{ $activeExamPeriod->week_1_start->format('d.m.') }}–{{ $activeExamPeriod->week_1_end->format('d.m.Y') }}
        @endif
        @if($activeExamPeriod->week_2_start)
            &nbsp;·&nbsp; Woche 2: {{ $activeExamPeriod->week_2_start->format('d.m.') }}–{{ $activeExamPeriod->week_2_end->format('d.m.Y') }}
        @endif
    @else
        Kein Prüfungszeitraum gewählt
    @endif
    &nbsp;·&nbsp; Erstellt: {{ now()->format('d.m.Y') }}
</div>

@if($rows->isEmpty())
    <p class="empty">Keine Abwesenheiten für diesen Prüfungszeitraum eingetragen.</p>
@else
    <table>
        <thead>
            <tr>
                <th style="width:12%">Kürzel</th>
                <th style="width:28%">Lehrkraft</th>
                <th style="width:18%">Datum</th>
                <th style="width:12%">Wochentag</th>
                <th>Grund</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $u)
                <tr>
                    <td class="teacher-col">{{ $u->teacher->shortcode ?? '–' }}</td>
                    <td>{{ $u->teacher ? $u->teacher->lastname . ', ' . $u->teacher->firstname : '–' }}</td>
                    <td class="date-col">{{ $u->date->format('d.m.Y') }}</td>
                    <td>{{ $weekdays[$u->date->format('l')] ?? $u->date->format('l') }}</td>
                    <td>{{ $u->reason ?: '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="footer">
        {{ $rows->count() }} Abwesenheit(en) von
        {{ $rows->pluck('teacher_id')->unique()->count() }} Lehrkraft(en)
    </div>
@endif

</body>
</html>
