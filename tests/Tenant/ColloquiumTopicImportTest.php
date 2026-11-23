<?php

namespace Tests\Tenant;

use App\Models\ColloquiumTopic;
use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Teacher;
use App\Services\ColloquiumTopicImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Modul 4.7 – Import des Kolloquiums-Themenkatalogs aus der Vorlage
 * "Kolloquium-Themen-Q12.xlsx".
 *
 * Die Testdatei wird zur Laufzeit im Original-Layout erzeugt (Titelzeile,
 * gemergte Halbjahres-Header, Spaltenköpfe in Zeile 3, Daten ab Zeile 4),
 * damit sie zu den Kurskürzeln der Testschule passt.
 */
class ColloquiumTopicImportTest extends TenantTestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tmpFiles = [];

        parent::tearDown();
    }

    /**
     * Schreibt eine Vorlage im Layout der Originaldatei.
     *
     * @param list<array{0: string, 1: string, 2: string, 3: list<string>, 4: list<string>}> $rows
     *        [Kurs, Lehrkraft, Fach, Themen 12/1, Themen 12/2]
     */
    private function makeTemplate(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Themengebiete Kolloquium Abiturjahrgang 2025-27');
        $sheet->mergeCells('A1:J1');

        $sheet->setCellValue('E2', '12/1');
        $sheet->mergeCells('E2:G2');
        $sheet->setCellValue('H2', '12/2');
        $sheet->mergeCells('H2:J2');

        foreach (['A3' => 'Nr.', 'B3' => 'Kurs', 'C3' => 'Lehrkraft', 'D3' => 'Fach',
                  'E3' => 'Thema 1', 'F3' => 'Thema 2', 'G3' => 'Thema 3',
                  'H3' => 'Thema 1', 'I3' => 'Thema 2', 'J3' => 'Thema 3'] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $rowIndex = 4;
        foreach ($rows as $i => [$code, $teacher, $subject, $first, $second]) {
            $sheet->setCellValue('A' . $rowIndex, (string) ($i + 1));
            $sheet->setCellValue('B' . $rowIndex, $code);
            $sheet->setCellValue('C' . $rowIndex, $teacher);
            $sheet->setCellValue('D' . $rowIndex, $subject);

            foreach (['E', 'F', 'G'] as $slot => $column) {
                if (isset($first[$slot])) {
                    $sheet->setCellValue($column . $rowIndex, $first[$slot]);
                }
            }

            foreach (['H', 'I', 'J'] as $slot => $column) {
                if (isset($second[$slot])) {
                    $sheet->setCellValue($column . $rowIndex, $second[$slot]);
                }
            }

            $rowIndex++;
        }

        $stub = tempnam(sys_get_temp_dir(), 'kollo');
        $path = $stub . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);

        $this->tmpFiles[] = $stub;
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function makeCourse(ExamPeriod $examPeriod, string $code, string $suffix): Course
    {
        $teacher = Teacher::create([
            'shortcode' => 'I' . $suffix,
            'firstname' => 'Imp',
            'lastname'  => 'Ort' . $suffix,
        ]);

        return Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => $code,
            'teacher_id'     => $teacher->id,
            'level'          => 'grundlegend',
        ]);
    }

    public function test_themen_werden_dem_kurs_zugeordnet_importiert(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, '1b1', 'A');

        $file = $this->makeTemplate([
            ['1b1', 'Kreil, Stefan', 'Biologie', ['Genetik', 'Evolution', 'Ökologie'], ['Neurobiologie']],
        ]);

        $result = (new ColloquiumTopicImportService())->import($file, $examPeriod->id);

        $this->assertSame(1, $result['imported_count']);
        $this->assertSame(0, $result['unmatched_count']);
        $this->assertSame(4, $result['topic_count']);

        $topics = ColloquiumTopic::where('course_id', $course->id)->get();
        $this->assertCount(4, $topics);

        $first = $topics->firstWhere('semester', '12/1');
        $this->assertSame('Genetik', $topics->where('semester', '12/1')->firstWhere('slot', 1)->title);
        $this->assertSame('Ökologie', $topics->where('semester', '12/1')->firstWhere('slot', 3)->title);
        $this->assertSame('Neurobiologie', $topics->where('semester', '12/2')->firstWhere('slot', 1)->title);
        $this->assertSame('12/1', $first->semester);

    }

    public function test_kurskuerzel_wird_case_insensitive_zugeordnet(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, '1B-L', 'B');

        // Vorlage schreibt das Kürzel klein — muss trotzdem greifen.
        $file = $this->makeTemplate([
            ['1b-l', 'Kaltenbrunner, Christoph', 'Biologie', ['Zellbiologie'], []],
        ]);

        $result = (new ColloquiumTopicImportService())->import($file, $examPeriod->id);

        $this->assertSame(1, $result['imported_count']);
        $this->assertSame(1, ColloquiumTopic::where('course_id', $course->id)->count());

    }

    public function test_unbekannter_kurs_wird_gemeldet_statt_still_verworfen(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $this->makeCourse($examPeriod, '2b1', 'C');

        // Vorlage des falschen Jahrgangs (Präfix 1 statt 2).
        $file = $this->makeTemplate([
            ['1b1', 'Kreil, Stefan', 'Biologie', ['Genetik'], []],
        ]);

        $result = (new ColloquiumTopicImportService())->import($file, $examPeriod->id);

        $this->assertSame(0, $result['imported_count']);
        $this->assertSame(1, $result['unmatched_count']);
        $this->assertSame(0, $result['topic_count']);
        $this->assertSame(['1b1'], $result['unmatched_codes']);
        $this->assertSame(0, ColloquiumTopic::count());

    }

    public function test_erneuter_import_ersetzt_themen_ohne_dubletten(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, '1d1', 'D');

        $first = $this->makeTemplate([
            ['1d1', 'Schwaiger, Vanessa', 'Deutsch', ['Lyrik', 'Drama', 'Epik'], ['Sprache']],
        ]);
        (new ColloquiumTopicImportService())->import($first, $examPeriod->id);

        // Korrigierte Vorlage: weniger Themen, andere Titel.
        $second = $this->makeTemplate([
            ['1d1', 'Schwaiger, Vanessa', 'Deutsch', ['Lyrik der Moderne'], []],
        ]);
        $result = (new ColloquiumTopicImportService())->import($second, $examPeriod->id);

        $this->assertSame(1, $result['topic_count']);

        $topics = ColloquiumTopic::where('course_id', $course->id)->get();
        $this->assertCount(1, $topics);
        $this->assertSame('Lyrik der Moderne', $topics->first()->title);

    }

    public function test_leere_zeilen_werden_uebersprungen(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $this->makeCourse($examPeriod, '1c1', 'E');

        $file = $this->makeTemplate([
            ['1c1', 'Dorner, Maria', 'Chemie', ['Organik'], []],
            ['', '', '', [], []],
        ]);

        $result = (new ColloquiumTopicImportService())->import($file, $examPeriod->id);

        $this->assertSame(1, $result['imported_count']);
        $this->assertSame(0, $result['unmatched_count']);

    }

    /**
     * Kernfall der Praxis: Die Vorlage läuft nach und nach ein. Ein Kurs, für
     * den noch keine Themen eingetragen sind, darf beim Re-Import nicht die
     * bereits erfassten Themen verlieren.
     */
    public function test_kurs_ohne_themen_loescht_bestehende_themen_nicht(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $course = $this->makeCourse($examPeriod, '1b1', 'F');

        $service = new ColloquiumTopicImportService();

        // Erster Durchlauf: Themen sind da.
        $full = $this->makeTemplate([
            ['1b1', 'Kreil, Stefan', 'Biologie', ['Genetik', 'Evolution'], []],
        ]);
        $service->import($full, $examPeriod->id);
        $this->assertSame(2, ColloquiumTopic::where('course_id', $course->id)->count());

        // Zweiter Durchlauf mit einer Vorlage, in der dieser Kurs (noch) leer
        // ist — etwa weil eine ältere Fassung erneut hochgeladen wird.
        $empty = $this->makeTemplate([
            ['1b1', 'Kreil, Stefan', 'Biologie', [], []],
        ]);
        $result = $service->import($empty, $examPeriod->id);

        $this->assertSame(0, $result['imported_count']);
        $this->assertSame(1, $result['skipped_empty_count']);
        $this->assertSame(
            2,
            ColloquiumTopic::where('course_id', $course->id)->count(),
            'Bestehende Themen dürfen durch eine leere Kurszeile nicht gelöscht werden.'
        );

    }

    public function test_import_kann_auf_ausgewaehlte_kurse_beschraenkt_werden(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();
        $wanted   = $this->makeCourse($examPeriod, '1b1', 'G');
        $skipped  = $this->makeCourse($examPeriod, '1c1', 'H');

        $file = $this->makeTemplate([
            ['1b1', 'Kreil, Stefan', 'Biologie', ['Genetik'], []],
            ['1c1', 'Dorner, Maria', 'Chemie', ['Organik'], []],
        ]);

        // Die OSK hat in der Vorschau nur 1b1 zur Übernahme markiert.
        $result = (new ColloquiumTopicImportService())->import($file, $examPeriod->id, ['1b1']);

        $this->assertSame(1, $result['imported_count']);
        $this->assertSame(1, ColloquiumTopic::where('course_id', $wanted->id)->count());
        $this->assertSame(0, ColloquiumTopic::where('course_id', $skipped->id)->count());

    }
}
