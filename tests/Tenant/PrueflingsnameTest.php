<?php

namespace Tests\Tenant;

use App\Models\StudentExam;
use App\Models\Zusatzpruefung;
use PHPUnit\Framework\Attributes\Test;

/**
 * Der Name des Prüflings ist nie leer.
 *
 * `getStudentName()` lieferte bis zum 30.08.2026 `?string`. In zwölf Protokoll-
 * und Logtexten wurde der Wert ungeschützt interpoliert — aus einem fehlenden
 * Namen wurde dort keine Fehlermeldung, sondern eine Lücke: „Prüfung für
 * zugewiesen". Wer das im Protokoll liest, merkt nicht einmal, dass etwas
 * fehlt.
 *
 * Der Fall selbst ist eine Unstimmigkeit in den Daten: eine Prüfung, an der
 * weder ein Schüler noch ein externer Bewerber hängt. Er soll sichtbar sein.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter Prueflingsname
 */
class PrueflingsnameTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

    }

    protected function tearDown(): void
    {

        parent::tearDown();
    }

    /**
     * Der Kern: Ohne beide Beziehungen steht ein Platzhalter da, keine Lücke.
     *
     * Bewusst an einer Instanz im Speicher — der Fall soll in der Datenbank
     * gar nicht vorkommen, die Methode muss ihn trotzdem tragen.
     */
    #[Test]
    public function eine_pruefung_ohne_schueler_und_ohne_bewerber_nennt_einen_platzhalter(): void
    {
        $pruefung = new StudentExam();

        $this->assertSame(StudentExam::NAME_UNBEKANNT, $pruefung->getStudentName());
        $this->assertNotSame('', $pruefung->getStudentName());
    }

    /**
     * Gegenprobe zum Platzhalter: Der Wert taugt als Name mitten im Satz.
     * Ohne sie wäre auch ein leeres Zeichen oder ein Zeilenumbruch „nicht
     * leer" und der Test darüber trotzdem grün.
     */
    #[Test]
    public function der_platzhalter_traegt_einen_protokolltext(): void
    {
        $pruefung = new StudentExam();

        $satz = "Prüfung für {$pruefung->getStudentName()} zugewiesen";

        $this->assertSame('Prüfung für Unbekannter Prüfling zugewiesen', $satz);
        $this->assertStringNotContainsString('für  zugewiesen', $satz);
    }

    /**
     * Die Kurzformen behalten ihre eigenen, knappen Platzhalter — sie stehen
     * in engen Spalten, dort wäre der lange Text falsch. Sie lesen deshalb
     * weiterhin den rohen Wert und nicht getStudentName().
     */
    #[Test]
    public function die_kurzformen_bleiben_kurz(): void
    {
        $pruefung = new StudentExam();

        $this->assertSame('?', $pruefung->prueflingsNachname());
        $this->assertSame('', $pruefung->prueflingsVorname());
        $this->assertSame('?', $pruefung->prueflingsKurzname());
    }

    /**
     * Eine Zusatzprüfung ohne angehängte Prüfung nennt denselben Platzhalter.
     * Vorher war auch das null, und drei Stellen in
     * zusatzpruefungen/index.blade.php gaben es ungeschützt aus.
     */
    #[Test]
    public function eine_zusatzpruefung_ohne_pruefung_nennt_denselben_platzhalter(): void
    {
        $zusatz = new Zusatzpruefung();

        $this->assertSame(StudentExam::NAME_UNBEKANNT, $zusatz->prueflingsName());
    }
}
