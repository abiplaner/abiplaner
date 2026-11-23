<?php

namespace App\Services;

use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Fächerwahl externer Bewerber — Regelprüfung nach § 61 BayGSO.
 *
 * Neu hergeleitet aus dem Wortlaut (Entscheidung Wilfried, 25.08.2026). Die
 * Regel-IDs verweisen auf docs/externe-bewerber/REGELWERK-61-62.md; dort steht
 * zu jeder Regel der Absatz und der Satz, aus dem sie stammt.
 *
 * ── Aufbau ─────────────────────────────────────────────────────────────────
 * pruefeWahl() ist der Regelkern und arbeitet auf einer einfachen Liste von
 * Fachbeschreibungen — ohne Datenbank, ohne Eloquent. Jede Regel ist dadurch
 * einzeln und schnell prüfbar. validateSubjectSelection() lädt die Fächerwahl
 * eines Bewerbers, reichert sie aus dem zentralen Fächerkatalog an und
 * delegiert.
 *
 * ── Was diese Prüfung NICHT abdeckt ────────────────────────────────────────
 * Diese Lücken sind bekannt und liegen am Datenmodell, nicht an der Herleitung:
 *
 *  – F1 (§ 48 Abs. 1 Nr. 1): Kunst und Musik nur schriftlich, Sport als
 *    besondere Fachprüfung, jeweils nur bei entsprechender Belegung. Die
 *    Belegung der Jahrgangsstufe 11 führt die Anwendung für externe Bewerber
 *    nicht.
 *  – F3 bis F5: Lehrplanalternativen (Biophysik, Astrophysik, Geologie) sind
 *    im Datenmodell nicht abgebildet.
 *  – A7 (§ 61 Abs. 1 S. 6): besondere Fachprüfung bei Kunst, Musik oder Sport
 *    als Leistungsfach — hängt an derselben Belegung wie F1.
 *  – A8, B5: seit dem 01.09.2026 in `App\Services\ExterneFristenService`
 *    abgebildet — reine Anzeige, siehe dort. G1 (15. Dezember, Ersatzschulen)
 *    ist weiterhin nicht abgebildet (Entscheidung 38: organisatorisch).
 *
 * Wer eine davon nachrüstet, ergänzt hier eine Regel und dort einen Test.
 */
class ExternalApplicantValidationService
{
    /** Amtliche Kürzel der in § 61 Abs. 1 S. 2 und S. 5 namentlich genannten Fächer. */
    private const DEUTSCH = 'D';
    private const MATHEMATIK = 'M';
    private const GESCHICHTE = 'G';

    private const TEIL_1 = 'part1_written';
    private const TEIL_2 = 'part2_oral';

    /** Die drei Aufgabenfelder nach § 18 Abs. 1; 'sport' und 'sonstiges' gehören zu keinem. */
    private const AUFGABENFELDER = [
        'sprachlich_literarisch' => 'Sprachlich-literarisch-künstlerisch',
        'gesellschaftswissenschaftlich' => 'Gesellschaftswissenschaftlich',
        'mint' => 'Mathematisch-naturwissenschaftlich-technisch',
    ];

    /** @var Collection<int, Subject>|null */
    private ?Collection $katalog = null;

    /**
     * Fächerwahl eines Bewerbers prüfen.
     *
     * @return array{valid: bool, errors: list<string>, warnings: list<string>, verstoesse: list<array<string, string>>, hinweise: list<array<string, string>>}
     */
    public function validateSubjectSelection(ExternalApplicant $applicant): array
    {
        return $this->pruefeWahl(
            $this->ausFaechern(
                $applicant->examSubjects,
                $applicant->leistungsfach_subject_id,
                $applicant->zweite_fremdsprache_subject_id,
            ),
            $applicant->istErsatzschueler(),
        );
    }

