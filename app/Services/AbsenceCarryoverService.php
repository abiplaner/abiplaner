<?php

namespace App\Services;

use App\Models\AbsenceCarryover;
use App\Models\ExamPeriod;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Vorjahres-Absenzkennzahlen einfrieren und zuordnen (Modul 1.1, Phase 3).
 *
 * freezeForPeriod():  Wizard-Schritt 3 — Aggregate aus den Rohdaten der
 *                     Quell-Periode erzeugen, BEVOR diese gelöscht werden.
 *                     Idempotent (updateOrCreate), damit der Wizard
 *                     resumierbar bleibt.
 * matchCarryovers():  Wizard-Schritt 6 — Namensabgleich gegen die Schüler
 *                     der Ziel-Periode: external_id, dann exakter Name,
 *                     dann 8.1-Toleranz (Rufname/Doppelname/Partikel,
 *                     siehe NameMatchingService) — jeweils nur bei
 *                     eindeutigem Treffer.
 * purgeExcept():      beim nächsten Wechsel Carryovers des Vor-Vorjahres
 *                     löschen (Aufbewahrung genau ein Jahr).
 */
class AbsenceCarryoverService
{
    public function __construct(
        private AbsenceEvaluationService $evaluation,
        private NameMatchingService $names
    ) {}

    /**
     * Friert die Kennzahlen aller Schüler der Quell-Periode ein, die
     * mindestens eine Absenz haben. Gibt die Anzahl der Zeilen zurück.
     */
    public function freezeForPeriod(ExamPeriod $sourcePeriod, string $sourceSchoolYear): int
    {
        $thresholds = $this->evaluation->thresholds();
        $count = 0;

        Student::where('exam_period_id', $sourcePeriod->id)
            ->whereHas('absences')
            ->orderBy('id')
            ->chunkById(100, function ($students) use ($thresholds, $sourceSchoolYear, &$count) {
                foreach ($students as $student) {
                    $this->freezeStudent($student, $thresholds, $sourceSchoolYear);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param array{krank_percent: float, unentschuldigt_count: int, verspaetet_count: int, schultage_bisher: int, schultage_automatisch: bool} $thresholds
     */
    private function freezeStudent(Student $student, array $thresholds, string $sourceSchoolYear): void
    {
        $resetDates = $this->evaluation->resetDates($student);
        $metrics    = $this->evaluation->evaluate($student, $thresholds, $resetDates);
        $attest     = $this->evaluation->attestpflichtStatus($student);

        // Stunden/Minuten mit denselben Reset-Stichtagen wie die Auswertung
        $unexcusedHours = (int) $student->absences()
            ->where('category', 'fehlt')
            ->where('excuse_status', 'nein')
            ->when($resetDates['unentschuldigt'] !== null, fn ($q) => $q->where('date_from', '>', $resetDates['unentschuldigt']))
            ->sum('hours');

        $tardinessMinutes = (int) $student->absences()
            ->where('category', 'verspaetet')
            ->when($resetDates['verspaetung'] !== null, fn ($q) => $q->where('date_from', '>', $resetDates['verspaetung']))
            ->sum('minutes_late');

        AbsenceCarryover::updateOrCreate(
            [
                'lastname'           => $student->lastname,
                'firstname'          => $student->firstname,
                'source_school_year' => $sourceSchoolYear,
            ],
            [
                'external_id'            => $student->external_id,
                'source_grade'           => $student->grade,
                'sick_days'              => (int) $metrics['krank_days'],
                'sick_percent'           => $metrics['krank_percent'],
                'unexcused_hours'        => $unexcusedHours,
                'tardiness_count'        => (int) $metrics['verspaetet_count'],
                'tardiness_minutes'      => $tardinessMinutes,
                'had_attest_requirement' => (bool) $attest['active'],
            ]
        );
    }

    /**
     * Ordnet noch nicht zugeordnete Carryovers eines Quell-Schuljahres den
     * Schülern der Ziel-Periode zu. Reihenfolge: external_id (eindeutig),
     * dann exakter Name (case-insensitive, eindeutig), dann 8.1-Toleranz
     * (Rufname/Doppelname/Partikel, eindeutig). Mehrdeutige Fälle bleiben
     * unzugeordnet (manuelle Zuordnung in der Klärungsliste). Gibt die
     * Anzahl der automatisch zugeordneten Zeilen zurück.
     */
    public function matchCarryovers(string $sourceSchoolYear, ExamPeriod $targetPeriod): int
    {
        $matched = 0;

        $carryovers = AbsenceCarryover::where('source_school_year', $sourceSchoolYear)
            ->whereNull('matched_student_id')
            ->get();

        // Zielschüler einmal laden — die drei Abgleichsstufen laufen in PHP
        // (nötig für die 8.1-Toleranz, die sich nicht sauber in SQL abbilden
        // lässt; bei Jahrgangsgrößen von wenigen hundert Schülern unkritisch).
        $targetStudents = Student::where('exam_period_id', $targetPeriod->id)
            ->orderBy('id')
            ->get(['id', 'lastname', 'firstname', 'external_id']);

        foreach ($carryovers as $carryover) {
            $student = $this->findTargetStudent($carryover, $targetStudents);

            if ($student === null) {
                continue;
            }

            $carryover->update([
                'matched_student_id' => $student->id,
                'matched_at'         => now(),
                'match_method'       => 'auto',
            ]);
            $matched++;
        }

        return $matched;
    }

    /**
     * @param Collection<int, Student> $targetStudents
     */
    private function findTargetStudent(AbsenceCarryover $carryover, Collection $targetStudents): ?Student
    {
        // 1. external_id — stärkstes Signal, muss eindeutig sein
        if ($carryover->external_id !== null && $carryover->external_id !== '') {
            $byExternalId = $targetStudents
                ->where('external_id', $carryover->external_id);

            if ($byExternalId->count() === 1) {
                return $byExternalId->first();
            }
        }

        // 2. exakter Name (case-insensitive), nur bei eindeutigem Treffer
        $byName = $targetStudents->filter(
            fn (Student $student) => mb_strtolower($student->lastname) === mb_strtolower($carryover->lastname)
                && mb_strtolower($student->firstname) === mb_strtolower($carryover->firstname)
        );

        if ($byName->count() === 1) {
            return $byName->first();
        }
        if ($byName->count() > 1) {
            // mehrdeutig schon beim exakten Namen — die (großzügigere)
            // Toleranzstufe kann das nicht mehr eindeutig machen
            return null;
        }

        // 3. 8.1-Toleranz: Rufname/Doppelname/Namenspartikel (Modul 8.1,
        //    siehe NameMatchingService), nur bei eindeutigem Treffer
        $byTolerance = $targetStudents->filter(
            fn (Student $student) => $this->names->lastnamesMatch($student->lastname, $carryover->lastname)
                && $this->names->firstnamesMatch($student->firstname, $carryover->firstname)
        );

        return $byTolerance->count() === 1 ? $byTolerance->first() : null;
    }

    /**
     * Löscht alle Carryovers außer denen des angegebenen Schuljahres
     * (Aufbewahrung genau ein Jahr, keine Kettenbildung).
     */
    public function purgeExcept(string $keepSourceSchoolYear): int
    {
        return AbsenceCarryover::where('source_school_year', '!=', $keepSourceSchoolYear)->delete();
    }
}
