{{--
    MINDESTPUNKTE-MODAL  (GSO §61 / Anlage 12 zu §52)
    Eingebunden via @include in zusatzpruefungen/index.blade.php.
    Alpine-Hilfsfunktionen liegen in public/js/mindestpunkte.js (mpMixin).
--}}

<style>
@media print {
    body > *:not(#zp-mindest-overlay) { display: none !important; }
    #zp-mindest-overlay {
        position: static !important; background: transparent !important;
        align-items: flex-start !important; padding: 0 !important;
    }
    #zp-mindest-overlay > div {
        max-height: none !important; box-shadow: none !important;
        border-radius: 0 !important; width: 100% !important; max-width: none !important;
    }
    .no-print { display: none !important; }
    .mp-print-break { page-break-before: always; }
}
</style>

<div id="zp-mindest-overlay"
     x-show="mindestpunkteModal.show" x-cloak
     class="fixed inset-0 flex items-center justify-center z-50"
     style="background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);"
     @click.self="mindestpunkteModal.show = false"
     @keydown.escape.window="mindestpunkteModal.show = false">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 flex flex-col" style="max-height: 92vh;">

        {{-- ─── Header ──────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between px-5 py-3 bg-indigo-50 border-b border-indigo-100 shrink-0 no-print">
            <div>
                <h2 class="text-sm font-bold text-indigo-900" x-text="mindestpunkteModal.studentName"></h2>
                <p class="text-xs text-indigo-500 mt-0.5"
                   x-text="mindestpunkteModal.paragraph === '62'
                           ? 'Mindestpunkte-Kalkulator · GSO §62 / Anlage 14'
                           : 'Mindestpunkte-Kalkulator · GSO §61 / Anlage 12 zu §52'"></p>
            </div>
            <button @click="mindestpunkteModal.show = false"
                    class="text-indigo-300 hover:text-indigo-600 text-2xl leading-none font-light no-print">×</button>
        </div>

        <div class="overflow-y-auto p-4 space-y-4 text-sm" id="zp-mindest-print-body">

            {{-- ─── Druckkopf ───────────────────────────────────────── --}}
            <div style="display:none;" id="zp-print-header">
                <h1 style="font-size:14px;font-weight:bold;margin-bottom:2px;"
                    x-text="'Mindestpunkte-Kalkulator – ' + mindestpunkteModal.studentName"></h1>
                <p style="font-size:10px;color:#6b7280;">
                    <span x-text="mindestpunkteModal.paragraph === '62' ? 'GSO §62' : 'GSO §61'"></span>
                    · {{ now()->format('d.m.Y H:i') }} Uhr
                </p>
                <hr style="margin:6px 0;">
            </div>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- EXTERNE BEWERBER — § 62 GSO                                --}}
            {{--                                                            --}}
            {{-- Zweiter Rechenweg, kein Sonderfall im ersten: für externe   --}}
            {{-- Bewerber entsteht die Gesamtqualifikation allein aus den    --}}
            {{-- acht Prüfungen, ohne Halbjahresleistungen. Die beiden       --}}
            {{-- Zweige darunter rechnen nach § 61 und bleiben deshalb für   --}}
            {{-- sie zu.                                                     --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <template x-if="mindestpunkteModal.paragraph === '62'">
                <div>
                    @include('zusatzpruefungen.partials.mindestpunkte-modal-62')
                </div>
            </template>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- VERBESSERUNGS-MODUS                                        --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <template x-if="mindestpunkteModal.paragraph !== '62' && mindestpunkteModal.improvementOnly">
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-xs font-semibold">
                            🎯 Nur zur Notenverbesserung
                        </span>
                        <span class="text-xs text-gray-400">Formel: round((2×schr. + mündl.) × 4 / 3)</span>
                    </div>
                    <template x-for="exam in mindestpunkteModal.exams.filter(e => e.isZusatzpruefung)" :key="'imp-'+exam.id">
                        <div class="mb-4 border border-emerald-200 rounded-lg overflow-hidden">
                            <div class="px-4 py-2 bg-emerald-50 flex items-center gap-2">
                                <span class="font-semibold text-gray-800" x-text="exam.subject"></span>
                                <span class="font-mono text-xs text-gray-400" x-text="exam.courseCode"></span>
                                <span x-show="exam.isLeistungsfach" class="text-xs text-amber-600 font-medium">(LF)</span>
                                <span class="ml-auto text-xs text-gray-500">
                                    Schriftlich: <strong class="font-mono" x-text="exam.writtenPoints ?? '–'"></strong> Pkt.
                                    <span x-show="exam.writtenPoints !== null" class="text-indigo-600 ml-1"
                                          x-text="'= ' + (exam.writtenPoints * 4) + ' (×4)'"></span>
                                    <template x-if="exam.improvementPointsNeeded !== null && exam.improvementPointsNeeded !== undefined">
                                        <span class="text-emerald-700 font-semibold ml-1"
                                              x-text="'· Ziel: +' + exam.improvementPointsNeeded + ' Pkt.'"></span>
                                    </template>
                                </span>
                            </div>
                            <template x-if="exam.writtenPoints !== null">
                                <div>
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wide border-b border-gray-200">
                                            <tr>
                                                <th class="px-3 py-1.5 text-center font-medium">Mind. mündlich</th>
                                                <th class="px-3 py-1.5 text-center font-medium">Komb. (0–15)</th>
                                                <th class="px-3 py-1.5 text-center font-medium">Beitrag (×4)</th>
                                                <th class="px-3 py-1.5 text-center font-medium">Δ gegenüber schr.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="row in mpImprovementRows(exam.writtenPoints, exam.improvementPointsNeeded)" :key="'ir-'+row.combined">
                                                <tr class="border-t border-gray-100 bg-green-50/60">
                                                    <td class="px-3 py-1.5 text-center font-mono font-semibold text-gray-800" x-text="row.minOral"></td>
                                                    <td class="px-3 py-1.5 text-center font-mono text-gray-700" x-text="row.combined"></td>
                                                    <td class="px-3 py-1.5 text-center font-mono text-indigo-700 font-semibold" x-text="row.contrib"></td>
                                                    <td class="px-3 py-1.5 text-center font-semibold font-mono text-green-700" x-text="'+' + row.delta + ' Pkt.'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <template x-if="mpImprovementRows(exam.writtenPoints, exam.improvementPointsNeeded).length === 0 && (exam.improvementPointsNeeded === null || exam.improvementPointsNeeded === undefined)">
                                        <p class="px-4 py-2 text-xs text-gray-400">Keine Verbesserung möglich (schriftlich bereits 15 Pkt.).</p>
                                    </template>
                                    <template x-if="mpImprovementRows(exam.writtenPoints, exam.improvementPointsNeeded).length === 0 && exam.improvementPointsNeeded !== null && exam.improvementPointsNeeded !== undefined">
                                        <p class="px-4 py-2 text-xs text-red-600">
                                            ✗ Ziel von +<span x-text="exam.improvementPointsNeeded"></span> Pkt. ist auch mit 15 Pkt. mündlich nicht erreichbar.
                                        </p>
                                    </template>
                                </div>
                            </template>
                            <template x-if="exam.writtenPoints === null">
                                <p class="px-4 py-3 text-xs text-gray-400">Schriftliche Note noch nicht eingetragen.</p>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- NORMAL-MODUS                                               --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <template x-if="mindestpunkteModal.paragraph !== '62' && !mindestpunkteModal.improvementOnly">
                <div class="space-y-4">

                    {{-- ─── A: Noteneingabe + Beiträge ─────────────────── --}}
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                            Prüfungsfächer &amp; Noteneingabe
                            <span class="normal-case font-normal text-gray-400 ml-1">(Noten automatisch gespeichert)</span>
                        </h3>
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <table class="w-full">
                                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Fach</th>
                                        <th class="px-3 py-2 text-center font-medium w-14">Kurs</th>
                                        <th class="px-3 py-2 text-center font-medium w-36">Note (0–15)</th>
                                        <th class="px-3 py-2 text-center font-medium w-20">Komb.</th>
                                        <th class="px-3 py-2 text-center font-medium w-24">Beitrag (×4)</th>
                                        <th class="px-3 py-2 text-center font-medium w-14 no-print">Reihenf.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(exam, ei) in mpSortedExams()" :key="'ea-'+exam.id">
                                        <tr class="border-t border-gray-100"
                                            :class="exam.isZusatzpruefung ? 'bg-indigo-50/40' : ''">

                                            {{-- Fachname --}}
                                            <td class="px-3 py-1.5">
                                                <span class="text-gray-800" x-text="exam.subject"></span>
                                                <span x-show="exam.isLeistungsfach" class="ml-1 text-xs text-amber-600 font-medium">(LF)</span>
                                                <span x-show="exam.isDeutsch || exam.isMathematik" class="ml-1 text-xs text-gray-400"
                                                      x-text="exam.isDeutsch ? '(D)' : '(M)'"></span>
                                                <span x-show="exam.isZusatzpruefung" class="ml-1 text-xs font-semibold text-indigo-600">ZP</span>
                                            </td>

                                            {{-- Kurskürzel --}}
                                            <td class="px-3 py-1.5 text-center font-mono text-xs text-gray-400" x-text="exam.courseCode"></td>

                                            {{-- Noteneingabe --}}
                                            <td class="px-3 py-1.5 text-center">
                                                {{-- ZP-Fach --}}
                                                <template x-if="exam.isZusatzpruefung">
                                                    <div class="flex flex-col items-center gap-0.5">
                                                        <template x-if="exam.writtenPoints === null">
                                                            <div class="flex items-center gap-1">
                                                                <input type="number" min="0" max="15"
                                                                    x-model.number="exam.writtenPoints"
                                                                    @keydown.enter="mpSaveWrittenPoints(exam); $el.blur()"
                                                                    class="w-14 text-sm text-center border border-amber-400 bg-amber-50 rounded px-1.5 py-0.5 focus:ring-1 focus:ring-amber-400 focus:outline-none"
                                                                    placeholder="schr.">
                                                                <button type="button"
                                                                    @click="mpSaveWrittenPoints(exam)"
                                                                    title="Schriftliche Note speichern"
                                                                    class="shrink-0 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded p-1">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                </button>
                                                            </div>
                                                        </template>
                                                        <template x-if="exam.writtenPoints !== null && exam.isSport">
                                                            <span class="text-xs font-mono text-gray-600"
                                                                  x-text="exam.writtenPoints + '\u00a0schr. / ' + (exam.sportPracticalGrade ?? '–') + '\u00a0prakt.'"></span>
                                                        </template>
                                                        <template x-if="exam.writtenPoints !== null && !exam.isSport">
                                                            <span class="text-xs font-mono text-gray-600"
                                                                  x-text="exam.writtenPoints + '\u00a0schr.'"></span>
                                                        </template>
                                                        <template x-if="exam.writtenPoints !== null">
                                                            <div class="flex items-center gap-1">
                                                                <input type="number" min="0" max="15"
                                                                    x-model.number="exam.targetPoints"
                                                                    @keydown.enter="mpSaveTargetPoints(exam); $el.blur()"
                                                                    class="w-14 text-xs text-center border rounded px-1.5 py-0.5 focus:ring-1 focus:outline-none"
                                                                    :class="exam.targetPoints !== null
                                                                        ? 'border-green-400 bg-green-50 text-green-700 font-semibold focus:ring-green-400'
                                                                        : 'border-gray-300 focus:ring-indigo-400'"
                                                                    placeholder="mündl.">
                                                                <button type="button"
                                                                    @click="mpSaveTargetPoints(exam)"
                                                                    title="Mündliche Note speichern"
                                                                    class="shrink-0 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded p-1">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>

                                                {{-- Normales Fach --}}
                                                <template x-if="!exam.isZusatzpruefung && !exam.isSport">
                                                    <input type="number" min="0" max="15"
                                                        x-model="mindestpunkteModal.scores[exam.id]"
                                                        @change="mpSaveExamScore(exam.id)"
                                                        @keydown.enter="$el.blur()"
                                                        class="w-16 text-sm text-center border rounded px-1.5 py-0.5 focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                                        :class="(mindestpunkteModal.scores[exam.id] !== null && mindestpunkteModal.scores[exam.id] !== undefined && mindestpunkteModal.scores[exam.id] !== '') ? 'border-indigo-300 bg-indigo-50 font-semibold' : 'border-gray-300'"
                                                        placeholder="–">
                                                </template>

                                                {{-- Sport (Theorie + Praktisch) --}}
                                                <template x-if="!exam.isZusatzpruefung && exam.isSport">
                                                    <div class="flex items-center gap-1 justify-center">
                                                        <div class="flex flex-col items-center gap-0.5">
                                                            <span class="text-gray-400" style="font-size:9px">Theorie</span>
                                                            <input type="number" min="0" max="15"
                                                                x-model="mindestpunkteModal.scores[exam.id]"
                                                                @change="mpSaveExamScore(exam.id)"
                                                                @keydown.enter="$el.blur()"
                                                                class="w-11 text-xs text-center border rounded px-1 py-0.5 focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                                                :class="(mindestpunkteModal.scores[exam.id] !== null && mindestpunkteModal.scores[exam.id] !== undefined && mindestpunkteModal.scores[exam.id] !== '') ? 'border-indigo-300 bg-indigo-50 font-semibold' : 'border-gray-300'"
                                                                placeholder="–">
                                                        </div>
                                                        <span class="text-gray-300 text-xs">+</span>
                                                        <div class="flex flex-col items-center gap-0.5">
                                                            <span class="text-gray-400" style="font-size:9px">Prakt.</span>
                                                            <input type="number" min="0" max="15"
                                                                x-model="mindestpunkteModal.practicalGrades[exam.id]"
                                                                @change="mpSavePracticalGrade(exam.id)"
                                                                @keydown.enter="$el.blur()"
                                                                class="w-11 text-xs text-center border border-amber-300 bg-amber-50 rounded px-1 py-0.5 focus:ring-1 focus:ring-amber-400 focus:outline-none"
                                                                placeholder="–">
                                                        </div>
                                                        <span class="text-gray-400 text-xs">→×2</span>
                                                    </div>
                                                </template>
                                            </td>

                                            {{-- Kombinierte Note (0–15) --}}
                                            <td class="px-3 py-1.5 text-center">
                                                {{-- ZP: komb. aus written+target (Sport-Sonderfall via mpCombinedFor(),
                                                     berücksichtigt zusätzlich die Praxisnote) --}}
                                                <template x-if="exam.isZusatzpruefung && exam.writtenPoints !== null && exam.targetPoints !== null">
                                                    <span class="font-mono font-bold text-sm"
                                                          :class="mpScoreColor(mpCombinedFor(exam))"
                                                          x-text="mpCombinedFor(exam)"></span>
                                                </template>
                                                {{-- ZP offen: schriftliche Note anzeigen (Sport: zusätzlich Praxisnote) --}}
                                                <template x-if="exam.isZusatzpruefung && exam.isSport && exam.writtenPoints !== null && exam.targetPoints === null">
                                                    <span class="font-mono text-xs text-gray-400"
                                                          x-text="exam.writtenPoints + '\u00a0schr. / ' + (exam.sportPracticalGrade ?? '–') + '\u00a0prakt.'"></span>
                                                </template>
                                                <template x-if="exam.isZusatzpruefung && !exam.isSport && exam.writtenPoints !== null && exam.targetPoints === null">
                                                    <span class="font-mono text-xs text-gray-400" x-text="exam.writtenPoints + '\u00a0schr.'"></span>
                                                </template>
                                                {{-- Normales Fach --}}
                                                <template x-if="!exam.isZusatzpruefung && !exam.isSport && mindestpunkteModal.scores[exam.id] !== null && mindestpunkteModal.scores[exam.id] !== undefined && mindestpunkteModal.scores[exam.id] !== ''">
                                                    <span class="font-mono text-sm"
                                                          :class="mpScoreColor(parseInt(mindestpunkteModal.scores[exam.id]))"
                                                          x-text="mindestpunkteModal.scores[exam.id]"></span>
                                                </template>
                                                {{-- Sport: kein 0–15 Einzelwert --}}
                                                <template x-if="exam.isSport && !exam.isZusatzpruefung">
                                                    <span class="text-xs text-gray-400">—</span>
                                                </template>
                                            </td>

                                            {{-- Abiturbeitrag (4-fache Wertung) --}}
                                            <td class="px-3 py-1.5 text-center">
                                                {{-- ZP abgeschlossen: mpContribFor() wählt automatisch Sport- oder
                                                     normale Formel (berücksichtigt bei Sport die Praxisnote) --}}
                                                <template x-if="exam.isZusatzpruefung && exam.writtenPoints !== null && exam.targetPoints !== null">
                                                    <span class="font-mono font-bold text-indigo-700"
                                                          x-text="mpContribFor(exam)"></span>
                                                </template>
                                                {{-- ZP offen, Sport: (praxis+theorie)×2 als Untergrenze-Platzhalter
                                                     (Nicht-ZP-Sport-Formel — kein 2×-Faktor wie bei normalen
                                                     ZP-Fächern, siehe MindestpunkteService::scoreContribution()) --}}
                                                <template x-if="exam.isZusatzpruefung && exam.isSport && exam.writtenPoints !== null && exam.targetPoints === null">
                                                    <span class="font-mono font-bold text-indigo-700"
                                                          x-text="(exam.writtenPoints + (exam.sportPracticalGrade ?? 0)) * 2"></span>
                                                </template>
                                                {{-- ZP offen, sonst: written × 4 als Platzhalter-Beitrag --}}
                                                <template x-if="exam.isZusatzpruefung && !exam.isSport && exam.writtenPoints !== null && exam.targetPoints === null">
                                                    <span class="font-mono font-bold text-indigo-700"
                                                          x-text="exam.writtenPoints * 4"></span>
                                                </template>
                                                {{-- Normales Fach --}}
                                                <template x-if="!exam.isZusatzpruefung && !exam.isSport && mindestpunkteModal.scores[exam.id] !== null && mindestpunkteModal.scores[exam.id] !== undefined && mindestpunkteModal.scores[exam.id] !== ''">
                                                    <span class="font-mono font-bold text-indigo-700"
                                                          x-text="parseInt(mindestpunkteModal.scores[exam.id]) * 4"></span>
                                                </template>
                                                {{-- Sport --}}
                                                <template x-if="!exam.isZusatzpruefung && exam.isSport">
                                                    <span class="font-mono text-indigo-700"
                                                          x-text="mpSportContrib(exam.id) !== '—' ? mpSportContrib(exam.id) : '—'"></span>
                                                </template>
                                            </td>

                                            {{-- Reihenfolge-Buttons (nur offene ZP) --}}
                                            <td class="px-1 py-1 text-center no-print">
                                                <template x-if="exam.isZusatzpruefung && exam.targetPoints === null">
                                                    <div class="flex flex-col items-center gap-0.5">
                                                        <button @click="mpMoveSortOrder(ei, -1)"
                                                                :disabled="mpZpIndexOf(ei) === 0"
                                                                class="text-gray-400 hover:text-indigo-600 disabled:opacity-20 disabled:cursor-default text-base leading-none px-1"
                                                                title="Zuerst prüfen">▲</button>
                                                        <button @click="mpMoveSortOrder(ei, +1)"
                                                                :disabled="mpZpIndexOf(ei) === mpZpOpenCount() - 1"
                                                                class="text-gray-400 hover:text-indigo-600 disabled:opacity-20 disabled:cursor-default text-base leading-none px-1"
                                                                title="Später prüfen">▼</button>
                                                    </div>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>

                                    {{-- Summenzeile --}}
                                    <tr class="border-t-2 border-indigo-200 bg-indigo-50/60">
                                        <td colspan="4" class="px-3 py-1.5 text-right text-xs font-semibold text-gray-500">
                                            Σ Abiturprüfung
                                            <template x-if="(computedData?.zpExams?.length ?? 0) > 0">
                                                <span class="font-normal text-gray-400"> (fixe Fächer + schr. ZP als Untergrenze)</span>
                                            </template>
                                            :
                                        </td>
                                        <td class="px-3 py-1.5 text-center font-mono font-bold">
                                            <template x-if="computedData?.allFixedScoresEntered && (computedData?.zpExams?.length ?? 0) === 0 && computedData?.total !== null">
                                                <span :class="computedData.total >= 100 ? 'text-green-700' : 'text-red-600'"
                                                      x-text="computedData.total + ' / 300'"></span>
                                            </template>
                                            <template x-if="computedData?.allFixedScoresEntered && (computedData?.zpExams?.length ?? 0) > 0 && computedData?.fixedTotal !== undefined">
                                                <span class="text-indigo-600"
                                                      x-text="computedData.fixedTotal + ' / 300'"></span>
                                            </template>
                                            <template x-if="!computedData?.allFixedScoresEntered || computedData === null">
                                                <span class="text-gray-300 text-xs">—</span>
                                            </template>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <template x-if="computeLoading">
                            <p class="text-xs text-indigo-400 mt-1.5">⟳ Berechne …</p>
                        </template>
                        <template x-if="!computedData?.allFixedScoresEntered && !computeLoading">
                            <p class="text-xs text-amber-600 mt-1.5">
                                💡 Trage alle Prüfungsnoten ein um die Kriterien zu sehen.
                            </p>
                        </template>
                    </div>

                    {{-- ─── B: 5 Kriterien (Ampel) ──────────────────────── --}}
                    <template x-if="computedData?.criteria">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                Abitur-Voraussetzungen
                                <template x-if="(computedData?.zpExams?.length ?? 0) > 0">
                                    <span class="normal-case font-normal text-gray-400 ml-1">(auf Basis der schriftlichen Noten)</span>
                                </template>
                            </h3>
                            <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="[key, crit] in Object.entries(computedData.criteria)" :key="'crit-'+key">
                                    <div class="flex items-start gap-2 px-3 py-2 rounded-lg border text-xs"
                                         :class="crit.pass === true ? 'bg-green-50 border-green-200' : (crit.pass === false ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200')">
                                        <span class="text-base shrink-0 mt-0.5"
                                              x-text="crit.pass === true ? '✅' : (crit.pass === false ? '❌' : '⚪')"></span>
                                        <div>
                                            <div class="font-semibold"
                                                 :class="crit.pass === true ? 'text-green-800' : (crit.pass === false ? 'text-red-800' : 'text-gray-600')"
                                                 x-text="crit.label"></div>
                                            <div x-show="crit.detail"
                                                 class="mt-0.5"
                                                 :class="crit.pass === false ? 'text-red-600' : 'text-gray-500'"
                                                 x-text="crit.detail"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- N=0: Gesamtergebnis --}}
                            <template x-if="(computedData?.zpExams?.length ?? 0) === 0">
                                <div class="mt-2 px-4 py-2 rounded-lg border text-sm font-semibold"
                                     :class="computedData.pass ? 'bg-green-100 border-green-300 text-green-800' : 'bg-red-100 border-red-300 text-red-800'">
                                    <template x-if="computedData.pass">
                                        <span>✓ Abitur bestanden — Σ = <span x-text="computedData.total"></span> / 300</span>
                                    </template>
                                    <template x-if="!computedData.pass">
                                        <span>✗ Noch nicht bestanden</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- ─── C: Kombinationstabelle ──────────────────────── --}}
                    <template x-if="computedData?.allFixedScoresEntered && (computedData?.zpExams?.length ?? 0) > 0">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    Mündliche Mindestpunkte
                                    <span class="normal-case font-normal text-gray-400 ml-1" x-text="mpCombinationTitle()"></span>
                                </h3>
                                <template x-if="(computedData?.zpExams?.length ?? 0) >= 2">
                                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer select-none no-print">
                                        <input type="checkbox" x-model="hideWorsening"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400">
                                        Verschlechterungen ausblenden
                                    </label>
                                </template>
                            </div>

                            {{-- Bereits erzielte ZP-Noten --}}
                            <template x-if="mindestpunkteModal.exams.some(e => e.isZusatzpruefung && e.targetPoints !== null)">
                                <div class="text-xs text-green-700 bg-green-50 border border-green-200 rounded px-2 py-1 mb-2 flex flex-wrap gap-x-3 gap-y-0.5">
                                    <span class="font-semibold">Bereits erzielt:</span>
                                    <template x-for="e in mindestpunkteModal.exams.filter(e => e.isZusatzpruefung && e.targetPoints !== null)" :key="'tp-'+e.id">
                                        <span x-text="e.subject + '\u00a0mündl.\u00a0' + e.targetPoints + '\u00a0→\u00a0komb.\u00a0' + mpCombinedFor(e) + '\u00a0(×4\u00a0=\u00a0' + mpContribFor(e) + ')'"></span>
                                    </template>
                                </div>
                            </template>

                            {{-- Keine Lösung --}}
                            <template x-if="(computedData?.top5Combinations?.length ?? 0) === 0 && !computeLoading">
                                <p class="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">
                                    ✗ Kein Bestehen möglich — selbst mit 15 Punkten in allen Zusatzprüfungen werden die Voraussetzungen nicht erfüllt.
                                </p>
                            </template>

                            {{-- Kombinationstabelle (N=1 und N≥2 gemeinsam) --}}
                            <template x-if="(computedData?.top5Combinations?.length ?? 0) > 0">
                                <div class="overflow-hidden rounded-lg border border-gray-200">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            {{-- Zeile 1: Fachname (je 2 Spalten) + Σ --}}
                                            <tr>
                                                <template x-for="(zp, zi) in computedData.zpExams" :key="'h1-'+zi">
                                                    <th colspan="2"
                                                        class="px-2 py-1.5 text-center text-gray-700 font-semibold border-r border-gray-200"
                                                        x-text="zp.subject + (zp.courseCode ? ' (' + zp.courseCode + ')' : '')"></th>
                                                </template>
                                                <th class="px-3 py-1.5 text-center text-gray-700 font-semibold w-24 whitespace-nowrap">Σ</th>
                                            </tr>
                                            {{-- Zeile 2: Spaltenüberschriften --}}
                                            <tr class="border-t border-gray-100 text-gray-500 uppercase tracking-wide text-gray-400" style="font-size:10px">
                                                <template x-for="cell in mpHeaderCells()" :key="cell.key">
                                                    <th class="px-2 py-1 text-center font-medium"
                                                        :class="cell.type === 'combo' ? 'border-r border-gray-200' : ''"
                                                        x-text="cell.type === 'oral' ? 'Mündl.' : 'Komb. / Beitrag'"></th>
                                                </template>
                                                <th class="px-3 py-1 text-center font-medium">Pkt.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, ri) in mpFilteredTop5()" :key="'row-'+ri">
                                                <tr class="border-t border-gray-100"
                                                    :class="ri % 2 === 0 ? 'bg-white' : 'bg-gray-50/60'">
                                                    <template x-for="cell in mpRowCells(row)" :key="cell.key">
                                                        <td class="px-2 py-1.5 text-center"
                                                            :class="cell.type === 'oral'
                                                                ? 'font-mono font-bold text-gray-800'
                                                                : 'border-r border-gray-200'">
                                                            {{-- Mündlich --}}
                                                            <template x-if="cell.type === 'oral'">
                                                                <span x-text="cell.oral !== null && cell.oral !== undefined ? cell.oral : '–'"></span>
                                                            </template>
                                                            {{-- Komb. / Beitrag --}}
                                                            <template x-if="cell.type === 'combo' && cell.combined !== null && cell.combined !== undefined && cell.oral !== null">
                                                                <span>
                                                                    <span class="font-mono"
                                                                          :class="mpScoreColor(cell.combined)"
                                                                          x-text="cell.combined"></span>
                                                                    <span class="text-gray-300 mx-0.5">/</span>
                                                                    <span class="font-mono font-semibold text-indigo-600"
                                                                          x-text="cell.contrib"></span>
                                                                    <template x-if="cell.written !== null && cell.written !== undefined">
                                                                        <span class="font-mono text-green-600 ml-0.5"
                                                                              x-text="'(' + (cell.contrib - cell.written * 4 >= 0 ? '+' : '') + (cell.contrib - cell.written * 4) + ')'"></span>
                                                                    </template>
                                                                </span>
                                                            </template>
                                                            <template x-if="cell.type === 'combo' && (cell.oral === null || cell.oral === undefined)">
                                                                <span class="text-gray-400 italic">nicht nötig</span>
                                                            </template>
                                                        </td>
                                                    </template>
                                                    {{-- Σ --}}
                                                    <td class="px-3 py-1.5 text-center font-mono font-bold text-green-700 whitespace-nowrap"
                                                        x-text="row.total !== null ? row.total + ' / 300' : '?'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <p class="px-3 py-1 text-xs text-gray-400 border-t border-gray-100">
                                        Bis zu 5 wahrscheinlichste Kombinationen (mündliche Note nahe der schriftlichen). „–" = ZP nicht nötig.
                                    </p>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- ─── D: Anmeldung — Streichung & Schwerpunkt ────── --}}
                    <template x-if="mindestpunkteModal.pruefungen && mindestpunkteModal.pruefungen.length > 0">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Anmeldung &ndash; Streichung &amp; Schwerpunkt
                                <span class="normal-case font-normal text-gray-400">(für Anmeldezettel und Fahrplan)</span>
                            </h3>
                            <div class="overflow-hidden rounded-lg border border-amber-200">
                                <table class="w-full text-xs">
                                    <thead class="bg-amber-50 text-gray-500 uppercase tracking-wide border-b border-amber-200" style="font-size:10px">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left font-medium">Fach</th>
                                            <th class="px-3 py-1.5 text-left font-medium w-48">Gestrichenes Sem. / Stoffgebiet</th>
                                            <th class="px-3 py-1.5 text-left font-medium w-44">Schwerpunktsemester</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="p in mindestpunkteModal.pruefungen" :key="'anm-'+p.pruefungId">
                                            <tr class="border-t border-amber-100">
                                                <td class="px-3 py-1.5 font-medium text-gray-800" x-text="p.subject + (p.courseCode ? ' (' + p.courseCode + ')' : '')"></td>
                                                <td class="px-2 py-1">
                                                    <select x-model="p.excluded_semester"
                                                            @change="mpSaveAnmeldung(p)"
                                                            class="w-full text-xs border-gray-300 rounded focus:ring-amber-400 focus:border-amber-400 py-0.5">
                                                        <option value="">&#8212; nicht festgelegt &#8212;</option>
                                                        <option value="stochastik" x-show="p.isMathematik">Stochastik</option>
                                                        <option value="geometrie"  x-show="p.isMathematik">Geometrie</option>
                                                        <option value="12_1" x-show="!p.isMathematik">AA 12/1</option>
                                                        <option value="12_2" x-show="!p.isMathematik">AA 12/2</option>
                                                    </select>
                                                    <span x-show="!p.excluded_semester" class="text-amber-600 text-xs">&#9888; Noch nicht gewählt</span>
                                                </td>
                                                <td class="px-2 py-1">
                                                    <template x-if="!p.isMathematik">
                                                        <div>
                                                            <select x-model="p.focus_semester"
                                                                    @change="mpSaveAnmeldung(p)"
                                                                    class="w-full text-xs border-gray-300 rounded focus:ring-amber-400 focus:border-amber-400 py-0.5">
                                                                <option value="">&#8212; nicht festgelegt &#8212;</option>
                                                                <option value="12_1" x-show="p.excluded_semester !== '12_1'">AA 12/1</option>
                                                                <option value="12_2" x-show="p.excluded_semester !== '12_2'">AA 12/2</option>
                                                                <option value="13_1">AA 13/1</option>
                                                                <option value="13_2">AA 13/2</option>
                                                            </select>
                                                            <span x-show="p.excluded_semester && !p.focus_semester" class="text-amber-600 text-xs">&#9888; Noch nicht gewählt</span>
                                                        </div>
                                                    </template>
                                                    <template x-if="p.isMathematik">
                                                        <span class="text-xs text-gray-400 italic">&#8212; (Mathematik)</span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                </div>
            </template>

        </div>{{-- /overflow-y-auto --}}

        {{-- ─── Footer ──────────────────────────────────────────────── --}}
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 shrink-0 flex items-center justify-between no-print">
            <p class="text-xs text-gray-400">Noten werden automatisch gespeichert.</p>
            <div class="flex gap-2">
                <button @click="mpPrintMindestpunkte()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                    <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Mindestpunkte drucken
                </button>
                <button @click="mpOpenKomplettPdf()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Fahrplan + Anmeldezettel (PDF)
                </button>
                <button @click="mindestpunkteModal.show = false"
                        class="px-3 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded">
                    Schließen
                </button>
            </div>
        </div>

    </div>
</div>
