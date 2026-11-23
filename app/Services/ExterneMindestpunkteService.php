<?php

namespace App\Services;

/**
 * § 62 GSO — die Umkehrung: welche Punktzahl braucht ein externer Bewerber
 * noch, um zu bestehen?
 *
 * Gegenstück zu MindestpunkteService, der dieselbe Frage für reguläre Schüler
 * nach § 61 beantwortet. Zweiter Rechenweg daneben, kein Sonderfall darin: für
 * externe Bewerber gibt es keine Halbjahresleistungen, die Gesamtqualifikation
 * entsteht allein aus den acht Prüfungsfächern.
 *
 * Dieser Service rechnet die Norm nicht selbst. Er stellt Kandidaten auf und
 * lässt jeden einzelnen von ExterneGesamtqualifikationService bewerten — dort
 * steht § 62 genau einmal, und dort bleibt er auch.
 *
 * ── Warum zwei getrennte Blöcke und nie eine gemeinsame Tabelle ─────────────
 *
 * § 61 hat eine einzige Bestehensprüfung über alle Fächer, deshalb dort eine
 * einzige Tabelle. § 62 hat zwei voneinander abgedichtete Teile: Abs. 1 S. 4
 * bricht die Prüfung nach dem ersten Teil ab. Ein starker zweiter Teil kann
 * einen schwachen ersten nicht heilen — er findet dann gar nicht statt. Eine
 * gemeinsame Tabelle über alle acht Fächer würde eine Verrechnung suggerieren,
 * die es nicht gibt.
 *
 * ── Erster Teil: Aufzählung, weil die Rundung sie erzwingt ──────────────────
 *
 * Freie Größe ist allein die mündliche Note einer Zusatzprüfung nach § 61
 * Abs. 1 S. 3; das schriftliche Ergebnis steht fest. Anlage 12 S. 4 rechnet
 *
 *     P = ((2s + m) / 3) × 11,   Rundung erst am Endergebnis.
 *
 * Weil erst am Ende gerundet wird, lässt sich die Summe nicht aus den
 * Einzelnoten herleiten — die Kombination muss aufgezählt werden.
 *
 * ── Zweiter Teil: Arithmetik, weil es nichts aufzuzählen gibt ───────────────
 *
 * Im zweiten Prüfungsteil gibt es keine Zusatzprüfungen (Entscheidung
 * Wilfried, 26.08.2026; der Rechner wirft, wenn eine auf Position > 4 kommt).
 * Ohne Zusatzprüfung ist die Gesamtpunktzahl exakt Punkte × 4, es wird nirgends
 * gerundet, und die Schwelle kollabiert:
 *
 *     4 × Σ ≥ 80   ⟺   Σ der einfachen Wertungen ≥ 20.
 *
 * Für Fächer ohne Zusatzprüfung gilt im ersten Teil dasselbe: 11 × Σ ≥ 220
 * ⟺ Σ ≥ 20. Beide Teile verlangen zwanzig Punkte einfacher Wertung auf vier
 * Fächern, also einen Schnitt von 5. Eine Tabelle mit 16⁴ = 65 536 Zeilen
 * dafür zu drucken wäre Unsinn; der zweite Teil bekommt deshalb einen
 * Restbedarf statt einer Aufzählung.
 */
class ExterneMindestpunkteService
{
    /**
     * Mehr offene Zusatzprüfungen als diese bekommen keine Kombinationstabelle.
     *
     * Dieselbe Grenze wie bei MindestpunkteService, der für reguläre Schüler
     * bei drei offenen Zusatzprüfungen endet. Vier sind bei externen Bewerbern
     * rechtlich möglich — alle vier Fächer des ersten Teils können zusätzlich
     * mündlich geprüft werden —, aber 16⁴ Kandidaten sind weder zu rechnen noch
     * zu lesen. Der Fall wird im Ergebnis ausdrücklich als solcher ausgewiesen
     * (`zu_viele_offene`) und nicht stillschweigend gekappt.
     */
    public const MAX_OFFENE_ZUSATZPRUEFUNGEN = 3;

    /**
     * Beide Prüfungsteile verlangen zwanzig Punkte einfacher Wertung über ihre
     * vier Fächer: 220 / 11 = 20 und 80 / 4 = 20.
     */
    public const EINFACHE_WERTUNG_JE_TEIL = 20;

