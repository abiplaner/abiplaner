<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Fächerübersicht - {{ $examPeriod->name }}</title>
    <style>
        @page {
            margin: 2cm 1.5cm;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.3;
        }
        
        h1 {
            font-size: 16pt;
            margin: 0 0 10px 0;
            padding: 0;
        }
        
        .header-info {
            font-size: 9pt;
            color: #666;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        thead {
            background-color: #f0f0f0;
        }
        
        th {
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            padding: 8px 5px;
            border: 1px solid #000;
        }
        
        th.fach {
            text-align: left;
            width: 30%;
        }
        
        th.niveau-header {
            font-size: 10pt;
            padding: 5px;
        }
        
        th.sub-header {
            font-size: 8pt;
            padding: 4px;
            width: 11%;
        }
        
        td {
            padding: 6px 5px;
            border: 1px solid #666;
            text-align: center;
            font-size: 10pt;
        }
        
        td.fach {
            text-align: left;
            font-weight: 500;
        }
        
        td.total {
            color: #666;
            font-weight: normal;
        }
        
        td.na {
            color: #ccc;
        }

        tfoot td {
            background-color: #f0f0f0;
            font-weight: bold;
            border-top: 2px solid #000;
        }
    </style>
</head>
<body>
    <h1>Fächerübersicht Abiturprüfungen {{ $examPeriod->year }}</h1>
    
    <div class="header-info">
        @if($school)
            <strong>{{ $school->name }}</strong><br>
        @endif
        Prüfungszeitraum: {{ $examPeriod->name }} · Jahrgangsstufe Q{{ $stufe ?? '13' }}<br>
        Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr
    </div>
    
    <table>
        <thead>
            <tr>
                <th rowspan="2" class="fach">Fach</th>
                <th colspan="3" class="niveau-header" style="border-right: 2px solid #000;">Schriftl.</th>
                <th colspan="3" class="niveau-header">Mündl.</th>
            </tr>
            <tr>
                <th class="sub-header">gA</th>
                <th class="sub-header">eA</th>
                <th class="sub-header" style="border-right: 2px solid #000; font-weight: bold;">Ges.</th>
                <th class="sub-header">gA</th>
                <th class="sub-header">eA</th>
                <th class="sub-header" style="font-weight: bold;">Ges.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($subjectStats as $stat)
                <tr>
                    <td class="fach">
                        {{ $stat['subject_name'] }} 
                        <span class="total">({{ $stat['total'] }})</span>
                    </td>
                    <td @if($stat['is_deutsch_or_math']) class="na" @endif>
                        @if($stat['is_deutsch_or_math'])
                            -
                        @else
                            {{ $stat['grundlegend_written'] > 0 ? $stat['grundlegend_written'] : '-' }}
                        @endif
                    </td>
                    <td>{{ $stat['erhoeht_written'] > 0 ? $stat['erhoeht_written'] : '-' }}</td>
                    <td style="border-right: 2px solid #000; font-weight: bold;">{{ ($stat['grundlegend_written'] + $stat['erhoeht_written']) > 0 ? ($stat['grundlegend_written'] + $stat['erhoeht_written']) : '-' }}</td>
                    <td @if($stat['is_deutsch_or_math']) class="na" @endif>
                        @if($stat['is_deutsch_or_math'])
                            -
                        @else
                            {{ $stat['grundlegend_oral'] > 0 ? $stat['grundlegend_oral'] : '-' }}
                        @endif
                    </td>
                    <td>{{ $stat['erhoeht_oral'] > 0 ? $stat['erhoeht_oral'] : '-' }}</td>
                    <td style="font-weight: bold;">{{ ($stat['grundlegend_oral'] + $stat['erhoeht_oral']) > 0 ? ($stat['grundlegend_oral'] + $stat['erhoeht_oral']) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="fach">Gesamt</td>
                <td>{{ $subjectStats->sum('grundlegend_written') }}</td>
                <td>{{ $subjectStats->sum('erhoeht_written') }}</td>
                <td style="border-right: 2px solid #000;">{{ $subjectStats->sum('grundlegend_written') + $subjectStats->sum('erhoeht_written') }}</td>
                <td>{{ $subjectStats->sum('grundlegend_oral') }}</td>
                <td>{{ $subjectStats->sum('erhoeht_oral') }}</td>
                <td>{{ $subjectStats->sum('grundlegend_oral') + $subjectStats->sum('erhoeht_oral') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
