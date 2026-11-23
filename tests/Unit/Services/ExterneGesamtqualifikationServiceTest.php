<?php

namespace Tests\Unit\Services;

use App\Services\ExterneGesamtqualifikationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * § 62 GSO — Gesamtqualifikation und Bestehen bei anderen Bewerbern.
 * Reine Logiktests ohne Datenbank.
 *
 * Grundlage sind die von Wilfried vorgelegten Wortlaute von §§ 61, 62, 64 und
 * der Anlagen 12, 14 und 15; die Regel-IDs verweisen auf
 * docs/externe-bewerber/REGELWERK-61-62.md.
 *
 * Ausführen: docker compose exec abiplaner_app php vendor/bin/phpunit --filter ExterneGesamtqualifikation
 */
class ExterneGesamtqualifikationServiceTest extends TestCase
{
    private ExterneGesamtqualifikationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExterneGesamtqualifikationService();
    }

    /**
     * Acht Prüfungsfächer mit den Positionen 1–8.
     *
     * @param list<int|null> $punkte einfache Wertung je Position, null = offen
     * @param list<int> $eaPositionen Fächer auf erhöhtem Anforderungsniveau
     * @return list<array<string, mixed>>
     */
    private function faecher(array $punkte, array $eaPositionen = [1, 2, 3]): array
    {
        $faecher = [];

        foreach ($punkte as $index => $wert) {
            $faecher[] = [
                'position' => $index + 1,
                'fach' => 'Fach ' . ($index + 1),
                'ea' => in_array($index + 1, $eaPositionen, true),
                'punkte' => $wert,
            ];
        }

        return $faecher;
    }

    // ── E1: Faktoren und Höchstpunktzahlen (Anlage 14) ──────────────────────

    public function test_hoechstpunktzahl_ist_900(): void
    {
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 15, 15, 15, 15, 15]));

        $this->assertSame(660, $ergebnis['erster_teil']['summe']);
        $this->assertSame(240, $ergebnis['zweiter_teil']['summe']);
        $this->assertSame(900, $ergebnis['gesamtqualifikation']);
        $this->assertTrue($ergebnis['bestanden']);
    }

    public function test_schriftliche_faecher_zaehlen_elffach_muendliche_vierfach(): void
    {
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 15, 15, 15, 15, 15]));

        $this->assertSame(11, $ergebnis['faecher'][0]['faktor']);
        $this->assertSame(165, $ergebnis['faecher'][0]['gesamtpunktzahl']);
        $this->assertSame(11, $ergebnis['faecher'][3]['faktor']);
        $this->assertSame(4, $ergebnis['faecher'][4]['faktor']);
        $this->assertSame(60, $ergebnis['faecher'][4]['gesamtpunktzahl']);
        $this->assertSame(4, $ergebnis['faecher'][7]['faktor']);
    }

    // ── E5: Bestehen des ersten Prüfungsteils ───────────────────────────────

    public function test_erster_teil_ist_mit_genau_220_punkten_bestanden(): void
    {
        // 4 × 5 Punkte × Faktor 11 = 220 — genau die Schwelle.
        $ergebnis = $this->service->berechne($this->faecher([5, 5, 5, 5, 5, 5, 5, 5]));

        $this->assertSame(220, $ergebnis['erster_teil']['summe']);
        $this->assertTrue($ergebnis['erster_teil']['bestanden']);
        $this->assertSame([], $ergebnis['erster_teil']['gruende']);
    }

    public function test_erster_teil_unter_220_punkten_wird_nicht_aufgerundet(): void
    {
        // 19 × 11 = 209. § 62 Abs. 1 S. 3: "Ein Aufrunden ist nicht zulässig."
        $ergebnis = $this->service->berechne($this->faecher([5, 5, 5, 4, 5, 5, 5, 5]));

        $this->assertSame(209, $ergebnis['erster_teil']['summe']);
        $this->assertFalse($ergebnis['erster_teil']['bestanden']);
        $this->assertStringContainsString('209 von mindestens 220', $ergebnis['erster_teil']['gruende'][0]);
    }

    public function test_ein_fach_mit_null_punkten_laesst_den_teil_scheitern(): void
    {
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 0, 15, 15, 15, 15]));

        $this->assertFalse($ergebnis['erster_teil']['bestanden']);
        $this->assertStringContainsString('Fach 4 ist mit 0 Punkten', $ergebnis['erster_teil']['gruende'][0]);
    }

    public function test_zwei_faecher_mit_mindestens_fuenf_punkten_sind_noetig(): void
    {
        // Nur Fach 1 erreicht 5 Punkte der einfachen Wertung.
        $ergebnis = $this->service->berechne($this->faecher([15, 4, 4, 4, 15, 15, 15, 15], [1]));

        $this->assertFalse($ergebnis['erster_teil']['bestanden']);
        $this->assertStringContainsString(
            'Nur 1 Fach/Fächer mit mindestens 5 Punkten',
            implode(' ', $ergebnis['erster_teil']['gruende'])
        );
    }

    public function test_eines_der_beiden_faecher_muss_auf_erhoehtem_niveau_liegen(): void
    {
        // Punkte reichen, aber kein einziges Fach auf erhöhtem Niveau.
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 15, 15, 15, 15, 15], []));

        $this->assertFalse($ergebnis['erster_teil']['bestanden']);
        $this->assertStringContainsString(
            'keines auf erhöhtem Anforderungsniveau',
            implode(' ', $ergebnis['erster_teil']['gruende'])
        );
    }

    public function test_der_zweite_teil_verlangt_kein_erhoehtes_niveau(): void
    {
        // § 62 Abs. 2 nennt die Niveau-Bedingung nicht — anders als Abs. 1 S. 3.
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 15, 15, 15, 15, 15], [1, 2, 3]));

        $this->assertTrue($ergebnis['zweiter_teil']['bestanden']);
        $this->assertSame([], $ergebnis['zweiter_teil']['gruende']);
    }

    public function test_zweiter_teil_ist_mit_genau_80_punkten_bestanden(): void
    {
        // 4 × 5 Punkte × Faktor 4 = 80 — genau ein Drittel von 240.
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 15, 5, 5, 5, 5]));

        $this->assertSame(80, $ergebnis['zweiter_teil']['summe']);
        $this->assertTrue($ergebnis['zweiter_teil']['bestanden']);
    }

    // ── E4: Mündliche Zusatzprüfung, Anlage 12 Satz 4 ───────────────────────

    public function test_zusatzpruefung_rundet_erst_am_endergebnis(): void
    {
        // s = 8, m = 10: (2 × 8 + 10) / 3 × 11 = 95,33 → 95.
        // Früh gerundet wäre round(26 / 3) = 9, mal 11 also 99 — vier Punkte
        // zu viel. Anlage 12 rundet ausdrücklich erst am Endergebnis.
        $this->assertSame(95, $this->service->zusatzpruefungspunktzahl(8, 10, 11));
    }

    public function test_zusatzpruefung_ist_formgleich_mit_dem_regulaeren_abitur(): void
    {
        // Dieselbe Formel, nur mit Faktor 4 statt 11: MindestpunkteService
        // setzt sie für reguläre Schüler um. Weicht eine der beiden ab, ist
        // eine von beiden falsch.
        $mindestpunkte = new \App\Services\MindestpunkteService();

        foreach ([[8, 10], [15, 15], [0, 15], [7, 3], [12, 4]] as [$schriftlich, $muendlich]) {
            $this->assertSame(
                $mindestpunkte->calcZpContribution($schriftlich, $muendlich),
                $this->service->zusatzpruefungspunktzahl($schriftlich, $muendlich, 4),
                "Abweichung bei s={$schriftlich}, m={$muendlich}"
            );
        }
    }

    public function test_zusatzpruefung_unter_elf_punkten_laesst_die_pruefung_scheitern(): void
    {
        // 2s + m = 2 → 7 Punkte in elffacher Wertung. Anlage 12 S. 6 verlangt
        // mindestens 11. Zugleich ist die einfache Wertung 0, sodass auch
        // § 62 Abs. 1 S. 3 Nr. 1 greift — beide Gründe fallen zusammen.
        $faecher = $this->faecher([null, 15, 15, 15, 15, 15, 15, 15]);
        $faecher[0] = [
            'position' => 1,
            'fach' => 'Mathematik',
            'ea' => true,
            'zusatzpruefung' => ['schriftlich' => 1, 'muendlich' => 0],
        ];

        $ergebnis = $this->service->berechne($faecher);

        $this->assertSame(7, $ergebnis['faecher'][0]['gesamtpunktzahl']);
        $this->assertSame(0, $ergebnis['faecher'][0]['einfache_wertung']);
        $this->assertFalse($ergebnis['erster_teil']['bestanden']);

        $gruende = implode(' ', $ergebnis['erster_teil']['gruende']);
        $this->assertStringContainsString('0 Punkten', $gruende);
        $this->assertStringContainsString('Anlage 12 S. 6', $gruende);
    }

    public function test_zusatzpruefung_mit_genau_elf_punkten_traegt(): void
    {
        // 2s + m = 3 → einfache Wertung 1, elffache Wertung 11. Das ist die
        // Grenze, ab der beide Bedingungen erfüllt sind.
        $this->assertSame(11, $this->service->zusatzpruefungspunktzahl(1, 1, 11));
        $this->assertSame(1, $this->service->einfacheWertungMitZusatzpruefung(1, 1));
    }

    // ── E6 / D2: die beiden Abbruchtatbestände ──────────────────────────────

    public function test_abbruch_nach_dem_ersten_pruefungsteil(): void
    {
        // § 62 Abs. 1 S. 4. Der zweite Teil ist noch gar nicht bewertet.
        $ergebnis = $this->service->berechne($this->faecher([4, 4, 4, 4, null, null, null, null]));

        $this->assertFalse($ergebnis['erster_teil']['bestanden']);
        $this->assertSame(
            ExterneGesamtqualifikationService::ABBRUCH_NACH_ERSTEM_TEIL,
            $ergebnis['abbruch']
        );
        $this->assertFalse($ergebnis['bestanden']);
        $this->assertNull($ergebnis['gesamtqualifikation']);
    }

    public function test_ohne_ergebnisse_des_zweiten_teils_bleibt_das_ergebnis_offen(): void
    {
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 15, null, null, null, null]));

        $this->assertTrue($ergebnis['erster_teil']['bestanden']);
        $this->assertNull($ergebnis['zweiter_teil']['bestanden']);
        $this->assertNull($ergebnis['abbruch']);
        $this->assertNull($ergebnis['bestanden']);
        $this->assertNull($ergebnis['gesamtqualifikation']);
        $this->assertFalse($ergebnis['vollstaendig']);
    }

    public function test_eine_null_im_zweiten_teil_steht_schon_vor_dem_letzten_ergebnis_fest(): void
    {
        $ergebnis = $this->service->berechne($this->faecher([15, 15, 15, 15, 0, null, null, null]));

        $this->assertFalse($ergebnis['zweiter_teil']['bestanden']);
        $this->assertFalse($ergebnis['zweiter_teil']['vollstaendig']);
        $this->assertFalse($ergebnis['bestanden']);
    }

    public function test_vorgezogene_muendliche_pruefung_mit_null_punkten_bricht_ab(): void
    {
        // § 61 Abs. 4 S. 2
        $ergebnis = $this->service->abbruchNachVorgezogenenMuendlichen([
            ['position' => 5, 'punkte' => 0],
            ['position' => 6, 'punkte' => 12],
        ]);

        $this->assertTrue($ergebnis['abbruch']);
        $this->assertStringContainsString('Fach 5', $ergebnis['gruende'][0]);
    }

    public function test_schwache_vorgezogene_pruefungen_allein_brechen_nicht_ab(): void
    {
        // 1 + 1 Punkt sind wenig, aber aus den beiden ausstehenden Fächern
        // sind noch 120 Punkte erreichbar — die 80er-Schwelle bleibt offen.
        $ergebnis = $this->service->abbruchNachVorgezogenenMuendlichen([
            ['position' => 7, 'punkte' => 1],
            ['position' => 8, 'punkte' => 1],
        ]);

        $this->assertFalse($ergebnis['abbruch']);
        $this->assertSame([], $ergebnis['gruende']);
    }

    public function test_vorgezogen_werden_koennen_nur_faecher_des_zweiten_teils(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->abbruchNachVorgezogenenMuendlichen([
            ['position' => 4, 'punkte' => 10],
            ['position' => 5, 'punkte' => 10],
        ]);
    }

    public function test_es_koennen_nur_zwei_pruefungen_vorgezogen_werden(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->abbruchNachVorgezogenenMuendlichen([
            ['position' => 5, 'punkte' => 10],
            ['position' => 6, 'punkte' => 10],
            ['position' => 7, 'punkte' => 10],
        ]);
    }

    /**
     * Der Zwischenstand zählt mit (Entscheidung Wilfrieds, 31.08.2026): Steht
     * erst eine der beiden vorgezogenen Prüfungen und schließt sie mit 0 ab,
     * wird schon gemeldet. Auf die zweite Note zu warten ändert nichts — eine
     * 0 ist durch kein späteres Ergebnis mehr zu heilen.
     */
    public function test_eine_einzelne_vorgezogene_pruefung_mit_null_bricht_schon_ab(): void
    {
        $ergebnis = $this->service->abbruchNachVorgezogenenMuendlichen([
            ['position' => 6, 'punkte' => 0],
        ]);

        $this->assertTrue($ergebnis['abbruch']);
        $this->assertStringContainsString('Fach 6', $ergebnis['gruende'][0]);
    }

    /**
     * Gegenprobe zum Zwischenstand: Eine noch unbenotete Prüfung ist kein
     * Abbruchgrund. Ohne diese Zusicherung meldete die Planung den Abbruch,
     * sobald ein Termin steht.
     */
    public function test_eine_unbenotete_vorgezogene_pruefung_bricht_nicht_ab(): void
    {
        $ergebnis = $this->service->abbruchNachVorgezogenenMuendlichen([
            ['position' => 5, 'punkte' => null],
        ]);

        $this->assertFalse($ergebnis['abbruch']);
        $this->assertSame([], $ergebnis['gruende']);
    }

    /** Und gar keine vorgezogene Prüfung ist erst recht keiner. */
    public function test_ohne_vorgezogene_pruefung_gibt_es_keinen_abbruchgrund(): void
    {
        $ergebnis = $this->service->abbruchNachVorgezogenenMuendlichen([]);

        $this->assertFalse($ergebnis['abbruch']);
    }

    // ── G: Ersatzschüler nach § 64 Abs. 2 / Anlage 15 ───────────────────────

    /**
     * @param list<int|null> $punkte Positionen 1–6
     * @param array{schulaufgabe: int, kleine_leistungsnachweise: list<int>} $einbringung
     * @return list<array<string, mixed>>
     */
    private function anlage15Faecher(array $punkte, array $einbringung): array
    {
        $faecher = $this->faecher(array_merge($punkte, [null, null]));

        foreach (ExterneGesamtqualifikationService::EINGEBRACHTE_POSITIONEN as $position) {
            $faecher[$position - 1] = [
                'position' => $position,
                'fach' => 'Fach ' . $position,
                'ea' => false,
                'einbringung' => $einbringung,
            ];
        }

        return $faecher;
    }

    public function test_anlage_15_kommt_ebenfalls_auf_900_punkte(): void
    {
        $ergebnis = $this->service->berechne(
            $this->anlage15Faecher(
                [15, 15, 15, 15, 15, 15],
                ['schulaufgabe' => 15, 'kleine_leistungsnachweise' => [15, 15]]
            ),
            ExterneGesamtqualifikationService::SCHEMA_ANLAGE_15
        );

        $this->assertSame(900, $ergebnis['gesamtqualifikation']);
        $this->assertSame(240, $ergebnis['zweiter_teil']['summe']);
        $this->assertSame(
            ExterneGesamtqualifikationService::QUELLE_EINBRINGUNG,
            $ergebnis['faecher'][6]['quelle']
        );
        $this->assertSame(60, $ergebnis['faecher'][6]['gesamtpunktzahl']);
        $this->assertTrue($ergebnis['bestanden']);
    }

    public function test_eingebrachte_faecher_zaehlen_fuer_paragraf_62_absatz_2_mit(): void
    {
        // § 64 Abs. 2 S. 1 ersetzt die mündliche Prüfung, nicht das Fach: es
        // bleiben vier Fächer des zweiten Teils, und Abs. 2 gilt über alle vier.
        $ergebnis = $this->service->berechne(
            $this->anlage15Faecher(
                [15, 15, 15, 15, 15, 15],
                ['schulaufgabe' => 0, 'kleine_leistungsnachweise' => [0, 0]]
            ),
            ExterneGesamtqualifikationService::SCHEMA_ANLAGE_15
        );

        $this->assertFalse($ergebnis['zweiter_teil']['bestanden']);
        $this->assertStringContainsString(
            'mit 0 Punkten',
            implode(' ', $ergebnis['zweiter_teil']['gruende'])
        );
    }

    // ── G9: § 64 Abs. 2 Satz 4 ──────────────────────────────────────────────

    public function test_eingebrachtes_ergebnis_nach_satz_4(): void
    {
        // "Durchschnitt aus der doppelt gewichteten Punktzahl der Schulaufgabe
        // und dem Durchschnitt der kleinen Leistungsnachweise."
        // (2 × 10 + Ø[8, 12]) / 3 = (20 + 10) / 3 = 10
        $this->assertSame(10, $this->service->eingebrachtesErgebnis(10, [8, 12]));

        // (2 × 11 + Ø[6, 7, 8]) / 3 = (22 + 7) / 3 = 9,67 → 10
        $this->assertSame(10, $this->service->eingebrachtesErgebnis(11, [6, 7, 8]));

        // (2 × 4 + Ø[10, 10]) / 3 = (8 + 10) / 3 = 6
        $this->assertSame(6, $this->service->eingebrachtesErgebnis(4, [10, 10]));
    }

    public function test_eingebrachte_punktzahl_rundet_erst_nach_der_multiplikation(): void
    {
        // Entscheidung Wilfried, 26.08.2026: erst mit 4 multiplizieren, dann
        // runden — wie bei Anlage 12.
        // (2 × 11 + Ø[6, 7, 8]) / 3 = 9,667
        //   erst runden, dann × 4  →  10 × 4      = 40  (falsch)
        //   erst × 4, dann runden  →  round(38,67) = 39  (richtig)
        $this->assertSame(39, $this->service->eingebrachtePunktzahl(11, [6, 7, 8]));

        // Die einfache Wertung für § 62 Abs. 2 wird davon unabhängig gerundet.
        $this->assertSame(10, $this->service->eingebrachtesErgebnis(11, [6, 7, 8]));
    }

    public function test_die_rundung_schlaegt_bis_in_die_gesamtqualifikation_durch(): void
    {
        $ergebnis = $this->service->berechne(
            $this->anlage15Faecher(
                [15, 15, 15, 15, 15, 15],
                ['schulaufgabe' => 11, 'kleine_leistungsnachweise' => [6, 7, 8]]
            ),
            ExterneGesamtqualifikationService::SCHEMA_ANLAGE_15
        );

        $this->assertSame(39, $ergebnis['faecher'][6]['gesamtpunktzahl']);
        $this->assertSame(10, $ergebnis['faecher'][6]['einfache_wertung']);
        // 60 + 60 + 39 + 39
        $this->assertSame(198, $ergebnis['zweiter_teil']['summe']);
        $this->assertSame(858, $ergebnis['gesamtqualifikation']);
    }

    public function test_die_schulaufgabe_zaehlt_doppelt(): void
    {
        // Ein und dieselben Zahlen, vertauscht: die Schulaufgabe schlägt
        // stärker durch als die kleinen Leistungsnachweise.
        $this->assertSame(11, $this->service->eingebrachtesErgebnis(12, [9, 9]));
        $this->assertSame(10, $this->service->eingebrachtesErgebnis(9, [12, 12]));
    }

    public function test_satz_2_verlangt_mindestens_zwei_kleine_leistungsnachweise(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/§ 64 Abs\. 2 S\. 2/');

        $this->service->eingebrachtesErgebnis(10, [10]);
    }

    // ── Eingabeprüfungen ────────────────────────────────────────────────────

    public function test_es_muessen_acht_pruefungsfaecher_sein(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/acht Prüfungsfächer/');

        $this->service->berechne($this->faecher([15, 15, 15, 15, 15, 15, 15]));
    }

    public function test_jede_position_genau_einmal(): void
    {
        $faecher = $this->faecher([15, 15, 15, 15, 15, 15, 15, 15]);
        $faecher[7]['position'] = 7;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Positionen 1 bis 8 genau einmal/');

        $this->service->berechne($faecher);
    }

    public function test_ein_eingebrachtes_ergebnis_gibt_es_nur_bei_anlage_15(): void
    {
        $faecher = $this->anlage15Faecher(
            [15, 15, 15, 15, 15, 15],
            ['schulaufgabe' => 12, 'kleine_leistungsnachweise' => [12, 12]]
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nur bei Anlage 15/');

        $this->service->berechne($faecher, ExterneGesamtqualifikationService::SCHEMA_ANLAGE_14);
    }

    public function test_eingebracht_werden_nur_die_faecher_sieben_und_acht(): void
    {
        $faecher = $this->faecher([15, 15, 15, 15, null, 15, 15, 15]);
        $faecher[4] = [
            'position' => 5,
            'fach' => 'Fach 5',
            'ea' => false,
            'einbringung' => ['schulaufgabe' => 12, 'kleine_leistungsnachweise' => [12, 12]],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Position 5/');

        $this->service->berechne($faecher, ExterneGesamtqualifikationService::SCHEMA_ANLAGE_15);
    }

    public function test_zusatzpruefungen_gibt_es_nur_im_ersten_pruefungsteil(): void
    {
        // § 61 Abs. 1 S. 3: zusätzlich mündlich geprüft werden die Fächer des
        // ersten Prüfungsteils. Die vier Fächer des zweiten Teils sind bereits
        // mündliche Prüfungen.
        $faecher = $this->faecher([15, 15, 15, 15, null, 15, 15, 15]);
        $faecher[4] = [
            'position' => 5,
            'fach' => 'Fach 5',
            'ea' => false,
            'zusatzpruefung' => ['schriftlich' => 10, 'muendlich' => 10],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ersten\s+Prüfungsteils/');

        $this->service->berechne($faecher);
    }

    public function test_punkte_ausserhalb_von_null_bis_fuenfzehn_werden_abgewiesen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->berechne($this->faecher([16, 15, 15, 15, 15, 15, 15, 15]));
    }
}
