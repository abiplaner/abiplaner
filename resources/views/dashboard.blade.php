<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            @unlessarchive
            @can('create', \App\Models\ExamPeriod::class)
                <button data-modal-open="createExamPeriodModal"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                    Neuer Prüfungszeitraum
                </button>
            @endcan
            @endarchive
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">


            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ===== IMPORTE (23.11.2026) =====
                 Die drei Dateien, ohne die nicht geplant werden kann. Erscheint,
                 solange eine davon fehlt — nach dem Schuljahreswechsel also wieder. --}}
            @unlessarchive
            @if (auth()->user()->isSchoolPlanner() && ($setupProgress['offen'] ?? 0) > 0)
                <div class="bg-white rounded-xl shadow-sm border border-indigo-200 overflow-hidden">
                    <div class="px-4 py-3 bg-indigo-50 border-b border-indigo-100 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="font-semibold text-indigo-900">
                            📥 Daten einlesen — {{ $setupProgress['erledigt'] }} von {{ $setupProgress['gesamt'] }} erledigt
                            <span class="font-normal text-sm text-indigo-800">· erst die Lehrkräfte, dann der Rest</span>
                        </h3>
                        <a href="{{ route('setup.index') }}" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">
                            Zur Einrichtung →
                        </a>
                    </div>
                    <ol class="divide-y divide-gray-100">
                        @foreach ($importe as $nr => $item)
                            @include('setup.partials._einlesen', ['item' => $item, 'nr' => $nr + 1])
                        @endforeach
                    </ol>
                </div>
            @endif
            @endarchive

            {{-- ===== KEIN PRÜFUNGSZEITRAUM ===== --}}
            @if (!$activeExamPeriod)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <p class="text-yellow-800 font-semibold">Kein Prüfungszeitraum vorhanden</p>
                    <p class="text-yellow-700 mt-1 text-sm">Bitte einen Prüfungszeitraum anlegen, um mit der Planung zu beginnen.</p>
                </div>
            @else

            {{-- ===== HEADER-BANNER ===== --}}
            <div class="rounded-xl border border-gray-200 bg-white px-6 py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                <div>
                    @if ($schoolName)
                        <div class="text-xs font-medium text-gray-400 uppercase tracking-widest mb-1">{{ $schoolName }}</div>
                    @endif
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-2xl font-bold text-gray-900">{{ $activeExamPeriod->name }}</h3>

                        @php
                            $dashPeriodSwitcher = app(\App\Services\CurrentExamPeriodService::class);
                            $dashPeriods = auth()->user()->isCentralAdmin() ? collect() : $dashPeriodSwitcher->all();
                        @endphp
                        @if($dashPeriods->count() > 1)
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button type="button" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600 rounded-md transition">
                                        <span>Schuljahr wechseln</span>
                                        <svg class="h-3.5 w-3.5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                        Prüfungsjahrgang
                                    </div>
                                    @foreach($dashPeriods as $dashP)
                                        <form method="POST" action="{{ route('exam-periods.switch') }}">
                                            @csrf
                                            <input type="hidden" name="exam_period_id" value="{{ $dashP->id }}">
                                            <x-dropdown-link :href="route('exam-periods.switch')" data-submit-parent class="flex items-center justify-between">
                                                <span>Abitur {{ $dashP->year }}</span>
                                                @if($dashP->id === $activeExamPeriod->id)
                                                    <span class="text-emerald-600 font-bold text-xs">✓</span>
                                                @elseif($dashPeriodSwitcher->latest() && $dashP->id !== $dashPeriodSwitcher->latest()->id)
                                                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">Archiv</span>
                                                @endif
                                            </x-dropdown-link>
                                        </form>
                                    @endforeach
                                </x-slot>
                            </x-dropdown>
                        @endif
                    </div>
                </div>
                @can('update', $activeExamPeriod)
                    <a href="{{ route('planning.index') }}"
                       class="shrink-0 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition text-sm shadow-sm">
                        Zur Kolloquiums-Planung →
                    </a>
                @endcan
            </div>

            {{-- ===== STATISTIK-ZEILE: STAMMDATEN ===== --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @php
                    $baseStats = [
                        ['label' => 'Schülerinnen & Schüler', 'value' => $stats['total_students'] ?? 0, 'icon' => '🎓'],
                        ['label' => 'Kurse',                  'value' => $stats['total_courses']  ?? 0, 'icon' => '📚'],
                        ['label' => 'Lehrkräfte',             'value' => $stats['total_teachers'] ?? 0, 'icon' => '👩‍🏫'],
                        ['label' => 'Räume',                  'value' => $stats['total_rooms']    ?? 0, 'icon' => '🚪'],
                    ];
                @endphp
                @foreach ($baseStats as $s)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
                        <span class="text-2xl">{{ $s['icon'] }}</span>
                        <div>
                            <div class="text-xs text-gray-500 font-medium">{{ $s['label'] }}</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $s['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ===== PRÜFUNGS-FORTSCHRITT ===== --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                {{-- Mündliche Prüfungen --}}
                @php
                    $oralTotal   = $stats['oral_exams']    ?? 0;
                    $oralDone    = $stats['oral_planned']   ?? 0;
                    $oralPct     = $oralTotal > 0 ? round($oralDone / $oralTotal * 100) : 0;
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-semibold text-gray-700">Kolloquien</span>
                        <span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full font-medium">mündlich</span>
                    </div>
                    <div class="flex items-end gap-3 mb-3">
                        <span class="text-3xl font-bold text-gray-900">{{ $oralDone }}</span>
                        <span class="text-sm text-gray-400 mb-1">/ {{ $oralTotal }} geplant</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $oralPct === 100 ? 'bg-green-500' : 'bg-blue-500' }}"
                             style="width: {{ $oralPct }}%"></div>
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-gray-500">
                        <span>{{ $oralPct }}% vergeben</span>
                        <a href="{{ route('planning.index') }}" class="text-blue-600 hover:underline">Zur Planung →</a>
                    </div>
                </div>

                {{-- Schriftliche Prüfungen --}}
                @php
                    $wrTotal         = $stats['written_exams']          ?? 0;
                    $wrSched         = $stats['written_scheduled']       ?? 0;
                    $wrSeated        = $stats['written_seated']          ?? 0;
                    $wrStudentsTotal = $stats['written_students_total']  ?? 0;
                    $wrPct           = $wrTotal > 0 ? round($wrSched / $wrTotal * 100) : 0;
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-semibold text-gray-700">Schriftliche Prüfungen</span>
                        <span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full font-medium">schriftlich</span>
                    </div>
                    <div class="flex items-end gap-3 mb-3">
                        <span class="text-3xl font-bold text-gray-900">{{ $wrSched }}</span>
                        <span class="text-sm text-gray-400 mb-1">/ {{ $wrTotal }} mit Termin</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
                        <div class="h-2 rounded-full {{ $wrPct === 100 ? 'bg-green-500' : 'bg-purple-500' }}"
                             style="width: {{ $wrPct }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>{{ $wrSeated }} von {{ $wrStudentsTotal }} Schülern vollständig eingeteilt</span>
                        <a href="{{ route('written-exams.dates.index') }}" class="text-purple-600 hover:underline">Übersicht →</a>
                    </div>
                </div>

                {{-- Zusatzprüfungen --}}
                @php
                    $zpTotal   = $stats['zp_total']   ?? 0;
                    $zpPlaced  = $stats['zp_placed']  ?? 0;
                    $zpPending = $stats['zp_pending'] ?? 0;
                    $zpPct     = $zpTotal > 0 ? round($zpPlaced / $zpTotal * 100) : 0;
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-semibold text-gray-700">Zusatzprüfungen</span>
                        <span class="text-xs px-2 py-0.5 bg-orange-100 text-orange-700 rounded-full font-medium">ZP</span>
                    </div>
                    @if ($zpTotal === 0)
                        <div class="text-sm text-gray-400 mt-2">Keine Zusatzprüfungen angelegt.</div>
                    @else
                        <div class="flex items-end gap-3 mb-3">
                            <span class="text-3xl font-bold text-gray-900">{{ $zpPlaced }}</span>
                            <span class="text-sm text-gray-400 mb-1">/ {{ $zpTotal }} platziert</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
                            <div class="h-2 rounded-full {{ $zpPct === 100 ? 'bg-green-500' : 'bg-orange-500' }}"
                                 style="width: {{ $zpPct }}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>{{ $zpPending }} noch ausstehend</span>
                            <a href="{{ route('zusatzpruefungen.index') }}" class="text-orange-600 hover:underline">Zur Planung →</a>
                        </div>
                    @endif
                </div>

            </div>

            {{-- ===== ZWEI SPALTEN: KALENDER + DATENSICHERUNG / SCHNELLZUGRIFF ===== --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- KALENDER: nächste Ereignisse (2/3 Breite) --}}
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5 min-w-0">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Nächste 30 Tage</h3>

                    @if ($upcomingEvents->isEmpty())
                        <p class="text-sm text-gray-400">Keine Termine in den nächsten 30 Tagen.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($upcomingEvents as $ev)
                                @php
                                    $today  = \Carbon\Carbon::today();
                                    $isNow  = $ev['date'] <= $today && ($ev['end_date'] === null || $ev['end_date'] >= $today);
                                    $isSoon = !$isNow && $ev['date']->diffInDays($today) <= 7;
                                    $dateLabel = $ev['end_date'] && $ev['end_date']->ne($ev['date'])
                                        ? $ev['date']->format('d.m.') . ' – ' . $ev['end_date']->format('d.m.Y')
                                        : $ev['date']->format('d.m.Y');
                                @endphp
                                <div class="flex items-start gap-3 py-2 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                                    {{-- Farbpunkt --}}
                                    <span class="shrink-0 w-2.5 h-2.5 rounded-full mt-1"
                                          style="background-color: {{ $ev['color'] ?? '#6b7280' }}"></span>
                                    {{-- Datum --}}
                                    <span class="shrink-0 text-xs font-mono text-gray-500 w-28 pt-0.5">{{ $dateLabel }}</span>
                                    {{-- Titel --}}
                                    <span class="text-sm text-gray-800 flex-1 font-medium leading-snug min-w-0">{{ $ev['title'] }}</span>
                                    {{-- Badges --}}
                                    <div class="shrink-0 flex flex-col items-end gap-1">
                                        @if ($isNow)
                                            <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">läuft</span>
                                        @elseif ($isSoon)
                                            <span class="text-xs px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full font-medium">bald</span>
                                        @endif
                                        <span class="text-xs text-gray-400">{{ $ev['type_name'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- RECHTE SPALTE: Datensicherung + Schnellzugriff --}}
                <div class="flex flex-col gap-4">

                    {{-- Datensicherung --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">💾 Datensicherung</h3>
                        @if ($lastBackup)
                            @php
                                $backupAge = \Carbon\Carbon::parse($lastBackup['created_at'] ?? null);
                                $daysOld   = (int) $backupAge->diffInDays(\Carbon\Carbon::now());
                                $hoursOld  = (int) $backupAge->diffInHours(\Carbon\Carbon::now()) % 24;
                                $ageLabel  = $daysOld > 0
                                    ? $daysOld . ' Tag' . ($daysOld === 1 ? '' : 'en') . ($hoursOld > 0 ? ', ' . $hoursOld . ' Std.' : '')
                                    : ($hoursOld > 0 ? $hoursOld . ' Std.' : 'gerade eben');
                            @endphp
                            <div class="text-xs text-gray-500 mb-1">Letztes Backup</div>
                            <div class="text-sm font-medium text-gray-800 mb-0.5">
                                {{ $backupAge->format('d.m.Y, H:i') }} Uhr
                            </div>
                            <div class="text-xs mb-3 {{ $daysOld <= 1 ? 'text-green-600' : ($daysOld <= 7 ? 'text-yellow-600' : 'text-red-600') }}">
                                vor {{ $ageLabel }}
                            </div>
                        @else
                            <div class="text-sm text-red-600 mb-3 font-medium">⚠ Kein Backup vorhanden</div>
                        @endif
                        <a href="{{ route('backups.index') }}"
                           class="block w-full text-center bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            Backup-Verwaltung →
                        </a>
                    </div>

                    {{-- Schnellzugriff --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Schnellzugriff</h3>
                        <div class="space-y-2">
                            <a href="{{ route('planning.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 text-sm font-medium transition">
                                🗓 Kolloquium-Planung
                            </a>
                            <a href="{{ route('written-exams.dates.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-800 text-sm font-medium transition">
                                ✏️ Schriftliche Prüfungen
                            </a>
                            <a href="{{ route('zusatzpruefungen.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg bg-orange-50 hover:bg-orange-100 text-orange-800 text-sm font-medium transition">
                                📋 Zusatzprüfungen
                            </a>
                            <a href="{{ route('students.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm transition">
                                🎓 Schülerliste
                            </a>
                            <a href="{{ route('courses.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm transition">
                                📚 Kurse
                            </a>
                            <a href="{{ route('teachers.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm transition">
                                👩‍🏫 Lehrkräfte
                            </a>
                            @unlessarchive
                            <a href="{{ route('year-change.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm transition">
                                🔄 Schuljahreswechsel
                            </a>
                            @endarchive
                        </div>
                    </div>

                </div>
            </div>

            {{-- ===== ALLE PRÜFUNGSZEITRÄUME ===== --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Alle Prüfungszeiträume</h3>
                <div class="space-y-2">
                    @foreach ($examPeriods as $period)
                        <div class="flex items-center justify-between rounded-lg px-4 py-3 border
                            {{ $period->id === $activeExamPeriod?->id ? 'bg-blue-50 border-blue-200' : 'border-gray-100 hover:bg-gray-50' }}">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">{{ $period->name }}</span>
                                    @if ($period->id === $activeExamPeriod?->id)
                                        <span class="text-xs px-2 py-0.5 bg-blue-600 text-white rounded-full">Aktiv</span>
                                    @endif
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        {{ ($period->status ?? 'planning') === 'planning' ? 'bg-yellow-100 text-yellow-800' :
                                           (($period->status ?? '') === 'locked'   ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600') }}">
                                        {{ ($period->status ?? 'planning') === 'planning' ? 'In Planung' :
                                           (($period->status ?? '') === 'locked' ? 'Gesperrt' : 'Abgeschlossen') }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    KW1: {{ $period->week_1_start->format('d.m.Y') }} – {{ $period->week_1_end->format('d.m.Y') }} &nbsp;|&nbsp;
                                    KW2: {{ $period->week_2_start->format('d.m.Y') }} – {{ $period->week_2_end->format('d.m.Y') }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @unlessarchive
                                @if (($period->status ?? 'planning') === 'planning' && $period->id !== $activeExamPeriod?->id)
                                    @can('update', $period)
                                        <form action="{{ route('exam-periods.activate', $period) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="px-3 py-1 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700"
                                                    data-confirm="Diesen Prüfungszeitraum aktivieren?">
                                                Aktivieren
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                                @can('update', $period)
                                    <button data-edit-period="{{ $period->id }}"
                                            class="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded-md hover:bg-gray-300">
                                        Bearbeiten
                                    </button>
                                @endcan
                                @can('delete', $period)
                                    <form action="{{ route('exam-periods.destroy', $period) }}" method="POST" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1 bg-red-100 text-red-700 text-xs rounded-md hover:bg-red-200"
                                                data-confirm="Prüfungszeitraum wirklich löschen? Alle Daten werden gelöscht!">
                                            Löschen
                                        </button>
                                    </form>
                                @endcan
                                @endarchive
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @endif {{-- end if $activeExamPeriod --}}
        </div>
    </div>

    {{-- ===== MODAL: PRÜFUNGSZEITRAUM BEARBEITEN ===== --}}
    <div id="editExamPeriodModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Prüfungszeitraum bearbeiten</h3>
                <button data-modal-close="editExamPeriodModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="editExamPeriodForm" method="POST">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" id="edit_name" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jahr</label>
                        <input type="number" name="year" id="edit_year" required min="2024" max="2099"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 1 Start</label>
                        <input type="date" name="week_1_start" id="edit_week_1_start" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 1 Ende</label>
                        <input type="date" name="week_1_end" id="edit_week_1_end" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 2 Start</label>
                        <input type="date" name="week_2_start" id="edit_week_2_start" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 2 Ende</label>
                        <input type="date" name="week_2_end" id="edit_week_2_end" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" data-modal-close="editExamPeriodModal"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== MODAL: NEUER PRÜFUNGSZEITRAUM ===== --}}
    <div id="createExamPeriodModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Neuer Prüfungszeitraum</h3>
                <button data-modal-close="createExamPeriodModal"
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="{{ route('exam-periods.store') }}" method="POST">
                @csrf
                @if ($zeitraumVorschlag)
                    <p class="mb-4 text-sm text-gray-600 bg-blue-50 border border-blue-100 rounded-md px-3 py-2">
                        Vorbelegt mit den Kolloquiumswochen aus der Bekanntmachung des Kultusministeriums
                        für das {{ $zeitraumVorschlag['name'] }}.
                    </p>
                @endif
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" required value="{{ $zeitraumVorschlag['name'] ?? '' }}"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               placeholder="z.B. Abitur 2027">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jahr</label>
                        <input type="number" name="year" required value="{{ $zeitraumVorschlag['year'] ?? date('Y') + 1 }}" min="2024" max="2099"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 1 Start</label>
                        <input type="date" name="week_1_start" required value="{{ $zeitraumVorschlag['week_1_start'] ?? '' }}"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 1 Ende</label>
                        <input type="date" name="week_1_end" required value="{{ $zeitraumVorschlag['week_1_end'] ?? '' }}"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 2 Start</label>
                        <input type="date" name="week_2_start" required value="{{ $zeitraumVorschlag['week_2_start'] ?? '' }}"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Woche 2 Ende</label>
                        <input type="date" name="week_2_end" required value="{{ $zeitraumVorschlag['week_2_end'] ?? '' }}"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button"
                            data-modal-close="createExamPeriodModal"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                        Erstellen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script @cspNonce>
    const examPeriods = @json($examPeriods->keyBy('id'));

    // Frueher onclick am Knopf (Modul 7.2).
    document.querySelectorAll('[data-edit-period]').forEach((knopf) => {
        knopf.addEventListener('click', () => editExamPeriod(Number(knopf.dataset.editPeriod)));
    });

    function editExamPeriod(periodId) {
        const period = examPeriods[periodId];
        if (!period) return;
        const fmt = (d) => d ? (d.match(/^\d{4}-\d{2}-\d{2}$/) ? d : new Date(d).toISOString().split('T')[0]) : '';
        document.getElementById('editExamPeriodForm').action = `/exam-periods/${periodId}`;
        document.getElementById('edit_name').value         = period.name  || '';
        document.getElementById('edit_year').value         = period.year  || '';
        document.getElementById('edit_week_1_start').value = fmt(period.week_1_start);
        document.getElementById('edit_week_1_end').value   = fmt(period.week_1_end);
        document.getElementById('edit_week_2_start').value = fmt(period.week_2_start);
        document.getElementById('edit_week_2_end').value   = fmt(period.week_2_end);
        document.getElementById('editExamPeriodModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editExamPeriodModal').classList.add('hidden');
    }

    document.getElementById('editExamPeriodModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeEditModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeEditModal();
            document.getElementById('createExamPeriodModal').classList.add('hidden');
        }
    });
    </script>
</x-app-layout>
