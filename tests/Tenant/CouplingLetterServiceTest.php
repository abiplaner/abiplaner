<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Services\CouplingLetterService;

/**
 * Modul 4.7 – Übernahme der Gruppenbuchstaben vom Papierformular.
 *
 * Die Lehrkraft schreibt A/B/C neben die Prüflinge, die OSK tippt das ab.
 * Gleiche Buchstaben eines Kurses werden zu einer Koppelgruppe mit
 * gemeinsamer coupling_id.
 */
class CouplingLetterServiceTest extends TenantTestCase
{
    private function makeCourse(ExamPeriod $examPeriod, string $code, string $suffix): Course
    {
        $teacher = Teacher::create([
            'shortcode' => 'K' . $suffix,
            'firstname' => 'Kopp',
            'lastname'  => 'Lung' . $suffix,
        ]);

        return Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => $code,
            'teacher_id'     => $teacher->id,
            'level'          => 'grundlegend',
        ]);
    }

    private function addExam(Course $course, ExamPeriod $examPeriod, string $suffix): StudentExam
    {
        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'KOP-' . $suffix,
            'firstname'      => 'Pruef',
            'lastname'       => 'Ling' . $suffix,
            'grade'          => '13',
        ]);

        return StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $course->teacher_id,
            'exam_type'   => 'oral',
        ]);
    }

    public function test_gleiche_buchstaben_bilden_eine_koppelgruppe(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, 'kop1', 'A');

        $a1 = $this->addExam($course, $examPeriod, 'A1');
        $a2 = $this->addExam($course, $examPeriod, 'A2');
        $b1 = $this->addExam($course, $examPeriod, 'B1');
        $b2 = $this->addExam($course, $examPeriod, 'B2');
        $solo = $this->addExam($course, $examPeriod, 'S1');

        $stats = (new CouplingLetterService())->apply($course, [
            $a1->id   => 'A',
            $a2->id   => 'A',
            $b1->id   => 'B',
            $b2->id   => 'B',
            $solo->id => '',
        ]);

        $this->assertSame(2, $stats['groups']);
        $this->assertSame(4, $stats['grouped']);
        $this->assertSame(1, $stats['ungrouped']);

        $a1->refresh();
        $a2->refresh();
        $b1->refresh();
        $b2->refresh();
        $solo->refresh();

        // A-Gruppe teilt eine coupling_id, B-Gruppe eine andere.
        $this->assertNotNull($a1->coupling_id);
        $this->assertSame($a1->coupling_id, $a2->coupling_id);
        $this->assertNotNull($b1->coupling_id);
        $this->assertSame($b1->coupling_id, $b2->coupling_id);
        $this->assertNotSame($a1->coupling_id, $b1->coupling_id);

        // Ohne Buchstabe: keine Kopplung.
        $this->assertNull($solo->coupling_id);

    }

    /**
     * Kernfall der Praxis: Die OSK vertippt sich oder die Lehrkraft korrigiert
     * das Formular. Ein zweiter Durchlauf muss die alten Gruppen ersetzen –
     * nicht ergänzen.
     */
    public function test_korrekturlauf_ersetzt_bestehende_kopplung(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, 'kop2', 'B');

        $e1 = $this->addExam($course, $examPeriod, 'C1');
        $e2 = $this->addExam($course, $examPeriod, 'C2');
        $e3 = $this->addExam($course, $examPeriod, 'C3');

        $service = new CouplingLetterService();

        // Erst: 1+2 gekoppelt, 3 allein.
        $service->apply($course, [$e1->id => 'A', $e2->id => 'A', $e3->id => '']);
        $firstCoupling = $e1->fresh()->coupling_id;
        $this->assertNotNull($firstCoupling);
        $this->assertNull($e3->fresh()->coupling_id);

        // Korrektur: 2+3 gekoppelt, 1 allein.
        $stats = $service->apply($course, [$e1->id => '', $e2->id => 'A', $e3->id => 'A']);

        $this->assertSame(1, $stats['groups']);
        $this->assertSame(2, $stats['grouped']);

        $this->assertNull($e1->fresh()->coupling_id, 'Alte Kopplung muss gelöst sein.');
        $this->assertNotNull($e2->fresh()->coupling_id);
        $this->assertSame($e2->fresh()->coupling_id, $e3->fresh()->coupling_id);
        $this->assertNotSame($firstCoupling, $e2->fresh()->coupling_id);

    }

    /**
     * Ein Buchstabe, der nur einmal vorkommt, ergibt keine Kopplung – eine
     * Gruppe braucht mindestens zwei Prüflinge. Der Fall wird gezählt, damit
     * die OSK einen möglichen Tippfehler bemerkt.
     */
    public function test_einzelner_buchstabe_koppelt_nicht(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, 'kop3', 'C');

        $e1 = $this->addExam($course, $examPeriod, 'D1');
        $e2 = $this->addExam($course, $examPeriod, 'D2');

        $stats = (new CouplingLetterService())->apply($course, [
            $e1->id => 'A',
            $e2->id => 'B',
        ]);

        $this->assertSame(0, $stats['groups']);
        $this->assertSame(0, $stats['grouped']);
        $this->assertSame(2, $stats['single'], 'Zwei einzeln gebliebene Buchstaben.');

        $this->assertNull($e1->fresh()->coupling_id);
        $this->assertNull($e2->fresh()->coupling_id);

    }

    public function test_gross_und_kleinschreibung_ist_egal(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, 'kop4', 'D');

        $e1 = $this->addExam($course, $examPeriod, 'E1');
        $e2 = $this->addExam($course, $examPeriod, 'E2');

        $stats = (new CouplingLetterService())->apply($course, [
            $e1->id => 'a',
            $e2->id => ' A ',
        ]);

        $this->assertSame(1, $stats['groups']);
        $this->assertSame($e1->fresh()->coupling_id, $e2->fresh()->coupling_id);
        $this->assertNotNull($e1->fresh()->coupling_id);

    }

    /**
     * Kopplungen gelten nur innerhalb eines Kurses. Eine Prüfung aus einem
     * fremden Kurs darf nicht mitgekoppelt und nicht entkoppelt werden.
     */
    public function test_fremde_kurse_bleiben_unberuehrt(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $courseA = $this->makeCourse($examPeriod, 'kop5', 'E');
        $courseB = $this->makeCourse($examPeriod, 'kop6', 'F');

        $a1 = $this->addExam($courseA, $examPeriod, 'F1');
        $a2 = $this->addExam($courseA, $examPeriod, 'F2');
        $foreign = $this->addExam($courseB, $examPeriod, 'F3');

        $service = new CouplingLetterService();

        // Kurs B vorab koppeln – das darf ein Lauf auf Kurs A nicht zerstören.
        $partner = $this->addExam($courseB, $examPeriod, 'F4');
        $service->apply($courseB, [$foreign->id => 'A', $partner->id => 'A']);
        $foreignCoupling = $foreign->fresh()->coupling_id;
        $this->assertNotNull($foreignCoupling);

        // Lauf auf Kurs A, dabei wird eine fremde ID untergeschoben.
        $stats = $service->apply($courseA, [
            $a1->id      => 'A',
            $a2->id      => 'A',
            $foreign->id => 'A',
        ]);

        $this->assertSame(1, $stats['groups']);
        $this->assertSame(2, $stats['grouped'], 'Die fremde Prüfung darf nicht mitzählen.');

        $this->assertSame(
            $foreignCoupling,
            $foreign->fresh()->coupling_id,
            'Die Kopplung des fremden Kurses muss unverändert bleiben.'
        );
        $this->assertNotSame($foreignCoupling, $a1->fresh()->coupling_id);

    }

    public function test_buchstaben_werden_aus_bestehenden_kopplungen_abgeleitet(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, 'kop7', 'G');

        $e1 = $this->addExam($course, $examPeriod, 'G1');
        $e2 = $this->addExam($course, $examPeriod, 'G2');
        $e3 = $this->addExam($course, $examPeriod, 'G3');
        $e4 = $this->addExam($course, $examPeriod, 'G4');

        $service = new CouplingLetterService();
        $service->apply($course, [
            $e1->id => 'A',
            $e2->id => 'A',
            $e3->id => 'B',
            $e4->id => 'B',
        ]);

        $exams = StudentExam::where('course_id', $course->id)->orderBy('id')->get();
        $letters = $service->lettersFor($exams);

        // Erste Gruppe wird zu A, zweite zu B – unabhängig von der UUID.
        $this->assertSame('A', $letters[$e1->id]);
        $this->assertSame('A', $letters[$e2->id]);
        $this->assertSame('B', $letters[$e3->id]);
        $this->assertSame('B', $letters[$e4->id]);

    }
}
