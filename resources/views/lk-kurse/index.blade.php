<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Leistungsfach-Kurse & Ergebnis
            </h2>
            <div class="flex space-x-3">
                @if($kurse->count() > 0)
                    <a href="{{ route('lk-kurse.export-pdf', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                       class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        PDF Export
                    </a>
                    <a href="{{ route('lk-kurse.export-excel', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                       class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Excel Export
                    </a>
                @endif
                <a href="{{ route('lk-wahlen.index', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Zu den Wahlen
                </a>
                @unlessarchive
                <a href="{{ route('lk-kurse.planen', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                   class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    Neue Zuteilung planen
                </a>
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Statistik-Karten --}}
            @if($kurse->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-sm text-gray-600">Kurse gesamt</div>
                        <div class="text-xl font-bold text-blue-600">
                            {{ $kursStatistik['anzahl_kurse'] - $kursStatistik['anzahl_externe_kurse'] }}
                            @if($kursStatistik['anzahl_externe_kurse'] > 0)
                                <span class="text-sm text-gray-500">
                                    + {{ $kursStatistik['anzahl_externe_kurse'] }} (extern)
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-sm text-gray-600">Erstwahl erfüllt</div>
                        <div class="text-2xl font-bold text-green-600">
                            {{ $wahlStatistik['erstwahl_erhalten'] }} 
                            <span class="text-base">({{ $wahlStatistik['erstwahl_prozent'] }}%)</span>
                        </div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4">
                        <div class="text-sm text-gray-600">Zweitwahl erfüllt</div>
                        <div class="text-2xl font-bold text-yellow-600">
                            {{ $wahlStatistik['zweitwahl_erhalten'] }}
                            <span class="text-base">({{ $wahlStatistik['zweitwahl_prozent'] }}%)</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-sm text-gray-600">Ø Schüler/Kurs</div>
                        <div class="text-2xl font-bold text-gray-600">{{ $kursStatistik['durchschnitt_schueler'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            Min: {{ $kursStatistik['min_schueler'] }} | Max: {{ $kursStatistik['max_schueler'] }}
                        </div>
                    </div>
                </div>

                {{-- Kurse-Übersicht --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">
                            @php
                                $internalCount = $kurse->filter(fn($k) => !$k->ist_extern)->count();
                                $externalCount = $kurse->filter(fn($k) => $k->ist_extern)->count();
                            @endphp
                            Kurse ({{ $internalCount }}@if($externalCount > 0) + {{ $externalCount }} (extern)@endif)
                        </h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fach</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Kurs</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lehrer</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Schüler</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($kurse as $kurs)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ $kurs->subject->name ?? '-' }}
                                                @if($kurs->ist_extern)
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                        Extern
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-center text-gray-900">
                                                {{ $kurs->kurs_nummer }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                @if($kurs->lehrer)
                                                    {{ $kurs->lehrer->lastname }}, {{ $kurs->lehrer->firstname }} @if($kurs->lehrer->shortcode)({{ $kurs->lehrer->shortcode }})@endif
                                                @else
                                                    <span class="text-gray-400">Nicht zugewiesen</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right text-gray-900">
                                                {{ $kurs->wahlen->count() }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <a href="{{ route('lk-kurse.show', $kurs->id) }}" 
                                                       class="text-blue-600 hover:text-blue-900"
                                                       title="Anzeigen">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>
                                                    @unlessarchive
                                                    <a href="{{ route('lk-kurse.edit', $kurs->id) }}" 
                                                       class="text-yellow-600 hover:text-yellow-900"
                                                       title="Bearbeiten">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>
                                                    @endarchive
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Nicht zugeteilte Schüler --}}
                @if($unassignedWahlen && $unassignedWahlen->count() > 0)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-red-600">Nicht zugeteilte Schüler ({{ $unassignedWahlen->count() }})</h3>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klasse</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vorname</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nachname</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Erstwahl</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zweitwahl</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($unassignedWahlen as $wahl)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $wahl->schueler->class_name ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $wahl->schueler->firstname ?? '' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $wahl->schueler->lastname ?? '' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $wahl->erstwahl->name ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $wahl->zweitwahl->name ?? '-' }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    @unlessarchive
                                                    <a href="{{ route('lk-wahlen.edit', $wahl->id) }}" 
                                                       class="text-yellow-600 hover:text-yellow-900"
                                                       title="Bearbeiten">
                                                        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>
                                                    @endarchive
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
                
                {{-- Reset Button --}}
                @unlessarchive
                <div class="mt-8 flex justify-end">
                    <form method="POST" action="{{ route('lk-kurse.reset') }}"
                          data-confirm="Möchten Sie die Zuteilung wirklich zurücksetzen? Alle Kurse und Zuteilungen werden gelöscht.">
                        @csrf
                        <input type="hidden" name="schuljahr" value="{{ $schuljahr }}">
                        <input type="hidden" name="jahrgang" value="{{ $jahrgang }}">
                        <button type="submit"
                                class="px-4 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50">
                            Zuteilung zurücksetzen
                        </button>
                    </form>
                </div>
                @endarchive

            @else
                {{-- Empty State --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Kurse vorhanden</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Für {{ $schuljahr }}, Q{{ $jahrgang - 10 }} wurden noch keine Kurse angelegt.
                            </p>
                            @unlessarchive
                            <div class="mt-6">
                                <a href="{{ route('lk-kurse.planen', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                                   class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Jetzt Zuteilung planen
                                </a>
                            </div>
                            @endarchive
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
