<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Import-Vorschau
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Statistik --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📊 Zusammenfassung</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $preview['stats']['total'] }}</div>
                            <div class="text-sm text-blue-700">Gesamt</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $preview['stats']['valid'] }}</div>
                            <div class="text-sm text-green-700">Gültig</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ $preview['stats']['warnings'] }}</div>
                            <div class="text-sm text-yellow-700">Warnungen</div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">{{ $preview['stats']['errors'] }}</div>
                            <div class="text-sm text-red-700">Fehler</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Problematische Lehrer (ohne ASV-Daten) --}}
            @php
                $problematicTeachers = collect($preview['active_teachers'])->filter(fn($t) => !$t['exists_in_db'] && !$t['has_asv_data']);
            @endphp

            @if($problematicTeachers->count() > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" x-data="teacherEditor()">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-orange-700">
                        ⚠️ Lehrer ohne ASV-Daten ({{ $problematicTeachers->count() }})
                    </h3>
                    
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-orange-800">
                            Diese Lehrer haben keine Daten in der Lehrbefähigung-Datei. Sie können die Namen manuell eingeben oder die Lehrer vom Import ausschließen.
                        </p>
                    </div>

                    <div class="space-y-3">
                        @foreach($problematicTeachers as $index => $teacher)
                        <div class="border border-orange-200 rounded-lg p-4 bg-orange-50" 
                             x-data="{ 
                                 code: '{{ $teacher['code'] }}',
                                 editing: false,
                                 deleted: false,
                                 firstname: '',
                                 lastname: '',
                                 position: '',
                                 subjects: '{{ addslashes($teacher['subjects_from_courses'] ?? '') }}'
                             }">
                            
                            <template x-if="!deleted">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <span class="font-bold text-xl text-orange-900" x-text="code"></span>
                                            <span class="text-sm text-gray-600">{{ $teacher['entry_count'] }} Stundenplan-Einträge</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="editing = !editing"
                                                    type="button"
                                                    class="px-3 py-1 text-xs font-semibold rounded-md"
                                                    :class="editing ? 'bg-gray-500 text-white' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'">
                                                <span x-show="!editing">✏️ Bearbeiten</span>
                                                <span x-show="editing">Abbrechen</span>
                                            </button>
                                            <button @click="deleted = true; updateSession()"
                                                    type="button"
                                                    class="px-3 py-1 text-xs font-semibold bg-red-100 text-red-700 hover:bg-red-200 rounded-md">
                                                🗑️ Ausschließen
                                            </button>
                                        </div>
                                    </div>

                                    <div x-show="!editing" class="text-sm text-gray-600">
                                        <template x-if="firstname || lastname">
                                            <div>
                                                <span x-text="firstname"></span> <span x-text="lastname"></span>
                                                <span x-show="position" class="text-xs text-gray-500" x-text="'(' + position + ')'"></span>
                                            </div>
                                        </template>
                                        <template x-if="!firstname && !lastname">
                                            <div class="text-orange-600 italic">
                                                Keine Daten - wird mit Kürzel angelegt
                                            </div>
                                        </template>
                                    </div>

                                    <div x-show="editing" class="grid grid-cols-3 gap-3 mt-3">
                                        <input type="text" 
                                               x-model="firstname"
                                               placeholder="Vorname"
                                               class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                                        <input type="text" 
                                               x-model="lastname"
                                               placeholder="Nachname"
                                               class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                                        <input type="text" 
                                               x-model="position"
                                               placeholder="Amtsbezeichnung (z.B. OStR)"
                                               class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                                        <div class="col-span-3">
                                            <label class="block text-xs text-gray-500 mb-1">
                                                Lehrbefähigung (Fachkürzel, kommagetrennt – aus Kurscodes extrahiert)
                                            </label>
                                            <input type="text" 
                                                   x-model="subjects"
                                                   placeholder="z.B. E, M, PH"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                        </div>
                                        <button @click="editing = false; updateSession()"
                                                type="button"
                                                class="col-span-3 px-4 py-2 bg-green-600 text-white font-semibold rounded-md hover:bg-green-700">
                                            ✓ Speichern
                                        </button>
                                    </div>

                                    <input type="hidden" 
                                           :name="'teacher_edits[' + code + '][firstname]'" 
                                           :value="firstname">
                                    <input type="hidden" 
                                           :name="'teacher_edits[' + code + '][lastname]'" 
                                           :value="lastname">
                                    <input type="hidden" 
                                           :name="'teacher_edits[' + code + '][position]'" 
                                           :value="position">
                                    <input type="hidden" 
                                           :name="'teacher_edits[' + code + '][subjects]'" 
                                           :value="subjects">
                                </div>
                            </template>

                            <template x-if="deleted">
                                <div class="text-center py-4">
                                    <span class="text-gray-500">Lehrer <strong x-text="code"></strong> wird vom Import ausgeschlossen.</span>
                                    <button @click="deleted = false; updateSession()"
                                            type="button"
                                            class="ml-3 text-blue-600 hover:text-blue-800 text-sm font-semibold">
                                        ↺ Rückgängig
                                    </button>
                                    <input type="hidden" :name="'teacher_deletions[]'" :value="code">
                                </div>
                            </template>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Aktive Lehrer im Stundenplan – kompakte Darstellung --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @php
                        $dbTeachers     = collect($preview['active_teachers'])->where('exists_in_db', true)->sortBy('code');
                        $updatedTeachers= $dbTeachers->where('has_asv_data', true);   // vorhanden + ASV → wird aktualisiert
                        $unchangedTeachers = $dbTeachers->where('has_asv_data', false); // vorhanden, kein ASV → keine Änderung
                        $newTeachersAsv = collect($preview['active_teachers'])->where('exists_in_db', false)->where('has_asv_data', true)->sortBy('code');
                    @endphp

                    <h3 class="text-lg font-semibold mb-1">
                        👥 Aktive Lehrer im Stundenplan ({{ count($preview['active_teachers']) }})
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Stunden = eindeutige Zeitslots (gekoppelte Kurse zählen einmal).
                        Dummy-Einträge ohne ASV-Zuordnung wurden automatisch ignoriert.
                    </p>

                    {{-- Wird aktualisiert (in DB + ASV-Daten vorhanden) --}}
                    @if($updatedTeachers->count())
                    <div class="mb-4">
                        <div class="text-xs font-semibold text-amber-600 uppercase mb-2">
                            ✏️ Wird aktualisiert ({{ $updatedTeachers->count() }})
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($updatedTeachers as $t)
                            @php
                                $tooltip = $t['name'] ?? $t['code'];
                                if ($t['position']) $tooltip .= ' · ' . $t['position'];
                                if ($t['employment_type'] || $t['hours']) {
                                    $tooltip .= ' · ' . ($t['employment_type'] ?? '') . ($t['hours'] ? ' ' . $t['hours'] . 'h/Wo' : '');
                                }
                                if ($t['subjects']) $tooltip .= ' | ' . $t['subjects'];
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-50 border border-amber-300 text-sm text-amber-900"
                                  title="{{ trim($tooltip) }}">
                                <strong>{{ $t['code'] }}</strong>
                                <span class="text-amber-600 text-xs">{{ $t['entry_count'] }}h</span>
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Bekannte Lehrer ohne ASV-Daten (kein Update) --}}
                    @if($unchangedTeachers->count())
                    <div class="mb-4">
                        <div class="text-xs font-semibold text-gray-500 uppercase mb-2">
                            ✓ In Datenbank – kein Update ({{ $unchangedTeachers->count() }})
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($unchangedTeachers as $t)
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-50 border border-green-200 text-sm text-green-800"
                                  title="{{ $t['name'] ?? $t['code'] }}{{ $t['position'] ? ' · '.$t['position'] : '' }}">
                                <strong>{{ $t['code'] }}</strong>
                                <span class="text-green-600 text-xs">{{ $t['entry_count'] }}h</span>
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Neue Lehrer mit ASV-Daten --}}
                    @if($newTeachersAsv->count())
                    <div>
                        <div class="text-xs font-semibold text-blue-600 uppercase mb-2">
                            ＋ Neu anlegen (aus ASV) ({{ $newTeachersAsv->count() }})
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($newTeachersAsv as $t)
                            @php
                                $tooltip = $t['name'] ?? $t['code'];
                                if ($t['position']) $tooltip .= ' · ' . $t['position'];
                                if ($t['subjects']) $tooltip .= ' | ' . $t['subjects'];
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-50 border border-blue-200 text-sm text-blue-800"
                                  title="{{ trim($tooltip) }}">
                                <strong>{{ $t['code'] }}</strong>
                                <span class="text-blue-600 text-xs">{{ $t['entry_count'] }}h</span>
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Aktionen --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">🚀 Import durchführen</h3>
                    
                    @php
                        $errorGroups = collect($preview['entries'])
                            ->where('status', 'error')
                            ->flatMap(fn($e) => $e['issues'])
                            ->countBy()
                            ->sortDesc();

                        $warnGroups = collect($preview['entries'])
                            ->where('status', 'warning')
                            ->flatMap(fn($e) => $e['issues'])
                            ->countBy()
                            ->sortDesc();
                    @endphp

                    @if($preview['stats']['errors'] > 0)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4" x-data="{ open: false }">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-red-800">
                                    ⛔ {{ $preview['stats']['errors'] }} Einträge mit Fehlern werden übersprungen.
                                </p>
                                @if($errorGroups->isNotEmpty())
                                <button @click="open = !open" type="button" class="text-xs text-red-600 underline ml-4 whitespace-nowrap">
                                    <span x-show="!open">Details anzeigen ▼</span>
                                    <span x-show="open" style="display:none">Details ausblenden ▲</span>
                                </button>
                                @endif
                            </div>
                            <div x-show="open" style="display:none" class="mt-3 border-t border-red-200 pt-3 space-y-1">
                                @foreach($errorGroups as $msg => $count)
                                <div class="text-xs text-red-700 flex justify-between gap-4">
                                    <span>{{ $msg }}</span>
                                    <span class="font-semibold shrink-0">{{ $count }}×</span>
                                </div>
                                @endforeach
                                @if($errorGroups->isEmpty())
                                <p class="text-xs text-red-500 italic">Keine Detail-Informationen verfügbar.</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($preview['stats']['warnings'] > 0)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4" x-data="{ open: false }">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-yellow-800">
                                    ⚠️ {{ $preview['stats']['warnings'] }} Einträge mit Warnungen – werden trotzdem importiert.
                                </p>
                                @if($warnGroups->isNotEmpty())
                                <button @click="open = !open" type="button" class="text-xs text-yellow-600 underline ml-4 whitespace-nowrap">
                                    <span x-show="!open">Details anzeigen ▼</span>
                                    <span x-show="open" style="display:none">Details ausblenden ▲</span>
                                </button>
                                @endif
                            </div>
                            <div x-show="open" style="display:none" class="mt-3 border-t border-yellow-200 pt-3 space-y-1">
                                @foreach($warnGroups as $msg => $count)
                                <div class="text-xs text-yellow-700 flex justify-between gap-4">
                                    <span>{{ $msg }}</span>
                                    <span class="font-semibold shrink-0">{{ $count }}×</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('timetable.confirm-import') }}" class="space-y-4" x-ref="importForm">
                        @csrf

                        <div class="flex items-center">
                            <input type="checkbox" 
                                   name="truncate" 
                                   id="truncate" 
                                   value="1"
                                   checked
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <label for="truncate" class="ml-2 block text-sm text-gray-700">
                                <strong>Vorhandene Daten löschen</strong> vor dem Import (Schuljahr: {{ $schoolYear }})
                                <span class="block text-xs text-gray-500">⚠️ Alle bestehenden Stundenplan-Einträge für dieses Schuljahr werden entfernt</span>
                            </label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" 
                                   name="clear_teachers" 
                                   id="clear_teachers" 
                                   value="1"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <label for="clear_teachers" class="ml-2 block text-sm text-gray-700">
                                <strong>Lehrer-Tabelle bereinigen</strong> - nur Lehrer aus Stundenplandaten behalten
                                <span class="block text-xs text-gray-500">⚠️ Entfernt alle Lehrer, die NICHT im Stundenplan vorkommen (z.B. nur in ASV-Lehrbefähigungen)</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t">
                            <form method="POST" action="{{ route('timetable.cancel-import') }}">
                                @csrf
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400">
                                    Abbrechen
                                </button>
                            </form>

                            <button type="submit" 
                                    class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Import bestätigen ({{ $preview['stats']['valid'] + $preview['stats']['warnings'] }} Einträge)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script @cspNonce>
        function teacherEditor() {
            return {
                updateSession() {
                    // Collect all teacher edits and deletions
                    const edits = {};
                    const deletions = [];

                    // Collect from all Alpine components
                    document.querySelectorAll('[x-data]').forEach(el => {
                        const data = Alpine.$data(el);
                        if (data.code) {
                            if (data.deleted) {
                                deletions.push(data.code);
                            } else if (data.firstname || data.lastname || data.position || data.subjects) {
                                edits[data.code] = {
                                    firstname: data.firstname,
                                    lastname: data.lastname,
                                    position: data.position,
                                    subjects: data.subjects,
                                };
                            }
                        }
                    });

                    // Update session via AJAX
                    fetch('{{ route('timetable.update-preview-session') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            teacher_edits: edits,
                            teacher_deletions: deletions
                        })
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) {
                              console.log('Session updated successfully');
                          }
                      })
                      .catch(error => console.error('Error updating session:', error));
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
