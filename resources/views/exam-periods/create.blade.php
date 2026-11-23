<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Neuer Prüfungszeitraum
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Diese Maske wird ausschliesslich von
                         admin.exam-periods.create gerendert. Sie zeigte auf die
                         Mandanten-Route exam-periods.store — die haengt hinter
                         EnsureUserHasSchool und ist fuer einen Super-Admin ohne
                         Schule verschlossen. --}}
                    <form action="{{ route('admin.exam-periods.store') }}" method="POST">
                        @csrf
                        
                        {{-- Grunddaten --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Grunddaten</h3>
                            
                            {{-- Keine Schulauswahl: exam_periods ist eine
                                 zentrale, schulunabhaengige Tabelle ohne
                                 school_id, und store() hat das Feld nie
                                 ausgewertet. Die Auswahl war ausserdem nicht
                                 absendbar — sie las $school->id, und School hat
                                 keine id (Schluessel ist school_number), also
                                 trug jede Option value="" und das required
                                 blockierte das Formular. --}}

                            <div class="mb-4">
                                <x-input-label for="name" value="Bezeichnung" />
                                <x-text-input id="name" name="name" type="text" 
                                              class="mt-1 block w-full" 
                                              value="{{ old('name') }}" 
                                              placeholder="z.B. Abitur 2025" 
                                              required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="exam_type" value="Prüfungstyp" />
                                    <x-text-input id="exam_type" name="exam_type" type="text" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('exam_type', 'Kolloquium G8') }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('exam_type')" class="mt-2" />
                                </div>
                                
                                <div>
                                    <x-input-label for="year" value="Jahr" />
                                    <x-text-input id="year" name="year" type="number" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('year', date('Y')) }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('year')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        
                        {{-- Zeiträume --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Prüfungszeiträume</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <x-input-label for="week_1_start" value="Woche 1 - Start" />
                                    <x-text-input id="week_1_start" name="week_1_start" type="date" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('week_1_start') }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_1_start')" class="mt-2" />
                                </div>
                                
                                <div>
                                    <x-input-label for="week_1_end" value="Woche 1 - Ende" />
                                    <x-text-input id="week_1_end" name="week_1_end" type="date" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('week_1_end') }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_1_end')" class="mt-2" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="week_2_start" value="Woche 2 - Start" />
                                    <x-text-input id="week_2_start" name="week_2_start" type="date" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('week_2_start') }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_2_start')" class="mt-2" />
                                </div>
                                
                                <div>
                                    <x-input-label for="week_2_end" value="Woche 2 - Ende" />
                                    <x-text-input id="week_2_end" name="week_2_end" type="date" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('week_2_end') }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_2_end')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        
                        {{-- Zeitplanung: bewusst keine Felder mehr.
                             exams_per_day, exam_duration_minutes und
                             prep_duration_minutes gibt es auf exam_periods
                             nicht (mehr). Die Eingaben wurden von store()
                             weder geprüft noch gespeichert — drei
                             Pflichtfelder, die ins Leere liefen. Die Zeiten
                             stehen in den Slot-Vorlagen. --}}

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('admin.exam-periods.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <x-primary-button>
                                Prüfungszeitraum erstellen
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
