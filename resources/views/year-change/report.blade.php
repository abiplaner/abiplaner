<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📄 Schuljahreswechsel — Abschlussbericht
            </h2>
            <a href="{{ route('year-change.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Zum Wizard
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Kopf --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-semibold">
                            {{ $sourcePeriod?->name ?? 'Quell-Periode' }}
                            <span class="text-blue-500 mx-1">→</span>
                            {{ $targetPeriod?->name ?? 'Ziel-Periode' }}
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Gestartet: {{ \Carbon\Carbon::parse($state['started_at'])->format('d.m.Y, H:i') }} Uhr
                            @if (!empty($state['started_by'])) von {{ $state['started_by'] }} @endif
                        </p>
                    </div>
                    @if (($state['completed_at'] ?? null) !== null)
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                            ✅ Abgeschlossen {{ \Carbon\Carbon::parse($state['completed_at'])->format('d.m.Y, H:i') }} Uhr
                        </span>
                    @else
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                            Schritt {{ $wizard->currentStep() }} von 9
                        </span>
                    @endif
                </div>
            </div>

            {{-- Schritt-Protokoll --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Schritt-Protokoll</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Schritt</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Erledigt am</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ergebnis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @for ($step = 1; $step <= 9; $step++)
                            @php
                                $completed = $wizard->stepCompleted($step);
                                $result = $wizard->stepResult($step);
                                $completedAt = $state['steps'][(string) $step]['completed_at'] ?? null;
                            @endphp
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap font-medium">{{ $step }}. {{ $wizard->stepTitle($step) }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($completed)
                                        <span class="text-green-700">✅ erledigt</span>
                                    @else
                                        <span class="text-gray-400">offen</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">
                                    {{ $completedAt !== null ? \Carbon\Carbon::parse($completedAt)->format('d.m.Y, H:i') : '—' }}
                                </td>

                                <td class="px-3 py-2 text-gray-600">
                                    @if ($result === null)
                                        —
                                    @elseif ($step === 2)
                                        Backup: {{ $result['filename'] ?? '?' }}
                                    @elseif ($step === 3)
                                        {{ $result['frozen'] ?? 0 }} Schüler eingefroren,
                                        {{ $result['purged_old_carryovers'] ?? 0 }} Vor-Vorjahres-Einträge gelöscht
                                    @elseif ($step === 4)
                                        {{ $result['deleted_absences'] ?? 0 }} Absenzen,
                                        {{ $result['deleted_measures'] ?? 0 }} Maßnahmen,
                                        {{ $result['deleted_imports'] ?? 0 }} Import-Protokolle gelöscht
                                    @elseif ($step === 5)
                                        {{ $result['students_in_target'] ?? 0 }} Schüler in der Ziel-Periode
                                    @elseif ($step === 6)
                                        {{ $result['matched_total'] ?? 0 }} zugeordnet,
                                        {{ $result['unmatched'] ?? 0 }} offen (Stand Auto-Zuordnung)
                                    @elseif ($step === 7)
                                        {{ $result['slot_templates_copied'] ?? 0 }} Zeitslot-,
                                        {{ $result['supervision_templates_copied'] ?? 0 }} Aufsichtsvorlagen kopiert
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            {{-- Aktuelle Zahlen (live) --}}
            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h4 class="font-semibold mb-3">Schüler in {{ $targetPeriod?->name ?? 'der Ziel-Periode' }}</h4>
                    <div class="flex flex-wrap gap-2 text-sm">
                        @forelse ($targetStudentsByGrade as $grade => $count)
                            <span class="px-2 py-1 bg-blue-50 text-blue-800 rounded">
                                Jgst. {{ $grade }}: <strong>{{ $count }}</strong>
                            </span>
                        @empty
                            <span class="text-gray-400">Noch keine Schüler importiert.</span>
                        @endforelse
                    </div>
                    <h4 class="font-semibold mt-4 mb-2">Lehrkräfte</h4>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <span class="px-2 py-1 bg-green-50 text-green-800 rounded">Aktiv: <strong>{{ $teacherStats['active'] }}</strong></span>
                        <span class="px-2 py-1 bg-gray-50 text-gray-600 rounded">Inaktiv: <strong>{{ $teacherStats['inactive'] }}</strong></span>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h4 class="font-semibold mb-3">Vorjahres-Zuordnung</h4>
                    @if ($carryoverStats !== null)
                        <div class="flex flex-wrap gap-2 text-sm mb-3">
                            <span class="px-2 py-1 bg-gray-50 text-gray-700 rounded">Gesamt: <strong>{{ $carryoverStats['total'] }}</strong></span>
                            <span class="px-2 py-1 bg-green-50 text-green-800 rounded">Automatisch: <strong>{{ $carryoverStats['matched_auto'] }}</strong></span>
                            <span class="px-2 py-1 bg-blue-50 text-blue-800 rounded">Manuell: <strong>{{ $carryoverStats['matched_manual'] }}</strong></span>
                            <span class="px-2 py-1 bg-amber-50 text-amber-800 rounded">Offen: <strong>{{ $carryoverStats['open'] ?? $carryoverStats['unmatched'] }}</strong></span>
                            @if (($carryoverStats['leavers'] ?? 0) > 0)
                                <span class="px-2 py-1 bg-gray-50 text-gray-500 rounded">Abgänger: <strong>{{ $carryoverStats['leavers'] }}</strong></span>
                            @endif
                        </div>

                        @if ($unmatchedCarryovers->isNotEmpty())
                            <p class="text-xs text-gray-500 mb-1">Offene Klärungsfälle (werden beim nächsten Wechsel gelöscht):</p>
                            <ul class="text-sm text-gray-700 list-disc pl-5">
                                @foreach ($unmatchedCarryovers as $carryover)
                                    <li>{{ $carryover->lastname }}, {{ $carryover->firstname }} (Jgst. {{ $carryover->source_grade ?? '?' }})</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-green-700">Alle Vorjahreswerte der verbleibenden Jahrgänge sind zugeordnet.</p>
                        @endif

                        @if ($leaverCarryovers->isNotEmpty())
                            <p class="text-xs text-gray-400 mt-2">
                                Dazu {{ $leaverCarryovers->count() }} Einträge des abgegangenen Abiturjahrgangs —
                                erwartungsgemäß ohne Zuordnung, sie werden beim nächsten Wechsel gelöscht.
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-gray-400">Keine Zuordnungsdaten vorhanden.</p>
                    @endif
                </div>
            </div>

            {{-- Hinweise --}}
            <div class="bg-blue-50 border-l-4 border-blue-400 px-4 py-3 rounded text-sm text-blue-900">
                <p class="font-semibold mb-1">Hinweise für das neue Schuljahr:</p>
                <ul class="list-disc pl-5 space-y-0.5">
                    <li>Prüfungstag-Zuordnungen (Datum → Zeitslot-Vorlage) wurden bewusst nicht kopiert — bitte für {{ $targetPeriod?->name ?? 'die neue Periode' }} neu anlegen.</li>
                    <li>Kopierte Aufsichtsvorlagen ggf. an geänderte Lehrer- und Raumsituation anpassen.</li>
                    <li>Der alte Jahrgang bleibt über den Perioden-Umschalter als Archiv einsehbar (schreibgeschützt).</li>
                </ul>
            </div>

            {{-- Abschluss --}}
            @if (($state['completed_at'] ?? null) === null)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    @if ($wizard->stepAvailable(9))
                        <h4 class="font-semibold mb-2">Schuljahreswechsel abschließen</h4>
                        <p class="text-sm text-gray-500 mb-3">
                            Markiert den Wechsel als erledigt. Offene Klärungsfälle können auch danach noch
                            in der Absenz-Detailansicht nachvollzogen werden.
                        </p>
                        <form method="POST" action="{{ route('year-change.finish') }}">
                            @csrf
                            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-semibold">
                                ✅ Wechsel jetzt abschließen
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500">
                            Der Wechsel kann abgeschlossen werden, sobald die Schritte 1–8 erledigt sind
                            (aktuell: Schritt {{ $wizard->currentStep() }}).
                        </p>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
