<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Lehrkraft bearbeiten: {{ $teacher->shortcode }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('teachers.update', $teacher) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="page" value="{{ request('page') }}">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-input-label for="shortcode" value="Kürzel *" />
                                <x-text-input id="shortcode" name="shortcode" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('shortcode', $teacher->shortcode) }}"
                                              required />
                                <x-input-error :messages="$errors->get('shortcode')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="title" value="Amtsbezeichnung" />
                                <x-text-input id="title" name="title" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('title', $teacher->title) }}"
                                              placeholder="z.B. Oberstudienrat" />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="gender" value="Geschlecht" />
                                <select id="gender" name="gender"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Keine Angabe</option>
                                    <option value="m" {{ old('gender', $teacher->gender) == 'm' ? 'selected' : '' }}>männlich</option>
                                    <option value="w" {{ old('gender', $teacher->gender) == 'w' ? 'selected' : '' }}>weiblich</option>
                                    <option value="d" {{ old('gender', $teacher->gender) == 'd' ? 'selected' : '' }}>divers</option>
                                </select>
                                <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <x-input-label for="firstname" value="Vorname *" />
                                <x-text-input id="firstname" name="firstname" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('firstname', $teacher->firstname) }}"
                                              required />
                                <x-input-error :messages="$errors->get('firstname')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="lastname" value="Nachname *" />
                                <x-text-input id="lastname" name="lastname" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('lastname', $teacher->lastname) }}"
                                              required />
                                <x-input-error :messages="$errors->get('lastname')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-input-label for="external_id" value="Externe ID" />
                                <x-text-input id="external_id" name="external_id" type="text"
                                              class="mt-1 block w-full"
                                              value="{{ old('external_id', $teacher->external_id) }}" />
                                <x-input-error :messages="$errors->get('external_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="employment_type" value="Beschäftigungsart" />
                                <select id="employment_type" name="employment_type"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Bitte wählen</option>
                                    <option value="vz" {{ old('employment_type', $teacher->employment_type) == 'vz' ? 'selected' : '' }}>Vollzeit (vz)</option>
                                    <option value="tz" {{ old('employment_type', $teacher->employment_type) == 'tz' ? 'selected' : '' }}>Teilzeit (tz)</option>
                                    <option value="vd" {{ old('employment_type', $teacher->employment_type) == 'vd' ? 'selected' : '' }}>Vorbereitungsdienst (vd)</option>
                                    <option value="wh" {{ old('employment_type', $teacher->employment_type) == 'wh' ? 'selected' : '' }}>Weniger als die Hälfte (wh)</option>
                                </select>
                                <x-input-error :messages="$errors->get('employment_type')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="hours" value="UPZ (Stunden)" />
                                <x-text-input id="hours" name="hours" type="number" step="0.1"
                                              class="mt-1 block w-full"
                                              value="{{ old('hours', $teacher->hours) }}" />
                                <x-input-error :messages="$errors->get('hours')" class="mt-2" />
                            </div>
                        </div>

                        {{-- Fakultas (Fächer) --}}
                        @php
                            $selectedSubjectIds = old('subject_ids', $teacher->subjects->pluck('id')->toArray());
                        @endphp
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
                                               {{ in_array($subject->id, $selectedSubjectIds) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ $subject->code }} – {{ $subject->name }}</span>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Aktuell gewählte Fächer als Badges --}}
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($subjects as $subject)
                                    @if(in_array($subject->id, $selectedSubjectIds))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ $subject->code }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('subject_ids')" class="mt-2" />
                        </div>

                        {{-- Kontakt Zusatzprüfungen --}}
                        <div class="mb-6 border border-amber-200 rounded-lg bg-amber-50 p-4">
                            <h4 class="text-sm font-semibold text-amber-800 mb-3">
                                📞 Kontakt für Zusatzprüfungen
                                <span class="text-xs font-normal text-amber-600 ml-1">(für Schüler-Fahrplan)</span>
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="contact_phone" value="Telefonnummer" />
                                    <x-text-input id="contact_phone" name="contact_phone" type="text"
                                                  class="mt-1 block w-full"
                                                  value="{{ old('contact_phone', $teacher->contact_phone) }}"
                                                  placeholder="z.B. 0176 12345678" />
                                    <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="contact_email" value="E-Mail (privat/dienstlich)" />
                                    <x-text-input id="contact_email" name="contact_email" type="email"
                                                  class="mt-1 block w-full"
                                                  value="{{ old('contact_email', $teacher->contact_email) }}"
                                                  placeholder="z.B. max.muster@gmail.com" />
                                    <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                                </div>
                                <div class="flex items-center gap-3 mt-6">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="hidden" name="contact_bycs" value="0">
                                        <input type="checkbox" name="contact_bycs" value="1"
                                               id="contact_bycs"
                                               {{ old('contact_bycs', $teacher->contact_bycs) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <span class="text-sm text-gray-700">Erreichbar per ByCS-Messenger</span>
                                    </label>
                                </div>
                                <div class="flex items-center gap-3 mt-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1"
                                               id="is_active"
                                               {{ old('is_active', $teacher->is_active) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                        <span class="text-sm text-gray-700">Aktiv (erscheint in Prüfer-, Beisitzer- und Aufsichts-Auswahlen)</span>
                                    </label>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    Inaktive Lehrkräfte bleiben mit allen bisherigen Zuweisungen erhalten,
                                    werden aber bei neuen Planungen nicht mehr angeboten (z.&nbsp;B. nach Ausscheiden).
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('teachers.index') }}"
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <x-primary-button>
                                Änderungen speichern
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
