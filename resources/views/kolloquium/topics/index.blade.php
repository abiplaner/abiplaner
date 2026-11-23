<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Themenkatalog – Kolloquium') }}
            </h2>
            <form method="GET" action="{{ route('kolloquium.topics.index') }}" class="flex items-center gap-2">
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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">



            {{-- Upload der Vorlage --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Vorlage einlesen</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Excel-Vorlage „Kolloquium-Themenbereiche“ hochladen. Vor dem Speichern wird eine
                        Vorschau angezeigt – es wird nichts überschrieben, was nicht ausdrücklich bestätigt wird.
                    </p>
                </div>

                <form method="POST" action="{{ route('kolloquium.topics.preview') }}"
                      enctype="multipart/form-data" class="px-6 py-4">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">

                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <input type="file" name="file" accept=".xlsx,.xls" required
                               class="block w-full text-sm text-gray-700 dark:text-gray-300
                                      file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                      file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700
                                      hover:file:bg-indigo-100 dark:file:bg-indigo-900/40 dark:file:text-indigo-300">

                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                                       text-white text-sm font-semibold rounded-lg shadow transition whitespace-nowrap">
                            Vorschau anzeigen
                        </button>
                    </div>

                    @error('file')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </form>
            </div>

            {{-- Aktueller Themenstand --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">Erfasste Themen</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $courses->count() }} Kurse · {{ $topicCount }} Themen für Jahrgang {{ $year }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('kolloquium.topics.couplings', ['year' => $year]) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                                  text-white text-sm font-semibold rounded-lg shadow transition whitespace-nowrap">
                            Kopplungen erfassen
                        </a>
                        <a href="{{ route('kolloquium.topics.form.pdf', ['year' => $year]) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700
                                  text-white text-sm font-semibold rounded-lg shadow transition whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Koppelformulare (PDF)
                        </a>
                    </div>
                </div>

                @if ($courses->isEmpty())
                    <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        Für diesen Jahrgang sind keine Kurse hinterlegt.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-600 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Kurs</th>
                                    <th class="px-4 py-2 text-left font-semibold">Fach</th>
                                    <th class="px-4 py-2 text-left font-semibold">Lehrkraft</th>
                                    <th class="px-4 py-2 text-left font-semibold">12/1</th>
                                    <th class="px-4 py-2 text-left font-semibold">12/2</th>
                                    <th class="px-4 py-2 text-right font-semibold">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($courses as $course)
                                    @php
                                        $first  = $course->colloquiumTopics->where('semester', '12/1')->sortBy('slot');
                                        $second = $course->colloquiumTopics->where('semester', '12/2')->sortBy('slot');
                                    @endphp
                                    <tr class="text-gray-800 dark:text-gray-200 align-top
                                               {{ $course->colloquiumTopics->isEmpty() ? 'opacity-60' : '' }}">
                                        <td class="px-4 py-2 font-medium whitespace-nowrap">{{ $course->course_code }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $course->subject?->name ?? '–' }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            {{ $course->teacher?->lastname ?? '–' }}
                                        </td>
                                        <td class="px-4 py-2">
                                            @forelse ($first as $topic)
                                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ $topic->slot }}. {{ $topic->title }}
                                                </div>
                                            @empty
                                                <span class="text-xs text-gray-400 dark:text-gray-500">–</span>
                                            @endforelse
                                        </td>
                                        <td class="px-4 py-2">
                                            @forelse ($second as $topic)
                                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ $topic->slot }}. {{ $topic->title }}
                                                </div>
                                            @empty
                                                <span class="text-xs text-gray-400 dark:text-gray-500">–</span>
                                            @endforelse
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            @if ($course->colloquiumTopics->isNotEmpty())
                                                <form method="POST"
                                                      action="{{ route('kolloquium.topics.destroy', $course) }}"
                                                      data-confirm="Themen für Kurs {{ $course->course_code }} wirklich entfernen?"
                                                      class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-xs text-red-600 dark:text-red-400 hover:underline">
                                                        Entfernen
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
