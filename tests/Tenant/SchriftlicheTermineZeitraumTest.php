<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WrittenExamYearDate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die schriftlichen Prüfungstermine zeigen nur Kurse ihres Abiturjahrgangs.
 *
 * Am 24.11.2026 standen in der Terminübersicht des Abiturs 2027 auch die
 * Kurse des Abiturs 2026 (Codes „2…“, die wie Q12-Kurse aussehen): Der
 * Controller lud Course::all(), ohne nach dem Prüfungszeitraum zu filtern.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter SchriftlicheTermineZeitraum
 */
class SchriftlicheTermineZeitraumTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'termine-zeitraum-9999@test.invalid';

    protected User $planner;

    protected WrittenExamYearDate $termin;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();

        $school = $this->createTestSchool();
        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $school->school_number,
        ]);

        // Ein Einzelfachtermin: dann ist das Fach der Kurse eindeutig.
        $termin = WrittenExamYearDate::with('slot.subjects')->orderBy('id')->get()
            ->first(fn (WrittenExamYearDate $d): bool => $d->slot->subjects->count() === 1);
        $this->assertNotNull($termin, 'Die Stammdaten brauchen einen Einzelfachtermin.');
        $this->termin = $termin;
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    #[Test]
    public function der_termin_zeigt_nur_kurse_seines_abiturjahrgangs(): void
    {
        $jahr = (int) $this->termin->exam_year;

        $eigener = $this->zeitraum($jahr);
        $alter   = $this->zeitraum($jahr - 1);

        $this->kurs($eigener, 'zt-eigen');
        $this->kurs($alter, 'zt-altjahr');

        $antwort = $this->actingAs($this->planner)
            ->get(route('written-exams.dates.show', $this->termin));

        $antwort->assertOk();
        $antwort->assertSee('zt-eigen');
        $antwort->assertDontSee('zt-altjahr');
    }

    private function zeitraum(int $jahr): ExamPeriod
    {
        return ExamPeriod::firstOrCreate(['year' => $jahr], [
            'name' => 'Abitur ' . $jahr,
            'week_1_start' => $jahr . '-05-18',
            'week_1_end' => $jahr . '-05-22',
            'week_2_start' => $jahr . '-06-08',
            'week_2_end' => $jahr . '-06-12',
        ]);
    }

    private function kurs(ExamPeriod $zeitraum, string $code): Course
    {
        $lehrkraft = Teacher::create([
            'shortcode' => strtoupper(substr(md5($code), 0, 4)),
            'firstname' => 'Test',
            'lastname' => 'Zeitraum',
        ]);

        return Course::create([
            'exam_period_id' => $zeitraum->id,
            'subject_id' => $this->termin->slot->subjects->first()->id,
            'course_code' => $code,
            'teacher_id' => $lehrkraft->id,
            'level' => $this->termin->slot->requirement_level,
        ]);
    }
}
