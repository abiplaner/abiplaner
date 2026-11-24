<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentCsvImportService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Der Schülerimport lehnt Dateien ab, deren Kurse nicht zur Stufe passen.
 *
 * Kurse der Q12 beginnen mit „1“, der Q13 mit „2“, jahrgangsübergreifende
 * mit „3“. Am 22.10.2026 kam die Q12 als Stufe 13 ins Abitur 2027 — die
 * Erkennung der Stufe war nur ein Vorschlag. Jetzt bricht der Import ab,
 * und zwar bevor die bestehenden Daten gelöscht werden.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter ImportStufenpruefung
 */
class ImportStufenpruefungTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'import-stufe-9999@test.invalid';

    protected User $planner;

    protected ExamPeriod $zeitraum;

    protected StudentCsvImportService $import;

    /** @var list<string> */
    private array $dateien = [];

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

        $this->zeitraum = ExamPeriod::query()->orderByDesc('year')->firstOrFail();
        $this->import   = app(StudentCsvImportService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->dateien as $datei) {
            @unlink($datei);
        }
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    #[Test]
    public function q13_kurse_lassen_sich_nicht_in_die_q12_importieren(): void
    {
        $datei = $this->csv('2d1', '2m1', '2e1', '2g1', '2b1');

        $this->assertSame(['2b1', '2d1', '2e1', '2g1', '2m1'], $this->import->kurseFremderStufe($datei, ';', '12'));

        try {
            $this->import->processCSV($datei, ';', $this->zeitraum, [], '12');
            $this->fail('Der Import hätte die Q13-Datei als Q12 ablehnen müssen.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('2d1', $e->getMessage());
        }

        $this->assertSame(0, Student::where('exam_period_id', $this->zeitraum->id)->where('lastname', 'Prüfling')->count());
    }

    #[Test]
    public function q12_kurse_lassen_sich_nicht_in_die_q13_importieren(): void
    {
        $datei = $this->csv('1d1', '1m1', '1e1', '1g1', '1b1');

        $this->expectException(\InvalidArgumentException::class);
        $this->import->processCSV($datei, ';', $this->zeitraum, [], '13');
    }

    #[Test]
    public function jahrgangsuebergreifende_kurse_mit_3_sind_in_beiden_stufen_erlaubt(): void
    {
        $this->assertSame([], $this->import->kurseFremderStufe($this->csv('1d1', '1m1', '1e1', '3inf1', '3l1'), ';', '12'));
        $this->assertSame([], $this->import->kurseFremderStufe($this->csv('2d1', '2m1', '2e1', '3inf1', '3l1'), ';', '13'));
    }

    /**
     * Der eigentliche Schaden wäre das Löschen: Mit „Bestehende Daten
     * ersetzen“ räumte der Controller die Stufe ab, bevor processCSV() die
     * Datei prüfte. Die Prüfung muss davor greifen.
     */
    #[Test]
    public function eine_falsche_datei_loescht_keine_bestehenden_schueler(): void
    {
        $bestehend = Student::create([
            'exam_period_id' => $this->zeitraum->id,
            'grade' => '12',
            'external_id' => 'bestand-1',
            'firstname' => 'Bleibt',
            'lastname' => 'Bestand',
        ]);

        $name = 'temp/stufe_' . uniqid() . '.csv';
        @mkdir(storage_path('app/temp'), 0750, true);
        copy($this->csv('2d1', '2m1', '2e1', '2g1', '2b1'), storage_path('app/' . $name));
        $this->dateien[] = storage_path('app/' . $name);

        $antwort = $this->actingAs($this->planner)->post(route('import.process'), [
            'temp_path' => $name,
            'delimiter' => ';',
            'grade' => '12',
            'clear_existing' => '1',
        ]);

        $antwort->assertRedirect(route('import.form'));
        $antwort->assertSessionHasErrors('error');
        $this->assertNotNull(Student::find($bestehend->id), 'Der Q12-Schüler wurde trotz abgelehnter Datei gelöscht.');
    }

    /**
     * Eine Import-Datei im Format des Schülerimports mit einem Prüfling.
     */
    private function csv(string $s1, string $s2, string $s3, string $k1, string $k2): string
    {
        $datei = tempnam(sys_get_temp_dir(), 'stufe_');
        file_put_contents($datei, implode("\r\n", [
            'Nr.;Name, Rufname;S1;;S2;;S3;;K1;;K2;',
            ';Prüfling, Paula;;;;;;;;;;',
            "1;;{$s1};EXTERN;{$s2};EXTERN;{$s3};EXTERN;{$k1};EXTERN;{$k2};EXTERN",
        ]));
        $this->dateien[] = $datei;

        return $datei;
    }
}
