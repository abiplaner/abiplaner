<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Neue Leistungskurs-Wahl anlegen
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    @if ($errors->any())
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Fehler beim Speichern</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc list-inside">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form action="{{ route('lk-wahlen.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            {{-- Schuljahr & Jahrgang --}}
                            <div>
                                <label for="schuljahr" class="block text-sm font-medium text-gray-700">Schuljahr</label>
                                <select id="schuljahr" name="schuljahr" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    @foreach($schuljahre as $sj)
                                        <option value="{{ $sj }}" {{ old('schuljahr') == $sj ? 'selected' : '' }}>{{ $sj }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="jahrgang" class="block text-sm font-medium text-gray-700">Jahrgang</label>
                                <select id="jahrgang" name="jahrgang" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="11" {{ old('jahrgang') == 11 ? 'selected' : '' }}>11 (Q11)</option>
                                    <option value="12" {{ old('jahrgang') == 12 ? 'selected' : '' }}>12 (Q12)</option>
                                    <option value="13" {{ old('jahrgang') == 13 ? 'selected' : '' }}>13 (Q13)</option>
                                </select>
                            </div>

                            {{-- Schüler Auswahl --}}
                            <div class="col-span-1 md:col-span-2">
                                <label for="student_id" class="block text-sm font-medium text-gray-700">Schüler auswählen</label>
                                <select id="student_id" name="student_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md select2-enable">
                                    <option value="">Bitte wählen...</option>
                                    @foreach($students as $student)
                                        <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                            {{ $student->lastname }}, {{ $student->firstname }} ({{ $student->class_name ?? 'Keine Klasse' }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Es werden Schüler der Jahrgänge 11-13 angezeigt.</p>
                            </div>

                            {{-- Wahlen --}}
                            <div>
                                <label for="erstwahl_subject_id" class="block text-sm font-medium text-gray-700">1. Leistungskurs-Wahl</label>
                                <select id="erstwahl_subject_id" name="erstwahl_subject_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Bitte wählen...</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}" {{ old('erstwahl_subject_id') == $subject->id ? 'selected' : '' }}>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="zweitwahl_subject_id" class="block text-sm font-medium text-gray-700">2. Leistungskurs-Wahl</label>
                                <select id="zweitwahl_subject_id" name="zweitwahl_subject_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Bitte wählen...</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}" {{ old('zweitwahl_subject_id') == $subject->id ? 'selected' : '' }}>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                        </div>

                        <div class="mt-8 flex justify-end">
                            <a href="{{ route('lk-wahlen.index') }}" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                                Abbrechen
                            </a>
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Wahl speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
