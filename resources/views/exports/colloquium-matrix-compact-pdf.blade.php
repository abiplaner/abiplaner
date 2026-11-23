@php
    /*
     * Kompakte Planungsmatrix
     * Zeilen  = Raum (rowspan) × belegter Zeitslot
     * Spalten = pro Prüfungstag 4 Sub-Spalten: Name | Kurs | Leh. | Beis.
     *
     * WICHTIG für DomPDF: kein colspan in Header-Row 1 – DomPDF liest bei
     * table-layout:fixed nur die erste Zeile für Spaltenbreiten. Stattdessen
     * hat Row 1 alle Sub-Spalten einzeln mit expliziten Breiten.
     */
    $numDays = count($days);

    $timeW = 4;   // %
    $roomW = 5;   // %
    $rest  = 100 - $timeW - $roomW;

    // Anteile innerhalb eines Tages (Summe 1.0) – Name bewusst breiter
    $nameShare = 0.58;
    $kursShare = 0.17;
    $lehShare  = 0.125;
    $beisShare = 0.125;

    $dayW  = round($rest / max($numDays, 1), 4);
    $nameW = round($dayW * $nameShare, 4);
    $kursW = round($dayW * $kursShare, 4);
    $lehW  = round($dayW * $lehShare,  4);
    $beisW = round($dayW * $beisShare, 4);

    $fs    = '5pt';
    $fsSub = '4.5pt';
    $pad   = '2px 2px';
    $wd    = ['So','Mo','Di','Mi','Do','Fr','Sa'];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Planungsmatrix – {{ $examPeriod->name }}</title>
