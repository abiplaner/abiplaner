<?php

namespace App\Services;

/**
 * Mindestpunkte-Kalkulator nach GSO §61 / Anlage 12 zu §52
 *
 * KANONISCHE Implementierung der Berechnungslogik.
 * Wird von:
 *  - ZusatzpruefungController::computeMindestpunkte()   (AJAX-Endpunkt für das Modal)
 *  - ZusatzpruefungController::exportMindestpunktePdf() (PDF-Export)
 *
 * ═══════════════════════════════════════════════════════════════════
 * FORMELN (Anlage 12 zu §52 GSO)
 * ═══════════════════════════════════════════════════════════════════
 *
 * Abiturbeitrag eines Prüfungsfachs (0–60, 4-fache Wertung):
 *   Normal-ZP:  P = round((2s + m) × 4 / 3)
 *   Sport-ZP:   P = round((2s + m) × 4 / 3)   ← identische Formel!
 *               s = Sporttheorie-Prüfungsleistung (0–15)
 *               m = mündliche Zusatzprüfung (0–15)
 *
 * Anzuzeigende kombinierte Note (0–15):
 *   Komb = round((2s + m) / 3)    (nur zur Anzeige, nie für Summen)
 *
 * Nicht-ZP-Fächer:
 *   Normal:  Abiturbeitrag = score × 4
 *   Sport:   Abiturbeitrag = (score + practicalGrade) × 2  (max. 60)
 *            (Berechnung bleibt im Controller / im Frontend, hier
 *             kommt der bereits berechnete effektive 0–15-Wert an)
 *
 * ═══════════════════════════════════════════════════════════════════
 * DATENFLUSS
 * ═══════════════════════════════════════════════════════════════════
 *
 * Der Service erhält ein $exams-Array. Jedes Element hat:
 *   id, subject, isZusatzpruefung, isDeutsch, isMathematik,
 *   isLeistungsfach, isForeignLanguage, isScience, isSport,
 *   level, aufgabenfeld, writtenPoints (0–15 | null),
 *   targetPoints (0–15 | null),   ← bereits erzielte mündl. Note
 *   score (0–15 | null),          ← für Nicht-ZP-Fächer (ohne Sport)
 *   sportScore (0–15 | null),     ← Theorie-Note für Sport (nicht-ZP)
 *   sportPracticalGrade (0–15 | null)
 *
 * Für Sport-Nicht-ZP gilt:
 *   Abiturbeitrag = (sportScore + sportPracticalGrade) × 2
 *   Die Logik liegt in scoreContribution().
 */
class MindestpunkteService
{
    // ─── Kern-Formel ────────────────────────────────────────────────

    /**
     * Abiturbeitrag (4-fache Wertung, 0–60) für ein ZP-Fach.
     * Anlage 12 zu §52 GSO: P = round((2s + m) × 4 / 3)
     * Gilt für alle ZP-Fächer inkl. Sport.
     */
    public function calcZpContribution(int $written, int $oral): int
    {
        return (int) round((2 * $written + $oral) * 4 / 3);
    }

    /**
     * Kombinierte Note (0–15) eines ZP-Fachs zur Anzeige.
     * round((2s + m) / 3), Ergebnis < 1 → 0.
     * NUR für die Anzeige verwenden, NIE für Summenberechnungen.
     */
    public function calcCombined(int $written, int $oral): int
    {
        $raw = (2 * $written + $oral) / 3;
        return $raw < 1 ? 0 : (int) round($raw);
    }

    /**
     * Abiturbeitrag (4-fache Wertung, 0–60) für Sport schriftlich + ZP.
     *
     * Amtliche Herleitung (§ 52 Abs. 2 Nr. 2 GSO, schulinterner Leitfaden
     * Nr. 2.3.2.2): schriftlicher Teil = (Praxis + Theorie) × 4, ZP-Teil =
     * mündlich × 4, beide addiert und durch 3 geteilt:
     *   P = ((praxis + theorie) × 4 + muendlich × 4) / 3
     *     = (praxis + theorie + muendlich) × 4 / 3
     * WICHTIG: NICHT über calcZpContribution(praxis+theorie, muendlich)
     * delegierbar — das würde (praxis+theorie) fälschlich nochmal verdoppeln
     * (calcZpContribution rechnet 2×written, hier ist aber bereits die volle,
     * nicht verdoppelte Summe aus zwei Komponenten gemeint). Eigene Formel
     * ohne den Faktor 2.
     */
    public function calcSportZpContribution(int $praxis, int $theorieSchriftlich, int $muendlich): int
    {
        return (int) round(($praxis + $theorieSchriftlich + $muendlich) * 4 / 3);
    }

    /**
     * Kombinierte Note (0–15) für Sport schriftlich + ZP zur Anzeige.
     * round((praxis + theorie + muendlich) / 3), Ergebnis < 1 → 0.
     * NUR für die Anzeige verwenden, NIE für Summenberechnungen.
     * (Analog zu calcCombined(), aber mit echter Summe aus Praxis+Theorie
     * statt 2×Theorie.)
     */
    public function calcSportZpCombined(int $praxis, int $theorieSchriftlich, int $muendlich): int
    {
        $raw = ($praxis + $theorieSchriftlich + $muendlich) / 3;
        return $raw < 1 ? 0 : (int) round($raw);
    }

    // calcImprovementMinOral() ist am 31.08.2026 entfallen.
    //
    // Sie beantwortete die Umkehrung der Verbesserungsfrage — „welche
    // mündliche Note wäre nötig gewesen" — und kannte den Sportfall nicht:
    // Dort ist weder der Beitrag mit Zusatzprüfung noch die Bezugsgröße ohne
    // sie dieselbe, und die Praxisnote war gar kein Parameter. Seit dem
    // 27.08.2026 fragt ExamBoardMinutesService::improvementGoalReached() die
    // tatsächlich erreichte Verbesserung über Zusatzpruefung::getPointsDelta(),
    // und die kennt den Sonderfall. Danach hatte die Methode keinen
    // Produktivaufruf mehr — nur noch einen Test, der sie am Leben hielt.

    /**
     * Abiturbeitrag (4-fache Wertung) für ein Nicht-ZP-Fach.
     *   Sport:   (score + practicalGrade) × 2  (max. 60)
     *   Andere:  score × 4
     *
     * @param array{isSport?: bool, score?: int|null,
     *              sportScore?: int|null, sportPracticalGrade?: int|null} $exam
     */
    public function scoreContribution(array $exam): ?int
    {
        if ($exam['isSport'] ?? false) {
            $s  = $exam['sportScore'] ?? $exam['score'] ?? null;
            $pg = $exam['sportPracticalGrade'] ?? null;
            if ($s === null || $pg === null) return null;
            return ((int) $s + (int) $pg) * 2;
        }
        $s = $exam['score'] ?? null;
        return $s !== null ? (int) $s * 4 : null;
    }

