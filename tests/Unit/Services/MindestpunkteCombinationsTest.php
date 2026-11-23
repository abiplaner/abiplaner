<?php

namespace Tests\Unit\Services;

use App\Services\MindestpunkteService;
use PHPUnit\Framework\TestCase;

/**
 * Tests für die Kombinationsberechnung (offene ZP-Fächer, mündliche Note noch
 * unbekannt) mit N=1, N=2 und N=3 gleichzeitig offenen Zusatzprüfungen.
 *
 * Kernidee (Round-Trip): Jede von computeCombinations() als "bestanden"
 * gemeldete Kombination wird mit den vorgeschlagenen oral-Werten als feste
 * targetPoints erneut durch compute() geschickt. Das Ergebnis muss
 * deckungsgleich sein (pass=true, gleiche Kriterien) — sonst weicht die
 * Vorausberechnung von der tatsächlichen Bewertung ab.
 *
 * Ausführen: docker compose exec abiplaner_app php vendor/bin/phpunit --filter Mindestpunkte
 */
class MindestpunkteCombinationsTest extends TestCase
{
    private MindestpunkteService $svc;

    protected function setUp(): void
    {
        $this->svc = new MindestpunkteService();
    }

    /**
     * Nimmt eine combinations-Zeile (oral-Array in $zpExams-Reihenfolge) und
     * baut daraus ein $exams-Array mit fix gesetzten targetPoints, um das
     * Ergebnis erneut (jetzt mit N=0) durch compute() zu schicken.
     *
     * oral[i] === null bedeutet fachlich "diese ZP ist nicht nötig" (siehe
     * checkReqsWithSkip() im Service) — das Fach wird dann wie "ZP nicht
     * angetreten" behandelt: isZusatzpruefung=false, score=writtenPoints,
     * NICHT targetPoints=null (das würde es fälschlich wieder als offen
     * gelten lassen statt als "nicht benötigt").
     */
    private function fixateOralValues(array $exams, array $zpExamIds, array $oralRow): array
    {
        return array_map(function ($e) use ($zpExamIds, $oralRow) {
            $pos = array_search($e['id'], $zpExamIds, true);
            if ($pos !== false && ($e['isZusatzpruefung'] ?? false)) {
                if ($oralRow[$pos] === null) {
                    $e['isZusatzpruefung'] = false;
                    $e['score']            = $e['writtenPoints'] ?? null;
                } else {
                    $e['targetPoints'] = $oralRow[$pos];
                }
            }
            return $e;
        }, $exams);
    }

    // ─── N=1: Ein offenes ZP-Fach (Französisch), Rest fix ────────────

    public function test_n1_zpExams_enthaelt_genau_das_offene_fach(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischZp(3, null), // offen
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertCount(1, $result['zpExams']);
        $this->assertSame(3, $result['zpExams'][0]['id']);
        $this->assertNull($result['pass'], 'Bei offenem ZP sollte pass auf Top-Level null sein');
    }

    public function test_n1_round_trip_alle_bestandenen_kombinationen_stimmen(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischZp(3, null),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $zpIds  = array_column($result['zpExams'], 'id');
        $this->assertNotEmpty($result['passedCombinations'], 'Es sollte mind. eine bestandene Kombination geben');

        foreach ($result['passedCombinations'] as $row) {
            $fixed   = $this->fixateOralValues($exams, $zpIds, $row['oral']);
            $reCheck = $this->svc->compute($fixed);
            $this->assertTrue(
                $reCheck['pass'],
                'Kombination oral=' . json_encode($row['oral']) . ' wurde als bestanden gemeldet, '
                . 'ist es aber bei fixer Neuberechnung nicht (total=' . $reCheck['total'] . ')'
            );
            $this->assertSame($row['total'], $reCheck['total'], 'Total in der Vorschau muss zur Neuberechnung passen');
        }
    }

    public function test_n1_kleinste_bestandene_kombination_ist_minimal(): void
    {
        // written=3 -> die kleinste bestehende mündliche Note finden und prüfen,
        // dass eine Note darunter tatsächlich NICHT bestehen würde.
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischZp(3, null),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $passed = $result['passedCombinations'];
        $this->assertNotEmpty($passed);
        $minOral = min(array_map(fn ($r) => $r['oral'][0], $passed));

        if ($minOral > 0) {
            $exams[2]['targetPoints'] = $minOral - 1;
            $below = $this->svc->compute($exams);
            $this->assertFalse($below['pass'], "Note " . ($minOral - 1) . " (eins unter der gemeldeten Mindestnote {$minOral}) sollte nicht bestehen");
        }
    }

    // ─── N=2: Zwei offene ZP-Fächer (Mathematik + Französisch) ───────

    public function test_n2_zpExams_enthaelt_beide_offenen_faecher(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(3, null),
            MindestpunkteFaelle::franzoesischZp(3, null),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertCount(2, $result['zpExams']);
    }

