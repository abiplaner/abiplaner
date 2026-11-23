<?php

namespace App\Http\Controllers;

use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Steuert den Lehrer-CSV-Import.
 *
 * Die fachliche Logik liegt im ImportService (existiert bereits).
 * Dieser Controller war vorher Teil des aufgeblasenen ImportController.
 *
 * Routen:
 *   GET  /teachers-import         → showTeacherImportForm()
 *   POST /teachers-import/preview → previewTeachers()
 *   POST /teachers-import         → importTeachers()
 */
class TeacherImportController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {}

    /**
     * Zeigt nur Lehrer mit echten Änderungen (neue, geänderte,
     * Deaktivierungsvorschläge). Unveränderte Lehrer werden herausgefiltert.
     */
    public function diffPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $schoolId = auth()->user()->school_id;

        try {
            $tempPath = $this->storeTempFile($request->file('file'));
            $preview  = $this->importService->previewTeachersFromCsv($schoolId, $request->file('file')->getRealPath());

            // Nur echte Änderungen: neu, update mit Änderungen, Deaktivierungsvorschläge
            $allTeachers = $preview['teachers'];

            $newTeachers         = array_values(array_filter($allTeachers, fn($t) => $t['action'] === 'create'));
            $updatedTeachers     = array_values(array_filter($allTeachers, fn($t) => $t['action'] === 'update' && !empty($t['changes'])));
            $deactivateProposals = array_values(array_filter($allTeachers, fn($t) => $t['action'] === 'deactivate'));
            $unchangedCount      = count(array_filter($allTeachers, fn($t) => $t['action'] === 'update' && empty($t['changes'])));

            return view('import.teachers-diff-preview', [
                'preview'             => $preview,
                'tempPath'            => $tempPath,
                'newTeachers'         => $newTeachers,
                'updatedTeachers'     => $updatedTeachers,
                'deactivateProposals' => $deactivateProposals,
                'unchangedCount'      => $unchangedCount,
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Vergleich: ' . $e->getMessage()]);
        }
    }

    /**
     * Zeigt das Upload-Formular.
     */
    public function showTeacherImportForm()
    {
        return view('import.teachers-form');
    }

    /**
     * Erzeugt eine Vorschau der zu importierenden Lehrer.
     */
    public function previewTeachers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $schoolId = auth()->user()->school_id;

        try {
            $preview  = $this->importService->previewTeachersFromCsv($schoolId, $request->file('file')->getRealPath());
            $tempPath = $this->storeTempFile($request->file('file'));

            \Log::info('Lehrer-Vorschau-Datei gespeichert', [
                'temp_path'   => $tempPath,
                'file_exists' => file_exists(storage_path('app/' . $tempPath)),
            ]);

            // Deaktivierungsvorschläge getrennt von den CSV-Zeilen anzeigen
            // (eigene Checkbox-Semantik: bestätigen statt ignorieren)
            $csvTeachers         = array_values(array_filter($preview['teachers'], fn($t) => $t['action'] !== 'deactivate'));
            $deactivateProposals = array_values(array_filter($preview['teachers'], fn($t) => $t['action'] === 'deactivate'));

            return view('import.teachers-preview', [
                'preview'             => $preview,
                'tempPath'            => $tempPath,
                'stats'               => $preview['stats'],
                'csvTeachers'         => $csvTeachers,
                'deactivateProposals' => $deactivateProposals,
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Fehler beim Parsen: ' . $e->getMessage()]);
        }
    }

    /**
     * Fuehrt den finalen Lehrer-Import durch.
     */
    public function importTeachers(Request $request)
    {
        $request->validate([
            'temp_path'    => 'required|string',
            'ignored'      => 'sometimes|array',
            'ignored.*'    => 'string',
            'deactivate'   => 'sometimes|array',
            'deactivate.*' => 'string',
        ]);

        $filePath   = storage_path('app/' . $request->input('temp_path'));
        $ignored    = $request->input('ignored', []);
        $deactivate = $request->input('deactivate', []);
        $schoolId   = auth()->user()->school_id;

        \Log::info('Lehrer-Import gestartet', [
            'temp_path'        => $request->input('temp_path'),
            'file_path'        => $filePath,
            'exists'           => file_exists($filePath),
            'ignored_count'    => count($ignored),
            'deactivate_count' => count($deactivate),
        ]);

        if (!file_exists($filePath)) {
            \Log::error('Lehrer-Import-Datei nicht gefunden!', ['path' => $filePath]);
            return redirect()->route('teachers.import.form')
                ->withErrors(['error' => "Temporaere Datei nicht gefunden. Bitte CSV erneut hochladen."]);
        }

        try {
            DB::beginTransaction();

            $result = $this->importService->importTeachersFromCsv($schoolId, $filePath, $ignored, $deactivate);

            DB::commit();

            @unlink($filePath);

            $successMessage = "Lehrer-Import erfolgreich abgeschlossen!\n\n";
            $successMessage .= "Neu: {$result['imported']}\n";
            $successMessage .= "Aktualisiert: {$result['updated']}\n";
            $successMessage .= "Deaktiviert: {$result['deactivated']}\n";
            if ($result['skipped'] > 0) {
                $successMessage .= "Ignoriert: {$result['skipped']}\n";
            }
            if (!empty($result['errors'])) {
                $successMessage .= "\nFehler:\n";
                foreach ($result['errors'] as $error) {
                    $successMessage .= "- {$error}\n";
                }
            }

            \Log::info('Lehrer-Import erfolgreich!', ['result' => $result]);

            return redirect()->route('teachers.index')->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Lehrer-Import-Fehler', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()->route('teachers.import.form')
                ->withErrors(['error' => 'Fehler beim Import: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // Private Hilfsmethoden
    // =========================================================================

    private function storeTempFile(\Illuminate\Http\UploadedFile $file): string
    {
        $fileName = 'teacher_import_' . time() . '_' . uniqid() . '.csv';
        $tempDir  = storage_path('app/temp');
        $fullPath = $tempDir . '/' . $fileName;

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0750, true);
        }

        copy($file->getRealPath(), $fullPath);

        return 'temp/' . $fileName;
    }
}
