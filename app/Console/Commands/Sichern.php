<?php

namespace App\Console\Commands;

use App\Services\TenantBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sicherung der Datenbank anlegen und alte Sicherungen derselben Art
 * aufräumen. Aufrufer: der Scheduler (Docker, nachts) und
 * `abiplaner:vorbereiten` (bei jedem Start bzw. vor einem Update).
 */
class Sichern extends Command
{
    protected $signature = 'abiplaner:sichern
        {--art=auto : Kennung im Dateinamen (auto, vor-update, …)}
        {--keep=14 : Wie viele Sicherungen dieser Art bleiben}';

    protected $description = 'Sicherung der Abiplaner-Datenbank anlegen und alte aufräumen';

    public function handle(TenantBackupService $dienst): int
    {
        $art = \Str::slug((string) $this->option('art')) ?: 'auto';
        $behalten = max(1, (int) $this->option('keep'));

        try {
            $sicherung = $dienst->createBackup(null, $art);
            $this->info("✓ {$sicherung['filename']}");
        } catch (\Throwable $e) {
            $this->error("✗ Sicherung fehlgeschlagen: {$e->getMessage()}");
            Log::error("Sicherung ({$art}) fehlgeschlagen: {$e->getMessage()}");

            return self::FAILURE;
        }

        collect($dienst->listBackups())
            ->filter(fn (array $b): bool => str_contains($b['filename'], "_{$art}."))
            ->sortByDesc('created_at')
            ->slice($behalten)
            ->each(function (array $alt) use ($dienst): void {
                $dienst->deleteBackup(null, $alt['filename']);
                $this->line("  gelöscht: {$alt['filename']}");
            });

        return self::SUCCESS;
    }
}
