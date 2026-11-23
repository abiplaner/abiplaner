<?php

namespace App\Exports;

use App\Exports\Concerns\BuildsSupervisionSheetRows;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ein Excel-Sheet für einen Prüfungstag im Gesamtexport.
 */
class WrittenExamSupervisionOverviewSheet implements FromArray, ShouldAutoSize, WithTitle, WithStyles, WithEvents
{
    use BuildsSupervisionSheetRows;

    protected string $dayString;
    /** @var array<string, mixed> */
    protected array $dayData;
    /** @var \Illuminate\Support\Collection<int, \App\Models\WrittenExamSupervisionTimeSlot> */
    protected $timeSlots;
    /** @var \Illuminate\Support\Collection<int, \App\Models\WrittenExamSupervisionGrid> */
    protected $grids;
    protected ?int $defaultGridId;

    protected array $headerRows    = [];
    protected array $gridTitleRows = [];
    protected array $columnRows    = [];
    /** @var array<int, array{row: int, col: int, category: string}> */
    protected array $categoryBadges = [];

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\WrittenExamSupervisionTimeSlot> $timeSlots
     * @param \Illuminate\Support\Collection<int, \App\Models\WrittenExamSupervisionGrid> $grids
     */
    public function __construct(string $dayString, array $dayData, $timeSlots, $grids, ?int $defaultGridId)
    {
        $this->dayString     = $dayString;
        $this->dayData       = $dayData;
        $this->timeSlots     = $timeSlots;
        $this->grids         = $grids;
        $this->defaultGridId = $defaultGridId;
    }

    public function array(): array
    {
        $assignments          = $this->dayData['assignments'];
        $corridorSupervisions = $this->dayData['corridorSupervisions'];
        $reserveSupervisions  = $this->dayData['reserveSupervisions'];
        $centralTypes         = $this->dayData['centralTypes'];
        $centralSupervisions  = $this->dayData['centralSupervisions'];
        $hasForeignLanguage   = $this->dayData['hasForeignLanguage'];
        $teacherCategoryMaps  = $this->dayData['teacherCategoryMaps'];

        $assignmentsByRoom = $assignments->groupBy('room_id');
        $slotsByGridId     = $this->timeSlots->groupBy(fn($s) => $s->written_exam_supervision_grid_id ?? 0);

        $roomsByGrid = [];
        foreach ($assignmentsByRoom as $roomAssignments) {
            $fa  = $roomAssignments->first();
            $gid = $fa->written_exam_supervision_grid_id ?? $this->defaultGridId ?? 0;
            $courseStr = $roomAssignments->map(fn($a) => ($a->course->course_code ?? '?')
                . ($a->course->teacher ? ' ' . $a->course->teacher->shortcode : ''))->implode(' | ');
            $roomsByGrid[$gid][] = ['room' => $fa->room, 'assignments' => $roomAssignments, 'courseStr' => $courseStr];
        }

        $allGridIds = collect(array_keys($roomsByGrid))
            ->merge($slotsByGridId->keys())
            ->unique()
            ->sortBy(fn($id) => $id == $this->defaultGridId ? 0 : 1)
            ->values();


        $rows   = [];
        $rowNum = 1;

        // Titelzeile
        $rows[] = [\Carbon\Carbon::parse($this->dayString)->locale('de')->isoFormat('dddd, DD. MMMM YYYY')
            . ($this->dayData['subjectString'] ? '  –  ' . $this->dayData['subjectString'] : '')];
        $this->headerRows[] = $rowNum++;
        if ($this->dayData['timeRange']) {
            $rows[] = [$this->dayData['timeRange']];
            $rowNum++;
        }
        $rows[] = [];
        $rowNum++;

        foreach ($allGridIds as $gid) {
            $grid      = $this->grids->firstWhere('id', $gid);
            if (($grid?->is_foreign_language) && !$hasForeignLanguage) continue;

            $gridSlots = $slotsByGridId->get($gid, collect())->sortBy('slot_number');
            $gridRooms = $roomsByGrid[$gid] ?? [];
            $slotRows  = $gridSlots->isNotEmpty() ? $gridSlots : collect([null]);

            [$titleRow, $hdr] = $this->buildGridHeaderRows(
                $grid !== null ? $grid->name : 'Standard-Raster',
                'Raum',
                $slotRows,
                true,
                $rowNum
            );
            $rows[] = $titleRow;
            $rows[] = $hdr;

            foreach ($gridRooms as $roomData) {
                $label = ($roomData['room']->room_number ?? 'R.' . $roomData['room']->id) . '  ' . $roomData['courseStr'];
                $rows[] = $this->buildSupervisionRow(
                    $label,
                    $slotRows,
                    fn($slot) => $roomData['assignments']->flatMap(fn($a) => $a->supervisions)
                        ->filter(fn($s) => $slot
                            ? $s->written_exam_supervision_time_slot_id === $slot->id
                            : $s->written_exam_supervision_time_slot_id === null),
                    $teacherCategoryMaps,
                    $rowNum
                );
                $rowNum++;
            }
            $rows[] = [];
            $rowNum++;
        }

        // Gangaufsicht & Reserve
        $defaultSlots    = $slotsByGridId->get($this->defaultGridId ?? 0, collect())->sortBy('slot_number');
        $defaultSlotRows = $defaultSlots->isNotEmpty() ? $defaultSlots : collect([null]);
        $defaultGrid     = $this->grids->firstWhere('is_default', true) ?? $this->grids->first();

        [$titleRow, $hdr] = $this->buildGridHeaderRows(
            'Gangaufsicht & Reserve – ' . ($defaultGrid !== null ? $defaultGrid->name : 'Standard'),
            'Position',
            $defaultSlotRows,
            false,
            $rowNum
        );
        $rows[] = $titleRow;
        $rows[] = $hdr;

        $filterBySlot = fn($supervisions, $slot) => $supervisions->filter(fn($s) => $slot
            ? $s->written_exam_supervision_time_slot_id === $slot->id
            : $s->written_exam_supervision_time_slot_id === null);

        $rows[] = $this->buildSupervisionRow(
            'Gangaufsicht',
            $defaultSlotRows,
            fn($slot) => $filterBySlot($corridorSupervisions, $slot),
            $teacherCategoryMaps,
            $rowNum
        );
        $rowNum++;

        $rows[] = $this->buildSupervisionRow(
            'Reserve (Lehrerzimmer)',
            $defaultSlotRows,
            fn($slot) => $filterBySlot($reserveSupervisions, $slot),
            $teacherCategoryMaps,
            $rowNum
        );
        $rowNum++;

        if ($centralTypes->isNotEmpty()) {
            $allSlots = $this->timeSlots->isNotEmpty() ? $this->timeSlots : collect([null]);
            foreach ($centralTypes as $type) {
                $rows[] = $this->buildSupervisionRow(
                    $type->name,
                    $allSlots,
                    fn($slot) => ($centralSupervisions->get($type->id) ?? collect())
                        ->filter(fn($s) => $slot ? $s->written_exam_supervision_time_slot_id === $slot->id : $s->written_exam_supervision_time_slot_id === null),
                    $teacherCategoryMaps,
                    $rowNum
                );
                $rowNum++;
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return \Carbon\Carbon::parse($this->dayString)->format('d.m.Y');
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->applyRowStyles($event->sheet->getDelegate());
            },
        ];
    }
}
