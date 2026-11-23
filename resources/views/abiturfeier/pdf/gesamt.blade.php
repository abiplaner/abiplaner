<html lang="de">
<head>
<meta charset="UTF-8">
<title>Ehrungen – Gesamtliste {{ $examPeriod->year }}</title>
<style>
    @page { margin: 18mm 0 12mm 0; }
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 7pt;
        line-height: 1.3;
        color: #111;
        margin: 0 10mm;
    }
    .running-header {
        position: fixed;
        top: -18mm; left: 0; right: 0; height: 16mm;
        background: #fff; text-align: center; padding-top: 2mm;
    }
    .school-name { font-size: 9pt; font-weight: bold; color: #1e3a5f; }
    .plan-title  { font-size: 11pt; font-weight: bold; color: #1e3a5f; margin: 1px 0; }
    .plan-meta   { font-size: 6pt; color: #555; }
    .page-footer {
        position: fixed;
        bottom: -12mm; left: 0; right: 0; height: 10mm;
        font-size: 6pt; color: #9ca3af;
        text-align: center; border-top: 1px solid #e5e7eb; background: #fff;
        padding-top: 2mm;
    }
    .section-header {
        background: #1e3a5f; color: #fff;
        font-size: 8pt; font-weight: bold;
        padding: 4px 6px; margin-top: 8mm; margin-bottom: 0;
    }
    .section-meta { font-size: 6pt; font-weight: normal; opacity: 0.8; }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 0; }
    thead th {
        background: #dce8f5; color: #1e3a5f;
        font-size: 6pt; font-weight: bold;
        padding: 3px 5px; border: 1px solid #b6cfe8;
        text-align: left;
    }
    tbody td {
        padding: 3px 5px;
        border: 1px solid #d1d5db;
        font-size: 7pt;
        vertical-align: top;
    }
    tr:nth-child(even) td { background: #f7fafd; }
    tr:nth-child(odd)  td { background: #fff; }

    .col-name  { width: 24%; }
    .col-lk    { width: 10%; }
    .col-status { width: 12%; }
    .col-flag  { width: 9%; text-align: center; }
    .col-notes { width: 36%; }
    .badge-candidate { color: #1d4ed8; font-style: italic; }
    .badge-rank      { color: #92400e; font-weight: bold; }
    .no-winner { color: #92400e; font-style: italic; padding: 4px 6px; }
</style>
</head>
<body>

<div class="running-header">
    <div class="school-name">Abiturfeier {{ $examPeriod->year }}</div>
    <div class="plan-title">Ehrungen &amp; Preise — Gesamtliste</div>
    <div class="plan-meta">Stand: {{ now()->format('d.m.Y H:i') }}</div>
</div>

<div class="page-footer">
    Ehrungen Abitur {{ $examPeriod->year }} — Gesamtliste
</div>

@foreach($awardTypes as $awardType)
    @php
        $typeAwards = $awardsByType->get($awardType->id, collect());
        $hasWinners = $typeAwards->isNotEmpty();
        $noWinnerNote = $awardType->no_winner_note ?? '';
        if (!$hasWinners && !$noWinnerNote) continue;
        $lkType = $awardType->getRelation('teacher');
        $sorted = $typeAwards->sortBy(fn($a) =>
            ($a->student?->lastname ?? '') . ($a->student?->firstname ?? '')
        );
    @endphp

    <div class="section-header">
        {{ $awardType->name }}
        @if($awardType->category)
            <span class="section-meta"> · {{ $awardType->category }}</span>
        @endif
        @if($lkType)
            <span class="section-meta"> · Lehrkraft: {{ $lkType->shortcode }}</span>
        @endif
        @if($hasWinners)
            <span class="section-meta"> · {{ $typeAwards->count() }} Schüler</span>
        @endif
    </div>

    @if(!$hasWinners && $noWinnerNote)
        <div class="no-winner">{{ $noWinnerNote }}</div>
    @else
        <table>
            <thead>
                <tr>
                    <th class="col-name">Schüler</th>
                    <th class="col-lk">Lehrkraft</th>
                    <th class="col-status">Status</th>
                    <th class="col-flag">Urkunde<br>im Kuvert</th>
                    <th class="col-flag">Sachpreis</th>
                    <th class="col-notes">Notiz / Begründung</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sorted as $award)
                    <tr>
                        <td>{{ $award->student?->lastname }}, {{ $award->student?->firstname }}</td>
                        <td>{{ $lkType?->shortcode ?? '–' }}</td>
                        <td>
                            @if($award->is_candidate)
                                <span class="badge-candidate">Kandidat</span>
                            @elseif($award->rank)
                                <span class="badge-rank">{{ $award->rank }}. Preis</span>
                            @else
                                –
                            @endif
                        </td>
                        <td class="col-flag">{{ $awardType->certificate_in_envelope ? '✓' : '–' }}</td>
                        <td class="col-flag">{{ $awardType->has_physical_prize ? '✓' : '–' }}</td>
                        <td>{{ $award->notes ?: '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endforeach

</body>
</html>
