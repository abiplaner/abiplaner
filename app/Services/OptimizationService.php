<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\OralExamDayRoom;
use App\Models\StudentExam;
use App\Models\TimeSlot;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\ExamAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OptimizationService - Automatische Prüfungsplanung
 * 
 * WICHTIG: exam_week wird NICHT beim Import gesetzt, sondern erst hier!
 * Der Algorithmus entscheidet, welche Prüfung in welche Woche kommt.
 * 
 * Ablauf:
 * 1. Prüferblöcke bilden (nach Kurs + Prüfer)
 * 2. Wochenzuweisung optimieren (jeder Schüler: 1x Woche 1, 1x Woche 2)
 * 3. Blöcke auf Zeitslots verteilen
 */
class OptimizationService
{
    private array $settings = [];
    private array $warnings = [];

    // Modul 4.6 (Simulation): Optional injizierte In-Memory-Ressourcen für den
    // Dry-Run. Sind sie gesetzt, überspringt optimize() das Laden aus der DB und
    // plant gegen diese hypothetische Tage-/Slot-/Raum-Menge. Kein DB-Zugriff,
    // keine Persistenz. Im Normalbetrieb null.
    private ?Collection $injectedTimeSlots = null;
    private ?Collection $injectedRooms = null;
    
    // Tracking für bereits belegte Ressourcen
    private array $occupiedSlots = [];      // slot_id => [room_ids]
    private array $teacherSchedule = [];    // teacher_id => [slot_ids]
    
    // Tracking für Prüflings-Wochen-Zuweisung.
    // Schlüssel ist StudentExam::prueflingsSchluessel() ("s<id>" für Schüler,
    // "e<id>" für externe Bewerber), nicht die students-ID: externe Bewerber
    // haben keinen Schülerdatensatz und fielen sonst alle in denselben Topf.
    // Für externe Bewerber bleibt dieser Speicher leer — siehe wochenRegelGilt().
    private array $studentWeekAssignment = []; // prueflingsSchluessel => [1 => exam_id, 2 => exam_id]

    // Tage, an denen ein externer Bewerber bereits eine mündliche Prüfung hat.
    // Das Gegenstück zur Wochenregel: § 61 Abs. 3 S. 9 lässt für ihn nur eine
    // mündliche Prüfung an einem Tag zu (Regel C9). Nur für externe Bewerber
    // gefüllt — für reguläre Schüler ordnet die Wochenregel die Verteilung, und
    // ein zweiter Eintrag je Tag kann bei ihnen gar nicht entstehen.
    private array $externalDayAssignment = []; // prueflingsSchluessel => ['Y-m-d' => true]

    // Prüfungen, die im Lauf bereits eine Woche bekommen haben — unabhängig
    // davon, ob für den Prüfling die Wochenregel gilt. Bis dahin las
    // scheduleRemainingExams diese Menge aus studentWeekAssignment ab; seit der
    // für externe Bewerber leer bleibt, wäre jede ihrer im Block geplanten
    // Prüfungen ein zweites Mal eingeplant worden.
    private array $verplanteExamIds = []; // student_exam_id => true

    // Gesperrte Tage pro Prüfling: prueflingsSchluessel => ['Y-m-d', ...]
    private array $studentBlockedDates = [];

    // Abwesenheitstage pro Lehrer: teacher_id => ['Y-m-d', ...]
    private array $teacherUnavailableDates = [];

    // Stundenplanfreie Wochentage pro Lehrer (Modul 4.3, Teilpunkt "freie Tage"):
    // teacher_id => [Wochentag 1-5 => true]. Nur Lehrkräfte MIT Stundenplandaten.
    private array $teacherFreeWeekdays = [];

    // Cache: 'Y-m-d' => ISO-Wochentag (1-7), für den Hot-Path in calculateSlotScore
    private array $dateWeekdayCache = [];

    // Day-Load-Penalty: Anzahl bereits geplanter Prüfungen pro Tag
    private array $dayLoad = [];            // 'Y-m-d' => int
    private array $slotDateCache = [];      // slot_id => 'Y-m-d'

    // Lehrer-Tages-Belegung (Modul 4.3): teacher_id => ['Y-m-d' => Anzahl]
    // Zählt Prüfer- UND Beisitzer-Einsätze, um Prüfungstage pro Lehrkraft zu bündeln
    private array $teacherDayLoad = [];

    // Gewählte Woche pro Prüfer (Modul 4.3): examiner_id => 1|2
    // Blöcke desselben Prüfers sollen möglichst in derselben Woche landen
    private array $examinerWeekChoice = [];

    // Team-Partner (Modul 4.5): teacher_id => [partner_teacher_id => true]
    // Zwei Lehrkräfte gelten als Team-Partner, wenn sie bei mindestens einer
    // noch ungeplanten (nicht fixierten) Kolloquiumsprüfung gemeinsam als
    // Prüfer+Beisitzer auftreten (Rolle egal, auch vertauscht). Rein zur
    // Planungslaufzeit ermittelt, nicht persistiert – reiner Optimizer-Bonus.
    private array $teamPartners = [];

    // Schüler mit letztem schriftlichem Prüfungstermin (sollen ab Mi in Woche 1)
    private array $studentsWithLateWrittenExam = []; // [prueflingsSchluessel, ...]
    private array $avoidDatesForLateExamStudents = []; // ['Y-m-d', ...] (Mo+Di Woche 1)

    /** @phpstan-ignore constructor.unusedParameter */
    public function __construct(ConflictDetectionService $conflictService)
    {
        // $conflictService wird von ExamPlanningService direkt genutzt
    }

    /**
     * Modul 4.6 (Simulation): Injiziert hypothetische Ressourcen für einen
     * Dry-Run. Danach plant optimize() gegen diese In-Memory-Slots/Räume statt
     * gegen die DB. Kein DB-Zugriff, keine Persistenz.
     *
     * WICHTIG: Die übergebenen TimeSlot-Instanzen müssen stabile, eindeutige
     * IDs tragen (der Algorithmus referenziert Slots durchgängig über ihre ID).
     * Aufbau der Ressourcen übernimmt der PlanningSimulationService.
     *
     * @param Collection<int, \App\Models\TimeSlot> $timeSlots
     * @param Collection<int, \App\Models\Room> $rooms
     */
    public function injectResources(Collection $timeSlots, Collection $rooms): void
    {
        $this->injectedTimeSlots = $timeSlots;
        $this->injectedRooms = $rooms;
    }

    /**
     * Modul 4.6: Hebt eine zuvor gesetzte Ressourcen-Injektion wieder auf,
     * sodass der nächste optimize()-Lauf wieder regulär aus der DB lädt.
     */
    public function clearInjectedResources(): void
    {
        $this->injectedTimeSlots = null;
        $this->injectedRooms = null;
    }

    /**
     * Hauptmethode: Führt die automatische Planung durch
     */
    public function optimize(ExamPeriod $examPeriod, array $options = []): array
    {
        Log::channel('single')->info("OptimizationService::optimize gestartet", [
            'exam_period_id' => $examPeriod->id,
            'options' => $options
        ]);
        
        // Einstellungen laden
        $this->settings = $this->loadSettings($examPeriod, $options);
        $this->warnings = [];
        $this->occupiedSlots = [];
        $this->teacherSchedule = [];
        $this->studentWeekAssignment = [];
        $this->externalDayAssignment = [];
        $this->verplanteExamIds = [];
        $this->studentBlockedDates = $this->loadStudentBlockedDates($examPeriod);
        $this->teacherUnavailableDates = $this->loadTeacherUnavailableDates();
        $this->teacherFreeWeekdays = $this->loadTeacherFreeWeekdays($examPeriod);
        $this->dateWeekdayCache = [];
        $this->dayLoad = [];
        $this->slotDateCache = [];
        $this->teacherDayLoad = [];
        $this->examinerWeekChoice = [];
        $this->teamPartners = [];
        $this->studentsWithLateWrittenExam = [];
        $this->avoidDatesForLateExamStudents = [];
        $this->loadStudentsWithLateWrittenExam($examPeriod);
        $this->loadTeamPartners($examPeriod);
        
        // Phase 0: FIXIERTE PRÜFUNGEN laden und Ressourcen reservieren
        $lockedCount = $this->loadLockedAssignments($examPeriod);
        Log::channel('single')->info("Fixierte Prüfungen geladen", ['count' => $lockedCount]);

        // Phase 0b (Modul 4.3): Lehrer-Tages-Belegung aus bereits bestehenden,
        // NICHT-fixierten Zuweisungen übernehmen (relevant bei clear_existing=false),
        // damit die Tages-Affinität auch diese Prüfungstage kennt.
        $this->seedTeacherDayLoadFromExistingAssignments($examPeriod);
        
        // Phase 1: Kapazitätsprüfung (angepasst für dynamische Wochenzuweisung)
        $capacityCheck = $this->checkCapacity($examPeriod);
        if (!$capacityCheck['sufficient']) {
            $this->warnings = array_merge($this->warnings, $capacityCheck['warnings']);
        }
        
        // Phase 2: Zeitslots und Räume laden
        // Modul 4.6 (Simulation): Ressourcen können als In-Memory-Collections
        // injiziert werden (Dry-Run mit hypothetischer Tage-/Slot-/Raum-Menge),
        // ohne echte TimeSlot/OralExamDayRoom-Datensätze zu verändern. Sind keine
        // injiziert, gilt das reguläre Laden aus der DB (Normalbetrieb).
        $timeSlots = $this->injectedTimeSlots ?? $this->loadTimeSlots($examPeriod);
        $rooms = $this->injectedRooms ?? $this->loadRooms($examPeriod);

        // Slot-Datum-Cache auch für injizierte Slots befüllen (der Rest des
        // Algorithmus referenziert Slots über ihre ID; injizierte Slots müssen
        // daher stabile, eindeutige IDs tragen – vom Aufrufer sicherzustellen).
        if ($this->injectedTimeSlots !== null) {
            foreach ($timeSlots as $slot) {
                $this->slotDateCache[$slot->id] = Carbon::parse($slot->date)->format('Y-m-d');
            }
        }
        
        // Phase 3a: KOPPLUNGSBLÖCKE bilden (HÖCHSTE PRIORITÄT)
        $couplingBlocks = $this->buildCouplingBlocks($examPeriod);
        Log::channel('single')->info("Kopplungsblöcke gebildet", ['count' => $couplingBlocks->count()]);
        
        // Phase 3b: Prüferblöcke bilden (ohne bereits gekoppelte Prüfungen)
        $examinerBlocks = $this->buildExaminerBlocks($examPeriod);
        Log::channel('single')->info("Prüferblöcke gebildet", ['count' => $examinerBlocks->count()]);
        
        // Phase 4: Wochenzuweisung für Kopplungsblöcke (ZUERST!)
        $couplingBlocksWithWeeks = $this->assignWeeksToBlocks($couplingBlocks, $examPeriod);
        
        // Phase 5: Wochenzuweisung für Prüferblöcke
        $examinerBlocksWithWeeks = $this->assignWeeksToBlocks($examinerBlocks, $examPeriod);
        
        // Phase 6: Kopplungsblöcke planen (ZUERST!)
        $schedule = $this->scheduleBlocks($couplingBlocksWithWeeks, $timeSlots, $rooms, $examPeriod);
        Log::channel('single')->info("Kopplungsblöcke geplant", ['scheduled' => count($schedule)]);
        
        // Phase 7: Prüferblöcke planen
        $examinerSchedule = $this->scheduleBlocks($examinerBlocksWithWeeks, $timeSlots, $rooms, $examPeriod);
        $schedule = array_merge($schedule, $examinerSchedule);
        Log::channel('single')->info("Prüferblöcke geplant", ['total_scheduled' => count($schedule)]);
        
        // Phase 8: Verbleibende Einzelprüfungen planen
        $remaining = $this->scheduleRemainingExams($examPeriod, $timeSlots, $rooms);
        $schedule = array_merge($schedule, $remaining);

        // Phase 8.5: Rescue-Pass für ungeplante Prüfungen (max. 2 Runden)
        // Wenn Prüfungen nicht geplant werden konnten (z.B. weil der Prüfer die ganze
        // Woche A abwesend ist und der Schüler Woche B schon belegt hat), versuchen wir
        // die bereits geplante Gegenprüfung des Schülers in die andere Woche zu verschieben,
        // um so Platz in der einzig möglichen Woche zu schaffen.
        for ($rescueRound = 1; $rescueRound <= 10; $rescueRound++) {
            $rescued = $this->runRescuePass($examPeriod, $timeSlots, $rooms, $schedule);
            $schedule = array_merge($schedule, $rescued['newAssignments']);
            if ($rescued['fixed'] === 0) {
                break;
            }
            Log::channel('single')->info("Rescue-Runde {$rescueRound}: {$rescued['fixed']} Prüfungen gerettet");
        }

        // Phase 9: Kopplungs-Raumoptimierung (mehrere Runden)
        // Gekoppelte Prüfungen die in verschiedenen Räumen gelandet sind, werden
        // in denselben Raum verschoben (max. 3 Optimierungsrunden)
        $optimizationRoundsApplied = 0;
        for ($round = 1; $round <= 3; $round++) {
            $result = $this->runCouplingRoomOptimization($schedule, $timeSlots, $rooms);
            $schedule = $result['schedule'];
            if ($result['fixed'] === 0) {
                break; // Keine weiteren Verbesserungen möglich
            }
            $optimizationRoundsApplied++;
            Log::channel('single')->info("Optimierungsrunde {$round}: {$result['fixed']} Kopplungen in gleichen Raum verschoben");
        }

        // Phase 9.5 (Modul 4.3): Lehrertage kompaktieren
        // Prüfungen von "schwachen" Tagen einer Lehrkraft (nur 1–2 Prüfungen)
        // auf ihre "starken" Tage verschieben, sofern das die Gesamtzahl der
        // Prüfungstage aller Beteiligten (Prüfer + Beisitzer) strikt verringert.
        $compactionMoves = 0;
        if ($this->settings['bundle_teacher_days'] ?? true) {
            for ($round = 1; $round <= 3; $round++) {
                $result = $this->runTeacherDayCompaction($schedule, $timeSlots, $rooms, $examPeriod);
                $schedule = $result['schedule'];
                if ($result['moved'] === 0) {
                    break;
                }
                $compactionMoves += $result['moved'];
                Log::channel('single')->info("Lehrertag-Kompaktierung Runde {$round}: {$result['moved']} Prüfungen verschoben");
            }
        }

        // Phase 10: Zwischenwarnungen bereinigen
        $allScheduledExamIds = array_column($schedule, 'student_exam_id');
        $this->warnings = array_values(array_filter($this->warnings, function($warning) use ($allScheduledExamIds) {
            if ($warning['type'] === 'block_not_scheduled') return false;
            if ($warning['type'] === 'no_week_available') return false;
            // exam_not_scheduled entfernen wenn Rescue-Pass die Prüfung doch eingeplant hat
            if ($warning['type'] === 'exam_not_scheduled') {
                $examId = $warning['details']['student_exam_id'] ?? null;
                if ($examId && in_array($examId, $allScheduledExamIds, true)) {
                    return false;
                }
            }
            return true;
        }));

        Log::channel('single')->info("Planung abgeschlossen", [
            'scheduled' => count($schedule),
            'locked' => $lockedCount,
            'optimization_rounds' => $optimizationRoundsApplied,
            'warnings' => count($this->warnings)
        ]);
        
        return [
            'assignments' => $schedule,
            'warnings' => $this->warnings,
            'stats' => [
                'coupling_blocks_created' => $couplingBlocks->count(),
                'examiner_blocks_created' => $examinerBlocks->count(),
                'exams_scheduled' => count($schedule),
                'locked_exams' => $lockedCount,
                'optimization_rounds' => $optimizationRoundsApplied,
                'teacher_day_compaction_moves' => $compactionMoves,
            ]
        ];
    }

