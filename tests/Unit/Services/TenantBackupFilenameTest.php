<?php

namespace Tests\Unit\Services;

use App\Services\TenantBackupService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sicherheits-Regressionstests für den Path-Traversal-Schutz beim
 * Backup-Handling (Security-Scan 2026-07, Finding M1).
 *
 * sanitizeBackupFilename() muss Verzeichnisanteile entfernen (basename) und
 * nur ein striktes Namensmuster (backup_….sqlite[.gz]) zulassen. Reiner
 * Logiktest ohne DB/Tenancy – die Methode ist statisch.
 */
class TenantBackupFilenameTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function validFilenames(): array
    {
        return [
            'sqlite gepackt'   => ['backup_2026-07-03_12-00-00.sqlite.gz'],
            'mit art'          => ['backup_2026-07-03_12-00-00_vor-update.sqlite.gz'],
            'sqlite ungepackt' => ['backup_2026-07-03_12-00-00.sqlite'],
            'nur ziffern'      => ['20260703.sqlite.gz'],
        ];
    }

    #[DataProvider('validFilenames')]
    public function test_gueltige_dateinamen_werden_unveraendert_akzeptiert(string $filename): void
    {
        $this->assertSame($filename, TenantBackupService::sanitizeBackupFilename($filename));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function traversalFilenames(): array
    {
        return [
            'parent env'       => ['../../../../.env'],
            'absoluter pfad'   => ['/etc/passwd'],
            'traversal sql'    => ['../school_9999/backup.sql'],
            'nur traversal'    => ['../../secret.sql'],
        ];
    }

    /**
     * Path-Traversal-Versuche dürfen NICHT das Zielverzeichnis verlassen:
     * Entweder wird durch basename() nur der reine Dateiname übrig gelassen
     * (und dann ggf. akzeptiert, aber ohne Verzeichniswechsel), oder der Name
     * verstößt gegen das Muster und wird abgelehnt. In keinem Fall darf das
     * Ergebnis noch einen Verzeichnisseparator enthalten.
     */
    #[DataProvider('traversalFilenames')]
    public function test_traversal_wird_neutralisiert_oder_abgelehnt(string $filename): void
    {
        try {
            $result = TenantBackupService::sanitizeBackupFilename($filename);
            // Wenn akzeptiert: garantiert kein Pfadseparator mehr enthalten.
            $this->assertStringNotContainsString('/', $result);
            $this->assertStringNotContainsString('\\', $result);
            $this->assertStringNotContainsString('..', $result);
        } catch (\InvalidArgumentException $e) {
            // Ablehnung ist ebenfalls ein gültiges Ergebnis.
            $this->assertTrue(true);
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidFilenames(): array
    {
        return [
            'falsche endung'   => ['backup.txt'],
            'alter mysql-dump' => ['backup_20260703_120000.sql.gz'],
            'php-datei'        => ['shell.php'],
            'keine endung'     => ['backup'],
            'leerzeichen'      => ['backup 2026.sql evil'],
            'nullbyte-artig'   => ['backup.sql\0.php'],
        ];
    }

    #[DataProvider('invalidFilenames')]
    public function test_ungueltige_dateinamen_werden_abgelehnt(string $filename): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TenantBackupService::sanitizeBackupFilename($filename);
    }
}
