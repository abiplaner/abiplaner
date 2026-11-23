/**
 * mindestpunkte.js
 * Alpine.js-Hilfsfunktionen für den Mindestpunkte-Kalkulator (GSO §61)
 *
 * Alle Funktionen tragen den Prefix "mp" um Kollisionen mit anderen
 * Alpine-Methoden im Index zu vermeiden.
 *
 * Formel (Anlage 12 zu §52 GSO):
 *   Abiturbeitrag (4-fach, 0–60): P = round((2s + m) × 4 / 3)
 *   Kombinierte Note (0–15):      K = round((2s + m) / 3)   [nur Anzeige]
 */

/**
 * Mixin-Objekt: wird in x-data des Haupt-Components gemergt.
 * Verwendung in index.blade.php:
 *   x-data="{ ...mpMixin(), ...restData }"
 */
function mpMixin() {
    return {

        // ─── Formeln ────────────────────────────────────────────────

        /** Abiturbeitrag (4-fache Wertung, 0–60) für ein ZP-Fach. */
        mpCalcZpContrib(written, oral) {
            if (written === null || written === undefined || oral === null || oral === undefined) return null;
            return Math.round((2 * Number(written) + Number(oral)) * 4 / 3);
        },

        /** Kombinierte Note (0–15) für ein ZP-Fach — NUR zur Anzeige. */
        mpCalcCombined(written, oral) {
            if (written === null || written === undefined || oral === null || oral === undefined) return null;
            const raw = (2 * Number(written) + Number(oral)) / 3;
            return raw < 1 ? 0 : Math.round(raw);
        },

        /**
         * Abiturbeitrag (4-fache Wertung, 0–60) für Sport schriftlich + ZP.
         * Amtliche Formel (§ 52 Abs. 2 Nr. 2 GSO, schulinterner Leitfaden
         * Nr. 2.3.2.2): P = round((praxis + theorieSchriftlich + muendlich) × 4 / 3).
         * NICHT über mpCalcZpContrib() berechenbar — dort wird written verdoppelt
         * (2×written), hier ist die volle, nicht verdoppelte Summe aus zwei
         * Komponenten (Praxis + Theorie) gemeint. Siehe PHP-Pendant
         * MindestpunkteService::calcSportZpContribution().
         */
        mpCalcSportZpContrib(praxis, written, oral) {
            if (praxis === null || praxis === undefined || written === null || written === undefined
                || oral === null || oral === undefined) return null;
            return Math.round((Number(praxis) + Number(written) + Number(oral)) * 4 / 3);
        },

        /**
         * Kombinierte Note (0–15) für Sport schriftlich + ZP — NUR zur Anzeige.
         * round((praxis + theorie + muendlich) / 3). Siehe PHP-Pendant
         * MindestpunkteService::calcSportZpCombined().
         */
        mpCalcSportZpCombined(praxis, written, oral) {
            if (praxis === null || praxis === undefined || written === null || written === undefined
                || oral === null || oral === undefined) return null;
            const raw = (Number(praxis) + Number(written) + Number(oral)) / 3;
            return raw < 1 ? 0 : Math.round(raw);
        },

        /**
         * Kombinierte Note (0–15) für ein ZP-Fach — NUR zur Anzeige.
         * Wählt automatisch die Sport-ZP-Formel (mpCalcSportZpCombined), wenn
         * exam.isSport zutrifft, sonst die normale ZP-Formel (mpCalcCombined).
         * Generischer Wrapper, um die isSport-Verzweigung nicht an jeder
         * Anzeigestelle im Template wiederholen zu müssen.
         */
        mpCombinedFor(exam) {
            if (exam.isSport) {
                return this.mpCalcSportZpCombined(exam.sportPracticalGrade, exam.writtenPoints, exam.targetPoints);
            }
            return this.mpCalcCombined(exam.writtenPoints, exam.targetPoints);
        },

        /**
         * Abiturbeitrag (4-fache Wertung) für ein ZP-Fach — automatische
         * Auswahl Sport-ZP- vs. normale Formel, analog zu mpCombinedFor().
         */
        mpContribFor(exam) {
            if (exam.isSport) {
                return this.mpCalcSportZpContrib(exam.sportPracticalGrade, exam.writtenPoints, exam.targetPoints);
            }
            return this.mpCalcZpContrib(exam.writtenPoints, exam.targetPoints);
        },

        /** Abiturbeitrag Sport-Nicht-ZP: (sportScore + practicalGrade) × 2 */
        mpSportContrib(examId) {
            const s  = this.mindestpunkteModal.scores[examId];
            const pg = this.mindestpunkteModal.practicalGrades
                     ? this.mindestpunkteModal.practicalGrades[examId]
                     : undefined;
            if (s === null || s === undefined || s === '' ||
                pg === null || pg === undefined || pg === '') return '—';
            return (parseInt(s) + parseInt(pg)) * 2;
        },

        /** Tailwind-Farbklasse je Punktzahl (0–15). */
        mpScoreColor(score) {
            if (score === null || score === undefined) return 'text-gray-400';
            const s = Number(score);
            if (s >= 5)  return 'text-green-700';
            if (s >= 4)  return 'text-amber-600';
            if (s >= 1)  return 'text-orange-500';
            return 'text-red-600 font-semibold';
        },

        // ─── Verbesserungs-Modus ────────────────────────────────────

        /**
         * Gibt für jede kombinierte Stufe > schriftlich die kleinste
         * Mündlichnote zurück, die diese Stufe erstmals erreicht.
         *
         * Ist auf der Zusatzprüfung eine Punktedifferenz aus dem
         * Notenbescheid (improvementPointsNeeded) hinterlegt, werden nur
         * Zeilen zurückgegeben, deren Δ (×4-Beitrag ggü. schriftlich)
         * dieses Ziel tatsächlich erreicht oder übertrifft — Zeilen, die
         * dem Schüler nichts bringen, werden nicht angezeigt.
         */
        mpImprovementRows(written, pointsNeeded) {
            if (written === null || written === undefined) return [];
            const w = Number(written);
            const minDelta = (pointsNeeded !== null && pointsNeeded !== undefined && pointsNeeded !== '')
                ? Number(pointsNeeded) : null;
            const rows = [];
            for (let target = w + 1; target <= 15; target++) {
                for (let oral = 0; oral <= 15; oral++) {
                    const raw      = (2 * w + oral) / 3;
                    const combined = raw < 1 ? 0 : Math.round(raw);
                    if (combined === target) {
                        const contrib = Math.round((2 * w + oral) * 4 / 3);
                        const delta   = contrib - w * 4;
                        if (minDelta === null || delta >= minDelta) {
                            rows.push({ minOral: oral, combined: target, contrib, delta });
                        }
                        break;
                    }
                }
            }
            return rows;
        },

        // ─── Sortierung & Reihenfolge ────────────────────────────────

        /** exams mit korrekt sortierten offenen ZP-Fächern. */
        mpSortedExams() {
            const exams = this.mindestpunkteModal.exams;
            if (!exams || exams.length === 0) return [];
            const result        = [...exams];
            const openPositions = [];
            const openExams     = [];
            result.forEach((e, i) => {
                if (e.isZusatzpruefung && e.targetPoints === null) {
                    openPositions.push(i);
                    openExams.push(e);
                }
            });
            const anyManual = openExams.some(e => (e.sort_order ?? 0) > 0);
            if (anyManual) {
                openExams.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
            }
            openPositions.forEach((pos, i) => { result[pos] = openExams[i]; });
            return result;
        },

        mpZpOpenExams() {
            return this.mpSortedExams().filter(e => e.isZusatzpruefung && e.targetPoints === null);
        },

        mpZpOpenCount() {
            return this.mpZpOpenExams().length;
        },

        mpZpIndexOf(ei) {
            const exam = this.mpSortedExams()[ei];
            return this.mpZpOpenExams().findIndex(e => e.id === exam.id);
        },

        async mpMoveSortOrder(ei, delta) {
            const openExams = this.mpZpOpenExams();
            const exam      = this.mpSortedExams()[ei];
            const pos       = openExams.findIndex(e => e.id === exam.id);
            const newPos    = pos + delta;
            if (newPos < 0 || newPos >= openExams.length) return;

            const swapWith = openExams[newPos];
            const orderA   = newPos + 1;
            const orderB   = pos + 1;

            const allExams = this.mindestpunkteModal.exams;
            const idxA     = allExams.findIndex(e => e.id === exam.id);
            const idxB     = allExams.findIndex(e => e.id === swapWith.id);
            if (idxA === -1 || idxB === -1) return;

            allExams[idxA] = { ...allExams[idxA], sort_order: orderA };
            allExams[idxB] = { ...allExams[idxB], sort_order: orderB };
            this.mindestpunkteModal.exams = [...allExams];

            const sortStore = Alpine.store('zpSortOrders');
            if (exam.pruefungId)     sortStore.set(exam.pruefungId,     orderA);
            if (swapWith.pruefungId) sortStore.set(swapWith.pruefungId, orderB);

            const token   = document.querySelector('meta[name="csrf-token"]').content;
            const persist = async (pruefungId, order) => {
                if (!pruefungId) return;
                await fetch(`/zusatzpruefungen/${pruefungId}/sort-order`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ sort_order: order }),
                });
            };
            await Promise.all([
                persist(exam.pruefungId,     orderA),
                persist(swapWith.pruefungId, orderB),
            ]);
            this.mpRecompute();
        },

        // ─── Kombinationstabelle ─────────────────────────────────────

        mpCombinationTitle() {
            const n = this.computedData?.zpExams?.length ?? 0;
            if (n === 0) return '— alle ZP abgeschlossen';
            if (n === 1) return '— Mindest-Mündlichnote';
            return '— bis zu 5 wahrscheinlichste Kombinationen';
        },

        mpIsWorsening(row) {
            const zpOpen = this.mpZpOpenExams();
            for (let i = 0; i < zpOpen.length; i++) {
                const written  = zpOpen[i]?.writtenPoints ?? null;
                const combined = row.combined?.[i] ?? null;
                if (written === null || combined === null) continue;
                if (combined < written) return true;
            }
            return false;
        },

        mpFilteredTop5() {
            const rows = this.computedData?.top5Combinations ?? [];
            if (!this.hideWorsening) return rows;
            const filtered = rows.filter(r => !this.mpIsWorsening(r));
            return filtered.length > 0 ? filtered : rows;
        },

        /**
         * Flaches Zellen-Array für die Spaltenüberschriften-Zeile (Zeile 2)
         * der Kombinationstabelle: [oral_1, combo_1, oral_2, combo_2, ...].
         * Gleicher Grund wie bei mpRowCells() — Alpine x-for erlaubt nur
         * EIN Wurzelelement pro Iteration, daher kein "zwei <th> als
         * Geschwister"-Template.
         */
        mpHeaderCells() {
            const zpExams = this.computedData?.zpExams ?? [];
            const cells = [];
            zpExams.forEach((zp, zi) => {
                cells.push({ key: 'h-oral-'  + zi, type: 'oral'  });
                cells.push({ key: 'h-combo-' + zi, type: 'combo' });
            });
            return cells;
        },

        /**
         * Flaches Zellen-Array für EINE Kombinationszeile, in der Reihenfolge
         * [oral_1, combo_1, oral_2, combo_2, ...] passend zu den Spalten im
         * Tabellenkopf (je ZP-Fach: "Mündl." + "Komb./Beitrag", colspan=2).
         * Notwendig, weil Alpine x-for nur EIN Wurzelelement pro Iteration
         * zulässt — zwei <td> als Geschwister im selben <template x-for>
         * würden dazu führen, dass nur die erste Spalte korrekt wiederholt wird.
         */
        mpRowCells(row) {
            const zpExams = this.computedData?.zpExams ?? [];
            const cells = [];
            zpExams.forEach((zp, zi) => {
                cells.push({
                    key:      'oral-' + zi,
                    type:     'oral',
                    oral:     row.oral?.[zi] ?? null,
                });
                cells.push({
                    key:      'combo-' + zi,
                    type:     'combo',
                    oral:     row.oral?.[zi] ?? null,
                    combined: row.combined?.[zi] ?? null,
                    contrib:  row.contrib?.[zi] ?? null,
                    written:  zp.writtenPoints ?? null,
                });
            });
            return cells;
        },

        // ─── § 62: Hilfsgriffe für die Eingabefelder ─────────────────

        /**
         * Die Prüfung eines externen Bewerbers aus der Fächerliste des Modals,
         * geschlüsselt auf student_exam_id. Die § 62-Rechnung selbst kennt
         * diesen Schlüssel nicht — sie rechnet über die Positionen 1–8 der
         * Anlage 14 —, die Eingabefelder brauchen ihn aber.
         */
        mp62Exam(studentExamId) {
            if (studentExamId === null || studentExamId === undefined) return null;
            return (this.mindestpunkteModal.exams ?? [])
                .find(e => e.id === studentExamId) ?? null;
        },

        /** Das gerechnete Fach zu einer Position 1–8. */
        mp62Bewertet(position) {
            return (this.computedData62?.ergebnis?.rechnung?.faecher ?? [])
                .find(f => f.position === position) ?? null;
        },

        // ─── recompute ───────────────────────────────────────────────

        mpRecompute() {
            if (this._computeTimer) clearTimeout(this._computeTimer);
            this._computeTimer = setTimeout(async () => {
                const m = this.mindestpunkteModal;
                if (!m.show || m.exams.length === 0) return;

                // Externe Bewerber bestehen nach § 62. Dort rechnet der Server
                // vollständig: die Positionen 1–8 der Anlage 14 stehen im
                // Datenmodell, und an ihnen hängen Faktoren, Niveauregeln und
                // beide Bestehenstatbestände. Der Browser schickt deshalb nur
                // den Prüflingsschlüssel und bekommt das fertige Ergebnis —
                // keine dritte Kopie der Formeln.
                if (m.paragraph === '62') {
                    this.computeLoading = true;
                    try {
                        const res = await fetch(
                            `/zusatzpruefungen/pruefling/${m.studentId}/mindestpunkte-62`
                        );
                        // Anders als früher wird ein Fehler hier nicht mehr
                        // verschluckt: ein stumm abgebrochenes fetch() hat schon
                        // einmal einen unsichtbaren Datenverlust erzeugt.
                        if (res.ok) {
                            this.computedData62 = await res.json();
                            this.computeError = null;
                        } else {
                            this.computedData62 = null;
                            this.computeError = `Die § 62-Rechnung konnte nicht geladen werden (HTTP ${res.status}).`;
                        }
                    } catch (e) {
                        this.computedData62 = null;
                        this.computeError = 'Die § 62-Rechnung konnte nicht geladen werden.';
                    } finally {
                        this.computeLoading = false;
                    }
                    return;
                }

                const exams = this.mpSortedExams().map(e => {
                    if (e.isZusatzpruefung) return e;
                    const s     = m.scores[e.id];
                    const score = (s !== null && s !== undefined && s !== '') ? parseInt(s) : null;
                    if (e.isSport) {
                        const pg    = m.practicalGrades ? m.practicalGrades[e.id] : undefined;
                        const pgInt = (pg !== null && pg !== undefined && pg !== '') ? parseInt(pg) : null;
                        return { ...e, sportScore: score, sportPracticalGrade: pgInt, score: null };
                    }
                    return { ...e, score };
                });

                this.computeLoading = true;
                try {
                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    const route = document.querySelector('meta[name="mp-compute-url"]')?.content
                                ?? '/zusatzpruefungen/mindestpunkte/compute';
                    const res   = await fetch(route, {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body:    JSON.stringify({ exams }),
                    });
                    if (res.ok) this.computedData = await res.json();
                } finally {
                    this.computeLoading = false;
                }
            }, 300);
        },

        // ─── Autosave ────────────────────────────────────────────────

        /**
         * Lädt die Modal-Daten (Fächer, Punkte, sort_order, curTotal, ...) frisch
         * vom Server und befüllt das offene Modal komplett neu — analog zum
         * Schließen/Wiederöffnen. Wird nach jeder Punkte-Änderung aufgerufen,
         * damit Summenanzeige, Kriterien-Ampeln und Top-5-Tabelle konsistent
         * bleiben, statt jeden reaktiven Pfad einzeln synchron halten zu müssen.
         */
        async mpReloadModal() {
            const pruefling = this.mindestpunkteModal.studentId;
            if (!pruefling || !this.mindestpunkteModal.show) return;
            const res = await fetch(`/zusatzpruefungen/pruefling/${pruefling}/mindestpunkte-modal-data`);
            if (!res.ok) return;
            const data = await res.json();
            this.openMindestpunkteModal(data);
        },

        async mpSaveWrittenPoints(exam) {
            if (!exam.pruefungId) return;
            const score = (exam.writtenPoints !== null && exam.writtenPoints !== undefined && exam.writtenPoints !== '')
                ? parseInt(exam.writtenPoints) : null;
            const token = document.querySelector('meta[name="csrf-token"]').content;
            await fetch(`/zusatzpruefungen/${exam.pruefungId}/points`, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body:    JSON.stringify({ written_points: score }),
            });
            Alpine.store('zpPoints').update(exam.pruefungId, 'written', score);
            const pruefling = this.mindestpunkteModal.studentId;
            if (pruefling && exam.id) {
                await fetch(`/zusatzpruefungen/pruefling/${pruefling}/exam-score`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body:    JSON.stringify({ student_exam_id: exam.id, score }),
                });
            }
            this.mpReloadModal();
        },

        /** Mündliche ZP-Note (target_points) speichern. Jederzeit editierbar/korrigierbar. */
        async mpSaveTargetPoints(exam) {
            if (!exam.pruefungId) return;
            const score = (exam.targetPoints !== null && exam.targetPoints !== undefined && exam.targetPoints !== '')
                ? parseInt(exam.targetPoints) : null;
            const token = document.querySelector('meta[name="csrf-token"]').content;
            await fetch(`/zusatzpruefungen/${exam.pruefungId}/points`, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body:    JSON.stringify({ target_points: score }),
            });
            Alpine.store('zpPoints').update(exam.pruefungId, 'target', score);
            this.mpReloadModal();
        },

        async mpSaveExamScore(examId) {
            const s         = this.mindestpunkteModal.scores[examId];
            const pruefling = this.mindestpunkteModal.studentId;
            if (!pruefling) return;
            const token = document.querySelector('meta[name="csrf-token"]').content;
            await fetch(`/zusatzpruefungen/pruefling/${pruefling}/exam-score`, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body:    JSON.stringify({
                    student_exam_id: examId,
                    score: (s !== '' && s !== null && s !== undefined) ? parseInt(s) : null,
                }),
            });
            this.mpRecompute();
        },

        async mpSavePracticalGrade(examId) {
            const pg        = this.mindestpunkteModal.practicalGrades
                            ? this.mindestpunkteModal.practicalGrades[examId] : undefined;
            const pruefling = this.mindestpunkteModal.studentId;
            if (!pruefling) return;
            const token = document.querySelector('meta[name="csrf-token"]').content;
            await fetch(`/zusatzpruefungen/pruefling/${pruefling}/exam-score`, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body:    JSON.stringify({
                    student_exam_id: examId,
                    practical_grade: (pg !== '' && pg !== null && pg !== undefined) ? parseInt(pg) : null,
                }),
            });
            this.mpRecompute();
        },

        // ─── Anmeldung (Streichung / Schwerpunkt) ────────────────────

        async mpSaveAnmeldung(p) {
            if (!p.pruefungId) return;
            if (p.excluded_semester && p.focus_semester && p.excluded_semester === p.focus_semester) {
                p.focus_semester = '';
                Alpine.store('zpToast').show('Gestrichenes und Schwerpunktsemester dürfen nicht identisch sein.', true);
                return;
            }
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const resp  = await fetch(`/zusatzpruefungen/${p.pruefungId}/anmeldung`, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body:    JSON.stringify({
                    excluded_semester: p.excluded_semester || null,
                    focus_semester:    p.focus_semester    || null,
                }),
            });
            if (!resp.ok) {
                const data = await resp.json().catch(() => ({}));
                Alpine.store('zpToast').show(data.error ?? 'Fehler beim Speichern.', true);
            }
        },

        // ─── Exporte ─────────────────────────────────────────────────

        mpOpenKomplettPdf() {
            const pruefling = this.mindestpunkteModal.studentId;
            if (pruefling) window.open(`/zusatzpruefungen/export/komplett-pdf?pruefling=${pruefling}`, '_blank');
        },

        mpPrintMindestpunkte() {
            const body = document.getElementById('zp-mindest-print-body');
            if (!body) return;
            const header = document.getElementById('zp-print-header');
            if (header) header.style.display = 'block';
            body.querySelectorAll('input[type="number"]').forEach(inp => {
                inp.setAttribute('value', inp.value ?? '');
            });
            const win = window.open('', '_blank');
            win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Mindestpunkte – ${this.mindestpunkteModal.studentName}</title>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 11px; margin: 12mm; color: #111; }
svg { display: none !important; }
h1 { font-size: 13px; font-weight: bold; margin: 0 0 2px; }
p { font-size: 9px; color: #555; margin: 0 0 4px; }
h3 { font-size: 11px; font-weight: 600; margin: 10px 0 3px; border-bottom: 1px solid #d1d5db; padding-bottom: 2px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
th, td { border: 1px solid #d1d5db; padding: 3px 6px; font-size: 10px; }
th { background: #f3f4f6; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
.font-mono { font-family: monospace; }
.font-semibold { font-weight: 600; }
.text-green-700 { color: #15803d; }
.text-red-600 { color: #dc2626; }
.text-amber-600 { color: #d97706; }
.text-orange-500 { color: #f97316; }
.text-gray-400, .text-gray-500 { color: #6b7280; }
.text-indigo-700, .text-indigo-600 { color: #4338ca; }
.no-print, button, label input[type="checkbox"] { display: none !important; }
@page { margin: 12mm; }
</style>
</head><body>${body.innerHTML}</body></html>`);
            win.document.close();
            if (header) header.style.display = 'none';
            win.focus();
            setTimeout(() => { win.print(); }, 400);
        },

    }; // end mpMixin
}
