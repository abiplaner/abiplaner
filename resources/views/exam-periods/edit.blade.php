<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Prüfungszeitraum bearbeiten: {{ $examPeriod->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Diese Maske wird ausschliesslich von
                         admin.exam-periods.edit gerendert. Sie zeigte auf die
                         Mandanten-Route exam-periods.update — die haengt hinter
                         EnsureUserHasSchool und ist fuer einen Super-Admin ohne
                         Schule verschlossen. --}}
                    <form action="{{ route('admin.exam-periods.update', $examPeriod) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        {{-- Grunddaten --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Grunddaten</h3>
                            
                            <div class="mb-4">
                                <x-input-label for="name" value="Bezeichnung" />
                                <x-text-input id="name" name="name" type="text" 
                                              class="mt-1 block w-full" 
                                              value="{{ old('name', $examPeriod->name) }}" 
                                              placeholder="z.B. Abitur 2025" 
                                              required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            
                            <div class="mb-4">
                                <x-input-label for="year" value="Jahr" />
                                <x-text-input id="year" name="year" type="number" 
                                              class="mt-1 block w-full" 
                                              value="{{ old('year', $examPeriod->year) }}" 
                                              required />
                                <x-input-error :messages="$errors->get('year')" class="mt-2" />
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
                                                  value="{{ old('week_1_start', $examPeriod->week_1_start->format('Y-m-d')) }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_1_start')" class="mt-2" />
                                </div>
                                
                                <div>
                                    <x-input-label for="week_1_end" value="Woche 1 - Ende" />
                                    <x-text-input id="week_1_end" name="week_1_end" type="date" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('week_1_end', $examPeriod->week_1_end->format('Y-m-d')) }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_1_end')" class="mt-2" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="week_2_start" value="Woche 2 - Start" />
                                    <x-text-input id="week_2_start" name="week_2_start" type="date" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('week_2_start', $examPeriod->week_2_start->format('Y-m-d')) }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_2_start')" class="mt-2" />
                                </div>
                                
                                <div>
                                    <x-input-label for="week_2_end" value="Woche 2 - Ende" />
                                    <x-text-input id="week_2_end" name="week_2_end" type="date" 
                                                  class="mt-1 block w-full" 
                                                  value="{{ old('week_2_end', $examPeriod->week_2_end->format('Y-m-d')) }}" 
                                                  required />
                                    <x-input-error :messages="$errors->get('week_2_end')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('admin.exam-periods.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <x-primary-button>
                                Änderungen speichern
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
