<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('seating-templates.index') }}" class="hover:text-blue-600">Sitzplan-Vorlagen</a>
                    <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span>Bearbeiten</span>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Vorlage bearbeiten: {{ $seatingTemplate->name }}
                </h2>
            </div>
            <a href="{{ route('seating-templates.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg shadow-md transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6"
             x-data="templateEditor({{ json_encode($seats) }}, {{ $seatingTemplate->rows }}, {{ $seatingTemplate->columns }}, {{ $seatingTemplate->id }}, {{ json_encode($seatingTemplate->door_positions ?? []) }}, '{{ $seatingTemplate->orientation ?? 'top' }}')">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Vorlagen-Info und Aktionen -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $seatingTemplate->name }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Dimensionen: <span x-text="rows"></span> × <span x-text="columns"></span>
                                | Verfügbare Plätze: <span x-text="getAvailableSeatsCount()"></span>
                                | Reserve: <span x-text="getReserveSeatsCount()"></span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="openResizeModal()"
                                    class="inline-flex items-center px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                Größe ändern
                            </button>
                        </div>
                    </div>

                    <form action="{{ route('seating-templates.update', $seatingTemplate) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name der Vorlage *</label>
                                <input type="text" name="name" id="name" required
                                       value="{{ old('name', $seatingTemplate->name) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Beschreibung</label>
                                <input type="text" name="description" id="description"
                                       value="{{ old('description', $seatingTemplate->description) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="orientation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pult-Seite (Orientierung)</label>
                                <select name="orientation" id="orientation"
                                        x-model="orientation"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="top"    {{ old('orientation', $seatingTemplate->orientation ?? 'top') === 'top'    ? 'selected' : '' }}>Oben – Pult / Tafel vorne oben</option>
                                    <option value="bottom" {{ old('orientation', $seatingTemplate->orientation ?? 'top') === 'bottom' ? 'selected' : '' }}>Unten – Pult / Tafel vorne unten</option>
                                    <option value="left"   {{ old('orientation', $seatingTemplate->orientation ?? 'top') === 'left'   ? 'selected' : '' }}>Links – Pult / Tafel vorne links</option>
                                    <option value="right"  {{ old('orientation', $seatingTemplate->orientation ?? 'top') === 'right'  ? 'selected' : '' }}>Rechts – Pult / Tafel vorne rechts</option>
                                </select>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Legt fest, wo Reihe 01 im Sitzplan beginnt. Die Nummerierung startet immer beim Pult.
                                </p>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Legende -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Legende & Bedienung</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded border-2 border-green-300 bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <span class="text-xs font-bold text-green-800 dark:text-green-300">✓</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Verfügbar</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Normaler Sitzplatz</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded border-2 border-red-300 bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                                <span class="text-xs font-bold text-red-800 dark:text-red-300">✕</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Blockiert</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Nicht nutzbar (z.B. Pult)</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded border-2 border-gray-300 bg-gray-50 dark:bg-gray-700 flex items-center justify-center">
                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">—</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Gang</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Leerraum / Durchgang</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded border-2 border-amber-400 bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
                                <span class="text-xs font-bold text-amber-700 dark:text-amber-300">R</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Reserve</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Reserveplatz (manuell)</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded border-2 border-blue-400 bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <span class="text-xs font-bold text-blue-700 dark:text-blue-300">Pult</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Lehrerpult</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Pult im Raster</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded border-2 border-orange-400 bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                                <span class="text-xs font-bold text-orange-700 dark:text-orange-300">Türe</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Türe</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Außerhalb des Rasters</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-3">
                        💡 Raster-Klick = Typ wechseln (Verfügbar → Lehrerpult → Blockiert → Gang → Reserve → Verfügbar) &nbsp;|&nbsp; Türen an den Wänden außerhalb des Rasters markieren
                    </p>
                </div>
            </div>

            <!-- Layout-Editor Grid -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <div class="inline-block min-w-full">

                            <!-- Pult-Indikator OBEN -->
                            <div x-show="orientation === 'top'" class="flex items-center gap-2 mb-2">
                                <div class="w-8"></div>
                                <div class="w-8"></div>
                                <div class="flex-1 flex items-center justify-center gap-2 py-1 px-3 rounded bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold">
                                    <span>🎓</span><span>Pult / Tafel (Reihe 01 beginnt hier)</span>
                                </div>
                                <div class="w-8"></div>
                            </div>

                            <!-- Türen OBEN (Wand oben = Tafelseite) -->
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-8"></div><!-- Platzhalter Reihen-Label -->
                                <div class="w-8"></div><!-- Platzhalter links -->
                                <template x-for="col in columns" :key="'door-top-' + col">
                                    <button type="button"
                                            @click="toggleDoor('top', col)"
                                            :class="hasDoor('top', col)
                                                ? 'border-orange-400 bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300'
                                                : 'border-dashed border-gray-200 dark:border-gray-700 bg-transparent text-gray-300 dark:text-gray-600 hover:border-orange-300 hover:text-orange-400'"
                                            class="w-16 h-10 rounded border-2 transition-all duration-150 flex flex-col items-center justify-center text-xs font-bold focus:outline-none"
                                            :title="hasDoor('top', col) ? 'Türe entfernen' : 'Türe hier markieren'">
                                        <span x-show="hasDoor('top', col)" class="text-xs font-bold leading-none">Türe</span>
                                        <span x-show="!hasDoor('top', col)" class="text-sm leading-none">+</span>
                                    </button>
                                </template>
                                <div class="w-8"></div><!-- Platzhalter rechts -->
                            </div>


                            <!-- Reihen mit Türen links/rechts -->
                            <template x-for="row in rowsReversed" :key="'row-' + row">
                                <div class="flex gap-2 mb-2">
                                    <!-- Reihen-Nummer -->
                                    <div class="w-8 flex items-center justify-center text-xs font-semibold text-gray-500 dark:text-gray-400">
                                        <span x-text="String(row).padStart(2, '0')"></span>
                                    </div>
                                    <!-- Türe LINKS -->
                                    <button type="button"
                                            @click="toggleDoor('left', row)"
                                            :class="hasDoor('left', row)
                                                ? 'border-orange-400 bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300'
                                                : 'border-dashed border-gray-200 dark:border-gray-700 bg-transparent text-gray-300 dark:text-gray-600 hover:border-orange-300 hover:text-orange-400'"
                                            class="w-10 h-16 rounded border-2 transition-all duration-150 flex flex-col items-center justify-center text-xs font-bold focus:outline-none"
                                            :title="hasDoor('left', row) ? 'Türe entfernen' : 'Türe hier markieren'">
                                        <span x-show="hasDoor('left', row)" class="text-xs font-bold leading-none">Türe</span>
                                        <span x-show="!hasDoor('left', row)" class="text-sm leading-none">+</span>
                                    </button>
                                    <!-- Sitzplätze -->
                                    <template x-for="col in columns" :key="'col-' + col">
                                        <button type="button"
                                                @click="toggleSeatType(row, col)"
                                                :class="getSeatClass(row, col)"
                                                class="w-16 h-16 rounded border-2 transition-all duration-200 flex flex-col items-center justify-center focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <span class="text-[10px] font-mono text-gray-400 leading-none"
                                                  x-text="String(row).padStart(2,'0') + '-' + String(col).padStart(2,'0')"></span>
                                            <span class="text-sm font-bold mt-0.5" x-text="getSeatIcon(row, col)"></span>
                                        </button>
                                    </template>
                                    <!-- Türe RECHTS -->
                                    <button type="button"
                                            @click="toggleDoor('right', row)"
                                            :class="hasDoor('right', row)
                                                ? 'border-orange-400 bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300'
                                                : 'border-dashed border-gray-200 dark:border-gray-700 bg-transparent text-gray-300 dark:text-gray-600 hover:border-orange-300 hover:text-orange-400'"
                                            class="w-10 h-16 rounded border-2 transition-all duration-150 flex flex-col items-center justify-center text-xs font-bold focus:outline-none"
                                            :title="hasDoor('right', row) ? 'Türe entfernen' : 'Türe hier markieren'">
                                        <span x-show="hasDoor('right', row)" class="text-xs font-bold leading-none">Türe</span>
                                        <span x-show="!hasDoor('right', row)" class="text-sm leading-none">+</span>
                                    </button>
                                </div>
                            </template>

                            <!-- Türen UNTEN -->
                            <div class="flex items-center gap-2 mt-1">
                                <div class="w-8"></div>
                                <div class="w-8"></div><!-- Platzhalter links -->
                                <template x-for="col in columns" :key="'door-bottom-' + col">
                                    <button type="button"
                                            @click="toggleDoor('bottom', col)"
                                            :class="hasDoor('bottom', col)
                                                ? 'border-orange-400 bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300'
                                                : 'border-dashed border-gray-200 dark:border-gray-700 bg-transparent text-gray-300 dark:text-gray-600 hover:border-orange-300 hover:text-orange-400'"
                                            class="w-16 h-10 rounded border-2 transition-all duration-150 flex flex-col items-center justify-center text-xs font-bold focus:outline-none"
                                            :title="hasDoor('bottom', col) ? 'Türe entfernen' : 'Türe hier markieren'">
                                        <span x-show="hasDoor('bottom', col)" class="text-xs font-bold leading-none">Türe</span>
                                        <span x-show="!hasDoor('bottom', col)" class="text-sm leading-none">+</span>
                                    </button>
                                </template>
                                <div class="w-8"></div><!-- Platzhalter rechts -->
                            </div>

                            <!-- Pult-Indikator UNTEN -->
                            <div x-show="orientation === 'bottom'" class="flex items-center gap-2 mt-2">
                                <div class="w-8"></div>
                                <div class="w-8"></div>
                                <div class="flex-1 flex items-center justify-center gap-2 py-1 px-3 rounded bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold">
                                    <span>🎓</span><span>Pult / Tafel (Reihe 01 beginnt hier)</span>
                                </div>
                                <div class="w-8"></div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Größe ändern Modal -->
            <div x-show="showResizeModal"
                 x-cloak
                 class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
                 @click.self="showResizeModal = false">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
                    <div class="mt-3">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Größe der Vorlage ändern</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reihen</label>
                                <input type="number" x-model.number="newRows" min="1" max="50"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Spalten</label>
                                <input type="number" x-model.number="newColumns" min="1" max="50"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-md">
                                <p class="text-xs text-yellow-700 dark:text-yellow-300">⚠️ Achtung: Plätze außerhalb der neuen Größe gehen verloren!</p>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" @click="showResizeModal = false"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Abbrechen</button>
                            <button type="button" @click="resizeGrid()"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">Größe ändern</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

