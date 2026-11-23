<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Template bearbeiten: {{ $slotTemplate->name }}
            </h2>
            <a href="{{ route('slot-templates.index', $examPeriod) }}" 
               class="text-gray-600 hover:text-gray-800">
                ← Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="slotEditor()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Template-Grunddaten --}}
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Template-Grunddaten</h3>
                <form method="POST" action="{{ route('slot-templates.update', [$examPeriod, $slotTemplate]) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="name" value="{{ old('name', $slotTemplate->name) }}" 
                                   class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                            <textarea name="description" rows="2" 
                                      class="w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $slotTemplate->description) }}</textarea>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>

            {{-- Zeitslots im Template --}}
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Zeitslots</h3>

                @if($slotTemplate->items->isEmpty())
                    <p class="text-gray-500 mb-4">Noch keine Zeitslots hinzugefügt.</p>
                @else
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slot</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vorbereitung</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prüfung</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Präferenz</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($slotTemplate->items->sortBy('slot_number') as $item)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono">{{ $item->slot_number }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono text-sm">
                                            {{ $item->prep_start_time->format('H:i') }} - {{ $item->prep_end_time->format('H:i') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono text-sm">
                                            {{ $item->exam_start_time->format('H:i') }} - {{ $item->exam_end_time->format('H:i') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 rounded text-xs font-medium
                                                @if($item->preference_weight >= 120) bg-green-100 text-green-800
                                                @elseif($item->preference_weight >= 100) bg-blue-100 text-blue-800
                                                @elseif($item->preference_weight >= 80) bg-gray-100 text-gray-800
                                                @elseif($item->preference_weight >= 60) bg-yellow-100 text-yellow-800
                                                @else bg-red-100 text-red-800
                                                @endif">
                                                {{ $item->preference_weight }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right">
                                            <button type="button"
                                                    @click="openEdit({
                                                        id: {{ $item->id }},
                                                        slot_number: {{ $item->slot_number }},
                                                        prep_start_time: '{{ $item->prep_start_time->format('H:i') }}',
                                                        prep_end_time: '{{ $item->prep_end_time->format('H:i') }}',
                                                        exam_start_time: '{{ $item->exam_start_time->format('H:i') }}',
                                                        exam_end_time: '{{ $item->exam_end_time->format('H:i') }}',
                                                        preference_weight: {{ $item->preference_weight }},
                                                        notes: '{{ addslashes($item->notes ?? '') }}'
                                                    })"
                                                    class="text-blue-600 hover:text-blue-800 mr-2">
                                                Bearbeiten
                                            </button>
                                            <form method="POST" 
                                                  action="{{ route('slot-template-items.destroy', [$examPeriod, $slotTemplate, $item]) }}"
                                                  class="inline"
                                                  data-confirm="Zeitslot wirklich löschen?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800">
                                                    Löschen
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Neuen Zeitslot hinzufügen --}}
                <div class="border-t pt-4">
                    <h4 class="font-medium mb-3">Neuen Zeitslot hinzufügen</h4>
                    <form method="POST" action="{{ route('slot-template-items.store', [$examPeriod, $slotTemplate]) }}" 
                          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        @csrf
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slot Nr.</label>
                            <input type="number" name="slot_number" value="{{ $slotTemplate->items->max('slot_number') + 1 }}" 
                                   min="1" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vorb. Start</label>
                            <input type="time" name="prep_start_time" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vorb. Ende</label>
                            <input type="time" name="prep_end_time" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prüf. Start</label>
                            <input type="time" name="exam_start_time" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prüf. Ende</label>
                            <input type="time" name="exam_end_time" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Präferenz-Gewicht
                                <span class="text-xs text-gray-500">(0-200)</span>
                            </label>
                            <input type="number" name="preference_weight" value="100" 
                                   min="0" max="200" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div class="md:col-span-2 lg:col-span-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notizen (optional)</label>
                            <input type="text" name="notes" class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        
                        <div class="md:col-span-2 lg:col-span-4">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                                + Zeitslot hinzufügen
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-4 p-4 bg-gray-50 rounded text-sm text-gray-700">
                    <div class="font-medium mb-2">💡 Präferenz-Gewichte:</div>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-green-500 rounded"></span>
                            <span>120+: Sehr bevorzugt</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-blue-500 rounded"></span>
                            <span>100-119: Bevorzugt</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-gray-400 rounded"></span>
                            <span>80-99: Neutral</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-yellow-500 rounded"></span>
                            <span>60-79: Weniger</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-red-500 rounded"></span>
                            <span>&lt;60: Vermeiden</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Template auf Tage anwenden --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Template auf Tage anwenden</h3>
                
                @if($slotTemplate->items->isEmpty())
                    <p class="text-gray-500">Fügen Sie zuerst Zeitslots zum Template hinzu.</p>
                @else
                    <form method="POST" action="{{ route('slot-templates.apply', [$examPeriod, $slotTemplate]) }}">
                        @csrf
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-3">
                                Wählen Sie die Tage aus, auf die dieses Template angewendet werden soll. 
                                Für jeden Tag werden {{ $slotTemplate->items->count() }} Zeitslot(s) erstellt.
                            </p>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @php
                                    $week1Dates = $examPeriod->getExamDates(1);
                                    $week2Dates = $examPeriod->getExamDates(2);
                                @endphp
                                
                                <div class="col-span-2 md:col-span-4 font-medium text-gray-700">Woche 1:</div>
                                @foreach($week1Dates as $date)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="dates[]" value="{{ $date->format('Y-m-d') }}" 
                                               class="rounded border-gray-300">
                                        <span>{{ $date->locale('de')->isoFormat('dd, DD.MM.') }}</span>
                                    </label>
                                @endforeach
                                
                                <div class="col-span-2 md:col-span-4 font-medium text-gray-700 mt-2">Woche 2:</div>
                                @foreach($week2Dates as $date)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="dates[]" value="{{ $date->format('Y-m-d') }}" 
                                               class="rounded border-gray-300">
                                        <span>{{ $date->locale('de')->isoFormat('dd, DD.MM.') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                            Template auf ausgewählte Tage anwenden
                        </button>
                    </form>
                @endif
            </div>
        </div>


        {{-- Edit-Modal --}}
        <div x-show="showModal" 
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4"
                 @click.away="showModal = false">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Zeitslot bearbeiten</h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form method="POST" :action="updateUrl">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slot Nr.</label>
                            <input type="number" name="slot_number" x-model="form.slot_number"
                                   min="1" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vorb. Start</label>
                            <input type="time" name="prep_start_time" x-model="form.prep_start_time"
                                   class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vorb. Ende</label>
                            <input type="time" name="prep_end_time" x-model="form.prep_end_time"
                                   class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prüf. Start</label>
                            <input type="time" name="exam_start_time" x-model="form.exam_start_time"
                                   class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prüf. Ende</label>
                            <input type="time" name="exam_end_time" x-model="form.exam_end_time"
                                   class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Präferenz-Gewicht <span class="text-xs text-gray-500">(0-200)</span>
                            </label>
                            <input type="number" name="preference_weight" x-model="form.preference_weight"
                                   min="0" max="200" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notizen (optional)</label>
                            <input type="text" name="notes" x-model="form.notes"
                                   class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>

                    <div class="mt-5 flex gap-3 justify-end">
                        <button type="button" @click="showModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Abbrechen
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script @cspNonce>
        function slotEditor() {
            return {
                showModal: false,
                updateUrl: '',
                form: {
                    slot_number: 1,
                    prep_start_time: '',
                    prep_end_time: '',
                    exam_start_time: '',
                    exam_end_time: '',
                    preference_weight: 100,
                    notes: ''
                },
                openEdit(item) {
                    const base = '{{ route('slot-template-items.update', [$examPeriod, $slotTemplate, '__ID__']) }}';
                    this.updateUrl = base.replace('__ID__', item.id);
                    this.form = {
                        slot_number: item.slot_number,
                        prep_start_time: item.prep_start_time,
                        prep_end_time: item.prep_end_time,
                        exam_start_time: item.exam_start_time,
                        exam_end_time: item.exam_end_time,
                        preference_weight: item.preference_weight,
                        notes: item.notes
                    };
                    this.showModal = true;
                }
            };
        }
    </script>
</x-app-layout>
