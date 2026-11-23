<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LastVisitedPageService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly LastVisitedPageService $lastVisitedPage,
    ) {
    }

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        if (! $user instanceof \App\Models\User) {
            // Sollte durch LoginRequest::authenticate() nie passieren; als
            // Sicherheitsnetz gegen andere Guards/Provider-Konfigurationen.
            Auth::logout();

            return redirect()->route('login');
        }

        // 2FA aktiv und dieses Gerät nicht vertrauenswürdig?
        // → zurück in den Zwischenzustand (nur Passwort verifiziert) und Challenge anzeigen.
        if ($user->hasTwoFactorEnabled()) {
            $trustToken = $request->cookie(TwoFactorChallengeController::TRUSTED_COOKIE);
            $trustToken = is_string($trustToken) ? $trustToken : null;

            if (! $this->twoFactor->isTrustedDevice($user, $trustToken)) {
                $remember = $request->boolean('remember');

                Auth::logout();
                $request->session()->put('login.id', $user->id);
                $request->session()->put('login.remember', $remember);

                return redirect()->route('two-factor.challenge');
            }
        }

        $request->session()->regenerate();

        // Super-Admins go to admin dashboard (no tenancy) — außer lokal, wo der
        // Super-Admin auch plant (User::isSchoolPlanner()).
        if ($user->isCentralAdmin()) {
            $default = route('admin.dashboard', absolute: false);

            return redirect()->intended($this->lastVisitedPage->destinationFor($user, $default));
        }

        // Regular users go to tenant dashboard (with tenancy)
        $default = route('dashboard', absolute: false);

        return redirect()->intended($this->lastVisitedPage->destinationFor($user, $default));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
