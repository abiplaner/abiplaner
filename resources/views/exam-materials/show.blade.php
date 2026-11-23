@php
    /** @var \App\Models\ExamMaterial $examMaterial */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subject> $faecher */
    /** @var \Illuminate\Support\Collection $zuordnungen */
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                @if($examMaterial->icon)<span class="mr-2">{{ $examMaterial->icon }}</span>@endif
                {{ $examMaterial->name }}
            </h2>
            @unlessarchive
            @can('update', $examMaterial)
                <a href="{{ route('written-exams.materials.edit', $examMaterial) }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                    {{ __('Bearbeiten') }}
                </a>
            @endcan
            @endarchive
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($examMaterial->description)
                        <p class="text-gray-700 dark:text-gray-300">{{ $examMaterial->description }}</p>
                    @else
                        <p class="text-gray-400 dark:text-gray-500 italic">{{ __('Keine Beschreibung hinterlegt.') }}</p>
                    @endif

                    @if($examMaterial->is_default)
                        <span class="inline-flex items-center mt-4 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-full">
                            {{ __('Grundausstattung') }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold mb-1">{{ __('Zugeordnete Fächer') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        In der Material-Checkliste der Aufsichtsliste erscheint dieses Material
                        bei genau diesen Fächern.
                    </p>

                    @if($faecher->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ __('Noch keinem Fach zugeordnet — das Material erscheint damit auf keiner Aufsichtsliste.') }}
                        </p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($faecher as $fach)
                                @php $zuordnung = $zuordnungen->get($fach->id); @endphp
                                <li class="py-3 flex items-start justify-between gap-4">
                                    <div>
                                        <span class="text-gray-900 dark:text-gray-100">{{ $fach->name }}</span>
                                        <span class="text-gray-400 dark:text-gray-500">({{ $fach->code }})</span>
                                        @if($zuordnung?->notes)
                                            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $zuordnung->notes }}</span>
                                        @endif
                                    </div>
                                    @if($zuordnung?->is_required)
                                        <span class="shrink-0 inline-flex items-center px-2 py-1 text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full">
                                            {{ __('erforderlich') }}
                                        </span>
                                    @else
                                        <span class="shrink-0 inline-flex items-center px-2 py-1 text-xs bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded-full">
                                            {{ __('zugelassen') }}
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div>
                <a href="{{ route('written-exams.materials.index') }}"
                   class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                    {{ __('Zurück zur Übersicht') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
