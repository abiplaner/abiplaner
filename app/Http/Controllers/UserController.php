<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\School;
use App\Services\ActivityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    use AuthorizesRequests;
    
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        
        $query = User::with('school');
        
        // Suche nach Name oder Email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter nach Rolle
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        
        // Filter nach Status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        
        $users = $query->orderBy('name')->paginate(20)->withQueryString();
        
        // Schulen für Filter-Dropdown
        $schools = \App\Models\School::orderBy('name')->get();
        
        return view('users.index', compact('users', 'schools'));
    }
    
    public function create()
    {
        $this->authorize('create', User::class);
        
        $schools = School::orderBy('name')->get();
        
        return view('users.create', compact('schools'));
    }
    
    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,planner',
            'is_active' => 'boolean',
        ]);
        
        $validated['password'] = Hash::make($validated['password']);
        $validated['email_verified_at'] = now(); // Auto-verifizieren
        $validated = $this->schuleDerLokalenEdition($validated);
        
        $user = User::create($validated);
        
        ActivityLogger::log('user_created', 'User', $user->id, "Benutzer {$user->name} erstellt");
        
        return redirect()->route('admin.users.index')->with('success', 'Benutzer erfolgreich erstellt.');
    }
    
    public function show(User $user)
    {
        $this->authorize('view', $user);
        
        $user->load('school');
        
        return view('users.show', compact('user'));
    }
    
    public function edit(User $user)
    {
        $this->authorize('update', $user);
        
        $schools = School::orderBy('name')->get();
        
        return view('users.edit', compact('user', 'schools'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,planner',
            'is_active' => 'boolean',
        ]);
        
        // Nur Passwort aktualisieren, wenn es angegeben wurde
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated = $this->schuleDerLokalenEdition($validated);

        if ($this->waereLetzterAdminWeg($user, $validated['role'], (bool) ($validated['is_active'] ?? $user->is_active))) {
            return back()->withInput()
                ->with('error', 'Mindestens ein aktiver Schuladmin muss bleiben, sonst kann niemand mehr Benutzer verwalten.');
        }

        $user->update($validated);
        
        ActivityLogger::log('user_updated', 'User', $user->id, "Benutzer {$user->name} aktualisiert");
        
        return redirect()->route('admin.users.index')->with('success', 'Benutzer erfolgreich aktualisiert.');
    }
    
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        
        // Verhindere, dass der Benutzer sich selbst löscht
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Sie können sich nicht selbst löschen.');
        }
        
        if ($this->waereLetzterAdminWeg($user, 'planner', false)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Der letzte aktive Schuladmin kann nicht gelöscht werden.');
        }

        $name = $user->name;
        $user->delete();
        
        ActivityLogger::log('user_deleted', 'User', $user->id, "Benutzer {$name} gelöscht");
        
        return redirect()->route('admin.users.index')->with('success', 'Benutzer erfolgreich gelöscht.');
    }

    /**
     * Setzt die 2FA eines Nutzers vollständig zurück (Secret + alle Geräte).
     * Anwendungsfall: Nutzer hat seine TOTP-App verloren (kein Recovery-Code-Verfahren).
     */
    public function resetTwoFactor(User $user, TwoFactorService $twoFactor)
    {
        $this->authorize('update', $user);

        $twoFactor->disable($user);

        ActivityLogger::log('user_2fa_reset', 'User', $user->id, "2FA für {$user->name} zurückgesetzt");

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Zwei-Faktor-Authentifizierung wurde zurückgesetzt.');
    }

    /**
     * Setzt nur die vertrauenswürdigen Geräte eines Nutzers zurück.
     * Die 2FA selbst bleibt aktiv; beim nächsten Login wird überall wieder ein Code verlangt.
     */
    public function resetTrustedDevices(User $user, TwoFactorService $twoFactor)
    {
        $this->authorize('update', $user);

        $twoFactor->forgetAllDevices($user);

        ActivityLogger::log('user_2fa_devices_reset', 'User', $user->id, "Vertrauenswürdige Geräte für {$user->name} zurückgesetzt");

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Vertrauenswürdige Geräte wurden zurückgesetzt.');
    }

    /**
     * Verliert die Installation mit dieser Änderung ihren letzten aktiven
     * Schuladmin? Dann käme niemand mehr an die Benutzerverwaltung.
     */
    private function waereLetzterAdminWeg(User $user, string $neueRolle, bool $bleibtAktiv): bool
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            return false;
        }
        if ($neueRolle === 'admin' && $bleibtAktiv) {
            return false;
        }

        return ! User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /**
     * Jeder Nutzer gehört zu der einen Schule der Installation.
     *
     * @param  array<string, mixed>  $daten
     * @return array<string, mixed>
     */
    private function schuleDerLokalenEdition(array $daten): array
    {
        $daten['school_id'] = School::query()->value('school_number');

        return $daten;
    }
}
