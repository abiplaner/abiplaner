<?php

namespace App\Services;

/**
 * Namens-Toleranz-Heuristiken des Absenz-Imports (Modul 8.1), extrahiert
 * zur Wiederverwendung im Carryover-Abgleich des Schuljahreswechsels
 * (Modul 1.1, Wizard-Schritt 6).
 *
 * Abgedeckte, in der Praxis beobachtete Abweichungen:
 *   - Stammdaten führen nur EINEN von mehreren Vornamen ("Philipp" vs.
 *     "Philipp Elias"), nicht zwingend den ersten ("Felix" vs.
 *     "Tien-Thanh Felix")
 *   - Bindestrich-Doppelnamen, von denen nur ein Teil geführt wird
 *     ("Lilly" vs. "Lilly-Marie")
 *   - Rufname/Kurzform ("Theo" vs. "Theodor") — über Präfix-Abgleich
 *     mit Mindestlänge, um Zufallstreffer zu vermeiden
 *   - Namenspartikel/Adelsprädikate im Nachnamen ("Zeppelin" vs.
 *     "von Zeppelin")
 *
 * Bewusst großzügig: mehrdeutige Treffer gehören in eine manuelle
 * Klärungsliste statt in eine falsche automatische Zuordnung.
 */
class NameMatchingService
{
    /** Mindestlänge für den Präfix-Abgleich (Rufname/Kurzform) */
    private const MIN_PREFIX_LENGTH = 3;

    /**
     * Prüft, ob ein Stammdaten-Vorname zu einem (evtl. mehrteiligen)
     * Referenz-Vornamen passen könnte — Logik unverändert aus dem
     * Absenz-Import (Modul 8.1) übernommen.
     */
    public function firstnameLooksLike(string $dbFirstname, string $referenceFirstname): bool
    {
        $db = mb_strtolower(trim($dbFirstname));
        $reference = mb_strtolower(trim($referenceFirstname));

        if ($db === '' || $reference === '') {
            return false;
        }
        if ($db === $reference) {
            return true;
        }

        // Referenz-Vorname in Einzelteile zerlegen: Leerzeichen UND Bindestrich
        $tokens = array_values(array_filter(
            preg_split('/[\s\-]+/u', $reference) ?: []
        ));

        foreach ($tokens as $token) {
            if ($db === $token) {
                return true;
            }
        }

        if (mb_strlen($db) >= self::MIN_PREFIX_LENGTH) {
            foreach ($tokens as $token) {
                if (mb_strlen($token) >= self::MIN_PREFIX_LENGTH
                    && (str_starts_with($token, $db) || str_starts_with($db, $token))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Symmetrische Vornamens-Toleranz für den Carryover-Abgleich: beide
     * Seiten sind Stammdaten (alter vs. neuer Jahrgang), jede kann die
     * vollständigere Form führen.
     */
    public function firstnamesMatch(string $a, string $b): bool
    {
        return $this->firstnameLooksLike($a, $b) || $this->firstnameLooksLike($b, $a);
    }

    /**
     * Nachnamen-Toleranz: exakt (case-insensitive) oder eine Seite trägt
     * einen vorangestellten Namenspartikel ("Zeppelin" vs. "von Zeppelin").
     * Symmetrisch, da beim Carryover-Abgleich beide Seiten Stammdaten sind.
     */
    public function lastnamesMatch(string $a, string $b): bool
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));

        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b
            || str_ends_with($a, ' ' . $b)
            || str_ends_with($b, ' ' . $a);
    }
}
