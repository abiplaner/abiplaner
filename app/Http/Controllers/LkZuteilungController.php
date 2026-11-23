<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\HasSchuljahr;

class LkZuteilungController extends Controller
{
    use HasSchuljahr;


    /**
     * Show the zuteilung planning form.
     */
    public function planen(Request $request)
    {
        $schuljahr = $request->input('schuljahr', $this->getCurrentSchuljahr());
        $jahrgang = $request->input('jahrgang', 12);
        
        // Load wahlen to check if any exist
        $wahlenCount = \App\Models\LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->count();
        
        if ($wahlenCount === 0) {
            return redirect()->route('lk-wahlen.index')
                ->with('error', 'Keine Wahlen vorhanden. Bitte importieren Sie zuerst Wahlen.');
        }
        
        // Analyze wahlen for statistics
        $zuteilungsService = new \App\Services\LkZuteilungsService();
        $analyse = $zuteilungsService->analyseWahlen(
            auth()->user()->school_id,
            $schuljahr,
            $jahrgang
        );
        
        // Get all subjects for external selection
        $alleFaecher = collect($analyse['fach_statistik'])->sortBy('name');
        
        // Config from .env or calculated
        $defaultMaxKurse = (int) ceil($analyse['anzahl_schueler'] / 15);
        if ($defaultMaxKurse < 1) $defaultMaxKurse = 1;
        $maxSchuelerProKurs = config('lk.max_schueler_pro_kurs', 25);
        
        // Available filter values
        $schuljahre = $this->getAvailableSchuljahre();
        $jahrgaenge = [11, 12, 13];
        
        // Auto-detect external subjects from wahlen flags
        $autoExternalSubjectIds = [];
        $wahlenExtern = \App\Models\LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->where(function($q) {
                $q->where('is_erstwahl_extern', true)
                  ->orWhere('is_zweitwahl_extern', true);
            })
            ->get();
            
        foreach ($wahlenExtern as $wahl) {
            if ($wahl->is_erstwahl_extern) {
                $autoExternalSubjectIds[] = $wahl->erstwahl_subject_id;
            }
            if ($wahl->is_zweitwahl_extern) {
                $autoExternalSubjectIds[] = $wahl->zweitwahl_subject_id;
            }
        }
        $autoExternalSubjectIds = array_unique($autoExternalSubjectIds);
        
        return view('lk-kurse.planen', compact(
            'schuljahr',
            'jahrgang',
            'wahlenCount',
            'analyse',
            'alleFaecher',
            'defaultMaxKurse',
            'maxSchuelerProKurs',
            'schuljahre',
            'jahrgaenge',
            'autoExternalSubjectIds'
        ));
    }
    
