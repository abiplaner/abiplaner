<?php

namespace Tests\Unit\Services;

/**
 * Helper zum Aufbau von $exams-Arrays für MindestpunkteService-Tests.
 *
 * Standard-Setup (5 Fächer, angelehnt an den real verifizierten Fall
 * "Sahin, Ali"):
 *   - Deutsch        (SLK, fix)
 *   - Mathematik     (MNT, ZP)
 *   - Französisch    (SLK, Fremdsprache, ZP)
 *   - Politik+Ges.   (GPR, fix)
 *   - Sport          (Leistungsfach, kein Aufgabenfeld, fix/Kolloquium)
 *
 * Jede der fünf Fach-Methoden gibt ein Exam-Array (gemäß Docblock von
 * MindestpunkteService::compute()) zurück; contribution() steuert direkt
 * den gewünschten ×4-Beitrag, ohne dass man written/oral Kombinationen
 * von Hand durchrechnen muss.
 */
class MindestpunkteFaelle
{
    /** Fixes Fach (kein ZP) mit gegebenem ×4-Beitrag (Note = Beitrag/4, muss glatt teilbar sein). */
    public static function fix(int $id, string $subject, int $contribX4, array $overrides = []): array
    {
        if ($contribX4 % 4 !== 0) {
            throw new \InvalidArgumentException("contribX4 muss durch 4 teilbar sein für ein fixes Fach (Note = Beitrag/4), bekommen: {$contribX4}");
        }
        return array_merge([
            'id'                  => $id,
            'subject'             => $subject,
            'isZusatzpruefung'    => false,
            'isDeutsch'           => false,
            'isMathematik'        => false,
            'isLeistungsfach'     => false,
            'isForeignLanguage'   => false,
            'isScience'           => false,
            'isSport'             => false,
            'level'               => 'grundlegend',
            'aufgabenfeld'        => null,
            'writtenPoints'       => null,
            'targetPoints'        => null,
            'score'               => intdiv($contribX4, 4),
            'sportScore'          => null,
            'sportPracticalGrade' => null,
            'sort_order'          => 0,
        ], $overrides);
    }

    /**
     * ZP-Fach mit explizitem written/target — Beitrag ergibt sich aus der
     * echten Formel round((2*written+target)*4/3). Damit lassen sich gezielt
     * die Rundungs-Grenzfälle (z.B. Beitrag 19 statt 20) konstruieren.
     */
    public static function zp(int $id, string $subject, int $written, ?int $target, array $overrides = []): array
    {
        return array_merge([
            'id'                  => $id,
            'subject'             => $subject,
            'isZusatzpruefung'    => true,
            'isDeutsch'           => false,
            'isMathematik'        => false,
            'isLeistungsfach'     => false,
            'isForeignLanguage'   => false,
            'isScience'           => false,
            'isSport'             => false,
            'level'               => 'grundlegend',
            'aufgabenfeld'        => null,
            'writtenPoints'       => $written,
            'targetPoints'        => $target,
            'score'               => null,
            'sportScore'          => null,
            'sportPracticalGrade' => null,
            'sort_order'          => 0,
        ], $overrides);
    }

    /** Hilfsfunktion: ×4-Beitrag, der einer ZP mit Note $written/$target entspricht (Anlage 12 §52). */
    public static function contribution(int $written, int $target): int
    {
        return (int) round((2 * $written + $target) * 4 / 3);
    }

    /** Deutsch, fix, SLK. */
    public static function deutsch(int $contribX4): array
    {
        return self::fix(1, 'Deutsch', $contribX4, [
            'isDeutsch'    => true,
            'aufgabenfeld' => 'sprachlich_literarisch',
        ]);
    }

    /** Mathematik als ZP (MNT). */
    public static function mathematikZp(int $written, ?int $target): array
    {
        return self::zp(2, 'Mathematik', $written, $target, [
            'isMathematik' => true,
            'aufgabenfeld' => 'mint',
        ]);
    }

    /** Mathematik fix (kein ZP), MNT. */
    public static function mathematikFix(int $contribX4): array
    {
        return self::fix(2, 'Mathematik', $contribX4, [
            'isMathematik' => true,
            'aufgabenfeld' => 'mint',
        ]);
    }

    /** Französisch als ZP, Fremdsprache, SLK. */
    public static function franzoesischZp(int $written, ?int $target): array
    {
        return self::zp(3, 'Französisch', $written, $target, [
            'isForeignLanguage' => true,
            'aufgabenfeld'      => 'sprachlich_literarisch',
        ]);
    }

