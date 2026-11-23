<?php

namespace App\Console\Commands;

use App\Models\SupportAnhang;
use App\Models\SupportFall;
use App\Services\SupportAnhangService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Raeumt Bildschirmfotos auf (Modul 6.4, Entscheidung 48).
 *
 * Der Regelweg ist der Abschluss des Falls — dort loescht der Controller. Der
 * Befehl ist die Gegenprobe fuer alles, was daran vorbeigekommen ist:
 *
 * 1. **Erledigte Faelle, an denen noch Bilder haengen.** Etwa weil der Status
 *    von Hand in der Datenbank gesetzt wurde oder ein Loeschen scheiterte.
 * 2. **Dateien ohne Eintrag.** Ein abgebrochener Upload, ein Absturz zwischen
 *    put() und create() — sie faenden sonst nie jemanden.
 *
 * Findet er nichts, sagt er das und tut nichts; er kann deshalb gefahrlos
 * taeglich laufen. `--probe` zeigt nur an.
 */
class SupportAnhaengeAufraeumen extends Command
{
    protected $signature = 'support:anhaenge-aufraeumen {--probe : Nur anzeigen, nichts loeschen}';

    protected $description = 'Loescht Bildschirmfotos erledigter Support-Faelle und verwaiste Dateien';

    public function handle(SupportAnhangService $dienst): int
    {
        $probe = (bool) $this->option('probe');
        $entfernt = 0;

        // 1. Erledigte Faelle, an denen noch Bilder haengen.
        $faelle = SupportFall::query()
            ->where('status', SupportFall::STATUS_ERLEDIGT)
            ->whereHas('anhaenge')
            ->get();

        foreach ($faelle as $fall) {
            $anzahl = $fall->anhaenge()->count();
            $this->line('Fall ' . $fall->id . ' (erledigt): ' . $anzahl . ' Bild(er)');

            if (! $probe) {
                $entfernt += $dienst->loesche($fall);
            }
        }

        // 2. Dateien ohne Eintrag.
        $bekannt = SupportAnhang::query()->pluck('pfad')->all();
        $verwaist = array_diff(
            Storage::disk(SupportAnhangService::DISK)->allFiles('support'),
            $bekannt
        );

        foreach ($verwaist as $pfad) {
            $this->line('Verwaist: ' . $pfad);

            if (! $probe) {
                Storage::disk(SupportAnhangService::DISK)->delete($pfad);
                $entfernt++;
            }
        }

        if ($faelle->isEmpty() && $verwaist === []) {
            $this->info('Nichts aufzuräumen.');

            return self::SUCCESS;
        }

        $this->info($probe
            ? 'Probe — nichts gelöscht.'
            : $entfernt . ' Datei(en) gelöscht.');

        return self::SUCCESS;
    }
}
