{{--
    Niederschriften-Export-Modal: Tagesauswahl vor dem PDF-Download.
    Tage ohne Prüfungen sind abgewählt; bereits abgeschlossene Tage bleiben wählbar,
    weil Niederschriften auch nachträglich nachgedruckt werden.

    Variablen:
      $days   — Collection<ZusatzpruefDay> (alle konfigurierten Tage, geordnet nach date)
      $placed — Collection<int, Collection<Zusatzpruefung>> (gruppiert nach day_id)
--}}
@php
    // Je Tag: Zahl der angetretenen (nicht abwesenden) Prüflinge — nur die bekommen ein Formular
    $nsDayCounts = [];
    foreach ($days as $day) {
        $nsDayCounts[$day->id] = $placed->get($day->id, collect())
            ->filter(fn ($p) => ! $p->absent && $p->assignment !== null)
            ->count();
    }
    $nsPreselected = array_keys(array_filter($nsDayCounts, fn ($c) => $c > 0));
@endphp
<div x-show="showNiederschriftModal" x-cloak
     class="fixed inset-0 flex items-center justify-center z-50"
     style="background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);"
     @click.self="showNiederschriftModal = false"
     @keydown.escape.window="showNiederschriftModal = false">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4"
         x-data="{
             selectedDays: {{ json_encode($nsPreselected) }},
             allDays: {{ json_encode($days->pluck('id')->values()->toArray()) }},
             counts: {{ json_encode($nsDayCounts) }},
             toggleDay(id) {
                 const idx = this.selectedDays.indexOf(id);
                 if (idx === -1) this.selectedDays.push(id);
                 else this.selectedDays.splice(idx, 1);
             },
             get sheetCount() {
                 return this.selectedDays.reduce((sum, id) => sum + (this.counts[id] || 0), 0);
             },
             selectAll()  { this.selectedDays = [...this.allDays]; },
             selectNone() { this.selectedDays = []; },
             download() {
                 if (this.selectedDays.length === 0) return;
                 const params = this.selectedDays.map(id => 'day_ids[]=' + id).join('&');
                 window.open('{{ route('zusatzpruefungen.export.niederschrift-pdf') }}?' + params, '_blank');
                 showNiederschriftModal = false;
             }
         }">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Niederschriften exportieren</h2>
            <button @click="showNiederschriftModal = false"
                class="text-gray-400 hover:text-gray-600 text-xl leading-none font-light">×</button>
        </div>

        {{-- Body --}}
        <div class="px-5 py-4 space-y-3">
            <p class="text-xs text-gray-500">
                Je Prüfling entsteht ein doppelseitiges A4-Blatt, vorausgefüllt mit Name, Fach,
                Zeiten, Räumen und Prüfungsausschuss.
            </p>

            @if($days->isEmpty())
                <p class="text-xs text-gray-400 text-center py-2">Keine Prüfungstage konfiguriert.</p>
            @else
                <div class="space-y-1.5">
                    @foreach($days as $day)
                        @php $nsCount = $nsDayCounts[$day->id] ?? 0; @endphp
                        <label class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors select-none">
                            <input type="checkbox"
                                :checked="selectedDays.includes({{ $day->id }})"
                                @change="toggleDay({{ $day->id }})"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="flex-1 min-w-0">
                                <span class="text-sm font-medium text-gray-800">
                                    {{ $day->date->locale('de')->isoFormat('dd, D. MMMM YYYY') }}
                                </span>
                                @if($day->label)
                                    <span class="ml-1.5 text-xs text-gray-400">{{ $day->label }}</span>
                                @endif
                                <span class="block text-xs mt-0.5">
                                    @if($nsCount === 0)
                                        <span class="text-gray-400">Keine Prüfungen</span>
                                    @else
                                        <span class="text-blue-600">{{ $nsCount }} {{ $nsCount === 1 ? 'Niederschrift' : 'Niederschriften' }}</span>
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex gap-3 text-xs pt-1">
                    <button @click="selectAll()" class="text-blue-500 hover:text-blue-700 hover:underline">Alle</button>
                    <button @click="selectNone()" class="text-gray-400 hover:text-gray-600 hover:underline">Keine</button>
                    <button @click="selectedDays = {{ json_encode($nsPreselected) }}"
                        class="text-gray-400 hover:text-gray-600 hover:underline">Zurücksetzen</button>
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <button @click="showNiederschriftModal = false"
                class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded border border-gray-200">
                Abbrechen
            </button>
            <button @click="download()"
                :disabled="sheetCount === 0"
                class="px-4 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                <span x-text="'PDF (' + sheetCount + ' ' + (sheetCount === 1 ? 'Blatt' : 'Blätter') + ')'"></span>
            </button>
        </div>
    </div>
</div>