    /** § 62 Abs. 1 S. 3 Nr. 3 und Abs. 2 Nr. 3: zwei Fächer mit mindestens 5. */
    public const FAECHER_MIT_FUENF = 2;

    public function __construct(
        private readonly ExterneGesamtqualifikationService $rechner
    ) {
    }

    /**
     * Was fehlt dem Bewerber noch zum Bestehen nach § 62?
     *
     * Die Fächer kommen in derselben Form wie bei
     * ExterneGesamtqualifikationService::berechne(), mit einem Zusatz: bei einer
     * Zusatzprüfung darf `muendlich` null sein. Genau das ist die offene Größe,
     * nach der dieser Service auflöst.
     *
     * @param list<array<string, mixed>> $faecher acht Fächer, Positionen 1–8
     * @return array<string, mixed>
     */
    public function berechne(
        array $faecher,
        string $schema = ExterneGesamtqualifikationService::SCHEMA_ANLAGE_14
    ): array {
        $faecher = $this->bereinige($faecher);
        $offene = $this->offeneZusatzpruefungen($faecher);

        // Ist-Stand: die offenen Zusatzprüfungen zählen als noch nicht bewertet.
        // Nicht etwa mit der schriftlichen Note als Untergrenze — bei m = 0 ist
        // (2s + 0) / 3 kleiner als s, eine Untergrenze wäre das also nicht.
        $ist = $this->rechner->berechne($this->ohneOffene($faecher), $schema);

        $ersterTeil = $this->ersterTeil($faecher, $offene, $schema, $ist);

        // § 62 Abs. 1 S. 4: steht der Abbruch fest, findet der zweite Teil nicht
        // mehr statt. Dann wird für ihn auch nichts ausgewiesen (Entscheidung
        // Wilfried, 26.08.2026).
        $abbruch = $ist['erster_teil']['bestanden'] === false
            ? ExterneGesamtqualifikationService::ABBRUCH_NACH_ERSTEM_TEIL
            : null;

        return [
            'schema' => $schema,
            'rechnung' => $ist,
            'erster_teil' => $ersterTeil,
            'zweiter_teil' => $abbruch === null ? $this->zweiterTeil($ist) : null,
            'abbruch' => $abbruch,
        ];
    }

    // ── Die offenen Größen heraussuchen ────────────────────────────────────

    /**
     * Eine beantragte Zusatzprüfung ohne eingetragene schriftliche Note ist
     * keine Rechengrundlage: der Rechner verlangt zwei Zahlen und würde null
     * zu 0 machen — ein Fach ohne Note stünde dann mit 0 Punkten in der
     * Rechnung und löste einen Abbruch aus, den es gar nicht gibt.
     *
     * Solche Fächer werden hier auf „noch nicht bewertet" zurückgesetzt.
     * ersterTeilRechenbar() erkennt sie daran und verweigert die Tabelle.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, mixed>>
     */
    private function bereinige(array $faecher): array
    {
        foreach ($faecher as $index => $fach) {
            $zusatzpruefung = $fach['zusatzpruefung'] ?? null;

            if (! is_array($zusatzpruefung)) {
                continue;
            }

            if (($zusatzpruefung['schriftlich'] ?? null) !== null) {
                continue;
            }

            unset($fach['zusatzpruefung']);
            $fach['punkte'] = null;
            $fach['zusatzpruefung_ohne_schriftliche_note'] = true;
            $faecher[$index] = $fach;
        }

        return array_values($faecher);
    }

    /**
     * Die Fächer mit beantragter, aber noch nicht abgelegter Zusatzprüfung.
     *
     * Erkennungsmerkmal ist `zusatzpruefung` mit `muendlich === null`. Die
     * schriftliche Note muss stehen — ohne sie ist nichts auszurechnen.
     * Sortiert nach Position: das ist die Nummerierung der Anlage 14 und
     * gleichzeitig die Reihenfolge, in der die Fächer überall sonst erscheinen.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, mixed>> je Eintrag zusätzlich 'index'
     */
    private function offeneZusatzpruefungen(array $faecher): array
    {
        $offene = [];

        foreach ($faecher as $index => $fach) {
            $zusatzpruefung = $fach['zusatzpruefung'] ?? null;

            if (! is_array($zusatzpruefung)) {
                continue;
            }

            if (($zusatzpruefung['muendlich'] ?? null) !== null) {
                continue;
            }

            if (($zusatzpruefung['schriftlich'] ?? null) === null) {
                continue;
            }

            $offene[] = ['index' => $index] + $fach;
        }

        usort($offene, fn (array $a, array $b): int => (int) $a['position'] <=> (int) $b['position']);

        return $offene;
    }

