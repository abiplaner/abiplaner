{{--
    Vorgezogene mündliche Prüfungen externer Bewerber — § 61 Abs. 4 S. 1.

    Zwei der vier Kolloquien dürfen in den zwei Wochen vor Beginn der
    schriftlichen Abiturprüfungen liegen. Termine werden von Hand vereinbart
    (Entscheidung Wilfrieds vom 31.08.2026), deshalb kein Raster und keine
    Auto-Planung — Datum und Uhrzeit stehen am Termin selbst.

    Der Block erscheint nur, wenn es etwas zu zeigen oder einzutragen gibt.
--}}
@if($vorgezogeneTermine->isNotEmpty() || $vorziehbareExamen->isNotEmpty())
<div class="mb-6">
    <div class="bg-purple-50 border-l-4 border-purple-400 shadow-sm sm:rounded-lg p-4">
        <div class="flex items-start justify-between gap-4 mb-3">
            <div>
                <h3 class="font-semibold text-base flex items-center gap-2 text-purple-900">
                    <span>📅 Vorgezogene mündliche Prüfungen</span>
                    <span class="text-sm font-normal text-purple-600">({{ $vorgezogeneTermine->count() }})</span>
                </h3>
                <p class="text-xs text-purple-700 mt-1">
                    § 61 Abs. 4 S. 1: zwei der vier mündlichen Prüfungen, im Zeitraum
                    <strong>{{ $vorgezogenFensterText }}</strong>.
                    Die Ergebnisse werden erst nach Abschluss der schriftlichen Prüfungen
                    mitgeteilt (§ 61 Abs. 4 S. 3).
                </p>
            </div>
            @if($vorziehbareExamen->isNotEmpty() && $vorgezogenFenster !== null)
                <button data-aktion="open-vorgezogen-neu"
                        class="flex-shrink-0 px-3 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 text-sm font-medium">
                    + Termin eintragen
                </button>
            @endif
        </div>

        @if($vorgezogeneTermine->isEmpty())
            <p class="text-sm text-purple-700 italic">Noch kein Termin vorgezogen.</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-purple-200 text-sm">
                <thead class="bg-purple-100">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Prüfling</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Fach</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Prüfer / Beisitzer</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Datum</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Uhrzeit</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Raum</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Notiz</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-purple-100">
                    @foreach($vorgezogeneTermine as $vt)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $vt->studentExam->getStudentName() }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $vt->studentExam->getSubjectLabel() }}</td>
                        <td class="px-3 py-2 text-gray-600">
                            @if($vt->studentExam->examiner)
                                {{ $vt->studentExam->examiner->shortcode }}
                            @else
                                <span class="text-red-600 font-medium">⚠ Kein Prüfer!</span>
                            @endif
                            @if($vt->studentExam->assessor) / {{ $vt->studentExam->assessor->shortcode }} @endif
                        </td>
                        <td class="px-3 py-2">{{ $vt->free_date?->format('d.m.Y') }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $vt->free_time_start }} – {{ $vt->free_time_end }}</td>
                        <td class="px-3 py-2">{{ $vt->examRoom?->room_number ?? '–' }}</td>
                        <td class="px-3 py-2 text-gray-500 italic text-xs">{{ $vt->notes }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <button data-aktion="open-vorgezogen-edit"
                                        data-id="{{ $vt->id }}"
                                        data-period="{{ $examPeriod->id }}"
                                        data-datum="{{ $vt->free_date?->format('Y-m-d') }}"
                                        data-von="{{ substr((string)$vt->free_time_start, 0, 5) }}"
                                        data-bis="{{ substr((string)$vt->free_time_end, 0, 5) }}"
                                        data-raum="{{ $vt->exam_room_id ?? '' }}"
                                        data-notiz="{{ $vt->notes ?? '' }}"
                                        class="text-blue-500 hover:text-blue-700 text-xs font-medium whitespace-nowrap"
                                        title="Termin bearbeiten">
                                    ✏️ Bearbeiten
                                </button>
                                <button data-aktion="remove-assignment"
                                        data-assignment="{{ $vt->id }}"
                                        class="text-gray-400 hover:text-red-600 text-xs whitespace-nowrap"
                                        title="Termin entfernen">
                                    ✕ Entfernen
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Dialog: vorgezogenen Termin anlegen oder ändern --}}
<div id="vorgezogen-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 id="vorgezogen-modal-titel" class="text-lg font-medium text-gray-900 mb-1">
            📅 Vorgezogene Prüfung eintragen
        </h3>
        <p class="text-xs text-gray-500 mb-4">
            Zulässiger Zeitraum nach § 61 Abs. 4 S. 1: {{ $vorgezogenFensterText }}
        </p>
        <input type="hidden" id="vorgezogen-assignment-id">
        <input type="hidden" id="vorgezogen-exam-period-id" value="{{ $examPeriod->id }}">
        <div class="space-y-4">
            <div id="vorgezogen-pruefung-feld">
                <label class="block text-sm font-medium text-gray-700 mb-1">Prüfung</label>
                <select id="vorgezogen-student-exam-id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-purple-500 focus:ring-purple-500">
                    <option value="">– bitte wählen –</option>
                    @foreach($vorziehbareExamen as $kandidat)
                        <option value="{{ $kandidat->id }}">
                            {{ $kandidat->getStudentName() }} — {{ $kandidat->getSubjectLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
                <input type="date" id="vorgezogen-free-date"
                       @if($vorgezogenFenster !== null)
                           min="{{ $vorgezogenFenster['start']->format('Y-m-d') }}"
                           max="{{ $vorgezogenFenster['ende']->format('Y-m-d') }}"
                       @endif
                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-purple-500 focus:ring-purple-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Von</label>
                    <input type="time" id="vorgezogen-free-time-start" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-purple-500 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bis</label>
                    <input type="time" id="vorgezogen-free-time-end" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-purple-500 focus:ring-purple-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prüfungsraum</label>
                <select id="vorgezogen-exam-room-id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-purple-500 focus:ring-purple-500">
                    <option value="">– bitte wählen –</option>
                    @foreach($examRooms as $room)
                        <option value="{{ $room->id }}">{{ $room->room_number }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notiz</label>
                <input type="text" id="vorgezogen-notes" maxlength="500" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-purple-500 focus:ring-purple-500">
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button data-modal-close="vorgezogen-modal"
                    class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">
                Abbrechen
            </button>
            <button data-aktion="submit-vorgezogen"
                    class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 text-sm font-medium">
                Speichern
            </button>
        </div>
    </div>
</div>
@endif
