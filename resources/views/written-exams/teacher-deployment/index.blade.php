<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Lehrereinsatz – Statistik') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"
             x-data="{
                search: '',
                sortBy: 'name',
                sortDir: 'asc',
                get rows() {
                    let data = @js($stats->values()->toArray());
                    // Filter
                    if (this.search.trim() !== '') {
                        const q = this.search.toLowerCase();
                        data = data.filter(r =>
                            (r.teacher.lastname  + ' ' + r.teacher.firstname + ' ' + r.teacher.shortcode)
                                .toLowerCase().includes(q)
                        );
                    }
                    // Sort
                    data = data.slice().sort((a, b) => {
                        let va, vb;
                        if (this.sortBy === 'name') {
                            va = a.teacher.lastname + ' ' + a.teacher.firstname;
                            vb = b.teacher.lastname + ' ' + b.teacher.firstname;
                        } else {
                            va = a[this.sortBy] ?? 0;
                            vb = b[this.sortBy] ?? 0;
                        }
                        if (va < vb) return this.sortDir === 'asc' ? -1 : 1;
                        if (va > vb) return this.sortDir === 'asc' ?  1 : -1;
                        return 0;
                    });
                    return data;
                },
                get totals() {
                    return this.rows.reduce((acc, r) => {
                        acc.written_courses     += r.written_courses;
                        acc.second_corrections  += r.second_corrections;
                        acc.oral_examiner       += r.oral_examiner;
                        acc.oral_assessor       += r.oral_assessor;
                        acc.supervisions        += r.supervisions;
                        acc.total               += r.total;
                        return acc;
                    }, { written_courses:0, second_corrections:0, oral_examiner:0, oral_assessor:0, supervisions:0, total:0 });
                },
                toggleSort(col) {
                    if (this.sortBy === col) {
                        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortBy  = col;
                        this.sortDir = 'asc';
                    }
                },
                exportUrl(type) {
                    const p = new URLSearchParams({ search: this.search, sort: this.sortBy, dir: this.sortDir });
                    const base = type === 'pdf'
                        ? '{{ route('written-exams.teacher-deployment.export-pdf') }}'
                        : '{{ route('written-exams.teacher-deployment.export-excel') }}';
                    return base + '?' + p.toString();
                }
             }">

            {{-- Toolbar: Suche + Export --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-4">
                <div class="p-4 flex flex-wrap items-center gap-3">
                    {{-- Suche --}}
                    <div class="flex-1 min-w-48">
                        <input x-model="search" type="text" placeholder="Lehrkraft suchen…"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2"/>
                    </div>
                    {{-- Export --}}
                    <a :href="exportUrl('pdf')" target="_blank"
                       class="inline-flex items-center px-4 py-2 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 text-sm font-medium rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        PDF
                    </a>
                    <a :href="exportUrl('excel')"
                       class="inline-flex items-center px-4 py-2 bg-green-100 hover:bg-green-200 dark:bg-green-900/30 dark:hover:bg-green-900/50 text-green-700 dark:text-green-300 text-sm font-medium rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Excel
                    </a>
                    {{-- Treffer-Zähler --}}
                    <span class="text-sm text-gray-500 dark:text-gray-400" x-text="rows.length + ' Lehrkraft/Lehrkräfte'"></span>
                </div>
            </div>

            {{-- Tabelle --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 select-none">
                            <tr>
                                @php
                                    $cols = [
                                        ['key' => 'name',         'label' => 'Lehrkraft',             'align' => 'left'],
                                        ['key' => 'written_courses',     'label' => 'Schriftl. Erstkorr.',   'align' => 'center'],
                                        ['key' => 'second_corrections',  'label' => 'Schriftl. Zweitkorr.',  'align' => 'center'],
                                        ['key' => 'oral_examiner',       'label' => 'Koll. Prüfer',          'align' => 'center'],
                                        ['key' => 'oral_assessor',       'label' => 'Koll. Beisitzer',       'align' => 'center'],
                                        ['key' => 'supervisions',        'label' => 'Aufsichten',            'align' => 'center'],
                                        ['key' => 'total',               'label' => 'Gesamt',                'align' => 'center'],
                                    ];
                                @endphp
                                @foreach($cols as $col)
                                <th @click="toggleSort('{{ $col['key'] }}')"
                                    class="px-4 py-3 text-{{ $col['align'] }} text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 {{ $col['align'] === 'center' ? 'justify-center w-full' : '' }}">
                                        {{ $col['label'] }}
                                        <span x-show="sortBy === '{{ $col['key'] }}'" class="text-indigo-500">
                                            <span x-show="sortDir === 'asc'">↑</span>
                                            <span x-show="sortDir === 'desc'">↓</span>
                                        </span>
                                    </span>
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <template x-for="(row, idx) in rows" :key="row.teacher.id">
                                <tr :class="idx % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/60 dark:bg-gray-700/30'"
                                    class="hover:bg-indigo-50/40 dark:hover:bg-indigo-900/20 transition-colors">

                                    {{-- Lehrkraft --}}
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="font-medium text-gray-900 dark:text-gray-100"
                                              x-text="row.teacher.lastname + ', ' + row.teacher.firstname"></span>
                                        <span class="ml-2 inline-block px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-xs rounded font-mono"
                                              x-text="row.teacher.shortcode"></span>
                                    </td>
                                    {{-- Schriftl. Erstkorrektur --}}
                                    <td class="px-4 py-3 text-center">
                                        <span :class="row.written_courses > 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-400 dark:text-gray-600'"
                                              class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                                              x-text="row.written_courses"></span>
                                    </td>
                                    {{-- Schriftl. Zweitkorrektur --}}
                                    <td class="px-4 py-3 text-center">
                                        <span :class="row.second_corrections > 0 ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300' : 'text-gray-400 dark:text-gray-600'"
                                              class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                                              x-text="row.second_corrections"></span>
                                    </td>
                                    {{-- Kolloquium Prüfer --}}
                                    <td class="px-4 py-3 text-center">
                                        <span :class="row.oral_examiner > 0 ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : 'text-gray-400 dark:text-gray-600'"
                                              class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                                              x-text="row.oral_examiner"></span>
                                    </td>
                                    {{-- Kolloquium Beisitzer --}}
                                    <td class="px-4 py-3 text-center">
                                        <span :class="row.oral_assessor > 0 ? 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300' : 'text-gray-400 dark:text-gray-600'"
                                              class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                                              x-text="row.oral_assessor"></span>
                                    </td>
                                    {{-- Aufsichten --}}
                                    <td class="px-4 py-3 text-center">
                                        <span :class="row.supervisions > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : 'text-gray-400 dark:text-gray-600'"
                                              class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                                              x-text="row.supervisions"></span>
                                    </td>
                                    {{-- Gesamt --}}
                                    <td class="px-4 py-3 text-center">
                                        <span :class="row.total > 0 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-600'"
                                              class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold shadow-sm"
                                              x-text="row.total"></span>
                                    </td>
                                </tr>
                            </template>

                            {{-- Leer-Meldung --}}
                            <tr x-show="rows.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500 italic">
                                    Keine Lehrkräfte gefunden.
                                </td>
                            </tr>
                        </tbody>
                        {{-- Summenzeile --}}
                        <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold text-sm">
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    Gesamt (<span x-text="rows.length"></span>)
                                </td>
                                <td class="px-4 py-3 text-center text-gray-800 dark:text-gray-200" x-text="totals.written_courses"></td>
                                <td class="px-4 py-3 text-center text-gray-800 dark:text-gray-200" x-text="totals.second_corrections"></td>
                                <td class="px-4 py-3 text-center text-gray-800 dark:text-gray-200" x-text="totals.oral_examiner"></td>
                                <td class="px-4 py-3 text-center text-gray-800 dark:text-gray-200" x-text="totals.oral_assessor"></td>
                                <td class="px-4 py-3 text-center text-gray-800 dark:text-gray-200" x-text="totals.supervisions"></td>
                                <td class="px-4 py-3 text-center text-indigo-700 dark:text-indigo-300 font-bold" x-text="totals.total"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>{{-- /max-w --}}
    </div>{{-- /py-12 --}}
</x-app-layout>
