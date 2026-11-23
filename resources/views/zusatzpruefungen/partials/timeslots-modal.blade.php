{{-- Zeitslots-Modal --}}

<div x-show="showTimeslotModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4"
     @keydown.escape.window="showTimeslotModal = false">
    <div class="fixed inset-0 bg-black/40" @click="showTimeslotModal = false"></div>
    <div class="relative z-50 bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div>
                <h2 class="font-semibold text-gray-800">Zeitslots konfigurieren</h2>
                <p class="text-xs text-gray-500 mt-0.5">Beginn der Vorbereitungszeit je Tag – beliebig viele, überlappend möglich</p>
            </div>
            <button @click="showTimeslotModal = false" class="text-gray-400 hover:text-gray-600 text-xl ml-4">×</button>
        </div>

        {{-- Inhalt --}}
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-5">

            @if(session('error') && session('reopen_timeslot_modal'))
                <div class="px-1 py-2 bg-red-50 border border-red-200 rounded text-red-700 text-xs">
                    {{ session('error') }}
                </div>
            @endif

            @forelse($days as $day)
                <div x-data="{ newTime: '' }">

                    {{-- Tag-Label --}}
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-semibold text-sm text-gray-700">
                            {{ $day->date->locale('de')->translatedFormat('l, d.m.Y') }}
                        </span>
                        @if($day->label)
                            <span class="text-xs text-gray-400">– {{ $day->label }}</span>
                        @endif
                        <span class="ml-auto text-xs text-gray-400">
                            {{ $day->timeslots->count() }}
                            {{ $day->timeslots->count() === 1 ? 'Slot' : 'Slots' }}
                        </span>
                    </div>

                    {{-- Bestehende Slots als Chips --}}
                    <div class="flex flex-wrap gap-1.5 mb-3 min-h-6">
                        @forelse($day->timeslots->sortBy('start_time') as $ts)
                            @php
                                $prepEnd     = \Carbon\Carbon::createFromTimeString($ts->start_time)->addMinutes(20)->format('H:i');
                                $examEnd     = \Carbon\Carbon::createFromTimeString($ts->start_time)->addMinutes(40)->format('H:i');
                                $hasAufsicht = $ts->aufsichten->isNotEmpty();
                            @endphp
                            <span class="group inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs
                                         transition-colors border
                                         {{ $hasAufsicht
                                            ? 'bg-amber-50 border-amber-300 text-amber-900 hover:border-amber-400'
                                            : 'bg-blue-50 border-blue-200 text-blue-900 hover:border-blue-400' }}"
                                  title="Vorb. {{ $ts->start_time }}–{{ $prepEnd }} · Prüfung {{ $prepEnd }}–{{ $examEnd }}{{ $hasAufsicht ? ' · Aufsichten eingetragen' : '' }}">
                                <span class="font-mono font-semibold">{{ $ts->start_time }}</span>
                                <span class="{{ $hasAufsicht ? 'text-amber-400' : 'text-blue-400' }}">
                                    <span class="text-gray-400">→ Prüfung</span> {{ $prepEnd }}–{{ $examEnd }}
                                </span>
                                @if($hasAufsicht)
                                    <span class="text-amber-500 text-xs" title="Aufsichten vorhanden – Löschen gesperrt">🔒</span>
                                @else
                                    @unlessarchive
                                    <form method="POST"
                                          action="{{ route('zusatzpruefungen.days.timeslots.destroy', $ts) }}"
                                          class="inline"
                                          data-confirm="Zeitslot {{ $ts->start_time }} löschen?">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="text-blue-300 hover:text-red-500 leading-none transition-colors"
                                                title="Löschen">×</button>
                                    </form>
                                    @endarchive
                                @endif
                            </span>
                        @empty
                            <span class="text-xs text-gray-400 italic">Noch keine Zeitslots.</span>
                        @endforelse
                    </div>

                    {{-- Neuen Slot hinzufügen --}}
                    @unlessarchive
                    <form method="POST"
                          action="{{ route('zusatzpruefungen.days.timeslots.store', $day) }}"
                          class="flex items-center gap-2">
                        @csrf
                        <input type="time" name="start_time" x-model="newTime"
                               required step="60"
                               class="text-sm border-gray-300 rounded py-1 px-2 w-28 font-mono">
                        <span x-show="newTime" x-cloak class="text-xs text-gray-400" x-text="
                            newTime ? (() => {
                                const [h,m] = newTime.split(':').map(Number);
                                const pe = String(Math.floor((h*60+m+20)/60)).padStart(2,'0') + ':' + String((h*60+m+20)%60).padStart(2,'0');
                                const ee = String(Math.floor((h*60+m+40)/60)).padStart(2,'0') + ':' + String((h*60+m+40)%60).padStart(2,'0');
                                return '→ Prüfung ' + pe + '–' + ee;
                            })() : ''
                        "></span>
                        <button type="submit"
                                :disabled="!newTime"
                                class="px-3 py-1 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-200 disabled:text-gray-400 text-white text-xs rounded transition-colors">
                            + Hinzufügen
                        </button>
                    </form>
                    @endarchive
                </div>

                @if(!$loop->last)
                    <hr class="border-gray-100">
                @endif

            @empty
                <div class="text-center py-8 text-sm text-gray-400">
                    <p>Noch keine Prüfungstage konfiguriert.</p>
                    <p class="mt-1">Bitte zuerst über <strong>⚙ Tage &amp; Räume</strong> Tage anlegen.</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="px-4 py-3 border-t border-gray-200 flex justify-end">
            <button @click="showTimeslotModal = false"
                class="px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded">
                Schließen
            </button>
        </div>
    </div>
</div>
