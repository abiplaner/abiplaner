<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Zwei-Faktor-Authentifizierung einrichten') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Scannen Sie den QR-Code mit Ihrer Authenticator-App (z. B. Google Authenticator, Authy, 1Password) und geben Sie anschließend den angezeigten 6-stelligen Code ein, um die Einrichtung abzuschließen.') }}
                    </p>

                    <div class="flex flex-col items-center my-6">
                        <img src="{{ $qrCode }}" alt="{{ __('QR-Code für die 2FA-Einrichtung') }}"
                             class="border border-gray-200 dark:border-gray-700 rounded bg-white p-2"
                             width="200" height="200">

                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Falls der QR-Code nicht scannbar ist, geben Sie diesen Schlüssel manuell ein:') }}
                        </p>
                        <code class="mt-1 px-2 py-1 text-sm font-mono bg-gray-100 dark:bg-gray-900 rounded select-all">{{ $secret }}</code>
                    </div>

                    <form method="POST" action="{{ route('two-factor.confirm') }}">
                        @csrf

                        <div>
                            <x-input-label for="code" :value="__('Bestätigungscode')" />
                            <x-text-input id="code" class="block mt-1 w-full text-center tracking-widest"
                                          type="text" name="code"
                                          inputmode="numeric" autocomplete="one-time-code"
                                          autofocus required />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-3 mt-6">
                            <a href="{{ route('profile.edit') }}"
                               class="underline text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                                {{ __('Abbrechen') }}
                            </a>
                            <x-primary-button>{{ __('Aktivieren') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
