@php
    /** @var \App\Models\ExamMaterial $examMaterial */
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Material bearbeiten') }}: {{ $examMaterial->name }}
            </h2>
            <a href="{{ route('written-exams.materials.show', $examMaterial) }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                {{ __('Ansicht') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('written-exams.materials.update', $examMaterial) }}">
                        @csrf
                        @method('PUT')

                        @include('exam-materials._form')

                        <div class="flex items-center justify-between mt-6">
                            <button type="submit"
                                    form="material-loeschen"
                                    class="px-4 py-2 text-sm text-red-700 dark:text-red-400 hover:underline">
                                {{ __('Material löschen') }}
                            </button>

                            <div class="flex items-center space-x-3">
                                <a href="{{ route('written-exams.materials.index') }}"
                                   class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                                    {{ __('Abbrechen') }}
                                </a>
                                <x-primary-button>
                                    {{ __('Speichern') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>

                    {{-- Eigenes Formular, ausserhalb des Bearbeiten-Formulars: Ein form
                         im form gibt es in HTML nicht. Der Knopf oben verweist ueber
                         das form-Attribut hierher. --}}
                    <form id="material-loeschen" method="POST"
                          action="{{ route('written-exams.materials.destroy', $examMaterial) }}"
                          x-data
                          @submit="if (! confirm('Material „' + {{ Js::from($examMaterial->name) }} + '“ wirklich löschen?')) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
