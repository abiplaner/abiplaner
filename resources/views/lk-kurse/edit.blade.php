<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Kurs bearbeiten: {{ $kurs->subject->name }} ({{ $kurs->kurs_nummer }})
            </h2>
            <a href="{{ route('lk-kurse.index', ['schuljahr' => $kurs->schuljahr, 'jahrgang' => $kurs->jahrgang]) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                Zurück
            </a>
        </div>
    </x-slot>

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

            {{-- Kurs-Daten bearbeiten --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Kursdetails</h3>
                    
                    <form action="{{ route('lk-kurse.update', $kurs->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Fach --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fach</label>
                                <div class="mt-1 p-2 bg-gray-100 rounded-md border border-gray-300 text-gray-700">
                                    {{ $kurs->subject->name }}
                                </div>
                            </div>
                            
                            {{-- Kursnummer --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Kursnummer</label>
                                <div class="mt-1 p-2 bg-gray-100 rounded-md border border-gray-300 text-gray-700">
                                    LK {{ $kurs->kurs_nummer }}
                                </div>
                            </div>
                            
                            {{-- Lehrer --}}
                            <div>
                                <label for="lehrer_id" class="block text-sm font-medium text-gray-700">Lehrer</label>
                                <select id="lehrer_id" name="lehrer_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">-- Kein Lehrer --</option>
                                    @foreach($availableTeachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ (old('lehrer_id', $kurs->lehrer_id) == $teacher->id) ? 'selected' : '' }}>
                                            {{ $teacher->lastname }}, {{ $teacher->firstname }} ({{ $teacher->shortcode }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            {{-- Notizen --}}
                            <div>
                                <label for="notizen" class="block text-sm font-medium text-gray-700">Notizen</label>
                                <textarea id="notizen" name="notizen" rows="1" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md">{{ old('notizen', $kurs->notizen) }}</textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-blue-600 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            {{-- Zugeteilte Schüler --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Zugeteilte Schüler ({{ $kurs->wahlen->count() }})</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nr.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klasse</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nachname</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vorname</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priorität</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($kurs->wahlen as $wahl)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $loop->iteration }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $wahl->schueler->class_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $wahl->schueler->lastname }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $wahl->schueler->firstname }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            @if($wahl->prioritaet_erhalten == 'erstwahl')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">1. Wahl</span>
                                            @elseif($wahl->prioritaet_erhalten == 'zweitwahl')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">2. Wahl</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Keine</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                            <form action="{{ route('lk-kurse.remove-student', ['id' => $kurs->id]) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="wahl_id" value="{{ $wahl->id }}">
                                                <button type="submit" class="text-red-600 hover:text-red-900" data-confirm="Soll der Schüler wirklich aus dem Kurs entfernt werden?">
                                                    Entfernen
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">Keine Schüler zugewiesen</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Schüler hinzufügen --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Schüler hinzufügen</h3>
                    
                    <form action="{{ route('lk-kurse.add-student', $kurs->id) }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                            <div class="col-span-1 md:col-span-2">
                                <label for="wahl_id" class="block text-sm font-medium text-gray-700">Schüler auswählen</label>
                                <select id="wahl_id" name="wahl_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">-- Bitte wählen --</option>
                                    @foreach($availableStudents as $studentWahl)
                                        <option value="{{ $studentWahl->id }}">
                                            {{ $studentWahl->schueler->lastname }}, {{ $studentWahl->schueler->firstname }} ({{ $studentWahl->schueler->class_name }})
                                            - Wünsche: {{ $studentWahl->erstwahl->name ?? '?' }} / {{ $studentWahl->zweitwahl->name ?? '?' }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    Nur Schüler des gleichen Jahrgangs, die noch keinem Kurs zugewiesen sind (oder einem anderen Kurs zugewiesen sind und hierher verschoben werden sollen).
                                </p>
                            </div>
                            
                            <div>
                                <button type="submit" class="w-full bg-green-600 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Hinzufügen
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
