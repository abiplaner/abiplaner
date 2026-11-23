<?php

namespace Tests\Unit\Services;

use App\Services\MindestpunkteService;
use PHPUnit\Framework\TestCase;

/**
 * Prüft die 5 GSO-Anforderungen (§61 / Anlage 12 zu §52) einzeln und in
 * Kombination, jeweils mit einem Fall der das Kriterium gerade erfüllt
 * und einem der es gerade NICHT erfüllt (Grenzfälle, inkl. der
 * Rundungs-Fallstricke bei ZP-Fächern: ×4-Beitrag != gerundete Note × 4).
 *
 * Ausführen: docker compose exec abiplaner_app php vendor/bin/phpunit --filter Mindestpunkte
 */
class MindestpunkteCriteriaTest extends TestCase
{
    private MindestpunkteService $svc;

    protected function setUp(): void
    {
        $this->svc = new MindestpunkteService();
    }

    /** Alle 5 Fächer auf 20 (Note 5) → alle Kriterien klar erfüllt. */
    public function test_happy_path_alle_kriterien_erfuellt(): void
    {
        $exams = MindestpunkteFaelle::standardFix([20, 20, 20, 20, 20]);
        $result = $this->svc->compute($exams);

        $this->assertTrue($result['pass'], 'Gesamtergebnis sollte bestanden sein');
        $this->assertSame(100, $result['total']);
        foreach (['each_ge_1', 'total_ge_100', 'three_ge_5', 'dreierblock', 'aufgabenfeld'] as $key) {
            $this->assertTrue($result['criteria'][$key]['pass'], "Kriterium {$key} sollte bestanden sein");
        }
    }

    // ─── Kriterium 1: Jedes Fach ≥ 1 Pkt. (= 4 Pkt. ×4) ──────────────

