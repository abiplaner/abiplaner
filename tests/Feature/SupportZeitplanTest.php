<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Die geplanten Läufe der Support-Organisation (Modul 6.4).
 *
 * **Warum das einen Test wert ist:** Beide Läufe sind Gegenproben — sie tun im
 * Regelfall nichts und fallen deshalb nicht auf, wenn sie fehlen.
 *
 * `support:nachbenachrichtigen` ist die zweite Hälfte der Vorkehrung „erst
 * speichern, dann senden": Ohne den Lauf bliebe eine liegengebliebene Meldung
 * für immer liegen, und das Abfangen im Dienst wäre nur die halbe Miete. Am
 * 29.08.2026 hat genau dieser Fall zugeschlagen — ein Versand scheiterte an
 * einer Namensauflösung, die zeitweise nur IPv6 liefert.
 *
 * `support:anhaenge-aufraeumen` räumt Bildschirmfotos weg, die am Abschluss des
 * Falls vorbeigekommen sind (Entscheidung 48).
 *
 * `support:fallakten-aufraeumen` hält die Aufbewahrungsfristen ein
 * (Entscheidung 53). Er ist der stillste der drei: Fällt er aus dem Zeitplan,
 * merkt es niemand — die Daten bleiben einfach liegen, und genau das ist der
 * Fehler.
 */
class SupportZeitplanTest extends TestCase
{
    public function test_die_drei_support_laeufe_stehen_im_zeitplan(): void
    {
        $befehle = $this->geplanteBefehle();

        $erwartet = [
            // Eine Fehlermeldung soll nicht bis zum nächsten Morgen warten.
            'support:nachbenachrichtigen' => '*/15 * * * *',
            'support:anhaenge-aufraeumen' => '10 3 * * *',
            // Nach dem Anhänge-Lauf: Ein Fall, der beide betrifft, verliert
            // erst seine Bilder und dann seine Akte.
            'support:fallakten-aufraeumen' => '20 3 * * *',
        ];

        foreach ($erwartet as $befehl => $ausdruck) {
            $this->assertContains(
                $befehl,
                array_keys($befehle),
                "Der Lauf '{$befehl}' steht nicht mehr im Zeitplan (routes/console.php)."
            );

            $this->assertSame($ausdruck, $befehle[$befehl]);
        }
    }

    /**
     * Keiner der Läufe darf sich selbst überholen — einer schreibt Mails, die
     * beiden anderen löschen.
     */
    public function test_die_laeufe_ueberholen_sich_nicht(): void
    {
        foreach ($this->geplanteEreignisse() as $ereignis) {
            if (! str_contains((string) $ereignis->command, 'support:')) {
                continue;
            }

            $this->assertNotNull(
                $ereignis->mutexName(),
                'Der Lauf braucht withoutOverlapping().'
            );
            $this->assertTrue($ereignis->withoutOverlapping);
        }
    }

    /**
     * @return array<string, string> Befehl → Cron-Ausdruck
     */
    private function geplanteBefehle(): array
    {
        $befehle = [];

        foreach ($this->geplanteEreignisse() as $ereignis) {
            $kommando = (string) $ereignis->command;

            // Das Kommando trägt den PHP-Pfad und "artisan" davor.
            if (preg_match("/artisan'? (\S+)/", $kommando, $treffer) === 1) {
                $befehle[trim($treffer[1], "'\"")] = $ereignis->expression;
            }
        }

        return $befehle;
    }

    /**
     * @return list<Event>
     */
    private function geplanteEreignisse(): array
    {
        return array_values(app(Schedule::class)->events());
    }
}
