<?php

namespace Tests\Unit\Services;

use App\Services\AbsenceCsvImportService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Prüft das Parsen der Freitext-Spalte "Art der Absenz" aus dem
 * Infoportal-Export sowie die Normalisierung des Entschuldigungsstatus
 * (Roadmap 8.1). Reine Logiktests ohne DB.
 *
 * Ausführen: docker compose exec abiplaner_app php vendor/bin/phpunit --filter AbsenceCsvImport
 */
class AbsenceCsvImportServiceTest extends TestCase
{
    public function test_krank_ohne_stundenangabe(): void
    {
        $this->assertSame(['krank', null, null, null], AbsenceCsvImportService::normalizeArt('krank'));
    }

    public function test_krank_mit_stundenbereich(): void
    {
        $this->assertSame(
            ['krank', 5, 12, null],
            AbsenceCsvImportService::normalizeArt('krank 5. bis 12. Stunde')
        );
    }

    public function test_tageweise_befreit(): void
    {
        $this->assertSame(
            ['tageweise_befreit', null, null, null],
            AbsenceCsvImportService::normalizeArt('tageweise befreit')
        );
    }

    public function test_verspaetet(): void
    {
        $this->assertSame(
            ['verspaetet', 1, 1, 9],
            AbsenceCsvImportService::normalizeArt('verspätet um 9 Minuten in der 1. Stunde')
        );
    }

    public function test_befreit_einzelstunde(): void
    {
        $this->assertSame(
            ['befreit', 11, 11, null],
            AbsenceCsvImportService::normalizeArt('befreit in 11. Stunde')
        );
    }

    public function test_befreit_stundenbereich(): void
    {
        $this->assertSame(
            ['befreit', 1, 4, null],
            AbsenceCsvImportService::normalizeArt('befreit 1. bis 4. Stunde')
        );
    }

    public function test_fehlt_einzelstunde(): void
    {
        $this->assertSame(
            ['fehlt', 3, 3, null],
            AbsenceCsvImportService::normalizeArt('fehlt in 3. Stunde')
        );
    }

    public function test_fehlt_stundenbereich(): void
    {
        $this->assertSame(
            ['fehlt', 1, 2, null],
            AbsenceCsvImportService::normalizeArt('fehlt 1. bis 2. Stunde')
        );
    }

    public function test_unbekanntes_muster_faellt_auf_sonstige_zurueck(): void
    {
        $this->assertSame(
            ['sonstige', null, null, null],
            AbsenceCsvImportService::normalizeArt('irgendein neues Format')
        );
    }

    /** @dataProvider excuseStatusProvider */
    #[DataProvider('excuseStatusProvider')]
    public function test_excuse_status_normalisierung(?string $raw, string $expected): void
    {
        $this->assertSame($expected, AbsenceCsvImportService::normalizeExcuseStatus($raw));
    }

    public static function excuseStatusProvider(): array
    {
        return [
            'schriftlich' => ['schriftlich', 'schriftlich'],
            'muendlich' => ['mündlich', 'muendlich'],
            'nein' => ['nein', 'nein'],
            'attest' => ['Attest', 'attest'],
            'attestpflicht' => ['schriftlich (Attestpflicht)', 'attestpflicht'],
            'leer' => ['', 'offen'],
            'null' => [null, 'offen'],
            'unbekannt' => ['irgendwas', 'offen'],
        ];
    }
}
