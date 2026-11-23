<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mantelbögen</title>
    <style>
        @page {
            size: A3 landscape;
            margin: 0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #000;
        }

        /* Haupttabelle: volle Breite, zwei gleiche Hälften */
        .page-wrap {
            width: 100%;
            height: 297mm;
            page-break-after: always;
            border-collapse: collapse;
        }
        .page-wrap:last-child {
            page-break-after: auto;
        }
        .td-left {
            width: 50%;
            border-right: 0.4mm solid #bbb;
            vertical-align: top;
        }
        /* Rechte Hälfte = ~210mm = DIN A4 Breite */
        .td-right {
            width: 50%;
            vertical-align: top;
            padding: 7mm 7mm 5mm 8mm;
        }

        /* Unterstriche */
        .uf {
            border-bottom: 1pt solid #000;
            display: inline-block;
            vertical-align: bottom;
        }
        .uf-bold {
            border-bottom: 1.5pt solid #000;
            display: inline-block;
            font-weight: bold;
            font-size: 11pt;
            vertical-align: bottom;
        }

        /* Sub-Labels unter Feldern */
        .sub {
            font-size: 7pt;
            text-align: center;
            display: block;
        }

        /* Abschnitt-Linie Bewertung */
        .section-line {
            border-bottom: 0.8pt solid #000;
            padding-bottom: 0.5mm;
            margin-bottom: 1.5mm;
            font-weight: bold;
            font-size: 9.5pt;
            display: block;
        }

        .bewerter-label {
            font-size: 8.5pt;
            margin-bottom: 0.5mm;
            margin-top: 2mm;
            display: block;
        }

        .foot-line {
            font-size: 8.5pt;
            margin-bottom: 1.8mm;
        }

        /* Einsichtnahme */
        .einsicht {
            border: 1.2pt solid #000;
            padding: 2mm 3mm;
            font-size: 8pt;
        }
        .einsicht-title {
            font-weight: bold;
            text-align: center;
            border-bottom: 0.8pt solid #000;
            padding-bottom: 1mm;
            margin-bottom: 2mm;
        }
        .einsicht-uf {
            border-bottom: 0.8pt solid #000;
            display: inline-block;
        }
        .einsicht-sig {
            border-top: 0.8pt solid #000;
            text-align: center;
            padding-top: 1mm;
            margin-top: 5mm;
            font-size: 8pt;
        }
        .checkbox {
            display: inline-block;
            width: 3mm;
            height: 3mm;
            border: 0.8pt solid #000;
            vertical-align: middle;
        }

        /* Helper: volle Breite, kein Border-Collapse-Gap */
        .w100 { width: 100%; border-collapse: collapse; }
        .w100 td { padding: 0; }
    </style>
</head>
<body>
@foreach($pages as $page)
@php
    $student    = $page['student'];
    $assignment = $page['assignment'];
    $yearDate   = $page['yearDate'];
    $seatNumber = $page['seatNumber'];

    $examYear  = $yearDate->exam_year;
    $examDate  = $yearDate->date->locale('de')->translatedFormat('d.m.Y');
    $startTime = \Carbon\Carbon::parse($yearDate->slot->start_time)->format('H:i');
    $endTime   = $yearDate->getEndTime($student->disability_compensation_percent)->format('H:i');
    $examTime  = $startTime . ' – ' . $endTime . ' Uhr';

    $subjectName = $yearDate->slot->subjects->first()?->name
                   ?? ($assignment->course->subject->name ?? '—');
    $courseCode  = $assignment->course->course_code ?? '—';

    $t1 = $assignment->course->teacher;
    $t2 = $assignment->course->secondCorrector;

    $t1Name = '';
    $t2Name = '';
    if ($t1) {
        // Kein $t1->amtstitel: teachers hat keine solche Spalte, der Zweig traf nie zu.
        // Der Amtstitel steht in teachers.title und wird oben schon vorangestellt.
        $t1Name = trim(($t1->title ? $t1->title . ' ' : '') . $t1->lastname . ($t1->shortcode ? ', ' . $t1->shortcode : ''));
    }
    if ($t2) {
        $t2Name = trim(($t2->title ? $t2->title . ' ' : '') . $t2->lastname . ($t2->shortcode ? ', ' . $t2->shortcode : ''));
    }

    $schoolName = $school ? ($school->name ?? $school->short_name ?? '') : '';
@endphp

