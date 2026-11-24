<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Schülerliste zeigt eine Jahrgangsstufe, nicht Q12 und Q13 gemischt.
 *
 * Vorgabe ist die Q13 (der Abiturjahrgang), ?stufe=12 zeigt die Q12.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter SchuelerlisteStufen
 */
class SchuelerlisteStufenTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'schuelerliste-stufen-9999@test.invalid';

    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();

        $schule = $this->createTestSchool();
        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $schule->school_number,
        ]);

        $zeitraum = ExamPeriod::query()->orderByDesc('year')->firstOrFail();
        foreach (['13' => 'Dreizehner', '12' => 'Zwoelfer', '11' => 'Elfer'] as $stufe => $name) {
            Student::create([
                'exam_period_id' => $zeitraum->id,
                'grade' => (string) $stufe,
                'external_id' => 'stufe-' . $stufe,
                'firstname' => 'Test',
                'lastname' => $name,
            ]);
        }
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    #[Test]
    public function ohne_auswahl_zeigt_die_liste_nur_die_q13(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.index'));

        $antwort->assertOk();
        $antwort->assertSee('Dreizehner');
        $antwort->assertDontSee('Zwoelfer');
        $antwort->assertDontSee('Elfer');
        $antwort->assertSee(route('students.index', ['stufe' => '12']), false);
    }

    #[Test]
    public function mit_stufe_12_zeigt_die_liste_nur_die_q12(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.index', ['stufe' => '12']));

        $antwort->assertOk();
        $antwort->assertSee('Zwoelfer');
        $antwort->assertDontSee('Dreizehner');
        $antwort->assertDontSee('Elfer');
        // Die Exporte folgen der Auswahl.
        $antwort->assertSee(route('students.export.pdf', ['stufe' => '12']), false);
    }

    #[Test]
    public function eine_unbekannte_stufe_faellt_auf_die_q13_zurueck(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.index', ['stufe' => '11']));

        $antwort->assertOk();
        $antwort->assertSee('Dreizehner');
        $antwort->assertDontSee('Elfer');
    }

    #[Test]
    public function der_excel_export_enthaelt_nur_die_gewaehlte_stufe(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.export.excel', ['stufe' => '12']));

        $antwort->assertOk();
        $datei = $antwort->baseResponse->getFile()->getPathname();
        $blatt = \PhpOffice\PhpSpreadsheet\IOFactory::load($datei)->getActiveSheet()->toArray();
        $text  = json_encode($blatt, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Zwoelfer', (string) $text);
        $this->assertStringNotContainsString('Dreizehner', (string) $text);
        $this->assertStringNotContainsString('Elfer', (string) $text);
    }
}
