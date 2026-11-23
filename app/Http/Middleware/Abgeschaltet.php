<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt Routen, deren Funktion derzeit abgeschaltet ist (Support-Meldungen:
 * Sie liefen an die zentrale Web-Version, die Desktop-Fassung hat keinen
 * Empfänger).
 *
 * Die Routen bleiben registriert, damit route()-Aufrufe in Views nicht mit
 * "Route not defined" abbrechen; sie antworten mit 404.
 */
class Abgeschaltet
{
    public function handle(Request $request, Closure $next): Response
    {
        abort(404);
    }
}
