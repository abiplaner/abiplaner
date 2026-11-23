<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                W-Seminar-Wahlen
            </h2>
            <div class="flex space-x-3">
                @unlessarchive
                <a href="{{ route('wseminar-kurse.planen', ['schuljahr' => $latestSchuljahr, 'jahrgang' => $latestJahrgang]) }}" 
                   class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    Zuteilung planen
                </a>
                @endarchive
                <a href="{{ route('wseminar-kurse.index', ['schuljahr' => $latestSchuljahr, 'jahrgang' => $latestJahrgang]) }}" 
                   class="px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                    Aktuelle Zuteilung
                </a>
                @unlessarchive
                <a href="{{ route('wseminar-wahlen.import') }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Wahlen importieren
                </a>

                <form action="{{ route('wseminar-wahlen.destroy-all') }}" method="POST" class="inline" 
                      data-confirm="Sind Sie sicher? Alle Wahlen des Jahrgangs Q11 werden unwiderruflich gelöscht.">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="schuljahr" value="{{ $latestSchuljahr }}">
                    <input type="hidden" name="jahrgang" value="{{ $latestJahrgang }}">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Alle Wahlen löschen
                    </button>
                </form>
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Importstatistiken --}}
            @if (session('import_stats'))
                @php $stats = session('import_stats'); @endphp
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3 w-full">
                            <h3 class="text-sm font-medium text-blue-800">Importstatistik</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <p class="font-semibold">{{ $stats['imported'] }}</p>
                                        <p class="text-xs">Wahlen importiert</p>
                                    </div>
                                    @if(isset($stats['students_found']))
                                    <div>
                                        <p class="font-semibold">{{ $stats['students_found'] }}</p>
                                        <p class="text-xs">Schüler gefunden</p>
                                    </div>
                                    @endif
                                    @if(isset($stats['students_created']))
                                    <div>
                                        <p class="font-semibold">{{ $stats['students_created'] }}</p>
                                        <p class="text-xs">Schüler neu angelegt</p>
                                    </div>
                                    @endif
                                    @if(isset($stats['kurse_found']))
                                    <div>
                                        <p class="font-semibold">{{ $stats['kurse_found'] }}</p>
                                        <p class="text-xs">W-Seminare gefunden</p>
                                    </div>
                                    @endif
                                    @if(isset($stats['kurse_created']))
                                    <div>
                                        <p class="font-semibold">{{ $stats['kurse_created'] }}</p>
                                        <p class="text-xs">W-Seminare neu angelegt</p>
                                    </div>
                                    @endif
                                </div>
                                
                                @if(isset($stats['kurse_list']) && count($stats['kurse_list']) > 0)
                                <div class="mt-3 pt-3 border-t border-blue-200">
                                    <p class="font-medium mb-2">Gefundene/Erstellte W-Seminare:</p>
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach($stats['kurse_list'] as $kurs)
                                            <li>{{ $kurs }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                @endif
                            </div>
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

            {{-- Filter --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('wseminar-wahlen.index') }}" class="flex flex-wrap gap-4 items-end">
                        {{-- Suche --}}
                        <div class="flex-1 min-w-[200px]">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                                Suche (Name/Klasse)
                            </label>
                            <input type="text" 
                                   name="search" 
                                   id="search" 
                                   value="{{ request('search') }}"
                                   placeholder="Name oder Klasse..."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        {{-- Status --}}
                        <div class="w-48">
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                Status
                            </label>
                            <select name="status" 
                                    id="status"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="alle" {{ request('status') == 'alle' ? 'selected' : '' }}>Alle</option>
                                <option value="nicht_zugeteilt" {{ request('status') == 'nicht_zugeteilt' ? 'selected' : '' }}>Nicht zugeteilt</option>
                                <option value="erstwahl" {{ request('status') == 'erstwahl' ? 'selected' : '' }}>Erstwahl erhalten</option>
                                <option value="zweitwahl" {{ request('status') == 'zweitwahl' ? 'selected' : '' }}>Zweitwahl erhalten</option>
                                <option value="drittwahl" {{ request('status') == 'drittwahl' ? 'selected' : '' }}>Drittwahl erhalten</option>
                                <option value="keine" {{ request('status') == 'keine' ? 'selected' : '' }}>Keine Wahl erhalten</option>
                            </select>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex gap-2">
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Filtern
                            </button>
                            <a href="{{ route('wseminar-wahlen.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Zurücksetzen
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Wahlen-Tabelle --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    #
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer group">
                                    <a href="{{ route('wseminar-wahlen.index', array_merge(request()->query(), ['sort' => 'lastname', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center">
                                        Schüler
                                        @if(request('sort') === 'lastname')
                                            <svg class="ml-1 h-4 w-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('direction') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                            </svg>
                                        @else
                                            <svg class="ml-1 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer group">
                                    <a href="{{ route('wseminar-wahlen.index', array_merge(request()->query(), ['sort' => 'class_name', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center">
                                        Klasse
                                        @if(request('sort') === 'class_name')
                                            <svg class="ml-1 h-4 w-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('direction') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                            </svg>
                                        @else
                                            <svg class="ml-1 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Erstwahl
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Zweitwahl
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Drittwahl
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer group">
                                    <a href="{{ route('wseminar-wahlen.index', array_merge(request()->query(), ['sort' => 'zuteilung', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center">
                                        Zugeteilt
                                        @if(request('sort') === 'zuteilung')
                                            <svg class="ml-1 h-4 w-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('direction') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                            </svg>
                                        @else
                                            <svg class="ml-1 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer group">
                                    <a href="{{ route('wseminar-wahlen.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center">
                                        Status
                                        @if(request('sort') === 'status')
                                            <svg class="ml-1 h-4 w-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('direction') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                            </svg>
                                        @else
                                            <svg class="ml-1 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($wahlen as $wahl)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ ($wahlen->currentPage() - 1) * $wahlen->perPage() + $loop->iteration }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            @if($wahl->schueler)
                                                {{ $wahl->schueler->lastname }}, {{ $wahl->schueler->firstname }}
                                            @else
                                                <span class="text-red-600">Schüler gelöscht (ID: {{ $wahl->schueler_id }})</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $wahl->schueler->class_name ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        @if($wahl->erstwahl)
                                            <div class="font-medium">{{ $wahl->erstwahl->subject?->name ?? 'Unbekannt' }}</div>
                                            <div class="text-xs text-gray-500">{{ Str::limit($wahl->erstwahl->titel, 30) }}</div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        @if($wahl->zweitwahl)
                                            <div class="font-medium">{{ $wahl->zweitwahl->subject?->name ?? 'Unbekannt' }}</div>
                                            <div class="text-xs text-gray-500">{{ Str::limit($wahl->zweitwahl->titel, 30) }}</div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        @if($wahl->drittwahl)
                                            <div class="font-medium">{{ $wahl->drittwahl->subject?->name ?? 'Unbekannt' }}</div>
                                            <div class="text-xs text-gray-500">{{ Str::limit($wahl->drittwahl->titel, 30) }}</div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        @if($wahl->zugeteilterKurs)
                                            <div class="font-medium">{{ $wahl->zugeteilterKurs->subject?->name ?? 'Unbekannt' }}</div>
                                            <div class="text-xs text-gray-500">{{ Str::limit($wahl->zugeteilterKurs->titel, 30) }}</div>
                                        @else
                                            <span class="text-gray-400">Nicht zugeteilt</span>
                                        @endif
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
                                        @elseif($wahl->prioritaet_erhalten === 'keine' && $wahl->zugeteilterKurs)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Keine Wahl
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Offen
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @unlessarchive
                                        <a href="{{ route('wseminar-wahlen.edit', $wahl) }}" class="text-blue-600 hover:text-blue-900">Bearbeiten</a>
                                        @endarchive
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Keine Wahlen vorhanden.
                                        @unlessarchive
                                        <a href="{{ route('wseminar-wahlen.import') }}" class="text-blue-600 hover:text-blue-800 underline">
                                            Wahlen importieren
                                        </a>
                                        @endarchive
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($wahlen->hasPages())
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        {{ $wahlen->links() }}
                    </div>
                @endif
            </div>

            {{-- Info-Box --}}
            @if($wahlen->count() > 0)
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Hinweise</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>W-Seminare finden nur in Q11 statt</li>
                                    <li>Jedes W-Seminar hat maximal 15 Teilnehmer</li>
                                    <li>Schüler können 3 Prioritäten wählen</li>
                                    <li>Die Zuteilung erfolgt später über einen separaten Planungsprozess</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
