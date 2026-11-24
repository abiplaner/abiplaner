<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Anmelden</h1>
    <p class="mt-1 mb-6 text-sm text-gray-500 dark:text-gray-400">Bitte melden Sie sich mit Ihrem Zugang an.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- E-Mail -->
        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Passwort -->
        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Passwort" />
                @if (Route::has('password.request'))
                    <a class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                        Passwort vergessen?
                    </a>
                @endif
            </div>
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Angemeldet bleiben -->
        <div>
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Angemeldet bleiben</span>
            </label>
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center py-2.5">
                Anmelden
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
