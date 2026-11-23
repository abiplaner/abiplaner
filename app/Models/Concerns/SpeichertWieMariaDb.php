<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Datums- und Uhrzeitwerte so speichern, wie MariaDB sie abgelegt hätte.
 *
 * Bis 22.11.2026 lief die Anwendung auf MariaDB. Deren DATE- und TIME-Spalten
 * haben Werte stillschweigend vereinheitlicht: '2026-05-10 00:00:00' wurde zu
 * '2026-05-10', '08:00' zu '08:00:00'. SQLite speichert Text, wie er kommt.
 * Code und Tests, die gegen '1900-01-01' oder '09:00:00' vergleichen, fanden
 * dann nichts mehr — die automatische Planung fand so keinen
 * Standard-Prüfungsraum und plante gar nichts.
 *
 *   Cast 'date'/'immutable_date' → Y-m-d   (Eloquent schreibt sonst Y-m-d H:i:s)
 *   Spaltentyp time               → H:i:s  (aus 'H:i' wird 'H:i:00')
 *
 * Der Spaltentyp kommt aus dem Schema, einmal je Tabelle und Prozess.
 */
trait SpeichertWieMariaDb
{
    /** @var array<string, array<string, true>> Tabelle → Uhrzeit-Spalten */
    private static array $uhrzeitSpalten = [];

    public function setAttribute($key, $value)
    {
        parent::setAttribute($key, $value);

        if ($value === null || $value === '') {
            return $this;
        }

        $roh = $this->attributes[$key] ?? null;

        if ($this->istReinesDatum($key)) {
            if ($roh instanceof CarbonInterface) {
                $this->attributes[$key] = $roh->format('Y-m-d');
            } elseif (is_string($roh) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]/', $roh) === 1) {
                $this->attributes[$key] = substr($roh, 0, 10);
            }
        } elseif (is_string($roh) && preg_match('/^\d{1,2}:\d{2}$/', $roh) === 1 && $this->istUhrzeitSpalte($key)) {
            $this->attributes[$key] = str_pad($roh, 5, '0', STR_PAD_LEFT) . ':00';
        }

        return $this;
    }

    private function istReinesDatum(string $key): bool
    {
        $cast = $this->getCasts()[$key] ?? null;

        return is_string($cast)
            && in_array(explode(':', $cast, 2)[0], ['date', 'immutable_date'], true);
    }

    private function istUhrzeitSpalte(string $key): bool
    {
        $tabelle = $this->getTable();

        if (! isset(self::$uhrzeitSpalten[$tabelle])) {
            self::$uhrzeitSpalten[$tabelle] = [];
            foreach (Schema::connection($this->getConnectionName())->getColumns($tabelle) as $spalte) {
                if (strtolower((string) $spalte['type_name']) === 'time') {
                    self::$uhrzeitSpalten[$tabelle][$spalte['name']] = true;
                }
            }
        }

        return isset(self::$uhrzeitSpalten[$tabelle][$key]);
    }
}
