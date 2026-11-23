<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                W-Seminar-Kurse (Zuteilung)
            </h2>
            <div class="flex space-x-3">
                @unlessarchive
                <a href="{{ route('wseminar-kurse.planen', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                   class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    Zuteilung planen
                </a>
                @endarchive
                
                {{-- Export Buttons --}}
                <div class="flex rounded-md shadow-sm" role="group">
                    <a href="{{ route('wseminar-kurse.export-pdf', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                       class="px-4 py-2 bg-red-600 text-white rounded-l-md hover:bg-red-700 border-r border-red-700 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        PDF
                    </a>
                    <a href="{{ route('wseminar-kurse.export-excel', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                       class="px-4 py-2 bg-green-600 text-white rounded-r-md hover:bg-green-700 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Excel
                    </a>
                </div>

                <a href="{{ route('wseminar-wahlen.index') }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Zu den Wahlen
                </a>
                @unlessarchive
                <form action="{{ route('wseminar-kurse.reset') }}" method="POST" class="inline" 
                      data-confirm="Sind Sie sicher? Die gesamte Zuteilung wird zurückgesetzt.">
                    @csrf
                    <input type="hidden" name="schuljahr" value="{{ $schuljahr }}">
                    <input type="hidden" name="jahrgang" value="{{ $jahrgang }}">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Zuteilung zurücksetzen
                    </button>
                </form>
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            @if (session('success'))
                <div class="bg-green-50 border-l-4 border-green-400 p-4">
                    <p class="text-sm text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Statistik-Karten --}}
            {{-- Statistik-Karten --}}
            @php
                $total = $statistik['anzahl_schueler'] > 0 ? $statistik['anzahl_schueler'] : 1;
                $pct = fn($val) => round(($val / $total) * 100);
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                    <h3 class="text-gray-500 text-xs uppercase font-bold">Erstwahl</h3>
                    <div class="mt-2 flex items-baseline">
                        <p class="text-2xl font-bold text-gray-900">{{ $statistik['erstwahl'] }}</p>
                        <p class="ml-2 text-sm text-gray-500">({{ $pct($statistik['erstwahl']) }}%)</p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-400">
                    <h3 class="text-gray-500 text-xs uppercase font-bold">Zweitwahl</h3>
                    <div class="mt-2 flex items-baseline">
                        <p class="text-2xl font-bold text-gray-900">{{ $statistik['zweitwahl'] }}</p>
                        <p class="ml-2 text-sm text-gray-500">({{ $pct($statistik['zweitwahl']) }}%)</p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-300">
                    <h3 class="text-gray-500 text-xs uppercase font-bold">Drittwahl</h3>
                    <div class="mt-2 flex items-baseline">
                        <p class="text-2xl font-bold text-gray-900">{{ $statistik['drittwahl'] }}</p>
                        <p class="ml-2 text-sm text-gray-500">({{ $pct($statistik['drittwahl']) }}%)</p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow border-l-4 border-red-500">
                    <h3 class="text-gray-500 text-xs uppercase font-bold">Nicht zugeteilt</h3>
                    <div class="mt-2 flex items-baseline">
                        <p class="text-2xl font-bold text-red-600">{{ $statistik['nicht_zugeteilt'] }}</p>
                        <p class="ml-2 text-sm text-gray-500">({{ $pct($statistik['nicht_zugeteilt']) }}%)</p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow border-l-4 border-gray-400">
                    <h3 class="text-gray-500 text-xs uppercase font-bold">Gar nicht gewählt</h3>
                    <div class="mt-2 flex items-baseline">
                        <p class="text-2xl font-bold text-gray-600">{{ $statistik['gar_nicht_gewaehlt'] }}</p>
                        <p class="ml-2 text-sm text-gray-500">({{ $pct($statistik['gar_nicht_gewaehlt']) }}%)</p>
                    </div>
                </div>
            </div>

            {{-- Kurse-Tabelle --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">W-Seminar</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Erstwahlen</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Zweitwahlen</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Drittwahlen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teilnehmer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leiter</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($kurse as $kurs)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    {{ $loop->iteration }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium">{{ $kurs->subject?->name }} - {{ $kurs->titel }}</div>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600">
                                    {{ $kurs->erstwahlen_count }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600">
                                    {{ $kurs->zweitwahlen_count }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600">
                                    {{ $kurs->drittwahlen_count }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-sm rounded {{ $kurs->wahlen->count() >= 15 ? 'bg-red-100' : 'bg-green-100' }}">
                                        {{ $kurs->wahlen->count() }} / {{ $kurs->max_teilnehmer }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">{{ $kurs->lehrer?->name ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('wseminar-kurse.show', $kurs->id) }}" class="text-blue-600 hover:text-blue-800">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                    Keine Kurse vorhanden.@unlessarchive <a href="{{ route('wseminar-kurse.planen') }}" class="text-blue-600">Zuteilung planen</a>@endarchive
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Nicht zugeteilte Schüler --}}
            @if(isset($unassignedWahlen) && $unassignedWahlen->count() > 0)
                <div class="mt-8">
                    <h3 class="text-lg font-semibold mb-4 text-red-600">
                        Nicht zugeteilte Schüler ({{ $unassignedWahlen->count() }})
                    </h3>
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-red-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-red-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-red-800 uppercase">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-red-800 uppercase">Schüler</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-red-800 uppercase">Klasse</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-red-800 uppercase">Erstwahl</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-red-800 uppercase">Zweitwahl</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-red-800 uppercase">Drittwahl</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($unassignedWahlen as $wahl)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                            {{ $loop->iteration }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900">{{ $wahl->schueler->lastname }}, {{ $wahl->schueler->firstname }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $wahl->schueler->class_name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $wahl->erstwahl?->titel ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $wahl->zweitwahl?->titel ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $wahl->drittwahl?->titel ?? '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
