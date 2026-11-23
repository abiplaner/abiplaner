<?php

namespace App\Console\Commands;

use App\Models\School;
use Database\Seeders\TestschuleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Legt die Testschule an oder befuellt sie neu (Roadmap 1.3).
 *
 * Die Schule wird bei Bedarf als eigener Tenant erzeugt — inklusive
 * Tenant-Datenbank, Migrationen und Grunddaten aus dem TenantSeeder. Danach
 * laeuft der TestschuleSeeder, der den kompletten Pruefungsjahrgang erzeugt.
 *
 * Ein erneuter Aufruf leert die betroffenen Tabellen und befuellt sie neu;
 * bei gleichem Seed entsteht derselbe Datenbestand.
 */
class CreateTestSchool extends Command
{
    protected $signature = 'testschule:erstellen
                            {--school-number=9990 : 4-stellige Schulnummer der Testschule}
                            {--name=Testgymnasium Musterstadt : Name der Testschule}
                            {--short-name=TGM : Kurzname der Testschule}
                            {--seed= : Zufallssaat; gleicher Wert erzeugt denselben Datenbestand}';

    protected $description = 'Erzeugt eine Testschule mit vollständigem, erfundenem Prüfungsjahrgang';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('❌ Die Testschule darf nicht in der Produktivumgebung angelegt werden.');

            return self::FAILURE;
        }

        $schoolNumber = (string) $this->option('school-number');

        if (!preg_match('/^[0-9]{4}$/', $schoolNumber)) {
            $this->error('❌ Die Schulnummer muss genau vier Ziffern haben.');

            return self::FAILURE;
        }

        $seed = $this->option('seed') !== null && $this->option('seed') !== ''
            ? (int) $this->option('seed')
            : TestschuleSeeder::STANDARD_SEED;

        config(['testschule.seed' => $seed]);

        // Es gibt genau eine Schule. Ist schon eine andere eingerichtet, wäre
        // die Testschule eine zweite — das lässt das Datenmodell nicht mehr zu.
        $vorhanden = School::query()->first();
        if ($vorhanden instanceof School && $vorhanden->school_number !== $schoolNumber) {
            $this->error("❌ Es ist bereits die Schule {$vorhanden->school_number} eingerichtet.");

            return self::FAILURE;
        }

        $school = $vorhanden;

        if ($school instanceof School) {
            $this->info("🏫 Vorhandene Testschule {$schoolNumber} wird neu befüllt.");
        } else {
            $this->info("🏫 Testschule {$schoolNumber} wird angelegt (Grunddaten).");

            $school = School::create([
                'name' => (string) $this->option('name'),
                'short_name' => (string) $this->option('short-name'),
                'school_number' => $schoolNumber,
                'city' => 'Musterstadt',
                'postal_code' => '80331',
                'state' => 'Bayern',
                'is_active' => true,
            ]);
        }

        $this->newLine();

        if ($school->wasRecentlyCreated) {
            Artisan::call('db:seed', ['--class' => 'TenantSeeder', '--force' => true], $this->getOutput());
        }

        $exitCode = Artisan::call('db:seed', [
            '--class' => 'TestschuleSeeder',
        ], $this->getOutput());

        if ($exitCode !== 0) {
            $this->error('❌ Der TestschuleSeeder ist fehlgeschlagen.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("✅ Testschule {$schoolNumber} steht bereit (Seed {$seed}).");

        return self::SUCCESS;
    }
}
