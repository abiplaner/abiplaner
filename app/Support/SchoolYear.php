<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Das Schuljahr im Kurzformat ("2026/27") — die eine Stelle dafuer.
 *
 * Bis zum 21.09.2026 stand "2025/26" als Vorgabewert in drei Controllern, einem
 * Dienst und zwei Modellen und in den Auswahllisten von fuenf Masken. Mit dem
 * Schuljahreswechsel bot keine dieser Masken das laufende Schuljahr an oder
 * waehlte es vor.
 *
 * Diese Klasse ist fuer das Kurzformat der Stundenplan-, Schienen- und
 * Klausurenplanung-Tabellen. Die LK-/W-Seminar-Wahlen fuehren die lange Form
 * ("2026/2027") und haben ihre eigenen Helfer (`HasSchuljahr`).
 */
final class SchoolYear
{
    /**
     * Das Schuljahr zu einem Datum. Stichtag ist der 1. August:
     * 2026-05-15 → "2025/26", 2026-09-10 → "2026/27".
     */
    public static function forDate(Carbon $date): string
    {
        $year = (int) $date->format('Y');

        return $date->month >= 8
            ? sprintf('%d/%02d', $year, ($year + 1) % 100)
            : sprintf('%d/%02d', $year - 1, $year % 100);
    }

    /** Das laufende Schuljahr. */
    public static function current(?Carbon $today = null): string
    {
        return self::forDate($today ?? now());
    }

    /**
     * Ist das ein Schuljahr im Kurzformat mit stimmigem Folgejahr?
     * "2026/27" ja; "2026/28", "26/27" und "irgendwas" nein.
     */
    public static function isValid(?string $schoolYear): bool
    {
        if ($schoolYear === null || !preg_match('#^(\d{4})/(\d{2})$#', $schoolYear, $treffer)) {
            return false;
        }

        return ((int) $treffer[1] + 1) % 100 === (int) $treffer[2];
    }

    /**
     * Erster und letzter Tag eines Schuljahres, passend zum Stichtag von
     * forDate(): 1. August bis 31. Juli. Erwartet ein gueltiges Schuljahr.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $schoolYear): array
    {
        $startjahr = (int) substr($schoolYear, 0, 4);

        return [
            Carbon::create($startjahr, 8, 1)->startOfDay(),
            Carbon::create($startjahr + 1, 7, 31)->startOfDay(),
        ];
    }

    /**
     * Die Schuljahre fuer Auswahllisten, neueste zuerst: das naechste, das
     * laufende und die zwei vorigen — dazu jedes Schuljahr aus $vorhandene
     * (etwa aus der Datenbank) und das gerade gewaehlte.
     *
     * @param iterable<mixed> $vorhandene
     * @return list<string>
     */
    public static function options(
        ?string $gewaehlt = null,
        iterable $vorhandene = [],
        ?Carbon $today = null
    ): array {
        $heute = $today ?? now();

        $jahre = [
            self::forDate($heute->copy()->addYear()),
            self::forDate($heute),
            self::forDate($heute->copy()->subYear()),
            self::forDate($heute->copy()->subYears(2)),
            (string) $gewaehlt,
        ];

        foreach ($vorhandene as $jahr) {
            $jahre[] = (string) $jahr;
        }

        $jahre = array_values(array_unique(array_filter($jahre)));
        rsort($jahre, SORT_STRING);

        return $jahre;
    }
}
