<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\DesktopZugang;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * „Passwort vergessen?“
 *
 * - **Desktop-App:** Das Passwort wird direkt hier neu gesetzt. Wer das
 *   Fenster der App vor sich hat, sitzt am Rechner mit der Datenbankdatei und
 *   käme ohnehin an alle Daten (die Datei ist nicht verschlüsselt) — eine
 *   E-Mail-Bestätigung schützte nichts, und eingerichtet ist dort meist kein
 *   Mailversand. Fremde Prozesse auf demselben Rechner hält der Startschlüssel
 *   ab (DesktopZugang).
 * - **Schulserver (Docker):** Link per E-Mail, wenn Mailversand eingerichtet
 *   ist; sonst setzt der Schuladmin das Passwort in der Benutzerverwaltung
 *   oder auf dem Server `php artisan abiplaner:passwort`.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        if (DesktopZugang::aktiv()) {
            return view('auth.forgot-password-desktop', [
                'konten' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            ]);
        }

        return view('auth.forgot-password');
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (DesktopZugang::aktiv()) {
            return $this->direktSetzen($request);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::warning('Passwort-Link nicht versendet: ' . $e->getMessage());

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Die E-Mail konnte nicht versendet werden. Bitte wenden Sie sich an Ihren Schuladmin.',
            ]);
        }

        // Für unbekannte Adressen dieselbe Antwort wie für bekannte: Die Seite
        // verrät nicht, welche Konten es gibt.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return back()->with('status', __('passwords.sent'));
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    private function direktSetzen(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'zwei_faktor_aus' => ['nullable', 'boolean'],
        ]);

        /** @var User $nutzer */
        $nutzer = User::query()->where('is_active', true)->findOrFail($daten['user_id']);
        $nutzer->forceFill(['password' => Hash::make($daten['password'])])->save();

        if ($request->boolean('zwei_faktor_aus') && $nutzer->hasTwoFactorEnabled()) {
            app(TwoFactorService::class)->disable($nutzer);
        }

        ActivityLogger::log('password_reset_desktop', 'User', $nutzer->id,
            "Passwort für {$nutzer->email} in der Desktop-App neu gesetzt");

        return redirect()->route('login')
            ->withInput(['email' => $nutzer->email])
            ->with('status', 'Das Passwort wurde neu gesetzt. Bitte melden Sie sich jetzt an.');
    }
}
