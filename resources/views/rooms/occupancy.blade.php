<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Raumbelegungsplan') }} - {{ $examPeriod->name }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('rooms.export-occupancy-pdf', $examPeriod) }}" target="_blank"
                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    📄 PDF-Export
                </a>
                <a href="{{ route('rooms.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Zurück zur Raumverwaltung') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if($rooms->isEmpty())
                        <div class="text-center py-8 text-gray-500">
                            <p class="text-lg">Keine Prüfungsräume gefunden.</p>
                            <p class="mt-2">Bitte legen Sie zunächst Räume an.</p>
                        </div>
                    @else
                        <!-- Raumübersicht -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-3">Prüfungsräume ({{ $rooms->count() }})</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($rooms as $room)
                                    <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 rounded-full text-sm font-medium">
                                        {{ $room->room_number }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <!-- Belegungsplan -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider sticky left-0 bg-gray-50 dark:bg-gray-700">
                                            Datum / Zeit
                                        </th>
                                        @foreach($rooms as $room)
                                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                {{ $room->room_number }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @php
                                        $currentDate = null;
                                    @endphp
                                    @foreach($occupancyData as $slotKey => $slotData)
                                        @php
                                            $showDate = $currentDate !== $slotData['date']->format('Y-m-d');
                                            $currentDate = $slotData['date']->format('Y-m-d');
                                        @endphp
                                        @if($showDate)
                                            <tr class="bg-gray-100 dark:bg-gray-700">
                                                <td colspan="{{ $rooms->count() + 1 }}" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                                    {{ $slotData['date']->format('d.m.Y') }} ({{ $slotData['date']->translatedFormat('l') }})
                                                </td>
                                            </tr>
                                        @endif
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100 sticky left-0 bg-white dark:bg-gray-800">
                                                Slot {{ $slotData['slot_number'] }}<br>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ substr($slotData['time'], 0, 5) }}</span>
                                            </td>
                                            @foreach($rooms as $room)
                                                @php
                                                    $roomData = $slotData['rooms'][$room->id] ?? null;
                                                    $count = $roomData['count'] ?? 0;
                                                    $assignments = $roomData['assignments'] ?? collect();
                                                @endphp
                                                <td class="px-2 py-2 text-center text-xs">
                                                    @if($count > 0)
                                                        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded p-2">
                                                            @foreach($assignments as $assignment)
                                                                <div class="mb-2 text-green-800 dark:text-green-200 last:mb-0">
                                                                    <div class="font-semibold">{{ $assignment->studentExam->prueflingsNachname() }}</div>
                                                                    <div class="text-xs text-green-600 dark:text-green-400">
                                                                        {{ $assignment->studentExam->getSubjectCode() ?? '' }}
                                                                    </div>
                                                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                                        <div>Prüfer: {{ $assignment->studentExam->examiner->shortcode ?? '?' }}</div>
                                                                        <div>Beisitzer: {{ $assignment->studentExam->assessor->shortcode ?? '—' }}</div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
