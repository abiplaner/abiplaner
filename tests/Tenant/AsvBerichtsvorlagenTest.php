<?php

namespace Tests\Tenant;

use App\Models\School;
use App\Models\User;
use App\Services\ImportLeitfadenService;
use PHPUnit\Framework\Attributes\Test;
use ZipArchive;

/**
 * ASV-Berichtsvorlagen zum Herunterladen (resources/asv-berichte/).
 *
 * Zwei Dinge muessen halten: Jede im Leitfaden eingetragene Vorlage liegt
 * wirklich als ASV-Berichtsdatei vor — sonst laedt die Schule eine Datei
 * herunter, die ASV beim Import abweist —, und ausgeliefert wird nur, was
 * eingetragen ist. Der Dateiname kommt aus der URL.
 */
class AsvBerichtsvorlagenTest extends TenantTestCase
{
    private const TEST_USER_EMAIL = 'planner-asv-berichte-9999@test.invalid';

    protected School $school;
    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::TEST_USER_EMAIL)->delete();

        $this->school = $this->createTestSchool();
        $this->planner = User::factory()->create([
            'email' => self::TEST_USER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

    }

    protected function tearDown(): void
    {
        User::where('email', self::TEST_USER_EMAIL)->delete();

        parent::tearDown();
    }

    private function leitfaeden(): ImportLeitfadenService
    {
        return app(ImportLeitfadenService::class);
    }

    /**
     * Eine .bsbj ist ein ZIP mit Metadata.json (AsvExpImpId REPORT_GEN) und
     * Objectliste.json. Genau das prueft ASV beim Import zuerst.
     */
    #[Test]
    public function jede_eingetragene_vorlage_ist_eine_asv_berichtsdatei(): void
    {
        $anzahl = 0;

        foreach ($this->leitfaeden()->alle() as $leitfaden) {
            foreach ($leitfaden['asv_berichte'] as $eintrag) {
                $bericht = $this->leitfaeden()->berichtsvorlage($leitfaden['schluessel'], $eintrag['datei']);
                $this->assertNotNull($bericht);
                $this->assertFileExists($bericht['pfad'], "Vorlage {$eintrag['datei']} fehlt in resources/asv-berichte/.");
                $this->assertStringEndsWith('.bsbj', $bericht['anzeigename']);

                $zip = new ZipArchive();
                $this->assertTrue($zip->open($bericht['pfad']) === true, "{$eintrag['datei']} ist kein ZIP.");
                $this->assertStringContainsString('REPORT_GEN', (string) $zip->getFromName('Metadata.json'));
                $this->assertNotFalse($zip->getFromName('Objectliste.json'));
                $zip->close();

                $anzahl++;
            }
        }

        $this->assertGreaterThan(0, $anzahl, 'Kein Leitfaden traegt eine ASV-Berichtsvorlage.');
    }

    #[Test]
    public function die_vorlage_laesst_sich_herunterladen(): void
    {
        $response = $this->actingAs($this->planner)->get(route('setup.imports.asv-report', [
            ImportLeitfadenService::LEHRKRAEFTE,
            'lehrerliste-kuerzel-name-amtsbez.bsbj',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.bsbj', (string) $response->headers->get('Content-Disposition'));
    }

    /** Nur Eingetragenes — kein Weg aus dem Verzeichnis, kein fremder Leitfaden. */
    #[Test]
    public function nicht_eingetragenes_wird_nicht_ausgeliefert(): void
    {
        $this->assertNull($this->leitfaeden()->berichtsvorlage(ImportLeitfadenService::LEHRKRAEFTE, '../../.env'));
        $this->assertNull($this->leitfaeden()->berichtsvorlage(ImportLeitfadenService::SCHUELER, 'lehrerliste-kuerzel-name-amtsbez.bsbj'));
        $this->assertNull($this->leitfaeden()->berichtsvorlage('gibt-es-nicht', 'lehrerliste-kuerzel-name-amtsbez.bsbj'));

        $this->actingAs($this->planner)
            ->get(route('setup.imports.asv-report', [ImportLeitfadenService::LEHRKRAEFTE, 'gibt-es-nicht.bsbj']))
            ->assertNotFound();
        $this->actingAs($this->planner)
            ->get('/einrichtung/importe/lehrkraefte/asv-bericht/..%2F..%2F.env')
            ->assertNotFound();
    }

    /** Lehrerimport-Seite und Import-Leitfaden bieten die Vorlage an — aus demselben Eintrag. */
    #[Test]
    public function importseite_und_leitfaden_zeigen_den_download(): void
    {
        $link = route('setup.imports.asv-report', [
            ImportLeitfadenService::LEHRKRAEFTE,
            'lehrerliste-kuerzel-name-amtsbez.bsbj',
        ]);

        $this->actingAs($this->planner)
            ->get(route('teachers.import.form'))
            ->assertOk()
            ->assertSee($link, false);

        $this->actingAs($this->planner)
            ->get(route('setup.imports'))
            ->assertOk()
            ->assertSee($link, false);
    }
}
