<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>W-Seminar Kurse</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .page-break { page-break-after: always; }
        h1 { font-size: 20px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        h2 { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; font-size: 12px; }
        th { background-color: #f0f0f0; }
        .header { margin-bottom: 30px; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .meta { font-size: 12px; color: #666; margin-bottom: 20px; }
        .stats { margin-top: 5px; font-size: 12px; }
        .unassigned { color: #d32f2f; font-weight: bold; }
    </style>
</head>
<body>

{{-- Übersicht --}}
<div class="page-break">
    <div class="header">
        <strong>W-Seminar Zuteilung Übersicht</strong>
        <span style="float: right;">{{ date('d.m.Y H:i') }}</span>
    </div>
    
    <h1>W-Seminar Zuteilung {{ $schuljahr }} (Klasse {{ $jahrgang }})</h1>
    
    <h2>Statistik</h2>
    <table>
        <thead>
            <tr>
                <th>Kategorie</th>
                <th>Anzahl</th>
                <th>Kategorie</th>
                <th>Anzahl</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Schüler gesamt</td>
                <td>{{ $statistik['anzahl_schueler'] }}</td>
                <td>Erstwahl erhalten</td>
                <td>{{ $statistik['erstwahl'] }}</td>
            </tr>
            <tr>
                <td>Zugeteilt</td>
                <td>{{ $statistik['zugeteilt'] }}</td>
                <td>Zweitwahl erhalten</td>
                <td>{{ $statistik['zweitwahl'] }}</td>
            </tr>
            <tr>
                <td class="unassigned">Nicht zugeteilt</td>
                <td class="unassigned">{{ $statistik['nicht_zugeteilt'] }}</td>
                <td>Drittwahl erhalten</td>
                <td>{{ $statistik['drittwahl'] }}</td>
            </tr>
        </tbody>
    </table>
    
    <h2>Seminar-Übersicht</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 25px;">#</th>
                <th>Kurs</th>
                <th>Lehrer</th>
                <th style="text-align: center;">Teilnehmer</th>
                <th style="text-align: center;">Erst</th>
                <th style="text-align: center;">Zweit</th>
                <th style="text-align: center;">Dritt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kurse as $kurs)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $kurs->subject?->name }} - {{ $kurs->titel }}</td>
                    <td>{{ $kurs->lehrer?->name ?? '-' }}</td>
                    <td style="text-align: center;">{{ $kurs->wahlen->count() }} / {{ $kurs->max_teilnehmer }}</td>
                    <td style="text-align: center;">{{ $kurs->erstwahlen_count }}</td>
                    <td style="text-align: center;">{{ $kurs->zweitwahlen_count }}</td>
                    <td style="text-align: center;">{{ $kurs->drittwahlen_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Nicht zugeteilte Schüler --}}
@if($unassigned->count() > 0)
    <div class="page-break">
        <div class="header">
            <strong class="unassigned">Nicht zugeteilte Schüler</strong>
            <span style="float: right;">{{ date('d.m.Y H:i') }}</span>
        </div>
        
        <h1>Nicht zugeteilte Schüler</h1>
        
        <div class="meta">
            <div><strong>Schuljahr:</strong> {{ $schuljahr }} | <strong>Jahrgang:</strong> {{ $jahrgang }}</div>
            <div class="stats">
                <strong>Anzahl:</strong> {{ $unassigned->count() }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">Nr.</th>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>Klasse</th>
                    <th>Erstwahl</th>
                    <th>Zweitwahl</th>
                    <th>Drittwahl</th>
                </tr>
            </thead>
            <tbody>
                @foreach($unassigned as $index => $wahl)
                    <tr>
                        <td style="text-align: center;">{{ $loop->iteration }}</td>
                        <td>{{ $wahl->schueler->lastname }}</td>
                        <td>{{ $wahl->schueler->firstname }}</td>
                        <td>{{ $wahl->schueler->class_name }}</td>
                        <td>{{ $wahl->erstwahl?->titel ?? '-' }}</td>
                        <td>{{ $wahl->zweitwahl?->titel ?? '-' }}</td>
                        <td>{{ $wahl->drittwahl?->titel ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Einzelseiten für Kurse --}}
@foreach($kurse as $kurs)
    <div class="{{ !$loop->last ? 'page-break' : '' }}">
        <div class="header">
            <strong>W-Seminar Kursliste</strong>
            <span style="float: right;">{{ date('d.m.Y H:i') }}</span>
        </div>
        
        <h1>{{ $kurs->subject->name }}</h1>
        
        <div class="meta">
            <div><strong>Titel:</strong> {{ $kurs->titel }}</div>
            <div><strong>Leitung:</strong> {{ $kurs->lehrer?->name ?? 'Unbekannt' }}</div>
            <div><strong>Schuljahr:</strong> {{ $schuljahr }} | <strong>Jahrgang:</strong> {{ $jahrgang }}</div>
            <div class="stats">
                <strong>Teilnehmer:</strong> {{ $kurs->wahlen->count() }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">Nr.</th>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>Klasse</th>
                    <th>Priorität</th>
                </tr>
            </thead>
            <tbody>
                @foreach($kurs->sorted_wahlen as $index => $wahl)
                    <tr>
                        <td style="text-align: center;">{{ $loop->iteration }}</td>
                        <td>{{ $wahl->schueler->lastname }}</td>
                        <td>{{ $wahl->schueler->firstname }}</td>
                        <td>{{ $wahl->schueler->class_name }}</td>
                        <td>
                            @if($wahl->prioritaet_erhalten === 'erstwahl') Erstwahl
                            @elseif($wahl->prioritaet_erhalten === 'zweitwahl') Zweitwahl
                            @elseif($wahl->prioritaet_erhalten === 'drittwahl') Drittwahl
                            @else -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

</body>
</html>
