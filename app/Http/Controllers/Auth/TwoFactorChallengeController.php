<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LastVisitedPageService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TOTP-Challenge nach erfolgreicher Passwort-Eingabe (Modul 7.1).
 *
 * Zwischenzustand: Der Nutzer ist passwort-verifiziert, aber NOCH NICHT
 * voll eingeloggt. Die Identität liegt nur als 'login.id' in der Session.
 * Erst nach korrektem TOTP-Code erfolgt Auth::login().
 */
class TwoFactorChallengeController extends Controller
{
    /**
     * Cookie-Name für vertrauenswürdige Geräte. I2: Lebensdauer identisch zur
     * serverseitigen Gültigkeit (TwoFactorService::TRUST_DURATION_DAYS), damit
     * Cookie und DB-Eintrag gemeinsam ablaufen. Serverseitig (expires_at) bleibt
     * die eigentliche Quelle der Wahrheit; Reset ist zusätzlich manuell möglich.
     */
    public const TRUSTED_COOKIE = 'abiplaner_2fa_trust';
    private const COOKIE_MINUTES = 60 * 24 * TwoFactorService::TRUST_DURATION_DAYS;

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly LastVisitedPageService $lastVisitedPage,
    ) {
    }

    /**
     * Zeigt das Eingabeformular für den TOTP-Code.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Prüft den TOTP-Code und loggt den Nutzer bei Erfolg vollständig ein.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('login.id');

        if ($userId === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'remember_device' => ['nullable', 'boolean'],
        ]);

        $user = User::findOrFail($userId);

        if (! $this->twoFactor->verifyForUser($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('Der eingegebene Code ist ungültig.'),
            ]);
        }

        // Vollständiger Login.
        $remember = (bool) $request->session()->pull('login.remember', false);
        Auth::login($user, $remember);

        $request->session()->forget('login.id');
        $request->session()->regenerate();

        $response = $this->redirectAfterLogin($user);

        // Optional: dieses Gerät als vertrauenswürdig merken.
        if (! empty($validated['remember_device'])) {
            $token = $this->twoFactor->rememberDevice(
                $user,
                $request->userAgent() !== null ? substr($request->userAgent(), 0, 255) : null,
                $request->ip(),
            );

            $response->withCookie(
                Cookie::make(self::TRUSTED_COOKIE, $token, self::COOKIE_MINUTES, httpOnly: true, sameSite: 'lax')
            );
        }

        return $response;
    }

    /**
     * Ziel nach Login – identisch zur Logik im AuthenticatedSessionController.
     */
    private function redirectAfterLogin(User $user): RedirectResponse
    {
        $default = route('dashboard', absolute: false);

        return redirect()->intended($this->lastVisitedPage->destinationFor($user, $default));
    }
}
