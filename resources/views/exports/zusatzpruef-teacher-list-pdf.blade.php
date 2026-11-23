<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mündliche Zusatzprüfungen – Lehrerübersicht {{ $examPeriod->year }}</title>
    <style>
        @page { margin: 20mm 0 10mm 0; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 7pt;
            line-height: 1.25;
            color: #111;
            margin: 0 8mm 0 8mm;
        }

        /* ── Wiederholender Seitenkopf ── */
        .running-header {
            position: fixed;
            top: -20mm; left: 0; right: 0;
            height: 18mm;
            background: #fff;
            text-align: center;
        }
        .school-name { font-size: 9pt;  font-weight: bold; color: #1e3a5f; }
        .plan-title  { font-size: 10.5pt; font-weight: bold; color: #1e3a5f; margin: 1px 0; }
        .plan-meta   { font-size: 6.5pt; color: #555; }

        /* ── Seitenfußzeile ── */
        .page-footer {
            position: fixed;
            bottom: -10mm; left: 0; right: 0;
            height: 8mm; padding: 1mm 9mm;
            font-size: 5.5pt; color: #9ca3af; text-align: center;
            border-top: 1px solid #e5e7eb; background: #fff;
        }

        /* ── Lehrer-Block ── */
        .teacher-block {
            margin-bottom: 7px;
        }
        .teacher-header {
            background: #1e3a5f;
            color: #fff;
            font-size: 8pt;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 2px 2px 0 0;
        }
        .teacher-sub {
            font-size: 6.5pt;
            font-weight: normal;
            color: #93c5fd;
            margin-left: 6px;
        }

        /* ── Eintrags-Tabelle ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        thead th {
            background: #f3f4f6;
            color: #374151;
            font-size: 6.5pt;
            font-weight: bold;
            padding: 2px 3px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        tbody td {
            padding: 2px 3px;
            border: 1px solid #d1d5db;
            vertical-align: middle;
            font-size: 7pt;
        }

        /* Abwechselnde Zeilenfarbe */
        .row-pruefung  { background: #fff; }
        .row-aufsicht  { background: #eff6ff; }

        /* Spaltenbreiten */
        .col-func    { width: 12%; }
        .col-date    { width:  9%; white-space: nowrap; }
        .col-prep    { width:  9%; white-space: nowrap; }
        .col-exam    { width:  9%; white-space: nowrap; }
        .col-room    { width:  5%;  text-align: center; }
        .col-student { width: 15%; }
        .col-subject { width: 18%; }
        .col-ausschl { width:  9%; }
        .col-schwer  { width:  9%; }
        .col-co      { width:  5%;  }

        /* Funktions-Stile (Plaintext) */
        .func-erst   { color: #1e3a5f; font-weight: bold; }
        .func-zweit  { color: #374151; }
        .func-gast   { color: #6b7280; font-style: italic; }
        .func-auf    { color: #1e40af; }

        .td-absent   { text-decoration: line-through; color: #9ca3af; }
        .td-nta      { color: #92400e; font-size: 6.5pt; }
        .td-date-wd  { font-size: 6.5pt; color: #6b7280; }

        .no-teachers { text-align: center; color: #9ca3af; padding: 40px; font-size: 11pt; }
        .no-entries  { font-size: 7pt; color: #9ca3af; font-style: italic; padding: 3px 5px; }
    </style>
</head>
<body>

{{-- ══ Wiederholender Seitenkopf ══ --}}
<div class="running-header">
    <div style="padding-top: 3mm; text-align: center;">
        @if($school)
            <div class="school-name">{{ $school->name }}</div>
        @endif
        <div class="plan-title">Abitur {{ $examPeriod->year }} &ndash; Mündliche Zusatzprüfungen &ndash; Lehrerübersicht</div>
        <div class="plan-meta">Stand: {{ \Carbon\Carbon::now()->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm') }} Uhr</div>
    </div>
</div>

{{-- ══ Seitenfußzeile ══ --}}
<div class="page-footer">
    {{ $school?->name ?? '' }}
    &nbsp;|&nbsp; Mündliche Zusatzprüfungen Abitur {{ $examPeriod->year }} – Lehrerübersicht
    &nbsp;|&nbsp; Erstellt: {{ now()->format('d.m.Y H:i') }} Uhr
</div>

@if(empty($teacherRows))
    <div class="no-teachers">Keine Einträge vorhanden (noch keine Prüfungen platziert oder Aufsichten eingetragen).</div>
@else
    @foreach($teacherRows as $tIdx => $teacherData)
        @php
            /** @var \App\Models\Teacher $teacher */
            $teacher = $teacherData['teacher'];
            $entries = $teacherData['entries'];

            $fullName = $teacher->lastname;
            if ($teacher->firstname) {
                $fullName .= ', ' . $teacher->firstname;
            }
            $pruefCount  = $entries->where('type', 'pruefung')->count();
            $aufsCount   = $entries->where('type', 'aufsicht')->count();
            $subInfo     = [];
            if ($pruefCount > 0) $subInfo[] = $pruefCount . ' ' . ($pruefCount === 1 ? 'Prüfung' : 'Prüfungen');
            if ($aufsCount  > 0) $subInfo[] = $aufsCount  . ' ' . ($aufsCount  === 1 ? 'Aufsicht' : 'Aufsichten');
        @endphp

        <div class="teacher-block">

            {{-- Lehrer-Header --}}
            <div class="teacher-header">
                {{ $teacher->shortcode }} &ndash; {{ $fullName }}
                @if(!empty($subInfo))
                    <span class="teacher-sub">({{ implode(', ', $subInfo) }})</span>
                @endif
            </div>

            @if($entries->isEmpty())
                <div class="no-entries">Keine Einträge.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="col-func">Funktion</th>
                            <th class="col-date">Datum</th>
                            <th class="col-prep">Vorber.-Zeit</th>
                            <th class="col-exam">Prüfungs-Zeit</th>
                            <th class="col-room">Raum</th>
                            <th class="col-student">Schüler/in</th>
                            <th class="col-subject">Fach (Kurs)</th>
                            <th class="col-ausschl">Ausschluss</th>
                            <th class="col-schwer">Schwerpunkt</th>
                            <th class="col-co">Mitprüfer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                            @php
                                $isPruefung = $entry['type'] === 'pruefung';
                                $carbon = \Carbon\Carbon::instance($entry['date'])->locale('de');
                            @endphp

                            @if($isPruefung)
                                <tr class="row-pruefung">
                                    <td class="col-func">
                                        @if($entry['role_label'] === 'Erstprüfer')
                                            <span class="func-erst">Erstprüfer</span>
                                        @elseif($entry['role_label'] === 'Zweitkorrektor')
                                            <span class="func-zweit">Zweitkorrektor</span>
                                        @else
                                            <span class="func-gast">Gast</span>
                                        @endif
                                    </td>
                                    <td class="col-date">
                                        {{ $carbon->isoFormat('DD.MM.YYYY') }}
                                        <div class="td-date-wd">{{ $carbon->isoFormat('dddd') }}</div>
                                    </td>
                                    <td class="col-prep">{{ $entry['prep_time'] }}</td>
                                    <td class="col-exam">{{ $entry['exam_time'] }}</td>
                                    <td class="col-room">{{ $entry['exam_room'] }}</td>
                                    <td class="col-student">
                                        @if($entry['absent'])
                                            <span class="td-absent">{{ $entry['student_name'] }}</span>
                                            <span class="td-nta">(n. angetre.)</span>
                                        @else
                                            {{ $entry['student_name'] }}@if($entry['nta_extra'] > 0) <span class="td-nta">(NTA)</span>@endif
                                        @endif
                                    </td>
                                    <td class="col-subject">{{ $entry['subject'] }}</td>
                                    @php
                                        $tlExcl  = $entry['excluded_semester'] ?? null;
                                        $tlFocus = $entry['focus_semester'] ?? null;
                                        $tlMath  = $entry['is_math'] ?? false;
                                        $tlSemLbl = ['12_1'=>'AA 12/1','12_2'=>'AA 12/2','13_1'=>'AA 13/1','13_2'=>'AA 13/2'];
                                        if ($tlMath) {
                                            $tlAusschluss  = $tlExcl ? ucfirst($tlExcl) : null;
                                            $tlSchwerpunkt = null;
                                        } else {
                                            $tlAusschluss  = ($tlExcl && isset($tlSemLbl[$tlExcl])) ? $tlSemLbl[$tlExcl] : null;
                                            $tlSchwerpunkt = ($tlFocus && isset($tlSemLbl[$tlFocus])) ? $tlSemLbl[$tlFocus] : null;
                                        }
                                    @endphp
                                    <td class="col-ausschl">
                                        @if($tlAusschluss)
                                            <span style="color:#b45309;">{{ $tlAusschluss }}</span>
                                        @else
                                            <span style="color:#ccc;">–</span>
                                        @endif
                                    </td>
                                    <td class="col-schwer">
                                        @if($tlSchwerpunkt)
                                            <span style="color:#1e40af;">{{ $tlSchwerpunkt }}</span>
                                        @else
                                            <span style="color:#ccc;">–</span>
                                        @endif
                                    </td>
                                    <td class="col-co">{{ $entry['co_examiners'] ?: '–' }}</td>
                                </tr>
                            @else
                                <tr class="row-aufsicht">
                                    <td class="col-func"><span class="func-auf">Aufsicht</span></td>
                                    <td class="col-date">
                                        {{ $carbon->isoFormat('DD.MM.YYYY') }}
                                        <div class="td-date-wd">{{ $carbon->isoFormat('dddd') }}</div>
                                    </td>
                                    <td class="col-prep">{{ $entry['time_range'] }}</td>
                                    <td class="col-exam" style="color:#9ca3af;">–</td>
                                    <td class="col-room" style="font-weight:bold;color:#1e40af;">{{ $entry['prep_room'] }}</td>
                                    <td class="col-student" style="color:#9ca3af;">Vorbereitungsraum</td>
                                    <td class="col-subject" style="color:#9ca3af;">–</td>
                                    <td class="col-ausschl" style="color:#9ca3af;">–</td>
                                    <td class="col-schwer" style="color:#9ca3af;">–</td>
                                    <td class="col-co" style="color:#9ca3af;">–</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif

        </div>{{-- /.teacher-block --}}
    @endforeach
@endif

</body>
</html>
