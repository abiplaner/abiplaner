<?php

namespace App\Services;

use App\Models\WseminarWahl;
use App\Models\WseminarKurs;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WseminarZuteilungsService
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
        $wahlen = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['erstwahl', 'zweitwahl', 'drittwahl'])
            ->get();
        
        // Ermittle Gesamtzahl der Schüler im Jahrgang
        // Für Q11 (Jahrgang 11) suchen wir nach Schülern in Klassen wie "11a", "11b" etc.
        $jahrgangPrefix = (string)($jahrgang);
        $gesamtSchueler = \App\Models\Student::where('class_name', 'LIKE', $jahrgangPrefix . '%')
            ->count();
        
        $seminarStatistik = [];
        
        // Erst-, Zweit- und Drittwahlen zählen
        foreach ($wahlen as $wahl) {
            // Erstwahl
            $erstwahl = $wahl->erstwahl;
            if ($erstwahl) {
                $seminarId = $erstwahl->id;
                $seminarTitel = $erstwahl->titel;
                $subjectName = $erstwahl->subject->name ?? 'Unbekannt';
                
                if (!isset($seminarStatistik[$seminarId])) {
                    $seminarStatistik[$seminarId] = [
                        'wseminar_kurs_id' => $seminarId,
                        'titel' => $seminarTitel,
                        'subject_name' => $subjectName,
                        'erstwahlen' => 0,
                        'zweitwahlen' => 0,
                        'drittwahlen' => 0,
                        'gesamt' => 0,
                    ];
                }
                
                $seminarStatistik[$seminarId]['erstwahlen']++;
                $seminarStatistik[$seminarId]['gesamt']++;
            }
            
            // Zweitwahl
            $zweitwahl = $wahl->zweitwahl;
            if ($zweitwahl) {
                $seminarId = $zweitwahl->id;
                $seminarTitel = $zweitwahl->titel;
                $subjectName = $zweitwahl->subject->name ?? 'Unbekannt';
                
                if (!isset($seminarStatistik[$seminarId])) {
                    $seminarStatistik[$seminarId] = [
                        'wseminar_kurs_id' => $seminarId,
                        'titel' => $seminarTitel,
                        'subject_name' => $subjectName,
                        'erstwahlen' => 0,
                        'zweitwahlen' => 0,
                        'drittwahlen' => 0,
                        'gesamt' => 0,
                    ];
                }
                
                $seminarStatistik[$seminarId]['zweitwahlen']++;
                $seminarStatistik[$seminarId]['gesamt']++;
            }
            
            // Drittwahl
            $drittwahl = $wahl->drittwahl;
            if ($drittwahl) {
                $seminarId = $drittwahl->id;
                $seminarTitel = $drittwahl->titel;
                $subjectName = $drittwahl->subject->name ?? 'Unbekannt';
                
                if (!isset($seminarStatistik[$seminarId])) {
                    $seminarStatistik[$seminarId] = [
                        'wseminar_kurs_id' => $seminarId,
                        'titel' => $seminarTitel,
                        'subject_name' => $subjectName,
                        'erstwahlen' => 0,
                        'zweitwahlen' => 0,
                        'drittwahlen' => 0,
                        'gesamt' => 0,
                    ];
                }
                
                $seminarStatistik[$seminarId]['drittwahlen']++;
                $seminarStatistik[$seminarId]['gesamt']++;
            }
        }
        
        // Sortiere nach Erstwahlen (absteigend)
        usort($seminarStatistik, function($a, $b) {
            return $b['erstwahlen'] <=> $a['erstwahlen'];
        });
        
        // Berechne Anzahl der Schüler mit Wahlen (= Summe aller Erstwahlen)
        $schuelerMitWahlen = array_sum(array_column($seminarStatistik, 'erstwahlen'));
        
        return [
            'anzahl_schueler' => $wahlen->count(),
            'gesamt_schueler_jahrgang' => $gesamtSchueler,
            'schueler_mit_wahlen' => $schuelerMitWahlen,
            'seminar_statistik' => $seminarStatistik,
        ];
    }
    
    /**
     * Phase 2: Zuteilung der Schüler zu W-Seminaren
     * 
     * Strategie:
     * 1. Sortiere Schüler nach Zufallsprinzip (fair)
     * 2. Für jeden Schüler:
     *    - Versuche Erstwahl (wenn Platz frei)
     *    - Sonst Zweitwahl (wenn Platz frei)
     *    - Sonst Drittwahl (wenn Platz frei)
     *    - Sonst: nicht zugeteilt
     * 
     * @param string $schoolId
     * @param string $schuljahr
     * @param int $jahrgang
     * @param string $strategie ('niemand_ohne_wahl', 'viele_erstwahlen', 'zufall', 'optimiert')
     * @param int|null $targetCourseCount
     * @return array
     */
    public function teileZu(string $schoolId, string $schuljahr, int $jahrgang, string $strategie = 'optimiert', ?int $targetCourseCount = null): array
    {
        // Lade alle Wahlen
        $wahlen = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->with(['erstwahl', 'zweitwahl', 'drittwahl', 'schueler'])
            ->get();
        
        // Lade alle Seminare
        $seminare = WseminarKurs::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->get()
            ->keyBy('id');
        
        // Tracking: Wie viele Schüler sind in jedem Seminar?
        $seminarBelegung = [];
        foreach ($seminare as $seminar) {
            $seminarBelegung[$seminar->id] = 0;
        }
        
        // Zuteilungen und Statistiken
        $zuteilungen = [];
        $statistik = [
            'erstwahl' => 0,
            'zweitwahl' => 0,
            'drittwahl' => 0,
            'keine' => 0,
        ];

        // Strategie-spezifische Logik
        
        // 0. Aktive Kurse bestimmen (basierend auf Target Count)
        $activeCourseIds = $seminare->keys()->toArray(); // Default: Alle
        $courseDemand = [];

        if ($targetCourseCount) {
             // Nachfrage berechnen (Summe 1., 2., 3. Wahl)
            foreach ($seminare as $id => $s) {
                $courseDemand[$id] = 0;
            }
            
            foreach ($wahlen as $wahl) {
                if ($wahl->erstwahl_wseminar_id) $courseDemand[$wahl->erstwahl_wseminar_id]++;
                if ($wahl->zweitwahl_wseminar_id) $courseDemand[$wahl->zweitwahl_wseminar_id]++;
                if ($wahl->drittwahl_wseminar_id) $courseDemand[$wahl->drittwahl_wseminar_id]++;
            }
            
            // Sortieren und Limitieren
            arsort($courseDemand);
            $activeCourseIds = array_slice(array_keys($courseDemand), 0, $targetCourseCount);
        }

        if ($strategie === 'optimiert') {
            
            // Zufällige Reihenfolge für Fairness bei gleicher Priorität
            $wahlen = $wahlen->shuffle();
            
            // Demand array neu aufbauen falls nicht passiert (falls targetCourseCount null war, aber optimiert gewählt - sollte eigentlich validiert sein, aber sicher ist sicher)
            if (empty($courseDemand)) {
                 foreach ($seminare as $id => $s) {
                    $courseDemand[$id] = 0;
                }
                foreach ($wahlen as $wahl) {
                    if ($wahl->erstwahl_wseminar_id) $courseDemand[$wahl->erstwahl_wseminar_id]++;
                    if ($wahl->zweitwahl_wseminar_id) $courseDemand[$wahl->zweitwahl_wseminar_id]++;
                    if ($wahl->drittwahl_wseminar_id) $courseDemand[$wahl->drittwahl_wseminar_id]++;
                }
            }
            
            // 4. Unterscheidung: "Schwache" Kurse (< 15 Demand) und "Starke" Kurse (>= 15)
            $weakCourseIds = [];
            $strongCourseIds = [];
            
            foreach ($activeCourseIds as $cid) {
                if ($courseDemand[$cid] < 15) {
                    $weakCourseIds[] = $cid;
                } else {
                    $strongCourseIds[] = $cid;
                }
            }
            
            // Tracking für bereits zugeteilte Schüler
            $assignedStudentIds = [];
            
            // 5. PHASE 1: Schwache Kurse füllen (Survival Mode)
            // Hier nehmen wir alle Kandidaten, damit der Kurs zustande kommt.
            foreach ($weakCourseIds as $courseId) {
                $candidates = $wahlen->filter(function($w) use ($courseId, $assignedStudentIds) {
                    if (in_array($w->schueler_id, $assignedStudentIds)) return false;
                    return $w->erstwahl_wseminar_id == $courseId 
                        || $w->zweitwahl_wseminar_id == $courseId 
                        || $w->drittwahl_wseminar_id == $courseId;
                });
                
                // Priorisiere nach Wahl, aber nimm im Zweifel alle
                $candidates = $candidates->sortBy(function($w) use ($courseId) {
                    if ($w->erstwahl_wseminar_id == $courseId) return 1;
                    if ($w->zweitwahl_wseminar_id == $courseId) return 2;
                    return 3;
                });
                
                foreach ($candidates as $wahl) {
                    // Kein Limit-Check hier? Oder doch? 
                    // Da Demand < 15, sollte es passen. Aber sicherheitshalber:
                    if ($seminarBelegung[$courseId] >= $seminare->get($courseId)->max_teilnehmer) break;
                    
                    $prio = 'keine';
                    if ($wahl->erstwahl_wseminar_id == $courseId) $prio = 'erstwahl';
                    elseif ($wahl->zweitwahl_wseminar_id == $courseId) $prio = 'zweitwahl';
                    elseif ($wahl->drittwahl_wseminar_id == $courseId) $prio = 'drittwahl';
                    
                    $zuteilungen[] = [
                        'wahl_id' => $wahl->id,
                        'schueler_id' => $wahl->schueler_id,
                        'wseminar_kurs_id' => $courseId,
                        'prioritaet' => $prio,
                    ];
                    
                    $seminarBelegung[$courseId]++;
                    $statistik[$prio]++;
                    $assignedStudentIds[] = $wahl->schueler_id;
                }
            }
            
            // 6. PHASE 2: Starke Kurse füllen (First Choice Priority)
            // Restliche Schüler holen
            $unassignedWahlen = $wahlen->filter(function($w) use ($assignedStudentIds) {
                return !in_array($w->schueler_id, $assignedStudentIds);
            });
            
            // Sortiere Schüler nach "Anzahl verfügbarer Optionen" (Constrained First)
            $unassignedWahlen = $unassignedWahlen->sortBy(function($w) use ($strongCourseIds) {
                $validOptions = 0;
                if (in_array($w->erstwahl_wseminar_id, $strongCourseIds)) $validOptions++;
                if (in_array($w->zweitwahl_wseminar_id, $strongCourseIds)) $validOptions++;
                if (in_array($w->drittwahl_wseminar_id, $strongCourseIds)) $validOptions++;
                return $validOptions == 0 ? 999 : $validOptions;
            });
            
            $studentQueue = $unassignedWahlen->values()->all();
            
            // Helper function to assign
            $tryAssign = function($wahl, $courseId, $prio) use (&$zuteilungen, &$seminarBelegung, &$statistik, &$assignedStudentIds, $seminare) {
                if ($seminarBelegung[$courseId] < $seminare->get($courseId)->max_teilnehmer) {
                    $zuteilungen[] = [
                        'wahl_id' => $wahl->id,
                        'schueler_id' => $wahl->schueler_id,
                        'wseminar_kurs_id' => $courseId,
                        'prioritaet' => $prio,
                    ];
                    $seminarBelegung[$courseId]++;
                    $statistik[$prio]++;
                    $assignedStudentIds[] = $wahl->schueler_id;
                    return true;
                }
                return false;
            };
            
            // Durchlauf 1: Erstwahlen
            foreach ($studentQueue as $wahl) {
                if (in_array($wahl->schueler_id, $assignedStudentIds)) continue;
                if ($wahl->erstwahl_wseminar_id && in_array($wahl->erstwahl_wseminar_id, $strongCourseIds)) {
                    $tryAssign($wahl, $wahl->erstwahl_wseminar_id, 'erstwahl');
                }
            }
            
            // Durchlauf 2: Zweitwahlen
            foreach ($studentQueue as $wahl) {
                if (in_array($wahl->schueler_id, $assignedStudentIds)) continue;
                if ($wahl->zweitwahl_wseminar_id && in_array($wahl->zweitwahl_wseminar_id, $strongCourseIds)) {
                    $tryAssign($wahl, $wahl->zweitwahl_wseminar_id, 'zweitwahl');
                }
            }
            
            // Durchlauf 3: Drittwahlen
            foreach ($studentQueue as $wahl) {
                if (in_array($wahl->schueler_id, $assignedStudentIds)) continue;
                if ($wahl->drittwahl_wseminar_id && in_array($wahl->drittwahl_wseminar_id, $strongCourseIds)) {
                    $tryAssign($wahl, $wahl->drittwahl_wseminar_id, 'drittwahl');
                }
            }
            
            // 7. PHASE 3: Nach-Optimierung (Verschieben um Unversorgte unterzubringen)
            // Wir versuchen, Unversorgte in volle Kurse zu bringen, indem wir dort Platz machen
            
            $stillUnassigned = $wahlen->filter(function($w) use ($assignedStudentIds) {
                return !in_array($w->schueler_id, $assignedStudentIds);
            });
            
            foreach ($stillUnassigned as $unassignedWahl) {
                // Versuche alle 3 Wünsche des Unversorgten
                $wishes = [
                    ['id' => $unassignedWahl->erstwahl_wseminar_id, 'prio' => 'erstwahl'],
                    ['id' => $unassignedWahl->zweitwahl_wseminar_id, 'prio' => 'zweitwahl'],
                    ['id' => $unassignedWahl->drittwahl_wseminar_id, 'prio' => 'drittwahl'],
                ];
                
                $assigned = false;
                
                foreach ($wishes as $wish) {
                    if ($assigned) break;
                    $targetCourseId = $wish['id'];
                    if (!$targetCourseId || !in_array($targetCourseId, $activeCourseIds)) continue;
                    
                    // Ist Platz? (Sollte eigentlich nicht sein, sonst wäre er oben zugeteilt worden, außer es war ein Weak Course der voll ist?)
                    if ($seminarBelegung[$targetCourseId] < $seminare->get($targetCourseId)->max_teilnehmer) {
                        $tryAssign($unassignedWahl, $targetCourseId, $wish['prio']);
                        $assigned = true;
                        break;
                    }
                    
                    // KEIN Platz -> Suche Tauschpartner im Zielkurs
                    // Suche jemanden in targetCourseId, der in einen ANDEREN Kurs wechseln kann, der noch Platz hat
                    
                    // Finde aktuelle Zuteilungen für diesen Kurs
                    foreach ($zuteilungen as $key => $zuteilung) {
                        if ($zuteilung['wseminar_kurs_id'] == $targetCourseId) {
                            // Kandidaten-Wahl laden
                            $kandidatWahl = $wahlen->firstWhere('id', $zuteilung['wahl_id']);
                            
                            // Check alternativen für Kandidat
                            $altCourses = [
                                ['id' => $kandidatWahl->erstwahl_wseminar_id, 'prio' => 'erstwahl'],
                                ['id' => $kandidatWahl->zweitwahl_wseminar_id, 'prio' => 'zweitwahl'],
                                ['id' => $kandidatWahl->drittwahl_wseminar_id, 'prio' => 'drittwahl'],
                            ];
                            
                            foreach ($altCourses as $alt) {
                                $altId = $alt['id'];
                                if (!$altId || $altId == $targetCourseId || !in_array($altId, $activeCourseIds)) continue;
                                
                                // Hat Alternative Platz?
                                if ($seminarBelegung[$altId] < $seminare->get($altId)->max_teilnehmer) {
                                    // SWAP DURCHFÜHREN!
                                    
                                    // 1. Kandidat verschieben
                                    $zuteilungen[$key]['wseminar_kurs_id'] = $altId;
                                    $zuteilungen[$key]['prioritaet'] = $alt['prio']; // Update Priority Status
                                    
                                    // Update Stats (altes weg, neues dazu? Kompliziert zu tracken, wir ignorieren Statistik-Update im Detail erstmal oder fixen es am Ende)
                                    // Einfachheitshalber: Belegung updaten
                                    $seminarBelegung[$targetCourseId]--;
                                    $seminarBelegung[$altId]++;
                                    
                                    // 2. Unversorgten zuteilen
                                    $zuteilungen[] = [
                                        'wahl_id' => $unassignedWahl->id,
                                        'schueler_id' => $unassignedWahl->schueler_id,
                                        'wseminar_kurs_id' => $targetCourseId,
                                        'prioritaet' => $wish['prio'],
                                    ];
                                    $seminarBelegung[$targetCourseId]++;
                                    $assignedStudentIds[] = $unassignedWahl->schueler_id;
                                    
                                    $assigned = true;
                                    break 2; // Break Candidates & Wishes loops
                                }
                            }
                        }
                    }
                }
            }

            // Statistik neu berechnen am Ende (da Swaps Prio geändert haben könnten)
            $statistik = ['erstwahl' => 0, 'zweitwahl' => 0, 'drittwahl' => 0, 'keine' => 0, 'leer' => 0];
            foreach ($zuteilungen as $z) {
                $statistik[$z['prioritaet']]++;
            }
            // Zähle "Nicht zugeteilt" vs "Leer" (Schüler ohne Wünsche wurden im Loop übersprungen)
            foreach ($wahlen as $wahl) {
                if (in_array($wahl->schueler_id, $assignedStudentIds)) continue;
                if (!$wahl->erstwahl_wseminar_id && !$wahl->zweitwahl_wseminar_id && !$wahl->drittwahl_wseminar_id) {
                    $statistik['leer']++;
                } else {
                    $statistik['keine']++;
                }
            }
            
        } else {
            // Standard-Logik: Viele Erstwahlen
            // Zufällige Reihenfolge für Fairness
            $wahlen = $wahlen->shuffle();
            
            $statistik['leer'] = 0; // Init
            $assignedStudentIds = [];

            // 1. DURCHLAUF: Greedy Verteilung (Erstwahlen zuerst)
            foreach ($wahlen as $wahl) {
                // Check if empty choice
                 if (!$wahl->erstwahl_wseminar_id && !$wahl->zweitwahl_wseminar_id && !$wahl->drittwahl_wseminar_id) {
                    $statistik['leer']++;
                    continue; 
                }

                $zugeteilt = false;
                
                // Versuche Erstwahl
                if ($wahl->erstwahl_wseminar_id && in_array($wahl->erstwahl_wseminar_id, $activeCourseIds)) {
                    $seminarId = $wahl->erstwahl_wseminar_id;
                    $seminar = $seminare->get($seminarId);
                    
                    if ($seminar && $seminarBelegung[$seminarId] < $seminar->max_teilnehmer) {
                        $zuteilungen[] = [
                            'wahl_id' => $wahl->id,
                            'schueler_id' => $wahl->schueler_id,
                            'wseminar_kurs_id' => $seminarId,
                            'prioritaet' => 'erstwahl',
                        ];
                        $seminarBelegung[$seminarId]++;
                        $statistik['erstwahl']++;
                        $zugeteilt = true;
                        $assignedStudentIds[] = $wahl->schueler_id;
                    }
                }
                
                // Versuche Zweitwahl
                if (!$zugeteilt && $wahl->zweitwahl_wseminar_id && in_array($wahl->zweitwahl_wseminar_id, $activeCourseIds)) {
                    $seminarId = $wahl->zweitwahl_wseminar_id;
                    $seminar = $seminare->get($seminarId);
                    
                    if ($seminar && $seminarBelegung[$seminarId] < $seminar->max_teilnehmer) {
                        $zuteilungen[] = [
                            'wahl_id' => $wahl->id,
                            'schueler_id' => $wahl->schueler_id,
                            'wseminar_kurs_id' => $seminarId,
                            'prioritaet' => 'zweitwahl',
                        ];
                        $seminarBelegung[$seminarId]++;
                        $statistik['zweitwahl']++;
                        $zugeteilt = true;
                        $assignedStudentIds[] = $wahl->schueler_id;
                    }
                }
                
                // Versuche Drittwahl
                if (!$zugeteilt && $wahl->drittwahl_wseminar_id && in_array($wahl->drittwahl_wseminar_id, $activeCourseIds)) {
                    $seminarId = $wahl->drittwahl_wseminar_id;
                    $seminar = $seminare->get($seminarId);
                    
                    if ($seminar && $seminarBelegung[$seminarId] < $seminar->max_teilnehmer) {
                        $zuteilungen[] = [
                            'wahl_id' => $wahl->id,
                            'schueler_id' => $wahl->schueler_id,
                            'wseminar_kurs_id' => $seminarId,
                            'prioritaet' => 'drittwahl',
                        ];
                        $seminarBelegung[$seminarId]++;
                        $statistik['drittwahl']++;
                        $zugeteilt = true;
                        $assignedStudentIds[] = $wahl->schueler_id;
                    }
                }
            }
            
            // 2. DURCHLAUF: "Rescue" Phase - Versuche Unversorgte durch Tausch unterzubringen
            $unassignedWahlen = $wahlen->filter(function($w) use ($assignedStudentIds) {
                // Nur Schüler betrachten, die Wünsche hatten, aber nicht zugeteilt wurden
                $hasWishes = $w->erstwahl_wseminar_id || $w->zweitwahl_wseminar_id || $w->drittwahl_wseminar_id;
                return !in_array($w->schueler_id, $assignedStudentIds) && $hasWishes;
            });

            foreach ($unassignedWahlen as $unassignedWahl) {
                // Versuche alle 3 Wünsche des Unversorgten
                $wishes = [
                    ['id' => $unassignedWahl->erstwahl_wseminar_id, 'prio' => 'erstwahl'],
                    ['id' => $unassignedWahl->zweitwahl_wseminar_id, 'prio' => 'zweitwahl'],
                    ['id' => $unassignedWahl->drittwahl_wseminar_id, 'prio' => 'drittwahl'],
                ];
                
                $assigned = false;
                
                foreach ($wishes as $wish) {
                    $targetCourseId = $wish['id'];
                    if (!$targetCourseId || !in_array($targetCourseId, $activeCourseIds)) continue;
                    // Simple Check: Ist zufällig jetzt Platz? (Unwahrscheinlich, aber möglich)
                    if ($seminarBelegung[$targetCourseId] < $seminare->get($targetCourseId)->max_teilnehmer) {
                         $zuteilungen[] = [
                            'wahl_id' => $unassignedWahl->id,
                            'schueler_id' => $unassignedWahl->schueler_id,
                            'wseminar_kurs_id' => $targetCourseId,
                            'prioritaet' => $wish['prio'],
                        ];
                        $seminarBelegung[$targetCourseId]++;
                        $statistik[$wish['prio']]++;
                        $assignedStudentIds[] = $unassignedWahl->schueler_id;
                        $assigned = true;
                        break;
                    }
                    
                    // SWAP LOGIK: Suche Tauschpartner im Zielkurs
                    // Wir suchen jemanden in diesem vollen Kurs, der in einen seiner Alternativkurse wechseln KANN
                    
                    foreach ($zuteilungen as $key => $zuteilung) {
                        if ($zuteilung['wseminar_kurs_id'] == $targetCourseId) {
                            // Kandidaten-Wahl laden
                            $kandidatWahl = $wahlen->firstWhere('id', $zuteilung['wahl_id']);
                            if (!$kandidatWahl) continue;

                            // Check alternativen für Kandidat
                            $altCourses = [
                                ['id' => $kandidatWahl->erstwahl_wseminar_id, 'prio' => 'erstwahl'],
                                ['id' => $kandidatWahl->zweitwahl_wseminar_id, 'prio' => 'zweitwahl'],
                                ['id' => $kandidatWahl->drittwahl_wseminar_id, 'prio' => 'drittwahl'],
                            ];
                            
                            foreach ($altCourses as $alt) {
                                $altId = $alt['id'];
                                // Darf nicht der gleiche Kurs sein und muss aktiv sein
                                if (!$altId || $altId == $targetCourseId || !in_array($altId, $activeCourseIds)) continue;
                                
                                // Hat Alternative Platz?
                                if ($seminarBelegung[$altId] < $seminare->get($altId)->max_teilnehmer) {
                                    // BINGO! Tausch durchführen
                                    
                                    // 1. Kandidat umbuchen
                                    // Statistik update für Kandidat (alt)
                                    $oldPrio = $zuteilungen[$key]['prioritaet'];
                                    $statistik[$oldPrio]--;
                                    
                                    // Werte ändern
                                    $zuteilungen[$key]['wseminar_kurs_id'] = $altId;
                                    $zuteilungen[$key]['prioritaet'] = $alt['prio'];
                                    
                                    // Statistik update für Kandidat (neu)
                                    $statistik[$alt['prio']]++;
                                    
                                    // Belegungen anpassen
                                    $seminarBelegung[$targetCourseId]--; // Wird gleich wieder erhöht
                                    $seminarBelegung[$altId]++;
                                    
                                    // 2. Unversorgten Schüler zuteilen
                                    $zuteilungen[] = [
                                        'wahl_id' => $unassignedWahl->id,
                                        'schueler_id' => $unassignedWahl->schueler_id,
                                        'wseminar_kurs_id' => $targetCourseId, // Der jetzt freie Platz
                                        'prioritaet' => $wish['prio'],
                                    ];
                                    $seminarBelegung[$targetCourseId]++; // Wieder voll
                                    $statistik[$wish['prio']]++;
                                    $assignedStudentIds[] = $unassignedWahl->schueler_id;
                                    
                                    $assigned = true;
                                    break 3; // Break candidate loop, wishes loop, and continue outer unassigned loop
                                }
                            }
                        }
                    }
                }
            }

            // Statistik "Nicht zugeteilt" berechnen
            // Schüler ohne jegliche Wünsche wurden bereits oben als 'leer' gezählt,
            // alle hier verbleibenden hatten Wünsche, wurden aber nicht zugeteilt.
            foreach ($wahlen as $wahl) {
                if (in_array($wahl->schueler_id, $assignedStudentIds)) continue;
                $statistik['keine']++;
            }
        }
        
        return [
            'zuteilungen' => $zuteilungen,
            'statistik' => $statistik,
            'seminar_belegung' => $seminarBelegung,
            'active_course_ids' => $activeCourseIds,
        ];
    }
    
    /**
     * Phase 3: Zuteilung in Datenbank speichern
     */
    public function speichereZuteilung(array $zuteilungen): int
    {
        $gespeichert = 0;
        
        DB::beginTransaction();
        
        try {
            foreach ($zuteilungen as $zuteilung) {
                WseminarWahl::where('id', $zuteilung['wahl_id'])
                    ->update([
                        'zuteilung_wseminar_kurs_id' => $zuteilung['wseminar_kurs_id'],
                        'prioritaet_erhalten' => $zuteilung['prioritaet'],
                    ]);
                
                $gespeichert++;
            }
            
            DB::commit();
            
            Log::info("W-Seminar Zuteilung gespeichert", [
                'anzahl' => $gespeichert,
            ]);
            
            return $gespeichert;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("W-Seminar Zuteilung speichern fehlgeschlagen", [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Zuteilung zurücksetzen
     */
    public function resetZuteilung(string $schoolId, string $schuljahr, int $jahrgang): int
    {
        $affected = WseminarWahl::query()
            ->where('schuljahr', $schuljahr)
            ->where('jahrgang', $jahrgang)
            ->update([
                'zuteilung_wseminar_kurs_id' => null,
                'prioritaet_erhalten' => 'keine',
            ]);
        
        Log::info("W-Seminar Zuteilung zurückgesetzt", [
            'school_id' => $schoolId,
            'schuljahr' => $schuljahr,
            'jahrgang' => $jahrgang,
            'affected' => $affected,
        ]);
        
        return $affected;
    }
}
