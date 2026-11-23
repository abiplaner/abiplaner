<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Zeitslots – Aufsichten schriftliche Prüfungen') }}
            </h2>
            <a href="{{ route('written-exams.supervisions.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="timeSlotManager()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 px-4 py-3 rounded-lg">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Infobox --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-300 px-4 py-3 rounded-lg text-sm">
                <strong>Hinweis:</strong> Die Zeitslots definieren die <strong>Aufsichtsschichten</strong> für alle schriftlichen
                Abiturprüfungstage – z.&thinsp;B. Slot 1: 8:00–10:15, Slot 2: 10:15–12:30.
                Jedes <strong>Raster</strong> fasst eine Gruppe von Slots zusammen; Räume zeigen dann nur die Slots ihres Rasters.
                Ähnliche Raster lassen sich per <strong>Kopieren</strong> duplizieren.
            </div>

            {{-- ===== RASTER-VERWALTUNG ===== --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Aufsichtsraster</h3>

                    {{-- Bestehende Raster --}}
                    <div class="flex flex-wrap gap-3">
                        <template x-for="grid in grids" :key="grid.id">
                            <div class="border dark:border-gray-600 rounded-xl p-3 space-y-2 min-w-52"
                                 :class="gridCardClass(grid)">
                                {{-- Badge-Zeile --}}
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="gridDotClass(grid)"></span>
                                    <span class="font-semibold text-sm" x-text="grid.name"></span>
                                    <span class="text-xs opacity-60 ml-auto" x-text="grid.slot_count + ' Slot' + (grid.slot_count !== 1 ? 's' : '')"></span>
                                </div>
                                {{-- Aktions-Buttons --}}
                                @unlessarchive
                                <div class="flex gap-1.5 flex-wrap">
                                    <button @click="toggleCopy(grid)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-md bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:hover:bg-indigo-800/50 text-indigo-700 dark:text-indigo-300 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        Kopieren
                                    </button>
                                    <template x-if="!grid.is_default">
                                        <button @click="deleteGrid(grid)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-md bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:hover:bg-red-800/50 text-red-600 dark:text-red-400 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Löschen
                                        </button>
                                    </template>
                                    <template x-if="grid.is_default">
                                        <span class="px-2.5 py-1 text-xs rounded-md text-gray-400 dark:text-gray-500 italic">Standard</span>
                                    </template>
                                </div>
                                {{-- Kopier-Formular (inline) --}}
                                <div x-show="copyState[grid.id] && copyState[grid.id].open"
                                     style="display:none"
                                     class="border-t dark:border-gray-600 pt-2 space-y-1.5">
                                    <label class="block text-xs text-gray-500 dark:text-gray-400">Name der Kopie</label>
                                    <input type="text"
                                           :value="copyState[grid.id] ? copyState[grid.id].name : ''"
                                           @input="copyState[grid.id].name = $event.target.value"
                                           @keydown.enter="copyGrid(grid)"
                                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-2 py-1">
                                    <div class="flex gap-1.5">
                                        <button @click="copyGrid(grid)"
                                                class="flex-1 px-2 py-1 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded transition-colors">
                                            Jetzt kopieren
                                        </button>
                                        <button @click="cancelCopy(grid)"
                                                class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 rounded transition-colors">
                                            Abbrechen
                                        </button>
                                    </div>
                                </div>
                                @endarchive
                            </div>
                        </template>
                    </div>

                    {{-- Neues Raster anlegen --}}
                    @unlessarchive
                    <div class="border-t dark:border-gray-700 pt-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide">Neues Raster anlegen</p>
                        <div class="flex items-end gap-3 flex-wrap">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name *</label>
                                <input type="text" x-model="newGrid.name" placeholder="z.B. Hörverstehen Englisch"
                                       @keydown.enter="createGrid()"
                                       class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm py-1.5 px-3 w-56 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Farbe</label>
                                <select x-model="newGrid.color"
                                        class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm py-1.5 px-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="blue">Blau</option>
                                    <option value="green">Grün</option>
                                    <option value="orange">Orange</option>
                                    <option value="purple">Lila</option>
                                    <option value="red">Rot</option>
                                    <option value="yellow">Gelb</option>
                                </select>
                            </div>
                            <button @click="createGrid()"
                                    class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Raster anlegen
                            </button>
                        </div>
                    </div>
                    @endarchive
                </div>
            </div>

            {{-- ===== ZEITSLOTS NACH RASTER ===== --}}
            @php
                $slotsByGrid = $timeSlots->groupBy('written_exam_supervision_grid_id');
            @endphp

            @foreach($grids as $grid)
                @php
                    $gridSlots = $slotsByGrid->get($grid->id, collect())->sortBy('slot_number');
                    $colorDot  = ['blue'=>'bg-blue-500','green'=>'bg-green-500','orange'=>'bg-orange-500','purple'=>'bg-purple-500','red'=>'bg-red-500','yellow'=>'bg-yellow-400'];
                    $colorBadge= ['blue'=>'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
                                  'green'=>'bg-green-100 text-green-800 border-green-300 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
                                  'orange'=>'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-700',
                                  'purple'=>'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700',
                                  'red'=>'bg-red-100 text-red-800 border-red-300 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700',
                                  'yellow'=>'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700'];
                    $dot  = $colorDot[$grid->color]  ?? 'bg-blue-500';
                    $badge= $colorBadge[$grid->color] ?? $colorBadge['blue'];
                @endphp
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg" id="grid-section-{{ $grid->id }}">
                    <div class="p-6">
                        {{-- Raster-Header --}}
                        <div class="flex items-center gap-3 mb-4">
                            <span class="w-3 h-3 rounded-full {{ $dot }} shrink-0"></span>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $grid->name }}</h3>
                            @if($grid->is_default)
                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">Standard</span>
                            @endif
                            <span class="ml-auto text-sm text-gray-400 dark:text-gray-500">
                                {{ $gridSlots->count() }} {{ $gridSlots->count() === 1 ? 'Slot' : 'Slots' }}
                            </span>
                        </div>

                        {{-- Bestehende Slots --}}
                        @if($gridSlots->isEmpty())
                            <p class="text-sm text-gray-400 dark:text-gray-500 italic mb-4">Noch keine Zeitslots in diesem Raster.</p>
                        @else
                            <div class="space-y-2 mb-4">
                                @foreach($gridSlots as $slot)
                                    <div class="flex items-center gap-3 px-4 py-3 border dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                        {{-- Slot-Nummer --}}
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $badge }} border text-xs font-bold shrink-0">
                                            {{ $slot->slot_number }}
                                        </span>

                                        {{-- Inline-Edit --}}
                                        @unlessarchive
                                        <form method="POST"
                                              action="{{ route('written-exams.supervisions.time-slots.update', $slot) }}"
                                              class="flex items-center gap-2 flex-1 flex-wrap">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="written_exam_supervision_grid_id" value="{{ $grid->id }}">
                                            <input type="time" name="start_time"
                                                   value="{{ substr($slot->start_time, 0, 5) }}" required
                                                   class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                                            <span class="text-gray-400 dark:text-gray-500">–</span>
                                            <input type="time" name="end_time"
                                                   value="{{ substr($slot->end_time, 0, 5) }}" required
                                                   class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                                            <input type="text" name="label"
                                                   value="{{ $slot->label }}"
                                                   placeholder="Bezeichnung (optional)"
                                                   class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-2 py-1 flex-1 min-w-28 focus:ring-indigo-500 focus:border-indigo-500">
                                            <button type="submit"
                                                    class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 text-sm font-medium whitespace-nowrap transition-colors">
                                                Speichern
                                            </button>
                                        </form>

                                        {{-- Löschen --}}
                                        <form method="POST"
                                              action="{{ route('written-exams.supervisions.time-slots.destroy', $slot) }}"
                                              data-confirm="Diesen Zeitslot wirklich löschen?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-400 hover:text-red-600 dark:hover:text-red-300 transition-colors"
                                                    title="Zeitslot löschen">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                        @else
                                        {{-- Archivansicht: Zeiten nur anzeigen --}}
                                        <div class="flex items-center gap-2 flex-1 text-sm text-gray-700 dark:text-gray-200">
                                            {{ substr($slot->start_time, 0, 5) }} – {{ substr($slot->end_time, 0, 5) }}
                                            @if($slot->label)
                                                <span class="text-gray-400 dark:text-gray-500">{{ $slot->label }}</span>
                                            @endif
                                        </div>
                                        @endarchive
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Neuen Slot zu diesem Raster hinzufügen --}}
                        @unlessarchive
                        <div class="border-t dark:border-gray-700 pt-4">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide">Slot hinzufügen</p>
                            <form method="POST" action="{{ route('written-exams.supervisions.time-slots.store') }}">
                                @csrf
                                <input type="hidden" name="written_exam_supervision_grid_id" value="{{ $grid->id }}">
                                <div class="flex items-end gap-3 flex-wrap">
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Beginn</label>
                                        <input type="time" name="start_time" value="{{ old('start_time') }}" required
                                               class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Ende</label>
                                        <input type="time" name="end_time" value="{{ old('end_time') }}" required
                                               class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Bezeichnung <span class="text-gray-400 font-normal">(optional)</span></label>
                                        <input type="text" name="label" value="{{ old('label') }}" placeholder="z.B. Frühdienst"
                                               class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 w-40">
                                    </div>
                                    <div>
                                        <button type="submit"
                                                class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Hinzufügen
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        @endarchive
                    </div>
                </div>
            @endforeach

        </div>
    </div>

