<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Zusatzpruefung;
use App\Models\ZusatzpruefExamScore;
use PHPUnit\Framework\Attributes\Test;

/**
 * Entscheidung 40 (28.08.2026): im Sport keine Theorienote ohne Praxisnote.
 *
 * Der Grund ist eine Rechnung, die sonst gar nicht stattfindet. Ein Sportfach
 * traegt die Praxisnote gleichberechtigt neben der Theorienote; fehlt sie,
 * geben `scoreContribution()` (Kolloquium) und `getExamResultPoints()`
 * (Zusatzpruefung) `null` zurueck. Und `null` ist kein Beitrag von 0, sondern
 * gar keiner: das Fach faellt aus Modal und Niederschrift heraus, ohne dass
 * irgendwo eine Luecke sichtbar waere.
 *
 * In der Pilotschule stand dieser Zustand real: von zehn Sport-Kolloquien trugen nur vier
 * eine Praxisnote. Die Zusatzpruefung ist dabei die kleinere Haelfte des
 * Problems — deshalb prueft dieser Test beide Wege.
 */
class SportPraxisnotePflichtTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2097;
    private const TEST_USER_EMAIL  = 'planner-sport-9999@test.invalid';

    private School $school;
    private User $planner;
    private ExamPeriod $examPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $this->examPeriod = ExamPeriod::create([
            'name'         => 'Testperiode Sportpflicht',
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

    // ── Der Kolloquiumsweg — die groessere Haelfte ────────────────────

    #[Test]
    public function eine_sportnote_ohne_praxisnote_wird_abgewiesen(): void
    {
        [$student, $pruefung] = $this->sportpruefling('oral');

        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 's' . $student->id]),
                ['student_exam_id' => $pruefung->id, 'score' => 13]
            )
            ->assertStatus(422)
            ->assertJsonPath('error', 'Für Sport männlich/weiblich fehlt die Praxisnote. Ohne sie zählt das Fach in der Abiturrechnung gar nicht.');

        $this->assertSame(
            0,
            ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->count(),
            'Die abgewiesene Note darf auch nicht halb gespeichert sein.'
        );
    }

    #[Test]
    public function mit_praxisnote_im_selben_aufruf_geht_es_durch(): void
    {
        [$student, $pruefung] = $this->sportpruefling('oral');

        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 's' . $student->id]),
                ['student_exam_id' => $pruefung->id, 'score' => 13, 'practical_grade' => 10]
            )
            ->assertOk();

        $note = ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->first();

        $this->assertInstanceOf(ZusatzpruefExamScore::class, $note);
        $this->assertSame(13, $note->score);
        $this->assertSame(10, $note->practical_grade);
    }

    /**
     * Die Regel gilt nur in einer Richtung. Wer die Praxisnote zuerst
     * eintraegt und die Theorienote spaeter, arbeitet unbehelligt — verboten
     * ist allein die Reihenfolge, die einen halben Datensatz hinterlaesst.
     */
    #[Test]
    public function die_praxisnote_allein_darf_jederzeit_gespeichert_werden(): void
    {
        [$student, $pruefung] = $this->sportpruefling('oral');

        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 's' . $student->id]),
                ['student_exam_id' => $pruefung->id, 'practical_grade' => 9]
            )
            ->assertOk();

        // Und danach traegt die Theorienote, ohne dass sie erneut mitgeschickt
        // werden muesste — die gespeicherte zaehlt.
        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 's' . $student->id]),
                ['student_exam_id' => $pruefung->id, 'score' => 7]
            )
            ->assertOk();

        $note = ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->first();

        $this->assertInstanceOf(ZusatzpruefExamScore::class, $note);
        $this->assertSame(7, $note->score);
        $this->assertSame(9, $note->practical_grade);
    }

    #[Test]
    public function ausserhalb_des_sports_gilt_die_regel_nicht(): void
    {
        [$student, $pruefung] = $this->sportpruefling('oral', fachCode: 'D', kursCode: '2D-L1');

        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 's' . $student->id]),
                ['student_exam_id' => $pruefung->id, 'score' => 13]
            )
            ->assertOk();
    }

    // ── Der Zusatzpruefungsweg ────────────────────────────────────────

    #[Test]
    public function eine_sport_zusatzpruefung_ohne_praxisnote_entsteht_nicht(): void
    {
        [$student, $pruefung] = $this->sportpruefling('written');

        $this->actingAs($this->planner)
            ->postJson(route('zusatzpruefungen.store'), [
                'pruefling'        => 's' . $student->id,
                'student_exam_ids' => [$pruefung->id],
                'scores'           => [(string) $pruefung->id => 4],
            ])
            ->assertStatus(422);

        $this->assertSame(
            0,
            Zusatzpruefung::where('student_exam_id', $pruefung->id)->count(),
            'Ohne Praxisnote darf keine Sport-Zusatzprüfung entstehen.'
        );

        $this->assertSame(
            0,
            ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->count(),
            'Und auch die Note daneben nicht — geprüft wird vor der Transaktion.'
        );
    }

    #[Test]
    public function mit_praxisnote_entsteht_die_sport_zusatzpruefung(): void
    {
        [$student, $pruefung] = $this->sportpruefling('written');

        $this->actingAs($this->planner)
            ->postJson(route('zusatzpruefungen.store'), [
                'pruefling'        => 's' . $student->id,
                'student_exam_ids' => [$pruefung->id],
                'scores'           => [(string) $pruefung->id => 4],
                'practical_grades' => [(string) $pruefung->id => 11],
            ])
            ->assertOk();

        $zp = Zusatzpruefung::where('student_exam_id', $pruefung->id)->first();

        $this->assertInstanceOf(Zusatzpruefung::class, $zp);
        $this->assertSame(4, $zp->written_points);
        $this->assertSame(11, $zp->sportPraxisnote());
    }

    /**
     * Der gefaehrlichste Fall: die Praxisnote steht schon, und ein Speichern
     * schickt sie als `null` mit. Genau das tut die Maske, wenn das Feld leer
     * ist — und genau so entstuende der Zustand wieder, den diese Regel
     * verhindert.
     */
    #[Test]
    public function eine_vorhandene_praxisnote_darf_nicht_geleert_werden(): void
    {
        [$student, $pruefung] = $this->sportpruefling('written');

        ZusatzpruefExamScore::create([
            'student_id'      => $student->id,
            'student_exam_id' => $pruefung->id,
            'exam_period_id'  => $this->examPeriod->id,
            'score'           => 4,
            'practical_grade' => 10,
        ]);

        $zp = Zusatzpruefung::create([
            'exam_period_id'  => $this->examPeriod->id,
            'student_id'      => $student->id,
            'student_exam_id' => $pruefung->id,
            'status'          => 'pending',
            'written_points'  => 4,
        ]);

        $this->actingAs($this->planner)
            ->patchJson(route('zusatzpruefungen.points.update', ['zusatzpruefung' => $zp->id]), [
                'target_points'   => 12,
                'practical_grade' => null,
            ])
            ->assertStatus(422);

        $this->assertSame(
            10,
            ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->first()?->practical_grade,
            'Die vorhandene Praxisnote muss stehen bleiben.'
        );

        $this->assertNull(
            $zp->fresh()?->target_points,
            'Und die mündliche Note darf auch nicht durchgelaufen sein.'
        );
    }

    /**
     * Der Notensatz wird ueber `student_exam_id` allein gefunden — auch dann,
     * wenn `student_id` in der gespeicherten Zeile und an der Zusatzpruefung
     * auseinanderfallen.
     *
     * Der Test steht hier, weil die naheliegende Begruendung falsch ist: Ein
     * externer Bewerber mit `student_id = null` auf beiden Seiten wurde vom
     * frueheren, zusammengesetzten Schluessel durchaus gefunden — Laravel
     * macht aus `where('col', null)` ein `whereNull`. Nachgerechnet am
     * 28.08.2026, indem der alte Schluessel wiederhergestellt und der Test
     * erneut gefahren wurde: er lief gruen durch und belegte damit gar nichts.
     *
     * Brechen kann der zusammengesetzte Schluessel nur bei einer Diskrepanz.
     * Deshalb legt dieser Test die Notenzeile bewusst ohne `student_id` an,
     * waehrend die Zusatzpruefung eine traegt: Dann sucht
     * `where('student_id', <id>)` daneben, updateOrCreate legt eine zweite
     * Zeile an — und laeuft in den UNIQUE auf `student_exam_id`.
     */
    #[Test]
    public function die_notenzeile_wird_ueber_die_pruefung_gefunden_nicht_ueber_den_pruefling(): void
    {
        [$student, $pruefung] = $this->sportpruefling('written');

        // Notenzeile ohne Prüflingsbezug — die Prüfung ist der Schlüssel.
        ZusatzpruefExamScore::create([
            'student_id'      => null,
            'student_exam_id' => $pruefung->id,
            'exam_period_id'  => $this->examPeriod->id,
            'score'           => 5,
            'practical_grade' => 8,
        ]);

        // Die Zusatzprüfung dagegen trägt den Schüler.
        $zp = Zusatzpruefung::create([
            'exam_period_id'  => $this->examPeriod->id,
            'student_id'      => $student->id,
            'student_exam_id' => $pruefung->id,
            'status'          => 'pending',
            'written_points'  => 5,
        ]);

        $this->actingAs($this->planner)
            ->patchJson(route('zusatzpruefungen.points.update', ['zusatzpruefung' => $zp->id]), [
                'target_points'   => 12,
                'practical_grade' => 9,
            ])
            ->assertOk();

        $this->assertSame(
            1,
            ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->count(),
            'Es darf genau eine Notenzeile geben, nicht zwei.'
        );

        $this->assertSame(
            9,
            ZusatzpruefExamScore::where('student_exam_id', $pruefung->id)->first()?->practical_grade
        );
    }

    // ── Aufbau ───────────────────────────────────────────────────────

    private function fachId(string $code): int
    {
        $fach = Subject::where('code', $code)->first();
        $this->assertInstanceOf(Subject::class, $fach, "Fach {$code} fehlt im Katalog");

        return (int) $fach->id;
    }

    /**
     * @return array{Student, StudentExam}
     */
    private function sportpruefling(
        string $examType,
        string $fachCode = 'Smw',
        string $kursCode = '2Smw-'
    ): array {
        $teacher = Teacher::create([
            'shortcode' => 'SPP',
            'firstname' => 'Test',
            'lastname'  => 'Sportlehrer',
        ]);

        $course = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id'     => $this->fachId($fachCode),
            'course_code'    => $kursCode,
            'teacher_id'     => $teacher->id,
            'level'          => 'erhoeht',
        ]);

        $student = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id'    => 'SPP-1',
            'firstname'      => 'Sport',
            'lastname'       => 'Prüfling',
            'grade'          => '13',
        ]);

        $pruefung = StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $teacher->id,
            'exam_type'   => $examType,
        ]);

        return [$student, $pruefung];
    }
}
