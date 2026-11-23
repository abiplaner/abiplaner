<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>W-Seminar Wahlübersicht {{ $schuljahr }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            margin: 20px;
        }
        h1 {
            font-size: 16pt;
            margin-bottom: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }
        h2 {
            font-size: 12pt;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .header-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }        .stats {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .stat-item {
            display: table-cell;
            padding: 10px;
            text-align: center;
            background-color: #e5e7eb;
            border: 1px solid #d1d5db;
        }
        .stat-label {
            font-size: 8pt;
            color: #666;
        }
        .stat-value {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #e5e7eb;
            padding: 8px;
            text-align: left;
            font-size: 9pt;
            border: 1px solid #d1d5db;
        }
        td {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            font-size: 9pt;
        }        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
        .info-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <h1>W-Seminar Wahlübersicht</h1>
    
    <div class="header-info">
        <strong>Schuljahr:</strong> {{ $schuljahr }}<br>
        <strong>Jahrgang:</strong> Q{{ $jahrgang - 10 }} (Jahrgangsstufe {{ $jahrgang }})<br>
        <strong>Erstellt am:</strong> {{ date('d.m.Y H:i') }} Uhr
    </div>
    
    <div class="info-box">
        <strong>Hinweise zur Planung:</strong><br>
        • Jedes W-Seminar hat maximal 15 Teilnehmer<br>
        • Schüler können 3 Prioritäten wählen (Erstwahl, Zweitwahl, Drittwahl)<br>
        • Die automatische Zuteilung erfolgt nach verschiedenen Strategien<br>
        <br>
        <strong>Wahlbeteiligung:</strong><br>
        @if(isset($analyse['gesamt_schueler_jahrgang']) && $analyse['gesamt_schueler_jahrgang'] > 0)
            @php
                $prozent = round(($analyse['schueler_mit_wahlen'] / $analyse['gesamt_schueler_jahrgang']) * 100, 1);
            @endphp
            {{ $analyse['schueler_mit_wahlen'] }} von {{ $analyse['gesamt_schueler_jahrgang'] }} Schülern haben gewählt ({{ $prozent }}%)
            @if($prozent < 100)
                <br><span style="color: #dc2626; font-weight: bold;">⚠ {{ $analyse['gesamt_schueler_jahrgang'] - $analyse['schueler_mit_wahlen'] }} Schüler fehlen noch</span>
            @endif
        @else
            {{ $wahlenCount }} Schüler haben gewählt
        @endif
    </div>    
    <h2>Statistik</h2>
    <div class="stats">
        <div class="stat-item">
            <div class="stat-label">Schüler im Jahrgang</div>
            <div class="stat-value">{{ $analyse['gesamt_schueler_jahrgang'] ?? 0 }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Schüler mit Wahlen</div>
            <div class="stat-value">{{ $analyse['schueler_mit_wahlen'] ?? 0 }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Anzahl Seminare</div>
            <div class="stat-value">{{ count($analyse['seminar_statistik']) }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Plätze verfügbar</div>
            <div class="stat-value">{{ (count($analyse['seminar_statistik']) * 15) }}</div>
        </div>
    </div>
    
    <h2>Wahlübersicht nach W-Seminar</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 7%; text-align: center;">Nr.</th>
                <th style="width: 40%;">W-Seminar</th>
                <th style="width: 13%; text-align: center;">Erstwahlen</th>
                <th style="width: 13%; text-align: center;">Zweitwahlen</th>
                <th style="width: 13%; text-align: center;">Drittwahlen</th>
                <th style="width: 14%; text-align: center;">Gesamt</th>
            </tr>
        </thead>        <tbody>
            @foreach($analyse['seminar_statistik'] as $index => $seminar)
                <tr style="{{ $seminar['gesamt'] < 10 ? 'background-color: #fee2e2;' : '' }}">
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $seminar['subject_name'] }}</strong><br>
                        <span style="font-size: 8pt; color: #666;">{{ $seminar['titel'] }}</span>
                        @if($seminar['gesamt'] < 10)
                            <br><span style="font-size: 7pt; color: #dc2626; font-weight: bold;">⚠ Zu wenig Nachfrage (< 10)</span>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $seminar['erstwahlen'] }}</td>
                    <td style="text-align: center;">{{ $seminar['zweitwahlen'] }}</td>
                    <td style="text-align: center;">{{ $seminar['drittwahlen'] }}</td>
                    <td style="text-align: center; {{ $seminar['gesamt'] < 10 ? 'font-weight: bold; color: #dc2626;' : 'font-weight: bold;' }}">
                        {{ $seminar['gesamt'] }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #e5e7eb; font-weight: bold;">
                <td></td>
                <td>Summe</td>
                <td style="text-align: center;">
                    {{ collect($analyse['seminar_statistik'])->sum('erstwahlen') }}
                </td>
                <td style="text-align: center;">
                    {{ collect($analyse['seminar_statistik'])->sum('zweitwahlen') }}
                </td>
                <td style="text-align: center;">
                    {{ collect($analyse['seminar_statistik'])->sum('drittwahlen') }}
                </td>
                <td style="text-align: center;">
                    {{ collect($analyse['seminar_statistik'])->sum('gesamt') }}
                </td>
            </tr>
        </tfoot>
    </table>    
    @php
        $hochNachfrage = collect($analyse['seminar_statistik'])->filter(fn($s) => $s['erstwahlen'] > 15)->sortByDesc('erstwahlen');
        $niedrigNachfrage = collect($analyse['seminar_statistik'])->filter(fn($s) => $s['gesamt'] < 8)->sortBy('gesamt');
    @endphp
    
    @if($hochNachfrage->count() > 0)
        <div style="page-break-inside: avoid; margin-top: 20px;">
            <h2>Seminare mit hoher Erstnachfrage (> 15 Schüler)</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60%;">W-Seminar</th>
                        <th style="width: 20%; text-align: center;">Erstwahlen</th>
                        <th style="width: 20%; text-align: center;">Überhang</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hochNachfrage as $seminar)
                        <tr>
                            <td>
                                <strong>{{ $seminar['subject_name'] }}</strong><br>
                                <span style="font-size: 8pt; color: #666;">{{ $seminar['titel'] }}</span>
                            </td>
                            <td style="text-align: center;">{{ $seminar['erstwahlen'] }}</td>
                            <td style="text-align: center; color: #dc2626;">
                                +{{ $seminar['erstwahlen'] - 15 }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif    
    @if($niedrigNachfrage->count() > 0)
        <div style="page-break-inside: avoid; margin-top: 20px;">
            <h2>Seminare mit niedriger Gesamtnachfrage (< 8 Schüler)</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60%;">W-Seminar</th>
                        <th style="width: 20%; text-align: center;">Erstwahlen</th>
                        <th style="width: 20%; text-align: center;">Gesamt</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($niedrigNachfrage as $seminar)
                        <tr>
                            <td>
                                <strong>{{ $seminar['subject_name'] }}</strong><br>
                                <span style="font-size: 8pt; color: #666;">{{ $seminar['titel'] }}</span>
                            </td>
                            <td style="text-align: center;">{{ $seminar['erstwahlen'] }}</td>
                            <td style="text-align: center;">{{ $seminar['gesamt'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    
    <div class="footer">
        Erstellt mit abiplaner | {{ date('d.m.Y H:i') }} Uhr
    </div>
</body>
</html>
