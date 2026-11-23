<?php

namespace App\Services;

use App\Models\School;

/**
 * Die Schule, für die gerade gearbeitet wird.
 *
 * Seit 22.11.2026 verwaltet Abiplaner genau eine Schule in genau einer
 * Datenbank; die Mehrschul-Verwaltung (stancl/tenancy) ist zurückgebaut.
 * Den Dienst gibt es weiter, damit Fach-Code an einer Stelle fragt:
 *
 *   aktiv()  — ist die Schule schon eingerichtet?
 *   schule() — der Datensatz der Schule (oder null vor der Einrichtung)
 *
 * betreten(), verlassen() und mit() stammen aus der Mandantenzeit. Sie
 * schalten nichts mehr um und bleiben, bis die letzten Aufrufer bereinigt
 * sind.
 */
class AktuelleSchule
{
    /** Ist die Schule eingerichtet? */
    public function aktiv(): bool
    {
        return $this->schule() !== null;
    }

    /** Die Schule oder null, solange die Ersteinrichtung aussteht. */
    public function schule(): ?School
    {
        return School::query()->first();
    }

    /** @deprecated Kein Umschalten mehr nötig — es gibt nur eine Datenbank. */
    public function betreten(School $schule): void
    {
    }

    /** @deprecated Kein Umschalten mehr nötig — es gibt nur eine Datenbank. */
    public function verlassen(): void
    {
    }

    /**
     * @deprecated $aufgabe direkt aufrufen.
     *
     * @template T
     * @param  callable(School): T  $aufgabe
     * @return T
     */
    public function mit(School $schule, callable $aufgabe): mixed
    {
        return $aufgabe($schule);
    }
}
