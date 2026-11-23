<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Aufsichtsliste Lehrkräfte</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; color: #111; margin: 0; padding: 10px; }
        h1   { font-size: 12px; margin: 0 0 2px 0; }
        .meta { font-size: 8px; color: #555; margin-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #1e3a5f; color: #fff;
            font-size: 8px; font-weight: bold;
            padding: 4px 5px; border: 1px solid #1e3a5f;
            text-align: left;
        }
        tbody td {
            border: 1px solid #d1d5db;
            padding: 3px 5px;
            vertical-align: top;
        }

        /* Abwechselnde Hintergrundfarben pro Lehrkraft */
        .teacher-even td { background: #f0f4ff; }
        .teacher-odd  td { background: #ffffff; }

        /* Erste Zeile einer Gruppe: oberer Rand etwas dicker */
        .group-first td { border-top: 1.5px solid #9ca3af; }

        .col-name    { width: 14%; font-weight: bold; font-size: 8px; }
        .col-date    { width: 11%; white-space: nowrap; }
        .col-time    { width: 13%; white-space: nowrap; }
        .col-pos     { width: 18%; }
        .col-subject { width: 20%; }
        .col-sig     { width: 24%; }

        .weekday { font-size: 7px; color: #6b7280; }

        .room-badge     { display: inline-block; background: #dbeafe; border: 1px solid #93c5fd; color: #1e40af; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }
        .corridor-badge { display: inline-block; background: #ede9fe; border: 1px solid #c4b5fd; color: #5b21b6; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }
        .reserve-badge  { display: inline-block; background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }
        .extra-badge    { display: inline-block; background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }
        .course-info    { font-size: 7px; color: #4b5563; margin-top: 1px; }

        .sig-box { border-bottom: 1px solid #aaa; height: 14px; width: 85%; margin: 2px auto 0; }
        .no-data { font-style: italic; color: #9ca3af; }
    </style>
</head>
<body>

<h1>Aufsichtsliste – Schriftliche Abiturprüfungen</h1>
<div class="meta">
    Erstellt am {{ now()->locale('de')->isoFormat('DD.MM.YYYY, HH:mm') }}
    @if($schoolName) &nbsp;|&nbsp; {{ $schoolName }} @endif
</div>

<table>
    <thead>
        <tr>
            <th class="col-name">Lehrkraft</th>
            <th class="col-date">Datum</th>
            <th class="col-time">Zeitslot</th>
            <th class="col-pos">Position / Raum</th>
            <th class="col-subject">Fach / Kurs</th>
            <th class="col-sig">Unterschrift</th>
        </tr>
    </thead>
    <tbody>
    @forelse($teacherRows as $tIdx => $teacherData)
        @php
            $teacher      = $teacherData['teacher'];
            $supervisions = $teacherData['supervisions'];
            $rowClass     = $tIdx % 2 === 0 ? 'teacher-even' : 'teacher-odd';
            $nameDisplay  = $teacher->lastname . ', ' . $teacher->firstname
                          . ' (' . $teacher->shortcode . ')';
        @endphp

        @if($supervisions->isEmpty())
            {{-- Lehrkraft ohne Aufsichten: eine Zeile --}}
            <tr class="{{ $rowClass }} group-first">
                <td class="col-name">{{ $nameDisplay }}</td>
                <td colspan="4" class="no-data">Keine Aufsichten</td>
                <td class="col-sig"></td>
            </tr>
        @else
            @foreach($supervisions as $sIdx => $row)
            @php
                $carbon  = \Carbon\Carbon::parse($row['date'])->locale('de');
                $isFirst = $sIdx === 0;
            @endphp
            <tr class="{{ $rowClass }}{{ $isFirst ? ' group-first' : '' }}">
                {{-- Name nur in der ersten Zeile der Gruppe --}}
                <td class="col-name">{{ $isFirst ? $nameDisplay : '' }}</td>

                <td class="col-date">
                    <div>{{ $carbon->isoFormat('DD.MM.YYYY') }}</div>
                    <div class="weekday">{{ $carbon->isoFormat('dddd') }}</div>
                </td>
                <td class="col-time">{{ $row['time_range'] }}</td>
                <td class="col-pos">
                    @if($row['position_type'] === 'room')
                        <span class="room-badge">{{ $row['position_label'] }}</span>
                    @elseif($row['position_type'] === 'corridor')
                        <span class="corridor-badge">{{ $row['position_label'] }}</span>
                    @elseif($row['position_type'] === 'reserve')
                        <span class="reserve-badge">{{ $row['position_label'] }}</span>
                    @else
                        <span class="extra-badge">{{ $row['position_label'] }}</span>
                    @endif
                    @if($row['course_info'])
                        <div class="course-info">{{ $row['course_info'] }}</div>
                    @endif
                </td>
                <td class="col-subject">{{ $row['subject_info'] }}</td>
                <td class="col-sig"><div class="sig-box"></div></td>
            </tr>
            @endforeach
        @endif
    @empty
        <tr>
            <td colspan="6" class="no-data" style="padding:10px; text-align:center;">
                Keine Aufsichten eingetragen.
            </td>
        </tr>
    @endforelse
    </tbody>
</table>

</body>
</html>
