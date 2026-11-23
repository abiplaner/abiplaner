<?php

namespace App\Exports;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\StudentExam;
use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CoursesOverviewExport implements Export, WithMultipleSheets
{
    protected ExamPeriod $examPeriod;

    public function __construct(ExamPeriod $examPeriod)
    {
        $this->examPeriod = $examPeriod;
    }

    public function sheets(): array
    {
        return [
            new CoursesWrittenSheet($this->examPeriod),
            new CoursesColloquiumSheet($this->examPeriod),
            new CoursesStatsSheet($this->examPeriod),
        ];
    }
}
