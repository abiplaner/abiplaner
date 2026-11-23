<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Stundenplan: {{ $teacher->shortcode }}
                <span class="text-gray-500 font-normal text-base ml-2">
                    {{ $teacher->title ? $teacher->title . ' ' : '' }}{{ $teacher->firstname }} {{ $teacher->lastname }}
                </span>
            </h2>
            <a href="{{ route('teachers.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                ← Zurück zur Lehrkräfteliste
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Schuljahr-Auswahl --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4">
                    <form method="GET" action="{{ route('teachers.timetable', $teacher) }}" class="flex items-center gap-4">
                        <label class="text-sm font-medium text-gray-700">Schuljahr:</label>
                        <select name="school_year" data-submit-on-change
                                class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            @forelse($availableYears as $year)
                                <option value="{{ $year }}" {{ $schoolYear === $year ? 'selected' : '' }}>{{ $year }}</option>
                            @empty
                                <option value="{{ $schoolYear }}" selected>{{ $schoolYear }}</option>
                            @endforelse
                        </select>
                        @if($availableYears->isEmpty())
                            <span class="text-sm text-gray-400">(Keine Stundenplandaten importiert)</span>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Stundenplan-Tabelle --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($entries->isEmpty())
                        <div class="text-center py-12 text-gray-500">
                            <svg class="mx-auto mb-4 w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="font-medium">Kein Stundenplan für {{ $teacher->shortcode }} im Schuljahr {{ $schoolYear }} gefunden.</p>
                            <p class="text-sm mt-1">
                                Bitte zuerst den
                                <a href="{{ route('timetable.upload') }}" class="text-blue-600 hover:underline">Stundenplan importieren</a>.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16 border border-gray-200">Std.</th>
                                        @foreach($days as $dayNum => $dayName)
                                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-200">
                                                {{ $dayName }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    @for($period = 1; $period <= $maxPeriod; $period++)
                                        @php
                                            $hasAnyEntry = false;
                                            foreach ($days as $dayNum => $dayName) {
                                                if ($entries->get($dayNum, collect())->where('period', $period)->isNotEmpty()) {
                                                    $hasAnyEntry = true; break;
                                                }
                                            }
                                        @endphp
                                        <tr class="{{ $hasAnyEntry ? '' : 'bg-gray-50' }} hover:bg-blue-50 transition-colors">
                                            <td class="px-3 py-2 text-sm font-semibold text-gray-500 text-center border border-gray-200">{{ $period }}</td>
                                            @foreach($days as $dayNum => $dayName)
                                                @php
                                                    $dayEntries = $entries->get($dayNum, collect())->where('period', $period)->values();
                                                @endphp
                                                <td class="px-2 py-2 text-sm align-top border border-gray-200 min-w-[100px]">
                                                    @if($dayEntries->isNotEmpty())
                                                        @foreach($dayEntries as $entry)
                                                            @php
                                                                $isOberstufe = in_array($entry->class_name, ['Q12', 'Q13'], true);
                                                                $isBereitschaft = $entry->course_code === 'BER';
                                                                $subjectLabel = $entry->subject;
                                                                $subjectFromCourse = null;
                                                                if ($entry->course_code && preg_match('/^([A-Za-zÄÖÜäöüß]+)/u', $entry->course_code, $matches)) {
                                                                    $subjectFromCourse = $matches[1];
                                                                }
                                                                $subjectCode = $subjectLabel ? ($subjectCodeMap[mb_strtolower($subjectLabel)] ?? null) : null;
                                                                $subjectBase = $subjectCode ?: $subjectFromCourse ?: $subjectLabel;
                                                                if ($isBereitschaft) {
                                                                    $primaryLabel = 'BER'; $secondaryLabel = null;
                                                                    $bgClass = 'bg-yellow-50 border-yellow-200'; $textClass = 'text-yellow-800'; $subTextClass = 'text-yellow-700';
                                                                } elseif ($isOberstufe) {
                                                                    $primaryLabel = $subjectBase ? ucfirst(mb_strtolower($subjectBase)) : $entry->course_code;
                                                                    $secondaryLabel = $entry->course_code ?: null;
                                                                    $bgClass = 'bg-blue-50 border-blue-200'; $textClass = 'text-blue-900'; $subTextClass = 'text-blue-600';
                                                                } else {
                                                                    $primaryLabel = $subjectBase ? ucfirst(mb_strtolower($subjectBase)) : ($entry->course_code ?: $entry->class_name);
                                                                    $secondaryLabel = $entry->class_name ?: null;
                                                                    $bgClass = 'bg-green-50 border-green-200'; $textClass = 'text-green-900'; $subTextClass = 'text-green-600';
                                                                }
                                                            @endphp
                                                            <div class="mb-1 last:mb-0 px-2 py-1 rounded border {{ $bgClass }} text-xs">
                                                                <div class="font-semibold {{ $textClass }}">{{ $primaryLabel }}</div>
                                                                <div class="{{ $subTextClass }} leading-tight">
                                                                    @if($secondaryLabel && $secondaryLabel !== $primaryLabel){{ $secondaryLabel }}@endif
                                                                    @if($entry->room)<span class="text-gray-500"> {{ $entry->room }}</span>@endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="text-gray-300 text-center text-xs">—</div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-600">
                            <div class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded bg-blue-100 border border-blue-200"></span>Oberstufe (Q12/Q13)</div>
                            <div class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded bg-green-100 border border-green-200"></span>Andere Klassen</div>
                            <div class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded bg-yellow-100 border border-yellow-200"></span>Bereitschaft</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- Abwesenheiten --}}
            {{-- ============================================================ --}}
            <div id="abwesenheiten" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6" x-data="unavailabilityPicker()">
                <div class="p-6">

                    <h3 class="text-lg font-semibold text-gray-800 mb-1 flex items-center gap-2">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        Abwesenheiten (Kolloquiumsplanung)
                    </h3>

                    @if($activeExamPeriod)
                        <p class="text-sm text-gray-500 mb-5">
                            Prüfungszeitraum:
                            <span class="font-medium text-gray-700">{{ $activeExamPeriod->name }} ({{ $activeExamPeriod->year }})</span>
                            @if($activeExamPeriod->week_1_start)
                                &nbsp;·&nbsp;
                                Woche 1: {{ $activeExamPeriod->week_1_start->format('d.m.') }}–{{ $activeExamPeriod->week_1_end->format('d.m.') }}
                            @endif
                            @if($activeExamPeriod->week_2_start)
                                &nbsp;·&nbsp;
                                Woche 2: {{ $activeExamPeriod->week_2_start->format('d.m.') }}–{{ $activeExamPeriod->week_2_end->format('d.m.Y') }}
                            @endif
                        </p>
                    @endif

                    @unlessarchive
                    @can('update', $teacher)

                        {{-- Tages-Chips --}}
                        @if($kolloquiumDays->isEmpty())
                            <p class="text-sm text-gray-400 italic mb-4">
                                Keine Kolloquiumstage gefunden. Bitte zuerst einen Prüfungszeitraum mit Datumsgrenzen anlegen.
                            </p>
                        @else
                            @php
                                $blockedDates = $unavailabilities
                                    ->when($activeExamPeriod, fn($c) => $c->where('exam_period_id', $activeExamPeriod->id))
                                    ->pluck('date')
                                    ->map(fn($d) => $d->format('Y-m-d'))
                                    ->flip()
                                    ->toArray();
                            @endphp

                            <div class="mb-4 space-y-3">
                                @php $lastWeek = null; @endphp
                                @foreach($kolloquiumDays as $day)
                                    @php
                                        $iso = $day->format('Y-m-d');
                                        $weekNum = $day->weekOfYear;
                                        $isBlocked = isset($blockedDates[$iso]);
                                    @endphp

                                    @if($lastWeek !== $weekNum)
                                        @if($lastWeek !== null)<div class="border-t border-gray-100 pt-1"></div>@endif
                                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                            Woche {{ $day->format('d.m.') }} – {{ $day->copy()->endOfWeek()->subDays(2)->format('d.m.Y') }}
                                        </p>
                                        @php $lastWeek = $weekNum; @endphp
                                    @endif

                                    <button type="button"
                                            @click="{{ $isBlocked ? '' : "toggle('{$iso}')" }}"
                                            :class="isSelected('{{ $iso }}')
                                                ? 'ring-2 ring-orange-500 bg-orange-500 text-white border-orange-500'
                                                : '{{ $isBlocked
                                                    ? 'bg-red-50 text-red-400 border-red-200 cursor-not-allowed'
                                                    : 'bg-white text-gray-700 border-gray-300 hover:border-orange-400 hover:bg-orange-50 cursor-pointer' }}'"
                                            {{ $isBlocked ? 'disabled' : '' }}
                                            title="{{ $isBlocked ? 'Bereits als abwesend eingetragen' : 'Klicken zum Auswählen' }}"
                                            class="inline-flex flex-col items-center px-3 py-2 rounded-lg border text-xs font-medium transition-all w-16 mr-2 mb-2 focus:outline-none select-none">
                                        <span class="text-[10px] uppercase tracking-wide opacity-60">{{ $day->translatedFormat('D') }}</span>
                                        <span class="text-sm font-bold leading-snug">{{ $day->format('d') }}</span>
                                        <span class="text-[10px] opacity-50">{{ $day->translatedFormat('M') }}</span>
                                        @if($isBlocked)
                                            <svg class="w-3 h-3 mt-0.5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>

                            {{-- Panel erscheint sobald Tage ausgewählt --}}
                            <div x-show="selected.length > 0" x-cloak
                                 class="bg-orange-50 border border-orange-200 rounded-lg p-4 space-y-3 mb-4">
                                <p class="text-sm font-medium text-orange-800">
                                    <span x-text="selected.length"></span> Tag(e) ausgewählt – bitte Grund eintragen und speichern:
                                </p>
                                <div class="flex gap-3 items-end">
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Grund (optional)</label>
                                        <input type="text" x-model="reason" maxlength="255"
                                               placeholder="z. B. Dienstreise, Fortbildung, …"
                                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-orange-500 focus:border-orange-500">
                                    </div>
                                    <button type="button" @click="submitAll()"
                                            :disabled="saving"
                                            :class="saving ? 'opacity-60 cursor-not-allowed' : ''"
                                            class="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 whitespace-nowrap">
                                        <span x-show="!saving">Speichern</span>
                                        <span x-show="saving" x-cloak>Wird gespeichert …</span>
                                    </button>
                                    <button type="button" @click="selected = []"
                                            class="px-3 py-2 text-gray-500 hover:text-gray-700 text-sm">
                                        Abbrechen
                                    </button>
                                </div>
                            </div>

                            {{-- Hidden-Formular für JS-Submit --}}
                            @if($activeExamPeriod)
                                <form id="unavailability-form" method="POST"
                                      action="{{ route('teachers.unavailability', $teacher) }}" class="hidden">
                                    @csrf
                                    <input type="hidden" name="exam_period_id" value="{{ $activeExamPeriod->id }}">
                                    <input type="hidden" name="date"   id="unavailability-date">
                                    <input type="hidden" name="all_day" value="1">
                                    <input type="hidden" name="reason"  id="unavailability-reason">
                                </form>
                            @endif
                        @endif

                        {{-- Erweitertes Formular (anderer Zeitraum / Uhrzeit) --}}
                        <details class="group mt-2">
                            <summary class="cursor-pointer text-xs text-gray-400 hover:text-gray-600 select-none list-none flex items-center gap-1">
                                <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                Anderen Prüfungszeitraum / Uhrzeit eintragen …
                            </summary>
                            <div class="mt-3 bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <form method="POST" action="{{ route('teachers.unavailability', $teacher) }}">
                                    @csrf
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Prüfungszeitraum *</label>
                                            <select name="exam_period_id" required class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                @foreach($examPeriods as $ep)
                                                    <option value="{{ $ep->id }}" {{ $activeExamPeriod?->id === $ep->id ? 'selected' : '' }}>
                                                        {{ $ep->name }} ({{ $ep->year }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Datum *</label>
                                            <input type="date" name="date" required value="{{ old('date') }}"
                                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        </div>
                                        <div class="col-span-2" x-data="{ allDay: true }">
                                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mb-2">
                                                <input type="checkbox" name="all_day" value="1" x-model="allDay"
                                                       class="rounded border-gray-300 text-blue-600"> Ganztägig
                                            </label>
                                            <div x-show="!allDay" x-cloak class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Von</label>
                                                    <input type="time" name="time_from" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Bis</label>
                                                    <input type="time" name="time_to" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Grund (optional)</label>
                                            <input type="text" name="reason" maxlength="255" placeholder="z. B. Dienstreise …"
                                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        </div>
                                        <div class="col-span-2">
                                            <button type="submit" class="w-full px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                                                Speichern
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </details>

                    @endcan
                    @endarchive

                    {{-- Liste bestehender Abwesenheiten --}}
                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                            Eingetragene Abwesenheiten
                            @if($unavailabilities->isNotEmpty())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                    {{ $unavailabilities->count() }}
                                </span>
                            @endif
                        </h4>
                        @if($unavailabilities->isEmpty())
                            <p class="text-sm text-gray-400 italic">Noch keine Abwesenheiten eingetragen.</p>
                        @else
                            <ul class="flex flex-wrap gap-2">
                                @foreach($unavailabilities as $u)
                                    <li class="flex items-center gap-3 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2 text-sm">
                                        <div>
                                            <span class="font-medium text-gray-800">{{ $u->date->translatedFormat('D, d.m.Y') }}</span>
                                            @if(!$u->all_day && $u->time_from)
                                                <span class="text-gray-500 text-xs ml-1">
                                                    {{ substr($u->time_from, 0, 5) }}{{ $u->time_to ? ' – '.substr($u->time_to,0,5) : '' }}
                                                </span>
                                            @else
                                                <span class="text-xs text-orange-600 ml-1">ganztägig</span>
                                            @endif
                                            <div class="text-xs text-gray-400">
                                                {{ $u->examPeriod?->name ?? '—' }}{{ $u->reason ? ' · '.$u->reason : '' }}
                                            </div>
                                        </div>
                                        @unlessarchive
                                        @can('update', $teacher)
                                            <form method="POST"
                                                  action="{{ route('teachers.unavailability.destroy', [$teacher, $u]) }}"
                                                  data-confirm="Abwesenheit löschen?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Löschen" class="text-red-400 hover:text-red-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                        @endarchive
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Zurück-Link --}}
            <div class="mt-4">
                <a href="{{ route('teachers.index') }}"
                   class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Zurück zur Übersicht
                </a>
            </div>
        </div>
    </div>

    <script @cspNonce>
    function unavailabilityPicker() {
        return {
            selected: [],
            reason: '',
            saving: false,

            toggle(date) {
                const idx = this.selected.indexOf(date);
                if (idx === -1) this.selected.push(date);
                else this.selected.splice(idx, 1);
            },

            isSelected(date) {
                return this.selected.includes(date);
            },

            async submitAll() {
                if (this.selected.length === 0 || this.saving) return;
                this.saving = true;

                const form   = document.getElementById('unavailability-form');
                const token  = form.querySelector('[name=_token]').value;
                const epId   = form.querySelector('[name=exam_period_id]').value;
                const action = form.action;
                const dates  = [...this.selected];
                const reason = this.reason;

                let hasError = false;

                // Alle Tage sequenziell speichern (await = warten bis jeder fertig ist)
                for (const date of dates) {
                    const fd = new FormData();
                    fd.append('_token',          token);
                    fd.append('exam_period_id',  epId);
                    fd.append('date',            date);
                    fd.append('all_day',         '1');
                    fd.append('reason',          reason);

                    try {
                        const response = await fetch(action, { method: 'POST', body: fd, redirect: 'follow' });
                        // Bei Fehler (4xx / 5xx) abbrechen
                        if (!response.ok && response.status !== 302) {
                            console.error('Fehler beim Speichern von ' + date + ':', response.status, response.statusText);
                            hasError = true;
                            break;
                        }
                    } catch (e) {
                        console.error('Netzwerkfehler beim Speichern von ' + date + ':', e);
                        hasError = true;
                        break;
                    }
                }

                if (hasError) {
                    alert('Beim Speichern ist ein Fehler aufgetreten. Bitte Seite neu laden und erneut versuchen.');
                    this.saving = false;
                    return;
                }

                // Seite neu laden und direkt zum Abschnitt scrollen
                window.location.href = window.location.pathname + '?school_year={{ $schoolYear }}#abwesenheiten';
            }
        }
    }

    // Nach Reload: Anchor-Scroll sanft animieren & Flash-Meldung hervorheben
    document.addEventListener('DOMContentLoaded', () => {
        if (window.location.hash === '#abwesenheiten') {
            const el = document.getElementById('abwesenheiten');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                el.classList.add('ring-2', 'ring-orange-300');
                setTimeout(() => el.classList.remove('ring-2', 'ring-orange-300'), 2000);
            }
        }
    });
    </script>
</x-app-layout>