    public function test_kriterium1_zp_gerade_noch_bestanden_4er_beitrag(): void
    {
        // written=1, target=1 -> round((2+1)*4/3) = round(4) = 4 (genau die Schwelle)
        $this->assertSame(4, MindestpunkteFaelle::contribution(1, 1));
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(1, 1),
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['each_ge_1']['pass']);
        $this->assertSame(4, $result['criteria']['each_ge_1']['actual']);
    }

    public function test_kriterium1_zp_rundungsfalle_beitrag_unter_4(): void
    {
        // written=1, target=0 -> round((2+0)*4/3) = round(2.67) = 3 (< 4!)
        $this->assertSame(3, MindestpunkteFaelle::contribution(1, 0));
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(1, 0),
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['each_ge_1']['pass']);
        $this->assertSame(3, $result['criteria']['each_ge_1']['actual']);
    }

    // ─── Kriterium 2: Σ Abiturprüfung ≥ 100 ──────────────────────────

    public function test_kriterium2_gerade_noch_bestanden_100(): void
    {
        // D=20, M=20, Sport=20 (Summe 60) + Politik(fix)=28 + Französisch-ZP(3,3)=12 -> 40 -> Gesamt 100
        $this->assertSame(12, MindestpunkteFaelle::contribution(3, 3));
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischZp(3, 3), // Beitrag 12
            MindestpunkteFaelle::politik(28),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertSame(100, $result['criteria']['total_ge_100']['actual']);
        $this->assertTrue($result['criteria']['total_ge_100']['pass']);
    }

    public function test_kriterium2_knapp_nicht_bestanden_99(): void
    {
        // D=20, M=20, Sport=20 (Summe 60) + Politik(fix)=24 + Französisch-ZP(3,5)=15 -> 39 -> Gesamt 99
        $this->assertSame(15, MindestpunkteFaelle::contribution(3, 5));
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischZp(3, 5), // Beitrag 15
            MindestpunkteFaelle::politik(24),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertSame(99, $result['criteria']['total_ge_100']['actual']);
        $this->assertFalse($result['criteria']['total_ge_100']['pass']);
    }

    // ─── Kriterium 3: Mind. 3 Fächer ≥ 20 (×4), inkl. D/M/LF ─────────

    public function test_kriterium3_rundungsfalle_beitrag_19_reicht_nicht(): void
    {
        // written=3, target=8 -> round((6+8)*4/3) = round(18.67) = 19 (< 20!)
        // calcCombined(3,8) würde round(14/3)=round(4.67)=5 ergeben (Note 5,
        // würde fälschlich als "≥5" durchgehen ohne den ×4-Fix)
        $this->assertSame(19, MindestpunkteFaelle::contribution(3, 8));
        $exams = [
            MindestpunkteFaelle::deutsch(4),               // Note 1, < 20
            MindestpunkteFaelle::mathematikZp(3, 8),        // Beitrag 19, < 20
            MindestpunkteFaelle::franzoesischFix(20),       // ≥ 20
            MindestpunkteFaelle::politik(20),               // ≥ 20
            MindestpunkteFaelle::sportLf(42),               // ≥ 20, LF
        ];
        $result = $this->svc->compute($exams);
        // Nur 3 Fächer ≥20 (Franz., Politik, Sport) -> exakt 3, sollte bestehen
        $this->assertTrue($result['criteria']['three_ge_5']['pass']);
        $this->assertSame(3, $result['criteria']['three_ge_5']['actual']);
    }

    public function test_kriterium3_rundungsfalle_beitrag_19_fehlt_dann_doch(): void
    {
        // Gleicher Fall, aber Politik jetzt auch unter 20 -> nur noch 2 Fächer ≥20
        $exams = [
            MindestpunkteFaelle::deutsch(4),
            MindestpunkteFaelle::mathematikZp(3, 8),  // Beitrag 19, < 20
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(16),         // jetzt auch < 20
            MindestpunkteFaelle::sportLf(42),
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['three_ge_5']['pass']);
        $this->assertSame(2, $result['criteria']['three_ge_5']['actual']);
    }

    public function test_kriterium3_fehlt_d_m_lf_unter_den_3(): void
    {
        // 3 Fächer ≥20, aber keines davon ist D/M/LF (nur Politik allein zu wenig)
        $exams = [
            MindestpunkteFaelle::deutsch(4),     // < 20
            MindestpunkteFaelle::mathematikFix(4), // < 20
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::fix(5, 'Kunst', 20, ['aufgabenfeld' => 'sprachlich']), // kein LF
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['three_ge_5']['pass']);
        $this->assertSame('Kein D/M/LF unter den Fächern mit ≥ 5 Pkt.', $result['criteria']['three_ge_5']['detail']);
    }

    // ─── Kriterium 4: Dreierblock D+M+FS/NW Σ×4 ≥ 40, max. 1× < 16 ───

    public function test_kriterium4_gerade_noch_bestanden_summe_40(): void
    {
        // D=16, M=16, Französisch(FS)=8 (fix) -> Summe 40, alle ≥16 außer keiner <16
        $exams = [
            MindestpunkteFaelle::deutsch(16),
            MindestpunkteFaelle::mathematikFix(16),
            MindestpunkteFaelle::franzoesischFix(8), // < 16, aber nur 1x erlaubt
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        // Summe D+M+FS = 16+16+8 = 40, genau die Schwelle; 1x <16 (Französisch) erlaubt
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['dreierblock']['pass']);
        $this->assertSame(40, $result['criteria']['dreierblock']['sum4']);
    }

    public function test_kriterium4_knapp_nicht_bestanden_summe_39(): void
    {
        // written=1, target=3 -> round((2+3)*4/3) = round(6.67) = 7
        $this->assertSame(7, MindestpunkteFaelle::contribution(1, 3));
        $exams = [
            MindestpunkteFaelle::deutsch(16),
            MindestpunkteFaelle::mathematikFix(16),
            MindestpunkteFaelle::franzoesischZp(1, 3), // Beitrag 7, Summe D+M+F = 39
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['dreierblock']['pass']);
    }

    public function test_kriterium4_rundungsfalle_zp_beitrag_15_statt_16(): void
    {
        // written=3, target=5 -> round((6+5)*4/3) = round(14.67) = 15 (< 16!)
        // Zusammen mit einem zweiten Fach <16 im Dreierblock darf das Kriterium
        // nicht mehr bestehen (max. 1x <16 erlaubt).
        $this->assertSame(15, MindestpunkteFaelle::contribution(3, 5));
        $exams = [
            MindestpunkteFaelle::deutsch(16),           // genau 16, nicht <16
            MindestpunkteFaelle::mathematikFix(16),
            MindestpunkteFaelle::franzoesischZp(3, 5),  // Beitrag 15, < 16
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        // Summe = 16+16+15 = 47 ≥ 40, aber 1x <16 (Französisch) -> noch erlaubt
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['dreierblock']['pass']);
    }

    public function test_kriterium4_zwei_unter_16_im_dreierblock_nicht_bestanden(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(12),           // < 16 (1.)
            MindestpunkteFaelle::mathematikFix(16),
            MindestpunkteFaelle::franzoesischZp(3, 5),  // Beitrag 15, < 16 (2.)
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['dreierblock']['pass']);
    }

    // ─── Kriterium 5: Max. 1× < 16 (×4) pro Aufgabenfeld ─────────────

    public function test_kriterium5_rundungsfalle_franzoesisch_braucht_6_nicht_5(): void
    {
        // Deutsch (SLK) bereits <16 verbraucht die eine Ausnahme im Aufgabenfeld
        // SLK. Französisch (auch SLK) muss daher selbst ≥16 sein.
        // written=3, target=5 -> Beitrag 15 (< 16) => Kriterium muss failen.
        $exams = [
            MindestpunkteFaelle::deutsch(4),            // SLK, Note 1, < 16
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischZp(3, 5),  // SLK, Beitrag 15, < 16
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['aufgabenfeld']['pass']);
        $this->assertStringContainsString('SLK', $result['criteria']['aufgabenfeld']['detail']);
    }

    public function test_kriterium5_mit_6_punkten_muendlich_gerade_bestanden(): void
    {
        // written=3, target=6 -> round((6+6)*4/3) = round(16) = 16 (genau die Schwelle)
        $this->assertSame(16, MindestpunkteFaelle::contribution(3, 6));
        $exams = [
            MindestpunkteFaelle::deutsch(4),            // SLK, < 16 (die eine erlaubte Ausnahme)
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischZp(3, 6),  // SLK, Beitrag genau 16
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['aufgabenfeld']['pass']);
    }

    public function test_kriterium5_zwei_verschiedene_aufgabenfelder_je_1x_unter_16_ok(): void
    {
        // Deutsch (SLK) <16 und Mathematik (MNT) <16 -> verschiedene Aufgabenfelder,
        // jeweils nur 1x betroffen -> sollte bestehen.
        $exams = [
            MindestpunkteFaelle::deutsch(4),           // SLK, < 16
            MindestpunkteFaelle::mathematikFix(4),     // MNT, < 16
            MindestpunkteFaelle::franzoesischFix(20),  // SLK, ≥16 (sonst 2x SLK <16)
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['aufgabenfeld']['pass']);
    }

    // ─── Sport-Sonderfall: Beitrag = (sportScore + practicalGrade) × 2 ──
    //     Sport hat eine eigene Formel (kein /3-Drittel wie bei ZP) und kein
    //     Aufgabenfeld, ist aber meist das Leistungsfach (LF) und damit für
    //     Kriterium 3 (3 Fächer ≥20 inkl. D/M/LF) und Kriterium 4
    //     (Dreierblock, falls D/M substituiert) direkt relevant.

    public function test_sport_kriterium1_gerade_noch_bestanden_summe_2(): void
    {
        // (1+1)*2 = 4 -> genau die Schwelle für "jedes Fach ≥4"
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLfDetailed(1, 1), // Beitrag (1+1)*2=4
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['each_ge_1']['pass']);
        $this->assertSame(4, $result['criteria']['each_ge_1']['actual']);
    }

    public function test_sport_kriterium1_knapp_nicht_bestanden_summe_unter_2(): void
    {
        // (1+0)*2 = 2 (< 4)
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLfDetailed(1, 0), // Beitrag (1+0)*2=2
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['each_ge_1']['pass']);
        $this->assertSame(2, $result['criteria']['each_ge_1']['actual']);
    }

    public function test_sport_beitrag_ist_symmetrisch_bei_theorie_praxis_tausch(): void
    {
        // (3+7)*2 und (7+3)*2 müssen denselben Beitrag liefern (20)
        $exams1 = MindestpunkteFaelle::standardFix([20, 20, 20, 20, 0]);
        $exams1[4] = MindestpunkteFaelle::sportLfDetailed(3, 7);
        $exams2 = MindestpunkteFaelle::standardFix([20, 20, 20, 20, 0]);
        $exams2[4] = MindestpunkteFaelle::sportLfDetailed(7, 3);

        $result1 = $this->svc->compute($exams1);
        $result2 = $this->svc->compute($exams2);
        $this->assertSame($result1['total'], $result2['total']);
        $this->assertSame(20 * 4 + 20, $result1['total']);
    }

    public function test_sport_als_lf_zaehlt_fuer_kriterium3_auch_wenn_dm_niedrig(): void
    {
        // D und M beide niedrig, nur Sport(LF) + 2 weitere Fächer ≥20 -> sollte
        // bestehen, da Sport als LF das D/M/LF-Schlüsselfach-Kriterium erfüllt.
        $exams = [
            MindestpunkteFaelle::deutsch(4),          // < 20
            MindestpunkteFaelle::mathematikFix(4),    // < 20
            MindestpunkteFaelle::franzoesischFix(20), // ≥ 20
            MindestpunkteFaelle::politik(20),         // ≥ 20
            MindestpunkteFaelle::sportLf(20),         // ≥ 20, LF
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['three_ge_5']['pass']);
    }

    public function test_sport_dreierblock_variante_b_d_substituiert(): void
    {
        // D substituiert (kein Deutsch-Fach im Array) -> Variante (b):
        // M + LF(Sport) + bestes{FS_grundlegend, NW}. M=16, Sport=16, Französisch(grundlegend)=8 -> Summe 40.
        $exams = [
            MindestpunkteFaelle::mathematikFix(16),
            MindestpunkteFaelle::franzoesischFix(8), // grundlegend (Default), < 16 aber nur 1x erlaubt
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(16),        // LF, genau 16
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['criteria']['dreierblock']['pass']);
    }

    public function test_sport_dreierblock_variante_b_knapp_nicht_bestanden(): void
    {
        // Gleicher Aufbau, aber Summe nur 39 (M+LF+FS = 16+16+7)
        $this->assertSame(7, MindestpunkteFaelle::contribution(1, 3));
        $exams = [
            MindestpunkteFaelle::mathematikFix(16),
            MindestpunkteFaelle::franzoesischZp(1, 3), // Beitrag 7
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(16),
        ];
        $result = $this->svc->compute($exams);
        $this->assertFalse($result['criteria']['dreierblock']['pass']);
    }

    // ─── Sport schriftlich + Zusatzprüfung (§ 52 Abs. 2 Nr. 2 GSO) ───────

    /**
     * Beleg-Beispiel aus dem schulinternen Leitfaden Nr. 2.3.2.2:
     * Praxis=15, Theorie=15, mündlich=15 → Beitrag = (15+15+15)×4/3 = 60.
     */
    public function test_sport_zp_amtliches_beispiel_volle_punktzahl(): void
    {
        $exams  = [MindestpunkteFaelle::sportZp(15, 15, 15)];
        $result = $this->svc->checkReqs($exams);
        $this->assertSame(60, $result['total']);
    }

    /**
     * Praxis=12, Theorie=10, mündlich=14 → Beitrag = round((12+10+14)×4/3) = 48.
     * Kombinierte Anzeige-Note = round((12+10+14)/3) = round(12) = 12.
     */
    public function test_sport_zp_beitrag_nach_amtlicher_formel(): void
    {
        $exam = MindestpunkteFaelle::sportZp(10, 12, 14);
        $oralMap = [];
        $result  = $this->svc->checkReqs([$exam], $oralMap);
        $this->assertSame(48, $result['total']);
    }

    /**
     * Regressionstest für den ursprünglichen Bug: Vor dem Fix wurde bei
     * Sport-ZP ausschließlich calcZpContribution(theorie, muendlich) gerechnet
     * (Praxisnote komplett ignoriert). Das hätte hier round((2×10+14)×4/3)=45
     * ergeben statt korrekt 48 (Praxis=12 fließt zusätzlich ein).
     */
    public function test_sport_zp_praxisnote_aendert_ergebnis_gegenueber_reiner_theorie_formel(): void
    {
        $exam   = MindestpunkteFaelle::sportZp(10, 12, 14);
        $result = $this->svc->checkReqs([$exam]);
        $theorieOnlyBeitrag = $this->svc->calcZpContribution(10, 14); // 45 = alter Bug-Wert
        $this->assertNotSame($theorieOnlyBeitrag, $result['total']);
        $this->assertSame(48, $result['total']);
    }

    /** Beitrag ist symmetrisch bei Praxis/Theorie-Tausch (Summe bleibt gleich). */
    public function test_sport_zp_beitrag_ist_symmetrisch_bei_praxis_theorie_tausch(): void
    {
        $exam1 = MindestpunkteFaelle::sportZp(3, 7, 9);
        $exam2 = MindestpunkteFaelle::sportZp(7, 3, 9);
        $r1    = $this->svc->checkReqs([$exam1]);
        $r2    = $this->svc->checkReqs([$exam2]);
        $this->assertSame($r1['total'], $r2['total']);
    }

    /**
     * Sport-ZP eingebunden in volle 5-Kriterien-Prüfung: Sport schriftlich+ZP
     * (Theorie=14, Praxis=15, mündlich=15 → Beitrag round((15+14+15)×4/3)=59)
     * zusammen mit D/M/F/Politik auf je 20 → Abitur muss klar bestanden sein.
     */
    public function test_sport_zp_in_voller_kriterienpruefung_bestanden(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportZp(14, 15, 15), // Beitrag 59
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['pass']);
        $this->assertSame(20 + 20 + 20 + 20 + 59, $result['total']);
    }

    /**
     * Fehlt die Praxisnote bei Sport-ZP, ist die Berechnung nicht möglich
     * (analog zum Nicht-ZP-Sport-Fall) — total/criteria müssen null sein,
     * NICHT stillschweigend nur die Theorie-ZP-Formel verwenden.
     */
    public function test_sport_zp_ohne_praxisnote_ist_nicht_berechenbar(): void
    {
        $exam = MindestpunkteFaelle::sportZp(10, 0, 14);
        $exam['sportPracticalGrade'] = null;
        $result = $this->svc->checkReqs([$exam]);
        $this->assertNull($result['total']);
        $this->assertFalse($result['pass']);
        $this->assertSame(['missing'], $result['failReqs']);
    }

    /**
     * compute(): offene Sport-ZP (targetPoints=null) mit bekannter Praxisnote
     * liefert in computeCombinations() für jede mündliche Note den korrekten
     * Sport-ZP-Beitrag (contribAndCombinedFor-Pfad), nicht die reine
     * Theorie-Formel. Stichprobe bei mündlich=15: Beitrag = (12+10+15)×4/3 = 49.
     */
    public function test_sport_zp_offen_combinations_nutzen_praxisnote(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportZp(10, 12, null), // offen, Praxis bekannt
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['allFixedScoresEntered']);
        $row15 = $result['combinations'][15] ?? null;
        $this->assertNotNull($row15);
        $this->assertSame([15], $row15['oral']);
        $this->assertSame(49, $row15['contrib'][0]); // (12+10+15)*4/3 = 49.33 -> 49
    }

    /**
     * Regressionstest für eine noch offene Sport-ZP im "fixedTotal".
     *
     * Der Faktor war hier schon zweimal falsch. Zuerst ×4 wie bei einem
     * gewöhnlichen Fach — (Praxis+Theorie)×4, also 88 statt 44, und damit
     * 44 Punkte zu viel im Gesamttotal. Dann (Praxis+Theorie)×2, der Beitrag
     * OHNE Zusatzprüfung.
     *
     * **Auch das war zu hoch**, denn es ist keine Untergrenze: Eine
     * Zusatzprüfung kann das Ergebnis auch verschlechtern (fachliche Klärung
     * Wilfried, 31.08.2026). Was dem Prüfling sicher bleibt, ist der Beitrag
     * beim schlechtestmöglichen Ausgang, also mündlich = 0:
     *
     *   ×4 (falsch)            (12 + 10) × 4        = 88
     *   ohne ZP (keine Grenze) (12 + 10) × 2        = 44
     *   Untergrenze            round((12+10+0)×4/3) = 29
     *
     * @see MindestpunkteUntergrenzeTest für die Herleitung
     */
    public function test_sport_zp_offen_zaehlt_mit_dem_schlechtesten_ausgang(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikFix(20),
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportZp(12, 10, null), // offen: Theorie 12, Praxis 10
        ];
        $result = $this->svc->compute($exams);
        $this->assertTrue($result['allFixedScoresEntered']);

        $this->assertSame(29, $this->svc->calcSportZpContribution(10, 12, 0));
        $this->assertSame(20 + 20 + 20 + 20 + 29, $result['fixedTotal']);
    }
}