    /** Französisch fix, Fremdsprache, SLK. */
    public static function franzoesischFix(int $contribX4): array
    {
        return self::fix(3, 'Französisch', $contribX4, [
            'isForeignLanguage' => true,
            'aufgabenfeld'      => 'sprachlich_literarisch',
        ]);
    }

    /** Politik und Gesellschaft, fix, GPR. */
    public static function politik(int $contribX4): array
    {
        return self::fix(4, 'Politik und Gesellschaft', $contribX4, [
            'aufgabenfeld' => 'gesellschaftswissenschaftlich',
        ]);
    }

    /**
     * Sport als Leistungsfach (kein Aufgabenfeld), fix.
     * Beitrag = (sportScore + sportPracticalGrade) × 2 (muss gerade sein).
     * Teilt sportScore/sportPracticalGrade gleichmäßig auf (z.B. Beitrag 20
     * -> sportScore=5, practicalGrade=5).
     */
    public static function sportLf(int $contribX4): array
    {
        if ($contribX4 % 2 !== 0) {
            throw new \InvalidArgumentException("Sport-Beitrag muss gerade sein ((s+pg)*2), bekommen: {$contribX4}");
        }
        $sum  = intdiv($contribX4, 2);
        $half = intdiv($sum, 2);
        return [
            'id'                  => 5,
            'subject'             => 'Sport',
            'isZusatzpruefung'    => false,
            'isDeutsch'           => false,
            'isMathematik'        => false,
            'isLeistungsfach'     => true,
            'isForeignLanguage'   => false,
            'isScience'           => false,
            'isSport'             => true,
            'level'               => 'grundlegend',
            'aufgabenfeld'        => null,
            'writtenPoints'       => null,
            'targetPoints'        => null,
            'score'               => null,
            'sportScore'          => $half,
            'sportPracticalGrade' => $sum - $half,
            'sort_order'          => 0,
        ];
    }

    /**
     * Sport als Leistungsfach mit explizitem Theorie-/Praxis-Wert (statt nur
     * Gesamtbeitrag) — für asymmetrische Fälle und Grenzwert-Tests am
     * Sport-Beitrag selbst: Beitrag = (sportScore + sportPracticalGrade) × 2.
     */
    public static function sportLfDetailed(int $sportScore, int $practicalGrade): array
    {
        return [
            'id'                  => 5,
            'subject'             => 'Sport',
            'isZusatzpruefung'    => false,
            'isDeutsch'           => false,
            'isMathematik'        => false,
            'isLeistungsfach'     => true,
            'isForeignLanguage'   => false,
            'isScience'           => false,
            'isSport'             => true,
            'level'               => 'grundlegend',
            'aufgabenfeld'        => null,
            'writtenPoints'       => null,
            'targetPoints'        => null,
            'score'               => null,
            'sportScore'          => $sportScore,
            'sportPracticalGrade' => $practicalGrade,
            'sort_order'          => 0,
        ];
    }

    /**
     * Sport schriftlich + abgelegte mündliche Zusatzprüfung.
     *
     * Amtliche Formel (§ 52 Abs. 2 Nr. 2 GSO, schulinterner Leitfaden
     * Nr. 2.3.2.2): Beitrag = round((praxis + theorieSchriftlich + muendlich) × 4 / 3).
     * Siehe MindestpunkteService::calcSportZpContribution().
     *
     * @param int|null $oral  mündliche ZP-Note (targetPoints); null = noch offene ZP
     */
    public static function sportZp(int $theorieSchriftlich, int $praxis, ?int $oral): array
    {
        return [
            'id'                  => 5,
            'subject'             => 'Sport',
            'isZusatzpruefung'    => true,
            'isDeutsch'           => false,
            'isMathematik'        => false,
            'isLeistungsfach'     => true,
            'isForeignLanguage'   => false,
            'isScience'           => false,
            'isSport'             => true,
            'level'               => 'grundlegend',
            'aufgabenfeld'        => null,
            'writtenPoints'       => $theorieSchriftlich,
            'targetPoints'        => $oral,
            'score'               => null,
            'sportScore'          => null,
            'sportPracticalGrade' => $praxis,
            'sort_order'          => 0,
        ];
    }

    /**
     * Standard-5-Fächer-Set ("Sahin, Ali"-Schema), alle Fächer fix
     * (kein offenes ZP). $contribs in Reihenfolge [D, M, F, Politik, Sport].
     */
    public static function standardFix(array $contribs): array
    {
        [$d, $m, $f, $pug, $sport] = $contribs;
        return [
            self::deutsch($d),
            self::mathematikFix($m),
            self::franzoesischFix($f),
            self::politik($pug),
            self::sportLf($sport),
        ];
    }
}
