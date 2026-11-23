<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aufsichtslisten - {{ $writtenExamDate->name }}</title>
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
        
        .room-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 15px;
            background-color: #f8fafc;
        }
        
        .room-header {
            background-color: #1e40af;
            color: white;
            padding: 10px 15px;
            margin: -15px -15px 15px -15px;
            border-radius: 5px 5px 0 0;
            font-size: 12pt;
            font-weight: bold;
        }
        
        .room-details {
            margin-bottom: 15px;
        }
        
        .room-details p {
            margin: 5px 0;
        }
        
        .supervision-list {
            margin: 15px 0;
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 10px;
        }
        
        .supervision-list h4 {
            margin: 0 0 10px 0;
            font-size: 10pt;
            color: #1e40af;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
        }
        
        .supervision-list ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        
        .supervision-list li {
            margin: 5px 0;
        }
        
        .materials-checklist {
            margin: 15px 0;
            background-color: #fffbeb;
            border: 1px solid #fbbf24;
            border-radius: 3px;
            padding: 10px;
        }
        
        .materials-checklist h4 {
            margin: 0 0 10px 0;
            font-size: 10pt;
            color: #92400e;
        }
        
        .materials-subject-group {
            margin-bottom: 8px;
        }
        
        .materials-subject-title {
            font-weight: bold;
            font-size: 9pt;
            color: #92400e;
            margin: 6px 0 3px 0;
            border-bottom: 1px solid #fde68a;
            padding-bottom: 2px;
        }
        
        .checklist-item {
            margin: 5px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .checklist-item:before {
            content: '☐';
            position: absolute;
            left: 0;
            font-size: 12pt;
        }
        
        .checklist-item.required:after {
            content: ' *';
            color: #ef4444;
            font-weight: bold;
        }
        
        .compensation-alert {
            margin: 15px 0;
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 3px;
            padding: 10px;
        }
        
        .compensation-alert h4 {
            margin: 0 0 8px 0;
            font-size: 10pt;
            color: #991b1b;
        }
        
        .compensation-list {
            font-size: 9pt;
            margin: 0;
            padding-left: 20px;
        }
        
        .compensation-list li {
            margin: 5px 0;
        }
        
        .statistics {
            display: table;
            width: 100%;
            margin: 10px 0;
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
        }
        
        .stat-item {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
        }
        
        .stat-item:last-child {
            border-right: none;
        }
        
        .stat-value {
            font-size: 16pt;
            font-weight: bold;
            color: #1e40af;
        }
        
        .stat-label {
            font-size: 8pt;
            color: #666;
            text-transform: uppercase;
            margin-top: 3px;
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
        
        .no-supervisions {
            color: #ef4444;
            font-weight: bold;
            padding: 10px;
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 3px;
            text-align: center;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>👥 Aufsichtslisten</h1>
        <div class="subtitle">{{ $writtenExamDate->name }}</div>
        <div class="subtitle">{{ $writtenExamDate->date->locale('de')->translatedFormat('l, d. F Y') }} | {{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr</div>
    </div>
    
    {{-- Info-Box --}}
    <div class="info-box">
        <p><strong>{{ $writtenExamDate->slot->subjects->count() === 1 ? 'Fach' : 'Fächer' }}:</strong>
            {{ $writtenExamDate->slot->subjects->map(fn($s) => $s->name . ' (' . $s->code . ')')->implode(', ') }}</p>
        <p><strong>Anforderungsniveau:</strong>
            {{ $writtenExamDate->slot->requirement_level === 'erhoeht' ? 'Erhöht' : 'Grundlegend' }}</p>
        <p><strong>Dauer:</strong> {{ $writtenExamDate->slot->getDurationMinutes() }} Min
            ({{ $writtenExamDate->getStartTime()->format('H:i') }}–{{ $writtenExamDate->getEndTime()->format('H:i') }} Uhr)</p>
        <p><strong>Exportiert am:</strong> {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr</p>
    </div>
    
    {{-- Gesamtstatistik --}}
    <div class="statistics">
        <div class="stat-item">
            <div class="stat-value">{{ $assignments->count() }}</div>
            <div class="stat-label">Räume</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $assignments->sum(fn($a) => $a->seatAssignments->count()) }}</div>
            <div class="stat-label">Schüler</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $assignments->sum(fn($a) => $a->supervisions->count()) }}</div>
            <div class="stat-label">Aufsichten</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $writtenExamDate->materials->count() }}</div>
            <div class="stat-label">Materialien</div>
        </div>
    </div>
    
    {{-- Raum-Sektionen --}}
    @foreach($assignments as $index => $assignment)
        <div class="room-section">
            <div class="room-header">
                📍 {{ $assignment->room->room_number }}
            </div>
            
            <div class="room-details">
                <p><strong>Kurs:</strong> {{ $assignment->course->name }}</p>
                <p><strong>Anzahl Schüler:</strong> {{ $assignment->seatAssignments->count() }}{{ $assignment->getSeatingTemplate() ? ' / ' . $assignment->getSeatingTemplate()->getTotalCapacity() . ' Plätze' : '' }}</p>
                @if($assignment->notes)
                    <p><strong>Hinweise:</strong> {{ $assignment->notes }}</p>
                @endif
            </div>
            
            {{-- Aufsichten --}}
            <div class="supervision-list">
                <h4>👥 Aufsichten</h4>
                
                @php
                    $roomSupervisions = $assignment->supervisions->where('supervision_type', 'room');
                    $corridorSupervisions = $assignment->supervisions->where('supervision_type', 'corridor');
                @endphp
                
                @if($roomSupervisions->count() > 0)
                    <p><strong>Raumaufsichten:</strong></p>
                    <ul>
                        @foreach($roomSupervisions as $supervision)
                            <li>{{ $supervision->teacher ? $supervision->teacher->firstname . ' ' . $supervision->teacher->lastname : '— (kein Lehrer zugewiesen)' }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="no-supervisions">⚠️ Keine Raumaufsichten zugewiesen!</p>
                @endif
                
                @if($corridorSupervisions->count() > 0)
                    <p><strong>Gangaufsichten:</strong></p>
                    <ul>
                        @foreach($corridorSupervisions as $supervision)
                            <li>{{ $supervision->teacher ? $supervision->teacher->firstname . ' ' . $supervision->teacher->lastname : '— (kein Lehrer zugewiesen)' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            
            {{-- Material-Checkliste (nach Fach gruppiert) --}}
            @if($writtenExamDate->materialsBySubject->isNotEmpty())
                <div class="materials-checklist">
                    <h4>📋 Material-Checkliste</h4>
                    @foreach($writtenExamDate->materialsBySubject as $group)
                        <div class="materials-subject-group">
                            <div class="materials-subject-title">{{ $group['subject']->name }}</div>
                            @foreach($group['materials'] as $material)
                                <div class="checklist-item {{ $material->pivot_is_required ? 'required' : '' }}">
                                    {{ $material->name }}
                                    @if($material->pivot_notes)
                                        <span style="color: #666; font-size: 9pt;"> - {{ $material->pivot_notes }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <p style="font-size: 8pt; color: #666; margin-top: 10px;">* = Pflichtmaterial</p>
                </div>
            @endif
            
            {{-- Nachteilsausgleich --}}
            @php
                // Auch externe Bewerber können einen Nachteilsausgleich haben.
                // Der Filter fragte bis zum 27.08. nur den eigenen Schüler ab —
                // die Aufsicht hätte den Externen um Punkt Schluss abgegeben.
                $studentsWithCompensation = $assignment->seatAssignments->filter(function($sa) {
                    return $sa->nachteilsausgleichProzent() > 0;
                });
                // Die Platznummer stand hier bis zum 27.08. als „—": gelesen
                // wurde seat->seat_number, eine Spalte, die es nicht gibt.
                // Gerade sie ist für die Aufsicht die nützliche Angabe.
                $platznummern = $assignment->platznummern();
            @endphp
            
            @if($studentsWithCompensation->count() > 0)
                <div class="compensation-alert">
                    <h4>⚠️ Schüler mit Nachteilsausgleich</h4>
                    <ul class="compensation-list">
                        @foreach($studentsWithCompensation as $sa)
                            @php
                                $prozent = $sa->nachteilsausgleichProzent();
                                $notiz   = $sa->pruefling()?->disability_compensation_notes;
                                $endTime = $writtenExamDate->getEndTimeForLevel(
                                    $assignment->course->level ?? 'grundlegend',
                                    $prozent
                                );
                            @endphp
                            <li>
                                <strong>{{ $sa->anzeigename() }}</strong>@if($sa->istExterner()) <em style="color:#666;">(externer Bewerber)</em>@endif
                                (Platz {{ $platznummern[$sa->seat_id] ?? '—' }}): 
                                +{{ $prozent }}% Zeit
                                → Ende: {{ $endTime->format('H:i') }} Uhr
                                @if($notiz)
                                    <br><em style="color: #666;">{{ $notiz }}</em>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        
        {{-- Seitenumbruch nach jedem 2. Raum --}}
        @if(($index + 1) % 2 === 0 && $index + 1 < $assignments->count())
            <div class="page-break"></div>
        @endif
    @endforeach
    
    {{-- Footer --}}
    <div class="footer">
        Seite <span class="pagenum"></span> | Exportiert am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr
    </div>
</body>
</html>
