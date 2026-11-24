<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Services\CurrentExamPeriodService;
use App\Models\ExamAssignment;
use App\Models\ExternalApplicant;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\TimeSlot;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\OralExamDayRoom;
use App\Models\SchoolSetting;
use App\Services\ActivityLogger;
use App\Services\ExamPlanningService;
use App\Services\ValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Barryvdh\DomPDF\Facade\Pdf;

class PlanningController extends Controller
{
    use AuthorizesRequests;

    private function getSchoolId()
    {
        $user = auth()->user();
        if ($user->school_id) {
            return $user->school_id;
        }
        
        // Fallback für Super Admin: Erste Schule nehmen.
        // school_number, nicht id: Die Tabelle `schools` hat keine Spalte
        // `id`. Bis zum 31.08.2026 stand hier `School::first()->id ?? null`
        // — das ergab immer null, und jeder Ausdruck, den ein Super-Admin
        // anstieß, lief anschließend ohne Schulkopf. Fünfte Erscheinungsform
        // des Schulnummer-Fehlers.
        if ($user->isSuperAdmin() || $user->email === 'admin@example.com') {
            return \App\Models\School::first()?->school_number;
        }
        
        return null;
    }
    
    /**
     * Prüft ob der User berechtigt ist zu planen
     * Planning ist unabhängig von ExamPeriod-Berechtigungen
     */
    protected function ensureCanPlan(): void
    {
        if (!auth()->user()->isPlanner()) {
            abort(403, 'Sie haben keine Berechtigung für diese Aktion.');
        }
    }
    
    /**
     * Planungsansicht anzeigen
     */
    public function index()
    {
        $this->ensureCanPlan();
        
        $schoolId = $this->getSchoolId();
        if (!$schoolId) {
            abort(403, 'Keine Schule zugeordnet.');
        }
        
        // Aktuellen Prüfungszeitraum finden (neuester)
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        
        if (!$examPeriod) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden. Bitte erstellen Sie zuerst einen Prüfungszeitraum.');
        }
        
        // Ungeplante Prüfungen laden - gruppiert nach Kurs (Kursname + Kursleiter)
        // Inkludiert sowohl reguläre Students als auch externe Bewerber
        $unplannedExams = StudentExam::imPruefungszeitraum($examPeriod)
            ->where('exam_type', 'oral') // ✅ Nur mündliche Prüfungen
            ->whereDoesntHave('assignment')
            ->with(['student', 'course.subject', 'examiner', 'assessor',
                    'externalExamSubject.externalApplicant', 'externalExamSubject.subject',
                    'illnessAssignment'])
            ->get()
            // Externe Bewerber haben weder Kurs noch Schülerdatensatz (Modul 2.1):
            // course_code und student->lastname sind dort null. Sortierung und
            // Gruppierung weichen deshalb auf Fach- und Prüflingsnamen aus, die
            // StudentExam für beide Fälle liefert.
            ->sortBy([
                // Primär nach Kurs-Code, bei Externen nach Fachname
                fn($a, $b) => strcmp($a->getSubjectLabel(), $b->getSubjectLabel()),
                // Sekundär nach Name des Prüflings
                fn($a, $b) => strcmp($a->getStudentName(), $b->getStudentName()),
            ])
            ->groupBy(function (StudentExam $exam): string {
                // Gruppierung: "Kurs-Code (Kursleiter-Kürzel)". Bei einem
                // externen Bewerber kann der Prüfer noch offen sein, seit
                // examiner_id nullable ist — Fallunterscheidung statt ?->,
                // weil Larastan die Beziehung für nicht-nullable hält.
                $pruefer = $exam->examiner;

                return $exam->getSubjectLabel()
                    . ' (' . ($pruefer instanceof Teacher ? $pruefer->shortcode : 'ohne Prüfer') . ')';
            });
        
        // Zeitslots laden – alle Assignments inkl. abgesagter (für cancelled-Anzeige in Matrix)
        $timeSlots = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->with(['examAssignments.studentExam.student',
                    'examAssignments.studentExam.course.subject',
                    'examAssignments.studentExam.examiner',
                    'examAssignments.studentExam.assessor',
                    'examAssignments.studentExam.externalExamSubject.subject',
                    'examAssignments.studentExam.externalExamSubject.externalApplicant',
                    'examAssignments.examRoom'])
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get()
            ->groupBy(function($slot) {
                return $slot->date->format('Y-m-d');
            });

        // Freitermine außerhalb der Prüfungswochen
        $outsideAssignments = ExamAssignment::whereNull('time_slot_id')
            ->where('exam_week', 0)
            ->where('status', '!=', 'cancelled')
            ->whereHas('studentExam', function($q) use ($examPeriod) {
                $q->where('exam_type', 'oral')->imPruefungszeitraum($examPeriod);
            })
            ->with(['studentExam.student', 'studentExam.course.subject',
                    'studentExam.examiner', 'studentExam.assessor',
                    'studentExam.externalExamSubject.subject',
                    'studentExam.externalExamSubject.externalApplicant', 'examRoom'])
            ->orderBy('free_date')
            ->orderBy('free_time_start')
            ->get();
        
        // Prüfungsräume: pro Tag aus oral_exam_day_rooms laden (role = 'exam')
        // Fallback je Tag: globaler Default (sentinel 1900-01-01), sonst alle aktiven exam-Räume
        [
            'globalExamRooms' => $globalExamRooms,
            'examRoomsByDate' => $examRoomsByDate,
            'prepRoomsByDate' => $prepRoomsByDate,
            'waitRoomsByDate' => $waitRoomsByDate,
        ] = $this->loadOralExamDayRooms($examPeriod);

        // Alle exam-Räume für Dropdown im Assign-Dialog
        $examRooms = Room::where('room_type', 'exam')
            ->where('is_active', true)
            ->get();

        $teachers = Teacher::active()->orderBy('shortcode')
            ->get();
        
        // Konflikte prüfen
        $conflicts = $this->getConflicts($examPeriod);
        
        // Statistiken
        // Nur Q13 (Abiturjahrgang) zählt für die Kolloquium-Planung, nicht Q12/Q11
        $totalStudents = Student::where('exam_period_id', $examPeriod->id)
            ->where('grade', '13')
            ->count();
        
        // Externe Bewerber sind Prüflinge desselben Zeitraums, und die Kachel
        // „Planungsfortschritt" zählt ihre Prüfungen längst mit
        // (imPruefungszeitraum). Ohne sie stand links daneben ein „Soll", das
        // kleiner war als die Zahl rechts — zwei Kacheln nebeneinander mit
        // verschiedenen Grundgesamtheiten.
        $externalApplicants = ExternalApplicant::forExamPeriod($examPeriod->id)
            ->with('oralSubjects')
            ->get();
        $expectedExternalExams = $externalApplicants->sum(
            static fn (ExternalApplicant $bewerber): int => $bewerber->sollMuendlichePruefungen()
        );

        $stats = [
            'students_count' => $totalStudents + $externalApplicants->count(),
            // Jeder Schüler braucht 2 mündliche Prüfungen; wie viele ein
            // externer Bewerber braucht, steht in seiner Fächerwahl.
            'expected_exams' => $totalStudents * 2 + $expectedExternalExams,
            // Durchgehend imPruefungszeitraum(): über `whereHas('student', …)`
            // fehlten externe Prüflinge in jeder dieser Zahlen — der Planer
            // sah eine Gesamtzahl, die kleiner war als die Wirklichkeit.
            'total' => StudentExam::imPruefungszeitraum($examPeriod)
                ->where('exam_type', 'oral')->count(), // ✅ Nur mündliche Prüfungen
            'planned' => ExamAssignment::whereHas(
                'studentExam',
                fn ($q) => $q->imPruefungszeitraum($examPeriod)->where('exam_type', 'oral')
            )->where('status', '!=', 'cancelled')->count(),
            // WICHTIG: assessor_id ist jetzt in StudentExam, nicht in ExamAssignment!
            'missing_assessor' => StudentExam::imPruefungszeitraum($examPeriod)
                ->where('exam_type', 'oral') // ✅ Nur mündliche Prüfungen
                ->whereHas('assignment') // Nur geplante Prüfungen
                ->whereNull('assessor_id')
                ->count(),
            'week_1' => ExamAssignment::whereHas(
                'studentExam',
                fn ($q) => $q->imPruefungszeitraum($examPeriod)->where('exam_type', 'oral')
            )->where('exam_week', 1)->count(),
            'week_2' => ExamAssignment::whereHas(
                'studentExam',
                fn ($q) => $q->imPruefungszeitraum($examPeriod)->where('exam_type', 'oral')
            )->where('exam_week', 2)->count(),
        ];
        $stats['unplanned'] = $stats['total'] - $stats['planned'];
        
        // Schüler mit falscher Prüfungsanzahl finden
        $studentsWithWrongCount = DB::table('students')
            ->leftJoin('student_exams', function($join) {
                $join->on('students.id', '=', 'student_exams.student_id')
                     ->where('student_exams.exam_type', '=', 'oral');
            })
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('students.grade', '13')
            ->groupBy('students.id', 'students.firstname', 'students.lastname')
            ->havingRaw('COUNT(student_exams.id) != 2')
            ->select('students.id', 'students.firstname', 'students.lastname', DB::raw('COUNT(student_exams.id) as exam_count'))
            ->orderBy('students.lastname')
            ->orderBy('students.firstname')
            ->get()
            ->map(static function (object $zeile): object {
                // Für einen regulären Schüler sind es immer genau zwei
                // Kolloquien. Die Zahl steht jetzt an der Zeile, weil die
                // externen Zeilen darunter eine andere tragen.
                $zeile->soll   = 2;
                $zeile->extern = false;

                return $zeile;
            });

        // Dieselbe Prüfung für externe Bewerber. Sie fand bisher nicht statt:
        // die Abfrage oben geht über `students`, und dort steht kein externer
        // Bewerber. Ein Bewerber mit drei statt vier mündlichen Prüfungen fiel
        // deshalb nicht auf — die Kachel meldete „✓ Korrekt".
        $externalExamCounts = DB::table('student_exams')
            ->join(
                'external_exam_subjects',
                'student_exams.external_exam_subject_id',
                '=',
                'external_exam_subjects.id'
            )
            ->where('student_exams.exam_type', 'oral')
            ->groupBy('external_exam_subjects.external_applicant_id')
            ->select(
                'external_exam_subjects.external_applicant_id as bewerber_id',
                DB::raw('COUNT(*) as anzahl')
            )
            ->get()
            ->keyBy('bewerber_id');

        $externalWithWrongCount = $externalApplicants
            ->map(static function (ExternalApplicant $bewerber) use ($externalExamCounts): object {
                return (object) [
                    'id'         => $bewerber->id,
                    'firstname'  => $bewerber->first_name,
                    'lastname'   => $bewerber->last_name,
                    'exam_count' => (int) ($externalExamCounts[$bewerber->id]->anzahl ?? 0),
                    'soll'       => $bewerber->sollMuendlichePruefungen(),
                    'extern'     => true,
                ];
            })
            ->filter(static fn (object $zeile): bool => $zeile->exam_count !== $zeile->soll)
            ->sortBy('lastname')
            ->values();

        $studentsWithWrongCount = $studentsWithWrongCount->concat($externalWithWrongCount);

        $stats['students_with_wrong_count'] = $studentsWithWrongCount->count();
        $stats['exam_count_valid'] = $stats['students_with_wrong_count'] === 0;
        
        // Gespeicherte Autoplanungs-Parameter laden
        $autoScheduleSettings = [
            'clear_existing'      => SchoolSetting::get('planning.clear_existing', '0') === '1',
            'max_consecutive'     => (int) SchoolSetting::get('planning.max_consecutive', '5'),
            'lf_preferred_week'   => SchoolSetting::get('planning.lf_preferred_week', ''),
            'bundle_teacher_days' => SchoolSetting::get('planning.bundle_teacher_days', '1') === '1',
            'respect_free_days'   => SchoolSetting::get('planning.respect_free_days', '1') === '1',
        ];

        // Exportierbare Tage für die Tagesauswahl-Komponente (Modul 4.2)
        $exportDays = $this->availableColloquiumDays($examPeriod);

        // Vorgezogene mündliche Prüfungen (§ 61 Abs. 4). Der Zeitraum wird
        // aus den zentralen schriftlichen Jahresterminen abgeleitet, nicht
        // gepflegt — siehe VorgezogeneKolloquienService::fenster().
        $vorgezogenService     = app(\App\Services\VorgezogeneKolloquienService::class);
        $vorgezogeneTermine    = $vorgezogenService->zuweisungen($examPeriod);
        $vorgezogenFenster     = $vorgezogenService->fenster($examPeriod);
        $vorgezogenFensterText = $vorgezogenService->fensterText($examPeriod);