    /**
     * Dieselben Fächer, aber die offenen Zusatzprüfungen als „noch nicht
     * bewertet". Der Rechner verlangt für eine Zusatzprüfung zwei Zahlen und
     * würde null zu 0 machen — deshalb fällt sie hier ganz weg.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, mixed>>
     */
    private function ohneOffene(array $faecher): array
    {
        $offeneIndizes = array_column($this->offeneZusatzpruefungen($faecher), 'index');

        foreach ($faecher as $index => $fach) {
            if (! in_array($index, $offeneIndizes, true)) {
                continue;
            }

            unset($fach['zusatzpruefung']);
            $fach['punkte'] = null;
            $faecher[$index] = $fach;
        }

        return array_values($faecher);
    }

    // ── Erster Prüfungsteil ────────────────────────────────────────────────

    /**
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     * @param array<string, mixed> $ist
     * @return array<string, mixed>
     */
    private function ersterTeil(array $faecher, array $offene, string $schema, array $ist): array
    {
        $ergebnis = [
            'offene' => array_map(fn (array $fach): array => [
                'position' => (int) $fach['position'],
                'fach' => isset($fach['fach']) ? (string) $fach['fach'] : null,
                'ea' => (bool) ($fach['ea'] ?? false),
                'schriftlich' => (int) $fach['zusatzpruefung']['schriftlich'],
            ], $offene),
            'kombinationen' => null,
            'top5' => [],
            'bestehende' => 0,
            'zu_viele_offene' => false,
            'eingaben_vollstaendig' => $this->ersterTeilRechenbar($faecher, $offene),
        ];

        if ($offene === [] || ! $ergebnis['eingaben_vollstaendig']) {
            return $ergebnis;
        }

        if (count($offene) > self::MAX_OFFENE_ZUSATZPRUEFUNGEN) {
            $ergebnis['zu_viele_offene'] = true;

            return $ergebnis;
        }

        $kombinationen = $this->kombinationen($faecher, $offene, $schema);
        $bestehende = array_values(array_filter(
            $kombinationen,
            fn (array $zeile): bool => $zeile['bestanden'] === true
        ));

        $ergebnis['kombinationen'] = $kombinationen;
        $ergebnis['bestehende'] = count($bestehende);
        $ergebnis['top5'] = $this->topFuenf($bestehende, $offene);

        return $ergebnis;
    }

    /**
     * Eine Kombinationstabelle setzt voraus, dass jedes Fach des ersten Teils
     * entweder bewertet ist oder eine offene Zusatzprüfung mit bekannter
     * schriftlicher Note trägt. Fehlt irgendwo die schriftliche Note, ist die
     * Frage nach der Mindestnote noch gar nicht gestellt.
     *
     * Gegenstück zu MindestpunkteService::allFixedScoresEntered().
     *
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     */
    private function ersterTeilRechenbar(array $faecher, array $offene): bool
    {
        $offenePositionen = array_map('intval', array_column($offene, 'position'));

        foreach ($faecher as $fach) {
            $position = (int) ($fach['position'] ?? 0);

            if ($position < 1 || $position > 4) {
                continue;
            }

            if (in_array($position, $offenePositionen, true)) {
                continue;
            }

            $zusatzpruefung = $fach['zusatzpruefung'] ?? null;

            if (is_array($zusatzpruefung)) {
                if (($zusatzpruefung['schriftlich'] ?? null) === null
                    || ($zusatzpruefung['muendlich'] ?? null) === null) {
                    return false;
                }

                continue;
            }

            if (($fach['punkte'] ?? null) === null) {
                return false;
            }
        }

        return true;
    }

    // ── Aufzählung der Kandidaten ──────────────────────────────────────────