    public function test_n2_round_trip_stichprobe_bestandener_kombinationen(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(3, null),
            MindestpunkteFaelle::franzoesischZp(3, null),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $zpIds  = array_column($result['zpExams'], 'id');
        $this->assertNotEmpty($result['passedCombinations']);

        // Stichprobe statt aller Kombinationen (N=2 kann viele Zeilen haben):
        // erste, mittlere und letzte bestandene Kombination prüfen.
        $passed = $result['passedCombinations'];
        $sampleIdx = array_unique([0, intdiv(count($passed), 2), count($passed) - 1]);
        foreach ($sampleIdx as $idx) {
            $row     = $passed[$idx];
            $fixed   = $this->fixateOralValues($exams, $zpIds, $row['oral']);
            $reCheck = $this->svc->compute($fixed);
            $this->assertTrue(
                $reCheck['pass'],
                'Kombination oral=' . json_encode($row['oral']) . ' (Index ' . $idx . ') wurde als bestanden '
                . 'gemeldet, ist es aber bei fixer Neuberechnung nicht'
            );
            $this->assertSame($row['total'], $reCheck['total']);
        }
    }

    public function test_n2_top5_kombinationen_sind_alle_tatsaechlich_bestanden(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(3, null),
            MindestpunkteFaelle::franzoesischZp(3, null),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $zpIds  = array_column($result['zpExams'], 'id');
        $this->assertNotEmpty($result['top5Combinations'], 'top5Combinations sollte bei N=2 mit bestehbaren Kombinationen nicht leer sein');

        foreach ($result['top5Combinations'] as $row) {
            $fixed   = $this->fixateOralValues($exams, $zpIds, $row['oral']);
            $reCheck = $this->svc->compute($fixed);
            $this->assertTrue($reCheck['pass'], 'Top5-Kombination oral=' . json_encode($row['oral']) . ' besteht bei Neuberechnung nicht');
        }
    }

    public function test_n2_unmoeglicher_fall_keine_kombination_besteht(): void
    {
        // Deutsch und Politik beide sehr niedrig, Sport kein LF mehr (entfernt
        // LF-Schutz) -> selbst mit 15/15 mündlich in beiden ZPs kann das
        // Dreierblock-/3-Fächer-Kriterium nicht erreicht werden.
        $exams = [
            MindestpunkteFaelle::deutsch(4),
            MindestpunkteFaelle::mathematikZp(0, null),
            MindestpunkteFaelle::franzoesischZp(0, null),
            MindestpunkteFaelle::politik(4),
            MindestpunkteFaelle::fix(5, 'Kunst', 4), // kein LF, niedrig
        ];
        $result = $this->svc->compute($exams);
        $this->assertEmpty($result['passedCombinations'], 'Bei diesem Aufbau sollte keine Kombination bestehen können');
    }

    // ─── N=3: Drei offene ZP-Fächer ───────────────────────────────────

    public function test_n3_zpExams_enthaelt_alle_drei_offenen_faecher(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(3, null),
            MindestpunkteFaelle::franzoesischZp(3, null),
            MindestpunkteFaelle::zp(6, 'Biologie', 3, null, ['isScience' => true, 'aufgabenfeld' => 'mint']),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertCount(3, $result['zpExams']);
    }

    public function test_n3_round_trip_top5_kombinationen_sind_bestanden(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(3, null),
            MindestpunkteFaelle::franzoesischZp(3, null),
            MindestpunkteFaelle::zp(6, 'Biologie', 3, null, ['isScience' => true, 'aufgabenfeld' => 'mint']),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $zpIds  = array_column($result['zpExams'], 'id');
        $this->assertNotEmpty($result['top5Combinations'], 'top5Combinations sollte bei N=3 mit bestehbaren Kombinationen nicht leer sein');

        foreach ($result['top5Combinations'] as $row) {
            $fixed   = $this->fixateOralValues($exams, $zpIds, $row['oral']);
            $reCheck = $this->svc->compute($fixed);
            $this->assertTrue(
                $reCheck['pass'],
                'N=3 Top5-Kombination oral=' . json_encode($row['oral']) . ' besteht bei Neuberechnung nicht (total=' . $reCheck['total'] . ')'
            );
            $this->assertSame($row['total'], $reCheck['total']);
        }
    }

    public function test_n3_alle_drei_voll_15_punkte_sollte_bestehen_wenn_ueberhaupt(): void
    {
        // Sanity-Check: Mit der bestmöglichen mündlichen Note (15) in allen drei
        // offenen ZPs muss es — sofern der Aufbau überhaupt lösbar ist — mindestens
        // eine bestandene Kombination geben (die mit 15/15/15 sollte dann auch passen).
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(3, 15),
            MindestpunkteFaelle::franzoesischZp(3, 15),
            MindestpunkteFaelle::zp(6, 'Biologie', 3, 15, ['isScience' => true, 'aufgabenfeld' => 'mint']),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['pass'], 'Mit maximaler mündlicher Note (15) in allen ZPs sollte das Abitur bestanden sein');
    }
}

