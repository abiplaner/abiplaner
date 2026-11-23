<?php

namespace Tests\Tenant;

use App\Services\TenantBackupService;
use Illuminate\Support\Facades\DB;
use Tests\Tenant\Concerns\MitDateiDatenbank;

/**
 * Modul 1.2 – Backup-Strategie überprüfen.
 *
 * Bisher war das Backup/Restore-System nur manuell über eine Checkliste
 * getestet. Diese Tests verifizieren den
 * kompletten Roundtrip (Sicherung UND Wiedereinspielung) automatisiert gegen
 * eine Datenbankdatei (MitDateiDatenbank) — seit 22.11.2026 die eine
 * SQLite-Datei der Anwendung.
 */
class BackupRestoreTest extends TenantTestCase
{
    use MitDateiDatenbank;

    private function pfad(array $sicherung): string
    {
        return (new TenantBackupService())->sicherungsVerzeichnis() . DIRECTORY_SEPARATOR . $sicherung['filename'];
    }

    public function test_backup_erstellt_sql_und_metadaten_datei(): void
    {
        $school = $this->createTestSchool();

        $service = new TenantBackupService();
        $result = $service->createBackup($school, 'roundtrip_test');

        $fullPath = $this->pfad($result);

        $this->assertFileExists($fullPath, 'Sicherungsdatei wurde nicht erstellt.');
        $this->assertFileExists($fullPath . '.meta.json', 'Metadaten-Datei wurde nicht erstellt.');
        $this->assertGreaterThan(0, $result['size']);

        $metadata = $result['metadata'];
        $this->assertSame(self::TEST_SCHOOL_NUMBER, $metadata['school_number']);
        $this->assertContains('teachers', $metadata['tables']);
        $this->assertGreaterThan(0, $metadata['migration_count']);
    }

    public function test_restore_stellt_urspruengliche_daten_wieder_her(): void
    {
        $school = $this->createTestSchool();

        DB::table('teachers')->insert([
            'shortcode' => 'ABC',
            'firstname' => 'Anna',
            'lastname' => 'Beispiel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new TenantBackupService();
        $backup = $service->createBackup($school, 'vor_aenderung');

        // Daten nach dem Backup verändern: bestehenden Lehrer löschen,
        // neuen (nicht gesicherten) Lehrer anlegen.
        DB::table('teachers')->where('shortcode', 'ABC')->delete();
        DB::table('teachers')->insert([
            'shortcode' => 'XYZ',
            'firstname' => 'Nicht',
            'lastname' => 'Gesichert',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service->restoreBackup($school, $backup['filename']);

        $shortcodes = DB::table('teachers')->pluck('shortcode')->toArray();

        $this->assertContains('ABC', $shortcodes, 'Wiederhergestellter Lehrer fehlt nach Restore.');
        $this->assertNotContains('XYZ', $shortcodes, 'Nach dem Backup angelegter Lehrer sollte durch Restore wieder verschwunden sein.');
    }

    public function test_beschaedigtes_backup_wird_bei_restore_abgelehnt(): void
    {
        $school = $this->createTestSchool();

        $service = new TenantBackupService();
        $backup = $service->createBackup($school, 'checksum_test');

        $fullPath = $this->pfad($backup);
        file_put_contents($fullPath, 'manipulierter inhalt', FILE_APPEND);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/beschädigt|manipuliert/');

        $service->restoreBackup($school, $backup['filename']);
    }

    public function test_delete_backup_entfernt_sql_und_metadaten_datei(): void
    {
        $school = $this->createTestSchool();

        $service = new TenantBackupService();
        $backup = $service->createBackup($school, 'delete_test');

        $fullPath = $this->pfad($backup);
        $this->assertFileExists($fullPath);
        $this->assertFileExists($fullPath . '.meta.json');

        $service->deleteBackup($school, $backup['filename']);

        $this->assertFileDoesNotExist($fullPath);
        $this->assertFileDoesNotExist($fullPath . '.meta.json');
    }
}