    /**
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     * @return list<array<string, mixed>>
     */
    private function kombinationen(array $faecher, array $offene, string $schema): array
    {
        return match (count($offene)) {
            1 => $this->kombinationenN1($faecher, $offene, $schema),
            2 => $this->kombinationenN2($faecher, $offene, $schema),
            default => $this->kombinationenN3($faecher, $offene, $schema),
        };
    }

    /**
     * Eine offene Zusatzprüfung: alle sechzehn möglichen mündlichen Noten.
     *
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     * @return list<array<string, mixed>>
     */
    private function kombinationenN1(array $faecher, array $offene, string $schema): array
    {
        $zeilen = [];

        for ($muendlich = 0; $muendlich <= 15; $muendlich++) {
            $zeilen[] = $this->zeile($faecher, $offene, $schema, [$muendlich]);
        }

        return $zeilen;
    }

    /**
     * Zwei offene Zusatzprüfungen: je Note im ersten Fach die kleinste Note im
     * zweiten, mit der der erste Prüfungsteil noch besteht. Dieselbe Form wie
     * MindestpunkteService für N = 2.
     *
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     * @return list<array<string, mixed>>
     */
    private function kombinationenN2(array $faecher, array $offene, string $schema): array
    {
        $zeilen = [];

        for ($ersteNote = 0; $ersteNote <= 15; $ersteNote++) {
            $treffer = null;

            for ($zweiteNote = 0; $zweiteNote <= 15; $zweiteNote++) {
                $zeile = $this->zeile($faecher, $offene, $schema, [$ersteNote, $zweiteNote]);

                if ($zeile['bestanden']) {
                    $treffer = $zeile;
                    break;
                }
            }

            $zeilen[] = $treffer ?? $this->zeile($faecher, $offene, $schema, [$ersteNote, 15]);
        }

        return $zeilen;
    }

    /**
     * Drei offene Zusatzprüfungen: zu jedem Paar der ersten beiden Noten die
     * dritte, die die Steigerungen am gleichmäßigsten verteilt — nicht die
     * kleinste. Bei Gleichstand die kleinste Summe der absoluten Abweichungen,
     * danach die kleinste dritte Note. Übernommen aus MindestpunkteService,
     * damit beide Kalkulatoren dieselbe Empfehlung geben.
     *
     * Ausgegeben werden nur bestehende Kombinationen; ein Paar, zu dem es keine
     * dritte Note gibt, erzeugt keine Zeile.
     *
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     * @return list<array<string, mixed>>
     */
    private function kombinationenN3(array $faecher, array $offene, string $schema): array
    {
        $schriftlich = array_map(
            fn (array $fach): int => (int) $fach['zusatzpruefung']['schriftlich'],
            $offene
        );

        $zeilen = [];

        for ($ersteNote = 0; $ersteNote <= 15; $ersteNote++) {
            for ($zweiteNote = 0; $zweiteNote <= 15; $zweiteNote++) {
                $beste = null;
                $besteSpanne = null;
                $besteAbweichung = null;

                for ($dritteNote = 0; $dritteNote <= 15; $dritteNote++) {
                    $noten = [$ersteNote, $zweiteNote, $dritteNote];
                    $zeile = $this->zeile($faecher, $offene, $schema, $noten);

                    if (! $zeile['bestanden']) {
                        continue;
                    }

                    $abweichungen = [
                        $ersteNote - $schriftlich[0],
                        $zweiteNote - $schriftlich[1],
                        $dritteNote - $schriftlich[2],
                    ];
                    $spanne = max($abweichungen) - min($abweichungen);
                    $summeAbsolut = array_sum(array_map('abs', $abweichungen));

                    if ($beste === null
                        || $spanne < $besteSpanne
                        || ($spanne === $besteSpanne && $summeAbsolut < $besteAbweichung)) {
                        $beste = $zeile;
                        $besteSpanne = $spanne;
                        $besteAbweichung = $summeAbsolut;
                    }
                }

                if ($beste !== null) {
                    $zeilen[] = $beste;
                }
            }
        }

        return $zeilen;
    }

