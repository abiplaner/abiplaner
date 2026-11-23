<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Lehrer-Import: Vorschau
            </h2>
            <a href="{{ route('teachers.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <!-- Statistik-Karten -->
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                        <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg text-center">
                            <span class="block text-2xl font-bold text-blue-700 dark:text-blue-200">{{ $stats['total'] }}</span>
                            <span class="text-sm text-blue-600 dark:text-blue-300">CSV Zeilen</span>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg text-center">
                            <span class="block text-2xl font-bold text-green-700 dark:text-green-200">{{ $stats['created'] }}</span>
                            <span class="text-sm text-green-600 dark:text-green-300">Neu</span>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900 p-4 rounded-lg text-center">
                            <span class="block text-2xl font-bold text-yellow-700 dark:text-yellow-200">{{ $stats['updated'] }}</span>
                            <span class="text-sm text-yellow-600 dark:text-yellow-300">Update</span>
                        </div>
                        <div class="bg-orange-50 dark:bg-orange-900 p-4 rounded-lg text-center">
                            <span class="block text-2xl font-bold text-orange-700 dark:text-orange-200">{{ $stats['deactivatable'] }}</span>
                            <span class="text-sm text-orange-600 dark:text-orange-300">Nicht in CSV</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg text-center">
                            <span class="block text-2xl font-bold text-gray-700 dark:text-gray-200">{{ $stats['already_inactive'] }}</span>
                            <span class="text-sm text-gray-600 dark:text-gray-300">Bereits inaktiv</span>
                        </div>
                        @if($stats['subject_errors'] > 0)
                        <div class="bg-red-50 dark:bg-red-900 p-4 rounded-lg text-center border-l-4 border-red-600">
                            <span class="block text-2xl font-bold text-red-700 dark:text-red-200">{{ $stats['subject_errors'] }}</span>
                            <span class="text-sm text-red-600 dark:text-red-300">Fächerfehler</span>
                        </div>
                        @endif
                    </div>

                    @if($stats['subject_errors'] > 0)
                    <div class="bg-red-50 dark:bg-red-900 border-l-4 border-red-500 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700 dark:text-red-200">
                                    <strong>Achtung:</strong> {{ $stats['subject_errors'] }} Lehrer haben Fächer, die nicht in der Datenbank existieren. 
                                    Diese Fächer werden rot markiert und beim Import ignoriert.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Kompakte Tabelle -->
                    <form action="{{ route('teachers.import') }}" method="POST" id="importForm">
                        @csrf
                        <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                        
                        <div class="bg-white dark:bg-gray-700 shadow overflow-x-auto sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <input type="checkbox" id="selectAll" class="rounded" />
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Aktion
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Kürzel
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Titel
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Beschäftigung
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Std.
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Fächer
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Änderungen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                    @foreach($csvTeachers as $teacher)
                                    <tr class="
                                        @if($teacher['action'] === 'create') bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30
                                        @elseif($teacher['action'] === 'update') bg-yellow-50 dark:bg-yellow-900/20 hover:bg-yellow-100 dark:hover:bg-yellow-900/30
                                        @endif
                                    ">
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <input type="checkbox" 
                                                   name="ignored[]" 
                                                   value="{{ $teacher['shortcode'] }}"
                                                   class="teacher-checkbox rounded" />
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            @if($teacher['action'] === 'create')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Neu
                                                </span>
                                            @elseif($teacher['action'] === 'update')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Update
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $teacher['shortcode'] }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {{ $teacher['name'] }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $teacher['title'] ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $teacher['employment_type'] ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ $teacher['hours'] ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($teacher['subjects'] as $subject)
                                                    @if($subject['valid'])
                                                        @php
                                                            $tooltip = $subject['name'];
                                                            // Zeige Mapping wenn normalisiert
                                                            if (isset($subject['normalized']) && $subject['code'] !== $subject['normalized']) {
                                                                $tooltip .= " (wird als '{$subject['normalized']}' behandelt)";
                                                            }
                                                        @endphp
                                                        <span class="px-2 py-0.5 text-xs rounded bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                                              title="{{ $tooltip }}">
                                                            {{ $subject['code'] }}
                                                            @if(isset($subject['normalized']) && $subject['code'] !== $subject['normalized'])
                                                                <span class="text-blue-600">→{{ $subject['normalized'] }}</span>
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="px-2 py-0.5 text-xs rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"
                                                              title="Fach nicht in Datenbank!">
                                                            {{ $subject['code'] }} ⚠️
                                                        </span>
                                                    @endif
                                                @endforeach
                                                @if(empty($teacher['subjects']))
                                                    <span class="text-gray-400 text-xs">Keine</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-300">
                                            @if($teacher['action'] === 'update' && !empty($teacher['changes']))
                                                <ul class="text-xs space-y-0.5">
                                                    @foreach($teacher['changes'] as $change)
                                                        <li class="text-yellow-700 dark:text-yellow-400">{{ $change }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(count($deactivateProposals) > 0)
                        <!-- Deaktivierungsvorschläge (Modul 1.1): aktiv in DB, fehlen in CSV -->
                        <div class="mt-6 bg-white dark:bg-gray-700 shadow overflow-x-auto sm:rounded-lg border-l-4 border-orange-400">
                            <div class="p-4">
                                <h4 class="font-semibold text-orange-700 dark:text-orange-300 mb-1">
                                    🚪 Deaktivierungsvorschläge ({{ count($deactivateProposals) }})
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-300 mb-3">
                                    Diese Lehrkräfte sind aktiv, fehlen aber in der CSV. Markierte werden auf „inaktiv"
                                    gesetzt (nichts wird gelöscht, Historie bleibt erhalten); nicht markierte bleiben aktiv.
                                </p>
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                                <label class="flex items-center gap-1 cursor-pointer">
                                                    <input type="checkbox" id="selectAllDeactivate" class="rounded" />
                                                    <span class="normal-case font-normal">Deaktivieren</span>
                                                </label>
                                            </th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Kürzel</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Fächer</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Verwendung</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                        @foreach($deactivateProposals as $teacher)
                                        <tr class="bg-orange-50 dark:bg-orange-900/20">
                                            <td class="px-3 py-2">
                                                <input type="checkbox"
                                                       name="deactivate[]"
                                                       value="{{ $teacher['shortcode'] }}"
                                                       class="deactivate-checkbox rounded" />
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap font-mono font-semibold text-orange-700 dark:text-orange-300">{{ $teacher['shortcode'] }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ $teacher['name'] }}</td>
                                            <td class="px-3 py-2">
                                                @foreach($teacher['subjects'] as $subject)
                                                    <span class="inline-block px-2 py-0.5 rounded text-xs mr-1 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $subject['code'] }}</span>
                                                @endforeach
                                            </td>
                                            <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-300">
                                                {{ !empty($teacher['dependencies']) ? implode(', ', $teacher['dependencies']) : '—' }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Aktionen -->
                        <div class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <span id="selectedCount">0</span> Lehrer zum Ignorieren ausgewählt
                            </div>
                            
                            <div class="flex gap-3">
                                <a href="{{ route('teachers.import.form') }}" 
                                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                                    Abbrechen
                                </a>
                                
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition flex items-center shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                    Freigeben & Importieren
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script @cspNonce>
        // Select All Checkbox Funktionalität
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.teacher-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });

        // Update Selected Count
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.teacher-checkbox:checked');
            document.getElementById('selectedCount').textContent = checkboxes.length;
        }

        // Event Listener für einzelne Checkboxen
        document.querySelectorAll('.teacher-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedCount();
                
                // Update "Select All" Status
                const allCheckboxes = document.querySelectorAll('.teacher-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.teacher-checkbox:checked');
                document.getElementById('selectAll').checked = allCheckboxes.length === checkedCheckboxes.length;
            });
        });

        // Initial Count
        updateSelectedCount();

        // "Alle deaktivieren"-Checkbox im Vorschlagsblock (Modul 1.1)
        const selectAllDeactivate = document.getElementById('selectAllDeactivate');
        if (selectAllDeactivate) {
            selectAllDeactivate.addEventListener('change', function() {
                document.querySelectorAll('.deactivate-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
    </script>
</x-app-layout>
