<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platzkarten - {{ $writtenExamDate->name }} - {{ $assignment->room->room_number }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Kein display:flex und keine Prozenthoehen in Tabellenzeilen:
           dompdf kennt Flexbox nicht und rechnet height:50% in einer
           table-row nicht aus. Die zweite Kartenreihe jeder Seite fiel
           deshalb heraus — sechs Karten waren angelegt, drei kamen aus dem
           Drucker. Feste Hoehen und page-break-inside: avoid tun es. */
        .cards-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .card-row {
            display: table-row;
            page-break-inside: avoid;
        }

        .card {
            display: table-cell;
            width: 33.33%;
            height: 80mm;
            padding: 4mm;
            border: 1px dashed #999;
            text-align: center;
            vertical-align: middle;
        }

        .card-inner {
            border: 2px solid #1e40af;
            border-radius: 5px;
            padding: 10px;
            background-color: #f8fafc;
        }

        .page-break { page-break-after: always; }
        
        .seat-number {
            font-size: 36pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .student-lastname {
            font-size: 26pt;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            word-wrap: break-word;
            line-height: 1.1;
        }
        
        .student-firstname {
            font-size: 16pt;
            font-weight: bold;
            color: #333;
            word-wrap: break-word;
            line-height: 1.2;
            margin-bottom: 8px;
        }
        
        .course-name {
            font-size: 13pt;
            color: #1e40af;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-weight: bold;
        }
        
        .exam-info {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
        
        .end-time {
            font-size: 10pt;
            color: #991b1b;
            font-weight: bold;
            margin-top: 4px;
        }
        
        .compensation-badge {
            display: inline-block;
            background-color: #fef2f2;
            color: #991b1b;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @php
        // 6 Karten pro Seite (3×2). Jeder Block wird neu durchnummeriert:
        // chunk() erhaelt die Schluessel der Quelle, und die Seite greift die
        // Karten weiter unten ueber get(0)..get(5) ab. Ohne das values() im
        // map() traegt der zweite Block die Schluessel 6..11, get(0) liefert
        // dort null — ab Seite zwei blieb jede Karte leer. Aufgefallen ist es
        // erst, als sortBy() auf die echten Platznummern umgestellt wurde:
        // vorher sortierte der Aufruf nach lauter null und liess die
        // Reihenfolge unveraendert, sodass wenigstens die erste Seite stimmte.
        $chunks = $seatAssignments->values()->chunk(6)->map(fn ($block) => $block->values());
        $courseLevel = $assignment->course->level ?? 'grundlegend';
        $teacherShortcode = $assignment->course->teacher?->shortcode ?? '';
        $subjectName = $writtenExamDate->subject->name ?? $assignment->course->course_code;
        $courseLabel = $teacherShortcode ? "{$subjectName} ({$teacherShortcode})" : $subjectName;
    @endphp
    
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)
            <div class="page-break"></div>
        @endif
        <div class="cards-grid">
                @for($row = 0; $row < 2; $row++)
                    <div class="card-row">
                        @for($col = 0; $col < 3; $col++)
                            @php
                                $cardIndex = $row * 3 + $col;
                                $card = $pageCards->get($cardIndex);
                            @endphp
                            
                            <div class="card">
                                @if($card)
                                    @php
                                        // Ueber anzeigeObjekt() statt ueber $card->student: auf dem
                                        // Platz kann auch ein externer Bewerber oder ein Gast sitzen,
                                        // und dann waere $card->student null gewesen — der Export
                                        // haette den ganzen Raum verweigert.
                                        $student = $card->anzeigeObjekt();
                                        $prozent = $card->nachteilsausgleichProzent();
                                        $endTime = $writtenExamDate->getEndTimeForLevel($courseLevel, $prozent ?: null);
                                    @endphp
                                    <div class="card-inner">
                                        <div class="seat-number">
                                            Platz {{ $seatNumbers[$card->seat_id] ?? '?' }}
                                        </div>
                                        
                                        <div class="student-lastname">
                                            {{ $student->lastname }}
                                        </div>
                                        <div class="student-firstname">
                                            {{ $student->firstname }}
                                        </div>
                                        @if($card->istExterner())
                                            <div class="course-name">(externer Bewerber)</div>
                                        @elseif($card->istGast())
                                            <div class="course-name">(Gastschüler)</div>
                                        @endif
                                        
                                        <div class="course-name">
                                            {{ $courseLabel }}
                                        </div>
                                        
                                        <div class="exam-info">
                                            {{ $writtenExamDate->date->locale('de')->translatedFormat('d.m.Y') }}
                                            &nbsp;{{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr
                                        </div>
                                        <div class="end-time">
                                            Ende: {{ $endTime->format('H:i') }} Uhr
                                            @if($prozent > 0)
                                                <span class="compensation-badge">+{{ $prozent }}%</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                @endfor
        </div>
    @endforeach
</body>
</html>
