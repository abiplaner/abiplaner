<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Schüler bearbeiten') }}: {{ $student->full_name }}
            </h2>
            <a href="{{ route('students.index') }}" 
               class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                Zurück zur Liste
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('students.update', $student) }}">
                        @csrf
                        @method('PUT')

                        <!-- Schüler-Nummer -->
                        <div class="mb-6">
                            <label for="external_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Schüler-Nummer *
                            </label>
                            <input type="text" 
                                   name="external_id" 
                                   id="external_id" 
                                   value="{{ old('external_id', $student->external_id) }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('external_id') border-red-500 @enderror"
                                   required>
                            @error('external_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Vorname -->
                        <div class="mb-6">
                            <label for="firstname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Vorname *
                            </label>
                            <input type="text" 
                                   name="firstname" 
                                   id="firstname" 
                                   value="{{ old('firstname', $student->firstname) }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('firstname') border-red-500 @enderror"
                                   required>
                            @error('firstname')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nachname -->
                        <div class="mb-6">
                            <label for="lastname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Nachname *
                            </label>
                            <input type="text" 
                                   name="lastname" 
                                   id="lastname" 
                                   value="{{ old('lastname', $student->lastname) }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('lastname') border-red-500 @enderror"
                                   required>
                            @error('lastname')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nachteilsausgleich -->
                        <div class="mb-6">
                            <label for="disability_compensation_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Nachteilsausgleich (in %)
                            </label>
                            <input type="number" 
                                   name="disability_compensation_percent" 
                                   id="disability_compensation_percent" 
                                   value="{{ old('disability_compensation_percent', $student->disability_compensation_percent) }}"
                                   min="0"
                                   max="100"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('disability_compensation_percent') border-red-500 @enderror"
                                   placeholder="z.B. 10, 20, 30">
                            @error('disability_compensation_percent')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Optional: Zeitverlängerung in Prozent (z.B. 20 für 20% mehr Zeit)
                            </p>
                        </div>

                        <!-- Nachteilsausgleich Notizen -->
                        <div class="mb-6">
                            <label for="disability_compensation_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Notizen zum Nachteilsausgleich
                            </label>
                            <textarea name="disability_compensation_notes" 
                                      id="disability_compensation_notes" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('disability_compensation_notes') border-red-500 @enderror"
                                      placeholder="Optionale Hinweise, z.B. Art der Beeinträchtigung, spezielle Anforderungen">{{ old('disability_compensation_notes', $student->disability_compensation_notes) }}</textarea>
                            @error('disability_compensation_notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Abitur bestanden -->
                        <div class="mb-6">
                            <label class="flex items-center gap-3 cursor-pointer select-none">
                                <input type="hidden" name="bestanden" value="0">
                                <input type="checkbox"
                                       name="bestanden"
                                       value="1"
                                       {{ old('bestanden', $student->bestanden) ? 'checked' : '' }}
                                       class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Abitur bestanden
                                </span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Standardmäßig aktiv. Wird das Häkchen entfernt, erscheint der Schüler nicht in der
                                Abiturfeier-Schülerliste und der Preisvergabe.
                            </p>
                            @error('bestanden')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

                        @php
                            // Prüfungen nach Typ gruppieren
                            $oralExams = $student->exams->where('exam_type', 'oral')->values();
                            $writtenExams = $student->exams->where('exam_type', 'written')->values();
                            
                            $k1 = $oralExams->get(0);
                            $k2 = $oralExams->get(1);
                            
                            $s1 = $writtenExams->get(0);
                            $s2 = $writtenExams->get(1);
                            $s3 = $writtenExams->get(2);
                        @endphp

                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Schriftliche Prüfungen
                        </h3>

                        <!-- S1 Kurs -->
                        <div class="mb-6">
                            <label for="s1_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                S1 Prüfungsfach (1. Schriftliche) *
                            </label>
                            <select name="s1_course_id" 
                                    id="s1_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('s1_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" 
                                            {{ old('s1_course_id', $s1?->course_id) == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }})
                                        @if($course->level === 'erhoeht') - LK @else - GK @endif
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('s1_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if($s1 && $s1->written_exam_year_date_id)
                                <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
                                    ⚠️ Hinweis: Diese Prüfung ist bereits einem Termin zugeordnet. Bei Änderung wird die Zuordnung entfernt.
                                </p>
                            @endif
                        </div>

                        <!-- S2 Kurs -->
                        <div class="mb-6">
                            <label for="s2_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                S2 Prüfungsfach (2. Schriftliche) *
                            </label>
                            <select name="s2_course_id" 
                                    id="s2_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('s2_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" 
                                            {{ old('s2_course_id', $s2?->course_id) == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }})
                                        @if($course->level === 'erhoeht') - LK @else - GK @endif
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('s2_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if($s2 && $s2->written_exam_year_date_id)
                                <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
                                    ⚠️ Hinweis: Diese Prüfung ist bereits einem Termin zugeordnet. Bei Änderung wird die Zuordnung entfernt.
                                </p>
                            @endif
                        </div>

                        <!-- S3 Kurs -->
                        <div class="mb-6">
                            <label for="s3_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                S3 Prüfungsfach (3. Schriftliche) *
                            </label>
                            <select name="s3_course_id" 
                                    id="s3_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('s3_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" 
                                            {{ old('s3_course_id', $s3?->course_id) == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }})
                                        @if($course->level === 'erhoeht') - LK @else - GK @endif
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('s3_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if($s3 && $s3->written_exam_year_date_id)
                                <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
                                    ⚠️ Hinweis: Diese Prüfung ist bereits einem Termin zugeordnet. Bei Änderung wird die Zuordnung entfernt.
                                </p>
                            @endif
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Mündliche Prüfungen (Kolloquien)
                        </h3>

                        <!-- K1 Kurs -->
                        <div class="mb-6">
                            <label for="k1_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                K1 Prüfungsfach *
                            </label>
                            <select name="k1_course_id" 
                                    id="k1_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('k1_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" 
                                            {{ old('k1_course_id', $k1?->course_id) == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }}) 
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('k1_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if($k1 && $k1->assignment)
                                <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
                                    ⚠️ Hinweis: Diese Prüfung ist bereits geplant. Bei Änderung wird die Planung entfernt.
                                </p>
                            @endif
                        </div>

                        <!-- K2 Kurs -->
                        <div class="mb-6">
                            <label for="k2_course_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                K2 Prüfungsfach *
                            </label>
                            <select name="k2_course_id" 
                                    id="k2_course_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white @error('k2_course_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Bitte wählen --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" 
                                            {{ old('k2_course_id', $k2?->course_id) == $course->id ? 'selected' : '' }}>
                                        {{ $course->subject->name ?? 'Unbekannt' }} 
                                        ({{ $course->course_code }}) 
                                        - Prüfer: {{ $course->teacher->shortcode ?? 'n/a' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('k2_course_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if($k2 && $k2->assignment)
                                <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
                                    ⚠️ Hinweis: Diese Prüfung ist bereits geplant. Bei Änderung wird die Planung entfernt.
                                </p>
                            @endif
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

                        <!-- Buttons -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('students.index') }}" 
                               class="px-6 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                                Abbrechen
                            </a>
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Änderungen speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Gesperrte Tage -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                        Gesperrte Tage (Kolloquium)
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                        Angeklickte Tage werden für den Schüler gesperrt. Die Auto-Planung überspringt diese Tage. Bei manueller Zuweisung erscheint eine Warnung.
                    </p>

                    @php
                        $dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
                        $blockedMap = $student->blockedDays->keyBy(fn($b) => $b->date->format('Y-m-d'));

                        $weeks = [];
                        foreach ([1, 2] as $w) {
                            $start = $w === 1 ? $examPeriod->week_1_start : $examPeriod->week_2_start;
                            $end   = $w === 1 ? $examPeriod->week_1_end   : $examPeriod->week_2_end;
                            if (!$start || !$end) continue;
                            $days = [];
                            $cur = $start->copy();
                            while ($cur->lte($end)) {
                                if ($cur->isWeekday()) {
                                    $days[] = $cur->copy();
                                }
                                $cur->addDay();
                            }
                            $weeks[$w] = $days;
                        }
                    @endphp

                    @foreach($weeks as $weekNum => $days)
                        <div class="mb-5">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                Woche {{ $weekNum }}
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($days as $day)
                                    @php
                                        $ymd     = $day->format('Y-m-d');
                                        $blocked = $blockedMap->get($ymd);
                                        $label   = $dayNames[$day->dayOfWeekIso - 1] . ' ' . $day->format('d.m.');
                                        $title   = $blocked ? ('Gesperrt' . ($blocked->reason ? ': ' . $blocked->reason : '') . ' – Klicken zum Entsperren') : 'Klicken zum Sperren';
                                    @endphp
                                    <button type="button"
                                            id="day-btn-{{ $ymd }}"
                                            data-date="{{ $ymd }}"
                                            data-blocked-id="{{ $blocked?->id ?? '' }}"
                                            data-reason="{{ $blocked?->reason ?? '' }}"
                                            title="{{ $title }}"
                                            data-toggle-blocked-day
                                            class="px-3 py-2 rounded-md text-sm font-medium transition-colors border-0 cursor-pointer"
                                            style="{{ $blocked ? 'background:#f59e0b;color:#fff;' : 'background:#f3f4f6;color:#374151;' }}">
                                        {{ $label }}
                                        @if($blocked)
                                            <span class="ml-1 opacity-75 text-xs">✕</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @if(empty($weeks))
                        <p class="text-sm text-gray-500 italic">Keine Kolloquiumswochen definiert.</p>
                    @endif

                    <div id="blocked-day-feedback" class="mt-3 text-sm hidden"></div>
                </div>
            </div>

            <script @cspNonce>
            const blockedDayStoreUrl  = '{{ route("students.blocked-days.store", $student) }}';
            const blockedDayDeleteUrl = '{{ route("students.blocked-days.destroy", ["student" => $student->id, "blockedDay" => "__ID__"]) }}';
            const csrfToken = '{{ csrf_token() }}';

            // Frueher onclick am Knopf (Modul 7.2).
            document.addEventListener('click', (ereignis) => {
                const knopf = ereignis.target.closest('[data-toggle-blocked-day]');

                if (knopf) {
                    toggleBlockedDay(knopf);
                }
            });

            function toggleBlockedDay(btn) {
                const date      = btn.dataset.date;
                const blockedId = btn.dataset.blockedId;

                if (blockedId) {
                    // Entsperren
                    const url = blockedDayDeleteUrl.replace('__ID__', blockedId);
                    fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            btn.dataset.blockedId = '';
                            btn.dataset.reason    = '';
                            btn.title             = 'Klicken zum Sperren';
                            btn.style.background  = '#f3f4f6';
                            btn.style.color       = '#374151';
                            // Entferne ✕-Span
                            btn.querySelector('span')?.remove();
                            showFeedback('Tag entsperrt.', 'green');
                        }
                    });
                } else {
                    // Sperren – optionalen Grund abfragen
                    const reason = window.prompt('Grund für die Sperrung (optional, z.B. "Beerdigung"):') ?? null;
                    if (reason === null) return; // Abbrechen

                    fetch(blockedDayStoreUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ date, reason: reason.trim() || null })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            btn.dataset.blockedId = data.blocked_day.id;
                            btn.dataset.reason    = data.blocked_day.reason ?? '';
                            const reasonText      = data.blocked_day.reason ? ': ' + data.blocked_day.reason : '';
                            btn.title             = 'Gesperrt' + reasonText + ' – Klicken zum Entsperren';
                            btn.style.background  = '#f59e0b';
                            btn.style.color       = '#fff';
                            // ✕-Span hinzufügen
                            const span = document.createElement('span');
                            span.className = 'ml-1 opacity-75 text-xs';
                            span.textContent = '✕';
                            btn.appendChild(span);
                            showFeedback('Tag gesperrt.', 'amber');
                        } else {
                            showFeedback(data.message || 'Fehler.', 'red');
                        }
                    })
                    .catch(() => showFeedback('Netzwerkfehler.', 'red'));
                }
            }

            function showFeedback(msg, color) {
                const el = document.getElementById('blocked-day-feedback');
                const colors = { green: 'text-green-600', amber: 'text-amber-600', red: 'text-red-600' };
                el.className = 'mt-3 text-sm ' + (colors[color] ?? 'text-gray-600');
                el.textContent = msg;
                el.classList.remove('hidden');
                setTimeout(() => el.classList.add('hidden'), 2500);
            }
            </script>

            <!-- Löschbereich -->
            <div class="bg-red-50 dark:bg-red-900/20 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-red-800 dark:text-red-300 mb-2">
                        Gefahrenzone
                    </h3>
                    <p class="text-sm text-red-700 dark:text-red-400 mb-4">
                        Das Löschen des Schülers kann nicht rückgängig gemacht werden. Alle zugehörigen Prüfungen und Planungen werden ebenfalls gelöscht.
                    </p>
                    <form method="POST" action="{{ route('students.destroy', $student) }}" 
                          data-confirm="Sind Sie sicher, dass Sie {{ $student->full_name }} löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden!">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Schüler löschen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
