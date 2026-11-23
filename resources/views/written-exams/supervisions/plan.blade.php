<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Aufsichtsplanung:') }}
                    @if($isCollectiveDay)
                        Sammeltermin {{ $writtenExamYearDate->slot->requirement_level === 'erhoeht' ? 'eA' : 'gA' }}
                    @else
                        {{ $writtenExamYearDate->subject?->name ?? '–' }}
                    @endif
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ \Carbon\Carbon::parse($writtenExamYearDate->date)->locale('de')->isoFormat('dddd, DD.MM.YYYY') }}
                </p>
                <div class="flex flex-col gap-y-0.5 mt-1">
                    @foreach($yearDatesWithSlots as $yd)
                        @php
                            $subjectNames = $yd->slot->subjects->map(fn($s) => $s->code ?: $s->name)->implode(', ');
                            $level        = $yd->slot->requirement_level === 'erhoeht' ? 'eA' : 'gA';
                            $startFmt     = \Carbon\Carbon::parse($yd->slot->start_time)->format('H:i');
                            $endFmt       = \Carbon\Carbon::parse($yd->slot->end_time)->format('H:i');
                        @endphp
                        <span class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-medium">{{ $subjectNames }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-0.5">({{ $level }})</span>
                            <span class="text-gray-400 dark:text-gray-500 mx-1">·</span>
                            {{ $startFmt }}–{{ $endFmt }} Uhr
                            @if($maxNtaPercent > 0)
                                @php $ntaEnd = $yd->slot->getEndTimeWithNta($maxNtaPercent)->format('H:i'); @endphp
                                <span class="text-amber-500 dark:text-amber-400 text-xs ml-1" title="NTA {{ $maxNtaPercent }} %">
                                    (NTA bis {{ $ntaEnd }})
                                </span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('written-exams.exports.supervisions-plan-pdf', $writtenExamYearDate) }}"
                   target="_blank"
                   class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </a>
                <a href="{{ route('written-exams.exports.supervisions-plan-excel', $writtenExamYearDate) }}"
                   class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-200 dark:bg-green-900/30 dark:hover:bg-green-900/50 text-green-700 dark:text-green-300 text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Excel
                </a>
                <a href="{{ route('written-exams.supervisions.time-slots.index') }}"
                   class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Zeitslots
                </a>
                <a href="{{ route('written-exams.supervisions.index') }}"
                   class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Übersicht
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Flash-Meldungen --}}

            {{-- ===== PRÜFUNGSZEITEN-ABDECKUNG PRÜFEN ===== --}}
            @php
                // Nur Slots aus tatsächlich verwendeten Rastern für Coverage-Check
                $relevantSlots  = $timeSlots->filter(fn($ts) => $usedGridIds->contains($ts->written_exam_supervision_grid_id));
                $slotStartTimes = $relevantSlots->map(fn($ts) => \Carbon\Carbon::parse($ts->start_time));
                $slotEndTimes   = $relevantSlots->map(fn($ts) => \Carbon\Carbon::parse($ts->end_time));
                $minSlotStart   = $slotStartTimes->sort()->first();
                $maxSlotEnd     = $slotEndTimes->sortDesc()->first();
                $coverageOk     = $relevantSlots->isNotEmpty()
                    && $minSlotStart !== null && $minSlotStart->lte($examStartTime)
                    && $maxSlotEnd   !== null && $maxSlotEnd->gte($examEndTime);
                $ntaCoverageOk  = $relevantSlots->isNotEmpty()
                    && $minSlotStart !== null && $minSlotStart->lte($examStartTime)
                    && $maxSlotEnd   !== null && $maxSlotEnd->gte($examEndTimeWithMaxNta);
            @endphp
            @if(!$timeSlots->isEmpty())
                @if(!$coverageOk)
                    <div class="flex items-start gap-3 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 rounded-lg text-sm">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <span>
                            <strong>Zeitslots decken die Prüfungszeit nicht ab!</strong>
                            Prüfung: {{ $examStartTime->format('H:i') }}–{{ $examEndTime->format('H:i') }} Uhr –
                            der letzte Zeitslot endet zu früh ({{ \Carbon\Carbon::parse($maxSlotEnd)->format('H:i') }} Uhr).
                            Bitte Zeitslots anpassen.
                        </span>
                    </div>
                @elseif($maxNtaPercent > 0 && !$ntaCoverageOk)
                    <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300 rounded-lg text-sm">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <span>
                            <strong>NTA-Verlängerung nicht abgedeckt!</strong>
                            Prüfung mit NTA ({{ $maxNtaPercent }}&nbsp;%) endet um {{ $examEndTimeWithMaxNta->format('H:i') }} Uhr –
                            der letzte Zeitslot endet um {{ \Carbon\Carbon::parse($maxSlotEnd)->format('H:i') }} Uhr.
                            Bitte einen Zeitslot bis mindestens {{ $examEndTimeWithMaxNta->format('H:i') }} Uhr anlegen.
                        </span>
                    </div>
                @else
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 rounded-lg text-sm">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Zeitslots decken die Prüfungszeit
                        ({{ $examStartTime->format('H:i') }}–{{ $examEndTimeWithMaxNta->format('H:i') }} Uhr{{ $maxNtaPercent > 0 ? ' inkl. NTA' : '' }})
                        vollständig ab.
                    </div>
                @endif
            @endif

            {{-- ===== AUFSICHTSRASTER ===== --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border dark:border-gray-700"
                 x-data="gridManager()">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors rounded-lg">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Aufsichtsraster</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500" x-text="grids.length + ' Raster · Raster-Zuweisung pro Raum im Tabellenkopf'"></span>
                    </div>
                    <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" x-collapse class="border-t dark:border-gray-700 px-5 py-4 space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Raster definieren Gruppen von Zeitslots. Jeder Prüfungsraum kann pro Tag einem Raster zugewiesen werden –
                        dann werden im Aufsichtsplan nur die passenden Zeitslots für diesen Raum angezeigt.
                    </p>

                    {{-- Bestehende Raster --}}
                    <div class="flex flex-wrap gap-2">
                        <template x-for="grid in grids" :key="grid.id">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium border"
                                  :class="gridBadgeClass(grid)">
                                <span class="w-2.5 h-2.5 rounded-full" :class="gridDotClass(grid)"></span>
                                <span x-text="grid.name"></span>
                                <template x-if="grid.is_foreign_language">
                                    <span class="text-xs opacity-75" title="Nur bei Fremdsprachen-Prüfungen sichtbar">🌍</span>
                                </template>
                                <span class="text-xs opacity-60" x-text="'(' + (grid.time_slots_count ?? 0) + ' Slots)'"></span>
                                <template x-if="!grid.is_default">
                                    <button @click="deleteGrid(grid)" class="ml-0.5 hover:text-red-500 font-bold text-lg leading-none">&times;</button>
                                </template>
                                <template x-if="grid.is_default">
                                    <span class="text-xs italic opacity-50">Standard</span>
                                </template>
                            </span>
                        </template>
                    </div>

                    {{-- Neues Raster anlegen --}}
                    <div class="flex items-end gap-3 flex-wrap pt-2 border-t dark:border-gray-700">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name *</label>
                            <input type="text" x-model="newGrid.name" placeholder="z.B. Hörverstehen Englisch"
                                   class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm py-1.5 px-3 w-56">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Farbe</label>
                            <select x-model="newGrid.color" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm py-1.5 px-2">
                                <option value="blue">Blau</option>
                                <option value="green">Grün</option>
                                <option value="orange">Orange</option>
                                <option value="purple">Lila</option>
                                <option value="red">Rot</option>
                                <option value="yellow">Gelb</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <input type="checkbox" x-model="newGrid.is_foreign_language" id="newGridFl"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
                            <label for="newGridFl" class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer" title="Raster nur bei Prüfungen mit moderner Fremdsprache einblenden">
                                🌍 Nur bei Fremdsprachen
                            </label>
                        </div>
                        <button @click="createGrid()"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg transition-colors">
                            + Raster anlegen
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        💡 Zeitslots zu Rastern zuordnen: <a href="{{ route('written-exams.supervisions.time-slots.index') }}" class="underline">Zeitslots verwalten</a>
                    </p>
                </div>
            </div>

            {{-- ===== ZUSÄTZLICHE AUFSICHTSPOSITIONEN (terminspezifisch) ===== --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border dark:border-gray-700" x-data="{ open: false }">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors rounded-lg">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Zusätzliche Aufsichtspositionen</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            Gangaufsicht &amp; Reserve sind immer dabei
                            @if($centralTypes->isNotEmpty())
                                &middot; {{ $centralTypes->count() }} extra
                            @endif
                        </span>
                    </div>
                    <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" x-collapse class="border-t dark:border-gray-700 px-5 py-4 space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <strong>Gangaufsicht</strong> und <strong>Reserve (Lehrerzimmer)</strong> sind immer als feste Spalten vorhanden.
                        Bei größeren Prüfungen kannst du hier zusätzliche Positionen für diesen Termin anlegen,
                        z.&nbsp;B. „2. Gangaufsicht", „Eingang Süd". Diese gelten nur für diesen Prüfungstermin.
                    </p>

                    {{-- Bestehende Extras --}}
                    @if($centralTypes->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($centralTypes as $type)
                                @php
                                    $colorMap = [
                                        'orange' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 border-orange-200 dark:border-orange-700',
                                        'teal'   => 'bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300 border-teal-200 dark:border-teal-700',
                                        'pink'   => 'bg-pink-100 dark:bg-pink-900/30 text-pink-800 dark:text-pink-300 border-pink-200 dark:border-pink-700',
                                        'yellow' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border-yellow-200 dark:border-yellow-700',
                                        'blue'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-700',
                                        'purple' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 border-purple-200 dark:border-purple-700',
                                    ];
                                    $colorClass = $colorMap[$type->color] ?? $colorMap['orange'];
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border {{ $colorClass }}">
                                    {{ $type->name }}
                                    @unlessarchive
                                    <form method="POST"
                                          action="{{ route('written-exams.supervisions.central-types.destroy', [$writtenExamYearDate, $type]) }}"
                                          data-confirm="Position „{{ $type->name }}“ und alle Zuteilungen für diesen Termin löschen?"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-0.5 hover:text-red-500 font-bold text-lg leading-none">&times;</button>
                                    </form>
                                    @endarchive
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">Keine zusätzlichen Positionen – Gangaufsicht und Reserve reichen für diesen Termin.</p>
                    @endif

                    {{-- Neue Position anlegen --}}
                    @unlessarchive
                    <form method="POST"
                          action="{{ route('written-exams.supervisions.central-types.store', $writtenExamYearDate) }}"
                          class="flex items-end gap-3 flex-wrap">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Bezeichnung</label>
                            <input type="text" name="name" placeholder="z.B. 2. Gangaufsicht" required maxlength="100"
                                   class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm py-1.5 px-3 w-56 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Farbe</label>
                            <select name="color" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm py-1.5 px-2">
                                <option value="orange">Orange</option>
                                <option value="teal">Grün-Blau</option>
                                <option value="pink">Pink</option>
                                <option value="yellow">Gelb</option>
                                <option value="blue">Blau</option>
                                <option value="purple">Lila</option>
                            </select>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Hinzufügen
                        </button>
                    </form>
                    @endarchive
                </div>
            </div>

            {{-- Keine Raumzuordnungen --}}
            @if($assignments->isEmpty())
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-6 text-center">
                    <p class="text-yellow-800 dark:text-yellow-300 font-medium">Noch keine Raumzuordnungen vorhanden.</p>
                    <p class="text-yellow-600 dark:text-yellow-400 text-sm mt-1">Bitte zuerst Räume und Kurse unter „Raumzuordnungen" zuweisen.</p>
                    <a href="{{ route('written-exams.dates.day-assign', ['day' => $writtenExamYearDate->date->format('Y-m-d')]) }}"
                       class="inline-block mt-3 px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm">
                        Zu den Raumzuordnungen
                    </a>
                </div>
            @else

            {{-- Legende für Farbmarkierungen --}}
            <div class="flex flex-wrap items-center gap-3 px-1 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-600 dark:text-gray-300">Farben der Aufsichten:</span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300 border border-teal-200 dark:border-teal-700">
                    <span class="w-2 h-2 rounded-full bg-teal-500"></span> Q13-Unterricht (Prüfung)
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-700">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span> Freistunde (war vorher da)
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Freistunde
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-700">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span> Anderer Unterricht!
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-500">
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span> Kein Stundenplan
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-800 dark:text-violet-300 border border-violet-200 dark:border-violet-700">
                    <span class="w-2 h-2 rounded-full bg-violet-500"></span> Studienreferendar/-in
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-300 dark:border-slate-500">
                    <span class="w-2 h-2 rounded-full bg-slate-400"></span> Ohne Amtsbezeichnung
                </span>
            </div>

            @if($timeSlots->isEmpty())
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-blue-800 dark:text-blue-300 text-sm">
                        Noch keine Zeitslots definiert.
                        <a href="{{ route('written-exams.supervisions.time-slots.index') }}" class="underline font-medium">Jetzt anlegen</a>
                    </p>
                </div>
            @endif

            @php
                $rooms = $assignmentsByRoom->map(function($roomAssignments) {
                    return [
                        'room'        => $roomAssignments->first()->room,
                        'assignments' => $roomAssignments,
                    ];
                })->values();

                // Farb-Mapping
                $colorBadge = [
                    'orange' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 border-orange-200 dark:border-orange-700',
                    'teal'   => 'bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300 border-teal-200 dark:border-teal-700',
                    'pink'   => 'bg-pink-100 dark:bg-pink-900/30 text-pink-800 dark:text-pink-300 border-pink-200 dark:border-pink-700',
                    'yellow' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border-yellow-200 dark:border-yellow-700',
                    'blue'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-700',
                    'purple' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 border-purple-200 dark:border-purple-700',
                ];
                $colorHeader = [
                    'orange' => 'text-orange-700 dark:text-orange-300',
                    'teal'   => 'text-teal-700 dark:text-teal-300',
                    'pink'   => 'text-pink-700 dark:text-pink-300',
                    'yellow' => 'text-yellow-700 dark:text-yellow-300',
                    'blue'   => 'text-blue-700 dark:text-blue-300',
                    'purple' => 'text-purple-700 dark:text-purple-300',
                ];
                $groupBgMap = ['blue'=>'bg-blue-50 dark:bg-blue-900/20','green'=>'bg-green-50 dark:bg-green-900/20','orange'=>'bg-orange-50 dark:bg-orange-900/20','purple'=>'bg-purple-50 dark:bg-purple-900/20','red'=>'bg-red-50 dark:bg-red-900/20','yellow'=>'bg-yellow-50 dark:bg-yellow-900/20'];
                $groupDot   = ['blue'=>'bg-blue-500','green'=>'bg-green-500','orange'=>'bg-orange-500','purple'=>'bg-purple-500','red'=>'bg-red-500','yellow'=>'bg-yellow-400'];
                $groupBorder= ['blue'=>'border-blue-200 dark:border-blue-700','green'=>'border-green-200 dark:border-green-700','orange'=>'border-orange-200 dark:border-orange-700','purple'=>'border-purple-200 dark:border-purple-700','red'=>'border-red-200 dark:border-red-700','yellow'=>'border-yellow-200 dark:border-yellow-700'];

                // Farb-Klassen für Lehrer-Kategorie-Badges
                $catBadge = [
                    'q13_lesson'        => 'bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300 border border-teal-200 dark:border-teal-700',
                    'free_already_there'=> 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-700',
                    'free'              => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-700',
                    'has_lesson'        => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-700',
                    'trainee'           => 'bg-violet-100 dark:bg-violet-900/30 text-violet-800 dark:text-violet-300 border border-violet-200 dark:border-violet-700',
                    'no_title'          => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-300 dark:border-slate-500',
                    'unknown'           => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-500',
                ];
                $catTitle = [
                    'q13_lesson'        => 'Q13-Unterricht – Klasse schreibt die Prüfung',
                    'free_already_there'=> 'Freistunde – war vorher in der Schule',
                    'free'              => 'Freistunde',
                    'has_lesson'        => '⚠ Hat anderen Unterricht!',
                    'trainee'           => 'Studienreferendar/-in',
                    'no_title'          => 'Ohne Amtsbezeichnung',
                    'unknown'           => 'Kein Stundenplan hinterlegt',
                ];

                // Slots nach Raster gruppieren
                $slotsByGridId = $timeSlots->groupBy(fn($s) => $s->written_exam_supervision_grid_id ?? 0);

                // Räume nach Raster gruppieren
                $roomsByGrid = [];
                foreach ($rooms as $roomData) {
                    $fa  = $roomData['assignments']->first();
                    $gid = $fa?->written_exam_supervision_grid_id ?? $defaultGridId ?? 0;
                    $roomsByGrid[$gid][] = $roomData;
                }

                // Alle Grid-IDs die Slots ODER Räume haben
                $allGridIds = collect(array_keys($roomsByGrid))
                    ->merge($slotsByGridId->keys())
                    ->unique()
                    ->sortBy(fn($id) => $id == $defaultGridId ? 0 : 1)
                    ->values();
            @endphp

            {{-- ===== PRO RASTER EIN BLOCK ===== --}}
            <div class="space-y-6">
            @foreach($allGridIds as $gid)
                @php
                    $grid      = $grids->firstWhere('id', $gid);
                    $gc        = $grid?->color ?? 'blue';
                    $gridSlots = $slotsByGridId->get($gid, collect())->sortBy('slot_number');
                    $gridRooms = $roomsByGrid[$gid] ?? [];
                    $slotIds   = $gridSlots->pluck('id');
                    $slotRows  = $gridSlots->isNotEmpty() ? $gridSlots : collect([null]);

                    // Fremdsprachen-Raster nur anzeigen wenn heute eine Fremdsprache geprüft wird
                    if (($grid?->is_foreign_language) && !$hasForeignLanguage) continue;
                @endphp

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border {{ $groupBorder[$gc] ?? 'border-gray-200 dark:border-gray-700' }}">

                    {{-- Raster-Header --}}
                    <div class="px-5 py-3 flex items-center gap-3 {{ $groupBgMap[$gc] ?? 'bg-gray-50' }} border-b {{ $groupBorder[$gc] ?? 'border-gray-200 dark:border-gray-700' }}">
                        <span class="w-3 h-3 rounded-full {{ $groupDot[$gc] ?? 'bg-gray-400' }}"></span>
                        <span class="font-semibold text-gray-800 dark:text-gray-100 text-sm">{{ $grid?->name ?? 'Standard-Raster' }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $gridSlots->count() }} {{ $gridSlots->count() === 1 ? 'Zeitslot' : 'Zeitslots' }}
                            &middot; {{ count($gridRooms) }} {{ count($gridRooms) === 1 ? 'Raum' : 'Räume' }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700/50">
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 border-b dark:border-gray-600 w-44 min-w-44">Raum</th>
                                    @if($gridSlots->isEmpty())
                                        <th class="px-3 py-2.5 text-xs text-gray-400 dark:text-gray-500 border-b dark:border-gray-600 border-l dark:border-l-gray-600 min-w-44 font-normal italic">(keine Zeitslots in diesem Raster)</th>
                                    @else
                                        @foreach($gridSlots as $slot)
                                            @php
                                                $tsEnd     = \Carbon\Carbon::parse($slot->end_time);
                                                $covers    = $tsEnd->gte($examEndTime);
                                                $coversNta = $tsEnd->gte($examEndTimeWithMaxNta);
                                                $dotColor  = !$covers
                                                    ? 'bg-red-400'
                                                    : ($maxNtaPercent > 0 && !$coversNta ? 'bg-amber-400' : 'bg-green-400');
                                                $dotTitle  = !$covers
                                                    ? 'Endet vor Prüfungsende (' . $examEndTime->format('H:i') . ')'
                                                    : ($maxNtaPercent > 0 && !$coversNta
                                                        ? 'Endet vor NTA-Ende (' . $examEndTimeWithMaxNta->format('H:i') . ')'
                                                        : 'Deckt Prüfungszeit ab');
                                            @endphp
                                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-600 border-l dark:border-l-gray-600 min-w-44">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="{{ !$covers ? 'background:#f87171' : ($maxNtaPercent > 0 && !$coversNta ? 'background:#fbbf24' : 'background:#4ade80') }}" title="{{ $dotTitle }}"></span>
                                                    <span class="font-semibold whitespace-nowrap">{{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}</span>
                                                </div>
                                                @if($slot->label)<div class="text-xs text-gray-400 dark:text-gray-500 font-normal mt-0.5 ml-3.5">{{ $slot->label }}</div>@endif
                                            </th>
                                        @endforeach
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700">

                                {{-- Raumzeilen --}}
                                @foreach($gridRooms as $roomData)
                                    @php
                                        $room            = $roomData['room'];
                                        $roomAssignments = $roomData['assignments'];
                                        $firstAssignment = $roomAssignments->first();
                                        $roomGridId      = $firstAssignment?->written_exam_supervision_grid_id ?? $defaultGridId;
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                                        <td class="px-4 py-3 align-top bg-white dark:bg-gray-800">
                                            <div class="font-bold text-blue-700 dark:text-blue-300 text-sm">{{ $room->room_number ?? "Raum {$room->id}" }}</div>
                                            @if($roomAssignments->some(fn($a) => $a->is_hoerverstaendnis))
                                                <span class="inline-flex items-center gap-1 mt-0.5 px-1.5 py-0.5 rounded text-xs font-semibold bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-700">
                                                    🎧 HV
                                                </span>
                                            @endif
                                            <select class="mt-1 w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded"
                                                    data-room-grid-for="{{ $firstAssignment?->id ?? '' }}">
                                                @foreach($grids as $g)
                                                    <option value="{{ $g->id }}" {{ $roomGridId == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                                                @endforeach
                                            </select>
                                            @foreach($roomAssignments as $a)
                                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300">
                                                        {{ $a->course->course_code ?? '?' }}@if($a->course->level)<span class="ml-1 opacity-75">({{ substr($a->course->level,0,1) }}A)</span>@endif
                                                    </span>
                                                    @if($a->course->teacher)<span class="text-gray-400 dark:text-gray-500 text-xs">{{ $a->course->teacher->shortcode ?? '' }}</span>@endif
                                                    <span class="text-gray-400 dark:text-gray-500 text-xs">{{ $a->getEffectiveStudents()->count() }} S.</span>
                                                </div>
                                            @endforeach
                                        </td>
                                        @foreach($slotRows as $slot)
                                            @php
                                                $cellSupervisions = $roomAssignments
                                                    ->flatMap(fn($a) => $a->supervisions)
                                                    ->filter(fn($s) => $slot
                                                        ? $s->written_exam_supervision_time_slot_id === $slot->id
                                                        : $s->written_exam_supervision_time_slot_id === null);
                                            @endphp
                                            <td class="px-3 py-3 align-top border-l dark:border-l-gray-700">
                                                <div class="flex flex-wrap gap-1.5 mb-1">
                                                    @foreach($cellSupervisions as $supervision)
                                                        @php
                                                            $slotKey  = $slot?->id ?? 'none';
                                                            $catKey   = $teacherCategoryMaps[$slotKey][$supervision->teacher_id] ?? 'unknown';
                                                            $badgeCss = $catBadge[$catKey];
                                                            $badgeTip = ($catTitle[$catKey] ?? '') . ' – ' . ($supervision->teacher->lastname ?? '');
                                                        @endphp
                                                        <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium {{ $badgeCss }}"
                                                              title="{{ $badgeTip }}">
                                                            <span>{{ $supervision->teacher->shortcode ?? '?' }}</span>
                                                            @unlessarchive
                                                            <form method="POST" action="{{ route('written-exams.supervisions.unassign-teacher', [$writtenExamYearDate, $supervision]) }}" data-confirm="Aufsicht entfernen?" class="inline">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="opacity-60 hover:opacity-100 hover:text-red-600 font-bold leading-none w-4 h-4 flex items-center justify-center rounded-full">&times;</button>
                                                            </form>
                                                            @endarchive
                                                        </span>
                                                    @endforeach
                                                </div>
                                                @php $emptyFormsCount = max(1, 2 - $cellSupervisions->count()); @endphp
                                                @for($i = 0; $i < $emptyFormsCount; $i++)
                                                    @include('written-exams.supervisions._assign-form', ['writtenExamDate' => $writtenExamYearDate, 'slot' => $slot, 'suggestions' => $slot ? $teacherSuggestions[$slot->id] : $teacherSuggestions['none'], 'hiddenFields' => ['written_exam_assignment_id' => $firstAssignment->id, 'supervision_type' => 'waiting'], 'ringColor' => 'indigo'])
                                                @endfor
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            {{-- ===== GANGAUFSICHT & RESERVE (einmal, mit Standard-Raster-Slots) ===== --}}
            @php
                $defaultGrid    = $grids->firstWhere('is_default', true) ?? $grids->first();
                $defaultGc      = $defaultGrid?->color ?? 'blue';
                $defaultSlots   = $slotsByGridId->get($defaultGrid?->id ?? 0, collect())->sortBy('slot_number');
                $defaultSlotRows = $defaultSlots->isNotEmpty() ? $defaultSlots : collect([null]);
            @endphp
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border {{ $groupBorder[$defaultGc] ?? 'border-gray-200 dark:border-gray-700' }}">
                <div class="px-5 py-3 flex items-center gap-3 {{ $groupBgMap[$defaultGc] ?? 'bg-gray-50' }} border-b {{ $groupBorder[$defaultGc] ?? 'border-gray-200 dark:border-gray-700' }}">
                    <span class="w-3 h-3 rounded-full {{ $groupDot[$defaultGc] ?? 'bg-gray-400' }}"></span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100 text-sm">Gangaufsicht &amp; Reserve</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Raster: {{ $defaultGrid?->name ?? 'Standard' }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700/50">
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 border-b dark:border-gray-600 w-44 min-w-44">Position</th>
                                @foreach($defaultSlotRows as $slot)
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-600 border-l dark:border-l-gray-600 min-w-44">
                                        @if($slot)
                                            @php
                                                $tsEnd     = \Carbon\Carbon::parse($slot->end_time);
                                                $covers    = $tsEnd->gte($examEndTime);
                                                $coversNta = $tsEnd->gte($examEndTimeWithMaxNta);
                                                $dotTitle  = !$covers
                                                    ? 'Endet vor Prüfungsende (' . $examEndTime->format('H:i') . ')'
                                                    : ($maxNtaPercent > 0 && !$coversNta
                                                        ? 'Endet vor NTA-Ende (' . $examEndTimeWithMaxNta->format('H:i') . ')'
                                                        : 'Deckt Prüfungszeit ab');
                                            @endphp
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="{{ !$covers ? 'background:#f87171' : ($maxNtaPercent > 0 && !$coversNta ? 'background:#fbbf24' : 'background:#4ade80') }}" title="{{ $dotTitle }}"></span>
                                                <span>{{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}</span>
                                            </div>
                                            @if($slot->label)<div class="text-xs text-gray-400 font-normal mt-0.5 ml-3.5">{{ $slot->label }}</div>@endif
                                        @else
                                            (ohne Zeitslot)
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            {{-- Gangaufsicht --}}
                            <tr class="bg-purple-50/40 dark:bg-purple-900/10 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors">
                                <td class="px-4 py-3 align-top bg-purple-50/60 dark:bg-purple-900/10">
                                    <div class="font-bold text-purple-700 dark:text-purple-300 text-sm">Gangaufsicht</div>
                                </td>
                                @foreach($defaultSlotRows as $slot)
                                    @php $cellCorridor = $corridorSupervisions->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null); @endphp
                                    <td class="px-3 py-3 align-top border-l dark:border-l-gray-700">
                                        <div class="flex flex-wrap gap-1.5 mb-1">
                                            @foreach($cellCorridor as $supervision)
                                                @php
                                                    $slotKey  = $slot?->id ?? 'none';
                                                    $catKey   = $teacherCategoryMaps[$slotKey][$supervision->teacher_id] ?? 'unknown';
                                                    $badgeCss = $catBadge[$catKey];
                                                    $badgeTip = ($catTitle[$catKey] ?? '') . ' – ' . ($supervision->teacher->lastname ?? '');
                                                @endphp
                                                <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium {{ $badgeCss }}"
                                                      title="{{ $badgeTip }}">
                                                    <span>{{ $supervision->teacher->shortcode ?? '?' }}</span>
                                                    @unlessarchive
                                                    <form method="POST" action="{{ route('written-exams.supervisions.unassign-teacher', [$writtenExamYearDate, $supervision]) }}" data-confirm="Gangaufsicht entfernen?" class="inline">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="opacity-60 hover:opacity-100 hover:text-red-600 font-bold leading-none w-4 h-4 flex items-center justify-center rounded-full">&times;</button>
                                                    </form>
                                                    @endarchive
                                                </span>
                                            @endforeach
                                        </div>
                                        @include('written-exams.supervisions._assign-form', ['writtenExamDate' => $writtenExamYearDate, 'slot' => $slot, 'suggestions' => $slot ? $teacherSuggestions[$slot->id] : $teacherSuggestions['none'], 'hiddenFields' => ['supervision_type' => 'corridor'], 'ringColor' => 'purple'])
                                    </td>
                                @endforeach
                            </tr>
                            {{-- Reserve --}}
                            <tr class="bg-amber-50/40 dark:bg-amber-900/10 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors">
                                <td class="px-4 py-3 align-top bg-amber-50/60 dark:bg-amber-900/10">
                                    <div class="font-bold text-amber-700 dark:text-amber-300 text-sm">Reserve</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Lehrerzimmer</div>
                                </td>
                                @foreach($defaultSlotRows as $slot)
                                    @php $cellReserve = $reserveSupervisions->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null); @endphp
                                    <td class="px-3 py-3 align-top border-l dark:border-l-gray-700">
                                        <div class="flex flex-wrap gap-1.5 mb-1">
                                            @foreach($cellReserve as $supervision)
                                                @php
                                                    $slotKey  = $slot?->id ?? 'none';
                                                    $catKey   = $teacherCategoryMaps[$slotKey][$supervision->teacher_id] ?? 'unknown';
                                                    $badgeCss = $catBadge[$catKey];
                                                    $badgeTip = ($catTitle[$catKey] ?? '') . ' – ' . ($supervision->teacher->lastname ?? '');
                                                @endphp
                                                <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium {{ $badgeCss }}"
                                                      title="{{ $badgeTip }}">
                                                    <span>{{ $supervision->teacher->shortcode ?? '?' }}</span>
                                                    @unlessarchive
                                                    <form method="POST" action="{{ route('written-exams.supervisions.unassign-teacher', [$writtenExamYearDate, $supervision]) }}" data-confirm="Reserve entfernen?" class="inline">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="opacity-60 hover:opacity-100 hover:text-red-600 font-bold leading-none w-4 h-4 flex items-center justify-center rounded-full">&times;</button>
                                                    </form>
                                                    @endarchive
                                                </span>
                                            @endforeach
                                        </div>
                                        @include('written-exams.supervisions._assign-form', ['writtenExamDate' => $writtenExamYearDate, 'slot' => $slot, 'suggestions' => $slot ? $teacherSuggestions[$slot->id] : $teacherSuggestions['none'], 'hiddenFields' => ['supervision_type' => 'reserve'], 'ringColor' => 'amber'])
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Extra-Typen (terminspezifisch, ohne Raster-Zuordnung) --}}
            @if($centralTypes->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-3 bg-gray-50 dark:bg-gray-700/50 border-b dark:border-gray-600">
                        <span class="font-semibold text-gray-700 dark:text-gray-200 text-sm">Zusätzliche Positionen</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700/50">
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 border-b dark:border-gray-600 w-44 min-w-44">Position</th>
                                    @foreach($timeSlots->isNotEmpty() ? $timeSlots : collect([null]) as $slot)
                                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-600 border-l dark:border-l-gray-600 min-w-44">
                                            @if($slot)
                                                {{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}
                                                @if($slot->grid)<div class="text-xs text-gray-400 font-normal">{{ $slot->grid->name }}</div>@endif
                                            @else
                                                (ohne Zeitslot)
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700">
                                @foreach($centralTypes as $type)
                                    @php $badgeClass = $colorBadge[$type->color] ?? $colorBadge['orange']; @endphp
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-bold {{ $colorHeader[$type->color] ?? 'text-gray-700' }} text-sm">{{ $type->name }}</div>
                                            <div class="text-xs text-gray-400 mt-0.5">extra</div>
                                        </td>
                                        @foreach($timeSlots->isNotEmpty() ? $timeSlots : collect([null]) as $slot)
                                            @php $cellExtra = ($centralSupervisions->get($type->id) ?? collect())->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null); @endphp
                                            <td class="px-3 py-3 align-top border-l dark:border-l-gray-700">
                                                <div class="flex flex-wrap gap-1.5 mb-1">
                                                    @foreach($cellExtra as $supervision)
                                                        @php
                                                            $slotKey  = $slot?->id ?? 'none';
                                                            $catKey   = $teacherCategoryMaps[$slotKey][$supervision->teacher_id] ?? 'unknown';
                                                            $badgeCss = $catBadge[$catKey];
                                                            $badgeTip = ($catTitle[$catKey] ?? '') . ' – ' . ($supervision->teacher->lastname ?? '');
                                                        @endphp
                                                        <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium {{ $badgeCss }}"
                                                              title="{{ $badgeTip }}">
                                                            <span>{{ $supervision->teacher->shortcode ?? '?' }}</span>
                                                            @unlessarchive
                                                            <form method="POST" action="{{ route('written-exams.supervisions.unassign-teacher', [$writtenExamYearDate, $supervision]) }}" data-confirm="Position entfernen?" class="inline">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="opacity-60 hover:opacity-100 hover:text-red-600 font-bold leading-none w-4 h-4 flex items-center justify-center rounded-full">&times;</button>
                                                            </form>
                                                            @endarchive
                                                        </span>
                                                    @endforeach
                                                </div>
                                                @include('written-exams.supervisions._assign-form', ['writtenExamDate' => $writtenExamYearDate, 'slot' => $slot, 'suggestions' => $slot ? $teacherSuggestions[$slot->id] : $teacherSuggestions['none'], 'hiddenFields' => ['supervision_type' => 'extra', 'central_supervision_type_id' => $type->id], 'ringColor' => $type->color])
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            </div>{{-- end space-y-6 --}}

            {{-- Zusammenfassung --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5 border dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Zusammenfassung</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $assignments->count() }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Raumzuordnungen</p>
                    </div>
                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $assignments->sum(fn($a) => $a->supervisions->count()) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Raumaufsichten</p>
                    </div>
                    <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $corridorSupervisions->count() }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Gangaufsichten</p>
                    </div>
                    <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $reserveSupervisions->count() }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Reserve</p>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        @php
                            $totalTeachers = collect()
                                ->concat($assignments->flatMap(fn($a) => $a->supervisions->pluck('teacher_id')))
                                ->concat($corridorSupervisions->pluck('teacher_id'))
                                ->concat($reserveSupervisions->pluck('teacher_id'))
                                ->concat(collect($centralSupervisions->values())->flatten()->pluck('teacher_id'))
                                ->unique()->count();
                        @endphp
                        <p class="text-2xl font-bold text-gray-700 dark:text-gray-200">{{ $totalTeachers }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Beteiligte Lehrer</p>
                    </div>
                </div>
            </div>

            @endif {{-- end assignments empty --}}
        </div>
    </div>

@push('scripts')
<script @cspNonce>
function gridManager() {
    return {
        open: false,
        grids: @json($grids),
        newGrid: { name: '', color: 'blue', is_foreign_language: false },

        gridBadgeClass(grid) {
            const map = {
                green:  'bg-green-100 text-green-800 border-green-300',
                orange: 'bg-orange-100 text-orange-800 border-orange-300',
                purple: 'bg-purple-100 text-purple-800 border-purple-300',
                red:    'bg-red-100 text-red-800 border-red-300',
                yellow: 'bg-yellow-100 text-yellow-800 border-yellow-300',
            };
            return map[grid.color] ?? 'bg-blue-100 text-blue-800 border-blue-300';
        },
        gridDotClass(grid) {
            const map = { blue:'bg-blue-500', green:'bg-green-500', orange:'bg-orange-500', purple:'bg-purple-500', red:'bg-red-500', yellow:'bg-yellow-400' };
            return map[grid.color] ?? 'bg-blue-500';
        },

        async createGrid() {
            if (!this.newGrid.name.trim()) { this.notify('Name ist erforderlich', 'error'); return; }
            try {
                const resp = await fetch('/written-exams/supervisions/grids', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(this.newGrid)
                });
                const data = await resp.json();
                if (data.success) {
                    this.grids.push(data.grid);
                    this.newGrid = { name: '', color: 'blue', is_foreign_language: false };
                    this.notify('Raster angelegt', 'success');
                } else { this.notify(data.message || 'Fehler', 'error'); }
            } catch(e) { this.notify('Netzwerkfehler', 'error'); }
        },

        async deleteGrid(grid) {
            if (!confirm(`Raster „${grid.name}" wirklich löschen?`)) return;
            try {
                const resp = await fetch(`/written-exams/supervisions/grids/${grid.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const data = await resp.json();
                if (data.success) { this.grids = this.grids.filter(g => g.id !== grid.id); this.notify('Raster gelöscht', 'success'); }
                else { this.notify(data.message || 'Fehler', 'error'); }
            } catch(e) { this.notify('Netzwerkfehler', 'error'); }
        },

        notify(msg, type = 'info') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
            const el = document.createElement('div');
            el.className = `fixed top-4 right-4 ${colors[type] ?? colors.info} text-white px-5 py-3 rounded-lg shadow-lg z-50 text-sm`;
            el.textContent = msg;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        }
    }
}

// Frueher onchange am Auswahlfeld (Modul 7.2). Ohne Zuteilung ist das
// Attribut leer — dann bleibt assignmentId null wie zuvor.
document.addEventListener('change', (ereignis) => {
    const feld = ereignis.target.closest('[data-room-grid-for]');

    if (feld) {
        const zuteilung = feld.dataset.roomGridFor;

        assignRoomGrid(zuteilung === '' ? null : Number(zuteilung), feld.value);
    }
});

async function assignRoomGrid(assignmentId, gridId) {
    if (!assignmentId) { alert('Kein Assignment gefunden'); return; }
    try {
        const resp = await fetch('/written-exams/supervisions/grids/assign-room', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ written_exam_assignment_id: assignmentId, written_exam_supervision_grid_id: gridId })
        });
        const data = await resp.json();
        if (!data.success) alert(data.message || 'Fehler beim Zuweisen');
        else location.reload();
    } catch(e) { alert('Netzwerkfehler'); }
}
</script>
@endpush
</x-app-layout>