    /**
     * Lädt fixierte Prüfungen und reserviert deren Ressourcen
     * Diese werden bei der Planung NICHT verändert
     */
    private function loadLockedAssignments(ExamPeriod $examPeriod): int
    {
        // $schoolId = $this->settings['school_id'] ?? null;
        
        // Über imPruefungszeitraum() und nicht über studentExam.student: Der
        // Weg über die Beziehung `student` ließ jede fixierte Prüfung eines
        // externen Bewerbers aus — ihr Slot galt der Auto-Planung als frei, und
        // sie wurde beim nächsten Lauf überplant.
        $query = ExamAssignment::where('is_locked', true)
            ->whereHas('studentExam', fn ($q) => $q->imPruefungszeitraum($examPeriod))
            ->with(['studentExam.externalExamSubject.externalApplicant', 'timeSlot']);
            
        $lockedAssignments = $query->get();
        
        foreach ($lockedAssignments as $assignment) {
            $studentExam = $assignment->studentExam;
            $timeSlot = $assignment->timeSlot;

            // Datum vormerken, damit markSlotOccupied die Tages-Belegung
            // (dayLoad + teacherDayLoad) auch für fixierte Prüfungen zählt
            $this->slotDateCache[$assignment->time_slot_id] = Carbon::parse($timeSlot->date)->format('Y-m-d');

            // Slot + Raum als belegt markieren
            $this->markSlotOccupied(
                $assignment->time_slot_id,
                $assignment->exam_room_id,
                $studentExam->examiner_id
            );
            
            // Prüfungstag reservieren (nur externe Bewerber, § 61 Abs. 3 S. 9)
            $this->tagBelegen($studentExam, $timeSlot->date);

            // Schüler-Woche reservieren
            $week = $this->getWeekNumber($timeSlot->date, $examPeriod);
            if ($week > 0) {
                $this->wocheBelegen($studentExam, $week);

                // Wochen-Kohärenz (Modul 4.3): weitere Blöcke dieses Prüfers
                // bevorzugt in dieselbe Woche wie seine fixierten Prüfungen
                if (!isset($this->examinerWeekChoice[$studentExam->examiner_id])) {
                    $this->examinerWeekChoice[$studentExam->examiner_id] = $week;
                }
            }
            
            // Beisitzer als belegt markieren (falls vorhanden - aus StudentExam)
            if ($studentExam->assessor_id) {
                $this->occupyTeacherSlot($studentExam->assessor_id, $assignment->time_slot_id);
            }
        }
        
        return $lockedAssignments->count();
    }

    /**
     * Modul 4.3: Übernimmt die Prüfungstage bereits bestehender, NICHT-fixierter
     * Zuweisungen in die Lehrer-Tages-Belegung (teacherDayLoad).
     *
     * Relevant bei clear_existing=false: Diese Prüfungen bleiben unverändert in
     * der DB (whereDoesntHave('assignment') schließt sie von der Neuplanung aus),
     * ihre Slots werden über die DB-Checks in isRoomOccupied/isTeacherOccupied
     * respektiert – aber ohne dieses Seeding wüsste die Tages-Affinität nichts
     * von diesen Prüfungstagen.
     *
     * Seit der Tagesregel für externe Bewerber (§ 61 Abs. 3 S. 9) übernimmt
     * dieselbe Schleife auch deren Prüfungstage: Eine stehengebliebene Prüfung
     * belegt den Tag, sonst legte die Planung eine zweite daneben.
     */
    private function seedTeacherDayLoadFromExistingAssignments(ExamPeriod $examPeriod): void
    {
        // imPruefungszeitraum() statt studentExam.student — sonst fehlten hier
        // dieselben externen Prüfungen wie in loadLockedAssignments().
        $assignments = ExamAssignment::where('is_locked', false)
            ->where('status', '!=', 'cancelled')
            ->whereHas('studentExam', fn ($q) => $q->imPruefungszeitraum($examPeriod))
            ->with(['studentExam.externalExamSubject.externalApplicant', 'timeSlot'])
            ->get();

        foreach ($assignments as $assignment) {
            if (!$assignment->timeSlot || !$assignment->studentExam) {
                continue;
            }

            $date = Carbon::parse($assignment->timeSlot->date)->format('Y-m-d');

            $this->tagBelegen($assignment->studentExam, $assignment->timeSlot->date);

            $this->teacherDayLoad[$assignment->studentExam->examiner_id][$date] =
                ($this->teacherDayLoad[$assignment->studentExam->examiner_id][$date] ?? 0) + 1;

            if ($assignment->studentExam->assessor_id) {
                $this->teacherDayLoad[$assignment->studentExam->assessor_id][$date] =
                    ($this->teacherDayLoad[$assignment->studentExam->assessor_id][$date] ?? 0) + 1;
            }
        }
    }

    /**
     * Lädt gesperrte Tage aller Schüler des Prüfungszeitraums.
     * Gibt zurück: prueflingsSchluessel => ['Y-m-d', ...]
     *
     * Gesperrte Tage führt das Datenmodell nur für Schüler; für externe
     * Bewerber gibt es sie nicht, deren Schlüssel kommt in dieser Liste also
     * gar nicht vor und isStudentDateBlocked() antwortet mit false.
     *
     * @return array<string, list<string>>
     */
    private function loadStudentBlockedDates(ExamPeriod $examPeriod): array
    {
        $students = \App\Models\Student::where('exam_period_id', $examPeriod->id)
            ->with('blockedDays')
            ->get();

        $result = [];
        foreach ($students as $student) {
            if ($student->blockedDays->isNotEmpty()) {
                $result['s' . $student->id] = $student->blockedDays
                    ->map(fn($b) => $b->date->format('Y-m-d'))
                    ->values()
                    ->all();
            }
        }
        return $result;
    }

    /**
     * Gilt für diesen Prüfling die Regel „höchstens eine Prüfung je Woche"?
     *
     * Für reguläre Schüler ja: Sie haben genau zwei Kolloquien, die auf die
     * beiden Wochen verteilt werden. Für externe Bewerber nein — sie legen nach
     * § 61 Abs. 3 vier mündliche Prüfungen ab (ein Ersatzschüler nach § 64
     * Abs. 2 zwei), und die Regel wäre für sie schlicht nicht erfüllbar: Die
     * Wochenzuweisung hielt genau eine Prüfung je Woche fest, die dritte und
     * vierte fielen mit „beide Wochen bereits belegt" aus der Planung.
     *
     * An ihre Stelle tritt die Tagesregel, siehe tagIstFuerPrueflingBelegt().
     */
    private function wochenRegelGilt(StudentExam $exam): bool
    {
        return !$exam->isExternalApplicant();
    }

    /**
     * Ist die Woche für diesen Prüfling noch frei? Für externe Bewerber immer.
     */
    private function wocheIstFrei(StudentExam $exam, int $week): bool
    {
        if (!$this->wochenRegelGilt($exam)) {
            return true;
        }

        return !isset($this->studentWeekAssignment[$exam->prueflingsSchluessel()][$week]);
    }

    /**
     * Merkt die Woche eines Prüflings vor. Für externe Bewerber ein No-op —
     * sonst blockierte die erste Prüfung die Woche für die zweite.
     */
    private function wocheBelegen(StudentExam $exam, int $week): void
    {
        // Vor der Verzweigung, weil diese Menge für jeden Prüfling gilt.
        $this->verplanteExamIds[$exam->id] = true;

        if (!$this->wochenRegelGilt($exam)) {
            return;
        }

        $this->studentWeekAssignment[$exam->prueflingsSchluessel()][$week] = $exam->id;
    }

    /**
     * Nimmt eine Wochenbelegung zurück — wenn ein Block doch nicht geplant
     * werden konnte, ist seine Prüfung wieder offen.
     */
    private function wocheFreigeben(StudentExam $exam, int $week): void
    {
        unset($this->verplanteExamIds[$exam->id]);

        if (!$this->wochenRegelGilt($exam)) {
            return;
        }

        unset($this->studentWeekAssignment[$exam->prueflingsSchluessel()][$week]);
    }

    /**
     * Merkt den Prüfungstag eines externen Bewerbers vor (§ 61 Abs. 3 S. 9).
     */
    private function tagBelegen(StudentExam $exam, $date): void
    {
        if ($this->wochenRegelGilt($exam)) {
            return;
        }

        $this->externalDayAssignment[$exam->prueflingsSchluessel()]
            [Carbon::parse($date)->format('Y-m-d')] = true;
    }

    /**
     * Gibt den Prüfungstag eines externen Bewerbers wieder frei — nötig, wenn
     * eine bereits eingeplante Prüfung verschoben wird (Tagesbündelung,
     * Rescue-Pass). Ohne das bliebe der alte Tag für immer gesperrt.
     */
    private function tagFreigeben(StudentExam $exam, $date): void
    {
        if ($this->wochenRegelGilt($exam)) {
            return;
        }

        unset(
            $this->externalDayAssignment[$exam->prueflingsSchluessel()]
                [Carbon::parse($date)->format('Y-m-d')]
        );
    }

    /**
     * Hat dieser externe Bewerber an diesem Tag schon eine mündliche Prüfung?
     * Für reguläre Schüler immer false — für sie gibt es die Regel nicht.
     */
    private function tagIstFuerPrueflingBelegt(StudentExam $exam, $date): bool
    {
        if ($this->wochenRegelGilt($exam)) {
            return false;
        }

        return isset(
            $this->externalDayAssignment[$exam->prueflingsSchluessel()]
                [Carbon::parse($date)->format('Y-m-d')]
        );
    }

    /**
     * Prüft ob ein Datum für einen bestimmten Prüfling gesperrt ist.
     */
    private function isStudentDateBlocked(string $studentId, \Carbon\Carbon $date): bool
    {
        if (!isset($this->studentBlockedDates[$studentId])) {
            return false;
        }
        return in_array($date->format('Y-m-d'), $this->studentBlockedDates[$studentId], true);
    }

    /**
     * Lädt Abwesenheitstage aller Lehrer (teacher_unavailability).
     * Gibt zurück: teacher_id => ['Y-m-d', ...]
     *
     * @return array<int, list<string>>
     */
    private function loadTeacherUnavailableDates(): array
    {
        $rows = \App\Models\Teacher::active()->with('unavailabilities')->get();
        $result = [];
        foreach ($rows as $teacher) {
            $dates = [];
            foreach ($teacher->unavailabilities as $u) {
                $dates[] = \Carbon\Carbon::parse($u->date)->format('Y-m-d');
            }
            if (!empty($dates)) {
                $result[$teacher->id] = $dates;
            }
        }
        return $result;
    }

    /**
     * Lädt stundenplanfreie Wochentage aller Lehrkräfte (Modul 4.3, "freie Tage").
     *
     * Datenquelle: TimetableEntry (Stundenplan-Import, siehe /teachers/{id}/timetable).
     * Das Schuljahr wird aus dem Beginn der ersten Kolloquiumswoche abgeleitet
     * (Kolloquien im Frühjahr 2026 → Schuljahr 2025/26); existieren dafür keine
     * Einträge, wird auf das neueste vorhandene Schuljahr zurückgegriffen.
     *
     * Lehrkräfte ohne jegliche Stundenplandaten fehlen im Ergebnis — für sie
     * greift die Penalty nicht ("keine Daten" ≠ "ganze Woche frei"). Damit bleibt
     * die Funktion für Schulen ohne Stundenplan-Import wirkungslos.
     *
     * @return array<int, array<int, true>> teacher_id => [Wochentag 1-5 => true]
     */
    private function loadTeacherFreeWeekdays(ExamPeriod $examPeriod): array
    {
        if (!$examPeriod->week_1_start) {
            return [];
        }

        return \App\Models\TimetableEntry::freeWeekdaysByTeacherForDate(
            Carbon::parse($examPeriod->week_1_start)
        );
    }

    /**
     * Lädt Schüler, deren letzter schriftlicher Prüfungstermin auf dem spätesten
     * Termin des Schuljahres liegt. Diese Schüler sollen in Woche 1 erst ab Mittwoch
     * mündlich geprüft werden (Soft-Constraint per Score-Penalty).
     */
    private function loadStudentsWithLateWrittenExam(ExamPeriod $examPeriod): void
    {
        // Letztes Datum aller schriftlichen Prüfungen dieses Jahrgangs ermitteln
        $maxDate = \App\Models\WrittenExamYearDate::where('exam_year', $examPeriod->year)
            ->max('exam_date');

        if (!$maxDate) {
            return;
        }

        $maxDateStr = Carbon::parse($maxDate)->format('Y-m-d');

        // Alle WrittenExamYearDate-IDs mit diesem Maximaldatum
        $yearDateIds = \App\Models\WrittenExamYearDate::where('exam_year', $examPeriod->year)
            ->whereDate('exam_date', $maxDateStr)
            ->pluck('id')
            ->toArray();

        if (empty($yearDateIds)) {
            return;
        }

        // Zugehörige WrittenExamAssignment-IDs (aus Tenant-DB)
        $assignmentIds = \App\Models\WrittenExamAssignment::whereIn('written_exam_year_date_id', $yearDateIds)
            ->pluck('id')
            ->toArray();

        if (empty($assignmentIds)) {
            return;
        }

        // Schüler-IDs aus den Sitzplatzzuordnungen, als Prüflingsschlüssel.
        // Der schriftliche Sitzplan kennt bisher nur Schüler; sobald er auch
        // externe Bewerber führt, kommt deren Schlüssel hier von selbst dazu.
        $studentIds = \App\Models\WrittenExamSeatAssignment::whereIn('written_exam_assignment_id', $assignmentIds)
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->unique()
            ->map(fn ($id): string => 's' . $id)
            ->values()
            ->all();

        $this->studentsWithLateWrittenExam = $studentIds;

        // Vermeidungstermine: Montag und Dienstag der Woche 1, sofern der letzte schriftliche
        // Termin kurz vor Beginn von Woche 1 liegt (≤ 7 Tage davor)
        $week1Start   = Carbon::parse($examPeriod->week_1_start);
        $week1End     = Carbon::parse($examPeriod->week_1_end);
        $lastWritten  = Carbon::parse($maxDateStr);

        if ($lastWritten->lte($week1Start) && $lastWritten->diffInDays($week1Start) <= 7) {
            $day = $week1Start->copy();
            while ($day->lte($week1End)) {
                if ($day->dayOfWeek === Carbon::MONDAY || $day->dayOfWeek === Carbon::TUESDAY) {
                    $this->avoidDatesForLateExamStudents[] = $day->format('Y-m-d');
                }
                $day->addDay();
            }
        }

        Log::channel('single')->info("Schüler mit letzter schriftlicher Prüfung geladen", [
            'count'        => count($studentIds),
            'last_written' => $maxDateStr,
            'avoid_dates'  => $this->avoidDatesForLateExamStudents,
        ]);
    }

