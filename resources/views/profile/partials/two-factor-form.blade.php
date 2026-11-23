<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Zwei-Faktor-Authentifizierung') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Erhöhen Sie die Sicherheit Ihres Kontos durch einen zusätzlichen Code aus einer Authenticator-App.') }}
        </p>
    </header>

    @if (session('status') === 'two-factor-enabled')
        <p class="mt-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ __('Zwei-Faktor-Authentifizierung wurde aktiviert.') }}
        </p>
    @elseif (session('status') === 'two-factor-disabled')
        <p class="mt-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ __('Zwei-Faktor-Authentifizierung wurde deaktiviert.') }}
        </p>
    @elseif (session('status') === 'two-factor-devices-cleared')
        <p class="mt-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ __('Alle vertrauenswürdigen Geräte wurden abgemeldet.') }}
        </p>
    @endif

    @php($twoFactorActive = auth()->user()->hasTwoFactorEnabled())

    <div class="mt-6">
        @if ($twoFactorActive)
            <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                {{ __('Status: aktiv') }}
                @php($deviceCount = auth()->user()->trustedDevices()->count())
                @if ($deviceCount > 0)
                    <span class="text-gray-500 dark:text-gray-400">
                        ({{ trans_choice(':count vertrauenswürdiges Gerät|:count vertrauenswürdige Geräte', $deviceCount, ['count' => $deviceCount]) }})
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-4">
                @if ($deviceCount > 0)
                    <form method="POST" action="{{ route('two-factor.devices.clear') }}">
                        @csrf
                        @method('DELETE')
                        <x-secondary-button>
                            {{ __('Alle Geräte abmelden') }}
                        </x-secondary-button>
                    </form>
                @endif

                <form method="POST" action="{{ route('two-factor.disable') }}"
                      data-confirm="{{ __('Zwei-Faktor-Authentifizierung wirklich deaktivieren?') }}">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>
                        {{ __('Deaktivieren') }}
                    </x-danger-button>
                </form>
            </div>
        @else
            <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                {{ __('Status: nicht eingerichtet') }}
            </div>

            <a href="{{ route('two-factor.show') }}"
               class="inline-flex items-center px-4 py-2 mt-4 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                {{ __('Einrichten') }}
            </a>
        @endif
    </div>
</section>
