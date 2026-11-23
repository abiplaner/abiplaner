<?php

namespace App\Console\Commands;

use App\Services\HilfeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Schreibt das Benutzerhandbuch als Markdown-Datei (Roadmap 6.3).
 *
 * Das Handbuch ist **kein zweites Dokument**, sondern eine andere Ausgabe der
 * Kapitel aus `hilfe_kapitel`. Es liegt deshalb auch nicht mehr im Repo: Eine
 * Datei neben einer redigierbaren Datenbank waere der veraltende Zweitabzug,
 * den dieses Modul gerade abgeschafft hat. Wer eine Datei braucht, erzeugt sie
 * hier — oder laedt das PDF unter `/hilfe` herunter.
 */
class HandbuchErzeugen extends Command
{
    protected $signature = 'handbuch:erzeugen
                            {--ausgabe=storage/app/BENUTZERHANDBUCH.md : Zieldatei, relativ zum Projektverzeichnis}';

    protected $description = 'Schreibt das Benutzerhandbuch aus den Kapiteln des Hilfesystems in eine Datei';

    public function handle(HilfeService $hilfe): int
    {
        $kapitel = $hilfe->alle();

        if ($kapitel === []) {
            $this->error('Keine Kapitel gefunden. Grundfassung einspielen mit "php artisan hilfe:einspielen".');

            return self::FAILURE;
        }

        $ziel = base_path((string) $this->option('ausgabe'));
        $inhalt = $hilfe->handbuchMarkdown();

        File::ensureDirectoryExists(dirname($ziel));
        File::put($ziel, $inhalt);

        $this->info(sprintf(
            '%d Kapitel in %d Gruppen geschrieben nach %s (%d Zeilen).',
            count($kapitel),
            count($hilfe->gruppen()),
            (string) $this->option('ausgabe'),
            substr_count($inhalt, "\n"),
        ));

        return self::SUCCESS;
    }
}
