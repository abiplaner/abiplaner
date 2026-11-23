<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Schülerdaten importieren: {{ $examPeriod->name }}
            </h2>
            <a href="{{ route('dashboard') }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8" x-data="{ mode: 'full' }">

            {{-- Umschalter --}}
            <div class="mb-6 flex gap-2">
                <button type="button" @click="mode = 'full'"
                        :class="mode === 'full' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-md text-sm font-medium">
                    Vollständiger Import (mit Kursen/Prüfungen)
                </button>
                <button type="button" @click="mode = 'simple'"
                        :class="mode === 'simple' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-md text-sm font-medium">
                    Einfache Jahrgangsliste (nur Namen, ohne Kurse)
                </button>
            </div>

            <div x-show="mode === 'full'">
            {{-- Hervorgehobener Hinweis: Datenquelle in ASV --}}
            <div class="mb-6 bg-amber-50 border-2 border-amber-400 rounded-lg p-4 shadow-sm">
                <div class="flex items-start">
                    <div class="flex-shrink-0 text-amber-600 text-xl mr-3">📌</div>
                    <div>
                        <h3 class="text-base font-bold text-amber-900">
                            Woher stammen die Daten? (ASV-Berichtsbibliothek)
                        </h3>
                        <p class="mt-1 text-sm text-amber-900">
                            Exportieren Sie die CSV-Datei in der <strong>ASV-Berichtsbibliothek</strong> im Bereich
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-200 text-amber-900">Schüler</span>
                            über den Bericht
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-200 text-amber-900">Abiturfächer Kurse</span>.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Anleitung --}}
            <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">CSV-Format & Validierung</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p class="mb-2">Die CSV-Datei muss folgendes Format haben:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>Zeile 1 (Schüler):</strong> Leer; Name, Vorname; ...</li>
                                <li><strong>Zeile 2 (Daten):</strong> Nr.; Leer; <strong class="text-red-700">S1-Kurs; S1-Lehrer; S2-Kurs; S2-Lehrer; S3-Kurs; S3-Lehrer</strong>; K1-Kurs; K1-Lehrer; K2-Kurs; K2-Lehrer</li>
                            </ul>
                            <p class="mt-3 font-medium text-red-700">⚠️ WICHTIG: Validierung erforderlich!</p>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li><strong>Alle Fächer</strong> müssen bereits in der Fächerverwaltung existieren</li>
                                <li><strong>Alle Lehrer-Kürzel</strong> müssen bereits in der Lehrerverwaltung existieren</li>
                                <li>Der Import zeigt eine Vorschau mit Validierung (rot/grün)</li>
                                <li>Speichern ist nur möglich, wenn alle Zuordnungen gültig sind</li>
                            </ul>
                            <p class="mt-2">Beispiel:</p>
                            <code class="block bg-white p-2 mt-1 rounded text-xs">
                                ;Mustermann, Max;;;;;;;;;;<br>
                                1;;<strong class="text-red-700">2E-L2;BCH;2d2;TEG;2geo1;ANG;</strong>2eth1;KUR;2m2;DOL
                            </code>
                            <p class="mt-2 text-xs">
                                <strong>Legende:</strong> Nr. | Name | <strong class="text-red-700">S1-Kurs | S1-Lehrer | S2-Kurs | S2-Lehrer | S3-Kurs | S3-Lehrer</strong> | K1-Kurs | K1-Lehrer | K2-Kurs | K2-Lehrer
                            </p>
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

                    <form action="{{ route('import.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
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
                            <p class="mt-1 text-sm text-gray-500">Nur CSV-Dateien (.csv, .txt)</p>
                        </div>

                        {{-- Trennzeichen --}}
                        <div>
                            <label for="delimiter" class="block text-sm font-medium text-gray-700 mb-2">
                                Trennzeichen *
                            </label>
                            <select name="delimiter" 
                                    id="delimiter" 
                                    required
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value=";" selected>Semikolon (;)</option>
                                <option value=",">Komma (,)</option>
                                <option value="\t">Tab</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Standard für deutsche CSV-Dateien: Semikolon</p>
                        </div>

                        {{-- Jahrgangsstufe --}}
                        <div>
                            <label for="grade" class="block text-sm font-medium text-gray-700 mb-2">
                                📚 Jahrgangsstufe *
                            </label>
                            <select name="grade" 
                                    id="grade" 
                                    required
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="13" selected>Q13 (13. Klasse) - Abitur</option>
                                <option value="12">Q12 (12. Klasse)</option>
                                <option value="11">Q11 (11. Klasse)</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                ⚠️ Beim Import werden nur Schüler dieser Jahrgangsstufe ersetzt
                            </p>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('dashboard') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            {{-- Diff-Modus: nur Änderungen anzeigen --}}
                            <button type="submit"
                                    formaction="{{ route('import.diff-preview') }}"
                                    style="background-color: #d97706;"
                                    class="px-4 py-2 text-white rounded-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2">
                                🔍 Nur Änderungen anzeigen
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Vorschau anzeigen
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Zusätzliche Informationen --}}
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Was wird importiert?</h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium">Schülerdaten</p>
                                <p class="text-gray-600">Name, Vorname und Schülernummer werden aus der CSV gelesen</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium">Schriftliche Prüfungen (S1, S2, S3)</p>
                                <p class="text-gray-600">Kurs und Prüfer aus Spalten 2-7 → Keine Wochenzuordnung</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium">Kolloquium 1 (K1)</p>
                                <p class="text-gray-600">Kurs und Prüfer aus Spalten 8-9 (Wochenzuordnung erfolgt später)</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium">Kolloquium 2 (K2)</p>
                                <p class="text-gray-600">Kurs und Prüfer aus Spalten 10-11 (Wochenzuordnung erfolgt später)</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start mt-4 pt-4 border-t">
                            <svg class="w-5 h-5 text-yellow-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium text-yellow-700">Validierungspflicht</p>
                                <p class="text-gray-600">Alle Fächer und Lehrer müssen vor dem Import in der Datenbank existieren. Die Vorschau zeigt fehlende Einträge rot an.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start mt-3">
                            <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium text-blue-700">Externe Prüfungen</p>
                                <p class="text-gray-600">Für Prüfungen an externen Schulen (z.B. Türkisch) verwenden Sie das Lehrer-Kürzel <strong>"EXTERN"</strong>. Dieser Platzhalter-Lehrer existiert automatisch in jeder Schule.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            {{-- Einfache Jahrgangsliste --}}
            <div x-show="mode === 'simple'">
                <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4 text-sm text-blue-700">
                    <p class="mb-1">Für Jahrgänge ohne Abiturprüfungs-Zuordnung (z. B. Q12): legt nur
                       Schüler-Stammdaten an (Name, Jahrgangsstufe), keine Kurse/Prüfungen.</p>
                    <p>Format: eine Zeile pro Schüler, <code class="bg-white px-1 rounded">Nachname;Vorname</code>,
                       erste Zeile wird als Kopfzeile übersprungen. Bereits vorhandene Schüler (gleicher
                       Name in derselben Jahrgangsstufe) werden übersprungen, kein Duplikat.</p>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Jahrgangsliste hochladen</h3>

                        <form action="{{ route('import.simple-preview') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                            @csrf
                            <div>
                                <label for="simple_file" class="block text-sm font-medium text-gray-700 mb-2">
                                    CSV-Datei auswählen *
                                </label>
                                <input type="file" name="file" id="simple_file" accept=".csv,.txt" required
                                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label for="simple_grade" class="block text-sm font-medium text-gray-700 mb-2">
                                    📚 Jahrgangsstufe *
                                </label>
                                <select name="grade" id="simple_grade" required
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="12" selected>Q12 (12. Klasse)</option>
                                    <option value="13">Q13 (13. Klasse)</option>
                                    <option value="11">11. Klasse</option>
                                </select>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <a href="{{ route('dashboard') }}"
                                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Abbrechen
                                </a>
                                <button type="submit"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Vorschau anzeigen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>