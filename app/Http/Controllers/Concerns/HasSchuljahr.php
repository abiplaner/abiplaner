<?php

namespace App\Http\Controllers\Concerns;

/**
 * Shared Schuljahr helpers for LK and W-Seminar controllers.
 *
 * Provides `getCurrentSchuljahr()` and `getAvailableSchuljahre()`, which were
 * previously copied verbatim into all six LK/W-Seminar controllers.
 */
trait HasSchuljahr
{
    /**
     * Returns the current German school year as "YYYY/YYYY+1".
     * The school year starts in September.
     */
    protected function getCurrentSchuljahr(): string
    {
        $year  = (int) date('Y');
        $month = (int) date('n');

        return $month >= 9
            ? $year . '/' . ($year + 1)
            : ($year - 1) . '/' . $year;
    }

    /**
     * Returns a list of school years for filter dropdowns:
     * one year before the current, the current, and two years ahead.
     *
     * @return string[]
     */
    protected function getAvailableSchuljahre(): array
    {
        $year  = (int) date('Y');
        $month = (int) date('n');

        $startYear = $month >= 9 ? $year : $year - 1;

        $schuljahre = [];
        for ($i = -1; $i <= 2; $i++) {
            $y = $startYear + $i;
            $schuljahre[] = $y . '/' . ($y + 1);
        }

        return $schuljahre;
    }
}
