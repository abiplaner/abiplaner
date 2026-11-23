<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Gemeinsame Basisklasse für LkImportService und WseminarImportService.
 *
 * Extrahiert folgende identische Blöcke:
 *  - parseName()              – Vor-/Nachname aus "Vollständiger Name" trennen
 *  - validateData()           – Grundvalidierung (leer, Vorname, Nachname) +
 *                               abstrakter Hook validateChoices()
 *  - findOrCreateStudent()    – Schüler per Name suchen / neu anlegen
 *  - deleteExistingWahlIfPresent() – Duplikat-Wahl für Nicht-Jahrgang-11 löschen
 *  - buildErrorResponse()     – Einheitlicher Catch/Rollback-Rückgabewert
 */
abstract class AbstractWahlImportService
{
    // -------------------------------------------------------------------------
    // Abstrakte Methoden – müssen von Unterklassen implementiert werden
    // -------------------------------------------------------------------------

    /**
     * Unterklassen-spezifische Wahl-Validierung (z. B. "Erst- ≠ Zweitwahl"
     * oder "alle drei Wahlen unterschiedlich").
     */
    abstract protected function validateChoices(array $row, int $lineNumber, array &$errors): void;

    /**
     * Gibt den vollqualifizierten Klassennamen des Wahl-Models zurück
     * (z. B. \App\Models\LkWahl::class oder \App\Models\WseminarWahl::class).
     *
     * @return class-string
     */
    abstract protected function getWahlModelClass(): string;

    // -------------------------------------------------------------------------
    // Clone CSV-Öffnung – gemeinsames fopen + Header-Prüfung
    // -------------------------------------------------------------------------

