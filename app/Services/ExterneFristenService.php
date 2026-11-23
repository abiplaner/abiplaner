<?php

namespace App\Services;

use App\Models\ExamPeriod;
use Carbon\CarbonImmutable;

/**
 * Zwei Fristen externer Bewerber — § 61 Abs. 1 S. 7 (A8) und Abs. 2 S. 5 (B5)
 * BayGSO.
 *
 * Reine Anzeige, kein Zustand und keine Sperre (Entscheidung 57, 01.09.2026 —
 * "wie die anderen Fristen"): Beide Daten werden dem Koordinator gezeigt,
 * nichts wird automatisch gelöscht oder blockiert. Anders als bei den
 * Aufbewahrungsfristen der Support-Fallakten (Entscheidung 53) gibt es hier
 * nichts aufzuräumen — beide Fristen sind Handlungsfristen für den Bewerber
 * bzw. den Prüfungsausschuss, keine Löschfristen für die Anwendung.
 *
 *   A8, § 61 Abs. 1 S. 7: "Der Antrag auf Zulassung zur mündlichen
 *   Zusatzprüfung in den Fächern des ersten Prüfungsteils ist spätestens am
 *   Tag nach Bekanntgabe der Ergebnisse der schriftlichen Prüfung der Fächer
 *   des ersten Prüfungsteils dem Prüfungsausschuss in Textform zu stellen."
 *
 *   B5, § 61 Abs. 2 S. 5: "Die neben dem Fach Mathematik zu den
 *   Prüfungsfächern eins bis drei des ersten Prüfungsteils gehörenden Fächer
 *   können bis 31. Januar mit einem der gewählten Prüfungsfächer vier bis
 *   acht getauscht werden."
 *
 * AUSLEGUNG A8 — der Anker "Bekanntgabe der Ergebnisse der schriftlichen
 * Prüfung" steht an keiner Stelle im Datenmodell. Wilfried (01.09.2026): Die
 * Ergebnisse werden am letzten möglichen Tag der Kolloquiumsprüfungen
 * bekanntgegeben, also am Freitag. Das ist `ExamPeriod::$week_2_end` — das
 * Ende der zweiten und letzten Prüfungswoche, zentral gepflegt und für jeden
 * Jahrgang bereits vorhanden. A8 wird deshalb abgeleitet, nicht gepflegt: Es
 * gibt keinen zweiten Ort, an dem ein eigenes Datum falsch stehen könnte
 * (dasselbe Prinzip wie beim Fenster der vorgezogenen Prüfungen, § 61 Abs. 4).
 *
 * B5 braucht keine Ableitung — der Wortlaut nennt einen festen Kalendertag.
 */
class ExterneFristenService
{
    /**
     * A8: der Tag nach der Bekanntgabe der Ergebnisse der schriftlichen
     * Prüfung — abgeleitet aus dem Ende der zweiten Prüfungswoche.
     */
    public function a8Frist(ExamPeriod $zeitraum): CarbonImmutable
    {
        // getRawOriginal() statt des gecasteten Attributs: Der 'date'-Cast
        // braucht für sein Format eine aufgelöste DB-Verbindung
        // (getConnection()->getQueryGrammar()) — unnötig für eine reine
        // Carbon-Rechnung und in einem schlanken Unit-Test ohne
        // Verbindungsauflösung sonst ein Fehler.
        return CarbonImmutable::parse($zeitraum->getRawOriginal('week_2_end'))
            ->startOfDay()
            ->addDay();
    }

    /**
     * B5: 31. Januar des Prüfungsjahrs.
     */
    public function b5Frist(ExamPeriod $zeitraum): CarbonImmutable
    {
        return CarbonImmutable::create($zeitraum->year, 1, 31)->startOfDay();
    }

    /**
     * Ist die Frist bereits verstrichen (heute nach dem Fristtag)?
     */
    public function istVerstrichen(CarbonImmutable $frist): bool
    {
        return CarbonImmutable::now()->startOfDay()->greaterThan($frist);
    }
}
