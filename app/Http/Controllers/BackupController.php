<?php

namespace App\Http\Controllers;

use App\Services\TenantBackupService;
use App\Http\Middleware\DesktopZugang;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    public function __construct(
        protected TenantBackupService $backupService
    ) {}
    
    /**
     * Zeige Backup-Verwaltungsseite
     */
    public function index()
    {
        // Die Schule des laufenden Schul-Kontexts (AktuelleSchule)
        /** @var \App\Models\School|null $school */
        $school = app(\App\Services\AktuelleSchule::class)->schule();
        
        if (!$school) {
            return redirect()->route('dashboard')
                ->with('error', 'Keine Schule im Tenant-Kontext gefunden');
        }
        
        $backups = $this->backupService->listBackups($school);
        $dbExists = $this->backupService->tenantDatabaseExists($school);
        $datenbankDatei = $this->backupService->datenbankDatei();
        $sicherungsOrdner = $this->backupService->sicherungsVerzeichnis($school);
        $ordnerOeffnenMoeglich = DesktopZugang::aktiv();
        $ordnerOeffnenText = match (PHP_OS_FAMILY) {
            'Windows' => 'Im Explorer öffnen',
            'Darwin' => 'Im Finder öffnen',
            default => 'Ordner öffnen',
        };

        return view('backups.index', compact(
            'school', 'backups', 'dbExists', 'datenbankDatei', 'sicherungsOrdner',
            'ordnerOeffnenMoeglich', 'ordnerOeffnenText'
        ));
    }

    /**
     * Sicherungsordner im Dateimanager des Rechners öffnen.
     *
     * Nur in der Desktop-App: Dort laufen Webserver und Fenster auf demselben
     * Rechner, der Befehl öffnet den Ordner also beim Benutzer. Auf einem
     * Server gäbe es dort niemanden, der ihn sieht.
     */
    public function ordnerOeffnen(): RedirectResponse
    {
        abort_unless(DesktopZugang::aktiv(), 404);

        $ordner = $this->backupService->sicherungsVerzeichnis();
        if (! is_dir($ordner)) {
            @mkdir($ordner, 0775, true);
        }

        $befehl = match (PHP_OS_FAMILY) {
            'Windows' => ['explorer.exe', $ordner],
            'Darwin' => ['open', $ordner],
            default => ['xdg-open', $ordner],
        };

        try {
            // explorer.exe meldet auch bei Erfolg den Code 1 — der Rückgabewert
            // sagt nichts, deshalb wird er nicht ausgewertet.
            (new Process($befehl))->setTimeout(15)->run();
        } catch (\Throwable $e) {
            return redirect()->route('backups.index')
                ->with('error', 'Der Ordner ließ sich nicht öffnen: ' . $e->getMessage());
        }

        return redirect()->route('backups.index');
    }
    
    /**
     * Datenbank komplett zurücksetzen (Neustart mit der Einrichtung).
     *
     * Doppelt abgesichert: das Wort ZURÜCKSETZEN eintippen und das eigene
     * Passwort angeben. Vorher sichert der Dienst den Stand.
     */
    public function zuruecksetzen(Request $request): RedirectResponse
    {
        $request->validate([
            'bestaetigung' => ['required', 'string', 'in:ZURÜCKSETZEN'],
            'password' => ['required', 'current_password'],
        ], [
            'bestaetigung.in' => 'Bitte genau „ZURÜCKSETZEN“ (in Großbuchstaben) eintippen.',
        ], [
            'bestaetigung' => 'Bestätigung',
        ]);

        try {
            $sicherung = $this->backupService->zuruecksetzen();
        } catch (\Throwable $e) {
            return redirect()->route('backups.index')->with('error', $e->getMessage());
        }

        // Die Benutzerkonten sind mit der Datenbank weg.
        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('ersteinrichtung.show')
            ->with('success', "Abiplaner wurde zurückgesetzt. Der bisherige Stand liegt als Sicherung {$sicherung} im Sicherungsordner.");
    }

    /**
     * Erstelle neuen Backup
     */
    public function create(Request $request)
    {
        /** @var \App\Models\School|null $school */
        $school = app(\App\Services\AktuelleSchule::class)->schule();
        
        if (!$school) {
            return redirect()->route('dashboard')
                ->with('error', 'Keine Schule im Tenant-Kontext gefunden');
        }
        
        $request->validate([
            'description' => 'nullable|string|max:100',
        ]);
        
        try {
            $backup = $this->backupService->createBackup($school, $request->description);
            
            ActivityLogger::log(
                'backup_created', 
                'School', 
                $school->school_number, 
                "Backup erstellt: {$backup['filename']}"
            );
            
            return redirect()
                ->route('backups.index')
                ->with('success', "Backup erfolgreich erstellt: {$backup['filename']}");
        } catch (\Exception $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Stelle Backup wieder her
     */
    public function restore(Request $request)
    {
        /** @var \App\Models\School|null $school */
        $school = app(\App\Services\AktuelleSchule::class)->schule();
        
        if (!$school) {
            return redirect()->route('dashboard')
                ->with('error', 'Keine Schule im Tenant-Kontext gefunden');
        }
        
        $request->validate([
            // Security-Checkliste H4: Strikte Whitelist gegen Path Traversal im Dateinamen.
            'filename' => ['required', 'string', 'regex:/^backup_[\w\-\.]+\.(sql|sql\.gz|sqlite\.gz)$/'],
        ]);
        
        try {
            $this->backupService->restoreBackup($school, $request->filename);
            
            ActivityLogger::log(
                'backup_restored', 
                'School', 
                $school->school_number, 
                "Backup wiederhergestellt: {$request->filename}"
            );
            
            return redirect()
                ->route('backups.index')
                ->with('success', "Backup erfolgreich wiederhergestellt: {$request->filename}");
        } catch (\Exception $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Wiederherstellung fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Lösche Backup
     */
    public function delete(Request $request)
    {
        /** @var \App\Models\School|null $school */
        $school = app(\App\Services\AktuelleSchule::class)->schule();
        
        if (!$school) {
            return redirect()->route('dashboard')
                ->with('error', 'Keine Schule im Tenant-Kontext gefunden');
        }
        
        $request->validate([
            // Security-Checkliste H4: Strikte Whitelist gegen Path Traversal im Dateinamen.
            'filename' => ['required', 'string', 'regex:/^backup_[\w\-\.]+\.(sql|sql\.gz|sqlite\.gz)$/'],
        ]);
        
        try {
            $this->backupService->deleteBackup($school, $request->filename);
            
            ActivityLogger::log(
                'backup_deleted', 
                'School', 
                $school->school_number, 
                "Backup gelöscht: {$request->filename}"
            );
            
            return redirect()
                ->route('backups.index')
                ->with('success', 'Backup erfolgreich gelöscht');
        } catch (\Exception $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Löschen fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Lade Backup herunter
     */
    public function download(Request $request)
    {
        /** @var \App\Models\School|null $school */
        $school = app(\App\Services\AktuelleSchule::class)->schule();
        
        if (!$school) {
            return redirect()->route('dashboard')
                ->with('error', 'Keine Schule im Tenant-Kontext gefunden');
        }
        
        $request->validate([
            // Security-Checkliste H4: Strikte Whitelist gegen Path Traversal im Dateinamen.
            'filename' => ['required', 'string', 'regex:/^backup_[\w\-\.]+\.(sql|sql\.gz|sqlite\.gz)$/'],
        ]);
        
        // Verwende school_number falls vorhanden
        $dbIdentifier = $school->school_number;
        $schoolDir = "backups/school_{$dbIdentifier}";
        
        // WICHTIG: Immer zentrales Storage verwenden, nicht tenant-spezifisches!
        // Lokal liegen die Sicherungen im Datenordner, nicht unter storage/.
        $path = $this->backupService->sicherungsVerzeichnis($school) . DIRECTORY_SEPARATOR
            . TenantBackupService::sanitizeBackupFilename((string) $request->filename);
        
        if (!file_exists($path)) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup-Datei nicht gefunden');
        }
        
        ActivityLogger::log(
            'backup_downloaded', 
            'School', 
            $school->school_number, 
            "Backup heruntergeladen: {$request->filename}"
        );
        
        return response()->download($path);
    }
}
