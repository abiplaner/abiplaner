<?php

namespace App\Services;

use App\Models\Teacher;
use Illuminate\Support\Collection;

class TimetableImportPreviewService
{
    private array $stats = [
        'total' => 0,
        'valid' => 0,
        'warnings' => 0,
        'errors' => 0,
    ];

    private array $teacherCache = [];
    private array $teacherNameCache = []; // shortcode → "Vorname Nachname"
    private array $teacherDetailsFromASV = []; // Teacher details from Lehrbefähigung file
    private array $issues = [];

    public function __construct()
    {
        // Cache all teachers for quick lookup
        $this->teacherCache = Teacher::pluck('id', 'shortcode')->toArray();

        // Namen-Cache: shortcode → "Vorname Nachname" – verhindert N+1 in validateEntry
        $this->teacherNameCache = Teacher::pluck(
            \App\Support\Sql::verketten(
                (new Teacher())->getConnection()->getDriverName(),
                'firstname', "' '", 'lastname'
            ),
            'shortcode'
        )->toArray();
    }

    /**
     * Parse Lehrbefähigung (ASV) file to get teacher details
     * These are enrichment data - the timetable is the source of truth!
     */
    public function parseTeacherDetails(string $filePath): array
    {
        // Detect encoding and convert to UTF-8
        $fileContent = file_get_contents($filePath);
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
            $tempFile = sys_get_temp_dir() . '/teachers_asv_' . uniqid() . '.csv';
            file_put_contents($tempFile, $fileContent);
            $filePath = $tempFile;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        $teachers = [];
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            
            // Skip empty lines
            if (trim($line) === '') {
                continue;
            }

            // Parse CSV line (semicolon-separated)
            $row = str_getcsv($line, ';');
            
            // Validate minimum fields
            if (count($row) < 3) {
                continue;
            }

            $teacherCode = trim($row[2] ?? ''); // Kürzel in column 3
            
            if (!empty($teacherCode)) {
                $hoursRaw = trim($row[6] ?? '');
                $teachers[$teacherCode] = [
                    'code'            => $teacherCode,
                    'name'            => trim($row[1] ?? ''),    // Name in Spalte 2
                    'position'        => trim($row[3] ?? ''),    // Amtsbezeichnung in Spalte 4
                    'subjects'        => trim($row[4] ?? ''),    // Lehrbefähigungen in Spalte 5
                    'employment_type' => trim($row[5] ?? ''),    // Beschäftigungsumfang (tz/vz) in Spalte 6
                    'hours'           => $hoursRaw !== '' ? (float) str_replace(',', '.', $hoursRaw) : null, // Stunden in Spalte 7
                ];
            }
        }

        fclose($handle);

