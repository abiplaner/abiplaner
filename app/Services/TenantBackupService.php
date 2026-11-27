<?php

namespace App\Services;

use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sicherungen der Abiplaner-Datenbank (eine SQLite-Datei).
 *
 * Seit 22.11.2026 gibt es nur noch eine Datenbank für genau eine Schule —
 * Sicherung und Wiederherstellung betreffen immer die ganze Datei, also auch
 * die Benutzerkonten. Der Klassenname und der School-Parameter stammen aus
 * der Mandantenzeit; die Schule dient nur noch den Metadaten.
 *
 * Eine Sicherung ist eine konsistente Kopie per VACUUM INTO (auch während
 * gearbeitet wird), mit PHP gepackt: backup_<zeit>[_<slug>].sqlite.gz plus
 * .meta.json. Kein exec(), kein gzip — beides gibt es auf Windows nicht.
 * Ablage: Ordner `sicherungen/` neben der Datenbankdatei.
 */
class TenantBackupService
{
    /** backup_<zeit>[_<slug>].sqlite.gz — nur Wortzeichen, Punkt, Bindestrich. */
    private const BACKUP_FILENAME_PATTERN = '/^[A-Za-z0-9._-]+\.(sqlite\.gz|sqlite)$/';

    /**
     * Validiert und säubert einen vom Client gelieferten Dateinamen (M1):
     * basename() gegen Path-Traversal, dazu ein striktes Namensmuster.
     *
     * @throws \InvalidArgumentException wenn der Name nicht dem Muster entspricht
     */
    public static function sanitizeBackupFilename(string $filename): string
    {
        $filename = basename($filename);

        if (preg_match(self::BACKUP_FILENAME_PATTERN, $filename) !== 1) {
            throw new \InvalidArgumentException("Ungültiger Backup-Dateiname: {$filename}");
        }

        return $filename;
    }

    /** Pfad der Datenbankdatei. */
    public function datenbankDatei(): string
    {
        return (string) config('database.connections.sqlite.database');
    }

    /** Ordner der Sicherungen (neben der Datenbankdatei). */
    public function sicherungsVerzeichnis(?School $school = null): string
    {
        $datei = $this->datenbankDatei();

        // :memory: (Tests) hat keinen Ordner — dirname() ergäbe '.', also
        // das Arbeitsverzeichnis.
        if ($datei === ':memory:' || $datei === '') {
            return storage_path('app' . DIRECTORY_SEPARATOR . 'sicherungen');
        }

        return dirname($datei) . DIRECTORY_SEPARATOR . 'sicherungen';
    }

    /**
     * Sicherung anlegen.
     *
     * @return array{filename: string, path: string, size: int|false, metadata: array<string, mixed>, created_at: \Illuminate\Support\Carbon}
     */
    public function createBackup(?School $school = null, ?string $description = null): array
    {
        $zeit = now()->format('Y-m-d_H-i-s');
        $filename = $description
            ? "backup_{$zeit}_" . \Str::slug($description) . '.sqlite.gz'
            : "backup_{$zeit}.sqlite.gz";

        $ordner = $this->sicherungsVerzeichnis();
        if (! is_dir($ordner) && ! mkdir($ordner, 0775, true) && ! is_dir($ordner)) {
            throw new \Exception("Sicherungsordner {$ordner} lässt sich nicht anlegen.");
        }

        $ziel = $ordner . DIRECTORY_SEPARATOR . $filename;
        $roh = $ordner . DIRECTORY_SEPARATOR . '.entsteht-' . $zeit . '.sqlite';

        try {
            // Konsistente Momentaufnahme, auch im WAL-Modus und bei laufenden
            // Schreibzugriffen. Über eine eigene Verbindung: VACUUM darf nicht
            // innerhalb einer Transaktion laufen, und Aufrufer wie der
            // Schuljahreswechsel sichern mitten in einer. Gesichert wird damit
            // der festgeschriebene Stand.
            $pdo = new \PDO('sqlite:' . $this->datenbankDatei());
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('VACUUM INTO ' . $pdo->quote($roh));
            $pdo = null;

            $this->packen($roh, $ziel);
        } finally {
            if (file_exists($roh)) {
                unlink($roh);
            }
        }

        if (! file_exists($ziel) || filesize($ziel) === 0) {
            throw new \Exception('Backup-Datei wurde nicht korrekt erstellt');
        }

        $metadata = $this->createBackupMetadata($ziel, $description);
        file_put_contents($ziel . '.meta.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info("Sicherung erstellt: {$filename} (" . filesize($ziel) . ' Bytes)');

        return [
            'filename' => $filename,
            'path' => 'sicherungen/' . $filename,
            'size' => filesize($ziel),
            'metadata' => $metadata,
            'created_at' => now(),
        ];
    }

