<?php

namespace App\Console\Commands;

use App\Services\HilfeDeckungsPruefung;
use App\Services\HilfeService;
use Illuminate\Console\Command;

/**
 * Prueft die Deckung zwischen Anwendung und Hilfesystem (Roadmap 6.3).
 *
 * Die CI prueft dasselbe gegen die **Grundfassung**. Dieser Befehl prueft den
 * Bestand einer **laufenden Installation** — die beiden koennen auseinander
 * liegen, seit die Kapitel redigierbar sind. Gedacht fuer nach dem Deploy und
 * fuer den Fall, dass jemand ein Kapitel geloescht hat.
 */
class HilfePruefen extends Command
{
    protected $signature = 'hilfe:pruefen';

    protected $description = 'Meldet Masken ohne Hilfekapitel und Kapitel mit toten Routenverweisen';

    public function handle(HilfeService $hilfe, HilfeDeckungsPruefung $pruefung): int
    {
        $kapitel = $hilfe->alle();

        if ($kapitel === []) {
            $this->error('Es ist kein einziges Kapitel hinterlegt — "php artisan hilfe:einspielen" fehlt vermutlich.');

            return self::FAILURE;
        }

        $luecken = $pruefung->luecken();
        $tote = $pruefung->toteVerweise();

        $this->info(sprintf(
            '%d Kapitel, %d Seiten geprueft.',
            count($kapitel),
            count($pruefung->seitenrouten()),
        ));

        foreach ($luecken as $name) {
            $this->warn('  Ohne Kapitel: ' . $name);
        }

        foreach ($tote as $eintrag) {
            $this->warn('  Toter Routenverweis: ' . $eintrag);
        }

        if ($luecken === [] && $tote === []) {
            $this->info('Die Hilfe deckt jede Maske ab, und kein Kapitel zeigt ins Leere.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            '%d Seite(n) ohne Kapitel, %d toter Routenverweis(e).',
            count($luecken),
            count($tote),
        ));

        return self::FAILURE;
    }
}
