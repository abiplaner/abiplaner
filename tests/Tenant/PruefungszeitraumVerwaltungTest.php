<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die vier Super-Admin-Seiten zur Prüfungszeitraum-Verwaltung müssen antworten.
 *
 * Am 30.08.2026 taten zwei davon das nicht: `admin/exam-periods/create` und
 * `admin/exam-periods/{id}` verlinkten auf den Routennamen
 * `exam-periods.index`, den es nie gab. Beim Rendern warf das eine
 * RouteNotFoundException — die Seiten antworteten mit 500, ohne dass irgendwo
 * ein Hinweis darauf stand.
 *
 * Der Test ruft die Seiten wirklich ab. RoutennamenInMaskenTest deckt die
 * gleiche Fehlerklasse flaechendeckend ab, aber nur statisch; hier zaehlt der
 * Statuscode.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter PruefungszeitraumVerwaltung
 */
class PruefungszeitraumVerwaltungTest extends TenantTestCase
{
    private const ADMIN_EMAIL = 'zeitraum-sa-9999@test.invalid';

    private const PERIODEN_NAME = 'Testzeitraum Verwaltung';

    protected School $school;

    protected User $superAdmin;

    protected ExamPeriod $zeitraum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raeumeAuf();

        $this->school = $this->createTestSchool();

        $this->superAdmin = User::factory()->create([
            'email' => self::ADMIN_EMAIL,
            'role' => 'super_admin',
            'school_id' => null,
        ]);

        $this->zeitraum = ExamPeriod::create([
            'name' => self::PERIODEN_NAME,
            'year' => 2099,
            'week_1_start' => '2099-05-04',
            'week_1_end' => '2099-05-08',
            'week_2_start' => '2099-05-11',
            'week_2_end' => '2099-05-15',
        ]);
    }

    protected function tearDown(): void
    {

        $this->raeumeAuf();

        parent::tearDown();
    }

    #[Test]
    public function die_uebersicht_antwortet(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.exam-periods.index'))
            ->assertOk();
    }

    #[Test]
    public function die_maske_zum_anlegen_antwortet(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.exam-periods.create'))
            ->assertOk();
    }

    /**
     * Die Detailseite ist entfallen. Sie lud Mandanten-Beziehungen auf einer
     * zentralen Route und konnte deshalb nie antworten — der Test haelt fest,
     * dass sie nicht durch die Hintertuer zurueckkommt.
     */
    #[Test]
    public function es_gibt_keine_detailseite_mehr(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.exam-periods.show'));
    }

    #[Test]
    public function die_maske_zum_bearbeiten_antwortet(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.exam-periods.edit', $this->zeitraum))
            ->assertOk();
    }

    /**
     * Gegenprobe: Ein Planer hat auf dem Super-Admin-Bereich nichts verloren.
     * Ohne sie waeren die Tests darueber auch dann gruen, wenn die
     * Rollenpruefung fehlte.
     */
    #[Test]
    public function ein_planer_kommt_nicht_in_den_super_admin_bereich(): void
    {
        $planer = User::factory()->create([
            'email' => 'zeitraum-planer-9999@test.invalid',
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

        try {
            $this->actingAs($planer)
                ->get(route('admin.exam-periods.index'))
                ->assertForbidden();
        } finally {
            $planer->delete();
        }
    }

    private function raeumeAuf(): void
    {
        User::whereIn('email', [self::ADMIN_EMAIL, 'zeitraum-planer-9999@test.invalid'])->delete();
        ExamPeriod::where('name', self::PERIODEN_NAME)->delete();
    }
}
