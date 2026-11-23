<?php

namespace App\Services;

use App\Models\LkWahl;
use App\Models\LkKurs;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LkZuteilungsService
{
    /**
     * Phase 1: Analyse der Wahlen
     * 
     * @param string $schoolId
     * @param string $schuljahr
     * @param int $jahrgang
     * @return array
     */
    public function analyseWahlen(string $schoolId, string $schuljahr, int $jahrgang): array
    {
        $wahlen = LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['erstwahl', 'zweitwahl'])
            ->get();
        
        $fachStatistik = [];
        
        // Erst- und Zweitwahlen zählen
        foreach ($wahlen as $wahl) {
            // Erstwahl
            if ($wahl->erstwahl) {
                $fachId = $wahl->erstwahl->id;
                $fachName = $wahl->erstwahl->name;
                
                if (!isset($fachStatistik[$fachId])) {
                    $fachStatistik[$fachId] = [
                        'subject_id' => $fachId,
                        'name' => $fachName,
                        'erstwahlen' => 0,
                        'zweitwahlen' => 0,
                        'gesamt' => 0,
                    ];
                }
                
                $fachStatistik[$fachId]['erstwahlen']++;
                $fachStatistik[$fachId]['gesamt']++;
            }
            
            // Zweitwahl
            if ($wahl->zweitwahl) {
                $fachId = $wahl->zweitwahl->id;
                $fachName = $wahl->zweitwahl->name;
                
                if (!isset($fachStatistik[$fachId])) {
                    $fachStatistik[$fachId] = [
                        'subject_id' => $fachId,
                        'name' => $fachName,
                        'erstwahlen' => 0,
                        'zweitwahlen' => 0,
                        'gesamt' => 0,
                    ];
                }
                
                $fachStatistik[$fachId]['zweitwahlen']++;
                $fachStatistik[$fachId]['gesamt']++;
            }
        }
        
        return [
            'anzahl_schueler' => $wahlen->count(),
            'fach_statistik' => array_values($fachStatistik),
        ];
    }
    
    /**
     * Phase 2: Plane welche Kurse eingerichtet werden sollen
     * 
     * @param array $statistik (von analyseWahlen())
     * @param array $config ['max_kurse' => int, 'externe_faecher' => array, 'max_schueler_pro_kurs' => int]
     * @return array ['kurse' => array, 'warnungen' => array]
     */
    public function planeKurse(array $statistik, array $config): array
    {
        $maxKurse = $config['max_kurse'] ?? 9;
        $externeFaecher = $config['externe_faecher'] ?? [];
        $maxSchuelerProKurs = $config['max_schueler_pro_kurs'] ?? 25;
        $allowParallel = $config['allow_parallel_kurse'] ?? true;
        
        $fachStatistik = collect($statistik['fach_statistik']);
        $geplanteKurse = [];
        $warnungen = [];
        
        // Tracking variables
        $currentCourseCount = 0;
        $subjectCourseCounts = []; // subject_id => count
        
        // ---------------------------------------------------------
        // Schritt 1: Externe Kurse (Priorität 1, zählen zum Limit)
        // ---------------------------------------------------------
        foreach ($externeFaecher as $subjectId) {
            $fach = $fachStatistik->firstWhere('subject_id', $subjectId);
            
            if ($fach) {
                // Determine how many courses needed based on First Choices
                // (External courses might need parallel if many students go there)
                // Assuming 1 external course per subject for now unless very large
                $geplanteKurse[] = [
                    'subject_id' => $subjectId,
                    'name' => $fach['name'],
                    'kurs_nummer' => 1,
                    'ist_extern' => true,
                    'erwartete_schueler' => $fach['erstwahlen'], // Use First Choices for demand
                    'prioritaet' => 999, 
                ];
                
                // Externe Kurse zählen NICHT zum Limit
                // $currentCourseCount++; 
                $subjectCourseCounts[$subjectId] = 1;
            }
        }
        
        // ---------------------------------------------------------
        // Schritt 2: Interne Kurse planen
        // ---------------------------------------------------------
        $slotsAvailable = $maxKurse - $currentCourseCount;
        
        if ($slotsAvailable < 0) {
            $warnungen[] = "Achtung: Externe Kurse ({$currentCourseCount}) überschreiten bereits das Limit von {$maxKurse} Kursen.";
            // We continue, but slotsAvailable is negative, so no internal courses will be added loop-wise if we strictly check > 0
            // But usually we should allow at least showing the external ones.
        }
        
        // Filter internal subjects and sort by FIRST CHOICE demand
        // Only consider subjects with at least 1 First Choice
        $interneKandidaten = $fachStatistik->reject(function($fach) use ($externeFaecher) {
            return in_array($fach['subject_id'], $externeFaecher);
        })->where('erstwahlen', '>', 0)
          ->sortByDesc('erstwahlen');
        
        // A) Initial Allocation: Give 1 course to each subject (Top-Down)
        foreach ($interneKandidaten as $fach) {
            if ($slotsAvailable <= 0) {
                $warnungen[] = "Kein Kursplatz mehr für Fach '{$fach['name']}' ({$fach['erstwahlen']} Erstwahlen).";
                continue;
            }
            
            $geplanteKurse[] = [
                'subject_id' => $fach['subject_id'],
                'name' => $fach['name'],
                'kurs_nummer' => 1,
                'ist_extern' => false,
                'erwartete_schueler' => $fach['erstwahlen'],
                'prioritaet' => $fach['erstwahlen'],
            ];
            
            $currentCourseCount++;
            $slotsAvailable--;
            $subjectCourseCounts[$fach['subject_id']] = 1;
        }
        
        // B) Parallel Courses: Distribute remaining slots to overcrowded subjects
        if ($allowParallel && $slotsAvailable > 0) {
            while ($slotsAvailable > 0) {
                // Find subject with highest average load per course
                $overcrowdedCandidate = null;
                $highestAvgLoad = 0;
                
                foreach ($interneKandidaten as $fach) {
                    if (!isset($subjectCourseCounts[$fach['subject_id']])) continue; // Should have at least 1
                    
                    $currentCourses = $subjectCourseCounts[$fach['subject_id']];
                    $avgLoad = $fach['erstwahlen'] / $currentCourses;
                    
                    if ($avgLoad > $maxSchuelerProKurs && $avgLoad > $highestAvgLoad) {
                        $highestAvgLoad = $avgLoad;
                        $overcrowdedCandidate = $fach;
                    }
                }
                
                if ($overcrowdedCandidate) {
                     // Add parallel course
                     $subjId = $overcrowdedCandidate['subject_id'];
                     $subjectCourseCounts[$subjId]++;
                     $kursNummer = $subjectCourseCounts[$subjId];
                     
                     $geplanteKurse[] = [
                        'subject_id' => $subjId,
                        'name' => $overcrowdedCandidate['name'],
                        'kurs_nummer' => $kursNummer,
                        'ist_extern' => false,
                        'erwartete_schueler' => round($overcrowdedCandidate['erstwahlen'] / $subjectCourseCounts[$subjId]),
                        'prioritaet' => $overcrowdedCandidate['erstwahlen'],
                    ];
                    
                    $slotsAvailable--;
                    $warnungen[] = "Parallelkurs {$kursNummer} für '{$overcrowdedCandidate['name']}' eingerichtet.";
                } else {
                    // No subject is seemingly overcrowded enough to warrant priority over saving a slot?
                    // Or maybe we should just fill slots with "heaviest" subjects regardless of Max limit?
                    // User said: "Nur wenn das nicht geht, dann die Zweitwahl." -> implying we tried to satisfy 1st.
                    // If we have slots, we should probably reduce class sizes.
                    
                    // Let's look for highest load even if < maxSchuelerProKurs?
                    // For now, adhere to maxSchuelerProKurs as trigger. 
                    // If no one is > 25, we stop.
                    break;
                }
            }
        }
        
        return [
            'kurse' => $geplanteKurse,
            'warnungen' => $warnungen,
        ];
    }
    
    /**
     * Phase 3: Teile Schüler den Kursen zu
     * 
     * @param string $schoolId
     * @param string $schuljahr
     * @param int $jahrgang
     * @param array $geplanteKurse (von planeKurse())
     * @param array $config ['strategie' => string, 'max_schueler_pro_kurs' => int]
     * @return array ['zuteilungen' => array, 'statistik' => array]
     */
    public function teileZu(string $schoolId, string $schuljahr, int $jahrgang, array $geplanteKurse, array $config): array
    {
        $strategie = $config['strategie'] ?? 'niemand_ohne_wahl';
        
        $wahlen = LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['schueler', 'erstwahl', 'zweitwahl'])
            ->get();
        
        // Initialisiere Kurse mit leeren Schüler-Arrays
        $kurse = [];
        foreach ($geplanteKurse as $kurs) {
            $kursKey = $kurs['subject_id'] . '_' . $kurs['kurs_nummer'];
            $kurse[$kursKey] = [
                'subject_id' => $kurs['subject_id'],
                'name' => $kurs['name'],
                'kurs_nummer' => $kurs['kurs_nummer'],
                'ist_extern' => $kurs['ist_extern'],
                'schueler' => [],
                'max_schueler' => $config['max_schueler_pro_kurs'] ?? 25,
            ];
        }
        
        // Strategie auswählen
        switch ($strategie) {
            case 'viele_erstwahlen':
                $zuteilungen = $this->zuteilungVieleErstwahlen($wahlen, $kurse);
                break;
            case 'ausgeglichen':
                $zuteilungen = $this->zuteilungAusgeglichen($wahlen, $kurse);
                break;
            case 'niemand_ohne_wahl':
            default:
                $zuteilungen = $this->zuteilungNiemandOhneWahl($wahlen, $kurse);
                break;
        }
        
        // Statistiken berechnen
        $statistik = $this->berechneZuteilungsStatistik($zuteilungen, $wahlen->count());
        
        // Identifiziere nicht zugeteilte Schüler
        $nichtZugeteilt = [];
        foreach ($wahlen as $wahl) {
            if (!isset($zuteilungen[$wahl->id])) {
                $nichtZugeteilt[] = [
                   'id' => $wahl->schueler_id,
                   'vorname' => $wahl->schueler->firstname,
                   'nachname' => $wahl->schueler->lastname,
                   'klasse' => $wahl->schueler->class_name,
                   'erstwahl' => $wahl->erstwahl->name ?? '-',
                   'zweitwahl' => $wahl->zweitwahl->name ?? '-',
                ];
            }
        }
        
        return [
            'zuteilungen' => $zuteilungen,
            'statistik' => $statistik,
            'nicht_zugeteilt' => $nichtZugeteilt,
        ];
    }
    
    /**
     * Strategie 1: Niemand ohne Wahl (Standard)
     * Priorität: Jeder Schüler bekommt Erst- oder Zweitwahl
     */
    private function zuteilungNiemandOhneWahl(Collection $wahlen, array $kurse): array
    {
        $zuteilungen = [];
        
        // Schritt 1: Externe Kurse zuerst (NUR Erstwahlen)
        $zuteilungen = $this->zuteilungExterneKurse($wahlen, $kurse);
        
        // Schritt 2: Erstwahlen in internen Kursen
        foreach ($wahlen as $wahl) {
            if (isset($zuteilungen[$wahl->id])) continue; // Bereits zugeteilt
            
            $erstwahl = $wahl->erstwahl_subject_id;
            
            // Finde Kurs mit freiem Platz für Erstwahl
            $kursGefunden = false;
            foreach ($kurse as $kursKey => $kurs) {
                if (!$kurs['ist_extern'] && 
                    $kurs['subject_id'] == $erstwahl && 
                    count($kurs['schueler']) < $kurs['max_schueler']) {
                    
                    $kurse[$kursKey]['schueler'][] = [
                        'wahl_id' => $wahl->id,
                        'schueler_id' => $wahl->schueler_id,
                        'prioritaet' => 'erstwahl',
                    ];
                    $zuteilungen[$wahl->id] = [
                        'kurs_key' => $kursKey,
                        'prioritaet' => 'erstwahl',
                    ];
                    $kursGefunden = true;
                    break;
                }
            }
        }
        
        // Schritt 3: Zweitwahlen für noch nicht zugeteilte
        foreach ($wahlen as $wahl) {
            if (isset($zuteilungen[$wahl->id])) continue; // Bereits zugeteilt
            
            $zweitwahl = $wahl->zweitwahl_subject_id;
            
            // Finde Kurs mit freiem Platz für Zweitwahl
            foreach ($kurse as $kursKey => $kurs) {
                // Bedingung: Gleiches Fach UND (Extern ODER Platz frei)
                if ($kurs['subject_id'] == $zweitwahl && 
                    ($kurs['ist_extern'] || count($kurs['schueler']) < $kurs['max_schueler'])) {
                    
                    $kurse[$kursKey]['schueler'][] = [
                        'wahl_id' => $wahl->id,
                        'schueler_id' => $wahl->schueler_id,
                        'prioritaet' => 'zweitwahl',
                    ];
                    $zuteilungen[$wahl->id] = [
                        'kurs_key' => $kursKey,
                        'prioritaet' => 'zweitwahl',
                    ];
                    break;
                }
            }
        }
        
        // Schritt 4: Restliche auf freie Plätze verteilen - DEAKTIVIERT
        // Schüler, die weder Erst- noch Zweitwahl erhalten haben, bleiben "nicht zugeteilt"
        /*
        foreach ($wahlen as $wahl) {
            if (isset($zuteilungen[$wahl->id])) continue; // Bereits zugeteilt
            
            // Finde irgendeinen Kurs mit freiem Platz
            foreach ($kurse as $kursKey => $kurs) {
                if (count($kurs['schueler']) < $kurs['max_schueler']) {
                    $kurse[$kursKey]['schueler'][] = [
                        'wahl_id' => $wahl->id,
                        'schueler_id' => $wahl->schueler_id,
                        'prioritaet' => 'keine',
                    ];
                    $zuteilungen[$wahl->id] = [
                        'kurs_key' => $kursKey,
                        'prioritaet' => 'keine',
                    ];
                    break;
                }
            }
        }
        */
        
        return $zuteilungen;
    }
    
    /**
     * Strategie 2: Möglichst viele Erstwahlen
     * Priorität: Maximiere Erstwahl-Erfüllung
     */
    private function zuteilungVieleErstwahlen(Collection $wahlen, array $kurse): array
    {
        $zuteilungen = [];
        
        // Schritt 1: Externe Kurse (wie bei Strategie 1)
        $zuteilungen = $this->zuteilungExterneKurse($wahlen, $kurse);
        
        // Schritt 2: ALLE Erstwahlen (auch wenn das bedeutet, dass manche leer ausgehen)
        foreach ($wahlen as $wahl) {
            if (isset($zuteilungen[$wahl->id])) continue;
            
            $erstwahl = $wahl->erstwahl_subject_id;
            
            foreach ($kurse as $kursKey => $kurs) {
                if (!$kurs['ist_extern'] && 
                    $kurs['subject_id'] == $erstwahl && 
                    count($kurs['schueler']) < $kurs['max_schueler']) {
                    
                    $kurse[$kursKey]['schueler'][] = [
                        'wahl_id' => $wahl->id,
                        'schueler_id' => $wahl->schueler_id,
                        'prioritaet' => 'erstwahl',
                    ];
                    $zuteilungen[$wahl->id] = [
                        'kurs_key' => $kursKey,
                        'prioritaet' => 'erstwahl',
                    ];
                    break;
                }
            }
        }
        
        // Schritt 3: Nur noch freie Plätze auffüllen - DEAKTIVIERT
        /*
        foreach ($wahlen as $wahl) {
            // ...
        }
        */
        
        return $zuteilungen;
    }
    

    
    /**
     * Strategie 3: Ausgeglichene Kursgrößen
     * Priorität: Minimiere Standardabweichung der Kursgrößen
     */
    private function zuteilungAusgeglichen(Collection $wahlen, array $kurse): array
    {
        $zuteilungen = [];
        
        // Schritt 1: Externe Kurse (wie bei anderen Strategien)
        $zuteilungen = $this->zuteilungExterneKurse($wahlen, $kurse);
        
        // Schritt 2: Erstwahlen, aber bevorzuge kleinere Kurse
        $nichtZugeteilt = [];
        foreach ($wahlen as $wahl) {
            if (isset($zuteilungen[$wahl->id])) continue;
            
            $erstwahl = $wahl->erstwahl_subject_id;
            
            // Finde alle passenden Kurse und wähle kleinsten
            $passendKurse = [];
            foreach ($kurse as $kursKey => $kurs) {
                if (!$kurs['ist_extern'] && 
                    $kurs['subject_id'] == $erstwahl && 
                    count($kurs['schueler']) < $kurs['max_schueler']) {
                    
                    $passendKurse[$kursKey] = count($kurs['schueler']);
                }
            }
            
            if (!empty($passendKurse)) {
                // Wähle Kurs mit wenigsten Schülern
                asort($passendKurse);
                $kleinsterKurs = array_key_first($passendKurse);
                
                $kurse[$kleinsterKurs]['schueler'][] = [
                    'wahl_id' => $wahl->id,
                    'schueler_id' => $wahl->schueler_id,
                    'prioritaet' => 'erstwahl',
                ];
                $zuteilungen[$wahl->id] = [
                    'kurs_key' => $kleinsterKurs,
                    'prioritaet' => 'erstwahl',
                ];
            } else {
                $nichtZugeteilt[] = $wahl;
            }
        }
        
        // Schritt 3: Zweitwahlen mit Ausgleich
        foreach ($nichtZugeteilt as $wahl) {
            if (isset($zuteilungen[$wahl->id])) continue;
            
            $zweitwahl = $wahl->zweitwahl_subject_id;
            
            $passendKurse = [];
            foreach ($kurse as $kursKey => $kurs) {
                // Bedingung: Gleiches Fach UND (Extern ODER Platz frei)
                if ($kurs['subject_id'] == $zweitwahl && 
                    ($kurs['ist_extern'] || count($kurs['schueler']) < $kurs['max_schueler'])) {
                    
                    $passendKurse[$kursKey] = count($kurs['schueler']);
                }
            }
            
            if (!empty($passendKurse)) {
                asort($passendKurse);
                $kleinsterKurs = array_key_first($passendKurse);
                
                $kurse[$kleinsterKurs]['schueler'][] = [
                    'wahl_id' => $wahl->id,
                    'schueler_id' => $wahl->schueler_id,
                    'prioritaet' => 'zweitwahl',
                ];
                $zuteilungen[$wahl->id] = [
                    'kurs_key' => $kleinsterKurs,
                    'prioritaet' => 'zweitwahl',
                ];
            }
        }
        
        // Schritt 4: Rest auf kleinste Kurse verteilen - DEAKTIVIERT
        /*
        foreach ($wahlen as $wahl) {
            if (isset($zuteilungen[$wahl->id])) continue;
            
            // Finde kleinsten Kurs
            $kursGroessen = [];
            foreach ($kurse as $kursKey => $kurs) {
                if (count($kurs['schueler']) < $kurs['max_schueler']) {
                    $kursGroessen[$kursKey] = count($kurs['schueler']);
                }
            }
            
            if (!empty($kursGroessen)) {
                asort($kursGroessen);
                $kleinsterKurs = array_key_first($kursGroessen);
                
                $kurse[$kleinsterKurs]['schueler'][] = [
                    'wahl_id' => $wahl->id,
                    'schueler_id' => $wahl->schueler_id,
                    'prioritaet' => 'keine',
                ];
                $zuteilungen[$wahl->id] = [
                    'kurs_key' => $kleinsterKurs,
                    'prioritaet' => 'keine',
                ];
            }
        }
        */
        
        return $zuteilungen;
    }
    
    /**
     * Schritt 1 (alle Strategien): Teile Schüler mit Erstwahl in einem externen Kurs sofort zu.
     * Modifiziert $kurse per Referenz (fügt Schüler ein).
     *
     * @param  array<string, mixed>  $kurse  (wird per Referenz befüllt)
     * @return array<int, array{kurs_key: string, prioritaet: string}>  Zuteilungen extern zugeteilter Schüler
     */
    private function zuteilungExterneKurse(Collection $wahlen, array &$kurse): array
    {
        $zuteilungen = [];

        foreach ($wahlen as $wahl) {
            $erstwahl = $wahl->erstwahl_subject_id;

            foreach ($kurse as $kursKey => $kurs) {
                if ($kurs['ist_extern'] && $kurs['subject_id'] == $erstwahl) {
                    $kurse[$kursKey]['schueler'][] = [
                        'wahl_id'     => $wahl->id,
                        'schueler_id' => $wahl->schueler_id,
                        'prioritaet'  => 'erstwahl',
                    ];
                    $zuteilungen[$wahl->id] = [
                        'kurs_key'   => $kursKey,
                        'prioritaet' => 'erstwahl',
                    ];
                    break; // innere Schleife beenden; äußere foreach läuft weiter
                }
            }
        }

        return $zuteilungen;
    }

    /**
     * Setzt LkWahlen (Zuteilung + Priorität) und löscht LkKurse für den Jahrgang.
     * Wird sowohl von speichereZuteilung() (vor dem Neuanlegen) als auch von
     * resetZuteilung() genutzt.
     *
     * @return array{wahlen_updated: int, kurse_deleted: int}
     */
    private function resetWahlenUndKurse(string $schoolId, string $schuljahr, int $jahrgang): array
    {
        $wahlenUpdated = LkWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->update([
                'zuteilung_lk_kurs_id' => null,
                'prioritaet_erhalten'  => 'keine',
            ]);

        $kurseDeleted = LkKurs::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->delete();

        return ['wahlen_updated' => $wahlenUpdated, 'kurse_deleted' => $kurseDeleted];
    }

    /**
     * Berechne Statistiken für Zuteilungsergebnis
     */
    private function berechneZuteilungsStatistik(array $zuteilungen, int $totalSchueler): array
    {
        $gesamtZugeteilt = count($zuteilungen);
        $total = $totalSchueler;
        $erstwahl = 0;
        $zweitwahl = 0;
        $keine = 0; // Zugewiesene "Keine"
        
        foreach ($zuteilungen as $zuteilung) {
            switch ($zuteilung['prioritaet']) {
                case 'erstwahl':
                    $erstwahl++;
                    break;
                case 'zweitwahl':
                    $zweitwahl++;
                    break;
                case 'keine':
                    $keine++;
                    break;
            }
        }
        
        // Nicht zugeteilte Schüler zählen auch zu "Keine Wahl erhalten"
        // Also "Gesamt - Zugeteilt" + "explizit als 'keine' Zugeteilt"
        $nichtZugeteilt = $total - $gesamtZugeteilt;
        $keineTotal = $keine + $nichtZugeteilt;
        
        return [
            'gesamt_schueler' => $total,
            'gesamt_zugeteilt' => $gesamtZugeteilt,
            'erstwahl_erhalten' => $erstwahl,
            'zweitwahl_erhalten' => $zweitwahl,
            'keine_wahl_erhalten' => $keineTotal,
            'nicht_zugeteilt' => $nichtZugeteilt,
            'erstwahl_prozent' => $total > 0 ? round(($erstwahl / $total) * 100, 1) : 0,
            'zweitwahl_prozent' => $total > 0 ? round(($zweitwahl / $total) * 100, 1) : 0,
            'keine_wahl_prozent' => $total > 0 ? round(($keineTotal / $total) * 100, 1) : 0,
        ];
    }
    
    /**
     * Speichere Zuteilung in Datenbank
     */
    public function speichereZuteilung(string $schoolId, string $schuljahr, int $jahrgang, array $geplanteKurse, array $zuteilungen): array
    {
        DB::beginTransaction();
        
        try {
            // Schritt 1: Alte Zuteilung und Kurse zurücksetzen
            $this->resetWahlenUndKurse($schoolId, $schuljahr, $jahrgang);
            
            // Schritt 2: Neue Kurse anlegen
            $kursMapping = []; // kurs_key => kurs_id
            
            foreach ($geplanteKurse as $kurs) {
                $neuerKurs = LkKurs::create([
                    'schuljahr' => $schuljahr,
                    'jahrgang' => $jahrgang,
                    'subject_id' => $kurs['subject_id'],
                    'kurs_nummer' => $kurs['kurs_nummer'],
                    'ist_extern' => $kurs['ist_extern'],
                    'lehrer_id' => null,
                    'notizen' => null,
                ]);
                
                $kursKey = $kurs['subject_id'] . '_' . $kurs['kurs_nummer'];
                $kursMapping[$kursKey] = $neuerKurs->id;
            }
            
            // Schritt 3: Zuteilungen speichern
            foreach ($zuteilungen as $wahlId => $zuteilung) {
                $kursId = $kursMapping[$zuteilung['kurs_key']] ?? null;
                
                if ($kursId) {
                    LkWahl::where('id', $wahlId)->update([
                        'zuteilung_lk_kurs_id' => $kursId,
                        'prioritaet_erhalten' => $zuteilung['prioritaet'],
                    ]);
                }
            }
            
            DB::commit();
            
            Log::info("LK Zuteilung gespeichert", [
                'school_id' => $schoolId,
                'schuljahr' => $schuljahr,
                'jahrgang' => $jahrgang,
                'kurse' => count($geplanteKurse),
                'zuteilungen' => count($zuteilungen),
            ]);
            
            return [
                'success' => true,
                'kurse_angelegt' => count($geplanteKurse),
                'zuteilungen_gespeichert' => count($zuteilungen),
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("LK Zuteilung Speichern fehlgeschlagen", [
                'school_id' => $schoolId,
                'schuljahr' => $schuljahr,
                'jahrgang' => $jahrgang,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Setze Zuteilung zurück
     */
    public function resetZuteilung(string $schoolId, string $schuljahr, int $jahrgang): array
    {
        DB::beginTransaction();
        
        try {
            ['wahlen_updated' => $wahlenUpdated, 'kurse_deleted' => $kurseDeleted] =
                $this->resetWahlenUndKurse($schoolId, $schuljahr, $jahrgang);
            
            DB::commit();
            
            Log::info("LK Zuteilung zurückgesetzt", [
                'school_id' => $schoolId,
                'schuljahr' => $schuljahr,
                'jahrgang' => $jahrgang,
                'wahlen_updated' => $wahlenUpdated,
                'kurse_deleted' => $kurseDeleted,
            ]);
            
            return [
                'success' => true,
                'wahlen_zurueckgesetzt' => $wahlenUpdated,
                'kurse_geloescht' => $kurseDeleted,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("LK Zuteilung Reset fehlgeschlagen", [
                'school_id' => $schoolId,
                'schuljahr' => $schuljahr,
                'jahrgang' => $jahrgang,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
