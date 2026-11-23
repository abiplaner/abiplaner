<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Zeitslot-Templates: {{ $examPeriod->name }}
            </h2>
            @unlessarchive
            <a href="{{ route('slot-templates.create', $examPeriod) }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                + Neues Template
            </a>
            @endarchive
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            


            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Verfügbare Templates</h3>

                @if($templates->isEmpty())
                    <p class="text-gray-500">Noch keine Templates vorhanden.</p>
                @else
                    <div class="space-y-4">
                        @foreach($templates as $template)
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-semibold text-lg">{{ $template->name }}</h4>
                                        @if($template->description)
                                            <p class="text-sm text-gray-600">{{ $template->description }}</p>
                                        @endif
                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ $template->items->count() }} Zeitslot(s)
                                        </p>
                                    </div>
                                    @unlessarchive
                                    <div class="flex gap-2">
                                        <a href="{{ route('slot-templates.edit', [$examPeriod, $template]) }}" 
                                           class="text-blue-600 hover:text-blue-800">
                                            Bearbeiten
                                        </a>
                                        <form method="POST" 
                                              action="{{ route('slot-templates.destroy', [$examPeriod, $template]) }}"
                                              data-confirm="Template wirklich löschen?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                Löschen
                                            </button>
                                        </form>
                                    </div>
                                    @endarchive
                                </div>

                                @if($template->items->isNotEmpty())
                                    <div class="mt-3 text-sm">
                                        <div class="font-medium mb-1">Zeitslots:</div>
                                        <div class="space-y-1">
                                            @foreach($template->items->sortBy('slot_number') as $item)
                                                <div class="flex items-center gap-2 text-gray-700">
                                                    <span class="font-mono">{{ $item->slot_number }}.</span>
                                                    <span class="font-mono">{{ $item->exam_start_time->format('H:i') }} - {{ $item->exam_end_time->format('H:i') }}</span>
                                                    <span class="px-2 py-0.5 rounded text-xs
                                                        @if($item->preference_weight >= 120) bg-green-100 text-green-800
                                                        @elseif($item->preference_weight >= 100) bg-blue-100 text-blue-800
                                                        @elseif($item->preference_weight >= 80) bg-gray-100 text-gray-800
                                                        @elseif($item->preference_weight >= 60) bg-yellow-100 text-yellow-800
                                                        @else bg-red-100 text-red-800
                                                        @endif">
                                                        {{ $item->preference_label }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-semibold text-blue-900 mb-2">💡 So funktionieren Templates</h4>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Erstellen Sie Templates für häufig verwendete Zeitstrukturen (z.B. "Normaler Wochentag", "Freitag verkürzt")</li>
                    <li>• Fügen Sie beliebig viele Zeitslots mit individuellen Zeiten hinzu</li>
                    <li>• Vergeben Sie Präferenz-Gewichte (höher = bevorzugt, niedriger = vermeiden)</li>
                    <li>• Wenden Sie Templates auf mehrere Tage gleichzeitig an</li>
                    <li>• Die erstellten Zeitslots können später noch individuell angepasst werden</li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
