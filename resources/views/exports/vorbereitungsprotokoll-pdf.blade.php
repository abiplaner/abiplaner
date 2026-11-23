<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Vorbereitungsprotokoll – {{ $examPeriod->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #000;
            background: #fff;
        }
        .page {
            page-break-before: always;
            padding: 10mm 13mm 8mm 13mm;
        }
        .page:first-child { page-break-before: avoid; }

        /* ── Kopfzeile ─────────────────────────────────────── */
        .header-outer {
            border-bottom: 2px solid #000;
            padding-bottom: 2.5mm;
            margin-bottom: 2.5mm;
        }
        .header-top {
            display: table;
            width: 100%;
        }
        .header-left  { display: table-cell; vertical-align: top; width: 55%; }
        .header-right { display: table-cell; vertical-align: top; width: 45%; text-align: right; }

        .abitur-label {
            font-size: 13pt;
            font-weight: bold;
            text-decoration: underline;
        }
        .school-name { font-size: 9pt; font-weight: bold; }

        .form-title {
            text-align: center;
            font-size: 12.5pt;
            font-weight: normal;
            margin: 2mm 0 2mm 0;
        }

        /* Meta-Zeile: Datum | Zeitslot + Uhrzeit | Raum */
        .meta-row {
            display: table;
            width: 100%;
            margin-top: 1.5mm;
        }
        .meta-cell { display: table-cell; vertical-align: middle; }
        .meta-label { font-size: 8pt; color: #555; }
        .meta-value { font-weight: bold; font-size: 10.5pt; }
        .meta-small { font-size: 8pt; color: #333; }

        /* Zeitslot-Zeile */
        .slot-row {
            display: table;
            width: 100%;
            margin-top: 2mm;
            background: #f0f0f0;
            border: 1px solid #bbb;
            border-radius: 2px;
            padding: 1.5mm 3mm;
        }
        .slot-badge {
            display: table-cell;
            vertical-align: middle;
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            width: 35%;
        }
        .slot-time {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            width: 40%;
        }
        .slot-room {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 25%;
        }

        /* ── Haupttabelle ────────────────────────────────── */
        table.main {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 2.5mm;
            font-size: 8.5pt;
        }
        table.main th {
            border: 1px solid #000;
            padding: 1.2mm 1.5mm;
            font-weight: bold;
            font-size: 8pt;
            text-align: center;
            background: #fff;
        }
        table.main td {
            border: 1px solid #000;
            padding: 0 1.5mm;
            vertical-align: middle;
            height: 5.8mm;
            overflow: hidden;
        }
        .col-nr    { width: 6%;  text-align: center; font-size: 8pt; color: #333; }
        .col-check { width: 8%;  text-align: center; font-size: 11pt; color: #999; line-height: 1; }
        .col-name  { width: 49%; font-size: 8.5pt; }
        .col-rem   { width: 37%; font-size: 8pt; }

        .sname     { font-weight: bold; }
        .nta-badge { font-size: 6pt; background: #ede9fe; color: #7c3aed;
                     padding: 0 2px; border-radius: 2px; margin-left: 1.5mm; }

        /* ── Unterer Bereich ─────────────────────────────── */
        .bottom-wrap { margin-top: 2.5mm; }

        .special-label { font-size: 8.5pt; font-weight: bold; margin-bottom: 1mm; }
        table.special {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.special td {
            border: 1px solid #000;
            height: 7.5mm;
            padding: 1mm 2mm;
            font-size: 8.5pt;
        }

        /* Unterschrift */
        .sig-row {
            display: table;
            width: 100%;
            margin-top: 6mm;
        }
        .sig-cell { display: table-cell; vertical-align: bottom; }
        .sig-cell.wide  { width: 44%; }
        .sig-cell.gap   { width: 6%; }
        .sig-cell.right { width: 50%; }

        .sig-name-line {
            border-bottom: 1px solid #000;
            min-height: 5mm;
            padding-bottom: 0.5mm;
            font-size: 9pt;
        }
        .sup-prefill  { font-weight: bold; }
        .sig-sublabel { font-size: 7pt; color: #555; margin-top: 0.5mm; }

        /* Fußnote – direkt über der Unterschrift */
        .footer-note {
            margin-top: 3mm;
            font-size: 7.5pt;
            line-height: 1.5;
            border-top: 1px solid #bbb;
            padding-top: 1.5mm;
        }
        .footer-bold { font-weight: bold; display: block; }
        .footer-hl   { background: #ffff00; display: block; margin-top: 0.5mm; }
    </style>
</head>
<body>
@foreach($pages as $page)
<div class="page">

    {{-- ── Kopf ─────────────────────────────────────────── --}}
    <div class="header-outer">
        <div class="header-top">
            <div class="header-left">
                <span class="abitur-label">Abitur {{ $examPeriod->year }}</span>
            </div>
            <div class="header-right">
                @if($school)
                    <div class="school-name">{{ $school->name }}</div>
                @endif
            </div>
        </div>

        <div class="form-title">Vorbereitung auf die Colloquiumsprüfung</div>

        {{-- Zeile 1: Datum (links) + Raum (rechts) --}}
        <div style="display:table; width:100%; margin-top:1.5mm;">
            <div style="display:table-cell; vertical-align:middle; font-size:9pt;">
                <span class="meta-label">am&nbsp;</span>
                <span class="meta-value">{{ $page['date']->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</span>
            </div>
            <div style="display:table-cell; vertical-align:middle; text-align:right; font-size:9pt;">
                <span class="meta-label">Raum&nbsp;</span>
                <span class="meta-value">{{ $page['prepRoom']->room_number }}</span>
            </div>
        </div>

        {{-- Zeile 2: Zeitslot + Uhrzeit – eigene, hervorgehobene Zeile --}}
        <div class="slot-row">
            <div class="slot-badge">Zeitslot {{ $page['slotNumber'] }}</div>
            <div class="slot-time">
                <span class="meta-label">von&nbsp;</span><span class="meta-value">{{ $page['prepStart'] }}</span>
                <span style="margin:0 2.5mm; color:#555; font-size:12pt;">–</span>
                <span class="meta-label">bis&nbsp;</span><span class="meta-value">{{ $page['prepEnd'] }}</span>
                <span class="meta-label">&nbsp;Uhr</span>
            </div>
            <div class="slot-room">&nbsp;</div>
        </div>
    </div>

    {{-- ── Tabelle ────────────────────────────────────────── --}}
    @php $entries = $page['entries']; @endphp
    <table class="main">
        <thead>
            <tr>
                <th class="col-nr"    style="width:6%">Nr.</th>
                <th class="col-check" style="width:8%">ersch.</th>
                <th class="col-name"  style="width:49%; text-align:left;">Prüfling</th>
                <th class="col-rem"   style="width:37%; text-align:left;">Bemerkung</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 1; $i <= 20; $i++)
            @php $entry = $entries->get($i - 1); @endphp
            <tr>
                <td class="col-nr"    style="width:6%">{{ $i }}.</td>
                <td class="col-check" style="width:8%">☐</td>
                <td class="col-name"  style="width:49%">
                    @if($entry !== null)
                        @php $se = $entry['assignment']->studentExam; @endphp
                        <span class="sname">{{ $se->getStudentName() }}</span>
                        @if(!$se->isExternalApplicant())
                            <span style="font-size:8pt; color:#555; font-weight:normal;">({{ $se->course->subject->code ?? '–' }})</span>
                        @endif
                        @if($se->hatNachteilsausgleich())
                            <span class="nta-badge">NTA</span>
                        @endif
                    @endif
                </td>
                <td class="col-rem" style="width:37%">&nbsp;</td>
            </tr>
            @endfor
        </tbody>
    </table>

    {{-- ── Besonderheiten + Fußnote + Unterschrift ────────── --}}
    <div class="bottom-wrap">

        <div class="special-label">Besonderheiten:</div>
        <table class="special">
            <tr><td>&nbsp;</td></tr>
            <tr><td>&nbsp;</td></tr>
        </table>

        {{-- Fußnote --}}
        <div class="footer-note">
            <span class="footer-bold">Das Thema wurde den Prüflingen zur angegebenen Zeit im verschlossenen Umschlag überreicht.</span>
            <span class="footer-hl">Nicht ausgegebene Umschläge mit Themen wurden an den Prüfungsausschuss zurückgegeben.</span>
        </div>

        {{-- Unterschrift --}}
        <div class="sig-row">
            <div class="sig-cell wide">
                <div class="sig-name-line">
                    @if($page['supervisor'] !== null)
                        <span class="sup-prefill">{{ $page['supervisor']->lastname }}, {{ $page['supervisor']->firstname }}</span>
                        <span style="font-size:7.5pt; color:#555;"> ({{ $page['supervisor']->shortcode }})</span>
                    @endif
                </div>
                <div class="sig-sublabel">Name der Aufsicht (Druckbuchstaben)</div>
            </div>
            <div class="sig-cell gap">&nbsp;</div>
            <div class="sig-cell right">
                <div class="sig-name-line">&nbsp;</div>
                <div class="sig-sublabel">Unterschrift der aufsichtführenden Lehrkraft</div>
            </div>
        </div>

    </div>

</div>
@endforeach
</body>
</html>