<script @cspNonce>
function templateEditor(initialSeats, initialRows, initialColumns, templateId, initialDoors, initialOrientation) {
    return {
        seats: {},
        rows: initialRows,
        columns: initialColumns,
        templateId: templateId,
        showResizeModal: false,
        newRows: initialRows,
        newColumns: initialColumns,
        orientation: initialOrientation || 'top',
        // doors: Array von { side: 'top'|'bottom'|'left'|'right', index: number }
        doors: Array.isArray(initialDoors) ? initialDoors : [],

        init() {
            if (Array.isArray(initialSeats)) {
                initialSeats.forEach(seat => {
                    const key = `${seat.row_number}-${seat.column_number}`;
                    this.seats[key] = seat;
                });
            }
        },

        get rowsReversed() {
            // Reihen in der Anzeigereihenfolge: Reihe 01 immer beim Pult.
            // orientation='top'/'left'/'right': Reihe 01 oben → aufsteigend anzeigen.
            // orientation='bottom': Reihe 01 unten → absteigend anzeigen.
            const arr = Array.from({ length: this.rows }, (_, i) => i + 1);
            return this.orientation === 'bottom' ? arr.reverse() : arr;
        },

        getSeat(row, col) {
            return this.seats[`${row}-${col}`] || null;
        },

        getSeatType(row, col) {
            const seat = this.getSeat(row, col);
            return seat ? seat.seat_type : 'available';
        },

        getSeatClass(row, col) {
            const type = this.getSeatType(row, col);
            switch (type) {
                case 'available':
                    return 'border-green-300 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300 hover:bg-green-100 dark:hover:bg-green-900/30';
                case 'teacher_desk':
                    return 'border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30';
                case 'blocked':
                    return 'border-red-300 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/30';
                case 'aisle':
                    return 'border-gray-300 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600';
                case 'reserve':
                    return 'border-amber-400 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/30';
                default:
                    return 'border-gray-300 bg-white dark:bg-gray-800';
            }
        },

        getSeatIcon(row, col) {
            switch (this.getSeatType(row, col)) {
                case 'available':     return '✓';
                case 'teacher_desk':  return 'Pult';
                case 'blocked':       return '✕';
                case 'aisle':         return '—';
                case 'reserve':       return 'R';
                default:              return '';
            }
        },

        toggleSeatType(row, col) {
            // Zyklus: available → teacher_desk → blocked → aisle → reserve → available
            const cycle = {
                available:    'teacher_desk',
                teacher_desk: 'blocked',
                blocked:      'aisle',
                aisle:        'reserve',
                reserve:      'available',
            };
            const newType = cycle[this.getSeatType(row, col)] ?? 'available';
            this.updateSeat(row, col, newType);
        },

        async updateSeat(row, col, seatType) {
            try {
                const response = await fetch(`/rooms/seating-templates/${this.templateId}/update-seat`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ row, column: col, seat_type: seatType })
                });
                if (!response.ok) throw new Error('Update fehlgeschlagen');
                const data = await response.json();
                this.seats[`${row}-${col}`] = data.seat;
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Speichern der Änderung');
            }
        },

        // --- Türen ---
        hasDoor(side, index) {
            return this.doors.some(d => d.side === side && d.index === index);
        },

        async toggleDoor(side, index) {
            if (this.hasDoor(side, index)) {
                this.doors = this.doors.filter(d => !(d.side === side && d.index === index));
            } else {
                this.doors.push({ side, index });
            }
            await this.saveDoors();
        },

        async saveDoors() {
            try {
                const response = await fetch(`/rooms/seating-templates/${this.templateId}/update-doors`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ door_positions: this.doors })
                });
                if (!response.ok) throw new Error('Türen-Update fehlgeschlagen');
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Speichern der Türposition');
            }
        },

        // --- Statistiken ---
        getAvailableSeatsCount() {
            return Object.values(this.seats).filter(s => s.seat_type === 'available').length;
        },

        getReserveSeatsCount() {
            return Object.values(this.seats).filter(s => s.seat_type === 'reserve').length;
        },

        openResizeModal() {
            this.newRows = this.rows;
            this.newColumns = this.columns;
            this.showResizeModal = true;
        },

        async resizeGrid() {
            if (this.newRows < 1 || this.newColumns < 1 || this.newRows > 50 || this.newColumns > 50) {
                alert('Ungültige Größe'); return;
            }
            if (!confirm('Sind Sie sicher? Plätze außerhalb der neuen Größe gehen verloren!')) return;
            try {
                const response = await fetch(`/rooms/seating-templates/${this.templateId}/resize`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ rows: this.newRows, columns: this.newColumns })
                });
                if (!response.ok) throw new Error('Resize fehlgeschlagen');
                window.location.reload();
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Ändern der Größe');
            }
        }
    }
}
</script>
