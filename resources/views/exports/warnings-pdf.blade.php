<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planungs-Warnungen - {{ $examPeriod->name }}</title>
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
        }
        
        .warning {
            margin-bottom: 15px;
            padding: 10px;
            border-left: 4px solid #f59e0b;
            background-color: #fffbeb;
            page-break-inside: avoid;
        }
        
        .warning.error {
            border-left-color: #ef4444;
            background-color: #fef2f2;
        }
        
        .warning-header {
            font-weight: bold;
            margin-bottom: 8px;
            color: #92400e;
        }
        
        .warning.error .warning-header {
            color: #991b1b;
        }
        
        .warning-number {
            display: inline-block;
            width: 30px;
            font-weight: bold;
        }
        
        .warning-message {
            margin-bottom: 8px;
        }
        
        .warning-details {
            font-size: 9pt;
            color: #666;
            margin-left: 30px;
            border-left: 2px solid #fbbf24;
            padding-left: 10px;
        }
        
        .warning.error .warning-details {
            border-left-color: #fca5a5;
        }
        
        .warning-details .detail-row {
            margin: 3px 0;
        }
        
        .warning-details .detail-label {
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
        }
        
        .warning-details ul {
            margin: 5px 0 5px 20px;
            padding: 0;
        }
        
        .warning-details ul li {
            margin: 2px 0;
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
        
        .page-break {
            page-break-after: always;
        }
        
        .summary {
            background-color: #eff6ff;
            border: 1px solid #93c5fd;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 3px;
        }
        
        .summary h3 {
            margin: 0 0 10px 0;
            color: #1e40af;
            font-size: 12pt;
        }
        
        .summary-stats {
            display: table;
            width: 100%;
        }
        
        .summary-stat {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 5px;
        }
        
        .summary-stat .number {
            font-size: 18pt;
            font-weight: bold;
            color: #1e40af;
        }
        
        .summary-stat .label {
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>⚠️ Planungs-Warnungen</h1>
        <div class="subtitle">{{ $examPeriod->name }}</div>
    </div>
    
    {{-- Info-Box --}}
    <div class="info-box">
        <p><strong>Schule:</strong> {{ $school->name }}</p>
        <p><strong>Prüfungszeitraum:</strong> 
            Woche 1: {{ \Carbon\Carbon::parse($examPeriod->week_1_start)->locale('de')->translatedFormat('D, d.m.Y') }} - {{ \Carbon\Carbon::parse($examPeriod->week_1_end)->locale('de')->translatedFormat('D, d.m.Y') }},
            Woche 2: {{ \Carbon\Carbon::parse($examPeriod->week_2_start)->locale('de')->translatedFormat('D, d.m.Y') }} - {{ \Carbon\Carbon::parse($examPeriod->week_2_end)->locale('de')->translatedFormat('D, d.m.Y') }}
        </p>
        <p><strong>Exportiert am:</strong> {{ $generatedAt }}</p>
    </div>
    
    {{-- Zusammenfassung --}}
    <div class="summary">
        <h3>Zusammenfassung</h3>
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number">{{ count($warnings) }}</div>
                <div class="label">Gesamt</div>
            </div>
            <div class="summary-stat">
                <div class="number">{{ collect($warnings)->where('severity', 'error')->count() }}</div>
                <div class="label">Fehler</div>
            </div>
            <div class="summary-stat">
                <div class="number">{{ collect($warnings)->where('severity', 'warning')->count() }}</div>
                <div class="label">Warnungen</div>
            </div>
        </div>
    </div>
    
    {{-- Warnungen --}}
    @foreach($warnings as $index => $warning)
        <div class="warning {{ $warning['severity'] === 'error' ? 'error' : '' }}">
            <div class="warning-header">
                <span class="warning-number">{{ $index + 1 }}.</span>
                {{ $warning['severity'] === 'error' ? '❌' : '⚠️' }} 
                {{ $warning['type'] ?? 'warning' }}
            </div>
            
            <div class="warning-message">
                {{ $warning['message'] }}
            </div>
            
            @if(isset($warning['details']) && !empty($warning['details']))
                <div class="warning-details">
                    @if(isset($warning['details']['student']))
                        <div class="detail-row">
                            <span class="detail-label">Schüler:</span>
                            {{ $warning['details']['student'] }}
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['course']))
                        <div class="detail-row">
                            <span class="detail-label">Kurs:</span>
                            {{ $warning['details']['course'] }}
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['examiner']))
                        <div class="detail-row">
                            <span class="detail-label">Prüfer:</span>
                            {{ $warning['details']['examiner'] }}
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['size']))
                        <div class="detail-row">
                            <span class="detail-label">Größe:</span>
                            {{ $warning['details']['size'] }} Prüfungen
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['week']))
                        <div class="detail-row">
                            <span class="detail-label">Woche:</span>
                            {{ $warning['details']['week'] }}
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['week1_slot']) && !empty($warning['details']['week1_slot']))
                        <div class="detail-row">
                            <span class="detail-label">Woche 1 Zeitslot:</span>
                            {{ $warning['details']['week1_slot'] }}
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['week2_slot']) && !empty($warning['details']['week2_slot']))
                        <div class="detail-row">
                            <span class="detail-label">Woche 2 Zeitslot:</span>
                            {{ $warning['details']['week2_slot'] }}
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['students']) && is_array($warning['details']['students']) && count($warning['details']['students']) > 0)
                        <div class="detail-row">
                            <span class="detail-label">Betroffene Schüler:</span>
                            <ul>
                                @foreach($warning['details']['students'] as $student)
                                    <li>{{ $student }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    @if(isset($warning['details']['conflicts']) && is_array($warning['details']['conflicts']) && count($warning['details']['conflicts']) > 0)
                        <div class="detail-row">
                            <span class="detail-label">Konflikte:</span>
                            <ul>
                                @foreach($warning['details']['conflicts'] as $conflict)
                                    <li>{{ $conflict }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
        
        {{-- Page break alle 10 Warnungen --}}
        @if(($index + 1) % 10 === 0 && $index + 1 < count($warnings))
            <div class="page-break"></div>
        @endif
    @endforeach
    
    {{-- Footer --}}
    <div class="footer">
        Seite <span class="pagenum"></span> | Exportiert am {{ $generatedAt }} | {{ $school->name }}
    </div>
</body>
</html>
