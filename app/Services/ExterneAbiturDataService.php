<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\StudentExam;
use App\Models\ZusatzpruefExamScore;
use App\Models\Zusatzpruefung;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Die acht Prüfungsfächer eines externen Bewerbers in genau der Form, die
 * ExterneGesamtqualifikationService und ExterneMindestpunkteService erwarten.
 *
 * Gegenstück zu AbiturExamDataService, der dasselbe für die Rechnung nach § 61
 * tut. Zwei Abbildungen, weil die beiden Rechenwege verschiedene Angaben
 * brauchen: § 61 fragt nach Aufgabenfeld, Dreierblock und Leistungsfach, § 62
 * nach der Position 1–8 der Anlage 14. Die Position trägt AbiturExamDataService
 * nicht — sie ergibt sich aus exam_part und subject_number und hat für reguläre
 * Schüler keine Bedeutung.
 *
 * Woher die Punkte kommen:
 *  – Fach des ersten Teils mit mündlicher Zusatzprüfung: written_points und
 *    target_points der Zusatzprüfung. Steht target_points noch aus, ist genau
 *    das die offene Größe, nach der ExterneMindestpunkteService auflöst.
 *  – jedes andere Fach: zusatzpruef_exam_scores.score, geschlüsselt auf
 *    student_exam_id allein — student_id ist für externe Bewerber null.
 */
class ExterneAbiturDataService
{
    /**
     * Die acht Fächer eines Bewerbers, nach Position 1–8 sortiert.
     *
     * Gibt null zurück, solange die Fächerwahl die acht Positionen nicht genau
     * einmal belegt. Der Rechner würde darauf werfen (§ 61 Abs. 1 S. 1), und
     * eine unvollständige Fächerwahl ist ein zulässiger Zwischenstand, kein
     * Fehler — die Oberfläche zeigt dann, dass noch nichts zu rechnen ist.
     *
     * @return list<array<string, mixed>>|null
     */
    public function faecherFuer(ExternalApplicant $bewerber, int $examPeriodId): ?array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ExternalExamSubject> $pruefungsfaecher */
        $pruefungsfaecher = $bewerber->examSubjects()
            ->with(['subject', 'externalApplicant', 'studentExam'])
            ->get();

        if ($pruefungsfaecher->count() !== 8) {
            return null;
        }

        $pruefungsIds = $pruefungsfaecher
            ->map(fn (ExternalExamSubject $fach): ?int => $fach->studentExam?->id)
            ->filter()
            ->values();

        $zusatzpruefungen = Zusatzpruefung::whereIn('student_exam_id', $pruefungsIds)
            ->where('exam_period_id', $examPeriodId)
            ->get()
            ->keyBy('student_exam_id');

        $noten = ZusatzpruefExamScore::whereIn('student_exam_id', $pruefungsIds)
            ->where('exam_period_id', $examPeriodId)
            ->get()
            ->keyBy('student_exam_id');

        $faecher = $pruefungsfaecher
            ->map(fn (ExternalExamSubject $fach): array => $this->fach($fach, $zusatzpruefungen, $noten))
            ->sortBy('position')
            ->values()
            ->all();

        $positionen = array_map('intval', array_column($faecher, 'position'));

