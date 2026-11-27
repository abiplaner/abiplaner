<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Einrichtungsassistent der lokalen Edition (erster Start).
 *
 * Legt genau einmal die Schule und den ersten Nutzer an. Die Schule läuft
 * durch dieselbe stancl-Pipeline wie in der Web-Version (Variante A):
 * Datei anlegen, Tenant-Migrationen, TenantSeeder.
 *
 * Der erste Nutzer wird Schuladmin — das ist fast immer der
 * Oberstufenkoordinator, der Stammdaten pflegt und plant (User::isAdmin()).
 *
 * Erreichbar nur, solange es keine Schule gibt; danach 404. Die Weiterleitung
 * dorthin macht App\Http\Middleware\ErsteinrichtungErzwingen.
 */
class ErsteinrichtungController extends Controller
{
    public function show(): View
    {
        $this->nurVorDerEinrichtung();

        return view('ersteinrichtung');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->nurVorDerEinrichtung();

        $daten = $request->validate([
            'school_number' => ['required', 'string', 'regex:/^[0-9]{4}$/'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'school_number.regex' => 'Die Schulnummer hat vier Ziffern, ggf. mit führender Null.',
        ]);

        try {
            $schule = School::create([
                'school_number' => $daten['school_number'],
                'name' => $daten['name'],
                'short_name' => $daten['short_name'],
                'city' => $daten['city'] ?? null,
                'state' => 'Bayern',
                'is_active' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ersteinrichtung: Schule konnte nicht angelegt werden: ' . $e->getMessage());

            // Halb angelegte Schule wieder entfernen, sonst ist der Assistent
            // gesperrt und die Schule unbrauchbar.
            School::query()->whereKey($daten['school_number'])->get()->each->delete();

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Die Schule konnte nicht angelegt werden: ' . $e->getMessage());
        }

        // Grunddaten der Schule (Räume, Zeitraster, Vorlagen). Früher hing das
        // an der stancl-Pipeline (InitializeTenantDatabase).
        $exitCode = \Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'TenantSeeder',
            '--force' => true,
        ]);
        if ($exitCode !== 0) {
            Log::error('Ersteinrichtung: TenantSeeder fehlgeschlagen: ' . trim(\Illuminate\Support\Facades\Artisan::output()));
            $schule->delete();

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Die Grunddaten der Schule konnten nicht angelegt werden.');
        }

        $nutzer = User::create([
            'name' => $daten['admin_name'],
            'email' => $daten['admin_email'],
            'password' => Hash::make($daten['password']),
            'role' => 'admin',
            'school_id' => $schule->getKey(),
            'is_active' => true,
        ]);
        // Nicht in $fillable; ohne Bestätigung sperrt die 'verified'-Middleware.
        $nutzer->forceFill(['email_verified_at' => now()])->save();

        Auth::login($nutzer);
        $request->session()->regenerate();

        ActivityLogger::log('school_created', 'School', $schule->getKey(),
            "Ersteinrichtung: Schule {$schule->name} ({$schule->getKey()}) und Zugang {$nutzer->email} angelegt");

        return redirect()->route('dashboard')
            ->with('success', "Willkommen! {$schule->name} ist eingerichtet.");
    }

    private function nurVorDerEinrichtung(): void
    {
        if (School::query()->exists()) {
            abort(404);
        }
    }
}
