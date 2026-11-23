<?php

namespace App\Services;

use App\Models\StudentExam;
use App\Models\ZusatzpruefExamScore;

/**
 * Im Sport gehoeren Theorie- und Praxisnote zusammen (Entscheidung 40,
 * 28.08.2026).
 *
 * Der Grund ist keine Ordnungsliebe, sondern eine Rechnung, die sonst nicht
 * stattfindet. Ein Sportfach traegt nach § 52 Abs. 2 Nr. 2 GSO die Praxisnote
 * gleichberechtigt neben der Theorienote:
 *
 * - ohne Zusatzpruefung `(Theorie + Praxis) × 2`
 *   (MindestpunkteService::scoreContribution()),
 * - mit Zusatzpruefung `round((Praxis + Theorie + muendlich) × 4 / 3)`
 *   (calcSportZpContribution()).
 *
 * Fehlt die Praxisnote, geben beide Wege `null` zurueck — und `null` ist kein
 * Beitrag von 0, sondern gar keiner: Im Modal und in der Niederschrift des
 * Pruefungsausschusses faellt das Fach schlicht heraus, und
 * `allFixedScoresEntered` steht faelschlich auf `false`. In den Echtdaten betraf das
 * vier Sport-Kolloquien; sechs weitere tragen bis heute keine Praxisnote.
 *
 * Deshalb wird der Zustand hier verhindert, statt ihn spaeter zu melden:
 * **eine Theorienote im Sport ist ohne Praxisnote nicht speicherbar.**
 *
 * Umgekehrt gilt die Regel nicht: Die Praxisnote allein darf jederzeit
 * eingetragen werden. Wer zuerst die Praxis und spaeter die Theorie erfasst,
 * arbeitet damit unbehelligt — verboten ist allein die Reihenfolge, die einen
 * halben Datensatz hinterlaesst.
 *
 * Diese Regel steht **einmal**. Sie wird aus drei Speicherwegen gerufen
 * (`store`, `storeExamScore`, `updatePoints`); eine zweite Fassung waere nach
 * aller Erfahrung dieses Projekts die falsche.
 */
class SportNotenRegel
{
    /**
     * Darf zu dieser Pruefung eine Theorienote gespeichert werden?
     *
     * @param  ?int $score           die zu speichernde Theorienote (null = keine)
     * @param  bool $praxisImRequest ob der Aufruf ueberhaupt eine Praxisnote mitfuehrt
     * @param  ?int $praxisNeu       die mitgefuehrte Praxisnote
     * @return ?string Meldung bei Verstoss, sonst null
     */
    public function pruefeNote(
        StudentExam $pruefung,
        ?int $score,
        bool $praxisImRequest = false,
        ?int $praxisNeu = null
    ): ?string {
        if (! $pruefung->istSport() || $score === null) {
            return null;
        }

        if ($this->praxisNachher($pruefung, $praxisImRequest, $praxisNeu) !== null) {
            return null;
        }

        return $this->meldung($pruefung)
            . ' Ohne sie zählt das Fach in der Abiturrechnung gar nicht.';
    }

    /**
     * Darf auf dieser Pruefung eine Zusatzpruefung entstehen oder ihre Punkte
     * gespeichert werden?
     *
     * Hier genuegt schon das Anlegen: eine Sport-Zusatzpruefung ohne
     * Praxisnote kann weder ihren Beitrag noch ihre Verbesserung ausweisen
     * (Zusatzpruefung::getExamResultPoints() und ::getPointsDelta() geben
     * `null` zurueck, die Uebersicht zeigt „→ ?").
     *
     * @return ?string Meldung bei Verstoss, sonst null
     */
    public function pruefeZusatzpruefung(
        StudentExam $pruefung,
        bool $praxisImRequest = false,
        ?int $praxisNeu = null
    ): ?string {
        if (! $pruefung->istSport()) {
            return null;
        }

        if ($this->praxisNachher($pruefung, $praxisImRequest, $praxisNeu) !== null) {
            return null;
        }

        return $this->meldung($pruefung)
            . ' Ohne sie lässt sich weder der Abiturbeitrag noch die Verbesserung berechnen.';
    }

    /**
     * Welche Praxisnote stuende nach diesem Speichervorgang in der Datenbank?
     *
     * Fuehrt der Aufruf eine Praxisnote mit, gilt sie — auch wenn sie `null`
     * ist: Das Loeschen einer vorhandenen Praxisnote stellt genau den Zustand
     * her, den diese Regel verhindert, und muss deshalb ebenso abgewiesen
     * werden. Nur wenn der Aufruf das Feld gar nicht mitfuehrt, zaehlt der
     * gespeicherte Wert.
     */
    private function praxisNachher(StudentExam $pruefung, bool $praxisImRequest, ?int $praxisNeu): ?int
    {
        if ($praxisImRequest) {
            return $praxisNeu;
        }

        $gespeichert = ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->first();

        return $gespeichert instanceof ZusatzpruefExamScore ? $gespeichert->practical_grade : null;
    }

    private function meldung(StudentExam $pruefung): string
    {
        $fach = $pruefung->getSubjectName() ?? 'das Sportfach';

        return "Für {$fach} fehlt die Praxisnote.";
    }
}
