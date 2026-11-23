<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\ExamAssignment;
use App\Models\TimeSlot;
use App\Models\Teacher;
use Illuminate\Support\Collection;

class ConflictDetectionService
{
    /**
     * Erkennt alle Konflikte für einen Prüfungszeitraum
     */
    public function detectAll(ExamPeriod $examPeriod): array
    {
        return [
            'errors' => array_merge(
                $this->detectTeacherDoubleBooking($examPeriod),
                $this->detectRoomConflicts($examPeriod),
                $this->detectStudentConflicts($examPeriod),
                $this->detectExternalSameDayConflicts($examPeriod)
            ),
            'warnings' => array_merge(
                $this->detectHighWorkload($examPeriod),
                $this->detectUnavailableTeachers($examPeriod),
                $this->detectThemeGroupSplits($examPeriod)
            )
        ];
    }

    /**
     * Lädt alle ExamAssignments für einen Prüfungszeitraum.
     * Gemeinsame Basis für detectTeacherDoubleBooking, detectRoomConflicts,
     * detectStudentConflicts und detectUnavailableTeachers.
     *
     * Über `imPruefungszeitraum()` und nicht über `whereHas('student', …)`:
     * für externe Bewerber ist student_id null, ihre Prüfungen fielen aus
     * dieser Abfrage heraus — und damit aus allen vier Erkennungen. Ein
     * Terminkonflikt mit einem externen Prüfling wurde also nicht gemeldet,
     * ohne dass irgendwo ein Fehler sichtbar geworden wäre.
     *
     * @param  string[]  $with  Eager-Load-Relations
     */
    private function loadAssignmentsForPeriod(ExamPeriod $examPeriod, array $with = []): \Illuminate\Support\Collection
    {
        return ExamAssignment::whereHas(
            'studentExam',
            fn ($q) => $q->imPruefungszeitraum($examPeriod)
        )->with($with)->get();
    }

