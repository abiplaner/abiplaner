<?php

namespace App\Console\Commands;

use App\Services\SupportBenachrichtigungsService;
use Illuminate\Console\Command;

/**
 * Holt gescheiterte Eskalationsmails nach (Modul 6.4, Stufe 1).
 *
 * **Die Gegenprobe zur Reihenfolge „erst speichern, dann senden":** Ohne
 * diesen Befehl waere eine gescheiterte Mail eine verlorene Meldung. Mit ihm
 * ist sie eine Verzoegerung. Findet er nichts, sagt er das und tut nichts —
 * er kann deshalb gefahrlos regelmaessig laufen.
 */
class SupportNachbenachrichtigen extends Command
{
    protected $signature = 'support:nachbenachrichtigen';

    protected $description = 'Sendet Eskalationsmails zu Support-Faellen nach, deren Versand gescheitert ist';

    public function handle(SupportBenachrichtigungsService $dienst): int
    {
        $ausstehend = $dienst->ausstehende();

        if ($ausstehend->isEmpty()) {
            $this->info('Keine ausstehenden Benachrichtigungen.');

            return self::SUCCESS;
        }

        $gelungen = 0;

        foreach ($ausstehend as $fall) {
            if ($dienst->benachrichtige($fall)) {
                $gelungen++;
                $this->line('Fall ' . $fall->id . ': zugestellt.');
            } else {
                $this->warn('Fall ' . $fall->id . ': weiterhin gescheitert.');
            }
        }

        $this->info($gelungen . ' von ' . $ausstehend->count() . ' nachgeholt.');

        // Wer den Befehl aus einem Skript ruft, will das Scheitern sehen.
        return $gelungen === $ausstehend->count() ? self::SUCCESS : self::FAILURE;
    }
}
