<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Raumbelegungsplan - {{ $examPeriod->name }}</title>
    <style>
        @page {
            margin: 10mm;
            size: A4 landscape;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7pt;
            line-height: 1.2;
            color: #333;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 5px;
        }
        
        h1 {
            font-size: 14pt;
            margin: 0 0 3px 0;
            color: #1a1a1a;
        }
        
        .room-title {
            font-size: 16pt;
            font-weight: bold;
            color: #2563eb;
            margin: 0;
        }
        
        .week-info {
            font-size: 9pt;
            color: #666;
            margin-top: 2px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        
        th {
            background-color: #2563eb;
            color: white;
            padding: 4px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #1e40af;
            font-size: 8pt;
        }
        
        th.time-col {
            width: 50px;
            background-color: #4b5563;
        }
        
        td {
            border: 1px solid #d1d5db;
            padding: 3px;
            vertical-align: top;
            font-size: 7pt;
        }
        
        td.time-cell {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
            width: 50px;
            font-size: 7pt;
        }
        
        .exam-entry {
            margin-bottom: 3px;
            padding: 2px 3px;
            background-color: #f0fdf4;
            border-left: 2px solid #10b981;
        }
        
        .exam-entry:last-child {
            margin-bottom: 0;
        }
        
        .student-name {
            font-weight: bold;
            color: #1a1a1a;
            font-size: 7pt;
        }
        
        .subject {
            color: #2563eb;
            font-size: 6pt;
        }
        
        .teachers {
            color: #4b5563;
            font-size: 6pt;
            margin-top: 1px;
        }
        
        .empty-cell {
            text-align: center;
            color: #d1d5db;
            font-size: 8pt;
        }
        
        .footer {
            position: fixed;
            bottom: 8mm;
            left: 10mm;
            right: 10mm;
            text-align: center;
            font-size: 7pt;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    @foreach($pdfData as $index => $roomData)
        @foreach([1, 2] as $weekNum)
            @if($index > 0 || $weekNum > 1)
                <div class="page-break"></div>
            @endif
            
            @php 
                $weekData = $roomData['weeks'][$weekNum];
                
                // Gruppiere Slots nach Wochentag und Slot-Nummer
                $weekGrid = [];
                $allSlotNumbers = [];
                
                foreach($weekData['dates'] as $dateData) {
                    $dayOfWeek = $dateData['date']->dayOfWeekIso; // 1=Montag, 5=Freitag
                    
                    foreach($dateData['slots'] as $slotData) {
                        $slotNum = $slotData['slot_number'];
                        $allSlotNumbers[$slotNum] = $slotData['time'];
                        
                        if(!isset($weekGrid[$slotNum])) {
                            $weekGrid[$slotNum] = [];
                        }
                        
                        $weekGrid[$slotNum][$dayOfWeek] = [
                            'date' => $dateData['date'],
                            'assignments' => $slotData['assignments']
                        ];
                    }
                }
                
                ksort($allSlotNumbers);
            @endphp
            
            <div class="header">
                <h1>Raumbelegungsplan</h1>
                <div class="room-title">Raum {{ $roomData['room']->room_number }}</div>
                <div class="week-info">
                    Woche {{ $weekNum }}: {{ $weekData['start']->locale('de')->translatedFormat('D, d.m.Y') }} - {{ $weekData['end']->locale('de')->translatedFormat('D, d.m.Y') }}
                </div>
                <div class="week-info">{{ $school ? $school->name . ' - ' : '' }}{{ $examPeriod->name }}</div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th class="time-col">Zeit</th>
                        <th>Montag</th>
                        <th>Dienstag</th>
                        <th>Mittwoch</th>
                        <th>Donnerstag</th>
                        <th>Freitag</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allSlotNumbers as $slotNum => $slotTime)
                        <tr>
                            <td class="time-cell">{{ substr($slotTime, 0, 5) }}</td>
                            
                            @for($day = 1; $day <= 5; $day++)
                                <td>
                                    @if(isset($weekGrid[$slotNum][$day]))
                                        @php $cellData = $weekGrid[$slotNum][$day]; @endphp
                                        
                                        @if($cellData['assignments']->count() > 0)
                                            @foreach($cellData['assignments'] as $assignment)
                                                <div class="exam-entry">
                                                    <div class="student-name">
                                                        {{ $assignment->studentExam->prueflingsNachname() }}
                                                    </div>
                                                    <div class="subject">
                                                        {{ $assignment->studentExam->getSubjectCode() ?? '–' }}
                                                    </div>
                                                    <div class="teachers">
                                                        P: {{ $assignment->studentExam->examiner->shortcode ?? '?' }}
                                                        | B: {{ $assignment->studentExam->assessor->shortcode ?? '—' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="empty-cell">—</div>
                                        @endif
                                    @else
                                        <div class="empty-cell">—</div>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="footer">
                Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr | Raum {{ $roomData['room']->room_number }} - Woche {{ $weekNum }}
            </div>
        @endforeach
    @endforeach
</body>
</html>
