<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitzplan - {{ $writtenExamDate->name }} - {{ $assignment->room->room_number }}</title>
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
        
        .seating-grid {
            margin: 20px 0;
            page-break-inside: avoid;
        }
        
        .seating-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        
        .seating-cell {
            display: table-cell;
            width: {{ 100 / $layout->columns }}%;
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
            font-size: 9pt;
            vertical-align: middle;
            min-height: 50px;
        }
        
        .seating-cell.available {
            background-color: #f0fdf4;
            border-color: #86efac;
        }
        
        .seating-cell.occupied {
            background-color: #dbeafe;
            border-color: #60a5fa;
            font-weight: bold;
        }
        
        .seating-cell.blocked {
            background-color: #fee2e2;
            border-color: #fca5a5;
        }
        
        .seating-cell.aisle {
            background-color: #f9fafb;
            border-color: #e5e7eb;
            border-style: dashed;
        }
        
        .seat-number {
            font-size: 8pt;
            color: #666;
            margin-bottom: 3px;
        }
        
        .student-name {
            font-size: 9pt;
            font-weight: bold;
            color: #1e40af;
            word-wrap: break-word;
        }
        
        .legend {
            margin-top: 30px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .legend h3 {
            font-size: 11pt;
            margin: 0 0 10px 0;
            color: #000;
        }
        
        .legend-items {
            display: table;
            width: 100%;
        }
        
        .legend-item {
            display: table-cell;
            width: 25%;
            padding: 5px;
        }
        
        .legend-color {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 1px solid #999;
            vertical-align: middle;
            margin-right: 8px;
        }
        
        .legend-color.available {
            background-color: #f0fdf4;
            border-color: #86efac;
        }
        
        .legend-color.occupied {
            background-color: #dbeafe;
            border-color: #60a5fa;
        }
        
        .legend-color.blocked {
            background-color: #fee2e2;
            border-color: #fca5a5;
        }
        
        .legend-color.aisle {
            background-color: #f9fafb;
            border-color: #e5e7eb;
            border-style: dashed;
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
        
        .page-orientation-hint {
            text-align: center;
            font-size: 9pt;
            color: #666;
            margin: 10px 0;
            font-style: italic;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>📋 Sitzplan</h1>
        <div class="subtitle">{{ $writtenExamDate->name }}</div>
        <div class="subtitle">{{ $writtenExamDate->date->locale('de')->translatedFormat('l, d. F Y') }} | {{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr</div>
    </div>
    
    {{-- Info-Box --}}
    <div class="info-box">
        <p><strong>Raum:</strong> {{ $assignment->room->room_number }}</p>
        <p>
            <strong>Kurs:</strong> {{ $assignment->course->course_code }}
            @if($assignment->course->teacher)
                &ndash; {{ $assignment->course->teacher->shortcode }} ({{ $assignment->course->teacher->lastname }})
            @endif
            &ndash; {{ $assignment->course->getLevelDisplayName() }}
        </p>
        <p><strong>Kapazität:</strong> {{ $layout->getTotalCapacity() }} Plätze ({{ $assignment->getAssignedStudentsCount() }} belegt, {{ $assignment->getRemainingCapacity() }} frei)</p>
        @if($assignment->notes)
            <p><strong>Hinweise:</strong> {{ $assignment->notes }}</p>
        @endif
    </div>
    
    <div class="page-orientation-hint">
        💡 Raum von der Tafel aus betrachtet (Türseite unten)
    </div>
    
    {{-- Ansicht von vorne (Tafel oben) --}}
    <h2 style="font-size: 13pt; margin: 15px 0 5px 0; color: #333;">📍 Ansicht von vorne (Blick zur Tafel)</h2>
    <div class="seating-grid">
        @foreach($grid as $rowNumber => $columns)
            <div class="seating-row">
                @foreach($columns as $colNumber => $cell)
                    @php
                        $seat = $cell['seat'];
                        $seatAssignment = $cell['assignment'];
                        
                        if (!$seat) {
                            $cellClass = 'blocked';
                            $content = '-';
                        } elseif ($seat->seat_type === 'aisle') {
                            $cellClass = 'aisle';
                            $content = 'Gang';
                        } elseif ($seat->seat_type === 'blocked') {
                            $cellClass = 'blocked';
                            $content = 'Blockiert';
                        } elseif ($seatAssignment) {
                            // anzeigename() statt $seatAssignment->student: auf dem Platz
                            // kann auch ein externer Bewerber oder ein Gast sitzen.
                            $cellClass = 'occupied';
                            $zusatz = $seatAssignment->istExterner()
                                ? '<div class="student-name" style="color:#666;">(extern)</div>'
                                : ($seatAssignment->istGast()
                                    ? '<div class="student-name" style="color:#666;">(Gast)</div>'
                                    : '');
                            $content = '<div class="seat-number">Platz ' . ($seatNumbers[$seat->id] ?? '?') . '</div>' .
                                      '<div class="student-name">' . e($seatAssignment->anzeigename()) . '</div>' .
                                      $zusatz;
                        } else {
                            $cellClass = 'available';
                            $content = '<div class="seat-number">Platz ' . ($seatNumbers[$seat->id] ?? '?') . '</div>' .
                                      '<div style="color: #999;">frei</div>';
                        }
                    @endphp
                    
                    <div class="seating-cell {{ $cellClass }}">
                        {!! $content !!}
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    
    {{-- Seitenumbruch --}}
    <div style="page-break-before: always;"></div>
    
    {{-- Info-Box (Wiederholung) --}}
    <div class="info-box">
        <p><strong>Raum:</strong> {{ $assignment->room->room_number }}</p>
        <p>
            <strong>Kurs:</strong> {{ $assignment->course->course_code }}
            @if($assignment->course->teacher)
                &ndash; {{ $assignment->course->teacher->shortcode }} ({{ $assignment->course->teacher->lastname }})
            @endif
        </p>
        <p><strong>Datum:</strong> {{ $writtenExamDate->date->locale('de')->translatedFormat('l, d. F Y') }} | {{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr</p>
    </div>
    
    <div class="page-orientation-hint">
        💡 Raum von hinten betrachtet (Blick zur Tür / Rücken zur Tafel)
    </div>
    
    {{-- Ansicht von hinten (umgekehrt) --}}
    <h2 style="font-size: 13pt; margin: 15px 0 5px 0; color: #333;">🔄 Ansicht von hinten (Blick zur Tür)</h2>
    <div class="seating-grid">
        @foreach(array_reverse(array_values($grid)) as $rowNumber => $columns)
            <div class="seating-row">
                @foreach(array_reverse(array_values($columns)) as $colNumber => $cell)
                    @php
                        $seat = $cell['seat'];
                        $seatAssignment = $cell['assignment'];
                        
                        if (!$seat) {
                            $cellClass = 'blocked';
                            $content = '-';
                        } elseif ($seat->seat_type === 'aisle') {
                            $cellClass = 'aisle';
                            $content = 'Gang';
                        } elseif ($seat->seat_type === 'blocked') {
                            $cellClass = 'blocked';
                            $content = 'Blockiert';
                        } elseif ($seatAssignment) {
                            // anzeigename() statt $seatAssignment->student: auf dem Platz
                            // kann auch ein externer Bewerber oder ein Gast sitzen.
                            $cellClass = 'occupied';
                            $zusatz = $seatAssignment->istExterner()
                                ? '<div class="student-name" style="color:#666;">(extern)</div>'
                                : ($seatAssignment->istGast()
                                    ? '<div class="student-name" style="color:#666;">(Gast)</div>'
                                    : '');
                            $content = '<div class="seat-number">Platz ' . ($seatNumbers[$seat->id] ?? '?') . '</div>' .
                                      '<div class="student-name">' . e($seatAssignment->anzeigename()) . '</div>' .
                                      $zusatz;
                        } else {
                            $cellClass = 'available';
                            $content = '<div class="seat-number">Platz ' . ($seatNumbers[$seat->id] ?? '?') . '</div>' .
                                      '<div style="color: #999;">frei</div>';
                        }
                    @endphp
                    
                    <div class="seating-cell {{ $cellClass }}">
                        {!! $content !!}
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    
    {{-- Legende --}}
    <div class="legend">
        <h3>Legende</h3>
        <div class="legend-items">
            <div class="legend-item">
                <span class="legend-color occupied"></span>
                <span>Belegt</span>
            </div>
            <div class="legend-item">
                <span class="legend-color available"></span>
                <span>Frei</span>
            </div>
            <div class="legend-item">
                <span class="legend-color blocked"></span>
                <span>Blockiert</span>
            </div>
            <div class="legend-item">
                <span class="legend-color aisle"></span>
                <span>Gang</span>
            </div>
        </div>
    </div>
    
    {{-- Footer --}}
    <div class="footer">
        Exportiert am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr | {{ $assignment->school?->name ?? '' }}
    </div>
</body>
</html>
