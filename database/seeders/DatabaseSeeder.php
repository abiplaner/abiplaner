<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Stammdaten (Fächer, Slot-Katalog, Termine und Prüfungszeiträume, Hilfe). Die Grunddaten der Schule
     * (Räume, Zeitraster, Vorlagen) spielt der TenantSeeder bei der
     * Ersteinrichtung ein.
     */
    public function run(): void
    {
        $this->call([
            SubjectSeeder::class,                        // Offizieller Fächerkatalog Bayern (mit §61 GSO)
            ExternalExamRequirementsSeeder::class,       // §61 GSO Anforderungen für externe Bewerber
            WrittenExamSlotsSeeder::class,               // Katalog der schriftlichen Prüfungsslots (jahresunabhängig)
            BayernTermineSeeder::class,                  // Ferien, Feiertage, Abitur aus database/data/termine
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
