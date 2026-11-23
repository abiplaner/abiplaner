<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Niederschriften exportieren
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $examPeriod->name }} – Prüfungen auswählen</p>
            </div>
            <a href="{{ route('planning.index') }}"
               class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Zurück zur Planung
            </a>
        </div>
    </x-slot>

    @php
        // Daten für Alpine.js vorbereiten
        $alpineRows = $assignments->map(function ($a) {
            $se       = $a->studentExam;
            $student  = $se?->student;
            $course   = $se?->course;
            $subject  = $course?->subject;
            $examiner = $se?->examiner;
            $assessor = $se?->assessor;
            $slot     = $a->timeSlot;

            $sN = mb_strtolower($subject?->name ?? '');
            $sC = mb_strtoupper($subject?->code ?? '');
            if (str_contains($sN,'englisch') || $sC==='E')        { $formType = 'sprachen'; $formLabel = 'Sprachen (E)'; }
            elseif (str_contains($sN,'französisch') || str_contains($sN,'franzosisch') || $sC==='F') { $formType = 'sprachen'; $formLabel = 'Sprachen (F)'; }
            elseif (str_contains($sN,'italienisch') || $sC==='IT'||$sC==='I')   { $formType = 'sprachen'; $formLabel = 'Sprachen (It)'; }
            else   { $formType = 'standard'; $formLabel = 'Standard'; }

            return [
                'id'         => $a->id,
                'woche'      => (string)($a->exam_week ?? ''),
                'datum'      => $slot?->date?->format('Y-m-d') ?? '',
                'datumDisp'  => $slot?->date ? $slot->date->translatedFormat('D, d.m.') : '–',
                'zeit'       => $slot ? substr((string)$slot->exam_start_time,0,5).' – '.substr((string)$slot->exam_end_time,0,5) : '–',
                'nachname'   => $student?->lastname ?? '',
                'vorname'    => $student?->firstname ?? '',
                'searchKey'  => mb_strtolower(($student?->lastname ?? '').' '.($student?->firstname ?? '')),
                'fach'       => $subject?->name ?? '–',
                'kurs'       => $course?->course_code ?? '',
                'kursName'   => $course?->name ?? '',
                'level'      => $course?->level === 'erhoeht' ? 'eA' : ($course?->level === 'grundlegend' ? 'gA' : ''),
                'examinerId' => (string)($examiner?->id ?? ''),
                'examiner'   => $examiner ? ($examiner->genderedTitle().$examiner->full_name) : '–',
                'assessor'   => $assessor ? ($assessor->genderedTitle().$assessor->full_name) : '–',
                'formType'   => $formType,
                'formLabel'  => $formLabel,
            ];
        })->values()->toArray();

        // Eindeutige Filterwerte
        $allDaten    = $assignments->map(fn($a)=>$a->timeSlot?->date)->filter()->unique()->sort();
        $allSubjects = $assignments->map(fn($a)=>$a->studentExam?->course?->subject)->filter()->unique('id')->sortBy('name');
        $allExaminer = $assignments->map(fn($a)=>$a->studentExam?->examiner)->filter()->unique('id')->sortBy('lastname');
        $allKurse    = $assignments->map(fn($a)=>$a->studentExam?->course?->course_code)->filter()->unique()->sort()->values();
    @endphp

    <div class="py-6" x-data="niederschriftAuswahl({{ json_encode($alpineRows) }})">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Filter-Leiste --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">

                    {{-- Suche Schülername --}}
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Schüler/in suchen</label>
                        <div class="relative">
                            <svg class="absolute left-2.5 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" x-model="search" placeholder="Nachname oder Vorname…"
                                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>

                    {{-- Woche --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Woche</label>
                        <select x-model="filterWoche" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Alle</option>
                            <option value="1">Woche 1</option>
                            <option value="2">Woche 2</option>
                        </select>
                    </div>

                    {{-- Datum --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
                        <select x-model="filterDatum" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Alle Tage</option>
                            @foreach($allDaten as $date)
                            <option value="{{ $date->format('Y-m-d') }}">{{ $date->translatedFormat('D, d.m.Y') }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Fach --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fach</label>
                        <select x-model="filterFach" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Alle Fächer</option>
                            @foreach($allSubjects as $subj)
                            <option value="{{ $subj->name }}">{{ $subj->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Kurs --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kurs</label>
                        <select x-model="filterKurs" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Alle Kurse</option>
                            @foreach($allKurse as $kuerzel)
                            <option value="{{ $kuerzel }}">{{ $kuerzel }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                {{-- Prüfer-Filter + Aktions-Buttons --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mt-3 items-end">
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Prüfer/in</label>
                        <select x-model="filterPruefer" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Alle Prüfer</option>
                            @foreach($allExaminer as $t)
                            <option value="{{ $t->id }}">{{ $t->genderedTitle() }}{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2 lg:col-span-3">
                        <button @click="selectVisible()" type="button"
                                class="flex-1 py-2 px-3 text-sm bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-md font-medium">
                            Sichtbare auswählen
                        </button>
                        <button @click="deselectAll()" type="button"
                                class="flex-1 py-2 px-3 text-sm bg-gray-50 hover:bg-gray-100 text-gray-600 border border-gray-200 rounded-md font-medium">
                            Alle abwählen
                        </button>
                        <button @click="resetFilter()" type="button" title="Filter zurücksetzen"
                                class="py-2 px-3 text-sm bg-gray-50 hover:bg-gray-100 text-gray-500 border border-gray-200 rounded-md">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Ergebnis + Export-Button --}}
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm text-gray-600">
                    <span x-text="visibleRows.length"></span> Prüfungen sichtbar ·
                    <span class="font-medium text-emerald-700" x-text="selectedIds.length"></span> ausgewählt
                </div>
                <button @click="exportPdf()" type="button"
                        :disabled="selectedIds.length === 0"
                        :class="selectedIds.length > 0
                            ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm cursor-pointer'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-md text-sm font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                    </svg>
                    Niederschriften exportieren
                    <template x-if="selectedIds.length > 0">
                        <span class="bg-white/25 text-xs rounded-full px-2 py-0.5 font-bold" x-text="selectedIds.length + '\u00a0Stk.'"></span>
                    </template>
                </button>
            </div>

            {{-- Tabelle --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-10 px-3 py-3 text-center">
                                <input type="checkbox"
                                       :checked="visibleRows.length > 0 && visibleRows.every(r => selectedIds.includes(r.id))"
                                       :indeterminate="selectedIds.length > 0 && !visibleRows.every(r => selectedIds.includes(r.id))"
                                       @change="toggleAllVisible($event)"
                                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('datum')">
                                <span class="inline-flex items-center gap-1">Datum <span x-text="sortIcon('datum')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('zeit')">
                                <span class="inline-flex items-center gap-1">Zeit <span x-text="sortIcon('zeit')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('woche')">
                                <span class="inline-flex items-center gap-1">W <span x-text="sortIcon('woche')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('nachname')">
                                <span class="inline-flex items-center gap-1">Schüler/in <span x-text="sortIcon('nachname')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('fach')">
                                <span class="inline-flex items-center gap-1">Fach <span x-text="sortIcon('fach')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('kurs')">
                                <span class="inline-flex items-center gap-1">Kurs <span x-text="sortIcon('kurs')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('examiner')">
                                <span class="inline-flex items-center gap-1">Prüfer/in <span x-text="sortIcon('examiner')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100" @click="sortColumn('assessor')">
                                <span class="inline-flex items-center gap-1">Beisitzer/in <span x-text="sortIcon('assessor')" class="text-gray-400"></span></span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Formular</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="row in visibleRows" :key="row.id">
                            <tr :class="selectedIds.includes(row.id) ? 'bg-emerald-50' : 'hover:bg-gray-50'"
                                class="transition-colors cursor-pointer"
                                @click="toggleId(row.id)">
                                <td class="px-3 py-2.5 text-center" @click.stop>
                                    <input type="checkbox"
                                           :checked="selectedIds.includes(row.id)"
                                           @change="toggleId(row.id)"
                                           class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-gray-700 font-medium" x-text="row.datumDisp"></td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-gray-600 font-mono text-xs" x-text="row.zeit"></td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold"
                                          :class="row.woche === '1' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700'"
                                          x-text="row.woche"></span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="font-semibold text-gray-900" x-text="row.nachname"></span>,
                                    <span class="text-gray-600" x-text="row.vorname"></span>
                                </td>
                                <td class="px-3 py-2.5 text-gray-700">
                                    <span x-text="row.fach"></span>
                                    <span x-show="row.level" class="text-xs text-gray-400 ml-0.5" x-text="'(' + row.level + ')'"></span>
                                </td>
                                <td class="px-3 py-2.5 text-gray-700">
                                    <span class="font-mono text-xs font-medium text-gray-800" x-text="row.kurs"></span>
                                    <span x-show="row.kursName" class="block text-xs text-gray-400 leading-tight" x-text="row.kursName"></span>
                                </td>
                                <td class="px-3 py-2.5 text-gray-700" x-text="row.examiner"></td>
                                <td class="px-3 py-2.5 text-gray-500" x-text="row.assessor"></td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                          :class="row.formType === 'sprachen' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'"
                                          x-text="row.formLabel"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                {{-- Leer-Zustand --}}
                <div x-show="visibleRows.length === 0" class="text-center py-12 text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Keine Prüfungen gefunden – bitte Filter anpassen
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script @cspNonce>
    function niederschriftAuswahl(allRows) {
        return {
            allRows: allRows,
            search: '',
            filterWoche: '',
            filterDatum: '',
            filterFach: '',
            filterKurs: '',
            filterPruefer: '',
            selectedIds: [],
            sortBy: 'datum',
            sortDir: 'asc',

            get visibleRows() {
                const s = this.search.toLowerCase().trim();
                let rows = this.allRows.filter(row => {
                    if (s && !row.searchKey.includes(s)) return false;
                    if (this.filterWoche && row.woche !== this.filterWoche) return false;
                    if (this.filterDatum && row.datum !== this.filterDatum) return false;
                    if (this.filterFach && row.fach !== this.filterFach) return false;
                    if (this.filterKurs && row.kurs !== this.filterKurs) return false;
                    if (this.filterPruefer && row.examinerId !== this.filterPruefer) return false;
                    return true;
                });

                const col = this.sortBy;
                const dir = this.sortDir === 'asc' ? 1 : -1;
                rows = [...rows].sort((a, b) => {
                    let av = a[col] ?? '';
                    let bv = b[col] ?? '';
                    // Datum+Zeit zusammen sortieren
                    if (col === 'datum') {
                        av = a.datum + a.zeit;
                        bv = b.datum + b.zeit;
                    }
                    if (col === 'nachname') {
                        av = a.nachname + a.vorname;
                        bv = b.nachname + b.vorname;
                    }
                    if (av < bv) return -1 * dir;
                    if (av > bv) return 1 * dir;
                    return 0;
                });
                return rows;
            },

            sortColumn(col) {
                if (this.sortBy === col) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = col;
                    this.sortDir = 'asc';
                }
            },

            sortIcon(col) {
                if (this.sortBy !== col) return '⇅';
                return this.sortDir === 'asc' ? '↑' : '↓';
            },

            toggleId(id) {
                const idx = this.selectedIds.indexOf(id);
                if (idx === -1) {
                    this.selectedIds.push(id);
                } else {
                    this.selectedIds.splice(idx, 1);
                }
            },

            toggleAllVisible(event) {
                if (event.target.checked) {
                    this.selectVisible();
                } else {
                    const visibleIds = this.visibleRows.map(r => r.id);
                    this.selectedIds = this.selectedIds.filter(id => !visibleIds.includes(id));
                }
            },

            selectVisible() {
                this.visibleRows.forEach(row => {
                    if (!this.selectedIds.includes(row.id)) {
                        this.selectedIds.push(row.id);
                    }
                });
            },

            deselectAll() {
                this.selectedIds = [];
            },

            resetFilter() {
                this.search = '';
                this.filterWoche = '';
                this.filterDatum = '';
                this.filterFach = '';
                this.filterKurs = '';
                this.filterPruefer = '';
            },

            exportPdf() {
                if (this.selectedIds.length === 0) return;
                const url = '{{ route('planning.export-niederschrift-pdf', $examPeriod) }}'
                    + '?ids=' + this.selectedIds.join(',');
                window.open(url, '_blank');
            },
        };
    }
    </script>
    @endpush

</x-app-layout>
