<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gemeinsame Bausteine für die Fachausschuss-/Kursliste-Exporte: Kurscode-
 * Spalte vor Exponential-Interpretation schützen, Kopfzeile einfärben,
 * Zebra-Striping + Rahmen + zwei Titelzeilen ("Fachausschüsse – …" und
 * "Stand: …") vor die Datentabelle einfügen.
 *
 * Wird von CoursesColloquiumSheet, CoursesWrittenSheet und
 * FachausschuesseExport genutzt (phpcpd-Befund 22.09.2026, siehe
 * claude/code-duplizierung-22-09.md). Die drei Klassen unterscheiden sich in
 * Datenherkunft (mündliche/schriftliche Prüfungen), Spaltenbreiten und dem
 * genauen Titeltext - nur die hier gebündelte Layout-Logik ist identisch.
 *
 * Setzt voraus, dass die nutzende Klasse App\Models\ExamPeriod-unabhängig
 * bleibt: der Titeltext wird von außen übergeben, nicht hier zusammengebaut.
 */
trait StylesCourseListSheet
{
    /**
     * Verhindert, dass Kurscodes wie "2e1" als Exponential-Zahl (20)
     * interpretiert werden.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'A' && is_string($value) && $value !== '') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
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

    /**
     * Zebra-Striping + Rahmen auf die Datentabelle anwenden, Spalte B
     * zentrieren und zwei Titelzeilen (Überschrift + "Stand: …") davor
     * einfügen.
     */
    protected function applyCourseListLayout(Worksheet $sheet, int $dataRowCount, string $titleText): void
    {
        $lastRow = 1 + $dataRowCount;

        // Zebra-Striping
        for ($r = 2; $r <= $lastRow; $r++) {
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']],
                ]);
            }
        }

        $sheet->getStyle("A1:D{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']],
            ],
        ]);

        $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->insertNewRowBefore(1, 2);
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', $titleText);
        $sheet->setCellValue('A2', 'Stand: ' . now()->format('d.m.Y'));
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => 'FF666666'], 'size' => 9],
        ]);
    }
}
