<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bereitet die Datenbank vor jedem Start vor. Die Desktop-App ruft den
 * Befehl auf, bevor sie das Fenster öffnet, der Docker-Container beim Start.
 * Mehrfach aufrufbar:
 *
 *   1. Ordner und Datenbankdatei anlegen, falls sie fehlen.
 *   2. Stehen Migrationen an (Programm-Update), vorher sichern — so ist der
 *      Stand vor dem Update immer wiederherstellbar.
 *   3. Migrationen einspielen.
 *   4. Beim allerersten Start die Stammdaten (DatabaseSeeder), sonst die
 *      Hilfekapitel auffrischen (hilfe:einspielen lässt redigierte stehen)
 *      und die bayernweiten Termine abgleichen (termine:einspielen).
 *   5. Einmal täglich eine automatische Sicherung.
 *
 * Die Schule selbst legt der Einrichtungsassistent an.
 */
class Vorbereiten extends Command
{
    protected $signature = 'abiplaner:vorbereiten';

    protected $description = 'Datenbank anlegen, vor Updates sichern, migrieren, Stammdaten einspielen';

    public function handle(Migrator $migrator): int
    {
        $datei = (string) config('database.connections.sqlite.database');
        $ordner = dirname($datei);

        if (! is_dir($ordner) && ! mkdir($ordner, 0775, true) && ! is_dir($ordner)) {
            $this->error("Datenordner {$ordner} lässt sich nicht anlegen.");

            return self::FAILURE;
        }

        if (! file_exists($datei)) {
            touch($datei);
            $this->info("Neue Datenbank angelegt: {$datei}");
        }

        $ersterStart = ! Schema::hasTable('migrations');
        $hatSchule = ! $ersterStart && Schema::hasTable('schools') && DB::table('schools')->exists();

        // Vor einem Update sichern: Gibt es Migrationen, die noch nicht liefen?
        if ($hatSchule && $this->stehenMigrationenAn($migrator)) {
            $this->info('Programm-Update erkannt: Sicherung vor dem Einspielen …');
            if ($this->call('abiplaner:sichern', ['--art' => 'vor-update', '--keep' => 10]) !== self::SUCCESS) {
                $this->error('Ohne Sicherung kein Update der Datenbank. Abbruch.');

                return self::FAILURE;
            }
        }

        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        // Zweites Merkmal für den ersten Start (falls er beim Seeden abbrach):
        // keine Prüfungsperiode.
        if ($ersterStart || DB::table('exam_periods')->doesntExist()) {
            $this->info('Erster Start: Stammdaten werden eingespielt …');
            $this->call('db:seed', ['--force' => true]);
        } else {
            $this->call('hilfe:einspielen');
            // Neue Schuljahre und Berichtigungen aus database/data/termine.
            // Ein Fehler darin meldet der Befehl selbst; der Start geht weiter.
            $this->call('termine:einspielen');
        }

        if ($hatSchule && ! $this->heuteSchonGesichert()) {
            $this->call('abiplaner:sichern', ['--art' => 'auto', '--keep' => 14]);
        }

        return self::SUCCESS;
    }

    private function stehenMigrationenAn(Migrator $migrator): bool
    {
        $gelaufen = DB::table('migrations')->pluck('migration')->all();
        $vorhanden = array_keys($migrator->getMigrationFiles([database_path('migrations')]));

        return array_diff($vorhanden, $gelaufen) !== [];
    }

    private function heuteSchonGesichert(): bool
    {
        return collect(app(\App\Services\TenantBackupService::class)->listBackups())
            ->contains(fn (array $b): bool => str_contains($b['filename'], '_auto.')
                && $b['created_at']->isToday());
    }
}
