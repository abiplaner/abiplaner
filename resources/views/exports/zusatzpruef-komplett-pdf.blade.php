<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zusatzprüfungen {{ $examPeriod->year }} – {{ $prueflingName }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            color: #111;
            margin: 14mm 14mm 12mm 14mm;
        }
        .page-break { page-break-before: always; }

        /* ── Fahrplan-Seite ─────────────────────────── */
        .school-name  { font-size: 8pt; color: #555; margin-bottom: 2pt; }
        .student-name { font-size: 13pt; font-weight: bold; margin-bottom: 1pt; }
        .subtitle     { font-size: 8.5pt; color: #555; margin-bottom: 6pt; }
        .nta-badge {
            display: inline-block; background: #fff3cd;
            border: 1px solid #ffc107; border-radius: 2pt;
            padding: 0 3pt; font-size: 7.5pt; font-weight: bold; color: #856404;
        }
        .intro-box {
            background: #f5f5f5; border-left: 3pt solid #333;
            padding: 4pt 7pt; margin: 4pt 0 6pt; font-size: 8.5pt;
        }
        table.main-table { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
        table.main-table th {
            background: #222; color: #fff; font-size: 8pt;
            padding: 3pt 5pt; text-align: left; border: 1px solid #111;
        }
        table.main-table td {
            border: 1px solid #bbb; padding: 4pt 6pt;
            font-size: 8.5pt; vertical-align: top;
        }
        table.main-table tr:nth-child(even) td { background: #f9f9f9; }
        .anm-info { font-size: 7.5pt; color: #555; }

        /* ── Mindestpunkte-Box ──────────────────────── */
        .mp-box {
            margin-top: 6pt; border: 1pt solid #c7d2fe;
            border-radius: 2pt; background: #eef2ff; padding: 4pt 6pt;
        }
        .mp-title { font-size: 8pt; font-weight: bold; color: #1e1b4b; margin-bottom: 3pt; }
        table.mp-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.mp-table th {
            background: #e0e7ff; font-size: 7pt; font-weight: bold;
            padding: 2pt 4pt; border: 1px solid #c7d2fe; color: #374151;
        }
        table.mp-table td { padding: 2pt 4pt; border: 1px solid #c7d2fe; vertical-align: middle; }
        .mp-table .zp-row { background: #f0f0ff; }
        .mp-table .row-pass { background: #f0f0ff; }
        .mp-table .text-center { text-align: center; }
        .mp-table .font-mono { font-family: 'DejaVu Sans Mono', monospace; }
        .mp-table .font-bold { font-weight: bold; }
        .mp-table .text-gray { color: #6b7280; }
        .mp-table .col-komb { width: 40pt; }
        .note-text { font-size: 6.5pt; color: #6b7280; margin-top: 2pt; }
        .c5  { color: #16a34a; font-weight: bold; }
        .c4  { color: #d97706; }
        .c1  { color: #f97316; }
        .c0  { color: #dc2626; font-weight: bold; }
        .cok { color: #16a34a; font-weight: bold; }
        .cnok { color: #dc2626; }

        /* ── Info-Bullets ───────────────────────────── */
        .info-section { margin-top: 6pt; font-size: 8.5pt; }
        .info-section ul { margin: 2pt 0 0 14pt; padding: 0; }
        .info-section li { margin-bottom: 3pt; }

        /* ── Anmeldezettel ──────────────────────────── */
        .anm-page { padding-top: 2mm; }
        .anm-header { font-size: 11pt; font-weight: bold; margin: 0 0 1pt; }
        .anm-subtitle { font-size: 8.5pt; color: #444; margin-bottom: 5pt; font-style: italic; }

        /* Kopfzeilen-Raster */
        .kopf-table { width: 100%; border-collapse: collapse; margin-bottom: 7pt; }
        .kopf-table td {
            border: 1px solid #888; padding: 4pt 6pt;
            font-size: 9pt; vertical-align: top;
        }
        .kopf-label { font-size: 7pt; color: #666; display: block; margin-bottom: 1pt; }
        .kopf-value { font-size: 10pt; font-weight: bold; white-space: nowrap; }

        /* Abschnitt-Titel */
        .sec-nr   { font-size: 9.5pt; font-weight: bold; margin: 8pt 0 2pt; }
        .sec-desc { font-size: 8.5pt; margin: 0 0 4pt; line-height: 1.4; }

        /* AA-Tabelle */
        table.aa-table { width: 100%; border-collapse: collapse; margin: 4pt 0 7pt; }
        table.aa-table th {
            background: #eee; font-weight: bold; text-align: left;
            padding: 4pt 6pt; border: 1px solid #999; font-size: 8.5pt;
        }
        table.aa-table td { border: 1px solid #999; padding: 4pt 6pt; font-size: 9pt; }
        .aa-table .row-strike td:first-child { text-decoration: line-through; color: #aaa; }
        .badge-gestrichen {
            display: inline-block; background: #fee2e2; color: #b91c1c;
            font-weight: bold; font-size: 7.5pt;
            border: 1px solid #fca5a5; border-radius: 2pt; padding: 1pt 4pt;
        }
        .badge-schwerpunkt {
            display: inline-block; background: #dbeafe; color: #1e40af;
            font-weight: bold; font-size: 7.5pt;
            border: 1px solid #93c5fd; border-radius: 2pt; padding: 1pt 4pt;
        }
        .badge-verbleibt { color: #555; font-size: 8.5pt; }

        /* Hinweis-Box */
        .hinweis-box {
            background: #f8f8f8; border: 1pt solid #ddd; border-radius: 2pt;
            padding: 4pt 6pt; font-size: 8.5pt; margin: 7pt 0 5pt;
        }

        /* Unterschriften + Kontakt (DomPDF: border-bottom auf td, nicht border-top auf div) */
        table.sig-kontakt {
            width: 100%; border-collapse: collapse; margin-top: 14pt;
        }
        table.sig-kontakt td { font-size: 8.5pt; color: #333; padding: 0; vertical-align: bottom; }
        td.linie { border-bottom: 1pt solid #555; }
        td.leer  { height: 18pt; }
        td.abst  { height: 10pt; }
        td.pad   { padding-right: 12pt; }
    </style>
</head>
<body>

@php
    $settings      = $settings ?? [];
    $anmeldeDatum  = $settings['zusatzpruefungen.anmeldeschluss_date'] ?? null;
    $anmeldeTime   = $settings['zusatzpruefungen.anmeldeschluss_time'] ?? '08:00';
    $anmeldestelle = $settings['zusatzpruefungen.anmeldestelle'] ?? 'Oberstufensekretariat';
    $pruefungVon   = $settings['zusatzpruefungen.pruefung_von'] ?? null;
    $pruefungBis   = $settings['zusatzpruefungen.pruefung_bis'] ?? null;
    $ergebnisInfo  = $settings['zusatzpruefungen.ergebnis_info'] ?? '';
    $fmtDate = fn(?string $d) => $d
        ? \Carbon\Carbon::parse($d)->locale('de')->translatedFormat('l, d.m.Y') : '–';
    $fmtDateShort = fn(?string $d) => $d
        ? \Carbon\Carbon::parse($d)->locale('de')->translatedFormat('D, d.m.Y') : '–';
    $semLabels = [
        '12_1' => 'AA 12/1', '12_2' => 'AA 12/2',
        '13_1' => 'AA 13/1', '13_2' => 'AA 13/2',
    ];
    $row      = $studentDataRows[0] ?? null;
    $computed = $row ? $row['computed'] : null;
    $curTotal = $row ? $row['curTotal'] : null;
    $zpExams  = $computed ? ($computed['zpExams'] ?? []) : [];
    $zpCount  = count($zpExams);
    $passedCombs = $computed ? ($computed['passedCombinations'] ?? []) : [];
    $passedN2    = $computed ? ($computed['passedCombinationsN2'] ?? []) : [];
    $allFixed    = $computed ? ($computed['allFixedScoresEntered'] ?? false) : false;
@endphp

{{-- ═══ SEITE 1: FAHRPLAN + MINDESTPUNKTE ════════════════════════ --}}

@if($school)
    <div class="school-name">{{ $school->name }} · Oberstufenkoordination</div>
@endif
<div class="student-name">
    {{ $prueflingName }}
    @if($ntaProzent > 0)
        <span class="nta-badge">NTA +{{ $ntaProzent }}&thinsp;%</span>
    @endif
</div>
<div class="subtitle">„Fahrplan" zur mündlichen Zusatzprüfung {{ $examPeriod->year }}</div>

<div class="intro-box">
    @if($improvementOnly ?? false)
        Sie haben das Abitur bereits bestanden. Eine Verbesserung Ihres Prüfungsergebnisses ist
        durch die mündliche Zusatzprüfung in folgendem Fach möglich:
    @else
        Sie haben die Abiturprüfung bisher leider noch nicht bestanden. Daher ist eine Anmeldung
        zur mündlichen Zusatzprüfung in folgenden Fächern sinnvoll:
    @endif
</div>

{{-- Fahrplan-Tabelle: ohne "Mind. Mündlich", mit Prüfungszeit wenn bekannt --}}
<table class="main-table">
    <thead>
        <tr>
            <th style="width:28%;">Prüfung</th>
            <th style="width:8%;text-align:center;">Reihenf.</th>
            <th style="width:12%;text-align:center;">Note schriftl.</th>
            <th style="width:22%;">Prüfungstermin</th>
            <th>Kontakt Prüfer</th>
        </tr>
    </thead>
    <tbody>
        @forelse($pruefungen as $pruefung)
        @php
            $subjectName = $pruefung->studentExam->getSubjectName() ?? '–';
            $courseCode  = $pruefung->istExtern() ? '–' : $pruefung->studentExam->getSubjectLabel();
            $isMath      = $pruefung->studentExam->istMathematik();
            $erstpruefer = $pruefung->pruefer->firstWhere('role', 'erstpruefer')?->teacher;
            $excluded    = $pruefung->excluded_semester;
            $focus       = $pruefung->focus_semester;

            // Streichung/Schwerpunkt auf zwei Zeilen
            $zeile1 = '';
            $zeile2 = '';
            if ($isMath && $excluded) {
                $zeile1 = ucfirst($excluded) . ' gestrichen';
            } elseif (!$isMath && $excluded && isset($semLabels[$excluded])) {
                $zeile1 = $semLabels[$excluded] . ' gestrichen';
                if ($focus && isset($semLabels[$focus])) {
                    $zeile2 = $semLabels[$focus] . ' = Schwerpunkt';
                }
            }

            // Prüfungszeit aus Assignment
            $asgn = $pruefung->assignment;
            $terminStr = '';
            if ($asgn) {
                $examStart = \Carbon\Carbon::createFromTimeString($asgn->start_time)
                    ->addMinutes($asgn->prep_duration_minutes);
                $examEnd   = $examStart->copy()->addMinutes($asgn->exam_duration_minutes);
                $dayDate   = $asgn->day?->date;
                $terminStr = $dayDate
                    ? $fmtDateShort($dayDate->toDateString()) . ', ' . $examStart->format('H:i') . '–' . $examEnd->format('H:i') . ' Uhr'
                    : $examStart->format('H:i') . '–' . $examEnd->format('H:i') . ' Uhr';
            }

            // Prüfer: vollständiger Nachname
            $pruefer_name = '';
            if ($erstpruefer) {
                $pruefer_name = $erstpruefer->lastname;
                if ($erstpruefer->firstname) {
                    $pruefer_name .= ', ' . mb_substr($erstpruefer->firstname, 0, 1) . '.';
                }
            }
            $contactParts = [];
            if ($erstpruefer) {
                if ($erstpruefer->contact_phone) $contactParts[] = $erstpruefer->contact_phone;
                if ($erstpruefer->contact_email) $contactParts[] = $erstpruefer->contact_email;
                if ($erstpruefer->contact_bycs)  $contactParts[] = 'ByCS-Messenger';
            }
        @endphp
        <tr>
            <td>
                <strong>{{ $subjectName }}</strong>
                <span class="anm-info"> {{ $courseCode }}</span>
                @if($zeile1)
                    <br><span class="anm-info">{{ $zeile1 }}</span>
                @endif
                @if($zeile2)
                    <br><span class="anm-info">{{ $zeile2 }}</span>
                @endif
            </td>
            <td style="text-align:center;">{{ $pruefung->sort_order ?: '–' }}</td>
            <td style="text-align:center;">
                {{ $pruefung->written_points !== null ? $pruefung->written_points . ' Pkt.' : '–' }}
            </td>
            <td>
                @if($terminStr)
                    {{ $terminStr }}
                @else
                    <span style="color:#999;">noch offen</span>
                @endif
            </td>
            <td>
                @if($erstpruefer)
                    <strong>{{ $pruefer_name }}</strong>
                    @if(count($contactParts) > 0)
                        @foreach($contactParts as $cp)
                            <br><span class="anm-info">{{ $cp }}</span>
                        @endforeach
                    @endif
                @else
                    <span style="color:#999;">–</span>
                @endif
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center;color:#999;">Keine Zusatzprüfungen eingetragen.</td>
        </tr>
        @endforelse
    </tbody>
</table>

{{-- Mindestpunkte-Kalkulator (ohne Fach-Mindestanforderungen) --}}
@if($istExtern)
    {{-- Externe Bewerber bestehen nach § 62 GSO, nicht nach § 61: ihre
         Gesamtqualifikation entsteht allein aus den acht Prüfungen, ohne
         Halbjahresleistungen. Bis zum Bau des ExterneMindestpunkteService stand
         hier nur ein Hinweis; jetzt steht hier die Rechnung, die dazu gehört. --}}
    @include('exports.partials.zusatzpruef-mp-62', [
        'externe62' => $row['externe62'] ?? $externe62 ?? null,
    ])
@elseif($improvementOnly ?? false)
    @php
        // Verbesserungsmodus: für jede ZP-Fach mit eingetragener schriftlicher
        // Note alle kombinierten Stufen oberhalb der schriftlichen Note auflisten,
        // gefiltert auf das Ziel aus dem Notenbescheid (improvementPointsNeeded).
        // Gleiche Formel wie im Modal (mpImprovementRows / calcZpContribution).
        $improvementExams = collect($row ? $row['exams'] : [])->filter(fn($e) => $e['isZusatzpruefung'] ?? false)->values();
    @endphp
    @foreach($improvementExams as $impExam)
        @php
            $written = $impExam['writtenPoints'] ?? null;
            $needed  = $impExam['improvementPointsNeeded'] ?? null;
            $rows = [];
            if ($written !== null) {
                for ($target = $written + 1; $target <= 15; $target++) {
                    for ($oral = 0; $oral <= 15; $oral++) {
                        $raw      = (2 * $written + $oral) / 3;
                        $combined = $raw < 1 ? 0 : (int) round($raw);
                        if ($combined === $target) {
                            $contrib = (int) round((2 * $written + $oral) * 4 / 3);
                            $delta   = $contrib - $written * 4;
                            if ($needed === null || $delta >= $needed) {
                                $rows[] = ['minOral' => $oral, 'combined' => $target, 'contrib' => $contrib, 'delta' => $delta];
                            }
                            break;
                        }
                    }
                }
            }
        @endphp
        <div class="mp-box">
            <div class="mp-title">
                Mindestpunkte-Kalkulator · GSO §61 · {{ $impExam['subject'] }}
                @if($written !== null)
                    &nbsp;·&nbsp; Schriftlich: <span class="cok">{{ $written }}</span> Pkt.
                    = {{ $written * 4 }} (×4)
                @endif
                @if($needed !== null)
                    &nbsp;·&nbsp; Ziel: <span class="cok">+{{ $needed }}</span> Pkt.
                @endif
            </div>
            @if($written === null)
                <p style="font-size:8pt;color:#6b7280;margin:2pt 0;">
                    Schriftliche Note noch nicht eingetragen.
                </p>
            @elseif(empty($rows) && $needed === null)
                <p style="font-size:8pt;color:#6b7280;margin:2pt 0;">
                    Keine Verbesserung möglich (schriftlich bereits 15 Pkt.).
                </p>
            @elseif(empty($rows) && $needed !== null)
                <p style="font-size:8pt;color:#dc2626;margin:2pt 0;">
                    Ziel von +{{ $needed }} Pkt. ist auch mit 15 Pkt. mündlich nicht erreichbar.
                </p>
            @else
                <table class="mp-table">
                    <thead>
                        <tr>
                            <th class="text-center">Mind. mündlich</th>
                            <th class="text-center">Komb. (0–15)</th>
                            <th class="text-center">Beitrag (×4)</th>
                            <th class="text-center">Δ gegenüber schr.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $r)
                        <tr class="row-pass">
                            <td class="text-center font-mono font-bold">{{ $r['minOral'] }}</td>
                            <td class="text-center font-mono">{{ $r['combined'] }}</td>
                            <td class="text-center font-mono font-bold">{{ $r['contrib'] }}</td>
                            <td class="text-center font-mono font-bold cok">+{{ $r['delta'] }} Pkt.</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
@elseif($allFixed && $zpCount > 0)
<div class="mp-box">
    <div class="mp-title">
        Mindestpunkte-Kalkulator · GSO §61
        @if($curTotal !== null)
            &nbsp;·&nbsp; Aktuelle Abiturprüfungspunkte (vor ZP):
            <span class="{{ $curTotal >= 100 ? 'cok' : 'cnok' }}">{{ $curTotal }}/300</span>
            <span style="color:#6b7280;font-size:7pt;">(Ziel: ≥ 100)</span>
        @endif
    </div>

    @include('exports.partials.zusatzpruef-mp-kombinationstabelle', [
        'zpExams'          => $zpExams,
        'top5'             => $computed['top5Combinations'] ?? [],
        'totalPassedCount' => $row['totalPassedCount'] ?? count($passedCombs),
        'svc'              => $service,
        'tableClass'       => 'mp-table',
    ])
</div>
@elseif(!$allFixed && $zpCount > 0)
<div class="mp-box">
    <div class="mp-title">Mindestpunkte-Kalkulator · GSO §61</div>
    <p style="font-size:8pt;color:#6b7280;margin:2pt 0;">
        Noch nicht alle Prüfungsnoten eingetragen — Kombinationstabelle nicht berechenbar.
    </p>
</div>
@endif

{{-- Info-Bullets --}}
<div class="info-section">
    <ul>
        <li>
            <strong>Anmeldung:</strong> Je Prüfung ein ausgefülltes Formblatt.
            @if($anmeldeDatum)
                Spätester Termin:
                <strong>{{ $fmtDate($anmeldeDatum) }}, {{ $anmeldeTime }} Uhr</strong>,
                {{ $anmeldestelle }}.
            @endif
        </li>
        <li>
            <strong>Prüfungstage:</strong>
            @if($pruefungVon && $pruefungBis)
                {{ $fmtDate($pruefungVon) }} bis {{ $fmtDate($pruefungBis) }}.
            @else
                Werden noch bekannt gegeben.
            @endif
            Genaue Zeiten auf mebis (ca. Montag 14:00 Uhr).
        </li>
        @if($ergebnisInfo)
        <li><strong>Ergebnis:</strong> {{ $ergebnisInfo }}</li>
        @endif
        <li>
            <strong>Abmeldung</strong> (falls nötig): Am Vortag schriftlich formlos
            (Datum, Name, Prüfung, Unterschrift). Andernfalls wird die Prüfung mit 0 Punkten bewertet.
        </li>
    </ul>
</div>

<div style="margin-top:5pt;font-size:7.5pt;color:#888;border-top:0.5pt solid #ccc;padding-top:2pt;">
    @if($school){{ $school->name }} · @endif
    Gedruckt: {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr
</div>

{{-- ═══ SEITEN 2+: ANMELDEZETTEL (ein Blatt pro Prüfung) ════════════ --}}

@foreach($pruefungen as $pruefung)
@php
    $subject     = $pruefung->studentExam->getSubjectName() ?? '–';
    $courseCode  = $pruefung->istExtern() ? '–' : $pruefung->studentExam->getSubjectLabel();
    $isMath      = $pruefung->studentExam->istMathematik();
    $erstpruefer = $pruefung->pruefer->firstWhere('role', 'erstpruefer')?->teacher;
    $excluded    = $pruefung->excluded_semester;
    $focus       = $pruefung->focus_semester;
    // Im Kopfraster: Kürzel (passt immer), in Unterschriftszeile: Nachname
    $kursleiterKuerzel  = $erstpruefer?->shortcode ?? '–';
    $kursleiterNachname = $erstpruefer ? $erstpruefer->lastname : '–';
@endphp

<div class="page-break anm-page">

<div class="anm-header">Meldung zur mündlichen Zusatzprüfung {{ $examPeriod->year }}</div>
<div class="anm-subtitle">
    @if($isMath)nur Mathematik@else Bitte ein Blatt pro Fach ausfüllen!@endif
</div>

{{-- Kopfzeilen-Raster: Kürzel im Kursleiter-Feld --}}
<table class="kopf-table">
    <tr>
        <td style="width:35%;">
            <span class="kopf-label">Prüfling: Name, Vorname</span>
            <span class="kopf-value">{{ $prueflingName }}</span>
        </td>
        <td style="width:30%;">
            <span class="kopf-label">Prüfungsfach</span>
            <span class="kopf-value">{{ $subject }}</span>
        </td>
        <td style="width:13%;">
            <span class="kopf-label">Kursbezeichnung</span>
            <span class="kopf-value">{{ $courseCode }}</span>
        </td>
        <td style="width:22%;">
            <span class="kopf-label">Kursleiter</span>
            <span class="kopf-value">{{ $kursleiterKuerzel }}</span>
        </td>
    </tr>
</table>

@if(!$isMath)
<div class="sec-nr">1) Ausschluss eines Ausbildungsabschnittes</div>
<div class="sec-desc">
    Entweder der AA 12/1 oder der AA 12/2 kann ausgeschlossen werden.
    Streichen Sie in der untenstehenden Tabelle sauber und eindeutig den entsprechenden Ausbildungsabschnitt!
</div>
<table class="aa-table">
    <thead><tr><th style="width:28%;">Ausbildungsabschnitt</th><th>Status / Schwerpunkt</th></tr></thead>
    <tbody>
        @foreach(['12_1','12_2','13_1','13_2'] as $sem)
        <tr class="{{ $excluded === $sem ? 'row-strike' : '' }}">
            <td>{{ $semLabels[$sem] }}</td>
            <td>
                @if($excluded === $sem) <span class="badge-gestrichen">GESTRICHEN</span>
                @elseif($focus === $sem) <span class="badge-schwerpunkt">Schwerpunktsemester (1. Prüfungsteil)</span>
                @else <span class="badge-verbleibt">verbleibt</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="sec-nr">2) Erster Prüfungsteil (ca. 10 Minuten)</div>
<div class="sec-desc">
    Einer der verbleibenden Ausbildungsabschnitte wird komplett als Prüfungsschwerpunkt gewählt.
    Die Aufgaben sollen in möglichst freier Rede beantwortet werden.<br>
    <strong>Schwerpunktsemester:</strong>
    @if($focus && isset($semLabels[$focus]))
        <span class="badge-schwerpunkt">{{ $semLabels[$focus] }}</span>
    @else
        <em style="color:#999;">nicht eingetragen</em>
    @endif
</div>

<div class="sec-nr">3) Zweiter Prüfungsteil (ca. 10 Minuten)</div>
<div class="sec-desc">
    Prüfungsgespräch über die Lerninhalte der beiden verbleibenden Ausbildungsabschnitte.
</div>

@else
<div class="sec-nr">1) Ausschluss eines Stoffgebietes</div>
<div class="sec-desc">
    Eines der Stoffgebiete Stochastik oder Geometrie kann ausgeschlossen werden.
</div>
<table class="aa-table">
    <thead><tr><th style="width:35%;">Stoffgebiet</th><th>Status</th></tr></thead>
    <tbody>
        <tr><td>Analysis</td><td><strong>verpflichtend</strong></td></tr>
        <tr class="{{ $excluded === 'stochastik' ? 'row-strike' : '' }}">
            <td>Stochastik</td>
            <td>@if($excluded === 'stochastik') <span class="badge-gestrichen">GESTRICHEN</span>
                @else <span class="badge-verbleibt">verbleibt</span> @endif</td>
        </tr>
        <tr class="{{ $excluded === 'geometrie' ? 'row-strike' : '' }}">
            <td>Geometrie</td>
            <td>@if($excluded === 'geometrie') <span class="badge-gestrichen">GESTRICHEN</span>
                @else <span class="badge-verbleibt">verbleibt</span> @endif</td>
        </tr>
    </tbody>
</table>

<div class="sec-nr">2) Ablauf der Prüfung</div>
<div class="sec-desc">
    Beantwortung der Fragen, die den verbleibenden beiden Stoffgebieten entnommen sind,
    in möglichst freier Rede.<br>
    Der Prüfling erhält für die 20-minütige Vorbereitungszeit schriftlich Aufgaben zu den Prüfungsteilen.
    @if($ntaProzent > 0)
        <br><strong>NTA +{{ $ntaProzent }}&thinsp;%:</strong> verlängerte Vorbereitungszeit.
    @endif
</div>
@endif

{{-- Hinweis-Box: nur bei Nicht-Mathe (bei Mathe steht der Text schon in Abschnitt 2) --}}
@if(!$isMath)
<div class="hinweis-box">
    Der Prüfling erhält für die <strong>20-minütige Vorbereitungszeit</strong> schriftlich Aufgaben zu den Prüfungsteilen.
    @if($ntaProzent > 0)
        &nbsp;<strong>NTA +{{ $ntaProzent }}&thinsp;%:</strong> verlängerte Vorbereitungszeit.
    @endif
</div>
@endif

<p style="margin-top:8pt;font-size:8.5pt;">
    Ich weiß, dass ich mich mit der jeweiligen Kursleiterin / dem jeweiligen Kursleiter umgehend in Verbindung setzen muss.
    Diese/r gibt dieses Formular an die OSK weiter.
</p>

{{-- Unterschriften + Kontakt --}}
<table class="sig-kontakt">
    {{-- Datum + Unterschrift Prüfling --}}
    <tr>
        <td style="width:16%;white-space:nowrap;padding-right:4pt;vertical-align:bottom;">{{ filled($school?->city) ? $school->city . ', den' : 'Ort, Datum' }}</td>
        <td class="linie pad" style="width:32%;">&nbsp;</td>
        <td style="width:4%;">&nbsp;</td>
        <td class="linie" style="width:48%;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="2">&nbsp;</td>
        <td>&nbsp;</td>
        <td style="padding-top:2pt;">{{ $prueflingName }}</td>
    </tr>
    {{-- Abstand --}}
    <tr><td colspan="4" class="abst"></td></tr>
    {{-- Linie für Kursleiter (volle Breite) --}}
    <tr>
        <td class="linie" colspan="4">&nbsp;</td>
    </tr>
    {{-- Beschriftung Kursleiter --}}
    <tr>
        <td colspan="4" style="padding-top:2pt;">Unterschrift des Kursleiters ({{ $kursleiterNachname }})</td>
    </tr>
    {{-- Abstand --}}
    <tr><td colspan="4" class="abst"></td></tr>
    {{-- Telefonnummer: Text oben, Linie darunter --}}
    <tr>
        <td colspan="4">Ich bin unter folgender Telefonnummer sicher zu erreichen:</td>
    </tr>
    <tr>
        <td class="linie" colspan="4">&nbsp;</td>
    </tr>
    {{-- Abstand --}}
    <tr><td colspan="4" class="abst"></td></tr>
    {{-- E-Mail: Text oben, Linie darunter --}}
    <tr>
        <td colspan="4">E-Mail-Adresse:</td>
    </tr>
    <tr>
        <td class="linie" colspan="4">&nbsp;</td>
    </tr>
</table>

</div>{{-- /page-break --}}
@endforeach

</body>
</html>
