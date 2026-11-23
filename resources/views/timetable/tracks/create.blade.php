<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Neue Schiene erstellen') }}
            </h2>
            <a href="{{ route('timetable.tracks.index', ['school_year' => $schoolYear]) }}" class="text-gray-600 hover:text-gray-900">
                ← Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    @if($errors->any())
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('timetable.tracks.store') }}" class="space-y-6">
                        @csrf

                        {{-- Hidden: School Year --}}
                        <input type="hidden" name="school_year" value="{{ $schoolYear }}">

                        {{-- Grade Selection --}}
                        <div>
                            <label for="grade" class="block text-sm font-medium text-gray-700 mb-2">
                                Jahrgang <span class="text-red-500">*</span>
                            </label>
                            <select id="grade" name="grade" required
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">-- Bitte wählen --</option>
                                <option value="Q12" {{ old('grade') == 'Q12' ? 'selected' : '' }}>Q12</option>
                                <option value="Q13" {{ old('grade') == 'Q13' ? 'selected' : '' }}>Q13</option>
                                <option value="JÜ" {{ old('grade') == 'JÜ' ? 'selected' : '' }}>JÜ (Jahrgangsübergreifend)</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                Wähle den Jahrgang für diese Schiene
                            </p>
                        </div>

                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Name der Schiene <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name') }}"
                                   required
                                   placeholder="z.B. Mathematik Mo/Do"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <p class="mt-1 text-sm text-gray-500">
                                Ein beschreibender Name für diese Schiene
                            </p>
                        </div>

                        {{-- Course Codes --}}
                        <div>
                            <label for="course_codes_input" class="block text-sm font-medium text-gray-700 mb-2">
                                Kurse <span class="text-red-500">*</span>
                            </label>
                            <textarea id="course_codes_input" 
                                      name="course_codes_input" 
                                      rows="3"
                                      required
                                      placeholder="1m1, 1m2, 1m3, 1m4"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('course_codes_input') }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">
                                Komma-getrennte Liste der Kurskürzel (z.B. 1m1, 1m2, 1m3)
                            </p>
                        </div>

                        {{-- Time Slots --}}
                        <div>
                            <label for="time_slots_input" class="block text-sm font-medium text-gray-700 mb-2">
                                Zeitfenster
                            </label>
                            
                            <input type="hidden" name="time_slots_json" id="time_slots_json" value="[]">
                            <input type="text" 
                                   id="time_slots_input" 
                                   name="time_slots_input" 
                                   value="{{ old('time_slots_input') }}"
                                   placeholder="Bitte nutzen Sie das Raster unten"
                                   readonly
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <p class="mt-1 text-sm text-gray-500">
                                Die Zeitfenster werden automatisch aus der Auswahl unten generiert.
                            </p>
                            
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
                                    
                                    // Initialize from old input if present (e.g. valid failure)
                                    // Since we deal with simple create, we usually start empty, but we could try to parse old input if needed
                                    
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
                        </div>

                        {{-- Info Box --}}
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Hinweise zur Eingabe</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <ul class="list-disc list-inside space-y-1">
                                            <li><strong>Kurse:</strong> Gib die Kurskürzel komma-getrennt ein (z.B. 1m1, 1m2, 1m3)</li>
                                            <li><strong>Zeitfenster:</strong> Format: Wochentag + Stunden (z.B. Mo 1./2 bedeutet Montag 1. und 2. Stunde)</li>
                                            <li><strong>Mehrere Zeitfenster:</strong> Mit Komma trennen (z.B. Mo 1./2, Do 3./4)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center justify-end space-x-4 pt-4">
                            <a href="{{ route('timetable.tracks.index', ['school_year' => $schoolYear]) }}" 
                               class="text-gray-700 hover:text-gray-900 px-4 py-2">
                                Abbrechen
                            </a>
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                Schiene erstellen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script @cspNonce>
    // Ensure cleanup of any previous content if needed
    // Already doing inline script
</script>