        // Kandidaten für den Dialog: mündliche Prüfungen externer Bewerber,
        // die noch keinen Termin haben.
        $vorziehbareExamen = StudentExam::imPruefungszeitraum($examPeriod)
            ->where('exam_type', 'oral')
            ->externalApplicants()
            ->whereDoesntHave('assignment', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->with(['externalExamSubject.subject', 'externalExamSubject.externalApplicant'])
            ->get()
            ->sortBy(fn (StudentExam $e): string => $e->getStudentName() . ' ' . $e->getSubjectLabel())
            ->values();

        return view('planning.index', compact(
            'examPeriod',
            'unplannedExams',
            'timeSlots',
            'examRooms',
            'examRoomsByDate',
            'prepRoomsByDate',
            'waitRoomsByDate',
            'globalExamRooms',
            'teachers',
            'conflicts',
            'stats',
            'studentsWithWrongCount',
            'autoScheduleSettings',
            'outsideAssignments',
            'exportDays',
            'vorgezogeneTermine',
            'vorgezogenFenster',
            'vorgezogenFensterText',
            'vorziehbareExamen',
        ));
    }
    
    /**
     * Manuelle Prüfungszuweisung (Drag & Drop)
     */
    public function assignExam(Request $request)
    {
        $this->ensureCanPlan();
        
        $validated = $request->validate([
            'student_exam_id' => 'required|exists:student_exams,id',
            'time_slot_id' => 'required|exists:time_slots,id',
            'exam_room_id' => 'required|exists:rooms,id',
            'prep_room_id' => 'nullable|exists:rooms,id',
        ]);
        
        $studentExam = StudentExam::findOrFail($validated['student_exam_id']);
        
        $timeSlot = TimeSlot::findOrFail($validated['time_slot_id']);
        
        // ===== SICHERHEITSPRÜFUNGEN =====

        // 0. Ohne Prüfer lässt sich kein Termin verplanen. examiner_id ist
        //    nullable, und die Liste der ungeplanten Prüfungen führt solche
        //    Fälle als "Fach (ohne Prüfer)" — vorher lief das in einen
        //    Typfehler statt in eine Meldung, die weiterhilft.
        if ($studentExam->examiner_id === null) {
            return response()->json([
                'success' => false,
                'message' => 'Für diese Prüfung ist noch kein Prüfer eingetragen. '
                    . 'Bitte tragen Sie zuerst einen Prüfer ein, dann lässt sich der Termin verplanen.',
            ], 422);
        }

        // 1. Schüler darf keine zwei Prüfungen in einer Woche haben
        $studentWeekConflict = $this->checkStudentWeekConflict(
            $studentExam,
            $timeSlot, 
            $validated['student_exam_id']
        );
        if ($studentWeekConflict) {
            return response()->json([
                'success' => false,
                'message' => $studentWeekConflict
            ], 422);
        }
        
        // 1b. Externer Bewerber: höchstens eine mündliche Prüfung an einem Tag
        //     (§ 61 Abs. 3 S. 9). Das ist für ihn die Entsprechung der
        //     Wochenregel oben, von der er ausgenommen ist.
        $externalDayConflict = $this->checkExternalSameDayConflict(
            $studentExam,
            \Carbon\Carbon::parse($timeSlot->date),
            $validated['student_exam_id']
        );
        if ($externalDayConflict) {
            return response()->json([
                'success' => false,
                'message' => $externalDayConflict
            ], 422);
        }

        // 2. Prüfer darf keine zwei Prüfungen gleichzeitig haben
        $examinerConflict = $this->checkTeacherTimeSlotConflict(
            $studentExam->examiner_id,
            $validated['time_slot_id'],
            $validated['student_exam_id'],
            'Prüfer'
        );
        if ($examinerConflict) {
            return response()->json([
                'success' => false,
                'message' => $examinerConflict
            ], 422);
        }
        
        // 3. Beisitzer darf keine zwei Prüfungen gleichzeitig haben
        $assessorConflict = $this->checkTeacherTimeSlotConflict(
            $studentExam->assessor_id,
            $validated['time_slot_id'],
            $validated['student_exam_id'],
            'Beisitzer'
        );
        if ($assessorConflict) {
            return response()->json([
                'success' => false,
                'message' => $assessorConflict
            ], 422);
        }
        
        // ===== ZUWEISUNG SPEICHERN =====
        
        // Gesperrter Tag? → Soft-Warning (wird nach dem Speichern zurückgegeben)
        $blockedDayWarning = null;
        $student = \App\Models\Student::with('blockedDays')->find($studentExam->student_id);
        if ($student !== null) {
            $slotDateCarbon = \Carbon\Carbon::parse($timeSlot->date);
            $blockedDay = $student->blockedDays->first(fn($b) => $b->date->isSameDay($slotDateCarbon));
            if ($blockedDay !== null) {
                $reason = $blockedDay->reason ? " (Grund: {$blockedDay->reason})" : '';
                $blockedDayWarning = "⚠️ Achtung: Der {$slotDateCarbon->format('d.m.Y')} ist für {$student->full_name} als gesperrter Tag eingetragen{$reason}.";
            }
        }
        
        // Woche aus Datum des Zeitslots bestimmen
        $examPeriod = $timeSlot->examPeriod;
        $slotDate   = $timeSlot->date;
        $examWeek   = 1;
        if ($examPeriod->week_2_start && $examPeriod->week_2_end
            && $slotDate >= $examPeriod->week_2_start
            && $slotDate <= $examPeriod->week_2_end) {
            $examWeek = 2;
        }

        // Erkrankungs-Absage für diese student_exam_id? → replaces_id setzen
        $illnessCancellation = ExamAssignment::where('student_exam_id', $validated['student_exam_id'])
            ->where('status', 'cancelled')
            ->where('cancelled_reason', 'illness')
            ->orderByDesc('id')
            ->first();

        $assignment = ExamAssignment::where('student_exam_id', $validated['student_exam_id'])
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($assignment) {
            $assignment->update([
                'time_slot_id' => $validated['time_slot_id'],
                'exam_room_id' => $validated['exam_room_id'],
                'prep_room_id' => $validated['prep_room_id'] ?? null,
                'exam_week'    => $examWeek,
            ]);
        } else {
            $assignment = ExamAssignment::create([
                'student_exam_id' => $validated['student_exam_id'],
                'time_slot_id'    => $validated['time_slot_id'],
                'exam_room_id'    => $validated['exam_room_id'],
                'prep_room_id'    => $validated['prep_room_id'] ?? null,
                'exam_week'       => $examWeek,
                'status'          => 'planned',
                'replaces_id'     => $illnessCancellation?->id,
            ]);
        }
        
        // Konflikte prüfen (für Warnungen)
        $conflicts = $this->checkAssignmentConflicts($assignment);
        
        // Logging
        ActivityLogger::log(
            'exam_assigned',
            'ExamAssignment',
            $assignment->id,
            "Prüfung für {$studentExam->getStudentName()} zugewiesen"
        );
        
        return response()->json([
            'success' => true,
            'assignment' => $assignment->load([
                'studentExam.student',
                'studentExam.course.subject',
                'studentExam.assessor',
                'timeSlot',
                'examRoom',
            ]),
            'conflicts' => $conflicts,
            'blocked_day_warning' => $blockedDayWarning,
        ]);
    }
    
    /**
     * Prüft ob ein Prüfling bereits eine Prüfung in der gleichen Woche hat.
     *
     * Ausnahme: Nachtermine (replaces_id != null) werden weder als Konflikt
     * gewertet noch blockiert – erkrankte Schüler dürfen in derselben Woche
     * zwei Prüfungen haben.
     *
     * Nimmt die Prüfung selbst entgegen und nicht deren student_id: Bei
     * externen Bewerbern ist student_id null (der Bewerber ist kein Schüler
     * der Schule). Die Methode brach damit ab, sobald ein externer Prüfling
     * zugewiesen wurde — und hätte, nur um den Typfehler bereinigt, für
     * Externe stillschweigend gar nichts mehr geprüft: where('student_id',
     * null) trifft in SQL nie zu. Der Scope desselbenPrueflings kennt beide
     * Fälle.
     */
    private function checkStudentWeekConflict(StudentExam $studentExam, TimeSlot $timeSlot, int $excludeExamId): ?string
    {
        // Externe Bewerber sind ausgenommen: Sie legen nach BayGSO alle Fächer
        // ab und haben typischerweise vier mündliche Prüfungen. Die Regel
        // "höchstens eine pro Woche" stammt aus der Kolloquiumsplanung
        // regulärer Schüler, die genau zwei Prüfungen auf zwei Wochen verteilt
        // bekommen. Auf Externe angewandt wäre sie schlicht nicht erfüllbar.
        if ($studentExam->isExternalApplicant()) {
            return null;
        }

        // Bestimme die Woche des Zeitslots
        $examPeriod = $timeSlot->examPeriod;
        $slotDate = $timeSlot->date;

        // Ermittle ob Woche 1 oder 2
        $week = 1;
        if ($slotDate >= $examPeriod->week_2_start && $slotDate <= $examPeriod->week_2_end) {
            $week = 2;
        }

        // Ist die zu platzierende Prüfung selbst ein Nachtermin?
        // → Wochenregel komplett überspringen (erkrankter Schüler hat Sonderrecht)
        $isNachtermin = ExamAssignment::where('student_exam_id', $excludeExamId)
            ->whereNotNull('replaces_id')
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($isNachtermin) {
            return null;
        }

        // Hat die Prüfung einen Erkrankungs-Absagevermerk (ohne Nachtermin)?
        // → Schüler war krank und wartet noch auf Nachtermin-Platzierung → Ausnahme erlauben
        $hasIllnessCancellation = ExamAssignment::where('student_exam_id', $excludeExamId)
            ->where('cancelled_reason', 'illness')
            ->exists();
        if ($hasIllnessCancellation) {
            return null;
        }

        // Prüfe ob der Schüler bereits eine andere aktive Prüfung in dieser Woche hat.
        // Nachtermine (replaces_id != null) zählen nicht als Konflikt – sie wurden
        // durch Erkrankung erzwungen und sollen reguläre Prüfungen nicht blockieren.
        $existingAssignment = ExamAssignment::whereHas('studentExam', function($query) use ($studentExam, $excludeExamId) {
                $query->desselbenPrueflings($studentExam)
                      ->where('id', '!=', $excludeExamId);
            })
            ->where('status', '!=', 'cancelled')  // Erkrankungs-Absagen nicht mitzählen
            ->whereNull('replaces_id')             // Nachtermine nicht als Konflikt werten
            ->whereHas('timeSlot', function($query) use ($examPeriod, $week) {
                if ($week === 1) {
                    $query->where('exam_period_id', $examPeriod->id)
                          ->whereBetween('date', [$examPeriod->week_1_start, $examPeriod->week_1_end]);
                } else {
                    $query->where('exam_period_id', $examPeriod->id)
                          ->whereBetween('date', [$examPeriod->week_2_start, $examPeriod->week_2_end]);
                }
            })
            ->with(['timeSlot', 'studentExam.student', 'studentExam.externalExamSubject.externalApplicant'])
            ->first();

        if ($existingAssignment) {
            $name = $existingAssignment->studentExam->getStudentName();
            $existingDate = $existingAssignment->timeSlot->date->format('d.m.Y');
            return "{$name} hat bereits eine Prüfung in Woche {$week} (am {$existingDate})";
        }

        return null;
    }
    
