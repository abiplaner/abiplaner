<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\Zusatzpruefung;

/**
 * Ermittelt die Angaben, die in der Niederschrift des Abiturprüfungsausschusses
 * nicht von Hand zusammengesucht werden sollen (Modul 4.1).
 *
 * Kern ist der Tagesordnungspunkt „Feststellung des Bestehens der Abiturprüfung
 * nach den mündlichen Zusatzprüfungen": Wer ist zum Bestehen angetreten und hat
 * sein Ziel erreicht, wer nicht, und wer ist nur zur Notenverbesserung
 * angetreten.
 *
 * Bewusst NICHT berechnet wird der Gesamtschnitt des Jahrgangs: Dafür wäre die
 * vollständige Gesamtqualifikation (Halbjahresleistungen + Abiturblock) nötig,
 * die Abiplaner nicht führt. Eine Zahl zu erfinden, die anschließend in einer
 * amtlichen Niederschrift steht, wäre der schlechteste denkbare Ausgang.
 */
class ExamBoardMinutesService
{
    public function __construct(
        private readonly AbiturExamDataService $examData,
        private readonly MindestpunkteService $mindestpunkte,
    ) {
    }

    /**
     * Ergebnisübersicht nach den mündlichen Zusatzprüfungen.
     *
     * Rückgabe:
     *   passed        — zum Bestehen angetreten und Ziel erreicht
     *   failed        — zum Bestehen angetreten und nicht bestanden
     *   improved      — zur Notenverbesserung angetreten und Ziel erreicht
     *   notImproved   — zur Notenverbesserung angetreten, Ziel verfehlt
     *   incomplete    — Ergebnis noch nicht vollständig erfasst (mündliche Note fehlt)
     *   absent        — nicht angetreten
     * Jede Liste ist eine Liste von Namen in der Kurzform des Protokolls.
     *
     * @return array{
     *   passed: list<string>, failed: list<string>, improved: list<string>,
     *   notImproved: list<string>, incomplete: list<string>, absent: list<string>,
     *   counts: array<string, int>
     * }
     */
    public function results(ExamPeriod $examPeriod): array
    {
        $pruefungen = Zusatzpruefung::where('exam_period_id', $examPeriod->id)
            // studentExam und examScore, weil improvementGoalReached() über
            // getPointsDelta() geht und das im Sport das Fach und die
            // Praxisnote braucht.
            ->with(['student', 'studentExam.course.subject', 'examScore'])
            ->get();

        $byStudent = $pruefungen->groupBy('student_id');

        $students = Student::whereIn('id', array_keys($byStudent->all()))->get()->keyBy('id');

        // Ob "Tran" als Nachname allein eindeutig ist, entscheidet der ganze
        // Jahrgang — nicht nur der Kreis der Zusatzprüflinge. Sonst stünde im
        // Protokoll ein Name, den zwei Personen tragen.
        $cohort = Student::where('exam_period_id', $examPeriod->id)->get()->all();
        $shortNames = $this->shortNames($students->all(), $cohort);

        $buckets = [
            'passed' => [], 'failed' => [], 'improved' => [],
            'notImproved' => [], 'incomplete' => [], 'absent' => [],
        ];

        foreach ($byStudent as $studentId => $group) {
            $student = $students->get($studentId);
            if ($student === null) {
                continue;
            }
            $name = $shortNames[$studentId];

            if ($group->every(fn (Zusatzpruefung $z): bool => (bool) $z->absent)) {
                $buckets['absent'][] = $name;
                continue;
            }

            $improvementOnly = $group->every(fn (Zusatzpruefung $z): bool => (bool) $z->improvement_only);

            // Ohne eingetragene mündliche Note lässt sich nichts feststellen
            $missingOral = $group->contains(
                fn (Zusatzpruefung $z): bool => ! $z->absent && $z->target_points === null
            );
            if ($missingOral) {
                $buckets['incomplete'][] = $name;
                continue;
            }

            if ($improvementOnly) {
                $reached = $group->every(fn (Zusatzpruefung $z): bool => $this->improvementGoalReached($z));
                $buckets[$reached ? 'improved' : 'notImproved'][] = $name;
                continue;
            }

            $exams  = $this->examData->examsFor($student, (int) $examPeriod->id);
            $result = $this->mindestpunkte->checkReqs($exams);

            if (in_array('missing', $result['failReqs'] ?? [], true)) {
                $buckets['incomplete'][] = $name;
                continue;
            }

            $buckets[$result['pass'] ? 'passed' : 'failed'][] = $name;
        }

        foreach ($buckets as $key => $names) {
            sort($names, SORT_LOCALE_STRING);
            $buckets[$key] = $names;
        }

        $buckets['counts'] = [
            'students'    => $byStudent->count(),
            'pruefungen'  => $pruefungen->count(),
            'passed'      => count($buckets['passed']),
            'failed'      => count($buckets['failed']),
            'improved'    => count($buckets['improved']),
            'notImproved' => count($buckets['notImproved']),
            'incomplete'  => count($buckets['incomplete']),
            'absent'      => count($buckets['absent']),
        ];

        return $buckets;
    }

