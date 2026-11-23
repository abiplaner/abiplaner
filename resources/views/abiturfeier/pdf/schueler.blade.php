<html lang="de">
<head>
<meta charset="UTF-8">
<title>Ehrungen – Schülerliste {{ $examPeriod->year }}</title>
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

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead th {
        background: #1e3a5f; color: #fff;
        font-size: 6pt; font-weight: bold;
        padding: 3px 5px; border: 1px solid #1e3a5f;
        text-align: left;
    }
    tbody td {
        padding: 3px 5px;
        border: 1px solid #d1d5db;
        font-size: 7pt;
        vertical-align: top;
    }

    tr.student-row td { background: #fff; }
    tr.student-row-alt td { background: #f7fafd; }

    /* Schüler ohne Ehrung: kursiv, hellgrau */
    tr.no-award td { color: #aaa; font-style: italic; }
    tr.no-award-alt td { color: #aaa; font-style: italic; background: #f7fafd; }

    /* Erste Zeile eines Schülerblocks mit Trennlinie */
    tr.block-first td { border-top: 2px solid #9ca3af; }

    .col-nr    { width:  4%; }
    .col-name  { width: 20%; }
    .col-type  { width: 25%; }
    .col-lk    { width: 8%; }
    .col-flag  { width: 8%; text-align: center; }
    .col-notes { width: 27%; }
</style>
</head>
<body>

<div class="running-header">
    <div class="school-name">Abiturfeier {{ $examPeriod->year }}</div>
    <div class="plan-title">Ehrungen &amp; Preise — Schülerliste (alphabetisch)</div>
    <div class="plan-meta">Stand: {{ now()->format('d.m.Y H:i') }}</div>
</div>

<div class="page-footer">
    Ehrungen Abitur {{ $examPeriod->year }} — Schülerliste
</div>

<table>
    <thead>
        <tr>
            <th class="col-nr">Nr.</th>
            <th class="col-name">Schüler</th>
            <th class="col-type">Preisart</th>
            <th class="col-lk">Lehrkraft</th>
            <th class="col-flag">Urkunde<br>im Kuvert</th>
            <th class="col-flag">Sachpreis</th>
            <th class="col-notes">Notiz / Begründung</th>
        </tr>
    </thead>
    <tbody>
        @php $nr = 0; @endphp
        @foreach($sorted as $student)
            @php
                $nr++;
                $studentAwards = $awardsByStudentId->get($student->id, collect());
                // Ehrungen nach sort_order der Preisart sortieren
                $studentAwards = $studentAwards->sortBy(function ($a) use ($typeMap) {
                    $t = $typeMap->get($a->award_type_id);
                    return $t ? $t->sort_order : 999;
                });
                $rowClass = ($nr % 2 === 0) ? 'student-row-alt' : 'student-row';
                $noAwardClass = ($nr % 2 === 0) ? 'no-award-alt' : 'no-award';
            @endphp

            @if($studentAwards->isEmpty())
                {{-- Schüler ohne Ehrung: eine Zeile, gedimmt --}}
                <tr class="{{ $noAwardClass }} block-first">
                    <td style="text-align:center">{{ $nr }}</td>
                    <td>{{ $student->lastname }}, {{ $student->firstname }}</td>
                    <td>–</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @else
                @php $first = true; @endphp
                @foreach($studentAwards as $award)
                    @php
                        $awardType = $typeMap->get($award->award_type_id);
                        $lkType    = $awardType ? $awardType->getRelation('teacher') : null;
                    @endphp
                    <tr class="{{ $rowClass }} {{ $first ? 'block-first' : '' }}">
                        <td style="text-align:center">{{ $first ? $nr : '' }}</td>
                        <td>{{ $first ? $student->lastname . ', ' . $student->firstname : '' }}</td>
                        <td>{{ $awardType?->name ?? '–' }}</td>
                        <td>{{ $lkType?->shortcode ?? '–' }}</td>
                        <td class="col-flag">{{ $awardType?->certificate_in_envelope ? '✓' : '–' }}</td>
                        <td class="col-flag">{{ $awardType?->has_physical_prize ? '✓' : '–' }}</td>
                        <td>{{ $award->notes ?: '' }}</td>
                    </tr>
                    @php $first = false; @endphp
                @endforeach
            @endif
        @endforeach
    </tbody>
</table>

</body>
</html>
