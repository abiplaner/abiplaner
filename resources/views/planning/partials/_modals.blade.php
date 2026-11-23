    {{-- Modal: Außerhalb der Prüfungswochen --}}
    <div id="outside-period-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-24 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">🔄 Nachtermin außerhalb der Prüfungswochen</h3>
            <p class="text-sm text-gray-500 mb-4">Prüferkonflikte werden bei Außerterminen nicht automatisch geprüft.</p>
            <input type="hidden" id="outside-assignment-id">
            <input type="hidden" id="outside-exam-period-id">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum *</label>
                    <input type="date" id="outside-free-date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Beginn *</label>
                        <input type="time" id="outside-free-time-start" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ende *</label>
                        <input type="time" id="outside-free-time-end" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Raum (optional)</label>
                    <select id="outside-exam-room-id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">– kein Raum –</option>
                        @foreach($examRooms as $room)
                            <option value="{{ $room->id }}">{{ $room->room_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notiz (optional)</label>
                    <input type="text" id="outside-notes" maxlength="500" placeholder="z.B. Nachtermin nach Krankheit" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button data-aktion="submit-outside-period" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-sm font-medium">
                    Nachtermin anlegen
                </button>
                <button data-modal-close="outside-period-modal" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">
                    Abbrechen
                </button>
            </div>
        </div>
    </div>
    {{-- Erkrankungs-Banner (erscheint wenn Illness-Mode aktiv) --}}
    <div id="illness-mode-banner" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-50
         bg-red-600 text-white px-6 py-3 rounded-xl shadow-xl flex items-center gap-4">
        <span class="font-semibold">🤒 Erkrankungs-Modus aktiv – Neuen Termin in Woche 1 oder 2 per Drag & Drop wählen</span>
        <button data-aktion="open-outside-from-banner" class="bg-white text-red-600 px-3 py-1 rounded-md text-sm font-medium hover:bg-red-50">
            Außerhalb der Prüfungswochen
        </button>
        <button data-aktion="deactivate-illness-mode" class="text-red-200 hover:text-white text-sm underline">
            Abbrechen
        </button>
    </div>

    {{-- Auto-Planung Modal --}}
    <div id="auto-schedule-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" data-aktion="close-auto-schedule"></div>
            
            {{-- Modal --}}
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                
                {{-- Einstellungen --}}
                <div id="modal-settings">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    🤖 Automatische Prüfungsplanung
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="clear-existing" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                               {{ $autoScheduleSettings['clear_existing'] ? 'checked' : '' }}>
                                        <label for="clear-existing" class="ml-3 block text-sm text-gray-700">
                                            Bestehende Zuweisungen vorher löschen
                                            <span class="text-gray-500">(fixierte 🔒 bleiben erhalten)</span>
                                        </label>
                                    </div>
                                    
                                    <div>
                                        <label for="max-consecutive" class="block text-sm text-gray-700 mb-1">
                                            Max. aufeinanderfolgende Prüfungen pro Prüfer
                                        </label>
                                        <input type="number" id="max-consecutive" value="{{ $autoScheduleSettings['max_consecutive'] }}" min="1" max="10"
                                               class="w-24 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" id="bundle-teacher-days" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                               {{ $autoScheduleSettings['bundle_teacher_days'] ? 'checked' : '' }}>
                                        <label for="bundle-teacher-days" class="ml-3 block text-sm text-gray-700">
                                            Prüfungstage pro Lehrkraft bündeln
                                            <span class="text-gray-500">(Prüfungen einer Lehrkraft an möglichst wenigen Tagen, möglichst am Stück)</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" id="respect-free-days" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                               {{ $autoScheduleSettings['respect_free_days'] ? 'checked' : '' }}>
                                        <label for="respect-free-days" class="ml-3 block text-sm text-gray-700">
                                            Stundenplanfreie Tage möglichst meiden
                                            <span class="text-gray-500">(weiche Präferenz, benötigt importierte Stundenpläne; kein Anspruch der Lehrkräfte)</span>
                                        </label>
                                    </div>
                                    
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded">
                                        <p class="text-sm text-blue-700">
                                            ℹ️ <strong>Slot-Präferenzen:</strong> Die Priorisierung von Zeitslots (frühe Termine bevorzugen, Freitag vermeiden) wird über die Zeitslot-Gewichtung gesteuert und kann im Admin-Bereich angepasst werden.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            🎓 Leistungsfach-Kolloquien bevorzugt einplanen in
                                        </label>
                                        <div class="space-y-2 pl-1">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="lf-preferred-week" value=""
                                                       {{ $autoScheduleSettings['lf_preferred_week'] === '' ? 'checked' : '' }}
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="text-sm text-gray-700">Keine Präferenz</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="lf-preferred-week" value="1"
                                                       {{ $autoScheduleSettings['lf_preferred_week'] === '1' ? 'checked' : '' }}
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="text-sm text-gray-700">Woche 1 (erste Prüfungswoche)</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="lf-preferred-week" value="2"
                                                       {{ $autoScheduleSettings['lf_preferred_week'] === '2' ? 'checked' : '' }}
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="text-sm text-gray-700">Woche 2 (zweite Prüfungswoche)</span>
                                            </label>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1 pl-1">
                                            Erkennungsmerkmal LF: Großbuchstabe direkt nach der „2" im Kurscode (z.&nbsp;B. „2G-L", „2Smw-")
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" data-aktion="start-auto-schedule"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            🚀 Planung starten
                        </button>
                        <button type="button" data-aktion="close-auto-schedule"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Abbrechen
                        </button>
                    </div>
                </div>
                
                {{-- Fortschritt --}}
                <div id="modal-progress" class="hidden">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div class="text-center py-8">
                            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600 mx-auto mb-4"></div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Planung läuft...</h3>
                            <p class="text-sm text-gray-500">
                                Bitte warten, dies kann einige Sekunden dauern.
                            </p>
                        </div>
                    </div>
                </div>
                
                {{-- Ergebnis --}}
                <div id="modal-result" class="hidden">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div id="result-content"></div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" data-aktion="close-and-reload"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Schließen & Aktualisieren
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    {{-- Validierungs-Modal --}}
    <div id="validation-modal" class="fixed z-50 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                                Validierungsergebnis
                            </h3>
                            <div id="validation-result"></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" data-aktion="close-validation"
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Schließen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script @cspNonce>
    // Validierungs-Funktion
    function validatePlanning() {
        fetch('{{ route('planning.validate') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            showValidationResult(data);
        })
        .catch(error => {
            console.error('Validierung fehlgeschlagen:', error);
            alert('Fehler bei der Validierung');
        });
    }

    function showValidationResult(data) {
        const resultDiv = document.getElementById('validation-result');
        
        let html = '<div class="space-y-4">';
        
        // Zusammenfassung
        html += '<div class="p-4 rounded-lg ' + (data.valid ? 'bg-green-50' : 'bg-red-50') + '">';
        html += '<p class="text-lg font-semibold">' + data.summary + '</p>';
        html += '</div>';
        
        // Fehler
        if (data.errors && data.errors.length > 0) {
            html += '<div class="border-l-4 border-red-600 bg-red-50 p-4">';
            html += '<h4 class="text-red-800 font-semibold mb-2">🔴 Fehler (' + data.errors.length + ')</h4>';
            html += '<ul class="list-disc pl-5 space-y-1 text-red-700">';
            data.errors.forEach(error => {
                html += '<li>' + error.message + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        // Warnungen
        if (data.warnings && data.warnings.length > 0) {
            html += '<div class="border-l-4 border-yellow-600 bg-yellow-50 p-4">';
            html += '<h4 class="text-yellow-800 font-semibold mb-2">⚠️ Warnungen (' + data.warnings.length + ')</h4>';
            html += '<ul class="list-disc pl-5 space-y-1 text-yellow-700">';
            data.warnings.forEach(warning => {
                html += '<li>' + warning.message + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        html += '</div>';
        
        resultDiv.innerHTML = html;
        document.getElementById('validation-modal').classList.remove('hidden');
    }

    function closeValidationModal() {
        document.getElementById('validation-modal').classList.add('hidden');
    }

    // Aktive Woche und Scroll-Position nach Reload wiederherstellen
    document.addEventListener('DOMContentLoaded', function() {
        const savedWeek        = sessionStorage.getItem('planningActiveWeek');
        const savedRightScroll = parseInt(sessionStorage.getItem('planningRightScroll') ?? '0', 10);
        const savedScrollY     = parseInt(sessionStorage.getItem('planningScrollY')     ?? '0', 10);

        sessionStorage.removeItem('planningActiveWeek');
        sessionStorage.removeItem('planningRightScroll');
        sessionStorage.removeItem('planningScrollY');

        if (savedWeek === '2') showWeek(2);

        if (savedRightScroll > 0 || savedScrollY > 0) {
            setTimeout(function() {
                if (savedRightScroll > 0) {
                    const el = document.getElementById('planning-right-scroll');
                    if (el) el.scrollTop = savedRightScroll;
                }
                if (savedScrollY > 0) window.scrollTo(0, savedScrollY);
            }, 50);
        }
    });
    </script>

    {{-- Modal: Zuweisungen zurücksetzen --}}
    <div id="reset-assignments-modal"
         class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <div class="flex items-start gap-3 mb-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Alle Zuweisungen zurücksetzen?</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Alle Prüfungszuweisungen werden gelöscht. Die Prüfungen selbst (Schüler, Fächer, Lehrer) bleiben erhalten.<br>
                        <strong class="text-red-700">Diese Aktion kann nicht rückgängig gemacht werden.</strong>
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button"
                        data-modal-close="reset-assignments-modal"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Abbrechen
                </button>
                @unlessarchive
                <form method="POST"
                      action="{{ route('planning.reset-assignments', $examPeriod) }}"
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">
                        Ja, alle Zuweisungen löschen
                    </button>
                </form>
                @endarchive
            </div>
        </div>
    </div>