    /**
     * Modul 4.5: Ermittelt Team-Partner unter den Lehrkräften.
     *
     * Zwei Lehrkräfte X und Y gelten als Team-Partner, wenn sie bei mindestens
     * einer noch nicht fixierten Kolloquiumsprüfung gemeinsam als Prüfer und
     * Beisitzer auftreten – unabhängig davon, wer welche Rolle hat, und
     * unabhängig vom Kurs (Abgrenzung zu 4.7/coupling_id, die kursgebunden ist).
     * Bei zwei Prüfungen mit demselben Paar in vertauschten Rollen (X prüft/Y
     * assistiert bei A, Y prüft/X assistiert bei B) sowie bei gleicher Rolle
     * (X+Y sind bei A und B jeweils Prüfer+Beisitzer) wird das Paar erkannt.
     *
     * Ergebnis dient ausschließlich als Optimizer-Bonus in calculateSlotScore()
     * (bevorzugt gemeinsamer Tag) – keine Persistierung, keine harte Regel.
     */
    private function loadTeamPartners(ExamPeriod $examPeriod): void
    {
        $exams = StudentExam::imPruefungszeitraum($examPeriod)
        ->whereDoesntHave('assignment') // fixierte Prüfungen (is_locked) lösen keinen neuen Bonus aus
        ->where('exam_type', 'oral')
        ->whereNotNull('assessor_id')
        ->get(['id', 'examiner_id', 'assessor_id']);

        foreach ($exams as $exam) {
            $a = $exam->examiner_id;
            $b = $exam->assessor_id;
            if ($a === null || $b === null || $a === $b) {
                continue;
            }
            $this->teamPartners[$a][$b] = true;
            $this->teamPartners[$b][$a] = true;
        }
    }

    /**
     * Hartes Kriterium: Ist ein Lehrer an einem Datum als abwesend eingetragen?
     */
    private function isTeacherUnavailableOnDate(int $teacherId, \Carbon\Carbon $date): bool
    {
        if (!isset($this->teacherUnavailableDates[$teacherId])) {
            return false;
        }
        return in_array($date->format('Y-m-d'), $this->teacherUnavailableDates[$teacherId], true);
    }

    /**
     * Lädt die Planungseinstellungen
     * WICHTIG: prefer_early_slots, prefer_early_days, avoid_friday werden nicht mehr verwendet!
     * Stattdessen wird preference_weight aus TimeSlot verwendet.
     */
    private function loadSettings(ExamPeriod $examPeriod, array $options): array
    {
        $defaults = [
            'max_consecutive_exams' => 5,
            'allow_block_overflow' => true,
            'max_exams_per_teacher_per_day' => 10,
            'respect_unavailability' => true,
            'lf_preferred_week' => null, // null = keine Präferenz, 1 oder 2 = LF-Kolloquien bevorzugt in diese Woche
            'bundle_teacher_days' => true, // Modul 4.3: Prüfungstage pro Lehrkraft bündeln
            'respect_free_days' => true, // Modul 4.3: stundenplanfreie Tage möglichst meiden (weiche Präferenz)
        ];
        
        $periodSettings = $examPeriod->planning_settings ?? [];
        
        return array_merge($defaults, $periodSettings, $options);
    }

    /**
     * Prüft ob genügend Kapazität vorhanden ist
     * Da exam_week noch nicht gesetzt ist, teilen wir die Gesamtprüfungen auf 2 Wochen
     */
    private function checkCapacity(ExamPeriod $examPeriod): array
    {
        // $schoolId = $this->settings['school_id'] ?? null;
        
        // Zähle ALLE ungeplanten MÜNDLICHEN Prüfungen (exam_type = 'oral')
        // Schriftliche Prüfungen werden separat geplant!
        // Reguläre und externe Prüflinge des Zeitraums (Modul 2.1)
        $query = StudentExam::imPruefungszeitraum($examPeriod)
        ->whereDoesntHave('assignment')
        ->where('exam_type', 'oral'); // NUR KOLLOQUIEN
        
        $totalExams = $query->count();
        
        // Bei 2 Prüfungen pro Schüler: Hälfte pro Woche
        $examsPerWeek = (int) ceil($totalExams / 2);
        
        ['roomCount' => $roomCount, 'slotsWeek1' => $slotsWeek1, 'slotsWeek2' => $slotsWeek2]
            = $this->examRoomAndSlotCounts($examPeriod);

        $capacityWeek1 = $slotsWeek1 * $roomCount;
        $capacityWeek2 = $slotsWeek2 * $roomCount;
        
        $warnings = [];
        
        Log::channel('single')->info("Kapazitätsprüfung", [
            'total_exams' => $totalExams,
            'exams_per_week' => $examsPerWeek,
            'capacity_week1' => $capacityWeek1,
            'capacity_week2' => $capacityWeek2,
        ]);
        
        if ($examsPerWeek > $capacityWeek1) {
            $warnings[] = [
                'type' => 'insufficient_capacity',
                'severity' => 'error',
                'message' => "Woche 1: ~{$examsPerWeek} Prüfungen, aber nur {$capacityWeek1} Plätze",
            ];
        }
        
        if ($examsPerWeek > $capacityWeek2) {
            $warnings[] = [
                'type' => 'insufficient_capacity',
                'severity' => 'error',
                'message' => "Woche 2: ~{$examsPerWeek} Prüfungen, aber nur {$capacityWeek2} Plätze",
            ];
        }
        
        return [
            'sufficient' => empty($warnings),
            'warnings' => $warnings,
        ];
    }

    /**
     * Verfügbare Prüfungsräume und Zeitslots je Kolloquiumswoche für einen
     * Prüfungszeitraum. Gemeinsame Grundlage für die Kapazitätsprüfung
     * (checkCapacity()) und die Wochenzuteilung (assignWeeksToBlocks()).
     *
     * @return array{roomCount: int, slotsWeek1: int, slotsWeek2: int}
     */
    private function examRoomAndSlotCounts(ExamPeriod $examPeriod): array
    {
        $roomCount = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->where('room_role', 'exam')
            ->where('date', '1900-01-01')
            ->count();
        if ($roomCount === 0) {
            $roomCount = Room::where('room_type', 'exam')->where('is_active', true)->count();
        }

        $slotsWeek1 = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->whereBetween('date', [$examPeriod->week_1_start, $examPeriod->week_1_end])
            ->count();

        $slotsWeek2 = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->whereBetween('date', [$examPeriod->week_2_start, $examPeriod->week_2_end])
            ->count();

        return ['roomCount' => $roomCount, 'slotsWeek1' => $slotsWeek1, 'slotsWeek2' => $slotsWeek2];
    }

    /**
     * Bildet Kopplungsblöcke basierend auf coupling_id
     * Diese haben HÖCHSTE PRIORITÄT und werden ZUERST geplant
     * Gekoppelte Prüfungen müssen IMMER zusammenbleiben (keine Größenbeschränkung)
     * 
     * WICHTIG: Nur KOLLOQUIEN (exam_type = 'oral') werden geplant!
     */
    private function buildCouplingBlocks(ExamPeriod $examPeriod): Collection
    {
        $maxSize = $this->settings['max_consecutive_exams'];

        // $schoolId = $this->settings['school_id'] ?? null;
        
        // Reguläre und externe Prüflinge des Zeitraums (Modul 2.1)
        $query = StudentExam::imPruefungszeitraum($examPeriod)
        ->whereDoesntHave('assignment')
        ->where('exam_type', 'oral') // NUR KOLLOQUIEN
        ->whereNotNull('coupling_id') // NUR gekoppelte Prüfungen
        ->with(['student', 'course.subject', 'examiner', 'assessor', 'externalExamSubject.subject', 'externalExamSubject.externalApplicant']);
        
        $allExams = $query->get();
        
        // Gruppiere nach coupling_id
        $couplingGroups = $allExams->groupBy('coupling_id');
        
        $blocks = collect();
        
        foreach ($couplingGroups as $couplingId => $exams) {
            $examArray = $exams->values()->all();
            $count = count($examArray);
            
            // Validierung: Alle Prüfungen sollten im gleichen Kurs sein
            $courseIds = $exams->pluck('course_id')->unique();
            if ($courseIds->count() > 1) {
                $this->warnings[] = [
                    'type' => 'coupling_multiple_courses',
                    'severity' => 'warning',
                    'message' => "Kopplung {$couplingId}: Prüfungen aus verschiedenen Kursen - wird trotzdem zusammen geplant",
                ];
            }

            $baseBlockData = [
                'type'        => 'coupling',
                'coupling_id' => $couplingId,
                'examiner_id' => $exams->first()->examiner_id,
                'course_id'   => $exams->first()->course_id,
                'assessor_id' => $exams->first()->assessor_id,
                'assigned_week' => null,
            ];

            if ($count <= $maxSize) {
                // Kopplung passt auf einen Tag → als einzelner Block
                $blocks->push(array_merge($baseBlockData, [
                    'key'   => "coupling_{$couplingId}",
                    'exams' => $examArray,
                    'size'  => $count,
                ]));
            } else {
                // Kopplung zu groß für einen Tag → auf mehrere Tages-Blöcke aufteilen
                $splitBlocks = $this->splitBlock($examArray, $maxSize);
                foreach ($splitBlocks as $index => $blockExams) {
                    $blocks->push(array_merge($baseBlockData, [
                        'key'   => "coupling_{$couplingId}_part{$index}",
                        'exams' => $blockExams,
                        'size'  => count($blockExams),
                    ]));
                }
                Log::channel('single')->info("Großer Kopplungsblock aufgeteilt", [
                    'coupling_id' => $couplingId,
                    'total_size'  => $count,
                    'parts'       => count($splitBlocks),
                ]);
            }

            Log::channel('single')->debug("Kopplungsblock erstellt", [
                'coupling_id' => $couplingId,
                'size' => $count,
                'course_id' => $exams->first()->course_id,
            ]);
        }
        
        // Sortiere: Größte Blöcke zuerst (schwieriger zu planen)
        return $blocks->sortByDesc('size');
    }

    /**
     * Bildet Prüferblöcke (OHNE exam_week, da noch nicht gesetzt)
     * Gruppiert nach: course_id + examiner_id
     * WICHTIG: Schließt bereits in Kopplungen geplante Prüfungen aus!
     * WICHTIG: Nur KOLLOQUIEN (exam_type = 'oral') werden geplant!
     */
    private function buildExaminerBlocks(ExamPeriod $examPeriod): Collection
    {
        $maxSize = $this->settings['max_consecutive_exams'];
        // $schoolId = $this->settings['school_id'] ?? null;
        
        // Reguläre und externe Prüflinge des Zeitraums (Modul 2.1)
        $query = StudentExam::imPruefungszeitraum($examPeriod)
        ->whereDoesntHave('assignment')
        ->where('exam_type', 'oral') // NUR KOLLOQUIEN
        ->whereNull('coupling_id') // NUR ungekoppelte Prüfungen (gekoppelte wurden bereits in buildCouplingBlocks verarbeitet)
        ->with(['student', 'course.subject', 'examiner', 'externalExamSubject.subject', 'externalExamSubject.externalApplicant']);
        
        $allExams = $query->get();
        
        // Gruppiere nach: course_id + examiner_id (OHNE exam_week!)
        $groups = $allExams->groupBy(function($exam) {
            // Externe Bewerber haben keinen Kurs. Ihre Prüfungen über den
            // Prüfer zusammenzufassen wäre doppelt falsch: zwei Fächer
            // desselben Bewerbers landeten in einem Block und damit in einer
            // Woche (die zweite überschriebe die erste in der Wochenzuweisung),
            // und § 61 Abs. 3 S. 9 will für Externe ohnehin höchstens eine
            // mündliche Prüfung pro Tag. Jede externe Prüfung ist ihr eigener
            // Block.
            if ($exam->isExternalApplicant()) {
                return "ext{$exam->external_exam_subject_id}_{$exam->examiner_id}";
            }

            return "{$exam->course_id}_{$exam->examiner_id}";
        });
        
        $blocks = collect();
        
        foreach ($groups as $groupKey => $exams) {
            $examArray = $exams->values()->all();
            $count = count($examArray);
            
            if ($count <= $maxSize) {
                $blocks->push([
                    'key' => $groupKey,
                    'type' => 'examiner', // Markierung als Prüferblock
                    'exams' => $examArray,
                    'examiner_id' => $exams->first()->examiner_id,
                    'course_id' => $exams->first()->course_id,
                    'size' => $count,
                    'assigned_week' => null, // Wird später gesetzt
                ]);
            } else {
                // Block aufteilen
                $splitBlocks = $this->splitBlock($examArray, $maxSize);
                foreach ($splitBlocks as $index => $blockExams) {
                    $blocks->push([
                        'key' => "{$groupKey}_part{$index}",
                        'type' => 'examiner',
                        'exams' => $blockExams,
                        'examiner_id' => $exams->first()->examiner_id,
                        'course_id' => $exams->first()->course_id,
                        'size' => count($blockExams),
                        'assigned_week' => null,
                    ]);
                }
            }
        }

        // Modul 4.3 (B1): Blöcke desselben Prüfers direkt nacheinander verarbeiten.
        // Zusammen mit der Tages-Affinität in calculateSlotScore landen sie so am
        // selben Tag und – da belegte Nachbarslots die Blocksuche einschränken –
        // möglichst am Stück. Prüfer mit den meisten Prüfungen zuerst
        // (am schwierigsten zu platzieren), innerhalb eines Prüfers größte Blöcke zuerst.
        if ($this->settings['bundle_teacher_days'] ?? true) {
            $examinerTotals = [];
            foreach ($blocks as $b) {
                $examinerTotals[$b['examiner_id']] = ($examinerTotals[$b['examiner_id']] ?? 0) + $b['size'];
            }

            return $blocks->sort(function (array $a, array $b) use ($examinerTotals) {
                return ($examinerTotals[$b['examiner_id']] <=> $examinerTotals[$a['examiner_id']])
                    ?: ($a['examiner_id'] <=> $b['examiner_id'])
                    ?: ($b['size'] <=> $a['size']);
            })->values();
        }

        // Sortiere: Größte Blöcke zuerst
        return $blocks->sortByDesc('size');
    }

