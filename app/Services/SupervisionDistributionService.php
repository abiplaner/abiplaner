<?php

namespace App\Services;

use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamAssignment;
use App\Models\Teacher;
use App\Models\Supervision;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SupervisionDistributionService
{
    /**
     * Automatische Verteilung der Aufsichten für einen Prüfungstermin
     * 
     * @param WrittenExamYearDate $examDate Der Prüfungstermin
     * @param string $schoolId Die Schul-ID
     * @return array ['success' => bool, 'message' => string, 'statistics' => array]
     */
    public function distributeForExamDate(WrittenExamYearDate $examDate, string $schoolId): array
    {
        try {
            DB::beginTransaction();

            $assignments = WrittenExamAssignment::where('written_exam_year_date_id', $examDate->id)
                ->with('room')
                ->get();

            if ($assignments->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Keine Raumzuordnungen für diesen Prüfungstermin gefunden.',
                    'statistics' => []
                ];
            }

            // Hole verfügbare Lehrer
            $availableTeachers = $this->getAvailableTeachers($schoolId, Carbon::parse($examDate->exam_date));
            
            if ($availableTeachers->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Keine verfügbaren Lehrer für diesen Termin gefunden.',
                    'statistics' => []
                ];
            }

            // Sortiere Lehrer nach aktueller Belastung
            $sortedTeachers = $this->sortTeachersByLoad($availableTeachers, Carbon::parse($examDate->exam_date));

            // Berechne benötigte Aufsichten
            $roomSupervisions = $assignments->count() * 2; // 2 Aufsichten pro Raum
            $corridorSupervisions = max(1, (int) ceil($assignments->count() / 3)); // 1 Gangaufsicht pro 3 Räume
            $totalNeeded = $roomSupervisions + $corridorSupervisions;

            // Prüfe ob genug Lehrer vorhanden sind
            if ($sortedTeachers->count() < $totalNeeded) {
                return [
                    'success' => false,
                    'message' => "Nicht genügend verfügbare Lehrer. Benötigt: {$totalNeeded}, Verfügbar: {$sortedTeachers->count()}",
                    'statistics' => [
                        'needed' => $totalNeeded,
                        'available' => $sortedTeachers->count()
                    ]
                ];
            }

            // Verteile Raumaufsichten
            $teacherIndex = 0;
            $assignedSupervisions = [];
            
            foreach ($assignments as $assignment) {
                // Weise 2 Raumaufsichten zu
                for ($i = 0; $i < 2; $i++) {
                    $teacher = $sortedTeachers[$teacherIndex % $sortedTeachers->count()];
                    
                    // Erstelle Aufsicht
                    $supervision = Supervision::create([
                        'teacher_id' => $teacher->id,
                        'room_id' => $assignment->room_id,
                        'written_exam_year_date_id' => $examDate->id,
                        'supervision_type' => 'room',
                        'written_exam_assignment_id' => $assignment->id,
                        'notes' => "Raumaufsicht - {$assignment->room->room_number}"
                    ]);
                    
                    $assignedSupervisions[] = [
                        'teacher' => $teacher->full_name,
                        'type' => 'room',
                        'room' => $assignment->room->room_number
                    ];
                    
                    $teacherIndex++;
                }
            }

            // Verteile Gangaufsichten
            for ($i = 0; $i < $corridorSupervisions; $i++) {
                $teacher = $sortedTeachers[$teacherIndex % $sortedTeachers->count()];
                
                // Erstelle Gangaufsicht (nicht an einen spezifischen Raum gebunden)
                $supervision = Supervision::create([
                    'teacher_id' => $teacher->id,
                    'written_exam_year_date_id' => $examDate->id,
                    'supervision_type' => 'corridor',
                    'written_exam_assignment_id' => null,
                    'notes' => 'Gangaufsicht'
                ]);
                
                $assignedSupervisions[] = [
                    'teacher' => $teacher->full_name,
                    'type' => 'corridor',
                    'room' => '-'
                ];
                
                $teacherIndex++;
            }

            DB::commit();

            Log::info('Aufsichtsverteilung abgeschlossen', [
                'exam_date_id' => $examDate->id,
                'school_id' => $schoolId,
                'total_assigned' => count($assignedSupervisions)
            ]);

            return [
                'success' => true,
                'message' => "Erfolgreich {$totalNeeded} Aufsichten verteilt.",
                'statistics' => [
                    'room_supervisions' => $roomSupervisions,
                    'corridor_supervisions' => $corridorSupervisions,
                    'total_assigned' => count($assignedSupervisions),
                    'teachers_used' => $sortedTeachers->count(),
                    'assignments' => $assignedSupervisions
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fehler bei Aufsichtsverteilung', [
                'exam_date_id' => $examDate->id,
                'school_id' => $schoolId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Fehler bei der Aufsichtsverteilung: ' . $e->getMessage(),
                'statistics' => []
            ];
        }
    }

    /**
     * Hole alle verfügbaren Lehrer für einen bestimmten Tag
     * 
     * @param string $schoolId Die Schul-ID
     * @param Carbon $date Das Datum
     * @return Collection Verfügbare Lehrer
     */
    public function getAvailableTeachers(string $schoolId, Carbon $date): Collection
    {
        // Lade alle relevanten IDs vorab, um Cross-Database joins (Tenant -> Central) zu vermeiden
        $writtenExamYearDateIds = \App\Models\WrittenExamYearDate::where('exam_date', $date->format('Y-m-d'))->pluck('id');
        $assignmentIds = \App\Models\WrittenExamAssignment::whereIn('written_exam_year_date_id', $writtenExamYearDateIds)->pluck('id');
        // time_slots ist Tenant
        $timeSlotIds = \App\Models\TimeSlot::where('date', $date->format('Y-m-d'))->pluck('id');

        return Teacher::active()->where('is_available_for_supervision', true)
            ->whereDoesntHave('unavailabilities', function ($query) use ($date) {
                $query->where('teacher_unavailability.date', $date->format('Y-m-d'));
            })
            ->with(['supervisions' => function ($query) use ($writtenExamYearDateIds, $assignmentIds, $timeSlotIds) {
                $query->where(function ($q) use ($writtenExamYearDateIds, $assignmentIds, $timeSlotIds) {
                    $q->whereIn('written_exam_year_date_id', $writtenExamYearDateIds)
                      ->orWhereIn('written_exam_assignment_id', $assignmentIds)
                      ->orWhereIn('time_slot_id', $timeSlotIds);
                });
            }])
            ->get();
    }

    /**
     * Sortiere Lehrer nach ihrer aktuellen Belastung
     * Lehrer mit weniger Aufsichten kommen zuerst
     * Bei gleicher Belastung werden Vollzeitlehrer bevorzugt
     * 
     * @param Collection $teachers Die Lehrer
     * @param Carbon $examDate Das Prüfungsdatum
     * @return Collection Sortierte Lehrer
     */
    public function sortTeachersByLoad(Collection $teachers, Carbon $examDate): Collection
    {
        return $teachers->sortBy(function ($teacher) use ($examDate) {
            // Hole Anzahl der Aufsichten für diesen Monat
            $monthStart = $examDate->copy()->startOfMonth();
            $monthEnd = $examDate->copy()->endOfMonth();
            
            $supervisionCount = Supervision::where('teacher_id', $teacher->id)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->count();
            
            // Gewichtung basierend auf Beschäftigungsumfang
            $weight = match($teacher->employment_type) {
                'full_time' => 1.0,
                'part_time' => 0.7,
                'less_than_half' => 0.5,
                default => 1.0
            };
            
            // Berücksichtige Präferenz-Gewicht (0-100, Standard 50)
            $preferenceWeight = ($teacher->supervision_preference_weight ?? 50) / 100;
            
            // Kombiniere: Je höher die Aufsichtenanzahl, desto höher der Score
            // Je niedriger die Beschäftigungsquote, desto höher der Score (weniger Aufsichten)
            // Je niedriger die Präferenz, desto höher der Score (weniger Aufsichten)
            return ($supervisionCount / $weight) - $preferenceWeight;
        });
    }

    /**
     * Hole Statistiken über die Aufsichtsbelastung für einen Prüfungstermin
     * 
     * @param WrittenExamYearDate $examDate Der Prüfungstermin
     * @param string $schoolId Die Schul-ID
     * @return array Statistiken
     */
    public function getTeacherStatistics(WrittenExamYearDate $examDate, string $schoolId): array
    {
        $date = Carbon::parse($examDate->exam_date);
        $assignmentIds = WrittenExamAssignment::where('written_exam_year_date_id', $examDate->id)->pluck('id');
        
        $supervisions = Supervision::where(function ($query) use ($examDate, $assignmentIds) {
                $query->where('written_exam_year_date_id', $examDate->id)
                      ->orWhereIn('written_exam_assignment_id', $assignmentIds);
            })
            ->with('teacher')
            ->get();
        
        // Gruppiere nach Lehrern
        $byTeacher = $supervisions->groupBy('teacher_id')->map(function ($teacherSupervisions) {
            $teacher = $teacherSupervisions->first()->teacher;
            
            return [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name,
                'employment_type' => $teacher->employment_type,
                'supervision_count' => $teacherSupervisions->count(),
                'room_supervisions' => $teacherSupervisions->where('supervision_type', 'room')->count(),
                'corridor_supervisions' => $teacherSupervisions->where('supervision_type', 'corridor')->count()
            ];
        })->values();
        
        // Gesamtstatistik
        return [
            'total_supervisions' => $supervisions->count(),
            'room_supervisions' => $supervisions->where('supervision_type', 'room')->count(),
            'corridor_supervisions' => $supervisions->where('supervision_type', 'corridor')->count(),
            'teachers_involved' => $byTeacher->count(),
            'by_teacher' => $byTeacher,
            'date' => $date->format('d.m.Y')
        ];
    }

    /**
     * Berechne die erwartete Aufsichtslast basierend auf den Zuordnungen
     * 
     * @param WrittenExamYearDate $examDate Der Prüfungstermin
     * @param string $schoolId Die Schul-ID
     * @return array Erwartete Last
     */
    public function calculateExpectedLoad(WrittenExamYearDate $examDate, string $schoolId): array
    {
        $assignments = WrittenExamAssignment::where('written_exam_year_date_id', $examDate->id)
            ->count();
        
        $roomSupervisions = $assignments * 2;
        $corridorSupervisions = max(1, (int) ceil($assignments / 3));
        $total = $roomSupervisions + $corridorSupervisions;
        
        // Hole verfügbare Lehrer
        $availableTeachers = $this->getAvailableTeachers($schoolId, Carbon::parse($examDate->exam_date));
        
        // Berechne durchschnittliche Last pro Lehrer
        $averageLoadPerTeacher = $availableTeachers->count() > 0 
            ? round($total / $availableTeachers->count(), 2) 
            : 0;
        
        return [
            'rooms' => $assignments,
            'room_supervisions_needed' => $roomSupervisions,
            'corridor_supervisions_needed' => $corridorSupervisions,
            'total_supervisions_needed' => $total,
            'available_teachers' => $availableTeachers->count(),
            'average_load_per_teacher' => $averageLoadPerTeacher,
            'is_feasible' => $availableTeachers->count() >= $total
        ];
    }

    /**
     * Validiere die Aufsichtsverteilung für einen Prüfungstermin
     * 
     * @param WrittenExamYearDate $examDate Der Prüfungstermin
     * @param string $schoolId Die Schul-ID
     * @return array Validierungsergebnisse mit errors und warnings
     */
    public function validateDistribution(WrittenExamYearDate $examDate, string $schoolId): array
    {
        $errors = [];
        $warnings = [];
        
        $assignments = WrittenExamAssignment::where('written_exam_year_date_id', $examDate->id)
            ->with(['supervisions', 'room'])
            ->get();
        
        if ($assignments->isEmpty()) {
            $errors[] = 'Keine Raumzuordnungen vorhanden.';
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }
        
        // Prüfe jede Zuordnung
        foreach ($assignments as $assignment) {
            $roomSupervisions = $assignment->supervisions()
                ->where('supervision_type', 'room')
                ->count();
            
            if ($roomSupervisions < 2) {
                $errors[] = "Raum {$assignment->room->room_number} hat nur {$roomSupervisions} Aufsicht(en). Mindestens 2 erforderlich.";
            } elseif ($roomSupervisions > 2) {
                $warnings[] = "Raum {$assignment->room->room_number} hat {$roomSupervisions} Aufsichten. Empfohlen sind 2.";
            }
        }
        
        $assignmentIds = $assignments->pluck('id');

        // Prüfe Gangaufsichten
        $corridorSupervisions = Supervision::where(function ($query) use ($examDate, $assignmentIds) {
                $query->where('written_exam_year_date_id', $examDate->id)
                      ->orWhereIn('written_exam_assignment_id', $assignmentIds);
            })
            ->where('supervision_type', 'corridor')
            ->count();
        
        $expectedCorridorSupervisions = max(1, (int) ceil($assignments->count() / 3));
        
        if ($corridorSupervisions < $expectedCorridorSupervisions) {
            $warnings[] = "Nur {$corridorSupervisions} Gangaufsicht(en) vorhanden. Empfohlen: {$expectedCorridorSupervisions}.";
        }
        
        // Prüfe auf Lehrer mit zu vielen Aufsichten am selben Tag
        $teacherLoads = Supervision::where(function ($query) use ($examDate, $assignmentIds) {
                $query->where('written_exam_year_date_id', $examDate->id)
                      ->orWhereIn('written_exam_assignment_id', $assignmentIds);
            })
            ->with('teacher')
            ->get()
            ->groupBy('teacher_id');
        
        foreach ($teacherLoads as $teacherId => $supervisions) {
            $count = $supervisions->count();
            $teacher = $supervisions->first()->teacher;
            
            // Prüfe max_supervisions_per_day wenn gesetzt
            if ($teacher->max_supervisions_per_day && $count > $teacher->max_supervisions_per_day) {
                $warnings[] = "{$teacher->full_name} hat {$count} Aufsichten, Maximum ist {$teacher->max_supervisions_per_day}.";
            }
            
            // Warne bei sehr hoher Belastung (mehr als 3)
            if ($count > 3) {
                $warnings[] = "{$teacher->full_name} hat {$count} Aufsichten an diesem Tag.";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Entferne alle Aufsichten für einen Prüfungstermin
     * 
     * @param WrittenExamYearDate $examDate Der Prüfungstermin
     * @param string $schoolId Die Schul-ID
     * @return array Ergebnis mit Anzahl gelöschter Einträge
     */
    public function clearSupervisions(WrittenExamYearDate $examDate, string $schoolId): array
    {
        try {
            $assignmentIds = WrittenExamAssignment::where('written_exam_year_date_id', $examDate->id)->pluck('id');
            
            $deletedCount = Supervision::where('written_exam_year_date_id', $examDate->id)
                ->orWhereIn('written_exam_assignment_id', $assignmentIds)
                ->delete();
            
            Log::info('Aufsichten gelöscht', [
                'exam_date_id' => $examDate->id,
                'school_id' => $schoolId,
                'deleted_count' => $deletedCount
            ]);
            
            return [
                'success' => true,
                'message' => "{$deletedCount} Aufsichten wurden gelöscht.",
                'deleted_count' => $deletedCount
            ];
            
        } catch (\Exception $e) {
            Log::error('Fehler beim Löschen der Aufsichten', [
                'exam_date_id' => $examDate->id,
                'school_id' => $schoolId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Fehler beim Löschen der Aufsichten: ' . $e->getMessage(),
                'deleted_count' => 0
            ];
        }
    }

    /**
     * Map a time range to school periods.
     */
    public function getPeriodsForTimeRange(string $start, string $end): array
    {
        $periods = [];
        $startMinutes = (int)substr($start, 0, 2) * 60 + (int)substr($start, 3, 2);
        $endMinutes = (int)substr($end, 0, 2) * 60 + (int)substr($end, 3, 2);

        $schoolPeriods = \App\Models\SchoolPeriod::where('is_break', false)
            ->whereNotNull('period_number')
            ->orderBy('start_time')
            ->get();

        foreach ($schoolPeriods as $schoolPeriod) {
            // Check if start_time is null before formatting
            if (!$schoolPeriod->start_time || !$schoolPeriod->end_time) {
                continue;
            }
            
            $pStartString = $schoolPeriod->start_time->format('H:i');
            $pEndString = $schoolPeriod->end_time->format('H:i');

            $pStart = (int)substr($pStartString, 0, 2) * 60 + (int)substr($pStartString, 3, 2);
            $pEnd = (int)substr($pEndString, 0, 2) * 60 + (int)substr($pEndString, 3, 2);

            // Overlap condition: latest start < earliest end
            if (max($startMinutes, $pStart) < min($endMinutes, $pEnd)) {
                $periods[] = $schoolPeriod->period_number;
            }
        }
        return $periods;
    }

    /**
     * Generates categorized teacher suggestions for a specific time slot based on their timetable.
     * 
     * Categories:
     * 1. Q13 lesson (course_code starts with 2)
     * 2. Free period during slot, but lesson right before (already in school)
     * 3. Free period during slot
     * 4. Has other lessons (warning)
     */
    public function getTeacherSuggestionsForTimeSlot(
        WrittenExamYearDate $examDate, 
        ?\App\Models\WrittenExamSupervisionTimeSlot $slot, 
        string $schoolId
    ): array {
        $availableTeachers = $this->getAvailableTeachers($schoolId, Carbon::parse($examDate->exam_date));
        
        // Filter out teachers already assigned to this slot (across all year-date IDs of the same calendar day)
        if ($slot) {
            $dateIds = \App\Models\WrittenExamYearDate::whereDate('exam_date', $examDate->exam_date)
                ->pluck('id');

            $assignedTeacherIds = \App\Models\Supervision::whereIn('written_exam_year_date_id', $dateIds)
                ->where('written_exam_supervision_time_slot_id', $slot->id)
                ->pluck('teacher_id')
                ->toArray();
                
            $availableTeachers = $availableTeachers->filter(function($teacher) use ($assignedTeacherIds) {
                return !in_array($teacher->id, $assignedTeacherIds);
            });
        }
        
        $suggestions = [
            'q13_lesson' => [],
            'free_already_there' => [],
            'free' => [],
            'has_lesson' => [],
            'trainee' => [],
            'no_title' => [],
        ];

        // If no time slot is defined, just return all as "free"
        if (!$slot) {
            foreach ($availableTeachers as $teacher) {
                $suggestions['free'][] = $teacher;
            }
            return $suggestions;
        }

        // Get the day of week (1 = Monday, ..., 5 = Friday)
        $dayOfWeek = Carbon::parse($examDate->exam_date)->dayOfWeekIso;
        
        // If it's the weekend, return all as "free"
        if ($dayOfWeek > 5) {
            foreach ($availableTeachers as $teacher) {
                $suggestions['free'][] = $teacher;
            }
            return $suggestions;
        }

        $categories = $this->categorizeTeachersForSlot($slot, $availableTeachers, $dayOfWeek);

        foreach ($availableTeachers as $teacher) {
            $suggestions[$categories[$teacher->id]][] = $teacher;
        }

        // Sort inside categories by lastname
        foreach ($suggestions as $key => $list) {
            usort($suggestions[$key], fn($a, $b) => strcmp($a->lastname, $b->lastname));
        }

        return $suggestions;
    }

    /**
     * Returns a [teacher_id => category] map for ALL available teachers for a given slot.
     * Unlike getTeacherSuggestionsForTimeSlot, does NOT filter out already-assigned teachers.
     * Used for color-coding supervision badges in the plan view.
     *
     * Categories: q13_lesson | free_already_there | free | has_lesson
     */
    public function getTeacherCategoryMap(
        WrittenExamYearDate $examDate,
        ?\App\Models\WrittenExamSupervisionTimeSlot $slot,
        string $schoolId
    ): array {
        $availableTeachers = $this->getAvailableTeachers($schoolId, Carbon::parse($examDate->exam_date));

        $map = [];

        if (!$slot) {
            foreach ($availableTeachers as $teacher) {
                $map[$teacher->id] = 'free';
            }
            return $map;
        }

        $dayOfWeek = Carbon::parse($examDate->exam_date)->dayOfWeekIso;

        if ($dayOfWeek > 5) {
            foreach ($availableTeachers as $teacher) {
                $map[$teacher->id] = 'free';
            }
            return $map;
        }

        return $this->categorizeTeachersForSlot($slot, $availableTeachers, $dayOfWeek)->all();
    }

    /**
     * Kategorisiert für jeden Lehrer aus $availableTeachers, was er während
     * des angegebenen Zeitfensters am Prüfungstag macht (Q13-Stunde, schon
     * anwesend, hat Stunde, Referendar, ohne Titel, sonst frei). Setzt
     * voraus, dass ein Zeitfenster übergeben wird und $dayOfWeek ein Werktag
     * (Mo-Fr, 1-5) ist - die Sonderfälle "kein Zeitfenster"/"Wochenende"
     * (jeder gilt als "free") behandelt jeder Aufrufer selbst, weil sie sich
     * darin unterscheiden, ob die Ergebnisliste anschließend sortiert wird.
     * Gemeinsame Grundlage für getTeacherSuggestionsForTimeSlot()
     * (Vorschlagslisten, gefiltert um bereits zugeteilte Lehrer) und
     * getTeacherCategoryMap() (Farb-Mapping für ALLE verfügbaren Lehrer).
     *
     * @return \Illuminate\Support\Collection<int, string> teacher_id => category
     */
    private function categorizeTeachersForSlot(
        \App\Models\WrittenExamSupervisionTimeSlot $slot,
        Collection $availableTeachers,
        int $dayOfWeek
    ): Collection {
        $categories = collect();

        $affectedPeriods = $this->getPeriodsForTimeRange($slot->start_time, $slot->end_time);

        // Load all timetable entries for the affected day for these teachers
        $timetableEntries = \App\Models\TimetableEntry::whereIn('period', collect($affectedPeriods)->push(min($affectedPeriods) - 1)->toArray())
            ->where('day_of_week', $dayOfWeek)
            ->whereIn('teacher_id', $availableTeachers->pluck('id'))
            ->get()
            ->groupBy('teacher_id');

        foreach ($availableTeachers as $teacher) {
            $teacherEntries = $timetableEntries->get($teacher->id, collect());

            $hasQ13Lesson = false;
            $hasOtherLesson = false;
            $hasLessonJustBefore = false;

            // Check what the teacher is doing in periods affected by the slot
            foreach ($affectedPeriods as $period) {
                $entryForPeriod = $teacherEntries->firstWhere('period', $period);
                if ($entryForPeriod) {
                    if (str_starts_with($entryForPeriod->course_code ?? '', '2')) {
                        $hasQ13Lesson = true;
                    } else {
                        $hasOtherLesson = true;
                    }
                }
            }

            // Check if teacher has lesson right before the first affected period
            $firstPeriod = min($affectedPeriods);
            if ($firstPeriod > 1 && $teacherEntries->firstWhere('period', $firstPeriod - 1)) {
                $hasLessonJustBefore = true;
            }

            // Studienreferendare immer in eigene Kategorie
            if (in_array($teacher->title, ['StRef', 'Studienreferendar', 'Studienreferendarin'], true)) {
                $categories[$teacher->id] = 'trainee';
            } elseif (empty($teacher->title)) {
                $categories[$teacher->id] = 'no_title';
            } elseif ($hasQ13Lesson) {
                $categories[$teacher->id] = 'q13_lesson';
            } elseif ($hasOtherLesson) {
                $categories[$teacher->id] = 'has_lesson';
            } elseif ($hasLessonJustBefore) {
                $categories[$teacher->id] = 'free_already_there';
            } else {
                $categories[$teacher->id] = 'free';
            }
        }

        return $categories;
    }
}