    /**
     * Beitrag (×4) und kombinierte Anzeige-Note (0–15) für ein einzelnes
     * ZP-Fach bei einer angenommenen mündlichen Note $oral.
     *
     * Berücksichtigt den Sport-ZP-Sonderfall (Praxisnote fließt zusätzlich
     * zur Theorienote ein, siehe calcSportZpContribution/calcSportZpCombined).
     * Für alle anderen ZP-Fächer identisch zu calcZpContribution/calcCombined
     * mit writtenPoints als $written.
     *
     * Wird in computeCombinations() für die "Was brauche ich mündlich"-Tabelle
     * verwendet, NICHT für die Bestehensprüfung selbst (die läuft über
     * checkReqs() → contribution()/effectiveScore()).
     *
     * @param array{isSport?: bool, writtenPoints?: int|null,
     *              sportPracticalGrade?: int|null} $exam
     * @return array{0: int, 1: int} [contrib4, combined]
     */
    private function contribAndCombinedFor(array $exam, int $oral): array
    {
        $written = (int) ($exam['writtenPoints'] ?? 0);
        if ($exam['isSport'] ?? false) {
            $praxis = (int) ($exam['sportPracticalGrade'] ?? 0);
            return [
                $this->calcSportZpContribution($praxis, $written, $oral),
                $this->calcSportZpCombined($praxis, $written, $oral),
            ];
        }
        return [
            $this->calcZpContribution($written, $oral),
            $this->calcCombined($written, $oral),
        ];
    }

    // minOralFor() ist am 31.08.2026 entfallen — zusammen mit dem einzigen
    // Zweck, den sie noch hatte.
    //
    // Sie gab die kleinste mündliche Note, mit der ein ZP-Fach einen
    // bestimmten Beitrag erreicht, und kannte den Sportfall nicht. Ihr
    // einziger Produktivaufruf war der zweite Sortierschlüssel für die
    // Reihenfolge der Zusatzprüfungen eines Prüflings
    // (ZusatzpruefungController) — „bei Gleichstand die schlechtere Aussicht
    // zuerst". Der konnte nie etwas bewirken: minOralFor(w, 1) hing allein
    // von w ab, und innerhalb einer Gleichstandsgruppe ist w dasselbe.
    //
    // Sortiert wird jetzt dort wie hier allein nach der schriftlichen Note.
    // Wer die Frage „was brauche ich mündlich mindestens" wirklich stellen
    // will, bekommt sie von computeCombinations() beantwortet — dort mit
    // Sportfall, weil contribAndCombinedFor() ihn kennt.

    // ─── Effektiver Beitrag eines einzelnen Fachs ───────────────────

    /**
     * Abiturbeitrag (4-facher Wert, 0–60) für ein Fach in einer konkreten
     * Situation (oralMap enthält mündliche Noten für offene ZP-Fächer).
     *
     * @param array<int, int> $oralMap  exam.id → mündliche Note
     */
    private function contribution(array $exam, array $oralMap): ?int
    {
        if (($exam['isZusatzpruefung'] ?? false) && ($exam['isSport'] ?? false)) {
            $inputs = $this->sportZpInputs($exam, $oralMap);
            if ($inputs === null) return null;
            return $this->calcSportZpContribution($inputs[0], $inputs[1], $inputs[2]);
        }
        if ($exam['isZusatzpruefung'] ?? false) {
            $inputs = $this->zpInputs($exam, $oralMap);
            if ($inputs === null) return null;
            return $this->calcZpContribution($inputs[0], $inputs[1]);
        }
        return $this->scoreContribution($exam);
    }

    /**
     * Liest praktische Note, theoretische Punkte und mündliche Note für eine
     * Sport-Zusatzprüfung aus dem Prüfungs-Array. Gibt null zurück, wenn
     * eines der drei Felder (noch) fehlt.
     *
     * @param array<int, int> $oralMap
     * @return array{0: int, 1: int, 2: int}|null  [praxis, theorie, oral]
     */
    private function sportZpInputs(array $exam, array $oralMap): ?array
    {
        $theorie = $exam['writtenPoints'] ?? null;
        $praxis  = $exam['sportPracticalGrade'] ?? null;
        $oral    = $exam['targetPoints'] ?? ($oralMap[$exam['id']] ?? null);
        if ($theorie === null || $praxis === null || $oral === null) return null;
        return [(int) $praxis, (int) $theorie, (int) $oral];
    }

    /**
     * Liest schriftliche Punkte und mündliche Note für eine (nicht-Sport-)
     * Zusatzprüfung aus dem Prüfungs-Array. Gibt null zurück, wenn eines der
     * beiden Felder (noch) fehlt.
     *
     * @param array<int, int> $oralMap
     * @return array{0: int, 1: int}|null  [written, oral]
     */
    private function zpInputs(array $exam, array $oralMap): ?array
    {
        $written = $exam['writtenPoints'] ?? null;
        $oral    = $exam['targetPoints'] ?? ($oralMap[$exam['id']] ?? null);
        if ($written === null || $oral === null) return null;
        return [(int) $written, (int) $oral];
    }

    /**
     * Effektive Note (0–15) eines Fachs zur Anforderungsprüfung.
     * ZP: round((2s+m)/3), Sport-ZP: round((praxis+theorie+m)/3),
     * Nicht-ZP: score bzw. (s+pg) für Sport.
     *
     * @param array<int, int> $oralMap
     */
    private function effectiveScore(array $exam, array $oralMap): ?int
    {
        if (($exam['isZusatzpruefung'] ?? false) && ($exam['isSport'] ?? false)) {
            $inputs = $this->sportZpInputs($exam, $oralMap);
            if ($inputs === null) return null;
            return $this->calcSportZpCombined($inputs[0], $inputs[1], $inputs[2]);
        }
        if ($exam['isZusatzpruefung'] ?? false) {
            $inputs = $this->zpInputs($exam, $oralMap);
            if ($inputs === null) return null;
            return $this->calcCombined($inputs[0], $inputs[1]);
        }
        if ($exam['isSport'] ?? false) {
            $s  = $exam['sportScore'] ?? $exam['score'] ?? null;
            $pg = $exam['sportPracticalGrade'] ?? null;
            if ($s === null || $pg === null) return null;
            // Sport-Gesamtnote: (score + practical) → als 0–15-Äquivalent: Beitrag / 4
            // Für Anforderungsprüfungen (≥1, ≥4, ≥5) verwenden wir den gerundeten Beitrag/4
            $contrib = ((int) $s + (int) $pg) * 2;
            return (int) round($contrib / 4);
        }
        $s = $exam['score'] ?? null;
        return $s !== null ? (int) $s : null;
    }

