<?php

namespace App\Exports;

use App\Models\ExamPeriod;
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

class SubjectStatsExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $examPeriodId;
    protected int $dataRowCount = 0;

    public function __construct($examPeriodId)
    {
        $this->examPeriodId = $examPeriodId;
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $examPeriod = ExamPeriod::findOrFail($this->examPeriodId);

        $subjectStats = app(\App\Services\SubjectStatsService::class)->calculate($examPeriod);

        $this->dataRowCount = $subjectStats->count();

        // Spalten D und G (Gesamt-Spalten) werden via AfterSheet als Formeln gesetzt (null-Platzhalter)
        return $subjectStats->map(function($stat) {
            return [
                $stat['subject_name'] . ' (' . $stat['total'] . ')',
                $stat['is_deutsch_or_math'] ? null : ($stat['grundlegend_written'] ?: null),
                $stat['erhoeht_written'] ?: null,
                null, // D: =Bx+Cx  (Formel via AfterSheet)
                $stat['is_deutsch_or_math'] ? null : ($stat['grundlegend_oral'] ?: null),
                $stat['erhoeht_oral'] ?: null,
                null, // G: =Ex+Fx  (Formel via AfterSheet)
            ];
        });
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet        = $event->sheet->getDelegate();
                $firstDataRow = 2;                       // Zeile 1 = Kopfzeile
                $lastDataRow  = 1 + $this->dataRowCount; // letzte Datenzeile
                $totalRow     = $lastDataRow + 1;         // Gesamtzeile

                // Gesamt-Formeln pro Datenzeile (Spalten D und G)
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $sheet->setCellValue("D{$r}", "=IFERROR(B{$r}+C{$r},0)");
                    $sheet->setCellValue("G{$r}", "=IFERROR(E{$r}+F{$r},0)");
                }

                // Gesamtzeile mit SUM-Formeln
                $sheet->setCellValue("A{$totalRow}", 'Gesamt');
                $sheet->setCellValue("B{$totalRow}", "=SUM(B{$firstDataRow}:B{$lastDataRow})");
                $sheet->setCellValue("C{$totalRow}", "=SUM(C{$firstDataRow}:C{$lastDataRow})");
                $sheet->setCellValue("D{$totalRow}", "=B{$totalRow}+C{$totalRow}");
                $sheet->setCellValue("E{$totalRow}", "=SUM(E{$firstDataRow}:E{$lastDataRow})");
                $sheet->setCellValue("F{$totalRow}", "=SUM(F{$firstDataRow}:F{$lastDataRow})");
                $sheet->setCellValue("G{$totalRow}", "=E{$totalRow}+F{$totalRow}");

                // Styling der Gesamtzeile
                $sheet->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE9E9E9']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
            },
        ];
    }

    public function headings(): array
    {
        return [
            'Fach',
            'Schriftlich gA',
            'Schriftlich eA',
            'Schriftlich Gesamt',
            'Mündlich gA',
            'Mündlich eA',
            'Mündlich Gesamt',
        ];
    }

    public function title(): string
    {
        return 'Fächerübersicht';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 14,
            'C' => 14,
            'D' => 16,
            'E' => 14,
            'F' => 14,
            'G' => 16,
        ];
    }
}
