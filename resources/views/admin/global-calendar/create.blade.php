<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Neues globales Kalender-Event erstellen') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.global-calendar.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Titel *</label>
                            <input type="text" name="title" id="title" value="{{ old('title') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('title')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Beschreibung</label>
                            <textarea name="description" id="description" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="event_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Startdatum *</label>
                                <input type="date" name="event_date" id="event_date" value="{{ old('event_date') }}" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Enddatum</label>
                                <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Startzeit</label>
                                <input type="time" name="start_time" id="start_time" value="{{ old('start_time') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Endzeit</label>
                                <input type="time" name="end_time" id="end_time" value="{{ old('end_time') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="event_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Event-Typ *</label>
                            <select name="event_type" id="event_type" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="exam" {{ old('event_type') === 'exam' ? 'selected' : '' }}>Prüfung</option>
                                <option value="holiday" {{ old('event_type') === 'holiday' ? 'selected' : '' }}>Feiertag</option>
                                <option value="event" {{ old('event_type') === 'event' ? 'selected' : '' }}>Event</option>
                                <option value="deadline" {{ old('event_type') === 'deadline' ? 'selected' : '' }}>Deadline</option>
                                <option value="meeting" {{ old('event_type') === 'meeting' ? 'selected' : '' }}>Meeting</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Jahrgangsstufen</label>
                            <div class="grid grid-cols-3 gap-2">
                                @for($i = 5; $i <= 13; $i++)
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="grade_levels[]" value="{{ $i }}" 
                                               {{ in_array($i, old('grade_levels', [])) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $i }}. Klasse</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Leer lassen = Für alle Jahrgangsstufen</p>
                        </div>

                        <div class="mb-4">
                            <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Farbe</label>
                            <input type="color" name="color" id="color" value="{{ old('color', '#3B82F6') }}"
                                   class="mt-1 block h-10 rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div class="mb-4">
                            <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priorität (1-10)</label>
                            <input type="number" name="priority" id="priority" min="1" max="10" value="{{ old('priority', 5) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('admin.global-calendar.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Event erstellen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
