<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lokale Edition: Solange keine Schule angelegt ist, führt jeder Aufruf zum
 * Einrichtungsassistenten (ErsteinrichtungController). In der Web-Version
 * tut die Middleware nichts.
 *
 * Hängt an der web-Gruppe (bootstrap/app.php). Die Prüfung ist eine
 * COUNT-Abfrage auf eine Tabelle mit genau einer Zeile — vernachlässigbar.
 */
class ErsteinrichtungErzwingen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('ersteinrichtung.*', 'csp.bericht', 'calendar.feed*')) {
            return $next($request);
        }

        if (! School::query()->exists()) {
            return redirect()->route('ersteinrichtung.show');
        }

        return $next($request);
    }
}
