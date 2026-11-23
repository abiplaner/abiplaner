<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\School;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\User;
use App\Models\ZusatzpruefExamScore;
use App\Services\ExterneAbiturDataService;
use PHPUnit\Framework\Attributes\Test;

/**
 * § 62 Abs. 1 S. 4 (Regel E6) — der Abbruch nach dem ersten Prüfungsteil.
 *
 * Gerechnet wurde er längst: `ExterneGesamtqualifikationService::berechne()`
 * liefert `abbruch => 'nach_erstem_teil'`, und das Mindestpunkte-Modal wie das
 * PDF zeigen es an. **Was fehlte, war die Folge für die Planung** — die vier
 * mündlichen Prüfungen standen weiter im Raster, zählten im Soll und in der
 * Konfliktliste mit, als wäre nichts.
 *
 * Seit dem 31.08.2026 meldet die Kolloquiumsplanung den Fall. Gemeldet, nicht
 * entschieden (Entscheidung Wilfrieds) — dieselbe Bauform wie § 61 Abs. 4 S. 2.
 *
 * Die beiden Gegenproben tragen den Test: Ein bestandener erster Teil und ein
 * **noch nicht entschiedener** dürfen nichts melden. Der zweite Fall ist der
 * gefährlichere — solange Noten fehlen, ist nichts abzubrechen, und eine
 * Meldung zur Unzeit wäre schlimmer als keine.
 *
 * Ausführen:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter AbbruchNachErstemTeil
 */
class AbbruchNachErstemTeilTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2093;
    private const TEST_USER_EMAIL = 'planner-abbruch-9999@test.invalid';

    /** Vier schriftliche Fächer; Mathematik ist immer eA (§ 61 Abs. 1 S. 5). */
    private const ERSTER_TEIL = ['Mathematik', 'Deutsch', 'Geschichte', 'Biologie'];

    private const ZWEITER_TEIL = ['Englisch', 'Geographie', 'Kunst', 'Musik'];

    protected School $school;
    protected User $planner;
    protected ExamPeriod $examPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::create([
            'name' => 'Testperiode Abbruch (' . self::TEST_PERIOD_YEAR . ')',
            'year' => self::TEST_PERIOD_YEAR,
            'week_1_start' => self::TEST_PERIOD_YEAR . '-05-04',
            'week_1_end' => self::TEST_PERIOD_YEAR . '-05-08',
            'week_2_start' => self::TEST_PERIOD_YEAR . '-05-11',
            'week_2_end' => self::TEST_PERIOD_YEAR . '-05-15',
        ]);

        $this->planner = User::factory()->create([
            'email' => self::TEST_USER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

    }

    protected function tearDown(): void
    {
        $this->cleanupCentralTestData();

        parent::tearDown();
    }

    private function cleanupCentralTestData(): void
    {
        User::where('email', self::TEST_USER_EMAIL)->delete();
        ExamPeriod::where('year', self::TEST_PERIOD_YEAR)->delete();
    }

    /**
     * Ein Bewerber mit allen acht Prüfungsfächern und den übergebenen Punkten.
     *
     * Die Fächerwahl muss hier **nicht** § 61 genügen — der Rechner nach § 62
     * prüft die Wahl nicht, er verlangt nur die acht Positionen. Geprüft wird
     * die Regel, nicht die Fächerwahl.
     *
     * @param  list<int|null>  $punkte  acht Werte, Positionen 1–8; null = noch offen
     */
    private function bewerber(string $nachname, array $punkte): ExternalApplicant
    {
        $this->assertCount(8, $punkte);

        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => $nachname]);

        $faecher = [
            ['part1_written', 'written', self::ERSTER_TEIL],
            ['part2_oral', 'oral', self::ZWEITER_TEIL],
        ];

        $position = 0;

        foreach ($faecher as [$teil, $typ, $namen]) {
            foreach ($namen as $nummer => $name) {
                $fachId = Subject::where('name', $name)->value('id');
                $this->assertNotNull($fachId, "Fach {$name} fehlt im Katalog.");

                $pruefungsfach = ExternalExamSubject::create([
                    'external_applicant_id' => $bewerber->id,
                    'subject_id' => $fachId,
                    'exam_part' => $teil,
                    'subject_number' => $nummer + 1,
                ]);

                $pruefung = StudentExam::create([
                    'external_exam_subject_id' => $pruefungsfach->id,
                    'exam_type' => $typ,
                ]);

                if ($punkte[$position] !== null) {
                    ZusatzpruefExamScore::create([
                        'student_exam_id' => $pruefung->id,
                        'exam_period_id' => $this->examPeriod->id,
                        'score' => $punkte[$position],
                    ]);
                }

                $position++;
            }
        }

        return $bewerber;
    }

    /** @return list<string> */
    private function gruende(): array
    {
        return app(ExterneAbiturDataService::class)->abbruchgruendeErsterTeil($this->examPeriod);
    }

    // ── Der Befund ─────────────────────────────────────────────────────────

    #[Test]
    public function ein_nicht_bestandener_erster_teil_wird_gemeldet(): void
    {
        // 4 × 3 Punkte × Faktor 11 = 132 — die 220 sind nicht erreicht.
        $this->bewerber('Zuwenig', [3, 3, 3, 3, null, null, null, null]);

        $gruende = $this->gruende();

        $this->assertCount(1, $gruende);
        $this->assertStringContainsString('Zuwenig', $gruende[0]);
        $this->assertStringContainsString('§ 62 Abs. 1 S. 4', $gruende[0]);
    }

    /**
     * Auch die Null im ersten Teil trägt — sie verletzt § 62 Abs. 1 S. 3 Nr. 1
     * unabhängig von der Punktsumme.
     */
    #[Test]
    public function eine_null_im_ersten_teil_wird_gemeldet(): void
    {
        $this->bewerber('Nullfach', [15, 15, 15, 0, null, null, null, null]);

        $this->assertCount(1, $this->gruende());
    }

    // ── Die Gegenproben ────────────────────────────────────────────────────

    #[Test]
    public function ein_bestandener_erster_teil_wird_nicht_gemeldet(): void
    {
        // 4 × 15 × 11 = 660, kein Fach mit 0, Mathematik als eA mit ≥ 5.
        $this->bewerber('Bestanden', [15, 15, 15, 15, null, null, null, null]);

        $this->assertSame([], $this->gruende());
    }

    /**
     * Die wichtigere Gegenprobe: Solange Noten fehlen, ist der erste Teil
     * **nicht entschieden**. Eine Meldung zur Unzeit wäre schlimmer als keine —
     * sie stünde ab dem Tag in der Konfliktliste, an dem der Bewerber angelegt
     * wird.
     */
    #[Test]
    public function ein_offener_erster_teil_wird_nicht_gemeldet(): void
    {
        $this->bewerber('Offen', [3, 3, 3, null, null, null, null, null]);

        $this->assertSame([], $this->gruende());
    }

    /**
     * Und eine unvollständige Fächerwahl darf den Lauf nicht umwerfen:
     * `berechne()` wirft auf allem, was nicht acht Positionen belegt.
     */
    #[Test]
    public function eine_unvollstaendige_faecherwahl_wirft_nicht(): void
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => 'Unvollstaendig']);

        ExternalExamSubject::create([
            'external_applicant_id' => $bewerber->id,
            'subject_id' => Subject::where('name', 'Mathematik')->value('id'),
            'exam_part' => 'part1_written',
            'subject_number' => 1,
        ]);

        $this->assertSame([], $this->gruende());
    }

    // ── Der Weg, den der Koordinator geht ──────────────────────────────────

    /**
     * Die Meldung muss in der Konfliktliste der Planungsmaske ankommen — dort
     * sieht der Koordinator nach, nicht im Mindestpunkte-Modal eines einzelnen
     * Bewerbers.
     */
    #[Test]
    public function die_planungsmaske_zeigt_die_meldung(): void
    {
        $this->bewerber('Sichtbar', [3, 3, 3, 3, null, null, null, null]);

        $this->actingAs($this->planner)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertSee('§ 62 Abs. 1 S. 4', false);
    }
}
