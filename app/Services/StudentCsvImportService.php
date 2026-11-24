<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Kapselt die gesamte Schüler-CSV-Import-Logik.
 *
 * Vorher als private Methoden im ImportController (1139 Zeilen).
 * Der Controller bleibt damit schlank und delegiert alle Parsing-,
 * Validierungs- und Persistenz-Aufgaben an diesen Service.
 */
class StudentCsvImportService
{
    private array $importStats = [
        'students_created'  => 0,
        'students_updated'  => 0,
        'exams_created'     => 0,
        'exams_updated'     => 0,
        'teachers_created'  => 0,
        'teachers_found'    => 0,
        'subjects_created'  => 0,
        'subjects_found'    => 0,
        'courses_created'   => 0,
        'courses_found'     => 0,
        'errors'            => [],
    ];
    // =========================================================================
    // Oeffentliche API
    // =========================================================================

    /**
     * Parst eine CSV-Datei zur Vorschau, ohne etwas zu speichern.
     *
     * @return array{students: array, stats: array, subjects: array, teachers: array}
     */
    public function parseCSVPreview(string $filePath, string $delimiter, ExamPeriod $examPeriod): array
    {
        $handle = fopen($filePath, 'r');
        $students = [];
        $stats = [
            'total_lines'       => 0,
            'header_lines'      => 0,
            'valid_students'    => 0,
            'skipped_students'  => 0,
            'missing_s1'        => 0,
            'missing_s2'        => 0,
            'missing_s3'        => 0,
            'missing_k1'        => 0,
            'missing_k2'        => 0,
            'validation_errors' => 0,
        ];

        $existingSubjects = Subject::pluck('name', 'code')->toArray();
        $existingTeachers = Teacher::pluck('id', 'shortcode')->toArray();

        try {
            $lineNumber  = 0;
            $studentData = null;

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $lineNumber++;
                $stats['total_lines']++;

                if ($lineNumber === 1) {
                    $stats['header_lines']++;
                    continue;
                }

                if ($this->isStudentMarkerLine($data)) {
                    $studentData = $data;
                    continue;
                }

                if ($this->isStudentDataLine($data, $studentData)) {
                    $name = $this->splitStudentName($studentData);

                    if ($name === null) {
                        $stats['skipped_students']++;
                        $studentData = null;
                        continue;
                    }

                    [$lastname, $firstname] = $name;

                    $hasCourses = false;
                    for ($i = 2; $i <= 10; $i += 2) {
                        if (isset($data[$i]) && !empty(trim($data[$i]))) {
                            $hasCourses = true;
                            break;
                        }
                    }

                    if ($hasCourses) {
                        $stats['valid_students']++;
                    } else {
                        $stats['skipped_students']++;
                    }

                    $exams = [
                        's1' => $this->validateExam($data, 2,  3,  $existingSubjects, $existingTeachers),
                        's2' => $this->validateExam($data, 4,  5,  $existingSubjects, $existingTeachers),
                        's3' => $this->validateExam($data, 6,  7,  $existingSubjects, $existingTeachers),
                        'k1' => $this->validateExam($data, 8,  9,  $existingSubjects, $existingTeachers),
                        'k2' => $this->validateExam($data, 10, 11, $existingSubjects, $existingTeachers),
                    ];

                    if (!$exams['s1']['course']) $stats['missing_s1']++;
                    if (!$exams['s2']['course']) $stats['missing_s2']++;
                    if (!$exams['s3']['course']) $stats['missing_s3']++;
                    if (!$exams['k1']['course']) $stats['missing_k1']++;
                    if (!$exams['k2']['course']) $stats['missing_k2']++;

                    $hasErrors = false;
                    foreach ($exams as $exam) {
                        if ($exam['has_error']) {
                            $hasErrors = true;
                            $stats['validation_errors']++;
                            break;
                        }
                    }

                    $students[] = [
                        'line'             => $lineNumber,
                        'external_id'      => trim($data[0] ?? ''),
                        'lastname'         => $lastname,
                        'firstname'        => $firstname,
                        'has_errors'       => $hasErrors,
                        'will_be_imported' => $hasCourses,
                        's1' => $exams['s1'],
                        's2' => $exams['s2'],
                        's3' => $exams['s3'],
                        'k1' => $exams['k1'],
                        'k2' => $exams['k2'],
                    ];

                    $studentData = null;
                } else {
                    $stats['skipped_students']++;
                }
            }

            fclose($handle);

            return [
                'students' => $students,
                'stats'    => $stats,
                'subjects' => $existingSubjects,
                'teachers' => $existingTeachers,
            ];

        } catch (\Exception $e) {
            fclose($handle);
            throw $e;
        }
    }

    /**
     * Erkennt anhand der Kurscodes in der Vorschau automatisch die Jahrgangsstufe:
     * Kurscodes mit führender "1" (z. B. 1d1, 1E-L) → Q12 ("12"),
     * Kurscodes mit führender "2" (z. B. 2d4, 2B-L) → Q13 ("13").
     *
     * @param array{students?: array<int, array<string, mixed>>} $preview
     */
    public function detectGradeFromPreview(array $preview): ?string
    {
        $q12Count = 0;
        $q13Count = 0;

        foreach ($preview['students'] ?? [] as $student) {
            foreach (['s1', 's2', 's3', 'k1', 'k2'] as $examType) {
                $courseCode = trim((string) ($student[$examType]['course'] ?? ''));
                if ($courseCode === '') {
                    continue;
                }
                if (preg_match('/^1[A-Za-z]/', $courseCode)) {
                    $q12Count++;
                } elseif (preg_match('/^2[A-Za-z]/', $courseCode)) {
                    $q13Count++;
                }
            }
        }

        if ($q12Count > 0 && $q12Count >= $q13Count * 2) {
            return '12';
        }

        if ($q13Count > 0 && $q13Count >= $q12Count * 2) {
            return '13';
        }

        return null;
    }

    /**
     * Kurscodes der Datei, die nicht zur gewählten Jahrgangsstufe gehören.
     *
     * Kurse der Q12 beginnen mit "1", Kurse der Q13 mit "2". Kurse mit "3"
     * sind jahrgangsübergreifend und in beiden Stufen erlaubt, alle übrigen
     * Codes prüft die Methode nicht. Am 22.10.2026 landete die Q12 als
     * Stufe 13 im Abitur 2027 — die Erkennung in detectGradeFromPreview() ist
     * nur ein Vorschlag, den die Vorschau überschreiben lässt. Diese Prüfung
     * ist hart: processCSV() bricht ab, bevor etwas geschrieben wird.
     *
     * @return list<string> sortiert, ohne Dubletten; leer = alles passt
     */
    public function kurseFremderStufe(string $filePath, string $delimiter, string $grade): array
    {
        $fremdesPraefix = match ($grade) {
            '12'    => '2',
            '13'    => '1',
            default => null,
        };

        if ($fremdesPraefix === null) {
            return [];
        }

        $codes = [];
        foreach ($this->parseCsvToStudentArray($filePath, $delimiter) as $schueler) {
            foreach ($schueler['courses'] as $kurs) {
                if (str_starts_with($kurs['course_code'], $fremdesPraefix)) {
                    $codes[$kurs['course_code']] = true;
                }
            }
        }

        $codes = array_map('strval', array_keys($codes));
        sort($codes);

        return $codes;
    }

    /**
     * Fehlermeldung zu kurseFremderStufe(), für Vorschau und Import gleich.
     *
     * @param list<string> $codes
     */
    public function meldungFremdeStufe(string $grade, array $codes): string
    {
        $erwartet = $grade === '12' ? '1' : '2';
        $liste    = implode(', ', array_slice($codes, 0, 10)) . (count($codes) > 10 ? ', …' : '');

        return "Die Datei passt nicht zur Q{$grade}: Kurse der Q{$grade} beginnen mit „{$erwartet}“ "
            . "(jahrgangsübergreifende mit „3“). Gefunden: {$liste}.";
    }

    /**
     * Berechnet den Unterschied zwischen CSV und bestehenden DB-Daten.
     *
     * Gibt zurück:
     *   new_students      – Schüler in CSV, aber nicht in DB
     *   updated_students  – Schüler in DB, deren Name sich geändert hat
     *   removed_students  – Schüler in DB, aber nicht in CSV
     *   new_exams         – Prüfungen in CSV, aber nicht in DB (Schüler+Kurscode)
     *   updated_exams     – Prüfungen in DB mit geändertem Lehrer
     *   removed_exams     – Prüfungen in DB, aber nicht in CSV
     *   unchanged_count   – Anzahl unveränderter Datensätze
     *
     * @return array<string, mixed>
     */
    public function computeDiff(
        string     $filePath,
        string     $delimiter,
        ExamPeriod $examPeriod,
        string     $grade,
        array      $corrections = []
    ): array {
        // --- CSV parsen ---
        $csvStudents = $this->parseCsvToStudentArray($filePath, $delimiter);

        // --- DB laden ---
        $dbStudents = Student::with([
                'exams.course.subject',
                'exams.examiner',
            ])
            ->where('exam_period_id', $examPeriod->id)
            ->where('grade', $grade)
            ->get()
            ->keyBy('external_id');

        $diff = [
            'new_students'     => [],
            'updated_students' => [],
            'removed_students' => [],
            'new_exams'        => [],
            'updated_exams'    => [],
            'removed_exams'    => [],
            'unchanged_count'  => 0,
        ];

        $csvExternalIds = [];

        foreach ($csvStudents as $csvStudent) {
            $extId = $csvStudent['external_id'];
            $csvExternalIds[] = $extId;

            // Lehrer-Korrekturen anwenden
            $csvCourses = [];
            foreach ($csvStudent['courses'] as $course) {
                $teacher = $course['teacher'];
                if (isset($corrections[$teacher])) {
                    $teacher = $corrections[$teacher]['to'];
                }
                $csvCourses[] = array_merge($course, ['teacher' => $teacher]);
            }

            if (!$dbStudents->has($extId)) {
                // Neuer Schüler
                $diff['new_students'][] = [
                    'external_id' => $extId,
                    'lastname'    => $csvStudent['lastname'],
                    'firstname'   => $csvStudent['firstname'],
                    'courses'     => $csvCourses,
                ];
                continue;
            }

            $dbStudent = $dbStudents->get($extId);

            // Name geändert?
            $nameChanged = $dbStudent->lastname !== $csvStudent['lastname']
                        || $dbStudent->firstname !== $csvStudent['firstname'];

            if ($nameChanged) {
                $diff['updated_students'][] = [
                    'external_id'   => $extId,
                    'old_lastname'  => $dbStudent->lastname,
                    'old_firstname' => $dbStudent->firstname,
                    'new_lastname'  => $csvStudent['lastname'],
                    'new_firstname' => $csvStudent['firstname'],
                ];
            }

            // Prüfungen vergleichen
            // DB-Kurscodes für diesen Schüler (course_code → [examiner_shortcode, exam_type])
            $dbExams = [];
            foreach ($dbStudent->exams as $se) {
                $dbExams[$se->course->course_code] = [
                    // @phpstan-ignore-next-line nullsafe.neverNull
                    'examiner'  => $se->examiner?->shortcode ?? 'EXTERN',
                    'exam_type' => $se->exam_type,
                ];
            }

            $csvCourseCodes = [];
            foreach ($csvCourses as $course) {
                if (!$course['course_code']) continue;
                $csvCourseCodes[] = $course['course_code'];

                if (!isset($dbExams[$course['course_code']])) {
                    // Neue Prüfung für bestehenden Schüler
                    $diff['new_exams'][] = [
                        'student_external_id' => $extId,
                        'student_name'        => $dbStudent->full_name,
                        'course_code'         => $course['course_code'],
                        'teacher'             => $course['teacher'],
                        'exam_type'           => $course['exam_type'],
                    ];
                } elseif ($dbExams[$course['course_code']]['examiner'] !== $course['teacher']) {
                    // Prüfer geändert
                    $diff['updated_exams'][] = [
                        'student_external_id' => $extId,
                        'student_name'        => $dbStudent->full_name,
                        'course_code'         => $course['course_code'],
                        'old_teacher'         => $dbExams[$course['course_code']]['examiner'],
                        'new_teacher'         => $course['teacher'],
                        'exam_type'           => $course['exam_type'],
                    ];
                } else {
                    $diff['unchanged_count']++;
                }
            }

            // Entfernte Prüfungen (in DB, aber nicht in CSV)
            foreach (array_keys($dbExams) as $dbCourseCode) {
                if (!in_array($dbCourseCode, $csvCourseCodes, true)) {
                    $diff['removed_exams'][] = [
                        'student_external_id' => $extId,
                        'student_name'        => $dbStudent->full_name,
                        'course_code'         => $dbCourseCode,
                        'teacher'             => $dbExams[$dbCourseCode]['examiner'],
                        'exam_type'           => $dbExams[$dbCourseCode]['exam_type'],
                    ];
                }
            }
        }

        // Entfernte Schüler (in DB, aber nicht in CSV)
        foreach ($dbStudents as $extId => $dbStudent) {
            if (!in_array((string)$extId, $csvExternalIds, true)) {
                $diff['removed_students'][] = [
                    'external_id' => $extId,
                    'lastname'    => $dbStudent->lastname,
                    'firstname'   => $dbStudent->firstname,
                    'exam_count'  => $dbStudent->exams->count(),
                ];
            }
        }

        return $diff;
    }

    /**
     * Erkennt die Markerzeile eines Schuelerdatensatzes: Spalte 1 enthaelt
     * "Nachname, Vorname", Spalte 0 (externe ID) ist noch leer. Die eigentlichen
     * Daten (externe ID, Kurse) folgen erst in der naechsten Zeile.
     *
     * @param array<int, string|null> $data
     */
    private function isStudentMarkerLine(array $data): bool
    {
        return !empty($data[1]) && str_contains($data[1], ',') && empty(trim($data[0] ?? ''));
    }

    /**
     * Erkennt die Abschlusszeile eines Schuelerdatensatzes: Spalte 0 (externe ID)
     * ist gefuellt, Spalte 1 ist leer, und eine Markerzeile wurde zuvor gesehen.
     *
     * @param array<int, string|null> $data
     * @param array<int, string|null>|null $studentData Zuvor gemerkte Markerzeile
     */
    private function isStudentDataLine(array $data, ?array $studentData): bool
    {
        return !empty(trim($data[0] ?? '')) && empty(trim($data[1] ?? '')) && $studentData !== null;
    }

    /**
     * Zerlegt die Namensangabe der Markerzeile ("Nachname, Vorname") in ihre
     * Teile. Gibt null zurueck, wenn Nach- oder Vorname fehlt.
     *
     * @param array<int, string|null> $studentData Markerzeile (Spalte 1 = "Nachname, Vorname")
     * @return array{0: string, 1: string}|null
     */
    private function splitStudentName(array $studentData): ?array
    {
        $nameParts = explode(',', $studentData[1]);
        $lastname  = trim($nameParts[0] ?? '');
        $firstname = trim($nameParts[1] ?? '');

        if (empty($lastname) || empty($firstname)) {
            return null;
        }

        return [$lastname, $firstname];
    }

    /**
     * Parst die CSV-Datei in ein einfaches Array-Format (ohne DB-Zugriff).
     *
     * @return array<int, array{external_id: string, lastname: string, firstname: string, courses: array}>
     */
    private function parseCsvToStudentArray(string $filePath, string $delimiter): array
    {
        $handle  = fopen($filePath, 'r');
        $results = [];

        try {
            $lineNumber  = 0;
            $studentData = null;

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) continue;

                if ($this->isStudentMarkerLine($data)) {
                    $studentData = $data;
                    continue;
                }

                if ($this->isStudentDataLine($data, $studentData)) {
                    $name = $this->splitStudentName($studentData);

                    if ($name === null) {
                        $studentData = null;
                        continue;
                    }

                    [$lastname, $firstname] = $name;

                    $courses = [];
                    foreach ([[2, 3, 'written'], [4, 5, 'written'], [6, 7, 'written'], [8, 9, 'oral'], [10, 11, 'oral']] as [$ci, $ti, $type]) {
                        $courseCode  = isset($data[$ci])  && !empty(trim($data[$ci]))  ? trim($data[$ci])  : null;
                        $teacherCode = isset($data[$ti]) && !empty(trim($data[$ti])) ? trim($data[$ti]) : 'EXTERN';
                        if ($courseCode) {
                            $courses[] = [
                                'course_code' => $courseCode,
                                'teacher'     => $teacherCode,
                                'exam_type'   => $type,
                            ];
                        }
                    }

                    if (!empty($courses)) {
                        $results[] = [
                            'external_id' => trim($data[0] ?? ''),
                            'lastname'    => $lastname,
                            'firstname'   => $firstname,
                            'courses'     => $courses,
                        ];
                    }

                    $studentData = null;
                }
            }

            fclose($handle);
            return $results;

        } catch (\Exception $e) {
            fclose($handle);
            throw $e;
        }
    }

    /**
     * Loescht existierende Schueler, Pruefungen und Planungen fuer den Pruefungszeitraum
     * (bzw. gezielt nur fuer die angegebene Jahrgangsstufe $grade).
     */
    public function clearExistingData(ExamPeriod $examPeriod, ?string $grade = null): void
    {
        // Portabel statt SET FOREIGN_KEY_CHECKS. Achtung SQLite (lokale
        // Edition): Innerhalb einer Transaktion wirkt PRAGMA foreign_keys
        // nicht — dort bleiben die Fremdschlüssel aktiv, und ON DELETE CASCADE
        // räumt mit ab. Die Reihenfolge unten (Kinder vor Eltern) trägt das.
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        try {
            $studentQuery = DB::table('students')->where('exam_period_id', $examPeriod->id);
            if ($grade) {
                $studentQuery->where('grade', $grade);
            }
            $studentIds = $studentQuery->pluck('id');

            if ($studentIds->isNotEmpty()) {
                $studentExamIds = DB::table('student_exams')
                    ->whereIn('student_id', $studentIds)
                    ->pluck('id');

                if ($studentExamIds->isNotEmpty()) {
                    DB::table('exam_assignments')
                        ->whereIn('student_exam_id', $studentExamIds)
                        ->delete();
                }

                DB::table('student_exams')->whereIn('student_id', $studentIds)->delete();

                // Die Wahlen der Q11 haengen ebenfalls am Schueler. Ohne diese
                // beiden Zeilen bleiben sie als Karteileichen stehen: Die
                // Fremdschluessel stehen zwar auf ON DELETE CASCADE, aber
                // FOREIGN_KEY_CHECKS ist hier ausgeschaltet — MariaDB raeumt
                // dann nichts hinterher. In den Echtdaten war so eine W-Seminar-Wahl
                // uebrig geblieben (schueler_id 258, mit Zuteilung auf einen
                // Kurs) und stand in der Liste als "Schueler geloescht (ID:
                // 258)". Sie belegte dabei weiter einen Platz. (31.08.2026)
                DB::table('wseminar_wahlen')->whereIn('schueler_id', $studentIds)->delete();
                DB::table('lk_wahlen')->whereIn('schueler_id', $studentIds)->delete();

                // Schüler, zu denen bereits Absenzen, Maßnahmen oder Vorjahres-Carryovers
                // existieren, behalten ihren Datensatz (ID bleibt stabil), damit beim
                // anschließenden processCSV() ihre Absenzen nicht verwaisen.
                $protectedStudentIds = collect()
                    ->merge(DB::table('absences')->whereIn('student_id', $studentIds)->pluck('student_id'))
                    ->merge(DB::table('absence_measures')->whereIn('student_id', $studentIds)->pluck('student_id'))
                    ->merge(DB::table('absence_carryovers')->whereIn('matched_student_id', $studentIds)->pluck('matched_student_id'))
                    ->unique()
                    ->values();

                $deleteQuery = DB::table('students')
                    ->where('exam_period_id', $examPeriod->id)
                    ->whereIn('id', $studentIds);

                if ($protectedStudentIds->isNotEmpty()) {
                    $deleteQuery->whereNotIn('id', $protectedStudentIds);
                    // Externe IDs der geschützten Schüler temporär freigeben, damit es bei
                    // geänderter Nummerierung in processCSV() keinen Unique-Konflikt gibt.
                    DB::table('students')
                        ->whereIn('id', $protectedStudentIds)
                        ->update(['external_id' => \App\Support\Sql::verketten(DB::getDriverName(), "'__keep_'", 'id')]);
                }

                $deleteQuery->delete();
            }

            // Nur Kurse löschen, die von keiner (verbleibenden) Prüfung eines anderen Jahrgangs genutzt werden
            $usedCourseIds = DB::table('student_exams')->distinct()->pluck('course_id');

            $unusedCourseQuery = DB::table('courses')->where('exam_period_id', $examPeriod->id);
            if ($grade && $usedCourseIds->isNotEmpty()) {
                $unusedCourseQuery->whereNotIn('id', $usedCourseIds);
            }
            $courseIdsToDelete = $unusedCourseQuery->pluck('id');

            if ($courseIdsToDelete->isNotEmpty()) {
                $assignmentIds = DB::table('written_exam_assignments')
                    ->whereIn('course_id', $courseIdsToDelete)
                    ->pluck('id');

                if ($assignmentIds->isNotEmpty()) {
                    DB::table('written_exam_seat_assignments')
                        ->whereIn('written_exam_assignment_id', $assignmentIds)
                        ->delete();
                    DB::table('supervisions')
                        ->whereIn('written_exam_assignment_id', $assignmentIds)
                        ->delete();
                    DB::table('written_exam_assignments')
                        ->whereIn('id', $assignmentIds)
                        ->delete();
                }

                DB::table('courses')->whereIn('id', $courseIdsToDelete)->delete();
            }

            if (!$grade) {
                $timeSlotIds = DB::table('time_slots')->where('exam_period_id', $examPeriod->id)->pluck('id');
                if ($timeSlotIds->isNotEmpty()) {
                    DB::table('supervisions')->whereIn('time_slot_id', $timeSlotIds)->delete();
                }
            }

        } finally {
            \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Liest die CSV und persistiert alle Schueler/Pruefungen in der DB.
     *
     * @param array<string, mixed> $corrections  Lehrer-Korrekturen aus der interaktiven Vorschau
     */
    public function processCSV(
        string     $filePath,
        string     $delimiter,
        ExamPeriod $examPeriod,
        array      $corrections = [],
        string     $grade       = '12',
        string     $schoolId    = ''
    ): void {
        $fremd = $this->kurseFremderStufe($filePath, $delimiter, $grade);
        if ($fremd !== []) {
            throw new \InvalidArgumentException($this->meldungFremdeStufe($grade, $fremd));
        }

        $handle = fopen($filePath, 'r');
        DB::beginTransaction();

        try {
            $lineNumber  = 0;
            $studentData = null;

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) continue;

                if ($this->isStudentMarkerLine($data)) {
                    $studentData = $data;
                    continue;
                }

                if ($this->isStudentDataLine($data, $studentData)) {
                    $name = $this->splitStudentName($studentData);

                    if ($name === null) {
                        $studentData = null;
                        continue;
                    }

                    [$lastname, $firstname] = $name;

                    $hasCourses = false;
                    for ($i = 2; $i <= 10; $i += 2) {
                        if (isset($data[$i]) && !empty(trim($data[$i]))) {
                            $hasCourses = true;
                            break;
                        }
                    }
                    if (!$hasCourses) {
                        $studentData = null;
                        continue;
                    }

                    $extId = trim($data[0] ?? '');

                    // 1. Prüfen, ob in derselben Periode + Jahrgangsstufe bereits ein
                    //    Schüler mit dieser external_id oder (aus clearExistingData geschützt)
                    //    mit identischem Nach- und Vornamen existiert.
                    $student = Student::where('exam_period_id', $examPeriod->id)
                        ->where('grade', $grade)
                        ->where('external_id', $extId)
                        ->first();

                    if (!$student) {
                        $student = Student::where('exam_period_id', $examPeriod->id)
                            ->where('grade', $grade)
                            ->where('lastname', $lastname)
                            ->where('firstname', $firstname)
                            ->first();
                    }

                    if ($student) {
                        $student->update([
                            'external_id' => $extId,
                            'grade'       => $grade,
                            'firstname'   => $firstname,
                            'lastname'    => $lastname,
                        ]);
                        $this->importStats['students_updated']++;
                    } else {
                        $student = Student::create([
                            'exam_period_id' => $examPeriod->id,
                            'grade'          => $grade,
                            'external_id'    => $extId,
                            'firstname'      => $firstname,
                            'lastname'       => $lastname,
                        ]);
                        $this->importStats['students_created']++;
                    }

                    // S1-S3 schriftlich, K1-K2 muendlich
                    foreach ([[2, 3, 'written'], [4, 5, 'written'], [6, 7, 'written'], [8, 9, 'oral'], [10, 11, 'oral']] as [$ci, $ti, $type]) {
                        $this->processExamColumn($student, $data, $ci, $ti, $type, $examPeriod, $corrections, $schoolId);
                    }

                    $studentData = null;
                }
            }

            fclose($handle);
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }
    }

    /**
     * Liefert die Import-Statistiken.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->importStats;
    }

    /**
     * Baut die Erfolgsmeldung aus den Statistiken zusammen.
     */
    public function buildSuccessMessage(): string
    {
        $s   = $this->importStats;
        $msg = "Import erfolgreich abgeschlossen!\n\n";
        $msg .= "Schueler: {$s['students_created']} neu erstellt";
        if ($s['students_updated'] > 0) {
            $msg .= ", {$s['students_updated']} aktualisiert";
        }
        $msg .= "\nPruefungen: {$s['exams_created']} erstellt (S1, S2, S3, K1, K2)\n";

        if ($s['courses_created'] > 0) {
            $msg .= "Kurse: {$s['courses_created']} neu angelegt";
            if ($s['courses_found'] > 0) {
                $msg .= ", {$s['courses_found']} gefunden";
            }
            $msg .= "\n";
        }

        if (!empty($s['errors'])) {
            $msg .= "\nWarnungen:\n";
            foreach ($s['errors'] as $error) {
                $msg .= "- {$error}\n";
            }
        }

        return $msg;
    }

    // =========================================================================
    // Private Hilfsmethoden
    // =========================================================================

    /**
     * Verarbeitet eine Pruefungs-Spalte (S1/S2/S3/K1/K2) beim echten Import.
     */
    private function processExamColumn(
        Student    $student,
        array      $data,
        int        $courseIdx,
        int        $teacherIdx,
        string     $examType,
        ExamPeriod $examPeriod,
        array      $corrections,
        string     $schoolId
    ): void {
        if (empty(isset($data[$courseIdx]) ? trim($data[$courseIdx]) : '')) {
            return;
        }

        $teacherCode = (isset($data[$teacherIdx]) && !empty(trim($data[$teacherIdx])))
            ? trim($data[$teacherIdx])
            : 'EXTERN';

        if (isset($corrections[$teacherCode])) {
            $teacherCode = $corrections[$teacherCode]['to'];
        }

        $this->createExamWithDependencies(
            $student,
            trim($data[$courseIdx]),
            $teacherCode,
            $examPeriod,
            null,
            $examType,
            $schoolId
        );
    }

    /**
     * Erstellt Pruefung und alle Abhaengigkeiten (Fach, Lehrer, Kurs, StudentExam).
     */
    private function createExamWithDependencies(
        Student    $student,
        string     $courseCode,
        string     $teacherCode,
        ExamPeriod $examPeriod,
        ?int       $examWeek,
        string     $examType,
        string     $schoolId = ''
    ): void {
        try {
            $subjectCode = $this->extractSubjectCode($courseCode);

            $subject = Subject::whereRaw('UPPER(code) = ?', [strtoupper($subjectCode)])->first();
            if (!$subject) {
                $error = "Fach '{$subjectCode}' fuer Kurs '{$courseCode}' nicht gefunden - Schueler: {$student->full_name}";
                Log::warning($error);
                $this->importStats['errors'][] = $error;
                return;
            }

            $teacher = $this->resolveTeacher($teacherCode);
            $course  = $this->createOrFindCourse($courseCode, $subject->id, $teacher->id, $examPeriod);

            $writtenExamDateId = null;
            if ($examType === 'written') {
                $writtenExamDateId = $this->findWrittenExamDateId($subject->id, $course->level, (int) $examPeriod->year);

                if (!$writtenExamDateId) {
                    $levelDisplay = $course->level === 'erhoeht' ? 'eA' : 'gA';
                    $this->importStats['errors'][] = "WARNUNG: Kein Pruefungstermin fuer {$subject->name} ({$levelDisplay}) - Schueler {$student->full_name}";
                }
            }

            $exam = StudentExam::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->first();

            $examData = [
                'examiner_id'          => $teacher->id,
                'exam_type'            => $examType,
                'written_exam_year_date_id' => $writtenExamDateId,
            ];

            if ($exam) {
                $exam->update($examData);
                $this->importStats['exams_updated']++;
            } else {
                StudentExam::create(array_merge(['student_id' => $student->id, 'course_id' => $course->id], $examData));
                $this->importStats['exams_created']++;
            }

        } catch (\Exception $e) {
            $this->importStats['errors'][] = "Fehler bei Schueler {$student->full_name}, Kurs {$courseCode}: {$e->getMessage()}";
        }
    }

    /**
     * Loest ein Lehrer-Kuerzel auf – unbekannte Kuerzel erhalten den EXTERN-Platzhalter.
     */
    private function resolveTeacher(string $teacherCode): Teacher
    {
        if ($teacherCode === 'EXTERN') {
            return Teacher::firstOrCreate(
                ['shortcode' => 'EXTERN'],
                ['firstname' => 'Extern', 'lastname' => 'Extern', 'title' => '', 'employment_type' => '', 'is_active' => true]
            );
        }

        $teacher = Teacher::where('shortcode', $teacherCode)->first();

        if (!$teacher) {
            Log::warning("Lehrer '{$teacherCode}' nicht gefunden - verwende EXTERN-Platzhalter");
            return $this->resolveTeacher('EXTERN');
        }

        return $teacher;
    }

    /**
     * Validiert eine Pruefungsspalte fuer die Vorschau (kein DB-Zugriff ausser vorgeladenen Arrays).
     *
     * @return array<string, mixed>
     */
    private function validateExam(
        array $data,
        int   $courseIndex,
        int   $teacherIndex,
        array $existingSubjects,
        array $existingTeachers
    ): array {
        $courseCode  = isset($data[$courseIndex])  && !empty(trim($data[$courseIndex]))  ? trim($data[$courseIndex])  : null;
        $teacherCode = isset($data[$teacherIndex]) && !empty(trim($data[$teacherIndex])) ? trim($data[$teacherIndex]) : null;

        $result = [
            'course'        => $courseCode,
            'teacher'       => $teacherCode,
            'subject_code'  => null,
            'subject_name'  => null,
            'subject_valid' => false,
            'teacher_valid' => false,
            'has_error'     => false,
            'errors'        => [],
        ];

        if (!$courseCode && !$teacherCode) return $result;

        if ($courseCode && !$teacherCode) {
            $result['teacher']       = 'EXTERN';
            $result['teacher_valid'] = true;
        }

        if (!$courseCode && $teacherCode) {
            $result['has_error'] = true;
            $result['errors'][]  = 'Kurs fehlt';
        }

        if ($courseCode) {
            try {
                $subjectCode = $this->extractSubjectCode($courseCode);
                $result['subject_code'] = $subjectCode;

                foreach ($existingSubjects as $code => $name) {
                    if (strcasecmp($code, $subjectCode) === 0) {
                        $result['subject_name']  = $name;
                        $result['subject_valid'] = true;
                        $result['subject_code']  = $code;
                        break;
                    }
                }

                if (!$result['subject_valid']) {
                    $result['has_error'] = true;
                    $result['errors'][]  = "Fach '{$subjectCode}' nicht gefunden";
                }
            } catch (\Exception) {
                $result['has_error'] = true;
                $result['errors'][]  = 'Fach konnte nicht extrahiert werden';
            }
        }

        if ($teacherCode && $result['teacher'] !== 'EXTERN') {
            if (isset($existingTeachers[$teacherCode])) {
                $result['teacher_valid'] = true;
            } else {
                $result['has_error'] = true;
                $result['errors'][]  = "Lehrer '{$teacherCode}' nicht gefunden";
            }
        }

        return $result;
    }

    /**
     * Findet den passenden written_exam_year_date für Fach + Level + Jahr.
     * Sucht über WrittenExamSlot → WrittenExamYearDate.
     */
    private function findWrittenExamDateId(int $subjectId, string $level, int $examYear): ?int
    {
        $slot = WrittenExamSlot::whereHas('subjects', fn ($q) => $q->where('subject_id', $subjectId))
            ->where('requirement_level', $level)
            ->first();

        if (!$slot) {
            // Fallback: Sammeltag für dieses Fach + Level suchen
            $slot = WrittenExamSlot::whereHas('subjects', fn ($q) => $q->where('subject_id', $subjectId))
                ->where('requirement_level', $level)
                ->where('name', 'like', 'Sammeltag%')
                ->first();
        }

        if (!$slot) {
            return null;
        }

        // Der Termin im Jahr des Prüfungszeitraums. Bis 23.11.2026 war es der
        // neueste Termin überhaupt — das ging nur gut, solange die Termine des
        // Folgejahres fehlten (seit termine:einspielen stehen sie oft schon da).
        $yearDate = WrittenExamYearDate::where('written_exam_slot_id', $slot->id)
            ->where('exam_year', $examYear)
            ->first();

        return $yearDate?->id;
    }

    /**
     * Extrahiert das Fachkuerzel aus einem Kurscode.
     *
     * Beispiele:
     *   "2eth1"  => "eth"
     *   "2E-L2"  => "e"
     *   "2PuG-L" => "pug"
     *   "2Smw-"  => "smw"
     */
    private function extractSubjectCode(string $courseCode): string
    {
        $code = preg_replace('/^[0-9]+/', '', $courseCode);
        $code = str_replace('-L', '', $code);
        $code = rtrim($code, '-');
        $code = preg_replace('/[0-9]+$/', '', $code);
        $code = strtolower(trim($code));

        if (empty($code)) {
            throw new \Exception("Konnte Fachkuerzel nicht aus Kurscode '{$courseCode}' extrahieren");
        }

        return $code;
    }

    /**
     * Findet oder erstellt einen Kurs.
     */
    private function createOrFindCourse(
        string     $courseCode,
        int        $subjectId,
        int        $teacherId,
        ExamPeriod $examPeriod
    ): Course {
        $course = Course::where('exam_period_id', $examPeriod->id)
            ->where('course_code', $courseCode)
            ->first();

        if ($course) {
            $this->importStats['courses_found']++;
            return $course;
        }

        $subject = Subject::find($subjectId);
        $level   = $this->extractLevelFromCourseCode($courseCode, $subject?->code);

        $course = Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => $subjectId,
            'course_code'    => $courseCode,
            'teacher_id'     => $teacherId,
            'level'          => $level,
        ]);

        $this->importStats['courses_created']++;

        return $course;
    }

    /**
     * Bestimmt das Leistungsniveau aus dem Kurscode.
     *
     * - Deutsch und Mathematik: immer 'erhoeht'
     * - "-L" im Code: 'erhoeht'
     * - Grossbuchstabe direkt nach fuehrender Ziffer: 'erhoeht'
     *
     * @return string 'erhoeht' | 'grundlegend'
     */
    private function extractLevelFromCourseCode(string $courseCode, ?string $subjectCode = null): string
    {
        if ($subjectCode && in_array(strtolower($subjectCode), ['d', 'de', 'deutsch', 'm', 'ma', 'mathe', 'mathematik'], true)) {
            return 'erhoeht';
        }

        if (stripos($courseCode, '-L') !== false) {
            return 'erhoeht';
        }

        if (preg_match('/^[0-9]+([A-Z])/', $courseCode)) {
            return 'erhoeht';
        }

        return 'grundlegend';
    }
}
