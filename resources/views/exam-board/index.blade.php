<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Abiturprüfungsausschuss</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $examPeriod->name }} — Sitzungen und Niederschriften
                </p>
            </div>
        </div>
    </x-slot>

    @php
        // Vorbelegung für eine neue Sitzung: Nummer hochzählen, Teilnehmerkreis
        // und Ort der letzten Sitzung übernehmen — der Ausschuss bleibt über
        // die Sitzungen hinweg derselbe.
        $blank = [
            'id'                   => null,
            'number'               => ($last->number ?? 0) + 1,
            'date'                 => now()->format('Y-m-d'),
            'start_time'           => $last->start_time ?? '',
            'end_time'             => $last->end_time ?? '',
            'location'             => $last->location ?? '',
            'chair_teacher_id'     => (string) ($last->chair_teacher_id ?? ''),
            'secretary_teacher_id' => (string) ($last->secretary_teacher_id ?? ''),
            'attendee_ids'         => $last !== null ? $last->attendees->pluck('teacher_id')->filter()->values()->all() : [],
            'guests'               => $last !== null ? $last->attendees->whereNull('teacher_id')->pluck('name_override')->values()->all() : [],
            'agenda'               => ['', ''],
            'attachments'          => [''],
            'notes'                => '',
        ];

        $meetingData = $meetings->mapWithKeys(fn ($m) => [$m->id => [
            'id'                   => $m->id,
            'number'               => $m->number,
            'date'                 => $m->date->format('Y-m-d'),
            'start_time'           => $m->start_time ?? '',
            'end_time'             => $m->end_time ?? '',
            'location'             => $m->location ?? '',
            'chair_teacher_id'     => (string) ($m->chair_teacher_id ?? ''),
            'secretary_teacher_id' => (string) ($m->secretary_teacher_id ?? ''),
            'attendee_ids'         => $m->attendees->pluck('teacher_id')->filter()->values()->all(),
            'guests'               => $m->attendees->whereNull('teacher_id')->pluck('name_override')->values()->all(),
            'agenda'               => count($m->agenda ?? []) > 0 ? $m->agenda : [''],
            'attachments'          => count($m->attachments ?? []) > 0 ? $m->attachments : [''],
            'notes'                => $m->notes ?? '',
        ]])->all();
    @endphp

    <div class="py-6" x-data="examBoard({{ json_encode($blank) }}, {{ json_encode($meetingData) }})">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            @if(session('success'))
                <div class="p-3 bg-green-50 border border-green-300 rounded text-green-800 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="p-3 bg-red-50 border border-red-300 rounded text-red-800 text-sm">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="p-3 bg-red-50 border border-red-300 rounded text-red-800 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- ── Ergebnisvorschau ───────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 text-sm">Stand nach den mündlichen Zusatzprüfungen</h3>
                    <span class="text-xs text-gray-400">
                        {{ $results['counts']['students'] }} Prüflinge ·
                        {{ $results['counts']['pruefungen'] }} Zusatzprüfungen
                    </span>
                </div>
                <div class="p-5">
                    @if($results['counts']['students'] === 0)
                        <p class="text-sm text-gray-400">Für diesen Jahrgang sind noch keine Zusatzprüfungen erfasst.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            @foreach([
                                ['passed',      'Bestanden',                    'text-emerald-700', 'bg-emerald-50'],
                                ['failed',      'Nicht bestanden',              'text-red-700',     'bg-red-50'],
                                ['improved',    'Verbesserung — Ziel erreicht', 'text-blue-700',    'bg-blue-50'],
                                ['notImproved', 'Verbesserung — Ziel verfehlt', 'text-amber-700',   'bg-amber-50'],
                                ['incomplete',  'Ergebnis unvollständig',       'text-gray-700',    'bg-gray-50'],
                                ['absent',      'Nicht angetreten',             'text-gray-500',    'bg-gray-50'],
                            ] as [$key, $label, $textClass, $bgClass])
                                @if(count($results[$key]) > 0)
                                <div class="{{ $bgClass }} rounded-lg p-3">
                                    <div class="font-medium {{ $textClass }} mb-1">
                                        {{ $label }} ({{ count($results[$key]) }})
                                    </div>
                                    <div class="text-gray-600 leading-relaxed">{{ implode(', ', $results[$key]) }}</div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                        @if(count($results['incomplete']) > 0)
                            <p class="mt-4 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2.5">
                                Für die als unvollständig gelisteten Prüflinge fehlt noch die mündliche Note.
                                Solange sie fehlt, kann das Protokoll keine Aussage über das Bestehen treffen —
                                im Word-Dokument steht an der Stelle ein Hinweis in eckigen Klammern.
                            </p>
                        @endif
                        <p class="mt-3 text-xs text-gray-400">
                            Der Gesamtschnitt des Jahrgangs wird nicht berechnet: Dafür wäre die vollständige
                            Gesamtqualifikation nötig, die Abiplaner nicht führt. Im Protokoll bleibt die Zahl frei.
                        </p>
                    @endif
                </div>
            </div>

            {{-- ── Sitzungen ──────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 text-sm">Sitzungen</h3>
                    <button type="button" @click="newMeeting()"
                        class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded">
                        Neue Sitzung
                    </button>
                </div>

                @if($meetings->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-400 text-center">Noch keine Sitzung erfasst.</p>
                @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Nr.</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Datum</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Zeit</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Ort</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Vorsitz</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Tagesordnung</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($meetings as $m)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $m->number }}.</td>
                            <td class="px-4 py-2.5 text-gray-700">{{ $m->date->format('d.m.Y') }}</td>
                            <td class="px-4 py-2.5 text-gray-600 font-mono text-xs">
                                {{ $m->start_time ?? '–' }}@if($m->end_time)–{{ $m->end_time }}@endif
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $m->location ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-gray-600">
                                {{ $m->chair !== null ? trim($m->chair->genderedTitleShort() . $m->chair->lastname) : '–' }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">
                                {{ implode(' · ', $m->agenda ?? []) ?: '–' }}
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('exam-board.protokoll', $m) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded">
                                    Protokoll (Word)
                                </a>
                                <button type="button" @click="edit({{ $m->id }})"
                                    class="ml-1 px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-300">
                                    Bearbeiten
                                </button>
                                <form method="POST" action="{{ route('exam-board.destroy', $m) }}" class="inline"
                                      data-confirm="Sitzung {{ $m->number }} wirklich löschen?">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="ml-1 px-2.5 py-1 text-xs bg-white hover:bg-red-50 text-red-600 rounded border border-red-200">
                                        Löschen
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            {{-- ── Formular ───────────────────────────────────────────── --}}
            <div x-show="open" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <form method="POST" :action="form.id ? '{{ url('exam-board') }}/' + form.id : '{{ route('exam-board.store') }}'">
                    @csrf
                    <template x-if="form.id">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 text-sm">
                            <span x-text="form.id ? 'Sitzung bearbeiten' : 'Neue Sitzung'"></span>
                        </h3>
                        <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                    </div>

                    <div class="p-5 space-y-5">

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Sitzungsnummer</label>
                                <input type="number" name="number" x-model="form.number" min="1" max="99" required
                                       class="w-full py-1.5 px-2 text-sm border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
                                <input type="date" name="date" x-model="form.date" required
                                       class="w-full py-1.5 px-2 text-sm border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Beginn</label>
                                <input type="time" name="start_time" x-model="form.start_time"
                                       class="w-full py-1.5 px-2 text-sm border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ende</label>
                                <input type="time" name="end_time" x-model="form.end_time"
                                       class="w-full py-1.5 px-2 text-sm border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ort</label>
                                <input type="text" name="location" x-model="form.location" placeholder="z. B. A204"
                                       class="w-full py-1.5 px-2 text-sm border-gray-300 rounded">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Vorsitz</label>
                                <select name="chair_teacher_id" x-model="form.chair_teacher_id"
                                        class="w-full py-1.5 px-2 text-sm border-gray-300 rounded">
                                    <option value="">— nicht festgelegt —</option>
                                    @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ trim($t->genderedTitleShort() . $t->lastname) }}, {{ $t->firstname }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Schriftführung</label>
                                <select name="secretary_teacher_id" x-model="form.secretary_teacher_id"
                                        class="w-full py-1.5 px-2 text-sm border-gray-300 rounded">
                                    <option value="">— nicht festgelegt —</option>
                                    @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ trim($t->genderedTitleShort() . $t->lastname) }}, {{ $t->firstname }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Anwesend
                                <span class="font-normal text-gray-400">(Vorsitz und Schriftführung mit auswählen)</span>
                            </label>
                            <div class="max-h-52 overflow-y-auto border border-gray-200 rounded p-2 grid grid-cols-2 md:grid-cols-3 gap-1">
                                @foreach($teachers as $t)
                                <label class="flex items-center gap-2 px-1.5 py-1 rounded hover:bg-gray-50 cursor-pointer text-sm">
                                    <input type="checkbox" name="attendee_ids[]" value="{{ $t->id }}"
                                           :checked="form.attendee_ids.includes({{ $t->id }})"
                                           @change="toggleAttendee({{ $t->id }})"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-gray-700 truncate">{{ trim($t->genderedTitleShort() . $t->lastname) }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Weitere Anwesende
                                <span class="font-normal text-gray-400">(ohne Lehrer-Datensatz, z. B. Ministerialbeauftragte)</span>
                            </label>
                            <template x-for="(g, i) in form.guests" :key="'g' + i">
                                <div class="flex gap-2 mb-1.5">
                                    <input type="text" :name="'guests[' + i + ']'" x-model="form.guests[i]"
                                           class="flex-1 py-1.5 px-2 text-sm border-gray-300 rounded">
                                    <button type="button" @click="form.guests.splice(i, 1)"
                                        class="px-2 text-gray-400 hover:text-red-600">×</button>
                                </div>
                            </template>
                            <button type="button" @click="form.guests.push('')"
                                class="text-xs text-blue-600 hover:underline">+ Person</button>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tagesordnung</label>
                            <template x-for="(t, i) in form.agenda" :key="'a' + i">
                                <div class="flex gap-2 mb-1.5 items-center">
                                    <span class="text-xs text-gray-400 w-5 text-right" x-text="(i + 1) + '.'"></span>
                                    <input type="text" :name="'agenda[' + i + ']'" x-model="form.agenda[i]"
                                           class="flex-1 py-1.5 px-2 text-sm border-gray-300 rounded">
                                    <button type="button" @click="form.agenda.splice(i, 1)"
                                        class="px-2 text-gray-400 hover:text-red-600">×</button>
                                </div>
                            </template>
                            <button type="button" @click="form.agenda.push('')"
                                class="text-xs text-blue-600 hover:underline">+ Tagesordnungspunkt</button>
                            <p class="mt-1.5 text-xs text-gray-400">
                                Ein Punkt, der „Bestehen" oder „Feststellung" enthält, wird im Protokoll
                                mit den Namenslisten von oben vorformuliert. Alle anderen bleiben leer.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Anlagen</label>
                            <template x-for="(a, i) in form.attachments" :key="'t' + i">
                                <div class="flex gap-2 mb-1.5">
                                    <input type="text" :name="'attachments[' + i + ']'" x-model="form.attachments[i]"
                                           class="flex-1 py-1.5 px-2 text-sm border-gray-300 rounded">
                                    <button type="button" @click="form.attachments.splice(i, 1)"
                                        class="px-2 text-gray-400 hover:text-red-600">×</button>
                                </div>
                            </template>
                            <button type="button" @click="form.attachments.push('')"
                                class="text-xs text-blue-600 hover:underline">+ Anlage</button>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Notizen
                                <span class="font-normal text-gray-400">(erscheinen am Ende des Protokolls)</span>
                            </label>
                            <textarea name="notes" x-model="form.notes" rows="4"
                                      class="w-full py-1.5 px-2 text-sm border-gray-300 rounded"></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex justify-end gap-2">
                        <button type="button" @click="open = false"
                            class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded border border-gray-200">
                            Abbrechen
                        </button>
                        <button type="submit"
                            class="px-4 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    @push('scripts')
    <script @cspNonce>
    function examBoard(blank, meetings) {
        return {
            open: false,
            blank: blank,
            meetings: meetings,
            form: JSON.parse(JSON.stringify(blank)),

            newMeeting() {
                this.form = JSON.parse(JSON.stringify(this.blank));
                this.open = true;
            },

            edit(id) {
                this.form = JSON.parse(JSON.stringify(this.meetings[id]));
                this.open = true;
                this.$nextTick(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }));
            },

            toggleAttendee(id) {
                const idx = this.form.attendee_ids.indexOf(id);
                if (idx === -1) this.form.attendee_ids.push(id);
                else this.form.attendee_ids.splice(idx, 1);
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
