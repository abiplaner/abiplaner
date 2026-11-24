<?php

namespace App\Services;

use App\Models\ColloquiumTopic;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import des Kolloquiums-Themenkatalogs (Roadmap 4.7).
 *
 * Erwartetes Layout (Vorlage "Kolloquium-Themen-Q12.xlsx"):
 *   Zeile 1: Titel (wird ignoriert)
 *   Zeile 2: gemergte Halbjahres-Header (E2:G2 = 12/1, H2:J2 = 12/2)
 *   Zeile 3: Spaltenköpfe
 *   ab Zeile 4: Daten
 *
 *   A = Nr. | B = Kurs | C = Lehrkraft | D = Fach
 *   E,F,G = Thema 1-3 für 12/1
 *   H,I,J = Thema 1-3 für 12/2
 *
 * Zugeordnet wird über courses.course_code innerhalb der aktuellen
 * ExamPeriod. Achtung: Die Kurskürzel tragen einen Jahrgangs-Präfix
 * ("1b1" vs. "2b1"), eine Vorlage des falschen Jahrgangs matcht daher
 * nicht. Deshalb gibt es parsePreview(): unzuordenbare Zeilen werden
 * sichtbar gemacht, statt still verschluckt zu werden.
 */
class ColloquiumTopicImportService
{
    private const FIRST_DATA_ROW = 4;

    /** Spalten je Halbjahr: semester => [slot => Spaltenindex (1-basiert)] */
    private const TOPIC_COLUMNS = [
        '12/1' => [1 => 5, 2 => 6, 3 => 7],
        '12/2' => [1 => 8, 2 => 9, 3 => 10],
    ];

    /**
     * Liest die Vorlage in Rohzeilen ein.
     *
     * @return list<array{row: int, course_code: string, teacher: string, subject: string, topics: array<string, array<int, string>>}>
     */
    public function readRows(string $filePath): array
    {
        $sheet = IOFactory::load($filePath)->getActiveSheet();
        $rows = [];

        foreach ($sheet->getRowIterator(self::FIRST_DATA_ROW) as $row) {
            $rowIndex = $row->getRowIndex();

            $courseCode = $this->cell($sheet, 2, $rowIndex);

            // Leerzeile bzw. Ende der Liste: ohne Kurskürzel ist die Zeile wertlos.
            if ($courseCode === '') {
                continue;
            }

            $topics = [];
            foreach (self::TOPIC_COLUMNS as $semester => $columns) {
                foreach ($columns as $slot => $column) {
                    $title = $this->cell($sheet, $column, $rowIndex);

                    if ($title !== '') {
                        $topics[$semester][$slot] = $title;
                    }
                }
            }

            $rows[] = [
                'row'         => $rowIndex,
                'course_code' => $courseCode,
                'teacher'     => $this->cell($sheet, 3, $rowIndex),
                'subject'     => $this->cell($sheet, 4, $rowIndex),
                'topics'      => $topics,
            ];
        }

        return $rows;
    }

    /**
     * Vorschau: ordnet jede Zeile einem Kurs der ExamPeriod zu und macht
     * sichtbar, was nicht zugeordnet werden konnte (z. B. Vorlage eines
     * anderen Jahrgangs).
     *
     * @return array{rows: list<array<string, mixed>>, row_count: int, matched_count: int, unmatched_count: int, topic_count: int}
     */
    public function parsePreview(string $filePath, int $examPeriodId): array
    {
        $rows = $this->readRows($filePath);
        $courses = $this->coursesByCode($examPeriodId);

        $preview = [];
        $matched = 0;
        $topicCount = 0;

        foreach ($rows as $row) {
            $course = $courses[$this->normalizeCode($row['course_code'])] ?? null;

            $rowTopics = 0;
            foreach ($row['topics'] as $slots) {
                $rowTopics += count($slots);
            }

            if ($course !== null) {
                $matched++;
                $topicCount += $rowTopics;
            }

            $preview[] = $row + [
                'course_id'      => $course?->id,
                'status'         => $course !== null ? 'matched' : 'unmatched',
                'topic_count'    => $rowTopics,
                'existing_count' => $course !== null
                    ? ColloquiumTopic::where('course_id', $course->id)->count()
                    : 0,
            ];
        }

        return [
            'rows'            => $preview,
            'row_count'       => count($preview),
            'matched_count'   => $matched,
            'unmatched_count' => count($preview) - $matched,
            'topic_count'     => $topicCount,
        ];
    }

