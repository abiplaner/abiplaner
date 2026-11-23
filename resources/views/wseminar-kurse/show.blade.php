<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                W-Seminar Details
            </h2>
            <a href="{{ route('wseminar-kurse.index') }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Kurs-Info --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Fach</h3>
                            <p class="text-lg">{{ $kurs->subject?->name ?? 'Unbekannt' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Titel</h3>
                            <p class="text-lg">{{ $kurs->titel }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Leiter</h3>
                            <p class="text-lg">{{ $kurs->lehrer?->name ?? 'Nicht zugewiesen' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Teilnehmer</h3>
                            <p class="text-lg">
                                <span class="font-bold">{{ $teilnehmer->count() }}</span> / {{ $kurs->max_teilnehmer }}
                                @if($teilnehmer->count() >= $kurs->max_teilnehmer)
                                    <span class="text-red-600 text-sm">(Voll)</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Teilnehmer-Liste --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Teilnehmer ({{ $teilnehmer->count() }})</h3>
                    
                    @if($teilnehmer->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Nr.
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Klasse
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Priorität erhalten
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($teilnehmer as $index => $wahl)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $wahl->schueler->lastname }}, {{ $wahl->schueler->firstname }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $wahl->schueler->class_name ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($wahl->prioritaet_erhalten === 'erstwahl')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Erstwahl
                                                    </span>
                                                @elseif($wahl->prioritaet_erhalten === 'zweitwahl')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Zweitwahl
                                                    </span>
                                                @elseif($wahl->prioritaet_erhalten === 'drittwahl')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">
                                                        Drittwahl
                                                    </span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        Keine Wahl
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">
                            Keine Teilnehmer zugeteilt.
                        </p>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
