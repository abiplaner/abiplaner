{{-- Einrichtungsassistent der lokalen Edition (ErsteinrichtungController). --}}
<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Abiplaner einrichten</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Einmalig: Ihre Schule und Ihr eigener Zugang. Weitere Kolleginnen und
        Kollegen legen Sie danach unter „Verwaltung → Benutzer“ an.
    </p>

    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('ersteinrichtung.store') }}" class="mt-6 space-y-6">
        @csrf

        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-gray-800 dark:text-gray-200">Schule</legend>

            <div>
                <x-input-label for="school_number" value="Schulnummer" />
                <x-text-input id="school_number" name="school_number" class="block mt-1 w-full"
                              :value="old('school_number')" required autofocus inputmode="numeric"
                              maxlength="4" pattern="[0-9]{4}" placeholder="4 Ziffern" />
                <x-input-error :messages="$errors->get('school_number')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="name" value="Name der Schule" />
                <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="short_name" value="Kurzname" />
                    <x-text-input id="short_name" name="short_name" class="block mt-1 w-full"
                                  :value="old('short_name')" required maxlength="50" placeholder="z. B. GYM" />
                    <x-input-error :messages="$errors->get('short_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="city" value="Ort" />
                    <x-text-input id="city" name="city" class="block mt-1 w-full" :value="old('city')" />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-gray-800 dark:text-gray-200">Ihr Zugang</legend>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Dieser Zugang darf alles: planen und die Stammdaten pflegen.
            </p>

            <div>
                <x-input-label for="admin_name" value="Name" />
                <x-text-input id="admin_name" name="admin_name" class="block mt-1 w-full"
                              :value="old('admin_name')" required autocomplete="name" />
                <x-input-error :messages="$errors->get('admin_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="admin_email" value="E-Mail (Anmeldename)" />
                <x-text-input id="admin_email" name="admin_email" type="email" class="block mt-1 w-full"
                              :value="old('admin_email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('admin_email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="Passwort" />
                <x-text-input id="password" name="password" type="password" class="block mt-1 w-full"
                              required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Passwort wiederholen" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                              class="block mt-1 w-full" required autocomplete="new-password" />
            </div>
        </fieldset>

        <div class="flex items-center justify-between gap-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Das Anlegen der Schuldatenbank dauert einige Sekunden.
            </p>
            <x-primary-button>Einrichten</x-primary-button>
        </div>
    </form>
</x-guest-layout>
