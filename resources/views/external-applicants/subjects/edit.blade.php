<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Fächerwahl') }}: {{ $externalApplicant->full_name }} ({{ $externalApplicant->examPeriod->year }})
        </h2>
    </x-slot>

    {{--
        Fächerwahl nach § 61 BayGSO.

        Die Regeln stehen ausschliesslich in ExternalApplicantValidationService.
        Diese Maske führt keine eigene Prüfung, sondern schickt die aktuelle
        Wahl bei jeder Änderung an external-applicants.subjects.pruefen und
        zeigt an, was zurückkommt. Damit können Client und Server nicht
        auseinanderlaufen (Entscheidung Wilfried, 25./26.08.2026).

        Zwei Dinge macht die Maske unmöglich, statt sie nur zu melden, weil die
        Norm dort keine Wahl lässt: Mathematik ist das erste schriftliche Fach
        (Anlage 14), und das erhöhte Anforderungsniveau ergibt sich aus Deutsch,
        Mathematik und dem einen Leistungsfach (§ 61 Abs. 1 S. 5).
    --}}

    <div class="py-12"
         x-data="faecherwahl({
            wahl: {{ Js::from($wahl) }},
            katalog: {{ Js::from($katalogFuerMaske) }},
            leistungsfach: {{ Js::from($externalApplicant->leistungsfach_subject_id) }},
            zweiteFremdsprache: {{ Js::from($externalApplicant->zweite_fremdsprache_subject_id) }},
            istErsatzschueler: {{ Js::from($externalApplicant->istErsatzschueler()) }},
            pruefUrl: {{ Js::from(route('external-applicants.subjects.pruefen', $externalApplicant)) }},
         })"
         x-init="pruefen()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">


            @if(session('warning'))
                <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 rounded-r-lg">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">{{ session('warning') }}</p>
                </div>
            @endif

            {{-- Zugeklappt: die Regeln stehen unten ohnehin als Verstösse, wenn
                 sie verletzt sind. Wer sie nachschlagen will, klappt auf. --}}
            <details class="mb-4 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 px-4 py-2 rounded-r-lg">
                <summary class="text-sm font-medium text-blue-800 dark:text-blue-200 cursor-pointer">
                    Fächerwahl nach § 61 GSO — Kurzfassung
                </summary>
                <ul class="mt-2 text-sm text-blue-700 dark:text-blue-300 list-disc list-inside space-y-1">
                    <li><strong>Erster Prüfungsteil:</strong> vier schriftlich geprüfte Fächer. Mathematik ist Fach 1 (Anlage 14), das vierte Fach nur auf grundlegendem Niveau.</li>
                    <li><strong>Zweiter Prüfungsteil:</strong> vier weitere, nur mündlich geprüfte Fächer. Auf erhöhtem Niveau darf dort nur Fach 5 liegen.</li>
                    <li><strong>Pflicht:</strong> Deutsch, Geschichte, Mathematik, eine Naturwissenschaft, zwei Fremdsprachen.</li>
                    <li><strong>Erhöhtes Niveau:</strong> Deutsch und Mathematik immer, dazu das Leistungsfach.</li>
                </ul>
            </details>

            <form method="POST" action="{{ route('external-applicants.subjects.update', $externalApplicant) }}">
                @csrf
                @method('PUT')

                <div class="mb-6 p-4 rounded-lg border"
                     :class="istGueltig ? 'bg-green-50 dark:bg-green-900/20 border-green-400' : 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-400'">
                    <div class="flex items-start justify-between">
                        <h3 class="text-sm font-medium"
                            :class="istGueltig ? 'text-green-800 dark:text-green-200' : 'text-yellow-800 dark:text-yellow-200'">
                            <span x-text="istGueltig ? '✅ Die Fächerwahl entspricht § 61 GSO.' : '⚠️ Die Fächerwahl ist noch nicht regelkonform.'"></span>
                        </h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400" x-show="pruefungLaeuft">prüft …</span>
                    </div>

                    <ul class="mt-2 text-sm space-y-1 list-disc list-inside text-yellow-800 dark:text-yellow-200"
                        x-show="verstoesse.length > 0">
                        <template x-for="verstoss in verstoesse" :key="verstoss.id + verstoss.meldung">
                            {{-- Beide Spans ohne Zeilenumbruch dazwischen, sonst
                                 steht im Text ein Leerzeichen vor dem Doppelpunkt. --}}
                            <li><span class="font-medium" x-text="verstoss.norm + ':'"></span><span x-text="' ' + verstoss.meldung"></span></li>
                        </template>
                    </ul>
                </div>

                {{--
                    Hinweise, keine Verstöße: Fälle, die die Norm offenlässt.
                    Eigener Block und eigene Farbe, damit sie nicht wie ein
                    Fehler aussehen — die Wahl bleibt gültig, und die Kopfzeile
                    oben steht weiter auf Grün.
                --}}
                <div class="mb-6 p-4 rounded-lg border border-blue-300 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700"
                     x-show="hinweise.length > 0" x-cloak>
                    <h3 class="text-sm font-medium text-blue-900 dark:text-blue-200">
                        ℹ️ Zu entscheiden
                    </h3>
                    <ul class="mt-2 text-sm space-y-1 list-disc list-inside text-blue-900 dark:text-blue-200">
                        <template x-for="hinweis in hinweise" :key="hinweis.id + hinweis.meldung">
                            <li><span class="font-medium" x-text="hinweis.norm + ':'"></span><span x-text="' ' + hinweis.meldung"></span></li>
                        </template>
                    </ul>
                </div>

                <div class="mb-4 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-3">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <label class="w-40 shrink-0 text-sm text-gray-700 dark:text-gray-300" for="leistungsfach">
                            <span class="font-medium">Leistungsfach</span>
                        </label>

                        <div class="flex-1 min-w-[14rem]">
                            <select id="leistungsfach" name="leistungsfach_subject_id"
                                    x-model.number="leistungsfach" @change="gemerkt.leistungsfach = null; pruefen()"
                                    class="w-full py-1.5 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                <option value="">-- noch nicht festgelegt --</option>
                                {{-- Serverseitig gerendert statt per x-for: eine Option, die es
                                     beim Setzen von x-model noch nicht gibt, kann den
                                     gespeicherten Wert nicht tragen. Das Feld blieb leer, und
                                     das nächste Speichern löschte das Leistungsfach — schon das
                                     blosse Öffnen der Seite genügte. JavaScript blendet hier nur
                                     noch aus, was gerade nicht gewählt ist, wie bei den
                                     Prüferfeldern. --}}
                                @foreach ($katalogFuerMaske as $fachId => $fach)
                                    @continue(in_array($fach['code'], ['D', 'M'], true))
                                    <option value="{{ $fachId }}" @selected($externalApplicant->leistungsfach_subject_id === $fachId)>{{ $fach['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            neben Deutsch und Mathematik · § 61 Abs. 1 S. 5
                        </span>
                    </div>

                    {{-- § 61 Abs. 3 S. 7: die zweite Fremdsprache wird nur auf
                         dem Niveau einer spät beginnenden geprüft — mit § 48
                         Abs. 1 Nr. 2 also nur mündlich und nur grundlegend.
                         Welche der gewählten Sprachen die zweite ist, sagt der
                         Wortlaut nicht; der Prüfling benennt sie
                         (Entscheidung 32 vom 27.08.2026).

                         Serverseitig gerendert aus demselben Grund wie das
                         Leistungsfach: eine Option, die es beim Setzen von
                         x-model noch nicht gibt, kann den gespeicherten Wert
                         nicht tragen. --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <label class="w-40 shrink-0 text-sm text-gray-700 dark:text-gray-300" for="zweite-fremdsprache">
                            <span class="font-medium">Zweite Fremdsprache</span>
                        </label>

                        <div class="flex-1 min-w-[14rem]">
                            <select id="zweite-fremdsprache" name="zweite_fremdsprache_subject_id"
                                    x-model.number="zweiteFremdsprache" @change="gemerkt.zweiteFremdsprache = null; pruefen()"
                                    class="w-full py-1.5 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                <option value="">-- noch nicht festgelegt --</option>
                                @foreach ($katalogFuerMaske as $fachId => $fach)
                                    @continue(! $fach['fremdsprache'])
                                    <option value="{{ $fachId }}" @selected($externalApplicant->zweite_fremdsprache_subject_id === $fachId)>{{ $fach['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            nur mündlich, nur grundlegend · § 61 Abs. 3 S. 7
                        </span>
                    </div>
                </div>

                @foreach ([['part1_written', '📝 Erster Prüfungsteil — schriftlich', 0], ['part2_oral', '🗣️ Zweiter Prüfungsteil — mündlich', 4]] as [$teil, $ueberschrift, $versatz])
                    <div class="mb-4 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                {{ $ueberschrift }}
                            </h3>

                            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                @for ($i = 0; $i < 4; $i++)
                                    @php
                                        $index = $versatz + $i;
                                        $hinweis = match ($index) {
                                            0 => 'Anlage 14',
                                            3 => 'nur grundlegend',
                                            4 => 'eA möglich',
                                            default => null,
                                        };
                                    @endphp
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
                                        <input type="hidden" name="subjects[{{ $index }}][exam_part]" value="{{ $teil }}">
                                        <input type="hidden" name="subjects[{{ $index }}][subject_number]" value="{{ $i + 1 }}">

                                        <label class="w-40 shrink-0 text-sm text-gray-700 dark:text-gray-300"
                                               for="fach-{{ $index }}">
                                            <span class="font-medium">Fach {{ $index + 1 }}</span>
                                            @if ($hinweis)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $hinweis }}</span>
                                            @endif
                                        </label>

                                        <div class="flex-1 min-w-[14rem]">
                                            @if ($index === 0)
                                                <input type="hidden" name="subjects[0][subject_id]" value="{{ $mathematikId }}">
                                                <select id="fach-0" disabled
                                                        class="w-full py-1.5 text-sm rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 dark:text-gray-300 cursor-not-allowed">
                                                    <option>Mathematik</option>
                                                </select>
                                            @else
                                                <select id="fach-{{ $index }}" name="subjects[{{ $index }}][subject_id]" required
                                                        x-model.number="wahl[{{ $index }}].subject_id"
                                                        @change="pruefereignungPruefen({{ $index }}); pruefen()"
                                                        class="w-full py-1.5 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">-- Fach wählen --</option>
                                                    @foreach ($groupedSubjects as $aufgabenfeld => $faecher)
                                                        <optgroup label="{{ $aufgabenfeld }}">
                                                            @foreach ($faecher as $fach)
                                                                <option value="{{ $fach->id }}">{{ $fach->name }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>

                                        <span class="w-8 shrink-0 text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                                              title="erhöhtes Anforderungsniveau"
                                              x-show="istErhoeht({{ $index }})">eA</span>

                                        @if ($teil === 'part2_oral')
                                            <label class="w-14 shrink-0 text-sm text-gray-600 dark:text-gray-400 text-right"
                                                   for="pruefer-{{ $index }}"
                                                   x-show="!wahl[{{ $index }}].eingebracht">Prüfer</label>

                                            {{-- Bewusst ohne x-model: die Prüferliste wird
                                                 serverseitig gerendert, damit eine gespeicherte
                                                 Zuweisung beim Öffnen der Seite im Feld steht.
                                                 Mit einem x-for erzeugten Optionsbaum kommt der
                                                 Wert zu spät, das Feld bliebe leer — und beim
                                                 Speichern wäre die Zuweisung weg. Gefiltert wird
                                                 beim Fachwechsel über data-faecher. --}}
                                            <div class="flex-1 min-w-[14rem]" x-show="!wahl[{{ $index }}].eingebracht">
                                                <select id="pruefer-{{ $index }}" name="subjects[{{ $index }}][examiner_id]"
                                                        class="w-full py-1.5 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                    <option value="">-- später --</option>
                                                    @foreach ($teachers as $teacher)
                                                        <option value="{{ $teacher['id'] }}"
                                                                data-faecher="{{ implode(',', $teacher['subject_ids']) }}"
                                                                @selected($wahl[$index]['examiner_id'] === $teacher['id'])>
                                                            {{ $teacher['shortcode'] }} - {{ $teacher['name'] }}@if($teacher['exam_count'] > 0) ({{ $teacher['exam_count'] }} Prüflinge)@endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif

                                        {{-- § 64 Abs. 2: nur ein Ersatzschüler kann einbringen,
                                             und nur auf den Prüfungsfächern sieben und acht —
                                             Anlage 15 führt deren Punkte in der Spalte 13/II, die
                                             der Fächer fünf und sechs in der Spalte Prüfung. Die
                                             übrigen Regeln (genau zwei, nicht in den
                                             Fremdsprachen, nicht im Leistungsfach, nicht in
                                             Deutsch) meldet die Regelprüfung; die Maske entscheidet
                                             nichts selbst. --}}
                                        @if ($teil === 'part2_oral' && $externalApplicant->istErsatzschueler() && in_array($index, [6, 7], true))
                                            <div class="w-full flex flex-wrap items-center gap-x-3 gap-y-1 sm:pl-[10.75rem] pt-1">
                                                <input type="hidden" name="subjects[{{ $index }}][ergebnis_quelle]"
                                                       :value="wahl[{{ $index }}].eingebracht ? 'einbringung' : 'pruefung'">

                                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <input type="checkbox"
                                                           x-model="wahl[{{ $index }}].eingebracht"
                                                           @change="pruefen()"
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                                    <span>Ergebnis aus 13/II einbringen</span>
                                                </label>

                                                <div class="flex items-center gap-2" x-show="wahl[{{ $index }}].eingebracht">
                                                    <label class="text-sm text-gray-600 dark:text-gray-400"
                                                           for="punkte-{{ $index }}">Punkte</label>
                                                    <input id="punkte-{{ $index }}" type="number" min="0" max="15" step="1"
                                                           name="subjects[{{ $index }}][eingebrachte_punktzahl]"
                                                           x-model.number="wahl[{{ $index }}].eingebrachte_punktzahl"
                                                           class="w-20 py-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        einfache Wertung, 0–15 · § 64 Abs. 2 S. 4 und 5
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-between">
                    <a href="{{ route('external-applicants.index') }}"
                       class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Zurück zur Übersicht</a>

                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400" x-show="!istGueltig">
                            Speichern ist möglich; die Wahl bleibt bis zur Behebung als unvollständig markiert.
                        </span>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Fächerwahl speichern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script @cspNonce>
        function faecherwahl(config) {
            return {
                wahl: config.wahl,
                katalog: config.katalog,
                leistungsfach: config.leistungsfach ?? '',
                zweiteFremdsprache: config.zweiteFremdsprache ?? '',
                istErsatzschueler: config.istErsatzschueler === true,
                pruefUrl: config.pruefUrl,
                verstoesse: [],
                /** Was die Norm offenlaesst — macht die Wahl nicht ungueltig. */
                hinweise: [],
                istGueltig: false,
                pruefungLaeuft: false,
                zeitgeber: null,
                /** Zuletzt selbst gewählte Werte — siehe haltenOderVergessen(). */
                gemerkt: { leistungsfach: null, zweiteFremdsprache: null },

                /** Die gewählten Fächer ausser Deutsch und Mathematik — die sind ohnehin eA. */
                get leistungsfachOptionen() {
                    return this.wahl
                        .map(zeile => zeile.subject_id)
                        .filter(id => id)
                        .map(id => ({ id: id, ...(this.katalog[id] ?? { name: '?', code: null }) }))
                        .filter(fach => fach.code !== 'D' && fach.code !== 'M');
                },

                /** Die gewählten Fremdsprachen — nur eine davon kann die zweite sein. */
                get fremdsprachenOptionen() {
                    return this.wahl
                        .map(zeile => zeile.subject_id)
                        .filter(id => id)
                        .map(id => ({ id: id, ...(this.katalog[id] ?? { name: '?', fremdsprache: false }) }))
                        .filter(fach => fach.fremdsprache === true);
                },

                /**
                 * Prüferliste auf die Fakultas des gewählten Fachs eingrenzen.
                 *
                 * Vertritt keine Lehrkraft das Fach, bleibt die vollständige
                 * Liste stehen — sonst wäre die Zuweisung blockiert, obwohl der
                 * Fachausschuss sie zulässt. Eine bereits gewählte Lehrkraft,
                 * die das neue Fach nicht vertritt, wird zurückgesetzt.
                 */
                pruefereignungPruefen(index) {
                    const feld = document.getElementById('pruefer-' + index);
                    if (!feld) return;

                    const fachId = this.wahl[index]?.subject_id;
                    const optionen = Array.from(feld.options).filter(o => o.value !== '');
                    const passend = optionen.filter(
                        o => (o.dataset.faecher ?? '').split(',').includes(String(fachId))
                    );
                    const sichtbar = passend.length > 0 ? passend : optionen;

                    optionen.forEach(o => { o.hidden = !sichtbar.includes(o); });

                    if (feld.selectedOptions[0]?.hidden) {
                        feld.value = '';
                    }
                },

                /**
                 * Blendet im Leistungsfach-Feld aus, was gerade nicht gewählt
                 * ist. Die Optionen selbst kommen serverseitig, damit ein
                 * gespeichertes Leistungsfach beim Laden erhalten bleibt.
                 */
                leistungsfachOptionenAktualisieren() {
                    const feld = document.getElementById('leistungsfach');
                    if (!feld) return;

                    const gewaehlt = this.leistungsfachOptionen.map(fach => fach.id);

                    Array.from(feld.options).forEach(option => {
                        option.hidden = option.value !== '' && !gewaehlt.includes(Number(option.value));
                    });
                },

                /**
                 * Dasselbe für die zweite Fremdsprache: Optionen serverseitig,
                 * hier wird nur ausgeblendet, was gerade nicht gewählt ist.
                 */
                zweiteFremdspracheOptionenAktualisieren() {
                    const feld = document.getElementById('zweite-fremdsprache');
                    if (!feld) return;

                    const gewaehlt = this.fremdsprachenOptionen.map(fach => fach.id);

                    Array.from(feld.options).forEach(option => {
                        option.hidden = option.value !== '' && !gewaehlt.includes(Number(option.value));
                    });
                },

                istErhoeht(index) {
                    const id = this.wahl[index]?.subject_id;
                    if (!id) return false;
                    const code = this.katalog[id]?.code;
                    return code === 'D' || code === 'M' || id === this.leistungsfach;
                },

                /**
                 * Ein Wert, der nicht mehr unter den gewählten Fächern steht,
                 * verschwindet aus dem Feld — aber er wird gemerkt und kommt
                 * zurück, sobald das Fach wieder in der Wahl steht.
                 *
                 * Ohne das Gedächtnis kostete jeder Tausch die Angabe: wer
                 * Fach 6 und Fach 7 vertauscht, nimmt die Sprache zwischen den
                 * beiden Schritten kurz aus der Wahl, und die Angabe war weg,
                 * obwohl sie nach dem zweiten Schritt wieder gilt.
                 *
                 * Eine Auswahl von Hand löscht das Gedächtnis (siehe @change
                 * an den beiden Feldern) — wer bewusst „noch nicht festgelegt"
                 * wählt, soll den alten Wert nicht zurückbekommen.
                 */
                haltenOderVergessen(name, wert, optionen) {
                    const ids = optionen.map(fach => fach.id);

                    if (wert && ids.includes(wert)) {
                        this.gemerkt[name] = wert;
                        return wert;
                    }

                    if (wert) {
                        this.gemerkt[name] = wert;
                        return '';
                    }

                    return this.gemerkt[name] && ids.includes(this.gemerkt[name])
                        ? this.gemerkt[name]
                        : '';
                },

                /**
                 * Prüfung serverseitig. Entprellt, damit schnelles Durchklicken
                 * nicht acht Anfragen auslöst.
                 */
                pruefen() {
                    this.leistungsfachOptionenAktualisieren();
                    this.zweiteFremdspracheOptionenAktualisieren();

                    this.leistungsfach = this.haltenOderVergessen(
                        'leistungsfach', this.leistungsfach, this.leistungsfachOptionen
                    );
                    this.zweiteFremdsprache = this.haltenOderVergessen(
                        'zweiteFremdsprache', this.zweiteFremdsprache, this.fremdsprachenOptionen
                    );

                    clearTimeout(this.zeitgeber);
                    this.zeitgeber = setTimeout(() => this.pruefungAusfuehren(), 250);
                },

                async pruefungAusfuehren() {
                    if (this.wahl.some(zeile => !zeile.subject_id)) {
                        this.verstoesse = [];
                        this.hinweise = [];
                        this.istGueltig = false;
                        return;
                    }

                    this.pruefungLaeuft = true;

                    try {
                        const antwort = await fetch(this.pruefUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({
                                subjects: this.wahl.map(zeile => ({
                                    subject_id: zeile.subject_id,
                                    exam_part: zeile.teil,
                                    subject_number: zeile.nummer,
                                    ergebnis_quelle: zeile.eingebracht ? 'einbringung' : 'pruefung',
                                })),
                                leistungsfach_subject_id: this.leistungsfach || null,
                                zweite_fremdsprache_subject_id: this.zweiteFremdsprache || null,
                            }),
                        });

                        if (!antwort.ok) {
                            // Keine eigene Fehlerlogik: die Maske trifft keine
                            // Regelentscheidung, sie zeigt nur an.
                            this.verstoesse = [];
                            this.hinweise = [];
                            this.istGueltig = false;
                            return;
                        }

                        const ergebnis = await antwort.json();
                        this.verstoesse = ergebnis.verstoesse ?? [];
                        this.hinweise = ergebnis.hinweise ?? [];
                        this.istGueltig = ergebnis.valid === true;
                    } finally {
                        this.pruefungLaeuft = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