    /**
     * Alle Sicherungen, neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function listBackups(?School $school = null): array
    {
        $ordner = $this->sicherungsVerzeichnis();
        if (! is_dir($ordner)) {
            return [];
        }

        $aktuell = count($this->getMigrationsList());
        $backups = [];

        foreach (array_diff(scandir($ordner) ?: [], ['..', '.']) as $file) {
            if (preg_match(self::BACKUP_FILENAME_PATTERN, $file) !== 1) {
                continue;
            }

            $pfad = $ordner . DIRECTORY_SEPARATOR . $file;
            $metadata = null;
            $compatible = null;
            $outdated = null;

            if (file_exists($pfad . '.meta.json')) {
                $metadata = json_decode((string) file_get_contents($pfad . '.meta.json'), true);
                $imBackup = (int) ($metadata['migration_count'] ?? 0);
                $compatible = $imBackup <= $aktuell;
                $outdated = $imBackup < $aktuell;
            }

            $backups[] = [
                'filename' => $file,
                'path' => 'sicherungen/' . $file,
                'size' => filesize($pfad),
                'size_human' => $this->formatBytes((int) filesize($pfad)),
                'created_at' => Carbon::createFromTimestamp((int) filemtime($pfad)),
                'metadata' => $metadata,
                'compatible' => $compatible,
                'outdated' => $outdated,
            ];
        }

        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    public function deleteBackup(?School $school, string $filename): void
    {
        $filename = self::sanitizeBackupFilename($filename);
        $pfad = $this->sicherungsVerzeichnis() . DIRECTORY_SEPARATOR . $filename;

        if (! file_exists($pfad)) {
            throw new \Exception("Backup-Datei nicht gefunden: {$filename}");
        }

        unlink($pfad);
        if (file_exists($pfad . '.meta.json')) {
            unlink($pfad . '.meta.json');
        }

        Log::info("Sicherung gelöscht: {$filename}");
    }

    /**
     * Datenbank durch eine Sicherung ersetzen.
     *
     * Ablauf: Prüfsumme und Version prüfen → Sicherheitskopie des jetzigen
     * Stands → Sicherung entpacken und prüfen (integrity_check) → Verbindung
     * schließen → Datei austauschen → fehlende Migrationen nachziehen
     * (Sicherung aus einer älteren Programmversion).
     */
    public function restoreBackup(?School $school, string $filename): void
    {
        $filename = self::sanitizeBackupFilename($filename);
        $backupPath = $this->sicherungsVerzeichnis() . DIRECTORY_SEPARATOR . $filename;

        if (! file_exists($backupPath)) {
            throw new \Exception("Backup-Datei nicht gefunden: {$filename}");
        }
        $this->validateBackupCompatibility($backupPath);

        $ziel = $this->datenbankDatei();
        $neu = $ziel . '.wiederherstellung';

        Log::warning('Wiederherstellung aus ' . $filename);
        $this->createBackup(null, 'vor-wiederherstellung');

        try {
            str_ends_with($backupPath, '.gz')
                ? $this->entpacken($backupPath, $neu)
                : copy($backupPath, $neu);

            $pruefung = new \PDO('sqlite:' . $neu);
            $pruefung->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $ergebnis = $pruefung->query('PRAGMA integrity_check')->fetchColumn();
            $hatSchulen = (bool) $pruefung
                ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'schools'")
                ->fetchColumn();
            $pruefung = null;

            if ($ergebnis !== 'ok' || ! $hatSchulen) {
                throw new \Exception('Die Sicherung ist beschädigt oder keine Abiplaner-Datenbank.');
            }

            DB::disconnect();

            foreach ([$ziel . '-wal', $ziel . '-shm'] as $begleiter) {
                if (file_exists($begleiter)) {
                    @unlink($begleiter);
                }
            }

            if (! @rename($neu, $ziel)) {
                throw new \Exception(
                    'Die Datenbank ist gerade geöffnet und lässt sich nicht ersetzen. '
                    . 'Bitte alle anderen Nutzer abmelden lassen und erneut versuchen.'
                );
            }
        } finally {
            if (file_exists($neu)) {
                @unlink($neu);
            }
        }

        DB::reconnect();
        Artisan::call('migrate', ['--force' => true]);

        Log::info('Wiederherstellung abgeschlossen: ' . $filename);
    }

