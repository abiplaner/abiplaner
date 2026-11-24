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

class CoursesColloquiumSheet extends DefaultValueBinder implements WithCustomValueBinder, FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
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
        $courses = Course::where('exam_period_id', $this->examPeriod->id)->derStufe('13')
            ->with(['studentExams' => fn ($q) => $q->derStufe('13'), 'subject', 'teacher', 'studentExams.assessor'])
            ->orderBy('course_code')
            ->get();

        $rows = collect();

        foreach ($courses as $course) {
            $oralExams = $course->studentExams->filter(fn($e) => $e->isOralExam());
            if ($oralExams->isEmpty()) {
                continue;
            }

            // Beisitzer mit Anzahl ermitteln (können pro Schüler unterschiedlich sein)
            $assessors = $oralExams
                ->filter(fn($e) => $e->assessor !== null)
                ->groupBy('assessor_id')
                ->map(function ($group) {
                    $count = $group->count();
                    $shortcode = $group->first()->assessor->shortcode;
                    // Nur Anzahl angeben wenn mehrere unterschiedliche Beisitzer
                    return ['shortcode' => $shortcode, 'count' => $count];
                })
                ->values();

            // Wenn alle Beisitzer gleich: nur Name, sonst Name (n)
            $totalAssessors = $assessors->count();
            if ($totalAssessors === 1) {
                $assessorStr = $assessors->first()['shortcode'];
            } elseif ($totalAssessors > 1) {
                $assessorStr = $assessors->map(fn($a) => $a['shortcode'] . ' (' . $a['count'] . ')')->implode(', ');
            } else {
                $assessorStr = '---';
            }

            $rows->push([
                $course->course_code,
                $oralExams->count(),
                $course->teacher ? $course->teacher->shortcode : '---',
                $assessorStr,
            ]);
        }

        $this->dataRowCount = $rows->count();
        return $rows;
    }

    public function headings(): array
    {
        return ['Kurs', 'Anzahl', 'Erstprüfer', 'Beisitzer'];
    }

    public function title(): string
    {
        return 'Fachausschüsse Kolloquien';
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
                    'Fachausschüsse – Kolloquien ' . $this->examPeriod->name
                );
            },
        ];
    }
}
