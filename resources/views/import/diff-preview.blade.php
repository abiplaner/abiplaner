<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Änderungsvorschau: {{ $examPeriod->name }} – Klasse {{ $grade }}
            </h2>
            <a href="{{ route('import.form') }}"
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(!empty($gradeAutoDetected))
                <div class="p-4 rounded-lg bg-amber-50 border border-amber-300 text-amber-900 text-sm">
                    ⚡ Die Kurscodes der Datei gehören zur Q{{ $grade }} — der Abgleich läuft deshalb gegen Jahrgangsstufe {{ $grade }}, nicht gegen die ausgewählte Stufe.
                </div>
            @endif

            {{-- Zusammenfassung --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">📊 Zusammenfassung der Änderungen</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">{{ count($diff['new_students']) }}</div>
                        <div class="text-sm text-gray-600">Neue Schüler</div>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">{{ count($diff['new_exams']) + count($diff['updated_exams']) }}</div>
                        <div class="text-sm text-gray-600">Neue/geänd. Prüfungen</div>
                    </div>
                    <div class="text-center p-4 bg-red-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">{{ count($diff['removed_exams']) }}</div>
                        <div class="text-sm text-gray-600">Entfernte Prüfungen</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-gray-600">{{ $diff['unchanged_count'] }}</div>
                        <div class="text-sm text-gray-600">Unverändert</div>
                    </div>
                </div>

                @php
                    $totalChanges = count($diff['new_students'])
                        + count($diff['updated_students'])
                        + count($diff['new_exams'])
                        + count($diff['updated_exams'])
                        + count($diff['removed_exams'])
                        + count($diff['removed_students']);
                @endphp

                @if ($totalChanges === 0)
                    <div class="mt-4 p-4 bg-green-50 border-l-4 border-green-400 rounded">
                        <p class="text-green-800 font-medium">✅ Keine Änderungen erkannt – die Datenbank ist bereits aktuell.</p>
                    </div>
                @else
                    <div class="mt-4 p-4 bg-amber-50 border-l-4 border-amber-400 rounded">
                        <p class="text-amber-800 font-medium">⚠️ {{ $totalChanges }} Änderung(en) gefunden. Prüfen Sie die Details unten und bestätigen Sie den Import.</p>
                    </div>
                @endif
            </div>


            {{-- Neue Schüler --}}
            @if (count($diff['new_students']) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-green-700">
                        ➕ Neue Schüler ({{ count($diff['new_students']) }})
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nr.</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Prüfungen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($diff['new_students'] as $s)
                                <tr class="bg-green-50">
                                    <td class="px-4 py-2 text-gray-500">{{ $s['external_id'] }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $s['lastname'] }}, {{ $s['firstname'] }}</td>
                                    <td class="px-4 py-2">
                                        @foreach ($s['courses'] as $c)
                                            <span class="inline-block mr-1 px-2 py-0.5 rounded text-xs {{ $c['exam_type'] === 'written' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                                {{ $c['course_code'] }} / {{ $c['teacher'] }}
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


            {{-- Geänderte Schülernamen --}}
            @if (count($diff['updated_students']) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-blue-700">
                        ✏️ Geänderte Schülernamen ({{ count($diff['updated_students']) }})
                    </h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nr.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bisher</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Neu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($diff['updated_students'] as $s)
                            <tr class="bg-blue-50">
                                <td class="px-4 py-2 text-gray-500">{{ $s['external_id'] }}</td>
                                <td class="px-4 py-2 line-through text-gray-500">{{ $s['old_lastname'] }}, {{ $s['old_firstname'] }}</td>
                                <td class="px-4 py-2 font-medium text-blue-700">{{ $s['new_lastname'] }}, {{ $s['new_firstname'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Neue Prüfungen für bestehende Schüler --}}
            @if (count($diff['new_exams']) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-green-700">
                        ➕ Neue Prüfungen (bestehende Schüler) ({{ count($diff['new_exams']) }})
                    </h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Schüler</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kurs</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lehrer</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Typ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($diff['new_exams'] as $e)
                            <tr class="bg-green-50">
                                <td class="px-4 py-2">{{ $e['student_name'] }}</td>
                                <td class="px-4 py-2 font-mono">{{ $e['course_code'] }}</td>
                                <td class="px-4 py-2">{{ $e['teacher'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $e['exam_type'] === 'written' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                        {{ $e['exam_type'] === 'written' ? 'Schriftlich' : 'Mündlich' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif


            {{-- Geänderte Prüfer --}}
            @if (count($diff['updated_exams']) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-amber-700">
                        ✏️ Geänderte Prüfer ({{ count($diff['updated_exams']) }})
                    </h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Schüler</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kurs</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bisher</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Neu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($diff['updated_exams'] as $e)
                            <tr class="bg-amber-50">
                                <td class="px-4 py-2">{{ $e['student_name'] }}</td>
                                <td class="px-4 py-2 font-mono">{{ $e['course_code'] }}</td>
                                <td class="px-4 py-2 line-through text-gray-500">{{ $e['old_teacher'] }}</td>
                                <td class="px-4 py-2 font-medium text-amber-700">{{ $e['new_teacher'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Entfernte Prüfungen --}}
            @if (count($diff['removed_exams']) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-red-700">
                        🗑️ Entfernte Prüfungen ({{ count($diff['removed_exams']) }})
                        <span class="text-sm font-normal text-gray-500 ml-2">(werden beim Update-Import gelöscht)</span>
                    </h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Schüler</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kurs</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lehrer</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Typ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($diff['removed_exams'] as $e)
                            <tr class="bg-red-50">
                                <td class="px-4 py-2">{{ $e['student_name'] }}</td>
                                <td class="px-4 py-2 font-mono">{{ $e['course_code'] }}</td>
                                <td class="px-4 py-2">{{ $e['teacher'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $e['exam_type'] === 'written' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                        {{ $e['exam_type'] === 'written' ? 'Schriftlich' : 'Mündlich' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif


            {{-- Entfernte Schüler --}}
            @if (count($diff['removed_students']) > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-red-700">
                        👤 Nur in DB (nicht in CSV) ({{ count($diff['removed_students']) }})
                        <span class="text-sm font-normal text-gray-500 ml-2">(werden beim Update-Import NICHT gelöscht)</span>
                    </h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nr.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Prüfungen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($diff['removed_students'] as $s)
                            <tr class="bg-red-50 opacity-60">
                                <td class="px-4 py-2 text-gray-500">{{ $s['external_id'] }}</td>
                                <td class="px-4 py-2">{{ $s['lastname'] }}, {{ $s['firstname'] }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $s['exam_count'] }} Prüfung(en)</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-gray-500">
                        ℹ️ Diese Schüler bleiben in der Datenbank. Verwenden Sie den vollständigen Import mit "Bestehende Daten löschen", um sie zu entfernen.
                    </p>
                </div>
            </div>
            @endif


            {{-- Aktions-Buttons --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if ($totalChanges > 0)
                        <h3 class="text-lg font-semibold mb-4">Änderungen übernehmen</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Der Update-Import fügt neue Schüler und Prüfungen hinzu, aktualisiert geänderte Prüfer
                            und entfernt Prüfungen, die nicht mehr in der CSV stehen. Bestehende Schüler ohne
                            CSV-Eintrag bleiben unverändert.
                        </p>
                        <form action="{{ route('import.process') }}" method="POST">
                            @csrf
                            <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                            <input type="hidden" name="delimiter" value="{{ $delimiter }}">
                            <input type="hidden" name="grade" value="{{ $grade }}">
                            <input type="hidden" name="corrections" value="{}">
                            {{-- KEIN clear_existing → reiner Update-Modus --}}
                            <div class="flex justify-end space-x-3">
                                <a href="{{ route('import.form') }}"
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
                            <a href="{{ route('import.form') }}"
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Zurück
                            </a>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
