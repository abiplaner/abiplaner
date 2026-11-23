<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * § 62 GSO — Gesamtqualifikation und Bestehen bei anderen Bewerbern.
 *
 * Zweiter Rechenweg neben MindestpunkteService, kein Sonderfall darin: Für
 * externe Bewerber gibt es keine Halbjahresleistungen, die Gesamtqualifikation
 * entsteht allein aus den acht Prüfungsfächern. MindestpunkteService bleibt für
 * reguläre Schüler zuständig und wird hier nicht angefasst.
 *
 * ── Die Rechnung ────────────────────────────────────────────────────────────
 * Anlage 14 (Regelfall) und Anlage 15 (Ersatzschüler nach § 64 Abs. 2) haben
 * dieselben Faktoren:
 *   Fächer 1–4 (erster Prüfungsteil, schriftlich): Faktor 11, je höchstens 165
 *   Fächer 5–8 (zweiter Prüfungsteil):             Faktor  4, je höchstens  60
 *   Summe der Faktoren 60 × 15 Punkte = 900.
 *
 * Sie unterscheiden sich nur in der Herkunft der Punkte für die Fächer 7 und 8:
 * bei Anlage 15 werden diese beiden nicht mündlich geprüft, sondern mit dem
 * Ergebnis aus dem letzten Ausbildungshalbjahr der Ersatzschule eingebracht
 * (§ 64 Abs. 2 S. 1). Es bleiben acht Prüfungsfächer, von denen sechs geprüft
 * und zwei eingebracht werden — § 62 Abs. 2 gilt deshalb über alle vier Fächer
 * des zweiten Teils.
 *
 * ── Bestehen ───────────────────────────────────────────────────────────────
 * Erster Teil (Abs. 1 S. 3): kein Fach mit 0 Punkten, mindestens 220 Punkte,
 * und in mindestens zwei der vier Fächer wenigstens 5 Punkte der einfachen
 * Wertung, davon eines auf erhöhtem Anforderungsniveau. Aufrunden ist nicht
 * zulässig. Sind die Bedingungen nicht erfüllt, wird die Prüfung nach dem
 * ersten Teil abgebrochen (Abs. 1 S. 4).
 *
 * Zweiter Teil (Abs. 2): kein Fach mit 0 Punkten, mindestens 80 Punkte, und in
 * mindestens zwei der vier Fächer wenigstens 5 Punkte der einfachen Wertung —
 * ohne die Anforderung an das Niveau.
 *
 * Beide Schwellen sind genau ein Drittel (660 bzw. 240), die Gesamtqualifikation
 * liegt damit auf derselben 300–900-Skala wie beim regulären Abitur.
 */
class ExterneGesamtqualifikationService
{
    public const SCHEMA_ANLAGE_14 = 'anlage_14';
    public const SCHEMA_ANLAGE_15 = 'anlage_15';

    public const FAKTOR_ERSTER_TEIL = 11;
    public const FAKTOR_ZWEITER_TEIL = 4;

    public const MINDESTPUNKTE_ERSTER_TEIL = 220;
    public const MINDESTPUNKTE_ZWEITER_TEIL = 80;

    /** Positionen, die bei Anlage 15 eingebracht statt geprüft werden. */
    public const EINGEBRACHTE_POSITIONEN = [7, 8];

    public const QUELLE_PRUEFUNG = 'pruefung';
    public const QUELLE_ZUSATZPRUEFUNG = 'zusatzpruefung';
    public const QUELLE_EINBRINGUNG = 'einbringung';

    public const ABBRUCH_NACH_ERSTEM_TEIL = 'nach_erstem_teil';
    public const ABBRUCH_VORGEZOGENE_MUENDLICHE = 'vorgezogene_muendliche';

    /**
     * § 61 Abs. 4 S. 1: „zwei der vier mündlichen Prüfungen". Die Obergrenze
     * steht hier, weil die Regel des Satzes 2 hier steht; die Planung setzt
     * sie über VorgezogeneKolloquienService::HOECHSTZAHL durch, das auf diese
     * Konstante zeigt.
     */
    public const HOECHSTZAHL_VORGEZOGENE = 2;

