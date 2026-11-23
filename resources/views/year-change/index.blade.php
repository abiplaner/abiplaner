<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🔄 Schuljahreswechsel
            </h2>
            <div class="flex gap-2">
                @if ($state !== null)
                    <a href="{{ route('year-change.report') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Bericht
                    </a>
                @endif
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    ← Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($isArchiveView)
                <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-800 px-4 py-3 rounded">
                    ⚠️ Sie befinden sich in einer <strong>Archivansicht</strong>. Der Wizard kann nur in der
                    aktuellen Periode ausgeführt werden — bitte oben über den Umschalter zurückwechseln.
                </div>
            @endif

            @if (!$running)
                {{-- ===== STARTSEITE: kein laufender Wechsel ===== --}}

                @if ($state !== null && ($state['completed_at'] ?? null) !== null)
                    <div class="bg-green-50 border-l-4 border-green-400 px-4 py-3 rounded">
                        <p class="text-green-800 font-medium">
                            ✅ Der letzte Schuljahreswechsel wurde am
                            {{ \Carbon\Carbon::parse($state['completed_at'])->format('d.m.Y, H:i') }} Uhr abgeschlossen.
                        </p>
                        <a href="{{ route('year-change.report') }}" class="text-sm text-green-700 underline">Abschlussbericht ansehen →</a>
                    </div>
                @endif

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-2">Neuen Schuljahreswechsel starten</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Der Wizard führt in neun Schritten durch den Wechsel: Backup, Einfrieren und Löschen der
                        Absenzdaten, Neuimport des Jahrgangs, Zuordnung der Vorjahreswerte, Kopieren der Vorlagen
                        und Lehrer-Abgleich. Der Fortschritt wird gespeichert — Sie können jederzeit unterbrechen
                        und später fortsetzen.
                    </p>

                    @if (!empty($preflight['errors']))
                        <div class="bg-red-50 border-l-4 border-red-400 px-4 py-3 rounded mb-4">
                            <p class="font-medium text-red-800 mb-1">Voraussetzungen nicht erfüllt:</p>
                            <ul class="list-disc pl-5 text-sm text-red-700">
                                @foreach ($preflight['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="flex items-center gap-4 p-4 bg-blue-50 rounded-lg mb-4">
                            <div class="text-center">
                                <div class="text-xs text-gray-500">Ablaufend</div>
                                <div class="font-semibold text-gray-800">{{ $preflight['source']->name }}</div>
                            </div>
                            <div class="text-2xl text-blue-500">→</div>
                            <div class="text-center">
                                <div class="text-xs text-gray-500">Neu</div>
                                <div class="font-semibold text-gray-800">{{ $preflight['target']->name }}</div>
                            </div>
                            @unless ($preflight['target']->exists)
                                <div class="text-xs text-gray-600 ml-2">
                                    Wird beim Start angelegt — Kolloquium
                                    {{ $preflight['target']->week_1_start->format('d.m.') }}–{{ $preflight['target']->week_1_end->format('d.m.') }}
                                    und {{ $preflight['target']->week_2_start->format('d.m.') }}–{{ $preflight['target']->week_2_end->format('d.m.Y') }}
                                    laut Bekanntmachung des Kultusministeriums.
                                </div>
                            @endunless
                        </div>

                        @foreach ($preflight['warnings'] as $warning)
                            <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-800 text-sm px-4 py-2 rounded mb-4">
                                ⚠️ {{ $warning }}
                            </div>
                        @endforeach

                        <form method="POST" action="{{ route('year-change.start') }}">
                            @csrf
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">
                                🔄 Wizard starten
                            </button>
                        </form>
                    @endif
                </div>

                @if (empty($preflight['errors']))
                    {{-- Einzelaktion: Schritt 7 ohne Wizard, für bereits vollzogene Wechsel --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">Nur Zeitslots &amp; Vorlagen aus dem Vorjahr übernehmen</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Für den Fall, dass der Wechsel bereits ohne den Wizard vollzogen wurde: übernimmt
                            Zeitslot-Vorlagen, Kolloquiums-Zeitslots (Woche 1 &amp; 2 auf die neuen Prüfungstage
                            gelegt), Aufsichtsraster, Aufsichts-Zeitslots und Aufsichtsvorlagen von
                            <strong>{{ $preflight['source']->name }}</strong> nach
                            <strong>{{ $preflight['target']->name }}</strong>.
                            Bereits Vorhandenes bleibt unverändert, die Aktion kann gefahrlos wiederholt werden.
                            Absenzdaten werden nicht angefasst. Prüfungstag-Zuordnungen werden <strong>nicht</strong>
                            übernommen — sie werden im neuen Jahr neu gesetzt.
                        </p>
                        <form method="POST" action="{{ route('year-change.copy-templates') }}">
                            @csrf
                            <button type="submit"
                                    class="px-6 py-2 bg-white border border-blue-600 text-blue-700 rounded-md hover:bg-blue-50 font-semibold">
                                📋 Zeitslots &amp; Vorlagen übernehmen
                            </button>
                        </form>
                    </div>
                @endif

            @else
                {{-- ===== LAUFENDER WECHSEL: 9-Schritte-Checkliste ===== --}}

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-lg font-semibold">
                            {{ $sourcePeriod?->name ?? 'Quell-Periode' }}
                            <span class="text-blue-500 mx-1">→</span>
                            {{ $targetPeriod?->name ?? 'Ziel-Periode' }}
                        </h3>
                        <span class="text-sm text-gray-500">Schritt {{ $currentStep }} von 9</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="h-2 rounded-full bg-blue-500" style="width: {{ (int) round(($currentStep - 1) / 9 * 100) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        Gestartet am {{ \Carbon\Carbon::parse($state['started_at'])->format('d.m.Y, H:i') }} Uhr
                        @if (!empty($state['started_by'])) von {{ $state['started_by'] }} @endif
                        — der Fortschritt wird gespeichert, Sie können jederzeit unterbrechen.
                    </p>
                </div>

                @for ($step = 1; $step <= 9; $step++)
                    @php
                        $completed = $wizard->stepCompleted($step);
                        $available = $wizard->stepAvailable($step);
                        $isCurrent = !$completed && $available;
                        $result = $wizard->stepResult($step);
                        $completedAt = $state['steps'][(string) $step]['completed_at'] ?? null;
                    @endphp

                    <div class="bg-white shadow-sm sm:rounded-lg p-5 {{ $isCurrent ? 'ring-2 ring-blue-400' : '' }} {{ (!$completed && !$available) ? 'opacity-60' : '' }}">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-semibold text-sm
                                {{ $completed ? 'bg-green-100 text-green-700' : ($isCurrent ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400') }}">
                                {{ $completed ? '✓' : $step }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center">
                                    <h4 class="font-semibold text-gray-800">{{ $wizard->stepTitle($step) }}</h4>
                                    @if ($completed && $completedAt)
                                        <span class="text-xs text-gray-400">
                                            erledigt {{ \Carbon\Carbon::parse($completedAt)->format('d.m.Y, H:i') }} Uhr
                                        </span>
                                    @endif
                                </div>

                                {{-- ===== Schritt 1: Voraussetzungen ===== --}}
                                @if ($step === 1)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Ablaufende Periode <strong>{{ $sourcePeriod?->name }}</strong>
                                        ({{ $sourceStudentCount }} Schüler, Schuljahr {{ $sourceSchoolYear }})
                                        wird abgelöst durch <strong>{{ $targetPeriod?->name }}</strong>.
                                        Alte Jahrgänge bleiben unter ihrer Periode einsehbar (Archiv, schreibgeschützt) —
                                        nur die Absenz-Rohdaten werden gelöscht (Schritte 3–4).
                                    </p>
                                    @if ($isCurrent)
                                        <form method="POST" action="{{ route('year-change.complete', 1) }}" class="mt-3">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                                Bestätigen &amp; weiter
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                {{-- ===== Schritt 2: Auto-Backup ===== --}}
                                @if ($step === 2)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Vollständiges Schul-Backup vor allen Änderungen
                                        (Typ <code class="text-xs bg-gray-100 px-1 rounded">auto_before_yearchange</code>).
                                    </p>
                                    @if ($completed && $result)
                                        <p class="text-sm text-green-700 mt-1">💾 {{ $result['filename'] ?? '' }}</p>
                                    @elseif ($isCurrent)
                                        <form method="POST" action="{{ route('year-change.run', 2) }}" class="mt-3">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                                💾 Backup jetzt erstellen
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                {{-- ===== Schritt 3: Aggregate einfrieren ===== --}}
                                @if ($step === 3)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Pro Schüler des Schuljahres {{ $sourceSchoolYear }} werden die
                                        Absenz-Kennzahlen (Krankheitstage, unentschuldigte Stunden, Verspätungen,
                                        Attestpflicht-Status) eingefroren. Carryovers des Vor-Vorjahres werden
                                        dabei gelöscht (Aufbewahrung genau ein Jahr).
                                    </p>
                                    @if ($completed && $result)
                                        <p class="text-sm text-green-700 mt-1">
                                            ❄️ {{ $result['frozen'] ?? 0 }} Schüler eingefroren,
                                            {{ $result['purged_old_carryovers'] ?? 0 }} alte Einträge entfernt
                                        </p>
                                    @elseif ($isCurrent)
                                        <form method="POST" action="{{ route('year-change.run', 3) }}" class="mt-3">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                                ❄️ Kennzahlen einfrieren
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                {{-- ===== Schritt 4: Rohdaten löschen (destruktiv) ===== --}}
                                @if ($step === 4)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Löscht alle Absenzen, Maßnahmen (inkl. Freitext-Gründen mit
                                        Art.-9-Gesundheitsdaten) und Import-Protokolle der Schüler des
                                        Schuljahres {{ $sourceSchoolYear }} — <strong>unwiderruflich</strong>.
                                        Das Faktum einer bestehenden Attestpflicht wurde in Schritt 3 als Flag übernommen.
                                    </p>
                                    @if ($completed && $result)
                                        <p class="text-sm text-green-700 mt-1">
                                            🗑️ {{ $result['deleted_absences'] ?? 0 }} Absenzen,
                                            {{ $result['deleted_measures'] ?? 0 }} Maßnahmen,
                                            {{ $result['deleted_imports'] ?? 0 }} Import-Protokolle gelöscht
                                        </p>
                                    @elseif ($isCurrent)
                                        <form method="POST" action="{{ route('year-change.run', 4) }}" class="mt-3 bg-red-50 border border-red-200 rounded-lg p-4">
                                            @csrf
                                            <label class="flex items-start gap-2 text-sm text-red-800 cursor-pointer">
                                                <input type="checkbox" name="confirm_delete" value="1" class="rounded mt-0.5">
                                                <span>
                                                    Ich bestätige, dass das Backup (Schritt 2) vorliegt und die
                                                    Absenz-Rohdaten des Schuljahres {{ $sourceSchoolYear }}
                                                    unwiderruflich gelöscht werden sollen.
                                                </span>
                                            </label>
                                            <button type="submit" class="mt-3 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-semibold">
                                                🗑️ Rohdaten jetzt löschen
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                {{-- ===== Schritt 5: Neuen Jahrgang importieren ===== --}}
                                @if ($step === 5)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Import über die bestehenden Wege: ASV-Abiturexport für die neue Q13
                                        (mit Abiturfächern), einfache Jahrgangsliste für Q12/Q11. Alle neuen
                                        Schüler erhalten automatisch die Periode {{ $targetPeriod?->name }}.
                                    </p>
                                    @if ($available)
                                        <div class="mt-2 flex flex-wrap gap-2 text-sm">
                                            @forelse ($targetStudentsByGrade as $grade => $count)
                                                <span class="px-2 py-1 bg-blue-50 text-blue-800 rounded">
                                                    Jgst. {{ $grade }}: <strong>{{ $count }}</strong>
                                                </span>
                                            @empty
                                                <span class="px-2 py-1 bg-gray-50 text-gray-500 rounded">Noch keine Schüler in der Ziel-Periode</span>
                                            @endforelse
                                        </div>
                                    @endif
                                    @if ($isCurrent)
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <a href="{{ route('import.form') }}"
                                               class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800 text-sm font-semibold">
                                                → Zum Schüler-Import
                                            </a>
                                            <form method="POST" action="{{ route('year-change.complete', 5) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="px-4 py-2 rounded-md text-sm font-semibold {{ empty($targetStudentsByGrade) ? 'bg-gray-200 text-gray-500' : 'bg-blue-600 text-white hover:bg-blue-700' }}">
                                                    Import abgeschlossen
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                @endif

                                {{-- ===== Schritt 6: Zuordnung + Klärungsliste ===== --}}
                                @if ($step === 6)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Namensabgleich der eingefrorenen Vorjahreswerte gegen die neuen
                                        Schülerdatensätze (external_id vor exaktem Namen). Mehrdeutige Fälle
                                        landen in der Klärungsliste und können hier manuell zugeordnet werden.
                                    </p>
                                    @if ($carryoverStats !== null && ($completed || $isCurrent))
                                        <div class="mt-2 flex flex-wrap gap-2 text-sm">
                                            <span class="px-2 py-1 bg-gray-50 text-gray-700 rounded">Gesamt: <strong>{{ $carryoverStats['total'] }}</strong></span>
                                            <span class="px-2 py-1 bg-green-50 text-green-800 rounded">Zugeordnet: <strong>{{ $carryoverStats['matched'] }}</strong></span>
                                            <span class="px-2 py-1 bg-amber-50 text-amber-800 rounded">Zu klären: <strong>{{ $carryoverStats['open'] ?? $carryoverStats['unmatched'] }}</strong></span>
                                            @if (($carryoverStats['leavers'] ?? 0) > 0)
                                                <span class="px-2 py-1 bg-gray-50 text-gray-500 rounded" title="Abiturjahrgang des Vorjahres — verlässt die Schule">Abgänger: <strong>{{ $carryoverStats['leavers'] }}</strong></span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($isCurrent)
                                        @if ($result === null)
                                            <form method="POST" action="{{ route('year-change.run', 6) }}" class="mt-3">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                                    🔗 Automatische Zuordnung starten
                                                </button>
                                            </form>
                                        @else

                                            @if ($unmatchedCarryovers->isNotEmpty())
                                                <div class="mt-3 border border-amber-200 rounded-lg overflow-hidden">
                                                    <div class="bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800">
                                                        Klärungsliste ({{ $unmatchedCarryovers->count() }})
                                                    </div>
                                                    @include('year-change.partials._carryover-table', ['rows' => $unmatchedCarryovers])
                                                </div>
                                                <p class="text-xs text-gray-400 mt-2">
                                                    Offen gebliebene Einträge werden beim nächsten Wechsel gelöscht.
                                                </p>
                                            @else
                                                <p class="text-sm text-green-700 mt-2">✅ Alle Vorjahreswerte der verbleibenden Jahrgänge sind zugeordnet.</p>
                                            @endif

                                            {{-- Abgänger: der Abiturjahrgang des Vorjahres. Zu diesen
                                                 Einträgen kann es im neuen Jahr nichts mehr geben — außer
                                                 bei Wiederholern. Deshalb eingeklappt, damit sie die
                                                 echten Klärungsfälle nicht zudecken. --}}
                                            @if ($leaverCarryovers->isNotEmpty())
                                                <details class="mt-3 border border-gray-200 rounded-lg overflow-hidden">
                                                    <summary class="bg-gray-50 px-4 py-2 text-sm font-medium text-gray-600 cursor-pointer select-none">
                                                        Abgänger des Vorjahres ({{ $leaverCarryovers->count() }}) — normalerweise nichts zu tun
                                                    </summary>
                                                    <p class="px-4 py-2 text-xs text-gray-500 bg-gray-50 border-t border-gray-100">
                                                        Diese Schülerinnen und Schüler haben das Abitur abgelegt und die
                                                        Schule verlassen; ihre Vorjahreswerte bleiben unzugeordnet und
                                                        werden beim nächsten Wechsel gelöscht. Zuordnen nur, wenn jemand
                                                        die Jahrgangsstufe wiederholt.
                                                    </p>
                                                    @include('year-change.partials._carryover-table', ['rows' => $leaverCarryovers])
                                                </details>
                                            @endif

                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                <form method="POST" action="{{ route('year-change.run', 6) }}">
                                                    @csrf
                                                    <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800 text-sm font-semibold">
                                                        🔁 Auto-Zuordnung erneut ausführen
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('year-change.complete', 6) }}">
                                                    @csrf
                                                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                                        Zuordnung abschließen
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    @endif
                                @endif

                                {{-- ===== Schritt 7: Vorlagen kopieren ===== --}}
                                @if ($step === 7)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Übernimmt die Schulstammdaten sowie Zeitslot-Vorlagen (inkl. Slots),
                                        Kolloquiums-Zeitslots (Woche 1 &amp; 2), Aufsichtsraster,
                                        Aufsichts-Zeitslots und Aufsichtsvorlagen (inkl. Zuweisungen)
                                        aus dem Vorjahr in die Periode {{ $targetPeriod?->name }}.
                                        Gleichnamige, bereits vorhandene Vorlagen werden übersprungen.
                                        Prüfungstag-Zuordnungen werden <strong>nicht</strong> kopiert —
                                        sie sind datumsgebunden und werden im neuen Jahr neu gesetzt.
                                    </p>

                                    @if ($completed && $result)
                                        <p class="text-sm text-green-700 mt-1">
                                            📋 {{ $result['slot_templates_copied'] ?? 0 }} Zeitslot-Vorlagen,
                                            {{ $result['time_slots_copied'] ?? 0 }} Kolloquiums-Zeitslots,
                                            {{ $result['supervision_time_slots_copied'] ?? 0 }} Aufsichts-Zeitslots und
                                            {{ $result['supervision_templates_copied'] ?? 0 }} Aufsichtsvorlagen kopiert
                                            ({{ ($result['slot_templates_skipped'] ?? 0) + ($result['supervision_templates_skipped'] ?? 0) }} übersprungen)
                                        </p>
                                    @elseif ($isCurrent)
                                        <form method="POST" action="{{ route('year-change.run', 7) }}" class="mt-3">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                                📋 Vorlagen &amp; Zeitslots jetzt übernehmen
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                {{-- ===== Schritt 8: Lehrer aktualisieren ===== --}}
                                @if ($step === 8)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Lehrer-Import mit der aktuellen Personalliste: Stammdaten werden
                                        aktualisiert, neue Lehrkräfte angelegt, nicht mehr enthaltene zur
                                        <strong>Deaktivierung vorgeschlagen</strong> (manuell bestätigt,
                                        nichts wird gelöscht). Wieder auftauchende Lehrkräfte werden reaktiviert.
                                    </p>
                                    @if ($teacherStats !== null && ($completed || $isCurrent))
                                        <div class="mt-2 flex flex-wrap gap-2 text-sm">
                                            <span class="px-2 py-1 bg-green-50 text-green-800 rounded">Aktiv: <strong>{{ $teacherStats['active'] }}</strong></span>
                                            <span class="px-2 py-1 bg-gray-50 text-gray-600 rounded">Inaktiv: <strong>{{ $teacherStats['inactive'] }}</strong></span>
                                        </div>
                                    @endif

                                    @if ($isCurrent)
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <a href="{{ route('teachers.import.form') }}"
                                               class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800 text-sm font-semibold">
                                                → Zum Lehrer-Import
                                            </a>
                                            <form method="POST" action="{{ route('year-change.complete', 8) }}">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                                    Lehrer-Abgleich abgeschlossen
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                @endif

                                {{-- ===== Schritt 9: Abschlussbericht ===== --}}
                                @if ($step === 9)
                                    <p class="text-sm text-gray-500 mt-1">
                                        Zusammenfassung aller Schritte mit Zahlen, offenen Klärungsfällen und
                                        Hinweisen. Mit dem Abschluss wird der Wechsel als erledigt markiert.
                                    </p>
                                    @if ($isCurrent)
                                        <a href="{{ route('year-change.report') }}"
                                           class="inline-block mt-3 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-semibold">
                                            📄 Bericht ansehen &amp; abschließen
                                        </a>
                                    @endif
                                @endif

                            </div>
                        </div>
                    </div>
                @endfor

                {{-- ===== Danger Zone: Wizard zurücksetzen ===== --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-red-200">
                    <h4 class="font-semibold text-red-700 mb-1">Wizard zurücksetzen</h4>
                    <p class="text-sm text-gray-500 mb-3">
                        Bricht den laufenden Wechsel ab und löscht den Fortschritt. Bereits ausgeführte
                        Schritte (Backup, eingefrorene Kennzahlen, gelöschte Rohdaten, kopierte Vorlagen)
                        werden <strong>nicht</strong> rückgängig gemacht.
                    </p>
                    <form method="POST" action="{{ route('year-change.reset') }}">
                        @csrf
                        <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer mb-3">
                            <input type="checkbox" name="confirm_reset" value="1" class="rounded mt-0.5">
                            <span>Ich möchte den Wizard-Fortschritt wirklich zurücksetzen.</span>
                        </label>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-semibold">
                            Zurücksetzen
                        </button>
                    </form>
                </div>

            @endif

        </div>
    </div>
</x-app-layout>
