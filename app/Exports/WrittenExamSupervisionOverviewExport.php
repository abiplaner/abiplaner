<?php

namespace App\Exports;

use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamCentralSupervisionType;
use App\Models\Supervision;
use App\Models\WrittenExamSupervisionTimeSlot;
use App\Models\WrittenExamSupervisionGrid;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel-Export aller Aufsichtspläne (Gesamtübersicht).
 * Erzeugt ein Sheet pro Prüfungstag.
 */
class WrittenExamSupervisionOverviewExport implements Export, WithMultipleSheets
{
    /** @var array<string, array<string, mixed>> */
    protected array $dayBlocks;

    /**
     * @param array<string, array<string, mixed>> $dayBlocks
     *   Keyed by 'Y-m-d', each value is the same structure as in the controller.
     */
    public function __construct(array $dayBlocks)
    {
        $this->dayBlocks = $dayBlocks;
    }

    /** @return \Maatwebsite\Excel\Concerns\WithTitle[] */
    public function sheets(): array
    {
        $sheets = [];
        $timeSlots    = WrittenExamSupervisionTimeSlot::with('grid')->orderBy('slot_number')->get();
        $grids        = WrittenExamSupervisionGrid::orderByDesc('is_default')->orderBy('name')->get();
        $defaultGridId = ($grids->firstWhere('is_default', true) ?? $grids->first())?->id;

        foreach ($this->dayBlocks as $dayString => $dayData) {
            $sheets[] = new WrittenExamSupervisionOverviewSheet(
                $dayString,
                $dayData,
                $timeSlots,
                $grids,
                $defaultGridId
            );
        }
        return $sheets;
    }
}
