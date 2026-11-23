<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Absenzen
            </h2>
            <div class="space-x-2">
                <a href="{{ route('school-settings.index') }}#absenzen"
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    Schwellenwerte
                </a>
                @unlessarchive
                <a href="{{ route('absences.import.form') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    CSV importieren
                </a>
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">


            {{-- Banner: letzter Import --}}
            @if (!$lastImport)
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 text-sm text-red-800">
                    Es wurde noch nie ein Absenzen-Export importiert.
                    <a href="{{ route('absences.import.form') }}" class="underline font-medium">Jetzt importieren</a>
                </div>
            @else
                @php $isStale = $daysSinceImport >= 7; @endphp
                <div class="mb-6 border-l-4 p-4 text-sm {{ $isStale ? 'bg-amber-50 border-amber-400 text-amber-800' : 'bg-gray-50 border-gray-300 text-gray-700' }}">
                    <div>
                        Letzter Import: {{ $lastImport->created_at->format('d.m.Y H:i') }}
                        ({{ $daysSinceImport === 0 ? 'heute' : $daysSinceImport . ' Tag(e) her' }})
                        @if ($isStale)
                            — Daten könnten veraltet sein.
                            <a href="{{ route('absences.import.form') }}" class="underline font-medium">Aktualisieren</a>
                        @endif
                    </div>
                    @if (!empty($lastImportsByGrade['13']) || !empty($lastImportsByGrade['12']))
                        <div class="mt-1.5 flex flex-wrap gap-3 text-xs text-gray-600">
                            @foreach (['13', '12'] as $g)
                                @if (!empty($lastImportsByGrade[$g]))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-white border border-gray-200">
                                        <strong class="mr-1">Q{{ $g }}:</strong>
                                        {{ $lastImportsByGrade[$g]->created_at->format('d.m.Y H:i') }}
                                        ({{ $lastImportsByGrade[$g]->original_filename }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-amber-100 text-amber-800">
                                        <strong class="mr-1">Q{{ $g }}:</strong> noch kein Import
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div x-data="absenceTable(@js($tableRows))">

                {{-- Jahrgangsstufe --}}
                <div class="mb-6 flex gap-2">
                    <button type="button" @click="setGrade('13')"
                            :class="gradeTab === '13' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-4 py-2 rounded-md text-sm font-medium">
                        Q13 (<span x-text="rows.filter(r => r.grade === '13').length"></span>)
                    </button>
                    <button type="button" @click="setGrade('12')"
                            :class="gradeTab === '12' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-4 py-2 rounded-md text-sm font-medium">
                        Q12 (<span x-text="rows.filter(r => r.grade === '12').length"></span>)
                    </button>
                </div>

                {{-- Aktion nötig --}}
                <div class="mb-8" x-show="needsAction().length > 0">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-700">Aktion nötig</h3>
                        <a :href="'{{ route('absences.export.action-needed-pdf') }}?grade=' + gradeTab"
                           class="text-sm text-blue-600 hover:underline">
                            Als PDF exportieren
                        </a>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-red-200">
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y">
                                <template x-for="row in needsAction()" :key="'action-' + row.url">
                                    <tr>
                                        <td class="py-2 px-4 font-medium" x-text="row.name"></td>
                                        <td class="py-2 px-4">
                                            <span class="inline-flex flex-wrap gap-1">
                                                <template x-for="reason in reasons(row)" :key="reason.label">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="reason.color" x-text="reason.label"></span>
                                                </template>
                                            </span>
                                        </td>
                                        <td class="py-2 px-4 text-right">
                                            <a :href="detailUrl(row)" class="text-blue-600 hover:underline">Details</a>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Suche --}}
                <div class="mb-4 flex items-center justify-between">
                    <input type="text" x-model="search" placeholder="Suche nach Name oder Klasse …"
                           class="w-full max-w-sm border-gray-300 rounded-md shadow-sm text-sm">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" x-model="onlyAction" class="rounded border-gray-300">
                        Nur „Aktion nötig"
                    </label>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-gray-500 select-none">
                                <th class="py-3 px-4 cursor-pointer hover:text-gray-700" @click="sortBy('name')">
                                    Schüler <span x-text="sortIndicator('name')"></span>
                                </th>
                                <th class="py-3 px-4 cursor-pointer hover:text-gray-700" @click="sortBy('class_name')">
                                    Klasse <span x-text="sortIndicator('class_name')"></span>
                                </th>
                                <th class="py-3 px-4 text-right cursor-pointer hover:text-gray-700" @click="sortBy('krank_days')">
                                    Krank (Tage) <span x-text="sortIndicator('krank_days')"></span>
                                </th>
                                <th class="py-3 px-4 text-right cursor-pointer hover:text-gray-700" @click="sortBy('krank_percent')">
                                    Krank (%) <span x-text="sortIndicator('krank_percent')"></span>
                                </th>
                                <th class="py-3 px-4 text-right cursor-pointer hover:text-gray-700" @click="sortBy('unentschuldigt_count')">
                                    Unentschuldigt <span x-text="sortIndicator('unentschuldigt_count')"></span>
                                </th>
                                <th class="py-3 px-4 text-right cursor-pointer hover:text-gray-700" @click="sortBy('verspaetet_count')">
                                    Verspätungen <span x-text="sortIndicator('verspaetet_count')"></span>
                                </th>
                                <th class="py-3 px-4 text-center cursor-pointer hover:text-gray-700" @click="sortBy('open_attest_count')">
                                    Attest <span x-text="sortIndicator('open_attest_count')"></span>
                                </th>
                                <th class="py-3 px-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="row in filteredSorted()" :key="row.url">
                                <tr>
                                    <td class="py-2 px-4">
                                        <span x-text="row.name"></span>
                                        <span x-show="row.vorjahr_auffaellig"
                                              class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 cursor-help align-middle"
                                              :title="row.vorjahr_title">Vorjahr</span>
                                    </td>
                                    <td class="py-2 px-4" x-text="row.class_name"></td>
                                    <td class="py-2 px-4 text-right" :class="row.auffaellig_krankheit ? 'text-red-700 font-semibold' : ''" x-text="row.krank_days"></td>
                                    <td class="py-2 px-4 text-right" :class="row.auffaellig_krankheit ? 'text-red-700 font-semibold' : ''"
                                        x-text="row.krank_percent !== null ? row.krank_percent.toLocaleString('de-DE', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + '%' : '—'"></td>
                                    <td class="py-2 px-4 text-right" :class="row.auffaellig_unentschuldigt ? 'text-red-700 font-semibold' : ''" x-text="row.unentschuldigt_count"></td>
                                    <td class="py-2 px-4 text-right" :class="row.auffaellig_verspaetung ? 'text-red-700 font-semibold' : ''" x-text="row.verspaetet_count"></td>
                                    <td class="py-2 px-4 text-center">
                                        <span x-show="row.open_attest_count > 0"
                                              :class="row.open_attest_overdue ? 'bg-red-500 text-white' : 'bg-amber-400 text-white'"
                                              :title="row.open_attest_overdue ? row.open_attest_count + ' Attest(e) ausstehend, Frist überschritten' : row.open_attest_count + ' Attest(e) ausstehend'"
                                              class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold"
                                              x-text="row.open_attest_count"></span>
                                        <span x-show="row.attestpflicht_active" class="inline-block w-2 h-2 rounded-full bg-gray-400 ml-1 align-middle"
                                              :title="'Attestpflicht verhängt seit ' + row.attestpflicht_since"></span>
                                        <span x-show="row.open_attest_count === 0 && !row.attestpflicht_active" class="text-gray-300">—</span>
                                    </td>
                                    <td class="py-2 px-4 text-right">
                                        <a :href="detailUrl(row)" class="text-blue-600 hover:underline">Details</a>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredSorted().length === 0">
                                <td colspan="8" class="py-6 px-4 text-center text-gray-500">
                                    Keine Treffer.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500">
                Rot markierte Zahlen liegen über den hinterlegten Schwellenwerten. Die runde Marke in der
                Attest-Spalte zeigt die Anzahl ausstehender Atteste (rot = Frist überschritten, gelb =
                noch innerhalb der 10-Tage-Frist), der kleine graue Punkt zeigt eine aktiv verhängte
                Attestpflicht (Modul 8.3) an — unabhängig voneinander. Zähler-Spalten gelten seit dem
                letzten Reset des jeweiligen Aspekts (dokumentierte Maßnahme, siehe Schüler-Detailseite)
                bzw. seit Beginn der Datenerfassung. Der gelbe „Vorjahr"-Badge neben dem Namen erscheint,
                wenn die per Schuljahreswechsel übernommenen Vorjahres-Kennzahlen über den Schwellenwerten
                lagen (Details per Mauszeiger; reiner Hinweis, fließt nicht in „Aktion nötig" ein).
                Reine Kennzahlen, keine automatische Bewertung.
            </p>
        </div>
    </div>

    <script @cspNonce>
        function absenceTable(rows) {
            return {
                rows: rows,
                search: '',
                onlyAction: false,
                gradeTab: new URLSearchParams(window.location.search).get('grade') || '13',
                sortKey: 'name',
                sortDir: 1,
                setGrade(grade) {
                    this.gradeTab = grade;
                    const url = new URL(window.location.href);
                    url.searchParams.set('grade', grade);
                    window.history.replaceState({}, '', url);
                },
                detailUrl(row) {
                    return row.url + '?grade=' + this.gradeTab;
                },
                sortBy(key) {
                    if (this.sortKey === key) {
                        this.sortDir *= -1;
                    } else {
                        this.sortKey = key;
                        this.sortDir = 1;
                    }
                },
                sortIndicator(key) {
                    if (this.sortKey !== key) return '';
                    return this.sortDir === 1 ? '▲' : '▼';
                },
                isActionNeeded(row) {
                    return row.auffaellig_krankheit || row.auffaellig_unentschuldigt ||
                           row.auffaellig_verspaetung || row.open_attest_overdue;
                },
                reasons(row) {
                    const r = [];
                    if (row.auffaellig_krankheit) {
                        const pct = row.krank_percent !== null
                            ? ' (' + row.krank_percent.toLocaleString('de-DE', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' %)'
                            : '';
                        r.push({ label: 'Krankheit: ' + row.krank_days + ' Tage' + pct, color: 'bg-orange-100 text-orange-800' });
                    }
                    if (row.auffaellig_unentschuldigt) {
                        r.push({ label: 'Unentschuldigt: ' + row.unentschuldigt_count + '×', color: 'bg-purple-100 text-purple-800' });
                    }
                    if (row.auffaellig_verspaetung) {
                        r.push({ label: 'Verspätung: ' + row.verspaetet_count + '×', color: 'bg-blue-100 text-blue-800' });
                    }
                    if (row.open_attest_overdue) {
                        r.push({ label: 'Attest überfällig (' + row.open_attest_count + ' offen)', color: 'bg-red-100 text-red-800' });
                    }
                    return r;
                },
                needsAction() {
                    return this.rows.filter(r => r.grade === this.gradeTab && this.isActionNeeded(r));
                },
                filteredSorted() {
                    const term = this.search.trim().toLowerCase();
                    let result = this.rows.filter(r => r.grade === this.gradeTab);
                    if (this.onlyAction) {
                        result = result.filter(r => this.isActionNeeded(r));
                    }
                    if (term !== '') {
                        result = result.filter(r =>
                            r.name.toLowerCase().includes(term) ||
                            (r.class_name || '').toLowerCase().includes(term)
                        );
                    }
                    const key = this.sortKey;
                    const dir = this.sortDir;
                    return [...result].sort((a, b) => {
                        let av = a[key];
                        let bv = b[key];
                        if (av === null) av = -Infinity;
                        if (bv === null) bv = -Infinity;
                        if (typeof av === 'boolean') { av = av ? 1 : 0; bv = bv ? 1 : 0; }
                        if (typeof av === 'string') {
                            return av.localeCompare(bv, 'de') * dir;
                        }
                        return (av - bv) * dir;
                    });
                }
            };
        }
    </script>
</x-app-layout>
