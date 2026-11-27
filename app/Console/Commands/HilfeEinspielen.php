<?php

namespace App\Console\Commands;

use Database\Seeders\HilfeKapitelSeeder;
use Illuminate\Console\Command;

/**
 * Spielt die Grundfassung der Hilfekapitel ein (Roadmap 6.3).
 *
 * Läuft bei jedem Start (abiplaner:vorbereiten). Die Markdown-Dateien im
 * Repository sind die einzige Quelle; die Datenbank wird auf ihren Stand
 * gebracht.
 */
class HilfeEinspielen extends Command
{
    protected $signature = 'hilfe:einspielen';

    protected $description = 'Bringt die Hilfekapitel auf den Stand der mitgelieferten Dateien';

    public function handle(): int
    {
        $seeder = new HilfeKapitelSeeder();

        if ($seeder->grundfassung() === []) {
            $this->error('Unter database/seeders/data/hilfe/ liegt keine lesbare Kapiteldatei.');

            return self::FAILURE;
        }

        $seeder->run();

        $this->info(sprintf(
            '%d neu, %d aktualisiert, %d unveraendert, %d entfernt.',
            $seeder->bilanz['neu'],
            $seeder->bilanz['aktualisiert'],
            $seeder->bilanz['unveraendert'],
            $seeder->bilanz['entfernt'],
        ));

        return self::SUCCESS;
    }
}