    /**
     * Prüft auf Doppelbelegung von Lehrern
     */
    private function detectTeacherDoubleBooking(ExamPeriod $examPeriod): array
    {
        $conflicts = [];
        
        // WICHTIG: assessor ist jetzt in StudentExam, nicht in ExamAssignment!
        $assignments = $this->loadAssignmentsForPeriod(
            $examPeriod,
            ['timeSlot', 'studentExam.examiner', 'studentExam.assessor']
        );
        
        // Gruppiere nach Zeitslot und Lehrer
        foreach ($assignments->groupBy('time_slot_id') as $timeSlotId => $slotAssignments) {
            $timeSlot = TimeSlot::find($timeSlotId);
            
            // Prüfe Prüfer (examiner)
            $examinerCounts = $slotAssignments->groupBy(fn($a) => $a->studentExam->examiner_id ?? 0);
            foreach ($examinerCounts as $teacherId => $exams) {
                if ($teacherId && $exams->count() > 1) {
                    $teacher     = Teacher::find($teacherId);
                    $teacherName = $teacher !== null ? $teacher->shortcode : "ID:{$teacherId}";
                    $conflicts[] = [
                        'type' => 'teacher_double_booking',
                        'severity' => 'error',
                        'message' => "Lehrer {$teacherName} hat {$exams->count()} Prüfungen gleichzeitig am {$timeSlot->date->format('d.m.Y')} um {$timeSlot->exam_start_time}",
                        'affected' => [
                            'teacher_id' => $teacherId,
                            'time_slot_id' => $timeSlotId
                        ]
                    ];
                }
            }
            
            // Prüfe Beisitzer (assessor) - WICHTIG: assessor_id ist jetzt in StudentExam!
            $assessorCounts = $slotAssignments->filter(fn($a) => $a->studentExam->assessor_id !== null)
                ->groupBy(fn($a) => $a->studentExam->assessor_id);
            foreach ($assessorCounts as $teacherId => $exams) {
                if ($teacherId && $exams->count() > 1) {
                    $teacher     = Teacher::find($teacherId);
                    $teacherName = $teacher !== null ? $teacher->shortcode : "ID:{$teacherId}";
                    $conflicts[] = [
                        'type' => 'teacher_double_booking',
                        'severity' => 'error',
                        'message' => "Lehrer {$teacherName} sitzt {$exams->count()}x gleichzeitig bei am {$timeSlot->date->format('d.m.Y')} um {$timeSlot->exam_start_time}",
                        'affected' => [
                            'teacher_id' => $teacherId,
                            'time_slot_id' => $timeSlotId
                        ]
                    ];
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Prüft auf Raum-Konflikte
     */
    private function detectRoomConflicts(ExamPeriod $examPeriod): array
    {
        $conflicts = [];
        
        $assignments = $this->loadAssignmentsForPeriod($examPeriod, ['timeSlot']);
        
        // Gruppiere nach Zeitslot und Prüfungsraum
        foreach ($assignments->groupBy('time_slot_id') as $timeSlotId => $slotAssignments) {
            $timeSlot = TimeSlot::find($timeSlotId);
            
            $roomCounts = $slotAssignments->groupBy('exam_room_id');
            foreach ($roomCounts as $roomId => $exams) {
                if ($exams->count() > 1) {
                    $conflicts[] = [
                        'type' => 'room_conflict',
                        'severity' => 'error',
                        'message' => "Raum {$roomId} ist {$exams->count()}x belegt am {$timeSlot->date->format('d.m.Y')} um {$timeSlot->exam_start_time}",
                        'affected' => [
                            'room_id' => $roomId,
                            'time_slot_id' => $timeSlotId
                        ]
                    ];
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Prüft auf Prüflings-Konflikte (2 Prüfungen gleichzeitig).
     *
     * Gruppiert über den Prüflingsschlüssel und nicht über `student_id`: der
     * ist für externe Bewerber null und würde als Array-Schlüssel zu "" —
     * alle externen Prüflinge eines Zeitraums lägen in einer Gruppe und
     * meldeten sich gegenseitig als Doppelbelegung einer Person, die es gar
     * nicht gibt.
     */
    private function detectStudentConflicts(ExamPeriod $examPeriod): array
    {
        $conflicts = [];
        
        $assignments = $this->loadAssignmentsForPeriod(
            $examPeriod,
            ['timeSlot', 'studentExam.student', 'studentExam.externalExamSubject.externalApplicant']
        );
        
        foreach ($assignments->groupBy('time_slot_id') as $timeSlotId => $slotAssignments) {
            $timeSlot = TimeSlot::find($timeSlotId);
            
            $prueflingsGruppen = $slotAssignments->groupBy(
                fn ($a) => $a->studentExam->prueflingsSchluessel()
            );

            foreach ($prueflingsGruppen as $schluessel => $exams) {
                if ($exams->count() > 1) {
                    $ersteExam = $exams->first()->studentExam;
                    $name = $ersteExam->getStudentName();
                    $bezeichnung = $ersteExam->isExternalApplicant() ? 'Bewerber' : 'Schüler';

                    $conflicts[] = [
                        'type' => 'student_double_booking',
                        'severity' => 'error',
                        'message' => "{$bezeichnung} {$name} hat {$exams->count()} Prüfungen gleichzeitig am {$timeSlot->date->format('d.m.Y')}",
                        'affected' => [
                            // Der Prüflingsschlüssel statt student_id: nur er
                            // trägt auch externe Bewerber.
                            'pruefling' => $schluessel,
                            'student_id' => $ersteExam->student_id,
                            'time_slot_id' => $timeSlotId
                        ]
                    ];
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * § 61 Abs. 3 S. 9: Für einen externen Bewerber findet nur eine mündliche
     * Prüfung an einem Tag statt (Regel C9).
     *
     * Die Zuweisungsmaske weist eine zweite Prüfung am selben Tag inzwischen
     * ab. Diese Erkennung ist die andere Hälfte: Sie macht sichtbar, was schon
     * dasteht — aus einer Auto-Planung von vor dieser Regel, aus einem Import
     * oder aus einer verschobenen Prüfung.
     *
     * Anders als detectStudentConflicts wird nach **Tag** gruppiert, nicht nach
     * Zeitslot: Zwei Prüfungen desselben Bewerbers am selben Tag zu
     * verschiedenen Uhrzeiten sind genau der Fall, den die Norm ausschließt —
     * und der Fall, den die Gruppierung nach Zeitslot nie sieht.
     *
     * Nachtermine bleiben außen vor (replaces_id), wie überall sonst auch.
     */
    private function detectExternalSameDayConflicts(ExamPeriod $examPeriod): array
    {
        $conflicts = [];

        $assignments = $this->loadAssignmentsForPeriod(
            $examPeriod,
            ['timeSlot', 'studentExam.externalExamSubject.externalApplicant']
        )
            ->filter(static fn ($a): bool => $a->studentExam->isExternalApplicant())
            ->filter(static fn ($a): bool => $a->replaces_id === null)
            ->filter(static fn ($a): bool => $a->status !== 'cancelled')
            ->filter(static fn ($a): bool => $a->timeSlot !== null);

        $gruppen = $assignments->groupBy(
            static fn ($a): string => $a->studentExam->prueflingsSchluessel()
                . '|' . $a->timeSlot->date->format('Y-m-d')
        );

        foreach ($gruppen as $schluessel => $tagesPruefungen) {
            if ($tagesPruefungen->count() <= 1) {
                continue;
            }

            $erste = $tagesPruefungen->first();
            $name  = $erste->studentExam->getStudentName();
            $datum = $erste->timeSlot->date->format('d.m.Y');

            $conflicts[] = [
                'type'     => 'external_same_day',
                'severity' => 'error',
                'message'  => "Bewerber {$name} hat {$tagesPruefungen->count()} mündliche Prüfungen am {$datum} "
                    . '– § 61 Abs. 3 S. 9 sieht nur eine Prüfung pro Tag vor',
                'affected' => [
                    'pruefling' => explode('|', (string) $schluessel)[0],
                    'datum'     => $erste->timeSlot->date->format('Y-m-d'),
                ],
            ];
        }

        return $conflicts;
    }

    /**
     * Warnung bei hoher Arbeitsbelastung
     */
    private function detectHighWorkload(ExamPeriod $examPeriod): array
    {
        $warnings = [];
        
        $timeSlots = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->orderBy('date')
            ->get();
        
        foreach ($timeSlots->groupBy('date') as $date => $daySlots) {
            $teachers = [];
            
            foreach ($daySlots as $slot) {
                // WICHTIG: assessor ist jetzt in StudentExam!
                // Die frühere Fassung hing hier ein `whereHas('student')` mit
                // leerem Rumpf davor — seit der Tenant-Umstellung filterte es
                // keine Bedingung mehr, sondern nur noch die externen
                // Prüflinge heraus. Der Zeitraum steht ohnehin am Slot.
                $assignments = ExamAssignment::where('time_slot_id', $slot->id)
                    ->with('studentExam.examiner', 'studentExam.assessor')
                    ->get();
                
                foreach ($assignments as $assignment) {
                    // Zähle Prüfer
                    $examinerId = $assignment->studentExam->examiner_id;
                    $teachers[$examinerId] = ($teachers[$examinerId] ?? 0) + 1;
                    
                    // Zähle Beisitzer (jetzt in StudentExam!)
                    $assessorId = $assignment->studentExam->assessor_id;
                    if ($assessorId) {
                        $teachers[$assessorId] = ($teachers[$assessorId] ?? 0) + 1;
                    }
                }
            }
            
            foreach ($teachers as $teacherId => $count) {
                if (!$teacherId) continue; // null-IDs überspringen
                
                $teacher = Teacher::find($teacherId);
                if (!$teacher) continue; // Lehrer nicht gefunden
                
                $maxAllowed = $teacher->max_exams_per_day ?? 10;
                
                if ($count > $maxAllowed) {
                    $warnings[] = [
                        'type' => 'high_workload',
                        'severity' => 'warning',
                        'message' => "Lehrer {$teacher->shortcode} hat {$count} Einsätze am {$date} (Maximum: {$maxAllowed})",
                        'affected' => [
                            'teacher_id' => $teacherId,
                            'date' => $date
                        ]
                    ];
                }
            }
        }
        
        return $warnings;
    }

    /**
     * Warnung bei nicht verfügbaren Lehrern
     */
    private function detectUnavailableTeachers(ExamPeriod $examPeriod): array
    {
        $warnings = [];
        
        // WICHTIG: assessor ist jetzt in StudentExam!
        $assignments = $this->loadAssignmentsForPeriod(
            $examPeriod,
            ['timeSlot', 'studentExam.examiner', 'studentExam.assessor']
        );
        
        foreach ($assignments as $assignment) {
            $date = $assignment->timeSlot->date;
            
            // Prüfe Prüfer (null-safe)
            $examiner = $assignment->studentExam?->examiner;
            if ($examiner instanceof Teacher && !$examiner->isAvailable($date)) {
                $warnings[] = [
                    'type' => 'teacher_unavailable',
                    'severity' => 'warning',
                    'message' => "Prüfer {$examiner->shortcode} ist am {$date->format('d.m.Y')} nicht verfügbar",
                    'affected' => [
                        'teacher_id' => $examiner->id,
                        'assignment_id' => $assignment->id
                    ]
                ];
            }
            
            // Prüfe Beisitzer (null-safe) - WICHTIG: Beisitzer ist jetzt in StudentExam!
            $assessor = $assignment->studentExam?->assessor;
            if ($assessor instanceof Teacher && !$assessor->isAvailable($date)) {
                $warnings[] = [
                    'type' => 'teacher_unavailable',
                    'severity' => 'warning',
                    'message' => "Beisitzer {$assessor->shortcode} ist am {$date->format('d.m.Y')} nicht verfügbar",
                    'affected' => [
                        'teacher_id' => $assessor->id,
                        'assignment_id' => $assignment->id
                    ]
                ];
            }
        }
        
        return $warnings;
    }

    /**
     * Warnung bei unterbrochenen Beisitzer-Blöcken (Coupling-Gruppen)
     */
    private function detectThemeGroupSplits(ExamPeriod $examPeriod): array
    {
        $warnings = [];
        
        // Gruppiere Assignments nach coupling_id der StudentExams.
        //
        // Hier wog der Ausfall besonders schwer: die Kopplung Extern + regulär
        // beim selben Prüfer war ausdrücklich der Grund, student_exams für
        // externe Bewerber zu öffnen — und ausgerechnet die Prüfung auf
        // unterbrochene Kopplungsblöcke sah die externe Hälfte nicht.
        $assignments = ExamAssignment::whereHas('studentExam', function($q) use ($examPeriod) {
            $q->imPruefungszeitraum($examPeriod)->whereNotNull('coupling_id');
        })
        ->with(['timeSlot', 'studentExam'])
        ->get()
        ->groupBy('studentExam.coupling_id');
        
        foreach ($assignments as $couplingId => $groupAssignments) {
            if ($groupAssignments->count() < 2) continue;
            
            // Prüfe ob alle Assignments aufeinanderfolgend sind
            $slots = $groupAssignments->pluck('timeSlot')->sortBy('id');
            $slotIds = $slots->pluck('id')->toArray();
            
            // Prüfe auf Lücken
            for ($i = 0; $i < count($slotIds) - 1; $i++) {
                if ($slotIds[$i + 1] != $slotIds[$i] + 1) {
                    $firstExam = $groupAssignments->first()->studentExam;
                    $assessor = $firstExam->assessor;
                    $assessorName = $assessor->shortcode ?? 'Unbekannt';
                    $warnings[] = [
                        'type' => 'coupling_group_split',
                        'severity' => 'warning',
                        'message' => "Gekoppelter Block von {$assessorName} ist nicht zusammenhängend",
                        'affected' => [
                            'coupling_id' => $couplingId,
                            'assessor_id' => $assessor?->id
                        ]
                    ];
                    break;
                }
            }
        }
        
        return $warnings;
    }

    /**
     * Prüft eine einzelne Zuweisung auf Konflikte
     */
    public function detectForAssignment(int $studentExamId, int $timeSlotId, int $assessorId): array
    {
        // Temporäre Zuweisung simulieren für Validierung
        $conflicts = ['errors' => [], 'warnings' => []];
        
        // Implementierung ähnlich wie detectAll, aber nur für diese eine Zuweisung
        // ... (wird bei Bedarf erweitert)
        
        return $conflicts;
    }
}