    /**
     * Die gespeicherte Fächerwahl in die Beschreibung überführen, auf der die
     * Regeln arbeiten.
     *
     * @param Collection<int, ExternalExamSubject> $faecher
     * @return list<array<string, mixed>>
     */
    public function ausFaechern(
        Collection $faecher,
        ?int $leistungsfachSubjectId,
        ?int $zweiteFremdspracheSubjectId = null,
    ): array {
        // Kein 'ersatzname' mehr: die gleichnamige Freitextspalte ist am
        // 26.08.2026 gefallen (2026_08_26_150000). Der Ausdruck stand hier
        // trotzdem weiter — und traf seither die Beziehung statt der Spalte.
        // Ein vorhandenes Fach ergab damit sein JSON (Model::__toString()),
        // ein aus dem Katalog entferntes den leeren String, der den Rückfall
        // auf 'Unbekanntes Fach' aushebelte, weil er nicht null ist.
        $auswahl = $faecher->map(static fn (ExternalExamSubject $fach): array => [
            'subject_id' => $fach->subject_id,
            'exam_part' => $fach->exam_part,
            'subject_number' => (int) $fach->subject_number,
            'ergebnis_quelle' => $fach->ergebnis_quelle,
        ])->values()->all();

        return $this->ausAuswahl($auswahl, $leistungsfachSubjectId, $zweiteFremdspracheSubjectId);
    }

    /**
     * Eine noch nicht gespeicherte Auswahl in die Beschreibung überführen —
     * für die Prüfung während der Eingabe und für das Speichern.
     *
     * Das erhöhte Anforderungsniveau steht hier und nirgends sonst: Deutsch und
     * Mathematik immer, dazu das mit der Anmeldung festgelegte Leistungsfach
     * (§ 61 Abs. 1 S. 5). Deshalb kann eine Wahl nicht mehr in sich
     * widersprüchlich sein, wie es mit den beiden alten Spalten möglich war.
     *
     * @param list<array<string, mixed>> $auswahl je Eintrag subject_id,
     *        exam_part, subject_number; optional ergebnis_quelle
     *        ('pruefung' oder 'einbringung', § 64 Abs. 2)
     * @return list<array<string, mixed>>
     */
    public function ausAuswahl(
        array $auswahl,
        ?int $leistungsfachSubjectId,
        ?int $zweiteFremdspracheSubjectId = null,
    ): array {
        $katalog = $this->katalog();
        $faecher = [];

        foreach ($auswahl as $eintrag) {
            $subjectId = $eintrag['subject_id'] === null ? null : (int) $eintrag['subject_id'];
            $fach = $subjectId === null ? null : $katalog->get($subjectId);
            $teil = (string) $eintrag['exam_part'];
            $nummer = (int) $eintrag['subject_number'];

            // Fallunterscheidung statt ?-> ?? — Larastan hält die Eigenschaft
            // für nicht-nullable und meldet den Nullsafe sonst als unnötig.
            // Null ist hier trotzdem möglich: ein Fach, das aus dem Katalog
            // entfernt wurde, hat keinen Eintrag mehr. Regel A5 meldet den Fall.
            $name = $fach instanceof Subject ? $fach->name : 'Unbekanntes Fach';

            $faecher[] = [
                'position' => $teil === self::TEIL_2 ? 4 + $nummer : $nummer,
                'teil' => $teil,
                'nummer' => $nummer,
                'ea' => $this->istErhoehtesNiveau($fach, $leistungsfachSubjectId),
                // § 61 Abs. 3 S. 7: welche der beiden Fremdsprachen die
                // zweite ist, benennt der Prüfling (Entscheidung 32).
                'zweite_fremdsprache' => $subjectId !== null
                    && $zweiteFremdspracheSubjectId !== null
                    && $subjectId === $zweiteFremdspracheSubjectId,
                'eingebracht' => ($eintrag['ergebnis_quelle'] ?? 'pruefung') === 'einbringung',
                // Nicht dasselbe wie 'ea': erhöht sind Deutsch und Mathematik
                // auch ohne Leistungsfach zu sein (§ 61 Abs. 1 S. 5). § 64
                // Abs. 2 S. 7 nennt beide getrennt, also stehen sie hier
                // getrennt.
                'leistungsfach' => $subjectId !== null
                    && $leistungsfachSubjectId !== null
                    && $subjectId === $leistungsfachSubjectId,
                'code' => $fach?->code,
                'name' => $name,
                'aufgabenfeld' => $fach?->aufgabenfeld,
                'fremdsprache' => (bool) $fach?->is_foreign_language,
                // 'spaetbeginnend' ist am 27.08.2026 gefallen: gelesen hat es
                // allein der Prüfungsplan, und dort war es die falsche Auskunft
                // (§ 61 Abs. 3 S. 7 meint die benannte zweite Fremdsprache, nicht
                // jedes Fach, das spät beginnend angeboten werden kann). Wer F2
                // eines Tages als eigene Regel baut, holt `can_be_late_starting`
                // hier wieder herein — ein Feld, das niemand liest, bleibt nicht
                // stehen.
                'naturwissenschaft' => (bool) $fach?->is_science,
                'abiturfach' => (bool) $fach?->can_abitur,
            ];
        }

        return $faecher;
    }

