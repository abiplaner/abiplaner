<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Kopplungen erfassen') }}
            </h2>
            <form method="GET" action="{{ route('kolloquium.topics.couplings') }}" class="flex items-center gap-2">
                <label class="text-sm text-gray-600 dark:text-gray-400">Jahrgang:</label>
                <select name="year" data-submit-on-change
                    class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm">
                    @foreach($examPeriods as $period)
                        <option value="{{ $period->year }}" @selected($period->year == $year)>{{ $period->year }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">


            @if (session('warning'))
                <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800
                            text-amber-800 dark:text-amber-200 px-4 py-3 rounded-lg text-sm">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg px-6 py-4">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200">Gruppenbuchstaben übertragen</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Die Buchstaben von den zurückgelaufenen Formularen eintragen. Gleiche Buchstaben
                    innerhalb eines Kurses ergeben eine Koppelgruppe; leere Felder bedeuten Einzelprüfung.
                    Ein Buchstabe, der in seinem Kurs nur einmal vorkommt, bildet keine Gruppe.
                </p>
            </div>

            @if ($courses->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg px-6 py-8 text-center
                            text-sm text-gray-500 dark:text-gray-400">
                    Für den Jahrgang {{ $year }} sind keine Kolloquiumsprüfungen erfasst.
                </div>
            @else
                <form method="POST" action="{{ route('kolloquium.topics.couplings.save') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">

                    @foreach ($courses as $course)
                        @php
                            $exams = $course->studentExams->sortBy(fn ($e) => [
                                $e->student?->lastname, $e->student?->firstname,
                            ]);
                            $topics = $course->colloquiumTopics->sortBy(['semester', 'slot']);
                        @endphp

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700
                                        flex items-center justify-between">
                                <div>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">
                                        {{ $course->course_code }}
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                        {{ $course->subject?->name }} · {{ $course->teacher?->lastname }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $exams->count() }} Prüflinge
                                </span>
                            </div>

                            @if ($topics->isNotEmpty())
                                <div class="px-5 py-2 bg-gray-50 dark:bg-gray-900/40 border-b
                                            border-gray-200 dark:border-gray-700">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        @foreach ($topics as $topic)
                                            <span class="mr-3">
                                                <span class="font-medium">{{ $topic->semester }}/{{ $topic->slot }}</span>
                                                {{ $topic->title }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($exams as $exam)
                                    <div class="px-5 py-2 flex items-center gap-4">
                                        <input type="text" maxlength="3"
                                               name="letters[{{ $exam->id }}]"
                                               value="{{ $currentLetters[$exam->id] ?? '' }}"
                                               class="w-16 text-center uppercase font-semibold
                                                      border-gray-300 dark:border-gray-600
                                                      dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm">
                                        <span class="text-sm text-gray-800 dark:text-gray-200">
                                            {{ $exam->student?->lastname }}, {{ $exam->student?->firstname }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between bg-white dark:bg-gray-800
                                shadow-sm rounded-lg px-6 py-4">
                        <a href="{{ route('kolloquium.topics.index', ['year' => $year]) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                            Zurück zum Themenkatalog
                        </a>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                                       text-white text-sm font-semibold rounded-lg shadow transition">
                            Kopplungen speichern
                        </button>
                    </div>
                </form>
            @endif

        </div>
    </div>
</x-app-layout>
