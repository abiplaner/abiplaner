<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Gerätübergreifende Rückkehr zur zuletzt besuchten Seite (Modul 1.6).
 *
 * Speichert je Nutzer (zentrale users-Tabelle) den zuletzt besuchten Pfad,
 * damit ein Login auf einem anderen Gerät wieder dort landet.
 */
class LastVisitedPageService
{
    /**
     * Ausgeschlossene Routennamen-Suffixe: Formular-Zwischenschritte, auf
     * denen man nach einem neuen Login nicht "hängen bleiben" soll, sondern
     * lieber die zugehörige Übersichtsseite wiederfindet.
     *
     * @var list<string>
     */
    private const EXCLUDED_NAME_SUFFIXES = ['.create', '.edit'];

    /**
     * Trägt den aktuellen Pfad als "zuletzt besucht" ein, sofern die Anfrage
     * dafür in Frage kommt (siehe shouldTrack()).
     */
    public function track(Request $request, User $user, BaseResponse $response): void
    {
        if (! $this->shouldTrack($request, $response)) {
            return;
        }

        $path = '/' . ltrim($request->path(), '/');

        if ($user->last_visited_path === $path) {
            return;
        }

        $user->forceFill(['last_visited_path' => $path])->saveQuietly();
    }

    /**
     * Liefert die Zielroute nach dem Login: die zuletzt besuchte Seite, sofern
     * sie noch einer existierenden Route entspricht, sonst $default.
     */
    public function destinationFor(User $user, string $default): string
    {
        $path = $user->last_visited_path;

        if ($path === null || $path === '') {
            return $default;
        }

        if (! $this->routeStillExists($path)) {
            return $default;
        }

        return $path;
    }

    private function shouldTrack(Request $request, BaseResponse $response): bool
    {
        // Nur echte Vollseiten-Navigation, keine AJAX/Fetch-Aufrufe.
        if (! $request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        // Nur erfolgreiche Antworten merken (kein 403/404/500).
        if (! $response->isSuccessful()) {
            return false;
        }

        // Exporte/Downloads (PDF, Excel, ...) sind keine "Seiten" im Sinne
        // dieser Funktion. Generisch erkannt statt über eine Liste einzelner
        // Export-Routen, damit künftige Export-Endpunkte automatisch mit
        // erfasst sind:
        // - BinaryFileResponse/StreamedResponse (Datei-Downloads, Excel) sind
        //   keine Illuminate\Http\Response.
        // - DomPDF-Antworten sind zwar Illuminate\Http\Response, setzen aber
        //   den Content-Type-Header explizit (z.B. 'application/pdf') schon
        //   vor dem Senden.
        // Normale Blade-View-Antworten setzen an dieser Stelle (vor
        // Response::prepare()/send()) i.d.R. noch KEINEN Content-Type-Header
        // – ein fehlender Header wird daher als "HTML-Seite" gewertet, ein
        // explizit gesetzter Nicht-HTML-Header schließt die Anfrage aus.
        if (! $response instanceof Response) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type');
        if ($contentType !== null && ! str_starts_with($contentType, 'text/html')) {
            return false;
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null) {
            foreach (self::EXCLUDED_NAME_SUFFIXES as $suffix) {
                if (str_ends_with($routeName, $suffix)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function routeStillExists(string $path): bool
    {
        try {
            app('router')->getRoutes()->match(Request::create($path, 'GET'));

            return true;
        } catch (\Throwable) {
            // Route existiert nicht mehr (z.B. gelöschte Ressource) oder
            // Pfad ist anderweitig ungültig – dann lieber auf den Default
            // zurückfallen als auf eine kaputte Seite zu leiten.
            return false;
        }
    }
}
