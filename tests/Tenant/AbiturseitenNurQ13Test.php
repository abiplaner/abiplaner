<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WrittenExamYearDate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Seiten zum Abitur zeigen nur die Q13, nicht die Q12 desselben Zeitraums.
 *
 * Seit dem Import vom 24.11.2026 stehen im Abitur 2027 auch die Schüler der
 * Q12 — mit schriftlichen und mündlichen Prüfungen in ihren künftigen
 * Abiturfächern, in eigenen Kursen („1…“). Alles, was nur nach dem
 * Prüfungszeitraum auswählte, zeigte sie mit: Termine, Lehrereinsatz,
 * Kursübersicht, Kolloquiumsthemen, Dashboard, Absenzen.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter AbiturseitenNurQ13
 */
class AbiturseitenNurQ13Test extends TenantTestCase
{
    private const PLANNER_EMAIL = 'nur-q13-9999@test.invalid';

    protected User $planner;

    protected ExamPeriod $zeitraum;

    protected WrittenExamYearDate $termin;

    protected Teacher $lehrkraft13;

    protected Teacher $lehrkraft12;

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

        // Ein Einzelfachtermin im Jahr des neuesten Zeitraums: Fach und Niveau
        // der Kurse stehen damit fest, und die Terminseite zeigt sie.
        $this->zeitraum = ExamPeriod::query()->orderByDesc('year')->firstOrFail();
        $termin = WrittenExamYearDate::with('slot.subjects')
            ->where('exam_year', $this->zeitraum->year)->orderBy('id')->get()
            ->first(fn (WrittenExamYearDate $d): bool => $d->slot->subjects->count() === 1);
        $this->assertNotNull($termin, 'Die Stammdaten brauchen einen Einzelfachtermin.');
        $this->termin = $termin;

        $this->lehrkraft13 = $this->lehrkraft('QDRE');
        $this->lehrkraft12 = $this->lehrkraft('QZWO');

        $this->jahrgang('13', 'Abiturientin', 'zz13kurs', $this->lehrkraft13);
        $this->jahrgang('12', 'Zwoelferin', 'zz12kurs', $this->lehrkraft12);
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    #[Test]
    public function kursuebersicht_und_kolloquiumsthemen_zeigen_nur_q13_kurse(): void
    {
        foreach ([route('courses.index'), route('kolloquium.topics.index')] as $adresse) {
            $antwort = $this->actingAs($this->planner)->get($adresse);

            $antwort->assertOk();
            $antwort->assertSee('zz13kurs');
            $antwort->assertDontSee('zz12kurs');
        }
    }

    #[Test]
    public function der_schriftliche_termin_zeigt_nur_q13_kurse(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('written-exams.dates.show', $this->termin));

