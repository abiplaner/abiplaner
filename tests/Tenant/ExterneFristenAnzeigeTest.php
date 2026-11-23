<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * A8/B5-Fristen auf der Übersicht externer Bewerber (§ 61 Abs. 1 S. 7 /
 * Abs. 2 S. 5), Entscheidung 57 (01.09.2026).
 *
 * Der Rechenweg selbst (Ableitung aus week_2_end, 31. Januar, "verstrichen")
 * steht mit eigenen Gegenproben in
 * tests/Unit/Services/ExterneFristenServiceTest.php. Hier geht es nur um die
 * Verdrahtung: kommt das berechnete Datum wirklich auf der echten Seite an.
 */
class ExterneFristenAnzeigeTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2094;
    private const TEST_USER_EMAIL = 'fristen-planner-9999@test.invalid';

    protected School $school;
    protected User $planner;
    protected ExamPeriod $zeitraum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $this->zeitraum = ExamPeriod::create([
            'name' => 'Testperiode Fristen (' . self::TEST_PERIOD_YEAR . ')',
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

    #[Test]
    public function die_uebersicht_zeigt_a8_und_b5_fuer_das_gewaehlte_jahr(): void
    {
        $response = $this->actingAs($this->planner)
            ->get(route('external-applicants.index', ['year' => self::TEST_PERIOD_YEAR]));

        $response->assertOk();

        // A8 = week_2_end + 1 Tag = 16.05.
        $response->assertSee('16.05.' . self::TEST_PERIOD_YEAR, false);
        // B5 = 31. Januar desselben Jahrgangs
        $response->assertSee('31.01.' . self::TEST_PERIOD_YEAR, false);
        $response->assertDontSee('verstrichen');
    }

    #[Test]
    public function ohne_zentrale_pruefungsperiode_bleibt_der_fristenblock_stumm(): void
    {
        // Ein Jahr, fuer das keine ExamPeriod existiert, kann trotzdem in der
        // Jahresliste auftauchen (Bewerber koennen aelter sein als ihre
        // Periode). Ohne week_2_end/year gibt es nichts abzuleiten.
        $ohnePeriode = self::TEST_PERIOD_YEAR + 1;

        $response = $this->actingAs($this->planner)
            ->get(route('external-applicants.index', ['year' => $ohnePeriode]));

        $response->assertOk();
        $response->assertDontSee('Antrag auf Zusatzprüfung');
        $response->assertDontSee('Fächertausch bis 31. Januar');
    }
}