    // ─── Anforderungsprüfung (5 Kriterien) ──────────────────────────

    /**
     * Prüft alle 5 GSO-Anforderungen und gibt Detail-Ergebnisse zurück.
     *
     * Rückgabe:
     * [
     *   pass:      bool,
     *   total:     int|null,   ← Σ Abiturbeiträge (4-fache Wertung)
     *   criteria:  array,      ← 5 Einzel-Kriterien (für Ampel-Anzeige)
     *   failReqs:  string[],   ← Schlüssel der nicht erfüllten Kriterien
     * ]
     *
     * @param array<int, array> $exams
     * @param array<int, int>   $oralMap
     */
    public function checkReqs(array $exams, array $oralMap = []): array
    {
        // Effektive Noten und Beiträge für alle Fächer berechnen
        $scores      = [];
        $contribs    = [];
        $allKnown    = true;

        foreach ($exams as $i => $exam) {
            $s = $this->effectiveScore($exam, $oralMap);
            $c = $this->contribution($exam, $oralMap);
            if ($s === null || $c === null) {
                $allKnown = false;
            }
            $scores[$i]   = $s;
            $contribs[$i] = $c;
        }

        if (! $allKnown) {
            return [
                'pass'     => false,
                'total'    => null,
                'criteria' => $this->buildCriteria($exams, $scores, $contribs, false),
                'failReqs' => ['missing'],
            ];
        }

        $total = (int) array_sum($contribs);

        // Die 5 Kriterien prüfen
        $c1 = $this->checkEachGe1($contribs);
        $c2 = $this->checkTotalGe100($total);
        $c3 = $this->checkThreeGe5($exams, $contribs);
        $c4 = $this->checkDreierblock($exams, $contribs);
        $c5 = $this->checkAufgabenfeld($exams, $contribs);

        $failReqs = [];
        if (! $c1['pass']) $failReqs[] = 'each_ge_1';
        if (! $c2['pass']) $failReqs[] = 'total_ge_100';
        if (! $c3['pass']) $failReqs[] = 'three_ge_5';
        if (! $c4['pass']) $failReqs[] = 'dreierblock';
        if (! $c5['pass']) $failReqs[] = 'aufgabenfeld';

        return [
            'pass'     => empty($failReqs),
            'total'    => $total,
            'criteria' => [
                'each_ge_1'    => $c1,
                'total_ge_100' => $c2,
                'three_ge_5'   => $c3,
                'dreierblock'  => $c4,
                'aufgabenfeld' => $c5,
            ],
            'failReqs' => $failReqs,
        ];
    }

    // ─── Die 5 Einzel-Kriterien ──────────────────────────────────────

    /** Kriterium 1: Jedes Fach ≥ 1 Punkt (0–15) */
    private function checkEachGe1(array $contribs): array
    {
        $minContrib = PHP_INT_MAX;
        foreach ($contribs as $c) {
            if ($c !== null && $c < $minContrib) $minContrib = $c;
        }
        $pass = $minContrib !== PHP_INT_MAX && $minContrib >= 4;
        return [
            'pass'     => $pass,
            'actual'   => $minContrib === PHP_INT_MAX ? null : $minContrib,
            'required' => 4,
            'label'    => 'Jedes Fach ≥ 1 Pkt. (= 4 Pkt. ×4)',
            'detail'   => $pass ? null : 'Mind. ein Fach hat 0 Punkte.',
        ];
    }

    /** Kriterium 2: Σ Abiturprüfung (4-fache Wertung) ≥ 100 */
    private function checkTotalGe100(int $total): array
    {
        return [
            'pass'     => $total >= 100,
            'actual'   => $total,
            'required' => 100,
            'label'    => 'Σ Abiturprüfung ≥ 100',
            'detail'   => $total >= 100 ? null : "Σ = {$total}, fehlen " . (100 - $total) . ' Pkt.',
        ];
    }

    /**
     * Kriterium 3: Mind. 3 Fächer ≥ 5, davon mind. 1 aus {D, M, LF}.
     * „fortgeführte Fremdsprache" zählt ebenfalls (isLeistungsfach deckt das ab,
     * da fortgeführte FS als Leistungsfach geführt wird).
     *
     * WICHTIG: Prüfung erfolgt auf dem ×4-Beitrag (Schwelle 20), NICHT auf der
     * gerundeten Einzelnote (calcCombined). calcCombined rundet bereits vor der
     * ×4-Multiplikation und ist laut eigenem Docblock NUR für die Anzeige
     * gedacht — bei ZP-Fächern führt das zu Rundungsabweichungen gegenüber
     * der amtlichen Berechnung (Anlage 12 §52: Rundung erst am ×4-Ergebnis).
     * Beispiel: written=4, oral=6 → calcCombined rundet auf 5 (Kriterium würde
     * fälschlich passen), aber calcZpContribution liefert 19 (< 20, korrekt
     * nicht bestanden).
     */
    private function checkThreeGe5(array $exams, array $contribs): array
    {
        $highIndices = array_keys(array_filter($contribs, fn ($c) => $c !== null && $c >= 20));
        $count       = count($highIndices);
        $hasKey      = false;
        foreach ($highIndices as $i) {
            $e = $exams[$i];
            if (($e['isDeutsch'] ?? false) || ($e['isMathematik'] ?? false) || ($e['isLeistungsfach'] ?? false)) {
                $hasKey = true;
                break;
            }
        }
        $pass = $count >= 3 && $hasKey;
        return [
            'pass'     => $pass,
            'actual'   => $count,
            'required' => 3,
            'label'    => 'Mind. 3 Fächer ≥ 5 Pkt. (= 20 Pkt. ×4, inkl. D/M/LF)',
            'detail'   => $pass ? null : ($count < 3
                ? "Nur {$count} von 3 benötigten Fächern ≥ 5 Pkt."
                : 'Kein D/M/LF unter den Fächern mit ≥ 5 Pkt.'),
        ];
    }

