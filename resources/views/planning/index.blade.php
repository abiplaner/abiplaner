<x-app-layout>
    @push('head-scripts')
    <style>
        /* Drop-Zone-Highlighting während Drag & Drop */
        .time-slot-cell { position: relative; }
        .drop-zone-valid {
            border-color: #22c55e !important;
            background-color: #f0fdf4 !important;
        }
        .drop-zone-valid::after {
            content: '✓';
            position: absolute;
            top: 4px;
            right: 6px;
            color: #16a34a;
            font-size: 11px;
            font-weight: bold;
            pointer-events: none;
        }
        .drop-zone-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
            opacity: 0.65;
        }
        .drop-zone-invalid::after {
            content: '✗';
            position: absolute;
            top: 4px;
            right: 6px;
            color: #dc2626;
            font-size: 11px;
            font-weight: bold;
            pointer-events: none;
        }

        /* Prüfer-Hover-Highlighting (Modul 4.3): beim Überfahren einer Prüfung
           werden alle weiteren Prüfungen desselben Erstprüfers hervorgehoben.
           Teal-Outline statt Ring/Background, damit es nicht mit den Drop-Zonen
           (grün/rot), Fixierung (blau), Extern (lila), fehlendem Beisitzer
           (orange) oder Kopplungsfarben (Pastell-Hintergründe) kollidiert. */
        .examiner-hover-source {
            outline: 3px dashed #0d9488;
            outline-offset: 1px;
        }
        .examiner-hover-match {
            outline: 3px solid #0d9488;
            outline-offset: 1px;
        }
    </style>
    {{-- Kritische JavaScript-Funktionen müssen VOR den anderen Funktionen definiert werden --}}
    <script @cspNonce>
        // Frueher onclick an den beiden Knoepfen (Modul 7.2). Ueber document
        // gebunden, damit die Reihenfolge zu den Definitionen egal ist —
        // validatePlanning() steht in partials/_modals.blade.php.
        document.addEventListener('click', (ereignis) => {
            if (ereignis.target.closest('[data-validate-planning]')) {
                validatePlanning();
            }

            if (ereignis.target.closest('[data-open-auto-schedule]')) {
                window.openAutoScheduleModal();
            }
        });

        // Definitionen der Modal-Funktionen für Auto-Planung und Validierung
        window.openAutoScheduleModal = function() {
            document.getElementById('auto-schedule-modal').classList.remove('hidden');
            document.getElementById('modal-settings').classList.remove('hidden');
            document.getElementById('modal-progress').classList.add('hidden');
            document.getElementById('modal-result').classList.add('hidden');
        }
        
        window.closeAutoScheduleModal = function() {
            document.getElementById('auto-schedule-modal').classList.add('hidden');
        }
        
        window.validatePlanning = function() {
            // Diese Funktion wird weiter unten vollständig definiert
            console.log('validatePlanning called - placeholder');
        }
    </script>

    {{-- Alpine-Komponente für den Export mit Tagesauswahl (Modul 4.2) --}}
    <script @cspNonce>
        document.addEventListener('alpine:init', () => {
            Alpine.data('colloquiumExport', (days) => ({
                open: false,
                showDayModal: false,
                exportTarget: null,
                exportNewTab: true,
                allDays: days,
                // Vorauswahl: nur noch nicht abgeschlossene Tage (preselected === true).
                selectedDays: days.filter(d => d.preselected).map(d => d.dateStr),

                toggleDay(dateStr) {
                    const i = this.selectedDays.indexOf(dateStr);
                    if (i === -1) this.selectedDays.push(dateStr);
                    else this.selectedDays.splice(i, 1);
                },
                selectAllDays()  { this.selectedDays = this.allDays.map(d => d.dateStr); },
                selectNoneDays() { this.selectedDays = []; },
                resetDays()      { this.selectedDays = this.allDays.filter(d => d.preselected).map(d => d.dateStr); },

                // Öffnet das Tagesauswahl-Modal für einen bestimmten Export.
                openDayModal(target, newTab) {
                    this.exportTarget = target;
                    this.exportNewTab = newTab;
                    this.open = false;
                    // Gibt es keine Tage, direkt exportieren (Controller liefert dann leer/alles).
                    if (this.allDays.length === 0) {
                        this.dispatchExport(target);
                        return;
                    }
                    this.showDayModal = true;
                },

                // Baut die finale URL mit day_ids[] und stößt den Export an.
                runExport() {
                    if (this.selectedDays.length === 0) return;
                    this.dispatchExport(this.buildUrl(this.exportTarget));
                    this.showDayModal = false;
                },

                buildUrl(base) {
                    // Alle Tage gewählt → keine Query nötig (Controller exportiert alle).
                    if (this.selectedDays.length === this.allDays.length) return base;
                    const sep = base.includes('?') ? '&' : '?';
                    const q = this.selectedDays.map(d => 'day_ids[]=' + encodeURIComponent(d)).join('&');
                    return base + sep + q;
                },

                dispatchExport(url) {
                    if (this.exportNewTab) window.open(url, '_blank');
                    else window.location.href = url;
                },
            }));
        });
    </script>
    @endpush

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Prüfungsplanung: {{ $examPeriod->name }}
            </h2>
            <div class="flex items-center gap-3">

                {{-- Export-Dropdown ----------------------------------------- --}}
                <div class="relative"
                     x-data="colloquiumExport(@js($exportDays ?? []))"
                     @click.outside="open = false">
                    <button @click="open = !open"
                            class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md text-sm font-medium shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportieren
                        <svg class="w-3.5 h-3.5 ml-0.5 transition-transform" :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-transition
                         class="absolute right-0 mt-1 w-60 bg-white rounded-lg shadow-lg border border-gray-200 z-50 py-1">

                        {{-- Kolloquiumsplan --}}
                        <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50 border-b">
                            Kolloquiumsplan
                        </div>
                        <a href="{{ route('planning.export-colloquium-pdf', $examPeriod) }}" target="_blank"
                           @click.prevent="openDayModal('{{ route('planning.export-colloquium-pdf', $examPeriod) }}', true)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Kolloquiumsplan (PDF)
                        </a>
                        <a href="{{ route('planning.export-compact-matrix-pdf', $examPeriod) }}" target="_blank"
                           @click.prevent="openDayModal('{{ route('planning.export-compact-matrix-pdf', $examPeriod) }}', true)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Planungsmatrix kompakt (PDF)
                        </a>
                        <a href="{{ route('planning.export-colloquium-excel', $examPeriod) }}"
                           @click.prevent="openDayModal('{{ route('planning.export-colloquium-excel', $examPeriod) }}', false)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Kolloquiumsplan (Excel)
                        </a>
                        <a href="{{ route('planning.niederschrift-auswahl', $examPeriod) }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Niederschriften (PDF)
                        </a>
                        <a href="{{ route('planning.export-aufgabenabgabe-pdf', $examPeriod) }}" target="_blank"
                           @click.prevent="openDayModal('{{ route('planning.export-aufgabenabgabe-pdf', $examPeriod) }}', true)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Aufgabenabgabe (PDF)
                        </a>

                        {{-- Weitere Exporte --}}
                        <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50 border-t border-b mt-1">
                            Weitere Exporte
                        </div>
                        <a href="{{ route('planning.export-students-pdf', $examPeriod) }}" target="_blank"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Schüler-Liste (PDF)
                        </a>
                        <a href="{{ route('planning.export-teachers-pdf', $examPeriod) }}" target="_blank"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Lehrer-Liste (PDF)
                        </a>
                        <a href="{{ route('planning.export-room-day-schedule-pdf', $examPeriod) }}" target="_blank"
                           @click.prevent="openDayModal('{{ route('planning.export-room-day-schedule-pdf', $examPeriod) }}', true)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Raum-Tagespläne (PDF)
                        </a>
                        <a href="{{ route('planning.export-prep-rooms-pdf', $examPeriod) }}" target="_blank"
                           @click.prevent="openDayModal('{{ route('planning.export-prep-rooms-pdf', $examPeriod) }}', true)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700">
                            <svg class="w-4 h-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Vorbereitungsräume (PDF)
                        </a>
                        <a href="{{ route('planning.export-vorbereitungsprotokoll-pdf', $examPeriod) }}" target="_blank"
                           @click.prevent="openDayModal('{{ route('planning.export-vorbereitungsprotokoll-pdf', $examPeriod) }}', true)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700">
                            <svg class="w-4 h-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            Vorbereitungsprotokoll (PDF)
                        </a>
                        <a href="{{ route('planning.export-daily-subjects-pdf', $examPeriod) }}" target="_blank"
                           @click.prevent="openDayModal('{{ route('planning.export-daily-subjects-pdf', $examPeriod) }}', true)"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                            </svg>
                            Tagesübersicht Fächer (PDF)
                        </a>
                        <a href="{{ route('rooms.occupancy') }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                            </svg>
                            Raumbelegungsplan
                        </a>
                    </div>

                    {{-- Tagesauswahl-Modal (Modul 4.2) – gemeinsam für alle tagesbasierten Exporte --}}
                    <div x-show="showDayModal" x-cloak
                         class="fixed inset-0 flex items-center justify-center z-50"
                         style="background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);"
                         @click.self="showDayModal = false"
                         @keydown.escape.window="showDayModal = false">

                        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 text-left">
                            {{-- Header --}}
                            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                                <h2 class="text-sm font-semibold text-gray-800">Tage für den Export auswählen</h2>
                                <button @click="showDayModal = false"
                                    class="text-gray-400 hover:text-gray-600 text-xl leading-none font-light">&times;</button>
                            </div>

                            {{-- Body --}}
                            <div class="px-5 py-4 space-y-3">
                                <p class="text-xs text-gray-500">Wähle die Tage aus, die im Export erscheinen sollen:</p>

                                <template x-if="allDays.length === 0">
                                    <p class="text-xs text-gray-400 text-center py-2">Keine Prüfungstage mit Kolloquien vorhanden.</p>
                                </template>

                                <div class="space-y-1.5" x-show="allDays.length > 0">
                                    <template x-for="day in allDays" :key="day.dateStr">
                                        <label class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors select-none">
                                            <input type="checkbox" :value="day.dateStr"
                                                :checked="selectedDays.includes(day.dateStr)"
                                                @change="toggleDay(day.dateStr)"
                                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            <span class="flex-1 min-w-0">
                                                <span class="text-sm font-medium text-gray-800" x-text="day.label"></span>
                                                <span class="block text-xs mt-0.5">
                                                    <template x-if="day.isDone">
                                                        <span class="text-gray-400" x-text="'Abgeschlossen (bis ' + day.lastEnd + ' Uhr)'"></span>
                                                    </template>
                                                    <template x-if="!day.isDone">
                                                        <span class="text-emerald-600" x-text="'Letzte Prüfung bis ' + day.lastEnd + ' Uhr'"></span>
                                                    </template>
                                                </span>
                                            </span>
                                        </label>
                                    </template>

                                    <div class="flex gap-3 text-xs pt-1">
                                        <button type="button" @click="selectAllDays()" class="text-emerald-600 hover:text-emerald-700 hover:underline">Alle</button>
                                        <button type="button" @click="selectNoneDays()" class="text-gray-400 hover:text-gray-600 hover:underline">Keine</button>
                                        <button type="button" @click="resetDays()" class="text-gray-400 hover:text-gray-600 hover:underline">Zurücksetzen</button>
                                    </div>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                                <button @click="showDayModal = false"
                                    class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded border border-gray-200">
                                    Abbrechen
                                </button>
                                <button @click="runExport()"
                                    :disabled="selectedDays.length === 0"
                                    class="px-4 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                                    <span x-text="'Export (' + selectedDays.length + ' ' + (selectedDays.length === 1 ? 'Tag' : 'Tage') + ')'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Trennlinie --}}
                <div class="h-8 w-px bg-gray-300"></div>

                {{-- Aktions-Buttons ------------------------------------------ --}}
                <a href="{{ route('timetable.teacher-availability') }}"
                   class="inline-flex items-center gap-1.5 bg-purple-100 hover:bg-purple-200 text-purple-800 px-4 py-2 rounded-md text-sm font-medium shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Lehrerverfügbarkeit
                </a>

                @unlessarchive
                <button data-validate-planning
                        class="inline-flex items-center gap-1.5 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Planung prüfen
                </button>
                <button data-modal-open="reset-assignments-modal"
                        class="inline-flex items-center gap-1.5 bg-red-100 hover:bg-red-200 text-red-700 px-4 py-2 rounded-md text-sm font-medium shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Zuweisungen zurücksetzen
                </button>

                <button data-open-auto-schedule
                        class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                    </svg>
                    Auto-Planung starten
                </button>
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Tab-Navigation --}}
            <div class="mb-6 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="{{ route('planning.index') }}" 
                       class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        📋 Prüfungen
                    </a>
                    <a href="{{ route('planning.rooms.index') }}" 
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        🏫 Räume &amp; Tage
                    </a>
                    <a href="{{ route('planning.time-slots.index') }}" 
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        🕐 Zeitslots
                    </a>
                </nav>
            </div>
            
            {{-- Statistiken --}}
            <div id="planning-stats" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <!-- Schüleranzahl -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        {{-- „Prüflinge", nicht „Schüler": externe Bewerber sind
                             hier mitgezählt, und ihre Prüfungen stecken im Soll. --}}
                        <div class="text-sm text-gray-500">Prüflinge</div>
                        <div class="text-2xl font-bold">{{ $stats['students_count'] }}</div>
                        <div class="text-xs text-gray-400 mt-1">Soll: {{ $stats['expected_exams'] }} Prüfungen</div>
                    </div>
                </div>
                
                <!-- Prüfungsanzahl Validierung -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg {{ $stats['exam_count_valid'] ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500' }}">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">Prüfungen je Prüfling</div>
                        <div class="text-2xl font-bold {{ $stats['exam_count_valid'] ? 'text-green-600' : 'text-red-600' }}">
                            @if($stats['exam_count_valid'])
                                ✓ Korrekt
                            @else
                                ✗ {{ $stats['students_with_wrong_count'] }}
                            @endif
                        </div>
                        @if(!$stats['exam_count_valid'])
                            {{-- Nicht mehr „≠ 2": ein externer Bewerber braucht vier
                                 mündliche Prüfungen, ein Ersatzschüler nach § 64 Abs. 2
                                 zwei. Die Soll-Zahl steht an der Zeile. --}}
                            <div class="text-xs text-red-500 mt-1">mit abweichender Anzahl</div>
                        @else
                            <div class="text-xs text-green-600 mt-1">Alle vollständig</div>
                        @endif
                    </div>
                </div>
                
                <!-- Planungsfortschritt kombiniert -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">Planungsfortschritt</div>
                        <div class="text-2xl font-bold">
                            <span class="text-green-600">{{ $stats['planned'] }}</span>
                            <span class="text-gray-400 text-lg">/</span>
                            <span class="text-gray-600 text-lg">{{ $stats['total'] }}</span>
                        </div>
                        <div class="text-xs text-orange-600 mt-1">
                            {{ $stats['unplanned'] }} ungeplant
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">Ohne Beisitzer</div>
                        <div class="text-2xl font-bold {{ $stats['missing_assessor'] > 0 ? 'text-orange-500' : 'text-green-600' }}">
                            {{ $stats['missing_assessor'] }}
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">Konflikte</div>
                        <div class="text-2xl font-bold {{ count($conflicts['errors']) > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ count($conflicts['errors']) }}
                        </div>
                    </div>
                </div>
            </div>

            <div id="planning-content">
            {{-- Warnung: Schüler mit falscher Prüfungsanzahl --}}
            @if(!$stats['exam_count_valid'])
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-red-800">
                                ⚠️ {{ $stats['students_with_wrong_count'] }} Prüflinge haben nicht die vorgesehene Zahl mündlicher Prüfungen!
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p class="mb-2">Ein Schüler braucht 2 Kolloquien, ein externer Bewerber die Fächer seines zweiten Prüfungsteils. Folgende Prüflinge weichen davon ab:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach($studentsWithWrongCount as $student)
                                        <li>
                                            <strong>{{ $student->lastname }}, {{ $student->firstname }}</strong>
                                            @if($student->extern)
                                                <span class="text-xs bg-red-100 text-red-800 px-1.5 py-0.5 rounded">extern</span>
                                            @endif
                                            <span class="text-red-600 font-semibold">
                                                hat {{ $student->exam_count }} statt {{ $student->soll }} Prüfungen
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="mt-3 text-xs">
                                    Prüfungszuordnungen für Schüler unter „Kurse &amp; Schüler", für externe Bewerber unter „Externe Bewerber" &rarr; Fächerwahl.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Konflikte anzeigen --}}
            @if(count($conflicts['errors']) > 0)
                <div class="bg-red-50 border-l-4 border-red-600 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Konflikte gefunden!</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach($conflicts['errors'] as $error)
                                        <li>{{ $error['message'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{--
                Warnungen — anders als die Konflikte darüber kein Fehler,
                sondern etwas, das der Koordinator entscheiden muss: der
                Abbruchgrund nach § 61 Abs. 4 S. 2 und der nach § 62 Abs. 1
                S. 4.

                Der Block hat gefehlt. `getConflicts()` füllt `warnings` seit
                dem 31.08.2026, aber gelesen hat sie niemand — die erste
                Meldung war unsichtbar, und der Test dazu prüfte nur den
                Dienst. **Ein Feld, das niemand liest, ist keine Meldung.**
            --}}
            @if(count($conflicts['warnings']) > 0)
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-900">Zu entscheiden</h3>
                            <div class="mt-2 text-sm text-amber-800">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach($conflicts['warnings'] as $warnung)
                                        <li>{{ $warnung['message'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Vorgezogene Prüfungen externer Bewerber (§ 61 Abs. 4) --}}
            @include('planning.partials._vorgezogene_pruefungen')

            {{-- Nachtermine außerhalb der Prüfungswochen (prominente Position vor dem Planungsraster) --}}
            @include('planning.partials._outside_assignments')

            {{-- Hauptbereich: 5-Spalten Layout mit fester Höhe, beide Spalten scrollen intern --}}
            <div id="planning-grid" class="grid grid-cols-1 md:grid-cols-5 gap-4" style="height: calc(100vh - 16rem);">
                
                {{-- Linke Spalte: Ungeplante Prüfungen – scrollt intern --}}
                <div class="md:col-span-1 h-full overflow-y-auto">
                    <div class="bg-white shadow-sm sm:rounded-lg flex flex-col h-full">
                        <div class="p-4 border-b flex-shrink-0">
                            <h3 class="font-semibold text-lg">Ungeplante Prüfungen</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $stats['unplanned'] }} Prüfung(en)</p>
                        </div>
                        <div class="p-4 space-y-4 overflow-y-auto flex-1">
                            @if($stats['unplanned'] > 0)
                                <div class="space-y-4">
                                    @foreach($unplannedExams as $courseGroup => $exams)
                                        {{-- Kurs-Überschrift --}}
                                        <div class="border-l-4 rounded bg-gray-50 px-3 py-2"
                                             style="border-color: {{ $exams->first()->fachfarbe() }}">
                                            <div class="font-semibold text-sm text-gray-700">
                                                {{ $courseGroup }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $exams->count() }} Prüfung(en)
                                            </div>
                                        </div>
                                        
                                        {{-- Schüler in diesem Kurs --}}
                                        <div class="space-y-1 ml-2">
                                            @foreach($exams as $exam)
                                                <div draggable="true" 
                                                     class="exam-card border-l-4 rounded bg-white px-3 py-2 cursor-move hover:shadow-md transition {{ $exam->isExternalApplicant() ? 'ring-2 ring-purple-300' : '' }} {{ $exam->preferred_week ? 'preferred-week-' . $exam->preferred_week : '' }}"
                                                     style="border-color: {{ $exam->fachfarbe() }}"
                                                     data-exam-id="{{ $exam->id }}"
                                                     data-examiner-id="{{ $exam->examiner_id }}"
                                                     data-student-id="{{ $exam->student_id ?? '' }}"
                                                     data-assessor-id="{{ $exam->assessor_id ?? '' }}"
                                                     data-preferred-week="{{ $exam->preferred_week ?? '' }}"
                                                     data-has-illness="{{ $exam->illnessAssignment ? '1' : '' }}">
                                                    <div class="text-sm flex items-center justify-between">
                                                        <span class="font-medium flex items-center gap-1">
                                                            @if($exam->isExternalApplicant())
                                                                <span class="text-purple-600" title="Externer Bewerber">📝</span>
                                                            @endif
                                                            {{ $exam->getStudentName() }}
                                                        </span>
                                                        <div class="flex items-center gap-1">
                                                            @if($exam->preferred_week)
                                                                <span class="text-xs font-bold px-1.5 py-0.5 rounded"
                                                                      style="background-color: {{ $exam->preferred_week === 1 ? '#dbeafe' : '#dcfce7' }}; color: {{ $exam->preferred_week === 1 ? '#1d4ed8' : '#15803d' }}">
                                                                    W{{ $exam->preferred_week }}
                                                                </span>
                                                            @endif
                                                            @if($exam->assessor)
                                                                <span class="text-green-600 text-xs font-medium">
                                                                    ({{ $exam->assessor->shortcode }})
                                                                </span>
                                                            @else
                                                                <span class="text-gray-400 text-xs">
                                                                    (kein B.)
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500 text-center py-8">
                                    Alle Prüfungen sind geplant! 🎉
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Rechte Spalte: Matrix-Ansicht --}}
                @include('planning.partials._week_grid')
            </div>
            </div>{{-- /planning-content --}}
        </div>
    </div>

    @include('planning.partials._modals')
    @include('planning.partials._scripts')

</x-app-layout>