    /**
     * Eine Kandidatenzeile: die angenommenen mündlichen Noten einsetzen und den
     * ersten Prüfungsteil vom Rechner bewerten lassen.
     *
     * Anlage 12 S. 6 — unter elf Punkten elffacher Wertung ist die
     * Abiturprüfung nicht bestanden — führt der Rechner als eigenen Grund und
     * setzt damit `bestanden` auf false. Die Zeile fällt also hart durch
     * (Entscheidung Wilfried, 26.08.2026); sie wird nicht bloß markiert.
     *
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     * @param list<int> $noten
     * @return array<string, mixed>
     */
    private function zeile(array $faecher, array $offene, string $schema, array $noten): array
    {
        $ergebnis = $this->rechner->berechne(
            $this->mitMuendlichen($faecher, $offene, $noten),
            $schema
        );

        $werte = [];

        foreach ($offene as $rang => $fach) {
            $bewertet = $ergebnis['faecher'][(int) $fach['position'] - 1];

            $werte[] = [
                'position' => (int) $fach['position'],
                'muendlich' => $noten[$rang],
                'einfache_wertung' => $bewertet['einfache_wertung'],
                'gesamtpunktzahl' => $bewertet['gesamtpunktzahl'],
            ];
        }

        return [
            'faecher' => $werte,
            'summe' => $ergebnis['erster_teil']['summe'],
            'bestanden' => $ergebnis['erster_teil']['bestanden'] === true,
            'gruende' => $ergebnis['erster_teil']['gruende'],
        ];
    }

    /**
     * Dieselben Fächer, aber mit eingesetzten mündlichen Noten für die offenen
     * Zusatzprüfungen.
     *
     * @param list<array<string, mixed>> $faecher
     * @param list<array<string, mixed>> $offene
     * @param list<int> $noten
     * @return list<array<string, mixed>>
     */
    private function mitMuendlichen(array $faecher, array $offene, array $noten): array
    {
        foreach ($offene as $rang => $fach) {
            $index = (int) $fach['index'];
            $zusatzpruefung = $faecher[$index]['zusatzpruefung'];
            $zusatzpruefung['muendlich'] = $noten[$rang];
            $faecher[$index]['zusatzpruefung'] = $zusatzpruefung;
        }

        return array_values($faecher);
    }

    /**
     * Die fünf wahrscheinlichsten Kombinationen: die mit der gleichmäßigsten
     * Steigerung gegenüber den schriftlichen Noten. Bei einer einzigen offenen
     * Zusatzprüfung genügt eine Zeile — jede höhere Note besteht ebenfalls und
     * sagt nichts Neues. Dieselbe Auswahl wie bei MindestpunkteService.
     *
     * @param list<array<string, mixed>> $bestehende
     * @param list<array<string, mixed>> $offene
     * @return list<array<string, mixed>>
     */
    private function topFuenf(array $bestehende, array $offene): array
    {
        if ($bestehende === [] || $offene === []) {
            return array_slice($bestehende, 0, 5);
        }

        if (count($offene) === 1) {
            return array_slice($bestehende, 0, 1);
        }

        $schriftlich = array_map(
            fn (array $fach): int => (int) $fach['zusatzpruefung']['schriftlich'],
            $offene
        );

        $bewertet = array_map(function (array $zeile) use ($schriftlich): array {
            $abweichungen = [];

            foreach ($zeile['faecher'] as $rang => $fach) {
                $abweichungen[] = (int) $fach['muendlich'] - $schriftlich[$rang];
            }

            $zeile['_spanne'] = max($abweichungen) - min($abweichungen);
            $zeile['_summe_absolut'] = array_sum(array_map('abs', $abweichungen));

            return $zeile;
        }, $bestehende);

        usort($bewertet, function (array $a, array $b): int {
            if ($a['_spanne'] !== $b['_spanne']) {
                return $a['_spanne'] <=> $b['_spanne'];
            }

            return $a['_summe_absolut'] <=> $b['_summe_absolut'];
        });

        return array_slice(
            array_map(
                fn (array $zeile): array => array_diff_key($zeile, ['_spanne' => 0, '_summe_absolut' => 0]),
                $bewertet
            ),
            0,
            5
        );
    }

    // ── Zweiter Prüfungsteil ───────────────────────────────────────────────

