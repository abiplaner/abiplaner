<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\StudentExam;
use App\Models\WrittenExamYearDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Aus der Fächerwahl eines externen Bewerbers werden Prüfungen.
 *
 * Nicht aus jedem Fach: bringt ein Ersatzschüler nach § 64 Abs. 2 das
 * Ergebnis aus 13/II ein, bleibt das Fach Prüfungsfach, wird aber nicht
 * geprüft. Aus acht Prüfungsfächern werden dann sechs Prüfungen.
 *
 * Seit dem Öffnen von student_exams (Septemberrunde) trägt die Prüfung den
 * Schlüssel auf das Prüfungsfach — `external_exam_subject_id`, unique — und
 * ist damit zugleich als externe Prüfung gekennzeichnet. student_id und
 * course_id bleiben leer; Fach, Niveau und Name des Prüflings holt
 * StudentExam über diese Beziehung aus ihren Quellen.
 *
 * Die frühere Fassung fing jeden Fehler pro Fach ab, legte ihn in ein
 * errors-Array und meldete am Ende Erfolg. Weil sie an ihrer ersten Anweisung
 * scheiterte — einer Spalte, die es nicht gab —, entstanden acht Einträge in
 * diesem Array und keine einzige Zeile in der Datenbank, ohne sichtbaren
 * Abbruch. Deshalb gibt es hier kein errors-Array mehr: was schiefgeht,
 * bricht die Transaktion ab und schlägt nach oben durch.
 */
