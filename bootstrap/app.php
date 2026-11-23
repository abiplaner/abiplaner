<?php

use App\Services\FehlerKennung;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::group([], base_path('routes/schule.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security-Checkliste M6: Nicht jedem Client blind als Proxy vertrauen.
        // Traefik läuft im selben Docker-Netzwerk; private Docker-/RFC1918-Bereiche
        // statt '*' eintragen, damit X-Forwarded-* Header nicht von außen gefälscht werden können.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);
        
        // CSP-Nonce und CSP-Header pro Request (Modul 7.2, Stufe 2). Seit dem
        // 30.08.2026 kommt der Header von hier statt aus nginx — ein statischer
        // Header kann die pro Request wechselnde Nonce nicht tragen.
        // Desktop-App: nur das eigene Fenster darf an die lokale Instanz.
        // Vor allem anderen, auch vor der Ersteinrichtung.
        $middleware->web(prepend: [
            \App\Http\Middleware\DesktopZugang::class,
        ]);
        $middleware->encryptCookies(except: [
            \App\Http\Middleware\DesktopZugang::COOKIE,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ContentSecurityPolicy::class,
            // Ohne eingerichtete Schule zum Einrichtungsassistenten.
            \App\Http\Middleware\ErsteinrichtungErzwingen::class,
        ]);

        // Die CSP-Meldestelle bekommt ihren Rumpf vom Browser, nicht aus einem
        // Formular — es gibt dort kein CSRF-Token zu schicken. Der Endpunkt
        // nimmt nichts entgegen, was er speichert oder ausführt.
        $middleware->validateCsrfTokens(except: [
            'csp-bericht',
        ]);

        $middleware->alias([
            'check.role' => \App\Http\Middleware\CheckRole::class,
            'ensure.school' => \App\Http\Middleware\EnsureUserHasSchool::class,
            'track.last-page' => \App\Http\Middleware\TrackLastVisitedPage::class,
            'abgeschaltet' => \App\Http\Middleware\Abgeschaltet::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Der letzte Fehler als Kontext einer Support-Meldung (Modul 6.4).
        //
        // Der Fehler passiert in Request A, die Meldung entsteht in Request B —
        // dazwischen liegt nur die Sitzung. Der Kurzbefund ist bewusst arm
        // (siehe FehlerKennung): gekuerzte Meldung, bei QueryException gar
        // keine, und nur Rahmen aus app/.
        $exceptions->report(function (Throwable $e): void {
            if (app()->runningInConsole() || ! request()->hasSession()) {
                return; // Kein Nutzer, kein Kontext, keine Sitzung.
            }

            session()->put(FehlerKennung::SESSION_SCHLUESSEL, FehlerKennung::erfassen($e));
            // Kein `return false` — das normale Logging laeuft weiter.
        });

        // Dieselbe Kennung in die Logzeile. Damit finden Fallakte und
        // storage/logs zueinander, ohne Zeitstempel zu vergleichen.
        $exceptions->context(fn (): array => ['fehler_id' => FehlerKennung::aktuelle()]);

        // Ueberlaufene Input-Limits sind ein Fehler des Clients, nicht der
        // Anwendung. Symfony liest den Query-String selbst per parse_str() ein;
        // reisst er max_input_vars (1000) oder max_input_nesting_level (64),
        // macht Laravel aus der Warning eine ErrorException — bisher ein 500
        // samt ERROR-Logzeile. Am 20.10.2026 hat ein Scanner genau damit
        // sieben Logeintraege in einer Sekunde erzeugt. Als 400 gilt der
        // Request als das, was er ist; HttpExceptions meldet Laravel nicht.
        $exceptions->map(function (ErrorException $e): Throwable {
            if (str_starts_with($e->getMessage(), 'parse_str(): Input variable')) {
                return new BadRequestHttpException('Zu viele oder zu tief verschachtelte Parameter.', $e);
            }

            return $e;
        });
    })->create();
