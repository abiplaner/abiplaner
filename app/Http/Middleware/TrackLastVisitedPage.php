<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\LastVisitedPageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Merkt sich pro Nutzer die zuletzt besuchte Seite (Modul 1.6).
 *
 * Läuft nach der eigentlichen Verarbeitung, damit Response-Status und
 * -Content-Type für die Entscheidung "war das eine echte Seite?" zur
 * Verfügung stehen.
 */
class TrackLastVisitedPage
{
    public function __construct(private readonly LastVisitedPageService $lastVisitedPage)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user instanceof User) {
            $this->lastVisitedPage->track($request, $user, $response);
        }

        return $response;
    }
}
