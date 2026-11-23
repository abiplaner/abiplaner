<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Bitte geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein, um die Anmeldung abzuschließen.') }}
    </div>

    <form method="POST" action="{{ route('two-factor.challenge') }}">
        @csrf

        <!-- Code -->
        <div>
            <x-input-label for="code" :value="__('Bestätigungscode')" />

            <x-text-input id="code" class="block mt-1 w-full text-center tracking-widest"
                            type="text"
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            autofocus
                            required />

            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <!-- Gerät merken -->
        <div class="block mt-4">
            <label for="remember_device" class="inline-flex items-center">
                <input id="remember_device" type="checkbox"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                       name="remember_device" value="1">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Diesem Gerät vertrauen (kein erneuter Code auf diesem Gerät)') }}
                </span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
               href="{{ route('login') }}">
                {{ __('Abbrechen') }}
            </a>

            <x-primary-button class="ms-3">
                {{ __('Anmelden') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
