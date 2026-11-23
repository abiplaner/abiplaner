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
                        <a href="{{ route('admin.written-exam-slots.year-dates', $year) }}"
                           class="px-3 py-1.5 bg-indigo-100 text-indigo-700 rounded-md text-sm font-medium hover:bg-indigo-200">
                            Abitur {{ $year }}
                        </a>
                    @endforeach
                    {{-- Nächstes Jahr vorschlagen --}}
                    @php $nextYear = now()->month >= 6 ? now()->year + 1 : now()->year; @endphp
                    @if(!$availableYears->contains($nextYear))
                        <a href="{{ route('admin.written-exam-slots.year-dates', $nextYear) }}"
                           class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-md text-sm hover:bg-gray-200 border border-dashed border-gray-400">
                            + Abitur {{ $nextYear }} anlegen
                        </a>
                    @endif
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