    /**
     * Kriterium 4: Dreierblock Σ×4 ≥ 40, max. 1× < 4.
     * Varianten nach GSO:
     *  (a) D + M + bestes {FS, NW}
     *  (b) D substituiert → M + LF + bestes {FS_grundlegend, NW}
     *  (c) M substituiert → D + LF + bestes {NW_grundlegend, FS}
     *
     * WICHTIG: Prüfung erfolgt auf den ×4-Beiträgen (Schwelle 16 statt 4,
     * Summe direkt aus den Beiträgen statt Note×4), NICHT auf der gerundeten
     * Einzelnote × 4. Gleicher Rundungsgrund wie bei checkThreeGe5/
     * checkAufgabenfeld: Note×4 rundet doppelt und kann von
     * calcZpContribution() abweichen (z.B. 19 statt 20 oder 15 statt 16).
     */
    public function checkDreierblock(array $exams, array $contribs): array
    {
        $dIdx  = null;
        $mIdx  = null;
        $lfCandidates  = [];
        $fsCandidates  = [];
        $fsGrundlegend = [];
        $nwCandidates  = [];
        $nwGrundlegend = [];

        foreach ($exams as $i => $e) {
            if ($e['isDeutsch']    ?? false) $dIdx = $i;
            if ($e['isMathematik'] ?? false) $mIdx = $i;
            if ($e['isLeistungsfach'] ?? false) $lfCandidates[] = $i;
            if (($e['isForeignLanguage'] ?? false) && !($e['isDeutsch'] ?? false)) {
                $fsCandidates[] = $i;
                if (($e['level'] ?? 'grundlegend') === 'grundlegend') $fsGrundlegend[] = $i;
            }
            if ($e['isScience'] ?? false) {
                $nwCandidates[] = $i;
                if (($e['level'] ?? 'grundlegend') === 'grundlegend') $nwGrundlegend[] = $i;
            }
        }

        $check = function (array $triplet) use ($contribs): bool {
            $vals  = array_map(fn ($i) => $contribs[$i] ?? 0, $triplet);
            $sum4  = (int) array_sum($vals);
            $below = count(array_filter($vals, fn ($c) => $c < 16));
            return $sum4 >= 40 && $below <= 1;
        };

        // (a) Normalfall
        if ($dIdx !== null && $mIdx !== null) {
            $thirds = array_values(array_unique(array_merge($fsCandidates, $nwCandidates)));
            if (empty($thirds)) {
                return ['pass' => true, 'label' => 'Dreierblock D+M+FS/NW', 'detail' => null, 'skipped' => true];
            }
            $best = null;
            foreach ($thirds as $ti) {
                if ($check([$dIdx, $mIdx, $ti])) { $best = $ti; break; }
            }
            $pass   = $best !== null;
            $vals   = $pass ? array_map(fn ($i) => $contribs[$i] ?? 0, [$dIdx, $mIdx, $best]) : null;
            $sum    = $vals ? array_sum($vals) : null;
            return [
                'pass'   => $pass,
                'label'  => 'Dreierblock D+M+FS/NW',
                'detail' => $pass ? "Σ = {$sum} (×4)" : 'D+M+FS/NW: Σ×4 < 40 oder mehr als 1× < 16 Pkt. (×4).',
                'sum4'   => $sum,
            ];
        }

        // (b) D substituiert
        if ($dIdx === null && $mIdx !== null) {
            if (empty($lfCandidates)) {
                return ['pass' => true, 'label' => 'Dreierblock (D sub.)', 'detail' => null, 'skipped' => true];
            }
            $thirds = array_values(array_unique(array_merge($fsGrundlegend, $nwCandidates)));
            if (empty($thirds)) {
                return ['pass' => true, 'label' => 'Dreierblock (D sub.)', 'detail' => null, 'skipped' => true];
            }
            foreach ($lfCandidates as $lfIdx) {
                foreach ($thirds as $ti) {
                    if ($ti === $lfIdx) continue;
                    if ($check([$mIdx, $lfIdx, $ti])) {
                        $sum = array_sum(array_map(fn ($i) => $contribs[$i] ?? 0, [$mIdx, $lfIdx, $ti]));
                        return ['pass' => true, 'label' => 'Dreierblock M+LF+FS/NW (D sub.)', 'detail' => "Σ = {$sum} (×4)", 'sum4' => $sum];
                    }
                }
            }
            return ['pass' => false, 'label' => 'Dreierblock M+LF+FS/NW (D sub.)', 'detail' => 'M+LF+FS/NW: Σ×4 < 40 oder mehr als 1× < 16 Pkt. (×4).'];
        }

        // (c) M substituiert
        // $mIdx ist hier strukturell immer null (vorige Branches mit $mIdx !== null sind bereits per return verlassen),
        // die Bedingung bleibt zur Symmetrie der vier Fallunterscheidungen explizit stehen.
        // @phpstan-ignore identical.alwaysTrue
        if ($mIdx === null && $dIdx !== null) {
            if (empty($lfCandidates)) {
                return ['pass' => true, 'label' => 'Dreierblock (M sub.)', 'detail' => null, 'skipped' => true];
            }
            $thirds = array_values(array_unique(array_merge($nwGrundlegend, $fsCandidates)));
            if (empty($thirds)) {
                return ['pass' => true, 'label' => 'Dreierblock (M sub.)', 'detail' => null, 'skipped' => true];
            }
            foreach ($lfCandidates as $lfIdx) {
                foreach ($thirds as $ti) {
                    if ($ti === $lfIdx) continue;
                    if ($check([$dIdx, $lfIdx, $ti])) {
                        $sum = array_sum(array_map(fn ($i) => $contribs[$i] ?? 0, [$dIdx, $lfIdx, $ti]));
                        return ['pass' => true, 'label' => 'Dreierblock D+LF+NW/FS (M sub.)', 'detail' => "Σ = {$sum} (×4)", 'sum4' => $sum];
                    }
                }
            }
            return ['pass' => false, 'label' => 'Dreierblock D+LF+NW/FS (M sub.)', 'detail' => 'D+LF+NW/FS: Σ×4 < 40 oder mehr als 1× < 16 Pkt. (×4).'];
        }

        return ['pass' => true, 'label' => 'Dreierblock', 'detail' => null, 'skipped' => true];
    }

