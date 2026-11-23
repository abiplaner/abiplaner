<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{-- Der Controller uebergibt $writtenExamYearDate. Diese Maske
                     las durchgehend ein $examDate, das es nie gab, und rief drei
                     Routennamen auf (written-exam-assignments.*), die nie
                     existiert haben — sie heissen written-exams.assignments.*.
                     Die Seite endete deshalb in einer RouteNotFoundException,
                     auch als Ziel der Weiterleitung nach destroy(). --}}
                {{ __('Kurs- und Raumzuordnungen') }}: {{ $writtenExamYearDate->slot->name }}
            </h2>
            <div class="flex items-center gap-2">
                {{-- Keine Schaltflaeche "Neue Zuordnung" mehr: Die Maske
                     dahinter antwortete seit jeher mit 500 und ist am
                     30.08.2026 entfallen. Angelegt werden Zuordnungen ueber die
                     Tages-Zuordnung, verlinkt aus der Terminliste. --}}
                @unlessarchive
                <a href="{{ route('written-exams.dates.day-assign', $writtenExamYearDate->exam_date->format('Y-m-d')) }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Zuordnungen für diesen Tag') }}
                </a>
                @endarchive
                <a href="{{ route('written-exams.dates.show', $writtenExamYearDate) }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg shadow-md transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Zurück') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Prüfungsinfo -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Datum') }}</label>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $writtenExamYearDate->date->format('d.m.Y') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Fach') }}</label>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $writtenExamYearDate->subject?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Beginn') }}</label>
                            {{-- Die Uhrzeit steht am Slot, nicht am Jahrestermin:
                                 written_exam_year_dates hat keine Spalte
                                 start_time. --}}
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($writtenExamYearDate->slot->start_time)->format('H:i') }} Uhr</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Zuordnungen') }}</label>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $assignments->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiken -->
            {{-- Aus $statistics des Controllers, nicht neu gerechnet: Der
                 fruehere Rechenblock hier las $a->layout — eine Beziehung, die
                 es auf WrittenExamAssignment nicht gibt. Sie lieferte still
                 null, und ->getTotalCapacity() darauf zerlegte die Seite. Der
                 Controller rechnet dasselbe laengst richtig, ueber
                 getSeatingTemplate(), und laedt die Vorlagen dafuer eager.

                 (Keine Blade-Direktive in diesem Kommentar: Blade uebersetzt
                 sie auch hier drin und zerlegt dann den ganzen Block.) --}}
            @php
                $totalCapacity = $statistics['total_capacity'];
                $totalAssigned = $statistics['total_students_assigned'];
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200">{{ __('Gesamt-Kapazität') }}</h3>
                    <p class="text-3xl font-bold text-blue-900 dark:text-blue-100">{{ $totalCapacity }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                    <h3 class="text-sm font-semibold text-green-800 dark:text-green-200">{{ __('Zugewiesene Plätze') }}</h3>
                    <p class="text-3xl font-bold text-green-900 dark:text-green-100">{{ $totalAssigned }}</p>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
                    <h3 class="text-sm font-semibold text-orange-800 dark:text-orange-200">{{ __('Freie Plätze') }}</h3>
                    <p class="text-3xl font-bold text-orange-900 dark:text-orange-100">{{ $totalCapacity - $totalAssigned }}</p>
                </div>
            </div>

            <!-- Zuordnungen-Tabelle -->
            @if($assignments->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            {{ __('Alle Zuordnungen') }}
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Kurs') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Raum') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Layout') }}</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Belegung') }}</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Aktionen') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($assignments as $assignment)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $assignment->course->course_code }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                {{-- room_number, nicht name: rooms hat keine Spalte name und
                                                     keine building. Die Spalte RAUM stand deshalb in jeder
                                                     Zeile leer, und die Gebaeude-Klammer konnte nie
                                                     erscheinen. Vierter Fund derselben Art in dieser Maske. --}}
                                                {{ $assignment->room?->room_number ?? '—' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                {{ $assignment->getSeatingTemplate()?->name ?? '—' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                @php
                                                    // Kein $assignment->layout: die Beziehung gibt es nicht.
                                                    // Der Sitzplan haengt am Raum, ueber getSeatingTemplate().
                                                    $assignedCount = $assignment->getAssignedStudentsCount();
                                                    $capacity      = $assignment->getSeatingTemplate()?->getTotalCapacity() ?? 0;
                                                    $percentage    = $capacity > 0 ? ($assignedCount / $capacity) * 100 : 0;
                                                @endphp
                                                <div class="flex flex-col items-center">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $assignedCount }} / {{ $capacity }}
                                                    </span>
                                                    <div class="w-full max-w-xs bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-1">
                                                        <div class="h-2 rounded-full transition-all
                                                            @if($percentage >= 100) bg-green-600
                                                            @elseif($percentage >= 75) bg-yellow-500
                                                            @elseif($percentage > 0) bg-blue-500
                                                            @else bg-gray-400
                                                            @endif"
                                                            style="width: {{ min($percentage, 100) }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="{{ route('written-exams.assignments.show', [$writtenExamYearDate, $assignment]) }}" 
                                                       class="inline-flex items-center px-3 py-1 bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900/30 dark:hover:bg-green-900/50 dark:text-green-300 rounded-lg text-sm font-medium transition-colors">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                                        </svg>
                                                        {{ __('Sitzplan') }}
                                                    </a>
                                                    @unlessarchive
                                                    <form action="{{ route('written-exams.assignments.destroy', [$writtenExamYearDate, $assignment]) }}" method="POST" class="inline-block"
                                                          data-confirm="{{ __('Wirklich löschen? Alle Sitzplatzzuweisungen werden entfernt.') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="inline-flex items-center px-3 py-1 bg-red-100 hover:bg-red-200 text-red-800 dark:bg-red-900/30 dark:hover:bg-red-900/50 dark:text-red-300 rounded-lg text-sm font-medium transition-colors">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            {{ __('Löschen') }}
                                                        </button>
                                                    </form>
                                                    @endarchive
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-center py-8 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <p class="mt-4 text-lg">{{ __('Noch keine Zuordnungen vorhanden.') }}</p>
                            <p class="mt-2">{{ __('Erstellen Sie die erste Kurs-Raum-Zuordnung.') }}</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
