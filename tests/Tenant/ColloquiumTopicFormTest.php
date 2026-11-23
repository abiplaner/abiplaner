<?php

namespace Tests\Tenant;

use App\Models\ColloquiumTopic;
use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;

/**
 * Modul 4.7 – Auswahl der Kurse für das Koppelformular (PDF).
 *
 * Gedruckt wird ein Blatt je Kurs, aber nur dann, wenn der Kurs sowohl
 * Kolloquiumsprüfungen als auch erfasste Themen hat: Ohne Themenkatalog
 * fehlt der Lehrkraft die Grundlage für die Kopplung, ohne Prüflinge gibt
 * es nichts zu koppeln.
 */
class ColloquiumTopicFormTest extends TenantTestCase
{
    /**
     * Bildet die Auswahl aus ColloquiumTopicController::formPdf() nach.
     *
     * @return \Illuminate\Support\Collection<int, Course>
     */
    private function selectCoursesForForm(ExamPeriod $examPeriod)
    {
        return Course::with([
                'colloquiumTopics',
                'studentExams' => function ($query): void {
                    $query->where('exam_type', 'oral');
                },
            ])
            ->where('exam_period_id', $examPeriod->id)
            ->get()
            ->filter(fn (Course $course): bool => $course->studentExams->isNotEmpty()
                && $course->colloquiumTopics->isNotEmpty())
            ->sortBy('course_code')
            ->values();
    }

    private function makeCourse(ExamPeriod $examPeriod, string $code, string $suffix): Course
    {
        $teacher = Teacher::create([
            'shortcode' => 'F' . $suffix,
            'firstname' => 'For',
            'lastname'  => 'Mular' . $suffix,
        ]);

        return Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => $code,
            'teacher_id'     => $teacher->id,
            'level'          => 'grundlegend',
        ]);
    }

    private function addOralExam(Course $course, ExamPeriod $examPeriod, string $suffix): StudentExam
    {
        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'EXT-' . $suffix,
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

    private function addTopic(Course $course, string $semester, int $slot): ColloquiumTopic
    {
        return ColloquiumTopic::create([
            'course_id' => $course->id,
            'semester'  => $semester,
            'slot'      => $slot,
            'title'     => 'Thema ' . $semester . '/' . $slot,
        ]);
    }

    public function test_nur_kurse_mit_themen_und_pruefungen_kommen_aufs_formular(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        // Vollständig: Prüflinge + Themen -> gedruckt
        $complete = $this->makeCourse($examPeriod, 'form1', 'A');
        $this->addOralExam($complete, $examPeriod, 'A');
        $this->addTopic($complete, '12/1', 1);

        // Prüflinge, aber keine Themen -> nicht gedruckt
        $noTopics = $this->makeCourse($examPeriod, 'form2', 'B');
        $this->addOralExam($noTopics, $examPeriod, 'B');

        // Themen, aber keine Prüflinge -> nicht gedruckt
        $noExams = $this->makeCourse($examPeriod, 'form3', 'C');
        $this->addTopic($noExams, '12/1', 1);

        $selected = $this->selectCoursesForForm($examPeriod);

        $this->assertCount(1, $selected);
        $this->assertSame($complete->id, $selected->first()->id);

    }

    /**
     * Schriftliche Prüfungen dürfen das Formular nicht auslösen: Es geht
     * ausschließlich um Kolloquien.
     */
    public function test_kurs_mit_nur_schriftlichen_pruefungen_wird_nicht_gedruckt(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $course = $this->makeCourse($examPeriod, 'form4', 'D');
        $this->addTopic($course, '12/1', 1);

        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'EXT-W1',
            'firstname'      => 'Schrift',
            'lastname'       => 'Lich',
            'grade'          => '13',
        ]);
        StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $course->teacher_id,
            'exam_type'   => 'written',
        ]);

        $this->assertCount(0, $this->selectCoursesForForm($examPeriod));

    }

    public function test_formular_kurse_sind_nach_kurskuerzel_sortiert(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        foreach (['form9' => 'E', 'form1' => 'F', 'form5' => 'G'] as $code => $suffix) {
            $course = $this->makeCourse($examPeriod, $code, $suffix);
            $this->addOralExam($course, $examPeriod, $suffix);
            $this->addTopic($course, '12/1', 1);
        }

        $codes = $this->selectCoursesForForm($examPeriod)
            ->pluck('course_code')
            ->all();

        $this->assertSame(['form1', 'form5', 'form9'], $codes);

    }
}
