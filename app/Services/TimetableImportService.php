<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;

/**
 * Schreibt geparste Stundenplandaten nach `timetable_entries`.
 *
 * **Warum es diesen Dienst gibt.** Die Abbildung einer Untis-Zeile auf eine
 * Datenbankzeile stand an zwei Stellen: in
 * `TimetableController::confirmImport()` — richtig — und in
 * `ImportTimetable.php` — falsch. Die Kopie im Befehl schrieb die Spalte
 * `teacher_code`, die es seit
 * `2026_01_15_000000_remove_teacher_code_from_timetable_entries` nicht mehr
 * gibt, und setzte `teacher_id` nie: jeder Import landete still im
 * catch-Zweig, und `importCombined()` meldete trotzdem Erfolg. Dieselbe
 * Familie wie Fehlerklasse 9 und 10 — ein Umbau, dessen zweiter Leser nicht
 * mitgezogen wurde (Befund 28.08.2026).
 *
 * Die Abbildung steht deshalb jetzt einmal hier; alle Wege rufen sie.
 */
class TimetableImportService
{
    /** Zeilen je Insert — Wert aus confirmImport() übernommen. */
    public const BATCH_SIZE = 500;

    /**
     * Kompletter Import aus einer Untis-Exportdatei.
     *
     * Parsen und Prüfen macht der Vorschau-Dienst — derselbe, den die geführte
     * Maske benutzt. Es gibt keine zweite Fassung des Dateiformats.
     *
     * @return array{imported: int, skipped: int, without_teacher: int, stats: array<string, int>, issues: array<int, array<string, mixed>>}
     */
    public function importFile(
        string $filePath,
        string $schoolYear,
        bool $onlyOberstufe = false,
        bool $truncate = false,
        ?string $teacherFilePath = null
    ): array {
        $preview = new TimetableImportPreviewService();

        if ($teacherFilePath !== null && $teacherFilePath !== '') {
            $preview->parseTeacherDetails($teacherFilePath);
        }

        $parsed = $preview->parseFile($filePath, $schoolYear, $onlyOberstufe);
        $written = $this->storeEntries($parsed['entries'], $schoolYear, $truncate);

        return [
            'imported'        => $written['imported'],
            'skipped'         => $written['skipped'],
            'without_teacher' => $written['without_teacher'],
            'stats'           => $parsed['stats'],
            'issues'          => $parsed['issues'],
        ];
    }

    /**
     * Vorgeparste Einträge schreiben.
     *
     * `teacher_code` wird **hier** und nicht früher nach `teacher_id`
     * aufgelöst: Beim geführten Import legt die Maske vorher neue Lehrkräfte
     * an, die im Vorschau-Datensatz noch gar keine ID haben können.
     *
     * Eine Zeile ohne auflösbare Lehrkraft wird trotzdem geschrieben
     * (`teacher_id = null`) — so war es bisher, und die Vorschau meldet den
     * Fall als Warnung. `without_teacher` zählt sie, damit der Aufrufer nicht
     * raten muss.
     *
     * @param array<int, array<string, mixed>> $entries Einträge aus TimetableImportPreviewService::parseFile()
     * @param list<string> $teacherDeletions Kürzel, die der Nutzer ausgeschlossen hat
     * @return array{imported: int, skipped: int, without_teacher: int}
     */
    public function storeEntries(
        array $entries,
        string $schoolYear,
        bool $truncate = false,
        array $teacherDeletions = []
    ): array {
        if ($truncate) {
            TimetableEntry::where('school_year', $schoolYear)->delete();
        }

        /** @var array<string, int> $teacherCache */
        $teacherCache = Teacher::pluck('id', 'shortcode')->toArray();

        $imported       = 0;
        $skipped        = 0;
        $withoutTeacher = 0;
        $batch          = [];
        $now            = now()->toDateTimeString();

        foreach ($entries as $entry) {
            $status = (string) ($entry['status'] ?? 'valid');

            if ($status === 'error' || $status === 'skip') {
                $skipped++;
                continue;
            }

            $teacherCode = (string) ($entry['teacher_code'] ?? '');

            if (in_array($teacherCode, $teacherDeletions, true)) {
                $skipped++;
                continue;
            }

            $teacherId = $teacherCache[$teacherCode] ?? null;

            if ($teacherId === null) {
                $withoutTeacher++;
            }

            $batch[] = [
                'teacher_id'  => $teacherId,
                'class_name'  => $entry['class_name'],
                'course_code' => $entry['course_code'],
                'subject'     => $entry['subject'],
                'room'        => $entry['room'],
                'day_of_week' => $entry['day_of_week'],
                'period'      => $entry['period'],
                'school_year' => $schoolYear,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
            $imported++;

            if (count($batch) >= self::BATCH_SIZE) {
                $this->insertBatch($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->insertBatch($batch);
        }

        return [
            'imported'        => $imported,
            'skipped'         => $skipped,
            'without_teacher' => $withoutTeacher,
        ];
    }

    /**
     * Ein Stapel, ein Insert — **ohne** try/catch.
     *
     * Genau daran ist der alte Befehl gescheitert: Er fing die Ausnahme, zählte
     * sie als `errors` und lieferte trotzdem Exit-Code 0. Ein Schreibfehler
     * gehört nach oben, wo der Aufrufer eine Transaktion zurückrollen und es
     * dem Nutzer sagen kann.
     *
     * @param array<int, array<string, mixed>> $batch
     */
    private function insertBatch(array $batch): void
    {
        DB::connection($this->connectionName())
            ->table('timetable_entries')
            ->insert($batch);
    }

    /**
     * Die Verbindung des Modells, nicht die Vorgabe: `timetable_entries` liegt
     * im Mandanten. Ein `DB::table()` auf der Default-Verbindung trifft je nach
     * Aufrufweg die zentrale Datenbank — und scheitert erst zur Laufzeit.
     */
    private function connectionName(): string
    {
        return (new TimetableEntry())->getConnectionName() ?? (string) config('database.default');
    }
}
