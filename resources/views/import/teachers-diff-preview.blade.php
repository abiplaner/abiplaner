<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lehrer-Import: Änderungsvorschau
            </h2>
            <a href="{{ route('teachers.import.form') }}"
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Zusammenfassung --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">📊 Zusammenfassung</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">{{ count($newTeachers) }}</div>
                        <div class="text-sm text-gray-600">Neu</div>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-600">{{ count($updatedTeachers) }}</div>
                        <div class="text-sm text-gray-600">Geändert</div>
                    </div>
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600">{{ count($deactivateProposals) }}</div>
                        <div class="text-sm text-gray-600">Nicht in CSV</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-gray-500">{{ $unchangedCount }}</div>
                        <div class="text-sm text-gray-600">Unverändert</div>
                    </div>
                </div>

                @php $totalChanges = count($newTeachers) + count($updatedTeachers) + count($deactivateProposals); @endphp
                @if ($totalChanges === 0)
                    <div class="mt-4 p-4 bg-green-50 border-l-4 border-green-400 rounded">
                        <p class="text-green-800 font-medium">✅ Keine Änderungen erkannt – alle Lehrer sind aktuell.</p>
                    </div>
                @else
                    <div class="mt-4 p-4 bg-amber-50 border-l-4 border-amber-400 rounded">
                        <p class="text-amber-800 font-medium">⚠️ {{ $totalChanges }} Änderung(en) gefunden. Prüfen Sie die Details und bestätigen Sie den Import.</p>
                    </div>
                @endif
            </div>


            {{-- Neue Lehrer --}}
            @if (count($newTeachers) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-green-700">➕ Neue Lehrer ({{ count($newTeachers) }})</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kürzel</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Beschäftigung</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Std.</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fächer</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($newTeachers as $t)
                                <tr class="bg-green-50">
                                    <td class="px-4 py-2 font-mono font-semibold text-green-700">{{ $t['shortcode'] }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $t['name'] }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $t['title'] ?: '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $t['employment_type'] ?: '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $t['hours'] ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @foreach ($t['subjects'] as $s)
                                            <span class="inline-block px-2 py-0.5 rounded text-xs mr-1 {{ $s['valid'] ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                                                {{ $s['code'] }}{{ !$s['valid'] ? ' ⚠️' : '' }}
                                            </span>
                                        @endforeach
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif


            {{-- Geänderte Lehrer --}}
            @if (count($updatedTeachers) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-yellow-700">✏️ Geänderte Lehrer ({{ count($updatedTeachers) }})</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kürzel</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Änderungen</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fächer</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($updatedTeachers as $t)
                                <tr class="bg-yellow-50">
                                    <td class="px-4 py-2 font-mono font-semibold text-yellow-700">{{ $t['shortcode'] }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $t['name'] }}</td>
                                    <td class="px-4 py-2">
                                        <ul class="text-xs space-y-0.5">
                                            @foreach ($t['changes'] as $change)
                                                <li class="text-yellow-800">{{ $change }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="px-4 py-2">
                                        @foreach ($t['subjects'] as $s)
                                            <span class="inline-block px-2 py-0.5 rounded text-xs mr-1 {{ $s['valid'] ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                                                {{ $s['code'] }}{{ !$s['valid'] ? ' ⚠️' : '' }}
                                            </span>
                                        @endforeach
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif


            {{-- Deaktivierungsvorschläge (Modul 1.1): in DB aktiv, fehlen in CSV --}}
            @if (count($deactivateProposals) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-1 text-orange-700">
                        🚪 Deaktivierungsvorschläge ({{ count($deactivateProposals) }})
                        <span class="text-sm font-normal text-gray-500 ml-2">(aktiv in DB, fehlen in CSV)</span>
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Diese Lehrkräfte werden <strong>nicht gelöscht</strong>. Markieren Sie diejenigen, die die
                        Schule verlassen haben — sie werden auf „inaktiv" gesetzt (Historie bleibt erhalten).
                        Nicht markierte Lehrkräfte bleiben unverändert aktiv (z.&nbsp;B. bei unvollständiger Importliste).
                    </p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        <label class="flex items-center gap-1 cursor-pointer">
                                            <input type="checkbox" id="selectAllDeactivate" class="rounded" />
                                            <span class="normal-case font-normal">Deaktivieren</span>
                                        </label>
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kürzel</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fächer</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Verwendung</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($deactivateProposals as $t)
                                <tr class="bg-orange-50">
                                    <td class="px-4 py-2">
                                        <input type="checkbox"
                                               name="deactivate[]"
                                               value="{{ $t['shortcode'] }}"
                                               form="teacherImportForm"
                                               class="deactivate-checkbox rounded" />
                                    </td>
                                    <td class="px-4 py-2 font-mono font-semibold text-orange-700">{{ $t['shortcode'] }}</td>
                                    <td class="px-4 py-2">{{ $t['name'] }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $t['title'] ?: '—' }}</td>
                                    <td class="px-4 py-2">
                                        @foreach ($t['subjects'] as $s)
                                            <span class="inline-block px-2 py-0.5 rounded text-xs mr-1 bg-gray-100 text-gray-600">{{ $s['code'] }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        {{ !empty($t['dependencies']) ? implode(', ', $t['dependencies']) : '—' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif


            {{-- Aktionsbereich --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if ($totalChanges > 0)
                        <h3 class="text-lg font-semibold mb-3">Änderungen übernehmen</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Neue Lehrer werden angelegt, geänderte Felder aktualisiert, markierte Lehrkräfte deaktiviert.
                            Nicht markierte und unveränderte Lehrkräfte bleiben unangetastet. Es wird nichts gelöscht.
                        </p>
                        <form action="{{ route('teachers.import') }}" method="POST" id="teacherImportForm">
                            @csrf
                            <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('teachers.import.form') }}"
                                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Abbrechen
                                </a>
                                <button type="submit"
                                        class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 font-semibold">
                                    ✅ Änderungen jetzt übernehmen
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="flex justify-end">
                            <a href="{{ route('teachers.import.form') }}"
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Zurück
                            </a>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <script @cspNonce>
        // "Alle deaktivieren"-Checkbox im Vorschlagsblock
        const selectAllDeactivate = document.getElementById('selectAllDeactivate');
        if (selectAllDeactivate) {
            selectAllDeactivate.addEventListener('change', function () {
                document.querySelectorAll('.deactivate-checkbox').forEach(cb => {
                    cb.checked = this.checked;
                });
            });
        }
    </script>
</x-app-layout>
