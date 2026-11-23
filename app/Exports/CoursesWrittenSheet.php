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

class CoursesWrittenSheet extends DefaultValueBinder implements WithCustomValueBinder, FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
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
            ->with(['subject', 'teacher', 'studentExams.secondCorrector'])
            ->orderBy('course_code')
            ->get();

        $rows = collect();

        foreach ($courses as $course) {
            $writtenExams = $course->studentExams->filter(fn($e) => $e->isWrittenExam());
            if ($writtenExams->isEmpty()) {
                continue;
            }

            // Alle Zweitkorrektoren mit Anzahl ermitteln
            $secondCorrectors = $writtenExams
                ->filter(fn($e) => $e->secondCorrector !== null)
                ->groupBy('second_corrector_id')
                ->map(fn($group) => $group->first()->secondCorrector->shortcode . ' (' . $group->count() . ')')
                ->values()
                ->implode(', ');

            if (empty($secondCorrectors)) {
                $secondCorrectors = '---';
            }

            $rows->push([
                $course->course_code,
                $writtenExams->count(),
                $course->teacher ? $course->teacher->shortcode : '---',
                $secondCorrectors,
            ]);
        }

        $this->dataRowCount = $rows->count();
        return $rows;
    }

    public function headings(): array
    {
        return ['Kurs', 'Anzahl', 'Erstprüfer', 'Zweitkorrektor'];
    }

    public function title(): string
    {
        return 'Fachausschüsse Schriftlich';
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 10, 'C' => 20, 'D' => 40];
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
}
