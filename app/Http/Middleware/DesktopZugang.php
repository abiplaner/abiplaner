<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Riegel der Desktop-App: Die lokale Instanz antwortet nur dem Fenster, das
 * sie gestartet hat.
 *
 * Die App startet den Webserver auf 127.0.0.1 und einem freien Port. Ohne
 * Riegel käme jeder Prozess und jeder Benutzer desselben Rechners (Terminal-
 * server!) mit einem Browser an die Schülerdaten. Deshalb erzeugt die App bei
 * jedem Start ein Zufalls-Token (ABIPLANER_DESKTOP_TOKEN) und öffnet zuerst
 * /desktop/start?token=…; das setzt ein Cookie, ohne das jede weitere Anfrage
 * mit 403 endet.
 *
 * Ohne gesetztes Token (Docker, Entwicklung) tut die Middleware nichts.
 */
class DesktopZugang
{
    public const COOKIE = 'abiplaner_desktop';

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('abiplaner.desktop_token', '');

        if ($token === '') {
            return $next($request);
        }

        $erwartet = hash('sha256', $token);

        if ($request->routeIs('desktop.start')) {
            if (! hash_equals($token, (string) $request->query('token', ''))) {
                abort(403, 'Ungültiger Startschlüssel.');
            }

            // SameSite=Lax, nicht Strict: Der erste Aufruf kommt von der
            // eingebauten Startseite (tauri://) und gilt als seitenübergreifend —
            // mit Strict schickte WebKit das Cookie bei der Weiterleitung nicht
            // mit, und das eigene Fenster sah nur die 403 (22.11.2026).
            return redirect('/')->withCookie(cookie(
                self::COOKIE, $erwartet, 0, '/', null, false, true, false, 'lax'
            ));
        }

        if (! hash_equals($erwartet, (string) $request->cookie(self::COOKIE, ''))) {
            abort(403, 'Abiplaner ist nur über die Abiplaner-App erreichbar.');
        }

        return $next($request);
    }
}