    /**
     * Gesamtpunktzahl eines Prüfungsfachs in einfacher Wertung × Faktor.
     *
     * Ohne Zusatzprüfung ist das schlicht Punkte × Faktor. Mit mündlicher
     * Zusatzprüfung gilt Anlage 12 Satz 4 (§ 62 Abs. 1 S. 2):
     *
     *     P = ((2s + m) / 3) × Faktor,   Rundung erst am Endergebnis
     *
     * Dieselbe Form wie MindestpunkteService::calcZpContribution(), dort mit
     * Faktor 4 für reguläre Schüler; für den ersten Prüfungsteil externer
     * Bewerber steht hier Faktor 11.
     */
    public function fachpunktzahl(int $einfacheWertung, int $faktor): int
    {
        $this->pruefePunktebereich($einfacheWertung);

        return $einfacheWertung * $faktor;
    }

    /**
     * Anlage 12 Satz 4: P = ((2s + m) / 3) × Faktor, am Ende gerundet.
     */
    public function zusatzpruefungspunktzahl(int $schriftlich, int $muendlich, int $faktor): int
    {
        $this->pruefePunktebereich($schriftlich);
        $this->pruefePunktebereich($muendlich);

        return (int) round((2 * $schriftlich + $muendlich) * $faktor / 3);
    }

    /**
     * Einfache Wertung eines Fachs mit mündlicher Zusatzprüfung.
     *
     * Anlage 12 gibt für die Zusatzprüfung nur die Gesamtpunktzahl in
     * gewichteter Form an. § 62 Abs. 1 S. 3 und Abs. 2 verlangen daneben
     * Aussagen über die *einfache* Wertung ("kein Fach mit 0 Punkten",
     * "mindestens 5 Punkte der einfachen Wertung").
     *
     * Gerechnet als round((2s + m) / 3) — dieselbe Formel, die
     * MindestpunkteService::calcCombined() für reguläre Schüler als kombinierte
     * Note führt. Der Wert wird ausschließlich für die Schwellenprüfungen
     * benutzt, nie für Summen; die Gesamtpunktzahl kommt unabhängig davon aus
     * zusatzpruefungspunktzahl() mit Rundung am Ende.
     *
     * Abgenommen von Wilfried am 26.08.2026: Die einfache Wertung ist die
     * Punktzahl von 0 bis 15, die der Prüfling für die Prüfung bekommt.
     *
     * Die Sonderregel "Rohwert unter 1 ergibt 0" stammt aus calcCombined() und
     * bleibt bewusst erhalten (Entscheidung Wilfried, 26.08.2026): externe
     * Bewerber sollen hier nicht anders behandelt werden als reguläre Schüler.
     *
     * Folge: § 62 Abs. 1 S. 3 Nr. 1 und Anlage 12 S. 6 fallen exakt zusammen —
     * die Grenze liegt in beiden Fällen bei 2s + m = 3, und die Anlage-12-Regel
     * greift nie allein. Ohne die Sonderregel täte sie es bei 2s + m = 2
     * (einfache Wertung 1, elffache Wertung 7). Beide Gründe werden getrennt
     * ausgewiesen, damit in der Niederschrift die zutreffende Norm steht.
     */
    public function einfacheWertungMitZusatzpruefung(int $schriftlich, int $muendlich): int
    {
        $this->pruefePunktebereich($schriftlich);
        $this->pruefePunktebereich($muendlich);

        $roh = (2 * $schriftlich + $muendlich) / 3;

        return $roh < 1 ? 0 : (int) round($roh);
    }

