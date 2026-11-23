<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            background: #fff;
            margin: 15mm;
        }

        /* Seitenumbruch zwischen Wochen */
        .week-block { page-break-after: always; }
        .week-block:last-child { page-break-after: avoid; }

        /* Seitenkopf pro Woche */
        .week-header {
            border-bottom: 2pt solid #1d4ed8;
            margin-bottom: 5mm;
            padding-bottom: 3mm;
        }
        .week-header .school {
            font-size: 8.5pt;
            color: #4b5563;
        }
        .week-header .title {
            font-size: 13pt;
            font-weight: bold;
            color: #1e40af;
            margin-top: 1mm;
        }
        .week-header .subtitle {
            font-size: 8.5pt;
            color: #4b5563;
            margin-top: 0.5mm;
        }
        .week-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .week-header .meta {
            font-size: 8pt;
            color: #6b7280;
            text-align: right;
        }

        /* Matrix-Tabelle */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 0.5pt solid #cbd5e1;
            padding: 2mm 3mm;
            vertical-align: middle;
        }

        th.col-subject, td.col-subject {
            text-align: left;
        }
        th.col-day, td.col-day {
            text-align: center;
        }
        th.col-total, td.col-total {
            text-align: center;
            font-weight: bold;
        }

        thead tr th {
            background-color: #1d4ed8;
            color: #fff;
            font-size: 8.5pt;
            font-weight: bold;
        }
        thead tr th.col-subject { background-color: #1e40af; }
        thead tr th.col-total   { background-color: #2563eb; }

        .th-weekday {
            font-size: 7pt;
            font-weight: normal;
            opacity: 0.85;
        }

        tbody tr:nth-child(even) td { background-color: #f8fafc; }
        tbody tr:nth-child(odd)  td { background-color: #fff; }

        td.has-value {
            background-color: #dbeafe;
            font-weight: bold;
            color: #1e40af;
        }

        tfoot tr td {
            background-color: #1d4ed8;
            color: #fff;
            font-weight: bold;
            font-size: 9pt;
            border-top: 1.5pt solid #1e40af;
        }
        tfoot tr td.col-subject { background-color: #1e40af; }
    </style>
</head>
<body>

@php
    $deWeekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $byWeek = collect($dailyOverview)->groupBy('week');
@endphp

@foreach ([1, 2] as $week)
@php
    $weekDays = $byWeek->get($week, collect())->values();
    $weekDays = $weekDays->filter(fn($d) => $d['total'] > 0)->values();
    if ($weekDays->isEmpty()) { continue; }

    $allSubjects = $weekDays
        ->flatMap(fn($d) => array_keys($d['subjects']))
        ->unique()->sort()->values();

    $grandTotal = $weekDays->sum(fn($d) => $d['total']);

    // Datumsbereich der Woche
    $firstDate = \Carbon\Carbon::parse($weekDays->first()['date'])->locale('de')->isoFormat('D. MMMM');
    $lastDate  = \Carbon\Carbon::parse($weekDays->last()['date'])->locale('de')->isoFormat('D. MMMM YYYY');
@endphp

<div class="week-block">

    <div class="week-header">
        <div class="week-header-row">
            <div>
                <div class="school">{{ $school->name ?? '' }}</div>
                <div class="title">Kolloquium Abitur {{ $examPeriod->year }} &mdash; Woche {{ $week }}</div>
                <div class="subtitle">Prüfungen je Fach und Tag &bull; {{ $firstDate }} &ndash; {{ $lastDate }}</div>
            </div>
            <div class="meta">Erstellt: {{ now()->format('d.m.Y') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-subject" style="width: 38%">Fach</th>
                @foreach ($weekDays as $day)
                @php
                    $dt      = \Carbon\Carbon::parse($day['date']);
                    $wdShort = $deWeekdays[$dt->dayOfWeek];
                @endphp
                <th class="col-day" style="width: {{ round(52 / $weekDays->count(), 1) }}%">
                    {{ $dt->format('d.m.') }}<br>
                    <span class="th-weekday">{{ $wdShort }}</span>
                </th>
                @endforeach
                <th class="col-total" style="width: 10%">Σ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allSubjects as $subject)
            @php $rowTotal = $weekDays->sum(fn($d) => $d['subjects'][$subject] ?? 0); @endphp
            <tr>
                <td class="col-subject">{{ $subject }}</td>
                @foreach ($weekDays as $day)
                @php $val = $day['subjects'][$subject] ?? 0; @endphp
                <td class="col-day {{ $val > 0 ? 'has-value' : '' }}">
                    {!! $val > 0 ? $val : '<span style="color:#cbd5e1">&ndash;</span>' !!}
                </td>
                @endforeach
                <td class="col-total">{{ $rowTotal }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="col-subject">Gesamt</td>
                @foreach ($weekDays as $day)
                    <td class="col-day">{{ $day['total'] }}</td>
                @endforeach
                <td class="col-total">{{ $grandTotal }}</td>
            </tr>
        </tfoot>
    </table>

</div>

@endforeach

</body>
</html>
