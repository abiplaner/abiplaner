<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('courses.index') }}"
               class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Kurs bearbeiten: <span class="text-indigo-600 dark:text-indigo-400">{{ $course->course_code }}</span>
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-700">
                            <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('courses.update', $course) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        {{-- Kursname --}}
                        <div>
                            <label for="course_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Kursname <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="course_code"
                                   name="course_code"
                                   value="{{ old('course_code', $course->course_code) }}"
                                   maxlength="50"
                                   required
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>

                        {{-- Fach --}}
                        <div>
                            <label for="subject_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Fach <span class="text-red-500">*</span>
                            </label>
                            <select id="subject_id"
                                    name="subject_id"
                                    required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- Bitte wählen --</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}"
                                            @if(old('subject_id', $course->subject_id) == $subject->id) selected @endif>
                                        {{ $subject->code }} – {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Niveau --}}
                        <div>
                            <label for="level" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Niveau
                            </label>
                            <select id="level"
                                    name="level"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- Keine Angabe --</option>
                                <option value="grundlegend" @if(old('level', $course->level) === 'grundlegend') selected @endif>
                                    gA (grundlegend)
                                </option>
                                <option value="erhoeht" @if(old('level', $course->level) === 'erhoeht') selected @endif>
                                    eA (erhöht)
                                </option>
                            </select>
                        </div>

                        {{-- Erstprüfer --}}
                        <div>
                            <label for="teacher_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Erstprüfer / Kursleiter
                            </label>
                            <select id="teacher_id"
                                    name="teacher_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- Nicht zugewiesen --</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}"
                                            @if(old('teacher_id', $course->teacher_id) == $teacher->id) selected @endif>
                                        {{ $teacher->shortcode }} – {{ $teacher->lastname }}, {{ $teacher->firstname }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Zweitkorrektor --}}
                        <div>
                            <label for="second_corrector_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Zweitkorrektor
                            </label>
                            <select id="second_corrector_id"
                                    name="second_corrector_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- Nicht zugewiesen --</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}"
                                            @if(old('second_corrector_id', $course->second_corrector_id) == $teacher->id) selected @endif>
                                        {{ $teacher->shortcode }} – {{ $teacher->lastname }}, {{ $teacher->firstname }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Info-Box --}}
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-xs text-blue-700 dark:text-blue-300">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Schüler und Prüfungen werden durch diese Änderung nicht berührt. Erstprüfer und Zweitkorrektor werden jedoch in den verknüpften Prüfungseinträgen aktualisiert.
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <a href="{{ route('courses.index') }}"
                               class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                Abbrechen
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md shadow-sm transition-colors">
                                Speichern
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
