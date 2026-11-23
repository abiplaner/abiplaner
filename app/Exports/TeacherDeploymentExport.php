<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeacherDeploymentExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @var \Illuminate\Support\Collection<int, array{teacher: \App\Models\Teacher, written_courses: int, second_corrections: int, oral_examiner: int, oral_assessor: int, supervisions: int, total: int}> */
    protected Collection $stats;

    /**
     * @param \Illuminate\Support\Collection<int, array{teacher: \App\Models\Teacher, written_courses: int, second_corrections: int, oral_examiner: int, oral_assessor: int, supervisions: int, total: int}> $stats
     */
    public function __construct(Collection $stats)
    {
        $this->stats = $stats;
    }

    public function title(): string
    {
        return 'Lehrereinsatz';
    }

    /** @return array<int, array<int, string|int>> */
    public function array(): array
    {
        $rows = [];

        // Header
        $rows[] = [
            'Kürzel',
            'Nachname',
            'Vorname',
            'Schriftl. (Erstkorr.)',
            'Schriftl. (Zweitkorr.)',
            'Kolloquium Prüfer',
            'Kolloquium Beisitzer',
            'Aufsichten',
            'Gesamt',
        ];

        foreach ($this->stats as $row) {
            /** @var \App\Models\Teacher $teacher */
            $teacher = $row['teacher'];
            $rows[] = [
                $teacher->shortcode,
                $teacher->lastname,
                $teacher->firstname,
                (int) $row['written_courses'],
                (int) $row['second_corrections'],
                (int) $row['oral_examiner'],
                (int) $row['oral_assessor'],
                (int) $row['supervisions'],
                (int) $row['total'],
            ];
        }

        // Summenzeile
        $rows[] = [
            '', '', 'Gesamt',
            (int) $this->stats->sum('written_courses'),
            (int) $this->stats->sum('second_corrections'),
            (int) $this->stats->sum('oral_examiner'),
            (int) $this->stats->sum('oral_assessor'),
            (int) $this->stats->sum('supervisions'),
            (int) $this->stats->sum('total'),
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->stats->count() + 2; // +1 Header, +1 Summenzeile

        // Header-Zeile
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        ]);

        // Summenzeile
        $sheet->getStyle("A{$lastRow}:I{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ]);

        // Zahlen-Spalten zentrieren (D–I)
        $sheet->getStyle("D2:I{$lastRow}")->getAlignment()->setHorizontal('center');

        return [];
    }
}
