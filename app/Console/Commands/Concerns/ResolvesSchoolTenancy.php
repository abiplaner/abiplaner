<?php

namespace App\Console\Commands\Concerns;

use App\Models\School;

/**
 * Prüft vor einem Schul-Befehl, dass die Schule eingerichtet ist.
 *
 * Aus der Mandantenzeit: Früher wählte --school=<Nummer> die Datenbank.
 * Heute gibt es genau eine Schule; eine angegebene Nummer muss zu ihr passen.
 */
trait ResolvesSchoolTenancy
{
    private function resolveSchoolAndStartTenancy(?string $schoolNumber, bool &$tenancyStarted): ?int
    {
        $tenancyStarted = false;
        $schule = School::query()->first();

        if (! $schule instanceof School) {
            $this->error('Es ist noch keine Schule eingerichtet.');

            return self::FAILURE;
        }

        $schoolNumber = trim((string) $schoolNumber);
        if ($schoolNumber !== '' && $schoolNumber !== $schule->school_number) {
            $this->error("Schule {$schoolNumber} nicht gefunden (eingerichtet ist {$schule->school_number}).");

            return self::FAILURE;
        }

        return null;
    }
}
