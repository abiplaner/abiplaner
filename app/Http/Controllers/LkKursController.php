<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LkKurs;
use App\Http\Controllers\Concerns\HasSchuljahr;
use App\Http\Controllers\Concerns\HasPrioritaetLabel;

class LkKursController extends Controller
{
    use HasSchuljahr;
    use HasPrioritaetLabel;


    /**
     * Display a listing of the kurse (after zuteilung).
     */
    public function index(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang  = $request->input('jahrgang', 12);

        $kurse = $this->getKurseSorted($schuljahr, $jahrgang);

        // Statistiken berechnen
        $statistikService = new \App\Services\LkStatistikService();

        $wahlen = \App\Models\LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['erstwahl', 'zweitwahl', 'zugeteilterKurs'])
            ->get();

        $wahlStatistik     = $statistikService->berechneWahlStatistik($wahlen);
        $kursStatistik     = $statistikService->berechneKursStatistik($kurse);
        $schuljahre        = $this->getAvailableSchuljahre();
        $jahrgaenge        = [11, 12, 13];
        $unassignedWahlen  = $wahlen->whereNull('zuteilung_lk_kurs_id');

        return view('lk-kurse.index', compact(
            'kurse', 'wahlStatistik', 'kursStatistik',
            'schuljahr', 'jahrgang', 'schuljahre', 'jahrgaenge', 'unassignedWahlen'
        ));
    }
    
    /**
     * Display the specified kurs.
     */
    public function show($id)
    {
        // Redirect to edit view as it serves as the detailed view with management capabilities
        return redirect()->route('lk-kurse.edit', $id);
    }

    /**
     * Show the form for editing the specified kurs.
     */
    public function edit($id)
    {
        $kurs = LkKurs::query()
            ->with(['subject', 'lehrer', 'wahlen.schueler'])
            ->findOrFail($id);
            
        // Filter teachers by subject (including cross-sport availability)
        if ($kurs->subject->aufgabenfeld === 'sport' || str_contains($kurs->subject->name, 'Sport')) {
             // Get Sport Subject IDs from Central DB
             $sportSubjectIds = \App\Models\Subject::where('aufgabenfeld', 'sport')
                ->orWhere('name', 'like', '%Sport%')
                ->pluck('id')
                ->toArray();
                
             // Query Tenant DB: Find teachers linked to these subjects in pivot table
             $availableTeachers = \App\Models\Teacher::whereIn('id', function($q) use ($sportSubjectIds) {
                $q->select('teacher_id')
                  ->from('teacher_subject')
                  ->whereIn('subject_id', $sportSubjectIds);
             })->orderBy('lastname')->get();

        } else {
             // Query Tenant DB: Find teachers linked to this specific subject
             $availableTeachers = \App\Models\Teacher::whereIn('id', function($q) use ($kurs) {
                $q->select('teacher_id')
                  ->from('teacher_subject')
                  ->where('subject_id', $kurs->subject_id);
             })->orderBy('lastname')->get();
        }
            
        // Load available students for adding (same cohort, ONLY those not assigned to ANY course)
        $availableStudents = \App\Models\LkWahl::query()
            ->where('schuljahr', $kurs->schuljahr)
            ->where('jahrgang', $kurs->jahrgang)
            ->whereNull('zuteilung_lk_kurs_id')
            ->with(['schueler', 'erstwahl', 'zweitwahl'])
            ->get()
            ->sortBy('schueler.lastname');
            
        return view('lk-kurse.edit', compact('kurs', 'availableStudents', 'availableTeachers'));
    }

    /**
     * Add a student to the course.
     */
    public function addStudent(Request $request, $id)
    {
        $kurs = LkKurs::query()->findOrFail($id);
        
        $request->validate([
            'wahl_id' => 'required|exists:lk_wahlen,id',
        ]);
        
        $wahl = \App\Models\LkWahl::query()->findOrFail($request->input('wahl_id'));
        
        // Determine priority
        $prioritaet = $this->determinePrioritaet($wahl, $kurs);
        
        $wahl->update([
            'zuteilung_lk_kurs_id' => $kurs->id,
            'prioritaet_erhalten' => $prioritaet,
        ]);
        
        return redirect()->route('lk-kurse.edit', $id)
            ->with('success', 'Schüler erfolgreich hinzugefügt.');
    }

    /**
     * Remove a student from the course.
     */
    public function removeStudent(Request $request, $id)
    {
        $kurs = LkKurs::query()->findOrFail($id);
        
        $request->validate([
            'wahl_id' => 'required|exists:lk_wahlen,id',
        ]);
        
        $wahl = \App\Models\LkWahl::query()->findOrFail($request->input('wahl_id'));
        
        if ($wahl->zuteilung_lk_kurs_id != $kurs->id) {
            return redirect()->back()->with('error', 'Dieser Schüler ist diesem Kurs nicht zugewiesen.');
        }
        
        $wahl->update([
            'zuteilung_lk_kurs_id' => null,
            'prioritaet_erhalten' => 'keine',
        ]);
        
        return redirect()->route('lk-kurse.edit', $id)
            ->with('success', 'Schüler erfolgreich aus dem Kurs entfernt.');
    }

    /**
     * Update the specified kurs (teacher, notizen).
     */
    public function update(Request $request, $id)
    {
        // Keine Schulprüfung mehr: Der Kurs steht in der Datenbank dieser
        // Schule, ein fremder ist über findOrFail() gar nicht erreichbar.
        $kurs = LkKurs::findOrFail($id);

        $request->validate([
            'lehrer_id' => 'nullable|exists:teachers,id',
            'notizen' => 'nullable|string|max:1000',
        ]);
        
        $kurs->update([
            'lehrer_id' => $request->input('lehrer_id'),
            'notizen' => $request->input('notizen'),
        ]);
        
        return redirect()->route('lk-kurse.edit', $id)
            ->with('success', 'Kurs erfolgreich aktualisiert.');
    }

    /**
     * Remove the specified kurs.
     */
    public function destroy($id)
    {
        // TODO: Implement in Phase 6
        LkKurs::destroy($id);
        return redirect()->route('lk-kurse.index');
    }

    /**
     * Reset the zuteilung for a schuljahr/jahrgang.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang' => 'required|integer|min:11|max:13',
        ]);
        
        $zuteilungsService = new \App\Services\LkZuteilungsService();
        
        $result = $zuteilungsService->resetZuteilung(
            auth()->user()->school_id,
            $request->input('schuljahr'),
            $request->input('jahrgang')
        );
        
        if ($result['success']) {
            $message = sprintf(
                'Zuteilung zurückgesetzt: %d Wahlen zurückgesetzt, %d Kurse gelöscht.',
                $result['wahlen_zurueckgesetzt'],
                $result['kurse_geloescht']
            );
            
            return redirect()->route('lk-wahlen.index', [
                'schuljahr' => $request->input('schuljahr'),
                'jahrgang' => $request->input('jahrgang')
            ])->with('success', $message);
        } else {
            return redirect()->back()
                ->withErrors(['error' => 'Reset fehlgeschlagen: ' . $result['error']]);
        }
    }
    
    /**
     * Update Zuteilung via AJAX (Drag & Drop).
     */
    public function updateZuteilung(Request $request, $id)
    {
        // Diese Prüfung war nie erfüllbar und ist mit der Spalte entfallen.
        //
        // `lk_kurse.school_id` war `bigint` und trug deshalb **42**; zentral
        // steht die Schulnummer als `varchar(4)`, also **'0042'**. Der
        // **strenge** Vergleich sieht zwei verschiedene Zeichenketten
        // ('42' !== '0042') und traf damit immer zu: Drag & Drop in der
        // LK-Zuteilung endete stets in „Keine Berechtigung".
        //
        // Die Prüfung eine Methode weiter oben (`update()`) benutzte `!=` —
        // lose verglichen sind '42' und '0042' numerisch gleich, dort fiel es
        // deshalb nicht auf. Derselbe Datenfehler, zwei verschiedene
        // Auswirkungen, je nach Vergleichsoperator.
        //
        // Es ist die Wurzel der 287 unsichtbaren Datensätze: Eine
        // Ganzzahlspalte kann die führende Null einer Schulnummer nicht halten.
        $kurs = LkKurs::findOrFail($id);

        $request->validate([
            'wahl_ids' => 'required|array',
            'wahl_ids.*' => 'integer|exists:lk_wahlen,id',
        ]);
        
        try {
            \DB::beginTransaction();
            
            $wahlIds = $request->input('wahl_ids');
            
            // Reset alte Zuteilungen für diesen Kurs
            \App\Models\LkWahl::where('zuteilung_lk_kurs_id', $kurs->id)
                ->update([
                    'zuteilung_lk_kurs_id' => null,
                    'prioritaet_erhalten' => 'keine',
                ]);
            
            // Neue Zuteilungen setzen
            foreach ($wahlIds as $wahlId) {
                $wahl = \App\Models\LkWahl::find($wahlId);

                // Auch hier stand ein strenger Vergleich ('42' === '0042'),
                // nur andersherum: Er traf **nie** zu. Erreicht wurde die
                // Stelle ohnehin nicht — der 403 oben kam zuerst.
                if ($wahl) {
                    $wahl->update([
                        'zuteilung_lk_kurs_id' => $kurs->id,
                        'prioritaet_erhalten'  => $this->determinePrioritaet($wahl, $kurs),
                    ]);
                }
            }
            
            \DB::commit();
            
            return response()->json([
                'success' => true,
                'anzahl' => count($wahlIds),
            ]);
            
        } catch (\Exception $e) {
            \DB::rollBack();
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export kurse to PDF.
     */
    public function exportPdf(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang  = $request->input('jahrgang', 12);

        $kurse = $this->getKurseSorted($schuljahr, $jahrgang);

        $statistikService = new \App\Services\LkStatistikService();
        $wahlen = \App\Models\LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['erstwahl', 'zweitwahl'])
            ->get();

        $wahlStatistik    = $statistikService->berechneWahlStatistik($wahlen);
        $unassignedWahlen = $wahlen->whereNull('zuteilung_lk_kurs_id');

        $html = view('lk-kurse.pdf', compact('kurse', 'wahlStatistik', 'schuljahr', 'jahrgang', 'unassignedWahlen'))->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        $filename = sprintf('lk_kurse_%s_Q%d_%s.pdf',
            str_replace('/', '-', $schuljahr),
            $jahrgang - 10,
            date('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Export kurse to Excel.
     */
    public function exportExcel(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang  = $request->input('jahrgang', 12);

        $kurse = $this->getKurseSorted($schuljahr, $jahrgang);
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // ---------------------------------------------------------
        // Blatt 1: Übersicht
        // ---------------------------------------------------------
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Übersicht');
        
        // Header info
        $sheet->setCellValue('A1', 'Leistungsfach-Kurse Übersicht');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        $sheet->setCellValue('A2', 'Schuljahr: ' . $schuljahr);
        $sheet->setCellValue('A3', 'Jahrgang: Q' . ($jahrgang - 10) . ' (' . $jahrgang . ')');
        
        $internalCount = $kurse->filter(fn($k) => !$k->ist_extern)->count();
        $externalCount = $kurse->filter(fn($k) => $k->ist_extern)->count();
        $countText = $internalCount;
        if ($externalCount > 0) {
            $countText .= ' + ' . $externalCount . ' (extern)';
        }
        
        $sheet->setCellValue('A4', 'Anzahl Kurse: ' . $countText);
        
        // Table Header
        $row = 6;
        $headers = ['Fach', 'Kurs', 'Lehrer', 'Extern', 'Schüler', 'Bemerkung'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB'); // Gray-200
            $col = str_increment($col);
        }
        
        // Table Data
        $row++;
        foreach ($kurse as $kurs) {
            $sheet->setCellValue('A' . $row, $kurs->subject->name ?? '-');
            $sheet->setCellValue('B' . $row, $kurs->kurs_nummer);
            $sheet->setCellValue('C' . $row, $kurs->lehrer ? ($kurs->lehrer->lastname ?? '-') . ', ' . ($kurs->lehrer->firstname ?? '-') : '-');
            $sheet->setCellValue('D' . $row, $kurs->ist_extern ? 'Ja' : 'Nein');
            $sheet->setCellValue('E' . $row, $kurs->wahlen->count());
            $sheet->setCellValue('F' . $row, $kurs->notizen);
            $row++;
        }
        
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // ---------------------------------------------------------
        // Nicht zugeteilte Schüler
        // ---------------------------------------------------------
        $wahlen = \App\Models\LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->whereNull('zuteilung_lk_kurs_id')
            ->with(['schueler', 'erstwahl', 'zweitwahl'])
            ->get()
            ->sortBy('schueler.lastname');

        if ($wahlen->count() > 0) {
            $row += 2;
            $sheet->setCellValue('A' . $row, 'Nicht zugeteilte Schüler');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            
            $row++;
            $headers = ['Klasse', 'Name', 'Vorname', 'Erstwahl', 'Zweitwahl'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $row, $header);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $sheet->getStyle($col . $row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE5E7EB');
                $col = str_increment($col);
            }
            
            $row++;
            foreach ($wahlen as $wahl) {
                $sheet->setCellValue('A' . $row, $wahl->schueler->class_name ?? '-');
                $sheet->setCellValue('B' . $row, $wahl->schueler->lastname ?? '-');
                $sheet->setCellValue('C' . $row, $wahl->schueler->firstname ?? '-');
                $sheet->setCellValue('D' . $row, $wahl->erstwahl->name ?? '-');
                $sheet->setCellValue('E' . $row, $wahl->zweitwahl->name ?? '-');
                $row++;
            }
        }
        
        // ---------------------------------------------------------
        // Weitere Blätter: Pro Kurs ein Tab
        // ---------------------------------------------------------
        foreach ($kurse as $kurs) {
            $newSheet = $spreadsheet->createSheet();
            
            // Sheet Title (Sanitize and truncate to 31 chars)
            $sheetTitle = ($kurs->subject->name ?? 'Unbekannt') . ' ' . $kurs->kurs_nummer;
            $sheetTitle = substr(preg_replace('/[\*\:\/\?\[\]\\\]/', '', $sheetTitle), 0, 31);
            // Ensure unique title if multiple same names exist (unlikely with Subject+Nr, but safety)
            // PhpSpreadsheet handles duplicates by throwing exc, handling simplistic:
            try {
                $newSheet->setTitle($sheetTitle);
            } catch (\Exception $e) {
                // Fallback if title exists or invalid
                $newSheet->setTitle(substr($sheetTitle, 0, 28) . rand(10,99));
            }
            
            // Header Info
            $newSheet->setCellValue('A1', ($kurs->subject->name ?? '-') . ' (Kurs ' . $kurs->kurs_nummer . ')');
            $newSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            
            if ($kurs->ist_extern) {
                $newSheet->setCellValue('A2', 'Externer Kurs');
                $newSheet->getStyle('A2')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED));
            }
            
            if ($kurs->lehrer) {
                $lehrerName = ($kurs->lehrer->lastname ?? '-') . ', ' . ($kurs->lehrer->firstname ?? '-');
                $newSheet->setCellValue('A3', 'Lehrer: ' . $lehrerName);
            } else {
                $newSheet->setCellValue('A3', 'Lehrer: -');
            }
            
            // Student Table Header
            $headers = ['Nr.', 'Klasse', 'Nachname', 'Vorname', 'Priorität'];
            $tableRow = 5;
            $colAlpha = 'A';
            foreach ($headers as $header) {
                $newSheet->setCellValue($colAlpha . $tableRow, $header);
                $newSheet->getStyle($colAlpha . $tableRow)->getFont()->setBold(true);
                $newSheet->getStyle($colAlpha . $tableRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE5E7EB');
                $colAlpha = str_increment($colAlpha);
            }
            
            // Student Data
            $wahlen = $kurs->wahlen->sortBy(fn($w) => $w->schueler->lastname ?? '');
            $tableRow++;
            $nr = 1;
            
            foreach ($wahlen as $wahl) {
                $prioText = $this->getPrioritaetLabel($wahl->prioritaet_erhalten);
                
                $newSheet->setCellValue('A' . $tableRow, $nr++);
                $newSheet->setCellValue('B' . $tableRow, $wahl->schueler->class_name ?? '-');
                $newSheet->setCellValue('C' . $tableRow, $wahl->schueler->lastname ?? '-');
                $newSheet->setCellValue('D' . $tableRow, $wahl->schueler->firstname ?? '-');
                $newSheet->setCellValue('E' . $tableRow, $prioText);
                $tableRow++;
            }
            
            foreach (range('A', 'E') as $col) {
                $newSheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        
        // Set active sheet index to the first sheet
        $spreadsheet->setActiveSheetIndex(0);
        
        // Generate filename
        $filename = sprintf('lk_kurse_%s_Q%d_%s.xlsx',
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

    // =========================================================================
    // Private Hilfsmethoden
    // =========================================================================

    /**
     * Lädt die LK-Kurse eines Schuljahres/Jahrgangs mit Relationen,
     * sortiert nach Teilnehmerzahl (absteigend).
     *
     * @return \Illuminate\Support\Collection<int, LkKurs>
     */
    private function getKurseSorted(string $schuljahr, int $jahrgang): \Illuminate\Support\Collection
    {
        return LkKurs::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['subject', 'lehrer', 'wahlen.schueler'])
            ->orderBy('subject_id')
            ->orderBy('kurs_nummer')
            ->get()
            ->sortByDesc(fn($k) => $k->wahlen->count());
    }

    /**
     * Ermittelt die Priorität einer Wahl bezüglich eines Kurses.
     * Gibt 'erstwahl', 'zweitwahl' oder 'keine' zurück.
     */
    private function determinePrioritaet(\App\Models\LkWahl $wahl, LkKurs $kurs): string
    {
        if ($wahl->erstwahl_subject_id == $kurs->subject_id) {
            return 'erstwahl';
        }

        if ($wahl->zweitwahl_subject_id == $kurs->subject_id) {
            return 'zweitwahl';
        }

        return 'keine';
    }
}
