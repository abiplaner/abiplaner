<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lehrkräfte importieren
            </h2>
            <a href="{{ route('teachers.index') }}" 
               class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        CSV-Datei hochladen
                    </h3>

                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
                        <h4 class="font-medium text-blue-900 mb-2">Dateiformat:</h4>
                        <p class="text-sm text-blue-800 mb-2">
                            Die CSV-Datei muss folgendes Format haben:
                        </p>
                        <code class="block text-xs bg-white p-2 rounded border text-gray-700">
                            Nr.;Name;Lehrkräfte.Kürzel;Lehrkräfte.Amtsbezeichnung.Anzeigeform;Lehrkräfte.Lehrbefähigungen<br>
                            1;Arnold, Jana;ALB;Oberstudienrat;Mu - GY<br>
                            2;Ambros, Ruth;ANG;Studienrat;E - GY, Geo - GY, Eth - GY
                        </code>
                        <ul class="mt-3 text-sm text-blue-800 list-disc list-inside">
                            <li>Trennung durch Semikolon (;)</li>
                            <li>Name im Format "Nachname, Vorname"</li>
                            <li>Bei mehreren Kürzeln wird das letzte verwendet</li>
                            <li>Fächer durch Komma getrennt</li>
                            <li>Das Suffix " - GY" wird automatisch entfernt</li>
                        </ul>
                    </div>

                    <form action="{{ route('teachers.import') }}" 
                          method="POST" 
                          enctype="multipart/form-data"
                          class="space-y-6">
                        @csrf

                        <div>
                            <label for="file" class="block text-sm font-medium text-gray-700">
                                CSV-Datei auswählen
                            </label>
                            <input type="file" 
                                   name="file" 
                                   id="file"
                                   accept=".csv,.txt"
                                   required
                                   class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer focus:outline-none focus:border-blue-500">
                            <p class="mt-1 text-sm text-gray-500">
                                Maximale Dateigröße: 2 MB
                            </p>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('teachers.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Importieren
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
