<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📥 Geführte Importe
            </h2>
            <a href="{{ route('setup.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Einrichtung
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900">Drei Dateien bis zur Planungsreife</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Zu jedem Import steht hier, aus welchem Programm die Datei kommt, welche Spalten sie
                    braucht und wie eine Zeile aussieht, die durchgeht. Die Vorlagen enthalten erfundene
                    Beispieldaten — Sie können sie herunterladen, den Inhalt durch Ihre Daten ersetzen und
                    die Datei unverändert wieder hochladen.
                </p>
                <p class="text-sm text-gray-600 mt-2">
                    Die Reihenfolge ist ein Vorschlag. Nur eines lohnt sich einzuhalten: erst die Lehrkräfte,
                    dann alles andere — die beiden anderen Dateien verweisen über das Kürzel auf sie.
                </p>
            </div>

            @foreach ($leitfaeden as $index => $leitfaden)
                <div id="{{ $leitfaden['schluessel'] }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $index + 1 }}. {{ $leitfaden['titel'] }}
                            </h3>
                            <span class="text-xs uppercase tracking-wide text-gray-500">
                                Quelle: {{ $leitfaden['quelle'] }}
                            </span>
                        </div>

                        <p class="text-sm text-gray-600 mt-2">{{ $leitfaden['beschreibung'] }}</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('setup.imports.template', $leitfaden['schluessel']) }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                ↓ Vorlage herunterladen
                            </a>

                            <x-asv-berichte :schluessel="$leitfaden['schluessel']" variante="knopf" />

                            @if ($leitfaden['route'] && \Illuminate\Support\Facades\Route::has($leitfaden['route']))
                                <a href="{{ route($leitfaden['route']) }}"
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                    Zur Importmaske
                                </a>
                            @endif
                        </div>

                        <p class="text-xs text-gray-500 mt-2">
                            Dateiname: {{ $leitfaden['dateiname'] }}
                        </p>
                    </div>

                    <div class="p-6 grid gap-6 md:grid-cols-2">
                        <div>
                            <h4 class="font-semibold text-gray-900 text-sm">So kommen Sie an die Datei</h4>
                            <ol class="mt-2 space-y-1.5 text-sm text-gray-600 list-decimal list-inside">
                                @foreach ($leitfaden['exportweg'] as $schritt)
                                    <li>{{ $schritt }}</li>
                                @endforeach
                            </ol>

                            @if ($leitfaden['hinweise'])
                                <h4 class="font-semibold text-gray-900 text-sm mt-5">Worauf zu achten ist</h4>
                                <ul class="mt-2 space-y-1.5 text-sm text-gray-600">
                                    @foreach ($leitfaden['hinweise'] as $hinweis)
                                        <li>↳ {{ $hinweis }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 text-sm">
                                Die Spalten
                                <span class="font-normal text-gray-500">
                                    (Trennzeichen „{{ $leitfaden['trennzeichen'] }}",
                                    {{ $leitfaden['kopfzeile'] ? 'mit' : 'ohne' }} Kopfzeile)
                                </span>
                            </h4>
                            <dl class="mt-2 space-y-2">
                                @foreach ($leitfaden['spalten'] as $spalte)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-900">{{ $spalte['feld'] }}</dt>
                                        <dd class="text-sm text-gray-600">{{ $spalte['bedeutung'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</x-app-layout>
