<?php

namespace Tests\Tenant;

use App\Models\ColloquiumTopic;
use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Teacher;
use Illuminate\Database\QueryException;

/**
 * Modul 4.7 – Kolloquiums-Themenkatalog: pro Kurs und Halbjahr (12/1, 12/2)
 * bis zu drei Themen-Slots. Die Themen sind reiner Katalog-Kontext für das
 * Papierformular; die eigentliche Kopplung bleibt an student_exams.coupling_id.
 *
 * Tests laufen gegen die echte Tenant-DB der Testschule 9999.
 */
class ColloquiumTopicTest extends TenantTestCase
{
    private function makeCourse(ExamPeriod $examPeriod, string $suffix): Course
    {
        $teacher = Teacher::create([
            'shortcode' => 'T' . $suffix,
            'firstname' => 'The',
            'lastname'  => 'Ma' . $suffix,
        ]);

        return Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'topic' . $suffix,
            'teacher_id'     => $teacher->id,
            'level'          => 'grundlegend',
        ]);
    }

    public function test_thema_wird_angelegt_und_gehoert_zum_kurs(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course     = $this->makeCourse($examPeriod, 'A');

        $topic = ColloquiumTopic::create([
            'course_id' => $course->id,
            'semester'  => '12/1',
            'slot'      => 1,
            'title'     => 'Nachhaltige Energieversorgung',
        ]);

        $this->assertSame(1, $topic->slot);
        $this->assertIsInt($topic->slot);
        $this->assertSame($course->id, $topic->course->id);
        $this->assertTrue($course->colloquiumTopics->contains($topic));

    }

    public function test_gleicher_slot_pro_kurs_und_halbjahr_ist_eindeutig(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course     = $this->makeCourse($examPeriod, 'B');

        ColloquiumTopic::create([
            'course_id' => $course->id,
            'semester'  => '12/1',
            'slot'      => 1,
            'title'     => 'Erstes Thema',
        ]);

        // Gleicher Slot im gleichen Halbjahr desselben Kurses: Unique-Verstoß.
        $this->expectException(QueryException::class);

        ColloquiumTopic::create([
            'course_id' => $course->id,
            'semester'  => '12/1',
            'slot'      => 1,
            'title'     => 'Kollidierendes Thema',
        ]);

    }

    public function test_gleicher_slot_in_anderem_halbjahr_ist_erlaubt(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course     = $this->makeCourse($examPeriod, 'C');

        ColloquiumTopic::create([
            'course_id' => $course->id,
            'semester'  => '12/1',
            'slot'      => 1,
            'title'     => 'Thema HJ1',
        ]);

        $second = ColloquiumTopic::create([
            'course_id' => $course->id,
            'semester'  => '12/2',
            'slot'      => 1,
            'title'     => 'Thema HJ2',
        ]);

        $this->assertDatabaseHas('colloquium_topics', [
            'id'       => $second->id,
            'semester' => '12/2',
        ]);

    }

    public function test_themen_werden_mit_kurs_kaskadierend_geloescht(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course     = $this->makeCourse($examPeriod, 'D');

        $topic = ColloquiumTopic::create([
            'course_id' => $course->id,
            'semester'  => '12/2',
            'slot'      => 3,
            'title'     => 'Zu löschendes Thema',
        ]);

        $course->delete();

        $this->assertDatabaseMissing('colloquium_topics', [
            'id' => $topic->id,
        ]);

    }
}
