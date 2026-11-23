<?php

namespace App\Exports;

use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamAssignment;
use App\Models\ExamPeriod;
use App\Models\StudentExam;
use App\Http\Controllers\ExportController;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class WrittenExamDatesOverviewExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    protected int $examYear;
    protected \Illuminate\Support\Collection $rows;

    public function __construct()
    {
        $activePeriod    = app(\App\Services\CurrentExamPeriodService::class)->current();
        $this->examYear  = $activePeriod !== null ? $activePeriod->year : (int) date('Y');

        // Gemeinsame Logik über Controller-Methode nutzen
        $controller      = app(ExportController::class);
        [$this->rows]    = $controller->loadOverviewData($this->examYear);
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Datum',
            'Wochentag',
            'Uhrzeit',
            'Prüfung',
            'Niveau',
            'Dauer (Min.)',
            'SuS gA',
            'SuS eA',
            'SuS gesamt',
            'Kurse',
            'Räume',
        ];
    }

    public function map($row): array
    {
        $row = (array) $row;

        $levelLabel = $row['level'] === 'grundlegend' ? 'gA' : 'eA';

        // Prüfungs-Name: bei Sammeltagen die Fächer auflisten
        if ($row['is_sammeltag']) {
            $displayName = 'Sammeltag ' . $levelLabel . ': ' . $row['subjects']->join(', ');
        } else {
            $displayName = $row['display_name'];
        }

        // Kurse: "2G-L (ZIM), 2PuG-L (CZO)"
        $courses = $row['assignments']->sortBy('course.course_code')
            ->map(function ($a) {
                $code = $a->course->course_code ?? '?';
                $tc   = $a->course?->teacher?->shortcode;
                return $tc ? "{$code} ({$tc})" : $code;
            })->implode(', ');

        // Räume: eindeutige Raumnummern
        $rooms = $row['assignments']->filter(fn($a) => $a->room)
            ->groupBy('room_id')
            ->map(fn($g) => $g->first()->room->room_number)
            ->implode(', ');

        return [
            Carbon::parse($row['exam_date'])->format('d.m.Y'),
            Carbon::parse($row['exam_date'])->locale('de')->isoFormat('dddd'),
            Carbon::parse($row['start_time'])->format('H:i'),
            $displayName,
            $levelLabel,
            $row['duration'] ?? '–',
            $row['cntG'] ?: '',
            $row['cntE'] ?: '',
            ($row['cntG'] + $row['cntE']) ?: '',
            $courses ?: '–',
            $rooms   ?: '–',
        ];
    }

    public function title(): string
    {
        return 'Prüfungstermine ' . $this->examYear;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '374151'],
                ],
            ],
        ];
    }
}
