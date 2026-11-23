<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Absenzen: {{ $student->lastname }}, {{ $student->firstname }}
            </h2>
            <a href="{{ route('absences.index') }}{{ request()->query('grade') ? '?grade=' . request()->query('grade') : '' }}"
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 border-l-4 border-green-400 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Attestpflicht-Status (abiplaner-interne Entscheidung, Modul 8.3) --}}
            <div class="border-l-4 p-4 text-sm {{ $attestpflicht['active'] ? 'bg-amber-50 border-amber-400 text-amber-900' : 'bg-gray-50 border-gray-300 text-gray-600' }}">
                @if ($attestpflicht['active'])
                    <strong>Attestpflicht aktiv</strong> seit {{ $attestpflicht['since']->format('d.m.Y') }}.
                    Für jede neue Krankmeldung ist innerhalb von 10 Kalendertagen ein Attest fällig.
                @else
                    Keine aktive Attestpflicht.
                @endif
            </div>

            {{-- Statistik --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Krankheitstage</p>
                    <p class="text-2xl font-semibold text-gray-800">{{ $statistics['krank_days'] }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Fehlstunden</p>
                    <p class="text-2xl font-semibold text-gray-800">{{ $statistics['fehlt_hours'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">davon {{ $statistics['fehlt_unentschuldigt_hours'] }} unentschuldigt</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Verspätungen</p>
                    <p class="text-2xl font-semibold text-gray-800">{{ $statistics['verspaetet_count'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $statistics['verspaetet_minutes'] }} Minuten gesamt</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Attest ausstehend</p>
                    <p class="text-2xl font-semibold {{ $openAttest['overdue'] ? 'text-red-700' : 'text-gray-800' }}">
                        {{ $openAttest['count'] }}
                    </p>
                    @if ($openAttest['overdue'])
                        <p class="text-xs text-red-600 mt-1">Frist überschritten</p>
                    @endif
                </div>
            </div>

            {{-- Vorjahres-Kennzahlen (Modul 1.1, Phase 3) --}}
            @if ($carryover !== null)
                <div class="bg-slate-50 border border-slate-200 shadow-sm sm:rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">
                            Vorjahr ({{ $carryover->source_school_year }}@if($carryover->source_grade), Q{{ $carryover->source_grade }}@endif)
                        </p>
                        @if ($carryover->had_attest_requirement)
                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">Attestpflicht bestand</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-500">Krankheitstage</p>
                            <p class="font-semibold text-slate-800">{{ $carryover->sick_days }}@if($carryover->sick_percent !== null) <span class="text-xs text-slate-500">({{ number_format($carryover->sick_percent, 1, ',', '.') }}&nbsp;%)</span>@endif</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Unentschuldigte Stunden</p>
                            <p class="font-semibold text-slate-800">{{ $carryover->unexcused_hours }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Verspätungen</p>
                            <p class="font-semibold text-slate-800">{{ $carryover->tardiness_count }} <span class="text-xs text-slate-500">({{ $carryover->tardiness_minutes }} Min.)</span></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Zuordnung</p>
                            <p class="font-semibold text-slate-800">{{ $carryover->match_method === 'auto' ? 'automatisch' : 'manuell' }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Absenzen-Liste (ausklappbar) --}}
            <details class="bg-white shadow-sm sm:rounded-lg overflow-hidden" {{ $absences->isEmpty() ? '' : 'open' }}>
                <summary class="cursor-pointer select-none px-4 py-3 bg-gray-50 text-sm font-medium text-gray-700">
                    Alle Einträge ({{ $absences->count() }})
                </summary>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-t">
                        <tr class="text-left text-gray-500">
                            <th class="py-3 px-4">Zeitraum</th>
                            <th class="py-3 px-4">Art</th>
                            <th class="py-3 px-4">Umfang</th>
                            <th class="py-3 px-4">Entschuldigt</th>
                            <th class="py-3 px-4">Grund</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($absences as $absence)
                            @php
                                $isOpenAttest = $absence->excuse_status === 'attestpflicht';
                                $deadline = $isOpenAttest ? $absence->date_from->copy()->addDays(10) : null;
                                $overdue = $deadline?->isPast() ?? false;
                            @endphp
                            <tr class="{{ $isOpenAttest ? ($overdue ? 'bg-red-50' : 'bg-amber-50') : '' }}">
                                <td class="py-2 px-4 whitespace-nowrap align-top">
                                    {{ $absence->date_from->format('d.m.Y') }}
                                    @if (!$absence->date_from->isSameDay($absence->date_to))
                                        – {{ $absence->date_to->format('d.m.Y') }}
                                    @endif
                                </td>
                                <td class="py-2 px-4 align-top">{{ $absence->art_raw }}</td>
                                <td class="py-2 px-4 whitespace-nowrap align-top">
                                    @if ($absence->days > 0) {{ $absence->days }} Tag(e) @endif
                                    @if ($absence->hours > 0) {{ $absence->hours }} Std. @endif
                                    @if ($absence->minutes > 0) {{ $absence->minutes }} Min. @endif
                                </td>
                                <td class="py-2 px-4 align-top">
                                    @if ($isOpenAttest)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $overdue ? 'bg-red-200 text-red-900' : 'bg-amber-200 text-amber-900' }}">
                                            Attest ausstehend – Frist {{ $deadline->format('d.m.Y') }}
                                            @if ($overdue) (überfällig) @endif
                                        </span>
                                    @else
                                        {{ $absence->excuse_status }}
                                    @endif
                                </td>
                                <td class="py-2 px-4 text-gray-600 max-w-xs truncate align-top" title="{{ $absence->reason }}">
                                    {{ $absence->reason }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 px-4 text-center text-gray-500">
                                    Keine Absenzen erfasst.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </details>

            {{-- Maßnahmen (Roadmap 8.3) --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Dokumentierte Maßnahmen</h3>

                @if ($errors->any())
                    <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 text-sm text-red-700">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @unlessarchive
                <form action="{{ route('absences.measures.store', $student) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Typ</label>
                        <select name="type" required class="w-full border-gray-300 rounded-md text-sm">
                            @foreach (\App\Models\AbsenceMeasure::availableTypes() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">
                            <a href="{{ route('school-settings.index') }}#absenzen" class="hover:underline">Typen anpassen</a>
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Aspekt (setzt Zähler zurück)</label>
                        <select name="aspect" required class="w-full border-gray-300 rounded-md text-sm">
                            @foreach (\App\Models\AbsenceMeasure::ASPECT_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
                        <input type="date" name="measure_date" required value="{{ now()->format('Y-m-d') }}"
                               class="w-full border-gray-300 rounded-md text-sm">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notiz</label>
                        <textarea name="note" rows="2" class="w-full border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                            Maßnahme dokumentieren
                        </button>
                    </div>
                </form>
                @endarchive

                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="py-2 pr-4">Datum</th>
                            <th class="py-2 pr-4">Typ</th>
                            <th class="py-2 pr-4">Aspekt</th>
                            <th class="py-2 pr-4">Notiz</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($measures as $measure)
                            <tr>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $measure->measure_date->format('d.m.Y') }}</td>
                                <td class="py-2 pr-4">{{ \App\Models\AbsenceMeasure::labelFor($measure->type) }}</td>
                                <td class="py-2 pr-4">{{ \App\Models\AbsenceMeasure::ASPECT_LABELS[$measure->aspect] }}</td>
                                <td class="py-2 pr-4 text-gray-600">{{ $measure->note }}</td>
                                <td class="py-2 text-right">
                                    @unlessarchive
                                    <form action="{{ route('absences.measures.destroy', [$student, $measure]) }}" method="POST"
                                          data-confirm="Maßnahme wirklich löschen?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline text-xs">Löschen</button>
                                    </form>
                                    @endarchive
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-500">Noch keine Maßnahmen dokumentiert.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
