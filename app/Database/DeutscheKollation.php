<?php

namespace App\Database;

use PDO;
use WeakMap;

/**
 * Deutsche Sortierung für SQLite (lokale Edition).
 *
 * MariaDB sortiert mit utf8mb4_unicode_ci: Umlaute bei ihren Grundbuchstaben,
 * Groß/klein egal. SQLite kennt nur BINARY (Ä hinter Z) und NOCASE (nur ASCII).
 * Hier wird deshalb eine Kollation "de" registriert, die DeutscheSqliteGrammar
 * an jede ORDER-BY-Spalte hängt.
 *
 * Vergleich über intl (Collator de_DE), falls vorhanden — sonst über einen
 * Schlüssel nach DIN 5007-1 (ä = a, ö = o, ü = u, ß = ss, Akzente weg,
 * Groß/klein egal), damit die Reihenfolge auch ohne intl stimmt.
 */
final class DeutscheKollation
{
    public const NAME = 'de';

    /** @var WeakMap<PDO, true>|null */
    private static ?WeakMap $registriert = null;

    private static ?\Collator $collator = null;

    /** Kollation an einer PDO-Verbindung anmelden (einmal je Verbindung). */
    public static function registrieren(PDO $pdo): void
    {
        self::$registriert ??= new WeakMap();
        if (isset(self::$registriert[$pdo])) {
            return;
        }

        $vergleich = static fn (string $a, string $b): int => self::vergleichen($a, $b);

        if ($pdo instanceof \Pdo\Sqlite) {
            $pdo->createCollation(self::NAME, $vergleich);
        } else {
            $pdo->sqliteCreateCollation(self::NAME, $vergleich);
        }

        self::$registriert[$pdo] = true;
    }

    /** Negativ, 0 oder positiv wie strcmp. */
    public static function vergleichen(string $a, string $b): int
    {
        if (class_exists(\Collator::class)) {
            self::$collator ??= new \Collator('de_DE');
            $ergebnis = self::$collator->compare($a, $b);
            if ($ergebnis !== false) {
                return $ergebnis;
            }
        }

        return strcmp(self::schluessel($a), self::schluessel($b)) ?: strcmp($a, $b);
    }

    /** Sortierschlüssel nach DIN 5007-1 (Ersatz ohne intl). */
    public static function schluessel(string $wert): string
    {
        $wert = mb_strtolower($wert, 'UTF-8');
        $wert = strtr($wert, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);

        if (class_exists(\Normalizer::class)) {
            $zerlegt = \Normalizer::normalize($wert, \Normalizer::FORM_D);
            if (is_string($zerlegt)) {
                // Akzente (kombinierende Zeichen) entfernen: é → e, č → c
                $wert = (string) preg_replace('/\p{Mn}+/u', '', $zerlegt);
            }
        }

        return $wert;
    }
}
