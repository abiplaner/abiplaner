<?php

namespace App\Exports;

use App\Http\Controllers\OralExamFormController;
use App\Models\ExamPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OralFachausschuesseExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    private ExamPeriod $examPeriod;

    public function __construct(ExamPeriod $examPeriod)
    {
        $this->examPeriod = $examPeriod;
    }

    public function array(): array
    {
        // Re-use logic from Controller
        $controller = new OralExamFormController();
        $rows = $controller->getFachausschuesseRows($this->examPeriod);

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                $row['kurs'],
                $row['anzahl'],
                $row['erst'],
                $row['zweit'],
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            ['Fachausschüsse Kolloquium ' . $this->examPeriod->year],
            ['Stand: ' . now()->format('d.m.Y')],
            [],
            ['Kurs', 'Anzahl', 'Erstprüfer', 'Zweitprüfer']
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'size' => 10]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
