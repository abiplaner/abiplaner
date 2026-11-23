<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Neuen Schüler anlegen') }}
            </h2>
            <a href="{{ route('students.index') }}" 
               class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                Zurück zur Liste
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('students.store') }}">
                        @csrf

                        <!-- Schüler-Nummer -->
                        <div class="mb-6">
                            <label for="external_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Schüler-Nummer *
                            </label>
                            <input type="text" 
                                   name="external_id" 
                                   id="external_id" 
                                   value="{{ old('external_id') }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('external_id') border-red-500 @enderror"
                                   required>
                            @error('external_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Vorname -->
                        <div class="mb-6">
                            <label for="firstname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Vorname *
                            </label>
                            <input type="text" 
                                   name="firstname" 
                                   id="firstname" 
                                   value="{{ old('firstname') }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('firstname') border-red-500 @enderror"
                                   required>
                            @error('firstname')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nachname -->
                        <div class="mb-6">
                            <label for="lastname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Nachname *
                            </label>
                            <input type="text" 
                                   name="lastname" 
                                   id="lastname" 
                                   value="{{ old('lastname') }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('lastname') border-red-500 @enderror"
                                   required>
                            @error('lastname')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nachteilsausgleich -->
                        <div class="mb-6">
                            <label for="disability_compensation_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Nachteilsausgleich (in %)
                            </label>
                            <input type="number" 
                                   name="disability_compensation_percent" 
                                   id="disability_compensation_percent" 
                                   value="{{ old('disability_compensation_percent') }}"
                                   min="0"
                                   max="100"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('disability_compensation_percent') border-red-500 @enderror"
                                   placeholder="z.B. 10, 20, 30">
                            @error('disability_compensation_percent')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Optional: Zeitverlängerung in Prozent (z.B. 20 für 20% mehr Zeit)
                            </p>
                        </div>

                        <!-- Nachteilsausgleich Notizen -->
                        <div class="mb-6">
                            <label for="disability_compensation_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Notizen zum Nachteilsausgleich
                            </label>
                            <textarea name="disability_compensation_notes" 
                                      id="disability_compensation_notes" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('disability_compensation_notes') border-red-500 @enderror"
                                      placeholder="Optionale Hinweise, z.B. Art der Beeinträchtigung, spezielle Anforderungen">{{ old('disability_compensation_notes') }}</textarea>
                            @error('disability_compensation_notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Schriftliche Prüfungen
                        </h3>

                        <!-- S1 Kurs -->
                        <div class="mb-6">
                            <label for="s1_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                S1 Prüfungsfach (1. Schriftliche) *
                            </label>
                            <select name="s1_course_id" 
                                    id="s1_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('s1_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" {{ old('s1_course_id') == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }})
                                        @if($course->level === 'erhoeht') - LK @else - GK @endif
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('s1_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- S2 Kurs -->
                        <div class="mb-6">
                            <label for="s2_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                S2 Prüfungsfach (2. Schriftliche) *
                            </label>
                            <select name="s2_course_id" 
                                    id="s2_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('s2_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" {{ old('s2_course_id') == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }})
                                        @if($course->level === 'erhoeht') - LK @else - GK @endif
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('s2_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- S3 Kurs -->
                        <div class="mb-6">
                            <label for="s3_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                S3 Prüfungsfach (3. Schriftliche) *
                            </label>
                            <select name="s3_course_id" 
                                    id="s3_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('s3_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" {{ old('s3_course_id') == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }})
                                        @if($course->level === 'erhoeht') - LK @else - GK @endif
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('s3_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Mündliche Prüfungen (Kolloquien)
                        </h3>

                        <!-- K1 Kurs -->
                        <div class="mb-6">
                            <label for="k1_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                K1 Prüfungsfach *
                            </label>
                            <select name="k1_course_id" 
                                    id="k1_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('k1_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" {{ old('k1_course_id') == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }}) 
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('k1_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- K2 Kurs -->
                        <div class="mb-6">
                            <label for="k2_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                K2 Prüfungsfach *
                            </label>
                            <select name="k2_course_id" 
                                    id="k2_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('k2_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" {{ old('k2_course_id') == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }}) 
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('k2_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

                        <!-- Buttons -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('students.index') }}" 
                               class="px-6 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                                Abbrechen
                            </a>
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Schüler anlegen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
