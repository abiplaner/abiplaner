{{--
    Mindestpunkte-Kalkulator nach § 62 GSO für einen externen Bewerber.

    Eigene Seite, weil der Knopf im Zusatzprüfungs-Modul an der
    Zusatzprüfungs-Liste hängt — wer keine Zusatzprüfung hat, steht dort nicht.
    § 62 gilt für externe Bewerber aber unabhängig davon: der zweite
    Prüfungsteil betrifft jeden von ihnen.

    Gerechnet wird hier nichts und gespeichert auch nichts Eigenes. Die Seite
    benutzt dieselben zwei Endpunkte wie das Modal:
      GET   zusatzpruefungen/pruefling/{p}/mindestpunkte-62   (fertiges Ergebnis)
      PATCH zusatzpruefungen/pruefling/{p}/exam-score          (eine Einzelnote)
    Die Anzeige selbst steckt im gemeinsamen Partial.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Mindestpunkte — {{ $externalApplicant->full_name }}
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                    externe Bewerbung · GSO § 62
                </span>
            </h2>
            <a href="{{ route('external-applicants.index') }}"
               class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(! $faecherwahlVollstaendig)
                <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700
                            rounded-lg p-4 text-sm text-amber-800 dark:text-amber-200">
                    <p class="font-semibold mb-1">Die Fächerwahl ist noch nicht vollständig.</p>
                    <p class="text-xs">
                        § 61 Abs. 1 S. 1: Gegenstand der Prüfung sind acht Prüfungsfächer, die die
                        Positionen 1 bis 8 genau einmal belegen. Solange das nicht steht, ist nach
                        § 62 nichts zu rechnen.
                    </p>
                    <a href="{{ route('external-applicants.subjects.edit', $externalApplicant) }}"
                       class="inline-block mt-2 text-xs font-medium underline">
                        Fächerwahl bearbeiten
                    </a>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 text-sm"
                 x-data="{
                     ...mpMixin(),
                     mindestpunkteModal: { show: true, studentId: '{{ $pruefling }}', paragraph: '62',
                                           exams: [], scores: {}, practicalGrades: {} },
                     computedData: null,
                     computedData62: null,
                     computeError: null,
                     computeLoading: false,
                     _computeTimer: null,
                     async init() {
                         await this.ladeFaecher();
                         this.mpRecompute();
                     },
                     /*
                      * Die Prüfungen des Bewerbers samt gespeicherter Noten —
                      * dieselbe Quelle, aus der sich auch das Modal bedient.
                      * Nötig, weil die Eingabefelder an student_exam_id hängen.
                      */
                     async ladeFaecher() {
                         const res = await fetch('{{ route('zusatzpruefungen.pruefling.all-exams', $pruefling) }}');
                         if (!res.ok) {
                             this.computeError = 'Die Prüfungen konnten nicht geladen werden (HTTP ' + res.status + ').';
                             return;
                         }
                         const daten = await res.json();
                         const faecher = daten.exams ?? daten ?? [];
                         /* saved_score trägt bei einer Zusatzprüfung die
                            schriftliche Note, sonst die Prüfungsnote — die
                            beiden Fälle gehen deshalb an verschiedene Stellen. */
                         this.mindestpunkteModal.exams = faecher.map(e => ({
                             id: e.id,
                             isZusatzpruefung: e.has_zp ?? false,
                             pruefungId: e.pruefung_id ?? null,
                             writtenPoints: e.has_zp ? (e.saved_score ?? null) : null,
                             targetPoints: e.target_points ?? null,
                         }));
                         const noten = {};
                         faecher.forEach(e => {
                             if (e.has_zp) return;
                             noten[e.id] = (e.saved_score !== null && e.saved_score !== undefined)
                                 ? e.saved_score : '';
                         });
                         this.mindestpunkteModal.scores = noten;
                     },
                     /* Nach jedem Speichern die Fächerliste mitziehen, damit
                        eine neu eingetragene Zusatzprüfungsnote nicht wieder
                        aus dem Formular verschwindet. */
                     async mpReloadModal() { await this.ladeFaecher(); this.mpRecompute(); },
                 }">

                <template x-if="computeLoading && !computedData62">
                    <p class="text-xs text-gray-400">Wird gerechnet …</p>
                </template>

                @include('zusatzpruefungen.partials.mindestpunkte-modal-62')

            </div>

            <p class="text-xs text-gray-400 dark:text-gray-500 px-1">
                Noten werden beim Verlassen des Feldes gespeichert. Mündliche Zusatzprüfungen
                werden weiterhin im Modul <a href="{{ route('zusatzpruefungen.index') }}"
                class="underline">Zusatzprüfungen</a> angelegt und geplant.
            </p>
        </div>
    </div>

    {{-- Vor Alpine: das Skript ist nicht deferred und laeuft daher waehrend
         des Parsens, mpMixin() steht damit bereit, wenn Alpine x-data liest. --}}
    <script src="{{ asset('js/mindestpunkte.js') }}?v={{ filemtime(public_path('js/mindestpunkte.js')) }}"></script>
</x-app-layout>
