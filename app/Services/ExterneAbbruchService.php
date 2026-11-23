<?php

namespace App\Services;

use App\Models\ExamAssignment;
use App\Models\ExternalApplicant;
use App\Models\Zusatzpruefung;
use App\Models\ZusatzpruefExamScore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Entscheidung 51 (01.09.2026): ein echter Abbruch-Zustand statt nur einer
 * Meldung.
 *
 * Bis zum 01.09.2026 galt: "Die Anwendung meldet den Abbruchgrund, sie bricht
 * nicht ab." Die Rechnung (ExterneGesamtqualifikationService::berechne() für
 * E6, ::abbruchNachVorgezogenenMuendlichen() für D2) ändert sich damit nicht
 * — sie liefert weiterhin nur die Auskunft. Was hier dazukommt, ist der
 * bewusste Knopf des Koordinators, der aus der Auskunft einen Zustand macht.
 *
 * Wilfried (01.09.2026), zwei Entscheidungen dazu:
 *  – Der Zustand wird gesetzt, nicht automatisch aus der Rechnung abgeleitet
 *    — ein Knopf, keine Automatik.
 *  – Noch nicht gehaltene Prüfungstermine werden automatisch abgesagt, wie
 *    bei einer Erkrankung (status=cancelled, cancelled_reason='abbruch'),
 *    aber ohne Nachtermin. Bereits gehaltene bleiben unangetastet — erkannt
 *    daran, ob für den Termin schon eine Note in zusatzpruef_exam_scores
 *    steht (ExamAssignment bildet ausschließlich mündliche Termine ab, siehe
 *    ExterneAbiturDataService).
 *
 * Ergänzt (01.09.2026, nachmittags), Klarstellung Wilfrieds: "Abgebrochen ist
 * abgebrochen" — es geht nicht mehr weiter, keine der Prüfungen wird noch
 * abgelegt. Das gilt auch für Zusatzprüfungstermine (ZusatzpruefAssignment),
 * die zunächst bewusst ausgeklammert waren. `Zusatzpruefung` kennt keinen
 * eigenen Abgesagt-Zustand — als Werkzeug dient dasselbe Feld, das schon für
 * "nicht angetreten" steht (`absent`), und das an allen Auswertungen
 * (Niederschrift, Ausdrucke, Konfliktprüfung) bereits vorbeiführt. Eine noch
 * nicht abgehaltene Zusatzprüfung (`target_points` fehlt) wird deaktiviert:
 * ihr Termin fällt (das Zeitfenster wird frei für andere), `absent` wird
 * gesetzt. Bereits abgehaltene (`target_points` gesetzt) bleiben unangetastet
 * — eine Note ist eine Tatsache, kein Termin. `ZusatzpruefungController::
 * index()` blendet als `absent` markierte Zusatzprüfungen aus Seitenleiste
 * und Tagesraster aus.
 */
class ExterneAbbruchService
{
    public const GRUND_D2 = 'd2';
    public const GRUND_E6 = 'e6';

    /** @var array<string, string> Für Anzeige und ActivityLog. */
    public const GRUENDE_TEXT = [
        self::GRUND_D2 => '§ 61 Abs. 4 S. 2 — Abbruch nach den vorgezogenen mündlichen Prüfungen',
        self::GRUND_E6 => '§ 62 Abs. 1 S. 4 — Abbruch nach dem ersten Prüfungsteil',
    ];

