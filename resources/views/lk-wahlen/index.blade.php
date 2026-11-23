<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Leistungsfach-Wahlen
            </h2>
            <div class="flex space-x-3">
                @unlessarchive
                <a href="{{ route('lk-kurse.planen', ['schuljahr' => $latestSchuljahr, 'jahrgang' => $latestJahrgang]) }}" 
                   class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    Zuteilung planen
                </a>
                @endarchive
                <a href="{{ route('lk-kurse.index', ['schuljahr' => $latestSchuljahr, 'jahrgang' => $latestJahrgang]) }}" 
                   class="px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                    Aktuelle Zuteilung
                </a>
                <a href="{{ route('lk-wahlen.export') }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Excel exportieren
                </a>
                @unlessarchive
                <a href="{{ route('lk-wahlen.import') }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Wahlen importieren
                </a>

                
                <form action="{{ route('lk-wahlen.destroy-all') }}" method="POST" class="inline" 
                      data-confirm="Sind Sie sicher? Alle Wahlen dieses Jahrgangs werden unwiderruflich gelöscht.">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="schuljahr" value="{{ $latestSchuljahr }}">
                    <input type="hidden" name="jahrgang" value="{{ $latestJahrgang }}">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Wahlen löschen
                    </button>
                </form>
                @endarchive
            </div>
        </div>
    </x-slot>

    {{-- x-cloak style --}}
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div x-data="wahlData()">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                {{-- Erfolgs-Meldung --}}
                @if (session('success'))
                    <div class="bg-green-50 border-l-4 border-green-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Warnungen --}}
                @if (session('warnings'))
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4" x-data="{ open: false }">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 w-full">
                                <button @click="open = !open" type="button" class="flex justify-between w-full text-left text-sm font-medium text-yellow-800 focus:outline-none">
                                    <span>Warnungen ({{ count(session('warnings')) }})</span>
                                    <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                
                                <div x-show="open" x-collapse class="mt-2 text-sm text-yellow-700">
                                    <ul class="list-disc list-inside">
                                        @foreach (session('warnings') as $warning)
                                            <li>{{ $warning }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif



                {{-- Statistik-Karten --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {{-- Gesamt Schüler --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Schüler gesamt
                                        </dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            {{ $wahlStatistik['gesamt_schueler'] }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Erstwahl erfüllt --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Erstwahl erfüllt
                                        </dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            {{ $wahlStatistik['erstwahl_erhalten'] }} ({{ $wahlStatistik['erstwahl_prozent'] }}%)
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Zweitwahl erfüllt --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Zweitwahl erfüllt
                                        </dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            {{ $wahlStatistik['zweitwahl_erhalten'] }} ({{ $wahlStatistik['zweitwahl_prozent'] }}%)
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Nicht zugeteilt --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-gray-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Nicht zugeteilt
                                        </dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            {{ $wahlStatistik['nicht_zugeteilt'] }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Fachverteilung --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Tabelle --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Fachverteilung</h3>
                            
                            @if(count($fachVerteilung) > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fach</th>
                                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">1. Wahl</th>
                                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">2. Wahl</th>
                                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gesamt</th>
                                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aktion</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($fachVerteilung as $fach)
                                                <tr>
                                                    <td class="px-3 py-2 text-sm text-gray-900">
                                                        {{ $fach['fach'] }}
                                                        @if($fach['is_extern'])
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 ml-1">
                                                                Extern
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-sm text-right text-gray-900">{{ $fach['erstwahlen'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-right text-gray-900">{{ $fach['zweitwahlen'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-right font-medium text-gray-900">{{ $fach['gesamt'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-center">
                                                        <button 
                                                            type="button"
                                                            @click="showSubjectModal('{{ $fach['fach'] }}')"
                                                            class="text-blue-600 hover:text-blue-900"
                                                            title="Details anzeigen">
                                                            <svg class="h-5 w-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-gray-500">Keine Wahlen vorhanden.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Chart --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Wahlverteilung</h3>
                            
                            @if(count($fachVerteilung) > 0)
                                <canvas id="wahlChart" class="w-full" style="max-height: 400px;"></canvas>
                            @else
                                <p class="text-gray-500">Keine Daten für Diagramm verfügbar.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Wahlen-Tabelle --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Alle Wahlen ({{ $wahlen->total() }})</h3>
                        </div>

                        {{-- Search & Filter Form --}}
                        <form method="GET" action="{{ route('lk-wahlen.index') }}" class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                <div class="col-span-1 md:col-span-2">
                                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Suche</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input type="text" name="search" id="search" 
                                               class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" 
                                               placeholder="Name oder Klasse..."
                                               value="{{ request('search') }}">
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" id="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                        <option value="alle" {{ request('status') == 'alle' ? 'selected' : '' }}>Alle anzeigen</option>
                                        <option value="erstwahl" {{ request('status') == 'erstwahl' ? 'selected' : '' }}>Erstwahl erhalten</option>
                                        <option value="zweitwahl" {{ request('status') == 'zweitwahl' ? 'selected' : '' }}>Zweitwahl erhalten</option>
                                        <option value="keine" {{ request('status') == 'keine' ? 'selected' : '' }}>Keine Wahl (in Zuteilung)</option>
                                        <option value="nicht_zugeteilt" {{ request('status') == 'nicht_zugeteilt' ? 'selected' : '' }}>Nicht zugeteilt</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Filter anwenden
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        @if($wahlen->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klasse</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schüler</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Erstwahl</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zweitwahl</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zugeteilt</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($wahlen as $wahl)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $wahl->schueler->class_name ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $wahl->schueler->lastname ?? '' }}, {{ $wahl->schueler->firstname ?? '' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $wahl->erstwahl->name ?? '-' }}
                                                    @if($wahl->is_erstwahl_extern)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 ml-1">
                                                            Extern
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $wahl->zweitwahl->name ?? '-' }}
                                                    @if($wahl->is_zweitwahl_extern)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 ml-1">
                                                            Extern
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    @if($wahl->zugeteilterKurs)
                                                        {{ $wahl->zugeteilterKurs->subject->name ?? '-' }}
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if($wahl->prioritaet_erhalten === 'erstwahl')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            Erstwahl
                                                        </span>
                                                    @elseif($wahl->prioritaet_erhalten === 'zweitwahl')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            Zweitwahl
                                                        </span>
                                                    @elseif($wahl->zuteilung_lk_kurs_id)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Keine Wahl
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            Nicht zugeteilt
                                                        </span>
                                                    @endif

                                                </td>
                                                <td class="px-4 py-3 text-right text-sm font-medium">
                                                    @unlessarchive
                                                    <div class="flex justify-end space-x-2">
                                                        <a href="{{ route('lk-wahlen.edit', $wahl->id) }}" class="text-indigo-600 hover:text-indigo-900" title="Bearbeiten">
                                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </a>
                                                        <form action="{{ route('lk-wahlen.destroy', $wahl->id) }}" method="POST" class="inline-block" data-confirm="Wahl wirklich löschen?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Löschen">
                                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    @endarchive
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            {{-- Pagination --}}
                            <div class="mt-4">
                                {{ $wahlen->links() }}
                            </div>
                        @else
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Wahlen</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Es sind noch keine Wahlen vorhanden.
                                </p>
                                <div class="mt-6">
                                    @unlessarchive
                                    <a href="{{ route('lk-wahlen.import') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        Wahlen importieren
                                    </a>
                                    @endarchive
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        {{-- Subject Details Modal --}}
        <div x-show="modalOpen" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div x-show="modalOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                     @click="modalOpen = false"></div>

                {{-- Modal panel --}}
                <div x-show="modalOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title" x-text="modalData.subject + ' - Wahldetails'"></h3>
                                
                                {{-- Loading State --}}
                                <div x-show="loading" class="text-center py-8">
                                    <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="mt-2 text-gray-500">Lade Daten...</p>
                                </div>

                                {{-- Content --}}
                                <div x-show="!loading" class="space-y-6">
                                    {{-- Summary --}}
                                    <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
                                        <div>
                                            <p class="text-sm text-gray-500">Erstwahlen</p>
                                            <p class="text-2xl font-semibold text-blue-600" x-text="modalData.total_erstwahlen"></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Zweitwahlen</p>
                                            <p class="text-2xl font-semibold text-green-600" x-text="modalData.total_zweitwahlen"></p>
                                        </div>
                                    </div>

                                    {{-- Erstwahlen Table --}}
                                    <div>
                                        <h4 class="text-md font-semibold mb-2 text-blue-700">Erstwahlen</h4>
                                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-blue-50 sticky top-0">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Klasse</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zweitwahl</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zugeteilt</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <template x-for="(student, index) in modalData.erstwahlen" :key="student.id">
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-3 py-2 text-sm text-gray-500" x-text="index + 1"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.class"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.name"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.zweitwahl"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.assigned || '-'"></td>
                                                        </tr>
                                                    </template>
                                                    <template x-if="modalData.erstwahlen.length === 0">
                                                        <tr>
                                                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">Keine Erstwahlen vorhanden</td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {{-- Zweitwahlen Table --}}
                                    <div>
                                        <h4 class="text-md font-semibold mb-2 text-green-700">Zweitwahlen</h4>
                                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-green-50 sticky top-0">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Klasse</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Erstwahl</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zugeteilt</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <template x-for="(student, index) in modalData.zweitwahlen" :key="student.id">
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-3 py-2 text-sm text-gray-500" x-text="index + 1"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.class"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.name"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.erstwahl"></td>
                                                            <td class="px-3 py-2 text-sm text-gray-900" x-text="student.assigned || '-'"></td>
                                                        </tr>
                                                    </template>
                                                    <template x-if="modalData.zweitwahlen.length === 0">
                                                        <tr>
                                                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">Keine Zweitwahlen vorhanden</td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" 
                                @click="modalOpen = false"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Schließen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart.js Script --}}
    @if(count($fachVerteilung) > 0)
        <script @cspNonce>
            document.addEventListener('DOMContentLoaded', async function() {
                // Chart.js liegt im Projekt und wird hier nachgeladen (Modul 7.2);
                // vom CDN blockiert die CSP es.
                const Chart = await window.chartJsLaden();

                const ctx = document.getElementById('wahlChart').getContext('2d');
                const chartData = @json($chartData);

                new Chart(ctx, {
                    type: 'bar',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            });
        </script>
    @endif

    {{-- Alpine.js Component --}}
    <script @cspNonce>
        function wahlData() {
            return {
                modalOpen: false,
                loading: false,
                modalData: {
                    subject: '',
                    erstwahlen: [],
                    zweitwahlen: [],
                    total_erstwahlen: 0,
                    total_zweitwahlen: 0
                },

                showSubjectModal(subjectName) {
                    this.modalOpen = true;
                    this.loading = true;
                    this.modalData = {
                        subject: subjectName,
                        erstwahlen: [],
                        zweitwahlen: [],
                        total_erstwahlen: 0,
                        total_zweitwahlen: 0
                    };

                    fetch('{{ route("lk-wahlen.subject-details") }}?subject_name=' + encodeURIComponent(subjectName), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.modalData = data;
                        this.loading = false;
                    })
                    .catch(error => {
                        console.error('Error loading subject details:', error);
                        this.loading = false;
                        alert('Fehler beim Laden der Daten');
                    });
                }
            }
        }
    </script>
</x-app-layout>
