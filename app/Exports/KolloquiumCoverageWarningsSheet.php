<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Erstes Sheet im Kolloquium-Aufsichtsexport: Warnhinweise aus der
 * Aufsichten-Kontrolle (Modul 4.4) – fehlende und überflüssige Aufsichten.
 */
class KolloquiumCoverageWarningsSheet implements FromArray, ShouldAutoSize, WithTitle, WithStyles
{
    /** @param array<int, string> $warnings */
    public function __construct(protected array $warnings)
    {
    }

    public function array(): array
    {
        if (empty($this->warnings)) {
            return [['Aufsichten-Kontrolle'], ['Alle benötigten Aufsichten sind besetzt, keine überflüssigen Einträge.']];
        }

        $rows = [['Aufsichten-Kontrolle – ' . count($this->warnings) . ' Punkt(e) zu prüfen']];
        foreach ($this->warnings as $w) {
            $rows[] = [$w];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Kontrolle';
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        return [];
    }
}
