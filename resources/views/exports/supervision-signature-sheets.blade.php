<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Aufsicht – Unterschriftsbögen</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
        }

        /* ── Seite ── */
        .page {
            width: 100%;
            padding: 14mm 14mm 12mm 14mm;
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: avoid;
        }

        /* ── Kopfzeile ── */
        .page-header {
            border-bottom: 2.5pt solid #1e3a5f;
            padding-bottom: 6pt;
            margin-bottom: 10pt;
        }
        .school-name {
            font-size: 8.5pt;
            color: #555;
            margin-bottom: 2pt;
        }
        .page-title {
            font-size: 15pt;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: 0.3pt;
        }

        /* ── Meta-Block ── */
        .meta-grid {
            display: table;
            width: 100%;
            margin-bottom: 12pt;
            border-collapse: collapse;
        }
        .meta-row {
            display: table-row;
        }
        .meta-cell {
            display: table-cell;
            width: 50%;
            padding: 3pt 0;
            vertical-align: top;
        }
        .meta-label {
            font-size: 8pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }
        .meta-value {
            font-size: 11pt;
            font-weight: bold;
            color: #1a1a1a;
        }
        .meta-value-normal {
            font-size: 10pt;
            color: #1a1a1a;
        }

        /* ── Trennlinie ── */
        .divider {
            border: none;
            border-top: 1pt solid #d1d5db;
            margin: 6pt 0 10pt 0;
        }

        /* ── Aufsichtstabelle ── */
        table.supervision-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14pt;
        }
        table.supervision-table thead tr {
            background-color: #1e3a5f;
            color: #ffffff;
        }
        table.supervision-table thead th {
            padding: 5pt 7pt;
            font-size: 8.5pt;
            font-weight: bold;
            text-align: left;
            letter-spacing: 0.2pt;
        }
        table.supervision-table thead th.center {
            text-align: center;
        }
        table.supervision-table tbody tr {
            border-bottom: 1pt solid #e5e7eb;
        }
        table.supervision-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        table.supervision-table tbody td {
            padding: 6pt 7pt;
            font-size: 10pt;
            vertical-align: middle;
        }
        table.supervision-table tbody td.sig-cell {
            border-bottom: 1pt solid #9ca3af;
            width: 48mm;
            min-height: 12pt;
            padding-bottom: 2pt;
        }
        table.supervision-table tbody td.time-cell {
            font-size: 9pt;
            color: #374151;
            white-space: nowrap;
            width: 28mm;
        }
        table.supervision-table tbody td.type-cell {
            width: 22mm;
        }
        .type-badge {
            display: inline-block;
            padding: 1.5pt 5pt;
            border-radius: 3pt;
            font-size: 8pt;
            font-weight: bold;
        }
        .type-waiting  { background: #eff6ff; color: #1d4ed8; border: 1pt solid #bfdbfe; }
        .type-corridor { background: #f5f3ff; color: #6d28d9; border: 1pt solid #ddd6fe; }
        .type-reserve  { background: #fffbeb; color: #92400e; border: 1pt solid #fde68a; }
        .type-extra    { background: #fef2f2; color: #991b1b; border: 1pt solid #fecaca; }

        .no-supervision {
            font-style: italic;
            color: #9ca3af;
            font-size: 9pt;
        }

        /* ── Besonderheiten-Box ── */
        .notes-section {
            margin-top: 6pt;
        }
        .notes-title {
            font-size: 9pt;
            font-weight: bold;
            color: #1e3a5f;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            margin-bottom: 5pt;
        }
        .notes-lines {
            width: 100%;
        }
        .notes-line {
            border-bottom: 1pt solid #9ca3af;
            height: 14pt;
            margin-bottom: 4pt;
        }

        /* ── Footer ── */
        .page-footer {
            position: fixed;
            bottom: 8mm;
            left: 14mm;
            right: 14mm;
            border-top: 0.5pt solid #d1d5db;
            padding-top: 3pt;
            font-size: 7.5pt;
            color: #9ca3af;
            display: table;
            width: 100%;
        }
        .footer-left  { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }
    </style>
</head>
<body>

{{-- ── Footer (fixed, erscheint auf jeder Seite) ── --}}
<div class="page-footer">
    <span class="footer-left">{{ $schoolName }}</span>
    <span class="footer-right">Erstellt am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr</span>
</div>

@foreach($pages as $page)
@php
    /** @var \Illuminate\Support\Collection $supervisionsBySlot */
    $supervisionsBySlot = $page['supervisionsBySlot'];
    /** @var \Illuminate\Support\Collection $slotKeys */
    $slotKeys           = $page['slotKeys'];
    /** @var \Illuminate\Support\Collection $timeSlots */
    $timeSlots          = $page['timeSlots'];
    /** @var \Illuminate\Support\Collection $courses */
    $courses            = $page['courses'];
    /** @var \App\Models\Room $room */
    $room               = $page['room'];
    /** @var \Carbon\Carbon $examDate */
    $examDate           = $page['examDate'];

    $totalSupervisions  = $supervisionsBySlot->flatten()->count();

    $typeLabels = [
        'waiting'  => 'Raumaufsicht',
        'corridor' => 'Gangaufsicht',
        'reserve'  => 'Reserve (LZ)',
        'extra'    => 'Zusatz',
    ];
    $typeClasses = [
        'waiting'  => 'type-waiting',
        'corridor' => 'type-corridor',
        'reserve'  => 'type-reserve',
        'extra'    => 'type-extra',
    ];
@endphp
<div class="page">

    {{-- ── Kopfzeile ── --}}
    <div class="page-header">
        @if($page['schoolName'])
            <div class="school-name">{{ $page['schoolName'] }}</div>
        @endif
        <div class="page-title">Aufsicht – Unterschriftsbogen</div>
    </div>

    {{-- ── Meta-Infos ── --}}
    <div class="meta-grid">
        <div class="meta-row">
            <div class="meta-cell">
                <div class="meta-label">Prüfungstag</div>
                <div class="meta-value">{{ $examDate->isoFormat('dddd, DD. MMMM YYYY') }}</div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Raum</div>
                <div class="meta-value">{{ $room->room_number }}</div>
            </div>
        </div>
        <div class="meta-row">
            <div class="meta-cell">
                <div class="meta-label">Fach / Prüfung</div>
                <div class="meta-value-normal">{{ $page['subjectStr'] }}</div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Prüfungszeit</div>
                <div class="meta-value-normal">{{ $page['timeRange'] }}</div>
            </div>
        </div>
        @if($courses->isNotEmpty())
        <div class="meta-row">
            <div class="meta-cell">
                <div class="meta-label">Kurs(e)</div>
                <div class="meta-value-normal">
                    {{ $courses->map(fn($c) => $c->course_code . ($c->teacher ? ' (' . $c->teacher->shortcode . ')' : ''))->join(' · ') }}
                </div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Schüler im Raum</div>
                <div class="meta-value-normal">{{ $page['studentCount'] }}</div>
            </div>
        </div>
        @endif
    </div>

    <hr class="divider">

    {{-- ── Aufsichtstabelle ── --}}
    @if($totalSupervisions > 0)
    <table class="supervision-table">
        <thead>
            <tr>
                <th style="width:28mm;">Zeitraum</th>
                <th style="width:22mm;">Funktion</th>
                <th>Name (Kürzel)</th>
                <th style="width:48mm;" class="center">Unterschrift</th>
            </tr>
        </thead>
        <tbody>
            @foreach($slotKeys as $slotKey)
            @php
                $slotSupervisions = $supervisionsBySlot->get($slotKey, collect());
                /** @var \App\Models\WrittenExamSupervisionTimeSlot|null $timeSlot */
                $timeSlot = ($slotKey !== 'none') ? $timeSlots->get($slotKey) : null;
                if ($timeSlot !== null) {
                    $timeLabel = substr($timeSlot->start_time, 0, 5) . '–' . substr($timeSlot->end_time, 0, 5);
                    if ($timeSlot->label) {
                        $timeLabel .= "\n" . $timeSlot->label;
                    }
                } else {
                    $timeLabel = $page['timeRange'] ?: '–';
                }
            @endphp
            @foreach($slotSupervisions as $supervision)
            @php
                /** @var \App\Models\Teacher|null $teacher */
                $teacher   = $supervision->teacher;
                $typKey    = $supervision->supervision_type ?? 'waiting';
                $typeLabel = $typeLabels[$typKey] ?? 'Aufsicht';
                $typeClass = $typeClasses[$typKey] ?? 'type-waiting';
                $teacherName = $teacher
                    ? ($teacher->lastname . ', ' . $teacher->firstname . ' (' . $teacher->shortcode . ')')
                    : '–';
            @endphp
            <tr>
                <td class="time-cell">{{ $timeLabel }}</td>
                <td class="type-cell">
                    <span class="type-badge {{ $typeClass }}">{{ $typeLabel }}</span>
                </td>
                <td>{{ $teacherName }}</td>
                <td class="sig-cell">&nbsp;</td>
            </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
    @else
        <p class="no-supervision">Für diesen Raum sind noch keine Aufsichten zugeteilt.</p>
    @endif

    {{-- ── Besonderheiten ── --}}
    <div class="notes-section">
        <div class="notes-title">Besonderheiten / Anmerkungen</div>
        <table class="notes-lines">
            @for ($i = 0; $i < 6; $i++)
            <tr><td class="notes-line">&nbsp;</td></tr>
            @endfor
        </table>
    </div>

</div>
@endforeach

</body>
</html>
