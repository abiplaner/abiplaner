<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Services\ImportLeitfadenService;
use App\Services\ImportService;
use App\Services\StudentCsvImportService;
use App\Services\TimetableImportService;
use Illuminate\Support\Facades\Route;

/**
 * Geführte Importe (Roadmap 6.2, zweite Stufe).
 *
 * Der Kern dieser Tests ist einer: **Jede ausgelieferte Vorlage muss durch den
 * Import laufen, für den sie gedacht ist.** Eine Beispieldatei, die die eigene
 * Maske abweist, ist schlimmer als gar keine — der Anwender sucht den Fehler
 * dann bei sich und hat keine Möglichkeit, ihn zu finden.
 *
 * Die Vorlagen werden deshalb nicht auf ihren Wortlaut geprüft, sondern durch
 * die echten Importdienste geschickt.
 */
class ImportLeitfadenTest extends TenantTestCase
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

    private function leitfaeden(): ImportLeitfadenService
    {
        return app(ImportLeitfadenService::class);
    }

    /** Schreibt die Vorlage so auf die Platte, wie der Nutzer sie herunterlädt. */
    private function vorlageAlsDatei(string $schluessel): string
    {
        $inhalt = $this->leitfaeden()->vorlage($schluessel);
        $this->assertNotNull($inhalt, "Keine Vorlage für {$schluessel}.");

        $leitfaden = $this->leitfaeden()->fuer($schluessel);
        $this->assertNotNull($leitfaden);

        $pfad = sys_get_temp_dir() . '/vorlage_' . $schluessel . '_' . uniqid() . '_' . $leitfaden['dateiname'];
        file_put_contents($pfad, $inhalt);
        $this->tmpFiles[] = $pfad;

        return $pfad;
    }

    public function test_die_lehrkraefte_vorlage_laeuft_durch_ihren_import(): void
    {
        $school = $this->createTestSchool();


        try {
            $datei = $this->vorlageAlsDatei(ImportLeitfadenService::LEHRKRAEFTE);

            $ergebnis = app(ImportService::class)->importTeachersFromCsv(
                (string) $school->getKey(),
                $datei
            );

            $this->assertSame(3, $ergebnis['imported'], 'Alle drei Beispielzeilen müssen ankommen.');
            $this->assertSame([], $ergebnis['errors']);

            foreach (['MUS', 'BSP', 'PRB'] as $kuerzel) {
                $this->assertTrue(
                    Teacher::where('shortcode', $kuerzel)->exists(),
                    "Die Lehrkraft {$kuerzel} aus der Vorlage fehlt."
                );
            }

            $anna = Teacher::where('shortcode', 'MUS')->firstOrFail();
            $this->assertSame('Muster', $anna->lastname);
            $this->assertSame('Anna', $anna->firstname);
        } finally {
        }
    }

    public function test_die_schueler_vorlage_laeuft_durch_ihre_vorschau(): void
    {
        $school = $this->createTestSchool();


        try {
            // Die Anleitung sagt: erst die Lehrkraefte. Genau so steht der Test
            // hier -- und prueft damit gleich mit, dass die Kuerzel der beiden
            // Vorlagen zueinander passen.
            foreach (['MUS' => 'Muster', 'BSP' => 'Beispiel', 'PRB' => 'Probe'] as $kuerzel => $nachname) {
                Teacher::create([
                    'shortcode' => $kuerzel,
                    'firstname' => 'Vorlage',
                    'lastname'  => $nachname,
                ]);
            }

            $periode = ExamPeriod::query()->orderByDesc('id')->first();
            $this->assertNotNull($periode, 'Die Grundausstattung sollte einen Pruefungszeitraum anlegen.');

            $datei = $this->vorlageAlsDatei(ImportLeitfadenService::SCHUELER);

            $vorschau = app(StudentCsvImportService::class)->parseCSVPreview($datei, ';', $periode);

            $this->assertCount(2, $vorschau['students'], 'Die Vorlage beschreibt zwei Prueflinge.');
            $this->assertSame(2, $vorschau['stats']['valid_students']);
            $this->assertSame(0, $vorschau['stats']['skipped_students']);

            // Fuenf Pruefungen je Prueflinge: S1 bis S3 schriftlich, K1 und K2
            // muendlich. Eine verschobene Spalte faellt hier auf.
            foreach (['s1', 's2', 's3', 'k1', 'k2'] as $slot) {
                $this->assertSame(0, $vorschau['stats']['missing_' . $slot], "In der Vorlage fehlt {$slot}.");
            }

            // Kein Fach und kein Kuerzel der Vorlage darf unbekannt sein --
            // sonst schickt die Anleitung den Anwender mit einer Datei los,
            // die seine eigene Vorschau rot anstreicht.
            foreach ($vorschau['students'] as $schueler) {
                $this->assertFalse($schueler['has_errors'], 'Die Vorlage erzeugt Validierungsfehler.');
                $this->assertTrue($schueler['will_be_imported']);

                foreach (['s1', 's2', 's3', 'k1', 'k2'] as $slot) {
                    $this->assertTrue($schueler[$slot]['subject_valid'], "Fach in {$slot} unbekannt.");
                    $this->assertTrue($schueler[$slot]['teacher_valid'], "Lehrkraft in {$slot} unbekannt.");
                }
            }

            $namen = array_map(
                static fn (array $s): string => $s['lastname'],
                $vorschau['students']
            );
            sort($namen);
            $this->assertSame(['Beispiel', 'Muster'], $namen);
        } finally {
        }
    }

    public function test_die_stundenplan_vorlage_laeuft_durch_ihren_import(): void
    {
        $school = $this->createTestSchool();


        try {
            // Die Kuerzel sind in allen drei Vorlagen dieselben; hier stehen sie
            // schon in der Tabelle, weil die Anleitung genau diese Reihenfolge
            // empfiehlt.
            foreach (['MUS' => 'Muster', 'BSP' => 'Beispiel', 'PRB' => 'Probe'] as $kuerzel => $nachname) {
                Teacher::create([
                    'shortcode' => $kuerzel,
                    'firstname' => 'Vorlage',
                    'lastname'  => $nachname,
                ]);
            }

            $datei = $this->vorlageAlsDatei(ImportLeitfadenService::STUNDENPLAN);

            $ergebnis = app(TimetableImportService::class)->importFile($datei, '2026/27');

            $this->assertSame(4, $ergebnis['imported'], 'Alle vier Beispielzeilen müssen ankommen.');
            $this->assertSame(0, $ergebnis['stats']['errors'], 'Die Vorlage darf keine fehlerhafte Zeile enthalten.');
            $this->assertSame(0, $ergebnis['without_teacher'], 'Die Kürzel der Vorlage müssen auflösbar sein.');
            $this->assertSame(4, TimetableEntry::where('school_year', '2026/27')->count());
        } finally {
        }
    }

    /**
     * Zwei Eigenschaften, die keine Prosa sind, sondern brechen würden:
     * Ein BOM stünde mitten im ersten Feld — die erste Spalte wäre dann keine
     * Zahl mehr und die Zeile fiele durch die Prüfung. Und die Untis-Vorlage
     * darf keine Kopfzeile tragen, weil der Parser sie als fehlerhafte Zeile
     * zählen würde.
     */
    public function test_die_vorlagen_sind_so_gebaut_wie_die_parser_sie_erwarten(): void
    {
        $dienst = $this->leitfaeden();

        foreach ($dienst->alle() as $leitfaden) {
            $inhalt = $dienst->vorlage($leitfaden['schluessel']);
            $this->assertNotNull($inhalt);

            $this->assertStringStartsNotWith(
                "\xEF\xBB\xBF",
                $inhalt,
                "Die Vorlage {$leitfaden['dateiname']} traegt ein BOM."
            );
            $this->assertStringEndsWith("\r\n", $inhalt);

            $ersteZeile = strtok($inhalt, "\r\n");
            $this->assertIsString($ersteZeile);

            if ($leitfaden['kopfzeile']) {
                $this->assertStringContainsString($leitfaden['trennzeichen'], $ersteZeile);
                $this->assertFalse(is_numeric(explode($leitfaden['trennzeichen'], $ersteZeile)[0]));
            } else {
                $this->assertTrue(
                    is_numeric(explode($leitfaden['trennzeichen'], $ersteZeile)[0]),
                    "Die Vorlage {$leitfaden['dateiname']} soll ohne Kopfzeile beginnen."
                );
            }
        }
    }

    public function test_jeder_leitfaden_verweist_auf_eine_vorhandene_maske(): void
    {
        foreach ($this->leitfaeden()->alle() as $leitfaden) {
            $this->assertNotNull($leitfaden['route']);
            $this->assertTrue(
                Route::has($leitfaden['route']),
                "Die Route {$leitfaden['route']} gibt es nicht (mehr)."
            );
        }

        $this->assertTrue(Route::has('setup.imports'));
        $this->assertTrue(Route::has('setup.imports.template'));
        $this->assertNull($this->leitfaeden()->vorlage('gibt-es-nicht'));
    }
}
