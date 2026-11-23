<?php

namespace App\Exports;

use App\Exports\Concerns\BuildsSupervisionSheetRows;
use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamCentralSupervisionType;
use App\Models\Supervision;
use App\Models\WrittenExamSupervisionTimeSlot;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WrittenExamSupervisionPlanExport implements FromArray, ShouldAutoSize, WithTitle, WithStyles, WithEvents
{
    use BuildsSupervisionSheetRows;

    protected \App\Models\WrittenExamYearDate $examDate;
    protected string $subjectString;
    /** @var array<string|int, array<int, string>> */
    protected array $teacherCategoryMaps;
    protected bool  $hasForeignLanguage;

    protected array $headerRows    = [];
    protected array $gridTitleRows = [];
    protected array $columnRows    = [];
    /** @var array<int, array{row: int, col: int, category: string}> */
    protected array $categoryBadges = []; // Zellen mit Kategorie-Farbe

    public function __construct(
        \App\Models\WrittenExamYearDate $examDate,
        string $subjectString,
        array $teacherCategoryMaps = [],
        bool $hasForeignLanguage = false
    ) {
        $this->examDate           = $examDate;
        $this->subjectString      = $subjectString;
        $this->teacherCategoryMaps = $teacherCategoryMaps;
        $this->hasForeignLanguage  = $hasForeignLanguage;
    }

    public function array(): array
    {
        $dateIds = \App\Models\WrittenExamYearDate::whereDate('exam_date', $this->examDate->exam_date)->pluck('id');

        $assignments = WrittenExamAssignment::with([
                'room', 'course.teacher', 'course.subject',
                'supervisions.teacher', 'supervisions.writtenExamSupervisionTimeSlot',
                'seatAssignments.student', 'seatAssignments.externalApplicant',
            ])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->get();

        $corridorSupervisions = Supervision::with(['teacher'])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->where('supervision_type', 'corridor')
            ->whereNull('written_exam_assignment_id')
            ->whereNull('central_supervision_type_id')
            ->get();

        $reserveSupervisions = Supervision::with(['teacher'])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->where('supervision_type', 'reserve')
            ->whereNull('written_exam_assignment_id')
            ->get();

        $centralTypes = WrittenExamCentralSupervisionType::whereIn('written_exam_year_date_id', $dateIds)
            ->orderBy('sort_order')->orderBy('name')->get();

        $centralSupervisions = Supervision::with(['teacher'])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->where('supervision_type', 'extra')
            ->whereNull('written_exam_assignment_id')
            ->whereNotNull('central_supervision_type_id')
            ->get()
            ->groupBy('central_supervision_type_id');

        $timeSlots = WrittenExamSupervisionTimeSlot::with('grid')->orderBy('slot_number')->get();
        $grids         = \App\Models\WrittenExamSupervisionGrid::orderByDesc('is_default')->orderBy('name')->get();
        $defaultGrid   = $grids->firstWhere('is_default', true) ?? $grids->first();
        $defaultGridId = $defaultGrid !== null ? $defaultGrid->id : null;

        $assignmentsByRoom = $assignments->groupBy('room_id');
        $slotsByGridId     = $timeSlots->groupBy(fn($s) => $s->written_exam_supervision_grid_id ?? 0);

        $roomsByGrid = [];
        foreach ($assignmentsByRoom as $roomAssignments) {
            $fa  = $roomAssignments->first();
            $gid = $fa->written_exam_supervision_grid_id ?? $defaultGridId ?? 0;
            $courseStr = $roomAssignments->map(function($a) {
                $start = $a->examDate->getStartTime()->format('H:i');
                $end   = collect($a->seatAssignments)
                    ->map(fn($sa) => $a->examDate->getEndTime($sa->student?->disability_compensation_percent ?: null)->format('H:i'))
                    ->unique()->implode('/') ?: '?';
                $t = $a->course->teacher ? ' ' . $a->course->teacher->shortcode : '';
                return ($a->course->course_code ?? '?') . $t . ' [' . $start . '–' . $end . ']';
            })->implode(' | ');
            $roomsByGrid[$gid][] = ['room' => $fa->room, 'assignments' => $roomAssignments, 'courseStr' => $courseStr];
        }

        $allGridIds = collect(array_keys($roomsByGrid))
            ->merge($slotsByGridId->keys())
            ->unique()
            ->sortBy(fn($id) => $id == $defaultGridId ? 0 : 1)
            ->values();

        $rows   = [];
        $rowNum = 1;

        // Titelzeilen
        $rows[] = ['Aufsichtsplan: ' . $this->subjectString];
        $this->headerRows[] = $rowNum++;
        $rows[] = [\Carbon\Carbon::parse($this->examDate->exam_date)->locale('de')->isoFormat('dddd, DD. MMMM YYYY')];
        $this->headerRows[] = $rowNum++;
        $rows[] = [];
        $rowNum++;

        // Pro Raster ein Block
        foreach ($allGridIds as $gid) {
            $grid      = $grids->firstWhere('id', $gid);
            // Fremdsprachen-Raster überspringen wenn kein FL-Tag
            if (($grid?->is_foreign_language) && !$this->hasForeignLanguage) continue;

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
                $label = 'Raum ' . ($roomData['room']->room_number ?? $roomData['room']->id) . '  ' . $roomData['courseStr'];
                $rows[] = $this->buildSupervisionRow(
                    $label,
                    $slotRows,
                    fn($slot) => $roomData['assignments']->flatMap(fn($a) => $a->supervisions)
                        ->filter(fn($s) => $slot
                            ? $s->written_exam_supervision_time_slot_id === $slot->id
                            : $s->written_exam_supervision_time_slot_id === null),
                    $this->teacherCategoryMaps,
                    $rowNum
                );
                $rowNum++;
            }
            $rows[] = [];
            $rowNum++;
        }

        // Gangaufsicht & Reserve
        $defaultSlots    = $slotsByGridId->get($defaultGrid !== null ? $defaultGrid->id : 0, collect())->sortBy('slot_number');
        $defaultSlotRows = $defaultSlots->isNotEmpty() ? $defaultSlots : collect([null]);

        [$titleRow, $hdr] = $this->buildGridHeaderRows(
            'Gangaufsicht & Reserve – Raster: ' . ($defaultGrid !== null ? $defaultGrid->name : 'Standard'),
            'Position',
            $defaultSlotRows,
            false,
            $rowNum
        );
        $rows[] = $titleRow;
        $rows[] = $hdr;

        $rows[] = $this->buildSupervisionRow(
            'Gangaufsicht',
            $defaultSlotRows,
            fn($slot) => $corridorSupervisions->filter(fn($s) => $slot
                ? $s->written_exam_supervision_time_slot_id === $slot->id
                : $s->written_exam_supervision_time_slot_id === null),
            $this->teacherCategoryMaps,
            $rowNum
        );
        $rowNum++;

        $rows[] = $this->buildSupervisionRow(
            'Reserve (Lehrerzimmer)',
            $defaultSlotRows,
            fn($slot) => $reserveSupervisions->filter(fn($s) => $slot
                ? $s->written_exam_supervision_time_slot_id === $slot->id
                : $s->written_exam_supervision_time_slot_id === null),
            $this->teacherCategoryMaps,
            $rowNum
        );
        $rowNum++;

        // Zusätzliche Positionen
        if ($centralTypes->isNotEmpty()) {
            $rows[] = [];
            $rowNum++;
            $allSlots = $timeSlots->isNotEmpty() ? $timeSlots : collect([null]);
            [$titleRow, $hdr] = $this->buildGridHeaderRows(
                'Zusätzliche Positionen',
                'Position',
                $allSlots,
                false,
                $rowNum
            );
            $rows[] = $titleRow;
            $rows[] = $hdr;

            foreach ($centralTypes as $type) {
                $rows[] = $this->buildSupervisionRow(
                    $type->name,
                    $allSlots,
                    fn($slot) => ($centralSupervisions->get($type->id) ?? collect())
                        ->filter(fn($s) => $slot
                            ? $s->written_exam_supervision_time_slot_id === $slot->id
                            : $s->written_exam_supervision_time_slot_id === null),
                    $this->teacherCategoryMaps,
                    $rowNum
                );
                $rowNum++;
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Aufsichtsplan';
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $this->applyRowStyles($sheet);

                // Legende ans Ende anhängen (nur im Einzeltag-Export, die Gesamtübersicht hat keine)
                $lastRow = $sheet->getHighestRow() + 2;
                $sheet->setCellValue("A{$lastRow}", 'Legende:');
                $sheet->getStyle("A{$lastRow}")->applyFromArray(['font' => ['bold' => true]]);
                $lastRow++;

                $legendLabels = [
                    'q13_lesson'         => 'Q13-Unterricht (Klasse schreibt Prüfung)',
                    'free_already_there' => 'Freistunde (war vorher in der Schule)',
                    'free'               => 'Freistunde',
                    'has_lesson'         => 'Anderer Unterricht (Warnung)',
                    'unknown'            => 'Kein Stundenplan hinterlegt',
                ];
                $catColors = $this->categoryColors();
                foreach ($legendLabels as $category => $label) {
                    $rgb = $catColors[$category] ?? 'F3F4F6';
                    $sheet->setCellValue("A{$lastRow}", $label);
                    $sheet->getStyle("A{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
                    ]);
                    $lastRow++;
                }
            },
        ];
    }
}
