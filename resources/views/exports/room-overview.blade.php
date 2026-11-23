<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raumübersicht - {{ $writtenExamDate->name }}</title>
    @include('exports.partials.pdf-base-styles')
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #666;
        }
        
        .header h1 {
            font-size: 18pt;
            margin: 0 0 5px 0;
            color: #000;
        }
        
        .header .subtitle {
            font-size: 11pt;
            color: #666;
            margin: 5px 0;
        }
        
        .summary {
            background-color: #eff6ff;
            border: 1px solid #93c5fd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 3px;
        }
        
        .summary h3 {
            margin: 0 0 15px 0;
            color: #1e40af;
            font-size: 12pt;
        }
        
        .summary-stats {
            display: table;
            width: 100%;
        }
        
        .summary-stat {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 5px;
        }
        
        .summary-stat .number {
            font-size: 20pt;
            font-weight: bold;
            color: #1e40af;
        }
        
        .summary-stat .label {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
        
        .room-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .room-table thead {
            background-color: #1e40af;
            color: white;
        }
        
        .room-table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1e40af;
        }
        
        .room-table th.center {
            text-align: center;
        }
        
        .room-table tbody tr {
            border-bottom: 1px solid #cbd5e1;
        }
        
        .room-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .room-table td {
            padding: 10px;
            border: 1px solid #cbd5e1;
        }
        
        .room-table td.center {
            text-align: center;
        }
        
        .room-table td.number {
            font-weight: bold;
            color: #1e40af;
        }
        
        .room-name {
            font-weight: bold;
            font-size: 11pt;
        }
        
        .course-badge {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }
        
        .utilization-bar {
            width: 100%;
            height: 20px;
            background-color: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }
        
        .utilization-fill {
            height: 100%;
            background-color: #10b981;
            transition: width 0.3s ease;
        }
        
        .utilization-fill.high {
            background-color: #f59e0b;
        }
        
        .utilization-fill.full {
            background-color: #ef4444;
        }
        
        .utilization-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 8pt;
            font-weight: bold;
            color: #000;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #999;
            padding: 10px;
            border-top: 1px solid #ddd;
        }
        
        .legend {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .legend h4 {
            margin: 0 0 10px 0;
            font-size: 10pt;
        }
        
        .legend-items {
            display: table;
            width: 100%;
        }
        
        .legend-item {
            display: table-cell;
            width: 33.33%;
            padding: 5px;
            font-size: 9pt;
        }
        
        .legend-color {
            display: inline-block;
            width: 20px;
            height: 10px;
            margin-right: 8px;
            border-radius: 2px;
            vertical-align: middle;
        }
        
        .legend-color.low {
            background-color: #10b981;
        }
        
        .legend-color.high {
            background-color: #f59e0b;
        }
        
        .legend-color.full {
            background-color: #ef4444;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>📊 Raumübersicht</h1>
        <div class="subtitle">{{ $writtenExamDate->name }}</div>
        <div class="subtitle">{{ $writtenExamDate->date->locale('de')->translatedFormat('l, d. F Y') }} | {{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr</div>
    </div>
    
    {{-- Info-Box --}}
    <div class="info-box">
        <p><strong>{{ $writtenExamDate->slot->subjects->count() === 1 ? 'Fach' : 'Fächer' }}:</strong>
            {{ $writtenExamDate->slot->subjects->map(fn($s) => $s->name . ' (' . $s->code . ')')->implode(', ') }}</p>
        <p><strong>Anforderungsniveau:</strong>
            {{ $writtenExamDate->slot->requirement_level === 'erhoeht' ? 'Erhöht' : 'Grundlegend' }}</p>
        <p><strong>Exportiert am:</strong> {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr</p>
    </div>
    
    {{-- Zusammenfassung --}}
    @php
        $totalCapacity = $roomsData->sum('capacity');
        $totalOccupied = $roomsData->sum('occupied');
        $totalFree = $roomsData->sum('free');
        $avgUtilization = $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100, 1) : 0;
    @endphp
    
    <div class="summary">
        <h3>Gesamtstatistik</h3>
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number">{{ $roomsData->count() }}</div>
                <div class="label">Räume</div>
            </div>
            <div class="summary-stat">
                <div class="number">{{ $totalCapacity }}</div>
                <div class="label">Kapazität gesamt</div>
            </div>
            <div class="summary-stat">
                <div class="number">{{ $totalOccupied }}</div>
                <div class="label">Belegt</div>
            </div>
            <div class="summary-stat">
                <div class="number">{{ $avgUtilization }}%</div>
                <div class="label">Durchschn. Auslastung</div>
            </div>
        </div>
    </div>
    
    {{-- Raumtabelle --}}
    <table class="room-table">
        <thead>
            <tr>
                <th>Raum</th>
                <th>Kurs</th>
                <th class="center" style="width: 80px;">Kapazität</th>
                <th class="center" style="width: 80px;">Belegt</th>
                <th class="center" style="width: 80px;">Frei</th>
                <th style="width: 150px;">Auslastung</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roomsData->sortBy('room.name') as $data)
                @php
                    $utilizationClass = '';
                    if ($data['utilization'] >= 95) {
                        $utilizationClass = 'full';
                    } elseif ($data['utilization'] >= 80) {
                        $utilizationClass = 'high';
                    }
                @endphp
                <tr>
                    <td>
                        <span class="room-name">{{ $data['room']->name }}</span>
                    </td>
                    <td>
                        <span class="course-badge">{{ $data['course']->name }}</span>
                    </td>
                    <td class="center number">{{ $data['capacity'] }}</td>
                    <td class="center number">{{ $data['occupied'] }}</td>
                    <td class="center number">{{ $data['free'] }}</td>
                    <td>
                        <div class="utilization-bar">
                            <div class="utilization-fill {{ $utilizationClass }}" 
                                 style="width: {{ $data['utilization'] }}%;">
                            </div>
                            <div class="utilization-text">{{ $data['utilization'] }}%</div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    {{-- Legende --}}
    <div class="legend">
        <h4>Auslastung - Legende</h4>
        <div class="legend-items">
            <div class="legend-item">
                <span class="legend-color low"></span>
                <span>&lt; 80% - Optimal</span>
            </div>
            <div class="legend-item">
                <span class="legend-color high"></span>
                <span>80-94% - Hoch</span>
            </div>
            <div class="legend-item">
                <span class="legend-color full"></span>
                <span>≥ 95% - Voll</span>
            </div>
        </div>
    </div>
    
    {{-- Footer --}}
    <div class="footer">
        Exportiert am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr
    </div>
</body>
</html>
