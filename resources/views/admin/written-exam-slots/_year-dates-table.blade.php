@if($slotGroup->isEmpty())
    <div class="px-6 py-6 text-center text-gray-400 text-sm">Keine Slots vorhanden.</div>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slot</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uhrzeit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fächer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">
                        Datum {{ $year }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wochentag</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($slotGroup as $slot)
                    @php
                        $yearDate = $slot->yearDates->first(); // eager loaded für dieses Jahr
                        $existingDate = $yearDate?->exam_date?->format('Y-m-d');
                        $weekday = $yearDate?->exam_date
                            ? \Carbon\Carbon::parse($yearDate->exam_date)->isoFormat('dddd, D. MMMM')
                            : null;
                        $hasMissing = !$existingDate;
                    @endphp
                    <tr class="{{ $hasMissing ? 'bg-amber-50' : 'hover:bg-gray-50' }}">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $slot->name }}</td>
                        <td class="px-4 py-3 font-mono text-gray-600 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                            <span class="text-gray-400 text-xs ml-1">({{ $slot->getDurationMinutes() }} Min.)</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($slot->subjects as $subject)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium text-white"
                                          style="background-color: {{ $subject->color }}">
                                        {{ $subject->code }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <input type="date"
                                   name="dates[{{ $slot->id }}]"
                                   value="{{ old("dates.{$slot->id}", $existingDate) }}"
                                   class="px-2 py-1.5 border rounded-md text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 w-full
                                          {{ $hasMissing ? 'border-amber-400 bg-amber-50' : 'border-gray-300' }}">
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap" id="weekday-{{ $slot->id }}">
                            @if($weekday)
                                <span class="text-green-700 font-medium">{{ $weekday }}</span>
                            @else
                                <span class="text-gray-300 italic">– kein Datum –</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Alpine.js: Wochentag live aktualisieren wenn Datum geändert wird --}}
    <script @cspNonce>
    document.addEventListener('DOMContentLoaded', function () {
        const weekdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        const months = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

        document.querySelectorAll('input[type="date"][name^="dates["]').forEach(function (input) {
            const slotId = input.name.match(/\[(\d+)\]/)[1];
            const cell = document.getElementById('weekday-' + slotId);
            if (!cell) return;

            input.addEventListener('change', function () {
                if (!this.value) {
                    cell.innerHTML = '<span class="text-gray-300 italic">– kein Datum –</span>';
                    return;
                }
                const d = new Date(this.value);
                const label = weekdays[d.getDay()] + ', ' + d.getDate() + '. ' + months[d.getMonth()];
                cell.innerHTML = '<span class="text-green-700 font-medium">' + label + '</span>';
            });
        });
    });
    </script>
@endif
