<?php

namespace App\Services;

use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\StudentExam;
use App\Models\WrittenExamYearDate;
use App\Models\ZusatzpruefExamScore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Vorgezogene mündliche Prüfungen externer Bewerber — § 61 Abs. 4 BayGSO.
 *
 * Der Wortlaut, aus dem alles Folgende kommt:
 *
 *   (4) 1Auf Wunsch der Bewerberin oder des Bewerbers können zwei der vier
 *   mündlichen Prüfungen im Zeitraum zwei Wochen vor Beginn der schriftlichen
 *   Abiturprüfungen stattfinden. 2Sollten in diesen beiden Prüfungen die
 *   Bedingungen von § 62 Abs. 2 bereits nicht erfüllt werden, so wird die
 *   Prüfung abgebrochen. 3Die Ergebnisse werden ansonsten erst nach Abschluss
 *   der schriftlichen Prüfungen mitgeteilt.
 *
 * Satz 3 ist nicht abgebildet: Entscheidung Wilfrieds vom 31.08.2026 — rein
 * organisatorisch, die Anwendung unterscheidet nichts. Er steht in der Hilfe.
 *
 * Eine Zuweisung gilt als vorgezogen, wenn `exam_week` den Wert
 * ExamAssignment::WOCHE_VORGEZOGEN trägt. Sie hat keinen Zeitslot, sondern
 * Datum und Uhrzeit von Hand — wie die Nachtermine außerhalb der
 * Prüfungswochen. Entscheidung Wilfrieds: keine automatische Zuordnung, keine
 * eigenen Slots. Es sind wenige Bewerber, und die Termine werden einzeln
 * vereinbart.
 */
class VorgezogeneKolloquienService
{
    /**
     * Zwei der vier mündlichen Prüfungen (Satz 1) — die Obergrenze je Bewerber.
     *
     * Der Wert steht nicht hier, sondern beim Rechenwerk zu § 61 Abs. 4: Satz 1
     * und Satz 2 hängen aneinander, und eine zweite Zahl liefe irgendwann
     * auseinander.
     */
    public const HOECHSTZAHL = ExterneGesamtqualifikationService::HOECHSTZAHL_VORGEZOGENE;

    /**
     * Der Zeitraum „zwei Wochen vor Beginn der schriftlichen Abiturprüfungen".
     *
     * AUSLEGUNG: gelesen als die vierzehn Tage, die dem ersten schriftlichen
     * Prüfungstag unmittelbar vorausgehen — Ende ist der Vortag, Beginn
     * dreizehn Tage davor. Für das Abitur 2027 (erster schriftlicher Tag
     * 21.04.2027) also 07.04.2027 bis 20.04.2027 einschließlich.
     *
     * Die zweite denkbare Lesart wäre „ab zwei Wochen vorher, bis zum Beginn" —
     * sie ergibt dasselbe Fenster. Offen bliebe allein, ob der erste
     * schriftliche Tag selbst noch dazugehört; „vor Beginn" schließt ihn aus.
     *
     * Der erste schriftliche Tag steht nicht am Prüfungszeitraum, sondern in
     * den zentralen Jahresterminen (written_exam_year_dates). Das Fenster wird
     * deshalb abgeleitet und nicht gepflegt: Es gibt keinen zweiten Ort, an dem
     * es falsch stehen könnte.
     *
     * @return array{start: CarbonImmutable, ende: CarbonImmutable}|null
     *         null, solange für den Jahrgang keine schriftlichen Termine
     *         eingetragen sind — dann ist das Fenster nicht bestimmbar.
     */
    public function fenster(ExamPeriod $zeitraum): ?array
    {
        $ersterSchriftlicher = WrittenExamYearDate::where('exam_year', $zeitraum->year)
            ->min('exam_date');

        if ($ersterSchriftlicher === null) {
            return null;
        }

        $ende = CarbonImmutable::parse($ersterSchriftlicher)->subDay()->startOfDay();

        return [
            'start' => $ende->subDays(13),
            'ende' => $ende,
        ];
    }