    /**
     * Prüft, ob ein externer Bewerber am selben Tag bereits eine mündliche
     * Prüfung hat — § 61 Abs. 3 S. 9 (Regel C9 in
     * docs/externe-bewerber/REGELWERK-61-62.md).
     *
     * Die Norm lautet „Sofern die Belange der prüfenden Schule es erlauben,
     * findet nur eine mündliche Prüfung an einem Tag statt". Entscheidung
     * Wilfrieds vom 30.08.2026: Die Regel wird hart durchgesetzt, so wie die
     * Wochenregel bei regulären Schülern hart ist. Für reguläre Prüflinge gibt
     * es diese Regel nicht — der Aufruf steigt für sie sofort aus.
     *
     * Nachtermine sind ausgenommen, aus demselben Grund wie bei der
     * Wochenregel: Sie sind durch eine Erkrankung erzwungen und sollen nicht an
     * einer Regel scheitern, die die reguläre Verteilung ordnet.
     *
     * Die Methode nimmt das Datum entgegen, nicht den Zeitslot: Seit § 61
     * Abs. 4 (31.08.2026) gibt es Prüfungen ohne Zeitslot, deren Tag in
     * `free_date` steht. Wer nur über `timeSlot` sucht, sieht sie nicht — und
     * zwei vorgezogene Prüfungen desselben Bewerbers am selben Tag wären an
     * der Regel vorbeigelaufen.
     */
    private function checkExternalSameDayConflict(
        StudentExam $studentExam,
        \Carbon\Carbon $datum,
        int $excludeExamId
    ): ?string {
        if (!$studentExam->isExternalApplicant()) {
            return null;
        }

        $isNachtermin = ExamAssignment::where('student_exam_id', $excludeExamId)
            ->whereNotNull('replaces_id')
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($isNachtermin) {
            return null;
        }

        $existingAssignment = ExamAssignment::whereHas(
                'studentExam',
                fn ($query) => $query->desselbenPrueflings($studentExam)
                    ->where('exam_type', 'oral')
                    ->where('id', '!=', $excludeExamId)
            )
            ->where('status', '!=', 'cancelled')
            ->whereNull('replaces_id')
            ->where(fn ($query) => $query
                ->whereHas('timeSlot', fn ($slot) => $slot->whereDate('date', $datum))
                ->orWhereDate('free_date', $datum))
            ->with('timeSlot')
            ->first();

        if ($existingAssignment === null) {
            return null;
        }

        $name      = $studentExam->getStudentName();
        $datumText = $datum->format('d.m.Y');

        return "{$name} hat am {$datumText} bereits eine mündliche Prüfung. "
            . 'Nach § 61 Abs. 3 S. 9 findet für externe Bewerber nur eine '
            . 'mündliche Prüfung an einem Tag statt.';
    }

    /**
     * Prüft ob ein Lehrer bereits zur gleichen Zeit eingeteilt ist (als Prüfer oder Beisitzer)
     */
    private function checkTeacherTimeSlotConflict(?int $teacherId, int $timeSlotId, int $excludeExamId, string $role): ?string
    {
        // Ohne eingeteilte Lehrkraft gibt es nichts, womit sie kollidieren
        // könnte. examiner_id und assessor_id sind beide nullable, und
        // Prüfungen ohne Prüfer sind in der Planung Alltag — die Liste der
        // ungeplanten Prüfungen führt sie als "Fach (ohne Prüfer)".
        //
        // Beim Beisitzer stand diese Prüfung bisher am Aufruf, beim Prüfer
        // fehlte sie. Hier steht sie einmal für beide.
        if ($teacherId === null) {
            return null;
        }

        $teacher = Teacher::find($teacherId);
        
        // Prüfe als Beisitzer (über StudentExam)
        $conflictAsAssessor = ExamAssignment::where('time_slot_id', $timeSlotId)
            ->whereHas('studentExam', function($q) use ($teacherId, $excludeExamId) {
                $q->where('assessor_id', $teacherId)
                  ->where('id', '!=', $excludeExamId);
            })
            ->exists();
        
        if ($conflictAsAssessor) {
            return "{$role} {$teacher->shortcode} ist zu dieser Zeit bereits als Beisitzer eingeteilt";
        }
        
        // Prüfe als Prüfer
        $conflictAsExaminer = ExamAssignment::where('time_slot_id', $timeSlotId)
            ->whereHas('studentExam', function($query) use ($teacherId, $excludeExamId) {
                $query->where('examiner_id', $teacherId)
                      ->where('id', '!=', $excludeExamId);
            })
            ->exists();
        
        if ($conflictAsExaminer) {
            return "{$role} {$teacher->shortcode} ist zu dieser Zeit bereits als Prüfer eingeteilt";
        }
        
        return null;
    }
    
