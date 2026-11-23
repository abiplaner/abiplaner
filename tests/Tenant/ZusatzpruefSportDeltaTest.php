<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Zusatzpruefung;
use App\Models\ZusatzpruefExamScore;
use App\Services\ExamBoardMinutesService;

/**
 * Die Verbesserung durch eine Zusatzprüfung — und der Sport-Sonderfall.
 *
 * Sport ist Abiturprüfungsfach nur als Leistungsfach (§ 48 Abs. 1 Nr. 1), und
 * eine Zusatzprüfung gibt es dort nur nach der schriftlichen Prüfung. Auf
 * diesem Weg zählt die Praxisnote gleichberechtigt neben der Theorienote —
 * auf **beiden Seiten** der Verbesserungsrechnung:
 *
 *   ohne Zusatzprüfung   (Theorie + Praxis) × 2
 *   mit Zusatzprüfung    round((Praxis + Theorie + mündlich) × 4 / 3)
 *
 * `getPointsDelta()` rechnete bis zum 27.08.2026 in beiden Hälften mit der
 * Formel für gewöhnliche Fächer (Theorie × 4 bzw. round((2 × Theorie +
 * mündlich) × 4 / 3)). Die Praxisnote fehlte damit ganz und die Theorienote
 * war doppelt gewichtet.
 */
class ZusatzpruefSportDeltaTest extends TenantTestCase
{
    private ExamPeriod $examPeriod;
    private int $suffix = 0;

    private function boot(): void
    {
        $school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::first();
    }

    private function fachId(string $code): int
    {
        $fach = Subject::where('code', $code)->first();
        $this->assertInstanceOf(Subject::class, $fach, "Fach {$code} fehlt im Katalog");

        return (int) $fach->id;
    }

