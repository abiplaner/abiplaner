<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * SQL-Bausteine, die MariaDB (Web-Version) und SQLite (lokale Edition)
 * unterschiedlich schreiben. Nur für die wenigen Stellen, die der
 * Query-Builder nicht selbst portabel ausdrückt — alles andere bleibt
 * beim Builder.
 *
 * Die Treiberfrage stellt der Aufrufer über die Verbindung seiner Abfrage
 * ($query->getConnection()->getDriverName()), nicht über die Edition: Tests
 * und Werkzeuge können in jeder Edition jede Verbindung benutzen.
 */
final class Sql
{
    /**
     * Zeichenketten verketten. MariaDB: CONCAT(a, b). SQLite: a || b
     * (unter MariaDB hiesse || ohne PIPES_AS_CONCAT „oder“).
     *
     * Die Teile sind SQL-Ausdrücke (Spaltennamen oder Literale wie "' '"),
     * keine Nutzereingaben.
     */
    public static function verketten(string $treiber, string ...$teile): Expression
    {
        return DB::raw($treiber === 'sqlite'
            ? '(' . implode(' || ', $teile) . ')'
            : 'CONCAT(' . implode(', ', $teile) . ')');
    }

    /**
     * Ersatz für MariaDBs FIELD(spalte, 'a', 'b', …) in ORDER BY: Position des
     * Werts in der Liste, 0 für alle anderen — genau wie FIELD. Als CASE in
     * beiden Treibern gleich.
     *
     * @param  list<string>  $werte  feste Werte aus dem Code, keine Eingaben
     */
    public static function positionIn(string $spalte, array $werte): string
    {
        $faelle = '';
        foreach (array_values($werte) as $i => $wert) {
            $faelle .= " WHEN {$spalte} = '" . str_replace("'", "''", $wert) . "' THEN " . ($i + 1);
        }

        return "CASE{$faelle} ELSE 0 END";
    }

    /**
     * Bedingung „Wochentag von $spalte ist $isoTag“ (ISO: 1 = Montag … 7 = Sonntag).
     * MariaDB: DAYOFWEEK zählt 1 = Sonntag. SQLite: strftime('%w') zählt 0 = Sonntag.
     */
    public static function wochentagIst(string $treiber, string $spalte, int $isoTag): string
    {
        if ($treiber === 'sqlite') {
            return "CAST(strftime('%w', {$spalte}) AS INTEGER) = " . ($isoTag % 7);
        }

        return "DAYOFWEEK({$spalte}) = " . ($isoTag % 7 + 1);
    }
}
