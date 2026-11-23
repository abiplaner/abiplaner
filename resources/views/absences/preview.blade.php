<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Absenzen-Import: Vorschau
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-6 bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-2">{{ $originalFilename }}</h3>
                <p class="text-sm text-gray-600">
                    {{ $preview['row_count'] }} Zeilen gelesen ·
                    {{ count($preview['matching']) }} unterschiedliche Schüler im Export ·
                    <span class="{{ $preview['unresolved_count'] > 0 ? 'text-amber-700 font-medium' : 'text-green-700' }}">
                        {{ $preview['unresolved_count'] }} Zuordnung(en) zu klären
                    </span>
                </p>
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

            <form id="import-form" action="{{ route('absences.import.process') }}" method="POST">
                @csrf
                <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                <input type="hidden" name="original_filename" value="{{ $originalFilename }}">
                <input type="hidden" name="corrections" id="corrections-field" value="{}">

                @if ($preview['unresolved_count'] > 0)
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-1">Zu klärende Zuordnungen</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Diese Namen aus dem Export konnten nicht eindeutig einem Schüler zugeordnet werden.
                            Wähle den passenden Schüler aus oder "ignorieren", um die Zeilen dieses Namens beim
                            Import zu überspringen.
                        </p>
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="py-2 pr-4">Name (Export)</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2">Zuordnung</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($preview['matching'] as $nameKey => $info)
                                    @if ($info['status'] !== 'matched')
                                        <tr class="border-b">
                                            <td class="py-2 pr-4">{{ $info['lastname'] }}, {{ $info['firstname'] }}</td>
                                            <td class="py-2 pr-4">
                                                @if ($info['status'] === 'unmatched')
                                                    <span class="text-red-700">kein Treffer</span>
                                                @else
                                                    <span class="text-amber-700">mehrdeutig ({{ $info['candidates']->count() }} Treffer)</span>
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                <select class="correction-select border-gray-300 rounded-md text-sm"
                                                        data-name-key="{{ $nameKey }}">
                                                    <option value="skip">-- ignorieren --</option>
                                                    @if ($info['status'] === 'ambiguous')
                                                        @foreach ($info['candidates'] as $candidate)
                                                            <option value="{{ $candidate->id }}">
                                                                {{ $candidate->lastname }}, {{ $candidate->firstname }}
                                                                @if ($candidate->class_name) ({{ $candidate->class_name }}) @endif
                                                            </option>
                                                        @endforeach
                                                    @else
                                                        @foreach ($allStudents as $student)
                                                            <option value="{{ $student->id }}">
                                                                {{ $student->lastname }}, {{ $student->firstname }}
                                                                @if ($student->class_name) ({{ $student->class_name }}) @endif
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-1">Automatisch zugeordnet</h3>
                        <p class="text-sm text-gray-600">
                            {{ collect($preview['matching'])->where('status', 'matched')->count() }} Schüler
                            wurden eindeutig über Nachname + Vorname erkannt.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('absences.import.form') }}"
                       class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Abbrechen
                    </a>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Import durchführen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script @cspNonce>
        document.getElementById('import-form').addEventListener('submit', function () {
            const corrections = {};
            document.querySelectorAll('.correction-select').forEach(function (select) {
                corrections[select.dataset.nameKey] = select.value;
            });
            document.getElementById('corrections-field').value = JSON.stringify(corrections);
        });
    </script>
</x-app-layout>