    /**
     * Zuweisung entfernen
     */
    public function removeAssignment(ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        try {
            $this->ensureCanPlan();
            
            // Lade Relationen ohne Global Scope
            $assignment->load(['studentExam' => function($query) {
                $query->with(['student' => function($q) {
                    $q->withoutGlobalScopes();
                }]);
            }]);
            
            $studentName = $assignment->studentExam->getStudentName();
            $assignmentId = $assignment->id;
            
            $assignment->delete();
            
            ActivityLogger::log(
                'exam_unassigned',
                'ExamAssignment',
                $assignmentId,
                "Prüfungszuweisung für {$studentName} entfernt"
            );
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Fehler beim Löschen der Zuweisung: ' . $e->getMessage(), [
                'assignment_id' => $assignment->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // Erkrankung / Nachtermin
    // =========================================================================

    /**
     * Termin wegen Erkrankung auf einen anderen Slot verschieben (Drag & Drop).
     * Atomare Operation: altes Assignment → cancelled/illness, neues → planned.
     */
    public function illnessReschedule(Request $request, ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();

        if ($assignment->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Dieser Termin ist bereits abgesagt.'], 422);
        }

        $validated = $request->validate([
            'time_slot_id' => 'required|exists:time_slots,id',
            'exam_room_id' => 'required|exists:rooms,id',
        ]);

        $timeSlot    = TimeSlot::findOrFail($validated['time_slot_id']);
        $studentExam = StudentExam::with(['student', 'examiner', 'assessor'])
                           ->findOrFail($assignment->student_exam_id);

        // Prüfer-/Beisitzer-Konflikt im neuen Slot prüfen
        $examinerConflict = $this->checkTeacherTimeSlotConflict(
            $studentExam->examiner_id,
            $validated['time_slot_id'],
            $studentExam->id,
            'Prüfer'
        );
        if ($examinerConflict) {
            return response()->json(['success' => false, 'message' => $examinerConflict], 422);
        }

        if ($studentExam->assessor_id) {
            $assessorConflict = $this->checkTeacherTimeSlotConflict(
                $studentExam->assessor_id,
                $validated['time_slot_id'],
                $studentExam->id,
                'Beisitzer'
            );
            if ($assessorConflict) {
                return response()->json(['success' => false, 'message' => $assessorConflict], 422);
            }
        }

        // Ziel-Slot bereits belegt?
        $slotOccupied = ExamAssignment::where('time_slot_id', $validated['time_slot_id'])
            ->where('exam_room_id', $validated['exam_room_id'])
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($slotOccupied) {
            return response()->json(['success' => false, 'message' => 'Der Zielslot ist bereits belegt.'], 422);
        }

        // Woche des neuen Slots bestimmen
        $newExamWeek = 1;
        if ($examPeriod->week_2_start && $examPeriod->week_2_end
            && $timeSlot->date >= $examPeriod->week_2_start
            && $timeSlot->date <= $examPeriod->week_2_end) {
            $newExamWeek = 2;
        }

        DB::transaction(function () use ($assignment, $validated, $newExamWeek, $studentExam) {
            // 1. Altes Assignment als krank markieren
            $assignment->update([
                'status'           => 'cancelled',
                'cancelled_reason' => 'illness',
            ]);

            // 2. Neues (Nachtermin-)Assignment anlegen
            $newAssignment = ExamAssignment::create([
                'student_exam_id' => $assignment->student_exam_id,
                'time_slot_id'    => $validated['time_slot_id'],
                'exam_room_id'    => $validated['exam_room_id'],
                'exam_week'       => $newExamWeek,
                'status'          => 'planned',
                'replaces_id'     => $assignment->id,
            ]);

            ActivityLogger::log(
                'illness_reschedule',
                'ExamAssignment',
                $newAssignment->id,
                "Nachtermin (Erkrankung) für {$studentExam->getStudentName()} – " .
                "Original #{$assignment->id} abgesagt, Nachtermin #{$newAssignment->id} angelegt"
            );
        });

        return response()->json(['success' => true]);
    }

    /**
     * Termin wegen Erkrankung auf einen freien Termin außerhalb der Prüfungswochen verschieben.
     */
    public function illnessRescheduleOutside(Request $request, ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();

        // Bereits abgesagt aus einem anderen Grund → ablehnen
        if ($assignment->status === 'cancelled' && $assignment->cancelled_reason !== 'illness') {
            return response()->json(['success' => false, 'message' => 'Dieser Termin ist bereits abgesagt.'], 422);
        }

        $validated = $request->validate([
            'free_date'       => 'required|date',
            'free_time_start' => 'required|date_format:H:i',
            'free_time_end'   => 'required|date_format:H:i|after:free_time_start',
            'exam_room_id'    => 'nullable|exists:rooms,id',
            'notes'           => 'nullable|string|max:500',
        ]);

        $studentExam = StudentExam::with('student')->findOrFail($assignment->student_exam_id);

        // Ist das Assignment bereits als illness abgesagt (z.B. via cancelAsIllness)?
        // → cancel-Schritt überspringen, direkt Nachtermin anlegen
        $alreadyCancelled = $assignment->status === 'cancelled' && $assignment->cancelled_reason === 'illness';

        DB::transaction(function () use ($assignment, $validated, $studentExam, $alreadyCancelled) {
            if (!$alreadyCancelled) {
                $assignment->update([
                    'status'           => 'cancelled',
                    'cancelled_reason' => 'illness',
                ]);
            }

            $newAssignment = ExamAssignment::create([
                'student_exam_id' => $assignment->student_exam_id,
                'time_slot_id'    => null,
                'exam_room_id'    => $validated['exam_room_id'] ?? $assignment->exam_room_id,
                'exam_week'       => 0,
                'status'          => 'planned',
                'replaces_id'     => $assignment->id,
                'free_date'       => $validated['free_date'],
                'free_time_start' => $validated['free_time_start'],
                'free_time_end'   => $validated['free_time_end'],
                'notes'           => $validated['notes'] ?? null,
            ]);

            ActivityLogger::log(
                'illness_reschedule_outside',
                'ExamAssignment',
                $newAssignment->id,
                "Nachtermin außerhalb (Erkrankung) für {$studentExam->getStudentName()} – " .
                "am {$validated['free_date']} {$validated['free_time_start']}"
            );
        });

        return response()->json(['success' => true]);
    }

    /**
     * Erkrankungs-Markierung rückgängig machen.
     * Löscht den Nachtermin, stellt den Original-Termin wieder her.
     */
    public function revertIllness(ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();

        if ($assignment->status !== 'cancelled' || $assignment->cancelled_reason !== 'illness') {
            return response()->json(['success' => false, 'message' => 'Kein Erkrankungs-Termin.'], 422);
        }

        $studentExam = StudentExam::with('student')->findOrFail($assignment->student_exam_id);

        DB::transaction(function () use ($assignment, $studentExam) {
            // Nachtermin löschen (falls vorhanden)
            $replacement = ExamAssignment::where('replaces_id', $assignment->id)->first();
            if ($replacement) {
                $replacement->delete();
            }

            // Original wiederherstellen
            $assignment->update([
                'status'           => 'planned',
                'cancelled_reason' => null,
            ]);

            ActivityLogger::log(
                'illness_reverted',
                'ExamAssignment',
                $assignment->id,
                "Erkrankungs-Markierung rückgängig für {$studentExam->getStudentName()}"
            );
        });

        return response()->json(['success' => true]);
    }

    /**
     * Prüfung wegen Erkrankung absagen und in die Seitenleiste (ungeplant) verschieben.
     * Im Gegensatz zu illnessReschedule wird kein Nachtermin angelegt – der Schüler
     * kann die Prüfung anschließend per Drag & Drop neu einplanen.
     */
    public function cancelAsIllness(ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();

        if ($assignment->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Dieser Termin ist bereits abgesagt.'], 422);
        }

        $studentExam = StudentExam::with('student')->findOrFail($assignment->student_exam_id);

        $assignment->update([
            'status'           => 'cancelled',
            'cancelled_reason' => 'illness',
        ]);

        ActivityLogger::log(
            'illness_cancelled',
            'ExamAssignment',
            $assignment->id,
            "Prüfung für {$studentExam->getStudentName()} wegen Erkrankung abgesagt – wartet auf Nachtermin"
        );

        return response()->json(['success' => true]);
    }

    /**
     * Außerhalb-Termin bearbeiten (Datum, Uhrzeit, Raum, Notiz aktualisieren).
     */
    public function updateOutsideAssignment(Request $request, ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();

        if ($assignment->exam_week !== 0 || $assignment->time_slot_id !== null) {
            return response()->json(['success' => false, 'message' => 'Kein Außerhalb-Termin.'], 422);
        }

        $validated = $request->validate([
            'free_date'       => 'required|date',
            'free_time_start' => 'required|date_format:H:i',
            'free_time_end'   => 'required|date_format:H:i|after:free_time_start',
            'exam_room_id'    => 'nullable|exists:rooms,id',
            'notes'           => 'nullable|string|max:500',
        ]);

        $assignment->update([
            'free_date'       => $validated['free_date'],
            'free_time_start' => $validated['free_time_start'],
            'free_time_end'   => $validated['free_time_end'],
            // Ohne Auswahl bleibt der bisherige Raum stehen. Bis zum
            // 31.08.2026 stand hier `?? null`: `exam_assignments.exam_room_id`
            // ist NOT NULL, und die Auswahl im Dialog bietet „– kein Raum –"
            // an. Wer das wählte, bekam einen 500er (gemessen: SQLSTATE 23000,
            // „Column 'exam_room_id' cannot be null").
            'exam_room_id'    => $validated['exam_room_id'] ?? $assignment->exam_room_id,
            'notes'           => $validated['notes'] ?? null,
        ]);

        ActivityLogger::log(
            'outside_assignment_updated',
            'ExamAssignment',
            $assignment->id,
            "Außerhalb-Termin aktualisiert: {$validated['free_date']} {$validated['free_time_start']}"
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Vorgezogene mündliche Prüfungen — § 61 Abs. 4 S. 1
    //
    // Zwei der vier Kolloquien eines externen Bewerbers dürfen in den zwei
    // Wochen vor Beginn der schriftlichen Abiturprüfungen liegen. Entscheidung
    // Wilfrieds vom 31.08.2026: von Hand, ohne Zeitslot und ohne
    // Auto-Planung — es sind wenige Bewerber, und die Termine werden einzeln
    // vereinbart. Der Termin sieht deshalb aus wie ein Nachtermin außerhalb
    // der Prüfungswochen (Datum und Uhrzeit am Assignment), trägt aber
    // exam_week = 3 statt 0, damit § 61 Abs. 4 S. 2 sich darauf stützen kann.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Eine vorgezogene mündliche Prüfung eintragen.
     */
    public function storeVorgezogen(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $validated = $request->validate([
            'student_exam_id' => 'required|integer|exists:student_exams,id',
            'free_date'       => 'required|date',
            'free_time_start' => 'required|date_format:H:i',
            'free_time_end'   => 'required|date_format:H:i|after:free_time_start',
            'exam_room_id'    => 'nullable|exists:rooms,id',
            'notes'           => 'nullable|string|max:500',
        ]);

        $studentExam = StudentExam::with('externalExamSubject.externalApplicant')
            ->findOrFail((int) $validated['student_exam_id']);

        $fehler = $this->pruefeVorgezogen($examPeriod, $studentExam, $validated, null);
        if ($fehler !== null) {
            return response()->json(['success' => false, 'message' => $fehler], 422);
        }

        // Ein Raum ist Pflicht: exam_room_id ist in der Tabelle NOT NULL.
        $raumId = $validated['exam_room_id'] ?? null;
        if ($raumId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Bitte einen Prüfungsraum auswählen.',
            ], 422);
        }

        $bestehende = ExamAssignment::where('student_exam_id', $studentExam->id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($bestehende instanceof ExamAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'Diese Prüfung ist bereits verplant. Bitte zuerst den bestehenden Termin entfernen.',
            ], 422);
        }

        $assignment = ExamAssignment::create([
            'student_exam_id' => $studentExam->id,
            'time_slot_id'    => null,
            'exam_room_id'    => $raumId,
            'exam_week'       => ExamAssignment::WOCHE_VORGEZOGEN,
            'status'          => 'planned',
            'free_date'       => $validated['free_date'],
            'free_time_start' => $validated['free_time_start'],
            'free_time_end'   => $validated['free_time_end'],
            'notes'           => $validated['notes'] ?? null,
        ]);

        ActivityLogger::log(
            'vorgezogene_pruefung_angelegt',
            'ExamAssignment',
            $assignment->id,
            "Vorgezogene mündliche Prüfung (§ 61 Abs. 4) für {$studentExam->getStudentName()} "
                . "am {$validated['free_date']} {$validated['free_time_start']}"
        );

        return response()->json(['success' => true]);
    }

    /**
     * Eine vorgezogene mündliche Prüfung ändern.
     */
    public function updateVorgezogen(Request $request, ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();

        if ($assignment->exam_week !== ExamAssignment::WOCHE_VORGEZOGEN) {
            return response()->json([
                'success' => false,
                'message' => 'Keine vorgezogene Prüfung.',
            ], 422);
        }

        $validated = $request->validate([
            'free_date'       => 'required|date',
            'free_time_start' => 'required|date_format:H:i',
            'free_time_end'   => 'required|date_format:H:i|after:free_time_start',
            'exam_room_id'    => 'nullable|exists:rooms,id',
            'notes'           => 'nullable|string|max:500',
        ]);

        $studentExam = StudentExam::with('externalExamSubject.externalApplicant')
            ->findOrFail($assignment->student_exam_id);

        $fehler = $this->pruefeVorgezogen($examPeriod, $studentExam, $validated, $assignment);
        if ($fehler !== null) {
            return response()->json(['success' => false, 'message' => $fehler], 422);
        }

        $assignment->update([
            'free_date'       => $validated['free_date'],
            'free_time_start' => $validated['free_time_start'],
            'free_time_end'   => $validated['free_time_end'],
            // Ohne Auswahl bleibt der bisherige Raum stehen: exam_room_id ist
            // NOT NULL, ein `?? null` liefe hier in einen 500er.
            'exam_room_id'    => $validated['exam_room_id'] ?? $assignment->exam_room_id,
            'notes'           => $validated['notes'] ?? null,
        ]);

        ActivityLogger::log(
            'vorgezogene_pruefung_geaendert',
            'ExamAssignment',
            $assignment->id,
            "Vorgezogene mündliche Prüfung geändert: {$validated['free_date']} {$validated['free_time_start']}"
        );

        return response()->json(['success' => true]);
    }

    /**
     * Die Regeln, die für eine vorgezogene Prüfung gelten — einmal für Anlegen
     * und Ändern. Gibt die Meldung zurück oder null, wenn nichts entgegensteht.
     *
     * @param  array<string, mixed>  $validated
     */
    private function pruefeVorgezogen(
        ExamPeriod $examPeriod,
        StudentExam $studentExam,
        array $validated,
        ?ExamAssignment $bestehende
    ): ?string {
        $dienst = app(\App\Services\VorgezogeneKolloquienService::class);

        // 1. Nur externe Bewerber. § 61 regelt „andere Bewerber"; für einen
        //    regulären Schüler gibt es den vorgezogenen Zeitraum nicht.
        if (! $studentExam->isExternalApplicant()) {
            return 'Vorgezogene mündliche Prüfungen nach § 61 Abs. 4 gibt es nur für externe Bewerber.';
        }

        $fach = $studentExam->externalExamSubject;

        // 2. Nur die vier Fächer des zweiten Prüfungsteils. Die Norm spricht
        //    von „zwei der vier mündlichen Prüfungen"; die Fächer 1 bis 4 des
        //    ersten Teils werden schriftlich geprüft.
        if ($fach === null || $fach->exam_part !== 'part2_oral') {
            return 'Vorziehen lassen sich nur die vier mündlichen Prüfungen des zweiten Prüfungsteils.';
        }

        $bewerber = $fach->externalApplicant;

        if (! $bewerber instanceof ExternalApplicant) {
            return 'Zu dieser Prüfung ist kein Bewerber hinterlegt.';
        }

        // 3. Das Datum muss in den zwei Wochen vor der ersten schriftlichen
        //    Prüfung liegen.
        $datum = \Carbon\Carbon::parse((string) $validated['free_date']);

        if (! $dienst->liegtImFenster($examPeriod, $datum)) {
            return 'Der Termin liegt außerhalb des Zeitraums nach § 61 Abs. 4 S. 1 ('
                . $dienst->fensterText($examPeriod) . ').';
        }

        // 4. Höchstens zwei je Bewerber.
        $schonVorgezogen = $dienst->anzahlFuer(
            $bewerber,
            $bestehende instanceof ExamAssignment ? $studentExam->id : null
        );

        if ($schonVorgezogen >= \App\Services\VorgezogeneKolloquienService::HOECHSTZAHL) {
            return $studentExam->getStudentName() . ' hat bereits zwei vorgezogene Prüfungen. '
                . 'Nach § 61 Abs. 4 S. 1 dürfen es höchstens zwei der vier mündlichen Prüfungen sein.';
        }

        // 5. C9 gilt weiter: höchstens eine mündliche Prüfung an einem Tag.
        return $this->checkExternalSameDayConflict($studentExam, $datum, $studentExam->id);
    }

    /**
     * Automatische Planung starten
     */
    public function autoSchedule(Request $request)
    {
        $this->ensureCanPlan();
        
        // Aktuellen Prüfungszeitraum finden (neuester)
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        
        if (!$examPeriod) {
            return response()->json([
                'success' => false,
                'message' => 'Kein Prüfungszeitraum gefunden.'
            ], 404);
        }
        
        $validated = $request->validate([
            'clear_existing'      => 'boolean',
            'max_consecutive'     => 'nullable|integer|min:1|max:10',
            'lf_preferred_week'   => 'nullable|integer|in:1,2',
            'bundle_teacher_days' => 'boolean',
            'respect_free_days'   => 'boolean',
        ]);

        // Parameter persistieren
        SchoolSetting::set('planning.clear_existing',      ($validated['clear_existing'] ?? false) ? '1' : '0');
        SchoolSetting::set('planning.max_consecutive',     (string) ($validated['max_consecutive'] ?? 5));
        SchoolSetting::set('planning.lf_preferred_week',   isset($validated['lf_preferred_week']) ? (string) $validated['lf_preferred_week'] : '');
        SchoolSetting::set('planning.bundle_teacher_days', ($validated['bundle_teacher_days'] ?? true) ? '1' : '0');
        SchoolSetting::set('planning.respect_free_days',   ($validated['respect_free_days'] ?? true) ? '1' : '0');

        try {
            $planningService = app(ExamPlanningService::class);
            
            $result = $planningService->autoSchedule($examPeriod, [
                'clear_existing' => $validated['clear_existing'] ?? false,
                'lf_preferred_week' => $validated['lf_preferred_week'] ?? null,
                // Modul 4.3: Prüfungstage pro Lehrkraft bündeln
                'bundle_teacher_days' => (bool) ($validated['bundle_teacher_days'] ?? true),
                // Modul 4.3 ("freie Tage"): stundenplanfreie Tage möglichst meiden
                'respect_free_days' => (bool) ($validated['respect_free_days'] ?? true),
                // Fix: max_consecutive wurde bisher zwar persistiert, aber nie an
                // den OptimizationService durchgereicht (Service-Key heißt anders)
                'max_consecutive_exams' => (int) ($validated['max_consecutive'] ?? 5),
            ]);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] 
                    ? "Planung abgeschlossen: {$result['scheduled_count']} Prüfungen geplant"
                    : 'Planung abgeschlossen mit Konflikten',
                'scheduled_count' => $result['scheduled_count'] ?? 0,
                'stats' => $result['stats'] ?? [],
                'warnings' => $result['warnings'] ?? [],
                'conflicts' => $result['conflicts'] ?? [],
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler bei der Autoplanung: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Konflikte abrufen
     */
    public function getConflicts(ExamPeriod $examPeriod)
    {
        $conflicts = [
            'errors' => [],
            'warnings' => []
        ];
        
        // Teacher Double Booking (Beisitzer über student_exams)
        $teacherConflicts = DB::table('exam_assignments as ea1')
            ->join('student_exams as se1', 'ea1.student_exam_id', '=', 'se1.id')
            ->join('exam_assignments as ea2', function($join) {
                $join->on('ea1.time_slot_id', '=', 'ea2.time_slot_id')
                     ->on('ea1.id', '!=', 'ea2.id');
            })
            ->join('student_exams as se2', function($join) {
                $join->on('ea2.student_exam_id', '=', 'se2.id')
                     ->on('se1.assessor_id', '=', 'se2.assessor_id');
            })
            ->join('time_slots', 'ea1.time_slot_id', '=', 'time_slots.id')
            ->join('teachers', 'se1.assessor_id', '=', 'teachers.id')
            // Der Zeitraum steht am Zeitslot, nicht am Schüler. Der frühere
            // INNER JOIN auf `students` holte ihn von dort — und schloss damit
            // jede Prüfung eines externen Bewerbers aus (student_id ist bei
            // ihnen null). Eine Doppelbuchung, an der eine externe Prüfung
            // beteiligt war, wurde deshalb nicht gemeldet, und die Kachel
            // „Konflikte" zählte sie nicht mit.
            ->where('time_slots.exam_period_id', $examPeriod->id)
            ->whereNotNull('se1.assessor_id')
            ->select('teachers.shortcode', 'time_slots.date', 'time_slots.exam_start_time')
            ->distinct()
            ->get();
            
        foreach ($teacherConflicts as $conflict) {
            $conflicts['errors'][] = [
                'type' => 'teacher_double_booking',
                'message' => "Lehrer {$conflict->shortcode} ist doppelt gebucht am {$conflict->date} um {$conflict->exam_start_time}"
            ];
        }
        
        // Room Conflicts
        $roomConflicts = DB::table('exam_assignments as ea1')
            ->join('exam_assignments as ea2', function($join) {
                $join->on('ea1.time_slot_id', '=', 'ea2.time_slot_id')
                     ->on('ea1.exam_room_id', '=', 'ea2.exam_room_id')
                     ->on('ea1.id', '!=', 'ea2.id');
            })
            ->join('time_slots', 'ea1.time_slot_id', '=', 'time_slots.id')
            ->join('rooms', 'ea1.exam_room_id', '=', 'rooms.id')
            // Wie oben: der Zeitraum kommt vom Zeitslot. Der Join über
            // `student_exams` auf `students` diente allein dazu und ließ
            // Räume, die durch eine externe Prüfung doppelt belegt waren,
            // unbemerkt.
            ->where('time_slots.exam_period_id', $examPeriod->id)
            ->select('rooms.room_number', 'time_slots.date', 'time_slots.exam_start_time')
            ->distinct()
            ->get();
            
        foreach ($roomConflicts as $conflict) {
            $conflicts['errors'][] = [
                'type' => 'room_conflict',
                'message' => "Raum {$conflict->room_number} ist doppelt belegt am {$conflict->date} um {$conflict->exam_start_time}"
            ];
        }

        // § 61 Abs. 4 S. 2: Abbruchgrund nach einer vorgezogenen Prüfung.
        // Als Warnung, nicht als Fehler — der Abbruch ist eine Entscheidung
        // des Koordinators, nicht der Anwendung (Entscheidung Wilfrieds vom
        // 31.08.2026). Die Anwendung sagt nur, dass der Grund vorliegt.
        foreach (app(\App\Services\VorgezogeneKolloquienService::class)->abbruchgruende($examPeriod) as $grund) {
            $conflicts['warnings'][] = [
                'type' => 'abbruchgrund_61_4',
                'message' => $grund,
            ];
        }

        // § 62 Abs. 1 S. 4 (E6): Der erste Prüfungsteil ist nicht bestanden.
        // Gerechnet wird das längst — im Mindestpunkte-Modal und im PDF steht
        // der Abbruch. Hier steht er, weil die vier mündlichen Prüfungen sonst
        // unbemerkt weiter im Raster stehen. Auch das eine Meldung und kein
        // Zustand (Entscheidung Wilfrieds, 31.08.2026).
        foreach (app(\App\Services\ExterneAbiturDataService::class)->abbruchgruendeErsterTeil($examPeriod) as $grund) {
            $conflicts['warnings'][] = [
                'type' => 'abbruchgrund_62_1_4',
                'message' => $grund,
            ];
        }

        return $conflicts;
    }
    
    /**
     * Konflikte für eine spezifische Zuweisung prüfen
     */
    private function checkAssignmentConflicts(ExamAssignment $assignment)
    {
        $conflicts = [];
        
        // Beisitzer bereits eingeteilt? (über StudentExam)
        $assessorId = $assignment->studentExam->assessor_id;
        if ($assessorId) {
            $teacherConflict = ExamAssignment::where('time_slot_id', $assignment->time_slot_id)
                ->whereHas('studentExam', fn($q) => $q->where('assessor_id', $assessorId))
                ->where('id', '!=', $assignment->id)
                ->exists();
                
            if ($teacherConflict) {
                $conflicts[] = [
                    'type' => 'warning',
                    'message' => 'Beisitzer ist bereits zu dieser Zeit eingeteilt'
                ];
            }
        }
        
        // Raum bereits belegt?
        $roomConflict = ExamAssignment::where('time_slot_id', $assignment->time_slot_id)
            ->where('exam_room_id', $assignment->exam_room_id)
            ->where('id', '!=', $assignment->id)
            ->exists();
            
        if ($roomConflict) {
            $conflicts[] = [
                'type' => 'error',
                'message' => 'Raum ist bereits zu dieser Zeit belegt'
            ];
        }
        
        return $conflicts;
    }
    
    /**
     * Statistiken abrufen
     */
    public function getStats(ExamPeriod $examPeriod)
    {
        $stats = [
            'total_exams' => StudentExam::imPruefungszeitraum($examPeriod)
                ->where('exam_type', 'oral')->count(), // ✅ Nur mündliche Prüfungen
            'assigned_exams' => ExamAssignment::whereHas(
                'studentExam',
                fn ($q) => $q->imPruefungszeitraum($examPeriod)->where('exam_type', 'oral')
            )->count(),
            'conflicts' => count($this->getConflicts($examPeriod)['errors']),
            'teachers' => Teacher::count(),
            'rooms' => Room::where('room_type', 'exam')->count(),
        ];
        
        return response()->json($stats);
    }

    /**
     * Prüfung fixieren/entsperren (Toggle)
     * Fixierte Prüfungen werden bei Autoplanung nicht verändert
     */
    public function toggleLock(Request $request, ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();
        
        $validated = $request->validate([
            'lock_reason' => 'nullable|string|max:255',
        ]);
        
        $wasLocked = $assignment->is_locked;
        $assignment->is_locked = !$wasLocked;
        $assignment->lock_reason = $assignment->is_locked 
            ? ($validated['lock_reason'] ?? 'Manuell fixiert')
            : null;
        $assignment->save();
        
        return response()->json([
            'success' => true,
            'is_locked' => $assignment->is_locked,
            'message' => $assignment->is_locked 
                ? 'Prüfung fixiert - wird bei Autoplanung nicht verändert'
                : 'Fixierung aufgehoben'
        ]);
    }

    /**
     * Prüfung fixieren (explizit)
     */
    public function lockAssignment(Request $request, ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();
        
        $validated = $request->validate([
            'lock_reason' => 'nullable|string|max:255',
        ]);
        
        $assignment->is_locked = true;
        $assignment->lock_reason = $validated['lock_reason'] ?? 'Manuell fixiert';
        $assignment->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Prüfung fixiert'
        ]);
    }

