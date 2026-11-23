<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Platzkarten{{ isset($roomPages) ? '' : ' - Raum ' . $room->room_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 15px; }
        .card-grid { display: table; width: 100%; }
        .card-row  { display: table-row; }
        .card-cell { display: table-cell; width: 50%; padding: 8px; vertical-align: top; }
        .seat-card { border: 2px solid #333; border-radius: 6px; padding: 12px 15px;
                     height: 210px; page-break-inside: avoid; position: relative; box-sizing: border-box; }
        .card-header { text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 10px; }
        .card-header h2 { font-size: 13pt; margin: 0 0 4px 0; color: #000; }
        .card-header .exam-info { font-size: 8pt; color: #666; }
        .card-body { text-align: center; margin-top: 10px; }
        .seat-number { font-size: 28pt; font-weight: bold; color: #2563eb; margin: 4px 0 8px 0; }
        .student-lastname  { font-size: 22pt; font-weight: bold; color: #000; line-height: 1.1; }
        .student-firstname { font-size: 13pt; color: #444; margin-top: 2px; }
        .course-badge { display: inline-block; padding: 2px 7px; border-radius: 4px;
                        font-size: 8.5pt; font-weight: bold; vertical-align: middle; margin-left: 5px; }
        .course-blue   { background:#dbeafe; color:#1e40af; border:1px solid #60a5fa; }
        .course-green  { background:#d1fae5; color:#065f46; border:1px solid #34d399; }
        .course-purple { background:#e9d5ff; color:#6b21a8; border:1px solid #a78bfa; }
        .course-orange { background:#fed7aa; color:#9a3412; border:1px solid #fb923c; }
        .course-pink   { background:#fce7f3; color:#9f1239; border:1px solid #f9a8d4; }
        .course-indigo { background:#e0e7ff; color:#3730a3; border:1px solid #818cf8; }
        .comp-badge { display:inline-block; padding:1px 5px; background:#fef3c7;
                      color:#92400e; border:1px solid #fcd34d; border-radius:3px;
                      font-size:7.5pt; font-weight:bold; margin-left:4px; }
        .hv-badge { display:inline-block; padding:1px 5px; background:#ccfbf1;
                    color:#0f766e; border:1px solid #5eead4; border-radius:3px;
                    font-size:7.5pt; font-weight:bold; margin-left:4px; }
        .room-info { position:absolute; bottom:8px; left:12px; right:12px; text-align:center;
                     font-size:7pt; color:#aaa; border-top:1px solid #eee; padding-top:5px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@php
    // Normalisierung: Einzelraum oder mehrere Räume (tagesweiser Export)
    $_pages = isset($roomPages)
        ? $roomPages
        : collect([['room' => $room, 'writtenExamDate' => $writtenExamDate, 'assignments' => $assignments, 'seatAssignments' => $seatAssignments]]);
    $colors = ['blue','green','purple','orange','pink','indigo'];
@endphp

@foreach($_pages as $_pageIdx => $_page)
    @php
        $room            = $_page['room'];
        $writtenExamDate = $_page['writtenExamDate'];
        $seatAssignments = $_page['seatAssignments'];
        $assignments     = $_page['assignments'];
        $cardCount       = 0;
    @endphp

    @if($_pageIdx > 0)
        <div class="page-break"></div>
    @endif

    <div class="card-grid">
        @foreach($seatAssignments as $sa)
            @php
                $cardCount++;
                $needsPageBreak = ($cardCount % 8 == 0);
                $courseIndex = $assignments->search(fn($a) => $a->course_id === $sa['course']->id);
                $color = $colors[$courseIndex !== false ? $courseIndex % count($colors) : 0];
                $teacher     = $sa['course']->teacher;
                $courseLabel = $sa['course']->course_code
                    . ($teacher ? ' (' . $teacher->shortcode . ')' : '');
            @endphp

            @if(($cardCount - 1) % 2 == 0)
                <div class="card-row">
            @endif

            <div class="card-cell">
                <div class="seat-card">
                    <div class="card-header">
                        <h2>
                            {{ $sa['course']->subject?->name ?? $writtenExamDate->subject?->name ?? '–' }}
                            <span class="course-badge course-{{ $color }}">{{ $courseLabel }}</span>
                            @if($sa['is_hv'] ?? false)
                                <span class="hv-badge">HV</span>
                            @endif
                        </h2>
                        <div class="exam-info">
                            {{ $writtenExamDate->date->locale('de')->translatedFormat('d.m.Y') }} |
                            Beginn: {{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr |
                            Ende: {{ $sa['end_time']->format('H:i') }} Uhr
                            @if($sa['compensation'])
                                <span class="comp-badge">+{{ $sa['compensation'] }}% NTA</span>
                            @endif
                            | Raum: {{ $room->room_number }}
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="seat-number">Platz {{ $sa['seat_number'] }}</div>
                        <div class="student-lastname">{{ $sa['student']->lastname ?: '–' }}</div>
                        <div class="student-firstname">{{ $sa['student']->firstname }}</div>
                        @if($sa['is_guest'] ?? false)
                            <div class="student-firstname" style="font-size:8pt;color:#888;">(Gastschüler)</div>
                        @elseif($sa['is_extern'] ?? false)
                            <div class="student-firstname" style="font-size:8pt;color:#888;">(externer Bewerber)</div>
                        @endif
                    </div>
                </div>
            </div>

            @if($cardCount % 2 == 0)
                </div>
                @if($needsPageBreak && !$loop->last)
                    <div class="page-break"></div>
                @endif
            @endif

            @if($loop->last && $cardCount % 2 != 0)
                <div class="card-cell"></div>
                </div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