    /**
     * Kriterium 5: Max. 1× < 4 pro Aufgabenfeld (SLK / GPR / MNT).
     * Fächer mit aufgabenfeld = null / 'sport' / 'sonstiges' werden ignoriert.
     *
     * WICHTIG: Prüfung erfolgt auf dem ×4-Beitrag (Schwelle 16 = "4 Pkt. in
     * vierfacher Wertung"), NICHT auf der gerundeten Einzelnote (calcCombined).
     * Gleicher Rundungsgrund wie bei checkThreeGe5: ein ZP-Fach mit z.B.
     * written=3, oral=5 rundet via calcCombined auf 4 (Kriterium würde
     * fälschlich als erfüllt gelten), aber calcZpContribution liefert nur 15
     * (< 16, korrekt nicht erfüllt). Erst oral=6 ergibt den nötigen Beitrag 16.
     */
    public function checkAufgabenfeld(array $exams, array $contribs): array
    {
        $relevant = ['sprachlich_literarisch', 'gesellschaftswissenschaftlich', 'mint'];
        $countBelow = [];
        foreach ($exams as $i => $e) {
            $af = $e['aufgabenfeld'] ?? null;
            if ($af === null || !in_array($af, $relevant, true)) continue;
            $c = $contribs[$i] ?? null;
            if ($c !== null && $c < 16) {
                $countBelow[$af] = ($countBelow[$af] ?? 0) + 1;
            }
        }
        $violations = array_filter($countBelow, fn ($n) => $n > 1);
        $pass       = empty($violations);
        $labels     = [
            'sprachlich_literarisch'    => 'SLK',
            'gesellschaftswissenschaftlich' => 'GPR',
            'mint'                      => 'MNT',
        ];
        $violated = implode(', ', array_map(fn ($af) => $labels[$af] ?? $af, array_keys($violations)));
        return [
            'pass'    => $pass,
            'label'   => 'Max. 1× < 4 Pkt. (= 16 Pkt. ×4) pro Aufgabenfeld',
            'detail'  => $pass ? null : "Mehr als 1× < 16 Pkt. (×4) in: {$violated}",
            'counts'  => $countBelow,
        ];
    }

    /** Hilfsmethode: Baut das criteria-Array auch bei unvollständigen Daten auf. */
    private function buildCriteria(array $exams, array $scores, array $contribs, bool $complete): array
    {
        if (!$complete) {
            $placeholder = ['pass' => null, 'label' => '', 'detail' => 'Noten unvollständig'];
            return [
                'each_ge_1'    => array_merge($placeholder, ['label' => 'Jedes Fach ≥ 1 Pkt. (= 4 Pkt. ×4)']),
                'total_ge_100' => array_merge($placeholder, ['label' => 'Σ Abiturprüfung ≥ 100']),
                'three_ge_5'   => array_merge($placeholder, ['label' => 'Mind. 3 Fächer ≥ 5 Pkt. (= 20 Pkt. ×4, inkl. D/M/LF)']),
                'dreierblock'  => array_merge($placeholder, ['label' => 'Dreierblock D+M+FS/NW']),
                'aufgabenfeld' => array_merge($placeholder, ['label' => 'Max. 1× < 4 Pkt. (= 16 Pkt. ×4) pro Aufgabenfeld']),
            ];
        }
        $total = (int) array_sum(array_filter($contribs));
        return [
            'each_ge_1'    => $this->checkEachGe1($contribs),
            'total_ge_100' => $this->checkTotalGe100($total),
            'three_ge_5'   => $this->checkThreeGe5($exams, $contribs),
            'dreierblock'  => $this->checkDreierblock($exams, $contribs),
            'aufgabenfeld' => $this->checkAufgabenfeld($exams, $contribs),
        ];
    }

    // ─── Kombinationsberechnung ──────────────────────────────────────

    /**
     * Berechnet alle Daten für einen Schüler.
     *
     * @param array<int, array{
     *   id: int, subject: string, isZusatzpruefung: bool,
     *   isDeutsch: bool, isMathematik: bool, isLeistungsfach: bool,
     *   isForeignLanguage: bool, isScience: bool,
     *   isSport: bool, level: string|null, aufgabenfeld: string|null,
     *   writtenPoints: int|null, targetPoints: int|null,
     *   score: int|null, sportScore: int|null,
     *   sportPracticalGrade: int|null, sort_order: int
     * }> $exams
     */
    public function compute(array $exams): array
    {
        // Offene ZP-Fächer (mündliche Note noch unbekannt)
        $zpExams = array_values(array_filter(
            $exams,
            static function (array $e): bool {
                return ($e['isZusatzpruefung'] ?? false) && $e['targetPoints'] === null;
            }
        ));

        // Sortierung: manuelle sort_order wenn vorhanden, sonst niedrigste schriftliche Note zuerst
        $hasManualOrder = count(array_filter($zpExams, static function (array $e): bool {
            return ($e['sort_order'] ?? 0) > 0;
        })) > 0;
        if ($hasManualOrder) {
            usort($zpExams, static function (array $a, array $b): int {
                return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
            });
        } else {
            usort($zpExams, fn ($a, $b) => ($a['writtenPoints'] ?? 999) <=> ($b['writtenPoints'] ?? 999));
        }

        // Alle fixen Scores bekannt?
        $allFixed = $this->allFixedScoresEntered($exams);

        if (! $allFixed) {
            return [
                'allFixedScoresEntered' => false,
                'zpExams'               => $zpExams,
                'combinations'          => null,
                'passedCombinations'    => null,
                'passedCombinationsN2'  => null,
                'criteria'              => $this->buildCriteria($exams, [], [], false),
            ];
        }

        $combinations = $this->computeCombinations($exams, $zpExams);
        $passed       = array_values(array_filter($combinations, fn ($r) => $r['pass']));
        $passedN2     = count($zpExams) === 2 ? $this->deduplicateN2($passed) : $passed;

        // Top-5 nach "wahrscheinlichster Kombination" (kleinste Abweichung von schriftl. Note)
        $top5 = $this->topFiveCombinations($passed, $zpExams);

        // Kriterien immer berechnen:
        // – N=0: aus dem tatsächlichen Gesamtergebnis
        // – N>0: aus den fixen Fächern + den offenen ZP-Fächern im
        //        schlechtestmöglichen Ausgang (mündlich = 0). Damit zeigt die
        //        Anzeige, welche Kriterien wirklich nicht mehr kippen können.
        $criteriaResult = $this->checkReqsWithWorstCaseZp($exams);

        // Summe aus fixen Fächern (ZP mit mündlicher Note: echter ×4-Beitrag;
        // ZP ohne mündliche Note: der Beitrag bei mündlich = 0 als Untergrenze)
        $fixedTotal = 0;
        foreach ($exams as $i => $exam) {
            if (($exam['isZusatzpruefung'] ?? false) && ($exam['isSport'] ?? false)) {
                $theorie = $exam['writtenPoints'] ?? null;
                $praxis  = $exam['sportPracticalGrade'] ?? null;
                $t       = $exam['targetPoints'] ?? null;
                if ($theorie !== null && $praxis !== null && $t !== null) {
                    // Mündliche Note bereits bekannt → echter Beitrag (Sport-ZP-Formel)
                    $fixedTotal += $this->calcSportZpContribution((int) $praxis, (int) $theorie, (int) $t);
                } elseif ($theorie !== null && $praxis !== null) {
                    // Noch offen → der Beitrag bei mündlich = 0, also die
                    // Sport-ZP-Formel mit dem schlechtestmöglichen Ausgang.
                    // Vorher stand hier (Praxis + Theorie) × 2 — der Beitrag
                    // OHNE Zusatzprüfung. Das ist keine Untergrenze, sondern
                    // liegt darüber: 2 ist mehr als die 4/3 der ZP-Formel.
                    $fixedTotal += $this->calcSportZpContribution((int) $praxis, (int) $theorie, 0);
                }
            } elseif ($exam['isZusatzpruefung'] ?? false) {
                $w = $exam['writtenPoints'] ?? null;
                $t = $exam['targetPoints'] ?? null;
                if ($w !== null && $t !== null) {
                    // Mündliche Note bereits bekannt → echter Beitrag (Anlage 12 §52)
                    $fixedTotal += $this->calcZpContribution((int) $w, (int) $t);
                } elseif ($w !== null) {
                    // Noch offen → der Beitrag bei mündlich = 0.
                    // Vorher stand hier schriftlich × 4 — der Beitrag OHNE
                    // Zusatzprüfung. Eine Zusatzprüfung kann das Ergebnis aber
                    // auch verschlechtern (Klärung 31.08.2026), und dann liegt
                    // der Beitrag darunter: round(8w/3) statt 4w.
                    $fixedTotal += $this->calcZpContribution((int) $w, 0);
                }
            } else {
                $c = $this->scoreContribution($exam);
                if ($c !== null) $fixedTotal += $c;
            }
        }

        return [
            'allFixedScoresEntered' => true,
            'zpExams'               => $zpExams,
            'combinations'          => $combinations,
            'passedCombinations'    => $passed,
            'passedCombinationsN2'  => $passedN2,
            'top5Combinations'      => $top5,
            'criteria'              => $criteriaResult['criteria'] ?? null,
            'total'                 => count($zpExams) === 0 ? ($criteriaResult['total'] ?? null) : null,
            'fixedTotal'            => $fixedTotal,
            'pass'                  => count($zpExams) === 0 ? ($criteriaResult['pass'] ?? null) : null,
        ];
    }

