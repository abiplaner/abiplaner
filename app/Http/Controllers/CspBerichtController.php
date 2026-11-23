<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Die Meldestelle der Content-Security-Policy (Modul 7.2, Stufe 2).
 *
 * Warum es sie gibt: Ein von der CSP blockiertes Skript erzeugt keine
 * Fehlermeldung, sondern einen Knopf, der nichts tut. Ohne diese Route wäre der
 * einzige Hinweis darauf die Browser-Konsole desjenigen, der gerade davorsitzt
 * — und der ruft an, statt sie zu öffnen. Mit ihr steht der Verstoß im Log,
 * mit Seite, Direktive und der blockierten Quelle.
 *
 * Der Browser schickt hierher ohne CSRF-Token und ohne Anmeldung (die Meldung
 * entsteht im Browser, nicht in der Anwendung). Beides ist in `bootstrap/app.php`
 * bzw. hier berücksichtigt; die Route ist eng gedrosselt und die Antwort leer.
 */
class CspBerichtController extends Controller
{
    /**
     * Felder, die aus dem Bericht ins Log gehen.
     *
     * Bewusst eine feste Liste und nicht der ganze Rumpf: `script-sample`
     * enthält einen Ausschnitt des blockierten Codes und kann alles Mögliche
     * tragen, `referrer` ganze URLs mit Parametern. Was hier nicht steht,
     * landet auch nicht im Log.
     */
    private const FELDER = [
        'document-uri',
        'violated-directive',
        'effective-directive',
        'blocked-uri',
        'line-number',
        'source-file',
        'disposition',
    ];

    public function __invoke(Request $request): Response
    {
        $bericht = $request->json('csp-report');

        if (!is_array($bericht)) {
            // Kein verwertbarer Rumpf — trotzdem 204, damit der Browser nicht
            // wiederholt. Ein Fehlerstatus brächte hier nichts ein.
            return response()->noContent();
        }

        $gefiltert = [];
        foreach (self::FELDER as $feld) {
            if (array_key_exists($feld, $bericht)) {
                $gefiltert[$feld] = is_scalar($bericht[$feld])
                    ? mb_substr((string) $bericht[$feld], 0, 500)
                    : null;
            }
        }

        Log::channel('single')->warning('CSP-Verstoß gemeldet', $gefiltert);

        return response()->noContent();
    }
}
