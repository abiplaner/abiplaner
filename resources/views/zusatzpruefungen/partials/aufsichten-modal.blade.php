{{-- Aufsichten-Modal --}}
{{-- Ein Dropdown pro Zeitslot + Vorbereitungsraum. Änderung → sofort per AJAX persistiert. --}}
{{-- Badge-Zähler im Button wird reaktiv über Alpine-Store zpAufsichten aktualisiert. --}}

<div x-show="showAufsichtenModal" x-cloak
     class="fixed inset-0 z-40 flex items-center justify-center p-4"
     @keydown.escape.window="showAufsichtenModal = false">

    <div class="fixed inset-0 bg-black/40" @click="showAufsichtenModal = false"></div>

    <div class="relative z-50 bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <div>
                <h2 class="font-semibold text-gray-800">Aufsichten Vorbereitungsräume</h2>
                <p class="text-xs text-gray-500 mt-0.5">Aufsicht wählen → wird sofort gespeichert (Dienstbeginn 10&thinsp;Min. vor Vorbereitungsbeginn)</p>
            </div>
            <button @click="showAufsichtenModal = false"
                    class="text-gray-400 hover:text-gray-600 text-xl ml-4 leading-none">×</button>
        </div>

        {{-- Inhalt --}}
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">

            @forelse($days as $day)
                @php
                    $prepDayRooms = $day->dayRooms
                        ->filter(fn($dr) => $dr->prep_room_id !== null)
                        ->unique('prep_room_id');
                    $timeslots = $day->timeslots->sortBy('start_time');
                @endphp

                <div>
                    <h3 class="font-semibold text-sm text-gray-800 mb-3">
                        {{ $day->date->locale('de')->translatedFormat('l, d.m.Y') }}
                        @if($day->label)
                            <span class="font-normal text-gray-400">– {{ $day->label }}</span>
                        @endif
                    </h3>

                    @if($prepDayRooms->isEmpty())
                        <p class="text-xs text-gray-400 italic ml-1">Keine Vorbereitungsräume für diesen Tag konfiguriert.</p>

                    @elseif($timeslots->isEmpty())
                        <p class="text-xs text-amber-600 ml-1">
                            Keine Zeitslots konfiguriert.
                            <button @click="showAufsichtenModal = false; showTimeslotModal = true"
                                    class="underline hover:no-underline ml-1">→ Zeitslots anlegen</button>
                        </p>

                    @else
                        @foreach($prepDayRooms as $dayRoom)
                            @php
                                $sharedDayRoomIds = $day->dayRooms
                                    ->where('prep_room_id', $dayRoom->prep_room_id)
                                    ->pluck('id');
                            @endphp

                            <div class="mb-4 ml-1">
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                    Vorbereitungsraum {{ $dayRoom->prepRoom?->room_number ?? '–' }}
                                </div>

                                <div class="space-y-1">
                                @foreach($timeslots as $ts)
                                    @php
                                        $dienstStart    = \Carbon\Carbon::createFromTimeString($ts->start_time)->subMinutes(10)->format('H:i');
                                        $prepEnd        = \Carbon\Carbon::createFromTimeString($ts->start_time)->addMinutes(20)->format('H:i');
                                        $examEnd        = \Carbon\Carbon::createFromTimeString($ts->start_time)->addMinutes(40)->format('H:i');
                                        $currentAuf     = $ts->aufsichten->whereIn('zusatzpruef_day_room_id', $sharedDayRoomIds->toArray())->first();
                                        $currentTeacher = $currentAuf?->teacher_id ?? '';
                                        $uncovered      = isset($uncoveredKeys[$day->id . ':' . $ts->id]);
                                        $comboKey       = $day->id . ':' . $ts->id . ':' . $dayRoom->prep_room_id;
                                        $needsAufsicht  = isset($slotsWithExams[$comboKey]);
                                        $orphaned       = $currentTeacher !== '' && !$needsAufsicht;
                                    @endphp

                                    <div class="flex items-center gap-3 py-1.5
                                                {{ $uncovered ? 'bg-amber-50/70 -mx-1 px-1 rounded' : '' }}
                                                {{ $orphaned  ? 'bg-red-50/60 -mx-1 px-1 rounded'   : '' }}"
                                         x-data="aufsichtSlot(
                                             {{ $day->id }},
                                             {{ $dayRoom->id }},
                                             {{ $ts->id }},
                                             '{{ $currentTeacher }}',
                                             {{ $needsAufsicht ? 'true' : 'false' }},
                                             {{ $orphaned ? 'true' : 'false' }}
                                         )">

                                        {{-- Zeitangabe --}}
                                        <div class="w-44 shrink-0 text-xs">
                                            <span class="font-mono font-semibold
                                                         {{ $uncovered ? 'text-amber-700' : ($orphaned ? 'text-red-500 line-through' : 'text-gray-700') }}">
                                                Dienst ab {{ $dienstStart }}
                                                @if($uncovered)
                                                    <span class="text-amber-500 ml-0.5" title="Keine Aufsicht – Prüfung findet statt">⚠</span>
                                                @endif
                                                @if($orphaned)
                                                    <span class="text-orange-400 ml-0.5" title="Kein Prüfling – Aufsicht überflüssig">✕</span>
                                                @endif
                                            </span>
                                            <span class="text-gray-400 ml-1">Vorb. {{ $ts->start_time }}–{{ $prepEnd }} · –{{ $examEnd }}</span>
                                            @if($orphaned)
                                                <div class="text-orange-500 mt-0.5 font-medium">Kein Prüfling eingetragen</div>
                                            @endif
                                        </div>

                                        {{-- Dropdown --}}
                                        <div class="flex items-center gap-2">
                                            <select x-model="teacherId"
                                                    @change="save()"
                                                    :disabled="busy"
                                                    class="text-xs border-gray-300 rounded py-1 max-w-56
                                                           disabled:opacity-50 disabled:cursor-wait
                                                           {{ $uncovered ? 'border-amber-300 bg-amber-50' : '' }}
                                                           {{ $orphaned  ? 'border-orange-300 bg-orange-50' : '' }}">
                                                <option value="">– keine –</option>
                                                @foreach($teachers as $t)
                                                    <option value="{{ $t->id }}">{{ $t->shortcode }} {{ $t->lastname }}</option>
                                                @endforeach
                                            </select>
                                            <span x-show="busy" class="text-xs text-gray-400">…</span>
                                            <span x-show="saved && !busy"
                                                  x-transition:enter="transition ease-out duration-200"
                                                  x-transition:leave="transition ease-in duration-500"
                                                  class="text-green-500 text-sm">✓</span>
                                        </div>

                                    </div>
                                @endforeach
                                </div>

                            </div>
                        @endforeach
                    @endif
                </div>

                @if(!$loop->last)<hr class="border-gray-100">@endif

            @empty
                <div class="text-center py-10 text-sm text-gray-400">
                    <p>Noch keine Prüfungstage konfiguriert.</p>
                    <p class="mt-1">Bitte zuerst über <strong>⚙ Tage &amp; Räume</strong> Tage anlegen.</p>
                </div>
            @endforelse

        </div>

        <div class="px-5 py-3 border-t border-gray-200 flex justify-end">
            <button @click="showAufsichtenModal = false"
                    class="px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded">
                Schließen
            </button>
        </div>

    </div>