    // ─── filterWorsening (öffentlich, für PDF-Export) ────────────────

    /**
     * Filtert Kombinationen heraus, bei denen combined < written
     * (Fach würde sich gegenüber der schriftlichen Note verschlechtern).
     *
     * @param array<int, array{oral: list<int|null>, combined: list<int|null>}> $rows
     * @param list<array{writtenPoints: int|null}>                              $zpOpenExams
     */
    public function filterWorsening(array $rows, array $zpOpenExams): array
    {
        return array_values(array_filter($rows, function (array $row) use ($zpOpenExams): bool {
            foreach ($zpOpenExams as $i => $zpExam) {
                $written  = $zpExam['writtenPoints'] ?? null;
                $combined = $row['combined'][$i] ?? null;
                if ($written === null || $combined === null) continue;
                if ($combined < $written) return false;
            }
            return true;
        }));
    }

    // ─── Hilfsmethoden ──────────────────────────────────────────────

    private function allFixedScoresEntered(array $exams): bool
    {
        foreach ($exams as $e) {
            if ($e['isZusatzpruefung'] ?? false) {
                // Offene ZP braucht mindestens die schriftliche Note
                if ($e['targetPoints'] === null && ($e['writtenPoints'] ?? null) === null) return false;
                // Abgeschlossene ZP: schriftliche Note muss bekannt sein
                if ($e['targetPoints'] !== null && ($e['writtenPoints'] ?? null) === null) return false;
                // Sport-ZP: Praxisnote muss zusätzlich bekannt sein (in beiden Fällen)
                if (($e['isSport'] ?? false) && ($e['sportPracticalGrade'] ?? null) === null) return false;
            } else {
                if ($e['isSport'] ?? false) {
                    $s  = $e['sportScore'] ?? $e['score'] ?? null;
                    $pg = $e['sportPracticalGrade'] ?? null;
                    if ($s === null || $pg === null) return false;
                } else {
                    $s = $e['score'] ?? null;
                    if ($s === null || $s === '') return false;
                }
            }
        }
        return true;
    }

