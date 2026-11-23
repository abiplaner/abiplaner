<?php

namespace App\Http\Middleware;

use App\Services\CurrentExamPeriodService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schreibschutz für Archiv-Perioden (Modul 1.1, Kap. 3.2).
 *
 * Sobald der Nutzer per Perioden-Umschalter eine Alt-Periode betrachtet
 * (Session-Override != neueste Periode), werden alle schreibenden
 * HTTP-Requests blockiert: Alt-Perioden sind strikt nur Ansicht + Export.
 *
 * Ausgenommen sind nutzerbezogene Aktionen, die nichts mit Periodendaten
 * zu tun haben (Umschalter selbst, Profil, Passwort, Logout).
 */
class PreventArchivePeriodWrites
{
    /** HTTP-Methoden ohne Schreibwirkung */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Routen-Namen(-Präfixe), die auch in der Archivansicht erlaubt bleiben */
    private const ALLOWED_ROUTE_PREFIXES = [
        'exam-periods.switch',
        'profile.',
        'password.',
        'logout',
        // Eine Fehlermeldung ist keine Periodendatei (Modul 6.4). Sperrte der
        // Schreibschutz sie, koennte ausgerechnet in der Archivansicht niemand
        // melden, dass die Archivansicht kaputt ist.
        'support.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        if (!$this->targetsArchivePeriod($request)) {
            return $next($request);
        }

        $message = 'Archivansicht: Alt-Perioden sind schreibgeschützt. '
            . 'Zum Bearbeiten bitte zur aktuellen Periode wechseln.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->back()->with('error', $message);
    }

    /**
     * Schreibschutz greift in zwei Fällen:
     * 1. Session-Override zeigt auf eine Alt-Periode (Archivansicht), oder
     * 2. die Route trägt eine {examPeriod}, die nicht die neueste ist —
     *    verhindert Schreibzugriffe ins Archiv per URL-Manipulation,
     *    unabhängig von der in der Session gewählten Periode.
     */
    private function targetsArchivePeriod(Request $request): bool
    {
        $service = app(CurrentExamPeriodService::class);

        if ($service->isArchiveView()) {
            return true;
        }

        $routePeriodId = $this->routeExamPeriodId($request);

        if ($routePeriodId === null) {
            return false;
        }

        $latest = $service->latest();

        return $latest !== null && $routePeriodId !== $latest->id;
    }

    /**
     * Perioden-ID aus dem {examPeriod}-Routenparameter, falls vorhanden.
     * Je nach Middleware-Reihenfolge ist der Parameter bereits ein Modell
     * (SubstituteBindings) oder noch der rohe URL-Wert.
     */
    private function routeExamPeriodId(Request $request): ?int
    {
        $route = $request->route();

        if (!$route instanceof \Illuminate\Routing\Route) {
            return null;
        }

        $parameter = $route->parameter('examPeriod');

        if ($parameter instanceof \App\Models\ExamPeriod) {
            return $parameter->id;
        }

        if (is_numeric($parameter)) {
            return (int) $parameter;
        }

        return null;
    }

    private function isAllowedRoute(Request $request): bool
    {
        $route = $request->route();

        if (!$route instanceof \Illuminate\Routing\Route) {
            return false;
        }

        $routeName = $route->getName();

        if ($routeName === null) {
            return false;
        }

        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
