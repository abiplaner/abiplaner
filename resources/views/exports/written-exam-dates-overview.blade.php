<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Prüfungstermine – Übersicht</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #222; margin: 0; }
        h1 { font-size: 14pt; margin: 0 0 2px 0; }
        .subtitle { font-size: 9pt; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #374151; color: #fff;
            padding: 5px 7px; text-align: left;
            font-size: 8.5pt; font-weight: bold;
        }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody tr { border-bottom: 1px solid #e5e7eb; }
        td { padding: 5px 7px; vertical-align: top; }
        .month-row td {
            background: #e5e7eb; color: #374151;
            font-weight: bold; font-size: 8pt;
            padding: 3px 7px;
        }
        .badge {
            display: inline-block; padding: 1px 5px;
            border-radius: 3px; font-size: 7.5pt;
            margin: 1px 1px 1px 0;
        }
        .badge-ga          { background: #d1fae5; color: #065f46; }
        .badge-ea          { background: #ede9fe; color: #5b21b6; }
        .badge-room        { background: #e0e7ff; color: #3730a3; }
        .badge-course-ok   { background: #d1fae5; color: #065f46; }
        .badge-course-room { background: #fef9c3; color: #854d0e; }
        .badge-course-none { background: #f3f4f6; color: #374151; }
        .text-muted { color: #9ca3af; font-style: italic; }
        .footer { margin-top: 18px; font-size: 7.5pt; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>

<h1>Schriftliche Prüfungstermine {{ $examYear ?? '' }}</h1>
<p class="subtitle">Erstellt am {{ now()->format('d.m.Y, H:i') }} Uhr</p>

<table>
    <thead>
        <tr>
            <th style="width:10%">Datum</th>
            <th style="width:6%">Zeit</th>
            <th style="width:20%">Prüfung</th>
            <th style="width:7%">Dauer</th>
            <th style="width:10%">Schüler</th>
            <th style="width:23%">Kurse</th>
            <th style="width:24%">Räume</th>
        </tr>
    </thead>
    <tbody>
        @php $currentMonth = null; @endphp
        @foreach($rows as $row)
            @php
                $month = \Carbon\Carbon::parse($row['exam_date'])->locale('de')->isoFormat('MMMM YYYY');
                $isGa  = $row['level'] === 'grundlegend';
            @endphp

            {{-- Monats-Trennzeile --}}
            @if($month !== $currentMonth)
                @php $currentMonth = $month; @endphp
                <tr class="month-row"><td colspan="7">{{ $month }}</td></tr>
            @endif

            <tr>
                {{-- Datum --}}
                <td>
                    {{ \Carbon\Carbon::parse($row['exam_date'])->locale('de')->isoFormat('dddd') }}<br>
                    {{ \Carbon\Carbon::parse($row['exam_date'])->format('d.m.Y') }}
                </td>

                {{-- Uhrzeit --}}
                <td>{{ \Carbon\Carbon::parse($row['start_time'])->format('H:i') }}</td>

                {{-- Fach + Niveau --}}
                <td>
                    @if($row['is_sammeltag'])
                        <strong>Sammeltag</strong><br>
                        <span style="font-size:8pt;color:#555;">{{ $row['subjects']->join(', ') }}</span>
                    @else
                        <strong>{{ $row['display_name'] }}</strong>
                    @endif
                    <br>
                    <span class="badge {{ $isGa ? 'badge-ga' : 'badge-ea' }}">{{ $isGa ? 'gA' : 'eA' }}</span>
                </td>

                {{-- Dauer --}}
                <td>
                    @if($row['duration'])
                        {{ $row['duration'] }}&thinsp;Min.
                    @else
                        <span style="color:#9ca3af;">–</span>
                    @endif
                </td>

                {{-- Schülerzahlen --}}
                <td>
                    @if($row['cntG'] || $row['cntE'])
                        @if($row['cntG'])<span class="badge badge-ga">{{ $row['cntG'] }} gA</span>@endif
                        @if($row['cntE'])<span class="badge badge-ea">{{ $row['cntE'] }} eA</span>@endif
                        <br><span style="font-size:7.5pt;color:#6b7280;">∑ {{ $row['cntG'] + $row['cntE'] }}</span>
                    @else
                        <span class="text-muted">–</span>
                    @endif
                </td>

                {{-- Kurse --}}
                <td>
                    @if($row['assignments']->isNotEmpty())
                        @foreach($row['assignments']->sortBy('course.course_code') as $a)
                            @php
                                $hasRoom  = $a->room_id !== null;
                                $hasSeats = ($a->seat_assignments_count ?? 0) > 0;
                                $cls = $hasRoom && $hasSeats ? 'badge-course-ok'
                                     : ($hasRoom ? 'badge-course-room' : 'badge-course-none');
                            @endphp
                            <span class="badge {{ $cls }}">{{ $a->course?->course_code ?? '?' }}@if($a->course?->teacher) ({{ $a->course->teacher->shortcode }})@endif</span>
                        @endforeach
                    @else
                        <span class="text-muted">Noch keine Kurse</span>
                    @endif
                </td>

                {{-- Räume --}}
                <td>
                    @if($row['assignments']->isNotEmpty())
                        @php
                            $rooms = $row['assignments']->filter(fn($a) => $a->room)
                                ->groupBy('room_id')
                                ->map(fn($g) => $g->first()->room);
                        @endphp
                        @if($rooms->isNotEmpty())
                            @foreach($rooms as $room)
                                <span class="badge badge-room">{{ $room->room_number }}</span>
                            @endforeach
                        @else
                            <span class="text-muted">Kein Raum</span>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<p class="footer">abiplaner &middot; {{ now()->format('d.m.Y') }}</p>
</body>
</html>
