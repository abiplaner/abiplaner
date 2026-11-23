<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportService
{
    // Der CSV-Import fuer Schueler und Kurse ist am 30.08.2026 entfallen.
    //
    // importFromCsv() und seine sieben Helfer riefen weder ein Controller
    // noch ein Befehl auf; der lebende Weg ist StudentCsvImportService
    // (StudentImportController, routes/tenant.php). Lauffaehig war die Kette
    // ohnehin nicht mehr: extractLevel() lieferte 'K1'/'K2' in courses.level,
    // ein enum('grundlegend','erhoeht') — jeder Insert waere gescheitert.
    //
    // Der Lehrkraft-Import unten ist davon unberuehrt und in Gebrauch.


    /**
     * Vorschau für Lehrer-Import aus CSV
     * 
     * @return array ['teachers' => array, 'stats' => array, 'warnings' => array]
     */
    /**
     * Parsing of Teacher CSV
     * Format: Nr.;Name;Shortcode;Title;Subjects;Type;UPZ
     */
    private function parseTeacherCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \Exception("Datei konnte nicht geöffnet werden: {$filePath}");
        }

        // Header check
        $header = fgetcsv($handle, 0, ';');
        
        // Basic validation of header structure could go here, but we'll be lenient

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            // Skip empty lines or summary lines
            if (empty($data[0]) || !is_numeric($data[0])) {
                continue;
            }

            // Parse Name (Format: "Nachname, Vorname")
            $nameParts = [];
            if (isset($data[1]) && str_contains($data[1], ',')) {
                $nameParts = array_map('trim', explode(',', $data[1], 2));
            }

            // Shortcode
            $shortcode = trim($data[2] ?? '');

            // Title
            $title = trim($data[3] ?? '');

            // Employment Type
            $employmentType = trim($data[5] ?? '');

            // UPZ / Hours
            $hours = isset($data[6]) ? (float)str_replace(',', '.', trim($data[6])) : null;

            // Subjects
            $subjects = [];
            if (isset($data[4]) && !empty($data[4])) {
                $subjectParts = explode(',', $data[4]);
                foreach ($subjectParts as $subject) {
                    // Remove " - GY" and similar suffixes, then trim
                    $cleaned = trim(preg_replace('/ - [A-Z]+/', '', $subject));
                    if (!empty($cleaned)) {
                        $subjects[] = $cleaned;
                    }
                }
            }

            if (!empty($nameParts) && count($nameParts) >= 2 && !empty($shortcode)) {
                $rows[] = [
                    'external_id' => trim($data[0]),
                    'lastname' => $nameParts[0],
                    'firstname' => $nameParts[1],
                    'shortcode' => $shortcode,
                    'title' => $title,
                    'employment_type' => $employmentType,
                    'hours' => $hours,
                    'subjects' => $subjects
                ];
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Normalisiert einen Fachcode für das Matching
     * Entfernt Klammern mit Inhalt, z.B. "PuG (Sk)" -> "PuG"
     * 
     * @param string $code
     * @return string
     */
    private function normalizeSubjectCode(string $code): string
    {
        // Entferne alles in runden Klammern inkl. Klammern und Leerzeichen davor
        $normalized = preg_replace('/\s*\([^)]*\)/', '', $code);
        
        return trim($normalized);
    }

    /**
     * Preview with Diff Logic and Subject Validation
     */
    public function previewTeachersFromCsv(string $schoolId, string $filePath): array
    {
        try {
            $csvRows = $this->parseTeacherCsv($filePath);
            
            // Load all existing subjects for validation (case-insensitive)
            $existingSubjects = Subject::all()->keyBy(function($subject) {
                return strtoupper($subject->code);
            });
            
            // Allow O(1) lookup by shortcode
            $csvTeachers = [];
            foreach ($csvRows as $row) {
                $csvTeachers[$row['shortcode']] = $row;
            }

            // Load existing teachers
            $existingTeachers = Teacher::with(['subjects', 'examinerAssignments', 'assessorAssignments', 'courses', 'supervisions'])
                ->get()
                ->keyBy('shortcode');

            $allTeachers = [];
            $stats = [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'deactivatable' => 0,
                'already_inactive' => 0,
                'subject_errors' => 0
            ];

            // 1. Process CSV Teachers (Create and Update)
            foreach ($csvTeachers as $shortcode => $row) {
                $stats['total']++;

                // Validate subjects
                $validatedSubjects = [];
                $hasSubjectError = false;
                foreach ($row['subjects'] as $subjectCode) {
                    // Normalisiere den Code (entferne Klammern wie bei "PuG (Sk)")
                    $normalizedCode = $this->normalizeSubjectCode($subjectCode);
                    $upperNormalized = strtoupper(trim($normalizedCode));
                    $isValid = isset($existingSubjects[$upperNormalized]);
                    
                    $validatedSubjects[] = [
                        'code' => $subjectCode,  // Original für Anzeige
                        'normalized' => $normalizedCode,  // Normalisiert für Info
                        'valid' => $isValid,
                        'name' => $isValid ? $existingSubjects[$upperNormalized]->name : null
                    ];
                    
                    if (!$isValid) {
                        $hasSubjectError = true;
                    }
                }
                
                if ($hasSubjectError) {
                    $stats['subject_errors']++;
                }

                if (isset($existingTeachers[$shortcode])) {
                    // Update
                    $teacher = $existingTeachers[$shortcode];
                    $changes = [];

                    if ($teacher->lastname !== $row['lastname'] || $teacher->firstname !== $row['firstname']) {
                        $changes[] = "Name: {$teacher->full_name} → {$row['lastname']}, {$row['firstname']}";
                    }
                    if ($teacher->title !== $row['title']) {
                        $changes[] = "Titel: '{$teacher->title}' → '{$row['title']}'";
                    }
                    if ($teacher->employment_type !== $row['employment_type']) {
                        $changes[] = "Beschäftigung: '{$teacher->employment_type}' → '{$row['employment_type']}'";
                    }
                    if (abs(($teacher->hours ?? 0) - ($row['hours'] ?? 0)) > 0.1) {
                         $changes[] = "Stunden: {$teacher->hours} → {$row['hours']}";
                    }
                    if (!$teacher->is_active) {
                        // In der CSV (= aktuelle Personalliste) enthalten →
                        // wird beim Import wieder aktiviert (Modul 1.1)
                        $changes[] = 'Reaktivierung (war inaktiv)';
                    }

                    $allTeachers[] = [
                        'shortcode' => $shortcode,
                        'name' => "{$row['lastname']}, {$row['firstname']}",
                        'title' => $row['title'],
                        'employment_type' => $row['employment_type'],
                        'hours' => $row['hours'],
                        'subjects' => $validatedSubjects,
                        'has_subject_error' => $hasSubjectError,
                        'action' => 'update',
                        'changes' => $changes,
                        'row' => $row
                    ];
                    $stats['updated']++;
                } else {
                    // Create
                    $allTeachers[] = [
                        'shortcode' => $shortcode,
                        'name' => "{$row['lastname']}, {$row['firstname']}",
                        'title' => $row['title'],
                        'employment_type' => $row['employment_type'],
                        'hours' => $row['hours'],
                        'subjects' => $validatedSubjects,
                        'has_subject_error' => $hasSubjectError,
                        'action' => 'create',
                        'changes' => [],
                        'row' => $row
                    ];
                    $stats['created']++;
                }
            }

            // 2. Deaktivierungsvorschläge (Modul 1.1, Entscheidung C):
            // Aktive Lehrkräfte, die in der CSV fehlen, werden NICHT mehr
            // gelöscht, sondern zur Deaktivierung vorgeschlagen (is_active =
            // false, manuell bestätigt — Importlisten können unvollständig
            // sein). Bereits inaktive Lehrkräfte werden nur gezählt.
            // EXTERN ist ein Systemlehrer und erscheint nicht in der CSV.
            foreach ($existingTeachers as $shortcode => $teacher) {
                if ($shortcode === 'EXTERN') {
                    continue; // Systemlehrer nie deaktivieren
                }
                if (!isset($csvTeachers[$shortcode])) {
                    if (!$teacher->is_active) {
                        $stats['already_inactive']++;
                        continue;
                    }

                    // Abhängigkeiten nur als Information (Historie bleibt
                    // bei Deaktivierung ohnehin intakt)
                    $dependencies = [];
                    if ($teacher->examinerAssignments->count() > 0) {
                        $dependencies[] = "{$teacher->examinerAssignments->count()} Prüfungen (Prüfer)";
                    }
                    if ($teacher->assessorAssignments->count() > 0) {
                        $dependencies[] = "{$teacher->assessorAssignments->count()} Prüfungen (Beisitzer)";
                    }
                    if ($teacher->courses->count() > 0) {
                        $dependencies[] = "{$teacher->courses->count()} Kurse";
                    }
                    if ($teacher->supervisions->count() > 0) {
                        $dependencies[] = "{$teacher->supervisions->count()} Aufsichten";
                    }

                    // Get existing subjects for display
                    $existingTeacherSubjects = [];
                    foreach ($teacher->subjects as $subject) {
                        $existingTeacherSubjects[] = [
                            'code' => $subject->code,
                            'valid' => true,
                            'name' => $subject->name
                        ];
                    }

                    $allTeachers[] = [
                        'shortcode' => $shortcode,
                        'name' => $teacher->full_name,
                        'title' => $teacher->title,
                        'employment_type' => $teacher->employment_type,
                        'hours' => $teacher->hours,
                        'subjects' => $existingTeacherSubjects,
                        'has_subject_error' => false,
                        'action' => 'deactivate',
                        'dependencies' => $dependencies,
                        'id' => $teacher->id
                    ];

                    $stats['deactivatable']++;
                }
            }

            return [
                'teachers' => $allTeachers,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            Log::error("Teacher CSV Preview Failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Import Execution with selective ignoring
     *
     * Modul 1.1 (Entscheidung C): Lehrkräfte, die in der CSV fehlen, werden
     * nicht mehr gelöscht. Stattdessen werden die in $deactivate explizit
     * bestätigten Kürzel auf is_active = false gesetzt (FK-Historie bleibt
     * intakt). Lehrkräfte aus der CSV werden beim Upsert (re)aktiviert.
     *
     * @param array<int, string> $ignored    Kürzel, die nicht importiert werden sollen
     * @param array<int, string> $deactivate Kürzel, die deaktiviert werden sollen (manuell bestätigt)
     */
    public function importTeachersFromCsv(string $schoolId, string $filePath, array $ignored = [], array $deactivate = []): array
    {
        // Log::info("DEBUG: importTeachersFromCsv called for school $schoolId");
        $startTime = microtime(true);
        
        try {
            // 1. Parse CSV (Fast)
            // Log::info("DEBUG: Parsing CSV...");
            $csvRows = $this->parseTeacherCsv($filePath);
            // Log::info("DEBUG: CSV parsed. Rows: " . count($csvRows));
            
            // 2. Preload Subjects (Fast) - Preload only what we need
            // Log::info("DEBUG: Preloading subjects...");
            $existingSubjects = Subject::all()->keyBy(function($subject) {
                return strtoupper($subject->code);
            });
            // Log::info("DEBUG: Subjects preloaded. Count: " . $existingSubjects->count());
            
            $imported = 0;
            $updated = 0;
            $deactivated = 0;
            $skipped = 0;
            $errors = [];
            
            // Collect shortcodes from CSV to identify deactivations later
            $csvShortcodes = [];

            DB::transaction(function($connection) use ($schoolId, $csvRows, $ignored, $deactivate, $existingSubjects, &$imported, &$updated, &$deactivated, &$skipped, &$errors, &$csvShortcodes) {
                foreach ($csvRows as $row) {
                    $shortcode = $row['shortcode'];
                    $csvShortcodes[] = $shortcode;
                    
                    // Check if this teacher explicitly ignored by user
                    if (in_array($shortcode, $ignored)) {
                        $skipped++;
                        continue;
                    }
                    
                    try {
                        // Check if exists (for stats)
                        $exists = Teacher::where('shortcode', $shortcode)->exists();
                        
                        $teacher = $this->createOrUpdateTeacher($schoolId, $row);
                        
                        if (!empty($row['subjects'])) {
                            $this->attachSubjects($teacher, $row['subjects'], $existingSubjects, $connection);
                        }
                        
                        if ($exists) {
                            $updated++;
                        } else {
                            $imported++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Fehler bei {$shortcode}: " . $e->getMessage();
                        Log::error("Teacher Import Error processing $shortcode: " . $e->getMessage());
                    }
                }
                
                Log::info("DEBUG: Finished processing CSV rows. Now handling deactivations.");
                
                // 3. Deaktivierungen (Modul 1.1, Entscheidung C): nur explizit
                // bestätigte Kürzel, die tatsächlich in der CSV fehlen und
                // aktuell aktiv sind. EXTERN (Systemlehrer) nie deaktivieren.
                // Kein Auto-Deaktivieren — Importlisten können unvollständig sein.
                if (!empty($deactivate)) {
                    $deactivated = Teacher::whereIn('shortcode', $deactivate)
                        ->whereNotIn('shortcode', array_merge($csvShortcodes, ['EXTERN']))
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }
            });

            // Log::info("DEBUG: Transaction committed.");
            
            ActivityLogger::log(
                'teacher_csv_import_completed',
                'School',
                $schoolId,
                "Lehrer Sync: {$imported} neu, {$updated} aktualisiert, {$deactivated} deaktiviert, {$skipped} ignoriert."
            );
            
            $totalTime = microtime(true) - $startTime;
            Log::info("Teacher Import finished in {$totalTime}s");

            return [
                'imported' => $imported,
                'updated' => $updated,
                'deactivated' => $deactivated,
                'skipped' => $skipped,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error("Teacher CSV Import Failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Creates or updates a teacher model
     *
     * Lehrkräfte aus der aktuellen Personalliste (CSV) werden immer als
     * aktiv geführt — eine zuvor deaktivierte Lehrkraft, die wieder in der
     * Liste auftaucht, wird dadurch reaktiviert (Modul 1.1).
     */
    private function createOrUpdateTeacher(string $schoolId, array $row): Teacher
    {
        return Teacher::updateOrCreate(
            [
                'shortcode' => $row['shortcode']
            ],
            [
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'title' => $row['title'],
                'employment_type' => $row['employment_type'],
                'hours' => $row['hours'],
                'external_id' => $row['external_id'],
                'is_active' => true
            ]
        );
    }

    /**
     * Attaches subjects to a teacher (only existing subjects, no auto-creation)
     * Normalizes subject codes by removing brackets (e.g., "PuG (Sk)" -> "PuG")
     * Optimized to use preloaded subject map
     * 
     * Uses explicit connection for transaction safety
     */
    private function attachSubjects(Teacher $teacher, array $subjectCodes, $existingSubjects, \Illuminate\Database\ConnectionInterface $connection): void
    {
        $subjectIds = [];
        
        foreach ($subjectCodes as $code) {
            // Normalisiere den Code (entferne Klammern wie bei "PuG (Sk)")
            $normalizedCode = $this->normalizeSubjectCode($code);
            $upperNormalized = strtoupper($normalizedCode);
            
            // O(1) Lookup
            if (isset($existingSubjects[$upperNormalized])) {
                $subjectIds[] = $existingSubjects[$upperNormalized]->id;
            } else {
                Log::warning("Fach '{$normalizedCode}' (Original: '{$code}') nicht gefunden beim Lehrer-Import", [
                    'teacher_shortcode' => $teacher->shortcode,
                    'original_code' => $code,
                    'normalized_code' => $normalizedCode
                ]);
            }
        }
        
        if (!empty($subjectIds)) {
            // Manuelle Synchronisation statt Eloquent sync(), um Cross-Database Lock Probleme zu vermeiden
            // Pivot-Tabelle ist in der Tenant-DB
            
            // 1. Hole bestehende Verknüpfungen
            $existing = $connection->table('teacher_subject')
                ->where('teacher_id', $teacher->id)
                ->pluck('subject_id')
                ->toArray();
                
            // 2. Berechne Diff
            $toInsert = array_diff($subjectIds, $existing);
            $toDelete = array_diff($existing, $subjectIds);
            
            // 3. Löschen
            if (!empty($toDelete)) {
                $connection->table('teacher_subject')
                    ->where('teacher_id', $teacher->id)
                    ->whereIn('subject_id', $toDelete)
                    ->delete();
            }
            
            // 4. Einfügen
            if (!empty($toInsert)) {
                $insertData = [];
                foreach ($toInsert as $sid) {
                    $insertData[] = [
                        'teacher_id' => $teacher->id,
                        'subject_id' => $sid
                    ];
                }
                $connection->table('teacher_subject')->insert($insertData);
            }
        } else {
            // Wenn keine Fächer übergeben wurden, alle Verknüpfungen löschen? 
            // Ja, analog zu sync([])
            $connection->table('teacher_subject')
                ->where('teacher_id', $teacher->id)
                ->delete();
        }
    }
}