    /**
     * Datenbank komplett zurücksetzen: Danach ist Abiplaner wie neu
     * installiert und startet mit dem Einrichtungsassistenten.
     *
     * Vorher entsteht eine Sicherung „vor-zuruecksetzen“; sie bleibt im
     * Sicherungsordner und lässt sich nach der neuen Einrichtung unter
     * „Sicherungen“ wiederherstellen. Die alte Datei wird erst gelöscht, wenn
     * die neue fertig ist — scheitert der Neuaufbau, kommt sie zurück.
     *
     * @return string Dateiname der Sicherung vor dem Zurücksetzen
     */
    public function zuruecksetzen(): string
    {
        $datei = $this->datenbankDatei();
        if ($datei === ':memory:' || $datei === '') {
            throw new \Exception('Diese Datenbank lässt sich nicht zurücksetzen.');
        }

        Log::warning('Datenbank wird zurückgesetzt');
        $sicherung = $this->createBackup(null, 'vor-zuruecksetzen')['filename'];

        $beiseite = $datei . '.zuruecksetzen-' . now()->format('YmdHis');

        DB::disconnect();
        foreach ([$datei . '-wal', $datei . '-shm'] as $begleiter) {
            if (file_exists($begleiter)) {
                @unlink($begleiter);
            }
        }
        if (! @rename($datei, $beiseite)) {
            DB::reconnect();
            throw new \Exception(
                'Die Datenbank ist gerade geöffnet und lässt sich nicht ersetzen. '
                . 'Bitte alle anderen Nutzer abmelden lassen und erneut versuchen.'
            );
        }

        try {
            touch($datei);
            DB::reconnect();
            if (Artisan::call('abiplaner:vorbereiten') !== 0) {
                throw new \Exception(trim(Artisan::output()) ?: 'Neuaufbau fehlgeschlagen.');
            }
        } catch (\Throwable $e) {
            DB::disconnect();
            @unlink($datei);
            @rename($beiseite, $datei);
            DB::reconnect();
            Log::error('Zurücksetzen fehlgeschlagen, alte Datenbank zurückgeholt: ' . $e->getMessage());

            throw new \Exception('Zurücksetzen fehlgeschlagen, die bisherigen Daten sind unverändert: ' . $e->getMessage());
        }

        @unlink($beiseite);
        Log::warning('Datenbank zurückgesetzt; Sicherung vorher: ' . $sicherung);

        return $sicherung;
    }

    /** Gibt es die Datenbankdatei? */
    public function tenantDatabaseExists(?School $school = null): bool
    {
        return is_file($this->datenbankDatei());
    }

    /** @return array<string, mixed> */
    private function createBackupMetadata(string $backupPath, ?string $description = null): array
    {
        $migrations = $this->getMigrationsList();
        $schule = School::query()->first();

        $tabellen = collect(DB::select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        ))->pluck('name')->all();

        return [
            'backup_version' => '2.0',
            'created_at' => now()->toIso8601String(),
            'school_number' => $schule?->school_number,
            'school_name' => $schule?->name,
            'description' => $description,
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'schema_version' => $migrations !== [] ? (string) end($migrations) : 'none',
            'migrations' => $migrations,
            'migration_count' => count($migrations),
            'last_migration' => $migrations !== [] ? (string) end($migrations) : 'none',
            'checksum' => hash_file('sha256', $backupPath),
            'checksum_algorithm' => 'sha256',
            'compressed' => str_ends_with($backupPath, '.gz'),
            'size_bytes' => filesize($backupPath),
            'tables' => $tabellen,
            'table_count' => count($tabellen),
        ];
    }

    /** @return list<string> */
    private function getMigrationsList(): array
    {
        try {
            return DB::table('migrations')->orderBy('id')->pluck('migration')->all();
        } catch (\Throwable $e) {
            Log::error('Migrationsliste nicht lesbar: ' . $e->getMessage());

            return [];
        }
    }

    private function validateBackupCompatibility(string $backupPath): void
    {
        $metaPfad = $backupPath . '.meta.json';
        if (! file_exists($metaPfad)) {
            Log::warning("Sicherung ohne Metadaten wird wiederhergestellt: {$backupPath}");

            return;
        }

        $metadata = json_decode((string) file_get_contents($metaPfad), true);
        if (! is_array($metadata)) {
            throw new \Exception('Backup-Metadaten sind beschädigt oder unlesbar!');
        }

        if (isset($metadata['checksum']) && hash_file('sha256', $backupPath) !== $metadata['checksum']) {
            throw new \Exception(
                "WARNUNG: Backup-Datei ist beschädigt oder wurde manipuliert!\n\n"
                . 'Restore wurde aus Sicherheitsgründen abgebrochen.'
            );
        }

        $imBackup = (int) ($metadata['migration_count'] ?? 0);
        $aktuell = count($this->getMigrationsList());
        if ($imBackup > $aktuell) {
            throw new \Exception(
                "FEHLER: Die Sicherung stammt aus einer neueren Programmversion.\n\n"
                . 'Restore nicht möglich! Bitte aktualisieren Sie zuerst die Anwendung.'
            );
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /** Datei mit PHP packen (gzip), in Blöcken statt ganz im Speicher. */
    private function packen(string $quelle, string $ziel): void
    {
        $ein = fopen($quelle, 'rb');
        $aus = gzopen($ziel, 'wb6');
        if ($ein === false || $aus === false) {
            throw new \Exception('Backup-Datei konnte nicht geschrieben werden.');
        }
        while (! feof($ein)) {
            gzwrite($aus, (string) fread($ein, 1 << 20));
        }
        fclose($ein);
        gzclose($aus);
    }

    private function entpacken(string $quelle, string $ziel): void
    {
        $ein = gzopen($quelle, 'rb');
        $aus = fopen($ziel, 'wb');
        if ($ein === false || $aus === false) {
            throw new \Exception('Sicherung konnte nicht entpackt werden.');
        }
        while (! gzeof($ein)) {
            fwrite($aus, (string) gzread($ein, 1 << 20));
        }
        gzclose($ein);
        fclose($aus);
    }
}
