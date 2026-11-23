<?php

namespace App\Console\Commands;

use App\Services\BayernTermine;
use Illuminate\Console\Command;

/**
 * Gleicht die bayernweiten Termine mit database/data/termine/*.json ab.
 *
 * Läuft bei jedem Start über `abiplaner:vorbereiten`; so kommen neue
 * Schuljahre und Berichtigungen mit dem nächsten Programm-Update an.
 * Mehrfach aufrufbar — ein zweiter Lauf meldet „0 neu, 0 geändert".
 *
 *   php artisan termine:einspielen            abgleichen
 *   php artisan termine:einspielen --probe    nur anzeigen, was sich ändern würde
 */
class TermineEinspielen extends Command
{
    protected $signature = 'termine:einspielen
        {--probe : Nichts schreiben, nur berichten}
        {--zeitraeume : Fehlende Prüfungszeiträume bis zum laufenden Schuljahr anlegen}';

    protected $description = 'Bayernweite Termine (Ferien, Feiertage, Abitur) aus den Termindateien abgleichen';

    public function handle(BayernTermine $termine): int
    {
        try {
            $bericht = $termine->einspielen((bool) $this->option('zeitraeume'), (bool) $this->option('probe'));
        } catch (\Throwable $e) {
            // Beim Start soll ein Fehler in einer Termindatei die App nicht
            // blockieren — deshalb Meldung und FAILURE statt Ausnahme.
            $this->error('Termine nicht eingespielt: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(($this->option('probe') ? '[Probe] ' : '') . sprintf(
            'Termine: %d neu, %d geändert, %d entfernt, %d unverändert; %d schriftliche Prüfungstermine gesetzt.',
            $bericht['neu'], $bericht['geaendert'], $bericht['geloescht'], $bericht['unveraendert'], $bericht['schriftlich']
        ));

        foreach ($bericht['zeitraeume'] as $name) {
            $this->info("Prüfungszeitraum „{$name}\" angelegt.");
        }

        foreach ($bericht['warnungen'] as $warnung) {
            $this->warn('⚠ ' . $warnung);
        }

        return self::SUCCESS;
    }
}