    /**
     * Liegt das Datum im vorgezogenen Zeitraum?
     *
     * Ohne bestimmbares Fenster ist die Antwort nein — lieber eine Eingabe
     * abweisen als sie ungeprüft durchlassen.
     */
    public function liegtImFenster(ExamPeriod $zeitraum, CarbonInterface $datum): bool
    {
        $fenster = $this->fenster($zeitraum);

        if ($fenster === null) {
            return false;
        }

        $tag = CarbonImmutable::parse($datum)->startOfDay();

        return $tag->greaterThanOrEqualTo($fenster['start'])
            && $tag->lessThanOrEqualTo($fenster['ende']);
    }

    /**
     * Das Fenster als Satz, für Meldungen und Masken.
     */
    public function fensterText(ExamPeriod $zeitraum): string
    {
        $fenster = $this->fenster($zeitraum);

        if ($fenster === null) {
            return 'Für diesen Jahrgang sind noch keine schriftlichen Prüfungstermine eingetragen.';
        }

        return $fenster['start']->format('d.m.Y') . ' bis ' . $fenster['ende']->format('d.m.Y');
    }

    /**
     * Wie viele vorgezogene Prüfungen der Bewerber schon hat.
     *
     * Abgesagte zählen nicht mit: Eine wegen Erkrankung abgesagte Prüfung ist
     * kein verbrauchter Platz.
     */
    public function anzahlFuer(ExternalApplicant $bewerber, ?int $ausserPruefungId = null): int
    {
        $query = ExamAssignment::where('exam_week', ExamAssignment::WOCHE_VORGEZOGEN)
            ->where('status', '!=', 'cancelled')
            ->whereHas(
                'studentExam',
                fn ($q) => $q->whereHas(
                    'externalExamSubject',
                    fn ($ees) => $ees->where('external_applicant_id', $bewerber->id)
                )
            );

        if ($ausserPruefungId !== null) {
            $query->where('student_exam_id', '!=', $ausserPruefungId);
        }

        return $query->count();
    }

    /**
     * Die vorgezogenen Prüfungen eines Zeitraums, für die Maske.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ExamAssignment>
     */
    public function zuweisungen(ExamPeriod $zeitraum)
    {
        return ExamAssignment::where('exam_week', ExamAssignment::WOCHE_VORGEZOGEN)
            ->where('status', '!=', 'cancelled')
            ->whereHas(
                'studentExam',
                fn ($q) => $q->where('exam_type', 'oral')->imPruefungszeitraum($zeitraum)
            )
            ->with([
                'studentExam.examiner',
                'studentExam.assessor',
                'studentExam.externalExamSubject.subject',
                'studentExam.externalExamSubject.externalApplicant',
                'examRoom',
            ])
            ->orderBy('free_date')
            ->orderBy('free_time_start')
            ->get();
    }

    /**
     * D2 für genau einen Bewerber (Entscheidung 51: Grundlage für den Knopf
     * "Abbruch feststellen"). Prüft dieselbe Bedingung wie abbruchgruende(),
     * nur auf die Zuweisungen dieses einen Bewerbers eingeschränkt — die
     * Zahl der vorgezogenen Prüfungen insgesamt ist klein genug, dass ein
     * Filtern in der bereits geladenen Collection keine eigene Abfrage
     * braucht.
     */
    public function abbruchFuer(ExternalApplicant $bewerber, ExamPeriod $zeitraum): bool
    {
        $eigene = $this->zuweisungen($zeitraum)->filter(
            fn (ExamAssignment $z): bool => $z->studentExam?->externalExamSubject?->external_applicant_id === $bewerber->id
        );

        if ($eigene->isEmpty()) {
            return false;
        }

        $rechnung = app(ExterneGesamtqualifikationService::class);
        $pruefungsIds = $eigene->map(static fn (ExamAssignment $z): int => $z->student_exam_id)->all();

        $noten = ZusatzpruefExamScore::whereIn('student_exam_id', $pruefungsIds)
            ->where('exam_period_id', $zeitraum->id)
            ->get()
            ->keyBy('student_exam_id');

        $vorgezogene = $eigene->map(function (ExamAssignment $z) use ($noten): array {
            $fach = $z->studentExam->externalExamSubject;
            $note = $noten->get($z->student_exam_id);

            return ['position' => $fach->position(), 'punkte' => $note?->score];
        })->values()->all();

        return $rechnung->abbruchNachVorgezogenenMuendlichen($vorgezogene)['abbruch'];
    }

