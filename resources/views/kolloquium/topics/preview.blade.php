<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Themenkatalog – Vorschau') }}
        </h2>
    </x-slot>

    <div class="py-6" x-data="{ all: true }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Zusammenfassung --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg px-6 py-4">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200">
                    {{ $originalName }}
                </h3>
                <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Zeilen</div>
                        <div class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $preview['row_count'] }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Kurs erkannt</div>
                        <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $preview['matched_count'] }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 dark:text-gray-400">ohne Zuordnung</div>
                        <div class="text-lg font-semibold {{ $preview['unmatched_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200' }}">
                            {{ $preview['unmatched_count'] }}
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Themen</div>
                        <div class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $preview['topic_count'] }}</div>
                    </div>
                </div>

                @if ($preview['matched_count'] === 0)
                    <div class="mt-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800
                                text-red-800 dark:text-red-200 px-4 py-3 rounded-lg text-sm">
                        <strong>Keine einzige Zeile konnte einem Kurs zugeordnet werden.</strong>
                        Vermutlich gehört die Vorlage zu einem anderen Jahrgang – die Kurskürzel tragen
                        einen Jahrgangs-Präfix (z. B. <code>1b1</code> gegenüber <code>2b1</code>).
                        Bitte prüfen, ob die Datei zum Jahrgang {{ $year }} passt.
                    </div>
                @elseif ($preview['unmatched_count'] > 0)
                    <div class="mt-4 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800
                                text-amber-800 dark:text-amber-200 px-4 py-3 rounded-lg text-sm">
                        {{ $preview['unmatched_count'] }} Zeilen konnten keinem Kurs zugeordnet werden und
                        werden übersprungen.
                    </div>
                @endif
            </div>

            {{-- Zeilen mit Auswahl --}}
            <form method="POST" action="{{ route('kolloquium.topics.store') }}">
                @csrf
                <input type="hidden" name="stored_path" value="{{ $storedPath }}">
                <input type="hidden" name="year" value="{{ $year }}">

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Zu übernehmende Kurse</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Nur angehakte Kurse werden gespeichert. Kurse ohne Themen in der Vorlage
                                bleiben unverändert – bereits erfasste Themen gehen dabei nicht verloren.
                            </p>
                        </div>
                    </div>

                    @error('course_codes')
                        <div class="px-6 py-3 bg-red-50 dark:bg-red-900/30 text-sm text-red-700 dark:text-red-300">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-600 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2 text-left">
                                        <input type="checkbox" x-model="all"
                                               @change="$el.closest('table').querySelectorAll('input.row-check:not(:disabled)').forEach(c => c.checked = all)"
                                               checked
                                               class="rounded border-gray-300 dark:border-gray-600">
                                    </th>
                                    <th class="px-4 py-2 text-left font-semibold">Zeile</th>
                                    <th class="px-4 py-2 text-left font-semibold">Kurs</th>
                                    <th class="px-4 py-2 text-left font-semibold">Lehrkraft</th>
                                    <th class="px-4 py-2 text-left font-semibold">Fach</th>
                                    <th class="px-4 py-2 text-center font-semibold">Themen</th>
                                    <th class="px-4 py-2 text-left font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($preview['rows'] as $row)
                                    @php
                                        $importable = $row['status'] === 'matched' && $row['topic_count'] > 0;
                                        $overwrites = $importable && $row['existing_count'] > 0;
                                    @endphp
                                    <tr class="text-gray-800 dark:text-gray-200
                                               {{ $row['status'] !== 'matched' ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}
                                               {{ $importable ? '' : 'opacity-60' }}">
                                        <td class="px-4 py-2">
                                            <input type="checkbox" class="row-check rounded border-gray-300 dark:border-gray-600"
                                                   name="course_codes[]" value="{{ $row['course_code'] }}"
                                                   @checked($importable) @disabled(!$importable)>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $row['row'] }}</td>
                                        <td class="px-4 py-2 font-medium whitespace-nowrap">{{ $row['course_code'] }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['teacher'] }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['subject'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['topic_count'] }}</td>
                                        <td class="px-4 py-2">
                                            @if ($row['status'] !== 'matched')
                                                <span class="text-xs font-medium text-red-700 dark:text-red-300">
                                                    Kurs nicht gefunden
                                                </span>
                                            @elseif ($row['topic_count'] === 0)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    keine Themen – bleibt unverändert
                                                </span>
                                            @elseif ($overwrites)
                                                <span class="text-xs font-medium text-amber-700 dark:text-amber-300">
                                                    überschreibt {{ $row['existing_count'] }} vorhandene
                                                </span>
                                            @else
                                                <span class="text-xs font-medium text-green-700 dark:text-green-300">
                                                    neu
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <a href="{{ route('kolloquium.topics.index', ['year' => $year]) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                            Abbrechen
                        </a>
                        <button type="submit"
                                @disabled($preview['matched_count'] === 0)
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                                       disabled:bg-gray-400 disabled:cursor-not-allowed
                                       text-white text-sm font-semibold rounded-lg shadow transition">
                            Ausgewählte übernehmen
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
