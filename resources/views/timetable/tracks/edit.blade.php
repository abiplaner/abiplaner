<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Schiene bearbeiten') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('timetable.tracks.update', $track) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $track->name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <p class="text-xs text-gray-500 mt-1">z.B. "Mathematik" oder "Schiene A"</p>
                        </div>

                        <div class="mb-4">
                            <label for="time_slots_input" class="block text-sm font-medium text-gray-700">Zeitfenster</label>
                            @php
                                $currentTimeSlots = '';
                                if (is_array($track->time_slots)) {
                                    $slots = $track->time_slots; // Fix: Assign to variable to avoid "Indirect modification" error with reset()
                                    // Check if it's the simple list of strings we expect, or the complex object from automatic detection
                                    $first = reset($slots);
                                    if (is_array($first) || is_object($first)) {
                                        // Complex structure from detection service, convert to string formatted for display
                                        // We try to reconstruct a string representation if possible, otherwise empty
                                        $parts = [];
                                        foreach ($slots as $slot) {
                                            if ((is_array($slot) || is_object($slot)) && isset($slot['day_short'], $slot['periods'])) {
                                                $periods = is_array($slot['periods']) ? implode('/', $slot['periods']) : $slot['periods'];
                                                $parts[] = $slot['day_short'] . ' ' . $periods . '.';
                                            }
                                        }
                                        $currentTimeSlots = implode(', ', $parts);
                                    } else {
                                        $currentTimeSlots = implode(', ', $slots);
                                    }
                                }
                            @endphp
                            <input type="hidden" name="time_slots_json" id="time_slots_json" value='@json($track->time_slots ?? [])'>
                            <input type="text" name="time_slots_input" id="time_slots_input" value="{{ old('time_slots_input', $currentTimeSlots) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" readonly placeholder="Bitte nutzen Sie das Raster unten">
                            <p class="text-xs text-gray-500 mt-1">Die Zeitfenster werden automatisch aus der Auswahl unten generiert.</p>
                            
                            <!-- Visual Time Slot Picker -->
                            <div class="mt-4 border rounded-md p-4 bg-gray-50 overflow-x-auto">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Zeitfenster auswählen</h4>
                                <table class="w-full text-center text-xs border-collapse">
                                    <thead>
                                        <tr>
                                            <th class="p-2"></th>
                                            @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr'] as $day)
                                                <th class="p-2 font-bold text-gray-600">{{ $day }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($period = 1; $period <= 11; $period++)
                                            <tr>
                                                <td class="p-2 font-bold text-gray-500">{{ $period }}.</td>
                                                @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr'] as $day)
                                                    <td class="p-1">
                                                        <button type="button" 
                                                            class="time-slot-btn w-full py-2 rounded border border-gray-200 bg-white hover:bg-blue-50 focus:outline-none transition-colors duration-200"
                                                            data-day="{{ $day }}" 
                                                            data-period="{{ $period }}"
                                                            data-toggle-time-slot>
                                                        </button>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>

                            <script @cspNonce>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const jsonInput = document.getElementById('time_slots_json');
                                    const textInput = document.getElementById('time_slots_input');
                                    let selectedSlots = [];
                                    
                                    try {
                                        const rawData = JSON.parse(jsonInput.value || '[]');
                                        
                                        if (Array.isArray(rawData)) {
                                            rawData.forEach(slot => {
                                                if (typeof slot === 'string') {
                                                    // Handle string format "Mo 1" or "Mo 1./2" etc.
                                                    // Normalize separators: "Mo 1./2" -> "Mo 1/2" -> "Mo 1 2"
                                                    const match = slot.match(/^([A-Za-z]+)\s+(.+)$/);
                                                    if (match) {
                                                        const day = match[1];
                                                        const periodsStr = match[2];
                                                        const periods = periodsStr.match(/\d+/g); // Find all numbers
                                                        if (periods) {
                                                            periods.forEach(p => {
                                                                selectedSlots.push(`${day}-${parseInt(p)}`);
                                                            });
                                                        }
                                                    }
                                                } else if (slot.day_short && slot.periods) {
                                                    const periods = Array.isArray(slot.periods) ? slot.periods : [slot.periods];
                                                    periods.forEach(p => {
                                                        selectedSlots.push(`${slot.day_short}-${p}`);
                                                    });
                                                }
                                            });
                                        }
                                    } catch(e) {
                                        console.error('Error parsing existing slots', e);
                                        // Fallback: try to parse the text input if JSON is empty/invalid? 
                                        // Actually current controller logic is: if JSON exists, use it. If not, check old input. 
                                        // But here we are initializing the grid from what we have.
                                        // If jsonInput is empty, we might want to seed from text input, but text input is just display now.
                                        // But wait, $track->time_slots is passed to jsonInput. So if it was string array in DB, it is array of strings in JSON.
                                        // So the above string handling logic should cover it.
                                    }

                                    // Initialize buttons
                                    selectedSlots.forEach(slotId => {
                                        const [day, period] = slotId.split('-');
                                        const btn = document.querySelector(`.time-slot-btn[data-day="${day}"][data-period="${period}"]`);
                                        if (btn) {
                                            btn.classList.add('bg-blue-500', 'text-white', 'border-blue-600');
                                            btn.classList.remove('bg-white', 'hover:bg-blue-50');
                                        }
                                    });

                                    // Frueher onclick am Knopf (Modul 7.2).
                                    // Ueber document gebunden, damit die
                                    // Reihenfolge zur Definition egal ist.
                                    document.addEventListener('click', (ereignis) => {
                                        const knopf = ereignis.target.closest('[data-toggle-time-slot]');

                                        if (knopf) {
                                            window.toggleTimeSlot(knopf);
                                        }
                                    });

                                    // Expose toggle function globally
                                    window.toggleTimeSlot = function(btn) {
                                        const day = btn.getAttribute('data-day');
                                        const period = btn.getAttribute('data-period');
                                        const slotId = `${day}-${period}`;
                                        
                                        if (selectedSlots.includes(slotId)) {
                                            // Deselect
                                            selectedSlots = selectedSlots.filter(id => id !== slotId);
                                            btn.classList.remove('bg-blue-500', 'text-white', 'border-blue-600');
                                            btn.classList.add('bg-white', 'hover:bg-blue-50');
                                        } else {
                                            // Select
                                            selectedSlots.push(slotId);
                                            btn.classList.add('bg-blue-500', 'text-white', 'border-blue-600');
                                            btn.classList.remove('bg-white', 'hover:bg-blue-50');
                                        }
                                        
                                        updateInputs();
                                    };

                                    function updateInputs() {
                                        // Convert selectedSlots back to structured JSON format used by backend
                                        // Structure: [{day_short: 'Mo', periods: [1, 2]}, ...]
                                        
                                        const structured = {};
                                        selectedSlots.forEach(slotId => {
                                            const [day, period] = slotId.split('-');
                                            if (!structured[day]) {
                                                structured[day] = [];
                                            }
                                            structured[day].push(parseInt(period));
                                        });

                                        const resultObj = [];
                                        const textParts = [];

                                        // Sort days order
                                        const dayOrder = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];
                                        dayOrder.forEach(day => {
                                            if (structured[day]) {
                                                // Sort periods
                                                structured[day].sort((a, b) => a - b);
                                                
                                                resultObj.push({
                                                    day_short: day,
                                                    periods: structured[day]
                                                });
                                                
                                                // Update text representation
                                                textParts.push(`${day} ${structured[day].join('/')}.`);
                                            }
                                        });

                                        jsonInput.value = JSON.stringify(resultObj);
                                        textInput.value = textParts.join(', ');
                                    }
                                });
                            </script>

                        <div class="mb-6">
                            <label for="course_codes_input" class="block text-sm font-medium text-gray-700">Enthaltene Kurse</label>
                            <p class="text-xs text-gray-500 mb-2">Geben Sie die Kurskürzel durch Komma getrennt ein (z.B. 1gD1, 1eM2).</p>
                            <textarea name="course_codes_input" id="course_codes_input" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">{{ implode(', ', $track->course_codes) }}</textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('timetable.tracks.index') }}" class="text-gray-600 hover:text-gray-900">
                                Abbrechen
                            </a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium">
                                Änderungen speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
