<?php

namespace App\Console\Commands;

use Database\Seeders\HilfeKapitelSeeder;
use Illuminate\Console\Command;

/**
 * Spielt die Grundfassung der Hilfekapitel ein (Roadmap 6.3).
 *
 * Derselbe Seeder, den die Migration ruft — hier von Hand, wenn im Repo neue
 * Kapitel dazugekommen sind. **Redigierte Kapitel bleiben unangetastet**; der
 * Lauf sagt, wie viele das waren.
 */
class HilfeEinspielen extends Command
{
    protected $signature = 'hilfe:einspielen';

    protected $description = 'Spielt neue und unveraenderte Hilfekapitel aus der Grundfassung ein';

    public function handle(): int
    {
        $seeder = new HilfeKapitelSeeder();

        if ($seeder->grundfassung() === []) {
            $this->error('Unter database/seeders/data/hilfe/ liegt keine lesbare Kapiteldatei.');

            return self::FAILURE;
        }

        $seeder->run();

        $this->info(sprintf(
            '%d neu, %d aktualisiert, %d unveraendert, %d redigiert (nicht angefasst).',
            $seeder->bilanz['neu'],
            $seeder->bilanz['aktualisiert'],
            $seeder->bilanz['unveraendert'],
            $seeder->bilanz['redigiert'],
        ));

        return self::SUCCESS;
    }
}