    /**
     * Öffnet die CSV-Datei, liest den Header und prüft die Mindest-Spaltenanzahl.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $errorHint  Hinweis auf erwartetes Format (für die Exception)
     * @return array{resource, array<int, string>}  [$handle, $header]
     * @throws \Exception  wenn Datei nicht geöffnet werden kann oder zu wenige Spalten hat
     */
    protected function openCsvAndGetHeader($file, string $errorHint = 'Erwarte Semikolon.'): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            throw new \Exception('CSV-Datei konnte nicht geöffnet werden.');
        }

        $delimiter = ';';
        $header    = fgetcsv($handle, 0, $delimiter);

        if (!$header || count($header) < 4) {
            fclose($handle);
            throw new \Exception(
                "Ungültiges CSV-Format (zu wenige Spalten oder falscher Trenner). {$errorHint}"
            );
        }

        return [$handle, $header];
    }

    // -------------------------------------------------------------------------
    // Clone Jahrgang-11-Löschung
    // -------------------------------------------------------------------------

    /**
     * Löscht alle bestehenden Wahlen für Jahrgang 11 (Vorbereitung für Reimport).
     * Schreibt eine Warnung wenn Einträge gelöscht wurden.
     *
     * @param  array<string>  $warnings  (wird per Referenz befüllt)
     */
    protected function deleteAllWahlenForJahrgang11(
        string $schuljahr,
        array  &$warnings
    ): void {
        $modelClass   = $this->getWahlModelClass();
        $deletedCount = $modelClass::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', 11)
            ->delete();

        if ($deletedCount > 0) {
            $warnings[] = "Es wurden {$deletedCount} existierende Wahlen für"
                . " Jahrgang 11 ({$schuljahr}) gelöscht.";
        }
    }

    // -------------------------------------------------------------------------
    // Clone 1 – Name-Parsing
    // -------------------------------------------------------------------------

    /**
     * Trennt "Vorname(n) Nachname" aus dem CSV-Feld "Vollständiger Name".
     *
     * @return array{vorname: string, nachname: string}
     */
    protected function parseName(string $fullName): array
    {
        $parts    = explode(' ', trim($fullName));
        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        if (empty($firstName)) {
            // Fallback: nur ein Wort vorhanden
            $firstName = $lastName;
            $lastName  = '';
        }

        return ['vorname' => $firstName, 'nachname' => $lastName];
    }

    // -------------------------------------------------------------------------
    // Clone 2 – validateData-Grundgerüst
    // -------------------------------------------------------------------------

    /**
     * Validiert die importierten Zeilen.
     *
     * Prüft gemeinsame Regeln (leere Datei, fehlender Vor-/Nachname) und
     * delegiert die Wahl-spezifische Prüfung an validateChoices().
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateData(array $data): array
    {
        $errors = [];

        if (empty($data)) {
            $errors[] = 'Die CSV-Datei enthält keine Daten.';
            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($data as $index => $row) {
            $lineNumber = $index + 2; // +2: 1 für Header, 1 für 0-basiert

            if (empty($row['vorname'])) {
                $errors[] = "Zeile {$lineNumber}: Vorname fehlt.";
            }

            if (empty($row['nachname'])) {
                $errors[] = "Zeile {$lineNumber}: Nachname fehlt.";
            }

            // Unterklasse prüft Eindeutigkeit der Wahlen
            $this->validateChoices($row, $lineNumber, $errors);
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    // -------------------------------------------------------------------------
    // Clone 3+4+5 – Student find-or-create
    // -------------------------------------------------------------------------

    /**
     * Sucht einen Schüler per Name (case-insensitive).
     * Legt ihn bei Nichtvorhandensein für Jahrgang 11 neu an.
     * Aktualisiert Klasse und Jahrgangsstufe bei vorhandenen Schülern.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string>         $warnings  (wird per Referenz befüllt)
     */
    protected function findOrCreateStudent(array $row, int $lineNumber, array &$warnings): Student
    {
        // Perioden-Audit (Modul 1.1): nur in der aktuell gewählten Periode
        // suchen — der Fallback-create legt den Schüler ebenfalls dort an.
        // Ohne Filter würde die Wahl nach dem Jahreswechsel am archivierten
        // Vorjahres-Datensatz derselben Person hängen.
        $student = Student::where('exam_period_id', app(CurrentExamPeriodService::class)->currentId())
            ->whereRaw('LOWER(firstname) = ?', [strtolower($row['vorname'])])
            ->whereRaw('LOWER(lastname) = ?', [strtolower($row['nachname'])])
            ->first();

        if (!$student) {
            $examPeriod = app(CurrentExamPeriodService::class)->current();

            if (!$examPeriod) {
                throw new \Exception(
                    'Kein Prüfungszeitraum gefunden. Bitte legen Sie zuerst einen Prüfungszeitraum an.'
                );
            }

            $student = Student::create([
                'firstname'   => $row['vorname'],
                'lastname'    => $row['nachname'],
                'class_name'  => $row['klasse'] ?? null,
                'exam_period_id' => $examPeriod->id,
                'external_id' => (string) Str::uuid(),
                'grade'       => '11', // String für ENUM, Jahrgang 11
            ]);

            $warnings[] = "Zeile {$lineNumber}: Schüler {$row['vorname']} {$row['nachname']}"
                . ' wurde neu angelegt (Jahrgangsstufe 11).';
        } else {
            // Klasse und Jahrgangsstufe ggf. aktualisieren
            $updateData = [];
            if (!empty($row['klasse'])) {
                $updateData['class_name'] = $row['klasse'];
            }
            if (!$student->grade) {
                $updateData['grade'] = '11'; // String für ENUM
            }
            if (!empty($updateData)) {
                $student->update($updateData);
            }
        }

        return $student;
    }

    // -------------------------------------------------------------------------
    // Clone 6 – Duplikat-Wahl-Prüfung
    // -------------------------------------------------------------------------

    /**
     * Prüft, ob für den Schüler bereits eine Wahl existiert (nur für Jahrgänge ≠ 11,
     * da Jahrgang 11 am Anfang des Imports komplett gelöscht wird).
     * Löscht vorhandene Einträge und schreibt eine Warnung.
     *
     * @param  array<string>  $warnings  (wird per Referenz befüllt)
     */
    protected function deleteExistingWahlIfPresent(
        string $schuljahr,
        int    $jahrgang,
        int    $schuelerId,
        string $vorname,
        string $nachname,
        int    $lineNumber,
        array  &$warnings
    ): void {
        if ($jahrgang === 11) {
            return; // Für Jahrgang 11 wurde am Anfang bereits alles gelöscht
        }

        $modelClass = $this->getWahlModelClass();
        $existing   = $modelClass::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->where('schueler_id', $schuelerId)
            ->first();

        if ($existing) {
            $warnings[] = "Zeile {$lineNumber}: Wahl für {$vorname} {$nachname}"
                . ' existiert bereits und wird überschrieben.';
            $existing->delete();
        }
    }

    // -------------------------------------------------------------------------
    // Clone 7 – Catch/Rollback-Rückgabewert
    // -------------------------------------------------------------------------

    /**
     * Erstellt den einheitlichen Log-Kontext für importWahlen().
     *
     * Die Schulnummer kommt aus dem Mandanten und nicht mehr als Parameter
     * herein. Sie war als `int $schoolId` typisiert, und PHP macht aus der
     * vierstelligen Nummer '0042' dabei still die Zahl 42 — im Log stand
     * also eine Schulnummer, die es nicht gibt. Über AktuelleSchule steht dort die
     * echte, mit führender Null.
     *
     * @return array{school_id: string|null, schuljahr: string, jahrgang: int}
     */
    protected function buildLogContext(string $schuljahr, int $jahrgang): array
    {
        $mandant = app(AktuelleSchule::class)->schule();

        return [
            'school_id' => $mandant !== null ? (string) $mandant->getKey() : null,
            'schuljahr' => $schuljahr,
            'jahrgang'  => $jahrgang,
        ];
    }

    /**
     * Baut den einheitlichen Fehler-Rückgabewert nach einem fehlgeschlagenen Import.
     * Rollt die Datenbank-Transaktion zurück und schreibt einen Log-Eintrag.
     *
     * @param  array<string, mixed>  $logContext   Kontextdaten für das Log (school_id, schuljahr, …)
     * @param  array<string>         $warnings     Bisherige Warnungen (werden ins Ergebnis übernommen)
     * @param  string                $logChannel   Bezeichnung für die Log-Meldung (z. B. "LK" / "W-Seminar")
     * @return array{success: false, imported: 0, errors: array<string>, warnings: array<string>}
     */
    protected function buildErrorResponse(
        \Exception $e,
        array      $logContext,
        array      $warnings,
        string     $logChannel
    ): array {
        DB::rollBack();

        Log::error("{$logChannel} Wahlen Import fehlgeschlagen", array_merge($logContext, [
            'error' => $e->getMessage(),
        ]));

        return [
            'success'  => false,
            'imported' => 0,
            'errors'   => ['Ein unerwarteter Fehler ist aufgetreten: ' . $e->getMessage()],
            'warnings' => $warnings,
        ];
    }
}
