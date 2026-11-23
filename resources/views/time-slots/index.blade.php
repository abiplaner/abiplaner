<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Kolloquium - {{ $examPeriod->name }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('slot-templates.index', $examPeriod) }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    ⚡ Zeitraster verwalten
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Tab-Navigation --}}
            <div class="mb-6 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="{{ route('planning.index') }}"
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        📋 Prüfungen
                    </a>
                    <a href="{{ route('planning.rooms.index') }}"
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        🏫 Räume &amp; Tage
                    </a>
                    <a href="{{ route('planning.time-slots.index') }}"
                       class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        🕐 Zeitslots
                    </a>
                </nav>
            </div>

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    {{ session('error') }}
                </div>
            @endif

            @php
                $slotsByDate = $timeSlots->groupBy(fn($slot) => $slot->date->format('Y-m-d'));
                $maxSlotsPerDay = $slotsByDate->max(fn($day) => $day->count()) ?: 0;
            @endphp

            {{-- Woche 1 --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="bg-blue-600 text-white px-6 py-3">
                    <h3 class="text-lg font-semibold">
                        Woche 1: {{ $examPeriod->week_1_start->format('d.m.Y') }} – {{ $examPeriod->week_1_end->format('d.m.Y') }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Slot</th>
                                @foreach($week1Dates as $date)
                                    @php $dateKey = $date->format('Y-m-d'); @endphp
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider min-w-[130px]">
                                        <div>{{ $date->translatedFormat('D') }}</div>
                                        <div class="font-bold text-gray-900 dark:text-gray-100 mb-1">{{ $date->format('d.m.') }}</div>
                                        @if($slotTemplates->count() > 0)
                                            @can('managePlanning', $examPeriod)
                                                <select data-assign-template="{{ $dateKey }}"
                                                        class="w-full text-[10px] border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 rounded py-0.5 font-normal normal-case">
                                                    <option value="">— Raster wählen —</option>
                                                    @foreach($slotTemplates as $tmpl)
                                                        <option value="{{ $tmpl->id }}"
                                                            {{ ($activeTemplateByDate[$dateKey] ?? null) == $tmpl->id ? 'selected' : '' }}>
                                                            {{ $tmpl->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endcan
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @for($slotNum = 1; $slotNum <= $maxSlotsPerDay; $slotNum++)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">#{{ $slotNum }}</td>
                                    @foreach($week1Dates as $date)
                                        @php
                                            $dateKey = $date->format('Y-m-d');
                                            $daySlots = $slotsByDate->get($dateKey, collect());
                                            $slot = $daySlots->where('slot_number', $slotNum)->first();
                                            $prefBg = 'bg-gray-50 dark:bg-gray-700';
                                            $prefBadge = 'bg-gray-100 text-gray-800';
                                            if ($slot) {
                                                if ($slot->preference_weight >= 120) { $prefBg = 'bg-green-50 dark:bg-green-900/20'; $prefBadge = 'bg-green-100 text-green-800'; }
                                                elseif ($slot->preference_weight >= 100) { $prefBg = 'bg-blue-50 dark:bg-blue-900/20'; $prefBadge = 'bg-blue-100 text-blue-800'; }
                                                elseif ($slot->preference_weight >= 80) { $prefBg = 'bg-gray-50 dark:bg-gray-700'; $prefBadge = 'bg-gray-100 text-gray-800'; }
                                                elseif ($slot->preference_weight >= 60) { $prefBg = 'bg-yellow-50 dark:bg-yellow-900/20'; $prefBadge = 'bg-yellow-100 text-yellow-800'; }
                                                else { $prefBg = 'bg-red-50 dark:bg-red-900/20'; $prefBadge = 'bg-red-100 text-red-800'; }
                                            }
                                        @endphp
                                        <td class="px-2 py-2 text-xs {{ $slot && !$slot->is_active ? 'opacity-50' : '' }}">
                                            @if($slot)
                                                <div class="space-y-1 {{ $prefBg }} p-2 rounded border border-gray-200 dark:border-gray-600">
                                                    <div class="text-gray-600 dark:text-gray-300">Vor: {{ substr($slot->prep_start_time,0,5) }}–{{ substr($slot->prep_end_time,0,5) }}</div>
                                                    <div class="text-gray-600 dark:text-gray-300">Prüf: {{ substr($slot->exam_start_time,0,5) }}–{{ substr($slot->exam_end_time,0,5) }}</div>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] {{ $prefBadge }}">
                                                        {{ $slot->preference_label }}
                                                    </span>
                                                </div>
                                            @else
                                                <div class="text-gray-400 text-center py-4">–</div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Woche 2 --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="bg-green-600 text-white px-6 py-3">
                    <h3 class="text-lg font-semibold">
                        Woche 2: {{ $examPeriod->week_2_start->format('d.m.Y') }} – {{ $examPeriod->week_2_end->format('d.m.Y') }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Slot</th>
                                @foreach($week2Dates as $date)
                                    @php $dateKey = $date->format('Y-m-d'); @endphp
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider min-w-[130px]">
                                        <div>{{ $date->translatedFormat('D') }}</div>
                                        <div class="font-bold text-gray-900 dark:text-gray-100 mb-1">{{ $date->format('d.m.') }}</div>
                                        @if($slotTemplates->count() > 0)
                                            @can('managePlanning', $examPeriod)
                                                <select data-assign-template="{{ $dateKey }}"
                                                        class="w-full text-[10px] border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 rounded py-0.5 font-normal normal-case">
                                                    <option value="">— Raster wählen —</option>
                                                    @foreach($slotTemplates as $tmpl)
                                                        <option value="{{ $tmpl->id }}"
                                                            {{ ($activeTemplateByDate[$dateKey] ?? null) == $tmpl->id ? 'selected' : '' }}>
                                                            {{ $tmpl->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endcan
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @for($slotNum = 1; $slotNum <= $maxSlotsPerDay; $slotNum++)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">#{{ $slotNum }}</td>
                                    @foreach($week2Dates as $date)
                                        @php
                                            $dateKey = $date->format('Y-m-d');
                                            $daySlots = $slotsByDate->get($dateKey, collect());
                                            $slot = $daySlots->where('slot_number', $slotNum)->first();
                                            $prefBg = 'bg-gray-50 dark:bg-gray-700';
                                            $prefBadge = 'bg-gray-100 text-gray-800';
                                            if ($slot) {
                                                if ($slot->preference_weight >= 120) { $prefBg = 'bg-green-50 dark:bg-green-900/20'; $prefBadge = 'bg-green-100 text-green-800'; }
                                                elseif ($slot->preference_weight >= 100) { $prefBg = 'bg-blue-50 dark:bg-blue-900/20'; $prefBadge = 'bg-blue-100 text-blue-800'; }
                                                elseif ($slot->preference_weight >= 80) { $prefBg = 'bg-gray-50 dark:bg-gray-700'; $prefBadge = 'bg-gray-100 text-gray-800'; }
                                                elseif ($slot->preference_weight >= 60) { $prefBg = 'bg-yellow-50 dark:bg-yellow-900/20'; $prefBadge = 'bg-yellow-100 text-yellow-800'; }
                                                else { $prefBg = 'bg-red-50 dark:bg-red-900/20'; $prefBadge = 'bg-red-100 text-red-800'; }
                                            }
                                        @endphp
                                        <td class="px-2 py-2 text-xs {{ $slot && !$slot->is_active ? 'opacity-50' : '' }}">
                                            @if($slot)
                                                <div class="space-y-1 {{ $prefBg }} p-2 rounded border border-gray-200 dark:border-gray-600">
                                                    <div class="text-gray-600 dark:text-gray-300">Vor: {{ substr($slot->prep_start_time,0,5) }}–{{ substr($slot->prep_end_time,0,5) }}</div>
                                                    <div class="text-gray-600 dark:text-gray-300">Prüf: {{ substr($slot->exam_start_time,0,5) }}–{{ substr($slot->exam_end_time,0,5) }}</div>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] {{ $prefBadge }}">
                                                        {{ $slot->preference_label }}
                                                    </span>
                                                </div>
                                            @else
                                                <div class="text-gray-400 text-center py-4">–</div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

<script @cspNonce>
const assignTemplateUrl = "{{ route('planning.time-slots.assign-template', $examPeriod) }}";
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// Frueher onchange am Auswahlfeld (Modul 7.2).
document.addEventListener('change', (ereignis) => {
    const feld = ereignis.target.closest('[data-assign-template]');

    if (feld) {
        assignTemplate(feld.dataset.assignTemplate, feld);
    }
});

function assignTemplate(date, selectEl) {
    const templateId = selectEl.value;
    if (!templateId) return;

    if (!confirm('Raster "' + selectEl.options[selectEl.selectedIndex].text + '" für ' + date + ' setzen? Alle bestehenden Slots dieses Tages werden ersetzt.')) {
        selectEl.value = selectEl.dataset.previous || '';
        return;
    }

    selectEl.dataset.previous = templateId;

    fetch(assignTemplateUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ date: date, template_id: templateId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
            selectEl.value = selectEl.dataset.previous || '';
        }
    })
    .catch(() => { alert('Netzwerkfehler.'); selectEl.value = selectEl.dataset.previous || ''; });
}
</script>


</x-app-layout>