    /**
     * Den Abbruch feststellen: Zustand setzen, offene Prüfungstermine
     * absagen. Auf einen bereits abgebrochenen Bewerber wirkungslos — kein
     * doppelter Log-Eintrag, kein zweites Datum.
     */
    public function feststellen(ExternalApplicant $bewerber, string $grund): void
    {
        if (! array_key_exists($grund, self::GRUENDE_TEXT)) {
            throw new InvalidArgumentException("Unbekannter Abbruchgrund '{$grund}'.");
        }

        if ($bewerber->istAbgebrochen()) {
            return;
        }

        DB::transaction(function () use ($bewerber, $grund) {
            $bewerber->update([
                'abgebrochen_am' => now(),
                'abbruch_grund' => $grund,
            ]);

            $abgesagt = $this->offeneTermine($bewerber);

            foreach ($abgesagt as $termin) {
                $termin->update([
                    'status' => 'cancelled',
                    'cancelled_reason' => 'abbruch',
                ]);
            }

            $deaktiviert = $this->offeneZusatzpruefungen($bewerber);

            foreach ($deaktiviert as $zusatzpruefung) {
                $zusatzpruefung->assignment()->delete();
                $zusatzpruefung->update(['absent' => true]);
            }

            $meldung = $bewerber->full_name . ': Abbruch festgestellt ('
                . self::GRUENDE_TEXT[$grund] . '), '
                . $abgesagt->count() . ' Prüfungstermin(e) abgesagt';

            $meldung .= $deaktiviert->isEmpty()
                ? '.'
                : ', ' . $deaktiviert->count() . ' Zusatzprüfung(en) deaktiviert.';

            ActivityLogger::log(
                'external_applicant_abbruch',
                'ExternalApplicant',
                $bewerber->id,
                $meldung
            );
        });
    }

    /**
     * Die Feststellung zurücknehmen (Tippfehler beim Grund, Fehlklick).
     *
     * Nimmt nur den Zustand zurück — bereits abgesagte Termine bleiben
     * abgesagt. Ein Termin, der einmal als abgesagt galt, braucht eine
     * bewusste Neuplanung, keine stille Wiederherstellung.
     */
    public function rueckgaengig(ExternalApplicant $bewerber): void
    {
        if (! $bewerber->istAbgebrochen()) {
            return;
        }

        $bewerber->update([
            'abgebrochen_am' => null,
            'abbruch_grund' => null,
        ]);

        ActivityLogger::log(
            'external_applicant_abbruch_rueckgaengig',
            'ExternalApplicant',
            $bewerber->id,
            $bewerber->full_name . ': Abbruch-Feststellung zurückgenommen. '
                . 'Bereits abgesagte Prüfungstermine und deaktivierte '
                . 'Zusatzprüfungen bleiben unangetastet.'
        );
    }

    /**
     * Die `student_exam_id`s aller Prüfungsfächer des Bewerbers — Grundlage
     * für `offeneTermine()` und `offeneZusatzpruefungen()`, damit beide
     * dieselbe Abbildung Bewerber → Prüfungen nutzen statt zwei eigene.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function studentExamIds(ExternalApplicant $bewerber): \Illuminate\Support\Collection
    {
        return $bewerber->examSubjects()
            ->with('studentExam')
            ->get()
            ->pluck('studentExam.id')
            ->filter()
            ->values();
    }

    /**
     * Aktive Prüfungstermine des Bewerbers, für die noch keine Note steht.
     *
     * @return Collection<int, ExamAssignment>
     */
    private function offeneTermine(ExternalApplicant $bewerber): Collection
    {
        $studentExamIds = $this->studentExamIds($bewerber);

        if ($studentExamIds->isEmpty()) {
            return new Collection();
        }

        $benotetIds = ZusatzpruefExamScore::whereIn('student_exam_id', $studentExamIds)
            ->whereNotNull('score')
            ->pluck('student_exam_id');

        return ExamAssignment::whereIn('student_exam_id', $studentExamIds)
            ->where('status', '!=', 'cancelled')
            ->whereNotIn('student_exam_id', $benotetIds)
            ->get();
    }

    /**
     * Noch nicht abgehaltene Zusatzprüfungen des Bewerbers — erkannt daran,
     * dass `target_points` fehlt (siehe Klassendocblock). Bereits als
     * `absent` markierte werden nicht erneut geliefert, damit ein zweiter
     * Aufruf von `feststellen()` wirkungslos bleibt.
     *
     * @return Collection<int, Zusatzpruefung>
     */
    private function offeneZusatzpruefungen(ExternalApplicant $bewerber): Collection
    {
        $studentExamIds = $this->studentExamIds($bewerber);

        if ($studentExamIds->isEmpty()) {
            return new Collection();
        }

        return Zusatzpruefung::whereIn('student_exam_id', $studentExamIds)
            ->where('absent', false)
            ->whereNull('target_points')
            ->get();
    }
}