    /**
     * Calculate zuteilung (AJAX).
     */
    public function berechne(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang' => 'required|integer|min:11|max:13',
            'max_kurse' => 'required|integer|min:1|max:20',
            'strategie' => 'required|in:niemand_ohne_wahl,viele_erstwahlen,ausgeglichen',
            'externe_faecher' => 'nullable|array',
            'externe_faecher.*' => 'integer',
        ]);
        
        try {
            ['analyse' => $analyse, 'planung' => $planung, 'ergebnis' => $ergebnis] = $this->calculateZuteilung($request);

            // Erweitere Kurse mit tatsächlicher Schülerzahl
            $kurseDetails = [];
            foreach ($planung['kurse'] as $kurs) {
                $kursKey = $kurs['subject_id'] . '_' . $kurs['kurs_nummer'];
                $zugeteilteSchueler = collect($ergebnis['zuteilungen'])
                    ->where('kurs_key', $kursKey)
                    ->count();
                
                $kurseDetails[] = [
                    'subject_id' => $kurs['subject_id'],
                    'name' => $kurs['name'],
                    'kurs_nummer' => $kurs['kurs_nummer'],
                    'ist_extern' => $kurs['ist_extern'],
                    'erwartete_schueler' => $kurs['erwartete_schueler'],
                    'zugeteilte_schueler' => $zugeteilteSchueler,
                ];
            }
            
            // Sortiere Kurse absteigend nach Teilnehmerzahl
            usort($kurseDetails, function($a, $b) {
                return $b['zugeteilte_schueler'] <=> $a['zugeteilte_schueler'];
            });

            return response()->json([
                'success' => true,
                'kurse' => $kurseDetails,
                'statistik' => $ergebnis['statistik'],
                'warnungen' => $planung['warnungen'],
                'anzahl_schueler' => $analyse['anzahl_schueler'],
                'nicht_zugeteilt' => $ergebnis['nicht_zugeteilt'] ?? [],
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Berechnung fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply/save the calculated zuteilung to database.
     */
    public function apply(Request $request)
    {
        $request->validate([
            'schuljahr' => 'required|string',
            'jahrgang' => 'required|integer|min:11|max:13',
            'max_kurse' => 'required|integer|min:1|max:20',
            'strategie' => 'required|in:niemand_ohne_wahl,viele_erstwahlen,ausgeglichen',
            'externe_faecher' => 'nullable|array',
            'externe_faecher.*' => 'integer',
        ]);
        
        try {
            // Recalculate (same as berechne)
            [
                'zuteilungsService' => $zuteilungsService,
                'planung' => $planung,
                'ergebnis' => $ergebnis,
            ] = $this->calculateZuteilung($request);

            // Save to database
            $saveResult = $zuteilungsService->speichereZuteilung(
                auth()->user()->school_id,
                $request->input('schuljahr'),
                $request->input('jahrgang'),
                $planung['kurse'],
                $ergebnis['zuteilungen']
            );
            
            if ($saveResult['success']) {
                $message = sprintf(
                    'Zuteilung erfolgreich durchgeführt: %d Kurse angelegt, %d Schüler zugeteilt.',
                    $saveResult['kurse_angelegt'],
                    $saveResult['zuteilungen_gespeichert']
                );
                
                return redirect()->route('lk-kurse.index', [
                    'schuljahr' => $request->input('schuljahr'),
                    'jahrgang' => $request->input('jahrgang')
                ])->with('success', $message);
            } else {
                return redirect()->back()
                    ->withErrors(['error' => 'Speichern fehlgeschlagen: ' . $saveResult['error']])
                    ->withInput();
            }
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Zuteilung fehlgeschlagen: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Berechnet Analyse, Planung und Zuteilung für die übergebene, bereits
     * validierte Anfrage neu. berechne() (Vorschau/AJAX) und apply()
     * (Speichern) führen denselben dreiphasigen Ablauf aus - apply() rechnet
     * bewusst noch einmal komplett neu, statt der Vorschau aus berechne() zu
     * vertrauen. Wirft Exceptions weiter, der Aufrufer fängt sie in seinem
     * eigenen try/catch ab (JSON-Fehler in berechne(), redirect()->back()
     * in apply()) - deshalb liegt die Validierung bewusst außerhalb dieser
     * Methode und weiterhin in jedem Aufrufer.
     *
     * @return array{zuteilungsService: \App\Services\LkZuteilungsService, analyse: array, planung: array, ergebnis: array}
     */
    private function calculateZuteilung(Request $request): array
    {
        $zuteilungsService = new \App\Services\LkZuteilungsService();

        // Phase 1: Analyse
        $analyse = $zuteilungsService->analyseWahlen(
            auth()->user()->school_id,
            $request->input('schuljahr'),
            $request->input('jahrgang')
        );

        // Phase 2: Planung
        $config = [
            'max_kurse' => $request->input('max_kurse'),
            'externe_faecher' => $request->input('externe_faecher', []),
            'max_schueler_pro_kurs' => config('lk.max_schueler_pro_kurs', 25),
            'allow_parallel_kurse' => config('lk.allow_parallel_kurse', true),
        ];

        $planung = $zuteilungsService->planeKurse($analyse, $config);

        // Phase 3: Zuteilung (Vorschau)
        $zuteilungsConfig = [
            'strategie' => $request->input('strategie'),
            'max_schueler_pro_kurs' => $config['max_schueler_pro_kurs'],
        ];

        $ergebnis = $zuteilungsService->teileZu(
            auth()->user()->school_id,
            $request->input('schuljahr'),
            $request->input('jahrgang'),
            $planung['kurse'],
            $zuteilungsConfig
        );

        return compact('zuteilungsService', 'analyse', 'planung', 'ergebnis');
    }
}
