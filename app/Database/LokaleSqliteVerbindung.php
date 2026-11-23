<?php

namespace App\Database;

use DateTimeInterface;
use Illuminate\Database\SQLiteConnection;

/**
 * SQLite-Verbindung der Anwendung (angemeldet in AppServiceProvider::register()
 * per Connection::resolverFor('sqlite', …)):
 *
 *   - deutsche Sortierung (DeutscheKollation, DeutscheSqliteGrammar)
 *   - Datumsbindungen wie unter MariaDB (siehe prepareBindings)
 */
class LokaleSqliteVerbindung extends SQLiteConnection
{
    /**
     * Laravel baut die PDO erst beim ersten Zugriff (Closure). Die Kollation
     * muss an jede neue PDO — auch nach reconnect().
     */
    public function getPdo()
    {
        $pdo = parent::getPdo();

        if ($pdo instanceof \PDO) {
            DeutscheKollation::registrieren($pdo);
        }

        return $pdo;
    }

    /**
     * Carbon-Werte auf Mitternacht als reines Datum binden.
     *
     * Datumsspalten liegen als 'Y-m-d' (SpeichertWieMariaDb). Laravel bindet
     * Carbon aber als 'Y-m-d H:i:s' — als Text verglichen ist '2027-05-10'
     * kleiner als '2027-05-10 00:00:00', und der erste Tag fiele aus jedem
     * whereBetween($von, $bis) heraus. MariaDB verglich Datum mit Datum.
     * Für Zeitstempel-Spalten bleibt der Vergleich gegen 'Y-m-d' richtig
     * ('2027-05-10 08:15:00' >= '2027-05-10').
     */
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $schluessel => $wert) {
            if ($wert instanceof DateTimeInterface && $wert->format('H:i:s.u') === '00:00:00.000000') {
                $bindings[$schluessel] = $wert->format('Y-m-d');
            }
        }

        return parent::prepareBindings($bindings);
    }

    protected function getDefaultQueryGrammar()
    {
        return new DeutscheSqliteGrammar($this);
    }
}
