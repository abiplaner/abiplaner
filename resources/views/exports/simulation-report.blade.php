<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Optimierungssimulation – Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #222; margin: 24px 28px; }
        h1 { font-size: 15pt; margin: 0 0 2px 0; }
        h2 { font-size: 11pt; margin: 20px 0 6px 0; color: #374151;
             border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        h3 { font-size: 10pt; margin: 14px 0 4px 0; }
        .subtitle { font-size: 9pt; color: #666; margin-bottom: 4px; }
        .intro { font-size: 8.5pt; color: #555; margin: 6px 0 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #374151; color: #fff;
            padding: 5px 7px; text-align: left;
            font-size: 8.5pt; font-weight: bold;
        }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody tr { border-bottom: 1px solid #e5e7eb; }
        td { padding: 5px 7px; vertical-align: top; }

        .kpi-table td { border-bottom: none; padding: 3px 7px; }
        .kpi-num { font-size: 13pt; font-weight: bold; color: #111; }
        .kpi-label { font-size: 7.5pt; color: #6b7280; }

        .badge {
            display: inline-block; padding: 1px 6px;
            border-radius: 3px; font-size: 7.5pt; font-weight: bold;
        }
        .badge-ok  { background: #d1fae5; color: #065f46; }
        .badge-bad { background: #fee2e2; color: #991b1b; }
        .tag-compact  { background: #d1fae5; color: #065f46; }
        .tag-spread   { background: #e0f2fe; color: #075985; }
        .tag-balanced { background: #ede9fe; color: #5b21b6; }

        .muted { color: #9ca3af; }
        .note {
            font-size: 8pt; color: #92400e;
            background: #fffbeb; border: 1px solid #fde68a;
            border-radius: 4px; padding: 6px 8px; margin: 6px 0 2px 0;
        }
        .day-head {
            background: #e5e7eb; color: #374151;
            font-weight: bold; font-size: 8.5pt;
            padding: 4px 7px; margin-top: 8px;
        }
        .plan-table { margin-bottom: 2px; }
        .plan-table thead th { background: #6b7280; font-size: 8pt; padding: 3px 6px; }
        .plan-table td { padding: 3px 6px; font-size: 8pt; }
        .suggestion-block { page-break-inside: auto; }
        .footer { margin-top: 18px; font-size: 7.5pt; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>

<h1>Optimierungssimulation – Report</h1>
<p class="subtitle">{{ $examPeriod->name }} &middot; erstellt am {{ now()->format('d.m.Y, H:i') }} Uhr</p>
<p class="intro">
    Die Simulation prüft, welche Kombinationen aus Prüfungstagen, Zeitslots pro Tag und
    Räumen ausreichen, um alle mündlichen Prüfungen konfliktfrei unterzubringen. Es wird
    nichts gespeichert oder verändert; der Report dient nur als Orientierung.
</p>

<h2>Ausgangslage (mündliche Prüfungen)</h2>
<table class="kpi-table">
    <tr>
        <td style="width:33%"><span class="kpi-num">{{ $demand['total'] }}</span><br>
            <span class="kpi-label">Mündliche Prüfungen gesamt</span></td>
        <td style="width:34%"><span class="kpi-num">{{ $demand['max_teacher_load'] }}</span><br>
            <span class="kpi-label">Höchste Last einer Lehrkraft (Prüfer + Beisitz)</span></td>
        <td style="width:33%"><span class="kpi-num">{{ $demand['max_student_load'] }}</span><br>
            <span class="kpi-label">Meiste Prüfungen eines Schülers</span></td>
    </tr>
</table>
<p class="intro">
    Untersuchter Suchbereich:
    {{ $ranges['days'][0] }}–{{ $ranges['days'][1] }} Tage,
    {{ $ranges['slots_per_day'][0] }}–{{ $ranges['slots_per_day'][1] }} Slots/Tag,
    {{ $ranges['rooms'][0] }}–{{ $ranges['rooms'][1] }} Räume.
    {{ $result['feasible_count'] }} machbare Kombinationen gefunden
    ({{ $result['dry_runs'] }} Simulationsläufe,
    {{ $result['skipped_arithmetic'] }} vorab rechnerisch ausgeschlossen).
</p>

@php
    $labels = [
        'compact'  => ['title' => 'Kompakt',    'desc' => 'Wenigste Prüfungstage',        'tag' => 'tag-compact'],
        'spread'   => ['title' => 'Entzerrt',   'desc' => 'Geringste Belastung pro Tag',  'tag' => 'tag-spread'],
        'balanced' => ['title' => 'Ausgewogen', 'desc' => 'Guter Kompromiss',             'tag' => 'tag-balanced'],
    ];
    $suggestions = $result['suggestions'] ?? [];
@endphp

<h2>Vergleich der Vorschläge</h2>

@if(empty($suggestions))
    <div class="note">
        Im untersuchten Bereich ließ sich keine Ausstattung finden, die alle
        {{ $demand['total'] }} mündlichen Prüfungen unterbringt. Erhöhen Sie die Obergrenzen –
        insbesondere bei Tagen und Slots/Tag, da die am stärksten belastete Lehrkraft
        mindestens {{ $demand['max_teacher_load'] }} Zeitpunkte benötigt.
    </div>
@else
    <table>
        <thead>
            <tr>
                <th style="width:22%">Variante</th>
                <th style="width:14%">Prüfungstage</th>
                <th style="width:14%">Slots/Tag</th>
                <th style="width:12%">Räume</th>
                <th style="width:16%">Kapazität</th>
                <th style="width:22%">Machbarkeit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($labels as $key => $meta)
                @if(isset($suggestions[$key]))
                    @php $s = $suggestions[$key]; @endphp
                    <tr>
                        <td><span class="badge {{ $meta['tag'] }}">{{ $meta['title'] }}</span><br>
                            <span class="kpi-label">{{ $meta['desc'] }}</span></td>
                        <td>{{ $s['days'] }}</td>
                        <td>{{ $s['slots_per_day'] }}</td>
                        <td>{{ $s['rooms'] }}</td>
                        <td>{{ $s['capacity'] }} Plätze</td>
                        <td>
                            @if($s['feasible'])
                                <span class="badge badge-ok">alle unterbringbar</span>
                            @else
                                <span class="badge badge-bad">{{ $s['unplaced'] }} offen</span>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif

@if(!empty($suggestions))
    <h2>Exemplarische Belegung je Vorschlag</h2>
    <div class="note">
        Die folgenden Belegungen sind <strong>Beispiele zur Plausibilisierung</strong>.
        Räume (SIM-…) und Uhrzeiten sind Platzhalter; die konkreten Prüfungstage, Zeiten und
        Räume legen Sie anschließend wie gewohnt in der Planung an.
    </div>

    @foreach($labels as $key => $meta)
        @if(isset($suggestions[$key]))
            @php
                $s = $suggestions[$key];
                $plan = $s['plan'] ?? [];
            @endphp
            <div class="suggestion-block">
                <h3>
                    <span class="badge {{ $meta['tag'] }}">{{ $meta['title'] }}</span>
                    &nbsp;{{ $s['days'] }} Tage &middot; {{ $s['slots_per_day'] }} Slots/Tag &middot; {{ $s['rooms'] }} Räume
                </h3>

                @if(empty($plan))
                    <p class="intro muted">Keine Belegung verfügbar.</p>
                @else
                    @foreach($plan as $day)
                        <div class="day-head">
                            {{ $day['weekday'] }},
                            {{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}
                            &middot; {{ $day['count'] }} Prüfung(en)
                        </div>
                        <table class="plan-table">
                            <thead>
                                <tr>
                                    <th style="width:8%">Slot</th>
                                    <th style="width:14%">Raum</th>
                                    <th style="width:34%">Schüler</th>
                                    <th style="width:22%">Fach</th>
                                    <th style="width:11%">Prüfer</th>
                                    <th style="width:11%">Beisitz</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($day['slots'] as $slot)
                                    @foreach($slot['exams'] as $exam)
                                        <tr>
                                            <td>{{ $loop->first ? $slot['slot_number'] : '' }}</td>
                                            <td>{{ $exam['room'] }}</td>
                                            <td>{{ $exam['student'] }}</td>
                                            <td>{{ $exam['subject'] }}</td>
                                            <td>{{ $exam['examiner'] }}</td>
                                            <td>{{ $exam['assessor'] }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                @endif
            </div>
        @endif
    @endforeach
@endif

<p class="footer">abiplaner &middot; Optimierungssimulation &middot; {{ now()->format('d.m.Y') }}</p>
</body>
</html>
