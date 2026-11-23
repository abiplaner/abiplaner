<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Die Content-Security-Policy für dynamische Antworten — Modul 7.2, Stufe 2.
 *
 * Seit dem 30.08.2026 kommt der Header von hier und nicht mehr aus nginx. Der
 * Grund ist zwingend: Die Nonce wechselt pro Request, ein statischer
 * nginx-Header kann sie nicht tragen. `docker/nginx/csp-statisch.conf` deckt
 * weiterhin die Antworten ab, die nie durch PHP laufen (Dateien aus public/,
 * nginx-eigene Fehlerseiten).
 *
 * Die Nonce liegt in der Vite-Instanz und ist von dort aus zweifach erreichbar:
 * `@vite` hängt sie selbst an die erzeugten <script>-Tags, eigene Inline-Blöcke
 * holen sie über die Blade-Direktive `@cspNonce` (siehe AppServiceProvider).
 *
 * Was der Schritt bringt: Solange `'unsafe-inline'` bei script-src stand,
 * schützte die Direktive praktisch nicht — ein eingeschleustes <script> war
 * erlaubt. Mit der Nonce ist es das nicht mehr. Voraussetzung war Stufe 1
 * (`0a4e1dc`): kein einziger Inline-Handler in den Views, jeder eigene
 * Inline-Block trägt `@cspNonce`. `ContentSecurityPolicyTest` hält beides fest.
 *
 * `'unsafe-eval'` bleibt: Alpine wertet seine `x-`Ausdrücke über `Function()`
 * aus. Es zu entfernen hieße, jede Inline-Expression in registrierte
 * `Alpine.data()`-Komponenten auszulagern — bewusst verworfen (siehe Roadmap
 * 7.2). `style-src 'unsafe-inline'` bleibt ebenfalls; das Modul zielt auf
 * script-src.
 */
class ContentSecurityPolicy
{
    /**
     * Unter welcher Kopfzeile die Politik hinausgeht — der einzige Schalter
     * dieses Moduls und zugleich der Weg zurück.
     *
     * `Content-Security-Policy` erzwingt. `Content-Security-Policy-Report-Only`
     * schickt denselben Text, ohne etwas zu blockieren: Der Browser meldet nur,
     * was er blockiert *hätte* — die Meldungen laufen weiter über report-uri.
     * Fällt im Alltag etwas stumm aus, ist das hier ein Wort, kein Rückbau.
     */
    private const KOPFZEILE = 'Content-Security-Policy';

    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        // Nur setzen, wenn nicht schon eine dasteht — eine Antwort, die ihre
        // eigene Politik mitbringt, soll sie behalten dürfen.
        if (!$response->headers->has(self::KOPFZEILE)) {
            $response->headers->set(self::KOPFZEILE, $this->politik());
        }

        return $response;
    }

    /**
     * Der Text der Politik, mit der Nonce dieses Requests.
     */
    private function politik(): string
    {
        $nonce = Vite::cspNonce();

        // Ohne Nonce keine Nonce-Quelle: `'nonce-'` wäre eine Quelle, auf die
        // nichts passt — die Seite fiele vollständig aus. Im Regelbetrieb kommt
        // das nicht vor (useCspNonce() steht oben); ein ausgetauschter
        // Vite-Zustand soll die Oberfläche trotzdem nicht töten.
        $skriptQuellen = $nonce !== null && $nonce !== ''
            ? "'self' 'nonce-{$nonce}' 'unsafe-eval'"
            : "'self' 'unsafe-inline' 'unsafe-eval'";

        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src {$skriptQuellen}",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            // Ein blockierter Handler erzeugt keine Fehlermeldung, sondern
            // einen Knopf, der nichts tut. Ohne Meldestelle wäre der einzige
            // Hinweis darauf die Browser-Konsole dessen, der gerade davorsitzt.
            'report-uri ' . route('csp.bericht', absolute: false),
        ]);
    }
}
