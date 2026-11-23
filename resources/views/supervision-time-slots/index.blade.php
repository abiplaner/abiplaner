<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Aufsichts-Zeitslots') }} - {{ $examPeriod->name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('supervisions.index', $examPeriod) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                    Zurück zur Planung
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Zeitslot manuell hinzufügen -->
            @unlessarchive
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                <h3 class="text-lg font-semibold mb-4">Neuen Zeitslot hinzufügen</h3>
                
                <form method="POST" action="{{ route('supervisions.time-slots.store', $examPeriod) }}" class="space-y-4">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Datum</label>
                            <select name="date" required class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Bitte wählen...</option>
                                @php
                                    $week1Start = new DateTime($examPeriod->week_1_start);
                                    $week1End = new DateTime($examPeriod->week_1_end);
                                    $week2Start = new DateTime($examPeriod->week_2_start);
                                    $week2End = new DateTime($examPeriod->week_2_end);
                                    
                                    $dates = [];
                                    
                                    // Woche 1
                                    $current = clone $week1Start;
                                    while ($current <= $week1End) {
                                        // Nur Wochentage (Mo-Fr)
                                        if ($current->format('N') <= 5) {
                                            $dates[] = ['date' => $current->format('Y-m-d'), 'label' => $current->format('d.m.Y') . ' (' . ['Mo', 'Di', 'Mi', 'Do', 'Fr'][$current->format('N')-1] . ', Woche 1)'];
                                        }
                                        $current->modify('+1 day');
                                    }
                                    
                                    // Woche 2
                                    $current = clone $week2Start;
                                    while ($current <= $week2End) {
                                        // Nur Wochentage (Mo-Fr)
                                        if ($current->format('N') <= 5) {
                                            $dates[] = ['date' => $current->format('Y-m-d'), 'label' => $current->format('d.m.Y') . ' (' . ['Mo', 'Di', 'Mi', 'Do', 'Fr'][$current->format('N')-1] . ', Woche 2)'];
                                        }
                                        $current->modify('+1 day');
                                    }
                                @endphp
                                @foreach($dates as $dateOption)
                                    <option value="{{ $dateOption['date'] }}">{{ $dateOption['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Startzeit</label>
                            <input type="time" name="start_time" required
                                   class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Endzeit</label>
                            <input type="time" name="end_time" required
                                   class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Zeitslot hinzufügen
                            </button>
                        </div>
                    </div>
                </form>
                
                @if($timeSlots->count() > 0)
                    <div class="mt-4 pt-4 border-t">
                        <button type="button" data-confirm="Wirklich alle Zeitslots löschen?" data-submit="delete-all-form"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Alle Zeitslots löschen
                        </button>
                    </div>
                @endif
                
                <form id="delete-all-form" method="POST" action="{{ route('supervisions.time-slots.destroy-all', $examPeriod) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
            @endarchive

            <!-- Vorhandene Zeitslots -->
            @if($timeSlots->count() > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Vorhandene Zeitslots ({{ $timeSlots->flatten()->count() }})</h3>
                    
                    @foreach($timeSlots as $date => $slots)
                        <div class="mb-6 border rounded-lg p-4">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="font-semibold text-gray-700">
                                    {{ \Carbon\Carbon::parse($date)->locale('de')->isoFormat('dddd, DD.MM.YYYY') }}
                                    <span class="text-sm text-gray-500 ml-2">({{ $slots->count() }} Zeitslots)</span>
                                </h4>
                                
                                <!-- Kopierfunktion -->
                                @unlessarchive
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('supervisions.time-slots.copy', $examPeriod) }}" 
                                          class="flex gap-2 items-center">
                                        @csrf
                                        <input type="hidden" name="source_date" value="{{ $date }}">
                                        <label class="text-sm text-gray-600">Kopieren nach:</label>
                                        <select name="target_dates[]" multiple size="3" required
                                                class="text-sm border-gray-300 rounded">
                                            @php
                                                $week1Start = new DateTime($examPeriod->week_1_start);
                                                $week1End = new DateTime($examPeriod->week_1_end);
                                                $week2Start = new DateTime($examPeriod->week_2_start);
                                                $week2End = new DateTime($examPeriod->week_2_end);
                                                
                                                $allDates = [];
                                                
                                                // Woche 1
                                                $current = clone $week1Start;
                                                while ($current <= $week1End) {
                                                    if ($current->format('N') <= 5 && $current->format('Y-m-d') !== $date) {
                                                        $allDates[] = ['date' => $current->format('Y-m-d'), 'label' => $current->format('d.m.Y') . ' (' . ['Mo', 'Di', 'Mi', 'Do', 'Fr'][$current->format('N')-1] . ')'];
                                                    }
                                                    $current->modify('+1 day');
                                                }
                                                
                                                // Woche 2
                                                $current = clone $week2Start;
                                                while ($current <= $week2End) {
                                                    if ($current->format('N') <= 5 && $current->format('Y-m-d') !== $date) {
                                                        $allDates[] = ['date' => $current->format('Y-m-d'), 'label' => $current->format('d.m.Y') . ' (' . ['Mo', 'Di', 'Mi', 'Do', 'Fr'][$current->format('N')-1] . ')'];
                                                    }
                                                    $current->modify('+1 day');
                                                }
                                            @endphp
                                            @foreach($allDates as $targetDate)
                                                <option value="{{ $targetDate['date'] }}">{{ $targetDate['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" 
                                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                            Kopieren
                                        </button>
                                    </form>
                                </div>
                                @endarchive
                            </div>
                            
                            <div class="space-y-2">
                                @foreach($slots as $slot)
                                    <div class="flex items-center justify-between border rounded p-3 hover:bg-gray-50">
                                        <div class="flex items-center gap-4 flex-1">
                                            <span class="text-sm font-medium text-gray-600 w-8">#{{ $slot->slot_number }}</span>
                                            
                                            <!-- Inline Edit Form -->
                                            @unlessarchive
                                            <form method="POST" action="{{ route('supervisions.time-slots.update', [$examPeriod, $slot]) }}" 
                                                  class="flex items-center gap-2 flex-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="date" value="{{ $slot->date }}">
                                                <input type="hidden" name="slot_number" value="{{ $slot->slot_number }}">
                                                
                                                <input type="time" name="start_time" value="{{ substr($slot->start_time, 0, 5) }}" required
                                                       class="text-sm border-gray-300 rounded px-2 py-1">
                                                <span>-</span>
                                                <input type="time" name="end_time" value="{{ substr($slot->end_time, 0, 5) }}" required
                                                       class="text-sm border-gray-300 rounded px-2 py-1">
                                                
                                                <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm px-2">
                                                    Speichern
                                                </button>
                                            </form>
                                            @else
                                            <span class="text-sm text-gray-700">
                                                {{ substr($slot->start_time, 0, 5) }} – {{ substr($slot->end_time, 0, 5) }}
                                            </span>
                                            @endarchive
                                            
                                            @if($slot->supervisions->count() > 0)
                                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded whitespace-nowrap">
                                                    {{ $slot->supervisions->count() }} Aufsichten
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <div class="flex gap-2">
                                            @unlessarchive
                                            <form method="POST" action="{{ route('supervisions.time-slots.destroy', [$examPeriod, $slot]) }}" 
                                                  data-confirm="Zeitslot wirklich löschen?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="text-red-600 hover:text-red-800 text-sm"
                                                        {{ $slot->supervisions->count() > 0 ? 'disabled' : '' }}>
                                                    Löschen
                                                </button>
                                            </form>
                                            @endarchive
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-gray-600">Noch keine Zeitslots vorhanden. Generieren Sie welche mit dem Formular oben.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