    /**
     * § 64 Abs. 2 Satz 4 — eingebrachtes Ergebnis eines ersetzten Fachs.
     *
     * IM ALLTAG NICHT IN GEBRAUCH (Entscheidung 35 vom 27.08.2026): Erfasst
     * wird allein die fertige Punktzahl in einfacher Wertung, die die
     * Ersatzschule meldet — wie sie zustande kam, führt diese Anwendung nicht.
     * Für ein eingebrachtes Fach rechnet deshalb fachpunktzahl($punkte, 4).
     *
     * **Erreichbar sind die drei Methoden trotzdem:** bewerte() ruft sie, wenn
     * ein Fach einen Schlüssel `einbringung` mitbringt. Diesen Weg beschickt
     * die Anwendung heute nirgends — die öffentliche berechne() nimmt ihn aber
     * entgegen, und die Tests gehen ihn. Bis zum 31.08.2026 stand hier, sie
     * würden „von nichts aufgerufen"; das war falsch und hätte beim Aufräumen
     * beinahe eine tragende Verzweigung gekostet.
     *
     * Sie bleiben samt Tests stehen: Sie bilden den Wortlaut korrekt ab, und
     * Entscheidung 35 soll zurücknehmbar sein.
     *
     * "Das Ergebnis ist der Durchschnitt aus der doppelt gewichteten Punktzahl
     * der Schulaufgabe und dem Durchschnitt der kleinen Leistungsnachweise."
     *
     *     E = (2 × Schulaufgabe + Ø kleine Leistungsnachweise) / 3
     *
     * Satz 2 verlangt eine Schulaufgabe und mindestens zwei kleine
     * Leistungsnachweise, darunter mindestens einen mündlichen. Die Zahl prüft
     * dieser Service; ob einer davon mündlich war, ist eine Frage der Erfassung
     * und steht hier bewusst nicht.
     *
     * Gerundet wird erst am Endergebnis, also nach der Multiplikation mit dem
     * Faktor 4 (Entscheidung Wilfried, 26.08.2026) — genau wie bei Anlage 12
     * Satz 4. Die einfache Wertung des Fachs, die § 62 Abs. 2 für "kein Fach
     * mit 0 Punkten" und "mindestens 5 Punkte" braucht, wird daneben separat
     * aus demselben Rohwert gerundet; sie ist kein Zwischenschritt der
     * Gesamtpunktzahl.
     *
     * @param list<int> $kleineLeistungsnachweise Punkte 0–15
     */
    public function eingebrachtesErgebnisRoh(int $schulaufgabe, array $kleineLeistungsnachweise): float
    {
        $this->pruefePunktebereich($schulaufgabe);

        if (count($kleineLeistungsnachweise) < 2) {
            throw new InvalidArgumentException(
                '§ 64 Abs. 2 S. 2 verlangt mindestens zwei kleine Leistungsnachweise, '
                . 'übergeben wurden ' . count($kleineLeistungsnachweise) . '.'
            );
        }

        foreach ($kleineLeistungsnachweise as $punkte) {
            $this->pruefePunktebereich($punkte);
        }

        $durchschnittKleine = array_sum($kleineLeistungsnachweise) / count($kleineLeistungsnachweise);

        return (2 * $schulaufgabe + $durchschnittKleine) / 3;
    }

    /**
     * Einfache Wertung (0–15) eines eingebrachten Fachs — für die
     * Schwellenprüfungen des § 62 Abs. 2, nicht für die Gesamtpunktzahl.
     *
     * @param list<int> $kleineLeistungsnachweise Punkte 0–15
     */
    public function eingebrachtesErgebnis(int $schulaufgabe, array $kleineLeistungsnachweise): int
    {
        return (int) round($this->eingebrachtesErgebnisRoh($schulaufgabe, $kleineLeistungsnachweise));
    }

    /**
     * § 64 Abs. 2 S. 5 mit Anlage 15: Gesamtpunktzahl eines eingebrachten Fachs.
     * Faktor 4, höchstens 60 Punkte — Rundung am Endergebnis.
     *
     * @param list<int> $kleineLeistungsnachweise Punkte 0–15
     */
    public function eingebrachtePunktzahl(
        int $schulaufgabe,
        array $kleineLeistungsnachweise,
        int $faktor = self::FAKTOR_ZWEITER_TEIL
    ): int {
        return (int) round($this->eingebrachtesErgebnisRoh($schulaufgabe, $kleineLeistungsnachweise) * $faktor);
    }

