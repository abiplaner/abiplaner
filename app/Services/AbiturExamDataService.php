<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\ZusatzpruefExamScore;
use App\Models\Zusatzpruefung;

/**
 * Baut die Fächer-Übersicht eines Prüflings in genau der Form, die der
 * MindestpunkteService erwartet.
 *
 * Zuvor lag diese Abbildung nur im ZusatzpruefungController (Mindestpunkte-
 * Modal). Für die Niederschrift des Abiturprüfungsausschusses (Modul 4.1) wird
 * dieselbe Struktur gebraucht, um festzustellen, wer die Abiturprüfung nach den
 * mündlichen Zusatzprüfungen bestanden hat. Zwei Kopien derselben Abbildung
 * wären genau die Art von Duplikat, die irgendwann auseinanderläuft — deshalb
 * hier einmal zentral.
 */
class AbiturExamDataService
{
    /**
     * Sportfächer haben eine Praxisnote, die in die Berechnung eingeht.
     *
     * Die Liste der Kürzel steht am Katalogeintrag (Subject::istSport()); hier
     * bleibt nur die Null-Behandlung.
     */
    public function isSportSubject(?Subject $subject): bool
    {
        return $subject?->istSport() ?? false;
    }

    /**
     * Alle Abitur-Prüfungsfächer eines Schülers.
     *
     * @return list<array<string, mixed>>
     */
    public function examsFor(Student $student, int $examPeriodId): array
    {
        return $this->examsForPruefling('s' . $student->id, $examPeriodId);
    }

