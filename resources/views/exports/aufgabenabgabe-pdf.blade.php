@php
    /**
     * Aufgabenabgabe-Checkliste
     * Pro Prüfungstag eine Seite (A4 Hochformat).
     * Zeigt je Zeitslot: Zeit | ✓ | Prüfling | Fach | Prüfer | Beisitzer
     */
    $wochentage = ['So','Mo','Di','Mi','Do','Fr','Sa'];
    $monate = [
        1=>'Januar',2=>'Februar',3=>'März',4=>'April',5=>'Mai',6=>'Juni',
        7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'Dezember'
    ];
    function fmtDate(\Carbon\Carbon $d, array $wd, array $mo): string {
        return $wd[$d->dayOfWeek] . ', ' . $d->day . '. ' . $mo[$d->month] . ' ' . $d->year;
    }
    function fmtTime($t): string {
        return substr((string)$t, 0, 5);
    }
    /**
     * Gibt den letzten Schultag vor $date zurück (kein WE, kein Ferien-/Feiertag).
     * $events = GlobalCalendarEvent-Collection (holiday / public_holiday)
     * Felder: event_date (Carbon), end_date (Carbon|null)
     */
    function prevSchoolDay(\Carbon\Carbon $date, $events): \Carbon\Carbon {
        $day = $date->copy()->subDay();
        while (true) {
            if ($day->isWeekend()) { $day->subDay(); continue; }
            $blocked = false;
            foreach ($events as $event) {
                $start = $event->event_date instanceof \Carbon\Carbon
                    ? $event->event_date
                    : \Carbon\Carbon::parse($event->event_date);
                $end = $event->end_date
                    ? ($event->end_date instanceof \Carbon\Carbon ? $event->end_date : \Carbon\Carbon::parse($event->end_date))
                    : $start->copy();
                if ($day->between($start, $end)) { $blocked = true; break; }
            }
            if ($blocked) { $day->subDay(); continue; }
            break;
        }
        return $day;
    }
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Aufgabenabgabe – {{ $examPeriod->name }}</title>
<style>
@page {
    margin: 14mm 12mm 12mm 12mm;
}
* { box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9pt;
    color: #1f2937;
    margin: 0;
    padding: 0;
}

/* ── Seitenkopf ─────────────────────────────────────── */
.page-header {
    border-bottom: 2.5px solid #1e3a5f;
    padding-bottom: 5px;
    margin-bottom: 10px;
}
.hd-school {
    font-size: 8pt;
    color: #4b5563;
}
.hd-title {
    font-size: 18pt;
    font-weight: bold;
    color: #1e3a5f;
    margin: 3px 0 2px;
}
.hd-meta {
    font-size: 7.5pt;
    color: #6b7280;
    margin-top: 2px;
}
.hd-notice {
    font-size: 8.5pt;
    color: #7c3aed;
    margin-top: 4px;
    font-style: italic;
}

/* ── Tabelle ─────────────────────────────────────────── */
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
thead th {
    background: #1e3a5f;
    color: #fff;
    font-size: 8pt;
    font-weight: bold;
    padding: 4px 5px;
    text-align: left;
    border: 1px solid #1e3a5f;
}
thead th.th-center {
    text-align: center;
}
tbody tr.slot-first td {
    border-top: 2px solid #60a5fa;
    background: #eff6ff;
}
tbody tr.slot-first td.td-time {
    font-weight: bold;
    color: #1e40af;
    font-size: 8.5pt;
}
tbody tr.slot-other td {
    border-top: 1px solid #e5e7eb;
    background: #fff;
}
tbody td {
    padding: 4px 5px;
    font-size: 8.5pt;
    border-left: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
    overflow: hidden;
}
td.td-time {
    text-align: center;
    white-space: nowrap;
}
td.td-student {
    font-weight: bold;
    white-space: nowrap;
}
td.td-subject {
    color: #374151;
    white-space: nowrap;
}
td.td-teacher {
    color: #1e40af;
    text-align: center;
    white-space: nowrap;
}
td.td-assessor {
    color: #4b5563;
    text-align: center;
    white-space: nowrap;
}
td.td-check {
    text-align: center;
    width: 22px;
}
.checkbox {
    display: inline-block;
    width: 13px;
    height: 13px;
    border: 1.5px solid #374151;
}
.slot-empty td {
    color: #9ca3af;
    font-style: italic;
    font-size: 8pt;
    text-align: center;
}

