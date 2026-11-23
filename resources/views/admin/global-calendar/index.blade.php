<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Globaler Kalender') }}
            </h2>
            <a href="{{ route('admin.global-calendar.create') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Neues Event erstellen
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">


            {{-- Such- und Filterleiste --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-4 p-4">
                <form method="GET" action="{{ route('admin.global-calendar.index') }}" class="flex flex-wrap gap-3 items-end">

                    {{-- Suche --}}
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Suche</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                </svg>
                            </div>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Titel suchen..."
                                   class="pl-9 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        </div>
                    </div>

                    {{-- Filter: Typ --}}
                    <div class="min-w-40">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Typ</label>
                        <select name="type"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Alle Typen</option>
                            <option value="exam"     {{ request('type') === 'exam'     ? 'selected' : '' }}>Prüfung</option>
                            <option value="holiday"  {{ request('type') === 'holiday'  ? 'selected' : '' }}>Feiertag</option>
                            <option value="event"    {{ request('type') === 'event'    ? 'selected' : '' }}>Event</option>
                            <option value="deadline" {{ request('type') === 'deadline' ? 'selected' : '' }}>Deadline</option>
                            <option value="meeting"  {{ request('type') === 'meeting'  ? 'selected' : '' }}>Meeting</option>
                        </select>
                    </div>

                    {{-- Filter: Jahrgangsstufe --}}
                    <div class="min-w-40">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Jahrgangsstufe</label>
                        <select name="grade"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Alle Stufen</option>
                            @for($i = 5; $i <= 13; $i++)
                                <option value="{{ $i }}" {{ request('grade') == $i ? 'selected' : '' }}>{{ $i }}. Klasse</option>
                            @endfor
                        </select>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex gap-2">
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                            </svg>
                            Filtern
                        </button>
                        @if(request('search') || request('type') || request('grade'))
                            <a href="{{ route('admin.global-calendar.index') }}"
                               class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 text-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Zurücksetzen
                            </a>
                        @endif
                    </div>

                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Ergebnis-Info --}}
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Globale Kalender-Events
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                ({{ $events->total() }} Einträge systemweit sichtbar)
                            </span>
                        </h3>
                    </div>

                    @if($events->isEmpty())
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            @if(request('search') || request('type') || request('grade'))
                                Keine Events gefunden. <a href="{{ route('admin.global-calendar.index') }}" class="text-blue-600 hover:underline">Filter zurücksetzen</a>
                            @else
                                Noch keine globalen Events vorhanden.
                            @endif
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Datum / Zeit</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Titel</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Typ</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Jahrgangsstufen</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($events as $event)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                            {{-- Datum / Zeit --}}
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <div>{{ $event->event_date->format('d.m.Y') }}
                                                    @if($event->end_date && $event->end_date->format('Y-m-d') !== $event->event_date->format('Y-m-d'))
                                                        – {{ $event->end_date->format('d.m.Y') }}
                                                    @endif
                                                </div>
                                                @if($event->start_time || $event->end_time)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                                                        </svg>
                                                        {{ $event->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $event->start_time)->format('H:i') : '' }}
                                                        @if($event->start_time && $event->end_time) – @endif
                                                        {{ $event->end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $event->end_time)->format('H:i') : '' }} Uhr
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Titel --}}
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <div class="flex items-center gap-2">
                                                    @if($event->color)
                                                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $event->color }}"></span>
                                                    @endif
                                                    {{ $event->title }}
                                                </div>
                                            </td>

                                            {{-- Typ --}}
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                @php
                                                    $typeLabels = ['exam' => 'Prüfung', 'holiday' => 'Feiertag', 'event' => 'Event', 'deadline' => 'Deadline', 'meeting' => 'Meeting'];
                                                    $typeColors = [
                                                        'exam'     => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                        'holiday'  => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                        'deadline' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                        'meeting'  => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                        'event'    => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                    ];
                                                @endphp
                                                <span class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full {{ $typeColors[$event->event_type] ?? 'bg-gray-100 text-gray-800' }}">
                                                    {{ $typeLabels[$event->event_type] ?? ucfirst($event->event_type) }}
                                                </span>
                                            </td>

                                            {{-- Jahrgangsstufen --}}
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                @if($event->grade_levels && count($event->grade_levels) > 0)
                                                    {{ implode(', ', $event->grade_levels) }}
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 italic">Alle</span>
                                                @endif
                                            </td>

                                            {{-- Aktionen (Icons) --}}
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="{{ route('admin.global-calendar.edit', $event) }}"
                                                       title="Bearbeiten"
                                                       class="p-1.5 rounded text-blue-600 hover:bg-blue-100 dark:text-blue-400 dark:hover:bg-blue-900 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </a>
                                                    <form action="{{ route('admin.global-calendar.destroy', $event) }}"
                                                          method="POST"
                                                          class="inline"
                                                          data-confirm="Event „{{ $event->title }}“ wirklich löschen?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                title="Löschen"
                                                                class="p-1.5 rounded text-red-600 hover:bg-red-100 dark:text-red-400 dark:hover:bg-red-900 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $events->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