    /**
     * Alle Abitur-Prüfungsfächer eines Prüflings mit Noten, ZP-Kennzeichnung
     * und den Merkmalen, die für die GSO-Anforderungsprüfung nötig sind.
     *
     * Sortierung: schriftliche vor mündlichen Fächern, darin nach Fachkürzel —
     * dieselbe Reihenfolge wie im Mindestpunkte-Modal.
     *
     * @param string $pruefling Prüflingsschlüssel „s<id>" oder „e<id>"
     * @return list<array<string, mixed>>
     */
    public function examsForPruefling(string $pruefling, int $examPeriodId): array
    {
        $allStudentExams = StudentExam::desPrueflings($pruefling)
            ->with([
                'course.subject',
                'externalExamSubject.subject',
                'externalExamSubject.externalApplicant',
            ])
            ->get();

        $pruefungen = Zusatzpruefung::whereHas(
                'studentExam',
                fn ($q) => $q->desPrueflings($pruefling)
            )
            ->where('exam_period_id', $examPeriodId)
            ->get();

        $zpExamIds = $pruefungen->pluck('student_exam_id')->all();

        // Schlüssel ist student_exam_id allein — student_id ist für externe
        // Bewerber null und taugt nicht als Filter.
        $savedScores = ZusatzpruefExamScore::whereIn('student_exam_id', $allStudentExams->pluck('id'))
            ->where('exam_period_id', $examPeriodId)
            ->get()
            ->keyBy('student_exam_id');

        return $allStudentExams->map(function (StudentExam $se) use ($zpExamIds, $pruefungen, $savedScores) {
            $isZP    = in_array($se->id, $zpExamIds, true);
            $zp      = $isZP ? $pruefungen->firstWhere('student_exam_id', $se->id) : null;
            // Fach und Kürzel kommen aus dem zentralen Katalog — bei einem
            // externen Bewerber über sein Prüfungsfach, bei einem Schüler
            // über den Kurs.
            $subject = $se->fachAusKatalog();
            $cc      = $se->getSubjectLabel();

            return [
                'id'                => $se->id,
                'subject'           => $subject instanceof Subject ? $subject->name : '–',
                'subjectCode'       => $subject instanceof Subject ? ($subject->code ?? '') : '',
                'courseCode'        => $cc,
                'level'             => $se->getLevel() ?? 'grundlegend',
                'examType'          => $se->exam_type,
                'isForeignLanguage' => (bool) ($subject?->is_foreign_language),
                'isScience'         => (bool) ($subject?->is_science),
                'aufgabenfeld'      => $subject instanceof Subject ? $subject->aufgabenfeld : null,
                // Das Leistungsfach ist nicht dasselbe wie erhöhtes Niveau:
                // Deutsch und Mathematik sind bei Externen immer eA, ohne
                // Leistungsfach zu sein. Die Ableitung steht am Model.
                'isLeistungsfach'   => $se->istLeistungsfach(),
                'isDeutsch'         => $subject?->code === 'D',
                'isMathematik'      => $se->istMathematik(),
                'isZusatzpruefung'  => $isZP,
                'writtenPoints'     => $isZP ? $zp?->written_points : null,
                'targetPoints'      => $isZP ? $zp?->target_points : null,
                'pruefungId'        => $isZP ? $zp?->id : null,
                'sort_order'        => $isZP ? ($zp->sort_order ?? 0) : 0,
                'isSport'           => $this->isSportSubject($subject),
                // Die Praxisnote gehört zu jedem Sportfach, nicht nur zu einem
                // mit Zusatzprüfung.
                //
                // Sport ist Abiturprüfungsfach nur als Leistungsfach (§ 48
                // Abs. 1 Nr. 1), und eine Zusatzprüfung gibt es nur nach einer
                // schriftlichen Prüfung (Wilfried, 27.08.2026). Damit gibt es
                // zwei Wege, und MindestpunkteService liest die Praxisnote auf
                // beiden: bei **Sport schriftlich mit Zusatzprüfung** über
                // contribAndCombinedFor(), bei **Sport als Leistungsfach im
                // Kolloquium** über scoreContribution() —
                // (score + sportPracticalGrade) × 2, und ohne Praxisnote gibt
                // die Methode null zurück.
                //
                // Diese Abbildung setzte sie bis zum 27.08.2026 nur im ZP-Fall,
                // die Kopie im ZusatzpruefungController nur im Nicht-ZP-Fall.
                // Jede deckte die Hälfte ab, die die andere verfehlte: der
                // Zusatzprüfungs-Weg war hier richtig, das Sport-Kolloquium
                // trug gar nichts bei (in den Echtdaten vier Fälle).
                'sportPracticalGrade' => $this->isSportSubject($subject)
                    ? ($savedScores[$se->id]->practical_grade ?? null)
                    : null,
                // Zwei Schreibweisen in einer Zeile, und das mit Absicht: Diese
                // Reihe bedient zwei Verträge. Was die Maske im Formular
                // zurückschickt, heisst wie die Spalte — `practical_grade`,
                // `improvement_only`; das JavaScript liest und postet genau
                // diese Namen. Was der MindestpunkteService als Eingabe
                // erwartet, folgt dagegen seinem eigenen, durchgehend
                // camelCase geschriebenen Vertrag (`sportPracticalGrade`,
                // `writtenPoints`, `improvementPointsNeeded`).
                //
                // `sportPracticalGrade` und `practical_grade` tragen deshalb
                // denselben Wert unter zwei Namen. Das sieht nach Schlamperei
                // aus und stand als solche in der Aufräumliste; beim Nachsehen
                // am 31.08.2026 war es keine. Wer eine der beiden Schreibweisen
                // vereinheitlicht, bricht einen der beiden Leser.
                'score'             => $isZP ? null : ($savedScores[$se->id]->score ?? null),
                'practical_grade'   => $savedScores[$se->id]->practical_grade ?? null,
                'improvement_only'  => $isZP ? (bool) ($zp?->improvement_only) : false,
                'improvementPointsNeeded' => $isZP ? $zp?->improvement_points_needed : null,
            ];
        })
            ->sortBy(fn ($e) => ($e['examType'] === 'written' ? '0' : '1') . $e['subjectCode'])
            ->values()
            ->all();
    }
}