/* ── Seitenumbruch ───────────────────────────────────── */
.day-block {
    page-break-after: always;
}
.day-block:last-child {
    page-break-after: avoid;
}

/* ── Fusszeile ───────────────────────────────────────── */
.page-footer {
    font-size: 7pt;
    color: #9ca3af;
    text-align: center;
    margin-top: 8px;
    border-top: 1px solid #e5e7eb;
    padding-top: 4px;
}
</style>
</head>
<body>

@forelse ($days as $day)
@php
    /** @var \Carbon\Carbon $date */
    $date        = $day['date'];
    $slots       = $day['timeSlots'];
    $totalExams  = $slots->sum(fn($s) => $s->examAssignments->count());
    if ($totalExams === 0) continue;
    $abgabetag   = prevSchoolDay($date, $blockingEvents);
@endphp
<div class="day-block">

    {{-- Seitenkopf --}}
    <div class="page-header">
        @if ($school)
            <div class="hd-school">{{ $school->name }}</div>
        @endif
        <div class="hd-title">
            {{ fmtDate($date, $wochentage, $monate) }}
        </div>
        <div class="hd-meta">{{ $examPeriod->name }}</div>
        <div class="hd-notice">
            Aufgabenstellungen bitte bis {{ fmtDate($abgabetag, $wochentage, $monate) }}, 12:00 Uhr abgeben.
        </div>
    </div>

    {{-- Tabelle --}}
    <table>
        <thead>
            <tr>
                <th style="width:12%">Zeit</th>
                <th class="th-center" style="width:6%">✓</th>
                <th style="width:38%">Prüfling</th>
                <th style="width:10%">Fach</th>
                <th class="th-center" style="width:17%">Prüfer</th>
                <th class="th-center" style="width:17%">Beisitzer</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($slots as $slot)
            @php
                $assignments = $slot->examAssignments->sortBy(function ($a) {
                    $s = $a->studentExam->student;
                    if ($s) return $s->lastname . ',' . $s->firstname;
                    $ext = $a->studentExam->externalExamSubject->externalApplicant ?? null;
                    return $ext ? $ext->lastname . ',' . $ext->firstname : 'zzz';
                });
                $slotTime = fmtTime($slot->exam_start_time) . '–' . fmtTime($slot->exam_end_time);
                $first    = true;
            @endphp
            @if ($assignments->isEmpty())
                <tr class="slot-empty">
                    <td>{{ $slotTime }}</td>
                    <td></td>
                    <td colspan="4">— keine Prüfungen —</td>
                </tr>
            @else
                @foreach ($assignments as $assignment)
                    @php
                        $se       = $assignment->studentExam;
                        $student  = $se->student;
                        $examiner = $se->examiner;
                        $assessor = $se->assessor;
                        $subject  = $se->course->subject ?? null;

                        if ($student) {
                            $studentName = $student->lastname . ', ' . $student->firstname;
                        } else {
                            $ext = $se->externalExamSubject->externalApplicant ?? null;
                            $studentName = $ext
                                ? $ext->lastname . ', ' . $ext->firstname . ' (extern)'
                                : '— extern —';
                        }

                        // Nachname + erster Buchstabe Vorname
                        $fmtTeacher = function ($t) {
                            if (!$t) return '–';
                            $initial = $t->firstname ? mb_substr($t->firstname, 0, 1) . '.' : '';
                            return $t->lastname . ($initial ? ', ' . $initial : '');
                        };

                        $rowClass = $first ? 'slot-first' : 'slot-other';
                        $first    = false;
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="td-time">{{ $loop->first ? $slotTime : '' }}</td>
                        <td class="td-check"><div class="checkbox"></div></td>
                        <td class="td-student">{{ $studentName }}</td>
                        <td class="td-subject">{{ $subject?->code ?? '–' }}</td>
                        <td class="td-teacher">{{ $fmtTeacher($examiner) }}</td>
                        <td class="td-assessor">{{ $fmtTeacher($assessor) }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
        </tbody>
    </table>

    <div class="page-footer">
        Erstellt am {{ now()->format('d.m.Y') }}
        &nbsp;·&nbsp;
        {{ $examPeriod->name }}
    </div>
</div>
@empty
    <p>Keine Prüfungstage gefunden.</p>
@endforelse

</body>
</html>
