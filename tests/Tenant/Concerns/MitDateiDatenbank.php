<?php

namespace Tests\Tenant\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Datenbank als echte Datei statt :memory:, ohne umschließende Transaktion.
 *
 * Für Tests, die Sicherungen anlegen oder zurückspielen: Die Sicherung liest
 * die Datei über eine eigene Verbindung (VACUUM INTO), und die
 * Wiederherstellung tauscht die Datei aus. Beides geht mit :memory: und
 * innerhalb der Test-Transaktion von RefreshDatabase nicht.
 *
 * Überschreibt refreshDatabase() aus RefreshDatabase: frische Datei je Test,
 * am Ende samt Sicherungsordner wieder gelöscht.
 */
trait MitDateiDatenbank
{
    public function refreshDatabase()
    {
        $ordner = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'abiplaner-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($ordner, 0775, true);
        $datei = $ordner . DIRECTORY_SEPARATOR . 'abiplaner.sqlite';
        touch($datei);

        config(['database.connections.sqlite.database' => $datei]);
        DB::purge('sqlite');

        $this->artisan('migrate', ['--force' => true]);

        $this->beforeApplicationDestroyed(function () use ($ordner): void {
            DB::disconnect();
            self::ordnerLoeschen($ordner);
        });
    }

    private static function ordnerLoeschen(string $ordner): void
    {
        foreach (scandir($ordner) ?: [] as $eintrag) {
            if ($eintrag === '.' || $eintrag === '..') {
                continue;
            }
            $pfad = $ordner . DIRECTORY_SEPARATOR . $eintrag;
            is_dir($pfad) ? self::ordnerLoeschen($pfad) : @unlink($pfad);
        }
        @rmdir($ordner);
    }
}
