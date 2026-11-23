    {{-- Nachtermine außerhalb der Prüfungswochen --}}
    @if($outsideAssignments->isNotEmpty())
    <div class="mb-6">
        <div class="bg-amber-50 border-l-4 border-amber-400 shadow-sm sm:rounded-lg p-4">
            <h3 class="font-semibold text-base mb-3 flex items-center gap-2 text-amber-800">
                <span>🔄 Nachtermine außerhalb der Prüfungswochen</span>
                <span class="text-sm font-normal text-amber-600">({{ $outsideAssignments->count() }})</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-amber-200 text-sm">
                    <thead class="bg-amber-100">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700 uppercase">Schüler</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700 uppercase">Kurs</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700 uppercase">Prüfer / Beisitzer</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700 uppercase">Datum</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700 uppercase">Uhrzeit</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700 uppercase">Raum</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700 uppercase">Notiz</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-amber-100">
                        @foreach($outsideAssignments as $oa)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $oa->studentExam->getStudentName() }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $oa->studentExam->getSubjectLabel() }}</td>
                            <td class="px-3 py-2 text-gray-600">
                                {{ $oa->studentExam->examiner?->shortcode ?? '—' }}
                                @if($oa->studentExam->assessor) / {{ $oa->studentExam->assessor->shortcode }} @endif
                            </td>
                            <td class="px-3 py-2">{{ $oa->free_date?->format('d.m.Y') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $oa->free_time_start }} – {{ $oa->free_time_end }}</td>
                            <td class="px-3 py-2">{{ $oa->examRoom?->room_number ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-500 italic text-xs">{{ $oa->notes }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <button data-aktion="open-edit-outside"
                                            data-id="{{ $oa->id }}"
                                            data-period="{{ $examPeriod->id }}"
                                            data-datum="{{ $oa->free_date?->format('Y-m-d') }}"
                                            data-von="{{ substr((string)$oa->free_time_start, 0, 5) }}"
                                            data-bis="{{ substr((string)$oa->free_time_end, 0, 5) }}"
                                            data-raum="{{ $oa->exam_room_id ?? '' }}"
                                            data-notiz="{{ $oa->notes ?? '' }}"
                                            class="text-blue-500 hover:text-blue-700 text-xs font-medium whitespace-nowrap"
                                            title="Termin bearbeiten">
                                        ✏️ Bearbeiten
                                    </button>
                                    @if($oa->replaces_id)
                                        <button data-aktion="revert-illness" data-assignment="{{ $oa->replaces_id }}" data-period="{{ $examPeriod->id }}"
                                                class="text-gray-400 hover:text-green-600 text-xs whitespace-nowrap"
                                                title="Rückgängig – Originaltermin wiederherstellen">
                                            ↩ Rückgängig
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Edit-Modal für Außerhalb-Termin --}}
    <div id="edit-outside-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">✏️ Außerhalb-Termin bearbeiten</h3>
            <input type="hidden" id="edit-outside-assignment-id">
            <input type="hidden" id="edit-outside-exam-period-id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
                    <input type="date" id="edit-outside-free-date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Von</label>
                        <input type="time" id="edit-outside-free-time-start" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bis</label>
                        <input type="time" id="edit-outside-free-time-end" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Raum (optional)</label>
                    <select id="edit-outside-exam-room-id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">– kein Raum –</option>
                        @foreach($globalExamRooms as $room)
                            <option value="{{ $room->id }}">{{ $room->room_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notiz</label>
                    <input type="text" id="edit-outside-notes" maxlength="500" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button data-modal-close="edit-outside-modal"
                        class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">
                    Abbrechen
                </button>
                <button data-aktion="submit-edit-outside"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                    Speichern
                </button>
            </div>
        </div>
    </div>
    @endif