    /**
     * Fixierung aufheben (explizit)
     */
    public function unlockAssignment(ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();
        
        $assignment->is_locked = false;
        $assignment->lock_reason = null;
        $assignment->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Fixierung aufgehoben'
        ]);
    }

    /**
     * Validiert alle Constraints für den aktuellen Prüfungszeitraum
     */
    public function validate(ValidationService $validationService)
    {
        // Aktuellen Prüfungszeitraum finden (neuester)
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        
        if (!$examPeriod) {
            return response()->json([
                'success' => false,
                'error' => 'Kein Prüfungszeitraum gefunden'
            ], 404);
        }
        
        $this->ensureCanPlan();
        
        try {
            $result = $validationService->validateExamPeriod($examPeriod);
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Validation error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Fehler bei der Validierung: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Exportiert eine alphabetische Lehrerliste mit allen Prüfungsdaten als PDF
     */
    public function exportTeachersPdf(ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();
        
        // Alle Lehrer der eigenen Schule mit ihren Prüfungen laden
        // Alle Lehrer der eigenen Schule mit ihren Prüfungen laden
        $teachers = Teacher::orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(function($teacher) use ($examPeriod) {
                // Prüfungen als Prüfer. Über imPruefungszeitraum(): sonst
                // fehlten einer Lehrkraft genau die Kolloquien externer
                // Bewerber in ihrer eigenen Übersicht.
                $examsAsExaminer = ExamAssignment::whereHas(
                        'studentExam',
                        fn ($q) => $q->where('examiner_id', $teacher->id)
                            ->imPruefungszeitraum($examPeriod)
                    )
                    ->with([
                        'studentExam.student', 'studentExam.course.subject',
                        'studentExam.externalExamSubject.subject',
                        'studentExam.externalExamSubject.externalApplicant',
                        'studentExam.assessor', 'timeSlot', 'examRoom',
                    ])
                    ->orderBy('time_slot_id')
                    ->get();
                
                // Prüfungen als Beisitzer
                $examsAsAssessor = ExamAssignment::whereHas(
                        'studentExam',
                        fn ($q) => $q->where('assessor_id', $teacher->id)
                            ->imPruefungszeitraum($examPeriod)
                    )
                    ->with([
                        'studentExam.student', 'studentExam.course.subject',
                        'studentExam.externalExamSubject.subject',
                        'studentExam.externalExamSubject.externalApplicant',
                        'studentExam.examiner', 'timeSlot', 'examRoom',
                    ])
                    ->orderBy('time_slot_id')
                    ->get();
                
                $teacher->examsAsExaminer = $examsAsExaminer;
                $teacher->examsAsAssessor = $examsAsAssessor;
                $teacher->totalExams = $examsAsExaminer->count() + $examsAsAssessor->count();
                
                return $teacher;
            })
            ->filter(function($teacher) {
                // Nur Lehrer mit Prüfungen anzeigen
                return $teacher->totalExams > 0;
            });
        
        $schoolId = $this->getSchoolId();
        $school = \App\Models\School::find($schoolId);
        
        $pdf = Pdf::loadView('exports.teachers-list-pdf', [
            'examPeriod' => $examPeriod,
            'teachers' => $teachers,
            'school' => $school,
        ]);
        
        $filename = 'Lehrerliste_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Exportiert eine alphabetische Schülerliste mit allen Prüfungsdaten als PDF
     */
    public function exportStudentsPdf(ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        // Alle regulären Schüler des Prüfungszeitraums alphabetisch laden
        $students = \App\Models\Student::where('exam_period_id', $examPeriod->id)
            ->derStufe('13')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->with([
                'exams' => function ($q) {
                    $q->where('exam_type', 'oral')
                      ->with([
                          'assignment.timeSlot',
                          'assignment.examRoom',
                          'examiner',
                          'assessor',
                          'course.subject',
                      ]);
                },
            ])
            ->get();

        $schoolId = $this->getSchoolId();
        $school   = \App\Models\School::find($schoolId);

        $pdf = Pdf::loadView('exports.students-list-pdf', [
            'examPeriod' => $examPeriod,
            'students'   => $students,
            'school'     => $school,
        ]);

        $filename = 'Schuelerliste_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // KOLLOQUIUMSPLAN-EXPORTE
    // =========================================================================

    /**
     * Aufsichten (Vorbereitung/Warteraum/Gangaufsicht) pro Tag – Struktur:
     * date => [ [start, end, types[prep/corridor/waiting => Teacher[]]] ]
     *
     * Aufsichten hängen direkt am echten Prüfungsslot (Modul 4.4, kein eigenes
     * Zeitraster). Ein Eintrag pro Prüfungsslot mit mind. einer zugewiesenen
     * Aufsicht dieses Tages.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildSupervisionSlotsByDate(ExamPeriod $examPeriod): array
    {
        $timeSlots = \App\Models\TimeSlot::where('exam_period_id', $examPeriod->id)
            ->with([
                'supervisions' => fn ($q) => $q
                    ->whereIn('supervision_type', ['preparation', 'waiting', 'corridor'])
                    ->with('teacher'),
            ])
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get();

        $supervisionSlotsByDate = [];
        foreach ($timeSlots as $slot) {
            if ($slot->supervisions->isEmpty()) {
                continue;
            }
            $dateKey      = $slot->date->format('Y-m-d');
            $typeTeachers = ['preparation' => [], 'corridor' => [], 'waiting' => []];
            foreach ($slot->supervisions as $sup) {
                $type = $sup->supervision_type;
                if (isset($typeTeachers[$type]) && $sup->teacher) {
                    $typeTeachers[$type][] = $sup->teacher;
                }
            }
            $supervisionSlotsByDate[$dateKey][] = [
                'start' => substr((string)$slot->prep_start_time, 0, 5),
                'end'   => substr((string)$slot->exam_end_time,   0, 5),
                'types' => $typeTeachers,
            ];
        }

        return $supervisionSlotsByDate;
    }

    /**
     * Kolloquiumsplan als PDF (DIN A4, Querformat)
     */
    public function exportColloquiumPdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $days   = $this->buildColloquiumDays($examPeriod, $this->resolveExportDates($request));
        $school = \App\Models\School::find($this->getSchoolId());

        $schlusstext = SchoolSetting::get('kolloquiumsplan.schlusstext') ?? '';

        // Platzhalter [[warteraum]] und [[nta_liste]] im Schlusstext ersetzen
        if ($schlusstext !== '') {
            // [[warteraum]]: alle eindeutigen Warteräume des Prüfungszeitraums
            $warteraumNummern = \App\Models\OralExamDayRoom::where('exam_period_id', $examPeriod->id)
                ->where('room_role', 'waiting')
                ->with('room')
                ->get()
                ->pluck('room.room_number')
                ->filter()
                ->unique()
                ->sort()
                ->join(', ');

            // [[nta_liste]]: Schüler mit NTA, jeweils "Nachname (X min)"
            // Nachteilsausgleich hängt am Prüfling: ein externer Bewerber kann
            // ihn haben wie ein Schüler, und für die Niederschrift zählt beides.
            $ntaItems = \App\Models\StudentExam::where('exam_type', 'oral')
                ->imPruefungszeitraum($examPeriod)
                ->where(fn($q) => $q
                    ->whereDoesntHave('student')
                    ->orWhereHas('student', fn($s) => $s->derStufe('13'))
                )
                ->with([
                    'student', 'assignment.timeSlot',
                    'externalExamSubject.externalApplicant',
                ])
                ->get()
                ->filter(fn($se) => $se->hatNachteilsausgleich())
                ->map(function ($se) {
                    $slot    = $se->assignment?->timeSlot;
                    $base    = $slot
                        ? (int)((strtotime((string)$slot->prep_end_time)
                               - strtotime((string)$slot->prep_start_time)) / 60)
                        : 20;
                    $extra   = $se->kompensierteDauer($base) - $base;
                    return ['name' => $se->getStudentName(), 'extra' => $extra];
                })
                ->unique('name')
                ->sortBy('name')
                ->map(fn($item) => $item['name'] . ' (' . $item['extra'] . ' min)')
                ->join('; ');

            $schlusstext = str_replace(
                ['[[warteraum]]', '[[nta_liste]]'],
                [
                    $warteraumNummern ?: '–',
                    $ntaItems         ?: '–',
                ],
                $schlusstext
            );
        }

        $supervisionSlotsByDate = $this->buildSupervisionSlotsByDate($examPeriod);

        $showAufsichten = SchoolSetting::get('kolloquiumsplan.show_aufsichten', '1') === '1';

        $pdf = Pdf::loadView('exports.colloquium-plan-pdf', [
            'examPeriod'             => $examPeriod,
            'days'                   => $days,
            'school'                 => $school,
            'supervisionSlotsByDate' => $supervisionSlotsByDate,
            'schlusstext'            => $schlusstext,
            'showAufsichten'         => $showAufsichten,
        ])->setPaper('a4', 'landscape');

        $filename = 'Kolloquiumsplan_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Kolloquiumsplan als Excel (.xlsx)
     */
    public function exportColloquiumExcel(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $days   = $this->buildColloquiumDays($examPeriod, $this->resolveExportDates($request));
        $school = \App\Models\School::find($this->getSchoolId());

        $supervisionSlotsByDate = $this->buildSupervisionSlotsByDate($examPeriod);

        $filename = 'Kolloquiumsplan_' . $examPeriod->name . '_' . date('Y-m-d') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ColloquiumPlanExport($examPeriod, collect($days), $school, $supervisionSlotsByDate),
            $filename
        );
    }

    /**
     * Kompakte Planungsmatrix als PDF:
     * Zeilen = Raum × Zeitslot, Spalten = Prüfungstage
     */
    public function exportCompactMatrixPdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $data   = $this->buildCompactMatrixData($examPeriod, $this->resolveExportDates($request));
        $school = \App\Models\School::find($this->getSchoolId());

        $pdf = Pdf::loadView('exports.colloquium-matrix-compact-pdf', [
            'examPeriod' => $examPeriod,
            'days'       => $data['days'],
            'rooms'      => $data['rooms'],
            'times'      => $data['times'],
            'matrix'     => $data['matrix'],
            'school'     => $school,
        ])->setPaper('a4', 'landscape');

        $filename = 'Kolloquium-Matrix_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Baut die transponierte Matrix-Struktur:
     * matrix[roomId] => ['room' => Room, 'rows' => [time => [dayIndex => ExamAssignment|null]]]
     * Filtert Tage ohne Zuweisungen und Zeitslots ohne Zuweisungen heraus.
     *
     * @param  array<int, string>|null  $allowedDates  Tagesauswahl-Filter (Modul 4.2)
     * @return array<string, mixed>
     */
    private function buildCompactMatrixData(ExamPeriod $examPeriod, ?array $allowedDates = null): array
    {
        $rawDays = $this->buildColloquiumDays($examPeriod, $allowedDates);

        // Nur Tage mit mindestens einer Zuweisung
        $days = array_values(array_filter($rawDays, function (array $day): bool {
            foreach ($day['timeSlots'] as $slot) {
                if ($slot->examAssignments->isNotEmpty()) {
                    return true;
                }
            }
            return false;
        }));

        // Alle Prüfungsräume in Reihenfolge des ersten Auftretens sammeln
        /** @var \Illuminate\Support\Collection<int, Room> $allRooms */
        $allRooms = collect();
        foreach ($days as $day) {
            foreach ($day['examRooms'] as $room) {
                if (!$allRooms->contains('id', $room->id)) {
                    $allRooms->push($room);
                }
            }
        }

        // Nur Zeiten (exam_start_time) sammeln, die in irgendeinem Raum/Tag belegt sind
        /** @var \Illuminate\Support\Collection<int, string> $allTimes */
        $allTimes = collect();
        foreach ($days as $day) {
            foreach ($day['timeSlots'] as $slot) {
                if ($slot->examAssignments->isNotEmpty()) {
                    $t = $slot->exam_start_time;
                    if (!$allTimes->contains($t)) {
                        $allTimes->push($t);
                    }
                }
            }
        }
        $allTimes = $allTimes->sort()->values();

        // Matrix aufbauen: pro Raum → pro Zeit → pro Tag → Zuweisung
        $matrix = [];
        foreach ($allRooms as $room) {
            $roomRows = [];
            foreach ($allTimes as $time) {
                $cells = [];
                foreach ($days as $day) {
                    $slot = $day['timeSlots']->firstWhere('exam_start_time', $time);
                    $assignment = $slot
                        ? $slot->examAssignments->firstWhere('exam_room_id', $room->id)
                        : null;
                    $cells[] = $assignment;
                }
                $roomRows[(string) $time] = $cells;
            }
            $matrix[$room->id] = [
                'room'    => $room,
                'rows'    => $roomRows,
                'numRows' => count($roomRows),
            ];
        }

        return [
            'days'   => $days,
            'rooms'  => $allRooms,
            'times'  => $allTimes,
            'matrix' => $matrix,
        ];
    }

    /**
     * Raum-Tagespläne als PDF — eine Seite pro Raum × Tag mit Prüfungen
     */
    public function exportRoomDaySchedulePdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $school  = \App\Models\School::find($this->getSchoolId());
        $colDays = $this->buildColloquiumDays($examPeriod, $this->resolveExportDates($request));

        // Seiten aufbauen: für jeden Tag und jeden Raum eine eigene Seite,
        // aber nur wenn in diesem Raum an diesem Tag mindestens eine Prüfung stattfindet.
        $pages = [];

        foreach ($colDays as $day) {
            foreach ($day['examRooms'] as $room) {
                // Slots filtern: nur solche, die eine Zuweisung für diesen Raum haben
                $slotsForRoom = $day['timeSlots']->map(function ($slot) use ($room) {
                    $assignment = $slot->examAssignments->firstWhere('exam_room_id', $room->id);
                    return [
                        'slot'       => $slot,
                        'assignment' => $assignment,
                    ];
                })->filter(fn($s) => $s['assignment'] !== null)->values();

                if ($slotsForRoom->isEmpty()) {
                    continue;
                }

                // Alle Slots des Tages für diesen Raum anzeigen (auch leere),
                // damit der Zeitraster vollständig sichtbar ist.
                $allSlots = $day['timeSlots']->map(function ($slot) use ($room) {
                    return [
                        'slot'       => $slot,
                        'assignment' => $slot->examAssignments->firstWhere('exam_room_id', $room->id),
                    ];
                })->values();

                $pages[] = [
                    'date'      => $day['date'],
                    'week'      => $day['week'],
                    'room'      => $room,
                    'prepRooms' => $day['prepRooms'],
                    'waitRooms' => $day['waitRooms'],
                    'slots'     => $allSlots,
                ];
            }
        }

        $pdf = Pdf::loadView('exports.exam-room-day-schedule-pdf', [
            'examPeriod' => $examPeriod,
            'pages'      => $pages,
            'school'     => $school,
        ])->setPaper('a4', 'portrait');

        $filename = 'Raum-Tagespläne_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Vorschaltseite: Auswahl der Prüflinge für die Niederschriften
     */
    public function niederschriftAuswahl(ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $assignments = ExamAssignment::join('time_slots', 'exam_assignments.time_slot_id', '=', 'time_slots.id')
            ->whereHas('studentExam.student', fn ($q) => $q->where('exam_period_id', $examPeriod->id))
            ->with([
                'studentExam.student',
                'studentExam.course.subject',
                'studentExam.examiner',
                'studentExam.assessor',
                'timeSlot',
            ])
            ->orderBy('time_slots.date')
            ->orderBy('time_slots.slot_number')
            ->select('exam_assignments.*')
            ->get();

        return view('planning.niederschrift-auswahl', compact('assignments', 'examPeriod'));
    }

    /**
     * Kolloquiums-Niederschriften als PDF (vorausgefüllt, A4 Hochformat)
     * Optionale Filterung per Query-Parameter ?ids=1,2,3
     */
    public function exportNiederschriftPdf(ExamPeriod $examPeriod, \Illuminate\Http\Request $request)
    {
        $this->ensureCanPlan();

        $idsParam = $request->query('ids', '');
        $ids = collect(explode(',', (string)$idsParam))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        $query = ExamAssignment::join('time_slots', 'exam_assignments.time_slot_id', '=', 'time_slots.id')
            ->whereHas('studentExam.student', fn ($q) => $q->where('exam_period_id', $examPeriod->id))
            ->with([
                'studentExam.student',
                'studentExam.course.subject',
                'studentExam.examiner',
                'studentExam.assessor',
                'timeSlot',
                'examRoom',
                'prepRoom',
            ])
            ->orderBy('time_slots.date')
            ->orderBy('time_slots.slot_number')
            ->select('exam_assignments.*');

        if (!empty($ids)) {
            $query->whereIn('exam_assignments.id', $ids);
        }

        $assignments = $query->get();

        if ($assignments->isEmpty()) {
            return back()->with('error', 'Keine Prüfungen für den Export ausgewählt.');
        }

        $school = \App\Models\School::find($this->getSchoolId());

        // Vorbereitungsräume je Tag aus OralExamDayRoom (role=preparation)
        // Tag-spezifisch hat Vorrang vor globalem Default (1900-01-01)
        $prepRoomEntries = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->where('room_role', 'preparation')
            ->with('room')
            ->get();

        $globalPrepRooms = $prepRoomEntries
            ->filter(fn($e) => $e->date->format('Y-m-d') === '1900-01-01')
            ->map(fn($e) => $e->room !== null ? $e->room->room_number : '–')
            ->values()->all();

        // Map: 'Y-m-d' => [room_number, ...]  (nur tagesspezifische)
        $dayPrepRoomsMap = $prepRoomEntries
            ->filter(fn($e) => $e->date->format('Y-m-d') !== '1900-01-01')
            ->groupBy(fn($e) => $e->date->format('Y-m-d'))
            ->map(fn($g) => $g->map(fn($e) => $e->room !== null ? $e->room->room_number : '–')->values()->all());

        // ── Modul 4.7: inhaltliche Kopplung und Themenkatalog ──────────
        // Ist ein Prüfling gekoppelt, prüft die Lehrkraft die Gruppe gemeinsam.
        // Das gehört auf die Niederschrift, damit beim Prüfen nicht auffällt,
        // dass noch zwei weitere Prüflinge zum selben Termin gehören.
        $couplingIds = $assignments
            ->map(fn ($a) => $a->studentExam?->coupling_id)
            ->filter()
            ->unique()
            ->values();

        /** @var array<string, list<string>> $couplingPartners */
        $couplingPartners = [];
        if ($couplingIds->isNotEmpty()) {
            $couplingPartners = StudentExam::whereIn('coupling_id', $couplingIds->all())
                ->with('student')
                ->get()
                ->groupBy('coupling_id')
                ->map(fn ($group) => $group
                    // Externe Bewerber haben keinen Schüler-Datensatz; ohne den
                    // zweiten Zweig fielen sie hier stillschweigend aus der
                    // Partnerliste (der Filter warf das übrig bleibende "," weg).
                    ->map(fn ($se) => $se->student !== null
                        ? trim(($se->student->lastname ?? '') . ', ' . ($se->student->firstname ?? ''))
                        : $se->getStudentName())
                    ->filter(fn (string $n): bool => $n !== ',' && $n !== '')
                    ->sort()
                    ->values()
                    ->all())
                ->all();
        }

        // Themenbereiche des Kurses als Orientierung für Prüfer und Beisitz
        $courseIds = $assignments
            ->map(fn ($a) => $a->studentExam?->course_id)
            ->filter()
            ->unique()
            ->values();

        $topicsByCourse = \App\Models\ColloquiumTopic::whereIn('course_id', $courseIds->all())
            ->orderBy('semester')
            ->orderBy('slot')
            ->get()
            ->groupBy('course_id')
            ->map(fn ($group) => $group
                ->map(fn ($t) => ['semester' => $t->semester, 'slot' => $t->slot, 'title' => $t->title])
                ->all())
            ->all();

        $pdf = PDF::loadView('exports.kolloquium-niederschrift-pdf', [
            'assignments'      => $assignments,
            'examPeriod'       => $examPeriod,
            'school'           => $school,
            'globalPrepRooms'  => $globalPrepRooms,
            'dayPrepRoomsMap'  => $dayPrepRoomsMap,
            'couplingPartners' => $couplingPartners,
            'topicsByCourse'   => $topicsByCourse,
        ]);

        $pdf->setPaper('A3', 'landscape');

        $year = $examPeriod->year ?? date('Y');
        return $pdf->stream("Niederschriften_Kolloquium_{$year}.pdf");
    }

    /**
     * Aufgabenabgabe-Checkliste als PDF (pro Tag eine Seite)
     */
    public function exportAufgabenabgabePdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $days   = $this->buildColloquiumDays($examPeriod, $this->resolveExportDates($request));
        $school = \App\Models\School::find($this->getSchoolId());

        // Ferien- und Feiertage aus der zentralen DB laden (für vorherigen Schultag)
        $blockingEvents = \App\Models\GlobalCalendarEvent::whereIn('event_type', ['holiday', 'public_holiday'])->get();

        $pdf = Pdf::loadView('exports.aufgabenabgabe-pdf', [
            'examPeriod'     => $examPeriod,
            'days'           => $days,
            'school'         => $school,
            'blockingEvents' => $blockingEvents,
        ])->setPaper('a4', 'portrait');

        $year     = $examPeriod->year ?? date('Y');
        $filename = 'Aufgabenabgabe_' . $year . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Tagesübersicht: pro Tag geprüfte Fächer mit Schüleranzahl als PDF
     */
    public function exportDailySubjectsPdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $days   = $this->buildColloquiumDays($examPeriod, $this->resolveExportDates($request));
        $school = \App\Models\School::find($this->getSchoolId());

        // Pro Tag: Fächer + Schüleranzahl aggregieren
        $dailyOverview = [];

        foreach ($days as $day) {
            /** @var array<string, int> $subjectCounts */
            $subjectCounts = [];
            $totalStudents = 0;

            foreach ($day['timeSlots'] as $slot) {
                foreach ($slot->examAssignments as $assignment) {
                    // Ueber getSubjectName(), nicht ueber den Kurs: Ein
                    // externer Bewerber hat keinen, und die Tagesuebersicht
                    // zaehlte seine Kolloquien deshalb unter "–" statt unter
                    // ihrem Fach.
                    $se = $assignment->studentExam;
                    $subjectName = $se->getSubjectName() ?? '–';

                    if (!isset($subjectCounts[$subjectName])) {
                        $subjectCounts[$subjectName] = 0;
                    }
                    $subjectCounts[$subjectName]++;
                    $totalStudents++;
                }
            }

            ksort($subjectCounts);

            $dailyOverview[] = [
                'date'     => $day['date'],
                'week'     => $day['week'],
                'subjects' => $subjectCounts,
                'total'    => $totalStudents,
            ];
        }

        $pdf = Pdf::loadView('exports.daily-subjects-pdf', [
            'examPeriod'    => $examPeriod,
            'dailyOverview' => $dailyOverview,
            'school'        => $school,
        ])->setPaper('a4', 'portrait');

        $filename = 'Tagesübersicht_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Lädt die Prüfungsraum-Konfiguration (oral_exam_day_rooms) für einen
     * Prüfungszeitraum: globale Default-Räume (Rolle "exam", Sentinel-Datum
     * 1900-01-01) als Fallback sowie tagesspezifische Räume je Rolle
     * (exam/preparation/waiting), gruppiert nach Datum. Gemeinsame Grundlage
     * für index() und buildColloquiumDays().
     *
     * @return array{globalExamRooms: \Illuminate\Support\Collection, examRoomsByDate: \Illuminate\Support\Collection, prepRoomsByDate: \Illuminate\Support\Collection, waitRoomsByDate: \Illuminate\Support\Collection}
     */
    private function loadOralExamDayRooms(ExamPeriod $examPeriod): array
    {
        $dayRoomRows = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->with('room')
            ->get();

        // Globale Defaults [room_id => OralExamDayRoom]
        $globalDefaultRows = $dayRoomRows
            ->filter(fn($r) => $r->date->format('Y-m-d') === '1900-01-01');

        $globalExamRooms = $globalDefaultRows
            ->where('room_role', 'exam')
            ->map(fn($r) => $r->room)
            ->values();

        // Tagesspezifische Einträge
        $specificRows = $dayRoomRows
            ->filter(fn($r) => $r->date->format('Y-m-d') !== '1900-01-01');

        $examRoomsByDate = $specificRows
            ->where('room_role', 'exam')
            ->groupBy(fn($r) => $r->date->format('Y-m-d'))
            ->map(fn($g) => $g->map(fn($r) => $r->room)->values());

        $prepRoomsByDate = $specificRows
            ->where('room_role', 'preparation')
            ->groupBy(fn($r) => $r->date->format('Y-m-d'))
            ->map(fn($g) => $g->map(fn($r) => $r->room)->values());

        $waitRoomsByDate = $specificRows
            ->where('room_role', 'waiting')
            ->groupBy(fn($r) => $r->date->format('Y-m-d'))
            ->map(fn($g) => $g->map(fn($r) => $r->room)->values());

        return [
            'globalExamRooms' => $globalExamRooms,
            'examRoomsByDate' => $examRoomsByDate,
            'prepRoomsByDate' => $prepRoomsByDate,
            'waitRoomsByDate' => $waitRoomsByDate,
        ];
    }

    /**
     * Hilfsmethode: Tagesstruktur für den Kolloquiumsplan aufbauen.
     * Gibt ein Array von Tages-Arrays zurück, nur Tage mit Zeitslots.
     *
     * @param  array<int, string>|null  $allowedDates  Wenn gesetzt, werden nur Tage mit
     *         passendem 'Y-m-d'-Datum zurückgegeben (Tagesauswahl-Filter, Modul 4.2).
     *         null = keine Einschränkung (alle Tage).
     * @return array<int, array<string, mixed>>
     */
    private function buildColloquiumDays(ExamPeriod $examPeriod, ?array $allowedDates = null): array
    {
        // Raum-Konfiguration laden (analog zum index())
        [
            'globalExamRooms' => $globalExamRooms,
            'examRoomsByDate' => $examRoomsByDate,
            'prepRoomsByDate' => $prepRoomsByDate,
            'waitRoomsByDate' => $waitRoomsByDate,
        ] = $this->loadOralExamDayRooms($examPeriod);

        // Zeitslots mit Assignments – nur nicht-abgesagte Assignments laden
        $timeSlots = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->with([
                'examAssignments' => fn($q) => $q->where('status', '!=', 'cancelled'),
                'examAssignments.studentExam.student',
                'examAssignments.studentExam.course.subject',
                'examAssignments.studentExam.examiner',
                'examAssignments.studentExam.assessor',
                'examAssignments.examRoom',
            ])
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get()
            ->groupBy(fn($slot) => $slot->date->format('Y-m-d'));

        $days = [];

        foreach ([1, 2] as $week) {
            foreach ($examPeriod->getExamDates($week) as $date) {
                $dateStr   = $date->format('Y-m-d');
                $daySlots  = $timeSlots[$dateStr] ?? collect();

                if ($daySlots->isEmpty()) {
                    continue;
                }

                // Tagesauswahl-Filter (Modul 4.2): nur freigegebene Tage exportieren.
                if ($allowedDates !== null && ! in_array($dateStr, $allowedDates, true)) {
                    continue;
                }

                $days[] = [
                    'date'      => $date,
                    'dateStr'   => $dateStr,
                    'week'      => $week,
                    'examRooms' => $examRoomsByDate[$dateStr] ?? $globalExamRooms,
                    'prepRooms' => $prepRoomsByDate[$dateStr] ?? collect(),
                    'waitRooms' => $waitRoomsByDate[$dateStr] ?? collect(),
                    'timeSlots' => $daySlots,
                ];
            }
        }

        return $days;
    }

    /**
     * Liest die Tagesauswahl (Modul 4.2) aus dem Request.
     * Erwartet day_ids[] als Liste von 'Y-m-d'-Datums-Strings.
     *
     * @return array<int, string>|null  Liste erlaubter Datumsstrings oder null (= alle Tage),
     *         wenn kein gültiger Filter übergeben wurde.
     */
    private function resolveExportDates(Request $request): ?array
    {
        $raw = $request->query('day_ids');

        if (! is_array($raw)) {
            return null;
        }

        $dates = [];
        foreach ($raw as $value) {
            if (! is_string($value)) {
                continue;
            }
            // Nur valide Y-m-d-Werte zulassen.
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if ($d !== false && $d->format('Y-m-d') === $value) {
                $dates[] = $value;
            }
        }

        return $dates === [] ? null : $dates;
    }

    /**
     * Liste der exportierbaren Kolloquiumstage für das Tagesauswahl-Modal (Modul 4.2).
     *
     * Liefert nur Tage, an denen tatsächlich mindestens ein Kolloquium platziert ist.
     * Pro Tag wird zusätzlich die späteste Prüfungsendzeit ermittelt, damit das Modal
     * abgeschlossene Tage (Endzeit < jetzt) standardmäßig abwählen und den nächsten
     * noch offenen Tag vorauswählen kann – analog zum Zusatzprüfungs-Export.
     *
     * @return array<int, array{
     *     dateStr: string,
     *     label: string,
     *     lastEnd: string|null,
     *     isDone: bool,
     *     preselected: bool
     * }>
     */
    private function availableColloquiumDays(ExamPeriod $examPeriod): array
    {
        // TimeSlots mit nicht-abgesagten Zuweisungen laden, nach Tag gruppiert.
        $slotsByDate = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->with(['examAssignments' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($slot) => $slot->date->format('Y-m-d'));

        $now  = now();
        $days = [];

        foreach ([1, 2] as $week) {
            foreach ($examPeriod->getExamDates($week) as $date) {
                $dateStr  = $date->format('Y-m-d');
                $daySlots = $slotsByDate->get($dateStr);

                if ($daySlots === null) {
                    continue;
                }

                // Späteste Endzeit über alle Slots dieses Tages, die eine Prüfung tragen.
                $latestEnd = null;
                foreach ($daySlots as $slot) {
                    if ($slot->examAssignments->isEmpty()) {
                        continue;
                    }
                    // exam_end_time ist ein String (DB-Typ time), z. B. "10:30:00".
                    $end = \Carbon\Carbon::parse($dateStr . ' ' . $slot->exam_end_time);
                    if ($latestEnd === null || $end->gt($latestEnd)) {
                        $latestEnd = $end;
                    }
                }

                // Tage ganz ohne platzierte Prüfungen werden nicht angeboten.
                if ($latestEnd === null) {
                    continue;
                }

                $isDone = $latestEnd->lte($now);

                $days[] = [
                    'dateStr'     => $dateStr,
                    'label'       => $date->locale('de')->isoFormat('dd, D. MMMM YYYY'),
                    'lastEnd'     => $latestEnd->format('H:i'),
                    'isDone'      => $isDone,
                    // Vorauswahl: alle noch nicht abgeschlossenen Tage.
                    'preselected' => ! $isDone,
                ];
            }
        }

        return $days;
    }

    /**
     * Alle Prüfungszuweisungen zurücksetzen (nur ExamAssignments löschen, StudentExams bleiben)
     */
    public function resetAssignments(ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        // Über imPruefungszeitraum(): mit `whereHas('student', …)` blieben die
        // Termine externer Prüflinge beim Zurücksetzen stehen — der Plan war
        // danach halb geleert, und die verbliebenen Zuweisungen belegten
        // Räume und Zeitfenster, die der nächste Lauf für frei hielt.
        $betroffene = fn () => ExamAssignment::whereHas(
            'studentExam',
            fn ($q) => $q->where('exam_type', 'oral')->imPruefungszeitraum($examPeriod)
        );

        $count = $betroffene()->count();

        $betroffene()->delete();

        ActivityLogger::log(
            'assignments_reset',
            'ExamPeriod',
            $examPeriod->id,
            "Alle {$count} Prüfungszuweisungen zurückgesetzt"
        );

        return redirect()
            ->route('planning.index')
            ->with('success', "{$count} Prüfungszuweisungen wurden zurückgesetzt. Die Zeitslots können jetzt geändert werden.");
    }

    /**
     * Planung zurücksetzen
     */
    public function resetPlanning(ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        // Alle StudentExams löschen, die zu dieser Prüfungsperiode gehören
        \App\Models\StudentExam::where('exam_period_id', $examPeriod->id)->delete();

        return redirect()
            ->route('planning.index')
            ->with('success', 'Planung wurde zurückgesetzt.');
    }

    /**
     * Prüfungswoche einer bereits zugewiesenen Prüfung wechseln.
     *
     * Die Zuweisung wird gelöscht. preferred_week wird auf die andere Woche gesetzt.
     * Gleichzeitig wird die zweite Prüfung desselben Schülers (falls vorhanden und geplant)
     * ebenfalls entfernt und deren preferred_week auf die aktuelle Woche gesetzt,
     * damit keine zwei Prüfungen in einer Woche landen.
     */
    public function swapWeek(Request $request, ExamPeriod $examPeriod, ExamAssignment $assignment)
    {
        $this->ensureCanPlan();

        $assignment->load(['studentExam.student', 'timeSlot']);

        $studentExam  = $assignment->studentExam;
        $currentWeek  = $assignment->exam_week; // 1 oder 2
        $targetWeek   = $currentWeek === 1 ? 2 : 1;
        $studentId    = $studentExam->student_id;
        $isExternal   = $studentExam->isExternalApplicant();

        // Zweite Prüfung desselben Schülers (die andere oral-Prüfung) heraussuchen
        /** @var ExamAssignment|null $otherAssignment */
        $otherAssignment = null;

        if (!$isExternal && $studentId !== null) {
            $otherStudentExam = StudentExam::where('student_id', $studentId)
                ->where('exam_type', 'oral')
                ->where('id', '!=', $studentExam->id)
                ->first();

            if ($otherStudentExam !== null) {
                $otherAssignment = ExamAssignment::where('student_exam_id', $otherStudentExam->id)->first();
            }
        }

        // 1. Aktuelle Zuweisung löschen, preferred_week auf Zielwoche setzen
        $assignment->delete();
        $studentExam->preferred_week = $targetWeek;
        $studentExam->save();

        // 2. Zweite Prüfung: Zuweisung löschen, preferred_week auf aktuelle Woche setzen
        if ($otherAssignment !== null) {
            $otherStudentExamModel = $otherAssignment->studentExam;
            $otherAssignment->delete();
            $otherStudentExamModel->preferred_week = $currentWeek;
            $otherStudentExamModel->save();
        }

        ActivityLogger::log(
            'exam_week_swapped',
            'ExamAssignment',
            $assignment->id,
            "Prüfungswoche für {$studentExam->getStudentName()} von Woche {$currentWeek} auf Woche {$targetWeek} gewechselt"
        );

        return response()->json([
            'success'      => true,
            'message'      => "Prüfung in Woche {$targetWeek} verschoben",
            'target_week'  => $targetWeek,
            'other_moved'  => $otherAssignment !== null,
        ]);
    }

    /**
     * Warnungen als PDF exportieren
     */
    public function exportWarningsPdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->authorize('view', $examPeriod);

        $request->validate([
            'warnings' => 'required|string',
        ]);

        try {
            $warnings = json_decode($request->input('warnings'), true);
            
            if (!$warnings || !is_array($warnings)) {
                throw new \Exception('Ungültige Warnungen-Daten');
            }

            // Schule des Planers laden
            $schoolId = $this->getSchoolId();
            $school = \App\Models\School::find($schoolId);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.warnings-pdf', [
                'examPeriod' => $examPeriod,
                'school' => $school,
                'warnings' => $warnings,
                'generatedAt' => now()->format('d.m.Y H:i'),
            ]);

            $filename = 'Planungs-Warnungen_' . $examPeriod->name . '_' . date('Y-m-d_His') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            return back()->with('error', 'PDF-Export fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Vorbereitungsprotokoll als PDF exportieren.
     *
     * Pro Zeitschiene (= eindeutige prep_start_time eines Tages) × Vorbereitungsraum
     * wird eine Seite im Format der amtlichen Vorbereitung-Aufsichtsprotokolle erzeugt:
     * Prüflinge 1–20 vorausgefüllt, Aufsichtslehrkraft eingetragen.
     */
    public function exportVorbereitungsprotokollPdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $school  = \App\Models\School::find($this->getSchoolId());
        $colDays = $this->buildColloquiumDays($examPeriod, $this->resolveExportDates($request));

        // Vorbereitungsraum-Aufsichten laden – direkt am echten Prüfungsslot (Modul 4.4,
        // kein eigenes Zeitraster mehr)
        $prepSupervisionsBySlotAndRoom = \App\Models\Supervision::whereIn(
                'time_slot_id',
                \App\Models\TimeSlot::where('exam_period_id', $examPeriod->id)->pluck('id')
            )
            ->where('supervision_type', 'preparation')
            ->whereNotNull('room_id')
            ->with('teacher')
            ->get()
            ->groupBy(fn ($s) => $s->time_slot_id . '_' . $s->room_id);

        $pages = [];

        foreach ($colDays as $day) {
            $prepRooms = $day['prepRooms'];
            if ($prepRooms->isEmpty()) {
                continue;
            }

            // Alle Assignments des Tages flach sammeln
            $allEntries = collect();
            foreach ($day['timeSlots'] as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $allEntries->push(['slot' => $slot, 'assignment' => $asgn]);
                }
            }
            if ($allEntries->isEmpty()) {
                continue;
            }

            // Nach prep_start_time gruppieren (jede eindeutige Zeit = eine Zeitschiene)
            $byPrepTime = [];
            foreach ($allEntries as $entry) {
                $key = substr((string) $entry['slot']->prep_start_time, 0, 8);
                $byPrepTime[$key][] = $entry;
            }
            ksort($byPrepTime);

            $multiplePrepRooms = $prepRooms->count() > 1;
            $slotIndex = 1;

            foreach ($byPrepTime as $prepStartKey => $entries) {
                $prepStart = substr($prepStartKey, 0, 5);
                $prepEnd   = substr((string) $entries[0]['slot']->prep_end_time, 0, 5);

                // Distinkte Prüfungsslot-IDs dieser Zeitschiene (mehrere parallele
                // Prüfungsslots können denselben Vorbereitungsbeginn haben)
                $slotIdsInGroup = collect($entries)->pluck('slot.id')->unique();

                $collator = new \Collator('de_DE@collation=phonebook');

                foreach ($prepRooms as $prepRoom) {
                    // Aufsichtslehrkraft für diesen Vorbereitungsraum + diese Zeitschiene:
                    // direkte Zuordnung am echten Prüfungsslot (Modul 4.4)
                    $supervisor = null;
                    foreach ($slotIdsInGroup as $slotId) {
                        $sup = $prepSupervisionsBySlotAndRoom->get($slotId . '_' . $prepRoom->id, collect())->first();
                        if ($sup !== null && $sup->teacher !== null) {
                            $supervisor = $sup->teacher;
                            break;
                        }
                    }

                    if ($multiplePrepRooms) {
                        $roomEntries = collect($entries)->filter(fn ($e) =>
                            $e['assignment']->prep_room_id === null ||
                            $e['assignment']->prep_room_id === $prepRoom->id
                        )->values();
                    } else {
                        $roomEntries = collect($entries)->values();
                    }

                    // Alphabetisch nach Schüler-Nachname sortieren (DIN 5007-2)
                    $sorted = $roomEntries->sort(function ($a, $b) use ($collator) {
                        $nameA = $a['assignment']->studentExam->getStudentName();
                        $nameB = $b['assignment']->studentExam->getStudentName();
                        return $collator->compare($nameA, $nameB);
                    })->values();

                    $pages[] = [
                        'date'       => $day['date'],
                        'week'       => $day['week'],
                        'prepRoom'   => $prepRoom,
                        'prepStart'  => $prepStart,
                        'prepEnd'    => $prepEnd,
                        'entries'    => $sorted,
                        'supervisor' => $supervisor,
                        'slotNumber' => $slotIndex,
                    ];
                }
                $slotIndex++;
            }
        }

        $pdf = Pdf::loadView('exports.vorbereitungsprotokoll-pdf', [
            'examPeriod' => $examPeriod,
            'pages'      => $pages,
            'school'     => $school,
        ])->setPaper('a4', 'portrait');

        $filename = 'Vorbereitungsprotokoll_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Vorbereitungsraum-Listen als PDF exportieren.
     *
     * Die Vorbereitungsräume sind pro Tag in OralExamDayRoom (room_role = 'preparation')
     * festgelegt. Pro Tag × Vorbereitungsraum erscheint eine Seite mit allen Prüflingen
     * des Tages, chronologisch nach Vorbereitungszeit sortiert.
     *
     * Bei mehreren Vorbereitungsräumen an einem Tag wird per prep_room_id (ExamAssignment)
     * gefiltert; ist diese nicht gesetzt, erscheinen alle Prüflinge auf jeder Seite.
     */
    public function exportPrepRoomsPdf(Request $request, ExamPeriod $examPeriod)
    {
        $this->ensureCanPlan();

        $school  = \App\Models\School::find($this->getSchoolId());
        $colDays = $this->buildColloquiumDays($examPeriod, $this->resolveExportDates($request));

        $pages = [];

        foreach ($colDays as $day) {
            $prepRooms = $day['prepRooms'];

            if ($prepRooms->isEmpty()) {
                continue;
            }

            // Alle Assignments des Tages flach sammeln
            $allEntries = collect();
            foreach ($day['timeSlots'] as $slot) {
                foreach ($slot->examAssignments as $asgn) {
                    $allEntries->push(['slot' => $slot, 'assignment' => $asgn]);
                }
            }

            if ($allEntries->isEmpty()) {
                continue;
            }

            $multiplePrepRooms = $prepRooms->count() > 1;

            foreach ($prepRooms as $prepRoom) {
                if ($multiplePrepRooms) {
                    // Nur Assignments, die explizit diesem Vorbereitungsraum zugewiesen sind
                    // ODER keinem (dann auf allen Seiten zeigen)
                    $entries = $allEntries->filter(function ($e) use ($prepRoom) {
                        return $e['assignment']->prep_room_id === null
                            || $e['assignment']->prep_room_id === $prepRoom->id;
                    });
                } else {
                    // Einzelner Vorbereitungsraum → alle Prüflinge des Tages
                    $entries = $allEntries;
                }

                $sortedEntries = $entries
                    ->sortBy(fn($e) => $e['slot']->prep_start_time)
                    ->values();

                $pages[] = [
                    'date'      => $day['date'],
                    'week'      => $day['week'],
                    'prepRoom'  => $prepRoom,
                    'examRooms' => $day['examRooms'],
                    'waitRooms' => $day['waitRooms'],
                    'entries'   => $sortedEntries,
                ];
            }
        }

        $pdf = Pdf::loadView('exports.prep-room-schedule-pdf', [
            'examPeriod' => $examPeriod,
            'pages'      => $pages,
            'school'     => $school,
        ])->setPaper('a4', 'portrait');

        $filename = 'Vorbereitungsraum-Listen_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