    private function pruefePunktebereich(int $punkte): void
    {
        if ($punkte < 0 || $punkte > 15) {
            throw new InvalidArgumentException(
                "Punkte müssen zwischen 0 und 15 liegen, übergeben wurde {$punkte}."
            );
        }
    }

    /**
     * Faktor einer Position: Fächer 1–4 elffach, Fächer 5–8 vierfach.
     */
    public function faktorFuerPosition(int $position): int
    {
        if ($position < 1 || $position > 8) {
            throw new InvalidArgumentException(
                "Prüfungsfächer tragen die Positionen 1 bis 8, übergeben wurde {$position}."
            );
        }

        return $position <= 4 ? self::FAKTOR_ERSTER_TEIL : self::FAKTOR_ZWEITER_TEIL;
    }

    /**
     * Ein einzelnes Fach auf einfache Wertung, Faktor und Gesamtpunktzahl
     * bringen. Noch nicht bewertete Fächer kommen mit null zurück — die
     * Rechnung muss auch nach dem ersten Prüfungsteil laufen, wenn der zweite
     * noch aussteht (§ 62 Abs. 1 S. 4 entscheidet genau dann über den Abbruch).
     *
     * @param array<string, mixed> $fach
     * @return array<string, mixed>
     */
    private function normalisiere(array $fach, string $schema): array
    {
        $position = (int) ($fach['position'] ?? 0);
        $faktor = $this->faktorFuerPosition($position);
        $eingebrachtErlaubt = $schema === self::SCHEMA_ANLAGE_15
            && in_array($position, self::EINGEBRACHTE_POSITIONEN, true);

        $zusatzpruefung = $fach['zusatzpruefung'] ?? null;
        $einbringung = $fach['einbringung'] ?? null;

        if ($einbringung !== null && ! $eingebrachtErlaubt) {
            throw new InvalidArgumentException(
                "Ein eingebrachtes Ergebnis ist nur bei Anlage 15 und nur auf den Positionen "
                . implode(' und ', self::EINGEBRACHTE_POSITIONEN) . " zulässig (§ 64 Abs. 2 S. 1); "
                . "übergeben wurde es auf Position {$position}."
            );
        }

        if ($zusatzpruefung !== null && $position > 4) {
            throw new InvalidArgumentException(
                'Eine mündliche Zusatzprüfung gibt es nur zu den Fächern des ersten '
                . "Prüfungsteils (§ 61 Abs. 1 S. 3); übergeben wurde sie auf Position {$position}."
            );
        }

        return $this->bewerte($fach, $position, $faktor, $zusatzpruefung, $einbringung);
    }

    /**
     * @param array<string, mixed> $fach
     * @param array<string, mixed>|null $zusatzpruefung
     * @param array<string, mixed>|null $einbringung
     * @return array<string, mixed>
     */
    private function bewerte(
        array $fach,
        int $position,
        int $faktor,
        ?array $zusatzpruefung,
        ?array $einbringung
    ): array {
        $grund = [
            'position' => $position,
            'fach' => isset($fach['fach']) ? (string) $fach['fach'] : null,
            'ea' => (bool) ($fach['ea'] ?? false),
            'faktor' => $faktor,
        ];

        if ($zusatzpruefung !== null) {
            $schriftlich = (int) $zusatzpruefung['schriftlich'];
            $muendlich = (int) $zusatzpruefung['muendlich'];

            return $grund + [
                'quelle' => self::QUELLE_ZUSATZPRUEFUNG,
                'einfache_wertung' => $this->einfacheWertungMitZusatzpruefung($schriftlich, $muendlich),
                'gesamtpunktzahl' => $this->zusatzpruefungspunktzahl($schriftlich, $muendlich, $faktor),
            ];
        }

        if ($einbringung !== null) {
            /** @var list<int> $kleine */
            $kleine = array_map('intval', (array) ($einbringung['kleine_leistungsnachweise'] ?? []));
            $schulaufgabe = (int) $einbringung['schulaufgabe'];

            return $grund + [
                'quelle' => self::QUELLE_EINBRINGUNG,
                'einfache_wertung' => $this->eingebrachtesErgebnis($schulaufgabe, $kleine),
                'gesamtpunktzahl' => $this->eingebrachtePunktzahl($schulaufgabe, $kleine, $faktor),
            ];
        }

        $punkte = $fach['punkte'] ?? null;

        if ($punkte === null) {
            return $grund + [
                'quelle' => self::QUELLE_PRUEFUNG,
                'einfache_wertung' => null,
                'gesamtpunktzahl' => null,
            ];
        }

        return $grund + [
            'quelle' => self::QUELLE_PRUEFUNG,
            'einfache_wertung' => (int) $punkte,
            'gesamtpunktzahl' => $this->fachpunktzahl((int) $punkte, $faktor),
        ];
    }

