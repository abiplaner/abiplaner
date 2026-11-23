<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Zusatzpruefung;
use App\Models\ZusatzpruefExamScore;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die beiden AJAX-Endpunkte des Mindestpunkte-Modals.
 *
 * public/js/mindestpunkte.js ruft sie nach jeder Punkte-Änderung: einmal zum
 * Speichern der Einzelnote, einmal zum kompletten Neuladen des Modals. Fällt
 * einer der beiden aus, merkt das niemand — das Modal fängt den Fehler ab und
 * zeigt einfach weiter die alten Zahlen. Genau das ist in der Septemberrunde
 * passiert, als die Routen als „tot" entfernt wurden.
 */
class ZusatzpruefMindestpunkteModalTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2096;
    private const TEST_USER_EMAIL  = 'planner-mp-9999@test.invalid';

    private School $school;
    private User $planner;
    private ExamPeriod $examPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $this->examPeriod = ExamPeriod::create([
            'name'         => 'Testperiode Mindestpunkte',
            'year'         => self::TEST_PERIOD_YEAR,
            'week_1_start' => self::TEST_PERIOD_YEAR . '-05-04',
            'week_1_end'   => self::TEST_PERIOD_YEAR . '-05-08',
            'week_2_start' => self::TEST_PERIOD_YEAR . '-05-11',
            'week_2_end'   => self::TEST_PERIOD_YEAR . '-05-15',
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

    #[Test]
    public function das_modal_laedt_die_daten_eines_schuelers_nach(): void
    {
        [$student, $studentExam] = $this->schuelerMitZusatzpruefung();

        $antwort = $this->actingAs($this->planner)
            ->getJson(route('zusatzpruefungen.pruefling.mindestpunkte-modal-data', [
                'pruefling' => 's' . $student->id,
            ]))
            ->assertOk();

        $antwort->assertJsonPath('studentId', 's' . $student->id);
        $antwort->assertJsonPath('studentName', $student->full_name);
        $this->assertCount(1, $antwort->json('exams'));
        $this->assertCount(1, $antwort->json('pruefungen'));
        $this->assertSame($studentExam->id, $antwort->json('exams.0.id'));
    }

    #[Test]
    public function eine_einzelnote_wird_gespeichert(): void
    {
        [$student, $studentExam] = $this->schuelerMitZusatzpruefung();

        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 's' . $student->id]),
                ['student_exam_id' => $studentExam->id, 'score' => 11]
            )
            ->assertOk();

        $note = ZusatzpruefExamScore::where('student_exam_id', $studentExam->id)->first();

        $this->assertNotNull($note, 'Ohne diesen Endpunkt verschwindet die Note stillschweigend.');
        $this->assertSame(11, $note->score);
        $this->assertSame($student->id, $note->student_id);

        // Zweiter Aufruf: korrigieren, nicht doppelt anlegen.
        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 's' . $student->id]),
                ['student_exam_id' => $studentExam->id, 'score' => 4]
            )
            ->assertOk();

        $this->assertSame(1, ZusatzpruefExamScore::where('student_exam_id', $studentExam->id)->count());
        $this->assertSame(4, ZusatzpruefExamScore::where('student_exam_id', $studentExam->id)->first()->score);
    }

    /**
     * @return array{Student, StudentExam}
     */
    private function schuelerMitZusatzpruefung(): array
    {
        $teacher = Teacher::create([
            'shortcode' => 'MPT',
            'firstname' => 'Test',
            'lastname'  => 'Lehrer',
        ]);

        $course = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => '2MP',
            'teacher_id'     => $teacher->id,
            'level'          => 'erhoeht',
        ]);

        $student = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id'    => 'MP-1',
            'firstname'      => 'Anna',
            'lastname'       => 'Muster',
            'grade'          => '13',
        ]);

        $studentExam = StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $teacher->id,
            'exam_type'   => 'written',
        ]);

        Zusatzpruefung::create([
            'exam_period_id'  => $this->examPeriod->id,
            'student_id'      => $student->id,
            'student_exam_id' => $studentExam->id,
            'status'          => 'pending',
            'written_points'  => 3,
        ]);

        return [$student, $studentExam];
    }
}
