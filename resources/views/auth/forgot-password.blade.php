<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Passwort vergessen</h1>
    <p class="mt-1 mb-6 text-sm text-gray-500 dark:text-gray-400">
        Geben Sie Ihre E-Mail-Adresse ein. Sie erhalten einen Link, mit dem Sie ein neues Passwort wählen können.
    </p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">Zurück zur Anmeldung</a>
            <x-primary-button>Link senden</x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
        Keine E-Mail erhalten? Ihr Schuladmin kann Ihr Passwort unter „Verwaltung → Benutzer“ neu setzen.
    </p>
</x-guest-layout>
