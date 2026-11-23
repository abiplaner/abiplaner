<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Formulare – Schriftliche Prüfungen') }}
            </h2>
            <form method="GET" action="{{ route('written-exams.forms.index') }}" class="flex items-center gap-2">
                <label class="text-sm text-gray-600 dark:text-gray-400">Jahrgang:</label>
                <select name="year" data-submit-on-change
                    class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm">
                    @foreach($examPeriods as $period)
                        <option value="{{ $period->year }}" @selected($period->year == $year)>{{ $period->year }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ activeTab: 'mantelboegen' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Tabs Navigation --}}
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button @click="activeTab = 'mantelboegen'"
                            :class="activeTab === 'mantelboegen' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        Mantelbögen
                    </button>
                    <button @click="activeTab = 'fachausschuesse'"
                            :class="activeTab === 'fachausschuesse' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        Fachausschüsse
                    </button>
                </nav>
            </div>

            {{-- ══════════════════════════════════════════════════════
                 1. Mantelbögen Tab
            ══════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'mantelboegen'" x-cloak class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden animate-fade-in-up">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Mantelbögen</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        DIN A3 quer · ein Bogen pro Schüler · alle verfügbaren Daten vorausgefüllt
                    </p>
                </div>

                @if($yearDates->isEmpty())
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">
                        Keine Prüfungstermine für Jahrgang {{ $year }} gefunden.
                    </div>
                @else
                    <form method="POST" action="{{ route('written-exams.forms.mantelbogen') }}">
                        @csrf

                        {{-- Tabelle --}}
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                <tr>
                                    <th class="w-8 px-4 py-2 text-center">
                                        <input type="checkbox" id="checkAll"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            data-check-all>
                                    </th>
                                    <th class="px-3 py-2 text-left">Datum</th>
                                    <th class="px-3 py-2 text-left">Zeit</th>
                                    <th class="px-3 py-2 text-left">Fach</th>
                                    <th class="px-3 py-2 text-left">Slot</th>
                                    <th class="px-3 py-2 text-right">Schüler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($yearDates as $yd)
                                    @php
                                        $subjects  = $yd->slot->subjects->pluck('name')->unique()->join(', ');
                                        $startTime = \Carbon\Carbon::parse($yd->slot->start_time)->format('H:i');
                                        $endTime   = \Carbon\Carbon::parse($yd->slot->end_time)->format('H:i');
                                        $level     = $yd->slot->requirement_level === 'erhoeht' ? 'eA' : 'gA';
                                        $numStudents = $studentCounts[$yd->id] ?? 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer"
                                        data-toggle-row>
                                        <td class="px-4 py-2 text-center">
                                            <input type="checkbox" name="year_date_ids[]" value="{{ $yd->id }}"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 yeardate-cb"
                                                data-update-download-btn>
                                        </td>
                                        <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                            {{ $yd->date->locale('de')->translatedFormat('D., d.m.Y') }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                            {{ $startTime }}–{{ $endTime }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                            {{ $subjects ?: '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                            {{ $yd->slot->name }} &middot; {{ $level }}
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            @if($numStudents > 0)
                                                <span class="text-green-700 dark:text-green-400 font-medium">{{ $numStudents }}</span>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Footer --}}
                        @unlessarchive
                        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50
                                    flex items-center justify-between">
                            <span class="text-xs text-gray-400" id="selInfo">Kein Termin ausgewählt</span>
                            <button type="submit" id="downloadBtn" disabled
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700
                                       text-white text-sm font-semibold rounded-lg shadow transition
                                       disabled:opacity-40 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                PDF herunterladen
                            </button>
                        </div>
                        @else
                        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-400">
                            Mantelbogen-Erstellung ist in der Archivansicht deaktiviert.
                        </div>
                        @endarchive
                    </form>
                @endif
            </div>

            {{-- ══════════════════════════════════════════════════════
                 2. Fachausschüsse Tab
            ══════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'fachausschuesse'" x-cloak class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden animate-fade-in-up">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">Fachausschüsse</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            Kurs · Schülerzahl · Erstprüfer · Zweitprüfer · für Jahrgang {{ $year }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('written-exams.forms.fachausschuesse.pdf', ['year' => $year]) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700
                                  text-white text-sm font-semibold rounded-lg shadow transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            PDF herunterladen
                        </a>
                        <a href="{{ route('written-exams.forms.fachausschuesse.excel', ['year' => $year]) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700
                                  text-white text-sm font-semibold rounded-lg shadow transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Excel herunterladen
                        </a>
                    </div>
                </div>

                @if(empty($fachausschuesseRows))
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">
                        Keine Daten für Jahrgang {{ $year }} gefunden.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                <tr>
                                    <th class="px-6 py-3">Kurs</th>
                                    <th class="px-6 py-3 text-center">Anzahl</th>
                                    <th class="px-6 py-3">Erstprüfer</th>
                                    <th class="px-6 py-3">Zweitprüfer</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($fachausschuesseRows as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-6 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $row['kurs'] }}</td>
                                        <td class="px-6 py-3 text-center text-gray-600 dark:text-gray-400 font-medium">{{ $row['anzahl'] }}</td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $row['erst'] }}</td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $row['zweit'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>

    <script @cspNonce>
        // Frueher Inline-Handler (Modul 7.2).
        document.querySelectorAll('[data-check-all]').forEach(
            (el) => el.addEventListener('change', () => toggleAll(el.checked)));
        document.querySelectorAll('[data-update-download-btn]').forEach(
            (el) => el.addEventListener('change', updateBtn));

        // Ein Klick irgendwo in die Zeile schaltet ihr Kaestchen um. Klicks auf
        // das Kaestchen selbst bleiben aussen vor — es schaltet ja schon.
        // Frueher hing dafuer ein event.stopPropagation() an der Zelle.
        document.querySelectorAll('[data-toggle-row]').forEach((zeile) => {
            zeile.addEventListener('click', (ereignis) => {
                if (ereignis.target.closest('input, label, a, button')) {
                    return;
                }

                zeile.querySelector('input[type=checkbox]')?.click();
            });
        });

        function toggleAll(state) {
            document.querySelectorAll('.yeardate-cb').forEach(cb => cb.checked = state);
            updateBtn();
        }
        function updateBtn() {
            const btn = document.getElementById('downloadBtn');
            if (!btn) return; // Archivansicht: Footer mit Button ist ausgeblendet
            const n = document.querySelectorAll('.yeardate-cb:checked').length;
            btn.disabled = n === 0;
            document.getElementById('selInfo').textContent =
                n === 0 ? 'Kein Termin ausgewählt'
                        : n + ' Termin' + (n > 1 ? 'e' : '') + ' ausgewählt';
            document.getElementById('checkAll').indeterminate =
                n > 0 && n < document.querySelectorAll('.yeardate-cb').length;
            document.getElementById('checkAll').checked =
                n === document.querySelectorAll('.yeardate-cb').length;
        }
    </script>
</x-app-layout>
