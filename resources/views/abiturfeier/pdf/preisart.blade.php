<html lang="de">
<head>
<meta charset="UTF-8">
<title>{{ $awardType->name }} – Abiturfeier {{ $examPeriod->year }}</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 9pt;
        line-height: 1.4;
        color: #111;
        margin: 20mm 15mm 15mm 15mm;
    }
    .header-block {
        border-left: 5px solid #1e3a5f;
        padding: 4mm 6mm;
        margin-bottom: 8mm;
        background: #f0f5fb;
    }
    .award-title { font-size: 14pt; font-weight: bold; color: #1e3a5f; }
    .award-year  { font-size: 10pt; color: #555; }
    .award-cat   { font-size: 8pt; color: #666; margin-top: 2px; }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead th {
        background: #1e3a5f; color: #fff;
        font-size: 8pt; font-weight: bold;
        padding: 4px 8px; border: 1px solid #1e3a5f;
        text-align: left;
    }
    tbody td {
        padding: 5px 8px;
        border: 1px solid #d1d5db;
        font-size: 9pt;
        vertical-align: top;
    }
    tr:nth-child(even) td { background: #f7fafd; }
    tr:nth-child(odd)  td { background: #fff; }

    .col-nr    { width:  5%; }
    .col-name  { width: 30%; }
    .col-lk    { width: 12%; }
    .col-flag  { width: 11%; text-align: center; }
    .col-notes { width: 31%; }

    .footer {
        margin-top: 12mm;
        font-size: 7pt; color: #9ca3af;
        border-top: 1px solid #e5e7eb; padding-top: 3mm;
        text-align: right;
    }
</style>
</head>
<body>

<div class="header-block">
    <div class="award-title">🏆 {{ $awardType->name }}</div>
    <div class="award-year">Abiturfeier {{ $examPeriod->year }}</div>
    @if($awardType->category)
        <div class="award-cat">Kategorie: {{ $awardType->category }}</div>
    @endif
</div>

@if($awardsForType->isEmpty())
    <p style="color:#888">Noch keine Schüler für diesen Preis eingetragen.</p>
@else
    @php $lkType = $awardType->getRelation('teacher'); @endphp
    <table>
        <thead>
            <tr>
                <th class="col-nr">Nr.</th>
                <th class="col-name">Schüler</th>
                <th class="col-lk">Lehrkraft</th>
                <th class="col-flag">Urkunde<br>im Kuvert</th>
                <th class="col-flag">Sachpreis</th>
                <th class="col-notes">Notiz / Begründung</th>
            </tr>
        </thead>
        <tbody>
            @foreach($awardsForType as $i => $award)
                <tr>
                    <td style="text-align:center; color:#888">{{ $i + 1 }}</td>
                    <td>{{ $award->student?->lastname }}, {{ $award->student?->firstname }}</td>
                    <td>{{ $lkType?->shortcode ?? '–' }}</td>
                    <td class="col-flag">{{ $awardType->certificate_in_envelope ? '✓' : '–' }}</td>
                    <td class="col-flag">{{ $awardType->has_physical_prize ? '✓' : '–' }}</td>
                    <td>{{ $award->notes ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Stand: {{ now()->format('d.m.Y H:i') }}
</div>

</body>
</html>
