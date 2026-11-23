<?php

namespace App\Exports;

use App\Models\ExamPeriod;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel-Export des Kolloquium-Aufsichtsplans.
 * Ein Sheet pro Prüfungstag.
 */
class KolloquiumSupervisionOverviewExport implements Export, WithMultipleSheets
{
    protected ExamPeriod $examPeriod;
    /** @var array<string, array<string, mixed>> */
    protected array $dayBlocks;
    /** @var \Illuminate\Support\Collection */
    protected $supervisions;
    /** @var array<int, string> */
    protected array $coverageWarnings;

    /**
     * @param array<string, array<string, mixed>> $dayBlocks
     * @param \Illuminate\Support\Collection      $supervisions
     * @param array<int, string>                  $coverageWarnings
     */
    public function __construct(ExamPeriod $examPeriod, array $dayBlocks, $supervisions, array $coverageWarnings = [])
    {
        $this->examPeriod       = $examPeriod;
        $this->dayBlocks        = $dayBlocks;
        $this->supervisions     = $supervisions;
        $this->coverageWarnings = $coverageWarnings;
    }

    /** @return \Maatwebsite\Excel\Concerns\WithTitle[] */
    public function sheets(): array
    {
        $sheets = [new KolloquiumCoverageWarningsSheet($this->coverageWarnings)];
        foreach ($this->dayBlocks as $dateStr => $dayData) {
            $sheets[] = new KolloquiumSupervisionOverviewSheet(
                $this->examPeriod,
                $dateStr,
                $dayData,
                $this->supervisions
            );
        }
        return $sheets;
    }
}