<style>
@page { margin: 5mm; }
* { box-sizing: border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: {{ $fs }};
       line-height: 1.1; color: #1f2937; margin: 0; padding: 0; }
.page-header { text-align:center; padding:4px 0 3px;
               border-bottom:2px solid #1e40af; margin-bottom:5px; }
.hd-school { font-size:7.5pt; font-weight:bold; color:#1e40af; }
.hd-title  { font-size:9pt; font-weight:bold; color:#1e40af; margin:1px 0; }
.hd-meta   { font-size:5pt; color:#6b7280; }

table { width:100%; border-collapse:collapse; table-layout:fixed; }

thead th {
    font-size: {{ $fsSub }}; font-weight:bold; padding: {{ $pad }};
    text-align:center; border:1px solid #60a5fa;
    overflow:hidden; white-space:nowrap;
    background:#1e40af; color:#fff;
}
/* Row 1: Tages-Label steht in der Name-Spalte, übrige Sub-Spalten leer */
thead th.th-r1-label { background:#1d4ed8; text-align:left; font-size:{{ $fsSub }}; }
thead th.th-r1-empty { background:#1d4ed8; border-left:none; }
/* Row 2: Sub-Labels */
thead th.th-sub { background:#3b82f6; font-weight:normal; font-size:4pt; }
thead th.th-fix { background:#1e40af; }   /* Raum + Zeit */

tbody td { padding: {{ $pad }}; border:1px solid #e5e7eb;
           vertical-align:middle; overflow:hidden; white-space:nowrap; }
td.td-time { background:#eff6ff; font-weight:bold; text-align:center;
             color:#1e40af; font-size:{{ $fs }}; }
td.td-room { background:#dbeafe; font-weight:bold; text-align:center;
             color:#1e40af; font-size:{{ $fs }}; }
td.td-name  { font-size:{{ $fs }};    font-weight:bold; color:#111827; }
td.td-kurs  { font-size:{{ $fsSub }}; color:#374151; text-align:center; }
td.td-leh   { font-size:{{ $fs }};    color:#1e40af;  text-align:center; }
td.td-beis  { font-size:{{ $fs }};    color:#4b5563;  text-align:center; }
td.td-empty { color:#d1d5db; text-align:center; font-size:4pt; }
tr.room-sep td { border-top:2px solid #93c5fd !important; }
.ext  { background:#dbeafe; color:#1e40af; font-size:4pt; padding:0 1px; }
.warn { color:#ea580c; }
.page-footer { font-size:5pt; color:#9ca3af; text-align:center;
               margin-top:4px; border-top:1px solid #e5e7eb; padding-top:3px; }
</style>
</head>
<body>

<div class="page-header">
    @if($school)<div class="hd-school">{{ $school->name }}</div>@endif
    <div class="hd-title">Kolloquium Planungsmatrix – {{ $examPeriod->name }}</div>
    <div class="hd-meta">
        Woche&nbsp;1: {{ $examPeriod->week_1_start->format('d.m.Y') }}–{{ $examPeriod->week_1_end->format('d.m.Y') }}
        &nbsp;|&nbsp;
        Woche&nbsp;2: {{ $examPeriod->week_2_start->format('d.m.Y') }}–{{ $examPeriod->week_2_end->format('d.m.Y') }}
        &nbsp;|&nbsp; Stand: {{ now()->format('d.m.Y H:i') }}&nbsp;Uhr
    </div>
</div>

@if(empty($days) || $rooms->isEmpty())
    <p style="text-align:center;color:#9ca3af;padding:30px;">Keine geplanten Prüfungen gefunden.</p>
@else
<table>
    <thead>
        {{--
            Row 1: Alle Spalten mit expliziten Breiten – KEIN colspan!
            DomPDF liest bei table-layout:fixed nur Row 1 für Spaltenbreiten.
            Day-Label steht nur in der th-r1-label-Zelle (Name-Spalte).
            Die 3 benachbarten Sub-Spalten sind leer (gleiche Hintergrundfarbe).
        --}}
        <tr>
            <th class="th-fix" style="width:{{ $roomW }}%" rowspan="2">Raum</th>
            <th class="th-fix" style="width:{{ $timeW }}%" rowspan="2">Zeit</th>
            @foreach($days as $day)
                <th class="th-r1-label" style="width:{{ $nameW }}%">
                    {{ $wd[$day['date']->dayOfWeek] }}&nbsp;{{ $day['date']->format('d.m.') }}@if($day['week']==2)&nbsp;②@endif
                </th>
                <th class="th-r1-empty" style="width:{{ $kursW }}%"></th>
                <th class="th-r1-empty" style="width:{{ $lehW  }}%"></th>
                <th class="th-r1-empty" style="width:{{ $beisW }}%"></th>
            @endforeach
        </tr>
        {{-- Row 2: Sub-Labels --}}
        <tr>
            @foreach($days as $day)
                <th class="th-sub" style="width:{{ $nameW }}%">Name</th>
                <th class="th-sub" style="width:{{ $kursW }}%">Kurs</th>
                <th class="th-sub" style="width:{{ $lehW  }}%">Leh.</th>
                <th class="th-sub" style="width:{{ $beisW }}%">Beis.</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($matrix as $roomId => $roomData)
            @php $room = $roomData['room']; $numRows = $roomData['numRows']; $first = true; @endphp
            @foreach($roomData['rows'] as $time => $cells)
                <tr class="{{ $first ? 'room-sep' : '' }}">
                    @if($first)
                        <td class="td-room" rowspan="{{ $numRows }}"
                            style="width:{{ $roomW }}%; vertical-align:middle;">
                            {{ $room->room_number }}
                        </td>
                        @php $first = false; @endphp
                    @endif
                    <td class="td-time" style="width:{{ $timeW }}%">{{ $time }}</td>
                    @foreach($cells as $asgn)
                        @if($asgn)
                            @php
                                $se   = $asgn->studentExam;
                                $ln   = $se->student?->lastname
                                     ?? $se->externalExamSubject?->externalApplicant?->lastname
                                     ?? '–';
                                $fi   = substr($se->student?->firstname
                                     ?? $se->externalExamSubject?->externalApplicant?->firstname
                                     ?? '', 0, 1);
                                $name = $ln . ($fi ? ', '.$fi.'.' : '');
                                $kurs = $se->isExternalApplicant()
                                    ? ($se->getSubjectName() ?? '–')
                                    : ($se->course->course_code ?? '–');
                            @endphp
                            <td class="td-name" style="width:{{ $nameW }}%">{{ $name }}@if($se->isExternalApplicant())<span class="ext">e</span>@endif</td>
                            <td class="td-kurs" style="width:{{ $kursW }}%">{{ $kurs }}</td>
                            <td class="td-leh"  style="width:{{ $lehW  }}%">{{ $se->examiner?->shortcode ?? '–' }}</td>
                            <td class="td-beis" style="width:{{ $beisW }}%">@if($se->assessor){{ $se->assessor->shortcode }}@else<span class="warn">–</span>@endif</td>
                        @else
                            <td class="td-empty" style="width:{{ $nameW }}%">–</td>
                            <td style="width:{{ $kursW }}%; border:1px solid #e5e7eb;"></td>
                            <td style="width:{{ $lehW  }}%; border:1px solid #e5e7eb;"></td>
                            <td style="width:{{ $beisW }}%; border:1px solid #e5e7eb;"></td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
@endif

<div class="page-footer">
    {{ $school?->name ?? '' }} &nbsp;|&nbsp; Planungsmatrix {{ $examPeriod->name }}
    &nbsp;|&nbsp; Erstellt: {{ now()->format('d.m.Y H:i') }}&nbsp;Uhr
</div>
</body>
</html>