    /**
     * Legt eine Zusatzprüfung an. `$praxis` schreibt die Praxisnote in
     * zusatzpruef_exam_scores — dort und nicht an der Zusatzprüfung steht sie.
     */
    private function makeZp(
        string $fachCode,
        int $written,
        ?int $target,
        ?int $praxis = null,
        bool $improvementOnly = false,
        ?int $improvementNeeded = null,
        string $courseVorsatz = 'apa',
    ): Zusatzpruefung {
        $s = ++$this->suffix;

        $student = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id'    => 'SPT-' . $s,
            'firstname'      => 'Vor',
            'lastname'       => 'Sportler' . $s,
            'grade'          => '13',
        ]);

        $teacher = Teacher::create([
            'shortcode' => 'S' . $s,
            'firstname' => 'Test',
            'lastname'  => 'Lehrer' . $s,
        ]);

        $course = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id'     => $this->fachId($fachCode),
            'course_code'    => $courseVorsatz . $s,
            'teacher_id'     => $teacher->id,
            'level'          => 'erhoeht',
        ]);

        $studentExam = StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $teacher->id,
            'exam_type'   => 'written',
        ]);

        if ($praxis !== null) {
            ZusatzpruefExamScore::create([
                'student_id'      => $student->id,
                'student_exam_id' => $studentExam->id,
                'exam_period_id'  => $this->examPeriod->id,
                'score'           => $written,
                'practical_grade' => $praxis,
            ]);
        }

        return Zusatzpruefung::create([
            'exam_period_id'            => $this->examPeriod->id,
            'student_id'                => $student->id,
            'student_exam_id'           => $studentExam->id,
            'status'                    => 'placed',
            'written_points'            => $written,
            'target_points'             => $target,
            'improvement_only'          => $improvementOnly,
            'improvement_points_needed' => $improvementNeeded,
        ]);
    }

    /**
     * Der gewöhnliche Fall bleibt, wie er war: Anlage 12 rundet erst am
     * Endergebnis, Bezugsgröße ist schriftlich × 4.
     */
    public function test_ohne_sport_bleibt_die_verbesserung_unveraendert(): void
    {
        $this->boot();

        // schriftlich 8, mündlich 10 → round(26 × 4 / 3) = 35, Bezug 32
        $zp = $this->makeZp('D', written: 8, target: 10);

        $this->assertFalse($zp->istSport());
        $this->assertSame(35, $zp->getExamResultPoints());
        $this->assertSame(3, $zp->getPointsDelta());

    }

    /**
     * Im Sport zählt die Praxisnote auf beiden Seiten mit.
     *
     * Theorie 12, Praxis 10, mündlich 12:
     *   mit ZP    round((10 + 12 + 12) × 4 / 3) = round(45,33) = 45
     *   ohne ZP   (12 + 10) × 2                                = 44
     *   Verbesserung                                           = +1
     *
     * Die alte Rechnung kam auf round((2 × 12 + 12) × 4 / 3) = 48 gegen 12 × 4
     * = 48, also auf eine Verbesserung von 0 — die Praxisnote fehlte ganz.
     */
    public function test_im_sport_zaehlt_die_praxisnote_auf_beiden_seiten(): void
    {
        $this->boot();

        $zp = $this->makeZp('Smw', written: 12, target: 12, praxis: 10, courseVorsatz: '2Smw');

        $this->assertTrue($zp->istSport());
        $this->assertSame(10, $zp->sportPraxisnote());
        $this->assertSame(45, $zp->getExamResultPoints());
        $this->assertSame(1, $zp->getPointsDelta());

    }

    /**
     * Zur Probe: die Verbesserung ist genau dann 0, wenn die mündliche Note
     * dem Schnitt aus Theorie und Praxis entspricht — dem Wert, mit dem das
     * Fach ohne Zusatzprüfung in einfacher Wertung stünde.
     */
    public function test_im_sport_ist_die_verbesserung_beim_schnitt_null(): void
    {
        $this->boot();

        // (12 + 10) / 2 = 11
        $zp = $this->makeZp('Smw', written: 12, target: 11, praxis: 10, courseVorsatz: '2Smw');

        $this->assertSame(44, $zp->getExamResultPoints());
        $this->assertSame(0, $zp->getPointsDelta());

    }

    /**
     * Ohne Praxisnote ist im Sport nichts berechenbar — und eine Zahl, die
     * so tut als wäre sie es, wäre schlimmer als keine. Die Übersicht zeigt
     * dann „→ ?" statt einer falschen Prognose.
     */
    public function test_im_sport_ohne_praxisnote_gibt_es_kein_ergebnis(): void
    {
        $this->boot();

        $zp = $this->makeZp('Smw', written: 12, target: 12, praxis: null, courseVorsatz: '2Smw');

        $this->assertTrue($zp->istSport());
        $this->assertNull($zp->sportPraxisnote());
        $this->assertNull($zp->getExamResultPoints());
        $this->assertNull($zp->getCombinedPoints());
        $this->assertNull($zp->getPointsDelta());

    }

    /**
     * Die Niederschrift stellt dieselbe Frage („Verbesserung erreicht?") und
     * muss dieselbe Antwort geben. Gefordert ist 1 Punkt Verbesserung; der
     * Fall aus dem Test oben erreicht genau 1 und gehört damit unter
     * „improved". Die frühere Rechnung über calcImprovementMinOral() kam auf
     * 0 und hätte den Prüfling ins Protokoll als „Ziel verfehlt" geschrieben.
     */
    public function test_die_niederschrift_beurteilt_die_sport_verbesserung_richtig(): void
    {
        $this->boot();

        $this->makeZp(
            'Smw', written: 12, target: 12, praxis: 10,
            improvementOnly: true, improvementNeeded: 1, courseVorsatz: '2Smw',
        );

        $r = app(ExamBoardMinutesService::class)->results($this->examPeriod);

        $this->assertSame(1, count($r['improved']), 'Das Ziel ist erreicht');
        $this->assertSame([], $r['notImproved']);

    }

    /**
     * Und die Gegenprobe: zwei Punkte gefordert, einer erreicht.
     */
    public function test_die_niederschrift_erkennt_die_verfehlte_sport_verbesserung(): void
    {
        $this->boot();

        $this->makeZp(
            'Smw', written: 12, target: 12, praxis: 10,
            improvementOnly: true, improvementNeeded: 2, courseVorsatz: '2Smw',
        );

        $r = app(ExamBoardMinutesService::class)->results($this->examPeriod);

        $this->assertSame([], $r['improved']);
        $this->assertSame(1, count($r['notImproved']));

    }
}