<table class="page-wrap">
 <tr>
  <td class="td-left"></td>
  <td class="td-right">

   {{-- Schulname --}}
   <div style="text-align:center; font-weight:bold; font-size:9.5pt; margin-bottom:5mm;">{{ $schoolName }}</div>

   {{-- Name + Abiturprüfung-Titel --}}
   <table class="w100" style="margin-bottom:3mm;">
    <tr>
     <td style="width:52%; vertical-align:bottom;">
      <span style="font-size:12pt; font-weight:bold; border-bottom:1.5pt solid #000; display:inline-block; min-width:70mm; padding-bottom:0.5mm;">{{ $student->full_name }}</span>
      <span style="font-size:7pt; display:block; text-align:center; margin-left:2mm;">
       &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Name)
      </span>
     </td>
     <td style="width:48%; vertical-align:top; text-align:right;">
      <span style="font-size:13pt; font-weight:bold;">Abiturpr&uuml;fung {{ $examYear }}</span>
     </td>
    </tr>
   </table>

   {{-- Tag / Prüfungszeit (rechtsbündig, nur rechte Spalte) --}}
   <table class="w100" style="margin-bottom:3mm;">
    <tr>
     <td style="width:52%;">&nbsp;</td>
     <td style="width:48%;">
      <table class="w100">
       <tr>
        <td style="font-size:9pt; padding-right:3mm; white-space:nowrap;">Tag der Pr&uuml;fung:</td>
        <td style="font-size:9pt;">{{ $examDate }}</td>
       </tr>
       <tr>
        <td style="font-size:9pt; padding-right:3mm; white-space:nowrap;">Pr&uuml;fungszeit:</td>
        <td style="font-size:9pt;">{{ $examTime }}</td>
       </tr>
      </table>
     </td>
    </tr>
   </table>

   {{-- Platznummer + Fach / Kurs --}}
   <table class="w100" style="margin-bottom:4mm;">
    <tr>
     <td style="width:50%; vertical-align:bottom;">
      <span style="font-size:9pt;">Platznummer:</span>&nbsp;
      <span class="uf" style="min-width:22mm; padding-bottom:0.5mm;">
       {{ $seatNumber !== null ? $seatNumber : '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' }}
      </span>
     </td>
     <td style="width:50%; vertical-align:top;">
      <table class="w100">
       <tr>
        <td style="font-size:9pt; white-space:nowrap; padding-right:2mm; width:12mm; vertical-align:bottom;">Fach:</td>
        <td style="vertical-align:bottom;">
         <span class="uf-bold" style="min-width:50mm; padding-bottom:0.5mm;">{{ $subjectName }}</span>
         <span class="sub">(Pr&uuml;fungsfach)</span>
        </td>
       </tr>
       <tr>
        <td style="font-size:9pt; padding-right:2mm; vertical-align:bottom;">Kurs:</td>
        <td style="vertical-align:bottom;">
         <span class="uf-bold" style="min-width:50mm; padding-bottom:0.5mm;">{{ $courseCode }}</span>
        </td>
       </tr>
      </table>
     </td>
    </tr>
   </table>


   {{-- Bewertung --}}
   <span class="section-line">Bewertung:</span>

   {{-- Makro: Punkte-Zeile als 2-Zeilen-Tabelle --}}
   {{-- 1. Berichterstatter --}}
   <span class="bewerter-label">1. Berichterstatter/in:</span>
   <table class="w100" style="margin-bottom:3mm;">
    {{-- Zeile 1: Felder --}}
    <tr>
     <td style="width:18%;">Punkte:&nbsp;<span class="uf" style="min-width:10mm;">&nbsp;&nbsp;</span></td>
     <td style="width:24%;">Note:&nbsp;<span class="uf" style="min-width:14mm;">&nbsp;&nbsp;</span></td>
     <td style="width:3%; text-align:center;">=</td>
     <td style="width:23%;"><span class="uf" style="min-width:20mm;">&nbsp;&nbsp;</span></td>
     <td style="width:32%;">Unterschrift:&nbsp;<span class="uf" style="min-width:22mm;">&nbsp;&nbsp;</span></td>
    </tr>
    {{-- Zeile 2: Sub-Labels --}}
    <tr>
     <td></td>
     <td style="font-size:7pt; text-align:center;">(mit Tendenz)</td>
     <td></td>
     <td style="font-size:7pt; text-align:center;">(Wortbezeichnung)</td>
     <td style="font-size:7pt; text-align:right;">{{ $t1Name }}</td>
    </tr>
   </table>

   {{-- 2. Berichterstatter --}}
   <span class="bewerter-label">2. Berichterstatter/in:</span>
   <table class="w100" style="margin-bottom:3mm;">
    <tr>
     <td style="width:18%;">Punkte:&nbsp;<span class="uf" style="min-width:10mm;">&nbsp;&nbsp;</span></td>
     <td style="width:24%;">Note:&nbsp;<span class="uf" style="min-width:14mm;">&nbsp;&nbsp;</span></td>
     <td style="width:3%; text-align:center;">=</td>
     <td style="width:23%;"><span class="uf" style="min-width:20mm;">&nbsp;&nbsp;</span></td>
     <td style="width:32%;">Unterschrift:&nbsp;<span class="uf" style="min-width:22mm;">&nbsp;&nbsp;</span></td>
    </tr>
    <tr>
     <td></td>
     <td style="font-size:7pt; text-align:center;">(mit Tendenz)</td>
     <td></td>
     <td style="font-size:7pt; text-align:center;">(Wortbezeichnung)</td>
     <td style="font-size:7pt; text-align:right;">{{ $t2Name }}</td>
    </tr>
   </table>

   {{-- Einvernehmlich --}}
   <div class="foot-line">Einvernehmlich erzielte Punkte/Note:</div>
   <table class="w100" style="margin-bottom:3mm;">
    <tr>
     <td style="width:18%;">Punkte:&nbsp;<span class="uf" style="min-width:10mm;">&nbsp;&nbsp;</span></td>
     <td style="width:24%;">Note:&nbsp;<span class="uf" style="min-width:14mm;">&nbsp;&nbsp;</span></td>
     <td style="width:3%; text-align:center;">=</td>
     <td style="width:55%;"><span class="uf" style="min-width:20mm;">&nbsp;&nbsp;</span></td>
    </tr>
    <tr>
     <td></td>
     <td style="font-size:7pt; text-align:center;">(mit Tendenz)</td>
     <td></td>
     <td style="font-size:7pt;">(Wortbezeichnung)</td>
    </tr>
   </table>

   {{-- Drittentscheid --}}
   <div class="foot-line">Drittentscheid durch:&nbsp;<span class="uf" style="min-width:55mm;">&nbsp;&nbsp;</span></div>
   <table class="w100" style="margin-bottom:4mm;">
    <tr>
     <td style="width:18%;">Punkte:&nbsp;<span class="uf" style="min-width:10mm;">&nbsp;&nbsp;</span></td>
     <td style="width:24%;">Note:&nbsp;<span class="uf" style="min-width:14mm;">&nbsp;&nbsp;</span></td>
     <td style="width:3%; text-align:center;">=</td>
     <td style="width:23%;"><span class="uf" style="min-width:20mm;">&nbsp;&nbsp;</span></td>
     <td style="width:32%;">Unterschrift:&nbsp;<span class="uf" style="min-width:22mm;">&nbsp;&nbsp;</span></td>
    </tr>
    <tr>
     <td></td>
     <td style="font-size:7pt; text-align:center;">(mit Tendenz)</td>
     <td></td>
     <td style="font-size:7pt; text-align:center;">(Wortbezeichnung)</td>
     <td></td>
    </tr>
   </table>


   {{-- Fußbereich: Zeitangaben links, Einsichtnahme rechts --}}
   <table class="w100">
    <tr>
     <td style="width:62%; vertical-align:top; padding-right:5mm;">
      <div class="foot-line">Beginn um:&nbsp;<span class="uf" style="min-width:14mm;">&nbsp;&nbsp;</span>&nbsp;Uhr</div>
      <div class="foot-line">Abgegeben um:&nbsp;<span class="uf" style="min-width:14mm;">&nbsp;&nbsp;</span>&nbsp;Uhr</div>
      <div class="foot-line">Anzahl der Bogen:&nbsp;<span class="uf" style="min-width:10mm;">&nbsp;&nbsp;</span></div>
      <div class="foot-line">Anzahl der Bl&auml;tter:&nbsp;<span class="uf" style="min-width:10mm;">&nbsp;&nbsp;</span></div>
      <div class="foot-line">
       Angabe liegt bei:&nbsp;&nbsp;
       ja&nbsp;<span class="checkbox"></span>&nbsp;&nbsp;&nbsp;
       nein&nbsp;<span class="checkbox"></span>
      </div>
      <div class="foot-line" style="margin-top:1.5mm;">
       Ausgetreten von&nbsp;<span class="uf" style="min-width:11mm;">&nbsp;&nbsp;</span>
       &nbsp;bis&nbsp;<span class="uf" style="min-width:11mm;">&nbsp;&nbsp;</span>&nbsp;Uhr
      </div>
      <div class="foot-line">
       Ausgetreten von&nbsp;<span class="uf" style="min-width:11mm;">&nbsp;&nbsp;</span>
       &nbsp;bis&nbsp;<span class="uf" style="min-width:11mm;">&nbsp;&nbsp;</span>&nbsp;Uhr
      </div>
      <div class="foot-line">
       Ausgetreten von&nbsp;<span class="uf" style="min-width:11mm;">&nbsp;&nbsp;</span>
       &nbsp;bis&nbsp;<span class="uf" style="min-width:11mm;">&nbsp;&nbsp;</span>&nbsp;Uhr
      </div>
     </td>
     <td style="width:38%; vertical-align:bottom;">
      <div class="einsicht">
       <div class="einsicht-title">Einsichtnahme</div>
       <div style="margin-bottom:2mm;">am&nbsp;<span class="einsicht-uf" style="min-width:30mm;">&nbsp;&nbsp;</span></div>
       <div style="margin-bottom:2mm;">
        von&nbsp;<span class="einsicht-uf" style="min-width:12mm;">&nbsp;&nbsp;</span>
        &nbsp;bis&nbsp;<span class="einsicht-uf" style="min-width:12mm;">&nbsp;&nbsp;</span>
       </div>
       <div class="einsicht-sig">Unterschrift</div>
      </div>
     </td>
    </tr>
   </table>

  </td>{{-- /td-right --}}
 </tr>
</table>{{-- /page-wrap --}}
@endforeach
</body>
</html>
