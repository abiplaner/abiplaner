<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lehrerverfügbarkeit (Kolloquiumsplanung)
            </h2>
            <a href="{{ route('planning.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Zur Kolloquiumsplanung
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Lehrer-Auswahl --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <label for="teacher_select" class="block text-sm font-medium text-gray-700 mb-2">
                        Lehrer auswählen
                    </label>
                    <select id="teacher_select" class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- Bitte wählen --</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher }}">{{ $teacher }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Stundenplan-Anzeige --}}
            <div id="schedule_container" class="hidden">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">
                            Stundenplan: <span id="teacher_name" class="text-indigo-600"></span>
                        </h3>

                        {{-- Wochenplan-Tabelle --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stunde</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montag</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dienstag</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mittwoch</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Donnerstag</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Freitag</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="schedule_body">
                                    {{-- Wird via JavaScript befüllt --}}
                                </tbody>
                            </table>
                        </div>

                        {{-- Legende --}}
                        <div class="mt-6 flex items-center space-x-4 text-sm">
                            <div class="flex items-center">
                                <span class="w-4 h-4 bg-red-200 border border-red-300 rounded mr-2"></span>
                                Belegt
                            </div>
                            <div class="flex items-center">
                                <span class="w-4 h-4 bg-green-200 border border-green-300 rounded mr-2"></span>
                                Frei
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Placeholder wenn kein Lehrer ausgewählt --}}
            <div id="placeholder" class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Auswahl</h3>
                <p class="mt-1 text-sm text-gray-500">Wählen Sie einen Lehrer aus, um den Stundenplan anzuzeigen.</p>
            </div>

        </div>
    </div>

    @push('scripts')
    <script @cspNonce>
        document.getElementById('teacher_select').addEventListener('change', function() {
            const teacherCode = this.value;
            const scheduleContainer = document.getElementById('schedule_container');
            const placeholder = document.getElementById('placeholder');
            const teacherName = document.getElementById('teacher_name');
            
            if (!teacherCode) {
                scheduleContainer.classList.add('hidden');
                placeholder.classList.remove('hidden');
                return;
            }

            // Fetch teacher schedule
            fetch(`/timetable/teacher/${teacherCode}/schedule`)
                .then(response => response.json())
                .then(data => {
                    teacherName.textContent = data.teacher_code;
                    renderSchedule(data.schedule);
                    placeholder.classList.add('hidden');
                    scheduleContainer.classList.remove('hidden');
                })
                .catch(error => {
                    console.error('Error fetching schedule:', error);
                    alert('Fehler beim Laden des Stundenplans');
                });
        });

        function renderSchedule(schedule) {
            const tbody = document.getElementById('schedule_body');
            tbody.innerHTML = '';

            // Create rows for periods 1-11
            for (let period = 1; period <= 11; period++) {
                const row = document.createElement('tr');
                
                // Period number
                const periodCell = document.createElement('td');
                periodCell.className = 'px-4 py-3 text-sm font-medium text-gray-900';
                periodCell.textContent = period + '.';
                row.appendChild(periodCell);

                // Days 1-5 (Monday-Friday)
                for (let day = 1; day <= 5; day++) {
                    const cell = document.createElement('td');
                    cell.className = 'px-4 py-3 text-sm';
                    
                    // Find entry for this day/period
                    const daySchedule = schedule[day] || [];
                    const entry = daySchedule.find(e => e.period === period);
                    
                    if (entry) {
                        cell.className += ' bg-red-50 border-l-4 border-red-400';
                        cell.innerHTML = `
                            <div class="font-semibold text-red-900">${entry.class_name}</div>
                            <div class="text-xs text-red-700">${entry.course_code || ''}</div>
                            <div class="text-xs text-gray-600">${entry.room || ''}</div>
                        `;
                    } else {
                        cell.className += ' bg-green-50';
                        cell.innerHTML = '<span class="text-green-600 text-xs">Frei</span>';
                    }
                    
                    row.appendChild(cell);
                }

                tbody.appendChild(row);
            }
        }
    </script>
    @endpush
</x-app-layout>
