<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Formulare – Kolloquium') }}
            </h2>
            <form method="GET" action="{{ route('kolloquium.forms.index') }}" class="flex items-center gap-2">
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

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">Fachausschüsse (Kolloquium)</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            Kurs · Schülerzahl · Erstprüfer · Zweitprüfer · für Jahrgang {{ $year }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('kolloquium.forms.fachausschuesse.pdf', ['year' => $year]) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700
                                  text-white text-sm font-semibold rounded-lg shadow transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            PDF herunterladen
                        </a>
                        <a href="{{ route('kolloquium.forms.fachausschuesse.excel', ['year' => $year]) }}"
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
                                    <th class="px-6 py-3">Anzahl</th>
                                    <th class="px-6 py-3">Erstprüfer</th>
                                    <th class="px-6 py-3">Zweitprüfer</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($fachausschuesseRows as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-6 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $row['kurs'] }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400 font-medium">{{ $row['anzahl'] }}</td>
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
</x-app-layout>
