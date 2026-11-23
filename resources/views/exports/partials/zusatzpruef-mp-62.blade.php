{{--
    Mindestpunkte-Kalkulator für externe Bewerber — § 62 GSO, für den PDF-Druck.

    Erwartet $externe62 = ['faecher_quelle' => …, 'ergebnis' => …] oder null.
    Gerechnet wird vollständig im ExterneMindestpunkteService; hier steht keine
    Formel.

    Zwei getrennte Blöcke und nie eine gemeinsame Tabelle: § 62 Abs. 1 S. 4
    bricht die Prüfung nach dem ersten Teil ab. Ein starker zweiter Teil kann
    einen schwachen ersten nicht heilen — er findet dann gar nicht statt.
--}}

{{--
    Eigene Klassen statt der des Wirts-Templates: dieser Partial wird aus zwei
    PDFs eingebunden (Komplett und Mindestpunkte), und deren Stylesheets sind
    nicht gleich. Eine fehlende Klasse fällt in dompdf nicht auf — sie rendert
    einfach ohne Wirkung. Deshalb bringt der Block seine Regeln selbst mit,
    einmal je Dokument.
--}}
@once
<style>
    .mp62-title { font-size: 9pt; font-weight: bold; margin: 4pt 0 2pt 0; }
    .mp62-box   { border: 0.5pt solid #d1d5db; padding: 4pt 6pt; }
    .mp62-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
    .mp62-table th, .mp62-table td { border: 0.5pt solid #e5e7eb; padding: 1.5pt 3pt; }
    .mp62-table th { background: #f9fafb; font-weight: 600; color: #4b5563; }
    .mp62-pass  { background: #f0fdf4; }
    .mp62-ok    { color: #15803d; }
    .mp62-nok   { color: #dc2626; }
    .mp62-c     { text-align: center; }
    .mp62-m     { font-family: DejaVu Sans Mono, monospace; }
    .mp62-b     { font-weight: bold; }
</style>
@endonce

<div class="mp62-title">Mindestpunkte-Kalkulator · GSO § 62</div>

@if($externe62 === null)
    <div style="font-size:8pt;color:#6b7280;border:0.5pt solid #d1d5db;padding:4pt 6pt;">
        Für externe Bewerberinnen und Bewerber richtet sich das Bestehen nach § 62 GSO.
        Die Fächerwahl belegt die acht Prüfungsfächer noch nicht vollständig
        (§ 61 Abs. 1 S. 1); solange ist nichts zu rechnen.
    </div>
@else
    @php
        $e62      = $externe62['ergebnis'];
        $quelle   = collect($externe62['faecher_quelle'])->keyBy('position');
        $teil1    = $e62['erster_teil'];
        $teil2    = $e62['zweiter_teil'];
        $rechnung = $e62['rechnung'];
    @endphp

    <div style="font-size:7.5pt;color:#6b7280;margin-bottom:3pt;">
        Fächer 1–4 elffach (je höchstens 165) · Fächer 5–8 vierfach (je höchstens 60) ·
        zusammen 900. Schwellen: 220 im ersten, 80 im zweiten Prüfungsteil.
    </div>

    {{-- ─── Die acht Prüfungsfächer ─────────────────────────────────── --}}
    <table class="mp62-table" style="margin-bottom:6pt;">
        <thead>
            <tr>
                <th class="mp62-c">Nr.</th>
                <th>Fach</th>
                <th class="mp62-c">Niveau</th>
                <th class="mp62-c">Faktor</th>
                <th class="mp62-c">einfach</th>
                <th class="mp62-c">Gesamt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rechnung['faecher'] as $fach)
                <tr @if($fach['position'] === 5) style="border-top:1pt solid #9ca3af;" @endif>
                    <td class="mp62-c">{{ $fach['position'] }}</td>
                    <td>
                        {{ $quelle[$fach['position']]['fach'] ?? '–' }}
                        @if($fach['quelle'] === 'zusatzpruefung')
                            <span style="color:#4f46e5;font-weight:bold;">ZP</span>
                        @elseif($fach['quelle'] === 'einbringung')
                            <span style="color:#0d9488;font-weight:bold;">13/II</span>
                        @endif
                    </td>
                    <td class="mp62-c">{{ $fach['ea'] ? 'eA' : 'gA' }}</td>
                    <td class="mp62-c" style="color:#9ca3af;">×{{ $fach['faktor'] }}</td>
                    <td class="mp62-c mp62-m">{{ $fach['einfache_wertung'] ?? '–' }}</td>
                    <td class="mp62-c mp62-m mp62-b">{{ $fach['gesamtpunktzahl'] ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ─── Abbruch nach dem ersten Prüfungsteil ────────────────────── --}}
    @if($e62['abbruch'] === 'nach_erstem_teil')
        <div style="font-size:8pt;border:0.5pt solid #fca5a5;background:#fef2f2;padding:4pt 6pt;margin-bottom:6pt;">
            <strong style="color:#991b1b;">
                Die Prüfung wird nach dem ersten Prüfungsteil abgebrochen (§ 62 Abs. 1 S. 4).
            </strong>
            <ul style="margin:2pt 0 0 12pt;padding:0;color:#b91c1c;">
                @foreach($rechnung['erster_teil']['gruende'] as $grund)
                    <li>{{ $grund }}</li>
                @endforeach
            </ul>
            <div style="margin-top:2pt;color:#dc2626;">
                Der zweite Prüfungsteil findet damit nicht mehr statt.
            </div>
        </div>
    @endif

    {{-- ─── Erster Prüfungsteil ─────────────────────────────────────── --}}
    <div class="mp62-box" style="margin-bottom:6pt;">
        <div class="mp62-title">
            Erster Prüfungsteil · Fächer 1–4 · § 62 Abs. 1 S. 3
            &nbsp;·&nbsp;
            <span class="{{ $rechnung['erster_teil']['summe'] >= 220 ? 'mp62-ok' : 'mp62-nok' }}">
                {{ $rechnung['erster_teil']['summe'] }}/220
            </span>
            <span style="color:#6b7280;font-size:7pt;">von 660</span>
        </div>

        @if(count($teil1['offene']) === 0)
            <p style="font-size:8pt;color:#6b7280;margin:2pt 0;">
                Keine offene mündliche Zusatzprüfung — es ist nichts aufzulösen.
            </p>
        @elseif(! $teil1['eingaben_vollstaendig'])
            <p style="font-size:8pt;color:#b45309;margin:2pt 0;">
                In mindestens einem Fach des ersten Prüfungsteils fehlt noch die Note.
                Solange ist die Frage nach der Mindestnote nicht gestellt.
            </p>
        @elseif($teil1['zu_viele_offene'])
            <p style="font-size:8pt;color:#b45309;margin:2pt 0;">
                Vier offene Zusatzprüfungen. Eine Tabelle über vier freie Größen hätte
                65 536 Zeilen und wird deshalb nicht ausgegeben.
            </p>
        @elseif($teil1['bestehende'] === 0)
            <p style="font-size:8pt;color:#dc2626;margin:2pt 0;">
                Auch mit 15 Punkten in jeder Zusatzprüfung wird der erste Prüfungsteil
                nicht mehr bestanden.
            </p>
        @else
            <p style="font-size:7.5pt;color:#6b7280;margin:2pt 0;">
                @if(count($teil1['offene']) === 1)
                    Nötige Note in der mündlichen Zusatzprüfung. Anlage 12 S. 4 rundet erst
                    am Endergebnis — die Zeile ist deshalb aufgezählt, nicht überschlagen.
                @else
                    Die gleichmäßigsten Kombinationen; insgesamt bestehen
                    {{ $teil1['bestehende'] }}.
                @endif
            </p>
            <table class="mp62-table">
                <thead>
                    <tr>
                        @foreach($teil1['offene'] as $offen)
                            <th class="mp62-c">
                                {{ $offen['fach'] ?? ('Fach ' . $offen['position']) }}<br>
                                <span style="font-weight:normal;color:#6b7280;font-size:7pt;">
                                    schriftl. {{ $offen['schriftlich'] }}
                                </span>
                            </th>
                        @endforeach
                        <th class="mp62-c">Σ erster Teil</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($teil1['top5'] as $zeile)
                        <tr class="mp62-pass">
                            @foreach($zeile['faecher'] as $fach)
                                <td class="mp62-c">
                                    <span class="mp62-m mp62-b">{{ $fach['muendlich'] }}</span>
                                    <span style="color:#6b7280;font-size:7pt;">
                                        → {{ $fach['einfache_wertung'] }} Pkt.
                                        = {{ $fach['gesamtpunktzahl'] }} (×11)
                                    </span>
                                </td>
                            @endforeach
                            <td class="mp62-c mp62-m mp62-b mp62-ok">{{ $zeile['summe'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ─── Zweiter Prüfungsteil: Restbedarf statt Tabelle ──────────── --}}
    @if($teil2 !== null)
        <div class="mp62-box" style="margin-bottom:6pt;">
            <div class="mp62-title">
                Zweiter Prüfungsteil · Fächer 5–8 · § 62 Abs. 2
                &nbsp;·&nbsp;
                <span class="{{ $teil2['erreicht'] >= 80 ? 'mp62-ok' : 'mp62-nok' }}">
                    {{ $teil2['erreicht'] }}/80
                </span>
                <span style="color:#6b7280;font-size:7pt;">von 240</span>
            </div>

            {{--
                Keine Aufzählung: im zweiten Prüfungsteil gibt es keine
                Zusatzprüfungen und damit keine Rundung. Die Gesamtpunktzahl
                eines Fachs ist exakt Punkte × 4, und die Schwelle wird zur
                reinen Arithmetik.
            --}}
            <p style="font-size:7.5pt;color:#6b7280;margin:2pt 0;">
                Ohne Zusatzprüfung ist die Gesamtpunktzahl eines Fachs exakt Punkte × 4 —
                die 80 Punkte sind damit <strong>20 Punkte einfacher Wertung</strong> über
                vier Fächer, also ein Schnitt von 5.
            </p>

            @if(count($teil2['offene']) === 0)
                <p style="font-size:8pt;margin:2pt 0;"
                   class="{{ $teil2['erreichbar'] ? 'mp62-ok' : 'mp62-nok' }}">
                    Alle vier Fächer sind bewertet — der zweite Prüfungsteil ist
                    {{ $teil2['erreichbar'] ? 'bestanden' : 'nicht bestanden' }}.
                </p>
            @else
                <p style="font-size:8pt;margin:2pt 0;">
                    Bereits erreicht: <span class="mp62-m mp62-b">{{ $teil2['erreicht'] }}</span>
                    Punkte ({{ $teil2['erreichte_wertung'] }} von 20 einfacher Wertung).
                    Offen:
                    {{ collect($teil2['offene'])
                        ->map(fn ($f) => $f['fach'] ?? ('Fach ' . $f['position']))
                        ->implode(', ') }}.
                </p>
                <ul style="margin:2pt 0 0 12pt;padding:0;font-size:8pt;
                           color:{{ $teil2['erreichbar'] ? '#374151' : '#b91c1c' }};">
                    <li>
                        zusammen noch
                        <strong>{{ $teil2['noch_noetige_wertung'] }}</strong>
                        Punkte einfacher Wertung
                        (= {{ $teil2['noch_noetige_punkte'] }} Punkte der Gesamtwertung)
                    </li>
                    <li>kein Fach mit 0 Punkten</li>
                    @if($teil2['noch_faecher_mit_fuenf'] > 0)
                        <li>
                            noch <strong>{{ $teil2['noch_faecher_mit_fuenf'] }}</strong>
                            Fach/Fächer mit mindestens 5 Punkten
                        </li>
                    @else
                        <li>zwei Fächer mit mindestens 5 Punkten stehen bereits</li>
                    @endif
                </ul>
                @if(! $teil2['erreichbar'])
                    <p style="font-size:8pt;margin:3pt 0 0 0;" class="mp62-nok">
                        <strong>Der zweite Prüfungsteil ist nicht mehr zu bestehen.</strong>
                    </p>
                @endif
            @endif

            @if($teil2['gruende'] !== [])
                <ul style="margin:2pt 0 0 12pt;padding:0;font-size:8pt;color:#b91c1c;">
                    @foreach($teil2['gruende'] as $grund)
                        <li>{{ $grund }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- ─── Gesamtqualifikation (§ 62 Abs. 3) ───────────────────────── --}}
    @if($rechnung['gesamtqualifikation'] !== null)
        <div style="font-size:9pt;border:0.5pt solid {{ $rechnung['bestanden'] ? '#86efac' : '#fca5a5' }};
                    background:{{ $rechnung['bestanden'] ? '#f0fdf4' : '#fef2f2' }};padding:4pt 6pt;">
            <strong class="{{ $rechnung['bestanden'] ? 'mp62-ok' : 'mp62-nok' }}">
                {{ $rechnung['bestanden']
                    ? 'Abiturprüfung bestanden (§ 62 Abs. 3)'
                    : 'Abiturprüfung nicht bestanden' }}
            </strong>
            &nbsp;·&nbsp;
            <span class="mp62-m mp62-b">{{ $rechnung['gesamtqualifikation'] }}/900</span>
        </div>
    @endif
@endif
