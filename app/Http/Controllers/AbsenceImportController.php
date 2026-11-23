<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\AbsenceCsvImportService;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Steuert den Absenzen-CSV-Import (Infoportal-Export, Roadmap 8.1).
 *
 * Ablauf: showForm() → preview() (Schüler-Zuordnung prüfen/korrigieren)
 * → import() (finaler, persistierter Import).
 */
class AbsenceImportController extends Controller
{
    public function __construct(
        private AbsenceCsvImportService $importService
    ) {}

    public function showForm()
    {
        return view('absences.import');
    }

    public function preview(Request $request)
    {
        if ($request->hasFile('file') && !$request->hasFile('files')) {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt',
            ]);
            /** @var list<UploadedFile> $uploadedFiles */
            $uploadedFiles = [$request->file('file')];
        } else {
            $request->validate([
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|mimes:csv,txt',
            ]);
            /** @var list<UploadedFile> $uploadedFiles */
            $uploadedFiles = array_values($request->file('files', []));
        }

        try {
            $tempPath = $this->storeMergedTempFiles($uploadedFiles);
            $filePath = storage_path('app/' . $tempPath);
            $originalFilename = implode(', ', array_map(
                fn (UploadedFile $f) => $f->getClientOriginalName(),
                $uploadedFiles
            ));

            $preview = $this->importService->parsePreview($filePath);

            // Perioden-Audit (Modul 1.1): Zuordnungs-Dropdown nur mit
            // Schülern der gewählten Periode befüllen
            $allStudents = Student::where('exam_period_id', app(\App\Services\CurrentExamPeriodService::class)->currentId())
                ->orderBy('lastname')->orderBy('firstname')
                ->get(['id', 'lastname', 'firstname', 'class_name', 'grade']);

            return view('absences.preview', [
                'preview' => $preview,
                'tempPath' => $tempPath,
                'originalFilename' => $originalFilename,
                'allStudents' => $allStudents,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Parsen: ' . $e->getMessage()]);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'temp_path' => 'required|string',
            'original_filename' => 'required|string',
            'corrections' => 'sometimes|string',
        ]);

        $filePath = storage_path('app/' . $request->input('temp_path'));
        $corrections = json_decode($request->input('corrections', '{}'), true) ?? [];

        if (!file_exists($filePath)) {
            return back()->withErrors(['error' => 'Temporäre Datei nicht gefunden. Bitte CSV erneut hochladen.']);
        }

        try {
            $absenceImport = $this->importService->import(
                $filePath,
                $request->input('original_filename'),
                $corrections,
                auth()->id()
            );

            @unlink($filePath);

            ActivityLogger::log(
                'absences_imported',
                'AbsenceImport',
                $absenceImport->id,
                "Absenzen-Import: {$absenceImport->created_count} neu, " .
                "{$absenceImport->updated_count} aktualisiert, {$absenceImport->skipped_count} ohne Schüler-Zuordnung übersprungen, " .
                "{$absenceImport->filtered_count} Befreiungen ignoriert"
            );

            return redirect()->route('absences.index')->with(
                'success',
                "Import abgeschlossen: {$absenceImport->created_count} neue Absenzen, " .
                "{$absenceImport->updated_count} aktualisiert, {$absenceImport->skipped_count} übersprungen (keine Schüler-Zuordnung), " .
                "{$absenceImport->filtered_count} Befreiungen/vorab genehmigte Freistellungen ignoriert."
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Import: ' . $e->getMessage()]);
        }
    }

    /**
     * Speichert eine oder mehrere hochgeladene CSV-Dateien als zusammengeführte
     * temporäre CSV-Datei (Kopfzeile aus der ersten Datei, Datenzeilen aus allen).
     *
     * @param list<UploadedFile> $files
     */
    private function storeMergedTempFiles(array $files): string
    {
        $fileName = 'absence_import_' . time() . '_' . uniqid() . '.csv';
        $tempDir = storage_path('app/temp');
        $fullPath = $tempDir . '/' . $fileName;

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0750, true);
        }

        if (count($files) === 1) {
            copy($files[0]->getRealPath(), $fullPath);
            return 'temp/' . $fileName;
        }

        $out = fopen($fullPath, 'w');
        if ($out === false) {
            throw new \RuntimeException('Temporäre Importdatei konnte nicht angelegt werden.');
        }

        $headerWritten = false;
        foreach ($files as $file) {
            $in = fopen($file->getRealPath(), 'r');
            if ($in === false) {
                continue;
            }
            $lineIndex = 0;
            while (($line = fgets($in)) !== false) {
                $lineIndex++;
                if ($lineIndex === 1) {
                    if (!$headerWritten) {
                        fwrite($out, $line);
                        $headerWritten = true;
                    }
                    continue;
                }
                if (trim($line) !== '') {
                    fwrite($out, $line);
                }
            }
            fwrite($out, "\n");
            fclose($in);
        }

        fclose($out);

        return 'temp/' . $fileName;
    }
}