    /**
     * Hat eine Verbesserungsprüfung ihr Ziel erreicht?
     *
     * Bezugsgröße ist improvement_points_needed (Verbesserung in 4-facher
     * Wertung). Ist keine Zielvorgabe hinterlegt, gilt jede Prüfung als
     * erreicht — es gäbe sonst nichts zu verfehlen.
     *
     * Gefragt wird die tatsächlich erreichte Verbesserung
     * (Zusatzpruefung::getPointsDelta()) statt der Umkehrung „welche
     * mündliche Note wäre nötig gewesen". Für alle Fächer außer Sport sind
     * beide Wege dasselbe — der Beitrag wächst monoton mit der mündlichen
     * Note, `target >= minOral` und `delta >= needed` sind äquivalent. Im
     * Sport war nur der erste richtig: Die frühere Umkehrung kannte die
     * Praxisnote nicht und setzte beide Seiten der Differenz falsch an
     * (27.08.2026, derselbe Befund wie in der Verbesserungsanzeige). Sie ist
     * am 31.08.2026 ganz entfallen, weil sie keinen Aufrufer mehr hatte.
     */
    private function improvementGoalReached(Zusatzpruefung $zp): bool
    {
        if ($zp->improvement_points_needed === null) {
            return true;
        }

        $delta = $zp->getPointsDelta();

        // null = nicht berechenbar (Note fehlt, im Sport auch die Praxisnote)
        if ($delta === null) {
            return false;
        }

        return $delta >= (int) $zp->improvement_points_needed;
    }

    /**
     * Kurzform der Namen für das Protokoll: nur der Nachname, und erst bei
     * Mehrdeutigkeit der Anfangsbuchstabe des Vornamens ("Tran F.") — genau
     * so, wie die Niederschriften bisher von Hand geschrieben wurden.
     *
     * @param  array<int, Student>  $students  die Namen, die gekürzt werden sollen
     * @param  array<int, Student>  $cohort    Bezugsmenge für die Eindeutigkeit
     * @return array<int, string>   student_id → Kurzname
     */
    private function shortNames(array $students, array $cohort = []): array
    {
        $reference = $cohort !== [] ? $cohort : $students;

        $countByLastname = [];
        foreach ($reference as $s) {
            $countByLastname[$s->lastname] = ($countByLastname[$s->lastname] ?? 0) + 1;
        }

        $out = [];
        foreach ($students as $s) {
            $out[$s->id] = ($countByLastname[$s->lastname] ?? 0) > 1
                ? $s->lastname . ' ' . mb_substr((string) $s->firstname, 0, 1) . '.'
                : $s->lastname;
        }

        return $out;
    }
}
