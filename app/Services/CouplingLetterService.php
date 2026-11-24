<?php

namespace App\Services;

use App\Models\Course;
use App\Models\StudentExam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erfassung der inhaltlichen Kopplungen aus dem Papierformular (Modul 4.7).
 *
 * Die Lehrkraft trägt auf dem ausgedruckten Formular je Prüfling einen
 * Gruppenbuchstaben ein (A, B, C ...). Gleiche Buchstaben innerhalb eines
 * Kurses bilden eine Koppelgruppe; die OSK tippt diese Buchstaben hier ab.
 *
 * Bewusst getrennt vom CouplingController: Dieser koppelt über Einzelauswahl
 * und lehnt bereits gekoppelte Prüfungen ab. Beim Abtippen muss ein
 * Korrekturlauf bestehende Gruppen dagegen ersetzen können.
 */
class CouplingLetterService
{
    /**
     * Übernimmt die Gruppenbuchstaben eines Kurses.
     *
     * Gleiche Buchstaben (Groß-/Kleinschreibung egal) werden zu einer Gruppe
     * mit gemeinsamer coupling_id. Buchstaben, die nur einmal vergeben sind,
     * ergeben keine Kopplung – eine Gruppe braucht mindestens zwei Prüflinge.
     * Leere Eingaben lösen eine bestehende Kopplung.
     *
     * @param array<int, string> $letters student_exam_id => Buchstabe (leer = keine Gruppe)
     * @return array{grouped: int, groups: int, single: int, ungrouped: int}
     */
    public function apply(Course $course, array $letters): array
    {
        // Nur Q13: Ein jahrgangsübergreifender Kurs („3…“) hat auch
        // Kolloquien der Q12, die das Formular nicht zeigt.
        $exams = StudentExam::where('course_id', $course->id)
            ->where('exam_type', 'oral')
            ->derStufe('13')
            ->get()
            ->keyBy('id');

        // Nur Prüfungen dieses Kurses berücksichtigen – fremde IDs ignorieren.
        $byLetter = [];
        foreach ($letters as $examId => $letter) {
            $normalized = $this->normalizeLetter((string) $letter);

            if ($normalized === '' || !$exams->has((int) $examId)) {
                continue;
            }

            $byLetter[$normalized][] = (int) $examId;
        }

        $stats = ['grouped' => 0, 'groups' => 0, 'single' => 0, 'ungrouped' => 0];

        DB::transaction(function () use ($course, $exams, $byLetter, &$stats): void {
            // Alle Kopplungen des Kurses zurücksetzen: Der Formularstand ist
            // maßgeblich, ein Korrekturlauf darf alte Gruppen nicht stehen lassen.
            StudentExam::where('course_id', $course->id)
                ->where('exam_type', 'oral')
                ->derStufe('13')
                ->update(['coupling_id' => null]);

            foreach ($byLetter as $examIds) {
                if (count($examIds) < 2) {
                    // Einzelner Buchstabe: keine Kopplung, aber vermutlich ein
                    // Tippfehler – wird der OSK zurückgemeldet.
                    $stats['single']++;
                    continue;
                }

                StudentExam::whereIn('id', $examIds)
                    ->update(['coupling_id' => (string) Str::uuid()]);

                $stats['groups']++;
                $stats['grouped'] += count($examIds);
            }

            // Prüflinge ohne Gruppe: alle, die weder in einer Koppelgruppe noch
            // mit einem (einzeln gebliebenen) Buchstaben versehen sind.
            $stats['ungrouped'] = $exams->count() - $stats['grouped'] - $stats['single'];
        });

        return $stats;
    }

    /**
     * Buchstaben vergleichbar machen: "a" und "A" sind dieselbe Gruppe.
     */
    private function normalizeLetter(string $letter): string
    {
        return mb_strtoupper(trim($letter));
    }

    /**
     * Leitet aus den gespeicherten Kopplungen wieder Buchstaben ab, damit die
     * Eingabefelder den aktuellen Stand zeigen.
     *
     * Gespeichert wird eine UUID, kein Buchstabe – die Zuordnung entsteht hier
     * neu, in der Reihenfolge der Prüflinge. Der konkrete Buchstabe kann daher
     * von dem auf dem Papier abweichen; die Gruppierung bleibt identisch, und
     * darauf allein kommt es an.
     *
     * @param \Illuminate\Support\Collection<int, StudentExam> $exams
     * @return array<int, string> student_exam_id => Buchstabe
     */
    public function lettersFor($exams): array
    {
        $letterByCoupling = [];
        $letters = [];
        $next = 0;

        foreach ($exams as $exam) {
            if ($exam->coupling_id === null) {
                $letters[$exam->id] = '';
                continue;
            }

            if (!isset($letterByCoupling[$exam->coupling_id])) {
                $letterByCoupling[$exam->coupling_id] = $this->letterAt($next);
                $next++;
            }

            $letters[$exam->id] = $letterByCoupling[$exam->coupling_id];
        }

        return $letters;
    }

    /**
     * 0 => A, 1 => B ... 25 => Z, 26 => AA (Kurse mit mehr als 26 Gruppen sind
     * praktisch ausgeschlossen, aber der Fall soll nicht in einen Fehler laufen).
     */
    private function letterAt(int $index): string
    {
        $letter = '';

        do {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letter;
    }
}
