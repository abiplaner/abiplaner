<?php

namespace Database\Seeders;

use App\Services\BayernTermine;
use Illuminate\Database\Seeder;

/**
 * Bayernweite Termine beim ersten Start (23.11.2026).
 *
 * Ersetzt GlobalCalendarEventsSeeder, ExamPeriodSeeder, Abitur2027Seeder und
 * Kalender202627Seeder. Die Daten stehen jetzt in database/data/termine/;
 * dieser Seeder legt zusätzlich die Prüfungszeiträume bis zum laufenden
 * Schuljahr an. Spätere Starts gleichen über `termine:einspielen` ab.
 *
 * Muss nach WrittenExamSlotsSeeder laufen — die Prüfungstage hängen am
 * Slot-Katalog.
 */
class BayernTermineSeeder extends Seeder
{
    public function run(BayernTermine $termine): void
    {
        $bericht = $termine->einspielen(mitZeitraeumen: true);

        $this->command->info(sprintf(
            '✓ Bayernweite Termine: %d eingefügt, %d berichtigt; %d schriftliche Prüfungstermine',
            $bericht['neu'], $bericht['geaendert'], $bericht['schriftlich']
        ));

        foreach ($bericht['zeitraeume'] as $name) {
            $this->command->info("✓ Prüfungszeitraum „{$name}\" angelegt");
        }

        foreach ($bericht['warnungen'] as $warnung) {
            $this->command->warn('  ⚠ ' . $warnung);
        }
    }
}
