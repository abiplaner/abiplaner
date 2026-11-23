<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WseminarKurs;
use App\Models\WseminarWahl;
use App\Http\Controllers\Concerns\HasSchuljahr;
use App\Http\Controllers\Concerns\HasPrioritaetLabel;

class WseminarKursController extends Controller
{
    use HasSchuljahr;
    use HasPrioritaetLabel;
    /**
     * Display a listing of the kurse (after zuteilung).
     */
    public function index(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang  = 11; // W-Seminare nur in Q11

        $kurse = $this->getKurseSorted($schuljahr, $jahrgang);

        // Prioritätszähler pro Kurs setzen
        $this->countPrioritaeten($kurse);

        // Wahlen für Gesamtstatistik
        $wahlen = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['erstwahl', 'zweitwahl', 'drittwahl', 'zugeteilterKurs'])
            ->get();

        $totalStudents = \App\Models\Student::where('class_name', 'LIKE', "%{$jahrgang}%")->count();

        $leerCount   = 0;
        $failedCount = 0;
        foreach ($wahlen as $w) {
            if (!$w->zuteilung_wseminar_kurs_id) {
                if (!$w->erstwahl_wseminar_id && !$w->zweitwahl_wseminar_id && !$w->drittwahl_wseminar_id) {
                    $leerCount++;
                } else {
                    $failedCount++;
                }
            }
        }

        $noRecordCount    = max(0, $totalStudents - $wahlen->count());
        $garNichtGewaehlt = $noRecordCount + $leerCount;

        $statistik = [
            'anzahl_schueler'      => $totalStudents,
            'zugeteilt'            => $wahlen->whereNotNull('zuteilung_wseminar_kurs_id')->count(),
            'nicht_zugeteilt'      => $failedCount,
            'gar_nicht_gewaehlt'   => $garNichtGewaehlt,
            'erstwahl'             => $wahlen->where('prioritaet_erhalten', 'erstwahl')->count(),
            'zweitwahl'            => $wahlen->where('prioritaet_erhalten', 'zweitwahl')->count(),
            'drittwahl'            => $wahlen->where('prioritaet_erhalten', 'drittwahl')->count(),
        ];

        $schuljahre       = $this->getAvailableSchuljahre();
        $unassignedWahlen = $wahlen->whereNull('zuteilung_wseminar_kurs_id');

