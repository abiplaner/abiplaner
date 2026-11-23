<?php

namespace App\Exports;

use App\Models\ExamPeriod;
use App\Models\School;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ColloquiumPlanExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    protected ExamPeriod $examPeriod;
    /** @var Collection<int, array<string, mixed>> */
    protected Collection $days;
    protected ?School $school;
    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $supervisionSlotsByDate;

    /** @var array<int, array<string, mixed>> Styling-Anweisungen */
    protected array $styleMap = [];

    /** @var array<string, string> coupling_id → Hex-Farbe (ohne #) */
    protected array $couplingColorMap = [];

    /**
     * @param Collection<int, array<string, mixed>> $days
     * @param array<string, array<int, array<string, mixed>>> $supervisionSlotsByDate
     */
    public function __construct(
        ExamPeriod $examPeriod,
        Collection $days,
        ?School $school,
        array $supervisionSlotsByDate = []
    ) {
        $this->examPeriod             = $examPeriod;
        $this->days                   = $days;
        $this->school                 = $school;
        $this->supervisionSlotsByDate = $supervisionSlotsByDate;

        // Coupling-Farbzuweisung (gleiche Palette wie Planungsansicht, ohne #)
        $palette = ['FDE68A', 'A7F3D0', 'BFDBFE', 'FBCFE8', 'DDD6FE',
                    'FCA5A5', 'BAE6FD', 'BBF7D0', 'FED7AA', 'E9D5FF'];
        $idx = 0;
        foreach ($days as $day) {
            foreach ($day['timeSlots'] as $slot) {
                foreach ($slot->examAssignments as $a) {
                    $cid = $a->studentExam->coupling_id ?? null;
                    if ($cid && !isset($this->couplingColorMap[$cid])) {
                        $this->couplingColorMap[$cid] = $palette[$idx % count($palette)];
                        $idx++;
                    }
                }
            }
        }
    }

    public function title(): string
    {
        return 'Kolloquiumsplan';
    }

    /** @return array<int, array<int, string|null>> */
    public function array(): array
    {
        $rows = [];

        // ── Globaler Titel ───────────────────────────────────────────────────
        $rows[] = [$this->school !== null ? $this->school->name : 'Schule'];
        $this->styleMap[] = ['row' => count($rows), 'type' => 'title'];

        $rows[] = ['Kolloquiumsplan – ' . $this->examPeriod->name];
        $this->styleMap[] = ['row' => count($rows), 'type' => 'subtitle'];

        $rows[] = ['Stand: ' . now()->format('d.m.Y H:i') . ' Uhr'];
        $rows[] = [null]; // Leerzeile

        foreach ($this->days as $day) {
            /** @var \Carbon\Carbon $date */
            $date      = $day['date'];
            $dateStr   = (string) ($day['dateStr'] ?? $date->format('Y-m-d'));
            /** @var \Illuminate\Support\Collection<int, mixed> $prepRooms */
            $prepRooms = $day['prepRooms'];
            /** @var \Illuminate\Support\Collection<int, mixed> $waitRooms */
            $waitRooms = $day['waitRooms'];
            /** @var \Illuminate\Support\Collection<int, \App\Models\TimeSlot> $timeSlots */
            $timeSlots = $day['timeSlots'];

            // ── Spalten aufbauen (identisch zur PDF-Ansicht) ─────────────────
            /** @var array<string|int, array<string, mixed>> $columns */
            $columns = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $se        = $asgn->studentExam;
                    $roomId    = $asgn->examRoom->id ?? 'no_room';
                    $courseKey = $se->isExternalApplicant()
                        ? 'ext_' . $se->examiner_id . '_' . ($se->getSubjectName() ?? '?')
                        : $se->examiner_id . '_' . $se->course_id;
                    if (!isset($columns[$roomId])) {
                        $columns[$roomId] = ['room' => $asgn->examRoom, 'courses' => []];
                    }
                    if (!isset($columns[$roomId]['courses'][$courseKey])) {
                        $columns[$roomId]['courses'][$courseKey] = [
                            'subjectLabel' => $se->isExternalApplicant()
                                ? ($se->getSubjectName() ?? '?')
                                : ($se->course->course_code ?? '?'),
                            'examiner'  => $se->examiner,
                            'assessors' => collect(),
                        ];
                    }
                    if ($se->assessor && !$columns[$roomId]['courses'][$courseKey]['assessors']->contains('id', $se->assessor->id)) {
                        $columns[$roomId]['courses'][$courseKey]['assessors']->push($se->assessor);
                    }
                }
            }
            uasort($columns, fn ($a, $b) => strcmp(
                $a['room']->room_number ?? 'ZZZ',
                $b['room']->room_number ?? 'ZZZ'
            ));

            if (empty($columns)) {
                continue;
            }

            // ── Lookup [roomId][slotId] → Zuweisung ──────────────────────────
            /** @var array<string|int, array<int, \App\Models\ExamAssignment>> $lookup */
            $lookup = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $lookup[$asgn->examRoom->id ?? 'no_room'][$slot->id] = $asgn;
                }
            }

            // ── Warteraum-Annotationen (Ziffern) ─────────────────────────────
            /** @var array<string, string> $couplingLastPrep */
            $couplingLastPrep = [];
            /** @var array<string, int> $couplingCount */
            $couplingCount    = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $cid = $asgn->studentExam->coupling_id;
                    if ($cid) {
                        $couplingCount[$cid] = ($couplingCount[$cid] ?? 0) + 1;
                        $t = (string) $slot->prep_start_time;
                        if (!isset($couplingLastPrep[$cid]) || $t > $couplingLastPrep[$cid]) {
                            $couplingLastPrep[$cid] = $t;
                        }
                    }
                }
            }

            $relevantCouplings = array_filter($couplingCount, fn ($c) => $c > 1);
            /** @var array<int, string|null> $waitTarget */
            $waitTarget = [];
            foreach ($timeSlots as $slot) {
                $myExamEnd = (string) $slot->exam_end_time;
                $myPrep    = (string) $slot->prep_start_time;
                foreach ($slot->examAssignments as $asgn) {
                    $cid = $asgn->studentExam->coupling_id;
                    $waitTarget[$asgn->id] = null;
                    if ($cid && isset($relevantCouplings[$cid])) {
                        $lastPrep = $couplingLastPrep[$cid];
                        if ($myPrep < $lastPrep && $lastPrep > $myExamEnd) {
                            $waitTarget[$asgn->id] = $lastPrep;
                        }
                    }
                }
            }
            $usedWaitTimes = array_values(array_unique(array_filter(array_values($waitTarget))));
            sort($usedWaitTimes);
            /** @var array<string, int> $waitTimeToNum */
            $waitTimeToNum = [];
            foreach ($usedWaitTimes as $i => $t) {
                $waitTimeToNum[$t] = $i + 1;
            }
            /** @var array<int, int|null> $waitAnnot */
            $waitAnnot = [];
            foreach ($waitTarget as $asgnId => $target) {
                $waitAnnot[$asgnId] = $target !== null ? ($waitTimeToNum[$target] ?? null) : null;
            }

            // ── NTA-Annotationen (Sterne) ─────────────────────────────────────
            /** @var array<int, int> $ntaMinutes */
            $ntaMinutes = [];
            foreach ($timeSlots as $slot) {
                $prepMin = (int) ((strtotime((string) $slot->prep_end_time)
                               - strtotime((string) $slot->prep_start_time)) / 60);
                foreach ($slot->examAssignments as $asgn) {
                    $se    = $asgn->studentExam;
                    // Nachteilsausgleich hängt am Prüfling, nicht am
                    // Schülerdatensatz — externe Bewerber können ihn haben.
                    $extra = $se->kompensierteDauer($prepMin) - $prepMin;
                    $ntaMinutes[$asgn->id] = $extra;
                }
            }
            $ntaDistinct = array_values(array_unique(array_filter(array_values($ntaMinutes))));
            sort($ntaDistinct);
            $starSymbols    = ['*', '**', '***', '****'];
            /** @var array<int, string> $ntaValueToStar */
            $ntaValueToStar = [];
            foreach ($ntaDistinct as $i => $val) {
                $ntaValueToStar[$val] = $starSymbols[$i] ?? str_repeat('*', $i + 1);
            }

            // ── Musik-Annotationen (X) ────────────────────────────────────────
            /** @var array<int, bool> $musicAnnot */
            $musicAnnot = [];
            foreach ($timeSlots as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $code = strtolower($asgn->studentExam->course->course_code ?? '');
                    $musicAnnot[$asgn->id] = str_contains($code, 'mu');
                }
            }

            // ── Fußnoten vorbereiten ──────────────────────────────────────────
            /** @var array<int, string> $waitFootnotes */
            $waitFootnotes = [];
            foreach ($waitTimeToNum as $t => $num) {
                $waitFootnotes[$num] = substr((string) $t, 0, 5);
            }
            ksort($waitFootnotes);
            $hasMusic     = in_array(true, $musicAnnot, true);
            $hasFootnotes = !empty($waitFootnotes) || !empty($ntaValueToStar) || $hasMusic;

            $colCount   = 2 + count($columns);
            $columnKeys = array_keys($columns);

            // ── Tages-Überschrift ────────────────────────────────────────────
            $dayLabel  = $date->locale('de')->isoFormat('dddd, D. MMMM YYYY')
                       . '  (Woche ' . $day['week'] . ')';
            $headerRow = array_fill(0, $colCount, null);
            $headerRow[0] = $dayLabel;
            $rows[] = $headerRow;
            $this->styleMap[] = ['row' => count($rows), 'type' => 'day_header', 'cols' => $colCount];

            // ── Aufsicht-Info (Vorb.-/Warteraum) ─────────────────────────────
            $parts = [];
            if ($prepRooms->isNotEmpty()) {
                $parts[] = 'Vorbereitungsraum: ' . $prepRooms->pluck('room_number')->join(', ');
            }
            if ($waitRooms->isNotEmpty()) {
                $parts[] = 'Warteraum: ' . $waitRooms->pluck('room_number')->join(', ');
            }
            if ($parts) {
                $infoRow    = array_fill(0, $colCount, null);
                $infoRow[0] = implode('  |  ', $parts);
                $rows[] = $infoRow;
                $this->styleMap[] = ['row' => count($rows), 'type' => 'info', 'cols' => $colCount];
            }

            // ── Spalten-Header (Raum + Prüfer-Team) ──────────────────────────
            $headerCols = ['Vorbereitungszeit', 'Prüfungszeit'];
            foreach ($columns as $col) {
                $lines = ['Raum ' . ($col['room']->room_number ?? '?')];
                foreach ($col['courses'] as $courseInfo) {
                    $lines[] = '── ' . ($courseInfo['subjectLabel'] ?? '');
                    $lines[] = 'P: ' . ($courseInfo['examiner']->lastname ?? '–');
                    foreach ($courseInfo['assessors'] as $assessor) {
                        $lines[] = 'B: ' . $assessor->lastname;
                    }
                }
                $headerCols[] = implode("\n", $lines);
            }
            $rows[] = $headerCols;
            $this->styleMap[] = ['row' => count($rows), 'type' => 'col_header'];

            // ── Datenzeilen ──────────────────────────────────────────────────
            foreach ($timeSlots as $slot) {
                $dataRow   = [];
                $dataRow[] = substr((string) $slot->prep_start_time, 0, 5)
                           . '–'
                           . substr((string) $slot->prep_end_time, 0, 5);
                $dataRow[] = substr((string) $slot->exam_start_time, 0, 5)
                           . '–'
                           . substr((string) $slot->exam_end_time, 0, 5);

                foreach ($columnKeys as $colIdx => $roomId) {
                    /** @var \App\Models\ExamAssignment|null $asgn */
                    $asgn = $lookup[$roomId][$slot->id] ?? null;
                    if ($asgn) {
                        $se = $asgn->studentExam;

                        // Name wie im PDF
                        if ($se->isExternalApplicant()) {
                            $displayName = $se->getStudentName();
                        } else {
                            $ln = $se->student->lastname  ?? '?';
                            $fn = $se->student->firstname ?? '';
                            $displayName = $fn
                                ? $ln . ', ' . strtoupper(substr($fn, 0, 1)) . '.'
                                : $ln;
                        }

                        // Annotationen (Wartenummer, NTA-Stern, Musik-X)
                        $suffix   = '';
                        $waitNum  = $waitAnnot[$asgn->id] ?? null;
                        $ntaExtra = $ntaMinutes[$asgn->id] ?? 0;
                        $isMusic  = $musicAnnot[$asgn->id] ?? false;
                        if ($waitNum !== null) {
                            $suffix .= (string) $waitNum;
                        }
                        if ($ntaExtra > 0) {
                            $suffix .= $ntaValueToStar[$ntaExtra] ?? '*';
                        }
                        if ($isMusic) {
                            $suffix .= 'X';
                        }

                        $courseLabel = $se->isExternalApplicant()
                            ? ($se->getSubjectName() ?? '?')
                            : ($se->course->course_code ?? '?');

                        $cell = $displayName . ($suffix !== '' ? ' ' . $suffix : '')
                              . "\n" . $courseLabel
                              . ($se->isExternalApplicant() ? ' (extern)' : '');

                        $dataRow[] = $cell;

                        // Coupling-Farbe für diese Zelle
                        $cid = $se->coupling_id ?? null;
                        if ($cid && isset($this->couplingColorMap[$cid])) {
                            $this->styleMap[] = [
                                'row'   => count($rows) + 1,
                                'col'   => 3 + $colIdx,
                                'type'  => 'coupling',
                                'color' => $this->couplingColorMap[$cid],
                            ];
                        }
                    } else {
                        $dataRow[] = null;
                    }
                }

                $rows[] = $dataRow;
            }

            // ── Fußnoten ─────────────────────────────────────────────────────
            if ($hasFootnotes) {
                $fnParts = [];
                foreach ($waitFootnotes as $num => $timeStr) {
                    $fnParts[] = $num . ': Wartezeit bis ' . str_replace(':', '.', $timeStr) . ' Uhr';
                }
                foreach ($ntaValueToStar as $min => $star) {
                    $fnParts[] = $star . ' Vorbereitungszeit ' . $min . ' Min. länger (NTA)';
                }
                if ($hasMusic) {
                    $fnParts[] = 'X  Vorbereitungszeit 5 Min. länger (Musik-Kolloquium)';
                }
                $fnRow    = array_fill(0, $colCount, null);
                $fnRow[0] = implode('   |   ', $fnParts);
                $rows[] = $fnRow;
                $this->styleMap[] = ['row' => count($rows), 'type' => 'footnote', 'cols' => $colCount];
            }

            // ── Aufsichts-Tabelle ─────────────────────────────────────────────
            $daySupSlots = $this->supervisionSlotsByDate[$dateStr] ?? [];
            if (!empty($daySupSlots)) {
                $supLabelRow    = array_fill(0, $colCount, null);
                $supLabelRow[0] = 'Aufsichten';
                $rows[] = $supLabelRow;
                $this->styleMap[] = ['row' => count($rows), 'type' => 'sup_label', 'cols' => $colCount];

                $supHeader    = array_fill(0, $colCount, null);
                $supHeader[0] = 'Zeitraum';
                $supHeader[1] = 'Vorbereitungsraum';
                $supHeader[2] = 'Gangaufsicht';
                $supHeader[3] = 'Warteraum';
                $rows[] = $supHeader;
                $this->styleMap[] = ['row' => count($rows), 'type' => 'sup_header'];

                foreach ($daySupSlots as $supSlot) {
                    $supRow    = array_fill(0, $colCount, null);
                    $supRow[0] = $supSlot['start'] . '–' . $supSlot['end'];
                    foreach (['preparation', 'corridor', 'waiting'] as $si => $type) {
                        $teachers       = $supSlot['types'][$type] ?? [];
                        $supRow[$si + 1] = !empty($teachers)
                            ? implode(', ', array_map(fn ($t) => $t->lastname, $teachers))
                            : 'N.N.';
                    }
                    $rows[] = $supRow;
                    $this->styleMap[] = ['row' => count($rows), 'type' => 'sup_row'];
                }
            }

            // Trennzeile zwischen Tagen
            $rows[] = array_fill(0, $colCount, null);
        }

        return $rows;
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        // Wrap-Text + Vertikal oben für alle Zellen
        $highRow = $sheet->getHighestRow();
        $highCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highCol . $highRow)->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        foreach ($this->styleMap as $s) {
            $row    = $s['row'];
            $maxCol = isset($s['cols']) ? $this->colLetter($s['cols']) : $highCol;
            $range  = 'A' . $row . ':' . $maxCol . $row;

            switch ($s['type']) {
                case 'title':
                    $sheet->getStyle($range)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E3A5F']],
                    ]);
                    break;
                case 'subtitle':
                    $sheet->getStyle($range)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '2563EB']],
                    ]);
                    break;
                case 'day_header':
                    $sheet->getStyle($range)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType'   => Fill::FILL_SOLID,
                                   'startColor' => ['rgb' => '1E3A5F']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                                        'vertical'   => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(22);
                    break;

                case 'info':
                    $sheet->getStyle($range)->applyFromArray([
                        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '374151']],
                        'fill' => ['fillType'   => Fill::FILL_SOLID,
                                   'startColor' => ['rgb' => 'F3F4F6']],
                    ]);
                    break;
                case 'col_header':
                    $sheet->getStyle($range)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType'   => Fill::FILL_SOLID,
                                   'startColor' => ['rgb' => '2563EB']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                                        'vertical'   => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                                       'color'       => ['rgb' => 'BFDBFE']]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(60);
                    break;
                case 'footnote':
                    $sheet->getStyle($range)->applyFromArray([
                        'font'    => ['italic' => true, 'size' => 8, 'color' => ['rgb' => '374151']],
                        'fill'    => ['fillType'   => Fill::FILL_SOLID,
                                      'startColor' => ['rgb' => 'F9FAFB']],
                        'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN,
                                                    'color'       => ['rgb' => 'E5E7EB']]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(18);
                    break;
                case 'sup_label':
                    $sheet->getStyle($range)->applyFromArray([
                        'font'    => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
                        'fill'    => ['fillType'   => Fill::FILL_SOLID,
                                      'startColor' => ['rgb' => 'DBEAFE']],
                        'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN,
                                                    'color'       => ['rgb' => '93C5FD']]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(16);
                    break;

                case 'sup_header':
                    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
                        'fill'      => ['fillType'   => Fill::FILL_SOLID,
                                        'startColor' => ['rgb' => 'EFF6FF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                                        'vertical'   => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                                         'color'       => ['rgb' => 'BFDBFE']]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(16);
                    break;
                case 'sup_row':
                    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                        'font'    => ['size' => 9, 'color' => ['rgb' => '374151']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                                       'color'       => ['rgb' => 'DBEAFE']]],
                    ]);
                    $sheet->getStyle('A' . $row)->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
                        'fill'      => ['fillType'   => Fill::FILL_SOLID,
                                        'startColor' => ['rgb' => 'EFF6FF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(16);
                    break;
                case 'coupling':
                    $colLetter = $this->colLetter($s['col']);
                    $sheet->getStyle($colLetter . $row)->applyFromArray([
                        'fill' => ['fillType'   => Fill::FILL_SOLID,
                                   'startColor' => ['rgb' => $s['color']]],
                    ]);
                    break;
            }
        }

        // Standard-Zeilenhöhe für Datenzeilen
        for ($r = 1; $r <= $highRow; $r++) {
            if ($sheet->getRowDimension($r)->getRowHeight() < 0) {
                $sheet->getRowDimension($r)->setRowHeight(45);
            }
        }

        // Zeit-Spalten etwas schmaler fixieren
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(16);

        return [];
    }

    /** Konvertiert 1-basierten Spalten-Index → Excel-Buchstabe (A, B, … AA …) */
    private function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod    = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index  = (int) (($index - $mod) / 26);
        }
        return $letter;
    }
}
