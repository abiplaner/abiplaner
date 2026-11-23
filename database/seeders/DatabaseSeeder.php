<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Stammdaten (Fächer, Perioden, Termine, Hilfe). Die Grunddaten der Schule
     * (Räume, Zeitraster, Vorlagen) spielt der TenantSeeder bei der
     * Ersteinrichtung ein.
     */
    public function run(): void
    {
        $this->call([
            SubjectSeeder::class,                        // Offizieller Fächerkatalog Bayern (mit §61 GSO)
            ExternalExamRequirementsSeeder::class,       // §61 GSO Anforderungen für externe Bewerber
            GlobalCalendarEventsSeeder::class,           // Zentrale Termine für alle Schulen
            ExamPeriodSeeder::class,                     // Kolloquiumsperioden
            WrittenExamSlotsSeeder::class,               // Ministerielle Prüfungszeiten + Termine 2026
            Abitur2027Seeder::class,                     // Periode, schriftliche Termine und Kalender 2026/27
            Kalender202627Seeder::class,                 // Fristen, Zeugnistermine und Feiertage 2026/27
            HilfeKapitelSeeder::class,                   // Grundfassung der Hilfekapitel (Modul 6.3)
        ]);

        $this->command->info('');
        $this->command->info('==============================================');
        $this->command->info('✓ Stammdaten eingespielt.');
        $this->command->info('==============================================');
        $this->command->info('');
        $this->command->info('Die Schule selbst legt der Einrichtungsassistent an (/ersteinrichtung).');
        $this->command->info('');
    }
}
