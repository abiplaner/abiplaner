<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schülerliste - {{ $writtenExamDate->name }} - {{ $assignment->course->course_code }}</title>
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
        
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .student-table thead {
            background-color: #1e40af;
            color: white;
        }
        
        .student-table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1e40af;
        }
        
        .student-table th.center {
            text-align: center;
        }
        
        .student-table tbody tr {
            border-bottom: 1px solid #cbd5e1;
        }
        
        .student-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .student-table tbody tr:hover {
            background-color: #e0e7ff;
        }
        
        .student-table td {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
        }
        
        .student-table td.center {
            text-align: center;
        }
        
        .student-table td.number {
            width: 40px;
            text-align: center;
            font-weight: bold;
            color: #666;
        }
        
        .student-name {
            font-weight: bold;
        }
        
        .seat-number {
            font-family: 'DejaVu Sans Mono', monospace;
            background-color: #dbeafe;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            color: #1e40af;
        }
        
        .compensation-indicator {
            display: inline-block;
            background-color: #fef2f2;
            color: #991b1b;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            margin-left: 5px;
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
        
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>📝 Schülerliste</h1>
        <div class="subtitle">{{ $writtenExamDate->name }}</div>
        <div class="subtitle">{{ $writtenExamDate->date->locale('de')->translatedFormat('l, d. F Y') }} | {{ $writtenExamDate->getStartTime()->format('H:i') }} Uhr</div>
    </div>
    
    {{-- Info-Box --}}
    <div class="info-box">
        @php
            $teacherShortcode = $assignment->course->teacher?->shortcode ?? '';
        @endphp
        <p>
            <strong>Kurs:</strong> {{ $assignment->course->course_code }}
            @if($teacherShortcode) ({{ $teacherShortcode }}) @endif
            &ndash; {{ $assignment->course->getLevelDisplayName() }}
        </p>
        <p><strong>Raum:</strong> {{ $assignment->room->room_number }}</p>
        <p><strong>Anzahl Schüler:</strong> {{ $students->count() }}</p>
        @if($assignment->notes)
            <p><strong>Hinweise:</strong> {{ $assignment->notes }}</p>
        @endif
    </div>
    
    {{-- Statistiken --}}
    <div class="statistics">
        <div class="stat-item">
            <div class="stat-value">{{ $students->count() }}</div>
            <div class="stat-label">Schüler gesamt</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $layout?->getTotalCapacity() ?? 0 }}</div>
            <div class="stat-label">Kapazität Raum</div>
        </div>
        <div class="stat-item">
            @php
                // Der Prozentsatz kommt aus dem Controller und gilt fuer
                // Schueler, externe Bewerber und Gaeste gleichermassen.
                $withCompensation = $students->filter(fn ($s) => $s['compensation'] > 0)->count();
            @endphp
            <div class="stat-value">{{ $withCompensation }}</div>
            <div class="stat-label">Mit Nachteilsausgleich</div>
        </div>
    </div>
    
    {{-- Schülertabelle --}}
    @php $isMathematik = $assignment->course->subject?->code === 'M'; @endphp
    @if($isMathematik)
    <p style="font-size:8pt;color:#555;margin-bottom:8px;">
        <em>* A-Teil bis = späteste Abgabe Teil A (max. 110 Min., bei NTA anteilig verlängert)</em>
    </p>
    @endif
    <table class="student-table">
        <thead>
            <tr>
                <th class="center" style="width: 40px;">Nr.</th>
                <th>Name</th>
                <th class="center" style="width: 70px;">Platz</th>
                @if($isMathematik)<th class="center" style="width: 90px;">A-Teil bis *</th>@endif
                <th class="center" style="width: 90px;">Endzeit</th>
                <th style="width: 110px;">Besonderheiten</th>
                <th class="center" style="width: 110px;">Kommentar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $data)
                @php
                    $student = $data['student'];
                    $seatNumber = $data['seat_number'];
                    $endTime = $data['end_time'];
                    $comp = $data['compensation'];
                    $hasCompensation = $comp > 0;
                    if ($isMathematik) {
                        $aTeilTime = $writtenExamDate->getStartTime()
                            ->addMinutes($hasCompensation ? (int) ceil(110 * (1 + $comp / 100)) : 110);
                    }
                @endphp
                <tr @if($hasCompensation) style="background-color: #fff7ed !important;" @endif>
                    <td class="number">{{ $index + 1 }}.</td>
                    <td>
                        <span class="student-name">
                            {{ $student->lastname }}, {{ $student->firstname }}
                        </span>
                        @if($hasCompensation)
                            <span class="compensation-indicator">Nachteilsausgleich</span>
                        @endif
                    </td>
                    <td class="center">
                        @if($seatNumber !== null)
                            <span class="seat-number">{{ $seatNumber }}</span>
                        @else
                            <span style="color:#999;">—</span>
                        @endif
                    </td>
                    @if($isMathematik)
                    <td class="center">
                        @if($hasCompensation)
                            <strong style="color: #991b1b;">{{ $aTeilTime->format('H:i') }}</strong>
                        @else
                            {{ $aTeilTime->format('H:i') }}
                        @endif
                    </td>
                    @endif
                    <td class="center">
                        @if($hasCompensation)
                            <strong style="color: #991b1b;">{{ $endTime->format('H:i') }}</strong>
                        @else
                            {{ $endTime->format('H:i') }}
                        @endif
                    </td>
                    <td>
                        @if($hasCompensation)
                            <span class="compensation-indicator">
                                +{{ $comp }}% Zeit
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="center">
                        <!-- Kommentarfeld leer -->
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    {{-- Unterschriften Aufsicht --}}
    <div class="signature-section">
        <div class="signature-line">
            <div class="signature-field">
                <div class="line"></div>
                <div class="label">Ort, Datum</div>
            </div>
            <div class="signature-field">
                <div class="line"></div>
                <div class="label">Unterschrift Aufsicht</div>
            </div>
        </div>
    </div>
    
    {{-- Footer --}}
    <div class="footer">
        Exportiert am {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr | {{ $assignment->school?->name ?? '' }}
    </div>
</body>
</html>
