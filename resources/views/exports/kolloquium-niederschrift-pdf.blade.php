@php
/*
 * Kolloquiums-Niederschriften – A3 quer, doppelseitig, gefaltet zu A4-Heft.
 *
 * Booklet-Layout (Seitenzahlen nach dem Falten):
 *   A3 Vorderseite:  [ S.4 – Fragen/Antworten (Rückdeckel) ]  |  [ S.1 – Deckblatt ]
 *   A3 Rückseite:    [ S.2 – Prüfungsteil 1               ]  |  [ S.3 – Prüfungsteil 2 ]
 *
 * DomPDF-Eigenheiten:
 *   - KEINE expliziten height-Angaben auf Seiten-Containern → Phantomseiten!
 *   - Zwei Panels per Tabelle (100 % Breite, 2 × 50 %).
 *   - page-break-after: always NUR auf dem Vorderseiten-Div und
 *     auf dem Rückseiten-Div bei nicht-letztem Prüfling.
 *   - Genug Zeilen, damit der Inhalt die Seite natürlich füllt.
 */
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Niederschriften Kolloquiumsprüfung</title>
<style>
/* ── Seite ───────────────────────────────────────────────────── */
@page { size: A3 landscape; margin: 0; }
* { box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 8pt; line-height: 1.25; color: #000;
    margin: 0; padding: 0;
}

/* ── A3-Blatt: KEINE height-Angabe → kein Phantomseiten-Bug ─── */
.vs { page-break-after: always; }   /* Vorderseite: immer Umbruch danach   */
.rs { page-break-after: auto;   }   /* Rückseite letzter Prüfling: kein Umbruch */
.rs-break { page-break-after: always; } /* Rückseite nicht-letzter Prüfling */

