<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Optimierungssimulation
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $examPeriod->name }} – günstige Kombination aus Prüfungstagen, Slots und Räumen finden
                </p>
            </div>
            <a href="{{ route('planning.index') }}"
               class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Zurück zur Planung
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Erläuterung --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                <p class="font-medium mb-1">Wozu dient die Simulation?</p>
                <p>
                    Die Simulation prüft, welche Kombinationen aus <strong>Prüfungstagen</strong>,
                    <strong>Zeitslots pro Tag</strong> und <strong>Räumen</strong> ausreichen, um alle
                    mündlichen Prüfungen konfliktfrei unterzubringen – und schlägt daraus günstige
                    Varianten vor (wenige Tage / geringe Tagesbelastung / ausgewogen).
                    Es wird <strong>nichts</strong> gespeichert oder verändert; das Ergebnis dient nur
                    als Orientierung für Ihre Planung.
                </p>
            </div>

            {{-- Nachfrage-Übersicht --}}
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Ausgangslage (mündliche Prüfungen)</h3>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-gray-800">{{ $demand['total'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">Mündliche Prüfungen gesamt</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800">{{ $demand['max_teacher_load'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">Höchste Last einer Lehrkraft<br>(Prüfer + Beisitze)</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800">{{ $demand['max_student_load'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">Meiste Prüfungen eines Schülers</div>
                    </div>
                </div>
            </div>

            {{-- Bandbreiten-Formular --}}
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Suchbereich festlegen</h3>
                <form method="POST" action="{{ route('planning.simulation.run') }}">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        {{-- Prüfungstage --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prüfungstage</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="days_min" min="1" max="60" required
                                       value="{{ old('days_min', $ranges['days'][0]) }}"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <span class="text-gray-400 text-sm">bis</span>
                                <input type="number" name="days_max" min="1" max="60" required
                                       value="{{ old('days_max', $ranges['days'][1]) }}"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        {{-- Slots pro Tag --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Zeitslots pro Tag</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="slots_min" min="1" max="20" required
                                       value="{{ old('slots_min', $ranges['slots_per_day'][0]) }}"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <span class="text-gray-400 text-sm">bis</span>
                                <input type="number" name="slots_max" min="1" max="20" required
                                       value="{{ old('slots_max', $ranges['slots_per_day'][1]) }}"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        {{-- Räume --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Räume</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="rooms_min" min="1" max="50" required
                                       value="{{ old('rooms_min', $ranges['rooms'][0]) }}"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <span class="text-gray-400 text-sm">bis</span>
                                <input type="number" name="rooms_max" min="1" max="50" required
                                       value="{{ old('rooms_max', $ranges['rooms'][1]) }}"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="mt-4 text-sm text-red-600">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="mt-5 flex items-center justify-between">
                        <p class="text-xs text-gray-400">
                            Die Werte werden als Vorgabe für diese Schule gespeichert.
                        </p>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md text-sm font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Simulation starten
                        </button>
                    </div>
                </form>
            </div>

            @if($result !== null)
                @php
                    // Vollständige Klassennamen (kein dynamisches Zusammensetzen –
                    // sonst entfernt der Tailwind-Compiler die Klassen).
                    $labels = [
                        'compact'  => [
                            'title' => 'Kompakt', 'desc' => 'Wenigste Prüfungstage',
                            'card' => 'border-emerald-200', 'head' => 'bg-emerald-50 border-emerald-100',
                            'title_col' => 'text-emerald-800', 'desc_col' => 'text-emerald-600',
                        ],
                        'spread'   => [
                            'title' => 'Entzerrt', 'desc' => 'Geringste Belastung pro Tag',
                            'card' => 'border-sky-200', 'head' => 'bg-sky-50 border-sky-100',
                            'title_col' => 'text-sky-800', 'desc_col' => 'text-sky-600',
                        ],
                        'balanced' => [
                            'title' => 'Ausgewogen', 'desc' => 'Guter Kompromiss',
                            'card' => 'border-violet-200', 'head' => 'bg-violet-50 border-violet-100',
                            'title_col' => 'text-violet-800', 'desc_col' => 'text-violet-600',
                        ],
                    ];
                @endphp

                {{-- Ergebnis-Meta + Export --}}
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span><strong class="text-gray-700">{{ $result['feasible_count'] }}</strong> machbare Kombinationen gefunden</span>
                        <span>·</span>
                        <span><strong class="text-gray-700">{{ $result['dry_runs'] }}</strong> Simulationsläufe</span>
                        <span>·</span>
                        <span><strong class="text-gray-700">{{ $result['skipped_arithmetic'] }}</strong> vorab rechnerisch ausgeschlossen</span>
                    </div>
                    @if(!empty($result['suggestions']))
                        <a href="{{ route('planning.simulation.export.pdf') }}"
                           class="inline-flex items-center gap-1.5 bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-md text-sm font-medium whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Report als PDF
                        </a>
                    @endif
                </div>

                @if(empty($result['suggestions']))
                    {{-- Keine machbare Kombination --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-5 text-sm text-amber-900">
                        <p class="font-medium mb-1">Keine machbare Kombination im gewählten Bereich</p>
                        <p>
                            In den angegebenen Bandbreiten ließ sich keine Ausstattung finden, die alle
                            {{ $demand['total'] }} mündlichen Prüfungen unterbringt. Erhöhen Sie die
                            Obergrenzen – insbesondere bei Tagen und Slots/Tag, da die am stärksten
                            belastete Lehrkraft mindestens {{ $demand['max_teacher_load'] }} Zeitpunkte
                            benötigt.
                        </p>
                    </div>
                @else
                    {{-- Vorschlags-Karten --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($labels as $key => $meta)
                            @if(isset($result['suggestions'][$key]))
                                @php $s = $result['suggestions'][$key]; @endphp
                                <div class="bg-white shadow-sm rounded-lg border-2 {{ $meta['card'] }} overflow-hidden">
                                    <div class="{{ $meta['head'] }} px-5 py-3 border-b">
                                        <div class="font-semibold {{ $meta['title_col'] }}">{{ $meta['title'] }}</div>
                                        <div class="text-xs {{ $meta['desc_col'] }}">{{ $meta['desc'] }}</div>
                                    </div>
                                    <div class="p-5 space-y-3">
                                        <div class="flex items-baseline justify-between">
                                            <span class="text-sm text-gray-500">Prüfungstage</span>
                                            <span class="text-lg font-bold text-gray-800">{{ $s['days'] }}</span>
                                        </div>
                                        <div class="flex items-baseline justify-between">
                                            <span class="text-sm text-gray-500">Slots pro Tag</span>
                                            <span class="text-lg font-bold text-gray-800">{{ $s['slots_per_day'] }}</span>
                                        </div>
                                        <div class="flex items-baseline justify-between">
                                            <span class="text-sm text-gray-500">Räume</span>
                                            <span class="text-lg font-bold text-gray-800">{{ $s['rooms'] }}</span>
                                        </div>
                                        <div class="border-t border-gray-100 pt-3 flex items-baseline justify-between">
                                            <span class="text-xs text-gray-400">Kapazität (Plätze)</span>
                                            <span class="text-sm font-medium text-gray-600">{{ $s['capacity'] }}</span>
                                        </div>
                                        <div class="pt-1">
                                            @if($s['feasible'])
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-1 rounded">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Alle Prüfungen unterbringbar
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-50 px-2 py-1 rounded">
                                                    {{ $s['unplaced'] }} Prüfung(en) offen
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <p class="text-xs text-gray-400">
                        Die Vorschläge sind eine Orientierung. Die konkreten Prüfungstage, Uhrzeiten und
                        Räume legen Sie anschließend wie gewohnt in der Planung an.
                    </p>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