    /**
     * Übernimmt den Katalog.
     *
     * Idempotent: Die Themen eines übernommenen Kurses werden vollständig
     * durch die Vorlage ersetzt, damit ein erneuter Import keine Dubletten
     * erzeugt.
     *
     * Wichtig: Kurszeilen OHNE Themen werden übersprungen und nicht geleert.
     * Die Vorlage läuft in der Praxis nach und nach ein (Stand 07/2026: 23 von
     * 60 Kursen befüllt) — ein Re-Import einer noch unvollständigen Datei darf
     * bereits erfasste Themen anderer Kurse nicht vernichten.
     *
     * @param list<string>|null $onlyCourseCodes Beschränkt die Übernahme auf
     *        diese Kurskürzel (aus der Vorschau abgewählte Zeilen bleiben außen
     *        vor). null = alle zugeordneten Zeilen mit Themen.
     * @return array{imported_count: int, skipped_empty_count: int, unmatched_count: int, topic_count: int, unmatched_codes: list<string>}
     */
    public function import(string $filePath, int $examPeriodId, ?array $onlyCourseCodes = null): array
    {
        $preview = $this->parsePreview($filePath, $examPeriodId);

        $selected = $onlyCourseCodes === null
            ? null
            : array_flip(array_map(fn (string $code): string => $this->normalizeCode($code), $onlyCourseCodes));

        $unmatchedCodes = [];
        $topicCount = 0;
        $importedCount = 0;
        $skippedEmpty = 0;

        DB::transaction(function () use ($preview, $selected, &$unmatchedCodes, &$topicCount, &$importedCount, &$skippedEmpty): void {
            foreach ($preview['rows'] as $row) {
                if ($row['status'] !== 'matched') {
                    $unmatchedCodes[] = $row['course_code'];
                    continue;
                }

                // Leere Kurszeile: nicht anfassen, sonst löscht eine noch nicht
                // vollständig ausgefüllte Vorlage bestehende Themen.
                if ($row['topic_count'] === 0) {
                    $skippedEmpty++;
                    continue;
                }

                if ($selected !== null && !isset($selected[$this->normalizeCode($row['course_code'])])) {
                    continue;
                }

                ColloquiumTopic::where('course_id', $row['course_id'])->delete();

                foreach ($row['topics'] as $semester => $slots) {
                    foreach ($slots as $slot => $title) {
                        ColloquiumTopic::create([
                            'course_id' => $row['course_id'],
                            'semester'  => $semester,
                            'slot'      => $slot,
                            'title'     => $title,
                        ]);
                        $topicCount++;
                    }
                }

                $importedCount++;
            }
        });

        return [
            'imported_count'      => $importedCount,
            'skipped_empty_count' => $skippedEmpty,
            'unmatched_count'     => $preview['unmatched_count'],
            'topic_count'         => $topicCount,
            'unmatched_codes'     => $unmatchedCodes,
        ];
    }

    /**
     * Kurse der ExamPeriod, indiziert über das normalisierte Kurskürzel.
     *
     * @return array<string, Course>
     */
    private function coursesByCode(int $examPeriodId): array
    {
        $courses = Course::where('exam_period_id', $examPeriodId)->derStufe('13')->get();

        $indexed = [];
        foreach ($courses as $course) {
            $indexed[$this->normalizeCode($course->course_code)] = $course;
        }

        return $indexed;
    }

    /**
     * Kurskürzel vergleichbar machen: Groß-/Kleinschreibung und Leerzeichen
     * sind in der Vorlage uneinheitlich ("1B-L" vs. "1b-l").
     */
    private function normalizeCode(string $code): string
    {
        return mb_strtolower(str_replace(' ', '', trim($code)));
    }

    private function cell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $column, int $row): string
    {
        $value = $sheet->getCell([$column, $row])->getValue();

        return trim((string) $value);
    }
}