        // Clean up temp file if created
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }

        $this->teacherDetailsFromASV = $teachers;

        return $teachers;
    }

    /**
     * Parse and validate timetable file without importing
     */
    public function parseFile(string $filePath, string $schoolYear, bool $onlyOberstufe = false): array
    {
        // Detect encoding and convert to UTF-8
        $fileContent = file_get_contents($filePath);
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
            $tempFile = sys_get_temp_dir() . '/timetable_preview_' . uniqid() . '.txt';
            file_put_contents($tempFile, $fileContent);
            $filePath = $tempFile;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        $entries = [];
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $this->stats['total']++;

            // Parse CSV line
            $row = str_getcsv($line);
            
            // Validate minimum fields
            if (count($row) < 7) {
                $this->addIssue('error', $lineNumber, "Unvollständige Zeile (weniger als 7 Felder)");
                $this->stats['errors']++;
                continue;
            }

            $className = trim($row[1] ?? '');
            
            // Filter for Oberstufe if requested
            if ($onlyOberstufe && !in_array($className, ['Q12', 'Q13'])) {
                continue; // Skip silently, not an error
            }

            // Bereitschafts-Zeilen und ähnliche Pseudo-Einträge ignorieren
            if (in_array($className, self::IGNORED_CLASS_NAMES, true)) {
                $this->stats['total']--;
                continue;
            }

            $teacherCode = trim($row[2] ?? '');
            $courseCode = trim($row[3] ?? '');
            $room = trim($row[4] ?? '');
            $dayOfWeek = (int)($row[5] ?? 0);
            $period = (int)($row[6] ?? 0);

            // Validate entry
            $validation = $this->validateEntry(
                $lineNumber,
                $teacherCode,
                $className,
                $courseCode,
                $room,
                $dayOfWeek,
                $period,
                $schoolYear
            );

            // Sonderzeilen (Tag=0, Stunde=0 usw.) lautlos überspringen
            if ($validation['status'] === 'skip') {
                $this->stats['total']--;
                continue;
            }

            $entry = [
                'line_number' => $lineNumber,
                'teacher_code' => $teacherCode,
                'teacher_id' => $validation['teacher_id'],
                'teacher_name' => $validation['teacher_name'],
                'class_name' => $className,
                'course_code' => $courseCode,
                'subject' => $this->extractSubject($courseCode),
                'room' => $room,
                'day_of_week' => $dayOfWeek,
                'period' => $period,
                'school_year' => $schoolYear,
                'status' => $validation['status'], // 'valid', 'warning', 'error'
                'issues' => $validation['issues'],
            ];

            $entries[] = $entry;

            if ($validation['status'] === 'valid') {
                $this->stats['valid']++;
            } elseif ($validation['status'] === 'warning') {
                $this->stats['warnings']++;
            } else {
                $this->stats['errors']++;
            }
        }

        fclose($handle);

        // Clean up temp file if created
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }

        return [
            'entries' => $entries,
            'stats' => $this->stats,
            'issues' => $this->issues,
            'teacher_mapping' => $this->getTeacherMappingSummary($entries),
            'active_teachers' => $this->getActiveTeachersFromEntries($entries),
            'asv_teacher_details' => $this->teacherDetailsFromASV,
            'has_asv_data' => !empty($this->teacherDetailsFromASV),
        ];
    }

    /**
     * Klassen-Bezeichnungen, die keine echten Unterrichtszeilen sind und ignoriert werden.
     * "BER" = Bereitschaft (Raum-/Zeitblockierung, kein Unterricht).
     *
     * @var string[]
     */
    private const IGNORED_CLASS_NAMES = [
        'BP',  // Betriebspräsenz
        'Dir', // Direktion
        'NA',  // Nicht zugeordnet / Raumblockierung
    ];

    /**
     * Get list of all teachers that appear in timetable entries.
     *
     * - Dummy-Lehrer (kein ASV-Eintrag, nicht in DB, kein Fach → z.B. "Na1" zur Raumblockierung)
     *   werden automatisch herausgefiltert.
     * - Kürzel in IGNORED_TEACHER_CODES werden grundsätzlich ignoriert.
     * - Stunden werden als eindeutige (Tag, Stunde)-Kombinationen gezählt, damit
     *   gekoppelte Kurse (BRN unterrichtet 5 Klassen gleichzeitig) nur einmal zählen.
     */
    private function getActiveTeachersFromEntries(array $entries): array
    {
        $activeTeachers = [];

        // Eindeutige Zeitslots je Lehrer sammeln
        $timeSlots = []; // teacherCode => Set von "day.period"-Strings
        // Kurscodes je Lehrer für spätere Fächer-Extraktion
        $teacherCourseCodes = []; // teacherCode => [course_code, ...]

        foreach ($entries as $entry) {
            $teacherCode = $entry['teacher_code'];
            if (empty($teacherCode)) {
                continue;
            }

            // Kurscodes für spätere Fächer-Extraktion sammeln
            if (!empty($entry['course_code'])) {
                $teacherCourseCodes[$teacherCode][] = $entry['course_code'];
            }

            // Erstmaliges Vorkommen: Datensatz anlegen
            if (!isset($activeTeachers[$teacherCode])) {
                $asvData = $this->teacherDetailsFromASV[$teacherCode] ?? null;
                $existsInDb = isset($this->teacherCache[$teacherCode]);
                $hasAsvData = $asvData !== null;

                // Dummy-Erkennung: nicht in DB, kein ASV-Eintrag → nur überspringen wenn das
                // Kürzel kein echtes Lehrerkürzel ist. Echte Kürzel bestehen aus 2–5 Großbuchstaben
                // (z.B. HMB, TRZ, SMZ). Raum-Blocker wie "Na1" oder "Frei" enthalten Ziffern
                // oder Kleinbuchstaben.
                if (!$existsInDb && !$hasAsvData) {
                    if (!preg_match('/^[A-ZÄÖÜ]{2,5}$/', $teacherCode)) {
                        continue; // Kein echtes Lehrerkürzel → überspringen
                    }
                }

                $activeTeachers[$teacherCode] = [
                    'code'                 => $teacherCode,
                    'name'                 => $asvData['name'] ?? null,
                    'position'             => $asvData['position'] ?? null,
                    'subjects'             => $asvData['subjects'] ?? null,
                    'employment_type'      => $asvData['employment_type'] ?? null,
                    'hours'                => $asvData['hours'] ?? null,
                    'exists_in_db'         => $existsInDb,
                    'has_asv_data'         => $hasAsvData,
                    'entry_count'          => 0,
                    'subjects_from_courses' => null, // wird unten befüllt
                ];
                $timeSlots[$teacherCode] = [];
            }

            // Zeitslot als "tag.stunde" dedup
            if (isset($entry['day_of_week'], $entry['period'])) {
                $slot = $entry['day_of_week'] . '.' . $entry['period'];
                $timeSlots[$teacherCode][$slot] = true;
            }
        }

        // entry_count setzen + Fächer aus Kurscodes für unbekannte Lehrer extrahieren
        foreach ($activeTeachers as $code => &$teacher) {
            $teacher['entry_count'] = count($timeSlots[$code] ?? []);

            if (!$teacher['has_asv_data'] && !$teacher['exists_in_db']) {
                $teacher['subjects_from_courses'] = $this->extractSubjectsFromCourseCodes(
                    array_unique($teacherCourseCodes[$code] ?? [])
                );
            }
        }
        unset($teacher);

        return array_values($activeTeachers);
    }

    /**
     * Extrahiert eindeutige Fachkürzel aus Kurscodes.
     * Unterstützte Formate:
     *   "1E-gA"  → "E"      (Ziffer + Fach + Bindestrich + Niveau)
     *   "2Ph"    → "PH"     (Ziffer + Fach ohne Bindestrich)
     *   "1EnKu"  → "EN"     (Ziffer + Fach bis Großbuchstaben-Wechsel)
     *   "E"      → "E"      (reiner Fachcode ohne Ziffer-Präfix)
     *
     * Gibt eine kommagetrennte Liste zurück, die direkt als Lehrbefähigungs-String verwendbar ist.
     */
    private function extractSubjectsFromCourseCodes(array $courseCodes): string
    {
        $subjects = [];

        \Illuminate\Support\Facades\Log::debug('[TimetableImport] extractSubjectsFromCourseCodes', [
            'courseCodes' => $courseCodes,
        ]);

        foreach ($courseCodes as $raw) {
            $code = trim($raw);
            if ($code === '') {
                continue;
            }

            // 1. "1E-gA", "2Ph-eA", "1Ek" → Buchstaben nach führenden Ziffern, bis '-' oder Ende
            if (preg_match('/^\d+([A-Za-z]+?)(?:-|$)/u', $code, $m)) {
                $subjects[strtoupper($m[1])] = true;
                continue;
            }

            // 2. Kein Ziffer-Präfix: nimm den ersten Buchstabenblock (z.B. "E", "Ph", "Geo")
            if (preg_match('/^([A-Za-z]+)/u', $code, $m)) {
                $subjects[strtoupper($m[1])] = true;
            }
        }

        ksort($subjects);
        $result = implode(', ', array_keys($subjects));

        \Illuminate\Support\Facades\Log::debug('[TimetableImport] extractSubjectsFromCourseCodes result', [
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Validate a single entry
     */
    private function validateEntry(
        int $lineNumber,
        string $teacherCode,
        string $className,
        string $courseCode,
        string $room,
        int $dayOfWeek,
        int $period,
        string $schoolYear
    ): array {
        $issues = [];
        $status = 'valid';
        $teacherId = null;
        $teacherName = null;

        // Sonderzeilen aus Untis: ungültiger Tag oder ungültige Stunde → sofort lautlos überspringen,
        // bevor weitere Validierung läuft (damit kein falscher error-Status entsteht)
        if ($dayOfWeek < 1 || $dayOfWeek > 5) {
            return ['status' => 'skip', 'issues' => [], 'teacher_id' => null, 'teacher_name' => null];
        }
        if ($period < 1 || $period > 12) {
            return ['status' => 'skip', 'issues' => [], 'teacher_id' => null, 'teacher_name' => null];
        }

        // Check required fields
        if (empty($teacherCode)) {
            $issues[] = "Fehlendes Lehrerkürzel";
            $status = 'error';
        } else {
            // Try to get teacher from database first
            if (isset($this->teacherCache[$teacherCode])) {
                $teacherId   = $this->teacherCache[$teacherCode];
                $teacherName = $this->teacherNameCache[$teacherCode] ?? null;
            } else {
                // Not in DB yet - check if we have ASV details
                if (isset($this->teacherDetailsFromASV[$teacherCode])) {
                    // We have ASV data for this teacher - will be imported
                    $teacherName = $this->teacherDetailsFromASV[$teacherCode]['name'] . ' (neu)';
                } else {
                    // No ASV data - show warning
                    $teacherName = null;
                    $issues[] = "Lehrer '$teacherCode' nicht in Lehrbefähigung-Datei (Name fehlt)";
                    $status = 'warning';
                    $this->addIssue('warning', $lineNumber, "Lehrer '$teacherCode' nicht in ASV-Daten gefunden", $teacherCode);
                }
            }
        }

        if (empty($className)) {
            $issues[] = "Fehlende Klassenbezeichnung";
            $status = 'error';
        }

        if (empty($courseCode)) {
            // Fehlender Kurscode: nur als Info, kein Warning – wird trotzdem importiert
            $issues[] = 'Kein Kurscode';
        }

        if (empty($room)) {
            // Fehlender Raum: in Untis normal, kein Warning
            $issues[] = 'Kein Raum eingetragen';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'teacher_id' => $teacherId,
            'teacher_name' => $teacherName,
        ];
    }

    /**
     * Add issue to issues list
     */
    private function addIssue(string $type, int $lineNumber, string $message, ?string $teacherCode = null): void
    {
        $this->issues[] = [
            'type' => $type,
            'line_number' => $lineNumber,
            'message' => $message,
            'teacher_code' => $teacherCode,
        ];
    }

    /**
     * Get summary of teacher mapping
     */
    private function getTeacherMappingSummary(array $entries): array
    {
        $found = [];
        $notFound = [];

        foreach ($entries as $entry) {
            $teacherCode = $entry['teacher_code'];
            
            if ($entry['teacher_id']) {
                if (!isset($found[$teacherCode])) {
                    $found[$teacherCode] = [
                        'code' => $teacherCode,
                        'name' => $entry['teacher_name'],
                        'count' => 0,
                    ];
                }
                $found[$teacherCode]['count']++;
            } else {
                if (!isset($notFound[$teacherCode])) {
                    $notFound[$teacherCode] = [
                        'code' => $teacherCode,
                        'count' => 0,
                    ];
                }
                $notFound[$teacherCode]['count']++;
            }
        }

        return [
            'found' => array_values($found),
            'not_found' => array_values($notFound),
        ];
    }

    /**
     * Extract subject name from course code
     */
    private function extractSubject(string $courseCode): ?string
    {
        if (empty($courseCode)) {
            return null;
        }

        $subjectMap = [
            'd' => 'Deutsch',
            'e' => 'Englisch',
            'f' => 'Französisch',
            'l' => 'Latein',
            's' => 'Spanisch',
            'm' => 'Mathematik',
            'ph' => 'Physik',
            'c' => 'Chemie',
            'b' => 'Biologie',
            'inf' => 'Informatik',
            'g' => 'Geschichte',
            'geo' => 'Geographie',
            'wr' => 'Wirtschaft und Recht',
            'pug' => 'Politik und Gesellschaft',
            'smw' => 'Sport männlich/weiblich',
            'eth' => 'Ethik',
            'ev' => 'Evangelische Religion',
            'k' => 'Katholische Religion',
            'ku' => 'Kunst',
            'mu' => 'Musik',
            'it' => 'Informationstechnologie',
            'psy' => 'Psychologie',
        ];

        $code = strtolower($courseCode);
        
        if (preg_match('/^\d+([a-z]+)/', $code, $matches)) {
            $subjectCode = $matches[1];
            return $subjectMap[$subjectCode] ?? ucfirst($subjectCode);
        }

        if (preg_match('/^\d+([a-z]+)-/i', $code, $matches)) {
            $subjectCode = strtolower($matches[1]);
            return $subjectMap[$subjectCode] ?? ucfirst($matches[1]);
        }

        return null;
    }
}
