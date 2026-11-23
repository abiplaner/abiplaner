<?php

namespace App\Services;

use App\Mail\SupportFallGemeldet;
use App\Models\SupportFall;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Die Eskalation einer Support-Meldung per Mail (Modul 6.4, Stufe 1).
 *
 * **Der ganze Zweck dieser Klasse ist die Reihenfolge: erst speichern, dann
 * senden.** `QUEUE_CONNECTION=sync` — der Versand laeuft im Request. Eine
 * hakelnde SMTP-Verbindung darf deshalb nicht die Meldung kosten, deren
 * Absenden vielleicht der einzige Weg ist, auf ein kaputtes System
 * hinzuweisen.
 *
 * Der Erfolg steht in `benachrichtigt_am`. Bleibt die Spalte leer, holt
 * `php artisan support:nachbenachrichtigen` den Versand nach — eine
 * gescheiterte Mail ist damit eine Verzoegerung und kein Datenverlust.
 *
 * Ist kein Empfaenger konfiguriert, ist das **kein Fehler**: Faelle stehen
 * dann nur im Dashboard. Der Fall gilt als zugestellt, damit ihn der
 * Nachhol-Befehl nicht in Ewigkeit erneut versucht.
 */
class SupportBenachrichtigungsService
{
    /**
     * @return bool ob der Fall jetzt als benachrichtigt gilt
     */
    public function benachrichtige(SupportFall $fall): bool
    {
        $empfaenger = trim((string) config('support.empfaenger', ''));

        if ($empfaenger === '') {
            $fall->forceFill(['benachrichtigt_am' => now()])->save();

            Log::info('Support-Fall ohne Mailversand abgelegt (kein Empfaenger konfiguriert).', [
                'fall' => $fall->id,
            ]);

            return true;
        }

        try {
            Mail::to($empfaenger)->send(new SupportFallGemeldet($fall));
        } catch (Throwable $e) {
            // Bewusst geschluckt: Der Fall ist gespeichert, und genau darum
            // ging es. Der Nachhol-Befehl findet ihn ueber die leere Spalte.
            Log::warning('Eskalationsmail zum Support-Fall gescheitert.', [
                'fall' => $fall->id,
                'fehler' => $e->getMessage(),
            ]);

            return false;
        }

        $fall->forceFill(['benachrichtigt_am' => now()])->save();

        return true;
    }

    /**
     * Faelle, deren Benachrichtigung noch aussteht.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SupportFall>
     */
    public function ausstehende(): \Illuminate\Database\Eloquent\Collection
    {
        return SupportFall::query()
            ->whereNull('benachrichtigt_am')
            ->orderBy('id')
            ->get();
    }
}
