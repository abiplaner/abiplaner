<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WseminarWahl;
use App\Services\WseminarZuteilungsService;
use App\Http\Controllers\Concerns\HasSchuljahr;

class WseminarZuteilungController extends Controller
{
    use HasSchuljahr;
    /**
     * Show the zuteilung planning form.
     */
    public function planen(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang = 11; // W-Seminare nur in Q11
        
        // Load wahlen to check if any exist
        $wahlenCount = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->count();
        
        if ($wahlenCount === 0) {
            return redirect()->route('wseminar-wahlen.index')
                ->with('error', 'Keine Wahlen vorhanden. Bitte importieren Sie zuerst Wahlen.');
        }
        
        // Analyze wahlen for statistics
        $zuteilungsService = new WseminarZuteilungsService();
        $analyse = $zuteilungsService->analyseWahlen(
            auth()->user()->school_id,
            $schuljahr,
            $jahrgang
        );
        
        // Calculate min courses
        $gesamtSchueler = $analyse['gesamt_schueler_jahrgang'] ?? 0;
        $minCourses = $gesamtSchueler > 0 ? ceil($gesamtSchueler / 15) : 0;
        
        // Available filter values
        $schuljahre = $this->getAvailableSchuljahre();
        
        return view('wseminar-kurse.planen', compact(
            'schuljahr',
            'jahrgang',
            'wahlenCount',
            'analyse',
            'schuljahre',
            'minCourses'
        ));
    }
    
