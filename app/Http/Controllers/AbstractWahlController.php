<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\HasSchuljahr;

/**
 * Gemeinsame Basis für LkWahlController und WseminarWahlController.
 *
 * Beide Klassen teilen dieselbe CSV-Import-Pipeline und die destroyAll-Logik.
 * Konkrete Controller implementieren die abstrakten Methoden und erben
 * dadurch import(), storeImport() und destroyAll().
 */
abstract class AbstractWahlController extends Controller
{
    use HasSchuljahr;

    // =========================================================================
    // Abstrakte Methoden – von konkreten Controllern zu implementieren
    // =========================================================================

    /**
     * Gibt eine neue Instanz des Import-Services zurück.
     */
    abstract protected function makeImportService(): object;

    /**
     * Gibt den Route-Namen für die Index-Seite zurück (z.B. 'lk-wahlen.index').
     */
    abstract protected function getIndexRoute(): string;

    /**
     * Validierungsregel für den `jahrgang`-Parameter beim Import.
     * Beispiel: 'required|integer|min:11|max:13' oder 'required|integer|in:11'
     */
    abstract protected function getJahrgangValidationRule(): string;

    /**
     * Gibt das Eloquent-Model zurück (für destroyAll).
     * Beispiel: \App\Models\LkWahl::class
     */
    abstract protected function getWahlModel(): string;

    /**
     * Route-Name für die Wahl-Index-Seite beim Weiterleiten nach destroyAll.
     * Standard: getIndexRoute() – kann überschrieben werden.
     */
    protected function getDestroyAllRoute(): string
    {
        return $this->getIndexRoute();
    }

    // =========================================================================
    // Gemeinsame Methoden
    // =========================================================================

    /**
     * Zeigt das Import-Formular.
     */
    public function import()
    {
        $schuljahre = $this->getAvailableSchuljahre();
        $jahrgaenge = $this->getImportJahrgaenge();

        return view($this->getImportViewName(), compact('schuljahre', 'jahrgaenge'));
    }

    /**
     * Verarbeitet den CSV-Upload und importiert die Wahlen.
     */
    public function storeImport(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'schuljahr' => 'required|string',
            'jahrgang'  => $this->getJahrgangValidationRule(),
        ]);

        $importService = $this->makeImportService();

        try {
            $data       = $importService->parseCSV($request->file('csv_file'));
            $validation = $importService->validateData($data);

            if (!$validation['valid']) {
                return redirect()->back()
                    ->withErrors(['csv_file' => 'Die CSV-Datei enthält Fehler:'])
                    ->with('validation_errors', $validation['errors'])
                    ->withInput();
            }

            $result = $importService->importWahlen(
                $data,
                $request->input('schuljahr'),
                $request->input('jahrgang')
            );

            if ($result['success']) {
                $message = "Import erfolgreich: {$result['imported']} Wahlen importiert.";

                if (!empty($result['warnings'])) {
                    $message .= ' Es gab ' . count($result['warnings']) . ' Warnungen.';
                }

                $session = redirect()->route($this->getIndexRoute())
                    ->with('success', $message)
                    ->with('warnings', $result['warnings']);

                // Optionale Import-Statistiken (W-Seminar nutzt diese)
                if (!empty($result['stats'])) {
                    $stats = array_merge(['imported' => $result['imported']], $result['stats']);
                    $session = $session->with('import_stats', $stats);
                }

                return $session;
            }

            return redirect()->back()
                ->withErrors(['csv_file' => 'Import fehlgeschlagen:'])
                ->with('import_errors', $result['errors'])
                ->with('warnings', $result['warnings'])
                ->withInput();

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['csv_file' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Löscht alle Wahlen eines Schuljahres / Jahrgangs.
     */
    public function destroyAll(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang'  => $this->getJahrgangValidationRule(),
        ]);

        $model   = $this->getWahlModel();
        $deleted = $model::query()
            ->where('schuljahr', $request->input('schuljahr'))
            ->where('jahrgang', $request->input('jahrgang'))
            ->delete();

        return redirect()->route($this->getDestroyAllRoute())
            ->with('success', "Es wurden {$deleted} Wahlen gelöscht.");
    }

    // =========================================================================
    // Optionale Hooks mit Defaults
    // =========================================================================

    /**
     * Jahrgangs-Optionen für das Import-Formular.
     * W-Seminar überschreibt mit [11], LK nutzt [11, 12, 13].
     *
     * @return int[]
     */
    protected function getImportJahrgaenge(): array
    {
        return [11, 12, 13];
    }

    /**
     * View-Name für das Import-Formular.
     */
    abstract protected function getImportViewName(): string;
}
