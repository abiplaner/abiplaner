<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSchoolTenancy;
use App\Services\TimetableImportService;
use Illuminate\Console\Command;

/**
 * Untis-Stundenplan importieren (Kommandozeilen-Weg).
 *
 * Der Befehl parst und schreibt **nicht mehr selbst**. Bis zum 28.08.2026 tat
 * er beides — mit einer eigenen Kopie der Fächer-Extraktion und einer Zeile,
 * die die längst entfernte Spalte `teacher_code` schrieb und `teacher_id` nie
 * setzte. Jeder Import landete still im catch-Zweig. Parsen macht jetzt
 * `TimetableImportPreviewService` (derselbe Dienst wie in der geführten
 * Maske), Schreiben `TimetableImportService`.
 */
class ImportTimetable extends Command
{
    use ResolvesSchoolTenancy;

    protected $signature = 'timetable:import
                            {file : Pfad zur Untis-Exportdatei}
                            {--year= : Schuljahr im Kurzformat, z. B. 2026/27 (Pflicht)}
                            {--school= : Schulnummer, wenn der Befehl außerhalb eines Mandanten läuft}
                            {--teachers= : Optionale ASV-Datei zur Anreicherung der Lehrernamen}
                            {--truncate : Vorhandene Einträge dieses Schuljahres vorher löschen}
                            {--only-oberstufe : Nur Q12 und Q13 importieren}';

    protected $description = 'Importiert einen Untis-Stundenplan in die Mandanten-Datenbank';

    public function handle(TimetableImportService $importService): int
    {
        $file       = (string) $this->argument('file');
        $schoolYear = trim((string) ($this->option('year') ?? ''));

        if (!file_exists($file)) {
            $this->error("Datei nicht gefunden: {$file}");

            return self::FAILURE;
        }

        // Kein Vorgabewert: Ein stilles "2024/25" schreibt einen Stundenplan,
        // den die Anwendung anschliessend nirgends findet.
        if ($schoolYear === '') {
            $this->error('Bitte das Schuljahr angeben, z. B. --year=2026/27.');

            return self::FAILURE;
        }

        $teachersFile = (string) ($this->option('teachers') ?? '');

        if ($teachersFile !== '' && !file_exists($teachersFile)) {
            $this->error("ASV-Datei nicht gefunden: {$teachersFile}");

            return self::FAILURE;
        }

        $tenancyStarted = false;
        $exitCode = $this->resolveSchoolAndStartTenancy($this->option('school'), $tenancyStarted);
        if ($exitCode !== null) {
            return $exitCode;
        }

        $truncate = (bool) $this->option('truncate');

        if ($truncate && $this->input->isInteractive()
            && !$this->confirm("Alle Stundenplaneinträge des Schuljahres {$schoolYear} werden gelöscht. Fortfahren?")) {
            $this->info('Import abgebrochen.');

            if ($tenancyStarted) {
                app(\App\Services\AktuelleSchule::class)->verlassen();
            }

            return self::SUCCESS;
        }

        $this->info("Importiere Stundenplan aus: {$file}");
        $this->info("Schuljahr: {$schoolYear}");

        try {
            $result = $importService->importFile(
                $file,
                $schoolYear,
                (bool) $this->option('only-oberstufe'),
                $truncate,
                $teachersFile !== '' ? $teachersFile : null
            );
        } finally {
            if ($tenancyStarted) {
                app(\App\Services\AktuelleSchule::class)->verlassen();
            }
        }

        $this->newLine();
        $this->table(
            ['Kennzahl', 'Anzahl'],
            [
                ['Zeilen gelesen', $result['stats']['total']],
                ['davon fehlerhaft', $result['stats']['errors']],
                ['davon mit Warnung', $result['stats']['warnings']],
                ['geschrieben', $result['imported']],
                ['übersprungen', $result['skipped']],
                ['ohne Lehrerzuordnung', $result['without_teacher']],
            ]
        );

        if ($result['without_teacher'] > 0) {
            $this->warn("{$result['without_teacher']} Einträge ohne teacher_id — diese Kürzel stehen nicht in der Lehrertabelle.");
        }

        // Eine gelesene, aber nicht geschriebene Datei ist ein Fehlschlag, kein
        // Erfolg mit Nullmeldung. Genau das hat der alte Befehl verschwiegen.
        if ($result['imported'] === 0 && $result['stats']['total'] > 0) {
            $this->error('Kein einziger Eintrag geschrieben.');

            return self::FAILURE;
        }

        $this->info("Import abgeschlossen: {$result['imported']} Einträge.");

        return self::SUCCESS;
    }
}
