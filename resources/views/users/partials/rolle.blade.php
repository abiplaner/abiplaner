{{-- Rollenauswahl für Anlegen und Bearbeiten. $aktuell: gewählte Rolle. --}}
<div class="col-span-2">
    <x-input-label for="role" :value="__('Rolle')" />
    <select id="role" name="role" required
        class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
        @foreach(\App\Models\User::ROLLEN as $wert => $bezeichnung)
            <option value="{{ $wert }}" @selected(old('role', $aktuell) === $wert)>{{ $bezeichnung }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">
        Planer: alle Planungsfunktionen. Schuladmin: zusätzlich das Menü „Verwaltung“
        (Benutzer, Fächer, Termine, Sicherungen).
    </p>
    <x-input-error :messages="$errors->get('role')" class="mt-2" />
</div>
