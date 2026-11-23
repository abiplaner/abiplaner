<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LkWahl;

class LkWahlController extends AbstractWahlController
{
    use \App\Http\Controllers\Concerns\HasPrioritaetLabel;

    // =========================================================================
    // AbstractWahlController – Pflicht-Implementierungen
    // =========================================================================

    protected function makeImportService(): object
    {
        return new \App\Services\LkImportService();
    }

    protected function getIndexRoute(): string
    {
        return 'lk-wahlen.index';
    }

    protected function getJahrgangValidationRule(): string
    {
        return 'required|integer|min:11|max:13';
    }

    protected function getWahlModel(): string
    {
        return LkWahl::class;
    }

    protected function getImportViewName(): string
    {
        return 'lk-wahlen.import';
    }


    /**
     * Display a listing of the LK wahlen.
     */
    public function index(Request $request)
    {
        // Load all wahlen for the school, ordered by most recent first
        $query = LkWahl::query()
            ->with(['schueler', 'erstwahl', 'zweitwahl', 'zugeteilterKurs.subject'])
            ->orderBy('schuljahr', 'desc')
            ->orderBy('jahrgang', 'desc')
            ->orderByRaw('zuteilung_lk_kurs_id IS NULL DESC')
            ->orderBy('schueler_id');

        // Filter by search term
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('schueler', function ($q) use ($search) {
                $q->where('lastname', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('class_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status') && $request->input('status') !== 'alle') {
            $status = $request->input('status');
            if ($status === 'nicht_zugeteilt') {
                $query->whereNull('zuteilung_lk_kurs_id');
            } elseif (in_array($status, ['erstwahl', 'zweitwahl', 'keine'])) {
                $query->where('prioritaet_erhalten', $status);
            }
        }

        $wahlen = $query->paginate(50)->withQueryString();
        
        // Statistiken berechnen
        $statistikService = new \App\Services\LkStatistikService();
        
        // Alle Wahlen für Statistiken laden (ohne Pagination)
        $alleWahlen = LkWahl::query()
            ->with(['erstwahl', 'zweitwahl'])
            ->get();
        
        $wahlStatistik = $statistikService->berechneWahlStatistik($alleWahlen);
        $fachVerteilung = $statistikService->berechneFachVerteilung($alleWahlen);
        $chartData = $statistikService->prepareChartData($fachVerteilung);
        
        // Determine latest cohort for planning button
        $latestWahl = $alleWahlen->sortByDesc('schuljahr')->sortByDesc('jahrgang')->first();
        $latestSchuljahr = $latestWahl ? $latestWahl->schuljahr : $this->getCurrentSchuljahr();
        $latestJahrgang = $latestWahl ? $latestWahl->jahrgang : 12;
        
        return view('lk-wahlen.index', compact(
            'wahlen',
            'wahlStatistik',
            'fachVerteilung',
            'chartData',
            'latestSchuljahr',
            'latestJahrgang'
        ));
    }
    
    /**
     * Show the form for creating a new wahl.
     */
    public function create(Request $request)
    {
        $schuljahre = $this->getAvailableSchuljahre();
        $jahrgaenge = [11, 12, 13];
        $subjects = \App\Models\Subject::whereNotIn('aufgabenfeld', ['sport', 'sonstiges'])
            ->orderBy('name')
            ->get();
            
        // Alle Schüler der Jahrgangsstufen 11 bis 13 zur Auswahl.
        //
        // Diese Abfrage lief nie: Sie filterte über `school_id` und
        // `jahrgang`, und beide Spalten gibt es in `students` nicht. Die
        // Klassenstufe heisst `grade` und ist ein enum('11','12','13') — die
        // Werte sind Zeichenketten, nicht Zahlen. `school_id` braucht es
        // nicht: In einer Datenbank je Schule stehen dort ohnehin nur die
        // eigenen Schüler.
        //
        // Die Maske „Neue LK-Wahl anlegen" endete deshalb in
        // „Unknown column 'school_id' in 'WHERE'" — ein 500er, der niemandem
        // auffiel, weil die Wahlen bisher importiert und nicht von Hand
        // angelegt wurden.
        $students = \App\Models\Student::whereIn('grade', ['11', '12', '13'])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        return view('lk-wahlen.create', compact('schuljahre', 'jahrgaenge', 'subjects', 'students'));
    }

    /**
     * Store a newly created wahl in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang' => 'required|integer|min:11|max:13',
            'student_id' => 'required|exists:students,id',
            'erstwahl_subject_id' => 'required|exists:subjects,id',
            'zweitwahl_subject_id' => 'required|exists:subjects,id|different:erstwahl_subject_id',
        ]);

        // Check if student already has a choice
        $existing = LkWahl::where('schueler_id', $request->input('student_id'))
            ->where('schuljahr', $request->input('schuljahr'))
            ->where('jahrgang', $request->input('jahrgang'))
            ->first();
            
        if ($existing) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['student_id' => 'Dieser Schüler hat bereits eine Wahl für diesen Jahrgang.']);
        }

        LkWahl::create([
            'schueler_id' => $request->input('student_id'),
            'schuljahr' => $request->input('schuljahr'),
            'jahrgang' => $request->input('jahrgang'),
            'erstwahl_subject_id' => $request->input('erstwahl_subject_id'),
            'zweitwahl_subject_id' => $request->input('zweitwahl_subject_id'),
            'prioritaet_erhalten' => 'keine',
        ]);

        return redirect()->route('lk-wahlen.index')->with('success', 'Wahl erfolgreich angelegt.');
    }

    /**
     * Display the specified wahl.
     */
    public function show($id)
    {
        // Not needed for now
        return redirect()->route('lk-wahlen.edit', $id);
    }
    
    /**
     * Show the form for editing the specified wahl.
     */
    public function edit($id)
    {
        $wahl = LkWahl::query()->findOrFail($id);
        
        $subjects = \App\Models\Subject::whereNotIn('aufgabenfeld', ['sport', 'sonstiges'])
            ->orderBy('name')
            ->get();
            
        // Load available LK courses for this cohort
        $kurse = \App\Models\LkKurs::query()
            ->where('schuljahr', $wahl->schuljahr)
            ->where('jahrgang', $wahl->jahrgang)
            ->with(['subject', 'lehrer'])
            ->orderBy('subject_id')
            ->orderBy('kurs_nummer')
            ->get();
            
        return view('lk-wahlen.edit', compact('wahl', 'subjects', 'kurse'));
    }

    /**
     * Update the specified wahl.
     */
    public function update(Request $request, $id)
    {
        $wahl = LkWahl::query()->findOrFail($id);

        $request->validate([
            'erstwahl_subject_id' => 'required|exists:subjects,id',
            'zweitwahl_subject_id' => 'required|exists:subjects,id|different:erstwahl_subject_id',
            'zuteilung_lk_kurs_id' => 'nullable|exists:lk_kurse,id',
        ]);

        $wahl->update([
            'erstwahl_subject_id' => $request->input('erstwahl_subject_id'),
            'zweitwahl_subject_id' => $request->input('zweitwahl_subject_id'),
            'zuteilung_lk_kurs_id' => $request->input('zuteilung_lk_kurs_id') ?: null,
        ]);

        return redirect()->route('lk-wahlen.index')->with('success', 'Wahl erfolgreich aktualisiert.');
    }

    /**
     * Remove the specified wahl.
     */
    public function destroy($id)
    {
        LkWahl::destroy($id);
        return redirect()->route('lk-wahlen.index');
    }

    /**
     * Get students who chose a specific subject (AJAX endpoint)
     */
    public function getSubjectDetails(Request $request)
    {
        $request->validate([
            'subject_name' => 'required|string',
        ]);

        $subjectName = $request->input('subject_name');

        // Find the subject first (on central connection)
        $subject = \App\Models\Subject::where('name', $subjectName)->first();

        if (!$subject) {
            return response()->json([
                'subject' => $subjectName,
                'erstwahlen' => [],
                'zweitwahlen' => [],
                'total_erstwahlen' => 0,
                'total_zweitwahlen' => 0,
            ]);
        }

        // Get all wahlen where this subject is Erst- or Zweitwahl
        $erstwahlen = LkWahl::query()
            ->with(['schueler', 'zugeteilterKurs.subject', 'zweitwahl'])
            ->where('erstwahl_subject_id', $subject->id)
            ->orderBy('schueler_id')
            ->get()
            ->map(function($wahl) {
                return [
                    'id' => $wahl->id,
                    'name' => ($wahl->schueler->lastname ?? '') . ', ' . ($wahl->schueler->firstname ?? ''),
                    'class' => $wahl->schueler->class_name ?? '-',
                    'priority' => 'Erstwahl',
                    'status' => $wahl->prioritaet_erhalten,
                    'assigned' => $wahl->zugeteilterKurs ? $wahl->zugeteilterKurs->subject->name : null,
                    'zweitwahl' => $wahl->zweitwahl->name ?? '-',
                ];
            });

        $zweitwahlen = LkWahl::query()
            ->with(['schueler', 'zugeteilterKurs.subject', 'erstwahl'])
            ->where('zweitwahl_subject_id', $subject->id)
            ->orderBy('schueler_id')
            ->get()
            ->map(function($wahl) {
                return [
                    'id' => $wahl->id,
                    'name' => ($wahl->schueler->lastname ?? '') . ', ' . ($wahl->schueler->firstname ?? ''),
                    'class' => $wahl->schueler->class_name ?? '-',
                    'priority' => 'Zweitwahl',
                    'status' => $wahl->prioritaet_erhalten,
                    'assigned' => $wahl->zugeteilterKurs ? $wahl->zugeteilterKurs->subject->name : null,
                    'erstwahl' => $wahl->erstwahl->name ?? '-',
                ];
            });

        return response()->json([
            'subject' => $subjectName,
            'erstwahlen' => $erstwahlen,
            'zweitwahlen' => $zweitwahlen,
            'total_erstwahlen' => $erstwahlen->count(),
            'total_zweitwahlen' => $zweitwahlen->count(),
        ]);
    }

    /**
     * Export wahlen to Excel.
     */
    public function export(Request $request)
    {
        // Load wahlen
        $wahlen = LkWahl::query()
            ->with(['schueler', 'erstwahl', 'zweitwahl', 'zugeteilterKurs.subject'])
            ->orderBy('schuljahr', 'desc')
            ->orderBy('jahrgang', 'desc')
            ->orderBy('schueler_id')
            ->get();
        
        // Create spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $sheet->setCellValue('A1', 'Vorname');
        $sheet->setCellValue('B1', 'Nachname');
        $sheet->setCellValue('C1', 'Erstwahl');
        $sheet->setCellValue('D1', 'Zweitwahl');
        $sheet->setCellValue('E1', 'Zugeteilt');
        $sheet->setCellValue('F1', 'Priorität');
        
        // Style header
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        
        // Data
        $row = 2;
        foreach ($wahlen as $wahl) {
            $sheet->setCellValue('A' . $row, $wahl->schueler->firstname ?? '');
            $sheet->setCellValue('B' . $row, $wahl->schueler->lastname ?? '');
            $sheet->setCellValue('C' . $row, $wahl->erstwahl->name ?? '');
            $sheet->setCellValue('D' . $row, $wahl->zweitwahl->name ?? '');
            $sheet->setCellValue('E' . $row, $wahl->zugeteilterKurs ? $wahl->zugeteilterKurs->subject->name : '-');
            $sheet->setCellValue('F' . $row, $wahl->prioritaet_erhalten === 'keine' && $wahl->zugeteilterKurs
                ? 'Keine Wahl'
                : $this->getPrioritaetLabelLong($wahl->prioritaet_erhalten));
            
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Generate filename
        $filename = sprintf('lk_wahlen_alle_%s.xlsx', 
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
