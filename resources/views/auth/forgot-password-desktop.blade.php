<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Passwort vergessen</h1>
    <p class="mt-1 mb-6 text-sm text-gray-500 dark:text-gray-400">
        Wählen Sie Ihr Konto und vergeben Sie ein neues Passwort. Das geht nur hier in der
        Abiplaner-App auf dem Rechner, auf dem die Daten liegen.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="user_id" value="Konto" />
            <select id="user_id" name="user_id" required
                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                @if($konten->count() !== 1)
                    <option value="">Bitte wählen …</option>
                @endif
                @foreach($konten as $konto)
                    <option value="{{ $konto->id }}" @selected((string) old('user_id') === (string) $konto->id)>
                        {{ $konto->name }} ({{ $konto->email }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Neues Passwort" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Neues Passwort wiederholen" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <label for="zwei_faktor_aus" class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
            <input id="zwei_faktor_aus" type="checkbox" name="zwei_faktor_aus" value="1"
                   class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>Zwei-Faktor-Anmeldung ebenfalls ausschalten (falls die Authenticator-App verloren ist)</span>
        </label>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">Zurück zur Anmeldung</a>
            <x-primary-button>Passwort setzen</x-primary-button>
        </div>
    </form>
</x-guest-layout>
