<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\User;
use App\Services\CurrentExamPeriodService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Perioden-Umschalter + Schreibschutz für Archiv-Perioden (Modul 1.1).
 *
 * Zwei zentrale Testperioden mit fiktiven Jahren weit in der Zukunft
 * (2097 = Alt-Periode, 2098 = neueste): so bleibt die neueste Periode
 * deterministisch die 2098er, unabhängig von echten Perioden in der DB.
 */
class PeriodSwitchTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR_OLD = 2097;
    private const TEST_PERIOD_YEAR_NEW = 2098;
    private const TEST_USER_EMAIL = 'switch-planner-9999@test.invalid';

    protected School $school;
    protected User $planner;
    protected ExamPeriod $oldPeriod;
    protected ExamPeriod $newPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $this->oldPeriod = $this->createExamPeriod(self::TEST_PERIOD_YEAR_OLD);
        $this->newPeriod = $this->createExamPeriod(self::TEST_PERIOD_YEAR_NEW);

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
        ExamPeriod::whereIn('year', [
            self::TEST_PERIOD_YEAR_OLD,
            self::TEST_PERIOD_YEAR_NEW,
        ])->delete();
    }

    private function createExamPeriod(int $year): ExamPeriod
    {
        return ExamPeriod::create([
            'name' => "Testperiode 9999 ({$year})",
            'year' => $year,
            'week_1_start' => "{$year}-05-04",
            'week_1_end' => "{$year}-05-08",
            'week_2_start' => "{$year}-05-11",
            'week_2_end' => "{$year}-05-15",
        ]);
    }

    // ------------------------------------------------------------------
    // Umschalter
    // ------------------------------------------------------------------

    #[Test]
    public function umschalten_auf_alt_periode_aktiviert_archivansicht(): void
    {
        $response = $this->actingAs($this->planner)
            ->post(route('exam-periods.switch'), [
                'exam_period_id' => $this->oldPeriod->id,
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas(
            CurrentExamPeriodService::SESSION_KEY,
            $this->oldPeriod->id
        );

        // Banner in der Archivansicht
        $dashboard = $this->actingAs($this->planner)->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Archivansicht Abitur ' . self::TEST_PERIOD_YEAR_OLD);
    }

    #[Test]
    public function reset_kehrt_zur_neuesten_periode_zurueck(): void
    {
        $response = $this->actingAs($this->planner)
            ->withSession([CurrentExamPeriodService::SESSION_KEY => $this->oldPeriod->id])
            ->post(route('exam-periods.switch-reset'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing(CurrentExamPeriodService::SESSION_KEY);

        $dashboard = $this->actingAs($this->planner)->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertDontSee('Archivansicht Abitur');
    }

    // ------------------------------------------------------------------
    // Schreibschutz
    // ------------------------------------------------------------------

    #[Test]
    public function archivansicht_blockiert_schreibende_requests(): void
    {
        $response = $this->actingAs($this->planner)
            ->withSession([CurrentExamPeriodService::SESSION_KEY => $this->oldPeriod->id])
            ->from(route('external-applicants.index'))
            ->post(route('external-applicants.store'), [
                'first_name' => 'Archiv',
                'last_name' => 'Blockiert',
                'email' => 'archiv@example.com',
                'birth_date' => '2005-01-15',
                'exam_period_id' => $this->oldPeriod->id,
            ]);

        $response->assertRedirect(route('external-applicants.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('external_applicants', [
            'last_name' => 'Blockiert',
        ]);
    }

    #[Test]
    public function archivansicht_blockiert_json_requests_mit_403(): void
    {
        $this->actingAs($this->planner)
            ->withSession([CurrentExamPeriodService::SESSION_KEY => $this->oldPeriod->id])
            ->postJson(route('external-applicants.store'), [
                'first_name' => 'Archiv',
                'last_name' => 'Blockiert',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function url_mit_alt_periode_wird_auch_ohne_session_override_blockiert(): void
    {
        // Kein Session-Override (Nutzer ist "in" der aktuellen Periode),
        // aber die URL zielt auf die Alt-Periode: muss trotzdem blockieren.
        $response = $this->actingAs($this->planner)
            ->postJson(route('planning.time-slots.store', $this->oldPeriod), []);

        $response->assertStatus(403);
        // Eindeutig UNSERE Middleware, nicht die ExamPeriodPolicy
        $this->assertStringContainsString('Archivansicht', (string) $response->json('message'));
    }

    #[Test]
    public function url_mit_aktueller_periode_passiert_die_middleware(): void
    {
        // Darf nicht am Archiv-Schreibschutz scheitern — Policy- oder
        // Validierungsfehler aus dem Controller sind hier nicht Gegenstand.
        $response = $this->actingAs($this->planner)
            ->postJson(route('planning.time-slots.store', $this->newPeriod), []);

        $this->assertStringNotContainsString('Archivansicht', (string) $response->getContent());
    }

    #[Test]
    public function aktuelle_periode_bleibt_beschreibbar(): void
    {
        $response = $this->actingAs($this->planner)
            ->post(route('external-applicants.store'), [
                'first_name' => 'Aktuell',
                'last_name' => 'Erlaubt',
                'email' => 'aktuell@example.com',
                'birth_date' => '2005-01-15',
                'exam_period_id' => $this->newPeriod->id,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('external_applicants', [
            'last_name' => 'Erlaubt',
        ]);
    }
}