    /**
     * Abbruchgründe nach § 61 Abs. 4 S. 2, ein Satz je betroffenem Bewerber.
     *
     * **Die Regel selbst steht nicht hier**, sondern in
     * `ExterneGesamtqualifikationService::abbruchNachVorgezogenenMuendlichen()`
     * — dort, wo auch der Rest der § 62-Rechnung steht, samt der Herleitung
     * und ihren Unit-Tests. Diese Methode holt nur die Noten aus der Datenbank
     * und übersetzt das Ergebnis in Sätze für die Konfliktliste.
     *
     * Am 31.08.2026 stand die Auslegung eine halbe Stunde lang zweimal im
     * Code: Die ältere Fassung rief niemand auf, und deshalb fiel sie beim
     * Suchen nicht auf. **Eine zweite Kopie einer Abbildung ist doppelt so
     * wahrscheinlich falsch** — hier wären es zwei Stellen gewesen, an denen
     * dieselbe Rechtsauslegung hätte auseinanderlaufen können.
     *
     * @return list<string>
     */
    public function abbruchgruende(ExamPeriod $zeitraum): array
    {
        $zuweisungen = $this->zuweisungen($zeitraum);

        if ($zuweisungen->isEmpty()) {
            return [];
        }

        $pruefungsIds = $zuweisungen
            ->map(static fn (ExamAssignment $z): int => $z->student_exam_id)
            ->all();

        $noten = ZusatzpruefExamScore::whereIn('student_exam_id', $pruefungsIds)
            ->where('exam_period_id', $zeitraum->id)
            ->get()
            ->keyBy('student_exam_id');

        $rechnung = app(ExterneGesamtqualifikationService::class);
        $gruende = [];

        foreach ($zuweisungen as $zuweisung) {
            $pruefung = $zuweisung->studentExam;

            if (! $pruefung instanceof StudentExam) {
                continue;
            }

            $fach = $pruefung->externalExamSubject;

            if ($fach === null) {
                continue;
            }

            // Wer den Abbruch schon festgestellt hat (Entscheidung 51),
            // braucht die Warnung nicht mehr.
            if ($fach->externalApplicant?->istAbgebrochen() === true) {
                continue;
            }

            $note = $noten->get($zuweisung->student_exam_id);

            // Je Prüfung einzeln hinein, nicht je Bewerber: Die Regel
            // entscheidet ohnehin fachweise, und die Meldung soll sagen
            // können, welches Fach die Null trägt. Dass es höchstens zwei je
            // Bewerber sind, setzt schon die Zuweisung durch.
            $ergebnis = $rechnung->abbruchNachVorgezogenenMuendlichen([
                ['position' => $fach->position(), 'punkte' => $note?->score],
            ]);

            if ($ergebnis['abbruch'] !== true) {
                continue;
            }

            $gruende[] = $pruefung->getStudentName()
                . ': vorgezogene Prüfung in ' . $pruefung->getSubjectLabel()
                . '. ' . $ergebnis['gruende'][0]
                . ' Nach § 61 Abs. 4 S. 2 ist die Prüfung abzubrechen.';
        }

        return $gruende;
    }
}
