<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Aufsichtsliste Lehrkräfte – Kolloquium</title>
<style>
body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8.5pt; color:#111; margin:0; padding:10px; }
h1   { font-size:12pt; margin:0 0 2px 0; color:#1e3a5f; }
.meta { font-size:7.5pt; color:#555; margin-bottom:8px; }

table { width:100%; border-collapse:collapse; }
thead th {
    background:#1e3a5f; color:#fff;
    font-size:8pt; font-weight:bold;
    padding:4px 5px; border:1px solid #1e3a5f; text-align:left;
}
tbody td { border:1px solid #d1d5db; padding:3px 5px; vertical-align:top; }

.teacher-even td { background:#f0f4ff; }
.teacher-odd  td { background:#fff; }
.group-first td  { border-top:1.5px solid #9ca3af; }

.col-name { width:15%; font-weight:bold; font-size:8pt; }
.col-date { width:11%; white-space:nowrap; }
.col-time { width:14%; white-space:nowrap; }
.col-pos  { width:32%; }
.col-sig  { width:28%; }

.weekday { font-size:7pt; color:#6b7280; }

.badge-prep     { display:inline-block; background:#fef3c7; border:1px solid #fde68a; color:#92400e; padding:1px 4px; border-radius:2px; font-weight:bold; font-size:7.5pt; }
.badge-wait     { display:inline-block; background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; padding:1px 4px; border-radius:2px; font-weight:bold; font-size:7.5pt; }
.badge-corridor { display:inline-block; background:#ede9fe; border:1px solid #c4b5fd; color:#5b21b6; padding:1px 4px; border-radius:2px; font-weight:bold; font-size:7.5pt; }

.sig-box  { border-bottom:1px solid #aaa; height:14px; width:85%; margin:2px auto 0; }
.no-data  { font-style:italic; color:#9ca3af; }
</style>
</head>
<body>

<h1>Aufsichtsliste – Kolloquium {{ $examPeriod->name }}</h1>
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
            <th class="col-pos">Position</th>
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
            <tr class="{{ $rowClass }} group-first">
                <td class="col-name">{{ $nameDisplay }}</td>
                <td colspan="3" class="no-data">Keine Aufsichten</td>
                <td class="col-sig"></td>
            </tr>
        @else
            @foreach($supervisions as $sIdx => $row)
            @php
                $carbon  = \Carbon\Carbon::parse($row['date'])->locale('de');
                $isFirst = $sIdx === 0;
                $badgeClass = match($row['type']) {
                    'preparation' => 'badge-prep',
                    'waiting'     => 'badge-wait',
                    default       => 'badge-corridor',
                };
            @endphp
            <tr class="{{ $rowClass }}{{ $isFirst ? ' group-first' : '' }}">
                <td class="col-name">{{ $isFirst ? $nameDisplay : '' }}</td>
                <td class="col-date">
                    <div>{{ $carbon->isoFormat('DD.MM.YYYY') }}</div>
                    <div class="weekday">{{ $carbon->isoFormat('dddd') }}</div>
                </td>
                <td class="col-time">{{ $row['time_range'] }}</td>
                <td class="col-pos">
                    <span class="{{ $badgeClass }}">{{ $row['position_label'] }}</span>
                </td>
                <td class="col-sig"><div class="sig-box"></div></td>
            </tr>
            @endforeach
        @endif
    @empty
        <tr>
            <td colspan="5" class="no-data" style="padding:10px;text-align:center;">
                Keine Aufsichten eingetragen.
            </td>
        </tr>
    @endforelse
    </tbody>
</table>

</body>
</html>
