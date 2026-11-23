<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Termin bearbeiten') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <form method="POST" action="{{ route('calendar.update', $calendarEvent) }}">
                        @csrf
                        @method('PUT')
                        
                        <!-- Titel -->
                        <div class="mb-4">
                            <label for="title" class="block text-sm font-medium mb-1">Titel *</label>
                            <input type="text" 
                                   name="title" 
                                   id="title" 
                                   value="{{ old('title', $calendarEvent->title) }}"
                                   required
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @error('title')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Beschreibung -->
                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium mb-1">Beschreibung</label>
                            <textarea name="description" 
                                      id="description" 
                                      rows="3"
                                      class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ old('description', $calendarEvent->description) }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Startdatum -->
                            <div>
                                <label for="event_date" class="block text-sm font-medium mb-1">Datum *</label>
                                <input type="date" 
                                       name="event_date" 
                                       id="event_date" 
                                       value="{{ old('event_date', $calendarEvent->event_date->format('Y-m-d')) }}"
                                       required
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @error('event_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <!-- Enddatum -->
                            <div>
                                <label for="end_date" class="block text-sm font-medium mb-1">Enddatum (optional)</label>
                                <input type="date" 
                                       name="end_date" 
                                       id="end_date" 
                                       value="{{ old('end_date', $calendarEvent->end_date?->format('Y-m-d')) }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Für mehrtägige Events (z.B. Ferien)</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Startzeit -->
                            <div>
                                <label for="start_time" class="block text-sm font-medium mb-1">Startzeit (optional)</label>
                                <input type="time" 
                                       name="start_time" 
                                       id="start_time" 
                                       value="{{ old('start_time', $calendarEvent->start_time?->format('H:i')) }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @error('start_time')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <!-- Endzeit -->
                            <div>
                                <label for="end_time" class="block text-sm font-medium mb-1">Endzeit (optional)</label>
                                <input type="time" 
                                       name="end_time" 
                                       id="end_time" 
                                       value="{{ old('end_time', $calendarEvent->end_time?->format('H:i')) }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @error('end_time')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Event-Typ -->
                            <div>
                                <label for="event_type" class="block text-sm font-medium mb-1">Typ *</label>
                                <select name="event_type" id="event_type" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Bitte wählen...</option>
                                    <option value="deadline" {{ old('event_type', $calendarEvent->event_type) == 'deadline' ? 'selected' : '' }}>Frist</option>
                                    <option value="exam_written" {{ old('event_type', $calendarEvent->event_type) == 'exam_written' ? 'selected' : '' }}>Schriftliche Prüfung</option>
                                    <option value="exam_oral" {{ old('event_type', $calendarEvent->event_type) == 'exam_oral' ? 'selected' : '' }}>Mündliche Prüfung/Kolloquium</option>
                                    <option value="exam_practical" {{ old('event_type', $calendarEvent->event_type) == 'exam_practical' ? 'selected' : '' }}>Praktische Prüfung</option>
                                    <option value="holiday" {{ old('event_type', $calendarEvent->event_type) == 'holiday' ? 'selected' : '' }}>Ferien</option>
                                    <option value="public_holiday" {{ old('event_type', $calendarEvent->event_type) == 'public_holiday' ? 'selected' : '' }}>Feiertag</option>
                                    <option value="info" {{ old('event_type', $calendarEvent->event_type) == 'info' ? 'selected' : '' }}>Information</option>
                                    <option value="grading" {{ old('event_type', $calendarEvent->event_type) == 'grading' ? 'selected' : '' }}>Notenabgabe</option>
                                    <option value="administration" {{ old('event_type', $calendarEvent->event_type) == 'administration' ? 'selected' : '' }}>Verwaltung</option>
                                    <option value="other" {{ old('event_type', $calendarEvent->event_type) == 'other' ? 'selected' : '' }}>Sonstiges</option>
                                </select>
                                @error('event_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Farbe -->
                            <div>
                                <label for="color" class="block text-sm font-medium mb-1">Farbe</label>
                                <input type="color" 
                                       name="color" 
                                       id="color" 
                                       value="{{ old('color', $calendarEvent->color) }}"
                                       class="w-full h-10 rounded-md border-gray-300 dark:border-gray-700">
                                @error('color')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Jahrgangsstufen -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Jahrgangsstufen</label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="grade_levels[]" value="11" 
                                           {{ in_array(11, old('grade_levels', $calendarEvent->grade_levels ?? [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    <span class="ml-2">11. Jahrgangsstufe</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="grade_levels[]" value="12" 
                                           {{ in_array(12, old('grade_levels', $calendarEvent->grade_levels ?? [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    <span class="ml-2">Q12</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="grade_levels[]" value="13" 
                                           {{ in_array(13, old('grade_levels', $calendarEvent->grade_levels ?? [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    <span class="ml-2">Q13</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Keine Auswahl = gilt für alle Jahrgangsstufen</p>
                            @error('grade_levels')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Ort -->
                        <div class="mb-4">
                            <label for="location" class="block text-sm font-medium mb-1">Ort (optional)</label>
                            <input type="text" 
                                   name="location" 
                                   id="location" 
                                   value="{{ old('location', $calendarEvent->location) }}"
                                   placeholder="z.B. Aula, Turnhalle, Raum 101"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @error('location')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Priorität -->
                            <div>
                                <label for="priority" class="block text-sm font-medium mb-1">Priorität</label>
                                <select name="priority" id="priority"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="0" {{ old('priority', $calendarEvent->priority) == 0 ? 'selected' : '' }}>Normal</option>
                                    <option value="5" {{ old('priority', $calendarEvent->priority) == 5 ? 'selected' : '' }}>Mittel</option>
                                    <option value="10" {{ old('priority', $calendarEvent->priority) == 10 ? 'selected' : '' }}>Hoch</option>
                                </select>
                                @error('priority')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <!-- Optionen -->
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_mandatory" value="1" 
                                           {{ old('is_mandatory', $calendarEvent->is_mandatory) ? 'checked' : '' }}
                                           class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    <span class="ml-2">Pflichttermin</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_recurring" value="1" 
                                           {{ old('is_recurring', $calendarEvent->is_recurring) ? 'checked' : '' }}
                                           class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    <span class="ml-2">Jährlich wiederkehrender Termin</span>
                                </label>
                                {{-- Hier stand ein Hinweis "Globaler Termin", der an
                                     $calendarEvent->school_id === null haengte. calendar_events
                                     hat keine Spalte school_id — der Vergleich traf deshalb
                                     immer zu, und der Hinweis stand ueber jedem Schultermin.
                                     Erreichbar ist diese Maske ohnehin nur ueber
                                     CalendarEventController::edit(), das einen CalendarEvent
                                     bindet; ein globaler Termin ist ein GlobalCalendarEvent und
                                     kommt hier nie an. --}}
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                                Änderungen speichern
                            </button>
                            <a href="{{ route('calendar.index') }}" 
                               class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg shadow-md transition-colors duration-150">
                                Abbrechen
                            </a>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
