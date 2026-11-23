<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                W-Seminar-Wahlen importieren
            </h2>
            <a href="{{ route('wseminar-wahlen.index') }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Anleitung --}}
            <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">CSV-Format</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p class="mb-2">Die CSV-Datei muss folgendes Format haben (<strong>Export aus dem Infoportal</strong>):</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>Zeile 1 (Header):</strong> W-Seminar-Titel in Spalten ab D</li>
                                <li><strong>Struktur:</strong> Klasse; Name; Kommentar; W-Seminar 1; W-Seminar 2; ...</li>
                                <li><strong>Format:</strong> Semikolon-getrennt (CSV)</li>
                                <li><strong>Werte:</strong> "1" für Erstwahl, "2" für Zweitwahl, "3" für Drittwahl</li>
                                <li><strong>Max. Teilnehmer:</strong> Jedes W-Seminar hat maximal 15 Plätze</li>
                            </ul>
                            <p class="mt-3 font-medium">Beispiel:</p>
                            <code class="block bg-white p-2 mt-1 rounded text-xs font-mono overflow-x-auto whitespace-pre">Klasse;Vollständiger Name;Kommentar;"Wirtschaft und Recht - Aktuelle Herausforderungen";"Englisch - Hogwarts, Narnia and Middle-earth"
11a;Hans Wurst;;2;3;1
11a;Anna Schmidt;;;1;2</code>
                            <p class="mt-3"><strong>Wichtig:</strong></p>
                            <ul class="list-disc list-inside space-y-1 text-xs">
                                <li>Schülername muss "Vorname Nachname" sein (Trennung beim letzten Leerzeichen)</li>
                                <li>W-Seminar-Titel im Format "Fach - Titel" im Header</li>
                                <li>Schüler können 3 Prioritäten wählen (1, 2, 3)</li>
                                <li>W-Seminare werden automatisch angelegt, wenn sie noch nicht existieren</li>
                                <li>Nur Jahrgang Q11 (da W-Seminare nur in Q11 stattfinden)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fehleranzeige --}}
            @if ($errors->any())
                <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Fehler beim Import</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Validierungs-Fehler --}}
            @if (session('validation_errors'))
                <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Validierungs-Fehler</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside">
                                    @foreach (session('validation_errors') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Import-Fehler --}}
            @if (session('import_errors'))
                <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Import-Fehler</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside">
                                    @foreach (session('import_errors') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Upload-Formular --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">CSV-Datei hochladen</h3>

                    <form action="{{ route('wseminar-wahlen.store-import') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        {{-- Hidden Default Values --}}
                        <input type="hidden" name="schuljahr" value="{{ $schuljahre[1] ?? ($schuljahre[0] ?? '') }}">
                        <input type="hidden" name="jahrgang" value="11">

                        {{-- CSV-Datei --}}
                        <div>
                            <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-2">
                                CSV-Datei *
                            </label>
                            <input type="file" 
                                   name="csv_file" 
                                   id="csv_file" 
                                   accept=".csv,.txt"
                                   required
                                   class="mt-1 block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100">
                            <p class="mt-1 text-xs text-gray-500">
                                Max. 2 MB, Format: CSV (UTF-8)
                            </p>
                            @error('csv_file')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('wseminar-wahlen.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Importieren
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
