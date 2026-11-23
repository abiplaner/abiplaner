<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

/**
 * SQLite-Grammatik der lokalen Edition: hängt an jede ORDER-BY-Spalte die
 * deutsche Kollation (DeutscheKollation), damit orderBy('lastname') wie unter
 * MariaDB sortiert — ohne die rund 76 Sortierstellen im Code anzufassen.
 *
 * Unschädlich für Zahlen und Datumswerte: SQLite wendet eine Kollation nur
 * beim Vergleich zweier Texte an; Zahlen vergleicht es numerisch. Datums-
 * und Zeitangaben stehen als ISO-Text da und behalten ihre Reihenfolge.
 *
 * orderByRaw(...) bleibt unverändert: Was dort steht, hat jemand bewusst
 * geschrieben.
 */
class DeutscheSqliteGrammar extends SQLiteGrammar
{
    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order) use ($query) {
            if (isset($order['sql']) || ($order['type'] ?? null) === 'InOrderOf' || ! isset($order['column'])) {
                return parent::compileOrdersToArray($query, [$order])[0];
            }

            return $this->wrap($order['column']) . ' collate ' . DeutscheKollation::NAME . ' ' . $order['direction'];
        }, $orders);
    }
}
