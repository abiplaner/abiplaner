@php
/*
 * Niederschriften über die mündliche Zusatzprüfung — A4 hoch, zwei Seiten je Prüfling.
 *
 *   Seite 1: Deckblatt — Daten, Prüfungsausschuss, Ergebnis, Unterschriften
 *   Seite 2: Verlauf   — Aufgabenstellung, Fragen/Antworten, Bemerkungen
 *
 * Aufbau bewusst analog zu exports/kolloquium-niederschrift-pdf.blade.php,
 * aber ohne dessen Booklet-Faltung: die Zusatzprüfung ist kürzer, ein
 * doppelseitiges A4-Blatt genügt.
 *
 * DomPDF-Eigenheiten:
 *   - KEINE expliziten height-Angaben auf Seiten-Containern → sonst Phantomseiten.
 *   - page-break-after: always auf jeder Seite außer der allerletzten.
 *
 * Erwartet: $entries (array, siehe ZusatzpruefungController::exportNiederschriftPdf),
 *           $examPeriod, $school
 */
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Niederschriften mündliche Zusatzprüfung</title>
<style>
@page { size: A4 portrait; margin: 14mm 15mm 12mm 15mm; }
* { box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9pt; line-height: 1.3; color: #000;
    margin: 0; padding: 0;
}

.page       { page-break-after: always; }
.page-last  { page-break-after: auto;   }