    /**
     * Bestehensprüfung für einen Prüfungsteil.
     *
     * Solange nicht alle vier Fächer bewertet sind, steht "bestanden" auf null
     * — außer ein Grund steht bereits fest (ein Fach mit 0 Punkten kann durch
     * kein späteres Ergebnis geheilt werden). Bedingungen, die erst aus der
     * vollständigen Wertung folgen, werden auch erst dann geprüft.
     *
     * @param list<array<string, mixed>> $faecher
     * @return array<string, mixed>
     */
    private function pruefeTeil(array $faecher, int $mindestpunkte, bool $niveauGefordert, string $norm): array
    {
        $gruende = [];
        $bewertet = array_values(array_filter($faecher, fn (array $f): bool => $f['gesamtpunktzahl'] !== null));
        $vollstaendig = count($bewertet) === count($faecher);
        $summe = array_sum(array_map(fn (array $f): int => (int) $f['gesamtpunktzahl'], $bewertet));

        foreach ($bewertet as $fach) {
            if ($fach['einfache_wertung'] === 0) {
                $gruende[] = $norm . ': Fach ' . $fach['position']
                    . ' ist mit 0 Punkten abgeschlossen.';
            }

            // § 62 Abs. 1 S. 2 mit Anlage 12 S. 6: Bei einem Ergebnis unter
            // 11 Punkten in elffacher Wertung ist die Abiturprüfung nicht
            // bestanden. Greift nur bei Fächern mit mündlicher Zusatzprüfung.
            if ($fach['quelle'] === self::QUELLE_ZUSATZPRUEFUNG
                && (int) $fach['gesamtpunktzahl'] < self::FAKTOR_ERSTER_TEIL) {
                $gruende[] = 'Anlage 12 S. 6: Fach ' . $fach['position']
                    . ' erreicht mit Zusatzprüfung nur ' . $fach['gesamtpunktzahl']
                    . ' Punkte in elffacher Wertung (mindestens ' . self::FAKTOR_ERSTER_TEIL . ').';
            }
        }

        if ($vollstaendig) {
            if ($summe < $mindestpunkte) {
                $gruende[] = $norm . ": Nur {$summe} von mindestens {$mindestpunkte} Punkten "
                    . '(ein Aufrunden ist nicht zulässig).';
            }

            $mindestensFuenf = array_values(array_filter(
                $bewertet,
                fn (array $f): bool => (int) $f['einfache_wertung'] >= 5
            ));

            if (count($mindestensFuenf) < 2) {
                $gruende[] = $norm . ': Nur ' . count($mindestensFuenf)
                    . ' Fach/Fächer mit mindestens 5 Punkten der einfachen Wertung (nötig sind zwei).';
            } elseif ($niveauGefordert && ! $this->enthaeltErhoehtesNiveau($mindestensFuenf)) {
                $gruende[] = $norm . ': Unter den Fächern mit mindestens 5 Punkten der einfachen '
                    . 'Wertung ist keines auf erhöhtem Anforderungsniveau.';
            }
        }

        return [
            'summe' => $summe,
            'vollstaendig' => $vollstaendig,
            'bestanden' => $gruende !== [] ? false : ($vollstaendig ? true : null),
            'gruende' => $gruende,
        ];
    }

