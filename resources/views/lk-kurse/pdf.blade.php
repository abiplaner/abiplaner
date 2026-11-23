<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Leistungsfach-Kurse {{ $schuljahr }}</title>
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
        }
        .stats {
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
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .extern-badge {
            display: inline-block;
            padding: 2px 6px;
            background-color: #ddd6fe;
            color: #5b21b6;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .schueler-list {
            font-size: 8pt;
            color: #666;
            margin-top: 3px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Leistungsfach-Kurse</h1>
    
    <div class="header-info">
        <strong>Schuljahr:</strong> {{ $schuljahr }}<br>
        <strong>Jahrgang:</strong> Q{{ $jahrgang - 10 }} (Jahrgangsstufe {{ $jahrgang }})<br>
        <strong>Erstellt am:</strong> {{ date('d.m.Y H:i') }} Uhr
    </div>
    
    <h2>Statistik</h2>
    <div class="stats">
        <div class="stat-item">
            <div class="stat-label">Kurse gesamt</div>
            <div class="stat-value">
                @php
                    $internalCount = $kurse->filter(fn($k) => !$k->ist_extern)->count();
                    $externalCount = $kurse->filter(fn($k) => $k->ist_extern)->count();
                @endphp
                {{ $internalCount }}
                @if($externalCount > 0)
                    <span style="font-size: 10pt; font-weight: normal; color: #666;">+ {{ $externalCount }} (extern)</span>
                @endif
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Erstwahl erfüllt</div>
            <div class="stat-value">{{ $wahlStatistik['erstwahl_prozent'] }}%</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Zweitwahl erfüllt</div>
            <div class="stat-value">{{ $wahlStatistik['zweitwahl_prozent'] }}%</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Schüler gesamt</div>
            <div class="stat-value">{{ $wahlStatistik['gesamt_schueler'] }}</div>
        </div>
    </div>
    
    @if(isset($unassignedWahlen) && $unassignedWahlen->count() > 0)
        <h2>Nicht zugeteilte Schüler ({{ $unassignedWahlen->count() }})</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Klasse</th>
                    <th style="width: 30%;">Nachname</th>
                    <th style="width: 30%;">Vorname</th>
                    <th style="width: 25%;">Wünsche</th>
                </tr>
            </thead>
            <tbody>
                @foreach($unassignedWahlen->sortBy('schueler.lastname') as $wahl)
                    <tr>
                        <td>{{ $wahl->schueler->class_name ?? '-' }}</td>
                        <td>{{ $wahl->schueler->lastname ?? '-' }}</td>
                        <td>{{ $wahl->schueler->firstname ?? '-' }}</td>
                        <td>
                            {{ $wahl->erstwahl->name ?? '-' }} / {{ $wahl->zweitwahl->name ?? '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <br>
    @endif
    
    <h2>
        @php
            $internalCount = $kurse->filter(fn($k) => !$k->ist_extern)->count();
            $externalCount = $kurse->filter(fn($k) => $k->ist_extern)->count();
        @endphp
        Kurse ({{ $internalCount }}@if($externalCount > 0) + {{ $externalCount }} (extern)@endif)
    </h2>
    <table>
        <thead>
            <tr>
                <th style="width: 25%;">Fach</th>
                <th style="width: 8%;">Kurs</th>
                <th style="width: 20%;">Lehrer</th>
                <th style="width: 10%;">Extern</th>
                <th style="width: 10%;">Schüler</th>
                <th style="width: 27%;">Bemerkung</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kurse as $kurs)
                <tr>
                    <td>{{ $kurs->subject->name ?? '-' }}</td>
                    <td style="text-align: center;">{{ $kurs->kurs_nummer }}</td>
                    <td>
                        @if($kurs->lehrer)
                            {{ $kurs->lehrer->last_name }}, {{ $kurs->lehrer->first_name }}
                        @else
                            -
                        @endif
                    </td>
                    <td style="text-align: center;">
                        @if($kurs->ist_extern)
                            <span class="extern-badge">Extern</span>
                        @else
                            -
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $kurs->wahlen->count() }}</td>
                    <td style="font-size: 8pt;">{{ $kurs->notizen ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div style="page-break-before: always;"></div>
    
    <h2>Schülerlisten pro Kurs</h2>
    @foreach($kurse as $kurs)
        <div style="page-break-inside: avoid;">
            <h3 style="font-size: 14pt; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">
                {{ $kurs->subject->name ?? '-' }} (Kurs {{ $kurs->kurs_nummer }})
                @if($kurs->ist_extern)
                    <span class="extern-badge">Extern</span>
                @endif
            </h3>
            
            @if($kurs->lehrer)
                <div style="font-size: 10pt; color: #444; margin-bottom: 10px;">
                    <strong>Lehrer:</strong> {{ $kurs->lehrer->lastname ?? $kurs->lehrer->last_name ?? '' }}, {{ $kurs->lehrer->firstname ?? $kurs->lehrer->first_name ?? '' }}
                </div>
            @endif
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">Nr.</th>
                        <th style="width: 15%;">Klasse</th>
                        <th style="width: 30%;">Nachname</th>
                        <th style="width: 30%;">Vorname</th>
                        <th style="width: 20%;">Priorität</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($kurs->wahlen->sortBy(fn($w) => $w->schueler->lastname ?? '') as $wahl)
                        <tr>
                            <td style="text-align: center;">{{ $loop->iteration }}</td>
                            <td>{{ $wahl->schueler->class_name ?? '-' }}</td>
                            <td>{{ $wahl->schueler->lastname ?? '-' }}</td>
                            <td>{{ $wahl->schueler->firstname ?? '-' }}</td>
                            <td style="text-align: center;">
                                @if($wahl->prioritaet_erhalten === 'erstwahl')
                                    1. Wahl
                                @elseif($wahl->prioritaet_erhalten === 'zweitwahl')
                                    2. Wahl
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">Keine Schüler zugeteilt</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(!$loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
    
    <div class="footer">
        Erstellt mit abiplaner | {{ date('d.m.Y H:i') }} Uhr
    </div>
</body>
</html>
