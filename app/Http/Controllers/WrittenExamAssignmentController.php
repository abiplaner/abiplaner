<?php

namespace App\Http\Controllers;

use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamSeatAssignment;
use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\Room;
use App\Models\SchoolSetting;

use App\Services\SeatAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WrittenExamAssignmentController extends Controller
{
    protected SeatAssignmentService $seatAssignmentService;

    public function __construct(SeatAssignmentService $seatAssignmentService)
    {
        $this->seatAssignmentService = $seatAssignmentService;
    }

    /**
     * Display a listing of written exam assignments.
     */
    public function index(Request $request, WrittenExamYearDate $writtenExamYearDate)
    {
        $writtenExamYearDate->load(['slot.subjects']);

        $assignments = WrittenExamAssignment::where('written_exam_year_date_id', $writtenExamYearDate->id)
            ->with(['course', 'room.seatingTemplateVariants.seatingTemplate.seats', 'seatAssignments'])
            ->get();

        $statistics = [
            'total_assignments' => $assignments->count(),
            'total_students_assigned' => $assignments->sum(function ($assignment) {
                return $assignment->seatAssignments->count();
            }),
            'total_capacity' => $assignments->sum(function ($assignment) {
                $template = $assignment->getSeatingTemplate();
                return $template ? $template->seats->where('seat_type', 'available')->count() : 0;
            })
        ];

        return view('written-exams.assignments.index', compact(
            'writtenExamYearDate',
            'assignments',
            'statistics'
        ));

    // create(), store(), edit(), update(), editAll() und updateAll() sind am
    // 30.08.2026 entfallen — mit ihren Routen und den drei Masken
    // create/edit/edit-all.blade.php.
    //
    // Alle drei Masken lasen ein $writtenExamDate, das diese Methoden nie
    // uebergeben haben (sie geben $writtenExamYearDate), und griffen darauf
    // ->name, ->start_time und ->level ab — drei Attribute, die es auf
    // WrittenExamYearDate nicht gibt; die stehen am Slot. Jede der drei
    // antwortete deshalb mit 500, und zwar seit jeher: Verlinkt war keine von
    // ihnen, erreichbar nur ueber die Adresse. Der einzige Weg dorthin fuehrte
    // ueber die Uebersicht, und die war bis heute selbst kaputt.
    //
    // Der lebende Weg zum Anlegen und Aendern von Kurs-Raum-Zuordnungen ist die
    // Tages-Zuordnung: WrittenExamDateController::dayAssign/storeDay, verlinkt
    // aus dates/index, dates/show und dem Aufsichtsplan. Sie legt die
    // Zuordnungen an und loescht sie wieder. Der raumweite Sitzplan
    // (showRoomPlan) ist die Hauptansicht seit 559b2bd.
    //
    // Geblieben sind index (Uebersicht, Ziel der Weiterleitung nach destroy),
    // show (leitet auf den raumweiten Sitzplan weiter), destroy und die
    // AJAX-Endpunkte des Sitzplans.

    }

    /**
     * Display room-wide seating plan.
     */
    public function showRoomPlan(Request $request, int|string $writtenExamYearDate, Room $room)
    {
        $writtenExamYearDate = WrittenExamYearDate::findOrFail($writtenExamYearDate);
        $isHv = $request->boolean('hv');

        // Alle YearDates desselben Tages und Jahres laden, damit eA + gA
        // desselben Fachs (verschiedene Slots) gemeinsam angezeigt werden.
        $allDateIdsForDay = WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)
            ->where('exam_year', $writtenExamYearDate->exam_year)
            ->pluck('id');

        $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $allDateIdsForDay)
            ->where('room_id', $room->id)
            ->where('is_hoerverstaendnis', $isHv)
            ->with(['course.writtenStudents', 'seatAssignments.student', 'seatAssignments.externalApplicant', 'seatAssignments.seat'])
            ->get();

        if ($assignments->isEmpty()) {
            return redirect()
                ->route('written-exams.dates.show', $writtenExamYearDate)
                ->with('error', 'Keine Zuordnungen für diesen Raum gefunden.');
        }

        $template = $room->seatingTemplateFor($isHv ? 'hoerverstaendnis' : 'standard');
        if (!$template) {
            return redirect()
                ->route('written-exams.dates.show', $writtenExamYearDate)
                ->with('error', 'Dem Raum ist keine Sitzplan-Vorlage zugewiesen.');
        }

        $allStudents = collect();
        $courseColors = [];
        $colors = ['blue', 'green', 'purple', 'orange', 'pink', 'indigo', 'red', 'yellow'];

        // NTA-Assignments für die beteiligten Kurse laden, damit deren Schüler-IDs
        // aus den Haupt-Assignments (student_ids=null) herausgefiltert werden können.
        $courseIds = $assignments->pluck('course_id')->unique()->values();
        $ntaAssignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $allDateIdsForDay)
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('student_ids')
            ->get();

        // Schüler-IDs, die in einem NTA-Assignment (anderem Raum) bereits versorgt sind
        $ntaStudentIdsByCourse = [];
        foreach ($ntaAssignments as $nta) {
            $cid = $nta->course_id;
            if (!isset($ntaStudentIdsByCourse[$cid])) {
                $ntaStudentIdsByCourse[$cid] = [];
            }
            $ntaStudentIdsByCourse[$cid] = array_merge($ntaStudentIdsByCourse[$cid], $nta->student_ids ?? []);
        }

        foreach ($assignments as $index => $assignment) {
            $courseColors[$assignment->course_id] = $colors[$index % count($colors)];

            $effectiveStudents = $assignment->getEffectiveStudents();

            // Bei Haupt-Assignments (student_ids=null): NTA-Schüler ausblenden,
            // da diese bereits einem separaten NTA-Raum zugeordnet sind.
            if (empty($assignment->student_ids)) {
                $ntaIds = $ntaStudentIdsByCourse[$assignment->course_id] ?? [];
                if (!empty($ntaIds)) {
                    $effectiveStudents = $effectiveStudents->filter(fn($s) => !in_array($s->id, $ntaIds));
                }
            }

            foreach ($effectiveStudents as $student) {
                $allStudents->push([
                    'id'               => $student->id,
                    'name'             => $student->full_name,
                    'external_id'      => $student->external_id,
                    'course_id'        => $assignment->course_id,
                    'course_code'      => $assignment->course->course_code,
                    'assignment_id'    => $assignment->id,
                    'has_compensation' => $student->hasDisabilityCompensation(),
                ]);
            }
        }

        // Externe Bewerber, die an diesem Tag eine schriftliche Prüfung
        // schreiben. Die Frage „darf der hier sitzen?" wird damit gar nicht
        // erst gestellt: zur Auswahl steht nur, wer an diesem Tag eine
        // Prüfung hat. Ein frei getippter Gastname bleibt daneben möglich,
        // aber er ist dann auch als Gast gemeint.
        $externeBewerber = ExternalApplicant::whereHas(
            'examSubjects.studentExam',
            fn ($q) => $q->whereIn('written_exam_year_date_id', $allDateIdsForDay)
        )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (ExternalApplicant $b) => [
                'id'               => null,
                'external_applicant_id' => $b->id,
                'name'             => $b->full_name,
                'external_id'      => null,
                'course_id'        => null,
                'course_code'      => '',
                'assignment_id'    => $assignments->first()->id,
                'has_compensation' => $b->hasDisabilityCompensation(),
                'nta_percent'      => $b->disability_compensation_percent,
                'is_extern'        => true,
            ])
            ->values()
            ->all();

        $allSeatAssignments = collect();
        foreach ($assignments as $assignment) {
            foreach ($assignment->seatAssignments as $sa) {
                $allSeatAssignments->push([
                    'student_id'    => $sa->student_id,
                    'guest_name'    => $sa->guest_name,
                    'external_applicant_id' => $sa->external_applicant_id,
                    'extern_name'   => $sa->istExterner() ? $sa->anzeigename() : null,
                    'seat_id'       => $sa->seat_id,
                    'assignment_id' => $assignment->id,
                    'course_id'     => $assignment->course_id
                ]);
            }
        }

        $grid = [];
        for ($row = 1; $row <= $template->rows; $row++) {
            for ($col = 1; $col <= $template->columns; $col++) {
                $seat = $template->seats->first(fn($s) => $s->row_number === $row && $s->column_number === $col);
                $seatAssignment = $seat
                    ? $allSeatAssignments->first(fn($sa) => $sa['seat_id'] === $seat->id)
                    : null;
                $grid[$row][$col] = ['seat' => $seat, 'assignment' => $seatAssignment];
            }
        }

        $availableSeats = $template->seats->where('seat_type', 'available')->count();
        $statistics = [
            'total_courses'    => $assignments->count(),
            'total_students'   => $allStudents->count(),
            'assigned_students'=> $allSeatAssignments->count(),
            'available_seats'  => $availableSeats,
            'remaining_capacity' => $availableSeats - $allSeatAssignments->count()
        ];

        $students = $allStudents->values()->all();
        $collator = new \Collator('de_DE@collation=phonebook');
        usort($students, fn($a, $b) => $collator->compare($a['name'], $b['name']));
        $seats = $template->seats;
        $seatAssignments = $allSeatAssignments->values()->all();
        $writtenExamDate = $writtenExamYearDate; // View-Alias

        $savedStrategy = SchoolSetting::get('seating_auto_assign.strategy', 'standard');

        return view('written-exams.assignments.room-plan', compact(
            'writtenExamYearDate', 'writtenExamDate',
            'room', 'assignments', 'template', 'students', 'seats',
            'seatAssignments', 'grid', 'statistics', 'courseColors', 'isHv',
            'savedStrategy', 'externeBewerber'
        ));
    }

    /**
     * Automatically assign all students from all courses in a room to seats.
     */
    public function autoAssignRoom(Request $request, $writtenExamYearDateId, $roomId)
    {
        $strategy = in_array($request->input('strategy'), ['standard', 'max_distance', 'compensation_separate'])
            ? $request->input('strategy') : 'standard';

        try {
            $writtenExamYearDate = WrittenExamYearDate::findOrFail($writtenExamYearDateId);
            $room = Room::findOrFail($roomId);

            $allDateIdsForDay = WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)
                ->where('exam_year', $writtenExamYearDate->exam_year)
                ->pluck('id');

            $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $allDateIdsForDay)
                ->where('room_id', $room->id)
                ->where('is_hoerverstaendnis', $request->boolean('hv'))
                ->with(['course.writtenStudents', 'seatAssignments'])
                ->get();

            if ($assignments->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Keine Zuordnungen für diesen Raum gefunden.'], 404);
            }

            $template = $room->seatingTemplateFor($request->boolean('hv') ? 'hoerverstaendnis' : 'standard');
            if (!$template) {
                return response()->json(['success' => false, 'message' => 'Dem Raum ist keine Sitzplan-Vorlage zugewiesen.'], 422);
            }

            // NTA-Assignments laden, damit NTA-Schüler nicht doppelt zugewiesen werden
            // (einmal aus dem Haupt-Assignment, einmal aus dem NTA-Assignment).
            $courseIds = $assignments->pluck('course_id')->unique()->values();
            $ntaAssignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $allDateIdsForDay)
                ->whereIn('course_id', $courseIds)
                ->whereNotNull('student_ids')
                ->whereNull('split_label')
                ->get();
            $ntaStudentIdsByCourse = [];
            foreach ($ntaAssignments as $nta) {
                $cid = (int) $nta->course_id;
                $ntaStudentIdsByCourse[$cid] = array_merge(
                    $ntaStudentIdsByCourse[$cid] ?? [],
                    array_map('intval', $nta->student_ids ?? [])
                );
            }

            $studentItems = [];
            foreach ($assignments as $assignment) {
                $assignedStudentIds = $assignment->seatAssignments()->pluck('student_id');
                $effectiveStudents = $assignment->getEffectiveStudents()->whereNotIn('id', $assignedStudentIds);

                // Aus Haupt-Assignments (student_ids=null, kein Split) NTA-Schüler
                // herausfiltern, da sie bereits im NTA-Assignment enthalten sind.
                if (empty($assignment->student_ids) && $assignment->split_label === null) {
                    $ntaIds = $ntaStudentIdsByCourse[(int) $assignment->course_id] ?? [];
                    if (!empty($ntaIds)) {
                        $effectiveStudents = $effectiveStudents->filter(fn($s) => !in_array($s->id, $ntaIds));
                    }
                }

                foreach ($effectiveStudents as $student) {
                    $studentItems[] = [
                        'student'          => $student,
                        'assignment_id'    => $assignment->id,
                        'course_id'        => $assignment->course_id,
                        'course_code'      => $assignment->course->course_code,
                        'has_compensation' => $student->hasDisabilityCompensation(),
                    ];
                }
            }

            if (empty($studentItems)) {
                return response()->json(['success' => false, 'message' => 'Alle Schüler sind bereits zugewiesen.'], 422);
            }

            DB::beginTransaction();
            $totalAssigned = $this->seatAssignmentService->autoAssignRoomWithStrategy($studentItems, $template, $strategy);
            DB::commit();

            SchoolSetting::set('seating_auto_assign.strategy', $strategy);

            return response()->json([
                'success' => true,
                'message' => $totalAssigned . ' Schüler aus ' . $assignments->count() . ' Kurs(en) erfolgreich zugewiesen.',
                'statistics' => ['assigned' => $totalAssigned]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete all course-room assignments for a room.
     */
    public function destroyRoomAssignments(Request $request, WrittenExamYearDate $writtenExamYearDate, Room $room)
    {
        try {
            $count = WrittenExamAssignment::where('written_exam_year_date_id', $writtenExamYearDate->id)
                ->where('room_id', $room->id)
                ->count();

            if ($count === 0) {
                return back()->with('error', 'Keine Zuordnungen für diesen Raum gefunden.');
            }

            WrittenExamAssignment::where('written_exam_year_date_id', $writtenExamYearDate->id)
                ->where('room_id', $room->id)
                ->delete();

            return back()->with('success', $count . ' Zuordnung(en) für Raum ' . $room->room_number . ' erfolgreich gelöscht.');

        } catch (\Exception $e) {
            return back()->with('error', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    /**
     * Clear all seat assignments for all courses in a room.
     */
    public function clearRoomAssignments(Request $request, int|string $writtenExamYearDate, Room $room)
    {
        $writtenExamYearDate = WrittenExamYearDate::findOrFail($writtenExamYearDate);
        try {
            $allDateIdsForDay = WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)
                ->where('exam_year', $writtenExamYearDate->exam_year)
                ->pluck('id');

            $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $allDateIdsForDay)
                ->where('room_id', $room->id)
                ->where('is_hoerverstaendnis', $request->boolean('hv'))
                ->get();

            if ($assignments->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Keine Zuordnungen für diesen Raum gefunden.'], 404);
            }

            $totalDeleted = 0;
            foreach ($assignments as $assignment) {
                $totalDeleted += $this->seatAssignmentService->clearAssignments($assignment);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $totalDeleted . ' Zuweisungen aus ' . $assignments->count() . ' Kurs(en) erfolgreich entfernt.',
                    'deleted_count' => $totalDeleted
                ]);
            }

            return back()->with('success', $totalDeleted . ' Zuweisungen aus ' . $assignments->count() . ' Kurs(en) erfolgreich entfernt.');

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified assignment → redirect to room-wide seating plan.
     */
    public function show(WrittenExamYearDate $writtenExamYearDate, WrittenExamAssignment $writtenExamAssignment)
    {
        return redirect()->route('written-exams.room-seating-plan', [$writtenExamYearDate, $writtenExamAssignment->room]);
    }

    /**
     * Remove a single seat assignment (AJAX) – used by the drag-and-drop seating plan.
     */
    public function removeAssignment(Request $request, int|string $writtenExamYearDate, WrittenExamAssignment $writtenExamAssignment)
    {
        try {
            $seatId = $request->input('seat_id');

            // Seat-ID ist innerhalb eines Raums eindeutig → reicht zum Löschen
            $seatAssignment = WrittenExamSeatAssignment::where('written_exam_assignment_id', $writtenExamAssignment->id)
                ->where('seat_id', $seatId)
                ->firstOrFail();

            $seatAssignment->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Assign a student to a seat (AJAX) – used by the drag-and-drop seating plan.
     */
    public function assignSeat(Request $request, int|string $writtenExamYearDate, WrittenExamAssignment $writtenExamAssignment)
    {
        try {
            // Ein Platz trägt genau eine der drei Angaben.
            $studentId = $request->input('student_id');            // eigener Schüler
            $externId  = $request->input('external_applicant_id'); // externer Bewerber
            $guestName = $request->input('guest_name');            // Gast ohne Datensatz
            $seatId    = $request->input('seat_id');

            if (!$studentId && !$externId && !$guestName) {
                return response()->json(['success' => false, 'message' => 'Kein Prüfling angegeben.'], 422);
            }

            if ($externId && !ExternalApplicant::whereKey($externId)->exists()) {
                return response()->json(['success' => false, 'message' => 'Unbekannter externer Bewerber.'], 422);
            }

            // Nur innerhalb der Assignments desselben Raums + Tages prüfen
            $roomId = $writtenExamAssignment->room_id;
            $yearDate = WrittenExamYearDate::findOrFail($writtenExamYearDate);
            $allDateIdsForDay = WrittenExamYearDate::whereDate('exam_date', $yearDate->exam_date)
                ->where('exam_year', $yearDate->exam_year)
                ->pluck('id');

            $assignmentIdsForRoom = WrittenExamAssignment::whereIn('written_exam_year_date_id', $allDateIdsForDay)
                ->where('room_id', $roomId)
                ->pluck('id');

            $alreadyOccupied = WrittenExamSeatAssignment::whereIn('written_exam_assignment_id', $assignmentIdsForRoom)
                ->where('seat_id', $seatId)
                ->exists();

            if ($alreadyOccupied) {
                return response()->json(['success' => false, 'message' => 'Dieser Platz ist bereits belegt.'], 422);
            }

            // Derselbe Bewerber darf im Raum nicht zweimal sitzen. Der
            // Unique-Index deckt nur ein einzelnes Assignment ab; ein Raum
            // trägt an einem Tag aber mehrere (eA und gA desselben Fachs).
            if ($externId) {
                $schonGesetzt = WrittenExamSeatAssignment::whereIn('written_exam_assignment_id', $assignmentIdsForRoom)
                    ->where('external_applicant_id', $externId)
                    ->exists();

                if ($schonGesetzt) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Dieser Bewerber sitzt an diesem Tag bereits in diesem Raum.',
                    ], 422);
                }
            }

            WrittenExamSeatAssignment::create([
                'written_exam_assignment_id' => $writtenExamAssignment->id,
                'student_id'  => $studentId ?: null,
                'external_applicant_id' => $externId ?: null,
                'guest_name'  => $externId ? null : ($guestName ?: null),
                'seat_id'     => $seatId,
                'assigned_at' => now(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified assignment from storage.
     */
    public function destroy(WrittenExamYearDate $writtenExamYearDate, WrittenExamAssignment $assignment)
    {
        try {
            $assignment->delete();
            return redirect()
                ->route('written-exams.assignments.index', $writtenExamYearDate)
                ->with('success', 'Zuordnung erfolgreich gelöscht.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Fehler: ' . $e->getMessage());
        }
    }

}
