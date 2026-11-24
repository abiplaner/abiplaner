<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="{{ route('students.index', ['stufe' => $stufe]) }}"
                   class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Fächerübersicht Q{{ $stufe }} – {{ $examPeriod->name }}
                </h2>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('students.subjects.export.pdf', ['stufe' => $stufe]) }}"
                   class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    PDF Export
                </a>
                <a href="{{ route('students.subjects.export.excel', ['stufe' => $stufe]) }}"
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Excel Export
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <x-stufen-umschalter route="students.subjects" :stufe="$stufe" :anzahl="$anzahlJeStufe" />

            @if($subjectStats->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                        <p>Keine Prüfungsdaten vorhanden.</p>
                    </div>
                </div>
            @else

                {{-- Plausibilitätsbox --}}
                @php
                    $allOk = $plausibility['written_ok'] && $plausibility['oral_ok'];
                @endphp
                <div class="mb-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                @if($allOk)
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="text-green-700 dark:text-green-400">Plausibilität</span>
                                @else
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                    <span class="text-red-700 dark:text-red-400">Plausibilität – Abweichung festgestellt</span>
                                @endif
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $plausibility['student_count'] }} Schüler × 3 schriftl. / 2 mündl.
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            {{-- Schriftlich --}}
                            <div class="rounded-lg px-4 py-3 flex items-center justify-between
                                {{ $plausibility['written_ok']
                                    ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'
                                    : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                                <div>
                                    <p class="text-xs font-medium {{ $plausibility['written_ok'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                        Schriftliche Prüfungen
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Erwartet: {{ $plausibility['written_expected'] }} &nbsp;|&nbsp; Vorhanden: {{ $plausibility['written_actual'] }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    @if($plausibility['written_ok'])
                                        <span class="text-xl font-bold text-green-600 dark:text-green-400">✓</span>
                                    @else
                                        <span class="text-sm font-bold {{ $plausibility['written_diff'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $plausibility['written_diff'] > 0 ? '+' : '' }}{{ $plausibility['written_diff'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            {{-- Mündlich --}}
                            <div class="rounded-lg px-4 py-3 flex items-center justify-between
                                {{ $plausibility['oral_ok']
                                    ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'
                                    : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                                <div>
                                    <p class="text-xs font-medium {{ $plausibility['oral_ok'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                        Mündliche Prüfungen
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Erwartet: {{ $plausibility['oral_expected'] }} &nbsp;|&nbsp; Vorhanden: {{ $plausibility['oral_actual'] }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    @if($plausibility['oral_ok'])
                                        <span class="text-xl font-bold text-green-600 dark:text-green-400">✓</span>
                                    @else
                                        <span class="text-sm font-bold {{ $plausibility['oral_diff'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $plausibility['oral_diff'] > 0 ? '+' : '' }}{{ $plausibility['oral_diff'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" rowspan="2">
                                            Fach
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-l border-gray-300 dark:border-gray-600" rowspan="2">
                                            Gesamt
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 tracking-wider border-l border-gray-300 dark:border-gray-600" colspan="3">
                                            Schriftl.
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 tracking-wider border-l border-gray-300 dark:border-gray-600" colspan="3">
                                            Mündl.
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 tracking-wider border-l border-gray-300 dark:border-gray-600">
                                            gA
                                        </th>
                                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-amber-600 dark:text-amber-400 tracking-wider">
                                            eA
                                        </th>
                                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-200 tracking-wider font-semibold">
                                            Ges.
                                        </th>
                                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 tracking-wider border-l border-gray-300 dark:border-gray-600">
                                            gA
                                        </th>
                                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-amber-600 dark:text-amber-400 tracking-wider">
                                            eA
                                        </th>
                                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-200 tracking-wider font-semibold">
                                            Ges.
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($subjectStats as $stat)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100 font-medium">
                                                {{ $stat['subject_name'] }}
                                            </td>
                                            <td class="px-4 py-3 text-center border-l border-gray-200 dark:border-gray-700 font-bold text-gray-700 dark:text-gray-300">
                                                {{ $stat['total'] }}
                                            </td>

                                            <!-- Schriftlich Grundlegend -->
                                            <td class="px-3 py-3 text-center border-l border-gray-200 dark:border-gray-700 {{ $stat['is_deutsch_or_math'] ? 'text-gray-300 dark:text-gray-600' : 'text-gray-900 dark:text-gray-100' }}">
                                                @if($stat['is_deutsch_or_math'])
                                                    –
                                                @else
                                                    {{ $stat['grundlegend_written'] > 0 ? $stat['grundlegend_written'] : '–' }}
                                                @endif
                                            </td>

                                            <!-- Schriftlich Erhöht -->
                                            <td class="px-3 py-3 text-center text-amber-700 dark:text-amber-400">
                                                {{ $stat['erhoeht_written'] > 0 ? $stat['erhoeht_written'] : '–' }}
                                            </td>

                                            <!-- Schriftlich Gesamt -->
                                            <td class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">
                                                {{ ($stat['grundlegend_written'] + $stat['erhoeht_written']) > 0 ? ($stat['grundlegend_written'] + $stat['erhoeht_written']) : '–' }}
                                            </td>

                                            <!-- Mündlich Grundlegend -->
                                            <td class="px-3 py-3 text-center border-l border-gray-200 dark:border-gray-700 {{ $stat['is_deutsch_or_math'] ? 'text-gray-300 dark:text-gray-600' : 'text-gray-900 dark:text-gray-100' }}">
                                                @if($stat['is_deutsch_or_math'])
                                                    –
                                                @else
                                                    {{ $stat['grundlegend_oral'] > 0 ? $stat['grundlegend_oral'] : '–' }}
                                                @endif
                                            </td>

                                            <!-- Mündlich Erhöht -->
                                            <td class="px-3 py-3 text-center text-amber-700 dark:text-amber-400">
                                                {{ $stat['erhoeht_oral'] > 0 ? $stat['erhoeht_oral'] : '–' }}
                                            </td>

                                            <!-- Mündlich Gesamt -->
                                            <td class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">
                                                {{ ($stat['grundlegend_oral'] + $stat['erhoeht_oral']) > 0 ? ($stat['grundlegend_oral'] + $stat['erhoeht_oral']) : '–' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700 border-t-2 border-gray-300 dark:border-gray-600">
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-gray-700 dark:text-gray-300">Gesamt</td>
                                        <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-gray-100 border-l border-gray-300 dark:border-gray-600">
                                            {{ $subjectStats->sum('total') }}
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold text-gray-900 dark:text-gray-100 border-l border-gray-300 dark:border-gray-600">
                                            {{ $subjectStats->sum('grundlegend_written') }}
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold text-amber-700 dark:text-amber-400">
                                            {{ $subjectStats->sum('erhoeht_written') }}
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold text-gray-900 dark:text-gray-100">
                                            {{ $subjectStats->sum('grundlegend_written') + $subjectStats->sum('erhoeht_written') }}
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold text-gray-900 dark:text-gray-100 border-l border-gray-300 dark:border-gray-600">
                                            {{ $subjectStats->sum('grundlegend_oral') }}
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold text-amber-700 dark:text-amber-400">
                                            {{ $subjectStats->sum('erhoeht_oral') }}
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold text-gray-900 dark:text-gray-100">
                                            {{ $subjectStats->sum('grundlegend_oral') + $subjectStats->sum('erhoeht_oral') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                            <p><span class="font-medium">Schriftl.</span> = Schriftliche Prüfung &nbsp;|&nbsp; <span class="font-medium">Mündl.</span> = Mündliche Prüfung (Kolloquium)</p>
                            <p><span class="font-medium">gA</span> = grundlegendes Anforderungsniveau &nbsp;|&nbsp; <span class="font-medium">eA</span> = erhöhtes Anforderungsniveau</p>
                            <p>Deutsch und Mathematik werden immer im erhöhten Anforderungsniveau gewertet.</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
