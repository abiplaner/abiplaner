{{--
    Generische Mindestpunkte-Kombinationstabelle für PDF-Exporte (GSO §61).
    Funktioniert einheitlich für N=1, N=2 und N=3 offene ZP-Fächer, indem
    pro Fach zwei Spalten (Mündl. / Komb.+Beitrag) erzeugt werden – analog
    zur Logik in mpRowCells()/mpHeaderCells() im Browser-Modal.

    Erwartete Variablen:
      $zpExams           Liste der offenen ZP-Fächer (aus computed['zpExams'])
      $top5              computed['top5Combinations'] (bereits max. 5 Zeilen,
                          bereits durch filterWorsening gefiltert)
      $totalPassedCount  Gesamtzahl aller bestandenen (nicht-verschlechternden)
                          Kombinationen – für den "nicht alle Kombinationen"-Hinweis
      $svc               MindestpunkteService-Instanz
      $tableClass        optionale zusätzliche CSS-Klasse für <table> (z.B.
                          "mp-table" im komplett-pdf-Template, das eigene
                          th/td-Stile unter diesem Selektor definiert)
--}}
@php
    $zpN = count($zpExams);
    $tableClass = $tableClass ?? '';
@endphp

@if(empty($top5))
    <p style="color:#dc2626;font-size:7.5pt;margin:2pt 0;">
        ✗ Keine Kombination (0–15) führt zum Bestehen des Abiturs.
    </p>
@else
<table class="{{ $tableClass }}">
    <thead>
        <tr>
            @foreach($zpExams as $zp)
                <th colspan="2" class="text-center">{{ $zp['subject'] }}</th>
            @endforeach
            <th class="text-center">Σ×4</th>
        </tr>
        <tr>
            @foreach($zpExams as $zp)
                <th class="col-komb text-center">Mündl.</th>
                <th class="col-komb text-center">Komb./Beitr.</th>
            @endforeach
            <th class="col-komb text-center">Pkt.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($top5 as $row)
        <tr class="row-pass">
            @for($zi = 0; $zi < $zpN; $zi++)
                @php
                    $oral     = $row['oral'][$zi] ?? null;
                    $combined = $row['combined'][$zi] ?? null;
                    $contrib  = $row['contrib'][$zi] ?? null;
                    $written  = $zpExams[$zi]['writtenPoints'] ?? null;
                    $delta    = ($contrib !== null && $written !== null) ? ($contrib - $written * 4) : null;
                    $cClass   = $combined === null ? 'text-gray'
                              : ($combined >= 5 ? 'c5' : ($combined >= 4 ? 'c4' : ($combined >= 1 ? 'c1' : 'c0')));
                @endphp
                <td class="font-mono font-bold text-center">{{ $oral !== null ? $oral : '–' }}</td>
                <td class="font-mono text-center {{ $cClass }}">
                    @if($oral === null)
                        <span class="text-gray" style="font-style:italic;">nicht nötig</span>
                    @else
                        {{ $combined }}&thinsp;/&thinsp;{{ $contrib }}
                        @if($delta !== null)
                            <span class="cok">({{ $delta >= 0 ? '+' : '' }}{{ $delta }})</span>
                        @endif
                    @endif
                </td>
            @endfor
            <td class="font-mono text-center">{{ $row['total'] !== null ? $row['total'] : '–' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="note-text">
    Bis zu 5 wahrscheinlichste Kombinationen (mündliche Note nahe der schriftlichen). „–" = ZP nicht nötig.
    @if($totalPassedCount > count($top5))
        <br><strong>Hinweis:</strong> Es gibt insgesamt {{ $totalPassedCount }} bestehende Kombinationen —
        hier werden nur die {{ count($top5) }} wahrscheinlichsten angezeigt, nicht alle möglichen.
    @endif
</div>
@endif
