<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                ❓ Hilfe
            </h2>
            <a href="{{ route('hilfe.handbuch') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                ↓ Handbuch als PDF
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    Jede Maske der Anwendung hat ein Kapitel. Sie erreichen es hier über das
                    Inhaltsverzeichnis oder direkt an Ort und Stelle über das <strong>„?"</strong> oben rechts —
                    dort öffnet sich das Kapitel zu genau der Seite, auf der Sie gerade stehen.
                </p>

                <form method="GET" action="{{ route('hilfe.index') }}" class="mt-4 flex flex-wrap gap-2">
                    <input type="search" name="q" value="{{ $begriff }}"
                           placeholder="Suchen, z. B. Kolloquium, Aufsicht, Nachteilsausgleich"
                           class="flex-1 min-w-64 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Suchen
                    </button>
                    @if ($begriff !== '')
                        <a href="{{ route('hilfe.index') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                            Zurücksetzen
                        </a>
                    @endif
                </form>
            </div>

            @if ($treffer !== null)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ count($treffer) }} {{ count($treffer) === 1 ? 'Kapitel' : 'Kapitel' }}
                        für „{{ $begriff }}"
                    </h3>

                    @if ($treffer === [])
                        <p class="text-sm text-gray-600 mt-2">
                            Nichts gefunden. Die Suche sieht Titel, Stichworte und den Fließtext aller Kapitel an —
                            versuchen Sie ein kürzeres Wort oder blättern Sie unten durch das Inhaltsverzeichnis.
                        </p>
                    @else
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($treffer as $eintrag)
                                <li class="py-3">
                                    <a href="{{ route('hilfe.show', $eintrag['kapitel']->slug) }}"
                                       class="text-indigo-600 font-semibold hover:text-indigo-800">
                                        {{ $eintrag['kapitel']->titel }}
                                    </a>
                                    <span class="text-xs text-gray-500 ml-2">{{ $eintrag['kapitel']->gruppe }}</span>
                                    <p class="text-sm text-gray-600 mt-1">{{ $eintrag['fundstelle'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900">Inhalt</h3>

                @forelse ($gruppen as $gruppe => $kapitelliste)
                    <div class="mt-5">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $gruppe }}</h4>
                        <ul class="mt-2 grid sm:grid-cols-2 gap-x-6 gap-y-1">
                            @foreach ($kapitelliste as $kapitel)
                                <li>
                                    <a href="{{ route('hilfe.show', $kapitel->slug) }}"
                                       class="text-sm text-indigo-600 hover:text-indigo-800">
                                        {{ $kapitel->titel }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="text-sm text-gray-600 mt-2">
                        Es ist noch kein Kapitel hinterlegt. Kapitel sind Markdown-Dateien unter
                        <code>resources/help/</code>.
                    </p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
