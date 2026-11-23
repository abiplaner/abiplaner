<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasSchool
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Super admins don't need a school assignment
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        // Regular users must be assigned to a school
        if (auth()->check() && !auth()->user()->school_id) {
            \Log::warning('User has no school assigned', [
                'user_id' => auth()->user()->id,
                'email' => auth()->user()->email,
            ]);
            abort(403, 'Benutzer ist keiner Schule zugeordnet.');
        }
        
        return $next($request);
    }
}
