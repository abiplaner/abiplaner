<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WrittenExamYearDate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Der Terminhinweis in der Schuelermaske — er erschien nie.
 *
 * `students/edit.blade.php` prueft, ob eine schriftliche Pruefung schon einem
 * Termin zugeordnet ist, und warnt dann: „Bei Aenderung wird die Zuordnung
 * entfernt." Gefragt wurde nach `written_exam_date_id`. Die Spalte heisst seit
 * der Umstellung auf die Slot-Struktur `written_exam_year_date_id`; Eloquent
 * liefert fuer das alte Attribut stillschweigend null, und die Bedingung war
 * damit immer falsch.
 *
 * In Produktion tragen 346 schriftliche Pruefungen einen Termin — der Hinweis
 * kam bei keiner einzigen (Stand 30.08.2026).
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter SchuelerMaskeTerminhinweis
 */
class SchuelerMaskeTerminhinweisTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'planner-terminhinweis-9999@test.invalid';
    private const HINWEIS = 'bereits einem Termin zugeordnet';

    protected School $school;
    protected User $planner;
    protected \App\Models\ExamPeriod $examPeriod;
    protected Student $schueler;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();

        $this->school = $this->createTestSchool();
        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);


        // Den Zeitraum nehmen, den die Maske selbst als den aktuellen ansieht —
        // sonst leitet StudentController::edit() weiter („gehoert nicht zum
        // aktuellen Pruefungszeitraum") und der Test prueft die Weiterleitung.
        $this->examPeriod = app(\App\Services\CurrentExamPeriodService::class)->currentOrFail();
        $this->schueler = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id' => 'TERMINHINWEIS-1',
            'grade' => '13',
            'firstname' => 'Tina',
            'lastname' => 'Terminhinweis',
        ]);
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    /**
     * Eine schriftliche Pruefung dieses Schuelers, wahlweise mit Termin.
     */
    private function schriftlichePruefung(bool $mitTermin): StudentExam
    {
        $fach = Subject::first();
        $lehrkraft = Teacher::firstOrCreate(
            ['shortcode' => 'THW'],
            ['firstname' => 'Test', 'lastname' => 'Hinweis']
        );

        $kurs = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id' => $fach->id,
            'course_code' => 'thw1',
            'teacher_id' => $lehrkraft->id,
            'level' => 'grundlegend',
        ]);

        $terminId = null;
        if ($mitTermin) {
            // Die Jahrestermine stehen zentral (WrittenExamSlotsSeeder) und
            // sind hier nur Beiwerk — der Test prueft die Maske, nicht die
            // Terminverwaltung.
            $termin = WrittenExamYearDate::first();
            $this->assertNotNull($termin, 'Es gibt keinen Jahrestermin zum Verweisen.');
            $terminId = $termin->id;
        }

        return StudentExam::create([
            'student_id' => $this->schueler->id,
            'course_id' => $kurs->id,
            'examiner_id' => $lehrkraft->id,
            'exam_type' => 'written',
            'written_exam_year_date_id' => $terminId,
        ]);
    }

    #[Test]
    public function der_hinweis_erscheint_wenn_ein_termin_zugeordnet_ist(): void
    {
        $this->schriftlichePruefung(mitTermin: true);

        $this->actingAs($this->planner)
            ->get(route('students.edit', $this->schueler))
            ->assertOk()
            ->assertSee(self::HINWEIS, false);
    }

    /**
     * Gegenprobe: Ohne Termin darf er nicht erscheinen. Ohne sie waere der
     * Test darueber auch dann gruen, wenn der Hinweis immer stuende — und
     * genau das waere die naheliegende Fehlkorrektur gewesen.
     */
    #[Test]
    public function ohne_termin_erscheint_der_hinweis_nicht(): void
    {
        $this->schriftlichePruefung(mitTermin: false);

        $this->actingAs($this->planner)
            ->get(route('students.edit', $this->schueler))
            ->assertOk()
            ->assertDontSee(self::HINWEIS, false);
    }

    /**
     * Und der Grund, warum es niemandem auffiel: Das alte Attribut liefert
     * stillschweigend null, statt einen Fehler zu werfen.
     */
    #[Test]
    public function das_alte_attribut_liefert_still_null(): void
    {
        $pruefung = $this->schriftlichePruefung(mitTermin: true);

        $this->assertNotNull(
            $pruefung->written_exam_year_date_id,
            'Der Termin steht in der neuen Spalte.'
        );
        $this->assertNull(
            $pruefung->written_exam_date_id,
            'Das alte Attribut gibt es nicht — Eloquent meldet das nicht, es liefert null.'
        );
    }
}