        $antwort->assertOk();
        $antwort->assertSee('zz13kurs');
        $antwort->assertDontSee('zz12kurs');
    }

    #[Test]
    public function der_lehrereinsatz_zaehlt_keine_q12_pruefungen(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('written-exams.teacher-deployment.index'));

        $antwort->assertOk();
        $zeilen = collect($antwort->viewData('stats'))->keyBy(fn (array $z) => $z['teacher']->shortcode);

        $this->assertSame(1, $zeilen['QDRE']['written_courses']);
        $this->assertSame(1, $zeilen['QDRE']['oral_examiner']);
        $this->assertSame(0, $zeilen['QZWO']['written_courses'], 'Schriftliche Prüfung der Q12 gezählt');
        $this->assertSame(0, $zeilen['QZWO']['oral_examiner'], 'Kolloquium der Q12 gezählt');
    }

    #[Test]
    public function dashboard_und_absenzen_zaehlen_nur_die_q13(): void
    {
        $q13 = Student::where('exam_period_id', $this->zeitraum->id)->derStufe('13')->count();

        $dashboard = $this->actingAs($this->planner)->get(route('dashboard'));
        $dashboard->assertOk();
        $this->assertSame($q13, $dashboard->viewData('stats')['total_students']);

        $absenzen = $this->actingAs($this->planner)->get(route('absences.index'));
        $absenzen->assertOk();
        $absenzen->assertSee('Abiturientin');
        $absenzen->assertDontSee('Zwoelferin');
    }

    /**
     * Jahrgangsübergreifende Kurse („3…“) haben Schüler beider Stufen. Sie
     * gehören auf die Abiturseiten — aber nur mit ihren Q13-Prüfungen. In
     * Produktion: 3l1 mit einem Kolloquium der Q13 und fünf der Q12.
     */
    #[Test]
    public function jahrgangsuebergreifende_kurse_erscheinen_nur_mit_ihren_q13_pruefungen(): void
    {
        $q13 = Student::where('lastname', 'Abiturientin')->firstOrFail();
        $q12 = Student::where('lastname', 'Zwoelferin')->firstOrFail();

        foreach (['zz3kol' => 'oral', 'zz3schrift' => 'written'] as $code => $art) {
            $kurs = Course::create([
                'exam_period_id' => $this->zeitraum->id,
                'subject_id' => $this->termin->slot->subjects->first()->id,
                'course_code' => $code,
                'teacher_id' => $this->lehrkraft13->id,
                'level' => $this->termin->slot->requirement_level,
            ]);
            foreach ([$q13, $q12] as $schueler) {
                StudentExam::create([
                    'student_id' => $schueler->id,
                    'course_id' => $kurs->id,
                    'exam_type' => $art,
                    'examiner_id' => $this->lehrkraft13->id,
                ]);
            }
        }

        // Kursübersicht: Kurs da, Q12-Schülerin nicht.
        $kurse = $this->actingAs($this->planner)->get(route('courses.index'));
        $kurse->assertOk();
        $kurse->assertSee('zz3kol');
        $kurse->assertSee('Abiturientin');
        $kurse->assertDontSee('Zwoelferin');

        // Termin: der schriftliche Kurs zählt nur die Q13-Schülerin.
        $termin = $this->actingAs($this->planner)->get(route('written-exams.dates.show', $this->termin));
        $termin->assertOk();
        $termin->assertSee('zz3schrift (1)');

        // Kopplungen: nur das Kolloquium der Q13.
        $kopplungen = $this->actingAs($this->planner)->get(route('kolloquium.topics.couplings'));
        $kopplungen->assertOk();
        $kopplungen->assertSee('Abiturientin');
        $kopplungen->assertDontSee('Zwoelferin');
    }

    private function lehrkraft(string $kuerzel): Teacher
    {
        return Teacher::create(['shortcode' => $kuerzel, 'firstname' => 'Test', 'lastname' => 'Lehrkraft' . $kuerzel]);
    }

    /**
     * Ein Schüler der Stufe mit je einer schriftlichen und mündlichen Prüfung
     * im eigenen Kurs — Fach und Niveau wie der Einzelfachtermin.
     */
    private function jahrgang(string $stufe, string $nachname, string $kurscode, Teacher $lehrkraft): void
    {
        $schueler = Student::create([
            'exam_period_id' => $this->zeitraum->id,
            'grade' => $stufe,
            'external_id' => 'nurq13-' . $stufe,
            'firstname' => 'Test',
            'lastname' => $nachname,
        ]);
        // Je Schüler und Kurs gibt es nur eine Prüfung: schriftlich im Kurs
        // des Termins, das Kolloquium in einem zweiten Kurs.
        foreach (['written' => $kurscode, 'oral' => $kurscode . 'kol'] as $art => $code) {
            $kurs = Course::create([
                'exam_period_id' => $this->zeitraum->id,
                'subject_id' => $this->termin->slot->subjects->first()->id,
                'course_code' => $code,
                'teacher_id' => $lehrkraft->id,
                'level' => $this->termin->slot->requirement_level,
            ]);
            StudentExam::create([
                'student_id' => $schueler->id,
                'course_id' => $kurs->id,
                'exam_type' => $art,
                'examiner_id' => $lehrkraft->id,
            ]);
        }
    }
}
