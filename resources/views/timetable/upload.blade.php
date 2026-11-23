<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Stundenplan importieren
            </h2>
            <a href="{{ route('timetable.exam-schedule') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Zum Klausurenplan
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Fehler!</strong>
                <ul class="mt-2 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Info Box --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">💡 Kombinierter Import</h4>
                <div class="text-sm text-blue-700 space-y-1">
                    <p><strong>Warum beide Dateien?</strong></p>
                    <ul class="list-disc list-inside ml-4 mt-2 space-y-1">
                        <li><strong>Stundenplan</strong> = Source of Truth (welche Lehrer sind aktiv?)</li>
                        <li><strong>Lehrbefähigung</strong> = Zusatzinfos (Name, Amtsbezeichnung)</li>
                        <li>Nur Lehrer aus dem Stundenplan werden importiert</li>
                        <li>Lehrbefähigung ist optional - aber empfohlen für vollständige Namen</li>
                    </ul>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6">Daten hochladen</h3>

                    @unlessarchive
                    <form method="POST" action="{{ route('timetable.preview') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        {{-- Untis Stundenplan --}}
                        <div class="border-b pb-6">
                            <h4 class="font-semibold text-gray-900 mb-4">1. Untis Stundenplan (Pflicht)</h4>
                            <div>
                                <label for="timetable_file" class="block text-sm font-medium text-gray-700 mb-2">
                                    Stundenplan-Datei (TXT/CSV) *
                                </label>
                                <input type="file" 
                                       name="timetable_file" 
                                       id="timetable_file" 
                                       accept=".txt,.csv"
                                       required
                                       class="mt-1 block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-indigo-50 file:text-indigo-700
                                              hover:file:bg-indigo-100">
                                <p class="mt-1 text-sm text-gray-500">
                                    Untis CSV/TXT Export (max. 10 MB) - definiert aktive Lehrer
                                </p>
                            </div>
                        </div>

                        {{-- ASV Lehrerdaten --}}
                        <div class="border-b pb-6">
                            <h4 class="font-semibold text-gray-900 mb-4">2. ASV Lehrbefähigung (Optional)</h4>
                            <div>
                                <label for="teachers_file" class="block text-sm font-medium text-gray-700 mb-2">
                                    Lehrbefähigung-CSV (ASV-Export)
                                </label>
                                <input type="file" 
                                       name="teachers_file" 
                                       id="teachers_file" 
                                       accept=".csv,.txt"
                                       class="mt-1 block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-green-50 file:text-green-700
                                              hover:file:bg-green-100">
                                <p class="mt-1 text-sm text-gray-500">
                                    Optional: CSV mit Namen + Amtsbezeichnungen (max. 2 MB)
                                </p>
                            </div>
                        </div>

                        {{-- Schuljahr --}}
                        <div>
                            <label for="school_year" class="block text-sm font-medium text-gray-700 mb-2">
                                Schuljahr
                            </label>
                            <select name="school_year" 
                                    id="school_year" 
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @php($laufendesJahr = old('school_year', \App\Support\SchoolYear::current()))
                                @foreach(\App\Support\SchoolYear::options($laufendesJahr) as $jahr)
                                    <option value="{{ $jahr }}" {{ $laufendesJahr === $jahr ? 'selected' : '' }}>{{ $jahr }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Optionen --}}
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="only_oberstufe" 
                                       id="only_oberstufe" 
                                       value="1"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <label for="only_oberstufe" class="ml-2 block text-sm text-gray-700">
                                    <strong>Nur Oberstufe</strong> importieren (Q12/Q13)
                                    <span class="block text-xs text-gray-500">Andere Klassen werden übersprungen</span>
                                </label>
                            </div>
                        </div>

                        {{-- Submit Button --}}
                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('teachers.index') }}" 
                               class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                Vorschau anzeigen
                            </button>
                        </div>
                    </form>
                    @else
                    <p class="text-sm text-gray-500">Der Stundenplan-Import ist in der Archivansicht deaktiviert.</p>
                    @endarchive
                </div>
            </div>

            {{-- Hilfe --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                {{-- Untis Hilfe --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">📘 Untis Export</h3>
                        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                            <li>Öffnen Sie Untis</li>
                            <li>Wählen Sie <strong>Datei → Export → Text</strong></li>
                            <li>Tabelle <strong>"Unterricht"</strong> wählen</li>
                            <li>Exportieren als <strong>CSV</strong> oder <strong>TXT</strong></li>
                            <li>Felder: Klasse, Lehrer, Fach/Kurs, Raum, Tag, Stunde</li>
                        </ol>
                        <div class="mt-4 p-3 bg-gray-50 rounded">
                            <p class="text-xs font-mono text-gray-600">
                                Beispiel:<br>
                                1275,"Q12","SNA","1d1","A201",3,3,,
                            </p>
                        </div>
                    </div>
                </div>

                {{-- ASV Hilfe --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">📗 ASV Export (Optional)</h3>
                        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                            <li>Öffnen Sie ASV</li>
                            <li>Gehen Sie zu <strong>Datei → Drucken → Lehrkräfte</strong></li>
                            <li>Wählen Sie <strong>alle Lehrkräfte</strong></li>
                            <li>Exportieren als <strong>CSV (Semikolon-getrennt)</strong></li>
                            <li>Felder: Name, Kürzel, Amtsbezeichnung, Lehrbefähigungen</li>
                        </ol>
                        <div class="mt-4 p-3 bg-gray-50 rounded">
                            <p class="text-xs font-mono text-gray-600">
                                Beispiel:<br>
                                1;Arnold, Jana;ALB;OStR;Mu - GY
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