class ExternalApplicantStudentExamService
{
    /**
     * Legt die Prüfungen eines Bewerbers an oder bringt sie auf Stand.
     *
     * @param int|null $examPeriodId Falls null: der Prüfungszeitraum des Bewerbers
     * @return array{created: int, updated: int, deleted: int}
     */
    public function generateStudentExams(ExternalApplicant $applicant, ?int $examPeriodId = null): array
    {
        $periode = $examPeriodId ?? $applicant->exam_period_id;

        if (! $periode) {
            throw new RuntimeException(
                "Der Bewerber {$applicant->id} hat keinen Prüfungszeitraum (exam_period_id fehlt)."
            );
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, ExternalExamSubject> $faecher */
        $faecher = $applicant->examSubjects()
            ->with(['subject', 'externalApplicant'])
            ->orderBy('exam_part')
            ->orderBy('subject_number')
            ->get();

        $ergebnis = DB::transaction(function () use ($faecher, $periode): array {
            $angelegt = 0;
            $aktualisiert = 0;
            $entfallen = 0;

            foreach ($faecher as $fach) {
                // § 64 Abs. 2 S. 1: für ein eingebrachtes Fach findet keine
                // Prüfung statt. Es bleibt Prüfungsfach — die Punktzahl kommt
                // aus 13/II —, aber es entsteht keine Prüfung, kein Termin und
                // keine Aufsicht. Wandert ein Fach nachträglich in die
                // Einbringung, fällt eine schon angelegte Prüfung hier weg;
                // sonst belegte sie weiter einen Platz im Kolloquiumsplan.
                if ($fach->istEingebracht()) {
                    $entfallen += StudentExam::where('external_exam_subject_id', $fach->id)->delete();

                    continue;
                }

                $daten = $this->datenFuer($fach, (int) $periode);
                $vorhandene = StudentExam::where('external_exam_subject_id', $fach->id)->first();

                if ($vorhandene instanceof StudentExam) {
                    // Wandert ein Fach zwischen den Prüfungsteilen, wird aus
                    // einem Kolloquium eine schriftliche Prüfung. Ein bereits
                    // vergebener Kolloquiumstermin gehört dann nicht mehr
                    // dazu; der schriftliche Termin wird ohnehin neu gesetzt.
                    if ($vorhandene->exam_type !== $daten['exam_type']) {
                        $vorhandene->assignments()->delete();
                    }

                    $vorhandene->update($daten);
                    $aktualisiert++;

                    continue;
                }

                StudentExam::create($daten + ['external_exam_subject_id' => $fach->id]);
                $angelegt++;
            }

            return ['created' => $angelegt, 'updated' => $aktualisiert, 'deleted' => $entfallen];
        });

        Log::info('Prüfungen eines externen Bewerbers erzeugt', [
            'applicant_id' => $applicant->id,
            'created' => $ergebnis['created'],
            'updated' => $ergebnis['updated'],
            'deleted' => $ergebnis['deleted'],
        ]);

        return $ergebnis;
    }

    /**
     * Die Felder einer Prüfung, ohne den Schlüssel auf das Prüfungsfach.
     *
     * Der Prüfer kommt aus der Fächerwahl. Ist dort keiner eingetragen, bleibt
     * das Feld leer — seit der Septemberrunde ist examiner_id nullable, und
     * ein externer Bewerber ohne zugewiesenen Prüfer ist ein zulässiger
     * Zwischenstand. Die frühere Fassung suchte sich stattdessen den ersten
     * aktiven Lehrer des Fachs und brach ab, wenn sie keinen fand.
     *
     * @return array<string, mixed>
     */
    private function datenFuer(ExternalExamSubject $fach, int $examPeriodId): array
    {
        $schriftlich = $fach->exam_part === 'part1_written';

        $daten = [
            'student_id' => null,   // Externe haben keinen Schülerdatensatz
            'course_id' => null,    // und keinen Kurs
            'examiner_id' => $fach->examiner_id,
            'assessor_id' => null,  // wird bei der Planung zugewiesen
            'exam_type' => $schriftlich ? 'written' : 'oral',
            'coupling_id' => null,  // kann später für eine Kopplung gesetzt werden
            'written_exam_year_date_id' => null,
        ];

        if (! $schriftlich) {
            return $daten;
        }

        $daten['written_exam_year_date_id'] = $this->zentralerTermin($fach, $examPeriodId)?->id;

        return $daten;
    }

    /**
     * Der zentrale schriftliche Prüfungstermin für dieses Fach.
     *
     * § 61 Abs. 2 S. 2 sieht ihn nur für die Fächer eins bis drei vor; die
     * Aufgabe im vierten Fach stellt nach S. 7 die prüfende Schule selbst und
     * hat keinen zentralen Termin. Solange das nicht abgebildet ist, wird für
     * alle vier gesucht und ein fehlender Termin protokolliert.
     */
    private function zentralerTermin(ExternalExamSubject $fach, int $examPeriodId): ?WrittenExamYearDate
    {
        $jahr = ExamPeriod::query()->whereKey($examPeriodId)->value('year');
        $fachname = $fach->fachname();

        if (! $jahr || $fachname === null) {
            return null;
        }

        $termin = WrittenExamYearDate::query()
            ->where('exam_year', (int) $jahr)
            ->whereHas('slot.subjects', fn ($q) => $q->where('name', $fachname))
            ->first();

        if (! $termin instanceof WrittenExamYearDate) {
            Log::warning('Kein zentraler schriftlicher Prüfungstermin gefunden', [
                'fach' => $fachname,
                'exam_period_id' => $examPeriodId,
            ]);

            return null;
        }

        return $termin;
    }

    /**
     * Löscht die Prüfungen eines externen Bewerbers.
     *
     * Im Regelfall braucht es das nicht mehr: fällt ein Prüfungsfach aus der
     * Wahl, nimmt der Fremdschlüssel die Prüfung mit (ON DELETE CASCADE) und
     * über deren eigene Kaskaden auch Termine und Zusatzprüfung. Die Methode
     * bleibt für den Fall, dass die Prüfungen ohne die Fächerwahl fallen
     * sollen — etwa beim Zurücksetzen einer Planung.
     */
    public function deleteStudentExams(ExternalApplicant $applicant): int
    {
        $geloescht = StudentExam::externalApplicants()
            ->whereHas('externalExamSubject', fn ($q) => $q->where('external_applicant_id', $applicant->id))
            ->delete();

        Log::info('Prüfungen eines externen Bewerbers gelöscht', [
            'applicant_id' => $applicant->id,
            'deleted' => $geloescht,
        ]);

        return $geloescht;
    }

    /**
     * Alle Bewerber eines Abiturjahrgangs auf einmal.
     *
     * @return array{applicants: int, created: int, updated: int, deleted: int}
     */
    public function generateForAllApplicants(int $year): array
    {
        // forYear() löst das Jahr cross-DB-sicher über die zentralen
        // exam_period-IDs auf (siehe ExternalApplicant::scopeForYear).
        $bewerber = ExternalApplicant::forYear($year)->get();

        $angelegt = 0;
        $aktualisiert = 0;
        $entfallen = 0;

        foreach ($bewerber as $einzelner) {
            $ergebnis = $this->generateStudentExams($einzelner);
            $angelegt += $ergebnis['created'];
            $aktualisiert += $ergebnis['updated'];
            $entfallen += $ergebnis['deleted'];
        }

        return [
            'applicants' => $bewerber->count(),
            'created' => $angelegt,
            'updated' => $aktualisiert,
            'deleted' => $entfallen,
        ];
    }
}
