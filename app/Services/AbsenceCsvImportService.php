<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\AbsenceImport;
use App\Models\Student;
use App\Services\CurrentExamPeriodService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Import des Infoportal-Absenzenberichts (Roadmap 8.1).
 *
 * Ablauf: parsePreview() zum Hochladen/Prüfen der Schüler-Zuordnung,
 * import() für den finalen, persistierten Import inkl. manueller
 * Korrekturen aus der Vorschau.
 *
 * Kein Bezug zur "Attestpflicht" im Sinne von Modul 8.3 — der Status
 * "schriftlich (Attestpflicht)" aus der Quelle wird nur als excuse_status
 * gespeichert, nicht als abiplaner-interne Attestpflicht-Entscheidung.
 */
class AbsenceCsvImportService
{
    /**
     * Kategorien, die beim Import bewusst ignoriert werden: vorab genehmigte
     * Freistellungen (Arzttermine, Schulveranstaltungen, religiöse Feiertage
     * u. ä.), die für die Fehlzeiten-Auswertung irrelevant sind und dort
     * nicht auftauchen sollen (siehe Wilfried, 03.07.2026).
     */
    private const IGNORED_CATEGORIES = ['befreit', 'tageweise_befreit'];

    public function __construct(
        private NameMatchingService $names
    ) {}

    /**
     * Liest die CSV, gruppiert nach Name+Vorname und ordnet Schüler zu.
     *
     * @return array{rows: array, matching: array, row_count: int, unresolved_count: int}
     */
    public function parsePreview(string $filePath): array
    {
        $rows = $this->relevantRows($this->readRows($filePath));
        $matching = $this->matchStudents($rows);

        $unresolved = collect($matching)
            ->whereIn('status', ['unmatched', 'ambiguous'])
            ->count();

        return [
            'rows' => $rows,
            'matching' => $matching,
            'row_count' => count($rows),
            'unresolved_count' => $unresolved,
        ];
    }

