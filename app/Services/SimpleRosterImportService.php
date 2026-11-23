<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\Student;

/**
 * Einfacher Jahrgangsliste-Import: nur "Nachname;Vorname" pro Zeile, ohne
 * Kurse/Prüfungen. Für Jahrgänge, die noch keine Abiturprüfungs-Zuordnung
 * haben (z. B. Q12), aber als Student-Stammdatensatz existieren müssen —
 * u. a. damit der Absenzen-Import (Roadmap 8.1) Namen zuordnen kann.
 *
 * Bewusst ein eigener, schlanker Service statt Erweiterung von
 * StudentCsvImportService: dessen Format (zweizeiliger Block mit Kurs-
 * Spalten) und Logik (Zeilen ohne Kurse werden übersprungen) passt hier
 * nicht.
 */
class SimpleRosterImportService
{
    /**
     * @return array{rows: array<int, array{lastname: string, firstname: string, status: string}>, new_count: int, existing_count: int}
     */
    public function parsePreview(string $filePath, ExamPeriod $examPeriod, string $grade): array
    {
        $names = $this->readNames($filePath);

        $existing = Student::where('exam_period_id', $examPeriod->id)
            ->where('grade', $grade)
            ->get(['lastname', 'firstname'])
            ->map(fn($s) => mb_strtolower(trim($s->lastname)) . '|' . mb_strtolower(trim($s->firstname)))
            ->flip();

        $rows = [];
        $newCount = 0;
        $existingCount = 0;

        foreach ($names as $name) {
            $key = mb_strtolower($name['lastname']) . '|' . mb_strtolower($name['firstname']);
            $isExisting = $existing->has($key);
            $isExisting ? $existingCount++ : $newCount++;

            $rows[] = [
                'lastname' => $name['lastname'],
                'firstname' => $name['firstname'],
                'status' => $isExisting ? 'existiert bereits' : 'neu',
            ];
        }

        return [
            'rows' => $rows,
            'new_count' => $newCount,
            'existing_count' => $existingCount,
        ];
    }

    /**
     * Legt neue Student-Datensätze an. Bereits vorhandene (gleicher
     * Nachname+Vorname im selben Prüfungszeitraum+Jahrgangsstufe) werden
     * übersprungen, damit ein wiederholter Import keine Duplikate erzeugt.
     *
     * @return array{created: int, skipped: int}
     */
    public function import(string $filePath, ExamPeriod $examPeriod, string $grade): array
    {
        $names = $this->readNames($filePath);

        $existingKeys = Student::where('exam_period_id', $examPeriod->id)
            ->where('grade', $grade)
            ->get(['lastname', 'firstname'])
            ->map(fn($s) => mb_strtolower(trim($s->lastname)) . '|' . mb_strtolower(trim($s->firstname)))
            ->flip();

        $usedExternalIds = Student::where('exam_period_id', $examPeriod->id)
            ->pluck('external_id')
            ->flip();

        $created = 0;
        $skipped = 0;

        foreach ($names as $name) {
            $key = mb_strtolower($name['lastname']) . '|' . mb_strtolower($name['firstname']);
            if ($existingKeys->has($key)) {
                $skipped++;
                continue;
            }

            $externalId = $this->generateExternalId($grade, $name['lastname'], $name['firstname'], $usedExternalIds);
            $usedExternalIds->put($externalId, 0);
            $existingKeys->put($key, 0);

            Student::create([
                'exam_period_id' => $examPeriod->id,
                'external_id' => $externalId,
                'grade' => $grade,
                'lastname' => $name['lastname'],
                'firstname' => $name['firstname'],
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Eindeutige externe ID aus Jahrgangsstufe + Namen, da die Jahrgangsliste
     * selbst keine ID liefert. Bei Kollision (z. B. Namensgleichheit) wird
     * ein Zähler angehängt.
     */
    private function generateExternalId(string $grade, string $lastname, string $firstname, $usedExternalIds): string
    {
        $slug = 'q' . $grade . '-' . $this->slugify($lastname) . '-' . $this->slugify($firstname);
        $candidate = $slug;
        $suffix = 2;

        while ($usedExternalIds->has($candidate)) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * @return array<int, array{lastname: string, firstname: string}>
     */
    private function readNames(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Datei konnte nicht geöffnet werden: {$filePath}");
        }

        $names = [];
        $lineNumber = 0;
        while (($line = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $lineNumber++;
            if ($lineNumber === 1) {
                continue; // Kopfzeile (Kursphase/Jahrgang)
            }
            if (count($line) < 2) {
                continue;
            }
            $lastname = trim((string) ($line[0] ?? ''));
            $lastname = preg_replace('/^\xEF\xBB\xBF/', '', $lastname);
            $firstname = trim((string) ($line[1] ?? ''));

            if ($lastname === '' || $firstname === '') {
                continue;
            }

            $names[] = ['lastname' => $lastname, 'firstname' => $firstname];
        }
        fclose($handle);

        return $names;
    }
}
