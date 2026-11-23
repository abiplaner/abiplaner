<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Import-Vorschau: {{ $examPeriod->name }}
            </h2>
            <a href="{{ route('import.form') }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Statistiken --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📊 Import-Statistik</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $preview['stats']['valid_students'] }}</div>
                            <div class="text-sm text-gray-600">Schüler gefunden</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $preview['stats']['valid_students'] - $preview['stats']['validation_errors'] }}</div>
                            <div class="text-sm text-gray-600">Vollständig gültig</div>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">{{ $preview['stats']['validation_errors'] }}</div>
                            <div class="text-sm text-gray-600">Mit Fehlern</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-gray-600">{{ $preview['stats']['skipped_students'] }}</div>
                            <div class="text-sm text-gray-600">Übersprungen</div>
                        </div>
                    </div>
                    
                    @if($preview['stats']['validation_errors'] > 0)
                        <div class="mt-4 p-4 bg-red-50 border-l-4 border-red-400">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">❌ Import nicht möglich!</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        Es gibt {{ $preview['stats']['validation_errors'] }} Schüler mit Validierungsfehlern. 
                                        Bitte korrigieren Sie die rot markierten Einträge in der CSV-Datei oder legen Sie die fehlenden Fächer/Lehrer an.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 p-4 bg-green-50 border-l-4 border-green-400">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">✅ Alle Daten gültig!</h3>
                                    <div class="mt-2 text-sm text-green-700">
                                        Sie können den Import jetzt durchführen. Alle Zuordnungen wurden erfolgreich validiert.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Legende --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📖 Legende</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-green-200 border border-green-400 rounded"></div>
                            <span>✓ Fach und Lehrer gefunden</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-red-200 border border-red-400 rounded"></div>
                            <span>✗ Fach oder Lehrer fehlt</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-gray-100 border border-gray-300 rounded"></div>
                            <span>- Keine Prüfung</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Schülerliste mit Validierung --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">👥 Schüler-Vorschau (alle {{ $preview['stats']['valid_students'] }} Schüler)</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nr.</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S1</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S2</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S3</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">K1</th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">K2</th>
                                    <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($preview['students'] as $student)
                                    <tr class="{{ $student['has_errors'] ? 'bg-red-50' : 'hover:bg-gray-50' }}">
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student['external_id'] }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $student['lastname'] }}, {{ $student['firstname'] }}
                                        </td>
                                        
                                        {{-- S1 --}}
                                        <td class="px-3 py-4 text-sm">
                                            @if($student['s1']['course'])
                                                <div class="p-2 rounded {{ $student['s1']['has_error'] ? 'bg-red-200 border border-red-400' : 'bg-green-200 border border-green-400' }}">
                                                    <div class="font-medium">{{ $student['s1']['subject_name'] ?? $student['s1']['subject_code'] ?? '?' }}</div>
                                                    <div class="text-xs">{{ $student['s1']['course'] }} / {{ $student['s1']['teacher'] }}</div>
                                                    @if(!empty($student['s1']['errors']))
                                                        <div class="text-xs text-red-800 mt-1">
                                                            @foreach($student['s1']['errors'] as $error)
                                                                • {{ $error }}<br>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="p-2 bg-gray-100 border border-gray-300 rounded text-center text-gray-400">-</div>
                                            @endif
                                        </td>
                                        
                                        {{-- S2 --}}
                                        <td class="px-3 py-4 text-sm">
                                            @if($student['s2']['course'])
                                                <div class="p-2 rounded {{ $student['s2']['has_error'] ? 'bg-red-200 border border-red-400' : 'bg-green-200 border border-green-400' }}">
                                                    <div class="font-medium">{{ $student['s2']['subject_name'] ?? $student['s2']['subject_code'] ?? '?' }}</div>
                                                    <div class="text-xs">{{ $student['s2']['course'] }} / {{ $student['s2']['teacher'] }}</div>
                                                    @if(!empty($student['s2']['errors']))
                                                        <div class="text-xs text-red-800 mt-1">
                                                            @foreach($student['s2']['errors'] as $error)
                                                                • {{ $error }}<br>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="p-2 bg-gray-100 border border-gray-300 rounded text-center text-gray-400">-</div>
                                            @endif
                                        </td>
                                        
                                        {{-- S3 --}}
                                        <td class="px-3 py-4 text-sm">
                                            @if($student['s3']['course'])
                                                <div class="p-2 rounded {{ $student['s3']['has_error'] ? 'bg-red-200 border border-red-400' : 'bg-green-200 border border-green-400' }}">
                                                    <div class="font-medium">{{ $student['s3']['subject_name'] ?? $student['s3']['subject_code'] ?? '?' }}</div>
                                                    <div class="text-xs">{{ $student['s3']['course'] }} / {{ $student['s3']['teacher'] }}</div>
                                                    @if(!empty($student['s3']['errors']))
                                                        <div class="text-xs text-red-800 mt-1">
                                                            @foreach($student['s3']['errors'] as $error)
                                                                • {{ $error }}<br>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="p-2 bg-gray-100 border border-gray-300 rounded text-center text-gray-400">-</div>
                                            @endif
                                        </td>
                                        
                                        {{-- K1 --}}
                                        <td class="px-3 py-4 text-sm">
                                            @if($student['k1']['course'])
                                                <div class="p-2 rounded {{ $student['k1']['has_error'] ? 'bg-red-200 border border-red-400' : 'bg-green-200 border border-green-400' }}">
                                                    <div class="font-medium">{{ $student['k1']['subject_name'] ?? $student['k1']['subject_code'] ?? '?' }}</div>
                                                    <div class="text-xs">{{ $student['k1']['course'] }} / {{ $student['k1']['teacher'] }}</div>
                                                    @if(!empty($student['k1']['errors']))
                                                        <div class="text-xs text-red-800 mt-1">
                                                            @foreach($student['k1']['errors'] as $error)
                                                                • {{ $error }}<br>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="p-2 bg-gray-100 border border-gray-300 rounded text-center text-gray-400">-</div>
                                            @endif
                                        </td>
                                        
                                        {{-- K2 --}}
                                        <td class="px-3 py-4 text-sm">
                                            @if($student['k2']['course'])
                                                <div class="p-2 rounded {{ $student['k2']['has_error'] ? 'bg-red-200 border border-red-400' : 'bg-green-200 border border-green-400' }}">
                                                    <div class="font-medium">{{ $student['k2']['subject_name'] ?? $student['k2']['subject_code'] ?? '?' }}</div>
                                                    <div class="text-xs">{{ $student['k2']['course'] }} / {{ $student['k2']['teacher'] }}</div>
                                                    @if(!empty($student['k2']['errors']))
                                                        <div class="text-xs text-red-800 mt-1">
                                                            @foreach($student['k2']['errors'] as $error)
                                                                • {{ $error }}<br>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="p-2 bg-gray-100 border border-gray-300 rounded text-center text-gray-400">-</div>
                                            @endif
                                        </td>
                                        
                                        {{-- Status --}}
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            @if($student['has_errors'])
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded">
                                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                    Fehler
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded">
                                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                    OK
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Import-Formular --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('import.process') }}" method="POST" 
                          @if($preview['stats']['validation_errors'] > 0) 
                              data-confirm="WARNUNG: Es gibt noch Validierungsfehler! Möchten Sie wirklich fortfahren? Fehlerhafte Einträge werden übersprungen." 
                          @else
                              data-confirm="Möchten Sie die {{ $preview['stats']['valid_students'] }} Schüler jetzt importieren? Alte Daten werden überschrieben!"
                          @endif
                          class="space-y-4">
                        @csrf
                        
                        <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                        <input type="hidden" name="delimiter" value="{{ $delimiter }}">
                        <input type="hidden" name="clear_existing" value="1">
                        
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="clear_existing" 
                                       id="clear_existing" 
                                       value="1"
                                       checked
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="clear_existing" class="ml-2 block text-sm text-gray-700">
                                    Alte Schüler und Kurse löschen (empfohlen)
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <a href="{{ route('import.form') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Zurück zum Upload
                            </a>
                            
                            @if($preview['stats']['validation_errors'] == 0)
                                <button type="submit" 
                                        class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                    ✅ Import durchführen
                                </button>
                            @else
                                <button type="submit" 
                                        disabled
                                        class="px-6 py-2 bg-gray-400 text-white rounded-md cursor-not-allowed"
                                        title="Import erst möglich, wenn alle Validierungsfehler behoben sind">
                                    ❌ Import nicht möglich
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>