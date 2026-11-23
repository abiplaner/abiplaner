@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $materials */
    /** @var \App\Models\ExamMaterial $material */
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Prüfungsmaterialien') }}
            </h2>
            @unlessarchive
            @can('create', App\Models\ExamMaterial::class)
                <a href="{{ route('written-exams.materials.create') }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ __('Neues Material') }}
                </a>
            @endcan
            @endarchive
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Suchformular -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('written-exams.materials.index') }}" class="flex gap-4">
                        <div class="flex-1">
                            <input type="text" 
                                   name="search" 
                                   value="{{ request('search') }}"
                                   placeholder="{{ __('Material suchen...') }}"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="submit" 
                                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors duration-150">
                            {{ __('Suchen') }}
                        </button>
                        @if(request('search'))
                            <a href="{{ route('written-exams.materials.index') }}" 
                               class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition-colors duration-150">
                                {{ __('Zurücksetzen') }}
                            </a>
                        @endif
                    </form>
                </div>
            </div>

            <!-- Materialliste -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if($materials->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Keine Materialien vorhanden') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Beginnen Sie, indem Sie ein neues Material anlegen.') }}</p>
                            @unlessarchive
                            @can('create', App\Models\ExamMaterial::class)
                                <div class="mt-6">
                                    <a href="{{ route('written-exams.materials.create') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        {{ __('Neues Material') }}
                                    </a>
                                </div>
                            @endcan
                            @endarchive
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($materials as $material)
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow duration-150">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center">
                                            @if($material->icon)
                                                <span class="text-3xl mr-3">{{ $material->icon }}</span>
                                            @else
                                                <svg class="w-10 h-10 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                </svg>
                                            @endif
                                            <div>
                                                <h3 class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                                                    {{ $material->name }}
                                                </h3>
                                                {{-- Hier standen zwei Abzeichen "Global" und "Schule", die an
                                                     $material->school_id === null hingen. exam_materials hat keine
                                                     solche Spalte — der Vergleich traf immer zu, jedes Material war
                                                     "Global". Es gibt die Unterscheidung auch nicht: In einer
                                                     Datenbank je Schule gehoert jedes Material dieser einen Schule.
                                                     (31.08.2026, vgl. den entfallenen scopeForSchool() am Modell.) --}}
                                                @if($material->is_default)
                                                    <span class="inline-flex items-center px-2 py-1 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-full">
                                                        {{ __('Grundausstattung') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @if($material->description)
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                            {{ Str::limit($material->description, 100) }}
                                        </p>
                                    @endif

                                    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 mb-4">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                        {{ trans_choice('{0}Keinem Fach zugeordnet|{1}Einem Fach zugeordnet|[2,*]:count Fächern zugeordnet', $material->faecher_anzahl ?? 0) }}
                                    </div>

                                    <div class="flex gap-2">
                                        <a href="{{ route('written-exams.materials.show', $material) }}" 
                                           class="flex-1 text-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors duration-150">
                                            {{ __('Details') }}
                                        </a>
                                        @unlessarchive
                                        @can('update', $material)
                                            <a href="{{ route('written-exams.materials.edit', $material) }}" 
                                               class="px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded-lg transition-colors duration-150">
                                                {{ __('Bearbeiten') }}
                                            </a>
                                        @endcan
                                        @endarchive
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $materials->links() }}
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