    /**
     * @param list<array<string, mixed>> $faecher
     */
    private function enthaeltErhoehtesNiveau(array $faecher): bool
    {
        foreach ($faecher as $fach) {
            if ($fach['ea'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vollständige Rechnung über die acht Prüfungsfächer.
     *
     * Fächer ohne Ergebnis dürfen fehlen (Wert null) — die Rechnung läuft auch,
     * solange nur der erste Prüfungsteil bewertet ist.
     *
     * @param list<array<string, mixed>> $faecher je Fach:
     *   'position'       int    1–8, jede Position genau einmal
     *   'fach'           string optional, nur für die Rückgabe
     *   'ea'             bool   erhöhtes Anforderungsniveau
     *   'punkte'         ?int   einfache Wertung 0–15, null = noch offen
     *   'zusatzpruefung' ?array{schriftlich: int, muendlich: int}
     *   'einbringung'    ?array{schulaufgabe: int, kleine_leistungsnachweise: list<int>}
     * @return array<string, mixed>
     */
    public function berechne(array $faecher, string $schema = self::SCHEMA_ANLAGE_14): array
    {
        if (! in_array($schema, [self::SCHEMA_ANLAGE_14, self::SCHEMA_ANLAGE_15], true)) {
            throw new InvalidArgumentException("Unbekanntes Bewertungsschema '{$schema}'.");
        }

        if (count($faecher) !== 8) {
            throw new InvalidArgumentException(
                '§ 61 Abs. 1 S. 1: Gegenstand der Prüfung sind acht Prüfungsfächer, übergeben wurden '
                . count($faecher) . '.'
            );
        }

        $bewertet = array_map(fn (array $fach): array => $this->normalisiere($fach, $schema), $faecher);
        usort($bewertet, fn (array $a, array $b): int => (int) $a['position'] <=> (int) $b['position']);

        if (array_map('intval', array_column($bewertet, 'position')) !== range(1, 8)) {
            throw new InvalidArgumentException(
                'Die acht Prüfungsfächer müssen die Positionen 1 bis 8 genau einmal belegen.'
            );
        }

        if ($schema === self::SCHEMA_ANLAGE_15) {
            foreach (self::EINGEBRACHTE_POSITIONEN as $position) {
                $fach = $bewertet[$position - 1];

                if ($fach['gesamtpunktzahl'] !== null && $fach['quelle'] !== self::QUELLE_EINBRINGUNG) {
                    throw new InvalidArgumentException(
                        "§ 64 Abs. 2 S. 1: Bei Anlage 15 wird Fach {$position} eingebracht, nicht geprüft."
                    );
                }
            }
        }

        $teil1 = $this->pruefeTeil(
            array_slice($bewertet, 0, 4),
            self::MINDESTPUNKTE_ERSTER_TEIL,
            true,
            '§ 62 Abs. 1 S. 3'
        );

        $teil2 = $this->pruefeTeil(
            array_slice($bewertet, 4, 4),
            self::MINDESTPUNKTE_ZWEITER_TEIL,
            false,
            '§ 62 Abs. 2'
        );

        // § 62 Abs. 1 S. 4: Sind die Bedingungen des ersten Teils nicht
        // erfüllt, wird die Prüfung nach dem ersten Prüfungsteil abgebrochen.
        $abbruch = $teil1['bestanden'] === false ? self::ABBRUCH_NACH_ERSTEM_TEIL : null;

        $vollstaendig = $teil1['vollstaendig'] === true && $teil2['vollstaendig'] === true;

        $bestanden = null;

        if ($teil1['bestanden'] === false || $teil2['bestanden'] === false) {
            $bestanden = false;
        } elseif ($teil1['bestanden'] === true && $teil2['bestanden'] === true) {
            $bestanden = true;
        }

        return [
            'schema' => $schema,
            'faecher' => $bewertet,
            'erster_teil' => $teil1,
            'zweiter_teil' => $teil2,
            'abbruch' => $abbruch,
            'vollstaendig' => $vollstaendig,
            // § 62 Abs. 3: Summe der Gesamtpunktzahlen der acht Prüfungsfächer.
            'gesamtqualifikation' => $vollstaendig ? (int) $teil1['summe'] + (int) $teil2['summe'] : null,
            'bestanden' => $bestanden,
        ];
    }

    /**
     * § 61 Abs. 4 S. 2 — Abbruch nach den beiden vorgezogenen mündlichen
     * Prüfungen: "Werden in diesen beiden Prüfungen die Bedingungen von § 62
     * Abs. 2 bereits nicht erfüllt, wird die Prüfung abgebrochen."
     *
     * Von den drei Bedingungen des Absatzes 2 kann nach zwei von vier Prüfungen
     * nur die erste endgültig verletzt sein:
     *  – kein Fach mit 0 Punkten: eine 0 ist durch kein späteres Ergebnis mehr
     *    zu heilen;
     *  – mindestens 80 Punkte: aus den beiden ausstehenden Fächern sind noch
     *    2 × 60 = 120 Punkte erreichbar, die Schwelle bleibt immer offen;
     *  – zwei Fächer mit mindestens 5 Punkten der einfachen Wertung: die beiden
     *    ausstehenden Fächer können sie allein liefern.
     *
     * Der Abbruch hängt damit allein an einer 0. **Abgenommen von Wilfried am
     * 31.08.2026 (Entscheidung 50).**
     *
     * **Der Zwischenstand zählt mit** (Entscheidung Wilfrieds vom 31.08.2026):
     * Gemeldet wird, sobald *eine* der beiden Prüfungen mit 0 abschließt — auf
     * die zweite Note zu warten ändert am Ergebnis nichts, kostet aber Zeit.
     * Deshalb nimmt die Methode **höchstens** zwei Fächer entgegen, nicht
     * genau zwei: Solange erst eine vorgezogene Prüfung terminiert oder
     * benotet ist, ist die Liste kürzer. Der förmliche Abbruch bleibt eine
     * Entscheidung des Koordinators (Entscheidung 51).
     *
     * **Diese Methode ist die einzige Stelle, an der § 61 Abs. 4 S. 2 steht.**
     * `VorgezogeneKolloquienService` holt die Noten aus der Datenbank und ruft
     * sie; er rechnet nicht selbst. Am 31.08.2026 stand die Regel eine halbe
     * Stunde lang zweimal im Code — die zweite Fassung entstand, weil hier
     * niemand die Methode aufrief und sie deshalb bei der Suche nicht auffiel.
     *
     * @param list<array<string, mixed>> $vorgezogene je Fach 'position' und 'punkte'
     * @return array{abbruch: bool, gruende: list<string>}
     */
    public function abbruchNachVorgezogenenMuendlichen(array $vorgezogene): array
    {
        if (count($vorgezogene) > self::HOECHSTZAHL_VORGEZOGENE) {
            throw new InvalidArgumentException(
                '§ 61 Abs. 4 S. 1 lässt höchstens zwei vorgezogene mündliche Prüfungen zu, übergeben wurden '
                . count($vorgezogene) . '.'
            );
        }

        $gruende = [];

        foreach ($vorgezogene as $fach) {
            $position = (int) ($fach['position'] ?? 0);

            if ($position < 5 || $position > 8) {
                throw new InvalidArgumentException(
                    'Vorgezogen werden können nur Fächer des zweiten Prüfungsteils (Positionen 5 bis 8); '
                    . "übergeben wurde Position {$position}."
                );
            }

            $punkte = $fach['punkte'] ?? null;

            if ($punkte === null) {
                continue;
            }

            $this->pruefePunktebereich((int) $punkte);

            if ((int) $punkte === 0) {
                $gruende[] = "§ 62 Abs. 2: Fach {$position} ist mit 0 Punkten abgeschlossen — "
                    . 'die Bedingung ist damit endgültig nicht mehr zu erfüllen.';
            }
        }

        return [
            'abbruch' => $gruende !== [],
            'gruende' => $gruende,
        ];
    }
}