</div>

<script @cspNonce>
function aufsichtSlot(dayId, dayRoomId, timeslotId, initialTeacherId, needsAufsicht, isOrphaned) {
    return {
        dayId, dayRoomId, timeslotId,
        teacherId:     String(initialTeacherId),
        wasEmpty:      !initialTeacherId,
        wasOrphaned:   isOrphaned,
        needsAufsicht,
        busy:  false,
        saved: false,

        async save() {
            this.busy  = true;
            this.saved = false;
            const nowEmpty = !this.teacherId;
            const token = document.querySelector('meta[name="csrf-token"]').content;
            try {
                const res = await fetch('/zusatzpruefungen/aufsichten', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({
                        zusatzpruef_day_id:          this.dayId,
                        zusatzpruef_day_room_id:     this.dayRoomId,
                        zusatzpruef_day_timeslot_id: this.timeslotId,
                        teacher_id:                  this.teacherId || null,
                    }),
                });

                if (res.ok) {
                    const store = Alpine.store('zpAufsichten');

                    // Fehlende Aufsichten aktualisieren
                    if (this.needsAufsicht) {
                        if (this.wasEmpty && !nowEmpty) store.update(-1);
                        else if (!this.wasEmpty && nowEmpty) store.update(+1);
                    }

                    // Überflüssige Aufsichten aktualisieren
                    if (this.wasOrphaned && nowEmpty) {
                        store.updateOrphaned(-1);
                        this.wasOrphaned = false;
                    }

                    this.wasEmpty = nowEmpty;
                    this.saved = true;
                    setTimeout(() => { this.saved = false; }, 2000);
                } else {
                    Alpine.store('zpToast').message = 'Fehler beim Speichern.';
                    Alpine.store('zpToast').error   = true;
                    Alpine.store('zpToast').visible = true;
                    setTimeout(() => { Alpine.store('zpToast').visible = false; }, 3500);
                }
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
