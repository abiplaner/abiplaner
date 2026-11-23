<?php

namespace App\Http\Controllers;

use App\Services\CurrentExamPeriodService;
use App\Services\ActivityLogger;
use App\Services\SimpleRosterImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Einfacher Jahrgangsliste-Import (nur Nachname;Vorname, ohne Kurse) —
 * Ergänzung zum vollständigen StudentImportController für Jahrgänge ohne
 * Abiturprüfungs-Zuordnung (z. B. Q12), siehe SimpleRosterImportService.
 */
class SimpleRosterImportController extends Controller
{
    public function __construct(
        private SimpleRosterImportService $importService
    ) {}

    public function preview(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        if (!$examPeriod) {
            return redirect()->route('dashboard')->with('error', 'Kein Pruefungszeitraum gefunden.');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'grade' => 'required|in:11,12,13',
        ]);

        try {
            $tempPath = $this->storeTempFile($request->file('file'));
            $filePath = storage_path('app/' . $tempPath);

            $preview = $this->importService->parsePreview($filePath, $examPeriod, $request->input('grade'));

            return view('import.simple-preview', [
                'examPeriod' => $examPeriod,
                'preview' => $preview,
                'tempPath' => $tempPath,
                'grade' => $request->input('grade'),
                'originalFilename' => $request->file('file')->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Parsen: ' . $e->getMessage()]);
        }
    }

    public function import(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        if (!$examPeriod) {
            return redirect()->route('dashboard')->with('error', 'Kein Pruefungszeitraum gefunden.');
        }

        $request->validate([
            'temp_path' => 'required|string',
            'grade' => 'required|in:11,12,13',
        ]);

        $filePath = storage_path('app/' . $request->input('temp_path'));
        if (!file_exists($filePath)) {
            return back()->withErrors(['error' => 'Temporäre Datei nicht gefunden. Bitte CSV erneut hochladen.']);
        }

        try {
            $result = $this->importService->import($filePath, $examPeriod, $request->input('grade'));
            @unlink($filePath);

            ActivityLogger::log(
                'students_roster_imported',
                'ExamPeriod',
                $examPeriod->id,
                "Jahrgangsliste-Import (Jahrgangsstufe {$request->input('grade')}): {$result['created']} neu, {$result['skipped']} übersprungen (bereits vorhanden)"
            );

            return redirect()->route('students.index')->with(
                'success',
                "Jahrgangsliste importiert: {$result['created']} neue Schüler, {$result['skipped']} bereits vorhanden."
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Import: ' . $e->getMessage()]);
        }
    }

    private function storeTempFile(UploadedFile $file): string
    {
        $fileName = 'roster_import_' . time() . '_' . uniqid() . '.csv';
        $tempDir = storage_path('app/temp');
        $fullPath = $tempDir . '/' . $fileName;

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0750, true);
        }

        copy($file->getRealPath(), $fullPath);

        return 'temp/' . $fileName;
    }
}
