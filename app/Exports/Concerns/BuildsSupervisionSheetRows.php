<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gemeinsame Bausteine für den Aufsichtsplan-Export: Zeilen mit
 * Lehrernamen + Kategorie-Farbmarkierung je Zeitslot aufbauen, sowie die
 * Kopf-/Raster-/Spalten-Formatierung auf dem Sheet anwenden.
 *
 * Wird von WrittenExamSupervisionOverviewSheet (Gesamtexport, ein Sheet pro
 * Tag, Daten werden von außen übergeben) und WrittenExamSupervisionPlanExport
 * (Einzeltag-Export, lädt seine Daten selbst) genutzt - beide bauen
 * strukturell denselben Aufsichtsplan auf (Raumzeilen, Gangaufsicht,
 * Reserve, zusätzliche Positionen), nur die Datenherkunft und ein paar
 * Beschriftungen unterscheiden sich zwischen Gesamt- und Einzelexport.
 * (phpcpd-Befund 22.09.2026: mehrere Klone zwischen und innerhalb beider
 * Dateien, siehe claude/code-duplizierung-22-09.md.)
 *
 * Setzt auf der nutzenden Klasse die Properties $categoryBadges (array),
 * $headerRows (array<int>), $gridTitleRows (array<int>) und $columnRows
 * (array<int>) voraus.
 */
trait BuildsSupervisionSheetRows
{
    /** Gibt "Nachname, V." zurück. */
    protected function formatTeacherName(?\App\Models\Teacher $teacher): string
    {
        if (!$teacher) return '?';
        return ($teacher->lastname ?? '?') . ', ' . substr($teacher->firstname ?? '', 0, 1) . '.';
    }

    /** Schlechteste Kategorie für eine Zelle (für Zellfärbung). */
    protected function worstCategory(array $categories): string
    {
        $priority = ['has_lesson' => 0, 'unknown' => 1, 'free' => 2, 'free_already_there' => 3, 'q13_lesson' => 4];
        $worst = 'q13_lesson';
        foreach ($categories as $cat) {
            if (($priority[$cat] ?? 99) < ($priority[$worst] ?? 99)) {
                $worst = $cat;
            }
        }
        return $worst;
    }

    /** Kategorie -> Zellfarbe (RGB ohne #). Einzige Quelle für die Farbwerte. */
    protected function categoryColors(): array
    {
        return [
            'q13_lesson'         => 'CCFBF1',
            'free_already_there' => 'DCFCE7',
            'free'               => 'DBEAFE',
            'has_lesson'         => 'FEE2E2',
            'unknown'            => 'F3F4F6',
        ];
    }

    /**
     * Baut die Raster-Titelzeile (Rasterbezeichnung) und die zugehörige
     * Spaltenkopf-Zeile (Zeitslot-Zeitspannen) für einen Aufsichtsplan-Block
     * und trägt beide Zeilennummern in $this->gridTitleRows/$columnRows ein.
     * Der Titeltext wird von außen übergeben, da er sich zwischen den
     * Blöcken (Raster, Gangaufsicht & Reserve, Zusätzliche Positionen) und
     * zwischen Gesamt- und Einzeltag-Export unterscheidet.
     *
     * @param  \Illuminate\Support\Collection|array  $slotRows  Slots dieses Blocks (oder [null] für "ohne Zeitslot")
     * @param  bool  $withLabelSuffix  Zeitslot-Label in Klammern anhängen (nur beim Raster-Block genutzt)
     * @param  int  $rowNum  aktuelle (noch nicht inkrementierte) Zeilennummer, wird um 2 erhöht
     * @return array{0: array<int, string>, 1: array<int, string>} [Titelzeile, Kopfzeile] (für $rows[] = ...)
     */
    protected function buildGridHeaderRows(
        string $title,
        string $firstColumnLabel,
        $slotRows,
        bool $withLabelSuffix,
        int &$rowNum
    ): array {
        $this->gridTitleRows[] = $rowNum++;

        $hdr = [$firstColumnLabel];
        foreach ($slotRows as $slot) {
            if (!$slot) {
                $hdr[] = '(ohne Zeitslot)';
                continue;
            }
            $range = substr($slot->start_time, 0, 5) . '–' . substr($slot->end_time, 0, 5);
            $hdr[] = $withLabelSuffix && $slot->label ? $range . ' (' . $slot->label . ')' : $range;
        }
        $this->columnRows[] = $rowNum++;

        return [[$title], $hdr];
    }

    /**
     * Baut eine Positions-/Raumzeile für den Excel-Export: erste Spalte ist
     * $label, danach je Slot aus $slotRows die Lehrernamen der von
     * $cellForSlot gelieferten Supervisions-Collection, mit Kategorie-Badge
     * (trägt direkt in $this->categoryBadges ein).
     *
     * @param  \Illuminate\Support\Collection|array  $slotRows  Slots dieser Zeile (oder [null] für "ohne Zeitslot")
     * @param  callable  $cellForSlot  function(?object $slot): \Illuminate\Support\Collection der Supervisions für diese Spalte
     * @param  array<string|int, array<int, string>>  $teacherCategoryMaps
     * @param  int  $rowNum  aktuelle (noch nicht inkrementierte) Zeilennummer, für categoryBadges
     * @return array die fertige Zeile (für $rows[] = ...)
     */
    protected function buildSupervisionRow(
        string $label,
        $slotRows,
        callable $cellForSlot,
        array $teacherCategoryMaps,
        int $rowNum
    ): array {
        $row = [$label];
        $colIdx = 2;
        foreach ($slotRows as $slot) {
            $slotKey = $slot !== null ? $slot->id : 'none';
            $cell = $cellForSlot($slot);
            $row[] = $cell->map(fn($s) => $this->formatTeacherName($s->teacher))->implode(' / ');
            $cats = $cell->map(fn($s) => $teacherCategoryMaps[$slotKey][$s->teacher_id] ?? 'unknown')->toArray();
            if (!empty($cats)) {
                $this->categoryBadges[] = ['row' => $rowNum, 'col' => $colIdx, 'category' => $this->worstCategory($cats)];
            }
            $colIdx++;
        }
        return $row;
    }

    /**
     * Wendet die Kopf-/Raster-/Spalten-Formatierung und die Kategorie-
     * Zellfärbung auf das Sheet an: headerRows fett+groß, gridTitleRows
     * fett+grau hinterlegt, columnRows fett+blau hinterlegt, categoryBadges
     * je nach Kategorie eingefärbt.
     */
    protected function applyRowStyles(Worksheet $sheet, string $highCol = 'Z'): void
    {
        $catColors = $this->categoryColors();

        foreach ($this->headerRows as $r) {
            $sheet->getStyle("A{$r}:{$highCol}{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 13]]);
        }
        foreach ($this->gridTitleRows as $r) {
            $sheet->getStyle("A{$r}:{$highCol}{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            ]);
        }
        foreach ($this->columnRows as $r) {
            $sheet->getStyle("A{$r}:{$highCol}{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            ]);
        }
        foreach ($this->categoryBadges as $badge) {
            $colLetter = Coordinate::stringFromColumnIndex($badge['col']);
            $rgb = $catColors[$badge['category']] ?? 'F3F4F6';
            $sheet->getStyle("{$colLetter}{$badge['row']}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
            ]);
        }
    }
}