    /**
     * Weist Blöcken eine Woche zu unter Berücksichtigung der Schüler-Constraint:
     * Jeder Schüler darf nur EINE Prüfung pro Woche haben.
     */
    private function assignWeeksToBlocks(Collection $blocks, ExamPeriod $examPeriod): Collection
    {
        // $schoolId = $this->settings['school_id'] ?? null;

        // Zähle Kapazität pro Woche
        ['roomCount' => $roomCount, 'slotsWeek1' => $slotsWeek1, 'slotsWeek2' => $slotsWeek2]
            = $this->examRoomAndSlotCounts($examPeriod);

        $remainingCapacity = [
            1 => $slotsWeek1 * $roomCount,
            2 => $slotsWeek2 * $roomCount,
        ];
        
        $assignedBlocks = collect();

        // LF-Präferenz: LF-Blöcke ZUERST verarbeiten, damit sie ihre bevorzugte Woche
        // sichern können, bevor Nicht-LF-Blöcke Schüler-Slots belegen.
        $lfPreferredWeek = $this->settings['lf_preferred_week'] ?? null;
        if ($lfPreferredWeek) {
            $blocks = $blocks->sortBy(function($block) {
                return $this->blockIsLeistungsfach($block) ? 0 : 1;
            })->values();
        }
        
        foreach ($blocks as $block) {
            $blockSize = $block['size'];
            
            // Prüfe welche Woche möglich ist (Schüler-Constraints + Prüfer-Verfügbarkeit)
            $possibleWeeks = $this->getPossibleWeeksForBlock($block);

            // Wochen herausfiltern, in denen der Prüfer komplett abwesend ist
            // (z.B. FÄR ist ganze Woche 1 abwesend → Woche 1 erst gar nicht versuchen)
            $examinerId = $block['examiner_id'] ?? null;
            if ($examinerId && !empty($possibleWeeks)) {
                $possibleWeeks = array_values(array_filter(
                    $possibleWeeks,
                    fn($w) => $this->teacherHasAvailableWeek($examinerId, $w, $examPeriod)
                ));
            }
            
            if (empty($possibleWeeks)) {
                $skipSplit = false; // true wenn Backtracking für Kopplung erfolgreich war

                if (($block['type'] ?? '') === 'coupling') {
                    // Backtracking: Versuche blockierende Gegenkopplung in andere Woche zu verschieben
                    $newAssignedBlocks = $this->tryBacktrackCouplingWeek($block, $assignedBlocks, $examPeriod);
                    if ($newAssignedBlocks !== null) {
                        $assignedBlocks = $newAssignedBlocks;
                        $possibleWeeks  = $this->getPossibleWeeksForBlock($block);
                        if ($examinerId && !empty($possibleWeeks)) {
                            $possibleWeeks = array_values(array_filter(
                                $possibleWeeks,
                                fn($w) => $this->teacherHasAvailableWeek($examinerId, $w, $examPeriod)
                            ));
                        }
                        if (!empty($possibleWeeks)) {
                            $skipSplit = true; // Backtracking erfolgreich → normal weiter
                        }
                    }

                    if (!$skipSplit) {
                        // Backtracking gescheitert – warnen, dann aufgetrennt einplanen
                        $studentNames = implode(', ', array_map(fn($e) => $e->getStudentName(), $block['exams']));
                        $conflictInfo = $this->describeCouplingConflict($block);
                        $this->warnings[] = [
                            'type'     => 'coupling_no_week_available',
                            'severity' => 'error',
                            'message'  => "Kopplung ({$studentNames}): Keine gemeinsame Woche möglich – wird aufgetrennt eingeplant und im Plan markiert. Konflikt: {$conflictInfo}",
                        ];
                    }
                }

                if (!$skipSplit) {
                    // Split-Code: Schüler nach freier Woche aufteilen
                    // Gilt für Prüferblöcke und Kopplungen die aufgetrennt werden müssen
                    $isCouplingConflict = ($block['type'] ?? '') === 'coupling';
                    $week1Exams    = [];
                    $week2Exams    = [];
                    $bothFreeExams = [];
                    $noWeekExams   = [];

                    foreach ($block['exams'] as $exam) {
                        $w1Free = $this->wocheIstFrei($exam, 1);
                        $w2Free = $this->wocheIstFrei($exam, 2);
                        if ($w1Free && $w2Free)      { $bothFreeExams[] = $exam; }
                        elseif ($w1Free)             { $week1Exams[]    = $exam; }
                        elseif ($w2Free)             { $week2Exams[]    = $exam; }
                        else                         { $noWeekExams[]   = $exam; }
                    }

                    foreach ($bothFreeExams as $exam) {
                        if (count($week1Exams) <= count($week2Exams)) { $week1Exams[] = $exam; }
                        else                                           { $week2Exams[] = $exam; }
                    }

                    if (!empty($week1Exams)) {
                        foreach ($week1Exams as $exam) {
                            if ($this->wocheIstFrei($exam, 1)) {
                                $this->wocheBelegen($exam, 1);
                            }
                        }
                        $assignedBlocks->push(array_merge($block, [
                            'key'              => $block['key'] . '_w1split',
                            'exams'            => $week1Exams,
                            'size'             => count($week1Exams),
                            'assigned_week'    => 1,
                            'coupling_conflict' => $isCouplingConflict,
                        ]));
                        $remainingCapacity[1] -= count($week1Exams);
                    }

                    if (!empty($week2Exams)) {
                        foreach ($week2Exams as $exam) {
                            if ($this->wocheIstFrei($exam, 2)) {
                                $this->wocheBelegen($exam, 2);
                            }
                        }
                        $assignedBlocks->push(array_merge($block, [
                            'key'              => $block['key'] . '_w2split',
                            'exams'            => $week2Exams,
                            'size'             => count($week2Exams),
                            'assigned_week'    => 2,
                            'coupling_conflict' => $isCouplingConflict,
                        ]));
                        $remainingCapacity[2] -= count($week2Exams);
                    }

                    foreach ($noWeekExams as $exam) {
                        $studentName = $exam->getStudentName();
                        $courseCode  = $exam->getSubjectLabel();
                        $this->warnings[] = [
                            'type'     => 'student_both_weeks_full',
                            'severity' => 'error',
                            'message'  => "Prüfung nicht planbar: {$studentName} ({$courseCode}) – beide Wochen bereits belegt",
                        ];
                    }

                    continue;
                }
                // $skipSplit = true → Backtracking erfolgreich, fall-through zur Wochenzuweisung
            }
            
            // Wähle Woche:
            // - Bei LF-Präferenz und LF-Block: bevorzugte Woche erzwingen (wenn möglich)
            // - Sonst: Woche mit mehr verbleibender Kapazität
            $chosenWeek = null;

            if ($lfPreferredWeek && $this->blockIsLeistungsfach($block) && in_array($lfPreferredWeek, $possibleWeeks)) {
                // LF-Präferenz: bevorzugte Woche direkt nehmen (Reihenfolge ist bereits sichergestellt)
                $chosenWeek = $lfPreferredWeek;
                // Fallback auf andere Woche wenn bevorzugte Woche keine Kapazität hat
                if ($remainingCapacity[$chosenWeek] < $blockSize) {
                    $others = array_diff($possibleWeeks, [$lfPreferredWeek]);
                    $chosenWeek = !empty($others) ? reset($others) : $chosenWeek;
                }
            } else {
                // Wochen-Kohärenz (Modul 4.3): weitere Blöcke desselben Prüfers
                // bevorzugt in seine bereits gewählte Woche legen – Bündelung auf
                // wenige Tage funktioniert nur innerhalb derselben Woche
                $examinerPrevWeek = (($this->settings['bundle_teacher_days'] ?? true) && $examinerId)
                    ? ($this->examinerWeekChoice[$examinerId] ?? null)
                    : null;

                if ($examinerPrevWeek !== null
                    && in_array($examinerPrevWeek, $possibleWeeks)
                    && $remainingCapacity[$examinerPrevWeek] >= $blockSize) {
                    $chosenWeek = $examinerPrevWeek;
                } else {
                    // Keine LF-Präferenz: Woche mit mehr Kapazität bevorzugen
                    foreach ($possibleWeeks as $week) {
                        if ($remainingCapacity[$week] >= $blockSize) {
                            if ($chosenWeek === null || $remainingCapacity[$week] > $remainingCapacity[$chosenWeek]) {
                                $chosenWeek = $week;
                            }
                        }
                    }
                    // Fallback: Woche mit mehr Kapazität auch wenn nicht ausreichend
                    if ($chosenWeek === null) {
                        $chosenWeek = $remainingCapacity[$possibleWeeks[0]] >= $remainingCapacity[$possibleWeeks[1] ?? $possibleWeeks[0]]
                            ? $possibleWeeks[0]
                            : ($possibleWeeks[1] ?? $possibleWeeks[0]);
                    }
                }
            }
            
            if ($chosenWeek) {
                $block['assigned_week'] = $chosenWeek;
                $assignedBlocks->push($block);
                $remainingCapacity[$chosenWeek] -= $blockSize;

                // Wochen-Kohärenz (Modul 4.3): erste Wochenwahl des Prüfers merken
                if ($examinerId && !isset($this->examinerWeekChoice[$examinerId])) {
                    $this->examinerWeekChoice[$examinerId] = $chosenWeek;
                }
                
                // Markiere Schüler VORLÄUFIG als in dieser Woche belegt
                // (wird in scheduleBlocks wieder entfernt falls Block nicht geplant werden kann)
                foreach ($block['exams'] as $exam) {
                    $this->wocheBelegen($exam, $chosenWeek);
                }
                
                Log::channel('single')->debug("Block zugewiesen", [
                    'key' => $block['key'],
                    'week' => $chosenWeek,
                    'size' => $blockSize,
                ]);
            }
        }
        
        return $assignedBlocks;
    }

    /**
     * Ermittelt welche Wochen für einen Block möglich sind
     * Eine Woche ist NICHT möglich, wenn ein Schüler des Blocks dort schon eine Prüfung hat
     */
    private function getPossibleWeeksForBlock(array $block): array
    {
        $possibleWeeks = [1, 2];
        
        foreach ($block['exams'] as $exam) {
            // wocheIstFrei() antwortet für externe Bewerber immer mit true:
            // Für sie ordnet die Tagesregel die Verteilung, nicht die Woche.
            foreach ([1, 2] as $week) {
                if (!$this->wocheIstFrei($exam, $week)) {
                    $possibleWeeks = array_diff($possibleWeeks, [$week]);
                }
            }
        }
        
        return array_values($possibleWeeks);
    }

    /**
     * Prüft ob ein Lehrer in einer gegebenen Woche mindestens einen Tag verfügbar ist.
     * Verhindert, dass ein Block in eine Woche eingeplant wird, in der der Prüfer
     * komplett abwesend ist (z.B. wegen Urlaub oder Krankheit).
     */
    private function teacherHasAvailableWeek(int $teacherId, int $week, ExamPeriod $examPeriod): bool
    {
        $start = Carbon::parse($week === 1 ? $examPeriod->week_1_start : $examPeriod->week_2_start);
        $end   = Carbon::parse($week === 1 ? $examPeriod->week_1_end   : $examPeriod->week_2_end);

        $current = $start->copy();
        while ($current->lte($end)) {
            if (!$current->isWeekend() && !$this->isTeacherUnavailableOnDate($teacherId, $current)) {
                return true;
            }
            $current->addDay();
        }
        return false;
    }

    /**
     * Backtracking: Versucht eine blockierende Gegenkopplung in die andere Woche zu verschieben,
     * damit die fehlgeschlagene Kopplung eine Woche zugewiesen bekommt.
     * Gibt die aktualisierte $assignedBlocks-Collection zurück, oder null bei Misserfolg.
     *
     * @param  array<string,mixed>       $failingBlock
     * @param  Collection<int,mixed>     $assignedBlocks
     * @return Collection<int,mixed>|null
     */
    private function tryBacktrackCouplingWeek(
        array $failingBlock,
        Collection $assignedBlocks,
        ExamPeriod $examPeriod
    ): ?Collection {
        foreach ($failingBlock['exams'] as $exam) {
            $studentId = $exam->prueflingsSchluessel();

            foreach ([1, 2] as $blockedWeek) {
                if (!isset($this->studentWeekAssignment[$studentId][$blockedWeek])) {
                    continue;
                }

                // Welche Kopplung blockiert diese Woche für diesen Schüler?
                $blockingExamId  = $this->studentWeekAssignment[$studentId][$blockedWeek];
                $blockingExam    = StudentExam::find($blockingExamId);
                if (!$blockingExam || !$blockingExam->coupling_id) {
                    continue;
                }

                $blockingCouplingId = $blockingExam->coupling_id;
                $targetWeek         = $blockedWeek === 1 ? 2 : 1;

                // Blockierenden Block in $assignedBlocks finden
                $blockingBlock = $assignedBlocks->first(
                    fn($b) => ($b['coupling_id'] ?? null) === $blockingCouplingId
                );
                if (!$blockingBlock) {
                    continue;
                }

                // Können alle Schüler des blockierenden Blocks in die Zielwoche?
                $canSwap = true;
                foreach ($blockingBlock['exams'] as $be) {
                    if (isset($this->studentWeekAssignment[$be->prueflingsSchluessel()][$targetWeek])) {
                        $canSwap = false;
                        break;
                    }
                }
                if (!$canSwap) {
                    continue;
                }

                // Prüfer des blockierenden Blocks in Zielwoche verfügbar?
                $blockingExaminerId = $blockingBlock['examiner_id'] ?? null;
                if ($blockingExaminerId && !$this->teacherHasAvailableWeek($blockingExaminerId, $targetWeek, $examPeriod)) {
                    continue;
                }

                // Tausch durchführen: studentWeekAssignment aktualisieren
                foreach ($blockingBlock['exams'] as $be) {
                    unset($this->studentWeekAssignment[$be->prueflingsSchluessel()][$blockedWeek]);
                    $this->studentWeekAssignment[$be->prueflingsSchluessel()][$targetWeek] = $be->id;
                }

                // assigned_week im Collection-Eintrag aktualisieren
                $updated = $assignedBlocks->map(function ($b) use ($blockingCouplingId, $targetWeek) {
                    if (($b['coupling_id'] ?? null) === $blockingCouplingId) {
                        $b['assigned_week'] = $targetWeek;
                    }
                    return $b;
                });

                Log::channel('single')->info("Backtracking: Kopplung {$blockingCouplingId} W{$blockedWeek}→W{$targetWeek} für {$failingBlock['coupling_id']}");
                return $updated;
            }
        }

        return null; // Kein Tausch möglich
    }

    /**
     * Beschreibt den Wochenkonflikt einer Kopplung für die Fehlermeldung.
     * Listet pro Schüler auf, welche Kopplung seine Wochen blockiert.
     */
    private function describeCouplingConflict(array $block): string
    {
        $parts = [];
        foreach ($block['exams'] as $exam) {
            $studentId   = $exam->prueflingsSchluessel();
            $studentName = $exam->getStudentName();
            foreach ([1, 2] as $week) {
                if (isset($this->studentWeekAssignment[$studentId][$week])) {
                    $blockingExam     = StudentExam::find($this->studentWeekAssignment[$studentId][$week]);
                    $blockingCourse   = $blockingExam?->course->course_code ?? '?';
                    $blockingCoupling = $blockingExam?->coupling_id ? '' : ' (ohne Kopplung)';
                    $parts[] = "{$studentName}: W{$week} belegt durch {$blockingCourse}{$blockingCoupling}";
                }
            }
        }
        return implode(' | ', $parts);
    }

    /**
     * Teilt einen großen Block optimal auf
     */
    private function splitBlock(array $exams, int $maxSize): array
    {
        $count = count($exams);
        $numBlocks = (int) ceil($count / $maxSize);
        $baseSize = (int) floor($count / $numBlocks);
        $remainder = $count % $numBlocks;
        
        $blocks = [];
        $offset = 0;
        
        for ($i = 0; $i < $numBlocks; $i++) {
            $blockSize = $baseSize + ($i < $remainder ? 1 : 0);
            $blockSize = min($blockSize, $maxSize + 1);
            $blocks[] = array_slice($exams, $offset, $blockSize);
            $offset += $blockSize;
        }
        
        return $blocks;
    }

    /**
     * Plant alle Blöcke auf verfügbare Zeitslots
     */
    private function scheduleBlocks(
        Collection $blocks, 
        Collection $timeSlots, 
        Collection $rooms,
        ExamPeriod $examPeriod
    ): array {
        $schedule = [];
        
        foreach ($blocks as $block) {
            if (!isset($block['assigned_week'])) {
                continue;
            }
            
            $result = $this->scheduleBlock($block, $timeSlots, $rooms, $examPeriod);
            
            if ($result) {
                $schedule = array_merge($schedule, $result);
            } else {
                // Block konnte nicht geplant werden - Schüler-Markierungen wieder entfernen!
                foreach ($block['exams'] as $exam) {
                    $this->wocheFreigeben($exam, $block['assigned_week']);
                }
                
                // Detaillierte Warnung mit Schülerinformationen
                $studentNames = [];
                foreach ($block['exams'] as $exam) {
                    $studentNames[] = $exam->getStudentName();
                }
                
                $examiner = $block['exams'][0]->examiner ?? null;
                $examinerName = $examiner->shortcode ?? 'unbekannt';
                $course = $block['exams'][0]->course ?? null;
                $courseCode = $course->course_code ?? 'unbekannt';
                
                $this->warnings[] = [
                    'type' => 'block_not_scheduled',
                    'severity' => 'warning',
                    'message' => isset($block['coupling_id'])
                        ? "Kopplungsblock nicht planbar (coupling {$block['coupling_id']}): {$block['size']} Prüfungen konnten nicht zusammen in Woche {$block['assigned_week']} platziert werden – bitte manuell planen"
                        : "Block nicht geplant: {$block['size']} Prüfungen, Woche {$block['assigned_week']} - Einzelplanung wird versucht",
                    'details' => [
                        'block_key' => $block['key'],
                        'examiner' => $examinerName,
                        'course' => $courseCode,
                        'week' => $block['assigned_week'],
                        'size' => $block['size'],
                        'students' => $studentNames,
                    ]
                ];
            }
        }
        
        return $schedule;
    }

