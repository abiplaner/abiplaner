<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Kolloquium – Aufsichtsplanung') }} &mdash; {{ $examPeriod->name }}
            </h2>
            <div class="flex items-center gap-2">
                {{-- Aufsichten-Kontrolle --}}
                <div x-data>
                    <button @click="$store.kolloquiumCoverage.showModal = true"
                        :class="($store.kolloquiumCoverage.uncoveredCount > 0 || $store.kolloquiumCoverage.orphanedCount > 0)
                            ? 'bg-amber-50 hover:bg-amber-100 border-amber-300 text-amber-800'
                            : 'bg-gray-100 hover:bg-gray-200 border-gray-300'"
                        class="relative inline-flex items-center gap-1 px-3 py-2 text-sm rounded-lg border">
                        👁 Aufsichten
                        <span x-show="$store.kolloquiumCoverage.uncoveredCount > 0"
                              x-text="$store.kolloquiumCoverage.uncoveredCount"
                              class="ml-0.5 bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5 leading-none ring-1 ring-white"
                              title="Aufsichten fehlen"></span>
                        <span x-show="$store.kolloquiumCoverage.orphanedCount > 0"
                              x-text="$store.kolloquiumCoverage.orphanedCount"
                              class="ml-0.5 bg-orange-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5 leading-none ring-1 ring-white"
                              title="Überflüssige Aufsichten"></span>
                    </button>
                </div>
                {{-- Exporte (Dropdown) --}}
                <x-dropdown align="right" width="60">
                    <x-slot name="trigger">
                        <button type="button"
                                class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Exporte
                            <svg class="w-4 h-4 ml-1.5 -mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <a href="{{ route('supervisions.export.signature-sheets-pdf', $examPeriod) }}"
                           target="_blank"
                           class="flex items-center px-4 py-2 text-sm text-purple-700 dark:text-purple-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition duration-150 ease-in-out">
                            <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            PDF Unterschriftsbögen
                        </a>
                        <a href="{{ route('supervisions.export.overview-pdf', $examPeriod) }}"
                           target="_blank"
                           class="flex items-center px-4 py-2 text-sm text-red-700 dark:text-red-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition duration-150 ease-in-out">
                            <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            PDF Gesamtplan
                        </a>
                        <a href="{{ route('supervisions.export.overview-excel', $examPeriod) }}"
                           class="flex items-center px-4 py-2 text-sm text-green-700 dark:text-green-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition duration-150 ease-in-out">
                            <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Excel Gesamtplan
                        </a>
                        <a href="{{ route('supervisions.export.teacher-list-pdf', $examPeriod) }}"
                           target="_blank"
                           class="flex items-center px-4 py-2 text-sm text-orange-700 dark:text-orange-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition duration-150 ease-in-out">
                            <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            PDF Lehrkräfte
                        </a>
                    </x-slot>
                </x-dropdown>
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition-colors shadow-sm">
                    Zurück
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div x-data="kolloquiumAufsichten()" x-init="init()">

                {{-- Wochenauswahl --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-5">
                    <div class="flex items-center gap-6">
                        <div class="w-44">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Woche</label>
                            <select x-model="selectedWeek" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="1">Woche 1</option>
                                <option value="2">Woche 2</option>
                            </select>
                        </div>
                        <p class="text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded px-3 py-2">
                            💡 Spalten = echte Prüfungsslots aus <strong>Kolloquium → Zeitslots</strong>.
                            Gang-Räume werden über <strong>Kolloquium → Räume &amp; Tage</strong> mit der Rolle „G" eingetragen.
                        </p>
                    </div>
                </div>

                {{-- Pro Tag eine Karte --}}
                <div class="space-y-8">
                    <template x-for="date in visibleExamDates" :key="date">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h2 class="text-xl font-semibold mb-4 text-gray-800" x-text="formatDate(date)"></h2>

                            {{-- Tag ohne Prüfungsslots --}}
                            <template x-if="getSlotsForDate(date).length === 0">
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                                    <p class="text-gray-500 text-sm">
                                        Keine geplanten Kolloquien an diesem Tag
                                        (siehe <a href="{{ route('planning.time-slots.index') }}" class="text-blue-600 hover:underline">Zeitslots &amp; Prüfungsplan</a>).
                                    </p>
                                </div>
                            </template>

                            {{-- Tag mit Prüfungsslots --}}
                            <template x-if="getSlotsForDate(date).length > 0">
                                <div class="overflow-x-auto">
                                    <table class="w-full border-collapse border text-sm">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="border p-1.5 text-left w-36 text-xs text-gray-600 align-bottom">Raum / Ort</th>
                                                <template x-for="slot in getSlotsForDate(date)" :key="slot.id">
                                                    <th class="border p-1.5 text-center min-w-36 align-bottom">
                                                        <div class="font-semibold text-gray-800 text-xs"
                                                             x-text="'Slot ' + slot.slot_number + ' (' + slot.prep_start_time.substring(0,5) + '–' + slot.exam_end_time.substring(0,5) + ')'"></div>
                                                    </th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        {{-- VORBEREITUNGSRÄUME --}}
                                        <template x-if="getPrepRooms(date).length > 0">
                                            <tr class="bg-amber-50">
                                                <td class="border p-1.5 font-semibold text-xs text-amber-800">
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                                                        Vorbereitungsräume
                                                    </span>
                                                </td>
                                                <template x-for="slot in getSlotsForDate(date)" :key="slot.id">
                                                    <td class="border p-1.5 text-center text-xs text-amber-700"
                                                        x-text="minus10(slot.prep_start_time) + '–' + slot.prep_end_time.substring(0,5)"></td>
                                                </template>
                                            </tr>
                                        </template>
                                        <template x-for="dayRoom in getPrepRooms(date)" :key="'prep_'+dayRoom.id">
                                            <tr class="hover:bg-amber-50/30">
                                                <td class="border p-1.5 bg-white align-top">
                                                    <div class="font-medium text-gray-800 text-xs" x-text="dayRoom.room.room_number"></div>
                                                </td>
                                                <template x-for="slot in getSlotsForDate(date)" :key="slot.id">
                                                    <td class="border p-1 align-top">
                                                        <div x-data="teacherCell(slot.id, dayRoom.room_id, 'preparation', date)">
                                                            <template x-if="!supervision">
                                                                @unlessarchive
                                                                <select @change="assign($event.target.value)"
                                                                        :class="isMissing ? 'border-red-400 ring-1 ring-red-400 bg-red-50' : 'border-amber-200 bg-amber-50'"
                                                                        class="w-full text-xs rounded py-1 px-1.5">
                                                                    <option value="">— Kürzel —</option>
                                                                    <optgroup label="Unterricht Q13" x-show="availableTeachers.some(t => t.has_q13)">
                                                                        <template x-for="t in availableTeachers.filter(t => t.has_q13)" :key="t.id">
                                                                            <option :value="t.id" x-text="t.shortcode + ' (' + t.lastname + ')'"></option>
                                                                        </template>
                                                                    </optgroup>
                                                                    <optgroup label="Weitere">
                                                                        <template x-for="t in availableTeachers.filter(t => !t.has_q13)" :key="t.id">
                                                                            <option :value="t.id" x-text="t.shortcode + ' (' + t.lastname + ')'"></option>
                                                                        </template>
                                                                    </optgroup>
                                                                </select>
                                                                @else
                                                                <span class="block text-center text-xs text-gray-300">–</span>
                                                                @endarchive
                                                            </template>
                                                            <template x-if="supervision">
                                                                <div :class="isOrphaned ? 'border-orange-400 ring-1 ring-orange-400' : 'border-amber-300'"
                                                                     class="flex justify-between items-center bg-amber-100 border p-1.5 rounded"
                                                                     :title="isOrphaned ? 'Laut Plan aktuell keine Prüfung in diesem Slot' : ''">
                                                                    <strong class="text-amber-800 text-xs" x-text="supervision.teacher.shortcode"></strong>
                                                                    @unlessarchive
                                                                    <button @click="remove()" class="text-red-400 hover:text-red-600 font-bold ml-1">×</button>
                                                                    @endarchive
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>

                                        {{-- WARTERÄUME --}}
                                        <template x-if="getWaitRooms(date).length > 0">
                                            <tr class="bg-green-50">
                                                <td class="border p-1.5 font-semibold text-xs text-green-800">
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                                                        Warteräume
                                                    </span>
                                                </td>
                                                <template x-for="slot in getSlotsForDate(date)" :key="slot.id">
                                                    <td class="border p-1.5 text-center text-xs text-green-700"
                                                        x-text="minus10(slot.prep_start_time) + '–' + slot.exam_end_time.substring(0,5)"></td>
                                                </template>
                                            </tr>
                                        </template>
                                        <template x-for="dayRoom in getWaitRooms(date)" :key="'wait_'+dayRoom.id">
                                            <tr class="hover:bg-green-50/30">
                                                <td class="border p-1.5 bg-white align-top">
                                                    <div class="font-medium text-gray-800 text-xs" x-text="dayRoom.room.room_number"></div>
                                                </td>
                                                <template x-for="slot in getSlotsForDate(date)" :key="slot.id">
                                                    <td class="border p-1 align-top">
                                                        <div x-data="teacherCell(slot.id, dayRoom.room_id, 'waiting', date)">
                                                            <template x-if="!supervision && isMissing">
                                                                @unlessarchive
                                                                <select @change="assign($event.target.value)"
                                                                        class="w-full text-xs rounded py-1 px-1.5 border-red-400 ring-1 ring-red-400 bg-red-50">
                                                                    <option value="">— Kürzel —</option>
                                                                    <optgroup label="Unterricht Q13" x-show="availableTeachers.some(t => t.has_q13)">
                                                                        <template x-for="t in availableTeachers.filter(t => t.has_q13)" :key="t.id">
                                                                            <option :value="t.id" x-text="t.shortcode + ' (' + t.lastname + ')'"></option>
                                                                        </template>
                                                                    </optgroup>
                                                                    <optgroup label="Weitere">
                                                                        <template x-for="t in availableTeachers.filter(t => !t.has_q13)" :key="t.id">
                                                                            <option :value="t.id" x-text="t.shortcode + ' (' + t.lastname + ')'"></option>
                                                                        </template>
                                                                    </optgroup>
                                                                </select>
                                                                @else
                                                                <span class="block text-center text-xs text-red-300">–</span>
                                                                @endarchive
                                                            </template>
                                                            <template x-if="!supervision && !isMissing">
                                                                <span class="text-gray-300 text-xs italic">– kein Bedarf –</span>
                                                            </template>
                                                            <template x-if="supervision">
                                                                <div :class="isOrphaned ? 'border-orange-400 ring-1 ring-orange-400' : 'border-green-300'"
                                                                     class="flex justify-between items-center bg-green-100 border p-1.5 rounded"
                                                                     :title="isOrphaned ? 'Laut Plan aktuell keine Kopplung, die hier Warteraum-Aufsicht erfordert' : ''">
                                                                    <strong class="text-green-800 text-xs" x-text="supervision.teacher.shortcode"></strong>
                                                                    @unlessarchive
                                                                    <button @click="remove()" class="text-red-400 hover:text-red-600 font-bold ml-1">×</button>
                                                                    @endarchive
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>

                                        {{-- GANGAUFSICHTEN (Gang-Räume) --}}
                                        <template x-if="getCorridorRooms(date).length > 0">
                                            <tr class="bg-slate-50">
                                                <td class="border p-1.5 font-semibold text-xs text-slate-700">
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full bg-slate-500 inline-block"></span>
                                                        Gangaufsichten
                                                    </span>
                                                </td>
                                                <template x-for="slot in getSlotsForDate(date)" :key="slot.id">
                                                    <td class="border p-1.5 text-center text-xs text-slate-600"
                                                        x-text="minus10(slot.prep_start_time) + '–' + slot.exam_end_time.substring(0,5)"></td>
                                                </template>
                                            </tr>
                                        </template>
                                        <template x-for="dayRoom in getCorridorRooms(date)" :key="'corridor_'+dayRoom.id">
                                            <tr class="hover:bg-slate-50/30">
                                                <td class="border p-1.5 bg-white align-top">
                                                    <div class="font-medium text-gray-800 text-xs" x-text="dayRoom.room.room_number"></div>
                                                </td>
                                                <template x-for="slot in getSlotsForDate(date)" :key="slot.id">
                                                    <td class="border p-1 align-top">
                                                        <div x-data="teacherCell(slot.id, dayRoom.room_id, 'corridor', date)">
                                                            <template x-if="!supervision">
                                                                @unlessarchive
                                                                <select @change="assign($event.target.value)"
                                                                        :class="isMissing ? 'border-red-400 ring-1 ring-red-400 bg-red-50' : 'border-slate-200 bg-slate-50'"
                                                                        class="w-full text-xs rounded py-1 px-1.5">
                                                                    <option value="">— Kürzel —</option>
                                                                    <optgroup label="Unterricht Q13" x-show="availableTeachers.some(t => t.has_q13)">
                                                                        <template x-for="t in availableTeachers.filter(t => t.has_q13)" :key="t.id">
                                                                            <option :value="t.id" x-text="t.shortcode + ' (' + t.lastname + ')'"></option>
                                                                        </template>
                                                                    </optgroup>
                                                                    <optgroup label="Weitere">
                                                                        <template x-for="t in availableTeachers.filter(t => !t.has_q13)" :key="t.id">
                                                                            <option :value="t.id" x-text="t.shortcode + ' (' + t.lastname + ')'"></option>
                                                                        </template>
                                                                    </optgroup>
                                                                </select>
                                                                @else
                                                                <span class="block text-center text-xs text-gray-300">–</span>
                                                                @endarchive
                                                            </template>
                                                            <template x-if="supervision">
                                                                <div :class="isOrphaned ? 'border-orange-400 ring-1 ring-orange-400' : 'border-slate-300'"
                                                                     class="flex justify-between items-center bg-slate-100 border p-1.5 rounded"
                                                                     :title="isOrphaned ? 'Laut Plan aktuell keine Prüfung in diesem Slot' : ''">
                                                                    <strong class="text-slate-700 text-xs" x-text="supervision.teacher.shortcode"></strong>
                                                                    @unlessarchive
                                                                    <button @click="remove()" class="text-red-400 hover:text-red-600 font-bold ml-1">×</button>
                                                                    @endarchive
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>

                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

            </div>{{-- end x-data --}}

            {{-- MODAL: Aufsichten-Kontrolle --}}
            <div x-data x-show="$store.kolloquiumCoverage.showModal" style="display:none"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
                <div class="bg-white rounded-lg shadow-xl p-6 w-[32rem] max-h-[80vh] overflow-y-auto"
                     @click.away="$store.kolloquiumCoverage.showModal = false">
                    <h3 class="text-lg font-semibold mb-1">Aufsichten-Kontrolle</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Bedarf automatisch aus dem echten Kolloquiumsplan berechnet: je Prüfungsslot eine
                        Aufsicht für Gang und genutzten Vorbereitungsraum, Warteraum nur bei Kopplungen.
                    </p>

                    <template x-if="$store.kolloquiumCoverage.uncoveredCount === 0 && $store.kolloquiumCoverage.orphanedCount === 0">
                        <p class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
                            ✓ Alle benötigten Aufsichten sind besetzt, keine überflüssigen Einträge.
                        </p>
                    </template>

                    <template x-if="$store.kolloquiumCoverage.uncoveredCount > 0">
                        <div class="mb-4">
                            <div class="text-sm font-semibold text-red-700 mb-1">
                                <span x-text="$store.kolloquiumCoverage.uncoveredCount"></span> fehlende Aufsicht(en)
                            </div>
                            <p class="text-xs text-gray-500">Rot markierte Felder unten im Aufsichtsraster.</p>
                        </div>
                    </template>

                    <template x-if="$store.kolloquiumCoverage.orphanedCount > 0">
                        <div class="mb-2">
                            <div class="text-sm font-semibold text-orange-700 mb-1">
                                <span x-text="$store.kolloquiumCoverage.orphanedCount"></span> überflüssige Aufsicht(en)
                            </div>
                            <p class="text-xs text-gray-500">Orange markierte Felder unten im Aufsichtsraster – hier ist laut Plan aktuell keine Prüfung angesetzt.</p>
                        </div>
                    </template>

                    <div class="flex justify-end mt-4">
                        <button @click="$store.kolloquiumCoverage.showModal = false"
                                class="px-4 py-2 text-sm bg-gray-200 rounded hover:bg-gray-300">Schließen</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script @cspNonce>
    document.addEventListener('alpine:init', () => {
        Alpine.store('kolloquiumCoverage', {
            showModal:        false,
            uncoveredCount:   {{ $coverage['uncoveredCount'] }},
            orphanedCount:    {{ $coverage['orphanedCount'] }},
            missingCellKeys:  new Set(@json(array_keys($coverage['missingCellKeys']))),
            orphanedCellKeys: new Set(@json(array_keys($coverage['orphanedCellKeys']))),
            async refresh() {
                try {
                    const r = await fetch(`/exam-periods/${window._examPeriodId}/supervisions/coverage`);
                    const data = await r.json();
                    this.uncoveredCount   = data.uncoveredCount;
                    this.orphanedCount    = data.orphanedCount;
                    this.missingCellKeys  = new Set(data.missingCellKeys);
                    this.orphanedCellKeys = new Set(data.orphanedCellKeys);
                } catch { /* stiller Fehler, Badge bleibt auf altem Stand */ }
            },
        });
        // Reaktivitäts-Zähler: window._supervisionsIndex ist ein normales JS-Objekt
        // (nicht Teil des Alpine-Proxys). Nach jeder Zuweisung/Entfernung wird dieser
        // Zähler erhöht, damit die davon abhängigen Zellen (teacherCell.supervision)
        // neu ausgewertet werden und die Änderung sofort sichtbar wird.
        Alpine.store('supervisionsTick', { n: 0 });
    });
    </script>
    <script @cspNonce>
    // Globale Daten (für teacherCell-Unterkomponenten verfügbar)
    window._teachers       = @json($teachers);       // Alle (ohne StRef) – für Modals
    window._teachersByDate = @json($availableTeachersByDate); // Gefiltert pro Datum
    window._examPeriodId   = {{ $examPeriod->id }};
    window._csrf           = () => document.querySelector('meta[name="csrf-token"]').content;

    // 10 Minuten von einer "HH:MM:SS"-Zeit abziehen, z. B. "13:00:00" -> "12:50"
    function minus10(t) {
        const [h, m] = t.split(':').map(Number);
        let total = h * 60 + m - 10;
        if (total < 0) total += 24 * 60;
        const hh = Math.floor(total / 60).toString().padStart(2, '0');
        const mm = (total % 60).toString().padStart(2, '0');
        return `${hh}:${mm}`;
    }

    // ===== teacherCell: Alpine-Komponente für Vorbereitungs-/Warte-/Gangaufsicht-Zellen =====
    function teacherCell(slotId, roomId, type, date) {
        return {
            slotId, roomId, type, date,
            get availableTeachers() {
                return window._teachersByDate?.[this.date] ?? window._teachers;
            },
            get supervision() {
                Alpine.store('supervisionsTick').n; // Reaktivitäts-Abhängigkeit registrieren
                return window._supervisionsIndex?.[`${this.slotId}_${this.roomId}_${this.type}`] ?? null;
            },
            get cellKey() {
                return `${this.slotId}_${this.roomId}_${this.type}`;
            },
            get isMissing() {
                return Alpine.store('kolloquiumCoverage').missingCellKeys.has(this.cellKey);
            },
            get isOrphaned() {
                return Alpine.store('kolloquiumCoverage').orphanedCellKeys.has(this.cellKey);
            },
            async assign(teacherId) {
                if (!teacherId) return;
                try {
                    const r = await fetch(`/exam-periods/${window._examPeriodId}/supervisions/assign`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window._csrf() },
                        body: JSON.stringify({
                            time_slot_id: this.slotId,
                            room_id: this.roomId,
                            teacher_id: teacherId,
                            supervision_type: this.type
                        })
                    });
                    const data = await r.json();
                    if (data.success) {
                        window._supervisionsIndex[`${this.slotId}_${this.roomId}_${this.type}`] = data.supervision;
                        Alpine.store('supervisionsTick').n++;
                        showNote('Aufsicht gespeichert', 'success');
                        Alpine.store('kolloquiumCoverage').refresh();
                    } else { showNote(data.message || 'Fehler', 'error'); }
                } catch { showNote('Netzwerkfehler', 'error'); }
            },
            async remove() {
                const sup = this.supervision;
                if (!sup || !confirm('Aufsicht wirklich entfernen?')) return;
                try {
                    const r = await fetch(`/exam-periods/${window._examPeriodId}/supervisions/${sup.id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': window._csrf() }
                    });
                    const data = await r.json();
                    if (data.success) {
                        delete window._supervisionsIndex[`${this.slotId}_${this.roomId}_${this.type}`];
                        Alpine.store('supervisionsTick').n++;
                        showNote('Aufsicht entfernt', 'success');
                        Alpine.store('kolloquiumCoverage').refresh();
                    }
                } catch { showNote('Netzwerkfehler', 'error'); }
            }
        };
    }

    // ===== Hauptkomponente =====
    function kolloquiumAufsichten() {
        return {
            selectedWeek:        1,
            teachers:            @json($teachers),
            allExamDates:        @json($allExamDates),
            timeSlots:           @json($timeSlotsJs),
            oralDayRooms:        @json($oralDayRooms),

            init() {
                // Supervision-Index für alle Raumaufsichten (preparation, waiting, corridor)
                window._supervisionsIndex = {};
                @foreach($supervisions as $sup)
                    window._supervisionsIndex[`{{ $sup->time_slot_id }}_{{ $sup->room_id }}_{{ $sup->supervision_type }}`] = {
                        id: {{ $sup->id }},
                        teacher: { shortcode: @json($sup->teacher->shortcode ?? '?') }
                    };
                @endforeach
            },

            // --- Datum & Slots ---
            formatDate(date) {
                const p = date.split('-');
                const d = new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]));
                const days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
                return `${days[d.getDay()]}, ${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}.${d.getFullYear()}`;
            },
            get visibleExamDates() {
                const ep = @json($examPeriod);
                const w1s = ep.week_1_start.substring(0,10), w1e = ep.week_1_end.substring(0,10);
                const w2s = ep.week_2_start.substring(0,10), w2e = ep.week_2_end.substring(0,10);
                if (this.selectedWeek == 1) return this.allExamDates.filter(d => d >= w1s && d <= w1e);
                return this.allExamDates.filter(d => d >= w2s && d <= w2e);
            },
            getSlotsForDate(date) {
                return this.timeSlots.filter(s => s.date === date).sort((a,b) => a.slot_number - b.slot_number);
            },
            getPrepRooms(date)     { return this.oralDayRooms.filter(r => r.date === date && r.room_role === 'preparation'); },
            getWaitRooms(date)     { return this.oralDayRooms.filter(r => r.date === date && r.room_role === 'waiting'); },
            getCorridorRooms(date) { return this.oralDayRooms.filter(r => r.date === date && r.room_role === 'corridor'); },
        };
    }

    function showNote(msg, type = 'info') {
        const c = { success:'bg-green-500', error:'bg-red-500', info:'bg-blue-500' };
        const el = document.createElement('div');
        el.className = `fixed top-4 right-4 ${c[type]??c.info} text-white px-6 py-3 rounded-lg shadow-lg z-[100] text-sm`;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }
    </script>
    @endpush
</x-app-layout>
