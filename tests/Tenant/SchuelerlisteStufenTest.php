<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Schülerliste zeigt eine Jahrgangsstufe, nicht Q12 und Q13 gemischt.
 *
 * Vorgabe ist die Q13 (der Abiturjahrgang), ?stufe=12 zeigt die Q12.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter SchuelerlisteStufen
 */
class SchuelerlisteStufenTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'schuelerliste-stufen-9999@test.invalid';

    protected User $planner;

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

        $zeitraum = ExamPeriod::query()->orderByDesc('year')->firstOrFail();
        foreach (['13' => 'Dreizehner', '12' => 'Zwoelfer', '11' => 'Elfer'] as $stufe => $name) {
            Student::create([
                'exam_period_id' => $zeitraum->id,
                'grade' => (string) $stufe,
                'external_id' => 'stufe-' . $stufe,
                'firstname' => 'Test',
                'lastname' => $name,
            ]);
        }
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    #[Test]
    public function ohne_auswahl_zeigt_die_liste_nur_die_q13(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.index'));

        $antwort->assertOk();
        $antwort->assertSee('Dreizehner');
        $antwort->assertDontSee('Zwoelfer');
        $antwort->assertDontSee('Elfer');
        $antwort->assertSee(route('students.index', ['stufe' => '12']), false);
    }

    #[Test]
    public function mit_stufe_12_zeigt_die_liste_nur_die_q12(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.index', ['stufe' => '12']));

        $antwort->assertOk();
        $antwort->assertSee('Zwoelfer');
        $antwort->assertDontSee('Dreizehner');
        $antwort->assertDontSee('Elfer');
        // Die Exporte folgen der Auswahl.
        $antwort->assertSee(route('students.export.pdf', ['stufe' => '12']), false);
    }

    /**
     * Der gewählte Knopf ist markiert. Am 24.11.2026 war er es nie: PHP machte
     * aus den Schlüsseln '13'/'12' Ganzzahlen, der Vergleich mit dem String
     * $stufe schlug immer fehl.
     */
    #[Test]
    public function der_knopf_der_gewaehlten_stufe_ist_markiert(): void
    {
        foreach (['13' => 'Q13 (Abiturjahrgang)', '12' => 'Q12'] as $stufe => $beschriftung) {
            $html = $this->actingAs($this->planner)
                ->get(route('students.index', ['stufe' => (string) $stufe]))
                ->getContent();

            $this->assertSame(1, substr_count((string) $html, 'aria-current="page"'), "Q{$stufe}: genau ein Knopf markiert");
            $this->assertMatchesRegularExpression(
                '~aria-current="page"[^>]*>(?:(?!</a>).)*' . preg_quote($beschriftung, '~') . '~s',
                (string) $html,
                "Q{$stufe}: der markierte Knopf ist der richtige"
            );
        }
    }

    #[Test]
    public function eine_unbekannte_stufe_faellt_auf_die_q13_zurueck(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.index', ['stufe' => '11']));

        $antwort->assertOk();
        $antwort->assertSee('Dreizehner');
        $antwort->assertDontSee('Elfer');
    }

    /**
     * Die Fächerübersicht zählte über abiturGrades() Q12 und Q13 zusammen —
     * der Import setzt keine Klasse, also fiel niemand heraus.
     */
    #[Test]
    public function die_faecheruebersicht_zaehlt_nur_die_gewaehlte_stufe(): void
    {
        [$fachQ13, $fachQ12] = \App\Models\Subject::whereNotIn('name', ['Deutsch', 'Mathematik'])
            ->orderBy('id')->take(2)->get()->all();

        $this->pruefung('Dreizehner', $fachQ13, 'fu13');
        $this->pruefung('Zwoelfer', $fachQ12, 'fu12');

        foreach (['13' => [$fachQ13, $fachQ12], '12' => [$fachQ12, $fachQ13]] as $stufe => [$eigenes, $fremdes]) {
            $antwort = $this->actingAs($this->planner)->get(route('students.subjects', ['stufe' => (string) $stufe]));

            $antwort->assertOk();
            $faecher = $antwort->viewData('subjectStats')->pluck('subject_name')->all();
            $this->assertContains($eigenes->name, $faecher, "Q{$stufe}: eigenes Fach fehlt");
            $this->assertNotContains($fremdes->name, $faecher, "Q{$stufe}: Fach der anderen Stufe gezählt");
            $this->assertSame(1, $antwort->viewData('plausibility')['student_count']);
            $antwort->assertSee(route('students.subjects.export.excel', ['stufe' => (string) $stufe]), false);
            $this->assertSame(1, substr_count((string) $antwort->getContent(), 'aria-current="page"'));
        }
    }

    private function pruefung(string $nachname, \App\Models\Subject $fach, string $kurscode): void
    {
        $schueler = Student::where('lastname', $nachname)->firstOrFail();
        $lehrkraft = \App\Models\Teacher::create([
            'shortcode' => strtoupper($kurscode),
            'firstname' => 'Test',
            'lastname' => 'Fach',
        ]);
        $kurs = \App\Models\Course::create([
            'exam_period_id' => $schueler->exam_period_id,
            'subject_id' => $fach->id,
            'course_code' => $kurscode,
            'teacher_id' => $lehrkraft->id,
            'level' => 'grundlegend',
        ]);
        \App\Models\StudentExam::create([
            'student_id' => $schueler->id,
            'course_id' => $kurs->id,
            'exam_type' => 'written',
        ]);
    }

    #[Test]
    public function der_excel_export_enthaelt_nur_die_gewaehlte_stufe(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('students.export.excel', ['stufe' => '12']));

        $antwort->assertOk();
        $datei = $antwort->baseResponse->getFile()->getPathname();
        $blatt = \PhpOffice\PhpSpreadsheet\IOFactory::load($datei)->getActiveSheet()->toArray();
        $text  = json_encode($blatt, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Zwoelfer', (string) $text);
        $this->assertStringNotContainsString('Dreizehner', (string) $text);
        $this->assertStringNotContainsString('Elfer', (string) $text);
    }
}