    private function computeCombinations(array $exams, array $zpExams): array
    {
        $n = count($zpExams);

        // N=0: Alle ZPs abgeschlossen
        if ($n === 0) {
            $r = $this->checkReqs($exams);
            return [[
                'oral'     => [],
                'combined' => [],
                'contrib'  => [],
                'pass'     => $r['pass'],
                'total'    => $r['total'],
                'criteria' => $r['criteria'],
                'failReqs' => $r['failReqs'],
            ]];
        }

        // N=1
        if ($n === 1) {
            $a    = $zpExams[0];
            $rows = [];
            for ($oA = 0; $oA <= 15; $oA++) {
                $r              = $this->checkReqs($exams, [$a['id'] => $oA]);
                [$c4, $combined] = $this->contribAndCombinedFor($a, $oA);
                $rows[] = [
                    'oral'     => [$oA],
                    'combined' => [$combined],
                    'contrib'  => [$c4],
                    'pass'     => $r['pass'],
                    'total'    => $r['total'],
                    'criteria' => $r['criteria'],
                    'failReqs' => $r['failReqs'],
                ];
            }
            return $rows;
        }

        // N=2
        if ($n === 2) {
            [$a, $b] = $zpExams;
            $rows    = [];
            for ($oA = 0; $oA <= 15; $oA++) {
                $minB = null; $tot = null; $cB = null; $c4B = null;
                for ($oB = 0; $oB <= 15; $oB++) {
                    $r = $this->checkReqs($exams, [$a['id'] => $oA, $b['id'] => $oB]);
                    if ($r['pass']) {
                        $minB = $oB;
                        $tot  = $r['total'];
                        [$c4B, $cB] = $this->contribAndCombinedFor($b, $oB);
                        break;
                    }
                }
                [$c4A, $cA] = $this->contribAndCombinedFor($a, $oA);
                $rows[] = [
                    'oral'     => [$oA, $minB],
                    'combined' => [$cA, $cB],
                    'contrib'  => [$c4A, $c4B],
                    'pass'     => $minB !== null,
                    'total'    => $tot,
                    'criteria' => null,
                    'failReqs' => [],
                ];
            }
            // Annotation: B nicht nötig wenn Schüler schon ohne B besteht
            foreach ($rows as &$row) {
                if (! $row['pass']) continue;
                $oA    = $row['oral'][0];
                $skipR = $this->checkReqsWithSkip($exams, [$a['id'] => $oA], [$b['id']]);
                if ($skipR['pass']) {
                    $row['oral'][1]    = null;
                    $row['combined'][1] = null;
                    $row['contrib'][1]  = null;
                    $row['total']      = $skipR['total'];
                }
            }
            unset($row);
            return $rows;
        }

        // N=3
        [$a, $b, $c] = $zpExams;
        $writtenA = (int) ($a['writtenPoints'] ?? 0);
        $writtenB = (int) ($b['writtenPoints'] ?? 0);
        $writtenC = (int) ($c['writtenPoints'] ?? 0);
        $rows     = [];
        for ($oA = 0; $oA <= 15; $oA++) {
            for ($oB = 0; $oB <= 15; $oB++) {
                // Statt des kleinsten bestehenden oC wird unter ALLEN bestehenden
                // oC-Werten derjenige gewählt, der die Spannweite der drei Deltas
                // (oral_i - written_i) minimiert — d.h. die gleichmäßigste
                // Steigerung über alle drei ZP-Fächer. Bei Gleichstand: kleinste
                // Summe der ABSOLUTEN Deltas (Σ|Δ|, nicht ΣΔ — sonst könnten sich
                // positive und negative Deltas gegenseitig aufheben), danach
                // kleinstes oC.
                $bestC = null; $bestSpread = null; $bestSumAbsDelta = null; $bestTot = null;
                for ($oC = 0; $oC <= 15; $oC++) {
                    $r = $this->checkReqs($exams, [$a['id'] => $oA, $b['id'] => $oB, $c['id'] => $oC]);
                    if (! $r['pass']) continue;
                    $deltas       = [$oA - $writtenA, $oB - $writtenB, $oC - $writtenC];
                    $spread       = max($deltas) - min($deltas);
                    $sumAbsDelta  = array_sum(array_map('abs', $deltas));
                    if (
                        $bestC === null
                        || $spread < $bestSpread
                        || ($spread === $bestSpread && $sumAbsDelta < $bestSumAbsDelta)
                    ) {
                        $bestC           = $oC;
                        $bestSpread      = $spread;
                        $bestSumAbsDelta = $sumAbsDelta;
                        $bestTot         = $r['total'];
                    }
                }
                if ($bestC !== null) {
                    [$c4A, $cA] = $this->contribAndCombinedFor($a, $oA);
                    [$c4B, $cB] = $this->contribAndCombinedFor($b, $oB);
                    [$c4C, $cC] = $this->contribAndCombinedFor($c, $bestC);
                    $rows[] = [
                        'oral'     => [$oA, $oB, $bestC],
                        'combined' => [$cA, $cB, $cC],
                        'contrib'  => [$c4A, $c4B, $c4C],
                        'pass'     => true,
                        'total'    => $bestTot,
                        'criteria' => null,
                        'failReqs' => [],
                    ];
                }
            }
        }
        // Annotation: nicht nötige ZPs als null markieren
        foreach ($rows as &$row) {
            [$oA, $oB] = $row['oral'];
            $r1 = $this->checkReqsWithSkip($exams, [$a['id'] => $oA], [$b['id'], $c['id']]);
            if ($r1['pass']) {
                $row['oral']    = [$oA, null, null];
                $row['combined'] = [$row['combined'][0], null, null];
                $row['contrib']  = [$row['contrib'][0], null, null];
                continue;
            }
            $r2 = $this->checkReqsWithSkip($exams, [$a['id'] => $oA, $b['id'] => $oB], [$c['id']]);
            if ($r2['pass']) {
                $row['oral'][2]    = null;
                $row['combined'][2] = null;
                $row['contrib'][2]  = null;
            }
        }
        unset($row);
        return $this->paretoFilterN3($rows);
    }

    /**
     * Prüft Bestehen wenn bestimmte ZP-Fächer NICHT angetreten werden.
     * Für übersprungene ZPs wird writtenPoints direkt als Score gewertet.
     *
     * @param array<int, int>   $oralMap
     * @param list<int>         $skipIds  exam.id-Liste der ZPs die nicht angetreten werden
     */
    private function checkReqsWithSkip(array $exams, array $oralMap, array $skipIds): array
    {
        $modifiedExams = $exams;
        foreach ($modifiedExams as &$exam) {
            if (($exam['isZusatzpruefung'] ?? false) && in_array($exam['id'], $skipIds, true)) {
                // ZP nicht angetreten → schriftliche Note gilt direkt als score (kein ZP-Beitrag)
                $exam['isZusatzpruefung'] = false;
                $exam['score']            = $exam['writtenPoints'] ?? null;
            }
        }
        unset($exam);
        return $this->checkReqs($modifiedExams, $oralMap);
    }

    /**
     * Vorab-Einschätzung der 5 Anforderungen, BEVOR die mündliche ZP angetreten
     * ist: Jedes offene ZP-Fach (writtenPoints bekannt, targetPoints noch null)
     * wird mit der **schlechtestmöglichen mündlichen Note** gerechnet, also 0.
     * Damit ist das Ergebnis wirklich eine Untergrenze — ein Kriterium, das
     * hier als erfüllt gilt, kann durch keine Zusatzprüfung mehr kippen.
     *
     * **Vorher stand hier die schriftliche Note als Platzhalter** (×4-Wertung,
     * wie beim Fall "ZP nicht angetreten"), mit der Begründung, die mündliche
     * Note könne die Lage nur verbessern oder gleich halten. **Das ist nicht
     * so: Eine Zusatzprüfung kann das Ergebnis auch verschlechtern**
     * (fachliche Klärung Wilfried, 31.08.2026). Der alte Platzhalter lag
     * deshalb systematisch zu hoch — bei 15 schriftlichen Punkten um 20 von
     * 900 Punkten, und bei zwei offenen ZP-Fächern um bis zu 40:
     *
     *   schriftlich  angesetzt (w×4)  echte Untergrenze  Differenz
     *        4             16                11              5
     *        8             32                21             11
     *       12             48                32             16
     *       15             60                40             20
     *
     * Die Anzeige meldete damit Kriterien als "schon sicher erfüllt", die ein
     * schlechter mündlicher Ausgang noch hätte reißen können — genau die
     * Auskunft, für die der Prüfling die Maske aufruft.
     *
     * @param array<int, array> $exams
     */
    private function checkReqsWithWorstCaseZp(array $exams): array
    {
        $oralMap = [];
        foreach ($exams as $e) {
            if (($e['isZusatzpruefung'] ?? false) && $e['targetPoints'] === null && ($e['writtenPoints'] ?? null) !== null) {
                $oralMap[$e['id']] = 0;
            }
        }

        return $this->checkReqs($exams, $oralMap);
    }