    /**
     * Plant einen einzelnen Block auf aufeinanderfolgende Zeitslots
     * WICHTIG: Berücksichtigt auch Beisitzer-Verfügbarkeit bei Kopplungsblöcken
     * WICHTIG: Slot-Präferenz wird über preference_weight gesteuert, nicht über Freitags-Filter
     */
    private function scheduleBlock(
        array $block, 
        Collection $timeSlots, 
        Collection $rooms,
        ExamPeriod $examPeriod
    ): ?array {
        $blockSize = $block['size'];
        $assignedWeek = $block['assigned_week'];
        $examinerId = $block['examiner_id'];
        // Beisitzer pro Prüfung aus den einzelnen Exam-Objekten lesen
        // (block-weites assessor_id gibt es nur bei Kopplungen, nicht bei Prüferblöcken)
        $perExamAssessorIds = array_map(fn($exam) => $exam->assessor_id, $block['exams']);
        
        // Filtere Slots nach zugewiesener Woche
        $weekSlots = $timeSlots->filter(function($slot) use ($examPeriod, $assignedWeek) {
            return $this->getWeekNumber($slot->date, $examPeriod) === $assignedWeek;
        });

        // Entferne Slots an Tagen, die für mindestens einen Schüler im Block gesperrt sind
        $studentIds = array_map(fn($exam) => $exam->prueflingsSchluessel(), $block['exams']);
        $weekSlots = $weekSlots->filter(function($slot) use ($studentIds) {
            $date = Carbon::parse($slot->date);
            foreach ($studentIds as $sid) {
                if ($this->isStudentDateBlocked($sid, $date)) {
                    return false;
                }
            }
            return true;
        });

        // § 61 Abs. 3 S. 9: An einem Tag, an dem ein externer Bewerber des
        // Blocks schon eine mündliche Prüfung hat, kommt keine zweite dazu.
        // Für reguläre Schüler antwortet die Prüfung immer mit false, der
        // Filter kostet sie also nichts.
        $weekSlots = $weekSlots->filter(function ($slot) use ($block): bool {
            foreach ($block['exams'] as $exam) {
                if ($this->tagIstFuerPrueflingBelegt($exam, $slot->date)) {
                    return false;
                }
            }

            return true;
        });

        // Per-Prüfung-Prüfer-IDs (wichtig für Kopplungsblöcke mit verschiedenen Prüfern)
        $perExamExaminerIds = array_map(fn($exam) => $exam->examiner_id, $block['exams']);

        // Finde beste Startposition für zusammenhängende Slots
        // Prüft pro Position sowohl den Prüfer als auch den Beisitzer jedes Exams
        $bestStart = $this->findBestBlockStart($weekSlots, $blockSize, $examinerId, $rooms, null, $perExamAssessorIds, $perExamExaminerIds);
        $slotsUsedForBestStart = $weekSlots;
        
        if ($bestStart) {
            // Sammle die Slots für diesen Block
            // WICHTIG: dieselbe Collection verwenden, aus der slot_index berechnet wurde!
            $slotsArray = $slotsUsedForBestStart->values();
            $blockSlots = [];
            for ($i = 0; $i < $blockSize; $i++) {
                $blockSlots[] = $slotsArray[$bestStart['slot_index'] + $i];
            }
            
            // Raum-Strategie 1: EINEN Raum für ALLE Slots (bevorzugt – kein Raumwechsel)
            $blockRoom = $this->findRoomForBlock($rooms, $blockSlots);

            // Raum-Strategie 2: Pro Slot einen freien Raum (Fallback – Prüfer wechselt Raum)
            // Wird nur genutzt wenn kein gemeinsamer Raum gefunden wurde
            $perSlotRooms = null;
            if (!$blockRoom) {
                $perSlotRooms = [];
                foreach ($blockSlots as $blockSlot) {
                    $room = $this->findAvailableRoom($rooms, $blockSlot);
                    if (!$room) {
                        $perSlotRooms = null; // Mindestens ein Slot hat keinen freien Raum
                        break;
                    }
                    $perSlotRooms[] = $room;
                }
            }

            if ($blockRoom || $perSlotRooms !== null) {
                $schedule = [];

                foreach ($block['exams'] as $index => $exam) {
                    $slot      = $blockSlots[$index];
                    $slotRoom  = $blockRoom ?? $perSlotRooms[$index];

                    $assignment = [
                        'student_exam_id'   => $exam->id,
                        'time_slot_id'      => $slot->id,
                        'exam_room_id'      => $slotRoom->id,
                        'prep_room_id'      => $slotRoom->id,
                        'exam_week'         => $assignedWeek,
                        'coupling_conflict' => $block['coupling_conflict'] ?? false,
                    ];

                    $schedule[] = $assignment;
                    $this->markSlotOccupied($slot->id, $slotRoom->id, $exam->examiner_id);
                    $this->tagBelegen($exam, $slot->date);

                    $examAssessorId = $exam->assessor_id;
                    if ($examAssessorId) {
                        $this->occupyTeacherSlot($examAssessorId, $slot->id);
                    }
                }

                return $schedule;
            }
        }

        return null;
    }
    
    /**
     * Findet einen Raum, der für ALLE Slots eines Blocks verfügbar ist
     * So muss der Prüfer nicht den Raum wechseln
     */
    private function findRoomForBlock(Collection $rooms, array $slots): ?Room
    {
        foreach ($rooms as $room) {
            $allAvailable = true;
            foreach ($slots as $slot) {
                if ($this->isRoomOccupied($slot->id, $room->id)) {
                    $allAvailable = false;
                    break;
                }
            }
            if ($allAvailable) {
                return $room;
            }
        }
        return null;
    }

    /**
     * Findet die beste Startposition für einen Block
     * Prüft, ob es einen Raum gibt, der für ALLE Slots im Block verfügbar ist
     * WICHTIG: Berücksichtigt auch Beisitzer-Verfügbarkeit (falls vorhanden)
     */
    private function findBestBlockStart(
        Collection $slots, 
        int $blockSize, 
        int $examinerId,
        Collection $rooms,
        ?int $assessorId = null,
        array $perExamAssessorIds = [],
        array $perExamExaminerIds = []
    ): ?array {
        $bestScore = PHP_INT_MAX;
        $bestIndex = null;
        
        $slotsArray = $slots->values();
        $maxIndex = count($slotsArray) - $blockSize;
        
        if ($maxIndex < 0) {
            return null;
        }
        
        for ($i = 0; $i <= $maxIndex; $i++) {
            if (!$this->areConsecutiveOnSameDay($slotsArray, $i, $blockSize)) {
                continue;
            }
            
            // Sammle die Slots für diese Position
            $blockSlots = [];
            for ($j = 0; $j < $blockSize; $j++) {
                $blockSlots[] = $slotsArray[$i + $j];
            }
            
            // Prüfe Prüfer-Verfügbarkeit pro Prüfung (positions-abhängig)
            // Wenn perExamExaminerIds gesetzt, wird jeder Prüfer an seiner Position geprüft
            $teacherConflict = false;
            for ($j = 0; $j < $blockSize; $j++) {
                $slot = $blockSlots[$j];
                $checkExaminerId = $perExamExaminerIds[$j] ?? $examinerId;
                // Hartes Kriterium: Abwesenheit
                if ($this->isTeacherUnavailableOnDate($checkExaminerId, Carbon::parse($slot->date))) {
                    $teacherConflict = true;
                    break;
                }
                if ($this->isTeacherOccupied($checkExaminerId, $slot->id)) {
                    $teacherConflict = true;
                    break;
                }
            }
            if ($teacherConflict) {
                continue;
            }

            // WICHTIG: Prüfe Beisitzer-Verfügbarkeit pro Prüfung (positions-abhängig)
            $assessorConflict = false;
            for ($j = 0; $j < $blockSize; $j++) {
                $slot = $blockSlots[$j];
                $checkAssessorId = $perExamAssessorIds[$j] ?? $assessorId;
                if ($checkAssessorId) {
                    // Hartes Kriterium: Abwesenheit
                    if ($this->isTeacherUnavailableOnDate($checkAssessorId, Carbon::parse($slot->date))) {
                        $assessorConflict = true;
                        break;
                    }
                    if ($this->isTeacherOccupied($checkAssessorId, $slot->id)) {
                        $assessorConflict = true;
                        break;
                    }
                }
            }
            if ($assessorConflict) {
                continue;
            }
            
            // Raumprüfung: erst same-room (bevorzugt), dann per-Slot-Räume als Fallback
            // same-room = ein Raum für alle Slots → sauberer, aber nicht zwingend nötig
            $roomForBlock = $this->findRoomForBlock($rooms, $blockSlots);
            $sameRoom = $roomForBlock !== null;
            if (!$sameRoom) {
                // Fallback: mindestens pro Slot einen freien Raum finden
                foreach ($blockSlots as $blockSlot) {
                    if (!$this->findAvailableRoom($rooms, $blockSlot)) {
                        continue 2; // Kein Raum in diesem Slot → Position ungültig
                    }
                }
            }
            
            // Berechne Score (same-room wird bevorzugt durch niedrigeren Score)
            // Modul 4.3: pro Position den jeweiligen Prüfer/Beisitzer bewerten,
            // damit die Tages-Affinität für alle Beteiligten wirkt
            $score = 0;
            for ($j = 0; $j < $blockSize; $j++) {
                $score += $this->calculateSlotScore(
                    $blockSlots[$j],
                    $perExamExaminerIds[$j] ?? $examinerId,
                    $perExamAssessorIds[$j] ?? $assessorId
                );
            }
            if (!$sameRoom) {
                $score += 500; // Penalty für per-Slot-Räume → same-room wird immer bevorzugt
            }
            
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
            }
        }
        
