{{--
    Mindestpunkte-Kalkulator für externe Bewerber — § 62 GSO.

    Rein darstellend: gerechnet wird vollständig auf dem Server
    (ExterneMindestpunkteService). Anders als beim § 61-Zweig steht hier keine
    einzige Formel im Browser — die Positionen 1–8 der Anlage 14, die Faktoren
    und beide Bestehenstatbestände hängen am Datenmodell.

    Erwartet `computedData62` = { paragraph, faecherwahl_vollstaendig, ergebnis }.
--}}

{{-- Fächerwahl noch nicht vollständig --}}
<template x-if="computedData62 && !computedData62.faecherwahl_vollstaendig">
    <div class="border border-amber-200 bg-amber-50 rounded-lg p-4 text-sm text-amber-800">
        <p class="font-semibold mb-1">Die Fächerwahl ist noch nicht vollständig.</p>
        <p class="text-xs">
            § 61 Abs. 1 S. 1: Gegenstand der Prüfung sind acht Prüfungsfächer, die die
            Positionen 1 bis 8 genau einmal belegen. Solange das nicht steht, ist nach
            § 62 nichts zu rechnen.
        </p>
    </div>
</template>

<template x-if="computeError">
    <div class="border border-red-200 bg-red-50 rounded-lg p-4 text-sm text-red-800"
         x-text="computeError"></div>
</template>

