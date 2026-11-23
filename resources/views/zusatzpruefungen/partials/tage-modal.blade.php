{{-- Tage & Räume Modal --}}
{{-- $rooms wird vom Controller übergeben --}}

<div x-show="showDayModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4"
     @keydown.escape.window="showDayModal = false">
    <div class="fixed inset-0 bg-black/40" @click="showDayModal = false"></div>
    <div class="relative z-50 bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <h2 class="font-semibold text-gray-800">Tage &amp; Räume konfigurieren</h2>
            <button @click="showDayModal = false" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
        </div>

        {{-- Inhalt --}}
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">

            {{-- Bestehende Tage --}}
            @forelse($days as $day)
                <div class="border border-gray-200 rounded-lg p-3 space-y-3">

                    {{-- Tag-Kopfzeile --}}
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-sm text-gray-700">
                            {{ $day->date->locale('de')->translatedFormat('l, d.m.Y') }}
                            @if($day->label) – {{ $day->label }} @endif
                        </div>
                        @unlessarchive
                        <form method="POST" action="{{ route('zusatzpruefungen.days.destroy', $day) }}"
                              data-confirm="Tag wirklich löschen?">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600">Löschen</button>
                        </form>
                        @endarchive
                    </div>

                    {{-- Raum-Konfiguration --}}
                    @php
                        $dayExamRoomIds = $day->dayRooms->pluck('exam_room_id')->unique()->toArray();
                        $dayPrepRoomIds = $day->dayRooms->pluck('prep_room_id')->filter()->unique()->toArray();
                    @endphp
                    <div>
                        <div class="text-xs font-semibold text-gray-500 mb-1">Räume</div>
                        <form method="POST" action="{{ route('zusatzpruefungen.days.rooms.set', $day) }}"
                              x-data="{
                                  examIds: {{ json_encode($dayExamRoomIds) }},
                                  prepIds: {{ json_encode($dayPrepRoomIds) }},
                                  toggleExam(id) {
                                      const idx = this.examIds.indexOf(id);
                                      if (idx === -1) {
                                          this.examIds.push(id);
                                          this.prepIds = this.prepIds.filter(p => p !== id);
                                      } else {
                                          this.examIds.splice(idx, 1);
                                      }
                                  },
                                  togglePrep(id) {
                                      const idx = this.prepIds.indexOf(id);
                                      if (idx === -1) {
                                          this.prepIds.push(id);
                                          this.examIds = this.examIds.filter(e => e !== id);
                                      } else {
                                          this.prepIds.splice(idx, 1);
                                      }
                                  }
                              }">
                            @csrf
                            <template x-for="id in examIds" :key="'e'+id">
                                <input type="hidden" name="exam_room_ids[]" :value="id">
                            </template>
                            <template x-for="id in prepIds" :key="'p'+id">
                                <input type="hidden" name="prep_room_ids[]" :value="id">
                            </template>
                            <table class="w-full text-sm border-collapse">
                                <thead>
                                    <tr class="text-xs text-gray-500 border-b border-gray-200">
                                        <th class="text-left font-medium py-1 pr-3">Raum</th>
                                        <th class="text-center font-medium py-1 px-3 w-28">Prüfungsraum</th>
                                        <th class="text-center font-medium py-1 px-3 w-28">Vorbereitungsraum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rooms as $r)
                                    <tr class="border-b border-gray-100 hover:bg-gray-50"
                                        :class="{
                                            'bg-blue-50':   examIds.includes({{ $r->id }}),
                                            'bg-orange-50': prepIds.includes({{ $r->id }})
                                        }">
                                        <td class="py-1 pr-3 text-gray-700">{{ $r->room_number }}</td>
                                        <td class="py-1 px-3 text-center">
                                            <input type="checkbox"
                                                   :checked="examIds.includes({{ $r->id }})"
                                                   @change="toggleExam({{ $r->id }})"
                                                   @archive disabled @endarchive
                                                   class="rounded border-gray-300 text-blue-600 cursor-pointer">
                                        </td>
                                        <td class="py-1 px-3 text-center">
                                            <input type="checkbox"
                                                   :checked="prepIds.includes({{ $r->id }})"
                                                   @change="togglePrep({{ $r->id }})"
                                                   @archive disabled @endarchive
                                                   class="rounded border-gray-300 text-orange-500 cursor-pointer">
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @unlessarchive
                            <button type="submit" class="text-xs text-blue-500 hover:text-blue-700 mt-1">Räume speichern</button>
                            @endarchive
                        </form>
                    </div>

                </div>
            @empty
                <p class="text-sm text-gray-400 text-center py-4">Noch keine Tage angelegt.</p>
            @endforelse

            {{-- Neuer Tag --}}
            @unlessarchive
            <div class="border border-dashed border-gray-300 rounded-lg p-3">
                <div class="text-sm font-semibold text-gray-600 mb-2">Neuer Tag</div>
                <form method="POST" action="{{ route('zusatzpruefungen.days.store') }}" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Datum</label>
                        <input type="date" name="date" required
                               class="text-sm border-gray-300 rounded py-1 px-2">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Label (optional)</label>
                        <input type="text" name="label" placeholder="z. B. Tag 1" maxlength="100"
                               class="text-sm border-gray-300 rounded py-1 px-2 w-32">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Startzeit</label>
                        <input type="time" name="start_time" value="13:30" required
                               class="text-sm border-gray-300 rounded py-1 px-2">
                    </div>
                    <button type="submit"
                        class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                        Tag anlegen
                    </button>
                </form>
            </div>
            @endarchive
        </div>

        {{-- Footer --}}
        <div class="px-4 py-3 border-t border-gray-200 flex justify-end">
            <button @click="showDayModal = false"
                class="px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded">
                Schließen
            </button>
        </div>
    </div>
</div>
