<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🧭 Einrichtung
            </h2>
            <a href="{{ route('dashboard') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Fortschritt --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($progress['offen'] === 0)
                    <h3 class="text-lg font-semibold text-green-800">✅ Die Einrichtung ist vollständig.</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Alle Bausteine bis zur Planungsreife stehen. Diese Seite bleibt erreichbar — nach einem
                        Schuljahreswechsel ist wieder einiges davon leer.
                    </p>
                @else
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ $progress['erledigt'] }} von {{ $progress['gesamt'] }} Bausteinen erledigt
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Die Reihenfolge ist ein Vorschlag, keine Vorgabe: Sie können jeden Punkt angehen, sobald
                        die Daten dafür vorliegen. Wo eine Reihenfolge sinnvoll ist, steht der Grund an der Zeile.
                    </p>
                @endif

                <div class="mt-4 w-full bg-gray-200 rounded-full h-2.5" role="progressbar"
                     aria-valuenow="{{ $progress['prozent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-2.5 rounded-full {{ $progress['offen'] === 0 ? 'bg-green-500' : 'bg-indigo-500' }}"
                         style="width: {{ $progress['prozent'] }}%"></div>
                </div>
            </div>

            {{-- Geführte Importe (Modul 6.2, zweite Stufe) --}}
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="grow">
                    <h3 class="font-semibold text-indigo-900">Woher kommen die Dateien?</h3>
                    <p class="text-sm text-indigo-800 mt-0.5">
                        Für Lehrkräfte, Schüler und den Stundenplan gibt es je eine Anleitung mit dem
                        Exportweg aus ASV beziehungsweise Untis, der Spaltenbedeutung und einer Vorlage
                        zum Herunterladen.
                    </p>
                </div>
                <a href="{{ route('setup.imports') }}"
                   class="shrink-0 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    Zu den Importen
                </a>
            </div>

            {{-- Von der Schule zu erledigen --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 sm:px-5 pt-5">
                    <h3 class="text-lg font-semibold text-gray-900">Von der Schule zu erledigen</h3>
                </div>
                <ul class="divide-y divide-gray-100 mt-2">
                    @foreach ($schoolItems as $item)
                        @include('setup.partials._item', ['item' => $item])
                    @endforeach
                </ul>
            </div>

            {{-- Vom Administrator --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 sm:px-5 pt-5">
                    <h3 class="text-lg font-semibold text-gray-900">Zentral vom Administrator</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Diese Punkte lassen sich nicht in der Schule selbst erledigen. Sie stehen hier, damit
                        sichtbar ist, worauf gewartet wird.
                    </p>
                </div>
                <ul class="divide-y divide-gray-100 mt-2">
                    @foreach ($adminItems as $item)
                        @include('setup.partials._item', ['item' => $item])
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>
