<?php

namespace App\Services;

use App\Models\WseminarWahl;
use App\Models\WseminarKurs;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WseminarImportService extends AbstractWahlImportService
{
    // -------------------------------------------------------------------------
    // Abstrakte Methoden implementieren
    // -------------------------------------------------------------------------

    /** @return class-string */
    protected function getWahlModelClass(): string
    {
        return WseminarWahl::class;
    }

    protected function validateChoices(array $row, int $lineNumber, array &$errors): void
    {
        $choices = array_filter([$row['erstwahl'] ?? '', $row['zweitwahl'] ?? '', $row['drittwahl'] ?? '']);
        if (count($choices) !== count(array_unique($choices))) {
            $errors[] = "Zeile {$lineNumber}: Alle Wahlen müssen unterschiedlich sein.";
        }
    }

    // -------------------------------------------------------------------------
    // CSV-Parsing (W-Seminar-spezifisch: Spalten sind Seminar-Titel, Werte 1/2/3)
    // -------------------------------------------------------------------------

    /**
     * Parse CSV file and return array of data rows.
     *
     * CSV-Format: Klasse;Vollständiger Name;Kommentar;"Fach - Titel 1";"Fach - Titel 2";…
     * Schüler markieren Erst-/Zweit-/Drittwahl mit 1 / 2 / 3.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array<int, array<string, mixed>>
     */
    public function parseCSV($file): array
    {
        $data = [];
        [$handle, $header] = $this->openCsvAndGetHeader(
            $file,
            'Erwarte Semikolon als Trenner.'
        );

        // Spalten 0–2: Klasse, Name, Kommentar  –  W-Seminare ab Index 3
        $wseminarMap = [];
        $delimiter   = ';';
        for ($i = 3; $i < count($header); $i++) {
            $rawTitle = trim($header[$i]);
            if (!empty($rawTitle)) {
                $wseminarMap[$i] = $rawTitle; // z. B. "Wirtschaft und Recht - Aktuelle Herausforderungen"
            }
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            ['vorname' => $firstName, 'nachname' => $lastName] =
                $this->parseName($row[1] ?? '');

            $erstwahl  = '';
            $zweitwahl = '';
            $drittwahl = '';

            foreach ($wseminarMap as $colIndex => $wseminarTitle) {
                if (!isset($row[$colIndex])) {
                    continue;
                }
                $val = trim($row[$colIndex]);
                if ($val === '1') {
                    $erstwahl  = $wseminarTitle;
                } elseif ($val === '2') {
                    $zweitwahl = $wseminarTitle;
                } elseif ($val === '3') {
                    $drittwahl = $wseminarTitle;
                }
            }

            $data[] = [
                'vorname'   => $firstName,
                'nachname'  => $lastName,
                'erstwahl'  => $erstwahl,
                'zweitwahl' => $zweitwahl,
                'drittwahl' => $drittwahl,
                'klasse'    => trim($row[0] ?? ''),
            ];
        }

        fclose($handle);

        return $data;
    }

    // -------------------------------------------------------------------------
    // W-Seminar-Matching
    // -------------------------------------------------------------------------

    /**
     * Findet einen bestehenden WseminarKurs anhand des Titels oder legt ihn neu an.
     *
     * Erwartet Format "Fach - Titel", z. B. "Deutsch - Ecocriticism".
     */
    public function matchWseminar(string $wseminarTitle, string $schuljahr, int|string $jahrgang): ?WseminarKurs
    {
        $parts = explode(' - ', $wseminarTitle, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $subjectName = trim($parts[0]);
        $titel       = trim($parts[1]);

        // Bestehenden Kurs suchen
        $kurs = WseminarKurs::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->where('titel', $titel)
            ->first();

        if ($kurs) {
            return $kurs;
        }

        // Fach suchen
        $subject = \App\Models\Subject::whereRaw('LOWER(name) = ?', [strtolower($subjectName)])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($subjectName) . '%'])
            ->first();

        if (!$subject) {
            return null;
        }

        // Neuen Kurs anlegen
        $nextKursNummer = (WseminarKurs::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->where('subject_id', $subject->id)
            ->max('kurs_nummer') ?? 0) + 1;

        return WseminarKurs::create([
            'schuljahr'      => $schuljahr,
            'jahrgang'       => $jahrgang,
            'subject_id'     => $subject->id,
            'titel'          => $titel,
            'max_teilnehmer' => 15,
            'kurs_nummer'    => $nextKursNummer,
        ]);
    }

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    /**
     * Importiert W-Seminar-Wahlen aus den geparsten CSV-Daten.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array{success: bool, imported: int, errors: array<string>, warnings: array<string>, stats: array<string, mixed>}
     */
    public function importWahlen(array $data, string $schuljahr, int $jahrgang): array
    {
        $imported = 0;
        $errors   = [];
        $warnings = [];

        $stats = [
            'students_found'   => 0,
            'students_created' => 0,
            'kurse_found'      => 0,
            'kurse_created'    => 0,
            'kurse_list'       => [],
        ];

        $processedKurse = [];

        $logContext = $this->buildLogContext($schuljahr, $jahrgang);

        DB::beginTransaction();

        try {
            // Jahrgang 11: alle bestehenden Wahlen löschen – Basisklasse
            if ($jahrgang === 11) {
                $this->deleteAllWahlenForJahrgang11($schuljahr, $warnings);
            }

            foreach ($data as $index => $row) {
                $lineNumber = $index + 2;

                // Clone 3+4+5 → Basisklasse
                $studentCountBefore = $stats['students_created'];
                $student = $this->findOrCreateStudent($row, $lineNumber, $warnings);

                // Statistik: neu oder gefunden?
                if ($stats['students_created'] > $studentCountBefore) {
                    // findOrCreateStudent hat einen neuen Schüler angelegt (Warnung wurde geschrieben)
                } else {
                    $stats['students_found']++;
                }

                // W-Seminare auflösen und Statistik führen
                $erstwahlKurs  = $this->resolveKurs($row['erstwahl']  ?? '', $schuljahr, $jahrgang, $processedKurse, $stats);
                $zweitwahlKurs = $this->resolveKurs($row['zweitwahl'] ?? '', $schuljahr, $jahrgang, $processedKurse, $stats);
                $drittwahlKurs = $this->resolveKurs($row['drittwahl'] ?? '', $schuljahr, $jahrgang, $processedKurse, $stats);

                // Pflichtfelder prüfen
                if (!empty($row['erstwahl'])  && !$erstwahlKurs)  {
                    $errors[] = "Zeile {$lineNumber}: W-Seminar '{$row['erstwahl']}' konnte nicht gefunden werden.";
                }
                if (!empty($row['zweitwahl']) && !$zweitwahlKurs) {
                    $errors[] = "Zeile {$lineNumber}: W-Seminar '{$row['zweitwahl']}' konnte nicht gefunden werden.";
                }
                if (!empty($row['drittwahl']) && !$drittwahlKurs) {
                    $errors[] = "Zeile {$lineNumber}: W-Seminar '{$row['drittwahl']}' konnte nicht gefunden werden.";
                }
                if (!empty($errors)) {
                    continue;
                }

                // Clone 6 → Basisklasse
                $this->deleteExistingWahlIfPresent(
                    $schuljahr, $jahrgang,
                    $student->id, $row['vorname'], $row['nachname'],
                    $lineNumber, $warnings
                );

                WseminarWahl::create([
                    'schuljahr'                  => $schuljahr,
                    'jahrgang'                   => $jahrgang,
                    'schueler_id'                => $student->id,
                    'erstwahl_wseminar_id'       => $erstwahlKurs?->id,
                    'zweitwahl_wseminar_id'      => $zweitwahlKurs?->id,
                    'drittwahl_wseminar_id'      => $drittwahlKurs?->id,
                    'zuteilung_wseminar_kurs_id' => null,
                    'prioritaet_erhalten'        => 'keine',
                ]);

                $imported++;
            }

            // Kursliste deduplizieren und sortieren
            $stats['kurse_list'] = array_values(array_unique($stats['kurse_list']));
            sort($stats['kurse_list']);

            DB::commit();

            Log::info('W-Seminar Wahlen Import erfolgreich', array_merge($logContext, [
                'imported'       => $imported,
                'errors_count'   => count($errors),
                'warnings_count' => count($warnings),
                'stats'          => $stats,
            ]));

            return [
                'success'  => empty($errors),
                'imported' => $imported,
                'errors'   => $errors,
                'warnings' => $warnings,
                'stats'    => $stats,
            ];

        } catch (\Exception $e) {
            // Clone 7 → Basisklasse (stats ins Result mergen)
            $base = $this->buildErrorResponse($e, $logContext, $warnings, 'W-Seminar');
            return array_merge($base, ['stats' => $stats]);
        }
    }

    // -------------------------------------------------------------------------
    // Hilfsmethode: Kurs auflösen + Statistik
    // -------------------------------------------------------------------------

    /**
     * Löst einen Kursnamen auf und aktualisiert processedKurse / stats.
     *
     * @param  array<int, bool>           $processedKurse  (wird per Referenz befüllt)
     * @param  array<string, mixed>       $stats           (wird per Referenz befüllt)
     */
    private function resolveKurs(
        string $title,
        string $schuljahr,
        int    $jahrgang,
        array  &$processedKurse,
        array  &$stats
    ): ?WseminarKurs {
        if (empty($title)) {
            return null;
        }

        $kurs = $this->matchWseminar($title, $schuljahr, $jahrgang);

        if ($kurs && !isset($processedKurse[$kurs->id])) {
            $processedKurse[$kurs->id] = true;
            if ($kurs->wasRecentlyCreated) {
                $stats['kurse_created']++;
            } else {
                $stats['kurse_found']++;
            }
            $stats['kurse_list'][] = $title;
        }

        return $kurs;
    }
}
