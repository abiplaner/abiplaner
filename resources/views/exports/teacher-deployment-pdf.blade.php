<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lehrereinsatz – Statistik</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; color: #111; margin: 0; padding: 10px; }
        h1   { font-size: 12px; margin: 0 0 2px 0; }
        .meta { font-size: 8px; color: #555; margin-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #1e3a5f; color: #fff;
            font-size: 8px; font-weight: bold;
            padding: 4px 6px; border: 1px solid #1e3a5f;
        }
        thead th.left  { text-align: left; }
        thead th.center { text-align: center; }

        tbody td {
            border: 1px solid #d1d5db;
            padding: 3px 6px;
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td { background: #f0f4ff; }
        tbody tr:nth-child(odd)  td { background: #ffffff; }

        tfoot td {
            border: 1px solid #9ca3af;
            padding: 4px 6px;
            font-weight: bold;
            background: #e5e7eb;
        }

        .col-name   { width: 28%; }
        .col-num    { width: 10%; text-align: center; }
        .col-total  { width: 10%; text-align: center; font-weight: bold; color: #3730a3; }

        .badge      { display: inline-block; min-width: 18px; text-align: center;
                      border-radius: 9px; padding: 1px 4px; font-size: 8px; font-weight: bold; }
        .b-blue     { background: #dbeafe; color: #1e40af; }
        .b-cyan     { background: #cffafe; color: #0e7490; }
        .b-purple   { background: #ede9fe; color: #5b21b6; }
        .b-pink     { background: #fce7f3; color: #9d174d; }
        .b-amber    { background: #fef3c7; color: #92400e; }
        .b-indigo   { background: #e0e7ff; color: #3730a3; font-weight: bold; }
        .zero       { color: #9ca3af; }
    </style>
</head>
<body>

<h1>Lehrereinsatz – Statistik</h1>
<div class="meta">Erstellt am {{ now()->locale('de')->isoFormat('DD.MM.YYYY, HH:mm') }}</div>

<table>
    <thead>
        <tr>
            <th class="col-name left">Lehrkraft</th>
            <th class="col-num center">Schriftl.<br>Erstkorr.</th>
            <th class="col-num center">Schriftl.<br>Zweitkorr.</th>
            <th class="col-num center">Koll.<br>Prüfer</th>
            <th class="col-num center">Koll.<br>Beisitzer</th>
            <th class="col-num center">Aufsichten</th>
            <th class="col-total center">Gesamt</th>
        </tr>
    </thead>
    <tbody>
    @foreach($stats as $row)
        @php
            /** @var \App\Models\Teacher $teacher */
            $teacher = $row['teacher'];
            $wc = (int) $row['written_courses'];
            $sc = (int) $row['second_corrections'];
            $oe = (int) $row['oral_examiner'];
            $oa = (int) $row['oral_assessor'];
            $sv = (int) $row['supervisions'];
            $tt = (int) $row['total'];
        @endphp
        <tr>
            <td class="col-name">
                {{ $teacher->lastname }}, {{ $teacher->firstname }}
                <span style="color:#6b7280; font-size:7.5px;">({{ $teacher->shortcode }})</span>
            </td>
            <td class="col-num">
                @if($wc > 0)<span class="badge b-blue">{{ $wc }}</span>@else<span class="zero">0</span>@endif
            </td>
            <td class="col-num">
                @if($sc > 0)<span class="badge b-cyan">{{ $sc }}</span>@else<span class="zero">0</span>@endif
            </td>
            <td class="col-num">
                @if($oe > 0)<span class="badge b-purple">{{ $oe }}</span>@else<span class="zero">0</span>@endif
            </td>
            <td class="col-num">
                @if($oa > 0)<span class="badge b-pink">{{ $oa }}</span>@else<span class="zero">0</span>@endif
            </td>
            <td class="col-num">
                @if($sv > 0)<span class="badge b-amber">{{ $sv }}</span>@else<span class="zero">0</span>@endif
            </td>
            <td class="col-total">
                @if($tt > 0)<span class="badge b-indigo">{{ $tt }}</span>@else<span class="zero">0</span>@endif
            </td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td class="col-name">Gesamt ({{ $stats->count() }} Lehrkräfte)</td>
            <td class="col-num">{{ (int) $stats->sum('written_courses') }}</td>
            <td class="col-num">{{ (int) $stats->sum('second_corrections') }}</td>
            <td class="col-num">{{ (int) $stats->sum('oral_examiner') }}</td>
            <td class="col-num">{{ (int) $stats->sum('oral_assessor') }}</td>
            <td class="col-num">{{ (int) $stats->sum('supervisions') }}</td>
            <td class="col-total">{{ (int) $stats->sum('total') }}</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
