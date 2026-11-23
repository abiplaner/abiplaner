<?php

namespace App\Exports;

use App\Exports\Concerns\StylesCourseListSheet;
use App\Models\Course;
use App\Models\ExamPeriod;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Export: Fachausschüsse für die schriftlichen Abiturprüfungen.
 * Format Lehrernennung: Nachname + erster Buchstabe Vorname (z. B. "Kreil W.")
 */
class FachausschuesseExport extends DefaultValueBinder implements
    WithCustomValueBinder, FromCollection, WithHeadings,
    WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    use StylesCourseListSheet;

    protected ExamPeriod $examPeriod;
    protected int $dataRowCount = 0;

    public function __construct(ExamPeriod $examPeriod)
    {
        $this->examPeriod = $examPeriod;
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $courses = Course::where('exam_period_id', $this->examPeriod->id)
            ->with(['subject', 'teacher', 'secondCorrector', 'studentExams'])
            ->orderBy('course_code')
            ->get();

        $rows = collect();

        foreach ($courses as $course) {
            $writtenExams = $course->studentExams->filter(fn($e) => $e->isWrittenExam());
            if ($writtenExams->isEmpty()) {
                continue;
            }

            $rows->push([
                $course->course_code,
                $writtenExams->count(),
                $this->teacherLabel($course->teacher),
                $this->teacherLabel($course->secondCorrector),
            ]);
        }

        $this->dataRowCount = $rows->count();
        return $rows;
    }

    public function headings(): array
    {
        return ['Kurs', 'Anzahl', 'Erstprüfer', 'Zweitprüfer'];
    }

    public function title(): string
    {
        return 'Fachausschüsse';
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 10, 'C' => 22, 'D' => 22];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->applyCourseListLayout(
                    $event->sheet->getDelegate(),
                    $this->dataRowCount,
                    'Fachausschüsse – Schriftliche Abiturprüfungen ' . $this->examPeriod->year
                );
            },
        ];
    }

    /**
     * Gibt "Nachname V." zurück, z. B. "Kreil W."
     * Gibt '—' zurück, wenn kein Lehrer gesetzt.
     *
     * @param \App\Models\Teacher|null $teacher
     */
    private function teacherLabel(?object $teacher): string
    {
        if ($teacher === null) {
            return '—';
        }
        $initial = $teacher->firstname !== '' ? mb_substr($teacher->firstname, 0, 1) . '.' : '';
        return trim($teacher->lastname . ($initial !== '' ? ' ' . $initial : ''));
    }
}