.bold  { font-weight: bold; }
.small { font-size: 7.5pt; }
.muted { color: #555; }

/* ── Kopf ──────────────────────────────────────────────────── */
.head-tbl   { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
.head-tbl td { vertical-align: top; }
.school-name { font-size: 10pt; font-weight: bold; }
.school-sub  { font-size: 7.5pt; line-height: 1.4; }
/* Der Jahrgangskasten ist eine eigene Zelle der Kopfzeilen-Tabelle:
   DomPDF rahmt weder inline-Elemente noch gefloatete Blöcke zuverlässig. */
.jahr-cell {
    border: 1.2pt solid #000; padding: 1mm 3mm;
    text-align: center; width: 32mm;
}
.jahr-lbl { font-size: 7pt; display: block; }
.jahr-val { font-size: 12pt; font-weight: bold; display: block; }

.doc-title { font-size: 12pt; font-weight: bold; text-align: center; margin-bottom: 0.8mm; }
.doc-sub   { font-size: 9pt;  text-align: center; margin-bottom: 4mm; }
.page-hd   { font-size: 8pt;  text-align: center; margin-bottom: 2mm; }

.section-hd {
    font-size: 9pt; font-weight: bold; text-decoration: underline;
    margin: 4mm 0 1.5mm;
}

/* ── Datenfeld-Tabelle ─────────────────────────────────────── */
.data-tbl { width: 100%; border-collapse: collapse; margin-bottom: 1mm; }
.data-tbl td { vertical-align: bottom; padding: 1mm 0; font-size: 9pt; }
.lbl    { white-space: nowrap; padding-right: 2mm; width: 52mm; }
.val    { border-bottom: 0.4pt solid #000; }
.filled { font-weight: bold; }

/* ── Prüfungsausschuss ─────────────────────────────────────── */
.aus-tbl { width: 100%; border-collapse: collapse; }
.aus-tbl td { border: 0.4pt solid #000; padding: 1.2mm 2mm; font-size: 8.5pt; vertical-align: middle; }
.aus-role { width: 42mm; background: #f2f2f2; }
.aus-name { height: 6mm; }

/* ── Ergebnis ──────────────────────────────────────────────── */
.erg-tbl { width: 100%; border-collapse: collapse; margin-top: 1mm; }
.erg-tbl td { padding: 1.2mm 0; font-size: 9pt; vertical-align: bottom; }
.erg-lbl  { width: 78mm; padding-right: 2mm; }
.erg-val  { border-bottom: 0.4pt solid #000; width: 22mm; text-align: center; }
.erg-unit { width: 14mm; padding-left: 2mm; white-space: nowrap; }
.erg-rest { border-bottom: 0.4pt solid #000; }
.formel   { font-size: 7.5pt; color: #333; margin-top: 1mm; }

/* ── Unterschriften ────────────────────────────────────────── */
.sig-tbl { width: 100%; border-collapse: collapse; margin-top: 16mm; }
.sig-tbl td { font-size: 7.5pt; text-align: center; padding: 0 2mm; vertical-align: bottom; }
.sig-line { border-top: 0.4pt solid #000; display: block; margin-bottom: 0.6mm; }

/* ── Schreiblinien ─────────────────────────────────────────── */
.wl { border-bottom: 0.35pt solid #888; height: 6.2mm; display: block; }

/* ── Fragen / Antworten ────────────────────────────────────── */
.qa-tbl { width: 100%; border-collapse: collapse; }
.qa-hdr td { font-size: 8pt; font-weight: bold; padding-bottom: 0.8mm; }
.qa-tbl .qa-f { width: 38%; border-bottom: 0.35pt solid #888; height: 6.2mm; vertical-align: bottom; }
.qa-tbl .qa-g { width: 3%; }
.qa-tbl .qa-a { width: 59%; border-bottom: 0.35pt solid #888; height: 6.2mm; vertical-align: bottom; }

.hinweis {
    font-size: 7.5pt; color: #333; border: 0.4pt solid #999;
    background: #fafafa; padding: 1.5mm 2mm; margin-top: 3mm;
}
</style>
</head>
<body>

@foreach($entries as $e)
@php $isLast = $loop->last; @endphp

{{-- ══════════════════ SEITE 1 — Deckblatt ══════════════════ --}}
<div class="page">

    <table class="head-tbl">
        <tr>
            <td>
                <div class="school-name">{{ $e['schoolName'] }}</div>
                <div class="school-sub muted">{{ $e['schoolSub'] }}</div>
            </td>
            <td class="jahr-cell">
                <span class="jahr-lbl">Abiturjahrgang</span>
                <span class="jahr-val">{{ $e['year'] }}</span>
            </td>
        </tr>
    </table>

    <div class="doc-title">Niederschrift</div>
    <div class="doc-sub">über die mündliche Zusatzprüfung</div>

    <table class="data-tbl">
        <tr>
            <td class="lbl">Schüler/in:</td>
            <td class="val filled">{{ $e['nachname'] }}, {{ $e['vorname'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Prüfungsfach:</td>
            <td class="val filled">{{ $e['fachStr'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Prüfungstag:</td>
            <td class="val filled">{{ $e['datumStr'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Vorbereitung:</td>
            <td class="val filled">{{ $e['prepStart'] }}&#160;–&#160;{{ $e['prepEnd'] }}&#160;Uhr&#160;<span class="small muted">({{ $e['prepMinutes'] }}&#160;Min.)</span></td>
        </tr>
        <tr>
            <td class="lbl">Prüfung:</td>
            <td class="val filled">{{ $e['examStart'] }}&#160;–&#160;{{ $e['examEnd'] }}&#160;Uhr&#160;<span class="small muted">({{ $e['examMinutes'] }}&#160;Min.)</span></td>
        </tr>
        <tr>
            <td class="lbl">Vorbereitungsraum:</td>
            <td class="val filled">{{ $e['prepRoom'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Prüfungsraum:</td>
            <td class="val filled">{{ $e['examRoom'] }}</td>
        </tr>
    </table>

    <div class="section-hd">Prüfungsausschuss</div>
    <table class="aus-tbl">
        <tr>
            <td class="aus-role">Vorsitz</td>
            <td class="aus-name">{{ $e['vorsitz'] }}</td>
        </tr>
        <tr>
            <td class="aus-role">Prüfer/in</td>
            <td class="aus-name filled">{{ $e['erstpruefer'] }}</td>
        </tr>
        <tr>
            <td class="aus-role">Schriftführer/in</td>
            <td class="aus-name filled">{{ $e['zweitpruefer'] }}</td>
        </tr>
        @if($e['weitere'] !== '')
        <tr>
            <td class="aus-role">Weitere Anwesende</td>
            <td class="aus-name">{{ $e['weitere'] }}</td>
        </tr>
        @endif
    </table>

    <div class="section-hd">Ergebnis der Prüfung</div>
    <table class="erg-tbl">
        <tr>
            <td class="erg-lbl">Ergebnis der schriftlichen Abiturprüfung:</td>
            <td class="erg-val filled">{{ $e['writtenPointsStr'] }}</td>
            <td class="erg-unit">Punkte</td>
        </tr>
        <tr>
            <td class="erg-lbl">Ergebnis der mündlichen Zusatzprüfung (0&#160;–&#160;15):</td>
            <td class="erg-val">&#160;</td>
            <td class="erg-unit">Punkte</td>
        </tr>
        <tr>
            <td class="erg-lbl">in Worten:</td>
            <td class="erg-rest" colspan="2">&#160;</td>
        </tr>
        <tr>
            <td class="erg-lbl bold">Endergebnis im Prüfungsfach:</td>
            <td class="erg-val">&#160;</td>
            <td class="erg-unit">Punkte</td>
        </tr>
    </table>
    <div class="formel">
        Ermittlung des Endergebnisses: (2&#160;×&#160;schriftliches Ergebnis&#160;+&#160;mündliches Ergebnis)&#160;:&#160;3,
        gerundet; auf die Punktzahl&#160;1 wird nicht aufgerundet.
    </div>

    @if($e['improvementOnly'])
    <div class="hinweis">
        Diese Zusatzprüfung dient ausschließlich der Notenverbesserung.
    </div>
    @endif

    <table class="sig-tbl">
        <tr>
            <td style="width:45%"><span class="sig-line">&#160;</span>Prüfer/in</td>
            <td style="width:10%"></td>
            <td style="width:45%"><span class="sig-line">&#160;</span>Schriftführer/in</td>
        </tr>
    </table>

</div>{{-- /Seite 1 --}}

{{-- ══════════════════ SEITE 2 — Verlauf ══════════════════ --}}
<div class="{{ $isLast ? 'page-last' : 'page' }}">

    <div class="page-hd">
        Niederschrift über die mündliche Zusatzprüfung&#160;·&#160;{{ $e['fachStr'] }}&#160;·&#160;{{ $e['datumStr'] }}
    </div>
    <div style="font-size:9pt; margin-bottom:2.5mm;">
        Schüler/in:&#160;<span class="bold">{{ $e['nachname'] }}, {{ $e['vorname'] }}</span>
    </div>

    <div class="section-hd" style="margin-top:0;">Aufgabenstellung / Prüfungsgegenstand</div>
    @for($i = 0; $i < 3; $i++)
    <span class="wl"></span>
    @endfor

    <div class="section-hd">Verlauf der Prüfung</div>
    <table class="qa-tbl">
        <tr class="qa-hdr">
            <td class="qa-f">Fragen</td>
            <td class="qa-g"></td>
            <td class="qa-a">Antworten</td>
        </tr>
        {{-- Zeilenzahl ist auf genau eine Seite abgestimmt: mehr Zeilen und die
             Bemerkungen rutschen auf eine dritte Seite. --}}
        @for($i = 0; $i < 22; $i++)
        <tr><td class="qa-f"></td><td class="qa-g"></td><td class="qa-a"></td></tr>
        @endfor
    </table>

    <div class="section-hd">Bemerkungen zur Bewertung</div>
    @for($i = 0; $i < 4; $i++)
    <span class="wl"></span>
    @endfor

</div>{{-- /Seite 2 --}}

@endforeach

</body>
</html>
