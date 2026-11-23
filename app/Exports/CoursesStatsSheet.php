<?php

namespace App\Exports;

use App\Models\ExamPeriod;
use App\Models\StudentExam;
use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CoursesStatsSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected ExamPeriod $examPeriod;
    protected int $dataRowCount = 0;

    public function __construct(ExamPeriod $examPeriod)
    {
        $this->examPeriod = $examPeriod;
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $allExams = StudentExam::forAbiturStudentsOf($this->examPeriod)
            ->with(['course', 'examiner', 'secondCorrector', 'assessor'])
            ->get();

        // Alle Lehrer der Schule laden
        $teachers = Teacher::orderBy('lastname')->get()->keyBy('id');

        // Statistik aufbauen: pro Lehrer-ID
        $stats = [];

        foreach ($allExams as $exam) {
            // Erstprüfer (examiner_id) – für alle Prüfungstypen
            if ($exam->examiner_id) {
                $stats[$exam->examiner_id] = $stats[$exam->examiner_id] ?? $this->emptyRow();
                if ($exam->isWrittenExam()) {
                    $stats[$exam->examiner_id]['written_first']++;
                } else {
                    $stats[$exam->examiner_id]['oral_first']++;
                }
            }

            // Zweitkorrektor (nur schriftlich)
            if ($exam->isWrittenExam() && $exam->second_corrector_id) {
                $stats[$exam->second_corrector_id] = $stats[$exam->second_corrector_id] ?? $this->emptyRow();
                $stats[$exam->second_corrector_id]['written_second']++;
            }

            // Beisitzer (nur mündlich)
            if ($exam->isOralExam() && $exam->assessor_id) {
                $stats[$exam->assessor_id] = $stats[$exam->assessor_id] ?? $this->emptyRow();
                $stats[$exam->assessor_id]['oral_assessor']++;
            }
        }

        // Sortieren nach Nachname
        $rows = collect($stats)
            ->map(function ($data, $teacherId) use ($teachers) {
                $teacher = $teachers->get($teacherId);
                $name = $teacher ? $teacher->lastname : "Lehrer-ID {$teacherId}";
                $data['name'] = $name;
                $data['total'] = $data['written_first'] + $data['written_second']
                    + $data['oral_first'] + $data['oral_assessor'];
                return $data;
            })
            ->sortBy('name')
            ->values()
            ->map(fn($d) => [
                $d['name'],
                $d['written_first']  ?: null,
                $d['written_second'] ?: null,
                $d['oral_first']     ?: null,
                $d['oral_assessor']  ?: null,
                $d['total'],
            ]);

        $this->dataRowCount = $rows->count();
        return $rows;
    }

    private function emptyRow(): array
    {
        return ['written_first' => 0, 'written_second' => 0, 'oral_first' => 0, 'oral_assessor' => 0];
    }

    public function headings(): array
    {
        return ['Name', 'Schriftl. Erstprüfer', 'Zweitkorrektor', 'Koll. Erstprüfer', 'Beisitzer', 'Summe'];
    }

    public function title(): string
    {
        return 'Prüfungsstatistik';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F3864']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 20, 'C' => 18, 'D' => 18, 'E' => 12, 'F' => 10];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = 1 + $this->dataRowCount;
                $totalRow = $lastRow + 1;

                // Zebra-Striping
                for ($r = 2; $r <= $lastRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:F{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']],
                        ]);
                    }
                }

                // Rahmen
                $sheet->getStyle("A1:F{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
                ]);

                // Zahlen zentrieren
                $sheet->getStyle("B2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Gesamtzeile
                $sheet->setCellValue("A{$totalRow}", 'Gesamt');
                for ($col = 'B'; $col <= 'F'; $col = str_increment($col)) {
                    $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}2:{$col}{$lastRow})");
                }
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE9E9E9']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                $sheet->getStyle("B{$totalRow}:F{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Titelzeile
                $sheet->insertNewRowBefore(1, 2);
                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A1', 'Prüfungsstatistik ' . $this->examPeriod->name);
                $sheet->setCellValue('A2', 'Stand: ' . now()->format('d.m.Y'));
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 13]]);
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['argb' => 'FF666666'], 'size' => 9],
                ]);
            },
        ];
    }
}