@php
    $gridsForJs = $grids->map(function($g) use ($timeSlots) {
        return [
            'id'         => $g->id,
            'name'       => $g->name,
            'color'      => $g->color,
            'is_default' => $g->is_default,
            'slot_count' => $timeSlots->where('written_exam_supervision_grid_id', $g->id)->count(),
        ];
    })->values();
@endphp

@push('scripts')
<script @cspNonce>
function timeSlotManager() {
    return {
        grids: @json($gridsForJs),
        newGrid:   { name: '', color: 'blue' },
        copyState: {},

        init() {
            this.grids.forEach(g => {
                this.copyState[g.id] = { open: false, name: g.name + ' (Kopie)' };
            });
        },

        gridCardClass(grid) {
            const map = {
                blue:   'bg-blue-50/50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800',
                green:  'bg-green-50/50 dark:bg-green-900/10 border-green-200 dark:border-green-800',
                orange: 'bg-orange-50/50 dark:bg-orange-900/10 border-orange-200 dark:border-orange-800',
                purple: 'bg-purple-50/50 dark:bg-purple-900/10 border-purple-200 dark:border-purple-800',
                red:    'bg-red-50/50 dark:bg-red-900/10 border-red-200 dark:border-red-800',
                yellow: 'bg-yellow-50/50 dark:bg-yellow-900/10 border-yellow-200 dark:border-yellow-800',
            };
            return map[grid.color] ?? map.blue;
        },
        gridDotClass(grid) {
            const map = { blue:'bg-blue-500', green:'bg-green-500', orange:'bg-orange-500', purple:'bg-purple-500', red:'bg-red-500', yellow:'bg-yellow-400' };
            return map[grid.color] ?? 'bg-blue-500';
        },

        toggleCopy(grid) {
            if (!this.copyState[grid.id]) {
                this.copyState[grid.id] = { open: false, name: grid.name + ' (Kopie)' };
            }
            this.copyState[grid.id].open = !this.copyState[grid.id].open;
        },
        cancelCopy(grid) {
            this.copyState[grid.id].open = false;
            this.copyState[grid.id].name = grid.name + ' (Kopie)';
        },

        async copyGrid(grid) {
            const state = this.copyState[grid.id];
            if (!state || !state.name.trim()) { this.notify('Bitte einen Namen eingeben.', 'error'); return; }
            try {
                const resp = await fetch(`/written-exams/supervisions/grids/${grid.id}/copy`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body:    JSON.stringify({ name: state.name.trim(), color: grid.color }),
                });
                const data = await resp.json();
                if (data.success) {
                    this.notify(`Raster „${data.grid.name}" wurde mit ${data.slots?.length ?? 0} Slots kopiert.`, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    this.notify(data.message || 'Fehler beim Kopieren', 'error');
                }
            } catch (e) { this.notify('Netzwerkfehler', 'error'); }
        },

        async createGrid() {
            if (!this.newGrid.name.trim()) { this.notify('Name ist erforderlich', 'error'); return; }
            try {
                const resp = await fetch('/written-exams/supervisions/grids', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body:    JSON.stringify(this.newGrid),
                });
                const data = await resp.json();
                if (data.success) {
                    this.notify(`Raster „${data.grid.name}" angelegt.`, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else { this.notify(data.message || 'Fehler', 'error'); }
            } catch (e) { this.notify('Netzwerkfehler', 'error'); }
        },

        async deleteGrid(grid) {
            if (!confirm(`Raster „${grid.name}" wirklich löschen?\n\nHinweis: Dazugehörige Zeitslots müssen vorher entfernt oder umgebucht werden.`)) return;
            try {
                const resp = await fetch(`/written-exams/supervisions/grids/${grid.id}`, {
                    method:  'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                const data = await resp.json();
                if (data.success) {
                    this.notify('Raster gelöscht', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else { this.notify(data.message || 'Fehler', 'error'); }
            } catch (e) { this.notify('Netzwerkfehler', 'error'); }
        },

        notify(msg, type = 'info') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
            const el = document.createElement('div');
            el.className = `fixed top-4 right-4 ${colors[type] ?? colors.info} text-white px-5 py-3 rounded-lg shadow-lg z-50 text-sm`;
            el.textContent = msg;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        },
    }
}
</script>
@endpush

</x-app-layout>
