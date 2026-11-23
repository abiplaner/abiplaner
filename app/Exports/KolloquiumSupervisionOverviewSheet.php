<?php

namespace App\Exports;

use App\Models\ExamPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ein Excel-Sheet für einen Prüfungstag im Kolloquium-Aufsichtsexport.
 *
 * Struktur:
 *   Zeile 1: Titel (Datum + Prüfungszeitraum-Name)
 *   Zeile 2: leer
 *   Zeile 3: Spaltenköpfe (Position | Slot1 | Slot2 | ...)
 *   Ab Zeile 4: Je eine Zeile pro Raum/Position
 *
 * Abschnitte:
 *   1. Vorbereitungsräume
 *   2. Warteräume
 *   3. Gangaufsichten (Räume)
 *   4. Gangaufsichten (freie Texte)
 */
class KolloquiumSupervisionOverviewSheet implements FromArray, ShouldAutoSize, WithTitle, WithStyles, WithEvents
{
    protected ExamPeriod $examPeriod;
    protected string $dateStr;
    /** @var array<string, mixed> */
    protected array $dayData;
    /** @var \Illuminate\Support\Collection */
    protected $supervisions;

    /** @var int[] */
    protected array $titleRows     = [];
    /** @var int[] */
    protected array $sectionRows   = [];
    /** @var int[] */
    protected array $headerRows    = [];

    /**
     * @param array<string, mixed>       $dayData
     * @param \Illuminate\Support\Collection $supervisions
     */
    public function __construct(ExamPeriod $examPeriod, string $dateStr, array $dayData, $supervisions)
    {
        $this->examPeriod   = $examPeriod;
        $this->dateStr      = $dateStr;
        $this->dayData      = $dayData;
        $this->supervisions = $supervisions;
    }

    private function teacherShort(?\App\Models\Teacher $teacher): string
    {
        if ($teacher === null) return '';
        return $teacher->shortcode ?? ($teacher->lastname ?? '?');
    }

    /** Aufsicht für Slot + Raum + Type ermitteln */
    private function findSupervision(int $slotId, int $roomId, string $type): ?\App\Models\Supervision
    {
        return $this->supervisions->first(
            fn($s) => $s->time_slot_id === $slotId
                   && $s->room_id === $roomId
                   && $s->supervision_type === $type
        );
    }

    public function array(): array
    {
        /** @var \Illuminate\Support\Collection $slots */
        $slots            = $this->dayData['slots'];
        $prepRooms        = $this->dayData['prepRooms'];
        $waitRooms        = $this->dayData['waitRooms'];
        $corridorRooms    = $this->dayData['corridorRooms'];
        $freeCorridorSups = $this->dayData['freeCorridorSups'];

        $rows   = [];
        $rowNum = 1;

        // Titelzeile
        $dateLabel = \Carbon\Carbon::parse($this->dateStr)->locale('de')->isoFormat('dddd, DD. MMMM YYYY');
        $rows[]    = [$dateLabel . '  –  ' . $this->examPeriod->name];
        $this->titleRows[] = $rowNum++;

        $rows[] = [];
        $rowNum++;

        // Spaltenköpfe aufbauen
        $slotHeaders = ['Position'];
        foreach ($slots as $slot) {
            $slotHeaders[] = substr($slot->prep_start_time, 0, 5) . '–' . substr($slot->exam_end_time, 0, 5);
        }

        // Helper: Abschnitt schreiben
        $writeSection = function (string $label, $rooms, string $type) use (
            &$rows, &$rowNum, $slots, $slotHeaders
        ): void {
            if ($rooms->isEmpty()) return;

            $rows[] = [$label];
            $this->sectionRows[] = $rowNum++;

            $rows[] = $slotHeaders;
            $this->headerRows[] = $rowNum++;

            foreach ($rooms as $odr) {
                $row = [$odr->room->room_number ?? '?'];
                foreach ($slots as $slot) {
                    $sup   = $this->findSupervision($slot->id, $odr->room_id, $type);
                    $row[] = $this->teacherShort($sup !== null ? $sup->teacher : null);
                }
                $rows[] = $row;
                $rowNum++;
            }

            $rows[] = [];
            $rowNum++;
        };

        $writeSection('Vorbereitungsräume', $prepRooms,     'preparation');
        $writeSection('Warteräume',         $waitRooms,     'waiting');
        $writeSection('Gangaufsichten (Räume)', $corridorRooms, 'corridor');

        // Freie Gangaufsichten (location_text)
        if ($freeCorridorSups->isNotEmpty()) {
            $rows[] = ['Gangaufsichten (Positionen)'];
            $this->sectionRows[] = $rowNum++;

            $rows[] = $slotHeaders;
            $this->headerRows[] = $rowNum++;

            // Pro Slot: alle freien Gangaufsichten als kommaseparierte Liste
            $freeBySlot = $freeCorridorSups->groupBy('time_slot_id');
            $row = ['Gang'];
            foreach ($slots as $slot) {
                $slotFree = $freeBySlot->get($slot->id, collect());
                $cell = $slotFree->map(fn($s) =>
                    $this->teacherShort($s->teacher)
                    . ($s->location_text ? ' (' . $s->location_text . ')' : '')
                )->filter()->join(' / ');
                $row[] = $cell;
            }
            $rows[] = $row;
            $rowNum++;
        }

        return $rows;
    }

    public function title(): string
    {
        return \Carbon\Carbon::parse($this->dateStr)->format('d.m.Y');
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $highCol = 'Z';

                foreach ($this->titleRows as $r) {
                    $sheet->getStyle("A{$r}:{$highCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 13],
                    ]);
                }
                foreach ($this->sectionRows as $r) {
                    $sheet->getStyle("A{$r}:{$highCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                    ]);
                }
                foreach ($this->headerRows as $r) {
                    $sheet->getStyle("A{$r}:{$highCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                    ]);
                }
            },
        ];
    }
}