/* ── Panel-Tabelle (zwei A4-Hälften nebeneinander) ─────────── */
.sheet-table { width: 100%; border-collapse: collapse; }
.panel {
    width: 50%;
    vertical-align: top;
    padding: 9mm 9mm 7mm 9mm;
}
.panel-right { border-left: 0.5pt dashed #bbb; }  /* Faltlinie */

/* ── Typografie ─────────────────────────────────────────────── */
.bold         { font-weight: bold; }
.uline        { text-decoration: underline; }
.small        { font-size: 7pt; }
.panel-title  { font-size: 9.5pt; font-weight: bold; text-align: center; margin-bottom: 0.5mm; }
.panel-sub    { font-size: 8.5pt; text-align: center; margin-bottom: 2mm; }
.pruefteil-hd { font-size: 7.5pt; text-align: center; margin-bottom: 1mm; }
.teil-label   { font-size: 8pt; font-weight: bold; margin-bottom: 1mm; }
.section-hd   { font-size: 8pt; font-weight: bold; text-decoration: underline;
                margin: 2mm 0 1mm; }

/* ── Deckblatt: Schulkopf + Woche-Box ──────────────────────── */
.deckblatt-top { width: 100%; border-collapse: collapse; margin-bottom: 1mm; }
.school-name   { font-size: 9pt; font-weight: bold; }
.school-sub    { font-size: 7pt; line-height: 1.4; }
.woche-box {
    border: 1.5pt solid #000; padding: 1mm 3mm;
    text-align: center; vertical-align: middle;
    min-width: 22mm;
}
.woche-val  { font-size: 14pt; font-weight: bold; display: block; }
.woche-lbl  { font-size: 7pt; display: block; }

/* ── Datenfeld-Tabelle ──────────────────────────────────────── */
.data-tbl { width: 100%; border-collapse: collapse; margin-bottom: 1.5mm; }
.data-tbl td { vertical-align: bottom; padding: 0.5mm 0; font-size: 7.5pt; }
.lbl  { white-space: nowrap; padding-right: 1.5mm; width: 44mm; }
.val  { border-bottom: 0.4pt solid #000; }
.filled { font-weight: bold; }

/* ── Themenkatalog des Kurses (Modul 4.7) ──────────────────── */
.topic-tbl { width: 100%; border-collapse: collapse; }
.topic-tbl td { font-size: 7pt; padding: 0.4mm 0; vertical-align: top; line-height: 1.25; }
.topic-sem   { width: 16mm; white-space: nowrap; color: #333; }
.topic-title { }

/* ── Schreiblinien ──────────────────────────────────────────── */
.wl    { border-bottom: 0.35pt solid #888; height: 5.6mm; display: block; }
.wl-sm { border-bottom: 0.35pt solid #888; height: 5.0mm; display: block; }

/* ── Zweispaltige Fragen / Antworten ────────────────────────── */
.qa-tbl { width: 100%; border-collapse: collapse; }
.qa-hdr td { font-size: 7pt; font-weight: bold; padding-bottom: 0.5mm; }
.qa-tbl .qa-f { width: 37%; border-bottom: 0.35pt solid #888;
                height: 5.5mm; vertical-align: bottom; }
.qa-tbl .qa-g { width: 3%;  }
.qa-tbl .qa-a { width: 60%; border-bottom: 0.35pt solid #888;
                height: 5.5mm; vertical-align: bottom; }

/* ── Kurzreferat-Kriterien (Standard) ──────────────────────── */
.krit-tbl { width: 100%; border-collapse: collapse; margin-bottom: 1mm; }
.krit-tbl td { vertical-align: bottom; font-size: 7.5pt; padding: 0.3mm 0; }
.krit-lbl { width: 54mm; padding-right: 1mm; }
.krit-val { border-bottom: 0.35pt solid #888; }

/* ── Bewertungsraster Sprachen ──────────────────────────────── */
.bew-tbl { width: 100%; border-collapse: collapse; margin: 1.5mm 0; font-size: 6.5pt; }
.bew-tbl th, .bew-tbl td {
    border: 0.4pt solid #000; padding: 0.8mm 1mm;
    text-align: center; vertical-align: top; line-height: 1.3;
}
.bew-tbl th { background: #f0f0f0; font-weight: bold; }
.bew-body td { height: 8mm; }

/* ── Ergebnis + Unterschriften ──────────────────────────────── */
.ergebnis { font-size: 7.5pt; margin-top: 1.5mm; }
.erg-line      { border-bottom: 0.4pt solid #000; display: inline-block; min-width: 38mm; }
.erg-line-long { border-bottom: 0.4pt solid #000; display: inline-block; min-width: 65mm; }
.sig-tbl { width: 100%; border-collapse: collapse; margin-top: 10mm; }
.sig-tbl td { font-size: 7pt; text-align: center; padding: 0 2mm; vertical-align: bottom; }
.sig-line { border-top: 0.4pt solid #000; display: block; margin-bottom: 0.5mm; }

/* ── Sprachkürzel-Checkboxen ────────────────────────────────── */
.lang-row  { text-align: center; font-size: 8.5pt; font-weight: bold;
             letter-spacing: 0.3pt; margin: 2mm 0; }
.lang-item { margin: 0 3mm; }
.cb-on     { font-size: 10pt; }

/* ── Teilnoten-Zeile ────────────────────────────────────────── */
.tnote { font-size: 7.5pt; margin-bottom: 0.5mm; }
.tnote-line { border-bottom: 0.4pt solid #000; display: inline-block; min-width: 22mm; }
</style>
</head>
<body>

@foreach($assignments as $assignment)
@php
    $student      = $assignment->studentExam?->student;
    $course       = $assignment->studentExam?->course;
    $subject      = $course?->subject;
    $examiner     = $assignment->studentExam?->examiner;
    $assessor     = $assignment->studentExam?->assessor;
    $slot         = $assignment->timeSlot;

    $nachname     = $student?->lastname  ?? '–';
    $vorname      = $student?->firstname ?? '–';
    $subjectName  = $subject?->name      ?? '–';
    $courseCode   = $course?->course_code ?? '–';
    $level        = $course?->level       ?? null;

    $datumStr     = $slot?->date ? \Carbon\Carbon::parse($slot->date)->format('d.m.Y') : '–';
    $examStart    = $slot?->exam_start_time ? substr((string)$slot->exam_start_time, 0, 5) : '–';
    $examEnd      = $slot?->exam_end_time   ? substr((string)$slot->exam_end_time, 0, 5)   : '–';
    $woche        = $assignment->exam_week ?? '–';

    // Amtsbezeichnung: geschlechtsspezifische Form via Teacher::genderedTitle()
    $examinerName = $examiner
        ? ($examiner->genderedTitle() . $examiner->lastname)
        : '–';
    $assessorName = $assessor
        ? ($assessor->genderedTitle() . $assessor->lastname)
        : '–';

    // Prüfungsraum
    $examRoom = $assignment->examRoom?->room_number ?? '–';

    // Vorbereitungsraum aus OralExamDayRoom (tag-spezifisch hat Vorrang)
    $slotDateStr = $slot?->date ? \Carbon\Carbon::parse($slot->date)->format('Y-m-d') : null;
    $dayPrep     = $slotDateStr ? ($dayPrepRoomsMap[$slotDateStr] ?? null) : null;
    $prepRooms   = $dayPrep ?? $globalPrepRooms;
    $prepRoom    = !empty($prepRooms) ? implode(', ', $prepRooms) : '–';

    // Sprachen-Formular: Fächer mit sprachlichem Bewertungsraster
    $sprachCodes  = ['E', 'F', 'L', 'Sp', 'It', 'Ru', 'Gr', 'Ch', 'D'];
    $isSprachen   = in_array($subject?->code ?? '', $sprachCodes);

    // Level-Anzeige: Untis-Codes in lesbare Bezeichnungen übersetzen
    $levelDisplay = match($level) {
        'erhoeht', 'erhoht', 'eA', 'erhöht' => 'erhöht',
        'grundlegend', 'gA'                  => 'grundlegend',
        default                               => $level,
    };
    $fachStr      = $subjectName . ($levelDisplay ? ' (' . $levelDisplay . ')' : '') . ' · ' . $courseCode;
    $year         = $examPeriod->year ?? date('Y');
    $isLast       = $loop->last;
    $schoolName   = $school?->name ?? '';

    // ── Modul 4.7: Kopplung und Themenkatalog ──────────────────────────
    // Gekoppelte Prüflinge werden von der Lehrkraft gemeinsam geprüft. Auf der
    // Niederschrift stehen deshalb die Namen der Gruppe — jeder Prüfling behält
    // aber sein eigenes Blatt, weil die Punkte einzeln vergeben werden.
    $ownName      = $nachname . ', ' . $vorname;
    $couplingId   = $assignment->studentExam?->coupling_id;
    $groupMembers = $couplingId !== null ? ($couplingPartners[$couplingId] ?? []) : [];
    $partners     = array_values(array_filter($groupMembers, fn ($n) => $n !== $ownName));
    $kopplungStr  = count($partners) > 0
        ? 'gekoppelt mit ' . implode(' · ', $partners)
        : 'Einzelprüfung';

    $courseTopics = $course !== null ? ($topicsByCourse[$course->id] ?? []) : [];
@endphp

{{-- ══════════════════════════════════════════════════════════════════
     A3 VORDERSEITE  (page-break-after: always)
     Links  = Seite 4: Fragen/Antworten (Rückdeckel)
     Rechts = Seite 1: Deckblatt
     ══════════════════════════════════════════════════════════════════ --}}
<div class="vs">
<table class="sheet-table"><tr>

{{-- ── LINKS: Seite 4 – Fragen/Antworten (Rückdeckel) ─────────────── --}}
<td class="panel">
    <div style="font-size:7.5pt; margin-bottom:1.5mm;">
        Schüler/in:&#160;<span class="bold">{{ $nachname }}, {{ $vorname }}</span>
    </div>
    <div style="font-size:7.5pt; font-weight:bold; margin-bottom:1mm;">
        Fragen / Antworten&#160;<span style="font-weight:normal; font-size:7pt;">(Fortsetzung)</span>
    </div>
    <table class="qa-tbl">
        <tr class="qa-hdr">
            <td class="qa-f">Fragen</td>
            <td class="qa-g"></td>
            <td class="qa-a">Antworten</td>
        </tr>
        @for($i = 0; $i < 38; $i++)
        <tr><td class="qa-f"></td><td class="qa-g"></td><td class="qa-a"></td></tr>
        @endfor
    </table>
</td>{{-- /Seite 4 --}}

{{-- ── RECHTS: Seite 1 – Deckblatt ──────────────────────────────────── --}}
<td class="panel panel-right">
    <table class="deckblatt-top">
        <tr>
            <td style="vertical-align:top; width:65%">
                <div class="school-name">{{ $schoolName }}</div>
                {{-- Untertitel: Ort. Ein Feld "Schulart" führt das School-Modell nicht. --}}
                <div class="school-sub small">{{ $school?->city ?? '' }}</div>
            </td>
            <td style="vertical-align:top; text-align:right; width:35%">
                <div class="woche-box">
                    <span class="woche-lbl">Woche</span>
                    <span class="woche-val">{{ $woche }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="panel-title">Niederschrift</div>
    <div class="panel-sub">über die Colloquiumsprüfung {{ $year }}</div>

    <table class="data-tbl">
        <tr>
            <td class="lbl">Schüler/in:</td>
            <td class="val filled">{{ $nachname }}, {{ $vorname }}</td>
        </tr>
        <tr>
            <td class="lbl">Prüfungsfach / Kurs:</td>
            <td class="val filled">{{ $fachStr }}</td>
        </tr>
        <tr>
            <td class="lbl">Erstprüfer/in:</td>
            <td class="val filled">{{ $examinerName }}</td>
        </tr>
        <tr>
            <td class="lbl">Beisitzer/in:</td>
            <td class="val filled">{{ $assessorName }}</td>
        </tr>
        <tr>
            <td class="lbl">Datum:</td>
            <td class="val filled">{{ $datumStr }}</td>
        </tr>
        <tr>
            <td class="lbl">Beginn / Ende:</td>
            <td class="val filled">{{ $examStart }}&#160;–&#160;{{ $examEnd }}&#160;Uhr</td>
        </tr>
        <tr>
            <td class="lbl">Prüfungsraum:</td>
            <td class="val filled">{{ $examRoom }}</td>
        </tr>
        <tr>
            <td class="lbl">Vorbereitungsraum:</td>
            <td class="val filled">{{ $prepRoom }}</td>
        </tr>
        <tr>
            <td class="lbl">Kopplung:</td>
            <td class="val {{ count($partners) > 0 ? 'filled' : '' }}">{{ $kopplungStr }}</td>
        </tr>
    </table>

    @if(count($courseTopics) > 0)
    <div style="font-size:7.5pt; font-weight:bold; margin:2mm 0 0.5mm;">Themenbereiche des Kurses</div>
    <table class="topic-tbl">
        @foreach($courseTopics as $t)
        <tr>
            <td class="topic-sem">{{ $t['semester'] }}&#160;·&#160;{{ $t['slot'] }}</td>
            <td class="topic-title">{{ $t['title'] }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div style="font-size:7.5pt; margin:3mm 0 1mm;">Die Niederschrift umfasst:</div>
    <div style="font-size:7.5pt; margin-bottom:1mm;">
        1.&#160;&#160;<span class="uline">Prüfungsteil&#160;1</span>&#160;(Seite&#160;2)
    </div>
    <div style="font-size:7.5pt; margin-bottom:1mm;">
        2.&#160;&#160;<span class="uline">Prüfungsteil&#160;2</span>&#160;(Seite&#160;3)
    </div>
    <div style="font-size:7.5pt; margin-bottom:2mm;">
        3.&#160;&#160;<span class="uline">Fragen / Antworten</span>&#160;(Seite&#160;4)
    </div>
    <div style="font-size:7.5pt; font-weight:bold; margin-bottom:1mm;">Ergebnis der Prüfung</div>
    <div class="ergebnis">
        Gesamtpunkte der Colloquiumsprüfung (0&#160;–&#160;15):<br><br>
        <span class="erg-line">&#160;</span>&#160;&#160;Punkt(e), in Worten:&#160;
        <span class="erg-line-long">&#160;</span>
    </div>
    <table class="sig-tbl">
        <tr>
            <td style="width:45%;"><span class="sig-line">&#160;</span>Schriftführer/in</td>
            <td style="width:10%;"></td>
            <td style="width:45%;"><span class="sig-line">&#160;</span>Prüfer/in</td>
        </tr>
    </table>
</td>{{-- /Deckblatt --}}
</tr></table>
</div>{{-- /A3 Vorderseite --}}

{{-- ════════════════════════════════════════════════════════════════
     A3 RÜCKSEITE  (page-break-after: always nur zwischen Prüflingen)
     Links  = Seite 2: Prüfungsteil 1
     Rechts = Seite 3: Prüfungsteil 2
     ════════════════════════════════════════════════════════════════ --}}
<div class="{{ $isLast ? 'rs' : 'rs-break' }}">
<table class="sheet-table"><tr>

{{-- ── LINKS: Seite 2 – Prüfungsteil 1 ──────────────────────────── --}}
<td class="panel">
    <div style="font-size:7.5pt; margin-bottom:1mm;">
        Schüler/in:&#160;<span class="bold">{{ $nachname }}, {{ $vorname }}</span>
    </div>
    <div class="pruefteil-hd">Niederschrift über die Colloquiumsprüfung {{ $year }}</div>
    <div class="teil-label">Prüfungsteil&#160;1</div>

    @if($isSprachen)
    {{-- Sprachen: Charakterisierung Kurzreferat (Freitext) + Fragen --}}
    <div style="font-size:7.5pt; margin-bottom:0.5mm;">
        Charakterisierung unter Berücksichtigung der Beurteilungsbereiche
        <span class="small">(je nach Sachverhalt)</span>
    </div>
    <div style="font-size:7.5pt; font-weight:bold; margin-bottom:0.5mm;">Kurzreferat</div>
    @for($i = 0; $i < 16; $i++)
    <div class="wl-sm"></div>
    @endfor
    <div style="font-size:7.5pt; font-weight:bold; margin:2mm 0 0.5mm;">Fragen zum Schwerpunkt</div>
    <table class="qa-tbl">
        <tr class="qa-hdr">
            <td class="qa-f">Fragen</td><td class="qa-g"></td><td class="qa-a">Antworten</td>
        </tr>
        @for($i = 0; $i < 15; $i++)
        <tr><td class="qa-f"></td><td class="qa-g"></td><td class="qa-a"></td></tr>
        @endfor
    </table>
    @else
    {{-- Standard: Kurzreferat-Kriterien + Fragen --}}
    <div style="font-size:7.5pt; margin-bottom:0.5mm;">
        <span class="bold">Kurzreferat&#160;</span>Charakterisierung unter Berücksichtigung der
        Beurteilungsbereiche <span class="small">(je nach Sachverhalt)</span>
    </div>
    @php
    $kriterien = [
        ['1.', 'Art des Vortrages',                                                   1],
        ['2.', 'Erfassen des Themas',                                                 2],
        ['3.', 'Gliederung und logischer Aufbau, Behandlung des Wesentlichen',        3],
        ['4.', 'Fachkenntnisse, Herstellung von Zusammenhängen',                      4],
        ['5.', 'Argumentation, Urteilsvermögen, Darlegung eines eigenen Standpunkts', 3],
        ['6.', 'Methodische Richtigkeit, Fachsprache',                                2],
    ];
    @endphp
    <table class="krit-tbl">
    @foreach($kriterien as [$nr, $text, $lines])
        <tr>
            <td class="krit-lbl">{{ $nr }}&#160;&#160;{{ $text }}</td>
            <td class="krit-val"></td>
        </tr>
        @for($i = 1; $i < $lines; $i++)
        <tr><td class="krit-lbl"></td><td class="krit-val"></td></tr>
        @endfor
    @endforeach
    </table>
    <div style="height:1.5mm;"></div>
    <table class="qa-tbl">
        <tr class="qa-hdr">
            <td class="qa-f">Fragen</td><td class="qa-g"></td><td class="qa-a">Antworten</td>
        </tr>
        @for($i = 0; $i < 15; $i++)
        <tr><td class="qa-f"></td><td class="qa-g"></td><td class="qa-a"></td></tr>
        @endfor
    </table>
    @endif

</td>{{-- /Prüfungsteil 1 --}}
{{-- ── RECHTS: Seite 3 – Prüfungsteil 2 ─────────────────────────── --}}
<td class="panel panel-right">
    <div style="font-size:7.5pt; margin-bottom:1mm;">
        Schüler/in:&#160;<span class="bold">{{ $nachname }}, {{ $vorname }}</span>
    </div>
    <div class="pruefteil-hd">Niederschrift über die Colloquiumsprüfung {{ $year }}</div>
    <div class="teil-label">Prüfungsteil&#160;2</div>

    @if($isSprachen)
    {{-- Sprachen: Teilnote + Bewertungsraster + Fragen --}}
    <div class="tnote">
        Inhalt:&#160;40&#160;%&#160;– Teilnote:&#160;<span class="tnote-line">&#160;</span>
        &#160;&#160;&#160;Sprache:&#160;60&#160;%&#160;– Teilnote:&#160;<span class="tnote-line">&#160;</span>
    </div>
    <table class="bew-tbl">
        <thead><tr>
            <th style="width:24%">Darstellung:<br>Kohärenz, Struktur,<br>Argumentation,<br>Einordnung in größere<br>Zusammenhänge</th>
            <th style="width:16%">Aufgaben­erfüllung:<br>Fach­kenntnisse</th>
            <th style="width:15%">Aus­sprache</th>
            <th style="width:15%">Wort­schatz</th>
            <th style="width:15%">Grammatik&#160;/<br>Syntax</th>
            <th style="width:15%">Gesprächs­fähigkeit,<br>Vortragsart</th>
        </tr></thead>
        <tbody><tr class="bew-body">
            <td></td><td></td><td></td><td></td><td></td><td></td>
        </tr></tbody>
    </table>
    <table class="qa-tbl">
        <tr class="qa-hdr">
            <td class="qa-f">Fragen</td><td class="qa-g"></td><td class="qa-a">Antworten</td>
        </tr>
        @for($i = 0; $i < 30; $i++)
        <tr><td class="qa-f"></td><td class="qa-g"></td><td class="qa-a"></td></tr>
        @endfor
    </table>
    @else
    {{-- Standard: Teilnote + Fragen/Antworten --}}
    <div class="tnote">
        Inhalt:&#160;40&#160;%&#160;– Teilnote:&#160;<span class="tnote-line">&#160;</span>
        &#160;&#160;&#160;Sprache&#160;/&#160;Darstellung:&#160;60&#160;%&#160;– Teilnote:&#160;<span class="tnote-line">&#160;</span>
    </div>
    <div style="height:1mm;"></div>
    <table class="qa-tbl">
        <tr class="qa-hdr">
            <td class="qa-f">Fragen</td><td class="qa-g"></td><td class="qa-a">Antworten</td>
        </tr>
        @for($i = 0; $i < 34; $i++)
        <tr><td class="qa-f"></td><td class="qa-g"></td><td class="qa-a"></td></tr>
        @endfor
    </table>
    @endif

</td>{{-- /Prüfungsteil 2 --}}
</tr></table>
</div>{{-- /A3 Rückseite --}}

@endforeach

</body>
</html>
