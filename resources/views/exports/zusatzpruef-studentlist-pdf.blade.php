<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zusatzprüfungen – Schülerliste {{ $examPeriod->year }}</title>
    <style>
        @page { margin: 18mm 0 10mm 0; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 6.5pt;
            line-height: 1.2;
            color: #111;
            margin: 0 8mm;
        }

        .running-header {
            position: fixed;
            top: -18mm; left: 0; right: 0; height: 16mm;
            background: #fff; text-align: center;
        }
        .school-name { font-size: 9pt; font-weight: bold; color: #1e40af; }
        .plan-title  { font-size: 10pt; font-weight: bold; color: #1e40af; margin: 1px 0; }
        .plan-meta   { font-size: 6pt; color: #555; }

        .page-footer {
            position: fixed;
            bottom: -10mm; left: 0; right: 0; height: 8mm;
            padding: 1mm 8mm; font-size: 6pt; color: #9ca3af;
            text-align: center; border-top: 1px solid #e5e7eb; background: #fff;
        }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }

        thead th {
            background: #1e40af; color: #fff;
            font-size: 6pt; font-weight: bold;
            padding: 3px 4px; border: 1px solid #1e40af;
            text-align: left; vertical-align: middle;
            white-space: nowrap;
        }
        tbody td {
            padding: 2px 4px;
            border: 1px solid #d1d5db;
            vertical-align: middle;
            font-size: 6.5pt;
            white-space: nowrap;
            overflow: hidden;
        }

        /* Zeilenfärbung: abwechselnd pro Schüler-Block */
        tr.student-even td { background: #fff; }
        tr.student-odd  td { background: #f0f4f8; }

        /* Trennlinie zwischen Schüler-Blöcken */
        tr.student-first td { border-top: 1px solid #9ca3af; }

        /* Spaltenbreiten (Portrait A4 = ~185mm nutzbar) */
        .col-name      { width: 16%; }
        .col-fach      { width:  4%; }
        .col-kurs      { width:  6%; text-align: center; }
        tbody .col-kurs { color: #374151; }
        .col-ausschluss { width: 9%; }
        .col-schwerpunkt{ width: 9%; }
        .col-pruefer   { width: 22%; }
        .col-datum     { width:  8%; }
        .col-vorb      { width: 10%; }
        .col-pruef     { width:  9%; }
        .col-raum      { width:  7%; text-align: center; }

        .s-name   { font-weight: bold; }
        .s-absent { color: #9ca3af; text-decoration: line-through; }
        .s-nta    { font-size: 6pt; color: #b45309; margin-left: 2px; }
        .s-ext    { font-size: 6pt; color: #1d4ed8; margin-left: 2px; }
        .s-na     { font-size: 6pt; color: #b91c1c; font-weight: bold; margin-left: 2px; }
        .s-pending{ color: #bbb; font-style: italic; }
        .empty-c  { color: #ccc; }

        .no-data { text-align: center; color: #9ca3af; padding: 40px; font-size: 11pt; }
        .summary { margin-top: 4px; font-size: 6pt; color: #9ca3af; }
    </style>
</head>
<body>

<div class="running-header">
    <div style="padding-top: 2mm; text-align: center;">
        @if($school)<div class="school-name">{{ $school->name }}</div>@endif
        <div class="plan-title">Abitur {{ $examPeriod->year }} &ndash; Zusatzprüfungen Schülerliste</div>
        <div class="plan-meta">Stand: {{ \Carbon\Carbon::now()->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm') }} Uhr</div>
    </div>
</div>

<div class="page-footer">
    {{ $school?->name ?? '' }} &nbsp;|&nbsp;
    Zusatzprüfungen Schülerliste Abitur {{ $examPeriod->year }} &nbsp;|&nbsp;
    Erstellt: {{ now()->format('d.m.Y H:i') }} Uhr
</div>

@php
function slName(?\App\Models\Teacher $t): string {
    if (!$t) return '?';
    $n = $t->lastname ?? '?';
    $f = $t->firstname ?? '';
    return $f !== '' ? $n . ', ' . strtoupper(mb_substr($f, 0, 1)) . '.' : $n;
}

// Nach Schüler gruppieren (Reihenfolge bleibt: alphabetisch Nachname/Vorname/Fach)
$grouped = $pruefungen->groupBy(fn($p) => $p->prueflingsSchluessel());
@endphp

@if($pruefungen->isEmpty())
    <div class="no-data">Keine Zusatzprüfungen gefunden.</div>
@else
<table>
    <thead>
        <tr>
            <th class="col-name">Name</th>
            <th class="col-fach">Fach</th>
            <th class="col-kurs">Kurs</th>
            <th class="col-ausschluss">Ausschluss</th>
            <th class="col-schwerpunkt">Schwerpunkt</th>
            <th class="col-pruefer">Prüfer</th>
            <th class="col-datum">Tag</th>
            <th class="col-vorb">Vorbereitung</th>
            <th class="col-pruef">Prüfungszeit</th>
            <th class="col-raum">Raum</th>
        </tr>
    </thead>
    <tbody>
        @php $studentIdx = 0; @endphp
        @foreach($grouped as $studentId => $rows)
            @php
                $rowClass    = $studentIdx % 2 === 0 ? 'student-even' : 'student-odd';
                $studentIdx++;
                $firstRow    = $rows->first();
                // Prüfling ist Schüler/in oder externe/r Bewerber/in; full_name
                // liefert in beiden Fällen „Nachname, Vorname".
                $fullName    = $firstRow->prueflingsName();
                $istExtern   = $firstRow->istExtern();
                $rowCount    = $rows->count();
            @endphp
            @foreach($rows as $rowNum => $p)
                @php
                    $isAbsent  = $p->absent;
                    $asgn      = $p->assignment;

                    // Prüfer – alles einzeilig
                    $erst  = $p->pruefer->where('role', 'erstpruefer')
                                ->sortBy('sort_order')->map(fn($r) => slName($r->teacher))->join(', ');
                    $zweit = $p->pruefer->where('role', 'zweitkorrektor')
                                ->sortBy('sort_order')->map(fn($r) => slName($r->teacher))->join(', ');
                    $prueferStr = $erst;
                    if ($zweit) $prueferStr .= ' / ' . $zweit;

                    // Zeiten & Datum
                    if ($asgn) {
                        $prepStart  = \Carbon\Carbon::createFromTimeString($asgn->start_time);
                        $prepEnd    = $prepStart->copy()->addMinutes($asgn->prep_duration_minutes);
                        $examStart  = $prepEnd->copy();
                        $examEnd    = $examStart->copy()->addMinutes($asgn->exam_duration_minutes);
                        $datumStr   = $asgn->day?->date
                            ? \Carbon\Carbon::parse($asgn->day->date)->locale('de')->isoFormat('dd D.M.')
                            : '–';
                        $vorbStr    = $prepStart->format('H:i') . '–' . $prepEnd->format('H:i');
                        $pruefStr   = $examStart->format('H:i') . '–' . $examEnd->format('H:i');
                        $raumStr    = $asgn->dayRoom?->examRoom?->room_number ?? '–';
                        $ntaExtra   = max(0, $asgn->prep_duration_minutes - 20);
                    } else {
                        $datumStr  = '';
                        $vorbStr   = '';
                        $pruefStr  = '';
                        $raumStr   = '';
                        $ntaExtra  = 0;
                    }
                @endphp
                <tr class="{{ $rowClass }}{{ $rowNum === 0 ? ' student-first' : '' }}">

                    {{-- Name: nur in erster Zeile, mit rowspan --}}
                    @if($rowNum === 0)
                    <td class="col-name" rowspan="{{ $rowCount }}">
                        <span class="{{ $isAbsent ? 's-name s-absent' : 's-name' }}">{{ $fullName }}</span>
                        @if($istExtern)
                            <span class="s-ext">ext</span>
                        @endif
                        @if($firstRow->hatNachteilsausgleich())
                            <span class="s-nta">NTA</span>
                        @endif
                        @if($isAbsent)
                            <span class="s-na">n.a.</span>
                        @endif
                    </td>
                    @endif

                    {{-- Fach --}}
                    <td class="col-fach">{{ $p->studentExam->getSubjectCode() ?? '–' }}</td>

                    {{-- Kurs --}}
                    <td class="col-kurs">{{ $p->istExtern() ? '–' : $p->studentExam->getSubjectLabel() }}</td>

                    @php
                        $slIsMath = $p->studentExam->istMathematik();
                        $slExcl   = $p->excluded_semester;
                        $slFocus  = $p->focus_semester;
                        $slSemLbl = ['12_1'=>'AA 12/1','12_2'=>'AA 12/2','13_1'=>'AA 13/1','13_2'=>'AA 13/2'];
                        if ($slIsMath) {
                            $slAusschluss  = $slExcl ? ucfirst($slExcl) : null;
                            $slSchwerpunkt = null;
                        } else {
                            $slAusschluss  = ($slExcl && isset($slSemLbl[$slExcl])) ? $slSemLbl[$slExcl] : null;
                            $slSchwerpunkt = ($slFocus && isset($slSemLbl[$slFocus])) ? $slSemLbl[$slFocus] : null;
                        }
                    @endphp

                    {{-- Ausschluss --}}
                    <td class="col-ausschluss">
                        @if($slAusschluss)
                            <span style="color:#b45309;">{{ $slAusschluss }}</span>
                        @else
                            <span class="empty-c">–</span>
                        @endif
                    </td>

                    {{-- Schwerpunkt --}}
                    <td class="col-schwerpunkt">
                        @if($slSchwerpunkt)
                            <span style="color:#1e40af;">{{ $slSchwerpunkt }}</span>
                        @else
                            <span class="empty-c">–</span>
                        @endif
                    </td>

                    {{-- Prüfer --}}
                    <td class="col-pruefer">
                        @if($prueferStr)
                            {{ $prueferStr }}
                        @else
                            <span class="empty-c">–</span>
                        @endif
                    </td>

                    {{-- Tag --}}
                    <td class="col-datum">
                        @if($asgn)
                            {{ $datumStr }}
                        @else
                            <span class="s-pending">–</span>
                        @endif
                    </td>

                    {{-- Vorbereitung --}}
                    <td class="col-vorb">
                        @if($asgn)
                            {{ $vorbStr }}@if($ntaExtra > 0) <span class="s-nta">+{{ $ntaExtra }}'</span>@endif
                        @else
                            <span class="s-pending">–</span>
                        @endif
                    </td>

                    {{-- Prüfungszeit --}}
                    <td class="col-pruef">
                        @if($asgn){{ $pruefStr }}@else<span class="empty-c">–</span>@endif
                    </td>

                    {{-- Raum --}}
                    <td class="col-raum">
                        @if($asgn){{ $raumStr }}@else<span class="empty-c">–</span>@endif
                    </td>

                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

<p class="summary">
    @php $hatExterne = $pruefungen->contains(fn ($p) => $p->istExtern()); @endphp
    {{ $grouped->count() }}
    @if($hatExterne){{ $grouped->count() === 1 ? 'Prüfling' : 'Prüflinge' }}@else{{ $grouped->count() === 1 ? 'Schüler/in' : 'Schülerinnen/Schüler' }}@endif,
    {{ $pruefungen->count() }} {{ $pruefungen->count() === 1 ? 'Prüfung' : 'Prüfungen' }}
    ({{ $pruefungen->where('status', 'placed')->count() }} platziert,
    {{ $pruefungen->where('absent', true)->count() }} nicht angetreten).
</p>
@endif

</body>
</html>