    /**
     * § 61 Abs. 1 S. 5 — die einzige Stelle, an der "erhöhtes
     * Anforderungsniveau" entschieden wird.
     */
    public function istErhoehtesNiveau(?Subject $fach, ?int $leistungsfachSubjectId): bool
    {
        if (! $fach instanceof Subject) {
            return false;
        }

        if (in_array($fach->code, [self::DEUTSCH, self::MATHEMATIK], true)) {
            return true;
        }

        return $leistungsfachSubjectId !== null && $fach->id === $leistungsfachSubjectId;
    }

    /**
     * Der zentrale Fächerkatalog, einmal je Anfrage. Er ist klein genug, um ihn
     * vollständig zu halten; die Alternative wäre eine Abfrage je Bewerber auf
     * der Übersichtsseite.
     *
     * @return Collection<int, Subject>
     */
    public function katalog(): Collection
    {
        return $this->katalog ??= Subject::query()->orderBy('sort_order')->get()->keyBy('id');
    }

    /**
     * Der Regelkern.
     *
     * @param list<array<string, mixed>> $faecher je Fach:
     *   'position'          int    1–8 nach Anlage 14
     *   'teil'              string part1_written|part2_oral
     *   'nummer'            int    1–4 innerhalb des Teils
     *   'ea'                bool   erhöhtes Anforderungsniveau
     *   'code'              ?string amtliches Fächerkürzel
     *   'name'              string  Anzeigename
     *   'aufgabenfeld'      ?string
     *   'fremdsprache'      bool
     *   'zweite_fremdsprache' bool  vom Prüfling benannt (§ 61 Abs. 3 S. 7)
     *   'eingebracht'       bool   statt geprüft (§ 64 Abs. 2)
     *   'naturwissenschaft' bool
     *   'abiturfach'        bool   can_abitur
     * @return array{valid: bool, errors: list<string>, warnings: list<string>, verstoesse: list<array<string, string>>, hinweise: list<array<string, string>>}
     */
    public function pruefeWahl(array $faecher, bool $istErsatzschueler = false): array
    {
        $verstoesse = $this->pruefeUmfang($faecher);

        // Ohne acht Fächer in der richtigen Aufteilung sind die übrigen Regeln
        // nicht sinnvoll auswertbar — sie würden nur Folgefehler melden.
        if ($verstoesse === []) {
            $verstoesse = array_merge(
                $this->pruefeFaecherkatalog($faecher),
                $this->pruefePflichtfaecher($faecher),
                $this->pruefeNiveau($faecher),
                $this->pruefeErstenTeil($faecher),
                $this->pruefeZweitenTeil($faecher),
                $this->pruefeFremdsprachen($faecher),
                $this->pruefeEinbringung($faecher, $istErsatzschueler),
                $this->pruefePositionen($faecher),
            );
        }

        // Hinweise machen die Wahl nicht ungültig — `valid` hängt allein an den
        // Verstößen. Sie benennen Fälle, die die Norm offenlässt und über die
        // der Koordinator entscheidet.
        $hinweise = $verstoesse === [] ? $this->pruefeDritteFremdsprache($faecher) : [];

        return [
            'valid' => $verstoesse === [],
            'errors' => array_map(
                static fn (array $v): string => $v['norm'] . ': ' . $v['meldung'],
                $verstoesse
            ),
            'warnings' => array_map(
                static fn (array $v): string => $v['norm'] . ': ' . $v['meldung'],
                $hinweise
            ),
            'verstoesse' => $verstoesse,
            'hinweise' => $hinweise,
        ];
    }

    /**
     * @return array{id: string, norm: string, meldung: string}
     */
    private function verstoss(string $id, string $norm, string $meldung): array
    {
        return ['id' => $id, 'norm' => $norm, 'meldung' => $meldung];
    }

