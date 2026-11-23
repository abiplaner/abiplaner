<?php

namespace Tests\Unit\Services;

use App\Services\ExterneGesamtqualifikationService;
use App\Services\ExterneMindestpunkteService;
use PHPUnit\Framework\TestCase;

/**
 * § 62 GSO — die Umkehrung: welche Punktzahl fehlt noch zum Bestehen?
 * Reine Logiktests ohne Datenbank.
 *
 * Grundlage sind die von Wilfried vorgelegten Wortlaute von §§ 61, 62, 64 und
 * der Anlagen 12, 14 und 15; die Regel-IDs verweisen auf
 * docs/externe-bewerber/REGELWERK-61-62.md.
 *
 * Ausführen: docker compose exec abiplaner_app php vendor/bin/phpunit --filter ExterneMindestpunkte
 */
class ExterneMindestpunkteServiceTest extends TestCase
{
    private ExterneMindestpunkteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExterneMindestpunkteService(new ExterneGesamtqualifikationService());
    }

    /**
     * Acht Prüfungsfächer mit den Positionen 1–8.
     *
     * Ein Eintrag ist entweder eine Punktzahl (einfache Wertung), null für
     * „noch offen" oder ein Array [schriftlich, muendlich] für ein Fach mit
     * mündlicher Zusatzprüfung; muendlich darf null sein — das ist die Größe,
     * nach der aufgelöst wird.
     *
     * @param list<int|null|array{0: int, 1: int|null}> $werte
     * @param list<int> $eaPositionen
     * @return list<array<string, mixed>>
     */
    private function faecher(array $werte, array $eaPositionen = [1, 2, 3]): array
    {
        $faecher = [];

        foreach ($werte as $index => $wert) {
            $fach = [
                'position' => $index + 1,
                'fach' => 'Fach ' . ($index + 1),
                'ea' => in_array($index + 1, $eaPositionen, true),
                'punkte' => is_array($wert) ? null : $wert,
            ];

            if (is_array($wert)) {
                $fach['zusatzpruefung'] = ['schriftlich' => $wert[0], 'muendlich' => $wert[1]];
            }

            $faecher[] = $fach;
        }

        return $faecher;
    }


    // ── Ohne offene Zusatzprüfung gibt es nichts aufzulösen ─────────────────

    public function test_ohne_offene_zusatzpruefung_keine_tabelle(): void
    {
        $ergebnis = $this->service->berechne($this->faecher([10, 10, 10, 10, 10, 10, 10, 10]));

        $this->assertSame([], $ergebnis['erster_teil']['offene']);
        $this->assertNull($ergebnis['erster_teil']['kombinationen']);
        $this->assertSame([], $ergebnis['erster_teil']['top5']);
        $this->assertTrue($ergebnis['rechnung']['bestanden']);
    }

    // ── E4/E5: eine offene Zusatzprüfung ────────────────────────────────────

    public function test_eine_offene_zusatzpruefung_ergibt_sechzehn_zeilen(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([5, 5, [2, null], 5, 3, 4, null, null])
        );

        $this->assertCount(1, $ergebnis['erster_teil']['offene']);
        $this->assertSame(3, $ergebnis['erster_teil']['offene'][0]['position']);
        $this->assertSame(2, $ergebnis['erster_teil']['offene'][0]['schriftlich']);
        $this->assertCount(16, $ergebnis['erster_teil']['kombinationen']);
    }

    public function test_grenze_liegt_bei_elf_punkten_muendlich(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([5, 5, [2, null], 5, 3, 4, null, null])
        );

        $zeilen = $ergebnis['erster_teil']['kombinationen'];

        // Schriftlich 2, die drei anderen Fächer je 5 Punkte = 165 Punkte.
        // round((2 × 2 + 11) × 11 / 3) = 55 → 220, die Schwelle auf den Punkt.
        $this->assertFalse($zeilen[10]['bestanden']);
        $this->assertSame(216, $zeilen[10]['summe']);
        $this->assertTrue($zeilen[11]['bestanden']);
        $this->assertSame(220, $zeilen[11]['summe']);
        $this->assertSame(5, $ergebnis['erster_teil']['bestehende']);
    }

    /**
     * Der Kern des ganzen Rechenwegs: die Kurzformel „zwanzig Punkte einfacher
     * Wertung" gilt nur für Fächer ohne Zusatzprüfung.
     *
     * Bei elf Punkten mündlich und zwei schriftlich steht in allen vier Fächern
     * des ersten Teils die einfache Wertung 5 — zusammen also 20, was nach der
     * Kurzformel reichen würde. Die tatsächliche Summe beträgt aber 216, weil
     * Anlage 12 S. 4 erst am Endergebnis rundet. Deshalb muss der erste Teil
     * aufgezählt und darf nicht überschlagen werden.
     */
    public function test_einfache_wertung_zwanzig_reicht_bei_zusatzpruefung_nicht(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([5, 5, [2, null], 5, 3, 4, null, null])
        );

        $zeile = $ergebnis['erster_teil']['kombinationen'][10];

        $this->assertSame(5, $zeile['faecher'][0]['einfache_wertung']);
        $this->assertSame(51, $zeile['faecher'][0]['gesamtpunktzahl']);
        $this->assertSame(216, $zeile['summe']);
        $this->assertFalse($zeile['bestanden']);
        // 5 × 11 wären 55 gewesen, gerechnet werden 51.
        $this->assertNotSame(55, $zeile['faecher'][0]['gesamtpunktzahl']);
    }

    // ── Anlage 12 S. 6: harter Ausschluss ───────────────────────────────────

    /**
     * Anlage 12 S. 6 — unter elf Punkten elffacher Wertung ist die
     * Abiturprüfung nicht bestanden. Die Zeile fällt hart durch, sie wird nicht
     * bloß markiert (Entscheidung Wilfried, 26.08.2026).
     *
     * Bei schriftlich 1 und mündlich 0 ist 2s + m = 2: die Gesamtpunktzahl
     * beträgt round(22 / 3) = 7, die einfache Wertung round(2/3) → unter 1,
     * also 0. Beide Gründe fallen hier zusammen und werden getrennt benannt,
     * damit in der Niederschrift die zutreffende Norm steht.
     */
    public function test_anlage_12_satz_6_schliesst_die_zeile_hart_aus(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([15, 15, [1, null], 15, 15, 15, 15, 15])
        );

        $zeile = $ergebnis['erster_teil']['kombinationen'][0];

        $this->assertSame(7, $zeile['faecher'][0]['gesamtpunktzahl']);
        $this->assertSame(0, $zeile['faecher'][0]['einfache_wertung']);
        $this->assertFalse($zeile['bestanden']);

        $gruende = implode(' | ', $zeile['gruende']);
        $this->assertStringContainsString('Anlage 12 S. 6', $gruende);
        $this->assertStringContainsString('0 Punkten abgeschlossen', $gruende);
    }

    /**
     * Die Summe allein hätte die Zeile getragen: 3 × 165 = 495 plus 7 sind 502
     * und damit weit über 220. Ausgeschlossen wird sie allein über die beiden
     * Fachbedingungen.
     */
    public function test_hoher_gesamtwert_rettet_die_ausgeschlossene_zeile_nicht(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([15, 15, [1, null], 15, 15, 15, 15, 15])
        );

        $zeile = $ergebnis['erster_teil']['kombinationen'][0];

        $this->assertSame(502, $zeile['summe']);
        $this->assertFalse($zeile['bestanden']);
    }

    // ── Zwei offene Zusatzprüfungen ─────────────────────────────────────────

    public function test_zwei_offene_liefern_je_note_die_kleinste_zweite(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([[5, null], [5, null], 5, 5, 10, 10, 10, 10])
        );

        $zeilen = $ergebnis['erster_teil']['kombinationen'];

        $this->assertCount(16, $zeilen);
        $this->assertCount(2, $ergebnis['erster_teil']['offene']);

        // Fächer 3 und 4 tragen 110 Punkte; die beiden Zusatzprüfungen müssen
        // zusammen 110 erreichen. Bei mündlich 0 im ersten Fach sind das 37,
        // im zweiten fehlen dann 73 — erreicht ab mündlich 10.
        $this->assertSame(0, $zeilen[0]['faecher'][0]['muendlich']);
        $this->assertSame(10, $zeilen[0]['faecher'][1]['muendlich']);
        $this->assertSame(220, $zeilen[0]['summe']);
        $this->assertTrue($zeilen[0]['bestanden']);
    }

    // ── Drei und vier offene Zusatzprüfungen ────────────────────────────────

    public function test_drei_offene_liefern_nur_bestehende_zeilen(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([[8, null], [8, null], [8, null], 10, 10, 10, 10, 10])
        );

        $this->assertCount(3, $ergebnis['erster_teil']['offene']);
        $this->assertNotSame([], $ergebnis['erster_teil']['kombinationen']);

        foreach ($ergebnis['erster_teil']['kombinationen'] as $zeile) {
            $this->assertTrue($zeile['bestanden']);
        }
    }

    public function test_top5_liefert_hoechstens_fuenf_zeilen(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([[8, null], [8, null], [8, null], 10, 10, 10, 10, 10])
        );

        $this->assertLessThanOrEqual(5, count($ergebnis['erster_teil']['top5']));
        $this->assertNotSame([], $ergebnis['erster_teil']['top5']);
    }

    /**
     * Bei einer einzigen offenen Zusatzprüfung genügt eine Zeile: jede höhere
     * Note besteht ebenfalls und sagt nichts Neues.
     */
    public function test_top5_bei_einer_offenen_ist_die_mindestnote(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([5, 5, [2, null], 5, 3, 4, null, null])
        );

        $this->assertCount(1, $ergebnis['erster_teil']['top5']);
        $this->assertSame(11, $ergebnis['erster_teil']['top5'][0]['faecher'][0]['muendlich']);
    }

    /**
     * Vier offene Zusatzprüfungen sind rechtlich möglich — alle vier Fächer des
     * ersten Teils können zusätzlich mündlich geprüft werden (§ 61 Abs. 1
     * S. 3). Eine Tabelle über vier freie Größen hätte 65 536 Zeilen; der Fall
     * wird ausgewiesen statt still gekappt.
     */
    public function test_vier_offene_bekommen_keine_tabelle_sondern_einen_hinweis(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([[8, null], [8, null], [8, null], [8, null], 10, 10, 10, 10])
        );

        $this->assertTrue($ergebnis['erster_teil']['zu_viele_offene']);
        $this->assertNull($ergebnis['erster_teil']['kombinationen']);
        $this->assertCount(4, $ergebnis['erster_teil']['offene']);
    }

    // ── Unvollständige Eingaben ─────────────────────────────────────────────

    /**
     * Fehlt in einem Fach des ersten Teils die Note, ist die Frage nach der
     * Mindestnote noch gar nicht gestellt. Gegenstück zu
     * MindestpunkteService::allFixedScoresEntered().
     */
    public function test_ohne_vollstaendige_schriftliche_noten_keine_tabelle(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([5, 5, [2, null], null, 3, 4, null, null])
        );

        $this->assertFalse($ergebnis['erster_teil']['eingaben_vollstaendig']);
        $this->assertNull($ergebnis['erster_teil']['kombinationen']);
    }

    /**
     * Eine beantragte Zusatzprüfung, zu der die schriftliche Note noch fehlt,
     * ist keine Rechengrundlage. Sie darf nicht als 0 durchschlagen — sonst
     * stünde das Fach mit 0 Punkten in der Rechnung und löste einen Abbruch
     * aus, den es gar nicht gibt.
     */
    public function test_zusatzpruefung_ohne_schriftliche_note_zaehlt_als_unbewertet(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([[null, null], 10, 10, 10, 10, 10, 10, 10])
        );

        $this->assertNull($ergebnis['abbruch']);
        $this->assertFalse($ergebnis['erster_teil']['eingaben_vollstaendig']);
        $this->assertNull($ergebnis['erster_teil']['kombinationen']);
        $this->assertSame([], $ergebnis['erster_teil']['offene']);
        $this->assertNull($ergebnis['rechnung']['faecher'][0]['gesamtpunktzahl']);
        $this->assertNull($ergebnis['rechnung']['erster_teil']['bestanden']);
    }

    // ── E6: Abbruch nach dem ersten Prüfungsteil ────────────────────────────

    /**
     * § 62 Abs. 1 S. 4: steht der Abbruch fest, findet der zweite Teil nicht
     * mehr statt — und wird deshalb auch nicht ausgewiesen (Entscheidung
     * Wilfried, 26.08.2026).
     */
    public function test_abbruch_nach_erstem_teil_unterdrueckt_den_zweiten(): void
    {
        $ergebnis = $this->service->berechne($this->faecher([0, 10, 10, 10, 10, 10, 10, 10]));

        $this->assertSame(
            ExterneGesamtqualifikationService::ABBRUCH_NACH_ERSTEM_TEIL,
            $ergebnis['abbruch']
        );
        $this->assertNull($ergebnis['zweiter_teil']);
    }

    /**
     * Eine 0 in einem Fach mit noch offener Zusatzprüfung ist keine 0 — die
     * einfache Wertung entsteht dort erst aus (2s + m) / 3. Das Fach zählt als
     * offen, nicht als abgeschlossen, und löst keinen Abbruch aus.
     */
    public function test_offene_zusatzpruefung_loest_keinen_abbruch_aus(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([[0, null], 15, 15, 15, 15, 15, 15, 15])
        );

        $this->assertNull($ergebnis['abbruch']);
        $this->assertNotNull($ergebnis['zweiter_teil']);
        $this->assertNull($ergebnis['rechnung']['erster_teil']['bestanden']);
    }

    // ── E7: zweiter Prüfungsteil als Restbedarf ─────────────────────────────

    public function test_zweiter_teil_weist_den_restbedarf_aus(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([10, 10, 10, 10, 3, 4, null, null])
        );

        $zweiter = $ergebnis['zweiter_teil'];

        $this->assertCount(2, $zweiter['offene']);
        $this->assertSame(28, $zweiter['erreicht']);
        $this->assertSame(7, $zweiter['erreichte_wertung']);
        $this->assertSame(52, $zweiter['noch_noetige_punkte']);
        $this->assertSame(13, $zweiter['noch_noetige_wertung']);
        $this->assertSame(2, $zweiter['noch_faecher_mit_fuenf']);
        $this->assertSame(13, $zweiter['mindestbelegung']);
        $this->assertTrue($zweiter['erreichbar']);
    }

    /**
     * Beide Prüfungsteile verlangen zwanzig Punkte einfacher Wertung über ihre
     * vier Fächer: 220 / 11 = 20 und 80 / 4 = 20. Für den zweiten Teil gilt das
     * exakt, weil es dort keine Zusatzprüfungen und damit keine Rundung gibt.
     */
    public function test_zweiter_teil_verlangt_zwanzig_punkte_einfacher_wertung(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([10, 10, 10, 10, 5, 5, 5, 5])
        );

        $zweiter = $ergebnis['zweiter_teil'];

        $this->assertSame(20, $zweiter['erreichte_wertung']);
        $this->assertSame(80, $zweiter['erreicht']);
        $this->assertSame(0, $zweiter['noch_noetige_punkte']);
        $this->assertTrue($zweiter['erreichbar']);
        $this->assertTrue($ergebnis['rechnung']['zweiter_teil']['bestanden']);
    }

    /**
     * Ein einziges offenes Fach kann nicht zwei Fächer mit mindestens fünf
     * Punkten liefern — die Bedingung ist dann nicht mehr erfüllbar, auch wenn
     * die Punktsumme noch reichen würde.
     */
    public function test_zwei_faecher_mit_fuenf_sind_mit_einem_offenen_nicht_zu_schaffen(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([10, 10, 10, 10, 4, 4, 4, null])
        );

        $zweiter = $ergebnis['zweiter_teil'];

        $this->assertSame(2, $zweiter['noch_faecher_mit_fuenf']);
        $this->assertCount(1, $zweiter['offene']);
        // Punktzahl allein wäre erreichbar: 48 stehen, 32 fehlen, 15 × 4 = 60.
        $this->assertSame(32, $zweiter['noch_noetige_punkte']);
        $this->assertFalse($zweiter['erreichbar']);
    }

    /**
     * Reicht auch die volle Punktzahl in allen offenen Fächern nicht mehr an
     * die Schwelle heran, ist der zweite Teil verloren.
     */
    public function test_unerreichbare_schwelle_wird_als_solche_ausgewiesen(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([10, 10, 10, 10, 1, 1, 1, null])
        );

        $zweiter = $ergebnis['zweiter_teil'];

        $this->assertSame(68, $zweiter['noch_noetige_punkte']);
        $this->assertSame(17, $zweiter['noch_noetige_wertung']);
        $this->assertFalse($zweiter['erreichbar']);
    }

    /**
     * Ein bereits mit 0 Punkten abgeschlossenes Fach des zweiten Teils ist
     * durch kein späteres Ergebnis mehr zu heilen.
     */
    public function test_null_punkte_im_zweiten_teil_sind_endgueltig(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([10, 10, 10, 10, 0, 15, 15, null])
        );

        $zweiter = $ergebnis['zweiter_teil'];

        $this->assertFalse($zweiter['erreichbar']);
        $this->assertNotSame([], $zweiter['gruende']);
        $this->assertStringContainsString('0 Punkten abgeschlossen', implode(' | ', $zweiter['gruende']));
    }

    public function test_vollstaendig_bestandener_zweiter_teil_ist_erreichbar(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([10, 10, 10, 10, 10, 10, 10, 10])
        );

        $this->assertSame([], $ergebnis['zweiter_teil']['offene']);
        $this->assertTrue($ergebnis['zweiter_teil']['erreichbar']);
    }

    // ── Die Ist-Rechnung wird unverändert durchgereicht ─────────────────────

    public function test_die_ist_rechnung_bleibt_erhalten(): void
    {
        $ergebnis = $this->service->berechne(
            $this->faecher([5, 5, [2, null], 5, 3, 4, null, null])
        );

        $this->assertSame(ExterneGesamtqualifikationService::SCHEMA_ANLAGE_14, $ergebnis['schema']);
        $this->assertArrayHasKey('faecher', $ergebnis['rechnung']);
        $this->assertCount(8, $ergebnis['rechnung']['faecher']);
        // Das Fach mit offener Zusatzprüfung zählt als noch nicht bewertet —
        // nicht etwa mit der schriftlichen Note als Untergrenze.
        $this->assertNull($ergebnis['rechnung']['faecher'][2]['gesamtpunktzahl']);
        $this->assertFalse($ergebnis['rechnung']['vollstaendig']);
    }
}
