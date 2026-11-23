<?php

namespace Tests\Unit\Services;

use App\Services\MindestpunkteService;
use PHPUnit\Framework\TestCase;

/**
 * Die Untergrenze eines noch offenen Zusatzpruefungs-Fachs.
 *
 * Solange die muendliche Note fehlt, muss die Maske sagen koennen, was dem
 * Pruefling im schlechtesten Fall sicher bleibt. Bis zum 31.08.2026 setzte der
 * Dienst dafuer den Beitrag OHNE Zusatzpruefung an — schriftlich x 4, beim
 * Sport (Praxis + Theorie) x 2 — mit der Begruendung, eine muendliche Note
 * koenne die Lage nur verbessern oder gleich halten.
 *
 * **Das ist nicht so: Eine Zusatzpruefung kann das Ergebnis auch
 * verschlechtern** (fachliche Klaerung Wilfried, 31.08.2026). Der Platzhalter
 * lag damit systematisch zu hoch, und die Kriterienanzeige meldete Punkte als
 * "schon sicher erfuellt", die ein schlechter muendlicher Ausgang noch haette
 * reissen koennen — genau die Auskunft, fuer die der Pruefling die Maske
 * aufruft.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter MindestpunkteUntergrenze
 */
class MindestpunkteUntergrenzeTest extends TestCase
{
    private MindestpunkteService $svc;

    protected function setUp(): void
    {
        $this->svc = new MindestpunkteService();
    }

    /**
     * Gegenprobe zuerst: Der alte Platzhalter und die echte Untergrenze sind
     * wirklich verschieden — sonst belegt der Rest dieses Tests nichts.
     *
     * Ab einem einzigen schriftlichen Punkt gehen sie auseinander, und der
     * Abstand waechst bis auf 20 von 900 Punkten. Bei zwei offenen ZP-Faechern
     * also bis zu 40.
     */
    public function test_der_alte_platzhalter_lag_ueber_der_echten_untergrenze(): void
    {
        $abstaende = [];

        for ($w = 0; $w <= 15; $w++) {
            $alt  = $w * 4;                                 // Beitrag ohne Zusatzpruefung
            $echt = $this->svc->calcZpContribution($w, 0);   // schlechtester Ausgang mit ihr

            $this->assertGreaterThanOrEqual(
                $echt,
                $alt,
                "Bei {$w} schriftlichen Punkten muesste der alte Platzhalter ueber der Untergrenze liegen."
            );

            $abstaende[$w] = $alt - $echt;
        }

        $this->assertSame(0, $abstaende[0], 'Bei 0 Punkten sind beide gleich.');
        $this->assertSame(1, $abstaende[1]);
        $this->assertSame(11, $abstaende[8]);
        $this->assertSame(20, $abstaende[15], 'Der groesste Abstand: 60 statt 40.');
    }

    /**
     * Ein offenes ZP-Fach geht mit dem Beitrag bei muendlich = 0 in die Summe
     * der festen Faecher ein.
     */
    public function test_ein_offenes_zp_fach_zaehlt_mit_dem_schlechtesten_ausgang(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(40),
            MindestpunkteFaelle::mathematikZp(12, null),   // offen
            MindestpunkteFaelle::franzoesischFix(40),
            MindestpunkteFaelle::politik(40),
            MindestpunkteFaelle::sportLf(40),
        ];

        $ergebnis = $this->svc->compute($exams);

        $untergrenze = $this->svc->calcZpContribution(12, 0);

        $this->assertSame(32, $untergrenze, 'round((2*12 + 0) * 4/3) = 32');
        $this->assertSame(4 * 40 + $untergrenze, $ergebnis['fixedTotal']);
        $this->assertNotSame(4 * 40 + 12 * 4, $ergebnis['fixedTotal'], 'Das waere der alte Platzhalter.');
    }

    /**
     * Im Sport dasselbe, nur mit der Sportformel: (Praxis + Theorie +
     * muendlich) x 4/3 statt (Praxis + Theorie) x 2.
     */
    public function test_ein_offenes_sport_zp_fach_ebenso(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(40),
            MindestpunkteFaelle::mathematikFix(40),
            MindestpunkteFaelle::franzoesischFix(40),
            MindestpunkteFaelle::politik(40),
            MindestpunkteFaelle::sportZp(10, 12, null),   // Theorie 10, Praxis 12, offen
        ];

        $ergebnis = $this->svc->compute($exams);

        $untergrenze = $this->svc->calcSportZpContribution(12, 10, 0);

        $this->assertSame(29, $untergrenze, 'round((12 + 10 + 0) * 4/3) = 29');
        $this->assertSame(4 * 40 + $untergrenze, $ergebnis['fixedTotal']);
        $this->assertNotSame(4 * 40 + (12 + 10) * 2, $ergebnis['fixedTotal'], 'Das waere der alte Platzhalter.');
    }

    /**
     * Der eigentliche Schaden: ein Kriterium, das mit dem alten Platzhalter
     * als erfuellt galt und es in Wahrheit nicht sicher war.
     *
     * Kriterium 2 verlangt mindestens 100 Punkte. Vier feste Faecher mit je
     * 20 Punkten (Note 5) ergeben 80; das offene ZP-Fach traegt mit dem alten
     * Platzhalter 5 x 4 = 20 bei, zusammen genau 100 — "erfuellt". Der
     * schlechteste Ausgang der Zusatzpruefung sind aber nur 13 Punkte, macht
     * 93. Das Kriterium kann also noch reissen, und die Maske sagt das jetzt.
     */
    public function test_ein_kriterium_gilt_erst_als_erfuellt_wenn_es_nicht_mehr_kippen_kann(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(20),
            MindestpunkteFaelle::mathematikZp(5, null),   // offen, 5 schriftlich
            MindestpunkteFaelle::franzoesischFix(20),
            MindestpunkteFaelle::politik(20),
            MindestpunkteFaelle::sportLf(20),
        ];

        $ergebnis = $this->svc->compute($exams);

        // Alter Platzhalter: 5 x 4 = 20, zusammen mit den vier festen Faechern
        // genau die geforderten 100. Echte Untergrenze: 13, zusammen 93.
        $this->assertSame(13, $this->svc->calcZpContribution(5, 0), 'Echte Untergrenze: 13 Punkte.');
        $this->assertSame(80 + 13, $ergebnis['fixedTotal']);

        $this->assertFalse(
            $ergebnis['criteria']['total_ge_100']['pass'],
            'Mit 93 sicheren Punkten darf das 100-Punkte-Kriterium nicht als erfuellt gelten.'
        );
    }

    /**
     * Gegenprobe dazu: Reicht es auch im schlechtesten Fall, steht das
     * Kriterium weiterhin als erfuellt da. Sonst waere der Test darueber auch
     * dann gruen, wenn die Anzeige nie etwas als erfuellt meldet.
     */
    public function test_was_wirklich_sicher_ist_gilt_weiter_als_erfuellt(): void
    {
        $exams = [
            MindestpunkteFaelle::deutsch(40),
            MindestpunkteFaelle::mathematikZp(12, null),   // Untergrenze 32
            MindestpunkteFaelle::franzoesischFix(40),
            MindestpunkteFaelle::politik(40),
            MindestpunkteFaelle::sportLf(40),
        ];

        $ergebnis = $this->svc->compute($exams);

        $this->assertSame(192, $ergebnis['fixedTotal'], '4 x 40 + 32');
        $this->assertTrue($ergebnis['criteria']['total_ge_100']['pass']);
    }

}
