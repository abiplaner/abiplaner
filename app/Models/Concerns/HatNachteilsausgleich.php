<?php

namespace App\Models\Concerns;

/**
 * Nachteilsausgleich eines Prüflings.
 *
 * Er steht als Prozentsatz an der Person — bei regulären Schülern an
 * `students`, bei externen Bewerbern an `external_applicants`. Die Rechnung
 * ist dieselbe und steht deshalb genau einmal hier: eine zweite Kopie liefe
 * früher oder später auseinander, und bei Prüfungszeiten fällt das erst am
 * Prüfungstag auf.
 *
 * Voraussetzung: die Tabelle führt `disability_compensation_percent`.
 */
trait HatNachteilsausgleich
{
    public function hasDisabilityCompensation(): bool
    {
        return $this->disability_compensation_percent !== null
            && $this->disability_compensation_percent > 0;
    }

    /**
     * Die um den Nachteilsausgleich verlängerte Dauer in Minuten.
     *
     * Abgerundet — so war es für reguläre Schüler seit jeher, und ein
     * Wechsel auf Runden würde bestehende Pläne verschieben.
     */
    public function getCompensatedDuration(int $baseDuration): int
    {
        if (! $this->hasDisabilityCompensation()) {
            return $baseDuration;
        }

        return (int) ($baseDuration * (1 + $this->disability_compensation_percent / 100));
    }
}
