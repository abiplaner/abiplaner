<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Selbstverwaltung der 2FA durch den eingeloggten Nutzer (Modul 7.1).
 *
 * Opt-in: aktivieren, per Code bestätigen, deaktivieren und alle
 * vertrauenswürdigen Geräte abmelden.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor)
    {
    }

    /**
     * Einrichtungsseite: erzeugt ein (unbestätigtes) Secret und zeigt den QR-Code.
     * Wird nur aufgerufen, solange die 2FA noch nicht bestätigt ist.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.edit');
        }

        // Bei jedem Aufruf ein frisches Secret, solange nicht bestätigt.
        $secret = $this->twoFactor->generateSecret($user);

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qrCode' => $this->twoFactor->qrCodeDataUri($user, $secret),
        ]);
    }

    /**
     * Bestätigt die Einrichtung mit dem ersten gültigen TOTP-Code.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $this->twoFactor->confirm($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('Der eingegebene Code ist ungültig. Bitte erneut versuchen.'),
            ]);
        }

        return redirect()->route('profile.edit')
            ->with('status', 'two-factor-enabled');
    }

    /**
     * Deaktiviert die 2FA vollständig (Secret + alle vertrauenswürdigen Geräte).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $this->twoFactor->disable($user);

        return redirect()->route('profile.edit')
            ->with('status', 'two-factor-disabled');
    }

    /**
     * "Alle Geräte abmelden": entfernt alle vertrauenswürdigen Geräte,
     * sodass beim nächsten Login überall wieder ein Code verlangt wird.
     */
    public function clearDevices(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $this->twoFactor->forgetAllDevices($user);

        return redirect()->route('profile.edit')
            ->with('status', 'two-factor-devices-cleared');
    }

    private function user(Request $request): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