        return $bestIndex !== null ? ['slot_index' => $bestIndex, 'score' => $bestScore] : null;
    }

    /**
     * Berechnet den Score für einen Zeitslot (niedriger = besser)
     * WICHTIG: Verwendet jetzt preference_weight aus der Datenbank!
     * Höheres preference_weight = niedrigerer Score = bessere Priorität
     *
     * Modul 4.3 (Tages-Affinität): Ist der Prüfer (oder Beisitzer) an diesem Tag
     * bereits eingeplant, wird der Slot massiv bevorzugt. Der Bonus dominiert
     * bewusst die Day-Load-Penalty und die preference_weights – sonst würde die
     * gleichmäßige Tagesverteilung die Prüfungen einer Lehrkraft weiter zerstreuen.
     *
     * Modul 4.3 ("freie Tage"): Slots an stundenplanfreien Wochentagen einer
     * Lehrkraft erhalten eine moderate Penalty (weiche Präferenz, s. u.).
     *
     * Modul 4.5 (Team-Partner): Analog zur Tages-Affinität, aber kursübergreifend
     * für Lehrkräfte-Paare, die irgendwo als Prüfer+Beisitzer zusammen auftreten.
     */
    private function calculateSlotScore(TimeSlot $slot, int $teacherId, ?int $assessorId = null): float
    {
        // Basis-Score: niedrigerer preference_weight → höherer Score → weniger bevorzugt
        $score = 200.0 - ($slot->preference_weight ?? 100);

        // Day-Load-Penalty: Tage mit vielen Prüfungen werden unattraktiver
        // → gleichmäßigere Verteilung über die Wochentage
        $date = $this->slotDateCache[$slot->id] ?? Carbon::parse($slot->date)->format('Y-m-d');
        $score += ($this->dayLoad[$date] ?? 0) * 8;

        if ($this->settings['bundle_teacher_days'] ?? true) {
            if (($this->teacherDayLoad[$teacherId][$date] ?? 0) > 0) {
                $score -= 1000; // Prüfer ist an diesem Tag bereits eingeplant
            }
            if ($assessorId !== null && ($this->teacherDayLoad[$assessorId][$date] ?? 0) > 0) {
                $score -= 300; // Beisitzer ist an diesem Tag bereits eingeplant
            }
        }

        // Modul 4.5 (Team-Partner): Ist an diesem Tag bereits eine Prüfung eines
        // bekannten Team-Partners (gleiches Prüfer/Beisitzer-Paar, auch
        // kursübergreifend) eingeplant, wird der Slot bevorzugt – schwächer als
        // die direkte Tages-Affinität derselben Lehrkraft (−1000/−300), aber
        // deutlich über der Day-Load-Penalty (+8). Rein additiver Bonus, kein
        // hartes Kriterium; mehrere Partner an einem Tag summieren sich bewusst
        // nicht mehrfach über denselben Partner (isset-Guard je Partner-Tag).
        if (($this->settings['bundle_teacher_days'] ?? true) && !empty($this->teamPartners[$teacherId])) {
            foreach (array_keys($this->teamPartners[$teacherId]) as $partnerId) {
                if (($this->teacherDayLoad[$partnerId][$date] ?? 0) > 0) {
                    $score -= 150; // Team-Partner ist an diesem Tag bereits eingeplant
                    break;
                }
            }
        }
        if ($assessorId !== null
            && ($this->settings['bundle_teacher_days'] ?? true)
            && !empty($this->teamPartners[$assessorId])) {
            foreach (array_keys($this->teamPartners[$assessorId]) as $partnerId) {
                if (($this->teacherDayLoad[$partnerId][$date] ?? 0) > 0) {
                    $score -= 150; // Team-Partner des Beisitzers ist an diesem Tag bereits eingeplant
                    break;
                }
            }
        }

        // Modul 4.3 (Teilpunkt "freie Tage"): Kolloquien möglichst nicht an
        // stundenplanfreien Wochentagen einer Lehrkraft. Weiche Präferenz:
        // deutlich über der Day-Load-Penalty (+8), aber klar unter der
        // Tages-Affinität (−1000/−300) — Bündelung behält Vorrang. Ist die
        // Lehrkraft an dem Tag ohnehin schon eingeplant, entfällt die Penalty
        // (sie kommt dann sowieso; weitere Prüfungen dort sind erwünscht).
        // Lehrkräfte ohne Stundenplandaten stehen nicht in teacherFreeWeekdays.
        if ($this->settings['respect_free_days'] ?? true) {
            $weekday = $this->dateWeekdayCache[$date] ??= (int) date('N', (int) strtotime($date));

            if (isset($this->teacherFreeWeekdays[$teacherId][$weekday])
                && ($this->teacherDayLoad[$teacherId][$date] ?? 0) === 0) {
                $score += 120; // Prüfer hätte an diesem Tag stundenplanfrei
            }
            if ($assessorId !== null
                && isset($this->teacherFreeWeekdays[$assessorId][$weekday])
                && ($this->teacherDayLoad[$assessorId][$date] ?? 0) === 0) {
                $score += 120; // Beisitzer hätte an diesem Tag stundenplanfrei
            }
        }

        return $score;
    }

    /**
     * Prüft ob Slots aufeinanderfolgend auf gleichem Tag sind
     */
    private function areConsecutiveOnSameDay(Collection $slots, int $startIndex, int $count): bool
    {
        if ($startIndex + $count > count($slots)) {
            return false;
        }
        
        $firstDate = Carbon::parse($slots[$startIndex]->date)->format('Y-m-d');
        $expectedSlotNumber = $slots[$startIndex]->slot_number;
        
        for ($i = 0; $i < $count; $i++) {
            $slot = $slots[$startIndex + $i];
            
            if (Carbon::parse($slot->date)->format('Y-m-d') !== $firstDate) {
                return false;
            }
            
            if ($slot->slot_number !== $expectedSlotNumber + $i) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Findet einen verfügbaren Raum für einen Slot
     */
    private function findAvailableRoom(Collection $rooms, TimeSlot $slot): ?Room
    {
        foreach ($rooms as $room) {
            if (!$this->isRoomOccupied($slot->id, $room->id)) {
                return $room;
            }
        }
        return null;
    }

    /**
     * Markiert einen Slot als belegt
     */
    private function markSlotOccupied(int $slotId, int $roomId, int $teacherId): void
    {
        if (!isset($this->occupiedSlots[$slotId])) {
            $this->occupiedSlots[$slotId] = [];
        }
        $this->occupiedSlots[$slotId][] = $roomId;

        $this->occupyTeacherSlot($teacherId, $slotId);

        // Day-Load-Penalty: Anzahl Prüfungen pro Tag hochzählen
        $date = $this->slotDateCache[$slotId] ?? null;
        if ($date !== null) {
            $this->dayLoad[$date] = ($this->dayLoad[$date] ?? 0) + 1;
        }
    }

    /**
     * Trägt einen Lehrer (Prüfer oder Beisitzer) in einem Slot als belegt ein
     * und zählt seine Tages-Belegung hoch (Modul 4.3).
     * Idempotent: bereits erfasste Slot-Einträge werden nicht doppelt gezählt.
     */
    private function occupyTeacherSlot(int $teacherId, int $slotId): void
    {
        if (!isset($this->teacherSchedule[$teacherId])) {
            $this->teacherSchedule[$teacherId] = [];
        }
        if (in_array($slotId, $this->teacherSchedule[$teacherId])) {
            return;
        }
        $this->teacherSchedule[$teacherId][] = $slotId;

        $date = $this->slotDateCache[$slotId] ?? null;
        if ($date !== null) {
            $this->teacherDayLoad[$teacherId][$date] = ($this->teacherDayLoad[$teacherId][$date] ?? 0) + 1;
        }
    }

    /**
     * Prüft ob ein Raum in einem Slot bereits belegt ist
     */
    private function isRoomOccupied(int $slotId, int $roomId): bool
    {
        if (isset($this->occupiedSlots[$slotId]) && 
            in_array($roomId, $this->occupiedSlots[$slotId])) {
            return true;
        }
        
        return ExamAssignment::where('time_slot_id', $slotId)
            ->where('exam_room_id', $roomId)
            ->exists();
    }

    /**
     * Prüft ob ein Lehrer in einem Slot bereits eingeteilt ist
     */
    private function isTeacherOccupied(int $teacherId, int $slotId): bool
    {
        if (isset($this->teacherSchedule[$teacherId]) && 
            in_array($slotId, $this->teacherSchedule[$teacherId])) {
            return true;
        }
        
        $asExaminer = ExamAssignment::where('time_slot_id', $slotId)
            ->whereHas('studentExam', fn($q) => $q->where('examiner_id', $teacherId))
            ->exists();
            
        $asAssessor = ExamAssignment::where('time_slot_id', $slotId)
            ->whereHas('studentExam', fn($q) => $q->where('assessor_id', $teacherId))
            ->exists();
            
        return $asExaminer || $asAssessor;
    }

    /**
     * Plant verbleibende Einzelprüfungen (die nicht in Blöcken geplant wurden)
     * Priorisiert Schüler ohne jegliche Prüfung
     * WICHTIG: Nur KOLLOQUIEN (exam_type = 'oral') werden geplant!
     */
    private function scheduleRemainingExams(
        ExamPeriod $examPeriod, 
        Collection $timeSlots, 
        Collection $rooms
    ): array {
        $schedule = [];
        
        // WICHTIG: Prüfungen, die bereits in Blöcken geplant wurden, ausschließen
        $alreadyScheduledExamIds = array_keys($this->verplanteExamIds);
        
        $unscheduledExams = StudentExam::imPruefungszeitraum($examPeriod)
        ->whereDoesntHave('assignment')
        ->where('exam_type', 'oral') // NUR KOLLOQUIEN
        ->whereNull('coupling_id') // Gekoppelte Prüfungen NIE einzeln planen (würde Kopplung zerreißen)
        ->whereNotIn('id', $alreadyScheduledExamIds) // Bereits in Blöcken geplante ausschließen
        ->with(['student', 'course.subject', 'examiner', 'externalExamSubject.subject', 'externalExamSubject.externalApplicant'])
        ->get()
        ->sortBy(function($exam) {
            // Priorisiere Schüler die noch GAR KEINE Prüfung haben
            $studentId = $exam->prueflingsSchluessel();
            $hasWeek1 = isset($this->studentWeekAssignment[$studentId][1]);
            $hasWeek2 = isset($this->studentWeekAssignment[$studentId][2]);
            
            if (!$hasWeek1 && !$hasWeek2) return 0; // Höchste Priorität
            if (!$hasWeek1 || !$hasWeek2) return 1; // Mittlere Priorität
            return 2; // Beide Wochen belegt - kann nicht geplant werden
        });
        
        foreach ($unscheduledExams as $exam) {
            $result = $this->scheduleSingleExam($exam, $timeSlots, $rooms, $examPeriod);
            
            if ($result) {
                $schedule[] = $result;
            } else {
                $studentName = $exam->getStudentName();
                $courseCode = $exam->getSubjectLabel();
                
                // Prüfe WARUM nicht geplant
                $studentId = $exam->prueflingsSchluessel();
                $hasWeek1 = isset($this->studentWeekAssignment[$studentId][1]);
                $hasWeek2 = isset($this->studentWeekAssignment[$studentId][2]);
                
                if ($hasWeek1 && $hasWeek2) {
                    // Hole die existierenden Prüfungen des Schülers
                    $week1ExamId = $this->studentWeekAssignment[$studentId][1] ?? null;
                    $week2ExamId = $this->studentWeekAssignment[$studentId][2] ?? null;
                    
                    $week1Info = '';
                    $week2Info = '';
                    
                    if ($week1ExamId && isset($schedule[$week1ExamId])) {
                        $slot = $this->findSlotById($timeSlots, $schedule[$week1ExamId]['slot_id']);
                        $week1Info = $slot ? " ({$slot->date->format('d.m.Y')}, {$slot->exam_start_time})" : '';
                    }
                    
                    if ($week2ExamId && isset($schedule[$week2ExamId])) {
                        $slot = $this->findSlotById($timeSlots, $schedule[$week2ExamId]['slot_id']);
                        $week2Info = $slot ? " ({$slot->date->format('d.m.Y')}, {$slot->exam_start_time})" : '';
                    }
                    
                    $this->warnings[] = [
                        'type' => 'student_both_weeks_full',
                        'severity' => 'error',
                        'message' => "Prüfung nicht geplant: {$studentName} ({$courseCode}) - Schüler hat bereits beide Wochen belegt",
                        'details' => [
                            'student' => $studentName,
                            'course' => $courseCode,
                            'week1_slot' => $week1Info,
                            'week2_slot' => $week2Info,
                        ]
                    ];
                } else {
                    $this->warnings[] = [
                        'type' => 'exam_not_scheduled',
                        'severity' => 'error',
                        'message' => "Prüfung nicht geplant: {$studentName} ({$courseCode}) - Keine Kapazität gefunden",
                        'details' => [
                            'student'        => $studentName,
                            'course'         => $courseCode,
                            'student_exam_id' => $exam->id,
                        ]
                    ];
                }
            }
        }
        
        return $schedule;
    }

    /**
     * Plant eine einzelne Prüfung
     * HARTES KRITERIUM: Niemals 2 Prüfungen pro Schüler pro Woche!
     * WICHTIG: Slot-Präferenz wird über preference_weight gesteuert
     */
    private function scheduleSingleExam(
        StudentExam $exam, 
        Collection $timeSlots, 
        Collection $rooms,
        ExamPeriod $examPeriod
    ): ?array {
        $examinerId = $exam->examiner_id;
        $studentId = $exam->prueflingsSchluessel();
        
        // Bestimme welche Woche(n) für diesen Prüfling verfügbar sind.
        // Für einen externen Bewerber sind es immer beide: Ihn beschränkt nicht
        // die Woche, sondern der Tag (§ 61 Abs. 3 S. 9).
        $week1Free = $this->wocheIstFrei($exam, 1);
        $week2Free = $this->wocheIstFrei($exam, 2);
        
        // HARTES KRITERIUM: Wenn beide Wochen belegt, NICHT planen
        if (!$week1Free && !$week2Free) {
            return null;
        }
        
        // Wochen-Reihenfolge: Freie Woche(n), bei LF-Präferenz bevorzugte Woche zuerst
        $weekOrder = [];
        if ($week1Free) $weekOrder[] = 1;
        if ($week2Free) $weekOrder[] = 2;

        // LF-Präferenz: bevorzugte Woche nach vorne schieben
        $lfPreferredWeek = $this->settings['lf_preferred_week'] ?? null;
        if ($lfPreferredWeek && $this->isLeistungsfach($exam) && in_array($lfPreferredWeek, $weekOrder) && count($weekOrder) > 1) {
            $weekOrder = array_merge([$lfPreferredWeek], array_diff($weekOrder, [$lfPreferredWeek]));
        }
        
        foreach ($weekOrder as $targetWeek) {
            // Filtere Slots nach Woche und sortiere nach preference_weight
            $weekSlots = $timeSlots->filter(function($slot) use ($examPeriod, $targetWeek) {
                return $this->getWeekNumber($slot->date, $examPeriod) === $targetWeek;
            })->sortBy(function($slot) use ($examinerId, $exam) {
                return $this->calculateSlotScore($slot, $examinerId, $exam->assessor_id);
            });

            // Soft-Constraint: Schüler mit letztem schriftlichem Prüfungstermin meiden Mo/Di W1.
            // Zwei-Phasen-Ansatz: bevorzugte Slots zuerst, Fallback auf alle Slots.
            $isLateExamStudent = !empty($this->avoidDatesForLateExamStudents)
                && in_array($studentId, $this->studentsWithLateWrittenExam, true);
            $slotsToSearch = $weekSlots;
            $fallbackSlots = null;
            if ($isLateExamStudent) {
                $preferred = $weekSlots->reject(function ($slot) {
                    return in_array(
                        Carbon::parse($slot->date)->format('Y-m-d'),
                        $this->avoidDatesForLateExamStudents,
                        true
                    );
                });
                if ($preferred->isNotEmpty()) {
                    $slotsToSearch = $preferred;
                    $fallbackSlots = $weekSlots; // Fallback wenn preferred leer
                }
            }

            $found = $this->tryScheduleInSlots($exam, $slotsToSearch, $rooms, $targetWeek, $studentId, $examinerId);
            if (!$found && $fallbackSlots !== null) {
                // Constraint konnte nicht eingehalten werden → alle Slots versuchen
                $found = $this->tryScheduleInSlots($exam, $fallbackSlots, $rooms, $targetWeek, $studentId, $examinerId);
                if ($found) {
                    Log::channel('single')->info("Späte-Prüfung-Constraint nicht eingehalten (Fallback)", [
                        'pruefling' => $studentId,
                        'week'      => $targetWeek,
                    ]);
                }
            }
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Hilfsmethode: versucht eine Einzelprüfung in einer gegebenen Slot-Collection zu planen.
     * Gibt das Assignment-Array zurück oder null wenn kein Slot gefunden.
     *
     * @param  Collection<int,TimeSlot> $slots
     * @return array<string,mixed>|null
     */
    private function tryScheduleInSlots(
        StudentExam $exam,
        Collection $slots,
        Collection $rooms,
        int $targetWeek,
        string $studentId,
        int $examinerId
    ): ?array {
        $assessorId = $exam->assessor_id;
        foreach ($slots as $slot) {
            // Schüler hat diesen Tag gesperrt?
            if ($this->isStudentDateBlocked($studentId, Carbon::parse($slot->date))) {
                continue;
            }

            // Externer Bewerber: an diesem Tag schon eine mündliche Prüfung?
            // (§ 61 Abs. 3 S. 9 — für reguläre Schüler immer false)
            if ($this->tagIstFuerPrueflingBelegt($exam, $slot->date)) {
                continue;
            }

            // Prüfer an diesem Tag abwesend? (hartes Kriterium)
            if ($this->isTeacherUnavailableOnDate($examinerId, Carbon::parse($slot->date))) {
                continue;
            }

            // Prüfer bereits in einem anderen Slot belegt?
            if ($this->isTeacherOccupied($examinerId, $slot->id)) {
                continue;
            }

            // Beisitzer verfügbar? (falls bereits zugewiesen)
            if ($assessorId) {
                if ($this->isTeacherUnavailableOnDate($assessorId, Carbon::parse($slot->date))) {
                    continue;
                }
                if ($this->isTeacherOccupied($assessorId, $slot->id)) {
                    continue;
                }
            }

            // Raum verfügbar?
            $room = $this->findAvailableRoom($rooms, $slot);
            if (!$room) {
                continue;
            }

            // Gefunden! Zuweisen
            $assignment = [
                'student_exam_id' => $exam->id,
                'time_slot_id'    => $slot->id,
                'exam_room_id'    => $room->id,
                'prep_room_id'    => $room->id,
                'exam_week'       => $targetWeek,
            ];

            $this->markSlotOccupied($slot->id, $room->id, $examinerId);

            if ($assessorId) {
                $this->occupyTeacherSlot($assessorId, $slot->id);
            }

            $this->wocheBelegen($exam, $targetWeek);
            $this->tagBelegen($exam, $slot->date);

            return $assignment;
        }

        return null;
    }

    /**
     * Lädt alle Zeitslots für einen Prüfungszeitraum
     */
    private function loadTimeSlots(ExamPeriod $examPeriod): Collection
    {
        // Nur Slots auf Tagen laden, für die in oral_exam_day_rooms explizit
        // Räume konfiguriert sind (tagesspezifische Einträge, kein globaler Default).
        // Tage ohne expliziten Eintrag (z.B. Freitage) werden übersprungen.
        $allowedDates = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->where('room_role', 'exam')
            ->where('date', '!=', '1900-01-01')
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        $query = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->orderBy('date')
            ->orderBy('slot_number');

        if (!empty($allowedDates)) {
            $query->whereIn('date', $allowedDates);
        }

        $slots = $query->get();

        // Slot-Datum-Cache für Day-Load-Penalty befüllen
        foreach ($slots as $slot) {
            $this->slotDateCache[$slot->id] = Carbon::parse($slot->date)->format('Y-m-d');
        }

        return $slots;
    }

    /**
     * Lädt alle aktiven Prüfungsräume
     */
    private function loadRooms(ExamPeriod $examPeriod): Collection
    {
        // Nur die im Planning konfigurierten Prüfungsräume verwenden
        // (globaler Default in oral_exam_day_rooms, Sentinel-Datum 1900-01-01)
        $configuredRoomIds = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->where('room_role', 'exam')
            ->where('date', '1900-01-01')
            ->pluck('room_id');

        if ($configuredRoomIds->isNotEmpty()) {
            return Room::whereIn('id', $configuredRoomIds)
                ->orderBy('room_number')
                ->get();
        }

        // Fallback: alle aktiven Prüfungsräume (falls kein globaler Default konfiguriert)
        return Room::where('room_type', 'exam')
            ->where('is_active', true)
            ->orderBy('room_number')
            ->get();
    }

    /**
     * Ermittelt die Wochennummer (1 oder 2) für ein Datum
     */
    private function getWeekNumber($date, ExamPeriod $examPeriod): int
    {
        $date = Carbon::parse($date);
        
        if ($date->between(
            Carbon::parse($examPeriod->week_1_start), 
            Carbon::parse($examPeriod->week_1_end)
        )) {
            return 1;
        }
        
        if ($date->between(
            Carbon::parse($examPeriod->week_2_start), 
            Carbon::parse($examPeriod->week_2_end)
        )) {
            return 2;
        }
        
        return 0;
    }

    /**
     * Findet einen Zeitslot in einer Collection anhand der ID
     */
    private function findSlotById($timeSlots, int $slotId): ?TimeSlot
    {
        return $timeSlots->firstWhere('id', $slotId);
    }

    /**
     * Prüft ob eine Prüfung ein Leistungsfach-Kolloquium ist.
     * Erkennungsmerkmal: Der Buchstabe direkt nach der "2" im Kurscode ist GROSSGESCHRIEBEN.
     * Beispiele: "2G-L" → LF, "2Smw-" → LF, "2d-" → kein LF (Deutsch), "2ph-" → kein LF
     */
    private function isLeistungsfach(StudentExam $exam): bool
    {
        // Die Ableitung steht am Model, für Schüler wie für externe Bewerber.
        //
        // Vorher entschied hier bei Externen das Anforderungsniveau — das war
        // zu weit: Deutsch und Mathematik sind nach § 61 Abs. 1 S. 5 immer auf
        // erhöhtem Niveau, ohne Leistungsfach zu sein. Ein externer Bewerber
        // hatte damit drei Leistungsfächer statt einem, und die Blockbildung
        // gewichtete entsprechend zu viele seiner Prüfungen hoch.
        return $exam->istLeistungsfach();
    }

    /**
     * Prüft ob ein Block Leistungsfach-Prüfungen enthält
     */
    private function blockIsLeistungsfach(array $block): bool
    {
        foreach ($block['exams'] as $exam) {
            if ($this->isLeistungsfach($exam)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Post-Optimierungsrunde: Versucht gekoppelte Prüfungen in denselben Raum zu verschieben.
     *
     * Hintergrund: Wenn ein Kopplungsblock nicht geschlossen geplant werden konnte
     * (Fallback auf Einzelplanung), landen die Exams in verschiedenen Räumen.
     * Diese Methode sucht für jede Kopplung einen gemeinsamen freien Raum und
     * verschiebt alle Einzel-Assignments dorthin – ohne Datenbankschreibzugriff
     * (die Änderungen fließen in das $schedule-Array ein, das anschließend persistiert wird).
     *
     * @param  array<int,array{student_exam_id:int,time_slot_id:int,exam_room_id:int,exam_week:int}> $schedule
     * @param  Collection<int,TimeSlot> $timeSlots
     * @param  Collection<int,Room>     $rooms
     * @return array{schedule:array<int,mixed>,fixed:int}
     */
    private function runCouplingRoomOptimization(array $schedule, Collection $timeSlots, Collection $rooms): array
    {
        if (empty($schedule)) {
            return ['schedule' => $schedule, 'fixed' => 0];
        }

        // student_exam_id → Position in $schedule
        $indexMap = [];
        foreach ($schedule as $i => $a) {
            $indexMap[$a['student_exam_id']] = $i;
        }

        // Alle coupling_ids der geplanten Prüfungen ermitteln
        $examIds = array_column($schedule, 'student_exam_id');
        $coupledExams = StudentExam::whereIn('id', $examIds)
            ->whereNotNull('coupling_id')
            ->get()
            ->groupBy('coupling_id');

        $fixed = 0;

        foreach ($coupledExams as $couplingId => $exams) {
            // Schedule-Indizes für diese Kopplung sammeln
            $indices = [];
            foreach ($exams as $exam) {
                if (isset($indexMap[$exam->id])) {
                    $indices[] = $indexMap[$exam->id];
                }
            }

            if (count($indices) < 2) {
                continue;
            }

            // Bereits im gleichen Raum? → nichts zu tun
            $usedRooms = array_unique(array_map(fn($i) => $schedule[$i]['exam_room_id'], $indices));
            if (count($usedRooms) === 1) {
                continue;
            }

            // Slot-IDs der Kopplung
            $slotIds = array_map(fn($i) => $schedule[$i]['time_slot_id'], $indices);

            // Gemeinsamen freien Raum suchen
            foreach ($rooms as $room) {
                $canUse = true;

                foreach ($slotIds as $slotId) {
                    // Prüfe DB-Belegung (fixierte Prüfungen)
                    if (ExamAssignment::where('time_slot_id', $slotId)
                            ->where('exam_room_id', $room->id)
                            ->where('is_locked', true)
                            ->exists()) {
                        $canUse = false;
                        break;
                    }
                    // Prüfe Kollision mit anderen Assignments im aktuellen Schedule
                    foreach ($schedule as $idx => $a) {
                        if (!in_array($idx, $indices, true)
                            && $a['time_slot_id'] === $slotId
                            && $a['exam_room_id'] === $room->id) {
                            $canUse = false;
                            break 2;
                        }
                    }
                }

                if ($canUse) {
                    foreach ($indices as $i) {
                        $schedule[$i]['exam_room_id'] = $room->id;
                    }
                    $fixed++;
                    Log::channel('single')->info("Kopplungsraum-Optimierung: coupling {$couplingId} → Raum {$room->room_number}");
                    break;
                }
            }
        }

        return ['schedule' => $schedule, 'fixed' => $fixed];
    }

    /** Modul 4.3: Tage mit höchstens so vielen Einsätzen gelten als "schwach" und werden aufgelöst */
    private const COMPACTION_MAX_WEAK_DAY_LOAD = 2;

    /**
     * Modul 4.3 – Phase 9.5: Lehrertage kompaktieren.
     *
     * Lehrer haben sich beschwert, dass ihre Prüfungen über viele Tage verstreut
     * waren. Dieser Post-Pass sucht "schwache" Tage einer Lehrkraft (1–2 Einsätze
     * als Prüfer oder Beisitzer) und versucht, ALLE Einsätze dieses Tages auf
     * andere aktive Tage der Lehrkraft in derselben Woche zu verschieben.
     *
     * Regeln:
     * - Fixierte Prüfungen und Kopplungen werden nie angefasst
     * - Alle harten Constraints (Schüler-Sperrtage, Abwesenheiten, Doppelbelegung,
     *   Raum) werden respektiert; Verschiebung nur innerhalb derselben Woche
     *   (Schüler-Wochen-Constraint bleibt automatisch erfüllt)
     * - Ein Tag wird nur komplett aufgelöst (alles oder nichts pro Tag)
     * - Übernommen wird nur, wenn die Gesamtzahl der Prüfungstage aller
     *   Beteiligten (Prüfer + Beisitzer) strikt sinkt – sonst Rollback
     * - Zielslots möglichst nah an bestehenden Einsätzen der Lehrkraft ("am Stück")
     *
     * @param  array<int,array<string,mixed>> $schedule
     * @param  Collection<int,TimeSlot>       $timeSlots
     * @param  Collection<int,Room>           $rooms
     * @return array{schedule: array<int,array<string,mixed>>, moved: int}
     */
    private function runTeacherDayCompaction(
        array $schedule,
        Collection $timeSlots,
        Collection $rooms,
        ExamPeriod $examPeriod
    ): array {
        if (empty($schedule)) {
            return ['schedule' => $schedule, 'moved' => 0];
        }

        $moved = 0;
        $slotsById = $timeSlots->keyBy('id');

        // Prüfungsdaten aller Schedule-Einträge (Prüfer, Beisitzer, Schüler, Kopplung)
        $examIds = array_column($schedule, 'student_exam_id');
        $exams = StudentExam::whereIn('id', $examIds)->get()->keyBy('id');

        // Verschiebbare Einsätze je Lehrkraft und Tag: teacher_id => date => [schedule-Index, ...]
        $teacherDayIndex = [];
        foreach ($schedule as $i => $a) {
            $exam = $exams->get($a['student_exam_id']);
            $date = $this->slotDateCache[$a['time_slot_id']] ?? null;
            if (!$exam || $date === null) {
                continue;
            }
            $teacherDayIndex[$exam->examiner_id][$date][] = $i;
            if ($exam->assessor_id) {
                $teacherDayIndex[$exam->assessor_id][$date][] = $i;
            }
        }

        foreach ($teacherDayIndex as $teacherId => $days) {
            // Gesamtbild inkl. fixierter und bestehender Prüfungen (teacherDayLoad ist die Wahrheit)
            $activeDays = array_filter($this->teacherDayLoad[$teacherId] ?? [], fn($n) => $n > 0);
            if (count($activeDays) < 2) {
                continue; // Nur ein Prüfungstag → nichts zu bündeln
            }

            foreach ($days as $weakDate => $indices) {
                $loadOnDate = $activeDays[$weakDate] ?? 0;
                if ($loadOnDate === 0 || $loadOnDate > self::COMPACTION_MAX_WEAK_DAY_LOAD) {
                    continue;
                }

                // Tag nur auflösen, wenn ALLE Einsätze der Lehrkraft an diesem Tag
                // verschiebbar sind (fixierte/bestehende Einsätze am Tag → unauflösbar)
                $movableIndices = array_values(array_unique(array_filter(
                    $indices,
                    function ($i) use ($schedule, $exams, $weakDate) {
                        $entry = $schedule[$i] ?? null;
                        if ($entry === null) {
                            return false;
                        }
                        // Eintrag könnte in einer früheren Iteration bereits verschoben worden sein
                        if (($this->slotDateCache[$entry['time_slot_id']] ?? null) !== $weakDate) {
                            return false;
                        }
                        $exam = $exams->get($entry['student_exam_id']);
                        return $exam !== null && $exam->coupling_id === null;
                    }
                )));

                if (count($movableIndices) < $loadOnDate) {
                    continue;
                }

                // Tageszahl aller potenziell Beteiligten VOR den Verschiebungen
                $involvedTeachers = [$teacherId => true];
                foreach ($movableIndices as $i) {
                    $exam = $exams->get($schedule[$i]['student_exam_id']);
                    $involvedTeachers[$exam->examiner_id] = true;
                    if ($exam->assessor_id) {
                        $involvedTeachers[$exam->assessor_id] = true;
                    }
                }
                $daysBefore = 0;
                foreach (array_keys($involvedTeachers) as $tid) {
                    $daysBefore += count(array_filter($this->teacherDayLoad[$tid] ?? [], fn($n) => $n > 0));
                }

                // Zieltage: andere aktive Tage der Lehrkraft in derselben Woche
                $weakWeek = $this->getWeekNumber($weakDate, $examPeriod);
                $targetDates = [];
                foreach ($activeDays as $d => $n) {
                    if ($d !== $weakDate && $this->getWeekNumber($d, $examPeriod) === $weakWeek) {
                        $targetDates[] = $d;
                    }
                }
                if (empty($targetDates)) {
                    continue;
                }

                // Alle Einsätze des schwachen Tages verschieben (alles oder nichts)
                $executedMoves = [];
                $allMoved = true;

                foreach ($movableIndices as $i) {
                    $exam = $exams->get($schedule[$i]['student_exam_id']);
                    if ($exam === null) {
                        $allMoved = false;
                        break;
                    }
                    $move = $this->tryMoveEntryToDates($schedule[$i], $exam, $targetDates, $slotsById, $rooms, $teacherId);

                    if ($move === null) {
                        $allMoved = false;
                        break;
                    }

                    $executedMoves[] = ['index' => $i, 'old' => $schedule[$i]];
                    $schedule[$i]['time_slot_id'] = $move['slot_id'];
                    $schedule[$i]['exam_room_id'] = $move['room_id'];
                    if (array_key_exists('prep_room_id', $schedule[$i])) {
                        $schedule[$i]['prep_room_id'] = $move['room_id'];
                    }
                }

                // Tageszahl NACH den Verschiebungen: nur übernehmen, wenn strikt besser
                $daysAfter = 0;
                foreach (array_keys($involvedTeachers) as $tid) {
                    $daysAfter += count(array_filter($this->teacherDayLoad[$tid] ?? [], fn($n) => $n > 0));
                }

                if (!$allMoved || $daysAfter >= $daysBefore) {
                    // Rollback in umgekehrter Reihenfolge
                    foreach (array_reverse($executedMoves) as $m) {
                        $i = $m['index'];
                        $exam = $exams->get($schedule[$i]['student_exam_id']);
                        $this->freeSlot($schedule[$i]['time_slot_id'], $schedule[$i]['exam_room_id'], $exam->examiner_id);
                        if ($exam->assessor_id) {
                            $this->freeTeacherSlot($exam->assessor_id, $schedule[$i]['time_slot_id']);
                        }
                        $this->markSlotOccupied($m['old']['time_slot_id'], $m['old']['exam_room_id'], $exam->examiner_id);
                        if ($exam->assessor_id) {
                            $this->occupyTeacherSlot($exam->assessor_id, $m['old']['time_slot_id']);
                        }
                        $schedule[$i] = $m['old'];
                    }
                    continue;
                }

                $moved += count($executedMoves);
                $activeDays = array_filter($this->teacherDayLoad[$teacherId] ?? [], fn($n) => $n > 0);
                Log::channel('single')->info("Lehrertag-Kompaktierung: Lehrer {$teacherId}, Tag {$weakDate} aufgelöst (" . count($executedMoves) . " Prüfungen)");
            }
        }

        return ['schedule' => $schedule, 'moved' => $moved];
    }

    /**
     * Modul 4.3: Versucht, einen Schedule-Eintrag auf einen der Zieltage zu
     * verschieben. Gibt bei Erfolg den neuen Slot/Raum zurück (Buchhaltung
     * bereits aktualisiert), sonst null (Buchhaltung unverändert).
     *
     * Zielslots werden nach Nähe zu bestehenden Einsätzen der Lehrkraft am
     * Zieltag sortiert, damit Prüfungen möglichst "am Stück" liegen.
     *
     * @param  array<string,mixed>          $entry
     * @param  array<int,string>            $targetDates
     * @param  Collection<int|string,TimeSlot> $slotsById
     * @param  Collection<int,Room>         $rooms
     * @return array{slot_id:int, room_id:int}|null
     */
    private function tryMoveEntryToDates(
        array $entry,
        StudentExam $exam,
        array $targetDates,
        Collection $slotsById,
        Collection $rooms,
        int $focusTeacherId
    ): ?array {
        $examinerId = $exam->examiner_id;
        $assessorId = $exam->assessor_id;
        $studentId  = $exam->prueflingsSchluessel();
        $oldSlotId  = $entry['time_slot_id'];
        $oldRoomId  = $entry['exam_room_id'];

        // Alten Slot freigeben, damit die Verfügbarkeits-Checks korrekt prüfen
        $this->freeSlot($oldSlotId, $oldRoomId, $examinerId);
        if ($assessorId) {
            $this->freeTeacherSlot($assessorId, $oldSlotId);
        }
        // Ebenso den alten Prüfungstag: Sonst spräche die Tagesregel gegen den
        // Bewerber selbst, sobald ein Zieltag geprüft wird, der zufällig sein
        // eigener bisheriger ist — und der alte Tag bliebe nach einem Umzug für
        // immer belegt.
        $alterTag = $this->slotDateCache[$oldSlotId] ?? null;
        if ($alterTag !== null) {
            $this->tagFreigeben($exam, $alterTag);
        }

        foreach ($targetDates as $targetDate) {
            $targetCarbon = Carbon::parse($targetDate);

            // Harte Constraints auf Tagesebene
            if ($this->isStudentDateBlocked($studentId, $targetCarbon)) {
                continue;
            }
            // § 61 Abs. 3 S. 9: Der Zieltag darf für einen externen Bewerber
            // nicht schon eine andere seiner mündlichen Prüfungen tragen. Die
            // Tagesbündelung optimiert auf Lehrer-Tage — ohne diese Prüfung
            // hätte sie genau die Häufung erzeugt, die die Norm ausschließt.
            if ($this->tagIstFuerPrueflingBelegt($exam, $targetCarbon)) {
                continue;
            }
            if ($this->isTeacherUnavailableOnDate($examinerId, $targetCarbon)) {
                continue;
            }
            if ($assessorId && $this->isTeacherUnavailableOnDate($assessorId, $targetCarbon)) {
                continue;
            }

            // Slots des Zieltages, sortiert nach Nähe zu bestehenden Einsätzen
            // der Lehrkraft an diesem Tag (→ "am Stück")
            $occupiedNumbers = [];
            foreach ($this->teacherSchedule[$focusTeacherId] ?? [] as $sid) {
                if (($this->slotDateCache[$sid] ?? null) === $targetDate) {
                    $s = $slotsById->get($sid);
                    if ($s) {
                        $occupiedNumbers[] = $s->slot_number;
                    }
                }
            }

            $daySlots = $slotsById
                ->filter(fn($s) => ($this->slotDateCache[$s->id] ?? null) === $targetDate)
                ->sortBy(function ($s) use ($occupiedNumbers) {
                    if (empty($occupiedNumbers)) {
                        return $s->slot_number;
                    }
                    return min(array_map(fn($n) => abs($s->slot_number - $n), $occupiedNumbers));
                });

            foreach ($daySlots as $slot) {
                if ($this->isTeacherOccupied($examinerId, $slot->id)) {
                    continue;
                }
                if ($assessorId && $this->isTeacherOccupied($assessorId, $slot->id)) {
                    continue;
                }
                $room = $this->findAvailableRoom($rooms, $slot);
                if (!$room) {
                    continue;
                }

                // Gefunden → neue Belegung eintragen
                $this->markSlotOccupied($slot->id, $room->id, $examinerId);
                if ($assessorId) {
                    $this->occupyTeacherSlot($assessorId, $slot->id);
                }
                $this->tagBelegen($exam, $targetDate);

                return ['slot_id' => $slot->id, 'room_id' => $room->id];
            }
        }

        // Kein Ziel gefunden → alte Belegung wiederherstellen
        $this->markSlotOccupied($oldSlotId, $oldRoomId, $examinerId);
        if ($assessorId) {
            $this->occupyTeacherSlot($assessorId, $oldSlotId);
        }
        if ($alterTag !== null) {
            $this->tagBelegen($exam, $alterTag);
        }

        return null;
    }

    /**
     * Rescue-Pass: Versucht ungeplante Prüfungen zu retten.
     *
     * Szenario: Prüfer X ist in Woche 1 abwesend. Schüler S hat Prüfung A
     * (Prüfer X) nicht geplant bekommen, weil Woche 2 bereits durch Prüfung B
     * belegt ist. Lösung: Prüfung B in Woche 1 verschieben → Woche 2 wird frei
     * für Prüfung A (Prüfer X).
     *
     * @param  array<int,mixed>           $schedule
     * @param  Collection<int,TimeSlot>   $timeSlots
     * @param  Collection<int,Room>       $rooms
     * @return array{newAssignments:array<int,mixed>,fixed:int}
     */
    private function runRescuePass(
        ExamPeriod $examPeriod,
        Collection $timeSlots,
        Collection $rooms,
        array &$schedule
    ): array {
        $newAssignments = [];
        $fixed = 0;

        // Alle ungeplanten mündlichen Prüfungen des Zeitraums
        $scheduledExamIds = array_column($schedule, 'student_exam_id');
        $unplanned = StudentExam::imPruefungszeitraum($examPeriod)
        ->whereDoesntHave('assignment')
        ->where('exam_type', 'oral')
        ->whereNotIn('id', $scheduledExamIds)
        ->with(['student', 'course', 'examiner', 'assessor', 'externalExamSubject.subject', 'externalExamSubject.externalApplicant'])
        ->get();

        if ($unplanned->isEmpty()) {
            return ['newAssignments' => [], 'fixed' => 0];
        }

        foreach ($unplanned as $exam) {
            // Gekoppelte Prüfungen nicht einzeln retten – würde die Kopplung zerreißen
            if ($exam->coupling_id !== null) continue;

            // Externe Bewerber überspringen: Dieser Durchgang löst genau einen
            // Fall – „die andere Prüfung des Schülers blockiert die freie
            // Woche" – und der setzt zwei Prüfungen auf zwei Wochen voraus. Ein
            // externer Bewerber hat vier Prüfungen und keine Wochenregel; sein
            // Engpass ist der Tag, und den löst kein Wochentausch.
            if (!$this->wochenRegelGilt($exam)) continue;

            $studentId  = $exam->prueflingsSchluessel();
            $examinerId = $exam->examiner_id;
            $assessorId = $exam->assessor_id;

            // Welche Wochen sind für den Prüfer verfügbar?
            $availableWeeks = [];
            foreach ([1, 2] as $w) {
                $weekSlots = $timeSlots->filter(fn($s) =>
                    $this->getWeekNumber($s->date, $examPeriod) === $w
                );
                // Mindestens ein Slot ohne Abwesenheit des Prüfers?
                $hasSlot = $weekSlots->contains(function($s) use ($examinerId, $assessorId) {
                    if ($this->isTeacherUnavailableOnDate($examinerId, Carbon::parse($s->date))) return false;
                    if ($assessorId && $this->isTeacherUnavailableOnDate($assessorId, Carbon::parse($s->date))) return false;
                    return true;
                });
                if ($hasSlot) $availableWeeks[] = $w;
            }

            if (empty($availableWeeks)) continue; // Prüfer hat gar keine freie Woche

            // Gegenprüfung des Schülers suchen:
            // 1. Im aktuellen In-Memory-Schedule
            // 2. Fallback: In der DB (Vorläufer-Assignment bei clear_existing=false)
            $otherExamInSchedule = null;

            foreach ($schedule as $idx => $a) {
                if ($a['student_exam_id'] === $exam->id) continue;
                $otherExam = StudentExam::find($a['student_exam_id']);
                if (!$otherExam || $otherExam->prueflingsSchluessel() !== $studentId) continue;
                // Gekoppelte Gegenprüfungen NICHT verschieben – das würde die Kopplung zerreißen
                if ($otherExam->coupling_id !== null) continue;
                $locked = ExamAssignment::where('student_exam_id', $a['student_exam_id'])
                    ->where('is_locked', true)->exists();
                if ($locked) continue;
                $otherExamInSchedule = [
                    'source'        => 'memory',
                    'assignment'    => $a,
                    'exam'          => $otherExam,
                    'idx'           => $idx,
                    'db_assignment' => null,
                ];
                break;
            }

            if (!$otherExamInSchedule) {
                $dbAssignment = ExamAssignment::whereHas('studentExam', fn($q) =>
                    $q->desselbenPrueflings($exam)
                      ->where('id', '!=', $exam->id)
                      ->where('exam_type', 'oral')
                      ->whereNull('coupling_id') // Gekoppelte Prüfungen nicht verschieben
                )
                ->where('is_locked', false)
                ->with(['studentExam', 'timeSlot'])
                ->first();

                if ($dbAssignment) {
                    $week = $this->getWeekNumber($dbAssignment->timeSlot->date, $examPeriod);
                    $otherExamInSchedule = [
                        'source'        => 'db',
                        'assignment'    => [
                            'student_exam_id' => $dbAssignment->student_exam_id,
                            'time_slot_id'    => $dbAssignment->time_slot_id,
                            'exam_room_id'    => $dbAssignment->exam_room_id,
                            'exam_week'       => $week,
                        ],
                        'exam'          => $dbAssignment->studentExam,
                        'idx'           => null,
                        'db_assignment' => $dbAssignment,
                    ];
                }
            }

            if (!$otherExamInSchedule) {
                Log::channel('single')->warning("Rescue: {$exam->getStudentName()} - keine Gegenprüfung gefunden");
                continue;
            }

            $currentWeek   = $otherExamInSchedule['assignment']['exam_week'];
            $swapToWeek    = $currentWeek === 1 ? 2 : 1;
            $freedWeek     = $currentWeek;
            $needWeek      = in_array($freedWeek, $availableWeeks) ? $freedWeek : null;

            Log::channel('single')->info("Rescue: {$exam->getStudentName()} ({$exam->getSubjectLabel()})", [
                'availableWeeks' => $availableWeeks, 'freedWeek' => $freedWeek,
                'needWeek' => $needWeek, 'source' => $otherExamInSchedule['source'],
                'otherCourse' => $otherExamInSchedule['exam']->course->course_code ?? '?',
            ]);
            $freedWeek     = $currentWeek;
            $needWeek      = in_array($freedWeek, $availableWeeks) ? $freedWeek : null;

            if ($needWeek === null) continue;

            $otherExam       = $otherExamInSchedule['exam'];
            $otherExaminerId = $otherExam->examiner_id;
            $otherAssessorId = $otherExam->assessor_id;
            $oldSlotId       = $otherExamInSchedule['assignment']['time_slot_id'];
            $oldRoomId       = $otherExamInSchedule['assignment']['exam_room_id'];

            // Alten Slot der Gegenprüfung ZUERST freigeben, damit isTeacherOccupied korrekt prüft
            $this->freeSlot($oldSlotId, $oldRoomId, $otherExaminerId);
            if ($otherAssessorId) $this->freeTeacherSlot($otherAssessorId, $oldSlotId);
            unset($this->studentWeekAssignment[$studentId][$currentWeek]);

            // Slot in $swapToWeek für Gegenprüfung suchen
            $swapWeekSlots = $timeSlots->filter(fn($s) =>
                $this->getWeekNumber($s->date, $examPeriod) === $swapToWeek
            )->sortBy(fn($s) => $this->calculateSlotScore($s, $otherExaminerId, $otherAssessorId));

            $swapSlot = null;
            $swapRoom = null;
            foreach ($swapWeekSlots as $s) {
                if ($this->isStudentDateBlocked($studentId, Carbon::parse($s->date))) continue;
                if ($this->isTeacherUnavailableOnDate($otherExaminerId, Carbon::parse($s->date))) continue;
                if ($otherAssessorId && $this->isTeacherUnavailableOnDate($otherAssessorId, Carbon::parse($s->date))) continue;
                if ($this->isTeacherOccupied($otherExaminerId, $s->id)) continue;
                if ($otherAssessorId && $this->isTeacherOccupied($otherAssessorId, $s->id)) continue;
                $room = $this->findAvailableRoom($rooms, $s);
                if (!$room) continue;
                $swapSlot = $s; $swapRoom = $room;
                break;
            }

            if (!$swapSlot) {
                // Rollback Freigabe
                $this->markSlotOccupied($oldSlotId, $oldRoomId, $otherExaminerId);
                if ($otherAssessorId) $this->occupyTeacherSlot($otherAssessorId, $oldSlotId);
                $this->studentWeekAssignment[$studentId][$currentWeek] = $otherExam->id;
                Log::channel('single')->warning("Rescue ✗: kein Swap-Slot in Woche {$swapToWeek} für {$otherExam->examiner->shortcode}");
                continue;
            }

            // Gegenprüfung in neuen Slot eintragen
            if ($otherExamInSchedule['source'] === 'memory' && $otherExamInSchedule['idx'] !== null) {
                $schedule[$otherExamInSchedule['idx']]['time_slot_id'] = $swapSlot->id;
                $schedule[$otherExamInSchedule['idx']]['exam_room_id'] = $swapRoom->id;
                $schedule[$otherExamInSchedule['idx']]['exam_week']    = $swapToWeek;
            } else {
                // DB-Assignment: als neuen Schedule-Eintrag (wird beim Speichern über UPDATE verarbeitet)
                $schedule[] = [
                    'student_exam_id' => $otherExam->id,
                    'time_slot_id'    => $swapSlot->id,
                    'exam_room_id'    => $swapRoom->id,
                    'prep_room_id'    => $swapRoom->id,
                    'exam_week'       => $swapToWeek,
                ];
            }

            $this->markSlotOccupied($swapSlot->id, $swapRoom->id, $otherExaminerId);
            if ($otherAssessorId) $this->occupyTeacherSlot($otherAssessorId, $swapSlot->id);
            $this->studentWeekAssignment[$studentId][$swapToWeek] = $otherExam->id;

            // Ungeplante Prüfung in $needWeek planen
            $result = $this->scheduleSingleExam($exam, $timeSlots, $rooms, $examPeriod);
            if ($result) {
                $newAssignments[] = $result;
                $fixed++;
                Log::channel('single')->info("Rescue ✓: {$exam->getStudentName()} ({$exam->getSubjectLabel()}) W{$currentWeek}→W{$swapToWeek}");
            } else {
                // Vollständiger Rollback
                Log::channel('single')->warning("Rescue ✗: scheduleSingleExam fehlgeschlagen für {$exam->getStudentName()}");
                $this->freeSlot($swapSlot->id, $swapRoom->id, $otherExaminerId);
                if ($otherAssessorId) $this->freeTeacherSlot($otherAssessorId, $swapSlot->id);
                unset($this->studentWeekAssignment[$studentId][$swapToWeek]);
                if ($otherExamInSchedule['source'] === 'memory' && $otherExamInSchedule['idx'] !== null) {
                    $schedule[$otherExamInSchedule['idx']]['time_slot_id'] = $oldSlotId;
                    $schedule[$otherExamInSchedule['idx']]['exam_room_id'] = $oldRoomId;
                    $schedule[$otherExamInSchedule['idx']]['exam_week']    = $currentWeek;
                } else {
                    array_pop($schedule);
                }
                $this->markSlotOccupied($oldSlotId, $oldRoomId, $otherExaminerId);
                if ($otherAssessorId) $this->occupyTeacherSlot($otherAssessorId, $oldSlotId);
                $this->studentWeekAssignment[$studentId][$currentWeek] = $otherExam->id;
            }
        }

        return ['newAssignments' => $newAssignments, 'fixed' => $fixed];
    }

    /**
     * Gibt einen Slot/Raum in den internen Tracking-Strukturen frei.
     */
    private function freeSlot(int $slotId, int $roomId, int $teacherId): void
    {
        if (isset($this->occupiedSlots[$slotId])) {
            $this->occupiedSlots[$slotId] = array_values(
                array_filter($this->occupiedSlots[$slotId], fn($r) => $r !== $roomId)
            );
        }
        $this->freeTeacherSlot($teacherId, $slotId);

        // Day-Load symmetrisch zurücknehmen (Gegenstück zu markSlotOccupied)
        $date = $this->slotDateCache[$slotId] ?? null;
        if ($date !== null && ($this->dayLoad[$date] ?? 0) > 0) {
            $this->dayLoad[$date]--;
        }
    }

    /**
     * Entfernt einen Slot aus dem Lehrer-Schedule-Tracking und nimmt die
     * Tages-Belegung des Lehrers zurück (Modul 4.3, Gegenstück zu occupyTeacherSlot).
     */
    private function freeTeacherSlot(int $teacherId, int $slotId): void
    {
        if (!isset($this->teacherSchedule[$teacherId])
            || !in_array($slotId, $this->teacherSchedule[$teacherId])) {
            return;
        }

        $this->teacherSchedule[$teacherId] = array_values(
            array_filter($this->teacherSchedule[$teacherId], fn($s) => $s !== $slotId)
        );

        $date = $this->slotDateCache[$slotId] ?? null;
        if ($date !== null && ($this->teacherDayLoad[$teacherId][$date] ?? 0) > 0) {
            $this->teacherDayLoad[$teacherId][$date]--;
        }
    }
}


