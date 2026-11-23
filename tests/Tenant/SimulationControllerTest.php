<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Modul 4.6 – HTTP-Ebene der Optimierungssimulation (Phase 4).
 *
 * Prüft die beiden Endpunkte planning.simulation.index/run mit eingeloggtem
 * Planner: Formular lädt, Suche liefert Vorschläge, Bandbreiten werden als
 * SchoolSetting gemerkt.
 */
class SimulationControllerTest extends TenantTestCase
{
    private const TEST_USER_EMAIL = 'sim-planner-9999@example.test';
    private const TEST_PERIOD_YEAR = 2099;

    protected \App\Models\School $school;
    protected User $planner;
    protected ExamPeriod $examPeriod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $year = self::TEST_PERIOD_YEAR;
        $this->examPeriod = ExamPeriod::create([
            'name'         => "Sim-Testperiode ({$year})",
            'year'         => $year,
            'week_1_start' => "{$year}-05-04",
            'week_1_end'   => "{$year}-05-08",
            'week_2_start' => "{$year}-05-11",
            'week_2_end'   => "{$year}-05-15",
        ]);

        $this->planner = User::factory()->create([
            'email'     => self::TEST_USER_EMAIL,
            'role'      => 'planner',
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

    private function makeOralExam(Teacher $examiner, Teacher $assessor, int $i): void
    {
        $course = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'simc' . $i,
            'teacher_id'     => $examiner->id,
            'level'          => 'grundlegend',
        ]);
        $student = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id'    => 'SIMHTTP-' . $i,
            'grade'          => '13',
            'firstname'      => 'Sim',
            'lastname'       => 'HTTP' . $i,
        ]);
        StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $examiner->id,
            'assessor_id' => $assessor->id,
            'exam_type'   => 'oral',
        ]);
    }

    #[Test]
    public function index_zeigt_das_formular_mit_default_bandbreiten(): void
    {
        $response = $this->actingAs($this->planner)
            ->get(route('planning.simulation.index'));

        $response->assertOk();
        $response->assertViewIs('planning.simulation.index');
        $response->assertSee('Optimierungssimulation');
        // Feste weite Startwerte (Konzept Kap. 4a).
        $response->assertViewHas('ranges', function ($ranges) {
            return $ranges['days'] === [1, 15]
                && $ranges['slots_per_day'] === [1, 8]
                && $ranges['rooms'] === [1, 20];
        });
    }

    #[Test]
    public function run_liefert_vorschlaege_und_merkt_bandbreiten(): void
    {
        $a = Teacher::create(['shortcode' => 'HTA', 'firstname' => 'Ada', 'lastname' => 'HT']);
        $b = Teacher::create(['shortcode' => 'HTB', 'firstname' => 'Bo', 'lastname' => 'HT']);
        for ($i = 0; $i < 4; $i++) {
            $this->makeOralExam($i % 2 === 0 ? $a : $b, $i % 2 === 0 ? $b : $a, $i);
        }

        $response = $this->actingAs($this->planner)
            ->post(route('planning.simulation.run'), [
                'days_min'  => 1, 'days_max'  => 4,
                'slots_min' => 1, 'slots_max' => 4,
                'rooms_min' => 1, 'rooms_max' => 4,
            ]);

        $response->assertOk();
        $response->assertViewHas('result', function ($result) {
            return is_array($result)
                && $result['feasible_count'] > 0
                && !empty($result['suggestions']);
        });

        // Bandbreiten wurden als SchoolSetting gemerkt.
        $this->assertSame('4', \App\Models\SchoolSetting::get('simulation.days.max'));
        $this->assertSame('1', \App\Models\SchoolSetting::get('simulation.days.min'));
    }

    #[Test]
    public function run_validiert_min_kleiner_gleich_max(): void
    {
        $response = $this->actingAs($this->planner)
            ->post(route('planning.simulation.run'), [
                'days_min'  => 5, 'days_max'  => 2, // ungültig
                'slots_min' => 1, 'slots_max' => 4,
                'rooms_min' => 1, 'rooms_max' => 4,
            ]);

        $response->assertSessionHasErrors('days_max');
    }

    #[Test]
    public function gast_wird_zum_login_umgeleitet(): void
    {
        // Das role-Enum kennt nur super_admin/planner – ein "nicht-Planner"
        // ist über die Rolle nicht darstellbar. Der real absicherbare Fall ist
        // der nicht angemeldete Zugriff (Auth-Middleware).
        $response = $this->get(route('planning.simulation.index'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function export_pdf_liefert_ein_pdf_dokument(): void
    {
        $a = Teacher::create(['shortcode' => 'EXA', 'firstname' => 'Eva', 'lastname' => 'Ex']);
        $b = Teacher::create(['shortcode' => 'EXB', 'firstname' => 'Ben', 'lastname' => 'Ex']);
        for ($i = 0; $i < 4; $i++) {
            $this->makeOralExam($i % 2 === 0 ? $a : $b, $i % 2 === 0 ? $b : $a, $i);
        }

        // Bandbreiten setzen, die der Export (loadRanges) verwendet.
        \App\Models\SchoolSetting::set('simulation.days.min', '1');
        \App\Models\SchoolSetting::set('simulation.days.max', '4');
        \App\Models\SchoolSetting::set('simulation.slots_per_day.min', '1');
        \App\Models\SchoolSetting::set('simulation.slots_per_day.max', '4');
        \App\Models\SchoolSetting::set('simulation.rooms.min', '1');
        \App\Models\SchoolSetting::set('simulation.rooms.max', '4');

        $response = $this->actingAs($this->planner)
            ->get(route('planning.simulation.export.pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function export_pdf_ist_fuer_gaeste_gesperrt(): void
    {
        $response = $this->get(route('planning.simulation.export.pdf'));

        $response->assertRedirect(route('login'));
    }
}