    /**
     * A1, A3 — acht Prüfungsfächer, vier schriftlich und vier mündlich.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeUmfang(array $faecher): array
    {
        $verstoesse = [];
        $teil1 = $this->teil($faecher, self::TEIL_1);
        $teil2 = $this->teil($faecher, self::TEIL_2);

        if (count($faecher) !== 8) {
            $verstoesse[] = $this->verstoss('A1', '§ 61 Abs. 1 S. 1',
                'Gegenstand der Prüfung sind acht Prüfungsfächer. Gewählt sind ' . count($faecher) . '.');
        }

        if (count($teil1) !== 4) {
            $verstoesse[] = $this->verstoss('A3', '§ 61 Abs. 1 S. 3',
                'Der erste Prüfungsteil umfasst vier schriftlich geprüfte Fächer. Gewählt sind '
                . count($teil1) . '.');
        }

        if (count($teil2) !== 4) {
            $verstoesse[] = $this->verstoss('A3', '§ 61 Abs. 1 S. 3',
                'Der zweite Prüfungsteil umfasst vier weitere, nur mündlich geprüfte Fächer. Gewählt sind '
                . count($teil2) . '.');
        }

        return $verstoesse;
    }

    /**
     * A5 — wählbar sind nur Fächer, die auch für Schülerinnen und Schüler der
     * Gymnasien als Prüfungsfächer wählbar sind (§ 48 Abs. 1 S. 8 mit Anlage 3
     * und Anlage 4 Nr. 1). Der Katalog bildet das über can_abitur ab.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeFaecherkatalog(array $faecher): array
    {
        $verstoesse = [];

        foreach ($faecher as $fach) {
            if ($fach['code'] === null) {
                $verstoesse[] = $this->verstoss('A5', '§ 61 Abs. 1 S. 4',
                    "Das Fach '{$fach['name']}' steht nicht im Fächerkatalog.");
                continue;
            }

            if ($fach['abiturfach'] !== true) {
                $verstoesse[] = $this->verstoss('A5', '§ 61 Abs. 1 S. 4',
                    "{$fach['name']} ist kein Abiturprüfungsfach.");
            }
        }

        return $verstoesse;
    }

    /**
     * A2 — unter den acht Fächern müssen Deutsch, Geschichte, Mathematik, eine
     * Naturwissenschaft und zwei Fremdsprachen sein.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefePflichtfaecher(array $faecher): array
    {
        $verstoesse = [];
        $norm = '§ 61 Abs. 1 S. 2';

        foreach ([self::DEUTSCH => 'Deutsch', self::GESCHICHTE => 'Geschichte', self::MATHEMATIK => 'Mathematik'] as $code => $name) {
            if (! $this->enthaeltCode($faecher, $code)) {
                $verstoesse[] = $this->verstoss('A2', $norm, "Das Pflichtfach {$name} fehlt.");
            }
        }

        if ($this->zaehle($faecher, 'naturwissenschaft') < 1) {
            $verstoesse[] = $this->verstoss('A2', $norm,
                'Es muss eine Naturwissenschaft gewählt werden (Biologie, Chemie oder Physik).');
        }

        $fremdsprachen = $this->zaehle($faecher, 'fremdsprache');

        if ($fremdsprachen < 2) {
            $verstoesse[] = $this->verstoss('A2', $norm,
                "Es müssen zwei Fremdsprachen gewählt werden. Gewählt sind {$fremdsprachen}.");
        }

        return $verstoesse;
    }

    /**
     * A6 — genau drei Fächer auf erhöhtem Anforderungsniveau: Deutsch,
     * Mathematik und das mit der Anmeldung festgelegte Leistungsfach.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeNiveau(array $faecher): array
    {
        $verstoesse = [];
        $norm = '§ 61 Abs. 1 S. 5';
        $ea = array_values(array_filter($faecher, static fn (array $f): bool => $f['ea'] === true));

        if (count($ea) !== 3) {
            $verstoesse[] = $this->verstoss('A6', $norm,
                'Genau drei Fächer werden auf erhöhtem Anforderungsniveau geprüft — Deutsch, '
                . 'Mathematik und das Leistungsfach. Markiert sind ' . count($ea) . '.');
        }

        foreach ([self::DEUTSCH => 'Deutsch', self::MATHEMATIK => 'Mathematik'] as $code => $name) {
            if ($this->enthaeltCode($faecher, $code) && ! $this->enthaeltCode($ea, $code)) {
                $verstoesse[] = $this->verstoss('A6', $norm,
                    "{$name} wird immer auf erhöhtem Anforderungsniveau geprüft.");
            }
        }

        return $verstoesse;
    }

    /**
     * B1, B3, B6 — die Regeln des ersten Prüfungsteils.
     *
     * B1 bezieht die Abdeckung der drei Aufgabenfelder ausdrücklich auf *die
     * vier Fächer des ersten Prüfungsteils*, nicht auf alle acht. Der bisherige
     * Dienst prüfte über alle acht; eine Wahl, die im ersten Teil nur zwei
     * Aufgabenfelder abdeckte, galt dadurch als gültig.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeErstenTeil(array $faecher): array
    {
        $verstoesse = [];
        $teil1 = $this->teil($faecher, self::TEIL_1);

        $abgedeckt = array_filter(array_column($teil1, 'aufgabenfeld'));

        foreach (self::AUFGABENFELDER as $schluessel => $bezeichnung) {
            if (! in_array($schluessel, $abgedeckt, true)) {
                $verstoesse[] = $this->verstoss('B1', '§ 61 Abs. 2 S. 1',
                    "Die vier Fächer des ersten Prüfungsteils decken das Aufgabenfeld "
                    . "{$bezeichnung} nicht ab.");
            }
        }

        // B3: unter den Fächern eins bis drei mindestens zwei auf erhöhtem
        // Niveau, darunter Mathematik.
        $ersteDrei = array_values(array_filter($teil1, static fn (array $f): bool => $f['nummer'] <= 3));
        $eaErsteDrei = array_values(array_filter($ersteDrei, static fn (array $f): bool => $f['ea'] === true));

        if (count($eaErsteDrei) < 2) {
            $verstoesse[] = $this->verstoss('B3', '§ 61 Abs. 2 S. 3',
                'Unter den Fächern eins bis drei müssen mindestens zwei auf erhöhtem '
                . 'Anforderungsniveau geprüft werden. Es sind ' . count($eaErsteDrei) . '.');
        }

        if (! $this->enthaeltCode($ersteDrei, self::MATHEMATIK)) {
            $verstoesse[] = $this->verstoss('B3', '§ 61 Abs. 2 S. 3',
                'Mathematik muss unter den Fächern eins bis drei des ersten Prüfungsteils sein.');
        }

        // B4: Wird Deutsch nicht im ersten Prüfungsteil geprüft, ist dort eine
        // Fremdsprache als Leistungsfach zu wählen.
        if (! $this->enthaeltCode($teil1, self::DEUTSCH)) {
            $fremdspracheAlsLeistungsfach = array_filter(
                $teil1,
                static fn (array $f): bool => $f['fremdsprache'] === true && $f['ea'] === true
            );

            if ($fremdspracheAlsLeistungsfach === []) {
                $verstoesse[] = $this->verstoss('B4', '§ 61 Abs. 2 S. 4',
                    'Wird Deutsch nicht im ersten Prüfungsteil geprüft, muss dort eine '
                    . 'Fremdsprache als Leistungsfach gewählt werden.');
            }
        }

        // B6: im vierten Fach nur grundlegendes Niveau.
        foreach ($teil1 as $fach) {
            if ($fach['nummer'] === 4 && $fach['ea'] === true) {
                $verstoesse[] = $this->verstoss('B6', '§ 61 Abs. 2 S. 6',
                    "Das vierte Fach des ersten Prüfungsteils wird nur auf grundlegendem "
                    . "Anforderungsniveau geprüft; {$fach['name']} ist als Leistungsfach markiert.");
            }
        }

        return $verstoesse;
    }

    /**
     * C1 — die Fächer des zweiten Prüfungsteils dürfen nicht bereits Gegenstand
     * der schriftlichen Prüfung sein. Praktisch: kein Fach doppelt.
     *
     * Bisher erzwang das nur der Unique-Index, der mitten in der Transaktion
     * als 500er durchschlug.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeZweitenTeil(array $faecher): array
    {
        $verstoesse = [];
        $namenJeCode = [];

        foreach ($faecher as $fach) {
            $schluessel = $fach['code'] ?? $fach['name'];
            $namenJeCode[$schluessel] = $fach['name'];
        }

        $anzahl = array_count_values(array_map(
            static fn (array $f): string => (string) ($f['code'] ?? $f['name']),
            $faecher
        ));

        foreach ($anzahl as $schluessel => $wieOft) {
            if ($wieOft > 1) {
                $verstoesse[] = $this->verstoss('C1', '§ 61 Abs. 3 S. 1',
                    "{$namenJeCode[$schluessel]} ist {$wieOft}-mal gewählt. Die Fächer des zweiten "
                    . 'Prüfungsteils dürfen nicht bereits Gegenstand der schriftlichen Prüfung sein.');
            }
        }

        return $verstoesse;
    }

    /**
     * C7 mit F2 — die zweite Fremdsprache wird nur auf dem Niveau einer spät
     * beginnenden Fremdsprache geprüft. Mit § 48 Abs. 1 Nr. 2 heißt das: nur
     * grundlegendes Niveau und nur als mündliches Prüfungsfach.
     *
     * **Abgenommen von Wilfried am 27.08.2026 (Entscheidung 31):** diese
     * Herleitung ist richtig. Die zweite Fremdsprache kann also nie
     * Leistungsfach und nie Fach des ersten Prüfungsteils sein.
     *
     * **Entscheidung 32:** welche der Fremdsprachen die zweite ist, sagt der
     * Wortlaut nicht — deshalb benennt sie der Prüfling selbst
     * (`external_applicants.zweite_fremdsprache_subject_id`). Bis zum
     * 27.08.2026 zählte diese Regel nur, wie viele Fremdsprachen wie eine
     * fortgeführte behandelt wurden; davor war sie nur eine Warnung und hing
     * am gefallenen Kennzeichen is_late_starting_language.
     *
     * **Drei Fremdsprachen:** Diese Regel beschränkt nur die benannte zweite.
     * Ob die dritte wie eine fortgeführte geprüft werden darf, sagt § 61
     * nicht. Seit dem 31.08.2026 wird der Fall als **Hinweis** gemeldet, nicht
     * als Verstoß (Entscheidung 54) — siehe pruefeDritteFremdsprache().
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeFremdsprachen(array $faecher): array
    {
        $verstoesse = [];
        $norm = '§ 61 Abs. 3 S. 7 mit § 48 Abs. 1 Nr. 2';

        $fremdsprachen = array_values(array_filter(
            $faecher,
            static fn (array $f): bool => $f['fremdsprache'] === true
        ));

        if (count($fremdsprachen) < 2) {
            return $verstoesse; // A2 meldet das bereits.
        }

        $benannte = array_values(array_filter(
            $faecher,
            static fn (array $f): bool => $f['zweite_fremdsprache'] === true
        ));

        if ($benannte === []) {
            $verstoesse[] = $this->verstoss('C7', $norm,
                'Es ist nicht festgelegt, welches der gewählten Fächer die zweite Fremdsprache '
                . 'ist. Sie wird nur auf dem Niveau einer spät beginnenden Fremdsprache geprüft '
                . 'und muss deshalb benannt werden.');

            return $verstoesse;
        }

        foreach ($benannte as $fach) {
            if ($fach['fremdsprache'] !== true) {
                $verstoesse[] = $this->verstoss('C7', $norm,
                    "Als zweite Fremdsprache ist {$fach['name']} benannt — das Fach ist keine "
                    . 'Fremdsprache.');

                continue;
            }

            if ($fach['teil'] === self::TEIL_1) {
                $verstoesse[] = $this->verstoss('C7', $norm,
                    "Die zweite Fremdsprache ({$fach['name']}) wird nur mündlich geprüft; sie "
                    . 'steht hier im ersten Prüfungsteil.');
            }

            if ($fach['ea'] === true) {
                $verstoesse[] = $this->verstoss('C7', $norm,
                    "Die zweite Fremdsprache ({$fach['name']}) wird nur auf grundlegendem "
                    . 'Anforderungsniveau geprüft; sie ist hier ein Fach auf erhöhtem Niveau.');
            }
        }

        return $verstoesse;
    }

    /**
     * C7, dritte Fremdsprache — ein Hinweis, kein Verstoß (Entscheidung 54,
     * 31.08.2026).
     *
     * § 61 Abs. 3 S. 7 beschränkt **die zweite Fremdsprache**: Sie wird nur
     * auf dem Niveau einer spät beginnenden geprüft. Über eine dritte sagt der
     * Wortlaut nichts. § 61 Abs. 1 S. 2 verlangt zwei — eine dritte ist also
     * zulässig, aber selten, und ihr Niveau ist ungeregelt.
     *
     * **Warum ein Hinweis und kein Verstoß:** Die Wahl abzuweisen hieße, eine
     * Frage zu entscheiden, die die Norm offenlässt — und einen zulässigen
     * Fall zu blockieren. Sie stillschweigend durchzulassen hieße, dem
     * Koordinator eine Entscheidung abzunehmen, von der er nichts erfährt.
     * Der Hinweis nennt den Fall und überlässt ihm die Entscheidung.
     *
     * Er erscheint erst, wenn die Wahl sonst fehlerfrei ist: Neben acht
     * offenen Verstößen wäre er nur Rauschen.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeDritteFremdsprache(array $faecher): array
    {
        $fremdsprachen = array_values(array_filter(
            $faecher,
            static fn (array $f): bool => $f['fremdsprache'] === true
        ));

        if (count($fremdsprachen) < 3) {
            return [];
        }

        $benannt = array_values(array_filter(
            $fremdsprachen,
            static fn (array $f): bool => $f['zweite_fremdsprache'] === true
        ));

        $benannteNamen = array_column($benannt, 'name');

        // Ohne benannte zweite Fremdsprache meldet C7 schon einen Verstoß, und
        // dieser Hinweis käme gar nicht erst dazu.
        if ($benannteNamen === []) {
            return [];
        }

        // Alle außer der benannten zweiten. Welche davon die *erste*
        // (fortgeführte) ist, führt das Datenmodell nicht — die Norm benennt
        // nur die zweite, und mehr wird hier auch nicht behauptet.
        $uebrige = array_column(array_filter(
            $fremdsprachen,
            static fn (array $f): bool => $f['zweite_fremdsprache'] !== true
        ), 'name');

        return [$this->verstoss(
            'C7',
            '§ 61 Abs. 3 S. 7',
            'Gewählt sind ' . count($fremdsprachen) . ' Fremdsprachen. Die Norm beschränkt '
            . 'allein die benannte zweite (' . $benannteNamen[0] . ') auf das Niveau einer '
            . 'spät beginnenden Fremdsprache; zu ' . implode(' und ', $uebrige)
            . ' sagt sie nichts. Auf welchem Niveau die weitere Fremdsprache geprüft wird, '
            . 'ist zu entscheiden — die Fächerwahl bleibt gültig.'
        )];
    }

    /**
     * G5 und G6 — die Ersetzung nach § 64 Abs. 2 (Ersatzschüler).
     *
     * Geprüft wird nur, wenn überhaupt ein Fach eingebracht werden soll. Wer
     * nichts einbringt, wird nach Anlage 14 bewertet wie jeder andere
     * Bewerber — § 64 Abs. 2 S. 1 sagt "kann" (Entscheidung 34).
     *
     * Vier Regeln:
     *  – nur für Schüler einer staatlich genehmigten Ersatzschule (S. 1),
     *  – in **genau zwei** Fächern (S. 1),
     *  – nur auf den Positionen **sieben und acht** (Anlage 15: dort steht
     *    die Punktzahl in der Spalte 13/II, bei 5 und 6 in der Spalte
     *    Prüfung),
     *  – **nicht** in den beiden Fremdsprachen, im Leistungsfach und in
     *    Deutsch (S. 7).
     *
     * Die letzte Regel kann eine sonst zulässige Fächerwahl unmöglich machen:
     * stehen drei der ausgenommenen Fächer im zweiten Prüfungsteil, bleibt nur
     * ein ersetzbares übrig, und S. 1 verlangt zwei. Das wird gemeldet, statt
     * die Ersetzung stillschweigend nicht anzubieten (Entscheidung 33) — ein
     * verschwundenes Angebot wäre dem Sachbearbeiter nicht erklärbar.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefeEinbringung(array $faecher, bool $istErsatzschueler): array
    {
        $verstoesse = [];
        $eingebracht = array_values(array_filter(
            $faecher,
            static fn (array $f): bool => $f['eingebracht'] === true
        ));

        if ($eingebracht === []) {
            return $verstoesse; // Anlage 14 — die Ersetzung ist nicht gewählt.
        }

        if (! $istErsatzschueler) {
            $verstoesse[] = $this->verstoss('G5', '§ 64 Abs. 2 S. 1',
                'Ein Ergebnis aus dem letzten Ausbildungshalbjahr kann nur einbringen, wer an '
                . 'einer staatlich genehmigten Ersatzschule unterrichtet wird; für diesen '
                . 'Bewerber ist keine Ersatzschule hinterlegt.');

            return $verstoesse; // alles Weitere wäre Folgefehler.
        }

        if (count($eingebracht) !== 2) {
            $verstoesse[] = $this->verstoss('G5', '§ 64 Abs. 2 S. 1',
                'Eingebracht wird in genau zwei der vier Fächer des zweiten Prüfungsteils; hier '
                . 'sind es ' . count($eingebracht) . '.');
        }

        foreach ($eingebracht as $fach) {
            if ($fach['position'] !== 7 && $fach['position'] !== 8) {
                $verstoesse[] = $this->verstoss('G5', 'Anlage 15',
                    "Eingebracht werden die Prüfungsfächer sieben und acht; {$fach['name']} "
                    . "steht auf Position {$fach['position']}.");
            }

            if ($this->vonErsetzungAusgenommen($fach)) {
                $verstoesse[] = $this->verstoss('G6', '§ 64 Abs. 2 S. 7',
                    'Von der Ersetzung ausgenommen sind die beiden Fremdsprachen, das '
                    . "Leistungsfach und Deutsch; {$fach['name']} gehört dazu.");
            }
        }

        $ersetzbar = count(array_filter(
            $this->teil($faecher, self::TEIL_2),
            fn (array $f): bool => ! $this->vonErsetzungAusgenommen($f)
        ));

        if ($ersetzbar < 2) {
            $verstoesse[] = $this->verstoss('G6', '§ 64 Abs. 2 S. 7',
                'Diese Fächerwahl lässt im zweiten Prüfungsteil '
                . ($ersetzbar === 0 ? 'kein Fach' : 'nur ein Fach')
                . ' zu, das ersetzt werden könnte — nötig sind zwei. Entweder die Fächerwahl '
                . 'ändern oder auf die Ersetzung verzichten.');
        }

        return $verstoesse;
    }

    /**
     * § 64 Abs. 2 S. 7 — die beiden Fremdsprachen, das Leistungsfach und
     * Deutsch bleiben geprüft.
     *
     * @param array<string, mixed> $fach
     */
    private function vonErsetzungAusgenommen(array $fach): bool
    {
        return $fach['fremdsprache'] === true
            || $fach['leistungsfach'] === true
            || $fach['code'] === self::DEUTSCH;
    }

