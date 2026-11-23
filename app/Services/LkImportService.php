<?php

namespace App\Services;

use App\Models\LkWahl;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LkImportService extends AbstractWahlImportService
{
    // -------------------------------------------------------------------------
    // Abstrakte Methoden implementieren
    // -------------------------------------------------------------------------

    /** @return class-string */
    protected function getWahlModelClass(): string
    {
        return LkWahl::class;
    }

    protected function validateChoices(array $row, int $lineNumber, array &$errors): void
    {
        if (
            !empty($row['erstwahl'])
            && !empty($row['zweitwahl'])
            && $row['erstwahl'] === $row['zweitwahl']
        ) {
            $errors[] = "Zeile {$lineNumber}: Erst- und Zweitwahl müssen unterschiedlich sein.";
        }
    }

    // -------------------------------------------------------------------------
    // CSV-Parsing (LK-spezifisch: Fach-Spalten mit is_extern-Flag)
    // -------------------------------------------------------------------------

    /**
     * Parse CSV file and return array of data rows.
     *
     * CSV-Format: Klasse;Vollständiger Name;Kommentar;"Fach - Kürzel";"Fach (extern)";…
     * Schüler markieren Erst-/Zweitwahl mit 1 / 2.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array<int, array<string, mixed>>
     */
    public function parseCSV($file): array
    {
        $data = [];
        [$handle, $header] = $this->openCsvAndGetHeader(
            $file,
            'Erwarte Semikolon.'
        );

        // Spalten 0–2: Klasse, Name, Kommentar  –  Fächer ab Index 3
        $subjectMap = [];
        $delimiter  = ';';
        for ($i = 3; $i < count($header); $i++) {
            $rawName = trim($header[$i]);
            if (empty($rawName)) {
                continue;
            }

            // "Russisch - Ru (extern)" → isExtern = true, subjectName = "Russisch"
            $isExtern  = str_contains(strtolower($rawName), '(extern)');
            $cleanName = str_replace([' (extern)', ' (Extern)'], '', $rawName);
            $parts     = explode(' - ', $cleanName);

            $subjectMap[$i] = [
                'name'      => trim($parts[0]),
                'is_extern' => $isExtern,
            ];
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            ['vorname' => $firstName, 'nachname' => $lastName] =
                $this->parseName($row[1] ?? '');

            $erstwahl         = '';
            $zweitwahl        = '';
            $erstwahlExtern   = false;
            $zweitwahlExtern  = false;

            foreach ($subjectMap as $colIndex => $info) {
                if (!isset($row[$colIndex])) {
                    continue;
                }
                $val = trim($row[$colIndex]);
                if ($val === '1') {
                    $erstwahl       = $info['name'];
                    $erstwahlExtern = $info['is_extern'];
                } elseif ($val === '2') {
                    $zweitwahl       = $info['name'];
                    $zweitwahlExtern = $info['is_extern'];
                }
            }

            $data[] = [
                'vorname'            => $firstName,
                'nachname'           => $lastName,
                'erstwahl'           => $erstwahl,
                'zweitwahl'          => $zweitwahl,
                'is_erstwahl_extern' => $erstwahlExtern,
                'is_zweitwahl_extern'=> $zweitwahlExtern,
                'klasse'             => trim($row[0] ?? ''),
            ];
        }

        fclose($handle);

        return $data;
    }

    // -------------------------------------------------------------------------
    // Fach-Matching
    // -------------------------------------------------------------------------

    /**
     * Sucht ein Subject-Model zum importierten Fachnamen.
     * Versucht zuerst exakte, dann case-insensitive, dann Teilstring-Suche.
     */
    public function matchSubject(string $subjectName): ?Subject
    {
        // Explizite Sonderbehandlung
        if (strtolower($subjectName) === 'sport') {
            $subject = Subject::where('name', 'Sport männlich/weiblich')->first();
            if ($subject) {
                return $subject;
            }
        }

        return Subject::where('name', $subjectName)->first()
            ?? Subject::whereRaw('LOWER(name) = ?', [strtolower($subjectName)])->first()
            ?? Subject::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($subjectName) . '%'])->first();
    }

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    /**
     * Importiert LK-Wahlen aus den geparsten CSV-Daten.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array{success: bool, imported: int, errors: array<string>, warnings: array<string>}
     */
    public function importWahlen(array $data, string $schuljahr, int $jahrgang): array
    {
        $imported = 0;
        $errors   = [];
        $warnings = [];

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
                $student = $this->findOrCreateStudent($row, $lineNumber, $warnings);

                // Fächer auflösen
                $erstwahlObj  = !empty($row['erstwahl'])  ? $this->matchSubject($row['erstwahl'])  : null;
                $zweitwahlObj = !empty($row['zweitwahl']) ? $this->matchSubject($row['zweitwahl']) : null;

                if (!empty($row['erstwahl']) && !$erstwahlObj) {
                    $errors[] = "Zeile {$lineNumber}: Fach '{$row['erstwahl']}' wurde nicht gefunden.";
                }
                if (!empty($row['zweitwahl']) && !$zweitwahlObj) {
                    $errors[] = "Zeile {$lineNumber}: Fach '{$row['zweitwahl']}' wurde nicht gefunden.";
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

                LkWahl::create([
                    'schuljahr'              => $schuljahr,
                    'jahrgang'               => $jahrgang,
                    'schueler_id'            => $student->id,
                    'erstwahl_subject_id'    => $erstwahlObj?->id,
                    'zweitwahl_subject_id'   => $zweitwahlObj?->id,
                    'zuteilung_lk_kurs_id'   => null,
                    'prioritaet_erhalten'    => 'keine',
                    'is_erstwahl_extern'     => $row['is_erstwahl_extern']  ?? false,
                    'is_zweitwahl_extern'    => $row['is_zweitwahl_extern'] ?? false,
                ]);

                $imported++;
            }

            DB::commit();

            Log::info('LK Wahlen Import erfolgreich', array_merge($logContext, [
                'imported'       => $imported,
                'errors_count'   => count($errors),
                'warnings_count' => count($warnings),
            ]));

            return [
                'success'  => empty($errors),
                'imported' => $imported,
                'errors'   => $errors,
                'warnings' => $warnings,
            ];

        } catch (\Exception $e) {
            // Clone 7 → Basisklasse
            return $this->buildErrorResponse($e, $logContext, $warnings, 'LK');
        }
    }
}
