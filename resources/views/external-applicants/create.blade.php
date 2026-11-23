<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Neuer externer Bewerber') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <form method="POST" action="{{ route('external-applicants.store') }}">
                        @csrf

                        <!-- Vorname -->
                        <div class="mb-4">
                            <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Vorname <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="first_name" id="first_name" 
                                   value="{{ old('first_name') }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nachname -->
                        <div class="mb-4">
                            <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Nachname <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="last_name" id="last_name" 
                                   value="{{ old('last_name') }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Geburtsdatum -->
                        <div class="mb-4">
                            <label for="birth_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Geburtsdatum <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="birth_date" id="birth_date" 
                                   value="{{ old('birth_date') }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('birth_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- E-Mail -->
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                E-Mail
                            </label>
                            <input type="email" name="email" id="email" 
                                   value="{{ old('email') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Telefon -->
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Telefon
                            </label>
                            <input type="text" name="phone" id="phone" 
                                   value="{{ old('phone') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Prüfungsjahr / ExamPeriod -->
                        <div class="mb-4">
                            <label for="exam_period_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Prüfungsjahr <span class="text-red-500">*</span>
                            </label>
                            
                            @if($examPeriod)
                                <!-- Wenn eine ExamPeriod für das Jahr existiert -->
                                <input type="hidden" name="exam_period_id" value="{{ $examPeriod->id }}">
                                <div class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md bg-gray-50 dark:bg-gray-800">
                                    {{ $examPeriod->year }} - {{ $examPeriod->name }}
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Externe Bewerber werden automatisch der Prüfungsperiode "{{ $examPeriod->name }}" zugeordnet.
                                </p>
                            @elseif($examPeriods->isNotEmpty())
                                <!-- Dropdown wenn ExamPeriods vorhanden aber nicht für das Jahr -->
                                <select name="exam_period_id" id="exam_period_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Bitte wählen --</option>
                                    @foreach($examPeriods as $period)
                                        <option value="{{ $period->id }}" {{ old('exam_period_id') == $period->id ? 'selected' : '' }}>
                                            {{ $period->year }} - {{ $period->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-sm text-yellow-600 dark:text-yellow-400">
                                    ⚠️ Für das Jahr {{ $year }} existiert noch keine Prüfungsperiode. Bitte wählen Sie eine andere Periode oder legen Sie eine neue an.
                                </p>
                            @else
                                <!-- Keine ExamPeriods vorhanden -->
                                <div class="mt-1 block w-full px-3 py-2 border border-red-300 dark:border-red-600 rounded-md bg-red-50 dark:bg-red-900/20">
                                    <p class="text-red-600 dark:text-red-400">
                                        ❌ Es sind noch keine Prüfungsperioden angelegt.
                                    </p>
                                </div>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    Bitte legen Sie zuerst eine Prüfungsperiode unter "Einstellungen → Prüfungsperioden" an.
                                </p>
                            @endif
                            
                            @error('exam_period_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- § 64: Ersatzschule. Nur wer hier eine Schule stehen
                             hat, kann nach § 64 Abs. 2 in zwei Fächern das
                             Ergebnis aus 13/II einbringen. Leer heisst: anderer
                             Bewerber nach § 60 — der Regelfall. --}}
                        <div class="mb-4">
                            <label for="ersatzschule_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Ersatzschule
                            </label>
                            <select name="ersatzschule_id" id="ersatzschule_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- keine (anderer Bewerber nach § 60) --</option>
                                @foreach($ersatzschulen as $schule)
                                    <option value="{{ $schule->id }}"
                                            {{ (int) old('ersatzschule_id') === $schule->id ? 'selected' : '' }}>
                                        {{ $schule->name }}@if($schule->ort), {{ $schule->ort }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <input type="text" name="ersatzschule_neu" value="{{ old('ersatzschule_neu') }}"
                                       placeholder="oder neue Ersatzschule: Name"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm">
                                <input type="text" name="ersatzschule_neu_ort" value="{{ old('ersatzschule_neu_ort') }}"
                                       placeholder="Ort (freiwillig)"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm">
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Ein Name im Feld „neue Ersatzschule" hat Vorrang vor der Auswahl. Gleiche Namen werden zusammengeführt.
                            </p>
                            @error('ersatzschule_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('ersatzschule_neu')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nachteilsausgleich -->
                        <div class="mb-4">
                            <label for="disability_compensation_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Nachteilsausgleich (%)
                            </label>
                            <input type="number" name="disability_compensation_percent" id="disability_compensation_percent"
                                   min="0" max="100" step="5"
                                   value="{{ old('disability_compensation_percent') }}"
                                   placeholder="z. B. 25"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Verlängert die Vorbereitungs- und Bearbeitungszeiten wie bei Schülerinnen und Schülern. Leer lassen, wenn keiner gewährt ist.
                            </p>
                            @error('disability_compensation_percent')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Notizen zum Nachteilsausgleich -->
                        <div class="mb-4">
                            <label for="disability_compensation_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Notizen zum Nachteilsausgleich
                            </label>
                            <textarea name="disability_compensation_notes" id="disability_compensation_notes" rows="2"
                                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('disability_compensation_notes') }}</textarea>
                            @error('disability_compensation_notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Notizen -->
                        <div class="mb-4">
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Notizen
                            </label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end space-x-3 mt-6">
                            <a href="{{ route('external-applicants.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Speichern & Fächerwahl
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
