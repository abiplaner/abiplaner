<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Absenzen: Aktion nötig – Q{{ $grade }}</title>
    @include('exports.partials.pdf-base-styles')
    <style>
        .pdf-table th, .pdf-table td { font-size: 8pt; }
        .reason-badge {
            display: inline-block;
            padding: 1px 5px;
            margin: 0 2px 2px 0;
            border-radius: 2px;
            font-size: 7pt;
            color: #ffffff;
            background-color: #6b7280;
        }
        .reason-badge.krankheit { background-color: #c2410c; }
        .reason-badge.unentschuldigt { background-color: #7e22ce; }
        .reason-badge.verspaetung { background-color: #1d4ed8; }
        .reason-badge.attest { background-color: #b91c1c; }
    </style>
</head>
<body>
    <div class="pdf-header">
        <h1>Absenzen: Aktion nötig – Q{{ $grade }}</h1>
        <div class="pdf-subtitle">
            Stand: {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr ·
            {{ $rows->count() }} {{ $rows->count() === 1 ? 'Schüler' : 'Schüler' }}
        </div>
    </div>

    <div class="info-box">
        <p>Schwellenwerte: Krankheitstage ab {{ $thresholds['krank_percent'] }}% der bisherigen Schultage,
           unentschuldigte Fehlstunden ab {{ $thresholds['unentschuldigt_count'] }},
           Verspätungen ab {{ $thresholds['verspaetet_count'] }}. Zusätzlich: überfällige Atteste
           (10-Tage-Frist überschritten).</p>
        <p>Zähler gelten seit dem letzten Reset des jeweiligen Aspekts (dokumentierte Maßnahme) bzw.
           seit Beginn der Datenerfassung. Reine Kennzahlen, keine automatische Bewertung.</p>
    </div>

    @if ($rows->isEmpty())
        <p class="pdf-muted">Keine Schüler mit Handlungsbedarf.</p>
    @else
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width: 22%;">Schüler</th>
                    <th style="width: 10%;">Klasse</th>
                    <th style="width: 38%;">Gründe</th>
                    <th style="width: 10%;">Krank</th>
                    <th style="width: 10%;">Unentsch.</th>
                    <th style="width: 10%;">Versp.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    <tr class="{{ $i % 2 === 1 ? 'pdf-row-even' : '' }}">
                        <td class="pdf-bold">{{ $row['name'] }}</td>
                        <td>{{ $row['class_name'] }}</td>
                        <td>
                            @foreach ($row['reasons'] as $reason)
                                @php
                                    $cls = match($reason) {
                                        'Krankheit' => 'krankheit',
                                        'Unentschuldigt' => 'unentschuldigt',
                                        'Verspätung' => 'verspaetung',
                                        'Attest überfällig' => 'attest',
                                        default => '',
                                    };
                                @endphp
                                <span class="reason-badge {{ $cls }}">{{ $reason }}</span>
                            @endforeach
                        </td>
                        <td class="pdf-center {{ $row['auffaellig_krankheit'] ? 'pdf-danger pdf-bold' : '' }}">
                            {{ $row['krank_days'] }}{{ $row['krank_percent'] !== null ? ' (' . number_format($row['krank_percent'], 1, ',', '.') . '%)' : '' }}
                        </td>
                        <td class="pdf-center {{ $row['auffaellig_unentschuldigt'] ? 'pdf-danger pdf-bold' : '' }}">
                            {{ $row['unentschuldigt_count'] }}
                        </td>
                        <td class="pdf-center {{ $row['auffaellig_verspaetung'] ? 'pdf-danger pdf-bold' : '' }}">
                            {{ $row['verspaetet_count'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="pdf-footer">
        Enthält Gesundheitsdaten (Art. 9 DSGVO) — vertraulich behandeln. Exportiert am
        {{ now()->locale('de')->translatedFormat('d.m.Y H:i') }} Uhr.
    </div>
</body>
</html>