    /**
     * Führt den finalen Import durch.
     *
     * @param array<string, int|string> $corrections nameKey => student_id oder 'skip'
     */
    public function import(string $filePath, string $originalFilename, array $corrections, ?int $userId): AbsenceImport
    {
        $allRows = $this->readRows($filePath);
        $rows = $this->relevantRows($allRows);
        $filtered = count($allRows) - count($rows);
        $matching = $this->matchStudents($rows);

        $studentIdByNameKey = [];
        foreach ($matching as $nameKey => $info) {
            if ($info['status'] === 'matched') {
                $studentIdByNameKey[$nameKey] = $info['student_id'];
            } elseif (isset($corrections[$nameKey]) && $corrections[$nameKey] !== 'skip' && $corrections[$nameKey] !== '') {
                $studentIdByNameKey[$nameKey] = (int) $corrections[$nameKey];
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $matchedRows = 0;
        $periodFrom = null;
        $periodTo = null;

        $absenceImport = DB::transaction(function () use (
            $rows, $allRows, $studentIdByNameKey, $originalFilename, $userId,
            &$created, &$updated, &$skipped, &$matchedRows, &$periodFrom, &$periodTo
        ) {
            $absenceImport = AbsenceImport::create([
                'original_filename' => $originalFilename,
                'row_count' => count($allRows),
                'imported_by_user_id' => $userId,
            ]);

            foreach ($rows as $row) {
                $studentId = $studentIdByNameKey[$row['name_key']] ?? null;

                if ($row['date_from'] && (!$periodFrom || $row['date_from']->lt($periodFrom))) {
                    $periodFrom = $row['date_from'];
                }
                if ($row['date_to'] && (!$periodTo || $row['date_to']->gt($periodTo))) {
                    $periodTo = $row['date_to'];
                }

                if (!$studentId) {
                    $skipped++;
                    continue;
                }
                $matchedRows++;

                $sourceKey = $this->buildSourceKey($row);

                $attributes = [
                    'absence_import_id' => $absenceImport->id,
                    'date_from' => $row['date_from'],
                    'date_to' => $row['date_to'],
                    'days' => $row['days'],
                    'hours' => $row['hours'],
                    'minutes' => $row['minutes'],
                    'art_raw' => $row['art_raw'],
                    'category' => $row['category'],
                    'hour_from' => $row['hour_from'],
                    'hour_to' => $row['hour_to'],
                    'minutes_late' => $row['minutes_late'],
                    'excuse_status' => $row['excuse_status'],
                    'reason' => $row['reason'],
                    'is_school_absence' => $row['is_school_absence'],
                    'source_recorded_at' => $row['source_recorded_at'],
                    'source_recorded_by' => $row['source_recorded_by'],
                ];

                $existing = Absence::where('student_id', $studentId)
                    ->where('source_key', $sourceKey)
                    ->first();

                if ($existing) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    Absence::create(array_merge($attributes, [
                        'student_id' => $studentId,
                        'source_key' => $sourceKey,
                    ]));
                    $created++;
                }
            }

            return $absenceImport;
        });

        $absenceImport->update([
            'matched_count' => $matchedRows,
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'filtered_count' => $filtered,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ]);

        return $absenceImport;
    }

    /**
     * Filtert Zeilen aus ignorierten Kategorien heraus (siehe IGNORED_CATEGORIES).
     */
    private function relevantRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn(array $row) => !in_array($row['category'], self::IGNORED_CATEGORIES, true)
        ));
    }

    /**
     * Gruppiert eindeutige Name+Vorname-Kombinationen und ordnet sie
     * bestehenden Schülern zu (automatisch bei eindeutigem Treffer).
     *
     * @return array<string, array{lastname: string, firstname: string, candidates: \Illuminate\Support\Collection, status: string, student_id: int|null}>
     */
    private function matchStudents(array $rows): array
    {
        $uniqueNames = [];
        foreach ($rows as $row) {
            $uniqueNames[$row['name_key']] = [
                'lastname' => $row['lastname'],
                'firstname' => $row['firstname'],
            ];
        }

        $matching = [];
        foreach ($uniqueNames as $key => $name) {
            // Nachname per SQL vorfiltern (exakt ODER Stammdaten mit
            // vorangestelltem Adelsprädikat/Namenspartikel, siehe
            // lastnameCandidatesQuery() — der Infoportal-Export lässt z. B.
            // "von" weg: Export "Zeppelin" vs. Stammdaten "von Zeppelin").
            // Vornamens-Abgleich danach in PHP, siehe
            // NameMatchingService::firstnameLooksLike() (Modul 8.1).
            $sameLastname = $this->lastnameCandidatesQuery($name['lastname'])
                ->orderBy('id')
                ->get(['id', 'lastname', 'firstname', 'class_name']);

            $students = $sameLastname->filter(
                fn($student) => $this->names->firstnameLooksLike($student->firstname, $name['firstname'])
            )->values();

            $status = match (true) {
                $students->count() === 1 => 'matched',
                $students->count() === 0 => 'unmatched',
                default => 'ambiguous',
            };

            $matching[$key] = [
                'lastname' => $name['lastname'],
                'firstname' => $name['firstname'],
                'candidates' => $students,
                'status' => $status,
                'student_id' => $status === 'matched' ? $students->first()->id : null,
            ];
        }

        return $matching;
    }

    /**
     * Nachnamen-Kandidaten: exakter Treffer ODER Stammdaten-Nachname endet
     * auf ein Leerzeichen + den Export-Nachnamen (deckt vorangestellte
     * Adelsprädikate/Namenspartikel ab, die der Infoportal-Export weglässt,
     * z. B. Export "Zeppelin" vs. Stammdaten "von Zeppelin").
     */
    private function lastnameCandidatesQuery(string $csvLastname)
    {
        // Perioden-Audit (Modul 1.1): nur gegen Schüler der gewählten
        // Periode matchen — sonst könnten Absenzen nach dem Jahreswechsel
        // am archivierten Vorjahres-Datensatz derselben Person landen.
        // Die orWhere-Bedingungen müssen dafür gruppiert bleiben.
        return Student::where('exam_period_id', app(CurrentExamPeriodService::class)->currentId())
            ->where(function ($q) use ($csvLastname) {
                $q->where('lastname', $csvLastname)
                  ->orWhere('lastname', 'LIKE', '% ' . $csvLastname);
            });
    }

    /**
     * Die Vornamens-Toleranz (ehemals firstNameLooksLike()) lebt jetzt in
     * NameMatchingService::firstnameLooksLike() — extrahiert für die
     * Wiederverwendung im Carryover-Abgleich (Modul 1.1), Logik unverändert.
     */

    private function nameKey(string $lastname, string $firstname): string
    {
        return mb_strtolower(trim($lastname)) . '|' . mb_strtolower(trim($firstname));
    }

    /**
     * Natural Key eines Absenz-Ereignisses OHNE Entschuldigungsstatus
     * (siehe Migration-Kommentar in create_absences_table). Bei mehreren
     * identischen Ereignissen am selben Tag (Datenqualität der Quelle,
     * z. B. doppelt erfasste Verspätungen) wird ein stabiles Ordinal anhand
     * von "Absenz eingetragen am" gebildet, damit echte Mehrfacheinträge
     * nicht kollabieren, ein Re-Import aber trotzdem dieselbe Zeile trifft.
     */
    private function buildSourceKey(array $row): string
    {
        $base = implode('|', [
            $row['date_from']?->format('Y-m-d'),
            $row['date_to']?->format('Y-m-d'),
            trim($row['art_raw']),
            $row['hours'],
            $row['minutes'],
        ]);

        return sha1($base . '|' . $row['name_key'] . '#' . $row['ordinal']);
    }

    /**
     * Liest die CSV-Datei und liefert normalisierte Zeilen inkl. Ordinal
     * für Mehrfacheinträge (siehe buildSourceKey()).
     */
    private function readRows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Datei konnte nicht geöffnet werden: {$filePath}");
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('CSV-Datei ist leer oder ungültig.');
        }
        // UTF-8-BOM aus der ersten Spaltenüberschrift entfernen
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $header = array_map(fn($h) => trim((string) $h), $header);

        $rows = [];
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if (count($line) < count($header)) {
                continue; // leere/kaputte Zeile überspringen
            }
            $data = array_combine($header, array_slice($line, 0, count($header)));
            $rows[] = $this->normalizeRow($data);
        }
        fclose($handle);

        $this->assignOrdinals($rows);

        return $rows;
    }

    private function normalizeRow(array $data): array
    {
        $lastname = trim((string) ($data['Name'] ?? ''));
        $firstname = trim((string) ($data['Vorname'] ?? ''));
        $artRaw = trim($this->stripNbsp((string) ($data['Art der Absenz'] ?? '')));
        [$category, $hourFrom, $hourTo, $minutesLate] = $this->normalizeArt($artRaw);

        return [
            'name_key' => $this->nameKey($lastname, $firstname),
            'lastname' => $lastname,
            'firstname' => $firstname,
            'date_from' => $this->parseDate($data['Datum von'] ?? null),
            'date_to' => $this->parseDate($data['Datum bis'] ?? null),
            'days' => (int) ($data['Tage'] ?? 0),
            'hours' => (int) ($data['Stunden'] ?? 0),
            'minutes' => (int) ($data['Minuten'] ?? 0),
            'art_raw' => $artRaw,
            'category' => $category,
            'hour_from' => $hourFrom,
            'hour_to' => $hourTo,
            'minutes_late' => $minutesLate,
            'excuse_status' => $this->normalizeExcuseStatus($data['entschuldigt'] ?? null),
            'reason' => $this->stripNbsp((string) ($data['Grund'] ?? '')) !== ''
                ? trim($this->stripNbsp((string) ($data['Grund'] ?? '')))
                : null,
            'is_school_absence' => trim((string) ($data['Ist Schulabsenz'] ?? '')) !== '',
            'source_recorded_at' => $this->parseDateTime($data['Absenz eingetragen am'] ?? null),
            'source_recorded_by' => trim((string) ($data['Eingetragen von'] ?? '')) ?: null,
        ];
    }

    /**
     * Weist Zeilen mit identischem Basis-Schlüssel ein stabiles Ordinal zu,
     * sortiert nach "Absenz eingetragen am" (stabil über Re-Imports hinweg,
     * solange unveränderte Zeilen ihren Erfassungszeitpunkt behalten).
     */
    private function assignOrdinals(array &$rows): void
    {
        $groups = [];
        foreach ($rows as $i => $row) {
            $groupKey = implode('|', [
                $row['name_key'],
                $row['date_from']?->format('Y-m-d'),
                $row['date_to']?->format('Y-m-d'),
                trim($row['art_raw']),
                $row['hours'],
                $row['minutes'],
            ]);
            $groups[$groupKey][] = $i;
        }

        foreach ($groups as $indices) {
            usort($indices, function ($a, $b) use ($rows) {
                $ta = $rows[$a]['source_recorded_at'];
                $tb = $rows[$b]['source_recorded_at'];
                if (!$ta || !$tb) {
                    return $a <=> $b;
                }
                return $ta->timestamp <=> $tb->timestamp ?: $a <=> $b;
            });
            foreach ($indices as $ordinal => $i) {
                $rows[$i]['ordinal'] = $ordinal;
            }
        }
    }

    private function stripNbsp(string $value): string
    {
        return trim(str_replace("\xc2\xa0", ' ', $value));
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d.m.Y', $value)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d.m.Y H:i:s', $value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Parst die Freitext-Spalte "Art der Absenz" in Kategorie + Stunden/Minuten.
     *
     * @return array{0: string, 1: int|null, 2: int|null, 3: int|null} [category, hourFrom, hourTo, minutesLate]
     */
    public static function normalizeArt(string $art): array
    {
        $art = trim($art);

        if ($art === 'krank') {
            return ['krank', null, null, null];
        }
        if ($art === 'tageweise befreit') {
            return ['tageweise_befreit', null, null, null];
        }
        if (preg_match('/^krank\s+(\d+)\.\s*bis\s*(\d+)\.\s*Stunde$/u', $art, $m)) {
            return ['krank', (int) $m[1], (int) $m[2], null];
        }
        if (preg_match('/^verspätet um (\d+) Minuten in der (\d+)\.\s*Stunde$/u', $art, $m)) {
            return ['verspaetet', (int) $m[2], (int) $m[2], (int) $m[1]];
        }
        if (preg_match('/^befreit in (\d+)\.\s*Stunde$/u', $art, $m)) {
            return ['befreit', (int) $m[1], (int) $m[1], null];
        }
        if (preg_match('/^befreit\s+(\d+)\.\s*bis\s*(\d+)\.\s*Stunde$/u', $art, $m)) {
            return ['befreit', (int) $m[1], (int) $m[2], null];
        }
        if (preg_match('/^fehlt in (\d+)\.\s*Stunde$/u', $art, $m)) {
            return ['fehlt', (int) $m[1], (int) $m[1], null];
        }
        if (preg_match('/^fehlt\s+(\d+)\.\s*bis\s*(\d+)\.\s*Stunde$/u', $art, $m)) {
            return ['fehlt', (int) $m[1], (int) $m[2], null];
        }

        return ['sonstige', null, null, null];
    }

    public static function normalizeExcuseStatus(?string $raw): string
    {
        $raw = trim((string) $raw);

        return match ($raw) {
            'schriftlich' => 'schriftlich',
            'mündlich' => 'muendlich',
            'nein' => 'nein',
            'Attest' => 'attest',
            'schriftlich (Attestpflicht)' => 'attestpflicht',
            default => 'offen',
        };
    }
}
