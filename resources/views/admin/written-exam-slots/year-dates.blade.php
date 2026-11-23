<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.written-exam-slots.index') }}" class="text-gray-400 hover:text-gray-600">
                    ← Slots
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Abitur {{ $year }} – Prüfungstermine
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Ein Datum pro Prüfungstag. Uhrzeiten kommen aus den Slot-Templates.
                    </p>
                </div>
            </div>
            {{-- Jahres-Umschalter --}}
            <div class="flex items-center gap-2">
                @foreach($availableYears as $y)
                    <a href="{{ route('admin.written-exam-slots.year-dates', $y) }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium
                              {{ $y == $year ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $y }}
                    </a>
                @endforeach
                @php $nextYear = $year + 1; @endphp
                @if(!$availableYears->contains($nextYear))
                    <a href="{{ route('admin.written-exam-slots.year-dates', $nextYear) }}"
                       class="px-3 py-1.5 rounded-md text-sm bg-gray-50 text-gray-400 hover:bg-gray-100 border border-dashed border-gray-300">
                        {{ $nextYear }} →
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800">{{ session('error') }}</div>
            @endif

            @php
                $totalMissing = 0;
                foreach ($days as $daySlots) {
                    if ($daySlots->first()->yearDates->isEmpty()) $totalMissing++;
                }
            @endphp
            @if($totalMissing > 0)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-amber-800 text-sm">
                        <strong>{{ $totalMissing }} Prüfungstag(e)</strong> haben noch kein Datum für Abitur {{ $year }}.
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.written-exam-slots.year-dates.store', $year) }}">
                @csrf

                @php $dayNumber = 0; @endphp
                @foreach($days as $dayKey => $daySlots)
                    @php
                        $dayNumber++;
                        $firstSlot   = $daySlots->first();
                        $existingDate = $firstSlot->yearDates->first()?->exam_date?->format('Y-m-d');
                        $hasMissing  = !$existingDate;
                        $isCollective = $daySlots->count() > 2 || ($daySlots->count() > 1 && $daySlots->pluck('requirement_level')->unique()->count() === 1);
                    @endphp

                    <div class="bg-white shadow-sm rounded-lg overflow-hidden
                                {{ $hasMissing ? 'ring-1 ring-amber-300' : '' }}"
                         x-data="{ date: '{{ old("day_dates.{$dayKey}", $existingDate ?? '') }}' }">

                        {{-- Kopfzeile: Prüfungstag + Datumseingabe --}}
                        <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-sm font-bold flex items-center justify-center">
                                    {{ $dayNumber }}
                                </span>
                                <div class="min-w-0">
                                    @if($isCollective)
                                        <span class="font-semibold text-gray-800">
                                            Sammeltag {{ $daySlots->first()->requirement_level === 'grundlegend' ? 'gA' : 'eA' }}
                                        </span>
                                    @else
                                        <span class="font-semibold text-gray-800">
                                            {{ $daySlots->first()->subjects->first()?->name ?? $daySlots->first()->name }}
                                        </span>
                                        <div class="flex gap-1 mt-0.5">
                                            @foreach($daySlots as $slot)
                                                <span class="text-xs px-1.5 py-0.5 rounded font-medium
                                                    {{ $slot->requirement_level === 'grundlegend' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                                    {{ $slot->requirement_level === 'grundlegend' ? 'gA' : 'eA' }}
                                                    {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Datumseingabe (einmal pro Tag) --}}
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <input type="date"
                                       x-model="date"
                                       @change="syncSlots('{{ $dayKey }}')"
                                       class="px-3 py-1.5 border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                              {{ $hasMissing ? 'border-amber-400 bg-amber-50' : 'border-gray-300' }}"
                                       placeholder="Datum wählen">
                                <span class="text-sm text-gray-500 w-44 text-right" id="weekday-day-{{ $dayKey }}">
                                    @if($existingDate)
                                        <span class="text-green-700 font-medium">
                                            {{ \Carbon\Carbon::parse($existingDate)->isoFormat('dddd, D. MMMM') }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 italic">– kein Datum –</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        {{-- Slot-Details (aufklappbar bei Sammeltagen, sonst immer sichtbar) --}}
                        @if($isCollective)
                            <div class="divide-y divide-gray-50">
                                @foreach($daySlots as $slot)
                                    <div class="px-5 py-2.5 flex items-center gap-3 text-sm text-gray-600">
                                        <span class="font-mono text-xs text-gray-400 w-20 flex-shrink-0">
                                            {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                                        </span>
                                        <span class="flex-1">{{ Str::after($slot->name, '– ') }}</span>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($slot->subjects as $subject)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium text-white"
                                                      style="background-color: {{ $subject->color }}">
                                                    {{ $subject->code }}
                                                </span>
                                            @endforeach
                                        </div>
                                        {{-- Hidden input für diesen Slot --}}
                                        <input type="hidden"
                                               name="dates[{{ $slot->id }}]"
                                               id="slot-date-{{ $slot->id }}"
                                               value="{{ old("dates.{$slot->id}", $existingDate ?? '') }}">
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Einzelfach: nur hidden inputs, Details sind im Header --}}
                            @foreach($daySlots as $slot)
                                <input type="hidden"
                                       name="dates[{{ $slot->id }}]"
                                       id="slot-date-{{ $slot->id }}"
                                       value="{{ old("dates.{$slot->id}", $existingDate ?? '') }}">
                            @endforeach
                        @endif
                    </div>
                @endforeach

                <div class="flex justify-end pt-2">
                    <button type="submit"
                            class="px-8 py-2.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium">
                        Alle Termine für Abitur {{ $year }} speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script @cspNonce>
    const weekdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    const months   = ['Januar','Februar','März','April','Mai','Juni',
                       'Juli','August','September','Oktober','November','Dezember'];

    // Mapping dayKey → slotIds (aus PHP generiert)
    const daySlotIds = @json($days->map(fn($slots) => $slots->pluck('id')));

    function syncSlots(dayKey) {
        const dateInput = event.target;
        const val = dateInput.value;

        // Wochentag aktualisieren
        const cell = document.getElementById('weekday-day-' + dayKey);
        if (cell) {
            if (!val) {
                cell.innerHTML = '<span class="text-gray-300 italic">– kein Datum –</span>';
            } else {
                const d = new Date(val);
                const label = weekdays[d.getDay()] + ', ' + d.getDate() + '. ' + months[d.getMonth()];
                cell.innerHTML = '<span class="text-green-700 font-medium">' + label + '</span>';
            }
        }

        // Alle hidden inputs dieses Tages synchronisieren
        const slotIds = daySlotIds[dayKey] || [];
        slotIds.forEach(function(id) {
            const hidden = document.getElementById('slot-date-' + id);
            if (hidden) hidden.value = val;
        });
    }
    </script>
</x-app-layout>
