<x-app-layout>
    @push('head-scripts')
    <meta name="mp-compute-url" content="{{ route('zusatzpruefungen.mindestpunkte.compute') }}">
    <style>
        .zp-card { cursor: grab; user-select: none; }
        .zp-card:active { cursor: grabbing; }
        .zp-drop-cell {
            min-height: 44px;
            border: 2px dashed #d1d5db;
            transition: background-color 0.1s, border-color 0.1s;
        }
        .zp-drop-cell.drag-over {
            border-color: #22c55e !important;
            background-color: #f0fdf4 !important;
        }
        .nta-card { border-left: 3px solid #f59e0b; }
        /* Vorausprüfung beim Dragging: rot = blockiert, grün = frei */
        .zp-drop-cell.pre-blocked {
            border-color: #f87171 !important;
            background-color: #fef2f2 !important;
            cursor: no-drop;
        }
        .zp-drop-cell.pre-free {
            border-color: #86efac !important;
            background-color: #f0fdf4 !important;
        }
        .zp-drop-cell.pre-free.drag-over {
            border-color: #16a34a !important;
            background-color: #dcfce7 !important;
        }
    </style>
    @endpush

    <div class="py-6" x-data="zusatzpruefungenApp"
         @student-selected.window="selectedStudentId = $event.detail.id; loadAllExams()">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold text-gray-900">Zusatzprüfungen</h1>
                <div class="flex items-center gap-2">
                    <button @click="openTeacherOverview()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded border border-gray-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                        Lehrkräfte
                    </button>
                    <button @click="showTimeslotModal = true"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded border border-gray-300">
                        ⏱ Zeitslots
                    </button>
                    <button @click="showDayModal = true"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded border border-gray-300">
                        ⚙ Tage &amp; Räume
                    </button>
                    <button @click="showAufsichtenModal = true"
                        :class="($store.zpAufsichten.uncoveredCount > 0 || $store.zpAufsichten.orphanedCount > 0)
                            ? 'bg-amber-50 hover:bg-amber-100 border-amber-300 text-amber-800'
                            : 'bg-gray-100 hover:bg-gray-200 border-gray-300'"
                        class="relative inline-flex items-center gap-1 px-3 py-1.5 text-sm rounded border">
                        👁 Aufsichten
                        <span x-show="$store.zpAufsichten.uncoveredCount > 0"
                              x-text="$store.zpAufsichten.uncoveredCount"
                              class="ml-0.5 bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5 leading-none ring-1 ring-white"
                              title="Aufsichten fehlen"></span>
                        <span x-show="$store.zpAufsichten.orphanedCount > 0"
                              x-text="$store.zpAufsichten.orphanedCount"
                              class="ml-0.5 bg-orange-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5 leading-none ring-1 ring-white"
                              title="Überflüssige Aufsichten"></span>
                    </button>
                    {{-- Export-Dropdown --}}
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded border border-blue-700">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                            Export
                            <svg class="w-3 h-3 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak
                            class="absolute right-0 mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
                            <a href="#"
                               @click.prevent="openPlanExportModal()"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Prüfungsplan (PDF)
                            </a>
                            <a href="{{ route('zusatzpruefungen.export.student-list-pdf') }}"
                               target="_blank"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Schülerliste (PDF)
                            </a>
                            <a href="#"
                               @click.prevent="showTeacherExportModal = true"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Lehrerübersicht (PDF)
                            </a>
                            <a href="{{ route('zusatzpruefungen.export.mindestpunkte-pdf') }}"
                               target="_blank"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v13a1 1 0 001 1z"/></svg>
                                Mindestpunkte (PDF)
                            </a>
                            <a href="#"
                               @click.prevent="showVorbProtModal = true"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                Vorbereitungsprotokoll (PDF)
                            </a>
                            <a href="#"
                               @click.prevent="showNiederschriftModal = true"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Niederschriften (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Flash --}}
            @if(session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-300 rounded text-green-800 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-300 rounded text-red-800 text-sm">{{ session('error') }}</div>
            @endif

            {{-- Schüler hinzufügen + Übersicht --}}
            @php
                $allPruefungen = $pending->merge($placed->flatten()->values())
                    ->sortBy(fn($p) => ($p->prueflingsName())
                        . str_pad((string)($p->sort_order ?? 0), 3, '0', STR_PAD_LEFT)
                        . ($p->studentExam->getSubjectName() ?? ''));
                // Gruppiert nach Prüflingsschlüssel, nicht nach student_id:
                // der ist für externe Bewerber null, und alle fielen damit in
                // dieselbe Gruppe.
                $byStudent = $allPruefungen->groupBy(fn($p) => $p->prueflingsSchluessel());
            @endphp
            <div class="mb-4 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden" id="zp-add-form-section">

                {{-- Toggle-Button --}}
                <button @click="showAddForm = !showAddForm"
                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 transition-transform duration-200" :class="showAddForm ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        Schüler hinzufügen &amp; Übersicht
                    </span>
                    @if($allPruefungen->isNotEmpty())
                        <span class="text-xs font-normal text-gray-400">
                            {{ $allPruefungen->count() }} {{ $allPruefungen->count() === 1 ? 'Prüfung' : 'Prüfungen' }},
                            {{ $byStudent->count() }} {{ $byStudent->count() === 1 ? 'Schüler/in' : 'Schülerinnen/Schüler' }}
                        </span>
                    @endif
                </button>

                <div x-show="showAddForm" x-cloak>

                    {{-- Hinzufügen-Formular --}}
                    <div class="px-4 py-3 border-t border-gray-100">
                        <div class="flex flex-wrap gap-4 items-start">

                            {{-- Schülersuche --}}
                            <div class="flex-none w-56" x-data="studentSearch" @click.outside="open = false">
                                <label class="block text-xs text-gray-500 mb-1">Schüler/in (Q13)</label>
                                <div class="relative">
                                    <input type="text" x-model="search"
                                        @input="open = true" @focus="open = true"
                                        placeholder="Nachname eingeben …"
                                        class="w-full border-gray-300 rounded text-sm py-1.5 pr-7">
                                    <button x-show="search" @click="clear()"
                                        class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
                                </div>
                                <ul x-show="open && filtered.length > 0" x-cloak
                                    class="absolute z-30 mt-1 max-h-56 overflow-y-auto bg-white border border-gray-300 rounded shadow-lg text-sm min-w-48 w-56">
                                    <template x-for="s in filtered" :key="s.id">
                                        <li @click="select(s)" class="px-3 py-1.5 cursor-pointer hover:bg-blue-50" x-text="s.name"></li>
                                    </template>
                                </ul>
                            </div>

                            {{-- Noten + ZP-Auswahl --}}
                            <div class="flex-1 min-w-0">
                                <div x-show="loadingExams" class="text-sm text-gray-400 py-2">Lade Prüfungsfächer …</div>

                                <template x-if="!loadingExams && allExams.length > 0">
                                <div class="space-y-2">
                                    {{-- Verbesserungs-Checkbox --}}
                                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" x-model="improvementOnly"
                                            class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-sm text-gray-700">Nur zur Notenverbesserung
                                            <span class="text-gray-400 font-normal">(Abitur bereits bestanden — nur Note des ZP-Fachs nötig)</span>
                                        </span>
                                    </label>

                                    {{-- Punktedifferenz aus dem Notenbescheid + berechnete Mindestnote --}}
                                    <template x-if="improvementOnly">
                                        <div class="flex flex-wrap items-center gap-2 bg-emerald-50 border border-emerald-200 rounded px-3 py-2">
                                            <label class="flex items-center gap-1.5 text-xs text-emerald-800">
                                                Punkte bis zur nächsten Zehntelnote
                                                <input type="number" min="0" max="255"
                                                    x-model="improvementPointsNeeded"
                                                    class="w-14 text-xs text-center border border-emerald-300 rounded px-1 py-0.5 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                                    placeholder="–">
                                                <span class="text-emerald-500">(laut Notenbescheid, z.&thinsp;B. 5)</span>
                                            </label>
                                            <template x-for="exam in allExams.filter(e => selectedZpIds.includes(e.id) && e.exam_type === 'written')" :key="'mo-'+exam.id">
                                                <span class="text-xs font-medium" x-text="mpImprovementHint(exam)"></span>
                                            </template>
                                        </div>
                                    </template>

                                    <div class="overflow-x-auto rounded border border-gray-200">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wide">
                                            <tr>
                                                <th class="px-2 py-1.5 text-left font-medium">Fach</th>
                                                <th class="px-2 py-1.5 text-center font-medium w-16">Kurs</th>
                                                <th class="px-2 py-1.5 text-center font-medium w-24">Prüfungsart</th>
                                                <th class="px-2 py-1.5 text-center font-medium w-20"
                                                    x-show="!improvementOnly">Note (0–15)</th>
                                                <th class="px-2 py-1.5 text-center font-medium w-20"
                                                    x-show="improvementOnly">Note (ZP-Fach)</th>
                                                <th class="px-2 py-1.5 text-center font-medium w-28">Zusatzprüfung</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="exam in allExams" :key="'ae-'+exam.id">
                                                <tr class="border-t border-gray-100"
                                                    x-show="!improvementOnly || exam.exam_type === 'written'"
                                                    :class="selectedZpIds.includes(exam.id) ? 'bg-amber-50/60' : (exam.has_zp ? 'bg-indigo-50/40' : '')">
                                                    {{-- Fach --}}
                                                    <td class="px-2 py-1.5">
                                                        <span class="font-medium text-gray-800" x-text="exam.subject"></span>
                                                        <span x-show="exam.is_leistungsfach" class="ml-1 text-amber-500 font-semibold">(LF)</span>
                                                        {{-- Erhöhtes Niveau ohne Leistungsfach gibt es nur bei externen
                                                             Bewerbern: Deutsch und Mathematik sind dort nach § 61 Abs. 1
                                                             S. 5 immer eA. Ohne diesen Hinweis ginge die Angabe verloren,
                                                             seit das (LF) nur noch am echten Leistungsfach steht. --}}
                                                        <span x-show="!exam.is_leistungsfach && exam.level === 'erhoeht'"
                                                              class="ml-1 text-xs text-amber-600">(eA)</span>
                                                    </td>
                                                    {{-- Kurskürzel --}}
                                                    <td class="px-2 py-1.5 text-center font-mono text-gray-400" x-text="exam.course_code"></td>
                                                    {{-- Art --}}
                                                    <td class="px-2 py-1.5 text-center text-gray-500"
                                                        x-text="exam.exam_type === 'written' ? 'Schriftlich' : 'Kolloquium'"></td>
                                                    {{-- Note --}}
                                                    <td class="px-2 py-1.5 text-center">
                                                        {{-- Verbesserungs-Modus: bereits angelegte ZP — Note bleibt editierbar --}}
                                                        <template x-if="improvementOnly && exam.has_zp && exam.exam_type === 'written' && !exam.is_sport">
                                                            <input type="number" min="0" max="15"
                                                                x-model="examScores[exam.id]"
                                                                class="w-14 text-xs text-center border border-emerald-300 bg-emerald-50/60 rounded px-1 py-0.5 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                                                placeholder="–">
                                                        </template>
                                                        {{-- Verbesserungs-Modus: nur das gewählte ZP-Fach bekommt ein Eingabefeld --}}
                                                        <template x-if="improvementOnly && !exam.has_zp && exam.exam_type === 'written' && selectedZpIds.includes(exam.id) && !exam.is_sport">
                                                            <input type="number" min="0" max="15"
                                                                x-model="examScores[exam.id]"
                                                                @input="onScoreInput(exam.id)"
                                                                class="w-14 text-xs text-center border border-emerald-400 bg-emerald-50 rounded px-1 py-0.5 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                                                placeholder="–">
                                                        </template>
                                                        <template x-if="improvementOnly && !exam.has_zp && exam.exam_type === 'written' && selectedZpIds.includes(exam.id) && exam.is_sport">
                                                            <div class="flex items-center gap-1 justify-center">
                                                                <div class="flex flex-col items-center gap-0.5">
                                                                    <span class="text-gray-400" style="font-size:9px">Prakt.</span>
                                                                    <input type="number" min="0" max="15"
                                                                        x-model="practicalGrades[exam.id]"
                                                                        class="w-12 text-xs text-center border border-amber-300 bg-amber-50 rounded px-1 py-0.5 focus:ring-1 focus:ring-amber-400 focus:outline-none"
                                                                        placeholder="–">
                                                                </div>
                                                                <span class="text-gray-300 text-xs">+</span>
                                                                <div class="flex flex-col items-center gap-0.5">
                                                                    <span class="text-gray-400" style="font-size:9px">Prüfg.</span>
                                                                    <input type="number" min="0" max="15"
                                                                        x-model="examScores[exam.id]"
                                                                        @input="onScoreInput(exam.id)"
                                                                        class="w-12 text-xs text-center border border-emerald-400 bg-emerald-50 rounded px-1 py-0.5 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                                                        placeholder="–">
                                                                </div>
                                                                <span class="text-gray-400 text-xs">×2</span>
                                                            </div>
                                                        </template>
                                                        <template x-if="improvementOnly && !exam.has_zp && !(exam.exam_type === 'written' && selectedZpIds.includes(exam.id))">
                                                            <span class="text-gray-300 text-xs">—</span>
                                                        </template>
                                                        {{-- Normalmodus: alle Fächer --}}
                                                        <template x-if="!improvementOnly && !exam.has_zp && !exam.is_sport">
                                                            <input type="number" min="0" max="15"
                                                                x-model="examScores[exam.id]"
                                                                @input="onScoreInput(exam.id)"
                                                                class="w-14 text-xs text-center border rounded px-1 py-0.5 focus:ring-1 focus:ring-blue-400 focus:outline-none"
                                                                :class="examScores[exam.id] === 0 || examScores[exam.id] === '0' ? 'border-red-400 bg-red-50 font-bold text-red-700' : (examScores[exam.id] !== '' && examScores[exam.id] !== null && examScores[exam.id] !== undefined ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50')"
                                                                placeholder="–">
                                                        </template>
                                                        {{-- Sport: zwei Felder nebeneinander --}}
                                                        <template x-if="!improvementOnly && !exam.has_zp && exam.is_sport">
                                                            <div class="flex items-center gap-1 justify-center">
                                                                <div class="flex flex-col items-center gap-0.5">
                                                                    <span class="text-gray-400" style="font-size:9px">Prakt.</span>
                                                                    <input type="number" min="0" max="15"
                                                                        x-model="practicalGrades[exam.id]"
                                                                        class="w-12 text-xs text-center border border-amber-300 bg-amber-50 rounded px-1 py-0.5 focus:ring-1 focus:ring-amber-400 focus:outline-none"
                                                                        placeholder="–">
                                                                </div>
                                                                <span class="text-gray-300 text-xs">+</span>
                                                                <div class="flex flex-col items-center gap-0.5">
                                                                    <span class="text-gray-400" style="font-size:9px">Prüfg.</span>
                                                                    <input type="number" min="0" max="15"
                                                                        x-model="examScores[exam.id]"
                                                                        @input="onScoreInput(exam.id)"
                                                                        class="w-12 text-xs text-center border rounded px-1 py-0.5 focus:ring-1 focus:ring-blue-400 focus:outline-none"
                                                                        :class="examScores[exam.id] === 0 || examScores[exam.id] === '0' ? 'border-red-400 bg-red-50 font-bold text-red-700' : (examScores[exam.id] !== '' && examScores[exam.id] !== null && examScores[exam.id] !== undefined ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50')"
                                                                        placeholder="–">
                                                                </div>
                                                                <span class="text-gray-400 text-xs">×2</span>
                                                            </div>
                                                        </template>
                                                        <template x-if="!improvementOnly && exam.has_zp && !exam.is_sport">
                                                            <div class="flex flex-col items-center gap-0.5">
                                                                <span class="font-mono text-gray-600 text-xs"
                                                                    x-text="(exam.saved_score !== null ? exam.saved_score : '–') + '\u00a0schr.'"></span>
                                                                <div class="flex items-center gap-1">
                                                                    <input type="number" min="0" max="15"
                                                                        x-model="targetScores[exam.id]"
                                                                        @keydown.enter="saveTargetPoints(exam); $el.blur()"
                                                                        class="w-12 text-xs text-center border rounded px-1 py-0.5 focus:ring-1 focus:outline-none"
                                                                        :class="targetScores[exam.id] !== '' && targetScores[exam.id] !== null && targetScores[exam.id] !== undefined
                                                                            ? 'border-green-400 bg-green-50 text-green-700 font-semibold focus:ring-green-400'
                                                                            : 'border-gray-300 focus:ring-indigo-400'"
                                                                        placeholder="mündl.">
                                                                    <button type="button"
                                                                        @click="saveTargetPoints(exam)"
                                                                        title="Mündliche Note speichern"
                                                                        class="shrink-0 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded p-0.5">
                                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        {{-- Sport schriftlich + bereits angelegte ZP: die schriftliche
                                                             Note bleibt reine Anzeige (analog zu allen anderen Fächern
                                                             bei has_zp). Die Praxisnote dagegen ist editierbar, seit sie
                                                             Pflicht ist (Entscheidung 40, 28.08.2026): ohne sie weist
                                                             der Server das Speichern der mündlichen Note ab — und wäre
                                                             sie hier nur Anzeige, hätte der Nutzer keinen Weg, sie
                                                             nachzutragen. Beim Durchklicken gefunden.
                                                             Siehe MindestpunkteService::calcSportZpContribution(). --}}
                                                        <template x-if="!improvementOnly && exam.has_zp && exam.is_sport">
                                                            <div class="flex flex-col items-center gap-0.5">
                                                                <span class="font-mono text-gray-600 text-xs"
                                                                    x-text="(exam.saved_score !== null ? exam.saved_score : '–') + '\u00a0schr.'"></span>
                                                                <div class="flex items-center gap-1">
                                                                    <input type="number" min="0" max="15"
                                                                        x-model="practicalGrades[exam.id]"
                                                                        @keydown.enter="saveTargetPoints(exam); $el.blur()"
                                                                        class="w-12 text-xs text-center border rounded px-1 py-0.5 focus:ring-1 focus:outline-none"
                                                                        :class="practicalGrades[exam.id] !== '' && practicalGrades[exam.id] !== null && practicalGrades[exam.id] !== undefined
                                                                            ? 'border-gray-300 bg-white'
                                                                            : 'border-amber-400 bg-amber-50 focus:ring-amber-400'"
                                                                        title="Praxisnote — ohne sie zaehlt das Fach in der Abiturrechnung nicht"
                                                                        placeholder="prakt.">
                                                                    <span class="text-[10px] text-gray-400">prakt.</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    <input type="number" min="0" max="15"
                                                                        x-model="targetScores[exam.id]"
                                                                        @keydown.enter="saveTargetPoints(exam); $el.blur()"
                                                                        class="w-12 text-xs text-center border rounded px-1 py-0.5 focus:ring-1 focus:outline-none"
                                                                        :class="targetScores[exam.id] !== '' && targetScores[exam.id] !== null && targetScores[exam.id] !== undefined
                                                                            ? 'border-green-400 bg-green-50 text-green-700 font-semibold focus:ring-green-400'
                                                                            : 'border-gray-300 focus:ring-indigo-400'"
                                                                        placeholder="mündl.">
                                                                    <button type="button"
                                                                        @click="saveTargetPoints(exam)"
                                                                        title="Mündliche Note speichern"
                                                                        class="shrink-0 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded p-0.5">
                                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </td>
                                                    {{-- ZP-Checkbox --}}
                                                    <td class="px-2 py-1.5 text-center">
                                                        <template x-if="exam.exam_type !== 'written'">
                                                            <span class="text-gray-300 text-xs">—</span>
                                                        </template>
                                                        <template x-if="exam.exam_type === 'written' && exam.has_zp">
                                                            <span class="inline-flex items-center gap-1.5 text-xs text-indigo-600 font-medium">
                                                                <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                                bereits angelegt
                                                                <button @click.prevent="destroyZp(exam.pruefung_id, exam.subject)"
                                                                    class="ml-0.5 text-red-300 hover:text-red-600 transition-colors leading-none"
                                                                    title="Zusatzprüfung löschen">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                </button>
                                                            </span>
                                                        </template>
                                                        <template x-if="exam.exam_type === 'written' && !exam.has_zp">
                                                            <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs">
                                                                <input type="checkbox"
                                                                    :checked="selectedZpIds.includes(exam.id)"
                                                                    @change="toggleZp(exam.id, $event.target.checked)"
                                                                    class="rounded border-gray-300 text-blue-600">
                                                                <span x-show="!selectedZpIds.includes(exam.id)" class="text-gray-400">ZP anlegen</span>
                                                                <span x-show="selectedZpIds.includes(exam.id)" class="text-blue-600 font-semibold">✓ ausgewählt</span>
                                                            </label>
                                                        </template>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                                            <tr>
                                                <td colspan="3" class="px-2 py-1.5 text-xs font-semibold text-gray-700">
                                                    Aktuell (Abiturprüfung):
                                                </td>
                                                <td colspan="2" class="px-2 py-1.5">
                                                    <template x-if="improvementOnly">
                                                        <span class="text-xs text-emerald-600 font-medium">🎯 Nur zur Verbesserung — kein Gesamtpunkte-Check nötig</span>
                                                    </template>
                                                    <template x-if="!improvementOnly && addFormTotal !== null">
                                                        <span class="text-xs font-bold"
                                                            :class="addFormTotal >= 100 ? 'text-green-700' : 'text-red-600'"
                                                            x-text="addFormTotal + ' Pkt. ' + (addFormTotal >= 100 ? '✓ ≥ 100' : '✗ < 100')">
                                                        </span>
                                                    </template>
                                                    <template x-if="!improvementOnly && addFormTotal === null">
                                                        <span class="text-xs text-gray-400">— alle Noten eintragen</span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>

                                    {{-- Pflichthinweis bei 0 Punkten --}}
                                    <template x-if="allExams.some(e => e.can_zp && !e.has_zp && (examScores[e.id] === 0 || examScores[e.id] === '0'))">
                                        <p class="text-xs text-red-600">
                                            ⚠ Fächer mit 0 Punkten sind für die Zusatzprüfung vorausgewählt (Pflicht).
                                        </p>
                                    </template>

                                    <div class="flex items-center gap-3 pt-1">
                                        <button @click="storeSelected()"
                                            :disabled="selectedZpIds.length === 0"
                                            class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm rounded">
                                            <span x-text="selectedZpIds.filter(id => !allExams.find(e=>e.id===id)?.has_zp).length > 0
                                                ? 'Anlegen (' + selectedZpIds.filter(id => !allExams.find(e=>e.id===id)?.has_zp).length + ' ZP)'
                                                : 'Speichern'"></span>
                                        </button>
                                        <button @click="selectedStudentId = null; allExams = []; examScores = {}; practicalGrades = {}; selectedZpIds = []; improvementOnly = false; improvementPointsNeeded = ''; window.dispatchEvent(new CustomEvent('student-name-prefill', { detail: { name: '' } }))"
                                            class="px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded border border-gray-300">
                                            Abbrechen
                                        </button>
                                        <span class="text-xs text-gray-400"
                                            x-show="selectedZpIds.length === 0"
                                            x-cloak>
                                            Bitte mindestens eine Zusatzprüfung auswählen.
                                        </span>
                                        <span class="text-xs text-gray-400"
                                            x-show="selectedZpIds.length > 0"
                                            x-cloak>
                                            Noten werden gleichzeitig gespeichert.
                                        </span>
                                    </div>
                                </div>
                                </template>

                                <template x-if="!loadingExams && allExams.length === 0 && selectedStudentId">
                                    <p class="text-sm text-gray-400 py-2">Keine Prüfungsfächer gefunden.</p>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Übersichtstabelle --}}
                    @if($allPruefungen->isNotEmpty())
                    <div x-data="{
                        zpExpanded: {},
                        expandAll()   { {{ Js::from($byStudent->keys()->values()) }}.forEach(id => this.zpExpanded[id] = true); },
                        collapseAll() { this.zpExpanded = {}; }
                    }" class="border-t border-gray-200 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-white">
                                    <td colspan="10" class="px-3 py-1 text-left">
                                        <button @click="expandAll()"
                                            class="text-xs text-blue-500 hover:text-blue-700 hover:underline">Alle aufklappen</button>
                                        <span class="text-gray-300 mx-1.5">·</span>
                                        <button @click="collapseAll()"
                                            class="text-xs text-blue-500 hover:text-blue-700 hover:underline">Alle einklappen</button>
                                    </td>
                                </tr>
                                <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                                    <th class="px-3 py-1.5 text-left font-medium w-44">Schüler/in</th>
                                    <th class="px-3 py-1.5 text-left font-medium w-36">Fach</th>
                                    <th class="px-3 py-1.5 text-left font-medium w-16">Kurs</th>
                                    <th class="px-3 py-1.5 text-left font-medium w-28">Prüfer</th>
                                    <th class="px-3 py-1.5 text-center font-medium w-8" title="Status">●</th>
                                    <th class="px-3 py-1.5 text-left font-medium w-44">Termin</th>
                                    <th class="px-3 py-1.5 text-left font-medium w-24">Prüfungsraum</th>
                                    <th class="px-3 py-1.5 text-center font-medium w-14" title="Schriftliche Prüfungspunkte">Schr.</th>
                                    <th class="px-3 py-1.5 text-center font-medium w-28" title="Mündliche Prüfungspunkte (fixierbar)">Mündl.</th>
                                    <th class="px-3 py-1.5 text-center font-medium w-20" title="Kombiniert (×4) + Verbesserung">= ×4</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $studentRowIndex = 0; @endphp
                                @foreach($byStudent as $studentId => $pruefungen)
                                    @php
                                        $rowBg     = $studentRowIndex % 2 === 0 ? '' : 'bg-gray-50/60';
                                        $rowNumber = $studentRowIndex + 1;
                                        $studentRowIndex++;
                                        $firstP    = $pruefungen->first();
                                        $deficit   = $deficits[$studentId] ?? null;
                                        $curTotal  = $deficit?->current_total_points;

                                        // Verbesserung summieren
                                        $totalDelta = 0;
                                        $allDeltaKnown = ($curTotal !== null);
                                        foreach ($pruefungen as $p2) {
                                            $d = $p2->getPointsDelta();
                                            if ($d !== null) {
                                                $totalDelta += $d;
                                            } else {
                                                $allDeltaKnown = false;
                                            }
                                        }
                                        $projected = ($curTotal !== null && $allDeltaKnown)
                                            ? $curTotal + $totalDelta
                                            : null;
                                        $hasImprovement = $pruefungen->contains(fn($p2) => $p2->improvement_only);
                                    @endphp

                                    {{-- Eingeklappte Zusammenfassung --}}
                                    <tr x-show="!zpExpanded['{{ $studentId }}']"
                                        class="{{ $rowBg }} border-t border-gray-200 cursor-pointer hover:bg-blue-50/40 transition-colors select-none"
                                        @click="zpExpanded['{{ $studentId }}'] = true">
                                        <td class="px-3 py-1.5 align-middle whitespace-nowrap">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs text-gray-400 w-5 text-right shrink-0 font-mono">{{ $rowNumber }}.</span>
                                                <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                                <span class="font-medium text-gray-900 text-sm">{{ $firstP->prueflingsName() }}</span>
                                                @if($firstP->hatNachteilsausgleich())
                                                    <span class="text-xs bg-yellow-100 text-yellow-800 px-1 py-0.5 rounded">+{{ $firstP->nachteilsausgleichProzent() }}&thinsp;%</span>
                                                @endif
                                                @if($hasImprovement)
                                                    <span class="text-xs bg-emerald-100 text-emerald-700 px-1 py-0.5 rounded" title="Nur zur Notenverbesserung">🎯 Verb.</span>
                                                @endif
                                                @if($curTotal !== null)
                                                    <span class="text-xs text-gray-400 ml-1">{{ $curTotal }}</span>
                                                    @if($projected !== null)
                                                        <span class="text-xs font-semibold {{ $projected >= 100 ? 'text-green-700' : 'text-red-600' }}">→ {{ $projected }}</span>
                                                    @else
                                                        <span class="text-xs text-gray-300">→ ?</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                        <td colspan="8" class="px-3 py-1.5">
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0">
                                                @foreach($pruefungen as $sp)
                                                <span class="text-xs flex items-center gap-0.5 text-gray-600">
                                                    @if($sp->absent)
                                                        <span style="color:#ef4444;font-size:.65rem">●</span>
                                                    @elseif($sp->status === 'placed')
                                                        <span style="color:#22c55e;font-size:.65rem">●</span>
                                                    @else
                                                        <span style="color:#eab308;font-size:.65rem">●</span>
                                                    @endif
                                                    {{ $sp->studentExam->getSubjectName() ?? '–' }}@if($sp->written_points !== null)<span class="text-gray-400 font-mono ml-0.5">({{ $sp->written_points }})</span>@endif
                                                </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-1.5 text-center text-xs">
                                            @if($projected !== null && $curTotal !== null)
                                                <span class="font-semibold {{ $projected >= 100 ? 'text-green-600' : 'text-red-500' }}">{{ $projected >= 100 ? '✓' : '✗' }}</span>
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Aufgeklappte Header-Zeile (Schülername + Aktionen) --}}
                                    <tr x-show="zpExpanded['{{ $studentId }}']" x-cloak
                                        class="{{ $rowBg }} border-t border-gray-200">
                                        <td colspan="10" class="px-3 pt-1.5 pb-1">
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5">
                                                {{-- Einklappen-Button + Name --}}
                                                <button @click.stop="zpExpanded['{{ $studentId }}'] = false"
                                                    class="text-gray-400 hover:text-gray-600 shrink-0" title="Einklappen">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                <span class="font-semibold text-gray-900 text-sm">{{ $firstP->prueflingsName() }}</span>
                                                @if($firstP->hatNachteilsausgleich())
                                                    <span class="text-xs bg-yellow-100 text-yellow-800 px-1 py-0.5 rounded leading-none">+{{ $firstP->nachteilsausgleichProzent() }}&thinsp;%</span>
                                                @endif
                                                @if($hasImprovement)
                                                    <span class="text-xs bg-emerald-100 text-emerald-700 px-1 py-0.5 rounded leading-none" title="Nur zur Notenverbesserung">🎯 Verb.</span>
                                                @endif
                                                {{-- Gesamtpunktzahl + Prognose.
                                                     Nur für Schüler: bei einem externen Bewerber
                                                     ergibt sie sich aus den acht Prüfungen und wird
                                                     nicht von Hand geführt (Entscheidung 26.08.2026). --}}
                                                @if(! $firstP->istExtern())
                                                <div class="flex items-center gap-1"
                                                     x-data="deficitEditor('{{ $studentId }}', {{ $curTotal ?? 'null' }})"
                                                     title="Aktuelle Gesamtpunktzahl im Abitur (ohne verbesserte Zusatzprüfungen)">
                                                    <span class="text-xs text-gray-400">Ges.:</span>
                                                    <input type="number" min="0" max="900"
                                                        x-model="val"
                                                        @blur="save()"
                                                        @keydown.enter="$el.blur()"
                                                        class="w-14 text-xs text-center border border-gray-200 rounded px-1 py-0.5 focus:ring-1 focus:ring-blue-400 focus:outline-none"
                                                        placeholder="–">
                                                    <template x-if="projected !== null">
                                                        <span class="flex items-center gap-1">
                                                            <span class="text-xs text-gray-400" x-text="projectedIsPartial ? '≥' : '→'"></span>
                                                            <span class="text-xs font-semibold px-1 py-0.5 rounded"
                                                                :class="projected >= 100 ? 'text-green-700 bg-green-50' : 'text-red-700 bg-red-50'"
                                                                :title="projectedIsPartial ? 'Teilprognose – noch nicht alle Prüfungen abgeschlossen' : (projected >= 100 ? 'Besteht bei diesen Zielpunkten ✓' : 'Noch nicht ausreichend ✗')"
                                                                x-text="projected + (projectedIsPartial ? '…' : '')">
                                                            </span>
                                                        </span>
                                                    </template>
                                                    <template x-if="projected === null && val !== ''">
                                                        <span class="text-xs text-gray-300">→ ?</span>
                                                    </template>
                                                </div>
                                                @else
                                                <span class="text-xs bg-purple-100 text-purple-700 px-1 py-0.5 rounded leading-none"
                                                      title="Externer Bewerber nach § 61 GSO">extern</span>
                                                @endif
                                                <span class="text-gray-300 select-none">·</span>
                                                {{-- Aktions-Links --}}
                                                @php
                                                    $allStudentExams = $allStudentExamsMap[$studentId] ?? collect();
                                                    $zpExamIds       = $pruefungen->pluck('student_exam_id')->toArray();

                                                    $modalExams = $allStudentExams->map(function($se) use ($zpExamIds, $pruefungen, $allExamScoresMap) {
                                                        $isZP    = in_array($se->id, $zpExamIds);
                                                        $zp      = $isZP ? $pruefungen->firstWhere('student_exam_id', $se->id) : null;
                                                        // Fach und Kürzel kommen aus dem zentralen Katalog —
                                                        // bei einem externen Bewerber über sein Prüfungsfach,
                                                        // bei einem Schüler über den Kurs.
                                                        $subject = $se->fachAusKatalog();
                                                        $cc      = $se->getSubjectLabel();
                                                        return [
                                                            'id'                => $se->id,
                                                            'subject'           => $subject?->name ?? '–',
                                                            'subjectCode'       => $subject?->code ?? '',
                                                            'courseCode'        => $cc,
                                                            'level'             => $se->getLevel() ?? 'grundlegend',
                                                            'examType'          => $se->exam_type,
                                                            'isForeignLanguage' => (bool)($subject?->is_foreign_language),
                                                            'isScience'         => (bool)($subject?->is_science),
                                                            'aufgabenfeld'      => $subject?->aufgabenfeld ?? null,
                                                            'isLeistungsfach'   => $se->istLeistungsfach(),
                                                            'isDeutsch'         => $subject?->code === 'D',
                                                            'isMathematik'      => $se->istMathematik(),
                                                            'isZusatzpruefung'  => $isZP,
                                                            'writtenPoints'     => $isZP ? $zp?->written_points : null,
                                                            'targetPoints'      => $isZP ? $zp?->target_points : null,
                                                            'pruefungId'        => $isZP ? $zp?->id : null,
                                                            'sort_order'        => $isZP ? ($zp?->sort_order ?? 0) : 0,
                                                            'isSport'           => $subject !== null && in_array($subject->code, ['Sw','Sm','Smw'], true),
                                                            'sportPracticalGrade' => ($isZP && $subject !== null && in_array($subject->code, ['Sw','Sm','Smw'], true))
                                                                ? ($allExamScoresMap[$se->id]?->practical_grade ?? null)
                                                                : null,
                                                            'score'             => $isZP ? null : ($allExamScoresMap[$se->id]?->score ?? null),
                                                            'practical_grade'   => $allExamScoresMap[$se->id]?->practical_grade ?? null,
                                                            'improvement_only'  => $isZP ? (bool)($zp?->improvement_only) : false,
                                                            'improvementPointsNeeded' => $isZP ? $zp?->improvement_points_needed : null,
                                                        ];
                                                    })
                                                    ->sortBy(fn($e) => ($e['examType'] === 'written' ? '0' : '1') . $e['subjectCode'])
                                                    ->values()->toArray();

                                                    $modalData = [
                                                        'studentName'    => $firstP->prueflingsName(),
                                                        'studentId'      => $studentId,
                                                        // Externe Bewerber bestehen nach § 62, nicht nach § 61.
                                                        'paragraph'      => str_starts_with((string) $studentId, 'e') ? '62' : '61',
                                                        'curTotal'       => $curTotal,
                                                        'improvementOnly'=> $pruefungen->every(fn($p2) => $p2->improvement_only),
                                                        'exams'          => $modalExams,
                                                        'pruefungen'  => $pruefungen->map(fn($p2) => [
                                                            'pruefungId'       => $p2->id,
                                                            'subject'          => ($p2->studentExam->getSubjectName() ?? '–')
                                                                                   . ' (' . $p2->studentExam->getSubjectLabel() . ')',
                                                            'written'          => $p2->written_points,
                                                            'targetInitial'    => $p2->target_points,
                                                            'isMathematik'     => $p2->studentExam->istMathematik(),
                                                            'excluded_semester' => $p2->excluded_semester,
                                                            'focus_semester'   => $p2->focus_semester,
                                                        ])->values()->toArray(),
                                                    ];
                                                    $hasAllZp = $pruefungen->count() >= 3;
                                                @endphp
                                                <button
                                                    @click="openMindestpunkteModal({{ json_encode($modalData, JSON_UNESCAPED_UNICODE) }})"
                                                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer whitespace-nowrap mr-3">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                                    Mindestpunkte
                                                </button>
                                                @if(!$hasAllZp)
                                                <button
                                                    @click="prefillStudent('{{ $studentId }}', '{{ addslashes($firstP->prueflingsName()) }}')"
                                                    class="inline-flex items-center gap-1 text-xs text-green-600 hover:text-green-800 hover:underline cursor-pointer whitespace-nowrap mr-3">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                    ZP ergänzen
                                                </button>
                                                @endif
                                                <button
                                                    @click="prefillStudent('{{ $studentId }}', '{{ addslashes($firstP->prueflingsName()) }}')"
                                                    class="inline-flex items-center gap-1 text-xs text-blue-500 hover:text-blue-700 hover:underline cursor-pointer whitespace-nowrap mr-3">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                    Noten bearbeiten
                                                </button>
                                                <button
                                                    @click="destroyStudent('{{ $studentId }}', '{{ addslashes($firstP->prueflingsName()) }}')"
                                                    class="inline-flex items-center gap-1 text-xs text-red-400 hover:text-red-600 hover:underline cursor-pointer whitespace-nowrap">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    Schüler entfernen
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- Aufgeklappte Fachzeilen --}}
                                    @foreach($pruefungen as $i => $p)
                                    <tr x-show="zpExpanded['{{ $studentId }}']" x-cloak
                                        class="{{ $rowBg }} hover:bg-blue-50/40 transition-colors
                                               {{ $i > 0 ? 'border-t border-gray-100' : '' }}">
                                        <td class="px-0 py-1" style="border-left: 2px solid #e5e7eb;"></td>
                                        <td class="px-3 py-1 text-gray-800 whitespace-nowrap max-w-[9rem] overflow-hidden text-ellipsis">
                                            {{ $p->studentExam->getSubjectName() ?? '–' }}
                                        </td>
                                        <td class="px-3 py-1 text-gray-500 font-mono text-xs whitespace-nowrap">
                                            {{ $p->studentExam->getSubjectLabel() }}
                                        </td>
                                        <td class="px-3 py-1 text-gray-600 whitespace-nowrap">
                                            @foreach($p->pruefer as $pr)
                                                <span class="text-xs {{ $pr->role === 'erstpruefer' ? 'font-semibold text-gray-700' : 'text-gray-400' }}">{{ $pr->teacher->shortcode }}</span>{{ !$loop->last ? ' · ' : '' }}
                                            @endforeach
                                        </td>
                                        <td class="px-3 py-1 text-center">
                                            @if($p->absent)
                                                <span style="color:#ef4444;font-size:1rem;line-height:1;" title="Nicht angetreten">●</span>
                                            @elseif($p->status === 'placed')
                                                <span style="color:#22c55e;font-size:1rem;line-height:1;" title="Platziert">●</span>
                                            @else
                                                <span style="color:#eab308;font-size:1rem;line-height:1;" title="Ausstehend">●</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1 text-gray-700 whitespace-nowrap">
                                            @if($p->assignment)
                                                @php
                                                    $asgn = $p->assignment;
                                                    $prepStart = \Carbon\Carbon::createFromTimeString($asgn->start_time);
                                                    $examStart = $prepStart->copy()->addMinutes($asgn->prep_duration_minutes);
                                                    $examEnd   = $examStart->copy()->addMinutes($asgn->exam_duration_minutes);
                                                @endphp
                                                <span class="font-medium">{{ $asgn->day?->date?->locale('de')->translatedFormat('D, d.m.') }}</span>
                                                <span class="text-xs text-gray-500 ml-1">{{ $examStart->format('H:i') }}–{{ $examEnd->format('H:i') }}</span>
                                            @else
                                                <span class="text-gray-300">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1 text-gray-700 whitespace-nowrap">
                                            @if($p->assignment?->dayRoom)
                                                {{ $p->assignment->dayRoom->examRoom?->room_number ?? '–' }}
                                            @else
                                                <span class="text-gray-300">–</span>
                                            @endif
                                        </td>
                                        {{-- Schriftlich --}}
                                        <td class="px-3 py-1 text-center"
                                            x-data="pointsEditor({{ $p->id }}, 'written_points', {{ $p->written_points ?? 'null' }})">
                                            <input type="number" min="0" max="15"
                                                x-model="val"
                                                @input="updateStore()"
                                                @blur="save()"
                                                @keydown.enter="$el.blur()"
                                                :disabled="locked"
                                                class="w-11 text-xs text-center border border-gray-200 rounded px-1 py-0.5 focus:ring-1 focus:ring-blue-400 focus:outline-none disabled:bg-gray-50 disabled:text-gray-400 disabled:cursor-not-allowed"
                                                :class="!locked && val !== null && val !== '' ? 'bg-gray-100 border-gray-300 font-semibold' : ''"
                                                placeholder="–">
                                        </td>
                                        {{-- Mündlich + Lock-Button --}}
                                        <td class="px-3 py-1 text-center"
                                            x-data="pointsEditor({{ $p->id }}, 'target_points', {{ $p->target_points ?? 'null' }}, {{ $p->locked ? 'true' : 'false' }})">
                                            <div class="flex items-center gap-1 justify-center">
                                                <input type="number" min="0" max="15"
                                                    x-model="val"
                                                    @input="updateStore()"
                                                    @blur="save()"
                                                    @keydown.enter="$el.blur()"
                                                    :disabled="locked"
                                                    class="w-11 text-xs text-center border rounded px-1 py-0.5 focus:ring-1 focus:ring-blue-400 focus:outline-none disabled:bg-gray-50 disabled:text-gray-400 disabled:cursor-not-allowed"
                                                    :class="locked ? 'border-gray-200' : (val !== null && val !== '' ? 'bg-blue-50 border-blue-300 font-semibold' : 'border-gray-200')"
                                                    placeholder="–">
                                                <button @click="toggleLock()"
                                                    class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0"
                                                    :title="locked ? 'Fixierung aufheben' : 'Punkte fixieren'"
                                                    :class="locked ? 'text-amber-500 hover:text-amber-700' : 'text-gray-300 hover:text-gray-500'">
                                                    <template x-if="locked">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                    </template>
                                                    <template x-if="!locked">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                                    </template>
                                                </button>
                                            </div>
                                        </td>
                                        {{-- Kombiniert ×4 + Delta (reaktiv aus Store) --}}
                                        <td class="px-3 py-1 text-center"
                                            x-data="{
                                                get c() { return $store.zpPoints.getContribution({{ $p->id }}); },
                                                get d() { return $store.zpPoints.getDelta({{ $p->id }}); }
                                            }">
                                            <template x-if="c !== null">
                                                <span class="inline-flex items-baseline gap-1">
                                                    <span class="text-xs font-semibold text-gray-700" x-text="c"></span>
                                                    <template x-if="d !== null && d !== 0">
                                                        <span class="text-xs font-medium"
                                                            :class="d > 0 ? 'text-green-600' : 'text-red-500'"
                                                            x-text="(d > 0 ? '+' : '') + d"></span>
                                                    </template>
                                                </span>
                                            </template>
                                            <template x-if="c === null">
                                                <span class="text-gray-200 text-xs">–</span>
                                            </template>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                        <p class="px-3 py-1.5 text-xs text-gray-400 border-t border-gray-100">
                            <strong>Schr.</strong> = schriftliche Prüfungspunkte &nbsp;·&nbsp;
                            <strong>Mündl.</strong> = mündliche Prüfungspunkte (🔒 = fixiert, nicht mehr änderbar) &nbsp;·&nbsp;
                            <strong>= ×4</strong> = round((2×Schr.+Mündl.)/3)×4, kein Aufrunden auf 1; in Grün die Verbesserung gegenüber der schriftlichen Prüfung &nbsp;·&nbsp;
                            <strong>Ges.</strong> → <strong>Prognose</strong> = Gesamtpunkte mit diesen Werten (grün ≥ 100)
                        </p>
                    </div>
                    @endif

                </div>{{-- /x-show --}}
            </div>

            {{-- Layout: Sidebar + Matrix --}}
            <div class="flex gap-4">

                {{-- SIDEBAR --}}
                <div class="w-72 shrink-0">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-3 min-h-40">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                            Nicht platziert ({{ $pending->count() }})
                        </h2>
                        @forelse($pending as $pruefung)
                            @include('zusatzpruefungen.partials.sidebar-card', ['pruefung' => $pruefung])
                        @empty
                            <p class="text-xs text-gray-400 text-center py-6">Keine unplatzierten Prüfungen</p>
                        @endforelse
                    </div>
                </div>

                {{-- MATRIX --}}
                <div class="flex-1 overflow-x-auto">
                    @if($days->isEmpty())
                        <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-500">
                            <p class="text-sm">Noch keine Prüfungstage konfiguriert.</p>
                            <p class="text-xs mt-1">Klicke auf <strong>⚙ Tage &amp; Räume</strong>, um Tage anzulegen.</p>
                        </div>
                    @else
                        @include('zusatzpruefungen.partials.matrix', ['days' => $days, 'placed' => $placed])
                    @endif
                </div>
            </div>

            {{-- AUFSICHTEN --}}
            @if($days->isNotEmpty())
                <div class="mt-4 text-center text-xs text-gray-400">
                    <button @click="showAufsichtenModal = true"
                            class="underline hover:no-underline text-gray-500">
                        👁 Aufsichten für Vorbereitungsräume konfigurieren
                    </button>
                </div>
            @endif

        </div>

        {{-- MODALS (müssen innerhalb des x-data-Scopes liegen) --}}
        @include('zusatzpruefungen.partials.tage-modal', ['days' => $days, 'rooms' => $rooms])
        @include('zusatzpruefungen.partials.timeslots-modal', ['days' => $days])
        @include('zusatzpruefungen.partials.aufsichten-modal', ['days' => $days, 'teachers' => $teachers, 'uncoveredKeys' => $uncoveredKeys, 'slotsWithExams' => $slotsWithExams])
        @include('zusatzpruefungen.partials.pruefer-drawer', ['teachers' => $teachers])
        @include('zusatzpruefungen.partials.teacher-overview-modal')
        @include('zusatzpruefungen.partials.plan-export-modal', ['days' => $days, 'placed' => $placed])
        @include('zusatzpruefungen.partials.teacher-export-modal', ['days' => $days, 'placed' => $placed])
        @include('zusatzpruefungen.partials.vorbereitungsprotokoll-export-modal', ['days' => $days, 'placed' => $placed])
        @include('zusatzpruefungen.partials.niederschrift-export-modal', ['days' => $days, 'placed' => $placed])
        @include('zusatzpruefungen.partials.mindestpunkte-modal')

    </div>


    {{-- Toast --}}
    <div x-data x-show="$store.zpToast.visible" x-transition
        :class="$store.zpToast.error ? 'bg-red-600' : 'bg-green-600'"
        class="fixed bottom-4 right-4 text-white text-sm px-4 py-2 rounded shadow-lg z-50"
        x-text="$store.zpToast.message">
    </div>

    @push('scripts')
    <script src="{{ asset('js/mindestpunkte.js') }}?v={{ filemtime(public_path('js/mindestpunkte.js')) }}"></script>
    @php
        // Daten für den Alpine zpPoints-Store
        $zpExams = [];
        $zpStudentExams = [];
        $zpSortOrders = [];
        foreach ($allPruefungen as $_p) {
            // Sport-ZP-Sonderfall: Praxisnote fließt zusätzlich zur Theorienote
            // (written_points) ein, siehe MindestpunkteService::calcSportZpContribution().
            $_subjCode = $_p->studentExam?->getSubjectCode();
            $_isSport  = in_array($_subjCode, ['Sw', 'Sm', 'Smw'], true);
            $zpExams[$_p->id] = [
                'written'        => $_p->written_points,
                'target'         => $_p->target_points,
                'locked'         => (bool) $_p->locked,
                'isSport'        => $_isSport,
                'practicalGrade' => $_isSport
                    ? ($sportZpPracticalGrades[$_p->student_exam_id]->practical_grade ?? null)
                    : null,
            ];
            $zpStudentExams[$_p->prueflingsSchluessel()][] = $_p->id;
            $zpSortOrders[$_p->id] = $_p->sort_order ?? 0;
        }
        // days-Map: dayId → ISO-Datum (für Reihenfolge-Prüfung im Drop)
        $daysDateMap = $days->mapWithKeys(fn($_d) => [$_d->id => $_d->date->toDateString()])->toArray();
    @endphp
    <script @cspNonce>
    document.addEventListener('alpine:init', () => {
        Alpine.store('zpAufsichten', {
        uncoveredCount: {{ $uncoveredCount }},
        orphanedCount:  {{ $orphanedCount }},
        update(delta) {
            this.uncoveredCount = Math.max(0, this.uncoveredCount + delta);
        },
        updateOrphaned(delta) {
            this.orphanedCount = Math.max(0, this.orphanedCount + delta);
        },
    });

    Alpine.store('zpToast', { visible: false, message: '', error: false });

        Alpine.store('zpPoints', {
            exams:        @json($zpExams),
            studentExams: @json($zpStudentExams),

            update(id, field, val) {
                if (!this.exams[id]) this.exams[id] = { written: null, target: null, locked: false };
                this.exams[id][field] = val;
            },

            isLocked(id) {
                return this.exams[id]?.locked ?? false;
            },

            setLocked(id, val) {
                if (this.exams[id]) this.exams[id].locked = val;
            },

            /**
             * Kombinierte Anzeige-Note (0–15). Sport-ZP: round((praxis+theorie+m)/3),
             * sonst round((2w+m)/3). Siehe MindestpunkteService::calcSportZpCombined()
             * bzw. calcCombined() (PHP-Pendant).
             */
            getCombined(id) {
                const e = this.exams[id];
                if (!e || e.written === null || e.target === null) return null;
                if (e.isSport) {
                    if (e.practicalGrade === null || e.practicalGrade === undefined) return null;
                    const raw = (e.practicalGrade + e.written + e.target) / 3;
                    return raw < 1 ? 0 : Math.round(raw);
                }
                const raw = (2 * e.written + e.target) / 3;
                return raw < 1 ? 0 : Math.round(raw);
            },

            /**
             * ×4-Beitrag (0–60) eines ZP-Fachs. WICHTIG: rechnet direkt mit
             * round((2w+t)×4/3) (Anlage 12 §52 GSO, Rundung erst am ×4-Ergebnis),
             * NICHT über getCombined()×4 — das würde doppelt runden und kann zu
             * Abweichungen wie 19 statt 20 vs. fälschlich 20 statt 19 führen.
             * Siehe MindestpunkteService::calcZpContribution() (PHP-Pendant).
             *
             * Sport-ZP-Sonderfall: schriftlicher Teil = (Praxis + Theorie), nicht
             * 2× Theorie — siehe MindestpunkteService::calcSportZpContribution()
             * und § 52 Abs. 2 Nr. 2 GSO (schulinterner Leitfaden Nr. 2.3.2.2).
             */
            getContribution(id) {
                const e = this.exams[id];
                if (!e || e.written === null || e.target === null) return null;
                if (e.isSport) {
                    if (e.practicalGrade === null || e.practicalGrade === undefined) return null;
                    return Math.round((e.practicalGrade + e.written + e.target) * 4 / 3);
                }
                return Math.round((2 * e.written + e.target) * 4 / 3);
            },

            /**
             * Abweichung des Beitrags ggü. dem Beitrag ohne ZP (mündliche Note = 0
             * gedacht, nur schriftlicher Teil). Sport-ZP: Referenzbasis ist
             * (Praxis + Theorie) × 2 — exakt die Nicht-ZP-Sport-Formel
             * (MindestpunkteService::scoreContribution(): (score+practicalGrade)×2),
             * NICHT ×4. Andere ZP-Fächer haben ohne ZP keinen Praxisanteil, daher
             * dort schlicht Theorie × 4 (volle Wertung eines Einzelfachs).
             */
            getDelta(id) {
                const e = this.exams[id];
                if (!e || e.written === null || e.written === undefined) return null;
                const c = this.getContribution(id);
                if (c === null) return null;
                if (e.isSport) {
                    if (e.practicalGrade === null || e.practicalGrade === undefined) return null;
                    return c - (e.practicalGrade + e.written) * 2;
                }
                return c - e.written * 4;
            },

            /**
             * Σ Delta aller ZP-Fächer eines Schülers (Anzeige in der Übersicht).
             * WICHTIG: nutzt getContribution() (echter ×4-Beitrag, einmal gerundet),
             * NICHT getCombined()×4 — sonst doppeltes Runden wie bei getContribution()
             * selbst beschrieben. Referenzbasis pro Fach: (Praxis+Theorie)×2 bei
             * Sport-ZP (siehe getDelta()), sonst Theorie×4.
             */
            getStudentDelta(studentId) {
                const ids = this.studentExams[studentId] || [];
                let delta = 0;
                let anyKnown = false;
                // Nur Prüfungen einrechnen, bei denen sowohl written als auch target bekannt sind.
                // Prüfungen ohne written oder target werden übersprungen (kein null-Abbruch).
                for (const id of ids) {
                    const e = this.exams[id];
                    if (!e || e.written === null || e.written === undefined) continue; // skip
                    if (e.target === null || e.target === undefined) continue;         // skip
                    if (e.isSport && (e.practicalGrade === null || e.practicalGrade === undefined)) continue; // skip
                    const d = this.getDelta(id);
                    if (d === null) continue;
                    delta += d;
                    anyKnown = true;
                }
                return anyKnown ? delta : null;
            },
        });

        Alpine.store('zpSortOrders', {
            orders: @json($zpSortOrders),
            set(pruefungId, order) {
                if (pruefungId) this.orders[pruefungId] = order;
            },
            get(pruefungId) {
                return this.orders[pruefungId] ?? 0;
            },
        });
    });

    document.addEventListener('alpine:init', () => {
    Alpine.data('zusatzpruefungenApp', () => ({
            ...mpMixin(),
            showAddForm: localStorage.getItem('zp-show-add-form') === '1',
            showDayModal: false,
            showTimeslotModal: {{ session('reopen_timeslot_modal') ? 'true' : 'false' }},
            showAufsichtenModal: false,
            showPlanExportModal: false,
            showTeacherExportModal: false,
            showVorbProtModal: false,
            showNiederschriftModal: false,
            showTeacherOverview: false,
            teacherOverviewLoading: false,
            teacherOverviewError: null,
            teacherOverviewData: [],
            teacherOverviewSearch: '',
            selectedStudentId: '',
            allExams: [],         // alle 5 Abiturfächer des ausgewählten Schülers
            examScores: {},       // { exam_id → score } für Noteneingabe
            practicalGrades: {},  // { exam_id → practical_grade } nur für Sport
            targetScores: {},     // { exam_id → mündliche ZP-Note } nur für bereits angelegte ZP
            selectedZpIds: [],    // exam_ids die als ZP angelegt werden sollen
            improvementOnly: false, // Nur-Verbesserungs-Modus
            improvementPointsNeeded: '', // Punktedifferenz aus Notenbescheid (300–900-Skala)
            loadingExams: false,
            draggingId: null,
            conflictMap: @json($conflictMapData),
            daysDateMap: @json($daysDateMap),
            allTeachers: @json($teachersData),
            activePruefungId: null,
            drawer: { open: false, pruefungData: null, pruefer: [] },

            // ─── Mindestpunkte-Modal ─────────────────────────────────────
            mindestpunkteModal: {
                show: false,
                studentName: '',
                studentId: null,
                // '61' für Schüler, '62' für externe Bewerber — zwei Rechenwege.
                paragraph: '61',
                curTotal: null,
                improvementOnly: false,
                pruefungen: [],  // für Print-Zwecke noch vorhanden (unused in Template)
                exams: [],       // alle 5 Abiturfächer mit Metadaten
                scores: {},      // { student_exam_id → score } für feste Fächer
                practicalGrades: {},  // { student_exam_id → practical_grade } nur Sport
            },
            // Berechnungsergebnis vom Server (MindestpunkteService)
            computedData: null,
            // Berechnungsergebnis nach § 62 (ExterneMindestpunkteService).
            // Eigenes Feld, weil die beiden Rechenwege verschiedene Formen
            // haben — ein gemeinsames Feld hätte am Ende beide Formen tragen
            // müssen und wäre genau die Vermischung, die vermieden werden soll.
            computedData62: null,
            computeError: null,
            computeLoading: false,
            _computeTimer: null,
            hideWorsening: true,   // Filter: Kombinationen mit Verschlechterung ausblenden

            openMindestpunkteModal(data) {
                this.mindestpunkteModal.studentName   = data.studentName;
                this.mindestpunkteModal.studentId     = data.studentId;
                // Der Prüflingsschlüssel entscheidet über die Norm: "e<id>" ist
                // ein externer Bewerber und rechnet nach § 62. Der Server sagt
                // es ausdrücklich; das String-Präfix ist nur der Rückfall für
                // den ersten, serverseitig eingebetteten Aufruf.
                this.mindestpunkteModal.paragraph     = data.paragraph
                    ?? (String(data.studentId ?? '').startsWith('e') ? '62' : '61');
                this.mindestpunkteModal.curTotal      = data.curTotal ?? null;
                this.mindestpunkteModal.improvementOnly = data.improvementOnly ?? false;

                // Aktuelle Punkte UND sort_order aus den reaktiven Stores laden —
                // überschreibt die beim Seitenrendering eingebetteten PHP-Werte
                const zpStore      = Alpine.store('zpPoints').exams;
                const sortOrders   = Alpine.store('zpSortOrders');

                const exams = (data.exams ?? []).map(e => {
                    if (e.isZusatzpruefung && e.pruefungId) {
                        const s = zpStore[e.pruefungId];
                        return { ...e,
                            writtenPoints: s ? (s.written ?? null) : (e.writtenPoints ?? null),
                            targetPoints:  s ? (s.target  ?? null) : (e.targetPoints  ?? null),
                            sort_order:    sortOrders.get(e.pruefungId),
                        };
                    }
                    return e;
                });
                this.mindestpunkteModal.exams = exams;

                const pruefungen = (data.pruefungen ?? []).map(p => {
                    if (p.pruefungId && zpStore[p.pruefungId]) {
                        return { ...p, written: zpStore[p.pruefungId].written ?? null };
                    }
                    return p;
                });
                this.mindestpunkteModal.pruefungen = pruefungen;

                const scores = {};
                const practicalGradesModal = {};
                exams.forEach(e => {
                    if (!e.isZusatzpruefung) {
                        scores[e.id] = (e.score !== null && e.score !== undefined) ? e.score : '';
                    }
                    // Sport: praktische Note vorinitialisieren (auch wenn noch nicht gesetzt)
                    if (e.isSport) {
                        practicalGradesModal[e.id] = (e.practical_grade !== null && e.practical_grade !== undefined)
                            ? e.practical_grade : '';
                    }
                });
                this.mindestpunkteModal.scores = scores;
                this.mindestpunkteModal.practicalGrades = practicalGradesModal;
                this.mindestpunkteModal.show = true;
                this.computedData = null;
                this.computedData62 = null;
                this.computeError = null;
                this.mpRecompute();
            },

            /** Druckt den Kalkulator in einem neuen Fenster */
            prefillStudent(studentId, studentName) {
                this.showAddForm = true;
                this.selectedStudentId = String(studentId);
                this.loadAllExams();
                this.$nextTick(() => {
                    window.dispatchEvent(new CustomEvent('student-name-prefill', { detail: { name: studentName } }));
                    document.getElementById('zp-add-form-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            },

            async destroyStudent(studentId, studentName) {
                if (!confirm(`Alle Zusatzprüfungen und Noten von „${studentName}" wirklich löschen?`)) return;
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/zusatzpruefungen/pruefling/${studentId}/all`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                });
                const data = await res.json();
                if (data.success) window.location.reload();
                else this.showToast('Fehler beim Löschen', true);
            },

            showToast(msg, error = false) {
                Alpine.store('zpToast').message  = msg;
                Alpine.store('zpToast').error    = error;
                Alpine.store('zpToast').visible  = true;
                setTimeout(() => { Alpine.store('zpToast').visible = false; }, 3500);
            },

            /** Gesamtpunktzahl aus den Noteneingaben im Formular */
            get addFormTotal() {
                if (this.allExams.length === 0) return null;
                const scores = this.allExams.map(e => {
                    // ZP bereits angelegt: mit muendlicher Note (target_points) den
                    // echten x4-Beitrag berechnen (Anlage 12 Paragraph 52 GSO:
                    // round((2*schr.+muendl.)*4/3), Rundung erst am x4-Ergebnis).
                    // Ohne muendliche Note bleibt schr.*4 die Untergrenze.
                    if (e.has_zp) {
                        if (e.saved_score === null || e.saved_score === undefined) return null;
                        const written = parseInt(e.saved_score);
                        if (e.target_points !== null && e.target_points !== undefined) {
                            return Math.round((2 * written + parseInt(e.target_points)) * 4 / 3);
                        }
                        return written * 4;
                    }
                    const s = this.examScores[e.id];
                    let score = (s !== null && s !== undefined && s !== '') ? parseInt(s) : null;
                    if (score === null) return null;
                    // Sport: (Prüfungsleistung + Praktisch) × 2
                    if (e.is_sport) {
                        const pg = this.practicalGrades[e.id];
                        const pgInt = (pg !== null && pg !== undefined && pg !== '') ? parseInt(pg) : null;
                        if (pgInt === null) return null;
                        return (score + pgInt) * 2;
                    }
                    return score * 4;
                });
                if (scores.some(s => s === null)) return null;
                return scores.reduce((sum, s) => sum + s, 0);
            },

            /** Lädt alle 5 Abiturfächer des Schülers (AJAX) */
            async loadAllExams() {
                if (!this.selectedStudentId) { this.allExams = []; return; }
                this.loadingExams = true;
                this.selectedZpIds = [];
                this.examScores = {};
                this.practicalGrades = {};
                this.targetScores = {};
                this.improvementOnly = false;
                this.improvementPointsNeeded = '';
                try {
                    const res = await fetch(`/zusatzpruefungen/pruefling/${this.selectedStudentId}/all-exams`);
                    this.allExams = await res.json();
                    this.allExams.forEach(e => {
                        // Note vorbefüllen — auch wenn bereits eine ZP angelegt ist,
                        // damit die schriftliche Note weiterhin sichtbar/editierbar bleibt.
                        this.examScores[e.id] = e.saved_score !== null && e.saved_score !== undefined ? e.saved_score : '';
                        // Mündliche ZP-Note vorbefüllen (nur relevant wenn has_zp)
                        if (e.has_zp) {
                            this.targetScores[e.id] = (e.target_points !== null && e.target_points !== undefined)
                                ? e.target_points : '';
                        }
                        // Sport: praktische Note vorinitialisieren (auch wenn null → leerer String für x-model)
                        if (e.is_sport) {
                            this.practicalGrades[e.id] = (e.practical_grade !== null && e.practical_grade !== undefined)
                                ? e.practical_grade : '';
                        }
                        // Auto-vorauswählen: ZP bereits vorhanden oder Score = 0
                        if (e.has_zp) {
                            this.selectedZpIds.push(e.id);
                            // Verbesserungsmodus + Punktedifferenz aus der ersten bereits
                            // angelegten ZP übernehmen (gilt aktuell global für den Schüler).
                            if (e.improvement_only) {
                                this.improvementOnly = true;
                                if (e.improvement_points_needed !== null && e.improvement_points_needed !== undefined) {
                                    this.improvementPointsNeeded = e.improvement_points_needed;
                                }
                            }
                        } else if (e.can_zp && e.saved_score === 0) {
                            this.selectedZpIds.push(e.id);
                        }
                    });
                } finally {
                    this.loadingExams = false;
                }
            },

            /** Wird bei Note-Eingabe aufgerufen — ZP bei 0 Punkten automatisch vorauswählen */
            onScoreInput(examId) {
                const exam = this.allExams.find(e => e.id === examId);
                if (!exam || !exam.can_zp || exam.has_zp) return;
                const s = this.examScores[examId];
                if (s === 0 || s === '0') {
                    if (!this.selectedZpIds.includes(examId)) this.selectedZpIds.push(examId);
                }
            },

            /**
             * Mündliche ZP-Note (target_points) einer bereits angelegten ZP speichern.
             * Nutzt denselben Endpoint wie mpSaveTargetPoints() im Mindestpunkte-Modal.
             * Speichern erfolgt explizit (Button/Enter), nicht automatisch bei jedem
             * Tastendruck — siehe Begründung beim Modal-Pendant.
             *
             * Sport-Sonderfall: sendet zusätzlich die unveränderte Praxisnote aus
             * practicalGrades mit (kein eigenes Eingabefeld bei has_zp — Praxis-
             * und Schriftlich-Note bleiben hier reine Anzeige, analog zu allen
             * anderen Fächern). Damit überschreibt das PATCH den vorhandenen
             * practical_grade-Wert nicht versehentlich mit null. Siehe
             * MindestpunkteService::calcSportZpContribution().
             */
            async saveTargetPoints(exam) {
                if (!exam.pruefung_id) return;
                const val   = this.targetScores[exam.id];
                const score = (val !== '' && val !== null && val !== undefined) ? parseInt(val) : null;
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const payload = { target_points: score };
                if (exam.is_sport) {
                    const pg = this.practicalGrades[exam.id];
                    payload.practical_grade = (pg !== '' && pg !== null && pg !== undefined) ? parseInt(pg) : null;
                }
                const res = await fetch(`/zusatzpruefungen/${exam.pruefung_id}/points`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body:    JSON.stringify(payload),
                });
                // Die Antwort wird geprüft, seit der Server ablehnen kann
                // (Entscheidung 40). Vorher setzte diese Funktion den lokalen
                // Zustand in jedem Fall — eine abgewiesene Eingabe hätte
                // gespeichert ausgesehen, bis jemand die Seite neu lädt.
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    this.showToast(data.error || 'Die Note konnte nicht gespeichert werden.', true);
                    return;
                }
                exam.target_points = score;
                if (exam.is_sport) exam.practical_grade = payload.practical_grade;
                Alpine.store('zpPoints').update(exam.pruefung_id, 'target', score);
            },

            /**
             * Berechnet im Verbesserungsmodus die kleinste mündliche ZP-Note,
             * die nötig ist, um die Punktedifferenz aus dem Notenbescheid zu
             * erreichen. Formel analog MindestpunkteService::calcZpContribution
             * (Anlage 12 zu §52 GSO): contrib = round((2×schr.+mündl.)×4/3).
             * Ziel: contrib(written, oral) ≥ written×4 + Δ.
             */
            mpImprovementHint(exam) {
                const written = parseInt(this.examScores[exam.id]);
                const delta   = parseInt(this.improvementPointsNeeded);
                if (isNaN(written) || isNaN(delta)) {
                    return exam.subject + ': Note + Punktedifferenz eintragen';
                }
                const target = written * 4 + delta;
                for (let oral = 0; oral <= 15; oral++) {
                    const contrib = Math.round((2 * written + oral) * 4 / 3);
                    if (contrib >= target) {
                        return exam.subject + ': mind. ' + oral + ' Pkt. mündlich nötig';
                    }
                }
                return exam.subject + ': auch mit 15 Pkt. nicht erreichbar';
            },

            /** ZP-Checkbox an/abwählen */
            toggleZp(examId, checked) {
                if (checked && !this.selectedZpIds.includes(examId)) {
                    this.selectedZpIds.push(examId);
                } else if (!checked) {
                    this.selectedZpIds = this.selectedZpIds.filter(id => id !== examId);
                }
            },

            /** Löscht eine einzelne Zusatzprüfung direkt aus dem Hinzufügen-Formular */
            async destroyZp(pruefungId, subjectName) {
                if (!confirm(`Zusatzprüfung „${subjectName}" wirklich löschen?`)) return;
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/zusatzpruefungen/${pruefungId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                });
                if (res.ok) {
                    // exam lokal als nicht angelegt markieren → sofortiges UI-Update ohne Reload
                    const exam = this.allExams.find(e => e.pruefung_id === pruefungId);
                    if (exam) {
                        exam.has_zp      = false;
                        exam.pruefung_id = null;
                        exam.saved_score = null;
                    }
                    // Übersichtstabelle aktualisieren (enthält Server-seitig gerenderte Daten)
                    window.location.reload();
                } else {
                    this.showToast('Fehler beim Löschen der Zusatzprüfung', true);
                }
            },

            /** Legt Zusatzprüfungen an und speichert alle Noten (ein Request) */
            async storeSelected() {
                const newZpIds = this.selectedZpIds.filter(id => !this.allExams.find(e => e.id === id)?.has_zp);
                const existingZpIds = this.selectedZpIds.filter(id => this.allExams.find(e => e.id === id)?.has_zp);
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const scores = {};
                this.allExams.forEach(e => {
                    const s = this.examScores[e.id];
                    if (s !== '' && s !== null && s !== undefined) scores[e.id] = parseInt(s);
                });
                // Für neu anzulegende ZP-Fächer die schriftliche Note mitsenden
                newZpIds.forEach(id => {
                    const s = this.examScores[id];
                    if (s !== '' && s !== null && s !== undefined) scores[id] = parseInt(s);
                });
                const practicalGradesFiltered = Object.fromEntries(
                    Object.entries(this.practicalGrades)
                        .filter(([, v]) => v !== '' && v !== null && v !== undefined)
                        .map(([k, v]) => [k, parseInt(v)])
                );
                // Abbrechen, wenn weder eine neue ZP angelegt noch eine bestehende
                // aktualisiert wird (Button-Disabled sollte das bereits verhindern)
                if (!newZpIds.length && !existingZpIds.length) return;
                const ipn = this.improvementPointsNeeded;
                const res = await fetch('/zusatzpruefungen', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({
                        pruefling: this.selectedStudentId,
                        student_exam_ids: [...newZpIds, ...existingZpIds],
                        scores,
                        practical_grades: practicalGradesFiltered,
                        improvement_only: this.improvementOnly,
                        improvement_points_needed: (ipn !== '' && ipn !== null && ipn !== undefined) ? parseInt(ipn) : null,
                    }),
                });
                const data = await res.json();
                if (data.success) window.location.reload();
                // `error` kommt von der serverseitigen Regelprüfung
                // (Entscheidung 40: im Sport keine Theorienote ohne
                // Praxisnote), `message` von den älteren Fehlerpfaden.
                else this.showToast(data.error || data.message || 'Fehler beim Anlegen', true);
            },

            onDragStart(ev, id) {
                this.draggingId = id;
                ev.dataTransfer.effectAllowed = 'move';

                // Zellen sofort als blockiert (rot) oder frei (grün) markieren
                const pruefung = this.conflictMap?.pruefungen?.[id];
                if (!pruefung) return;
                this.$nextTick(() => {
                    document.querySelectorAll('.zp-drop-cell').forEach(cell => {
                        const dayId     = parseInt(cell.dataset.dayId);
                        const startTime = cell.dataset.startTime;
                        if (!dayId || !startTime) return;
                        const blocked = this.isBlockedCell(dayId, startTime, pruefung);
                        cell.classList.add(blocked ? 'pre-blocked' : 'pre-free');
                    });
                });
            },

            onDragOver(ev) {
                ev.preventDefault();
                // Blockierte Zellen: drag-over NICHT setzen (bleibt rot)
                if (!ev.currentTarget.classList.contains('pre-blocked')) {
                    ev.currentTarget.classList.add('drag-over');
                }
            },

            onDragLeave(ev) {
                ev.currentTarget.classList.remove('drag-over');
            },

            async onDrop(ev, dayId, dayRoomId, startTime) {
                ev.preventDefault();
                ev.currentTarget.classList.remove('drag-over');
                if (!this.draggingId) return;

                // Client-seitige Sicherheitsprüfung: blockierte Zelle ablehnen
                if (ev.currentTarget.classList.contains('pre-blocked')) {
                    this.showToast('Dieser Zeitslot ist nicht verfügbar.', true);
                    this.draggingId = null;
                    return;
                }

                // ── Reihenfolge-Prüfung ───────────────────────────────────
                // Wenn der Schüler mehrere ZPs hat, soll sort_order=1 den frühesten Tag bekommen.
                const draggedPruefung = this.conflictMap?.pruefungen?.[this.draggingId];
                const orderViolation  = this.checkSortOrderViolation(this.draggingId, dayId, draggedPruefung);
                if (orderViolation) {
                    this.showToast(orderViolation, true);
                    this.draggingId = null;
                    return;
                }

                const token = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/zusatzpruefungen/${this.draggingId}/place`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({
                        zusatzpruef_day_id: dayId,
                        zusatzpruef_day_room_id: dayRoomId,
                        start_time: startTime,
                    }),
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json();
                    this.showToast(data.error ?? 'Fehler beim Platzieren.', true);
                }
                this.draggingId = null;
            },

            async unplace(id) {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                await fetch(`/zusatzpruefungen/${id}/place`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': token },
                });
                window.location.reload();
            },

            async toggleAbsent(id) {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/zusatzpruefungen/${id}/absent`, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': token },
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    this.showToast('Fehler beim Aktualisieren.', true);
                }
            },

            openDrawer(data) {
                this.activePruefungId    = data.id;
                this.drawer.pruefungData = data;
                this.drawer.pruefer      = data.pruefer ? JSON.parse(JSON.stringify(data.pruefer)) : [];
                this.drawer.open         = true;
            },

            /** Lehrer für den Prüfer-Drawer – gefiltert nach Fakultas des Prüfungsfachs.
             *  Lehrer ohne Fachzuordnung (subject_ids leer) werden nicht angezeigt. */
            filteredTeachersForDrawer() {
                const subjectId = this.drawer.pruefungData?.subjectId;
                if (!subjectId) return this.allTeachers.filter(t => t.subject_ids.length > 0);
                return this.allTeachers.filter(
                    t => t.subject_ids.includes(subjectId)
                );
            },

            addPruefer() {
                this.drawer.pruefer.push({ teacher_id: '', role: 'gast' });
            },

            removePruefer(i) {
                this.drawer.pruefer.splice(i, 1);
            },

            async savePruefer() {
                const valid = this.drawer.pruefer.filter(p => p.teacher_id);
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/zusatzpruefungen/${this.activePruefungId}/pruefer`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ pruefer: valid }),
                });
                if (res.ok) { window.location.reload(); }
                else { this.showToast('Fehler beim Speichern.', true); }
            },

            get teacherOverviewFiltered() {
                if (!this.teacherOverviewSearch.trim()) return this.teacherOverviewData;
                const q = this.teacherOverviewSearch.trim().toLowerCase();
                return this.teacherOverviewData.filter(t =>
                    t.shortcode.toLowerCase().includes(q) ||
                    t.name.toLowerCase().includes(q) ||
                    t.entries.some(e =>
                        (e.student_name || '').toLowerCase().includes(q) ||
                        (e.subject || '').toLowerCase().includes(q)
                    )
                );
            },

            openPlanExportModal() {
                this.showPlanExportModal = true;
            },

            async openTeacherOverview() {
                this.showTeacherOverview = true;
                if (this.teacherOverviewData.length > 0) return;
                this.teacherOverviewLoading = true;
                this.teacherOverviewError = null;
                try {
                    const resp = await fetch('{{ route('zusatzpruefungen.teacher-overview') }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    const data = await resp.json();
                    this.teacherOverviewData = data.map(t => ({ ...t, _open: false }));
                } catch (err) {
                    this.teacherOverviewError = 'Fehler beim Laden der Daten: ' + err.message;
                } finally {
                    this.teacherOverviewLoading = false;
                }
            },

            init() {
                // Drag-Ende: pre-Markierungen aufräumen (auch bei Abbruch)
                document.addEventListener('dragend', () => {
                    document.querySelectorAll('.zp-drop-cell').forEach(cell => {
                        cell.classList.remove('pre-blocked', 'pre-free', 'drag-over');
                    });
                });

                // Auf-/Eingeklappt-Zustand des "Schüler hinzufügen & Übersicht"-Bereichs
                // im Browser merken (überlebt window.location.reload() nach dem Speichern)
                this.$watch('showAddForm', (val) => {
                    localStorage.setItem('zp-show-add-form', val ? '1' : '0');
                });
            },

            // ─── Conflict-Helpers ─────────────────────────────────────────
            timeToMinutes(t) {
                if (!t) return 0;
                const parts = t.split(':');
                return parseInt(parts[0]) * 60 + parseInt(parts[1]);
            },

            /** True, wenn dieser Slot für die gezogene Prüfung blockiert ist. */
            isBlockedCell(dayId, startTime, pruefung) {
                for (const placed of (this.conflictMap?.placed ?? [])) {
                    // Eigene aktuelle Position beim Verschieben nicht als Konflikt werten
                    if (placed.pruefung_id === this.draggingId) continue;
                    if (placed.day_id !== dayId) continue;

                    // Schüler-Konflikt: gleicher Schüler, gleicher Tag → ganzer Tag blockiert
                    if (placed.pruefling === pruefung.pruefling) return true;

                    // Prüfer-Konflikt: nur exakt gleiche Startzeit blockiert
                    // (zeitlich überlappende Slots werden server-seitig abgefangen)
                    if (placed.start_time === startTime) {
                        if (placed.teacher_ids.some(tid => pruefung.teacher_ids.includes(tid))) return true;
                    }
                }
                return false;
            },

            /**
             * Prüft ob das Platzieren von pruefungId auf dayId die Reihenfolge (sort_order) verletzt.
             * Regel: Eine Prüfung mit niedrigerem sort_order muss an einem früheren oder gleichen Tag liegen
             * als eine Prüfung desselben Schülers mit höherem sort_order.
             * Gibt null zurück wenn alles ok, sonst eine Fehlermeldung.
             */
            checkSortOrderViolation(pruefungId, targetDayId, draggedPruefung) {
                if (!draggedPruefung) return null;
                const draggedOrder = draggedPruefung.sort_order ?? 0;
                // Wenn sort_order = 0 für alle: keine manuelle Reihenfolge gesetzt → ok
                const allSameStudent = Object.values(this.conflictMap?.pruefungen ?? {})
                    .filter(p => p.pruefling === draggedPruefung.pruefling);
                const anyManual = allSameStudent.some(p => (p.sort_order ?? 0) > 0);
                if (!anyManual) return null;

                const targetDate = this.daysDateMap[targetDayId] ?? '';
                const placed = this.conflictMap?.placed ?? [];

                for (const other of placed) {
                    if (other.pruefung_id === pruefungId) continue; // sich selbst überspringen
                    const otherInfo = this.conflictMap?.pruefungen?.[other.pruefung_id];
                    if (!otherInfo || otherInfo.pruefling !== draggedPruefung.pruefling) continue;

                    const otherOrder = otherInfo.sort_order ?? 0;
                    const otherDate  = other.day_date ?? '';
                    if (!otherDate || !targetDate) continue;

                    // Gezogene Prüfung kommt VOR der anderen (niedrigerer sort_order):
                    // → Zieldatum darf nicht NACH dem Datum der anderen liegen
                    if (draggedOrder < otherOrder && targetDate > otherDate) {
                        return `Reihenfolge-Konflikt: Diese Prüfung (Fach ${draggedOrder}) sollte vor der bereits platzierten Prüfung (${otherDate}) stattfinden.`;
                    }
                    // Gezogene Prüfung kommt NACH der anderen (höherer sort_order):
                    // → Zieldatum darf nicht VOR dem Datum der anderen liegen
                    if (draggedOrder > otherOrder && targetDate < otherDate) {
                        return `Reihenfolge-Konflikt: Diese Prüfung (Fach ${draggedOrder}) sollte nach der bereits platzierten Prüfung (${otherDate}) stattfinden.`;
                    }
                }
                return null;
            },
        }));
    });

    // Inline-Editor für written_points / target_points
    document.addEventListener('alpine:init', () => {
    Alpine.data('pointsEditor', (pruefungId, field, initial, initialLocked = false) => {
        const storeField = field === 'written_points' ? 'written'
                         : field === 'target_points'  ? 'target'
                         : null;
        if (initialLocked) Alpine.store('zpPoints').setLocked(pruefungId, true);
        return {
            val: initial !== null ? String(initial) : '',
            get locked() { return Alpine.store('zpPoints').isLocked(pruefungId); },
            updateStore() {
                if (!storeField) return;
                const v = this.val !== '' ? parseInt(this.val) : null;
                Alpine.store('zpPoints').update(pruefungId, storeField, isNaN(v) ? null : v);
            },
            async save() {
                if (this.locked) return;
                this.updateStore();
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const body  = {};
                body[field] = this.val !== '' ? parseInt(this.val) : null;
                const res = await fetch(`/zusatzpruefungen/${pruefungId}/points`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body:    JSON.stringify(body),
                });
                // Diese Komponente steht ausserhalb der Übersicht und hat kein
                // eigenes showToast — die Meldung geht direkt an den globalen
                // Store. Der eingegebene Wert bleibt im Feld stehen: er ist
                // nicht falsch, es fehlt die Praxisnote daneben
                // (Entscheidung 40). Gespeichert ist er nicht.
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    Alpine.store('zpToast').message = data.error || 'Die Punktzahl konnte nicht gespeichert werden.';
                    Alpine.store('zpToast').error   = true;
                    Alpine.store('zpToast').visible = true;
                    setTimeout(() => { Alpine.store('zpToast').visible = false; }, 3500);
                }
            },
            async toggleLock() {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/zusatzpruefungen/${pruefungId}/lock`, {
                    method:  'PATCH',
                    headers: { 'X-CSRF-TOKEN': token },
                });
                if (res.ok) {
                    const data = await res.json();
                    Alpine.store('zpPoints').setLocked(pruefungId, data.locked);
                }
            },
        };
    });
    });

    // Inline-Editor für die Gesamtpunktzahl eines Schülers
    document.addEventListener('alpine:init', () => {
    // pruefling ist der Prüflingsschlüssel ("s<id>"). Das Feld wird nur für
    // Schüler gerendert — die Route trägt deshalb weiterhin die students-ID.
    Alpine.data('deficitEditor', (pruefling, initial) => ({
            val: initial !== null ? String(initial) : '',
            get projected() {
                const v     = parseInt(this.val);
                const delta = Alpine.store('zpPoints').getStudentDelta(pruefling);
                if (isNaN(v) || delta === null) return null;
                return v + delta;
            },
            get projectedIsPartial() {
                const ids = Alpine.store('zpPoints').studentExams[pruefling] || [];
                return ids.some(id => {
                    const e = Alpine.store('zpPoints').exams[id];
                    return !e || e.written === null || e.written === undefined || e.target === null || e.target === undefined;
                });
            },
            async save() {
                if (!String(pruefling).startsWith('s')) return;
                const token = document.querySelector('meta[name="csrf-token"]').content;
                await fetch(`/zusatzpruefungen/student/${String(pruefling).slice(1)}/deficit`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body:    JSON.stringify({ current_total_points: this.val !== '' ? parseInt(this.val) : null }),
                });
            },
    }));
    });

    document.addEventListener('alpine:init', () => {
    Alpine.data('studentSearch', () => ({
            search: '',
            open: false,
            {{-- Schüler und externe Bewerber gemeinsam; die id ist der
                 Prüflingsschlüssel ("s<id>" bzw. "e<id>"). --}}
            students: @json($prueflinge),
            get filtered() {
                if (!this.search) return this.students;
                const q = this.search.toLowerCase();
                return this.students.filter(s => s.name.toLowerCase().includes(q));
            },
            init() {
                window.addEventListener('student-name-prefill', (e) => {
                    this.search = e.detail.name;
                    this.open   = false;
                });
            },
            select(s) {
                this.search = s.name;
                this.open = false;
                window.dispatchEvent(new CustomEvent('student-selected', { detail: { id: s.id } }));
            },
            clear() {
                this.search = '';
                this.open = false;
                window.dispatchEvent(new CustomEvent('student-selected', { detail: { id: '' } }));
            },
    }));
    });
    </script>
    @endpush

</x-app-layout>