    /**
     * Top-5-Auswahl: Kombinationen mit der GLEICHMÄSSIGSTEN Steigerung
     * gegenüber der schriftlichen Note kommen zuerst.
     *
     * Hintergrund: Eine Kombination, bei der ein Fach stark steigt
     * (z.B. +12) während ein anderes gar nicht angetreten werden muss
     * (delta = 0), ist unrealistischer als eine, bei der sich der
     * Schüler in allen ZP-Fächern um einen ähnlichen Betrag steigert.
     * Daher wird primär nach der SPANNWEITE der Deltas sortiert
     * (max(delta) − min(delta)), nicht nach der Summe der Abweichungen.
     * "Nicht nötig" (oral === null) zählt dabei als delta = 0.
     *
     * Bei Gleichstand der Spannweite: kleinste Summe der ABSOLUTEN Deltas
     * (Σ|Δ|) bevorzugt, d.h. insgesamt geringste Gesamtsteigerung über
     * alle ZP-Fächer. Wichtig: Σ|Δ| und nicht Σ(mündliche Note), da eine
     * Summe der reinen mündlichen Noten "nicht nötig"-Fächer künstlich
     * begünstigen würde (sie tragen nichts zur Summe bei, obwohl sie als
     * Delta=0 mitgezählt werden sollten) – das war der Grund, warum zuvor
     * Kombinationen mit überproportionalem Anstieg in einem Fach + einem
     * komplett übersprungenen Fach fälschlich vor gleichmäßigeren
     * Kombinationen mit drei moderaten Steigerungen lagen.
     *
     * @param array<int, array>          $passed    Alle bestandenen Kombinationen
     * @param array<int, array>          $zpExams   Offene ZP-Fächer
     */
    private function topFiveCombinations(array $passed, array $zpExams): array
    {
        if (empty($passed) || empty($zpExams)) {
            return array_slice($passed, 0, 5);
        }

        // N=1: Es gibt nur eine relevante Mindestnote – jede höhere Note
        // besteht ebenfalls, weitere Zeilen liefern keine zusätzliche Information.
        if (count($zpExams) === 1) {
            return array_slice($passed, 0, 1);
        }

        $scored = array_map(function (array $row) use ($zpExams): array {
            $deltas = [];
            foreach ($zpExams as $i => $zp) {
                $oral    = $row['oral'][$i] ?? null;
                $written = (int) ($zp['writtenPoints'] ?? 0);
                // "Nicht nötig" → keine Steigerung nötig → delta 0
                $deltas[] = $oral !== null ? ($oral - $written) : 0;
            }
            $row['_spread']    = max($deltas) - min($deltas);
            $row['_sumAbsDelta'] = array_sum(array_map('abs', $deltas));
            return $row;
        }, $passed);

        usort($scored, function ($a, $b) {
            if ($a['_spread'] !== $b['_spread']) return $a['_spread'] <=> $b['_spread'];
            return $a['_sumAbsDelta'] <=> $b['_sumAbsDelta'];
        });

        return array_slice(
            array_map(fn ($r) => array_diff_key($r, ['_spread' => 0, '_sumAbsDelta' => 0]), $scored),
            0, 5
        );
    }

    /** N=2: Deduplizierung – nur Zeilen anzeigen, wo sich Mindestnote Fach 2 ändert */
    private function deduplicateN2(array $passed): array
    {
        $result    = [];
        $lastOral1 = PHP_INT_MIN;
        foreach ($passed as $row) {
            $o1 = $row['oral'][1] ?? null;
            if ($o1 !== $lastOral1) {
                $result[]  = $row;
                $lastOral1 = $o1 ?? PHP_INT_MIN;
            }
        }
        $seenOral0 = array_map(fn ($r) => $r['oral'][0] ?? null, $result);
        foreach ($passed as $row) {
            if (($row['oral'][1] ?? null) === null) continue;
            $o0 = $row['oral'][0] ?? null;
            if (in_array($o0, $seenOral0, true)) continue;
            $result[]    = $row;
            $seenOral0[] = $o0;
        }
        usort($result, function ($a, $b) {
            $aNull = ($a['oral'][1] ?? null) === null;
            $bNull = ($b['oral'][1] ?? null) === null;
            if ($aNull !== $bNull) return $aNull ? 1 : -1;
            return ($a['combined'][0] ?? 99) <=> ($b['combined'][0] ?? 99);
        });
        return $result;
    }

    /** Pareto-Filter für N=3: entfernt dominierte Kombinationen */
    private function paretoFilterN3(array $rows): array
    {
        $cmpVec = static fn (array $oral): array =>
            array_map(static fn ($v) => $v === null ? -1 : $v, $oral);

        // Deduplizierung
        $seen = []; $unique = [];
        foreach ($rows as $row) {
            $key = implode(',', $cmpVec($row['oral']));
            if (! isset($seen[$key])) { $seen[$key] = true; $unique[] = $row; }
        }

        return array_values(array_filter($unique, function ($row) use ($unique, $cmpVec) {
            $rowCmp = $cmpVec($row['oral']);
            foreach ($unique as $other) {
                if ($other === $row) continue;
                $otherCmp = $cmpVec($other['oral']);
                $dominated = true; $strictlyBetter = false;
                for ($i = 0; $i < 3; $i++) {
                    if ($otherCmp[$i] > $rowCmp[$i]) { $dominated = false; break; }
                    if ($otherCmp[$i] < $rowCmp[$i]) $strictlyBetter = true;
                }
                if ($dominated && $strictlyBetter) return false;
            }
            return true;
        }));
    }
}
