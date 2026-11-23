<?php

namespace Tests\Unit\Services;

use App\Services\ExternalApplicantValidationService;
use PHPUnit\Framework\TestCase;

/**
 * Fächerwahl externer Bewerber — Regelprüfung nach § 61 BayGSO.
 * Reine Logiktests ohne Datenbank; geprüft wird der Regelkern pruefeWahl().
 *
 * Die Regel-IDs verweisen auf docs/externe-bewerber/REGELWERK-61-62.md. Wird
 * dort bei der Abnahme eine Regel korrigiert, ändert sich hier genau ein Fall.
 *
 * Ausführen: docker compose exec abiplaner_app php vendor/bin/phpunit --filter ExternalApplicantValidation
 */
class ExternalApplicantValidationServiceTest extends TestCase
{
    private ExternalApplicantValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExternalApplicantValidationService();
    }

    /** Ausschnitt des Fächerkatalogs, so wie ihn die zentrale Tabelle führt. */
    private const KATALOG = [
        'M' => ['name' => 'Mathematik', 'aufgabenfeld' => 'mint'],
        'D' => ['name' => 'Deutsch', 'aufgabenfeld' => 'sprachlich_literarisch'],
        'E' => ['name' => 'Englisch', 'aufgabenfeld' => 'sprachlich_literarisch', 'fremdsprache' => true],
        'F' => ['name' => 'Französisch', 'aufgabenfeld' => 'sprachlich_literarisch', 'fremdsprache' => true],
        'L' => ['name' => 'Latein', 'aufgabenfeld' => 'sprachlich_literarisch', 'fremdsprache' => true],
        'G' => ['name' => 'Geschichte', 'aufgabenfeld' => 'gesellschaftswissenschaftlich'],
        'Geo' => ['name' => 'Geographie', 'aufgabenfeld' => 'gesellschaftswissenschaftlich'],
        'B' => ['name' => 'Biologie', 'aufgabenfeld' => 'mint', 'naturwissenschaft' => true],
        'Ph' => ['name' => 'Physik', 'aufgabenfeld' => 'mint', 'naturwissenschaft' => true],
        'Ku' => ['name' => 'Kunst', 'aufgabenfeld' => 'sprachlich_literarisch'],
        'Psy' => ['name' => 'Psychologie', 'aufgabenfeld' => 'gesellschaftswissenschaftlich', 'abiturfach' => false],
    ];

    /**
     * Baut eine Fächerwahl aus Kürzeln in der Reihenfolge der Positionen 1–8.
     *
     * `$extras` deckt die drei Angaben ab, die nicht am einzelnen Fach hängen:
     *   'zweite_fremdsprache' ?string Kürzel; ohne Angabe die **letzte**
     *                                 Fremdsprache der Liste (§ 61 Abs. 3 S. 7
     *                                 verlangt seit Entscheidung 32, dass sie
     *                                 benannt ist)
     *   'leistungsfach'       ?string Kürzel des Leistungsfachs
     *   'eingebracht'         list<int> Positionen, die nach § 64 Abs. 2
     *                                 eingebracht statt geprüft werden
     *
     * @param list<string> $codes
     * @param list<int> $eaPositionen
     * @param array<string, mixed> $extras
     * @return list<array<string, mixed>>
     */
    private function wahl(array $codes, array $eaPositionen = [1, 2, 3], array $extras = []): array
    {
        $fremdsprachen = array_values(array_filter(
            $codes,
            static fn (string $code): bool => (bool) (self::KATALOG[$code]['fremdsprache'] ?? false)
        ));

        $zweiteFremdsprache = array_key_exists('zweite_fremdsprache', $extras)
            ? $extras['zweite_fremdsprache']
            : ($fremdsprachen === [] ? null : end($fremdsprachen));

        $leistungsfach = $extras['leistungsfach'] ?? null;
        /** @var list<int> $eingebracht */
        $eingebracht = $extras['eingebracht'] ?? [];

        $faecher = [];

        foreach ($codes as $index => $code) {
            $position = $index + 1;
            $eintrag = self::KATALOG[$code];

            $faecher[] = [
                'position' => $position,
                'teil' => $position <= 4 ? 'part1_written' : 'part2_oral',
                'nummer' => $position <= 4 ? $position : $position - 4,
                'ea' => in_array($position, $eaPositionen, true),
                'zweite_fremdsprache' => $code === $zweiteFremdsprache,
                'eingebracht' => in_array($position, $eingebracht, true),
                'leistungsfach' => $code === $leistungsfach,
                'code' => $code,
                'name' => $eintrag['name'],
                'aufgabenfeld' => $eintrag['aufgabenfeld'],
                'fremdsprache' => $eintrag['fremdsprache'] ?? false,
                'naturwissenschaft' => $eintrag['naturwissenschaft'] ?? false,
                'abiturfach' => $eintrag['abiturfach'] ?? true,
            ];
        }

        return $faecher;
    }

    /**
     * Eine nach § 61 und Anlage 14 einwandfreie Wahl.
     *
     * Erster Teil: Mathematik (eA), Deutsch (eA), Englisch als Leistungsfach
     * (eA), Geschichte (gA). Deckt mint, SLK und GPR ab.
     * Zweiter Teil: Biologie, Französisch, Geographie, Physik — alle gA.
     *
     * @return list<array<string, mixed>>
     */
    private function gueltigeWahl(): array
    {
        return $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph']);
    }

    /**
     * @param array<string, mixed> $ergebnis
     * @return list<string>
     */
    private function ids(array $ergebnis): array
    {
        return array_values(array_unique(array_column($ergebnis['verstoesse'], 'id')));
    }

    public function test_eine_regelkonforme_wahl_hat_keine_verstoesse(): void
    {
        $ergebnis = $this->service->pruefeWahl($this->gueltigeWahl());

        $this->assertTrue($ergebnis['valid'], implode(' | ', $ergebnis['errors']));
        $this->assertSame([], $ergebnis['errors']);
    }

    public function test_jede_meldung_nennt_die_norm(): void
    {
        // Die Meldung soll dem Oberstufenkoordinator sagen, woraus die Regel
        // folgt — sonst ist eine Ablehnung nicht nachvollziehbar.
        $ergebnis = $this->service->pruefeWahl($this->wahl(['D', 'M', 'E', 'G', 'B', 'F', 'Geo', 'Ph']));

        $this->assertNotSame([], $ergebnis['errors']);

        foreach ($ergebnis['errors'] as $meldung) {
            $this->assertMatchesRegularExpression('/^(§|Anlage) /', $meldung);
        }
    }

    // ── A1, A3: Umfang ──────────────────────────────────────────────────────

    public function test_a1_es_muessen_acht_pruefungsfaecher_sein(): void
    {
        $ergebnis = $this->service->pruefeWahl($this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo']));

        $this->assertContains('A1', $this->ids($ergebnis));
    }

    public function test_a3_vier_faecher_je_pruefungsteil(): void
    {
        $wahl = $this->gueltigeWahl();
        // Fach 5 in den ersten Teil schieben: 5 schriftlich, 3 mündlich.
        $wahl[4]['teil'] = 'part1_written';

        $ergebnis = $this->service->pruefeWahl($wahl);

        $this->assertContains('A3', $this->ids($ergebnis));
    }

    public function test_bei_falschem_umfang_werden_keine_folgefehler_gemeldet(): void
    {
        // Sonst meldet eine unvollständige Wahl ein Dutzend Regeln auf einmal
        // und die eigentliche Ursache geht unter.
        $ergebnis = $this->service->pruefeWahl($this->wahl(['M', 'D', 'E']));

        $this->assertSame(['A1', 'A3'], $this->ids($ergebnis));
    }

    // ── A5: Fächerkatalog ───────────────────────────────────────────────────

    public function test_a5_nur_abiturpruefungsfaecher_sind_waehlbar(): void
    {
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Psy', 'Ph'])
        );

        $this->assertContains('A5', $this->ids($ergebnis));
        $this->assertStringContainsString('Psychologie ist kein Abiturprüfungsfach',
            implode(' ', $ergebnis['errors']));
    }

    public function test_a5_ein_fach_ausserhalb_des_katalogs_wird_gemeldet(): void
    {
        $wahl = $this->gueltigeWahl();
        $wahl[7]['code'] = null;
        $wahl[7]['name'] = 'Sozialkunde';

        $ergebnis = $this->service->pruefeWahl($wahl);

        $this->assertContains('A5', $this->ids($ergebnis));
    }

    // ── A2: Pflichtfächer ───────────────────────────────────────────────────

    public function test_a2_geschichte_ist_pflicht(): void
    {
        // Geschichte durch Kunst ersetzt; das Aufgabenfeld II fällt damit im
        // ersten Teil weg, deshalb meldet B1 zusätzlich.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'Ku', 'B', 'F', 'Geo', 'Ph'])
        );

        $this->assertContains('A2', $this->ids($ergebnis));
        $this->assertStringContainsString('Pflichtfach Geschichte fehlt',
            implode(' ', $ergebnis['errors']));
    }

    public function test_a2_eine_naturwissenschaft_ist_pflicht(): void
    {
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'Ku', 'F', 'Geo', 'L'])
        );

        $this->assertContains('A2', $this->ids($ergebnis));
        $this->assertStringContainsString('Naturwissenschaft', implode(' ', $ergebnis['errors']));
    }

    public function test_a2_zwei_fremdsprachen_sind_pflicht(): void
    {
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'Geo', 'Ku', 'Ph'])
        );

        $this->assertContains('A2', $this->ids($ergebnis));
        $this->assertStringContainsString('zwei Fremdsprachen', implode(' ', $ergebnis['errors']));
    }

    // ── A6: genau drei Fächer auf erhöhtem Niveau ───────────────────────────

    public function test_a6_genau_drei_faecher_auf_erhoehtem_niveau(): void
    {
        $drei = $this->service->pruefeWahl($this->gueltigeWahl());
        $this->assertTrue($drei['valid']);

        $vier = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 2, 3, 5])
        );

        $this->assertContains('A6', $this->ids($vier));
    }

    public function test_a6_deutsch_und_mathematik_sind_immer_erhoeht(): void
    {
        // Nur Mathematik und Englisch auf eA, Deutsch ohne.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 3])
        );

        $this->assertContains('A6', $this->ids($ergebnis));
        $this->assertStringContainsString(
            'Deutsch wird immer auf erhöhtem Anforderungsniveau geprüft',
            implode(' ', $ergebnis['errors'])
        );
    }

    // ── B1, B3, B4, B6: erster Prüfungsteil ─────────────────────────────────

    public function test_b1_die_aufgabenfelder_zaehlen_nur_ueber_den_ersten_teil(): void
    {
        // Der erste Teil deckt nur SLK und mint ab; das gesellschafts-
        // wissenschaftliche Aufgabenfeld steckt im zweiten Teil (Geographie).
        // Genau diese Wahl galt bisher als gültig, weil über alle acht Fächer
        // geprüft wurde.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'B', 'G', 'F', 'Geo', 'Ph'])
        );

        $this->assertContains('B1', $this->ids($ergebnis));
        $this->assertStringContainsString('Gesellschaftswissenschaftlich',
            implode(' ', $ergebnis['errors']));
    }

    public function test_b3_zwei_der_ersten_drei_faecher_auf_erhoehtem_niveau(): void
    {
        // Nur Mathematik auf eA unter den Fächern 1–3; das dritte
        // Leistungsfach steht auf Position 5.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 5])
        );

        $this->assertContains('B3', $this->ids($ergebnis));
    }

    public function test_b3_mathematik_gehoert_unter_die_ersten_drei_faecher(): void
    {
        // Mathematik auf Position 4 — verletzt B3 und zugleich die Position
        // nach Anlage 14.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['D', 'E', 'G', 'M', 'B', 'F', 'Geo', 'Ph'], [1, 2, 4])
        );

        $this->assertContains('B3', $this->ids($ergebnis));
        $this->assertContains('E2', $this->ids($ergebnis));
    }

    public function test_b4_ohne_deutsch_im_ersten_teil_braucht_es_dort_eine_fremdsprache_als_leistungsfach(): void
    {
        // Deutsch steht im zweiten Teil, im ersten ist keine Fremdsprache auf
        // erhöhtem Niveau. A6 meldet zusätzlich, weil Deutsch trotzdem eA sein
        // muss — das ist richtig so, beide Regeln greifen unabhängig.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'G', 'B', 'Geo', 'D', 'E', 'F', 'Ph'], [1, 3, 5])
        );

        $this->assertContains('B4', $this->ids($ergebnis));
    }

    public function test_b6_das_vierte_fach_des_ersten_teils_ist_grundlegend(): void
    {
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'G', 'E', 'B', 'F', 'Geo', 'Ph'], [1, 2, 4])
        );

        $this->assertContains('B6', $this->ids($ergebnis));
    }

    // ── C1, C7: zweiter Prüfungsteil und Fremdsprachen ──────────────────────

    public function test_c1_kein_fach_zweimal(): void
    {
        // Bisher schlug das erst am Unique-Index mitten in der Transaktion als
        // 500er durch.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'B'])
        );

        $this->assertContains('C1', $this->ids($ergebnis));
        $this->assertStringContainsString('Biologie ist 2-mal gewählt',
            implode(' ', $ergebnis['errors']));
    }

    public function test_c7_nur_eine_fremdsprache_im_ersten_teil(): void
    {
        // Englisch und Französisch beide schriftlich — genau die Wahl, die im
        // Probeeintrag der Pilotschule stand und bisher nur eine Warnung war.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'F', 'B', 'G', 'Geo', 'Ph'])
        );

        $this->assertContains('C7', $this->ids($ergebnis));
    }

    public function test_c7_die_zweite_fremdsprache_darf_nicht_auf_erhoehtem_niveau_liegen(): void
    {
        // Englisch ist schriftliches Fach 3 auf grundlegendem Niveau, das
        // dritte Leistungsfach ist Französisch auf Position 5. Damit werden
        // beide Fremdsprachen wie fortgeführte behandelt — die zweite müsste
        // auf dem Niveau einer spät beginnenden geprüft werden. Alle übrigen
        // Regeln sind erfüllt, sodass hier wirklich nur C7 greift.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'F', 'B', 'Geo', 'Ph'], [1, 2, 5])
        );

        $this->assertSame(['C7'], $this->ids($ergebnis));
    }

    public function test_c7_greift_nicht_bei_einer_fortgefuehrten_und_einer_muendlichen_sprache(): void
    {
        $ergebnis = $this->service->pruefeWahl($this->gueltigeWahl());

        $this->assertNotContains('C7', $this->ids($ergebnis));
    }

    public function test_c7_die_zweite_fremdsprache_muss_benannt_sein(): void
    {
        // Entscheidung 32: welche der beiden die zweite ist, sagt der Wortlaut
        // nicht — also benennt sie der Prüfling. Ohne Angabe lässt sich die
        // Regel nicht anwenden, und das ist ein Verstoß, keine stille Lücke.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 2, 3],
                ['zweite_fremdsprache' => null])
        );

        $this->assertSame(['C7'], $this->ids($ergebnis));
        $this->assertStringContainsString('nicht festgelegt', implode(' ', $ergebnis['errors']));
    }

    public function test_c7_beschraenkt_die_benannte_sprache_und_nicht_die_andere(): void
    {
        // Englisch ist Leistungsfach im ersten Teil, Französisch ist als
        // zweite Fremdsprache benannt und steht mündlich auf gA. Genau so ist
        // es richtig — und die Rollen sind nicht vertauschbar: benennt man
        // Englisch, meldet dieselbe Wahl zwei Verstöße.
        $codes = ['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'];

        $richtig = $this->service->pruefeWahl(
            $this->wahl($codes, [1, 2, 3], ['zweite_fremdsprache' => 'F'])
        );
        $vertauscht = $this->service->pruefeWahl(
            $this->wahl($codes, [1, 2, 3], ['zweite_fremdsprache' => 'E'])
        );

        $this->assertTrue($richtig['valid'], implode(' | ', $richtig['errors']));
        $this->assertSame(['C7'], $this->ids($vertauscht));
        $this->assertCount(2, $vertauscht['errors']); // erster Teil und eA
    }

    // ── C7: die dritte Fremdsprache (Entscheidung 54, 31.08.2026) ───────────

    /**
     * § 61 Abs. 3 S. 7 beschränkt **die zweite** Fremdsprache. Über eine
     * dritte sagt der Wortlaut nichts — die Anwendung darf das weder
     * entscheiden noch verschweigen. Deshalb ein Hinweis, kein Verstoß.
     */
    public function test_c7_eine_dritte_fremdsprache_ist_ein_hinweis_und_kein_verstoss(): void
    {
        // Englisch (eA, erster Teil), Latein und Französisch mündlich auf gA;
        // Französisch ist als zweite Fremdsprache benannt.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'L', 'F', 'Geo', 'B'], [1, 2, 3],
                ['zweite_fremdsprache' => 'F'])
        );

        $this->assertTrue($ergebnis['valid'], implode(' | ', $ergebnis['errors']));
        $this->assertSame([], $ergebnis['errors'], 'Ein Hinweis ist kein Verstoß.');

        $this->assertCount(1, $ergebnis['hinweise']);
        $this->assertSame('C7', $ergebnis['hinweise'][0]['id']);
        $this->assertStringContainsString('3 Fremdsprachen', $ergebnis['hinweise'][0]['meldung']);
        $this->assertStringContainsString('Französisch', $ergebnis['hinweise'][0]['meldung']);

        // Der flache Kanal trägt dieselbe Meldung — er stand bis zum
        // 31.08.2026 in der Signatur und war immer leer.
        $this->assertCount(1, $ergebnis['warnings']);
    }

    /** Gegenprobe: zwei Fremdsprachen sind der Regelfall und ohne Hinweis. */
    public function test_zwei_fremdsprachen_erzeugen_keinen_hinweis(): void
    {
        $ergebnis = $this->service->pruefeWahl($this->gueltigeWahl());

        $this->assertTrue($ergebnis['valid']);
        $this->assertSame([], $ergebnis['hinweise']);
        $this->assertSame([], $ergebnis['warnings']);
    }

    /**
     * Zweite Gegenprobe: Neben offenen Verstößen wäre der Hinweis nur
     * Rauschen. Er erscheint erst, wenn die Wahl sonst stimmt.
     */
    public function test_neben_verstoessen_erscheint_kein_hinweis(): void
    {
        // Dieselben drei Fremdsprachen, aber ohne benannte zweite: C7 meldet
        // dann einen Verstoß, und der Hinweis tritt zurück.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'L', 'F', 'Geo', 'B'], [1, 2, 3],
                ['zweite_fremdsprache' => null])
        );

        $this->assertFalse($ergebnis['valid']);
        $this->assertSame([], $ergebnis['hinweise']);
    }

    // ── G5, G6: Ersetzung nach § 64 Abs. 2 (Ersatzschüler) ──────────────────

    public function test_g5_eine_gueltige_ersatzschueler_wahl_hat_keine_verstoesse(): void
    {
        // Zweiter Teil: Biologie (5), Französisch (6), Geographie (7),
        // Physik (8). Eingebracht werden 7 und 8 — beides keine Fremdsprache,
        // nicht Deutsch und nicht das Leistungsfach.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 2, 3],
                ['leistungsfach' => 'E', 'eingebracht' => [7, 8]]),
            istErsatzschueler: true,
        );

        $this->assertTrue($ergebnis['valid'], implode(' | ', $ergebnis['errors']));
    }

    public function test_g5_einbringen_kann_nur_ein_schueler_einer_ersatzschule(): void
    {
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 2, 3],
                ['leistungsfach' => 'E', 'eingebracht' => [7, 8]]),
            istErsatzschueler: false,
        );

        $this->assertSame(['G5'], $this->ids($ergebnis));
        $this->assertStringContainsString('keine Ersatzschule hinterlegt',
            implode(' ', $ergebnis['errors']));
    }

    public function test_g5_es_muessen_genau_zwei_faecher_sein(): void
    {
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 2, 3],
                ['leistungsfach' => 'E', 'eingebracht' => [7]]),
            istErsatzschueler: true,
        );

        $this->assertSame(['G5'], $this->ids($ergebnis));
        $this->assertStringContainsString('genau zwei', implode(' ', $ergebnis['errors']));
    }

    public function test_g5_eingebracht_werden_nur_die_faecher_sieben_und_acht(): void
    {
        // Anlage 15 führt die Punkte der Fächer 7 und 8 in der Spalte 13/II,
        // die der Fächer 5 und 6 in der Spalte Prüfung.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'E', 'G', 'B', 'F', 'Geo', 'Ph'], [1, 2, 3],
                ['leistungsfach' => 'E', 'eingebracht' => [5, 6]]),
            istErsatzschueler: true,
        );

        $ids = $this->ids($ergebnis);
        $this->assertContains('G5', $ids);
        $this->assertContains('G6', $ids); // Französisch auf Position 6
        $this->assertStringContainsString('Prüfungsfächer sieben und acht',
            implode(' ', $ergebnis['errors']));
    }

    public function test_g6_fremdsprache_leistungsfach_und_deutsch_bleiben_geprueft(): void
    {
        // Zweiter Teil: Deutsch (5, eA), Englisch (6), Französisch (7),
        // Physik (8) — Französisch soll eingebracht werden.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'G', 'B', 'Geo', 'D', 'E', 'F', 'Ph'], [1, 3, 5],
                ['leistungsfach' => 'B', 'zweite_fremdsprache' => 'F', 'eingebracht' => [7, 8]]),
            istErsatzschueler: true,
        );

        $this->assertContains('G6', $this->ids($ergebnis));
        $this->assertStringContainsString('Französisch gehört dazu',
            implode(' ', $ergebnis['errors']));
    }

    public function test_g6_meldet_eine_faecherwahl_die_keine_zwei_ersetzbaren_faecher_laesst(): void
    {
        // Entscheidung 33: die Maske meldet den Fall, statt die Ersetzung
        // stillschweigend nicht anzubieten. Im zweiten Teil stehen Deutsch,
        // beide Fremdsprachen und Physik — ersetzbar ist allein Physik.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'G', 'B', 'Geo', 'D', 'E', 'F', 'Ph'], [1, 3, 5],
                ['leistungsfach' => 'B', 'zweite_fremdsprache' => 'F', 'eingebracht' => [7, 8]]),
            istErsatzschueler: true,
        );

        $this->assertContains('G6', $this->ids($ergebnis));
        $this->assertStringContainsString('nur ein Fach', implode(' ', $ergebnis['errors']));
    }

    public function test_ohne_einbringung_wird_die_ersetzung_gar_nicht_geprueft(): void
    {
        // § 64 Abs. 2 S. 1 sagt "kann": ein Ersatzschüler, der nichts
        // einbringt, wird nach Anlage 14 bewertet wie jeder andere Bewerber.
        $ergebnis = $this->service->pruefeWahl($this->gueltigeWahl(), istErsatzschueler: true);

        $this->assertTrue($ergebnis['valid'], implode(' | ', $ergebnis['errors']));
    }

    // ── E2: Positionen nach Anlage 14 ───────────────────────────────────────

    public function test_e2_mathematik_ist_das_erste_schriftliche_fach(): void
    {
        // Die bisherige Maske fixierte Mathematik auf Fach 2 — genau das ist
        // der Fall, den Anlage 14 ausschließt.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['D', 'M', 'E', 'G', 'B', 'F', 'Geo', 'Ph'])
        );

        $this->assertContains('E2', $this->ids($ergebnis));
        $this->assertStringContainsString('Mathematik ist das erste schriftliche Prüfungsfach',
            implode(' ', $ergebnis['errors']));
    }

    public function test_e2_im_zweiten_teil_darf_nur_fach_fuenf_auf_erhoehtem_niveau_liegen(): void
    {
        // Das dritte Leistungsfach auf Position 7 statt 5.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'G', 'B', 'E', 'F', 'Geo', 'Ph'], [1, 2, 7])
        );

        $this->assertContains('E2', $this->ids($ergebnis));
    }

    public function test_e2_das_dritte_leistungsfach_darf_auf_position_fuenf_liegen(): void
    {
        // Erster Teil: Mathematik und Deutsch auf eA, Englisch als
        // Leistungsfach im Kolloquium — nach Anlage 14 zulässig.
        $ergebnis = $this->service->pruefeWahl(
            $this->wahl(['M', 'D', 'G', 'B', 'E', 'F', 'Geo', 'Ph'], [1, 2, 5])
        );

        $this->assertNotContains('E2', $this->ids($ergebnis));
        $this->assertTrue($ergebnis['valid'], implode(' | ', $ergebnis['errors']));
    }

    public function test_e2_verweist_bei_einer_einbringung_auf_anlage_15(): void
    {
        // Dieselbe Regel, andere Fundstelle: für einen Ersatzschüler, der
        // einbringt, gilt Anlage 15. Eine Meldung, die auf Anlage 14
        // verweist, schickt den Koordinator in die falsche Tabelle.
        $codes = ['D', 'M', 'E', 'G', 'B', 'F', 'Geo', 'Ph']; // Mathematik auf Position 2

        $anlage14 = $this->service->pruefeWahl($this->wahl($codes));
        $anlage15 = $this->service->pruefeWahl(
            $this->wahl($codes, [1, 2, 3], ['leistungsfach' => 'E', 'eingebracht' => [7, 8]]),
            istErsatzschueler: true,
        );

        $normen = static fn (array $e): array => array_column(
            array_filter($e['verstoesse'], static fn (array $v): bool => $v['id'] === 'E2'),
            'norm'
        );

        $this->assertSame(['Anlage 14'], array_values(array_unique($normen($anlage14))));
        $this->assertSame(['Anlage 15'], array_values(array_unique($normen($anlage15))));
    }
}
