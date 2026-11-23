<?php

namespace App\Services;

use Illuminate\Support\Collection;

class LkStatistikService
{
    /**
     * Berechne Wahlstatistiken aus einer Collection von LkWahl
     * 
     * @param Collection $wahlen
     * @return array
     */
    public function berechneWahlStatistik(Collection $wahlen): array
    {
        $statistik = [
            'gesamt_schueler' => $wahlen->count(),
            'zugeteilt' => $wahlen->where('zuteilung_lk_kurs_id', '!=', null)->count(),
            'nicht_zugeteilt' => $wahlen->where('zuteilung_lk_kurs_id', null)->count(),
            'erstwahl_erhalten' => $wahlen->where('prioritaet_erhalten', 'erstwahl')->count(),
            'zweitwahl_erhalten' => $wahlen->where('prioritaet_erhalten', 'zweitwahl')->count(),
            'keine_wahl_erhalten' => $wahlen->where('prioritaet_erhalten', 'keine')->where('zuteilung_lk_kurs_id', '!=', null)->count(),
        ];
        
        // Prozente berechnen
        if ($statistik['zugeteilt'] > 0) {
            $statistik['erstwahl_prozent'] = round(($statistik['erstwahl_erhalten'] / $statistik['zugeteilt']) * 100, 1);
            $statistik['zweitwahl_prozent'] = round(($statistik['zweitwahl_erhalten'] / $statistik['zugeteilt']) * 100, 1);
            $statistik['keine_wahl_prozent'] = round(($statistik['keine_wahl_erhalten'] / $statistik['zugeteilt']) * 100, 1);
        } else {
            $statistik['erstwahl_prozent'] = 0;
            $statistik['zweitwahl_prozent'] = 0;
            $statistik['keine_wahl_prozent'] = 0;
        }
        
        return $statistik;
    }
    
    /**
     * Berechne Fachverteilung für Erst- und Zweitwahlen
     * 
     * @param Collection $wahlen (mit erstwahl und zweitwahl relationships geladen)
     * @return array
     */
    public function berechneFachVerteilung(Collection $wahlen): array
    {
        $fachStatistik = [];
        
        // Erstwahlen zählen
        foreach ($wahlen as $wahl) {
            if ($wahl->erstwahl) {
                $fachName = $wahl->erstwahl->name;
                
                if (!isset($fachStatistik[$fachName])) {
                    $fachStatistik[$fachName] = [
                        'fach' => $fachName,
                        'erstwahlen' => 0,
                        'zweitwahlen' => 0,
                        'gesamt' => 0,
                        'is_extern' => false,
                    ];
                }
                
                $fachStatistik[$fachName]['erstwahlen']++;
                $fachStatistik[$fachName]['gesamt']++;
                
                if ($wahl->is_erstwahl_extern) {
                    $fachStatistik[$fachName]['is_extern'] = true;
                }
            }
        }
        
        // Zweitwahlen zählen
        foreach ($wahlen as $wahl) {
            if ($wahl->zweitwahl) {
                $fachName = $wahl->zweitwahl->name;
                
                if (!isset($fachStatistik[$fachName])) {
                    $fachStatistik[$fachName] = [
                        'fach' => $fachName,
                        'erstwahlen' => 0,
                        'zweitwahlen' => 0,
                        'gesamt' => 0,
                        'is_extern' => false,
                    ];
                }
                
                $fachStatistik[$fachName]['zweitwahlen']++;
                $fachStatistik[$fachName]['gesamt']++;

                if ($wahl->is_zweitwahl_extern) {
                    $fachStatistik[$fachName]['is_extern'] = true;
                }
            }
        }
        
        // Nach Gesamtzahl sortieren (absteigend)
        usort($fachStatistik, function($a, $b) {
            return $b['gesamt'] <=> $a['gesamt'];
        });
        
        return $fachStatistik;
    }
    
    /**
     * Bereite Daten für Chart.js Balkendiagramm vor
     * 
     * @param array $fachStatistik (von berechneFachVerteilung())
     * @return array
     */
    public function prepareChartData(array $fachStatistik): array
    {
        $labels = [];
        $erstwahlenData = [];
        $zweitwahlenData = [];
        
        foreach ($fachStatistik as $fach) {
            $labels[] = $fach['fach'];
            $erstwahlenData[] = $fach['erstwahlen'];
            $zweitwahlenData[] = $fach['zweitwahlen'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Erstwahlen',
                    'data' => $erstwahlenData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)', // blue-500
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Zweitwahlen',
                    'data' => $zweitwahlenData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)', // green-500
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }
    
    /**
     * Berechne Statistiken für zugeteilte Kurse
     * 
     * @param Collection $kurse (mit wahlen relationship geladen)
     * @return array
     */
    public function berechneKursStatistik(Collection $kurse): array
    {
        if ($kurse->isEmpty()) {
            return [
                'anzahl_kurse' => 0,
                'anzahl_externe_kurse' => 0,
                'durchschnitt_schueler' => 0,
                'min_schueler' => 0,
                'max_schueler' => 0,
                'standardabweichung' => 0,
            ];
        }
        
        $schuelerProKurs = $kurse->map(function($kurs) {
            return $kurs->wahlen->count();
        })->toArray();
        
        $durchschnitt = count($schuelerProKurs) > 0 ? array_sum($schuelerProKurs) / count($schuelerProKurs) : 0;
        
        // Standardabweichung berechnen
        $varianz = 0;
        if (count($schuelerProKurs) > 0) {
            foreach ($schuelerProKurs as $anzahl) {
                $varianz += pow($anzahl - $durchschnitt, 2);
            }
            $varianz = $varianz / count($schuelerProKurs);
        }
        $standardabweichung = sqrt($varianz);
        
        return [
            'anzahl_kurse' => $kurse->count(),
            'anzahl_externe_kurse' => $kurse->where('ist_extern', true)->count(),
            'durchschnitt_schueler' => round($durchschnitt, 1),
            'min_schueler' => !empty($schuelerProKurs) ? min($schuelerProKurs) : 0,
            'max_schueler' => !empty($schuelerProKurs) ? max($schuelerProKurs) : 0,
            'standardabweichung' => round($standardabweichung, 2),
        ];
    }
}
