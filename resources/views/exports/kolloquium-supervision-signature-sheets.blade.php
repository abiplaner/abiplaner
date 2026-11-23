<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Aufsicht Unterschriftsbögen – Kolloquium</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:10pt; color:#1a1a1a; }

        .page { width:100%; padding:14mm 14mm 12mm 14mm; page-break-after:always; }
        .page:last-child { page-break-after:avoid; }

        /* Kopfzeile */
        .page-header { border-bottom:2.5pt solid #1e40af; padding-bottom:6pt; margin-bottom:10pt; }
        .school-name { font-size:8.5pt; color:#555; margin-bottom:2pt; }
        .page-title  { font-size:15pt; font-weight:bold; color:#1e40af; letter-spacing:0.3pt; }

        /* Meta */
        .meta-grid { display:table; width:100%; margin-bottom:12pt; border-collapse:collapse; }
        .meta-row  { display:table-row; }
        .meta-cell { display:table-cell; width:50%; padding:3pt 0; vertical-align:top; }
        .meta-label { font-size:8pt; color:#666; text-transform:uppercase; letter-spacing:0.4pt; }
        .meta-value { font-size:11pt; font-weight:bold; color:#1a1a1a; }

        .divider { border:none; border-top:1pt solid #d1d5db; margin:6pt 0 10pt 0; }

        /* Aufsichtstabelle */
        table.supervision-table { width:100%; border-collapse:collapse; margin-bottom:14pt; }
        table.supervision-table thead tr { background:#1e40af; color:#fff; }
        table.supervision-table thead th {
            padding:5pt 7pt; font-size:8.5pt; font-weight:bold;
            text-align:left; letter-spacing:0.2pt;
        }
        table.supervision-table thead th.center { text-align:center; }
        table.supervision-table tbody tr { border-bottom:1pt solid #e5e7eb; }
        table.supervision-table tbody tr:nth-child(even) { background:#f9fafb; }
        table.supervision-table tbody td { padding:6pt 7pt; font-size:10pt; vertical-align:middle; }
        table.supervision-table tbody td.time-cell { font-size:9pt; color:#374151; white-space:nowrap; width:28mm; }
        table.supervision-table tbody td.type-cell { width:40mm; }
        table.supervision-table tbody td.sig-cell  { border-bottom:1pt solid #9ca3af; width:50mm; }

        .badge-prep     { display:inline-block; padding:1.5pt 5pt; border-radius:3pt; font-size:8pt; font-weight:bold; background:#fef3c7; color:#92400e; border:1pt solid #fde68a; }
        .badge-wait     { display:inline-block; padding:1.5pt 5pt; border-radius:3pt; font-size:8pt; font-weight:bold; background:#d1fae5; color:#065f46; border:1pt solid #6ee7b7; }
        .badge-corridor { display:inline-block; padding:1.5pt 5pt; border-radius:3pt; font-size:8pt; font-weight:bold; background:#ede9fe; color:#5b21b6; border:1pt solid #c4b5fd; }

        .no-supervision { font-style:italic; color:#9ca3af; font-size:9pt; }

        /* Besonderheiten */
        .notes-section { margin-top:6pt; }
        .notes-title   { font-size:9pt; font-weight:bold; color:#1e40af; text-transform:uppercase; letter-spacing:0.4pt; margin-bottom:5pt; }
        .notes-line    { border-bottom:1pt solid #9ca3af; height:14pt; margin-bottom:4pt; }

        /* Footer */
        .page-footer {
            position:fixed; bottom:8mm; left:14mm; right:14mm;
            border-top:0.5pt solid #d1d5db; padding-top:3pt;
            font-size:7.5pt; color:#9ca3af; display:table; width:100%;
        }
        .footer-left  { display:table-cell; text-align:left; }
        .footer-right { display:table-cell; text-align:right; }

        @page { size:A4 portrait; margin:10mm 10mm 16mm 10mm; }
    </style>
</head>
<body>

<div class="page-footer">
    <span class="footer-left">{{ $schoolName }}</span>
    <span class="footer-right">Erstellt am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr</span>
</div>

@foreach($pages as $page)
@php /** @var \Carbon\Carbon $examDate */ $examDate = $page['examDate']; @endphp
<div class="page">

    <div class="page-header">
        @if($page['schoolName'])
            <div class="school-name">{{ $page['schoolName'] }}</div>
        @endif
        <div class="page-title">Aufsicht – Unterschriftsbogen (Kolloquium)</div>
    </div>

    <div class="meta-grid">
        <div class="meta-row">
            <div class="meta-cell">
                <div class="meta-label">Prüfungstag</div>
                <div class="meta-value">{{ $examDate->isoFormat('dddd, DD. MMMM YYYY') }}</div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Prüfungszeitraum</div>
                <div class="meta-value" style="font-size:10pt;">{{ $examPeriod->name }}</div>
            </div>
        </div>
    </div>

    <hr class="divider">

    @if(!empty($page['entries']))
    <table class="supervision-table">
        <thead>
            <tr>
                <th style="width:28mm;">Zeitraum</th>
                <th style="width:40mm;">Position</th>
                <th>Name (Kürzel)</th>
                <th style="width:50mm;" class="center">Unterschrift</th>
            </tr>
        </thead>
        <tbody>
            @foreach($page['entries'] as $entry)
            @php
                $badgeClass = match($entry['type']) {
                    'preparation' => 'badge-prep',
                    'waiting'     => 'badge-wait',
                    default       => 'badge-corridor',
                };
                $teacher     = $entry['teacher'];
                $teacherName = $teacher->lastname . ', ' . $teacher->firstname . ' (' . $teacher->shortcode . ')';
            @endphp
            <tr>
                <td class="time-cell">{{ $entry['time_label'] }} Uhr</td>
                <td class="type-cell">
                    <span class="{{ $badgeClass }}">{{ $entry['position'] }}</span>
                </td>
                <td>{{ $teacherName }}</td>
                <td class="sig-cell">&nbsp;</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p class="no-supervision">Für diesen Tag sind noch keine Aufsichten zugeteilt.</p>
    @endif

    <div class="notes-section">
        <div class="notes-title">Besonderheiten / Anmerkungen</div>
        @for($i = 0; $i < 5; $i++)
            <div class="notes-line">&nbsp;</div>
        @endfor
    </div>

</div>{{-- .page --}}
@endforeach

</body>
</html>
