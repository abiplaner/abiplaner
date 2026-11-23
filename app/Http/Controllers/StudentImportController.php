<?php

namespace App\Http\Controllers;

use App\Services\CurrentExamPeriodService;
use App\Models\Teacher;
use App\Services\ActivityLogger;
use App\Services\StudentCsvImportService;
use Illuminate\Http\Request;

/**
 * Steuert den Schueler-CSV-Import (Vorschau + finaler Import).
 *
 * Die gesamte fachliche Logik (Parsen, Validieren, Persistieren) liegt im
 * StudentCsvImportService. Dieser Controller ist rein fuer HTTP-Handling.
 *
 * Routen:
 *   GET  /import              → showForm()
 *   POST /import/preview      → preview()
 *   POST /import              → import()
 */
class StudentImportController extends Controller
{
    public function __construct(
        private StudentCsvImportService $importService
    ) {}

    /**
     * Zeigt das Upload-Formular.
     */
    public function showForm()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Pruefungszeitraum gefunden.');
        }

        return view('import.form', compact('examPeriod'));
    }

    /**
     * Verarbeitet den Upload, erzeugt eine interaktive Vorschau.
     */
    public function preview(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Pruefungszeitraum gefunden.');
        }

        $request->validate([
            'file'      => 'required|file|mimes:csv,txt',
            'delimiter' => 'required|in:;,\t',
            'grade'     => 'required|in:11,12,13',
        ]);

        try {
            $preview = $this->importService->parseCSVPreview(
                $request->file('file')->getRealPath(),
                $request->input('delimiter', ';'),
                $examPeriod
            );

            $requestedGrade = (string) $request->input('grade', '13');
            $detectedGrade = $this->importService->detectGradeFromPreview($preview);
            $gradeAutoDetected = $detectedGrade !== null && $detectedGrade !== $requestedGrade;
            $grade = $detectedGrade ?? $requestedGrade;

            $tempPath = $this->storeTempFile($request->file('file'));

            $availableTeachers = Teacher::active()->orderByRaw("CASE WHEN shortcode = 'EXTERN' THEN 0 ELSE 1 END")
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(['shortcode', 'firstname', 'lastname'])
                ->toArray();

            return view('import.preview-interactive', [
                'examPeriod'        => $examPeriod,
                'preview'           => $preview,
                'tempPath'          => $tempPath,
                'delimiter'         => $request->input('delimiter', ';'),
                'grade'             => $grade,
                'gradeAutoDetected' => $gradeAutoDetected,
                'availableTeachers' => $availableTeachers,
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Parsen: ' . $e->getMessage()]);
        }
    }

    /**
     * Berechnet Diff zwischen CSV und DB und zeigt nur die Änderungen.
     */
    public function diffPreview(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Pruefungszeitraum gefunden.');
        }

        $request->validate([
            'file'      => 'required|file|mimes:csv,txt',
            'delimiter' => 'required|in:;,\t',
            'grade'     => 'required|in:11,12,13',
        ]);

        try {
            $tempPath = $this->storeTempFile($request->file('file'));

            $filePath = storage_path('app/' . $tempPath);

            // Wie in preview(): Stufe aus den Kurscodes erkennen. Ohne diese
            // Pruefung landete am 22.10.2026 die Q12 (Kurse 1xx) als Stufe 13
            // im Abitur 2027, weil der Abgleich stumm die Vorgabe '13' nahm.
            $requestedGrade = (string) $request->input('grade', '13');
            $detectedGrade = $this->importService->detectGradeFromPreview(
                $this->importService->parseCSVPreview($filePath, $request->input('delimiter', ';'), $examPeriod)
            );
            $gradeAutoDetected = $detectedGrade !== null && $detectedGrade !== $requestedGrade;
            $grade = $detectedGrade ?? $requestedGrade;

            $availableTeachers = Teacher::active()->orderByRaw("CASE WHEN shortcode = 'EXTERN' THEN 0 ELSE 1 END")
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(['shortcode', 'firstname', 'lastname'])
                ->toArray();

            $diff = $this->importService->computeDiff(
                $filePath,
                $request->input('delimiter', ';'),
                $examPeriod,
                $grade,
            );

            return view('import.diff-preview', [
                'examPeriod'        => $examPeriod,
                'diff'              => $diff,
                'tempPath'          => $tempPath,
                'delimiter'         => $request->input('delimiter', ';'),
                'grade'             => $grade,
                'gradeAutoDetected' => $gradeAutoDetected,
                'availableTeachers' => $availableTeachers,
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Vergleich: ' . $e->getMessage()]);
        }
    }

    /**
     * Fuehrt den finalen Import durch.
     */
    public function import(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Pruefungszeitraum gefunden.');
        }

        \Log::info('=== IMPORT GESTARTET ===', [
            'exam_period_id' => $examPeriod->id,
            'user_id'        => auth()->id(),
            'request_data'   => $request->all(),
        ]);

        $request->validate([
            'temp_path'     => 'required|string',
            'delimiter'     => 'required|in:;,\t',
            'clear_existing'=> 'sometimes|boolean',
            'grade'         => 'required|in:11,12,13',
            'corrections'   => 'sometimes|string',
        ]);

        $filePath      = storage_path('app/' . $request->input('temp_path'));
        $delimiter     = $request->input('delimiter', ';');
        $grade         = $request->input('grade', '12');
        $clearExisting = $request->has('clear_existing') && $request->input('clear_existing');
        $corrections   = json_decode($request->input('corrections', '{}'), true) ?? [];

        if (!file_exists($filePath)) {
            \Log::error('Importdatei nicht gefunden!', ['path' => $filePath]);
            return back()->withErrors(['error' => "Temporaere Datei nicht gefunden. Bitte CSV erneut hochladen."]);
        }

        try {
            if ($clearExisting) {
                $this->importService->clearExistingData($examPeriod, $grade);
            }

            $this->importService->processCSV(
                $filePath,
                $delimiter,
                $examPeriod,
                $corrections,
                $grade,
                auth()->user()->school_id
            );

            @unlink($filePath);

            $stats = $this->importService->getStats();

            ActivityLogger::log(
                'students_imported',
                'ExamPeriod',
                $examPeriod->id,
                "CSV-Import: {$stats['students_created']} Schueler, {$stats['exams_created']} Pruefungen, {$stats['courses_created']} neue Kurse"
            );

            \Log::info('Import erfolgreich!', ['stats' => $stats]);

            return redirect()->route('students.index')
                ->with('success', $this->importService->buildSuccessMessage());

        } catch (\Exception $e) {
            \Log::error('Import-Fehler', [
                'exception'   => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
                'exam_period' => $examPeriod->id,
            ]);
            return back()->withErrors(['error' => 'Fehler beim Import: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // Private Hilfsmethoden
    // =========================================================================

    /**
     * Speichert die hochgeladene Datei in storage/app/temp und gibt den relativen Pfad zurueck.
     */
    private function storeTempFile(\Illuminate\Http\UploadedFile $file): string
    {
        $fileName = 'import_' . time() . '_' . uniqid() . '.csv';
        $tempDir  = storage_path('app/temp');
        $fullPath = $tempDir . '/' . $fileName;

        if (!is_dir($tempDir)) {
            // Security-Checkliste M2: 0750 statt 0777 (kein Schreib-/Ausführzugriff für "andere").
            mkdir($tempDir, 0750, true);
        }

        copy($file->getRealPath(), $fullPath);

        \Log::info('Vorschau-Datei gespeichert', [
            'temp_path'   => 'temp/' . $fileName,
            'file_exists' => file_exists($fullPath),
        ]);

        return 'temp/' . $fileName;
    }
}
