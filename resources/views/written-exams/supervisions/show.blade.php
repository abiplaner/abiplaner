<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Aufsichtsverteilung:') }} {{ $writtenExamDate->name }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('written-exams.dates.show', $writtenExamDate) }}" 
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

            <!-- Prüfungsinformationen -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ __('Prüfungsinformationen') }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Fach') }}</label>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $writtenExamDate->subject->name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Datum') }}</label>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">
                                {{ $writtenExamDate->date->format('d.m.Y') }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Beginn') }}</label>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">
                                {{ \Carbon\Carbon::parse($writtenExamDate->start_time)->format('H:i') }} Uhr
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Räume') }}</label>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">
                                {{ $expectedLoad['rooms'] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Erwartete Aufsichtslast -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ __('Erwartete Aufsichtslast') }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {{ $expectedLoad['room_supervisions_needed'] }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('Raumaufsichten') }}</p>
                        </div>
                        <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {{ $expectedLoad['corridor_supervisions_needed'] }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('Gangaufsichten') }}</p>
                        </div>
                        <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ $expectedLoad['total_supervisions_needed'] }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('Gesamt') }}</p>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                                {{ $expectedLoad['available_teachers'] }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('Verfügbare Lehrer') }}</p>
                        </div>
                        <div class="text-center p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $expectedLoad['average_load_per_teacher'] }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('Ø pro Lehrer') }}</p>
                        </div>
                    </div>

                    @if(!$expectedLoad['is_feasible'])
                        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <div class="flex">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm text-red-800 dark:text-red-300">
                                    {{ __('Warnung: Nicht genügend verfügbare Lehrer für die automatische Verteilung.') }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Validierung -->
            @if(isset($validation) && (!empty($validation['errors']) || !empty($validation['warnings'])))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            {{ __('Validierung') }}
                        </h3>

                        @if(!empty($validation['errors']))
                            <div class="mb-4">
                                <h4 class="font-medium text-red-800 dark:text-red-300 mb-2">{{ __('Fehler') }}</h4>
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach($validation['errors'] as $error)
                                        <li class="text-sm text-red-700 dark:text-red-400">{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(!empty($validation['warnings']))
                            <div>
                                <h4 class="font-medium text-yellow-800 dark:text-yellow-300 mb-2">{{ __('Warnungen') }}</h4>
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach($validation['warnings'] as $warning)
                                        <li class="text-sm text-yellow-700 dark:text-yellow-400">{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Aktionen -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-wrap gap-3 justify-center">
                        @unlessarchive
                        @can('create', \App\Models\Supervision::class)
                            <form action="{{ route('written-exams.supervisions.auto-distribute', $writtenExamDate) }}" 
                                  method="POST" 
                                  class="inline">
                                @csrf
                                <button type="submit" 
                                        @if(!$expectedLoad['is_feasible']) disabled @endif
                                        class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold rounded-lg shadow-md transition-colors">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    {{ __('Automatisch verteilen') }}
                                </button>
                            </form>
                        @endcan

                        @can('delete', \App\Models\Supervision::class)
                            <form action="{{ route('written-exams.supervisions.clear', $writtenExamDate) }}" 
                                  method="POST" 
                                  class="inline"
                                  data-confirm="{{ __('Sind Sie sicher, dass Sie alle Aufsichten löschen möchten?') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg shadow-md transition-colors">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    {{ __('Alle löschen') }}
                                </button>
                            </form>
                        @endcan
                        @endarchive

                        <a href="{{ route('written-exams.supervisions.plan', $writtenExamDate) }}" 
                           class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Manuell planen') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Aktuelle Verteilung -->
            @if(isset($statistics) && $statistics['total_supervisions'] > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            {{ __('Aktuelle Verteilung') }}
                        </h3>

                        <!-- Übersicht -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <p class="text-xl font-bold text-blue-600 dark:text-blue-400">
                                    {{ $statistics['room_supervisions'] }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Raumaufsichten') }}</p>
                            </div>
                            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                <p class="text-xl font-bold text-purple-600 dark:text-purple-400">
                                    {{ $statistics['corridor_supervisions'] }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Gangaufsichten') }}</p>
                            </div>
                            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <p class="text-xl font-bold text-green-600 dark:text-green-400">
                                    {{ $statistics['teachers_involved'] }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Beteiligte Lehrer') }}</p>
                            </div>
                        </div>

                        <!-- Aufsichten nach Lehrern -->
                        @if(!empty($statistics['by_teacher']))
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                                {{ __('Lehrer') }}
                                            </th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                                {{ __('Beschäftigung') }}
                                            </th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                                {{ __('Raumaufsichten') }}
                                            </th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                                {{ __('Gangaufsichten') }}
                                            </th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                                {{ __('Gesamt') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($statistics['by_teacher'] as $teacherStat)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $teacherStat['teacher_name'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <span class="px-2 py-1 text-xs rounded-full
                                                        @if($teacherStat['employment_type'] === 'full_time')
                                                            bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                                        @elseif($teacherStat['employment_type'] === 'part_time')
                                                            bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                                        @else
                                                            bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                                        @endif">
                                                        @if($teacherStat['employment_type'] === 'full_time')
                                                            {{ __('Vollzeit') }}
                                                        @elseif($teacherStat['employment_type'] === 'part_time')
                                                            {{ __('Teilzeit') }}
                                                        @else
                                                            {{ __('< 50%') }}
                                                        @endif
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $teacherStat['room_supervisions'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $teacherStat['corridor_supervisions'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <span class="inline-flex items-center px-2 py-1 text-sm font-medium rounded-full
                                                        @if($teacherStat['supervision_count'] >= 3)
                                                            bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                                        @elseif($teacherStat['supervision_count'] >= 2)
                                                            bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                                        @else
                                                            bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                                        @endif">
                                                        {{ $teacherStat['supervision_count'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <!-- Noch keine Verteilung -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-center py-8 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <p class="mt-4 text-lg font-medium">{{ __('Noch keine Aufsichten verteilt') }}</p>
                            <p class="mt-2 text-sm">{{ __('Verwenden Sie die automatische Verteilung oder weisen Sie Aufsichten manuell zu.') }}</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
