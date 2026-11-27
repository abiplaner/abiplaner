<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Schriftliche Prüfungs-Slots
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Jahresunabhängige Zeitvorgaben des Ministeriums. Jedes Jahr werden die konkreten Termine gepflegt.
                </p>
            </div>
            <a href="{{ route('admin.written-exam-slots.create') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                + Neuer Slot
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">


            {{-- Jahrestermine-Navigation --}}
            <div class="bg-white shadow-sm rounded-lg p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium text-gray-700">Jahrestermine verwalten:</span>
                    @foreach($availableYears as $year)
                        @if((int) $year === $aktuellesAbitur)
                            <a href="{{ route('admin.written-exam-slots.year-dates', $year) }}"
                               aria-current="true" title="Abitur des laufenden Schuljahres"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white rounded-md text-sm font-semibold shadow-sm ring-2 ring-indigo-300 hover:bg-indigo-700">
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-300"></span>
                                Abitur {{ $year }} <span class="font-normal text-indigo-100">· aktuell</span>
                            </a>
                        @else
                            <a href="{{ route('admin.written-exam-slots.year-dates', $year) }}"
                               class="px-3 py-1.5 {{ (int) $year < $aktuellesAbitur ? 'bg-gray-100 text-gray-500 hover:bg-gray-200' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' }} rounded-md text-sm font-medium">
                                Abitur {{ $year }}
                            </a>
                        @endif
                    @endforeach
                    {{-- Fehlende Jahre vorschlagen: das laufende, ab Juni auch das nächste --}}
                    @foreach(array_filter([$aktuellesAbitur, now()->month >= 6 ? $aktuellesAbitur + 1 : null]) as $vorschlag)
                        @if(!$availableYears->contains($vorschlag))
                            <a href="{{ route('admin.written-exam-slots.year-dates', $vorschlag) }}"
                               class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-md text-sm hover:bg-gray-200 border border-dashed border-gray-400">
                                + Abitur {{ $vorschlag }} anlegen
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- gA-Slots --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-blue-50">
                    <h3 class="font-semibold text-blue-900">Grundlegendes Anforderungsniveau (gA)</h3>
                    <p class="text-xs text-blue-700 mt-0.5">{{ ($slots['grundlegend'] ?? collect())->count() }} Slots</p>
                </div>
                @include('admin.written-exam-slots._table', ['slotGroup' => $slots['grundlegend'] ?? collect(), 'levelColor' => 'blue'])
            </div>

            {{-- eA-Slots --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-purple-50">
                    <h3 class="font-semibold text-purple-900">Erhöhtes Anforderungsniveau (eA)</h3>
                    <p class="text-xs text-purple-700 mt-0.5">{{ ($slots['erhoeht'] ?? collect())->count() }} Slots</p>
                </div>
                @include('admin.written-exam-slots._table', ['slotGroup' => $slots['erhoeht'] ?? collect(), 'levelColor' => 'purple'])
            </div>

        </div>
    </div>
</x-app-layout>