        return $positionen === range(1, 8) ? $faecher : null;
    }

    /**
     * Ein einzelnes Prüfungsfach.
     *
     * @param EloquentCollection<array-key, Zusatzpruefung> $zusatzpruefungen
     * @param EloquentCollection<array-key, ZusatzpruefExamScore> $noten
     * @return array<string, mixed>
     */
    private function fach(
        ExternalExamSubject $fach,
        EloquentCollection $zusatzpruefungen,
        EloquentCollection $noten
    ): array {
        $pruefung = $fach->studentExam;
        $position = $fach->position();

        $grund = [
            'position' => $position,
            'fach' => $fach->fachname(),
            'fach_kuerzel' => $fach->subject?->code,
            'ea' => $fach->niveau() === 'erhoeht',
            'student_exam_id' => $pruefung instanceof StudentExam ? $pruefung->id : null,
            'punkte' => null,
        ];

        if (! $pruefung instanceof StudentExam) {
            return $grund;
        }

        $zusatzpruefung = $zusatzpruefungen->get($pruefung->id);

        // Eine mündliche Zusatzprüfung gibt es nur zu den Fächern des ersten
        // Prüfungsteils (§ 61 Abs. 1 S. 3); exam_type ist für die Fächer fünf
        // bis acht 'oral', und can_zp hängt an 'written'. Die Prüfung auf die
        // Position steht trotzdem hier, weil der Rechner sonst wirft und die
        // Meldung dann aus dem falschen Zusammenhang käme.
        if ($zusatzpruefung instanceof Zusatzpruefung && $position <= 4) {
            return $grund + [
                'zusatzpruefung' => [
                    'schriftlich' => $zusatzpruefung->written_points,
                    'muendlich' => $zusatzpruefung->target_points,
                ],
            ];
        }

        $note = $noten->get($pruefung->id);

        $grund['punkte'] = $note instanceof ZusatzpruefExamScore ? $note->score : null;

        return $grund;
    }

    /**
     * § 62 Abs. 1 S. 4 (Regel E6) — wessen erster Prüfungsteil nicht bestanden
     * ist, ein Satz je Bewerber.
     *
     * Der Abbruch **rechnet sich schon** in
     * `ExterneGesamtqualifikationService::berechne()` und steht als
     * `abbruch => 'nach_erstem_teil'` im Mindestpunkte-Modal und im PDF. Was
     * fehlte, war die Folge für die Planung: Die vier mündlichen Prüfungen
     * standen weiter im Raster, zählten im Soll und in der Konfliktliste mit.
     *
     * **Gemeldet, nicht entschieden** (Entscheidung Wilfrieds vom 31.08.2026,
     * dieselbe Bauform wie § 61 Abs. 4 S. 2): Die Anwendung nennt den Fall in
     * der Konfliktliste; ob abgebrochen wird und was mit bereits geplanten
     * Terminen geschieht, entscheidet der Koordinator.
     *
     * `berechne()` setzt `abbruch` nur, wenn der erste Teil **entschieden und
     * nicht bestanden** ist — solange Noten fehlen, ist `bestanden` null und
     * es wird nichts gemeldet. Eine unvollständige Fächerwahl liefert hier gar
     * keine Fächer; der Rechner würfe darauf.
     *
     * @return list<string>
     */
    public function abbruchgruendeErsterTeil(ExamPeriod $zeitraum): array
    {
        $rechnung = app(ExterneGesamtqualifikationService::class);
        $gruende = [];

        $bewerber = ExternalApplicant::forExamPeriod($zeitraum->id)->get();

        foreach ($bewerber as $einzelner) {
            // Wer den Abbruch schon festgestellt hat (Entscheidung 51),
            // braucht die Warnung nicht mehr — sie stünde nur noch für einen
            // bereits entschiedenen Fall in der Konfliktliste.
            if ($einzelner->istAbgebrochen()) {
                continue;
            }

            $ergebnis = $this->ergebnisErsterTeil($einzelner, $zeitraum, $rechnung);

            if (($ergebnis['abbruch'] ?? null) !== ExterneGesamtqualifikationService::ABBRUCH_NACH_ERSTEM_TEIL) {
                continue;
            }

            $gruende[] = $einzelner->full_name
                . ': der erste Prüfungsteil ist nicht bestanden ('
                . $ergebnis['erster_teil']['summe'] . ' von '
                . ExterneGesamtqualifikationService::MINDESTPUNKTE_ERSTER_TEIL
                . ' Punkten erforderlich). Nach § 62 Abs. 1 S. 4 wird die Prüfung nach dem '
                . 'ersten Prüfungsteil abgebrochen — die mündlichen Prüfungen stehen noch '
                . 'in der Planung.';
        }

        return $gruende;
    }

    /**
     * E6 für genau einen Bewerber — trägt die eigentliche Prüfung, die
     * abbruchgruendeErsterTeil() für den Konflikttext und
     * ExterneAbbruchService für den Knopf "Abbruch feststellen" gleichermaßen
     * brauchen. **Eine zweite Kopie dieser Bedingung wäre doppelt so
     * wahrscheinlich falsch** (Fehlerklasse 11) — deshalb steht sie nur hier.
     */
    public function abbruchNachErstemTeilFuer(ExternalApplicant $bewerber, ExamPeriod $zeitraum): bool
    {
        $ergebnis = $this->ergebnisErsterTeil($bewerber, $zeitraum, app(ExterneGesamtqualifikationService::class));

        return ($ergebnis['abbruch'] ?? null) === ExterneGesamtqualifikationService::ABBRUCH_NACH_ERSTEM_TEIL;
    }

    /**
     * @return array<string, mixed>|null null, solange die Fächerwahl
     *         unvollständig ist — dann gibt es nichts zu rechnen.
     */
    private function ergebnisErsterTeil(
        ExternalApplicant $bewerber,
        ExamPeriod $zeitraum,
        ExterneGesamtqualifikationService $rechnung
    ): ?array {
        $faecher = $this->faecherFuer($bewerber, (int) $zeitraum->id);

        if ($faecher === null) {
            return null;
        }

        return $rechnung->berechne($faecher, $bewerber->bewertungsschema());
    }
}