        return view('wseminar-kurse.index', compact(
            'kurse', 'statistik', 'schuljahr', 'jahrgang',
            'schuljahre', 'unassignedWahlen'
        ));
    }
    
    /**
     * Reset zuteilung.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang' => 'required|integer|in:11',
        ]);
        
        $zuteilungsService = new \App\Services\WseminarZuteilungsService();
        
        $affected = $zuteilungsService->resetZuteilung(
            auth()->user()->school_id,
            $request->input('schuljahr'),
            $request->input('jahrgang')
        );
        
        return redirect()->route('wseminar-kurse.index', [
            'schuljahr' => $request->input('schuljahr'),
            'jahrgang' => $request->input('jahrgang')
        ])->with('success', "Zuteilung wurde zurückgesetzt. {$affected} Wahlen betroffen.");
    }

    /**
     * Show a specific kurs.
     */
    public function show($id)
    {
        $kurs = WseminarKurs::query()
            ->with(['subject', 'lehrer', 'wahlen.schueler'])
            ->findOrFail($id);
        
        // Sort students by lastname
        $teilnehmer = $kurs->wahlen->sortBy('schueler.lastname');
        
        return view('wseminar-kurse.show', compact('kurs', 'teilnehmer'));
    }

    /**
     * Export courses to PDF (one course per page).
     */
    public function exportPdf(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang  = 11;

        $kurse = $this->getKurseSorted($schuljahr, $jahrgang);

        // Prioritätszähler + sortierte Wahlen pro Kurs setzen
        foreach ($kurse as $kurs) {
            $kurs->erstwahlen_count  = 0;
            $kurs->zweitwahlen_count = 0;
            $kurs->drittwahlen_count = 0;
            $kurs->sorted_wahlen     = $kurs->wahlen->sortBy('schueler.lastname');

            foreach ($kurs->wahlen as $wahl) {
                if ($wahl->prioritaet_erhalten === 'erstwahl')       $kurs->erstwahlen_count++;
                elseif ($wahl->prioritaet_erhalten === 'zweitwahl') $kurs->zweitwahlen_count++;
                elseif ($wahl->prioritaet_erhalten === 'drittwahl') $kurs->drittwahlen_count++;
            }
        }
        
        // Calculate global statistics
        $wahlenAll = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->get();
            
        $statistik = [
            'anzahl_schueler' => $wahlenAll->count(),
            'zugeteilt' => $wahlenAll->whereNotNull('zuteilung_wseminar_kurs_id')->count(),
            'nicht_zugeteilt' => $wahlenAll->whereNull('zuteilung_wseminar_kurs_id')->count(),
            'erstwahl' => $wahlenAll->where('prioritaet_erhalten', 'erstwahl')->count(),
            'zweitwahl' => $wahlenAll->where('prioritaet_erhalten', 'zweitwahl')->count(),
            'drittwahl' => $wahlenAll->where('prioritaet_erhalten', 'drittwahl')->count(),
        ];

        // Unassigned students
        $unassigned = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->whereNull('zuteilung_wseminar_kurs_id')
            ->with(['schueler', 'erstwahl', 'zweitwahl', 'drittwahl'])
            ->get()
            ->sortBy('schueler.lastname');
            
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('wseminar-kurse.export-pdf', compact('kurse', 'unassigned', 'statistik', 'schuljahr', 'jahrgang'));
        $pdf->setPaper('A4', 'portrait');
        
        $filename = $this->getExportFilename($schuljahr);
        return $pdf->download($filename . '.pdf');
    }

    /**
     * Export courses to Excel (one course per tab).
     */
    public function exportExcel(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang = 11;

        $kurse = WseminarKurs::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['subject', 'lehrer', 'wahlen.schueler'])
            ->orderBy('subject_id')
            ->orderBy('titel')
            ->get()
            ->filter(function($kurs) {
                return $kurs->wahlen->count() > 0;
            });

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);
        
        // 1. Overview Sheet
        $overviewSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Übersicht');
        $spreadsheet->addSheet($overviewSheet, 0);
        
        $overviewSheet->setCellValue('A1', 'W-Seminar Kursübersicht');
        $overviewSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $overviewSheet->setCellValue('A2', "Schuljahr: $schuljahr, Jahrgang: $jahrgang");
        
        $row = 4;
        $headers = ['Nr.', 'Kurs', 'Lehrer', 'Teilnehmer', 'Erstwahlen', 'Zweitwahlen', 'Drittwahlen'];
        $col = 'A';
        foreach ($headers as $header) {
            $overviewSheet->setCellValue($col . $row, $header);
            $overviewSheet->getStyle($col . $row)->getFont()->setBold(true);
            $col = str_increment($col);
        }
        
        $row++;
        $nr = 1;
        foreach ($kurse as $kurs) {
            $erst = $kurs->wahlen->where('prioritaet_erhalten', 'erstwahl')->count();
            $zweit = $kurs->wahlen->where('prioritaet_erhalten', 'zweitwahl')->count();
            $dritt = $kurs->wahlen->where('prioritaet_erhalten', 'drittwahl')->count();
            
            $overviewSheet->setCellValue('A' . $row, $nr++);
            $overviewSheet->setCellValue('B' . $row, $kurs->subject->name . ' - ' . $kurs->titel);
            $overviewSheet->setCellValue('C' . $row, $kurs->lehrer->name ?? '-');
            $overviewSheet->setCellValue('D' . $row, $kurs->wahlen->count());
            $overviewSheet->setCellValue('E' . $row, $erst);
            $overviewSheet->setCellValue('F' . $row, $zweit);
            $overviewSheet->setCellValue('G' . $row, $dritt);
            $row++;
        }
        
        foreach (range('A', 'G') as $col) $overviewSheet->getColumnDimension($col)->setAutoSize(true);
        
        // 2. Course Sheets
        foreach ($kurse as $index => $kurs) {
            // Excel sheet names max 31 chars
            $sheetName = substr($kurs->subject->name, 0, 30);
            // Ensure unique names if multiple courses have same subject
            $sheetCode = $sheetName; 
            $counter = 1;
            while ($spreadsheet->getSheetByName($sheetCode)) {
                $sheetCode = substr($sheetName, 0, 28) . '_' . $counter++;
            }
            
            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheetCode);
            $spreadsheet->addSheet($sheet);
            
            $sheet->setCellValue('A1', $kurs->subject->name . ' - ' . $kurs->titel);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->setCellValue('A2', 'Lehrer: ' . ($kurs->lehrer->name ?? '-'));
            $sheet->setCellValue('A3', 'Schuljahr: ' . $schuljahr);
            
            $sheet->setCellValue('A5', 'Nr.');
            $sheet->setCellValue('B5', 'Nachname');
            $sheet->setCellValue('C5', 'Vorname');
            $sheet->setCellValue('D5', 'Klasse');
            $sheet->setCellValue('E5', 'Priorität');
            $sheet->getStyle('A5:E5')->getFont()->setBold(true);
            
            $wahlen = $kurs->wahlen->sortBy('schueler.lastname');
            $row = 6;
            $nr = 1;
            
            foreach ($wahlen as $wahl) {
                $sheet->setCellValue('A' . $row, $nr++);
                $sheet->setCellValue('B' . $row, $wahl->schueler->lastname);
                $sheet->setCellValue('C' . $row, $wahl->schueler->firstname);
                $sheet->setCellValue('D' . $row, $wahl->schueler->class_name);
                
                $prio = $this->getPrioritaetLabelLong($wahl->prioritaet_erhalten);
                $sheet->setCellValue('E' . $row, $prio);
                $row++;
            }
            
            foreach (range('A', 'E') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // 3. Unassigned Sheet
        $unassigned = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->whereNull('zuteilung_wseminar_kurs_id')
            ->with(['schueler', 'erstwahl', 'zweitwahl', 'drittwahl'])
            ->get()
            ->sortBy('schueler.lastname');
            
        if ($unassigned->count() > 0) {
            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Nicht zugeteilt');
            $spreadsheet->addSheet($sheet);
            
            $sheet->setCellValue('A1', 'Nicht zugeteilte Schüler');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->setCellValue('A2', 'Anzahl: ' . $unassigned->count());
            
            $headers = ['Nr.', 'Nachname', 'Vorname', 'Klasse', 'Erstwahl', 'Zweitwahl', 'Drittwahl'];
            $col = 'A';
            $row = 4;
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $row, $h);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $col = str_increment($col);
            }
            
            $row++;
            $nr = 1;
            foreach ($unassigned as $wahl) {
                $sheet->setCellValue('A' . $row, $nr++);
                $sheet->setCellValue('B' . $row, $wahl->schueler->lastname);
                $sheet->setCellValue('C' . $row, $wahl->schueler->firstname);
                $sheet->setCellValue('D' . $row, $wahl->schueler->class_name);
                $sheet->setCellValue('E' . $row, $wahl->erstwahl->titel ?? '-');
                $sheet->setCellValue('F' . $row, $wahl->zweitwahl->titel ?? '-');
                $sheet->setCellValue('G' . $row, $wahl->drittwahl->titel ?? '-');
                $row++;
            }
            
            foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $spreadsheet->setActiveSheetIndex(0);
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $filename = $this->getExportFilename($schuljahr);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    // =========================================================================
    // Private Hilfsmethoden
    // =========================================================================

    /**
     * Lädt die W-Seminar-Kurse eines Schuljahres/Jahrgangs mit Relationen,
     * sortiert nach Titel.
     *
     * @return \Illuminate\Support\Collection<int, WseminarKurs>
     */
    private function getKurseSorted(string $schuljahr, int $jahrgang): \Illuminate\Support\Collection
    {
        return WseminarKurs::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['subject', 'lehrer', 'wahlen.schueler'])
            ->orderBy('subject_id')
            ->orderBy('titel')
            ->get();
    }

    /**
     * Setzt Prioritätszähler (erstwahlen_count, zweitwahlen_count, drittwahlen_count)
     * auf jedem Kurs-Objekt in der Collection.
     *
     * @param \Illuminate\Support\Collection<int, WseminarKurs> $kurse
     */
    private function countPrioritaeten(\Illuminate\Support\Collection $kurse): void
    {
        foreach ($kurse as $kurs) {
            $kurs->erstwahlen_count  = $kurs->wahlen->where('prioritaet_erhalten', 'erstwahl')->count();
            $kurs->zweitwahlen_count = $kurs->wahlen->where('prioritaet_erhalten', 'zweitwahl')->count();
            $kurs->drittwahlen_count = $kurs->wahlen->where('prioritaet_erhalten', 'drittwahl')->count();
        }
    }

    /**
     * Generate export filename in format: W-Seminar_Kurse_2026-28_YYYY-MM-DD
     */
    private function getExportFilename($schuljahr)
    {
        $startYear = (int) explode('/', $schuljahr)[0];
        $examYearStart = $startYear + 1; // 2025 -> 2026
        $examYearEnd = $startYear + 3;   // 2025 -> 2028
        $examYearString = $examYearStart . '-' . substr((string) $examYearEnd, -2); // 2026-28
        
        return 'W-Seminar_Kurse_' . $examYearString . '_' . date('Y-m-d');
    }
}
