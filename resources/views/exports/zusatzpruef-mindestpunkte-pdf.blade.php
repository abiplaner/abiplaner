<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mindestpunkte-Kalkulator {{ $examPeriod->year }}</title>
    <style>
        @page { margin: 14mm 12mm 10mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 7pt;
            line-height: 1.3;
            color: #111;
            margin: 0;
        }

        /* ─── Seitentrennung ────────────────────────────────────── */
        .student-page { page-break-after: always; }
        .student-page:last-child { page-break-after: avoid; }

        /* ─── Kopfzeile ─────────────────────────────────────────── */
        .page-header {
            border-bottom: 2px solid #4338ca;
            padding-bottom: 3pt;
            margin-bottom: 5pt;
        }
        .school-name { font-size: 7pt; color: #555; }
        .student-name { font-size: 11pt; font-weight: bold; color: #1e1b4b; }
        .header-meta { font-size: 6pt; color: #6b7280; margin-top: 1pt; }
        .header-flex { display: table; width: 100%; }
        .header-left  { display: table-cell; vertical-align: bottom; }
        .header-right { display: table-cell; text-align: right; vertical-align: bottom; font-size: 6pt; color: #6b7280; }

        /* ─── Infobox ───────────────────────────────────────────── */
        .info-box {
            background: #f8f8fc;
            border: 1px solid #e0e7ff;
            border-radius: 3pt;
            padding: 3pt 5pt;
            margin-bottom: 5pt;
            font-size: 6.5pt;
            color: #374151;
        }
        .info-box strong { color: #1e1b4b; }
        .total-ok  { color: #16a34a; font-weight: bold; }
        .total-nok { color: #dc2626; font-weight: bold; }

        /* ─── Abschnitt-Titel ───────────────────────────────────── */
        .section-title {
            font-size: 7.5pt;
            font-weight: bold;
            color: #1e1b4b;
            border-bottom: 1px solid #e0e7ff;
            padding-bottom: 1pt;
            margin-bottom: 3pt;
            margin-top: 5pt;
        }

        /* ─── Tabellen ──────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4pt;
        }
        th {
            background: #eef2ff;
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 2.5pt 4pt;
            border: 1px solid #d1d5db;
            color: #374151;
        }
        td {
            font-size: 6.5pt;
            padding: 2pt 4pt;
            border: 1px solid #d1d5db;
            color: #111;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .font-mono   { font-family: 'DejaVu Sans Mono', monospace; }
        .font-bold   { font-weight: bold; }
        .text-gray   { color: #6b7280; }

        .row-zp { background: #eef2ff; }
        .row-alt { background: #f9fafb; }
        .row-pass { background: #f0fdf4; }

        .col-note { width: 28pt; }
        .col-komb { width: 24pt; }
        .col-kurs { width: 28pt; }

        /* ─── Note-Farbcodes ────────────────────────────────────── */
        .c5  { color: #16a34a; font-weight: bold; }
        .c4  { color: #d97706; }
        .c1  { color: #f97316; }
        .c0  { color: #dc2626; font-weight: bold; }
        .cok { color: #16a34a; font-weight: bold; }
        .cnok { color: #dc2626; }

        /* ─── Hinweise ──────────────────────────────────────────── */
        .note-text {
            font-size: 6pt;
            color: #6b7280;
            margin-top: 2pt;
        }
        .alert-missing {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 2pt;
            padding: 3pt 5pt;
            font-size: 6.5pt;
            color: #92400e;
            margin-top: 4pt;
        }
        .alert-impossible {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 2pt;
            padding: 3pt 5pt;
            font-size: 6.5pt;
            color: #991b1b;
            margin-top: 3pt;
        }
        .alert-pass {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 2pt;
            padding: 3pt 5pt;
            font-size: 6.5pt;
            color: #166534;
            margin-top: 3pt;
        }

        .nta-badge {
            background: #fef3c7;
            color: #92400e;
            border-radius: 2pt;
            padding: 0 2pt;
            font-size: 5.5pt;
            font-weight: bold;
        }

        .footer-text {
            font-size: 5.5pt;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 2pt;
            margin-top: 5pt;
        }
    </style>
</head>
<body>

@foreach($studentDataRows as $row)
@php
    /** @var \App\Models\Student $student */
    $student  = $row['student'];
    $exams    = $row['exams'];
    $computed = $row['computed'];
    $curTotal = $row['curTotal'];
    /** @var \App\Services\MindestpunkteService $svc */
    $svc      = $row['service'];

    $allFixed    = $computed['allFixedScoresEntered'];
    $zpExams     = $computed['zpExams'];
    $zpCount     = count($zpExams);
    $combinations = $computed['combinations'];
    $passedCombs  = $computed['passedCombinations'];
    $passedN2     = $computed['passedCombinationsN2'];

    // Tabelle A: exams in sort_order-Reihenfolge (offene ZPs sortiert, Rest unverändert)
    $openPositions = [];
    $openExamsRaw  = [];
    foreach ($exams as $i => $e) {
        if (($e['isZusatzpruefung'] ?? false) && $e['targetPoints'] === null) {
            $openPositions[] = $i;
            $openExamsRaw[]  = $e;
        }
    }
    $anyManual = count(array_filter($openExamsRaw, fn($e) => ($e['sort_order'] ?? 0) > 0)) > 0;
    if ($anyManual) {
        usort($openExamsRaw, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
    }
    $sortedExams = $exams;
    foreach ($openPositions as $pi => $pos) {
        $sortedExams[$pos] = $openExamsRaw[$pi];
    }

    // Bereits erzielte ZP-Noten
    $doneZp = array_filter($exams, fn($e) => ($e['isZusatzpruefung'] ?? false) && $e['targetPoints'] !== null);
@endphp
<div class="student-page">

    {{-- ─── Kopfzeile ──────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="header-flex">
            <div class="header-left">
                @if($school)
                    <div class="school-name">{{ $school->name }}</div>
                @endif
                <div class="student-name">
                    {{ $student->full_name }}
                    @if($student->hasDisabilityCompensation())
                        <span class="nta-badge">+{{ $student->disability_compensation_percent }}&thinsp;%&thinsp;NTA</span>
                    @endif
                </div>
                <div class="header-meta">Mindestpunkte-Kalkulator · GSO §61 · Abitur {{ $examPeriod->year }}</div>
            </div>
            <div class="header-right">
                Gedruckt: {{ now()->format('d.m.Y H:i') }} Uhr
            </div>
        </div>
    </div>

    {{-- ─── Infobox: Formel + Gesamtpunktzahl ─────────────────── --}}
    <div class="info-box">
        <strong>Formel GSO §61:</strong>
        Kombinierte Note = round((2 × schriftlich + mündlich) / 3) — Ergebnis &lt; 1 bleibt 0.
        @if($curTotal !== null)
            &nbsp;·&nbsp; Aktuelle Abiturprüfungspunkte (vor ZP):
            <span class="{{ $curTotal >= 100 ? 'total-ok' : 'total-nok' }}">
                {{ $curTotal }}&thinsp;/&thinsp;300
            </span>
            <span class="text-gray">(Ziel: ≥&thinsp;100)</span>
        @endif
    </div>

    {{-- ─── A: Alle Prüfungsfächer ──────────────────────────────── --}}
    <div class="section-title">A · Alle Prüfungsfächer &amp; Noten</div>
    <table>
        <thead>
            <tr>
                <th>Fach</th>
                <th class="col-kurs text-center">Kurs</th>
                <th style="width:56pt;" class="text-center">Prüfungsart</th>
                <th class="col-note text-center">Note</th>
                <th class="col-komb text-center">Komb.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sortedExams as $i => $exam)
            @php
                $bgClass = ($exam['isZusatzpruefung'] ?? false) ? 'row-zp' : ($i % 2 === 1 ? 'row-alt' : '');
                if ($exam['isZusatzpruefung'] ?? false) {
                    $noteDisplay = $exam['writtenPoints'] !== null ? $exam['writtenPoints'] . ' schr.' : '—';
                    if ($exam['targetPoints'] !== null) {
                        $combined = $svc->calcCombined((int)$exam['writtenPoints'], (int)$exam['targetPoints']);
                        $combClass = $combined >= 5 ? 'c5' : ($combined >= 4 ? 'c4' : ($combined >= 1 ? 'c1' : 'c0'));
                        $kombDisplay = $combined;
                    } else {
                        $combClass = 'text-gray';
                        $kombDisplay = '—';
                    }
                } else {
                    $s = $exam['score'] ?? null;
                    $isSport = $exam['isSport'] ?? false;
                    if ($isSport) {
                        // 'score_raw' gab es nur in der Kopie, die der Controller
                        // sich selbst gebaut hatte; sie nullte 'score' für Sport
                        // und legte den Rohwert daneben. AbiturExamDataService
                        // führt beides in 'score'.
                        $scoreRaw = $exam['score'] ?? null;
                        $pg       = $exam['practical_grade'] ?? null;
                        $noteDisplay = ($scoreRaw !== null ? $scoreRaw : '—')
                            . ($pg !== null ? ' + ' . $pg : '');
                        $kombDisplay = '—';
                        $combClass   = 'text-gray';
                    } else {
                        $noteDisplay = $s !== null ? (string)$s : '—';
                        $combClass   = 'text-gray';
                        $kombDisplay = '—';
                    }
                }
            @endphp
            <tr class="{{ $bgClass }}">
                <td>
                    {{ $exam['subject'] }}
                    @if($exam['isLeistungsfach'] ?? false)
                        <span class="text-gray">(LF)</span>
                    @endif
                    @if($exam['isDeutsch'] ?? false)
                        <span class="text-gray">(D)</span>
                    @elseif($exam['isMathematik'] ?? false)
                        <span class="text-gray">(M)</span>
                    @endif
                </td>
                <td class="font-mono text-center text-gray">{{ $exam['courseCode'] ?? '–' }}</td>
                <td class="text-center">
                    @if($exam['isZusatzpruefung'] ?? false)
                        <span style="color:#4338ca;font-weight:bold;">Zusatzprüfung</span>
                    @elseif(($exam['examType'] ?? '') === 'written')
                        Schriftlich
                    @else
                        Kolloquium
                    @endif
                </td>
                <td class="font-mono text-center">
                    @if($exam['isZusatzpruefung'] ?? false)
                        {{ $exam['writtenPoints'] !== null ? $exam['writtenPoints'] : '—' }}
                        @if($exam['targetPoints'] !== null)
                            <br><span style="color:#16a34a;font-size:5.5pt;">{{ $exam['targetPoints'] }} mündl.&thinsp;✓</span>
                        @endif
                    @elseif($exam['isSport'] ?? false)
                        {{ $noteDisplay }}
                        @if(($exam['score'] ?? null) !== null && ($exam['practical_grade'] ?? null) !== null)
                            <br><span class="text-gray" style="font-size:5.5pt;">Prüfg.&thinsp;+&thinsp;prakt.</span>
                        @endif
                    @else
                        {{ $noteDisplay }}
                    @endif
                </td>
                <td class="font-mono text-center font-bold {{ $combClass }}">{{ $kombDisplay }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(!$allFixed)
        <div class="alert-missing">
            ⚠ Nicht alle Prüfungsnoten eingetragen — Kombinationstabelle kann nicht berechnet werden.
        </div>
    @endif

    @if($allFixed)
    {{-- ─── B: Kombinationstabelle ───────────────────────────────── --}}
    <div class="section-title">B · Kombinationstabelle
        <span style="font-size:5.5pt;font-weight:normal;color:#6b7280;">
            @if($zpCount === 0) — alle ZP abgeschlossen
            @elseif($zpCount === 1) — nur bestandene Kombinationen (N=1)
            @elseif($zpCount === 2) — je mündl. Fach 1: Mindestnote Fach 2
            @else — Pareto-optimale Kombinationen
            @endif
        </span>
    </div>

    @if(count($doneZp) > 0)
    <div style="font-size:6pt;color:#166534;background:#f0fdf4;border:1px solid #86efac;border-radius:2pt;padding:2pt 4pt;margin-bottom:3pt;">
        <strong>Bereits erzielt:</strong>
        @foreach($doneZp as $dz)
            {{ $dz['subject'] }} mündl.&thinsp;{{ $dz['targetPoints'] }} → komb.&thinsp;{{ $svc->calcCombined((int)$dz['writtenPoints'], (int)$dz['targetPoints']) }}@if(!$loop->last),&ensp;@endif
        @endforeach
    </div>
    @endif

    @if($zpCount === 0)
        {{-- N = 0: alle ZP abgeschlossen → tatsächliches Gesamtergebnis --}}
        @if($computed['pass'])
            <div class="alert-pass">✓ Alle Zusatzprüfungen abgeschlossen — der Schüler besteht das Abitur (Σ×4 = {{ $computed['total'] }}).</div>
        @else
            <div class="alert-impossible">✗ Alle Zusatzprüfungen abgeschlossen — die Voraussetzungen sind nicht erfüllt (Σ×4 = {{ $computed['total'] }}).</div>
        @endif
    @else
        @include('exports.partials.zusatzpruef-mp-kombinationstabelle', [
            'zpExams'          => $zpExams,
            'top5'             => $computed['top5Combinations'] ?? [],
            'totalPassedCount' => $row['totalPassedCount'] ?? count($passedCombs ?? []),
            'svc'              => $svc,
        ])
    @endif

    @if($allFixed && $zpCount > 0 && count($passedCombs ?? []) === 0)
        <div class="alert-impossible">✗ Keine Kombination (0–15) erfüllt alle Anforderungen — Abitur nicht bestehbar.</div>
    @elseif($allFixed && $zpCount > 0 && count($passedCombs ?? []) > 0)
        <div class="alert-pass" style="margin-top:2pt;">✓ Alle gezeigten Kombinationen führen zum Bestehen.</div>
    @endif

    @endif {{-- /allFixed --}}

    {{-- ─── Geprüfte Anforderungen ──────────────────────────────── --}}
    <div class="footer-text">
        <strong>(1)</strong> jedes Fach ≥ 1 Pkt. &nbsp;·&nbsp;
        <strong>(2)</strong> Σ×4 ≥ 100 (Abiturprüfung) &nbsp;·&nbsp;
        <strong>(3)</strong> mind. 3 Fächer ≥ 5 (inkl. D/M/LF) &nbsp;·&nbsp;
        <strong>(4)</strong> D+M+FS/NW: Σ×4 ≥ 40, max. 1× &lt; 4
    </div>

</div>
@endforeach

{{-- Externe Bewerber: eigene Seite je Prüfling, gerechnet nach § 62 statt
     nach § 61. Zwei Rechenwege, kein Sonderfall im ersten. --}}
@foreach($externeRows ?? [] as $externeRow)
<div class="student-page">
    <div class="student-name">{{ $externeRow['prueflingName'] }} <span style="font-weight:normal;color:#6b7280;">· externe Bewerbung</span></div>
    @include('exports.partials.zusatzpruef-mp-62', ['externe62' => $externeRow['externe62']])
</div>
@endforeach

@if(!empty($uebersprungeneExterne ?? []))
<div class="student-page">
    <div class="student-name">Noch nicht zu rechnen: externe Bewerberinnen und Bewerber</div>
    <div style="font-size:8.5pt;margin-top:6pt;line-height:1.5;">
        Für die folgenden Prüflinge richtet sich das Bestehen nach § 62 GSO. Ihre
        Fächerwahl belegt die acht Prüfungsfächer noch nicht vollständig
        (§ 61 Abs. 1 S. 1); solange ist nichts zu rechnen. Sie stehen hier, damit
        niemand sie stillschweigend vermisst.
        <ul style="margin-top:6pt;">
            @foreach($uebersprungeneExterne as $name)
                <li>{{ $name }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

</body>
</html>