    /**
     * Calculate zuteilung (AJAX).
     */
    public function berechne(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang' => 'required|integer|in:11',
            'strategie' => 'required|in:optimiert,viele_erstwahlen',
            'target_course_count' => 'nullable|integer|min:1',
        ]);
        
        try {
            $zuteilungsService = new WseminarZuteilungsService();
            
            // Phase 1: Analyse
            $analyse = $zuteilungsService->analyseWahlen(
                auth()->user()->school_id,
                $request->input('schuljahr'),
                $request->input('jahrgang')
            );
            
            // Phase 2: Zuteilung (Vorschau)
            $ergebnis = $zuteilungsService->teileZu(
                auth()->user()->school_id,
                $request->input('schuljahr'),
                $request->input('jahrgang'),
                $request->input('strategie'),
                $request->input('target_course_count')
            );
            
            // Get seminar details with actual student counts
            $seminare = \App\Models\WseminarKurs::query()
                ->where('schuljahr', $request->input('schuljahr'))
                ->where('jahrgang', $request->input('jahrgang'))
                ->with('subject')
                ->get()
                ->keyBy('id');
            
            $seminareDetails = [];
            
            
            // Calculate distribution within the assignment (how many of each priority assigned)
            $verteilung = [];
            foreach ($seminare as $s) {
                $verteilung[$s->id] = [
                    'erstwahl' => 0,
                    'zweitwahl' => 0,
                    'drittwahl' => 0
                ];
            }

            foreach ($ergebnis['zuteilungen'] as $zuteilung) {
                $kursId = $zuteilung['wseminar_kurs_id'];
                $prioritaet = $zuteilung['prioritaet']; // 'erstwahl', 'zweitwahl', 'drittwahl'
                
                if (isset($verteilung[$kursId]) && isset($verteilung[$kursId][$prioritaet])) {
                    $verteilung[$kursId][$prioritaet]++;
                }
            }

            foreach ($seminare as $seminar) {
                $belegung = $ergebnis['seminar_belegung'][$seminar->id] ?? 0;
                $stat = $verteilung[$seminar->id];
                $isActive = in_array($seminar->id, $ergebnis['active_course_ids'] ?? []);

                $seminareDetails[] = [
                    'id' => $seminar->id,
                    'titel' => $seminar->titel,
                    'subject_name' => $seminar->subject->name ?? 'Unbekannt',
                    'max_teilnehmer' => $seminar->max_teilnehmer,
                    'zugeteilte_schueler' => $belegung,
                    'freie_plaetze' => $seminar->max_teilnehmer - $belegung,
                    'erstwahlen' => $stat['erstwahl'],
                    'zweitwahlen' => $stat['zweitwahl'],
                    'drittwahlen' => $stat['drittwahl'],
                    'is_active' => $isActive,
                ];
            }
            
            // Sort by student count (descending)
            usort($seminareDetails, function($a, $b) {
                return $b['zugeteilte_schueler'] <=> $a['zugeteilte_schueler'];
            });

            return response()->json([
                'success' => true,
                'seminare' => $seminareDetails,
                'statistik' => $ergebnis['statistik'],
                'anzahl_schueler' => $analyse['anzahl_schueler'],
                'gesamt_schueler' => $analyse['gesamt_schueler_jahrgang'],
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler bei der Zuteilung: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply zuteilung (save to database).
     */
    public function apply(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang' => 'required|integer|in:11',
            'strategie' => 'required|in:optimiert,viele_erstwahlen',
            'target_course_count' => 'nullable|integer|min:1',
        ]);
        
        try {
            $zuteilungsService = new WseminarZuteilungsService();
            
            // Calculate zuteilung
            $ergebnis = $zuteilungsService->teileZu(
                auth()->user()->school_id,
                $request->input('schuljahr'),
                $request->input('jahrgang'),
                $request->input('strategie'),
                $request->input('target_course_count')
            );
            
            // Save to database
            $gespeichert = $zuteilungsService->speichereZuteilung($ergebnis['zuteilungen']);
            
            return response()->json([
                'success' => true,
                'message' => "Zuteilung erfolgreich gespeichert. {$gespeichert} Schüler zugeteilt.",
                'statistik' => $ergebnis['statistik'],
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Speichern: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Export W-Seminar Wahlübersicht to PDF.
     */
    public function exportPdf(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang = 11;
        
        $zuteilungsService = new WseminarZuteilungsService();
        $analyse = $zuteilungsService->analyseWahlen(
            auth()->user()->school_id,
            $schuljahr,
            $jahrgang
        );
        
        $wahlenCount = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->count();
        
        $html = view('wseminar-kurse.planen-pdf', compact(
            'analyse',
            'schuljahr',
            'jahrgang',
            'wahlenCount'
        ))->render();
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        
        $filename = sprintf('wseminar_wahluebersicht_%s_Q%d_%s.pdf',
            str_replace('/', '-', $schuljahr),
            $jahrgang - 10,
            date('Y-m-d')
        );
        
        return $pdf->download($filename);
    }
    
    /**
     * Export W-Seminar Wahlübersicht to Excel.
     */
    public function exportExcel(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang = 11;
        
        $zuteilungsService = new WseminarZuteilungsService();
        $analyse = $zuteilungsService->analyseWahlen(
            auth()->user()->school_id,
            $schuljahr,
            $jahrgang
        );
        
        $wahlenCount = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->count();
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Wahlübersicht');
        
        // Header info
        $sheet->setCellValue('A1', 'W-Seminar Wahlübersicht');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        $sheet->setCellValue('A2', 'Schuljahr: ' . $schuljahr);
        $sheet->setCellValue('A3', 'Jahrgang: Q' . ($jahrgang - 10) . ' (' . $jahrgang . ')');
        
        // Wahlbeteiligung
        $gesamtSchueler = $analyse['gesamt_schueler_jahrgang'] ?? 0;
        $schuelerMitWahlen = $analyse['schueler_mit_wahlen'] ?? 0;
        
        $sheet->setCellValue('A4', 'Schüler im Jahrgang: ' . $gesamtSchueler);
        $sheet->setCellValue('A5', 'Schüler mit Wahlen: ' . $schuelerMitWahlen);
        
        if ($gesamtSchueler > 0) {
            $prozent = round(($schuelerMitWahlen / $gesamtSchueler) * 100, 1);
            $sheet->setCellValue('A6', 'Wahlbeteiligung: ' . $prozent . '%');
            
            if ($prozent < 100) {
                $fehlend = $gesamtSchueler - $schuelerMitWahlen;
                $sheet->setCellValue('B6', '(' . $fehlend . ' Schüler fehlen noch)');
                $sheet->getStyle('B6')->getFont()->getColor()->setARGB('FFDC2626'); // Red
            }
        }
        
        // Table Header
        $row = 8;
        $headers = ['Nr.', 'W-Seminar', 'Erstwahlen', 'Zweitwahlen', 'Drittwahlen', 'Gesamt'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            $col = str_increment($col);
        }
        
        // Data rows
        $row++;
        $nr = 1;
        foreach ($analyse['seminar_statistik'] as $seminar) {
            // Markiere Seminare mit < 10 Gesamtwahlen
            $isLowDemand = $seminar['gesamt'] < 10;
            
            $sheet->setCellValue('A' . $row, $nr++);
            $sheet->setCellValue('B' . $row, $seminar['subject_name'] . ' - ' . $seminar['titel']);
            $sheet->setCellValue('C' . $row, $seminar['erstwahlen']);
            $sheet->setCellValue('D' . $row, $seminar['zweitwahlen']);
            $sheet->setCellValue('E' . $row, $seminar['drittwahlen']);
            $sheet->setCellValue('F' . $row, $seminar['gesamt']);
            
            // Center align numbers
            foreach (['A', 'C', 'D', 'E', 'F'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
            
            // Markiere Zeilen mit zu wenig Nachfrage
            if ($isLowDemand) {
                $sheet->getStyle('A' . $row . ':F' . $row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFEE2E2'); // Red-100
                $sheet->getStyle('F' . $row)->getFont()
                    ->setBold(true)
                    ->getColor()->setARGB('FFDC2626'); // Red-600
                    
                // Füge Hinweis in Spalte B hinzu
                $currentValue = $sheet->getCell('B' . $row)->getValue();
                $sheet->setCellValue('B' . $row, $currentValue . ' [⚠ Zu wenig Nachfrage]');
                $sheet->getStyle('B' . $row)->getFont()->getColor()->setARGB('FFDC2626');
            }
            
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Add borders to table
        $tableRange = 'A8:F' . ($row - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Generate filename
        $filename = sprintf('wseminar_wahluebersicht_%s_Q%d_%s.xlsx',
            str_replace('/', '-', $schuljahr),
            $jahrgang - 10,
            date('Y-m-d')
        );
        
        // Create writer and download
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
}
