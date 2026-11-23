{{--
    Tabelle der noch nicht zugeordneten Vorjahreswerte mit manueller Zuordnung.

    Wird zweimal eingebunden: einmal für die verbleibenden Jahrgänge
    (die eigentliche Klärungsliste) und einmal für den abgegangenen
    Abiturjahrgang, der eingeklappt darunter steht.

    Variablen:
      $rows           — Collection<AbsenceCarryover>
      $targetStudents — Collection<Student> für die Auswahl
--}}
<table class="min-w-full divide-y divide-gray-200 text-sm">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vorjahr (Name)</th>
            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kennzahlen</th>
            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zuordnen zu</th>
            <th class="px-3 py-2"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        @foreach ($rows as $carryover)
        <tr>
            <td class="px-3 py-2 whitespace-nowrap">
                {{ $carryover->lastname }}, {{ $carryover->firstname }}
                <span class="text-xs text-gray-400">(Jgst. {{ $carryover->source_grade ?? '?' }})</span>
            </td>
            <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">
                {{ $carryover->sick_days }} Kr.-Tage,
                {{ $carryover->unexcused_hours }} unentsch. Std.
                @if ($carryover->had_attest_requirement) · Attestpflicht @endif
            </td>
            <td class="px-3 py-2">
                <form method="POST" action="{{ route('year-change.match-manual') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="carryover_id" value="{{ $carryover->id }}">
                    <select name="student_id" class="text-sm rounded-md border-gray-300 py-1" required>
                        <option value="">— Schüler wählen —</option>
                        @foreach ($targetStudents as $student)
                            <option value="{{ $student->id }}">
                                {{ $student->lastname }}, {{ $student->firstname }} (Jgst. {{ $student->grade }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-xs font-semibold">
                        Zuordnen
                    </button>
                </form>
            </td>
            <td></td>
        </tr>
        @endforeach
    </tbody>
</table>