    /**
     * E2 — die Positionen nach Anlage 14, und dieselben nach Anlage 15.
     *
     * Mathematik ist schriftliches Fach 1 (die bisherige Maske fixierte es auf
     * Fach 2), und im zweiten Prüfungsteil darf nur Fach 5 auf erhöhtem Niveau
     * liegen — es gibt also höchstens ein mündliches Fach auf eA.
     *
     * Die Regel ist in beiden Anlagen gleich: Anlage 15 unterscheidet sich von
     * Anlage 14 allein in der Herkunft der Punkte für die Fächer 7 und 8, die
     * Niveauspalten sind identisch. Nur die Norm in der Meldung wechselt —
     * eine Meldung, die auf Anlage 14 verweist, wäre für einen Ersatzschüler
     * die falsche Fundstelle.
     *
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, string>>
     */
    private function pruefePositionen(array $faecher): array
    {
        $verstoesse = [];
        $eingebracht = array_filter($faecher, static fn (array $f): bool => $f['eingebracht'] === true);
        $norm = $eingebracht === [] ? 'Anlage 14' : 'Anlage 15';

        foreach ($faecher as $fach) {
            if ($fach['code'] === self::MATHEMATIK && $fach['position'] !== 1) {
                $verstoesse[] = $this->verstoss('E2', $norm,
                    'Mathematik ist das erste schriftliche Prüfungsfach; gewählt ist Position '
                    . $fach['position'] . '.');
            }

            if ($fach['position'] >= 6 && $fach['ea'] === true) {
                $verstoesse[] = $this->verstoss('E2', $norm,
                    "Im zweiten Prüfungsteil darf nur das fünfte Prüfungsfach auf erhöhtem "
                    . "Anforderungsniveau liegen; {$fach['name']} steht auf Position "
                    . $fach['position'] . '.');
            }
        }

        return $verstoesse;
    }

    /**
     * @param list<array<string, mixed>> $faecher
     * @return list<array<string, mixed>>
     */
    private function teil(array $faecher, string $teil): array
    {
        return array_values(array_filter($faecher, static fn (array $f): bool => $f['teil'] === $teil));
    }

    /**
     * @param list<array<string, mixed>> $faecher
     */
    private function enthaeltCode(array $faecher, string $code): bool
    {
        foreach ($faecher as $fach) {
            if ($fach['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $faecher
     */
    private function zaehle(array $faecher, string $merkmal): int
    {
        return count(array_filter($faecher, static fn (array $f): bool => $f[$merkmal] === true));
    }
}
