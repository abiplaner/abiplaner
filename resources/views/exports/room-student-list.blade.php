<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Schülerliste{{ isset($roomPages) ? '' : ' - Raum ' . $room->room_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; line-height: 1.3; margin: 12px; }
        .header { text-align: center; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 2px solid #555; }
        .header h1 { font-size: 13pt; margin: 0 0 2px 0; }
        .header .sub { font-size: 8.5pt; color: #666; }
        .info-box { background: #f9f9f9; border: 1px solid #ddd; padding: 4px 8px; margin-bottom: 8px; border-radius: 3px; }
        .info-box p { margin: 1px 0; }
        .course-section { margin-bottom: 12px; }
        .course-header { background: #e5e7eb; padding: 4px 8px; margin-bottom: 4px;
                         font-weight: bold; font-size: 9pt; border-left: 4px solid #374151; border-radius: 2px; }
        .hv-badge { display: inline-block; padding: 1px 4px; margin-left: 4px; border-radius: 3px;
                    font-size: 7.5pt; background: #ccfbf1; color: #0f766e; border: 1px solid #5eead4; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; padding: 3px 6px; text-align: left; border: 1px solid #ccc; font-size: 8pt; }
        td { padding: 2px 6px; border: 1px solid #ddd; font-size: 8pt; vertical-align: middle; }
        tr.comp-row { background: #fff7ed !important; }
        tr:nth-child(even):not(.comp-row) { background: #f9fafb; }
        .seat-nr  { font-weight: bold; color: #2563eb; }
        .end-time-comp { color: #dc2626; font-weight: bold; }
        .comp-badge { display:inline-block; padding:1px 4px; background:#fef3c7;
                      color:#92400e; border:1px solid #fcd34d; border-radius:3px;
                      font-size:7pt; font-weight:bold; margin-left:3px; vertical-align:middle; }
        .footer { text-align: center; font-size: 7.5pt; color: #aaa; margin-top: 12px;
                  border-top: 1px solid #ddd; padding-top: 5px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
@php
    // Normalisierung: Einzelraum oder mehrere Räume (tagesweiser Export)
    $_pages = isset($roomPages)
        ? $roomPages
        : collect([['room' => $room, 'writtenExamDate' => $writtenExamDate, 'assignments' => $assignments, 'seatNumbers' => $seatNumbers]]);
@endphp

@foreach($_pages as $_pageIdx => $_page)
    @php
        $room            = $_page['room'];
        $writtenExamDate = $_page['writtenExamDate'];
        $assignments     = $_page['assignments'];
        $seatNumbers     = $_page['seatNumbers'];

        $roomSubjectNames = $assignments
            ->map(fn($a) => $a->course->subject?->name)
            ->filter()->unique()->values();
        $subjectLine = $roomSubjectNames->isNotEmpty()
            ? $roomSubjectNames->join(' – ')
            : $writtenExamDate->slot->name;
        // A-Teil-Spalte nur für Mathematik (eA, max. 110 Min.)
        $isMathematik = $assignments->contains(fn($a) => $a->course->subject?->code === 'M');
    @endphp

    @if($_pageIdx > 0)
        <div class="page-break"></div>
    @endif

    <div class="header">
        <h1>Schülerliste – Raum {{ $room->room_number }}</h1>
        <div class="sub">
            {{ $subjectLine }} &nbsp;|&nbsp;
            {{ $writtenExamDate->date->locale('de')->translatedFormat('l, d. F Y') }} |
            {{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr
        </div>
    </div>

    <div class="info-box">
        <p>
            <strong>Raum:</strong> {{ $room->room_number }}
        </p>
        <p>
            <strong>Kurse:</strong>
            @foreach($assignments->unique('course_id') as $assignment)
                {{ $assignment->course->course_code }}
                @if($assignment->course->teacher)({{ $assignment->course->teacher->shortcode }})@endif
                @if(!$loop->last), @endif
            @endforeach
        </p>
        @if($isMathematik)
        <p style="font-size:7.5pt;color:#555;margin-top:3px;">
            <em>* Spalte „A-Teil bis" = späteste Abgabe Teil A (max. 110 Min., bei NTA anteilig verlängert)</em>
        </p>
        @endif
    </div>

    @foreach($assignments as $assignment)
        @php
            $level   = $assignment->course->level ?? 'grundlegend';
            $teacher = $assignment->course->teacher;
            $courseLabel = $assignment->course->course_code
                . ($teacher ? ' · ' . $teacher->shortcode . ' (' . $teacher->lastname . ')' : '');
            $assignedStudentIds = $assignment->seatAssignments->pluck('student_id');
            $collator = new \Collator('de_DE@collation=phonebook');
            $students = $assignment->course->writtenStudents
                ->whereIn('id', $assignedStudentIds)
                ->sort(function ($a, $b) use ($collator) {
                    $l = $collator->compare($a->lastname ?? '', $b->lastname ?? '');
                    return $l !== 0 ? $l : $collator->compare($a->firstname ?? '', $b->firstname ?? '');
                })->values(); // Keys zurücksetzen → Nr.-Spalte beginnt korrekt bei 1
        @endphp
        <div class="course-section">
            <div class="course-header">
                {{ $courseLabel }}
                @if($assignment->is_hoerverstaendnis)<span class="hv-badge">HV</span>@endif
                &nbsp;–&nbsp; {{ $assignment->course->writtenStudents->count() }} Schüler,
                {{ $students->count() }} zugewiesen
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:5%;">Nr.</th>
                        <th style="width:22%;">Nachname</th>
                        <th style="width:20%;">Vorname</th>
                        <th style="width:8%;">Platz</th>
                        @if($isMathematik)<th style="width:14%;">A-Teil bis *</th>@endif
                        <th style="width:12%;">Endzeit</th>
                        <th style="width:8%;">Kommentar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $index => $student)
                        @php
                            $seatAssignment = $assignment->seatAssignments->where('student_id', $student->id)->first();
                            $seatNum = $seatAssignment ? ($seatNumbers[$seatAssignment->seat_id] ?? '?') : null;
                            $comp    = $student->disability_compensation_percent ?? 0;
                            $hasComp = $comp > 0;
                            $assignmentDate = $assignment->examDate ?? $writtenExamDate;
                            $endTime = $assignment->is_hoerverstaendnis
                                ? $assignmentDate->getEndTimeHv($hasComp ? $comp : null)
                                : $assignmentDate->getEndTimeForLevel($level, $hasComp ? $comp : null);
                            $aTeilTime = $isMathematik
                                ? $assignmentDate->getStartTime()->addMinutes($hasComp ? (int) ceil(110 * (1 + $comp / 100)) : 110)
                                : null;
                        @endphp
                        <tr class="{{ $hasComp ? 'comp-row' : '' }}">
                            <td style="text-align:center;">{{ $index + 1 }}</td>
                            <td>
                                {{ $student->lastname }}
                                @if($hasComp)
                                    <span class="comp-badge">+{{ $comp }}% NTA</span>
                                @endif
                            </td>
                            <td>{{ $student->firstname }}</td>
                            <td style="text-align:center;">
                                @if($seatNum)
                                    <span class="seat-nr">{{ $seatNum }}</span>
                                @else
                                    <span style="color:#bbb;">–</span>
                                @endif
                            </td>
                            @if($isMathematik)
                            <td style="text-align:center;" class="{{ $hasComp ? 'end-time-comp' : '' }}">
                                {{ $aTeilTime->format('H:i') }} Uhr
                            </td>
                            @endif
                            <td style="text-align:center;" class="{{ $hasComp ? 'end-time-comp' : '' }}">
                                {{ $endTime->format('H:i') }} Uhr
                            </td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

@endforeach

<div class="footer">Exportiert am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr</div>
</body>
</html>
