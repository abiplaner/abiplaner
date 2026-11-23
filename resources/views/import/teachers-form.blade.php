<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lehrer importieren
            </h2>
            <a href="{{ route('teachers.index') }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            <x-asv-berichte :schluessel="\App\Services\ImportLeitfadenService::LEHRKRAEFTE" />

            {{-- Anleitung --}}
            <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">CSV-Format für Lehrer-Import</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p class="mb-2">Die CSV-Datei muss folgendes Format haben:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>Spalte 1:</strong> Nummer</li>
                                <li><strong>Spalte 2:</strong> Name (Format: "Nachname, Vorname")</li>
                                <li><strong>Spalte 3:</strong> Kürzel (z.B. "MUS", "BSP")</li>
                                <li><strong>Spalte 4:</strong> Amtsbezeichnung (z.B. "Oberstudienrat")</li>
                                <li><strong>Spalte 5:</strong> Lehrbefähigungen (z.B. "Mu - GY, E - GY")</li>
                                <li><strong>Spalte 6:</strong> Beschäftigungsart (z.B. "vz", "tz")</li>
                                <li><strong>Spalte 7:</strong> UPZ (z.B. "23.0")</li>
                            </ul>
                            <p class="mt-3">✨ <strong>Hinweise:</strong></p>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li>Bei mehreren Kürzeln wird das <strong>letzte</strong> verwendet</li>
                                <li>Das "- GY" wird automatisch entfernt</li>
                                <li>Fächer werden automatisch angelegt, falls sie nicht existieren</li>
                            </ul>
                            <p class="mt-2">Beispiel:</p>
                            <code class="block bg-white p-2 mt-1 rounded text-xs">
                                Nr.;Name;Lehrkräfte.Kürzel;Lehrkräfte.Amtsbezeichnung.Anzeigeform;Lehrkräfte.Lehrbefähigungen;Lehrkräfte.Beschäftigungsart.Kurzform;Lehrkräfte.UPZ<br>
                                1;Mustermann, Max;MUS;Oberstudienrat;Mu - GY;vz;23.0<br>
                                2;Beispiel, Berta;BSP;Studienrat;E - GY, Geo - GY, Eth - GY;tz;17.0
                            </code>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Upload-Formular --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">CSV-Datei hochladen</h3>

                    @if ($errors->any())
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Fehler beim Upload</h3>
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

                    <form action="{{ route('teachers.import.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        {{-- Datei-Upload --}}
                        <div>
                            <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                                CSV-Datei auswählen *
                            </label>
                            <input type="file" 
                                   name="file" 
                                   id="file" 
                                   accept=".csv,.txt"
                                   required
                                   class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500">
                            <p class="mt-1 text-sm text-gray-500">Nur CSV-Dateien (.csv, .txt) mit Semikolon als Trennzeichen</p>
                        </div>

                        {{-- Submit-Buttons --}}
                        <div class="flex gap-4">
                            <button type="submit"
                                    formaction="{{ route('teachers.import.diff-preview') }}"
                                    style="background-color: #d97706;"
                                    class="px-4 py-2 text-white rounded-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2">
                                🔍 Nur Änderungen anzeigen
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Vorschau anzeigen
                            </button>
                            <a href="{{ route('teachers.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