    /**
     * Restbedarf statt Tabelle: was in den noch offenen Fächern fünf bis acht
     * zusammen erreicht werden muss.
     *
     * Im zweiten Prüfungsteil gibt es keine Zusatzprüfungen, also auch keine
     * Rundung: die Gesamtpunktzahl eines Fachs ist exakt Punkte × 4. Damit ist
     * die Frage arithmetisch beantwortbar und braucht keine Aufzählung.
     *
     * Drei Bedingungen aus § 62 Abs. 2 sind zu erfüllen, und alle drei werden
     * getrennt ausgewiesen, weil sie einzeln reißen können:
     *  – kein Fach mit 0 Punkten, also jedes offene Fach mindestens 1;
     *  – zusammen mindestens 80 Punkte;
     *  – mindestens zwei Fächer mit wenigstens 5 Punkten einfacher Wertung.
     *
     * @param array<string, mixed> $ist
     * @return array<string, mixed>
     */
    private function zweiterTeil(array $ist): array
    {
        /** @var list<array<string, mixed>> $faecher */
        $faecher = array_slice($ist['faecher'], 4, 4);

        $bewertet = array_values(array_filter(
            $faecher,
            fn (array $fach): bool => $fach['gesamtpunktzahl'] !== null
        ));
        $offen = array_values(array_filter(
            $faecher,
            fn (array $fach): bool => $fach['gesamtpunktzahl'] === null
        ));

        $anzahlOffen = count($offen);
        $erreicht = array_sum(array_map(fn (array $fach): int => (int) $fach['gesamtpunktzahl'], $bewertet));
        $erreichteWertung = array_sum(
            array_map(fn (array $fach): int => (int) $fach['einfache_wertung'], $bewertet)
        );
        $mitFuenf = count(array_filter(
            $bewertet,
            fn (array $fach): bool => (int) $fach['einfache_wertung'] >= 5
        ));

        $noetigePunkte = max(0, ExterneGesamtqualifikationService::MINDESTPUNKTE_ZWEITER_TEIL - $erreicht);
        // Ein offenes Fach trägt exakt Punkte × 4 bei — aufgerundet ist das die
        // kleinste Summe einfacher Wertungen, die die Schwelle noch erreicht.
        $noetigeWertung = (int) ceil($noetigePunkte / ExterneGesamtqualifikationService::FAKTOR_ZWEITER_TEIL);
        $nochMitFuenf = max(0, self::FAECHER_MIT_FUENF - $mitFuenf);

        // Kleinste Belegung, die beide Mengenbedingungen zugleich erfüllt: die
        // geforderten Fünfen, jedes übrige offene Fach mindestens 1 — und
        // mindestens so viel, wie zur Schwelle noch fehlt.
        $mindestbelegung = $anzahlOffen > 0
            ? max($noetigeWertung, $nochMitFuenf * 5 + max(0, $anzahlOffen - $nochMitFuenf))
            : $noetigeWertung;

        // Ein Fach, das bereits mit 0 Punkten abgeschlossen ist, ist durch kein
        // späteres Ergebnis mehr zu heilen — der Rechner führt das als Grund.
        $endgueltigVerfehlt = $ist['zweiter_teil']['gruende'] !== [];

        if ($anzahlOffen === 0) {
            $erreichbar = ! $endgueltigVerfehlt && $ist['zweiter_teil']['bestanden'] === true;
        } else {
            $erreichbar = ! $endgueltigVerfehlt
                && $nochMitFuenf <= $anzahlOffen
                && $mindestbelegung <= 15 * $anzahlOffen;
        }

        return [
            'offene' => array_map(fn (array $fach): array => [
                'position' => (int) $fach['position'],
                'fach' => $fach['fach'],
                'ea' => (bool) $fach['ea'],
            ], $offen),
            'erreicht' => $erreicht,
            'erreichte_wertung' => $erreichteWertung,
            'schwelle' => ExterneGesamtqualifikationService::MINDESTPUNKTE_ZWEITER_TEIL,
            'schwelle_wertung' => self::EINFACHE_WERTUNG_JE_TEIL,
            'noch_noetige_punkte' => $noetigePunkte,
            'noch_noetige_wertung' => $noetigeWertung,
            'mindestbelegung' => $mindestbelegung,
            'faecher_mit_fuenf' => $mitFuenf,
            'noch_faecher_mit_fuenf' => $nochMitFuenf,
            'erreichbar' => $erreichbar,
            'gruende' => $ist['zweiter_teil']['gruende'],
        ];
    }
}
