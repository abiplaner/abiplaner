<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Raum hinzufügen') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('rooms.store') }}">
                        @csrf

                        <!-- Raumnummer -->
                        <div class="mb-4">
                            <x-input-label for="room_number" :value="__('Raumnummer')" />
                            <x-text-input id="room_number" class="block mt-1 w-full" type="text" name="room_number" 
                                :value="old('room_number')" required autofocus />
                            <x-input-error :messages="$errors->get('room_number')" class="mt-2" />
                        </div>

                        <!-- Raumtyp -->
                        <div class="mb-4">
                            <x-input-label for="room_type" :value="__('Raumtyp')" />
                            <select id="room_type" name="room_type" 
                                class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="exam" {{ old('room_type') == 'exam' ? 'selected' : '' }}>Prüfungsraum</option>
                                <option value="preparation" {{ old('room_type') == 'preparation' ? 'selected' : '' }}>Vorbereitungsraum</option>
                                <option value="waiting" {{ old('room_type') == 'waiting' ? 'selected' : '' }}>Warteraum</option>
                                <option value="lockdown" {{ old('room_type') == 'lockdown' ? 'selected' : '' }}>Absperrraum</option>
                                <option value="corridor" {{ old('room_type') == 'corridor' ? 'selected' : '' }}>Gangraum</option>
                            </select>
                            <x-input-error :messages="$errors->get('room_type')" class="mt-2" />
                        </div>

                        <!-- Sitzplan-Vorlage: Standard -->
                        <div class="mb-4">
                            <x-input-label for="seating_template_id" :value="__('Sitzplan-Vorlage (Standard, optional)')" />
                            <select id="seating_template_id" name="seating_template_id" 
                                class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- Keine Vorlage --</option>
                                @foreach($seatingTemplates as $template)
                                    <option value="{{ $template->id }}" 
                                        {{ old('seating_template_id') == $template->id ? 'selected' : '' }}>
                                        {{ $template->name }} ({{ $template->rows }}×{{ $template->columns }}, {{ $template->seats->where('seat_type', 'available')->count() }} Plätze)
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Diese Vorlage wird für schriftliche Prüfungen in diesem Raum verwendet.
                            </p>
                            <x-input-error :messages="$errors->get('seating_template_id')" class="mt-2" />
                        </div>

                        <!-- Sitzplan-Vorlage: Hörverstehen -->
                        <div class="mb-4">
                            <x-input-label for="hv_seating_template_id" :value="__('Sitzplan-Vorlage Hörverstehen (optional)')" />
                            <select id="hv_seating_template_id" name="hv_seating_template_id" 
                                class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- Wie Standard --</option>
                                @foreach($seatingTemplates as $template)
                                    <option value="{{ $template->id }}" 
                                        {{ old('hv_seating_template_id') == $template->id ? 'selected' : '' }}>
                                        {{ $template->name }} ({{ $template->rows }}×{{ $template->columns }}, {{ $template->seats->where('seat_type', 'available')->count() }} Plätze)
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Nur nötig, wenn für den Hörverstehen-Teil (moderne Fremdsprachen) ein abweichender Sitzplan im selben Raum verwendet wird.
                            </p>
                            <x-input-error :messages="$errors->get('hv_seating_template_id')" class="mt-2" />
                        </div>

                        <!-- Notizen -->
                        <div class="mb-4">
                            <x-input-label for="notes" :value="__('Notizen (optional)')" />
                            <textarea id="notes" name="notes" rows="3"
                                class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <!-- Aktiv -->
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Raum ist aktiv') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-3">
                            <a href="{{ route('rooms.index') }}" 
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                                Abbrechen
                            </a>
                            <x-primary-button>
                                {{ __('Raum erstellen') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