<template x-if="computedData62 && computedData62.ergebnis">
    <div class="space-y-4">

        {{-- ─── Kopf ────────────────────────────────────────────────── --}}
        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1 px-2 py-1 bg-sky-100 text-sky-800 rounded text-xs font-semibold">
                § 62 GSO · andere Bewerber
            </span>
            <span class="text-xs text-gray-500"
                  x-text="computedData62.ergebnis.schema === 'anlage_15'
                          ? 'Anlage 15 (Ersatzschule nach § 64 Abs. 2)'
                          : 'Anlage 14'"></span>
            <span class="text-xs text-gray-400">
                Fächer 1–4 elffach (je max. 165) · Fächer 5–8 vierfach (je max. 60) · zusammen 900
            </span>
        </div>

        {{-- ─── Abbruch nach dem ersten Prüfungsteil ─────────────────── --}}
        <template x-if="computedData62.ergebnis.abbruch === 'nach_erstem_teil'">
            <div class="border border-red-300 bg-red-50 rounded-lg p-3">
                <p class="text-sm font-semibold text-red-800">
                    Die Prüfung wird nach dem ersten Prüfungsteil abgebrochen (§ 62 Abs. 1 S. 4).
                </p>
                <ul class="mt-1 text-xs text-red-700 list-disc list-inside space-y-0.5">
                    <template x-for="grund in computedData62.ergebnis.rechnung.erster_teil.gruende"
                              :key="grund">
                        <li x-text="grund"></li>
                    </template>
                </ul>
                <p class="mt-2 text-xs text-red-600">
                    Der zweite Prüfungsteil findet damit nicht mehr statt und wird hier
                    nicht ausgewiesen.
                </p>
            </div>
        </template>

        {{-- ─── Ist-Stand über alle acht Fächer ──────────────────────── --}}
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-3 py-2 bg-gray-50 text-xs font-semibold text-gray-700">
                Die acht Prüfungsfächer
            </div>
            <table class="w-full text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-2 py-1 text-left font-medium">Nr.</th>
                        <th class="px-2 py-1 text-left font-medium">Fach</th>
                        <th class="px-2 py-1 text-center font-medium">Niveau</th>
                        <th class="px-2 py-1 text-center font-medium">Faktor</th>
                        <th class="px-2 py-1 text-center font-medium">Note</th>
                        <th class="px-2 py-1 text-center font-medium">einfach</th>
                        <th class="px-2 py-1 text-center font-medium">Gesamt</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="quelle in computedData62.faecher_quelle" :key="quelle.position">
                        <tr class="border-t border-gray-100"
                            :class="quelle.position === 5 ? 'border-t-2 border-gray-300' : ''"
                            x-data="{ get fach() { return mp62Bewertet(quelle.position) ?? {}; },
                                      get pruefung() { return mp62Exam(quelle.student_exam_id); } }">
                            <td class="px-2 py-1 text-gray-500" x-text="quelle.position"></td>
                            <td class="px-2 py-1">
                                <span x-text="quelle.fach ?? '–'"></span>
                                <span x-show="fach.quelle === 'zusatzpruefung'"
                                      class="ml-1 text-indigo-600 font-semibold">ZP</span>
                                <span x-show="fach.quelle === 'einbringung'"
                                      class="ml-1 text-teal-600 font-semibold">13/II</span>
                            </td>
                            <td class="px-2 py-1 text-center"
                                x-text="quelle.ea ? 'eA' : 'gA'"
                                :class="quelle.ea ? 'text-amber-700 font-medium' : 'text-gray-400'"></td>
                            <td class="px-2 py-1 text-center text-gray-400" x-text="'×' + (fach.faktor ?? '')"></td>

                            {{-- Eingabe: bei einer Zusatzprüfung schriftlich + mündlich,
                                 sonst die eine Prüfungsnote. Gespeichert wird über
                                 dieselben Endpunkte wie beim § 61-Zweig — beide
                                 sprechen den Prüflingsschlüssel. --}}
                            <td class="px-2 py-1 text-center">
                                <template x-if="quelle.zusatzpruefung && pruefung">
                                    <span class="inline-flex items-center gap-1">
                                        <input type="number" min="0" max="15"
                                               x-model="pruefung.writtenPoints"
                                               @change="mpSaveWrittenPoints(pruefung)"
                                               @keydown.enter="$el.blur()"
                                               class="w-11 text-xs text-center border border-gray-300 rounded px-1 py-0.5"
                                               placeholder="schr.">
                                        <span class="text-gray-300">+</span>
                                        <input type="number" min="0" max="15"
                                               x-model="pruefung.targetPoints"
                                               @change="mpSaveTargetPoints(pruefung)"
                                               @keydown.enter="$el.blur()"
                                               class="w-11 text-xs text-center border border-indigo-300 bg-indigo-50 rounded px-1 py-0.5"
                                               placeholder="mündl.">
                                    </span>
                                </template>
                                <template x-if="!quelle.zusatzpruefung && quelle.student_exam_id">
                                    <input type="number" min="0" max="15"
                                           x-model="mindestpunkteModal.scores[quelle.student_exam_id]"
                                           @change="mpSaveExamScore(quelle.student_exam_id)"
                                           @keydown.enter="$el.blur()"
                                           class="w-14 text-xs text-center border rounded px-1 py-0.5"
                                           :class="(mindestpunkteModal.scores[quelle.student_exam_id] !== null
                                                    && mindestpunkteModal.scores[quelle.student_exam_id] !== undefined
                                                    && mindestpunkteModal.scores[quelle.student_exam_id] !== '')
                                                   ? 'border-indigo-300 bg-indigo-50 font-semibold' : 'border-gray-300'"
                                           placeholder="–">
                                </template>
                                <template x-if="!quelle.student_exam_id">
                                    <span class="text-gray-300">–</span>
                                </template>
                            </td>
                            <td class="px-2 py-1 text-center font-mono"
                                x-text="fach.einfache_wertung ?? '–'"
                                :class="mpScoreColor(fach.einfache_wertung)"></td>
                            <td class="px-2 py-1 text-center font-mono font-semibold"
                                x-text="fach.gesamtpunktzahl ?? '–'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- ─── Erster Prüfungsteil ──────────────────────────────────── --}}
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-3 py-2 bg-indigo-50 flex items-center justify-between gap-2 flex-wrap">
                <span class="text-xs font-semibold text-indigo-900">
                    Erster Prüfungsteil · Fächer 1–4 · § 62 Abs. 1 S. 3
                </span>
                <span class="text-xs font-mono"
                      :class="computedData62.ergebnis.rechnung.erster_teil.summe >= 220
                              ? 'text-green-700 font-semibold' : 'text-gray-600'">
                    <span x-text="computedData62.ergebnis.rechnung.erster_teil.summe"></span>/220
                    <span class="text-gray-400">von 660</span>
                </span>
            </div>

            <div class="p-3 space-y-3">

                {{-- Keine offene Zusatzprüfung --}}
                <template x-if="computedData62.ergebnis.erster_teil.offene.length === 0">
                    <p class="text-xs text-gray-500">
                        Keine offene mündliche Zusatzprüfung — es ist nichts aufzulösen.
                        Der erste Prüfungsteil steht mit den eingetragenen Noten fest.
                    </p>
                </template>

                {{-- Schriftliche Noten fehlen noch --}}
                <template x-if="computedData62.ergebnis.erster_teil.offene.length > 0
                                && !computedData62.ergebnis.erster_teil.eingaben_vollstaendig">
                    <p class="text-xs text-amber-700">
                        In mindestens einem Fach des ersten Prüfungsteils fehlt noch die Note.
                        Solange ist die Frage nach der Mindestnote nicht gestellt.
                    </p>
                </template>

                {{-- Vier offene Zusatzprüfungen: keine Tabelle --}}
                <template x-if="computedData62.ergebnis.erster_teil.zu_viele_offene">
                    <p class="text-xs text-amber-700">
                        Vier offene Zusatzprüfungen. Eine Tabelle über vier freie Größen
                        hätte 65 536 Zeilen und wird deshalb nicht ausgegeben — die
                        Einzelfälle sind hier von Hand zu betrachten.
                    </p>
                </template>

                {{-- Die Empfehlung --}}
                <template x-if="computedData62.ergebnis.erster_teil.top5.length > 0">
                    <div>
                        <p class="text-xs text-gray-600 mb-1">
                            <span x-show="computedData62.ergebnis.erster_teil.offene.length === 1">
                                Nötige Note in der mündlichen Zusatzprüfung:
                            </span>
                            <span x-show="computedData62.ergebnis.erster_teil.offene.length > 1">
                                Die gleichmäßigsten Kombinationen
                                (<span x-text="computedData62.ergebnis.erster_teil.bestehende"></span>
                                bestehen insgesamt):
                            </span>
                        </p>
                        <table class="w-full text-xs border border-gray-200">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <template x-for="fach in computedData62.ergebnis.erster_teil.offene"
                                              :key="'kopf-' + fach.position">
                                        <th class="px-2 py-1 text-center font-medium">
                                            <span x-text="fach.fach ?? ('Fach ' + fach.position)"></span>
                                            <span class="block text-gray-400 font-normal">
                                                schriftl. <span x-text="fach.schriftlich"></span>
                                            </span>
                                        </th>
                                    </template>
                                    <th class="px-2 py-1 text-center font-medium">Σ erster Teil</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(zeile, zi) in computedData62.ergebnis.erster_teil.top5"
                                          :key="'top-' + zi">
                                    <tr class="border-t border-gray-100 bg-green-50">
                                        <template x-for="fach in zeile.faecher" :key="'z-' + zi + '-' + fach.position">
                                            <td class="px-2 py-1 text-center">
                                                <span class="font-mono font-bold text-green-800"
                                                      x-text="fach.muendlich"></span>
                                                <span class="text-gray-400 text-[10px] block">
                                                    → <span x-text="fach.einfache_wertung"></span> Pkt.
                                                    = <span x-text="fach.gesamtpunktzahl"></span> (×11)
                                                </span>
                                            </td>
                                        </template>
                                        <td class="px-2 py-1 text-center font-mono font-bold text-green-800"
                                            x-text="zeile.summe"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                {{-- Keine Kombination besteht --}}
                <template x-if="computedData62.ergebnis.erster_teil.kombinationen
                                && computedData62.ergebnis.erster_teil.bestehende === 0">
                    <p class="text-xs text-red-700 font-medium">
                        Auch mit 15 Punkten in jeder Zusatzprüfung wird der erste Prüfungsteil
                        nicht mehr bestanden.
                    </p>
                </template>

            </div>
        </div>

        {{-- ─── Zweiter Prüfungsteil ─────────────────────────────────── --}}
        <template x-if="computedData62.ergebnis.zweiter_teil">
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-3 py-2 bg-teal-50 flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-teal-900">
                        Zweiter Prüfungsteil · Fächer 5–8 · § 62 Abs. 2
                    </span>
                    <span class="text-xs font-mono"
                          :class="computedData62.ergebnis.zweiter_teil.erreicht >= 80
                                  ? 'text-green-700 font-semibold' : 'text-gray-600'">
                        <span x-text="computedData62.ergebnis.zweiter_teil.erreicht"></span>/80
                        <span class="text-gray-400">von 240</span>
                    </span>
                </div>

                <div class="p-3 space-y-2 text-xs">

                    {{--
                        Kein Aufzählen: im zweiten Prüfungsteil gibt es keine
                        Zusatzprüfungen und damit keine Rundung. Die Gesamtpunktzahl
                        eines Fachs ist exakt Punkte × 4, und die Schwelle wird zur
                        reinen Arithmetik.
                    --}}
                    <p class="text-gray-500">
                        Ohne Zusatzprüfung ist die Gesamtpunktzahl eines Fachs exakt
                        Punkte × 4 — die Schwelle von 80 Punkten sind damit
                        <span class="font-semibold">20 Punkte einfacher Wertung</span>
                        über vier Fächer, also ein Schnitt von 5.
                    </p>

                    <template x-if="computedData62.ergebnis.zweiter_teil.offene.length === 0">
                        <p :class="computedData62.ergebnis.zweiter_teil.erreichbar
                                   ? 'text-green-700 font-medium' : 'text-red-700 font-medium'"
                           x-text="computedData62.ergebnis.zweiter_teil.erreichbar
                                   ? 'Alle vier Fächer sind bewertet, der zweite Prüfungsteil ist bestanden.'
                                   : 'Alle vier Fächer sind bewertet, der zweite Prüfungsteil ist nicht bestanden.'"></p>
                    </template>

                    <template x-if="computedData62.ergebnis.zweiter_teil.offene.length > 0">
                        <div class="space-y-1">
                            <p>
                                Bereits erreicht:
                                <span class="font-mono font-semibold"
                                      x-text="computedData62.ergebnis.zweiter_teil.erreicht"></span> Punkte
                                (<span x-text="computedData62.ergebnis.zweiter_teil.erreichte_wertung"></span>
                                von 20 einfacher Wertung).
                            </p>
                            <p>
                                Offen:
                                <template x-for="(fach, fi) in computedData62.ergebnis.zweiter_teil.offene"
                                          :key="'o-' + fach.position">
                                    <span>
                                        <span x-show="fi > 0">, </span>
                                        <span class="font-medium" x-text="fach.fach ?? ('Fach ' + fach.position)"></span>
                                    </span>
                                </template>
                            </p>
                            <ul class="list-disc list-inside space-y-0.5"
                                :class="computedData62.ergebnis.zweiter_teil.erreichbar ? 'text-gray-700' : 'text-red-700'">
                                <li>
                                    zusammen noch
                                    <span class="font-mono font-bold"
                                          x-text="computedData62.ergebnis.zweiter_teil.noch_noetige_wertung"></span>
                                    Punkte einfacher Wertung
                                    (=&nbsp;<span x-text="computedData62.ergebnis.zweiter_teil.noch_noetige_punkte"></span>
                                    Punkte der Gesamtwertung)
                                </li>
                                <li>kein Fach mit 0 Punkten</li>
                                <li x-show="computedData62.ergebnis.zweiter_teil.noch_faecher_mit_fuenf > 0">
                                    noch
                                    <span class="font-mono font-bold"
                                          x-text="computedData62.ergebnis.zweiter_teil.noch_faecher_mit_fuenf"></span>
                                    Fach/Fächer mit mindestens 5 Punkten
                                </li>
                                <li x-show="computedData62.ergebnis.zweiter_teil.noch_faecher_mit_fuenf === 0"
                                    class="text-green-700">
                                    zwei Fächer mit mindestens 5 Punkten stehen bereits
                                </li>
                            </ul>
                            <p x-show="!computedData62.ergebnis.zweiter_teil.erreichbar"
                               class="text-red-700 font-semibold pt-1">
                                Der zweite Prüfungsteil ist nicht mehr zu bestehen.
                            </p>
                        </div>
                    </template>

                    <template x-if="computedData62.ergebnis.zweiter_teil.gruende.length > 0">
                        <ul class="list-disc list-inside text-red-700 space-y-0.5 pt-1">
                            <template x-for="grund in computedData62.ergebnis.zweiter_teil.gruende"
                                      :key="'g2-' + grund">
                                <li x-text="grund"></li>
                            </template>
                        </ul>
                    </template>

                </div>
            </div>
        </template>

        {{-- ─── Gesamtqualifikation ──────────────────────────────────── --}}
        <template x-if="computedData62.ergebnis.rechnung.gesamtqualifikation !== null">
            <div class="border rounded-lg p-3 flex items-center justify-between"
                 :class="computedData62.ergebnis.rechnung.bestanden
                         ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50'">
                <span class="text-sm font-semibold"
                      :class="computedData62.ergebnis.rechnung.bestanden ? 'text-green-800' : 'text-red-800'"
                      x-text="computedData62.ergebnis.rechnung.bestanden
                              ? 'Abiturprüfung bestanden (§ 62 Abs. 3)'
                              : 'Abiturprüfung nicht bestanden'"></span>
                <span class="font-mono font-bold text-lg"
                      :class="computedData62.ergebnis.rechnung.bestanden ? 'text-green-800' : 'text-red-800'">
                    <span x-text="computedData62.ergebnis.rechnung.gesamtqualifikation"></span>/900
                </span>
            </div>
        </template>

    </div>
</template>
