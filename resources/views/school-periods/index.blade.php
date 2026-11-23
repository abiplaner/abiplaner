<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Stundenraster verwalten') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    

                    @if ($errors->any())
                        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded">
                            <ul class="list-disc list-inside text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Hier können Sie die Zeiträume (Unterrichtsstunden und Pausen) für Ihre Schule festlegen. 
                    </div>

                    <form method="POST" action="{{ route('school-periods.store') }}" x-data="schoolPeriodsData()">
                        @csrf
                        
                        <div class="mb-4 flex justify-end">
                            @unlessarchive
                            <button type="button" @click="addPeriod()" class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition ease-in-out duration-150">
                                + Zeitraum hinzufügen
                            </button>
                            @endarchive
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Typ</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Stunde (Nr)</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name / Bezeichner</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Von (Uhr)</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Bis (Uhr)</th>
                                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Löschen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <template x-for="(period, index) in periods" :key="period.id || 'new_' + index">
                                        <tr x-show="!period._delete" class="bg-white dark:bg-gray-800">
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <select :name="'periods[' + index + '][is_break]'" x-model="period.is_break" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="0">Stunde</option>
                                                    <option value="1">Pause</option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <input type="number" :name="'periods[' + index + '][period_number]'" x-model="period.period_number" min="1" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" :placeholder="period.is_break == 1 ? '-' : '1'">
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <input type="text" :name="'periods[' + index + '][name]'" x-model="period.name" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="z.B. 1. Stunde / 1. Pause">
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <input type="time" :name="'periods[' + index + '][start_time]'" x-model="period.start_time" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <input type="time" :name="'periods[' + index + '][end_time]'" x-model="period.end_time" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-center">
                                                <input type="hidden" :name="'periods[' + index + '][id]'" :value="period.id">
                                                <input type="hidden" :name="'periods[' + index + '][_delete]'" :value="period._delete ? 1 : 0">
                                                @unlessarchive
                                                <button type="button" @click="period._delete = true" class="text-red-500 hover:text-red-700 font-bold text-lg" title="Entfernen">
                                                    &times;
                                                </button>
                                                @endarchive
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        @unlessarchive
                        <div class="mt-6 flex items-center justify-end">
                            <button type="submit" class="px-6 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                                Speichern
                            </button>
                        </div>
                        @endarchive
                    </form>

                </div>
            </div>
        </div>
    </div>
    
    <script @cspNonce>
        function schoolPeriodsData() {
            @php
                $mappedPeriods = $periods->map(function($p) {
                    return [
                        'id' => $p->id,
                        'period_number' => $p->period_number,
                        'name' => $p->name,
                        'is_break' => $p->is_break ? '1' : '0',
                        'start_time' => $p->start_time ? $p->start_time->format('H:i') : '',
                        'end_time' => $p->end_time ? $p->end_time->format('H:i') : '',
                        '_delete' => false
                    ];
                })->values()->toArray();
            @endphp
            return {
                periods: @json($mappedPeriods),
                addPeriod() {
                    this.periods.push({
                        id: null,
                        period_number: '',
                        name: '',
                        is_break: '0',
                        start_time: '08:00',
                        end_time: '08:45',
                        _delete: false
                    });
                }
            }
        }
    </script>
</x-app-layout>
