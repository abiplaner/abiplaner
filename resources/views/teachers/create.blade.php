<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Neue Lehrkraft
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('teachers.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-input-label for="shortcode" value="Kürzel *" />
                                <x-text-input id="shortcode" name="shortcode" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('shortcode') }}"
                                              placeholder="z.B. BCH"
                                              required />
                                <x-input-error :messages="$errors->get('shortcode')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="title" value="Amtsbezeichnung" />
                                <x-text-input id="title" name="title" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('title') }}"
                                              placeholder="z.B. Oberstudienrat" />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="gender" value="Geschlecht" />
                                <select id="gender" name="gender"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Keine Angabe</option>
                                    <option value="m" {{ old('gender') == 'm' ? 'selected' : '' }}>männlich</option>
                                    <option value="w" {{ old('gender') == 'w' ? 'selected' : '' }}>weiblich</option>
                                    <option value="d" {{ old('gender') == 'd' ? 'selected' : '' }}>divers</option>
                                </select>
                                <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <x-input-label for="firstname" value="Vorname *" />
                                <x-text-input id="firstname" name="firstname" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('firstname') }}"
                                              required />
                                <x-input-error :messages="$errors->get('firstname')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="lastname" value="Nachname *" />
                                <x-text-input id="lastname" name="lastname" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('lastname') }}"
                                              required />
                                <x-input-error :messages="$errors->get('lastname')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-input-label for="external_id" value="Externe ID" />
                                <x-text-input id="external_id" name="external_id" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('external_id') }}" />
                                <x-input-error :messages="$errors->get('external_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="employment_type" value="Beschäftigungsart" />
                                <select id="employment_type" name="employment_type"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Bitte wählen</option>
                                    <option value="vz" {{ old('employment_type') == 'vz' ? 'selected' : '' }}>Vollzeit (vz)</option>
                                    <option value="tz" {{ old('employment_type') == 'tz' ? 'selected' : '' }}>Teilzeit (tz)</option>
                                    <option value="vd" {{ old('employment_type') == 'vd' ? 'selected' : '' }}>Vorbereitungsdienst (vd)</option>
                                    <option value="wh" {{ old('employment_type') == 'wh' ? 'selected' : '' }}>Weniger als die Hälfte (wh)</option>
                                </select>
                                <x-input-error :messages="$errors->get('employment_type')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="hours" value="UPZ (Stunden)" />
                                <x-text-input id="hours" name="hours" type="number" step="0.1"
                                              class="mt-1 block w-full"
                                              value="{{ old('hours') }}" />
                                <x-input-error :messages="$errors->get('hours')" class="mt-2" />
                            </div>
                        </div>

                        {{-- Fakultas (Fächer) --}}
                        <div class="mb-6" x-data="{ open: false }">
                            <x-input-label value="Fakultas (Fächer)" />
                            <p class="text-sm text-gray-500 mb-2">Fächer auswählen, die die Lehrkraft unterrichten darf.</p>

                            <button type="button"
                                    @click="open = !open"
                                    class="mb-2 text-sm text-indigo-600 hover:underline focus:outline-none">
                                <span x-text="open ? 'Fächerliste schließen' : 'Fächerliste öffnen'">Fächerliste öffnen</span>
                            </button>

                            <div x-show="open" x-cloak
                                 class="border border-gray-200 rounded-md p-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 max-h-72 overflow-y-auto">
                                @foreach($subjects as $subject)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox"
                                               name="subject_ids[]"
                                               value="{{ $subject->id }}"
                                               {{ in_array($subject->id, old('subject_ids', [])) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ $subject->code }} – {{ $subject->name }}</span>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Gewählte Fächer als Badges --}}
                            <div id="selected-subjects-preview" class="mt-2 flex flex-wrap gap-1">
                                @foreach($subjects as $subject)
                                    @if(in_array($subject->id, old('subject_ids', [])))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ $subject->code }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('subject_ids')" class="mt-2" />
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('teachers.index') }}"
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <x-primary-button>
                                Lehrkraft anlegen
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
