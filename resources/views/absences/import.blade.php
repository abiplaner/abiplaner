<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Absenzen importieren (Infoportal-Export)
            </h2>
            <a href="{{ route('absences.index') }}"
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4 text-sm text-blue-800">
                <p class="mb-1">CSV-Export aus dem Infoportal (Absenzenbericht), Semikolon-getrennt.</p>
                <p class="mb-1">Sie können <strong>eine oder mehrere CSV-Dateien gleichzeitig</strong> (z.&thinsp;B. für Q12 und Q13 zusammen) auswählen oder die Jahrgänge nacheinander importieren — bestehende Absenzen des jeweils anderen Jahrgangs bleiben vollständig erhalten.</p>
                <p>Schüler werden automatisch über Nachname + Vorname zugeordnet. Bei mehrdeutigen
                   oder fehlenden Treffern können Sie die Zuordnung in der Vorschau manuell festlegen.</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('absences.import.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        <div>
                            <label for="files" class="block text-sm font-medium text-gray-700 mb-2">
                                CSV-Datei(en) auswählen (z.&thinsp;B. Q12 und Q13 gemeinsam) *
                            </label>
                            <input type="file" name="files[]" id="files" accept=".csv,.txt" multiple required
                                   class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Mehrfachauswahl möglich (Strg/Cmd + Klick), um Q12- und Q13-Bericht in einem Schritt zu importieren.</p>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('absences.index') }}"
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Vorschau anzeigen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
