<?php

namespace App\Http\Controllers;

use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Exports\WrittenExamDatesOverviewExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    protected \App\Services\SupervisionDistributionService $distributionService;

    public function __construct(\App\Services\SupervisionDistributionService $distributionService)
    {
        $this->distributionService = $distributionService;
    }

    /**
     * Baut teacherCategoryMaps [slotId|'none' => [teacher_id => category]] auf.
     * @return array<string|int, array<int, string>>
     */
    private function buildTeacherCategoryMaps(\App\Models\WrittenExamYearDate $examDate): array
    {
        $schoolId = auth()->user()->school_id;
        if (!$schoolId) return [];

        $timeSlots = \App\Models\WrittenExamSupervisionTimeSlot::orderBy('slot_number')->get();

        $maps = [];
        $maps['none'] = $this->distributionService->getTeacherCategoryMap($examDate, null, $schoolId);
        foreach ($timeSlots as $slot) {
            $maps[$slot->id] = $this->distributionService->getTeacherCategoryMap($examDate, $slot, $schoolId);
        }
        return $maps;
    }
    /**
     * Export seating plan as PDF.
     */
    public function seatingPlan(WrittenExamYearDate $writtenExamYearDate, WrittenExamAssignment $writtenExamAssignment)
    {
        // Mit Multi-Tenancy sind alle Planer im Tenant automatisch autorisiert

        try {
            $writtenExamAssignment->load([
                'course.teacher',
                'room.seatingTemplateVariants.seatingTemplate.seats',
                'seatAssignments.student', 'seatAssignments.externalApplicant',
                'seatAssignments.seat'
            ]);

            // Bereite Grid-Daten vor
            $grid = $this->prepareGridData($writtenExamAssignment);

            // Die Vorlage muss die View selbst bekommen. Sie griff bisher auf
            // `$assignment->layout` zu — eine Relation, die es nicht gibt. Der
            // Zugriff lief auf null, „Attempt to read property columns on
            // null", und der Export endete jedes Mal in der Fehlerweiche.
            $layout = $writtenExamAssignment->getSeatingTemplate();
            if (!$layout) {
                return redirect()
                    ->back()
                    ->with('error', 'Dem Raum ist keine Sitzplan-Vorlage zugewiesen.');
            }

            $pdf = Pdf::loadView('exports.seating-plan', [
                'writtenExamDate' => $writtenExamYearDate,
                'assignment' => $writtenExamAssignment,
                'layout' => $layout,
                'grid' => $grid,
                'seatNumbers' => $writtenExamAssignment->platznummern(),
            ]);

            $filename = sprintf(
                'Sitzplan_%s_%s_%s.pdf',
                str_replace(['/', '\\'], '-', $writtenExamYearDate->subject->code ?? 'Sammeltag'),
                $writtenExamAssignment->course->course_code,
                $writtenExamAssignment->room->room_number
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export des Sitzplans', [
                'assignment_id' => $writtenExamAssignment->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export seat cards as PDF.
     */
    public function seatCards(WrittenExamYearDate $writtenExamYearDate, WrittenExamAssignment $writtenExamAssignment)
    {
        // Mit Multi-Tenancy sind alle Planer im Tenant automatisch autorisiert

        try {
            $writtenExamAssignment->load([
                'course.teacher',
                'room',
                'seatAssignments.student', 'seatAssignments.externalApplicant',
                'seatAssignments.seat'
            ]);

            // Sortiere nach Platznummer. Bis zum 27.08.2026 stand hier
            // sortBy('seat.seat_number') — eine Spalte, die es nicht gibt.
            // Sortiert wurde also nach lauter null, die Karten kamen in
            // beliebiger Reihenfolge aus dem Drucker, und auf jeder stand
            // „Platz " ohne Zahl.
            $seatNumbers = $writtenExamAssignment->platznummern();
            $seatAssignments = $writtenExamAssignment->seatAssignments
                ->sortBy(fn ($sa) => $seatNumbers[$sa->seat_id] ?? PHP_INT_MAX);

            $pdf = Pdf::loadView('exports.seat-cards', [
                'writtenExamDate' => $writtenExamYearDate,
                'assignment' => $writtenExamAssignment,
                'seatAssignments' => $seatAssignments,
                'seatNumbers' => $seatNumbers,
            ])->setPaper('a4', 'landscape');

            $filename = sprintf(
                'Platzkarten_%s_%s_%s.pdf',
                str_replace(['/', '\\'], '-', $writtenExamYearDate->subject->code ?? 'Sammeltag'),
                $writtenExamAssignment->course->course_code,
                $writtenExamAssignment->room->room_number
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Platzkarten', [
                'assignment_id' => $writtenExamAssignment->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export supervision lists as PDF.
     */
    public function supervisionLists(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        try {
            // slot.subjects ist zentral und darf eager geladen werden.
            $writtenExamYearDate->load(['slot.subjects']);

            // assignments ist eine TENANT-Tabelle – NICHT über das zentrale
            // YearDate eager-loadbar (Cross-DB). Separat über das Tenant-Model
            // laden (erprobtes Muster, vgl. WrittenExamAssignmentController).
            $assignments = \App\Models\WrittenExamAssignment::where('written_exam_year_date_id', $writtenExamYearDate->id)
                ->with([
                    'room.seatingTemplateVariants.seatingTemplate.seats',
                    'course',
                    'seatAssignments.student', 'seatAssignments.externalApplicant',
                    'supervisions.teacher',
                ])
                ->get();

            $pdf = Pdf::loadView('exports.supervision-lists', [
                'writtenExamDate' => $writtenExamYearDate,
                'assignments' => $assignments
            ]);

            $filename = sprintf(
                'Aufsichtslisten_%s_%s.pdf',
                str_replace(['/', '\\'], '-', $writtenExamYearDate->subject->code ?? 'Sammeltag'),
                $writtenExamYearDate->date->format('Y-m-d')
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Aufsichtslisten', [
                'exam_date_id' => $writtenExamYearDate->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export student list as PDF.
     */
    public function studentList(WrittenExamYearDate $writtenExamYearDate, WrittenExamAssignment $writtenExamAssignment)
    {
        // Mit Multi-Tenancy sind alle Planer im Tenant automatisch autorisiert

        try {
            $writtenExamAssignment->load([
                'course.teacher',
                'room',
                'seatAssignments.student', 'seatAssignments.externalApplicant',
                'seatAssignments.seat'
            ]);

            $courseLevel = $writtenExamAssignment->course->level ?? 'grundlegend';

            // Sortiere Prüflinge nach Nachname (Umlaut-korrekt via Collator, DIN 5007-2: ä=ae).
            // Über nachname()/vorname() statt über $sa->student: auf dem Platz kann
            // auch ein externer Bewerber oder ein Gast sitzen, und der wäre hier
            // bisher als leerer Name ganz nach vorn sortiert worden — die Zeile
            // darunter hätte den Export dann mit einem Fehler abgebrochen.
            $seatNumbers = $writtenExamAssignment->platznummern();
            $collator = new \Collator('de_DE@collation=phonebook');
            $students = $writtenExamAssignment->seatAssignments
                ->sort(function ($a, $b) use ($collator) {
                    $l = $collator->compare($a->nachname(), $b->nachname());
                    return $l !== 0 ? $l : $collator->compare($a->vorname(), $b->vorname());
                })
                ->map(function ($seatAssignment) use ($writtenExamYearDate, $courseLevel, $seatNumbers) {
                    $prozent = $seatAssignment->nachteilsausgleichProzent();
                    return [
                        'student' => $seatAssignment->anzeigeObjekt(),
                        'compensation' => $prozent,
                        'seat_number' => $seatNumbers[$seatAssignment->seat_id] ?? null,
                        'end_time' => $writtenExamYearDate->getEndTimeForLevel($courseLevel, $prozent ?: null),
                    ];
                });

            $pdf = Pdf::loadView('exports.student-list', [
                'writtenExamDate' => $writtenExamYearDate,
                'assignment' => $writtenExamAssignment,
                // Wie beim Sitzplan: die View griff auf `$assignment->layout`
                // zu, eine Relation, die es nicht gibt.
                'layout' => $writtenExamAssignment->getSeatingTemplate(),
                'students' => $students
            ]);

            $filename = sprintf(
                'Schuelerliste_%s_%s_%s.pdf',
                str_replace(['/', '\\'], '-', $writtenExamYearDate->subject->code ?? 'Sammeltag'),
                $writtenExamAssignment->course->course_code,
                $writtenExamAssignment->room->room_number
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Schülerliste', [
                'assignment_id' => $writtenExamAssignment->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export room overview as PDF.
     */
    public function roomOverview(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('viewAny', \App\Models\WrittenExamAssignment::class);

        try {
            // slot.subjects ist zentral und darf eager geladen werden.
            $writtenExamYearDate->load(['slot.subjects']);

            // assignments ist eine TENANT-Tabelle – NICHT über das zentrale
            // YearDate eager-loadbar (Cross-DB). Separat über das Tenant-Model
            // laden (erprobtes Muster, vgl. WrittenExamAssignmentController).
            $assignments = \App\Models\WrittenExamAssignment::where('written_exam_year_date_id', $writtenExamYearDate->id)
                ->with([
                    'room.seatingTemplateVariants.seatingTemplate.seats',
                    'course',
                    'seatAssignments',
                ])
                ->get();

            // Bereite Raumübersicht vor
            $roomsData = $assignments->map(function (\App\Models\WrittenExamAssignment $assignment) {
                $template = $assignment->getSeatingTemplate();
                $availableSeats = $template ? $template->getTotalCapacity() : 0;
                $occupiedSeats = $assignment->seatAssignments->count();

                return [
                    'room' => $assignment->room,
                    'course' => $assignment->course,
                    'capacity' => $availableSeats,
                    'occupied' => $occupiedSeats,
                    'free' => $availableSeats - $occupiedSeats,
                    'utilization' => $availableSeats > 0 ? round(($occupiedSeats / $availableSeats) * 100, 1) : 0
                ];
            });

            $pdf = Pdf::loadView('exports.room-overview', [
                'writtenExamDate' => $writtenExamYearDate,
                'roomsData' => $roomsData
            ]);

            $filename = sprintf(
                'Raumuebersicht_%s_%s.pdf',
                str_replace(['/', '\\'], '-', $writtenExamYearDate->subject->code ?? 'Sammeltag'),
                $writtenExamYearDate->date->format('Y-m-d')
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Raumübersicht', [
                'exam_date_id' => $writtenExamYearDate->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export room-wide seating plan as PDF (all courses in a room).
     */
    public function roomSeatingPlan(Request $request, WrittenExamYearDate $writtenExamYearDate, \App\Models\Room $room)
    {
        // Mit Multi-Tenancy sind alle Planer im Tenant automatisch autorisiert

        try {
            $isHv = $request->boolean('hv');

            // Lade alle Assignments für diesen Raum (Standard- oder Hörverstehen-Gruppe,
            // beide können denselben Raum mit unterschiedlicher Sitzplan-Variante nutzen)
            $dateIds = \App\Models\WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');
            $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
                ->where('room_id', $room->id)
                ->where('is_hoerverstaendnis', $isHv)
                ->with([
                    'course.teacher',
                    'course.subject',
                    'room.seatingTemplateVariants.seatingTemplate.seats',
                    'seatAssignments.student', 'seatAssignments.externalApplicant',
                    'seatAssignments.seat'
                ])
                ->get();

            if ($assignments->isEmpty()) {
                return redirect()
                    ->back()
                    ->with('error', 'Keine Zuordnungen für diesen Raum gefunden.');
            }

            $layout = $room->seatingTemplateFor($isHv ? 'hoerverstaendnis' : 'standard');
            if (!$layout) {
                return redirect()
                    ->back()
                    ->with('error', 'Dem Raum ist keine Sitzplan-Vorlage zugewiesen.');
            }

            // Platznummern für verfügbare Sitze vorberechnen (zeilenweise, links nach rechts)
            $seatNumbers = $layout->platznummern();

            // Haupt-Assignments zuerst → konsistente Farbzuweisung (main=blau, NTA=grün/lila…)
            $assignments = $assignments->sortBy(fn($a) => empty($a->student_ids) ? 0 : 1)->values();

            // Sammle alle Sitzplatzzuweisungen mit Kursinformationen
            // NTA-Schüler aus Haupt-Assignments entfernen (analog zur HTML-Ansicht)
            $courseIdsForNta = $assignments->filter(fn($a) => empty($a->student_ids))->pluck('course_id')->unique();
            $ntaIdsByCourse  = $this->buildNtaStudentIdsByCourse($dateIds, $courseIdsForNta);
            foreach ($assignments as $assignment) {
                if (!empty($assignment->student_ids)) {
                    continue;
                }
                $ntaIds = $ntaIdsByCourse[$assignment->course_id] ?? [];
                if (!empty($ntaIds)) {
                    $assignment->setRelation(
                        'seatAssignments',
                        $assignment->seatAssignments->filter(fn($sa) => !in_array($sa->student_id, $ntaIds))->values()
                    );
                }
            }

            $allSeatAssignments = collect();
            $courseColors = [];
            $colors = ['blue', 'green', 'purple', 'orange', 'pink', 'indigo'];
            
            foreach ($assignments as $index => $assignment) {
                $courseColors[$assignment->course_id] = $colors[$index % count($colors)];
                foreach ($assignment->seatAssignments as $sa) {
                    $studentObj = $sa->anzeigeObjekt();
                    $allSeatAssignments->push([
                        'seat'    => $sa->seat,
                        'student' => $studentObj,
                        'course'  => $assignment->course,
                        'color'   => $colors[$index % count($colors)]
                    ]);
                }
            }

            // Bereite Grid-Daten vor
            $grid = $this->prepareRoomGridData($layout, $allSeatAssignments);

            // Orientation based on aspect ratio: more columns than rows → landscape
            $orientation = ($layout->columns > $layout->rows) ? 'landscape' : 'portrait';

            // 'front' = Ansicht von vorne (Pult oben), 'back' = Ansicht von hinten (Pult unten)
            $view = request('view', 'front');

            $pdf = Pdf::loadView('exports.room-seating-plan', [
                'writtenExamDate' => $writtenExamYearDate,
                'room' => $room,
                'layout' => $layout,
                'assignments' => $assignments,
                'grid' => $grid,
                'courseColors' => $courseColors,
                'seatNumbers' => $seatNumbers,
                'exportView' => $view,
            ])->setPaper('a4', $orientation);

            $hasHv = $assignments->some(fn($a) => $a->is_hoerverstaendnis);
            $viewSuffix = $view === 'back' ? '_hinten' : '_vorne';
            $filename = sprintf(
                'Sitzplan_Raum_%s_%s%s%s_%s.pdf',
                $room->room_number,
                str_replace(['/', '\\'], '-', $writtenExamYearDate->subject->code ?? 'Sammeltag'),
                $hasHv ? '_HV' : '',
                $viewSuffix,
                $writtenExamYearDate->date->format('Y-m-d')
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export des Raum-Sitzplans', [
                'room_id' => $room->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Bereitet Platzkarten-Daten für einen Raum vor (wiederverwendbar für Einzel- und Tagesexport).
     * @return array<string, mixed>|null
     */
    private function buildRoomSeatCardsPageData(WrittenExamYearDate $writtenExamYearDate, \App\Models\Room $room, bool $isHv = false): ?array
    {
        $dateIds = \App\Models\WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');
        $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
            ->where('room_id', $room->id)
            ->where('is_hoerverstaendnis', $isHv)
            ->with([
                'course.teacher',
                'course.subject',
                'room.seatingTemplateVariants.seatingTemplate.seats',
                'seatAssignments.student', 'seatAssignments.externalApplicant',
                'seatAssignments.seat',
            ])
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $layout = $room->seatingTemplateFor($isHv ? 'hoerverstaendnis' : 'standard');
        if ($layout === null) {
            return null;
        }

        // NTA-Schüler aus Haupt-Assignments entfernen (analog zur HTML-Ansicht)
        $courseIds = $assignments->filter(fn($a) => empty($a->student_ids))->pluck('course_id')->unique();
        $ntaIdsByCourse = $this->buildNtaStudentIdsByCourse($dateIds, $courseIds);
        foreach ($assignments as $assignment) {
            if (!empty($assignment->student_ids)) {
                continue; // NTA-Assignment: alle Schüler behalten
            }
            $ntaIds = $ntaIdsByCourse[$assignment->course_id] ?? [];
            if (!empty($ntaIds)) {
                $assignment->setRelation(
                    'seatAssignments',
                    $assignment->seatAssignments->filter(fn($sa) => !in_array($sa->student_id, $ntaIds))->values()
                );
            }
        }

        $seatNumbers = $layout->platznummern();

        $allSeatAssignments = collect();
        foreach ($assignments as $assignment) {
            $isHv = (bool) $assignment->is_hoerverstaendnis;
            foreach ($assignment->seatAssignments as $sa) {
                // Der Nachteilsausgleich kommt jetzt auch beim externen
                // Bewerber an — vorher zählte hier nur der eigene Schüler,
                // und der Externe bekam die unverlängerte Zeit aufgedruckt.
                $comp    = $sa->nachteilsausgleichProzent();
                $endTime = $isHv
                    ? $writtenExamYearDate->getEndTimeHv($comp ?: null)
                    : $writtenExamYearDate->getEndTime($comp ?: null);
                $allSeatAssignments->push([
                    'seat'         => $sa->seat,
                    'seat_number'  => $seatNumbers[$sa->seat_id] ?? '?',
                    'student'      => $sa->anzeigeObjekt(),
                    'guest_name'   => $sa->guest_name,
                    'is_extern'    => $sa->istExterner(),
                    'is_guest'     => $sa->istGast(),
                    'course'       => $assignment->course,
                    'end_time'     => $endTime,
                    'compensation' => $comp,
                    'is_hv'        => $isHv,
                ]);
            }
        }

        return [
            'room'            => $room,
            'writtenExamDate' => $writtenExamYearDate,
            'assignments'     => $assignments,
            'seatAssignments' => $allSeatAssignments->sortBy('seat_number'),
        ];
    }

    /**
     * Bereitet Schülerlisten-Daten für einen Raum vor (wiederverwendbar für Einzel- und Tagesexport).
     * @return array<string, mixed>|null
     */
    private function buildRoomStudentListPageData(WrittenExamYearDate $writtenExamYearDate, \App\Models\Room $room, bool $isHv = false): ?array
    {
        $dateIds = \App\Models\WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');
        $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
            ->where('room_id', $room->id)
            ->where('is_hoerverstaendnis', $isHv)
            ->with([
                'examDate',
                'course.teacher',
                'course.subject',
                'course.writtenStudents',
                'room.seatingTemplateVariants.seatingTemplate.seats',
                'seatAssignments.student', 'seatAssignments.externalApplicant',
                'seatAssignments.seat',
            ])
            ->get(); // NTA-Assignments dieses Raums bleiben als eigene Sektion erhalten

        if ($assignments->isEmpty()) {
            return null;
        }

        // NTA-Schüler aus Haupt-Assignments entfernen (analog zur HTML-Ansicht)
        $courseIds = $assignments->pluck('course_id')->unique();
        $ntaIdsByCourse = $this->buildNtaStudentIdsByCourse($dateIds, $courseIds);
        foreach ($assignments as $assignment) {
            if (!empty($assignment->student_ids)) {
                continue; // NTA-Assignment: eigene Schüler unverändert lassen
            }
            $ntaIds = $ntaIdsByCourse[$assignment->course_id] ?? [];
            if (!empty($ntaIds)) {
                $assignment->setRelation(
                    'seatAssignments',
                    $assignment->seatAssignments->filter(fn($sa) => !in_array($sa->student_id, $ntaIds))->values()
                );
            }
        }

        $layout = $room->seatingTemplateFor($isHv ? 'hoerverstaendnis' : 'standard');
        if ($layout === null) {
            return null;
        }

        $seatNumbers = $layout->platznummern();

        return [
            'room'            => $room,
            'writtenExamDate' => $writtenExamYearDate,
            'assignments'     => $assignments,
            'seatNumbers'     => $seatNumbers,
        ];
    }

    /**
     * Gibt eine Map course_id → student_ids[] aller NTA-Assignments für die
     * angegebenen Kurse am selben Prüfungstag zurück (raumunabhängig).
     * Entspricht der NTA-Filterlogik der HTML-Raumplan-Ansicht.
     * @param \Illuminate\Support\Collection<int, int> $dateIds
     * @param \Illuminate\Support\Collection<int, int> $courseIds
     * @return array<int, int[]>
     */
    private function buildNtaStudentIdsByCourse(\Illuminate\Support\Collection $dateIds, \Illuminate\Support\Collection $courseIds): array
    {
        if ($courseIds->isEmpty()) {
            return [];
        }
        $result = [];
        WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('student_ids')
            ->get()
            ->each(function ($nta) use (&$result) {
                $cid = $nta->course_id;
                if (!isset($result[$cid])) {
                    $result[$cid] = [];
                }
                $result[$cid] = array_values(array_unique(array_merge($result[$cid], $nta->student_ids ?? [])));
            });
        return $result;
    }

    /**
     * Export room-wide seat cards as PDF (all courses in a room).
     */
    public function roomSeatCards(Request $request, WrittenExamYearDate $writtenExamYearDate, \App\Models\Room $room)
    {
        try {
            $data = $this->buildRoomSeatCardsPageData($writtenExamYearDate, $room, $request->boolean('hv'));

            if ($data === null) {
                return redirect()->back()->with('error', 'Keine Zuordnungen für diesen Raum gefunden.');
            }

            $pdf = Pdf::loadView('exports.room-seat-cards', $data)->setPaper('a4', 'landscape');

            $filename = sprintf(
                'Platzkarten_Raum_%s_%s_%s.pdf',
                $room->room_number,
                $this->getExamDateSubjectString($writtenExamYearDate),
                $writtenExamYearDate->exam_date->format('Y-m-d')
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Raum-Platzkarten', ['room_id' => $room->id, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export room-wide student list as PDF (all courses in a room).
     */
    public function roomStudentList(Request $request, WrittenExamYearDate $writtenExamYearDate, \App\Models\Room $room)
    {
        try {
            $data = $this->buildRoomStudentListPageData($writtenExamYearDate, $room, $request->boolean('hv'));

            if ($data === null) {
                return redirect()->back()->with('error', 'Keine Zuordnungen für diesen Raum gefunden.');
            }

            $pdf = Pdf::loadView('exports.room-student-list', $data);

            $filename = sprintf(
                'Schuelerliste_Raum_%s_%s_%s.pdf',
                $room->room_number,
                $this->getExamDateSubjectString($writtenExamYearDate),
                $writtenExamYearDate->exam_date->format('Y-m-d')
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Raum-Schülerliste', ['room_id' => $room->id, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Helper: Prepare grid data for room-wide seating plan.
     */
    private function prepareRoomGridData($layout, $seatAssignments): array
    {
        $grid = [];
        
        for ($row = 1; $row <= $layout->rows; $row++) {
            for ($col = 1; $col <= $layout->columns; $col++) {
                $seat = $layout->seats->first(function ($s) use ($row, $col) {
                    return $s->row_number === $row && $s->column_number === $col;
                });
                
                $assignment = null;
                if ($seat) {
                    $assignment = $seatAssignments->first(function ($sa) use ($seat) {
                        return $sa['seat']->id === $seat->id;
                    });
                }
                
                $grid[$row][$col] = [
                    'seat' => $seat,
                    'assignment' => $assignment
                ];
            }
        }
        
        return $grid;
    }

    /**
     * Helper: Prepare grid data for seating plan.
     */
    private function prepareGridData(WrittenExamAssignment $assignment): array
    {
        $grid = [];
        $layout = $assignment->getSeatingTemplate();
        
        if (!$layout) {
            return $grid;
        }
        
        for ($row = 1; $row <= $layout->rows; $row++) {
            for ($col = 1; $col <= $layout->columns; $col++) {
                $seat = $layout->seats->first(function ($s) use ($row, $col) {
                    return $s->row_number === $row && $s->column_number === $col;
                });
                
                $seatAssignment = null;
                if ($seat) {
                    $seatAssignment = $assignment->seatAssignments->first(function ($sa) use ($seat) {
                        return $sa->seat_id === $seat->id;
                    });
                }
                
                $grid[$row][$col] = [
                    'seat' => $seat,
                    'assignment' => $seatAssignment
                ];
            }
        }
        
        return $grid;
    }

    /**
     * Übersicht aller Prüfungstermine als PDF.
     */
    public function datesOverviewPdf()
    {
        $this->authorize('viewAny', WrittenExamYearDate::class);

        try {
            $activePeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
            $examYear = $activePeriod !== null ? $activePeriod->year : (int) date('Y');

            [$rows, $assignmentsByDate, $studentCountsByDate] = $this->loadOverviewData($examYear);

            $pdf = Pdf::loadView('exports.written-exam-dates-overview', compact(
                'rows',
                'examYear'
            ))->setPaper('a4', 'landscape');

            return $pdf->download('Pruefungstermine_Uebersicht_' . $examYear . '_' . now()->format('Y-m-d') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Fehler beim PDF-Export der Prüfungstermine-Übersicht', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Lädt und verarbeitet alle Prüfungstermine für die Übersicht:
     * - Termine ohne Schüler und Kurszuweisung werden gefiltert
     * - Sammeltage werden pro Tag+Niveau zu einer Zeile zusammengefasst
     */
    public function loadOverviewData(int $examYear): array
    {
        $dates = WrittenExamYearDate::with(['slot.subjects'])
            ->where('exam_year', $examYear)
            ->orderBy('exam_date')
            ->orderBy('id')
            ->get();

        $dateIds = $dates->pluck('id');
        $assignmentsByDate = collect();
        $studentCountsByDate = [];

        if ($dateIds->isNotEmpty()) {
            $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
                ->with(['course.teacher', 'course.subject', 'room'])
                ->withCount('seatAssignments')
                ->get();
            $assignmentsByDate = $assignments->groupBy('written_exam_year_date_id');

            $rawCounts = \App\Models\StudentExam::whereIn('written_exam_year_date_id', $dateIds)
                ->where('exam_type', 'written')
                ->join('courses', 'courses.id', '=', 'student_exams.course_id')
                ->selectRaw('written_exam_year_date_id, courses.level, COUNT(*) as cnt')
                ->groupBy('written_exam_year_date_id', 'courses.level')
                ->get();
            foreach ($rawCounts as $row) {
                $dateId = $row->written_exam_year_date_id;
                if (!isset($studentCountsByDate[$dateId])) {
                    $studentCountsByDate[$dateId] = ['grundlegend' => 0, 'erhoeht' => 0];
                }
                // level kommt per selectRaw aus courses, nicht aus student_exams.
                $studentCountsByDate[$dateId][$row->getAttribute('level')] = $row->cnt;
            }
        }

        $rows = $this->buildOverviewRows($dates, $assignmentsByDate, $studentCountsByDate);

        return [$rows, $assignmentsByDate, $studentCountsByDate];
    }

    /**
     * Baut die Zeilen für die Terminübersicht:
     * - Filtert Termine ohne Schüler und Kurse
     * - Fasst Sammeltage (gleicher Tag + Niveau) zu einer Zeile zusammen
     */
    private function buildOverviewRows(
        \Illuminate\Support\Collection $dates,
        \Illuminate\Support\Collection $assignmentsByDate,
        array $studentCountsByDate
    ): \Illuminate\Support\Collection {
        $regularRows    = collect();
        $sammeltageGroups = [];

        foreach ($dates as $date) {
            $slot            = $date->slot;
            $dateAssignments = $assignmentsByDate->get($date->id, collect());
            $counts          = $studentCountsByDate[$date->id] ?? [];
            $cntG            = $counts['grundlegend'] ?? 0;
            $cntE            = $counts['erhoeht']     ?? 0;

            // Termine ohne Schüler und ohne Kurszuweisung überspringen
            if ($cntG + $cntE === 0 && $dateAssignments->isEmpty()) {
                continue;
            }

            $isSammeltag = str_starts_with($slot->name, 'Sammeltag');

            if ($isSammeltag) {
                $key = $date->exam_date . '_' . $slot->requirement_level;
                if (!isset($sammeltageGroups[$key])) {
                    $sammeltageGroups[$key] = [
                        'exam_date'    => $date->exam_date,
                        'level'        => $slot->requirement_level,
                        'start_time'   => $slot->start_time,
                        'display_name' => 'Sammeltag',
                        'subjects'     => collect(),
                        'assignments'  => collect(),
                        'cntG'         => 0,
                        'cntE'         => 0,
                        'duration'     => null,
                        'is_sammeltag' => true,
                    ];
                }
                // Fach-Namen aus den zugewiesenen Kursen holen (statt Slot-Name)
                foreach ($dateAssignments as $a) {
                    $subjectName = $a->course?->subject?->name;
                    if ($subjectName) {
                        $sammeltageGroups[$key]['subjects']->push($subjectName);
                    }
                }
                $sammeltageGroups[$key]['subjects'] = $sammeltageGroups[$key]['subjects']->unique()->values();
                $sammeltageGroups[$key]['cntG']        += $cntG;
                $sammeltageGroups[$key]['cntE']        += $cntE;
                $sammeltageGroups[$key]['assignments']  = $sammeltageGroups[$key]['assignments']->merge($dateAssignments);
                // Früheste Startzeit übernehmen
                if ($slot->start_time < $sammeltageGroups[$key]['start_time']) {
                    $sammeltageGroups[$key]['start_time'] = $slot->start_time;
                }
            } else {
                $regularRows->push([
                    'exam_date'    => $date->exam_date,
                    'level'        => $slot->requirement_level,
                    'start_time'   => $slot->start_time,
                    'display_name' => $slot->name,
                    'subjects'     => collect(),
                    'duration'     => $slot->getDurationMinutes(),
                    'assignments'  => $dateAssignments,
                    'cntG'         => $cntG,
                    'cntE'         => $cntE,
                    'is_sammeltag' => false,
                ]);
            }
        }

        return $regularRows
            ->merge(collect(array_values($sammeltageGroups)))
            ->sortBy('exam_date')
            ->values();
    }

    /**
     * Übersicht aller Prüfungstermine als Excel.
     */
    public function datesOverviewExcel()
    {
        $this->authorize('viewAny', WrittenExamYearDate::class);

        try {
            $filename = 'Pruefungstermine_Uebersicht_' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(
                new WrittenExamDatesOverviewExport(),
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Fehler beim Excel-Export der Prüfungstermine-Übersicht', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Kurslisten zur Endkontrolle: pro Prüfungstag eine Seite,
     * pro Kurs ein Abschnitt mit allen schriftlichen Prüfern und Schülern.
     * Alle Kurse mit schriftlichen Prüfungen werden angezeigt, auch ohne Raumzuweisung.
     */
    public function courseChecklistsPdf()
    {
        $this->authorize('viewAny', WrittenExamYearDate::class);

        try {
            // Aktives Jahr ermitteln
            $activePeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
            $examYear = $activePeriod !== null ? $activePeriod->year : (int) date('Y');

            // Alle YearDates mit Slot + Fächern laden (central DB)
            $yearDates = WrittenExamYearDate::with(['slot.subjects'])
                ->where('exam_year', $examYear)
                ->orderBy('exam_date')
                ->get();

            if ($yearDates->isEmpty()) {
                return redirect()->back()->with('error', 'Keine Prüfungstermine für das aktuelle Jahr gefunden.');
            }

            $yearDateIds = $yearDates->pluck('id');

            // Alle Assignments laden → Kurs→Raum und Kurs→YearDate (tenant DB)
            $allAssignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $yearDateIds)
                ->with(['room'])
                ->get();
            // courseId → assignment (für Raum-Lookup)
            $assignmentByCourse = $allAssignments->keyBy('course_id');
            // yearDateId → [courseIds] (Assignments)
            $assignedCourseIdsByYearDate = $allAssignments->groupBy('written_exam_year_date_id')
                ->map(fn($g) => $g->pluck('course_id')->all());

            // Alle Kurse mit schriftlichen StudentExams laden (tenant DB).
            // Bewusst nur Schüler: die Abfrage sammelt course_id ein, und
            // externe Bewerber haben keinen Kurs.
            $writtenExamCourseIds = \App\Models\StudentExam::where('exam_type', 'written')
                // Nur Q13 — die Q12 hat im selben Zeitraum schriftliche
                // Prüfungen in ihren künftigen Abiturfächern.
                ->whereHas('student', fn($q) => $q->where('exam_period_id', $activePeriod !== null ? $activePeriod->id : 0)->derStufe('13'))
                ->pluck('course_id')->unique()->filter();

            $courses = \App\Models\Course::whereIn('id', $writtenExamCourseIds)
                ->with(['subject', 'teacher', 'secondCorrector'])
                ->get()
                ->keyBy('id');

            // Schüler je Kurs laden
            $studentsByCourse = \App\Models\StudentExam::whereIn('course_id', $writtenExamCourseIds)
                ->where('exam_type', 'written')
                ->with(['student'])
                ->get()
                ->groupBy('course_id');

            // Fach → YearDate Mapping aufbauen (für Kurse ohne Assignment)
            $subjectToYearDate = [];
            $collectiveYearDatesByLevel = ['grundlegend' => null, 'erhoeht' => null];
            foreach ($yearDates as $yd) {
                if ($yd->slot->subjects->isEmpty()) {
                    $collectiveYearDatesByLevel[$yd->slot->requirement_level] = $yd;
                } else {
                    foreach ($yd->slot->subjects as $subject) {
                        $subjectToYearDate[$subject->id] = $yd;
                    }
                }
            }

            // Jeder Kurs einem YearDate zuordnen
            $courseToYearDate = [];
            foreach ($courses as $courseId => $course) {
                if ($assignmentByCourse->has($courseId)) {
                    $ydId = $assignmentByCourse[$courseId]->written_exam_year_date_id;
                    $courseToYearDate[$courseId] = $yearDates->firstWhere('id', $ydId);
                } elseif ($course->subject_id && isset($subjectToYearDate[$course->subject_id])) {
                    $courseToYearDate[$courseId] = $subjectToYearDate[$course->subject_id];
                } else {
                    $level = $course->level ?? 'grundlegend';
                    $courseToYearDate[$courseId] = $collectiveYearDatesByLevel[$level] ?? null;
                }
            }

            // Nach Prüfungstag gruppieren
            $coursesByDay = [];
            foreach ($courses as $courseId => $course) {
                $yd = $courseToYearDate[$courseId] ?? null;
                if (!$yd) {
                    continue;
                }
                $day = $yd->exam_date->format('Y-m-d');
                $coursesByDay[$day][] = ['course' => $course, 'yearDate' => $yd];
            }
            ksort($coursesByDay);

            // Ausgabe-Struktur aufbauen
            $dateGroups = [];
            foreach ($coursesByDay as $day => $dayCourses) {
                // Slot-Labels für diesen Tag
                $dayYearDateIds = collect($dayCourses)->pluck('yearDate.id')->unique();
                $slotLabels = $yearDates->whereIn('id', $dayYearDateIds->all())
                    ->unique('id')
                    ->sortBy('exam_date')
                    ->map(fn($yd) =>
                        $yd->slot->name . ' · ' .
                        \Carbon\Carbon::parse($yd->slot->start_time)->format('H:i') . '–' .
                        \Carbon\Carbon::parse($yd->slot->end_time)->format('H:i') . ' Uhr'
                    )->values()->all();

                $courseList = [];
                foreach ($dayCourses as $entry) {
                    $course = $entry['course'];
                    $yd     = $entry['yearDate'];
                    $assignment = $assignmentByCourse->get($course->id);

                    $examStudents = $studentsByCourse->get($course->id, collect());
                    if ($examStudents->isEmpty()) {
                        continue;
                    }

                    $students = $examStudents
                        ->sortBy(fn($se) => ($se->student !== null ? $se->student->lastname : '') . ' ' . ($se->student !== null ? $se->student->firstname : ''))
                        ->map(fn($se) => [
                            // @phpstan-ignore-next-line nullsafe.neverNull
                            'lastname'  => $se->student?->lastname ?? '–',
                            // @phpstan-ignore-next-line nullsafe.neverNull
                            'firstname' => $se->student?->firstname ?? '–',
                            'nta'       => $se->student !== null && $se->student->disability_compensation_percent > 0,
                        ])->values()->all();

                    $courseList[] = [
                        'course_code'      => $course->course_code,
                        // @phpstan-ignore-next-line nullsafe.neverNull
                        'subject_name'     => $course->subject?->name ?? '–',
                        'level_label'      => $course->level === 'grundlegend' ? 'gA' : 'eA',
                        // @phpstan-ignore-next-line nullsafe.neverNull
                        'examiner'         => $course->teacher?->full_name ?? '–',
                        // @phpstan-ignore-next-line nullsafe.neverNull
                        'second_corrector' => $course->secondCorrector?->full_name ?? '–',
                        'room'             => ($assignment !== null && $assignment->room !== null) ? $assignment->room->room_number : '–',
                        'start_time'       => \Carbon\Carbon::parse($yd->slot->start_time)->format('H:i'),
                        'end_time'         => \Carbon\Carbon::parse($yd->slot->end_time)->format('H:i'),
                        'students'         => $students,
                    ];
                }

                usort($courseList, fn($a, $b) => strcmp($a['course_code'], $b['course_code']));

                if (!empty($courseList)) {
                    $dateGroups[] = [
                        'date'    => $day,
                        'slots'   => $slotLabels,
                        'courses' => $courseList,
                    ];
                }
            }

            $pdf = Pdf::loadView('exports.course-checklists', compact('dateGroups', 'examYear'))
                ->setPaper('a4', 'portrait');

            return $pdf->download('Kurslisten_Endkontrolle_' . $examYear . '_' . now()->format('Y-m-d') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Fehler beim PDF-Export der Kurslisten', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export supervision plan matrix as PDF.
     */
    public function supervisionPlanPdf(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        try {
            $dateIds = \App\Models\WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');

            $assignments = WrittenExamAssignment::with([
                    'room',
                    'course.teacher',
                    'course.subject',
                    'supervisions.teacher',
                    'supervisions.writtenExamSupervisionTimeSlot',
                ])
                ->whereIn('written_exam_year_date_id', $dateIds)
                ->get();

            $corridorSupervisions = \App\Models\Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
                ->whereIn('written_exam_year_date_id', $dateIds)
                ->where('supervision_type', 'corridor')
                ->whereNull('written_exam_assignment_id')
                ->whereNull('central_supervision_type_id')
                ->get();

            $reserveSupervisions = \App\Models\Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
                ->whereIn('written_exam_year_date_id', $dateIds)
                ->where('supervision_type', 'reserve')
                ->whereNull('written_exam_assignment_id')
                ->get();

            $centralTypes = \App\Models\WrittenExamCentralSupervisionType::whereIn('written_exam_year_date_id', $dateIds)
                ->orderBy('sort_order')->orderBy('name')->get();

            $centralSupervisions = \App\Models\Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
                ->whereIn('written_exam_year_date_id', $dateIds)
                ->where('supervision_type', 'extra')
                ->whereNull('written_exam_assignment_id')
                ->whereNotNull('central_supervision_type_id')
                ->get()
                ->groupBy('central_supervision_type_id');

            $timeSlots = \App\Models\WrittenExamSupervisionTimeSlot::with('grid')->orderBy('slot_number')->get();

            $grids = \App\Models\WrittenExamSupervisionGrid::orderByDesc('is_default')->orderBy('name')->get();
            $defaultGridId = ($grids->firstWhere('is_default', true) ?? $grids->first())?->id;

            $assignmentsByRoom = $assignments->groupBy('room_id');
            $hasForeignLanguage = $assignments->some(fn($a) => $a->is_hoerverstaendnis);
            $teacherCategoryMaps = $this->buildTeacherCategoryMaps($writtenExamYearDate);

            $rows = $timeSlots->isNotEmpty() ? $timeSlots : collect([null]);

            $subjectString = $this->getExamDateSubjectString($writtenExamYearDate);

            $pdf = Pdf::loadView('exports.supervision-plan-matrix', [
                'writtenExamDate'      => $writtenExamYearDate,
                'corridorSupervisions' => $corridorSupervisions,
                'reserveSupervisions'  => $reserveSupervisions,
                'centralTypes'         => $centralTypes,
                'centralSupervisions'  => $centralSupervisions,
                'subjectString'        => $subjectString,
                'grids'                => $grids,
                'defaultGridId'        => $defaultGridId,
                'timeSlots'            => $timeSlots,
                'assignmentsByRoom'    => $assignmentsByRoom,
                'hasForeignLanguage'   => $hasForeignLanguage,
                'teacherCategoryMaps'  => $teacherCategoryMaps,
            ])->setPaper('a4', 'landscape');

            $filename = sprintf(
                'Aufsichtsplan_%s_%s.pdf',
                $subjectString,
                $writtenExamYearDate->exam_date->format('Y-m-d')
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export des Aufsichtsplans', [
                'exam_date_id' => $writtenExamYearDate->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Export supervision plan matrix as Excel.
     */
    public function supervisionPlanExcel(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        try {
            $subjectString = $this->getExamDateSubjectString($writtenExamYearDate);

            $filename = sprintf(
                'Aufsichtsplan_%s_%s.xlsx',
                $subjectString,
                $writtenExamYearDate->exam_date->format('Y-m-d')
            );

            $teacherCategoryMaps = $this->buildTeacherCategoryMaps($writtenExamYearDate);
            $hasForeignLanguage  = \App\Models\WrittenExamAssignment::whereIn(
                    'written_exam_year_date_id',
                    \App\Models\WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id')
                )->where('is_hoerverstaendnis', true)->exists();

            return Excel::download(
                new \App\Exports\WrittenExamSupervisionPlanExport(
                    $writtenExamYearDate,
                    $subjectString,
                    $teacherCategoryMaps,
                    $hasForeignLanguage
                ),
                $filename
            );

        } catch (\Exception $e) {
            Log::error('Fehler beim Excel-Export des Aufsichtsplans', [
                'exam_date_id' => $writtenExamYearDate->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    private function getExamDateSubjectString(WrittenExamYearDate $writtenExamYearDate): string
    {
        $dateIds = \App\Models\WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');
        
        $subjects = \App\Models\Course::whereIn('id', function($query) use ($dateIds) {
            $query->select('course_id')
                  ->from('written_exam_assignments')
                  ->whereIn('written_exam_year_date_id', $dateIds);
        })
        ->with('subject')
        ->get()
        ->pluck('subject.name')
        ->unique()
        ->filter()
        ->sort()
        ->take(3); // Nimmt maximal 3 Fächer für Dateinamen
        
        return $subjects->isEmpty() ? 'Sammeltermin' : str_replace(['/', '\\'], '-', $subjects->join('_'));
    }

    /**
     * Baut die dayBlocks-Struktur für den Gesamtaufsichtsplan-Export.
     * Enthält pro Kalendertag alle nötigen Daten (Assignments, Aufsichten, Kategorie-Maps).
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildSupervisionOverviewDayBlocks(): array
    {
        // Perioden-Audit (Modul 1.1): nur Termine des gewählten Jahres —
        // sonst mischte der Gesamtaufsichtsplan alle Jahrgänge.
        $activePeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
        $examYear     = $activePeriod !== null ? $activePeriod->year : (int) date('Y');

        $examDates = \App\Models\WrittenExamYearDate::with(['slot.subjects'])
            ->where('exam_year', $examYear)
            ->orderBy('exam_date')
            ->get();

        $examDateIds = $examDates->pluck('id');

        $allAssignments = \App\Models\WrittenExamAssignment::with([
                'room',
                'course.teacher',
                'course.subject',
                'supervisions.teacher',
                'supervisions.writtenExamSupervisionTimeSlot',
                'seatAssignments.student', 'seatAssignments.externalApplicant',
            ])
            ->whereIn('written_exam_year_date_id', $examDateIds)
            ->get()
            ->groupBy('written_exam_year_date_id');

        $allCorridorSupervisions = \App\Models\Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
            ->whereIn('written_exam_year_date_id', $examDateIds)
            ->where('supervision_type', 'corridor')
            ->whereNull('written_exam_assignment_id')
            ->whereNull('central_supervision_type_id')
            ->get()
            ->groupBy('written_exam_year_date_id');

        $allReserveSupervisions = \App\Models\Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
            ->whereIn('written_exam_year_date_id', $examDateIds)
            ->where('supervision_type', 'reserve')
            ->whereNull('written_exam_assignment_id')
            ->get()
            ->groupBy('written_exam_year_date_id');

        $allCentralTypes = \App\Models\WrittenExamCentralSupervisionType::whereIn('written_exam_year_date_id', $examDateIds)
            ->orderBy('sort_order')->get()
            ->groupBy('written_exam_year_date_id');

        $allCentralSupervisions = \App\Models\Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
            ->whereIn('written_exam_year_date_id', $examDateIds)
            ->where('supervision_type', 'extra')
            ->whereNull('written_exam_assignment_id')
            ->whereNotNull('central_supervision_type_id')
            ->get()
            ->groupBy('central_supervision_type_id');

        // Nach Kalendertag gruppieren
        $datesByDay = $examDates->groupBy(fn($d) => $d->exam_date->format('Y-m-d'));

        $dayBlocks = [];

        foreach ($datesByDay as $dayString => $dayDates) {
            $dayDateIds = $dayDates->pluck('id');

            // Assignments und Aufsichten für alle YearDates dieses Tags zusammenführen
            $assignments = collect();
            $corridorSupervisions = collect();
            $reserveSupervisions  = collect();
            $centralTypes         = collect();
            $centralSupervisionsMerged = collect();

            foreach ($dayDateIds as $dateId) {
                $assignments = $assignments->merge($allAssignments->get($dateId, collect()));
                $corridorSupervisions = $corridorSupervisions->merge($allCorridorSupervisions->get($dateId, collect()));
                $reserveSupervisions  = $reserveSupervisions->merge($allReserveSupervisions->get($dateId, collect()));
                $typesForDate = $allCentralTypes->get($dateId, collect());
                $centralTypes = $centralTypes->merge($typesForDate);
            }
            // Central supervisions nach type_id gruppiert
            $centralSupervisionsMerged = $allCentralSupervisions->filter(fn($group, $typeId) =>
                $centralTypes->pluck('id')->contains($typeId)
            );

            $hasForeignLanguage = $assignments->some(fn($a) => $a->is_hoerverstaendnis);

            // Fächerstring
            $subjects = $dayDates->flatMap(fn($d) => $d->slot !== null ? $d->slot->subjects : collect())->unique('id');
            $subjectStr = $subjects->count() > 1
                ? 'Sammeltermin'
                : ($subjects->first() !== null ? $subjects->first()->name : '');

            // Zeitbereich
            $firstDate   = $dayDates->first();
            $startTimes  = $dayDates->map(fn($d) => \Carbon\Carbon::parse($d->slot !== null ? $d->slot->start_time : null)->format('H:i'))->unique()->sort();
            $endTimes    = $dayDates->map(fn($d) => \Carbon\Carbon::parse($d->slot !== null ? $d->slot->end_time : null)->format('H:i'))->unique()->sort();
            $timeRange   = $startTimes->first() ? ($startTimes->first() . ' – ' . $endTimes->last() . ' Uhr') : '';

            // Kategorie-Maps pro Slot
            $teacherCategoryMaps = $firstDate ? $this->buildTeacherCategoryMaps($firstDate) : [];

            $dayBlocks[$dayString] = [
                'assignments'          => $assignments,
                'corridorSupervisions' => $corridorSupervisions,
                'reserveSupervisions'  => $reserveSupervisions,
                'centralTypes'         => $centralTypes,
                'centralSupervisions'  => $centralSupervisionsMerged,
                'hasForeignLanguage'   => $hasForeignLanguage,
                'teacherCategoryMaps'  => $teacherCategoryMaps,
                'subjectString'        => $subjectStr,
                'timeRange'            => $timeRange,
            ];
        }

        return $dayBlocks;
    }

    /**
     * Gesamtaufsichtsplan aller Prüfungstage als PDF.
     * Pro Tag eine Seite, Matrixdarstellung wie im Word-Dokument.
     */
    public function supervisionsOverviewPdf()
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        try {
            $dayBlocks   = $this->buildSupervisionOverviewDayBlocks();
            $timeSlots   = \App\Models\WrittenExamSupervisionTimeSlot::with('grid')->orderBy('slot_number')->get();
            $grids       = \App\Models\WrittenExamSupervisionGrid::orderByDesc('is_default')->orderBy('name')->get();
            $defaultGridId = ($grids->firstWhere('is_default', true) ?? $grids->first())?->id;

            $pdf = Pdf::loadView('exports.supervisions-overview', compact(
                'dayBlocks', 'timeSlots', 'grids', 'defaultGridId'
            ))->setPaper('a4', 'landscape');

            $filename = 'Aufsichtsplan_Gesamt_' . now()->format('Y-m-d') . '.pdf';
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim PDF-Export des Gesamtaufsichtsplans', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Aufsichtsliste alphabetisch nach Lehrkräften als PDF.
     * Pro Lehrkraft eine Seite mit allen ihren Aufsichten und Unterschriftsfeld.
     */
    public function teacherSupervisionListPdf()
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        try {
            // Perioden-Audit (Modul 1.1): nur Termine des gewählten Jahres
            $activePeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
            $examYear     = $activePeriod !== null ? $activePeriod->year : (int) date('Y');

            $examDates   = \App\Models\WrittenExamYearDate::with(['slot.subjects'])
                ->where('exam_year', $examYear)
                ->orderBy('exam_date')
                ->get();
            $examDateIds = $examDates->pluck('id');
            $dateById    = $examDates->keyBy('id');

            // Alle Aufsichten mit Relations
            $supervisions = \App\Models\Supervision::with([
                    'teacher',
                    'writtenExamSupervisionTimeSlot',
                    'writtenExamAssignment.room',
                    'writtenExamAssignment.course.subject',
                    'centralSupervisionType',
                ])
                ->whereIn('written_exam_year_date_id', $examDateIds)
                ->get();

            // Alle Central-Types vorladen (für Extra-Aufsichten)
            $centralTypeById = \App\Models\WrittenExamCentralSupervisionType::whereIn('written_exam_year_date_id', $examDateIds)
                ->get()->keyBy('id');

            // Pro Supervision eine normalisierte Zeile aufbauen
            $rows = [];
            foreach ($supervisions as $s) {
                /** @var \App\Models\Supervision $s */
                $yearDate  = $dateById->get($s->written_exam_year_date_id);
                /** @var \App\Models\WrittenExamSupervisionTimeSlot|null $slot */
                $slot      = $s->writtenExamSupervisionTimeSlot;
                $teacher   = $s->teacher;
                if ($teacher === null) continue;

                // Zeitangabe
                if ($slot !== null) {
                    $timeRange = substr($slot->start_time, 0, 5) . '–' . substr($slot->end_time, 0, 5) . ' Uhr';
                } elseif ($yearDate !== null && $yearDate->slot !== null) {
                    $timeRange = \Carbon\Carbon::parse($yearDate->slot->start_time)->format('H:i') . ' Uhr';
                } else {
                    $timeRange = '–';
                }

                // Position + Fach bestimmen
                $assignment = $s->writtenExamAssignment;
                if ($assignment !== null && $s->supervision_type !== 'corridor'
                    && $s->supervision_type !== 'reserve' && $s->supervision_type !== 'extra') {
                    $posType     = 'room';
                    $posLabel    = $assignment->room !== null ? ($assignment->room->room_number ?? 'Raum') : 'Raum';
                    $courseInfo  = $assignment->course !== null
                        ? (($assignment->course->course_code ?? '') . ($assignment->course->teacher !== null ? ' (' . $assignment->course->teacher->shortcode . ')' : ''))
                        : null;
                    $subjectInfo = $assignment->course !== null && $assignment->course->subject !== null
                        ? $assignment->course->subject->name
                        : '';
                } elseif ($s->supervision_type === 'corridor') {
                    $posType     = 'corridor';
                    $posLabel    = 'Gangaufsicht';
                    $courseInfo  = null;
                    $subjectInfo = $yearDate !== null ? $yearDate->slot->subjects->pluck('name')->implode(', ') : '';
                } elseif ($s->supervision_type === 'reserve') {
                    $posType     = 'reserve';
                    $posLabel    = 'Reserve (LZ)';
                    $courseInfo  = null;
                    $subjectInfo = $yearDate !== null ? $yearDate->slot->subjects->pluck('name')->implode(', ') : '';
                } else {
                    /** @var \App\Models\WrittenExamCentralSupervisionType|null $centralType */
                    $centralType = $s->centralSupervisionType ?? ($s->central_supervision_type_id !== null ? $centralTypeById->get($s->central_supervision_type_id) : null);
                    $posType     = 'extra';
                    $posLabel    = $centralType !== null ? $centralType->name : 'Zusatz';
                    $courseInfo  = null;
                    $subjectInfo = $yearDate !== null ? $yearDate->slot->subjects->pluck('name')->implode(', ') : '';
                }

                $rows[] = [
                    'teacher_id'     => $s->teacher_id,
                    'teacher'        => $teacher,
                    'date'           => $yearDate !== null ? $yearDate->exam_date : now()->toDate(),
                    'sort_date'      => $yearDate !== null ? $yearDate->exam_date->format('Y-m-d') : '9999-12-31',
                    'time_range'     => $timeRange,
                    'slot_sort'      => $slot !== null ? $slot->slot_number : 0,
                    'position_type'  => $posType,
                    'position_label' => $posLabel,
                    'course_info'    => $courseInfo,
                    'subject_info'   => $subjectInfo,
                ];
            }

            // Nach Lehrer gruppieren
            $byTeacher = collect($rows)->groupBy('teacher_id');

            // Alle beteiligten Lehrer alphabetisch laden
            $teacherIds = $byTeacher->keys()->filter()->values();
            $teachers   = \App\Models\Teacher::whereIn('id', $teacherIds)
                ->orderBy('lastname')->orderBy('firstname')
                ->get()->keyBy('id');

            $teacherRows = [];
            foreach ($teachers as $teacher) {
                $sups = $byTeacher->get($teacher->id, collect())
                    ->sortBy('sort_date')->sortBy('slot_sort')
                    ->values();
                $teacherRows[] = ['teacher' => $teacher, 'supervisions' => $sups];
            }

            // Schulname
            $school     = \App\Models\School::find(auth()->user()->school_id);
            $schoolName = $school !== null ? $school->name : '';

            $pdf = Pdf::loadView('exports.teacher-supervision-list', compact(
                'teacherRows', 'schoolName'
            ))->setPaper('a4', 'portrait');

            $filename = 'Aufsichtsliste_Lehrkraefte_' . now()->format('Y-m-d') . '.pdf';
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim PDF-Export der Lehrkräfte-Aufsichtsliste', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Unterschriftsbögen: Pro Prüfungstag und Raum eine Seite.
     * Die Aufsichten können abzeichnen und Besonderheiten notieren.
     */
    public function supervisionSignatureSheetsPdf()
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        try {
            $examDates = \App\Models\WrittenExamYearDate::with(['slot.subjects'])
                ->orderBy('exam_date')
                ->get();

            if ($examDates->isEmpty()) {
                return redirect()->back()->with('error', 'Keine Prüfungstermine vorhanden.');
            }

            $examDateIds = $examDates->pluck('id');

            // Alle Assignments mit Aufsichten laden
            $allAssignments = \App\Models\WrittenExamAssignment::with([
                    'room',
                    'course.subject',
                    'course.teacher',
                    'supervisions.teacher',
                    'supervisions.writtenExamSupervisionTimeSlot',
                    'seatAssignments',
                ])
                ->whereIn('written_exam_year_date_id', $examDateIds)
                ->get();

            // Zeitslots laden (für Labels)
            $timeSlots = \App\Models\WrittenExamSupervisionTimeSlot::orderBy('slot_number')->get()->keyBy('id');

            // Nach Kalendertag gruppieren
            $datesByDay = $examDates->groupBy(fn($d) => $d->exam_date->format('Y-m-d'));

            // Schulname
            $school     = \App\Models\School::find(auth()->user()->school_id);
            $schoolName = $school !== null ? $school->name : '';

            // Seitenstruktur aufbauen: pro Tag → pro Raum eine Page
            $pages = [];
            foreach ($datesByDay as $dayString => $dayDates) {
                $dayDateIds = $dayDates->pluck('id');

                // Alle Assignments dieses Tages
                $dayAssignments = $allAssignments->whereIn('written_exam_year_date_id', $dayDateIds->all());

                if ($dayAssignments->isEmpty()) {
                    continue;
                }

                // Fächerstring + Zeitangabe
                $subjects  = $dayDates->flatMap(fn($d) => $d->slot !== null ? $d->slot->subjects : collect())->unique('id');
                $subjectStr = $subjects->isEmpty() ? 'Sammeltermin' : $subjects->pluck('name')->join(', ');
                $startTimes = $dayDates->map(fn($d) => $d->slot !== null ? \Carbon\Carbon::parse($d->slot->start_time)->format('H:i') : null)->filter()->unique()->sort();
                $endTimes   = $dayDates->map(fn($d) => $d->slot !== null ? \Carbon\Carbon::parse($d->slot->end_time)->format('H:i') : null)->filter()->unique()->sort();
                $timeRange  = $startTimes->isNotEmpty() ? ($startTimes->first() . ' – ' . $endTimes->last() . ' Uhr') : '';
                $examDate   = \Carbon\Carbon::parse($dayString)->locale('de');

                // Räume dieses Tages (eindeutig)
                $roomAssignmentsByRoom = $dayAssignments->groupBy('room_id');

                foreach ($roomAssignmentsByRoom as $roomId => $roomAssignments) {
                    $room = $roomAssignments->first()->room;
                    if ($room === null) continue;

                    // Alle Aufsichten für diesen Raum, geordnet nach Zeitslot
                    $allSupervisions = $roomAssignments->flatMap(fn($a) => $a->supervisions);
                    $studentCount    = $roomAssignments->sum(fn($a) => $a->seatAssignments->count());

                    // Supervisions nach Zeitslot-Slot gruppieren (slot_number für Sortierung)
                    $supervisionsBySlot = $allSupervisions->groupBy(function($s) {
                        return $s->written_exam_supervision_time_slot_id ?? 'none';
                    });

                    // Slot-Reihenfolge: erst die ohne Slot, dann aufsteigend nach slot_number
                    $slotKeys = $supervisionsBySlot->keys()->sortBy(function($key) use ($timeSlots) {
                        if ($key === 'none') return -1;
                        $ts = $timeSlots->get($key);
                        return $ts !== null ? $ts->slot_number : 999;
                    })->values();

                    // Kursinformation für diesen Raum
                    $courses = $roomAssignments->map(fn($a) => $a->course)->filter()->unique('id');

                    $pages[] = [
                        'dayString'          => $dayString,
                        'examDate'           => $examDate,
                        'subjectStr'         => $subjectStr,
                        'timeRange'          => $timeRange,
                        'room'               => $room,
                        'courses'            => $courses,
                        'studentCount'       => $studentCount,
                        'supervisionsBySlot' => $supervisionsBySlot,
                        'slotKeys'           => $slotKeys,
                        'timeSlots'          => $timeSlots,
                        'schoolName'         => $schoolName,
                    ];
                }
            }

            if (empty($pages)) {
                return redirect()->back()->with('error', 'Keine Räume mit Aufsichten gefunden.');
            }

            $pdf = Pdf::loadView('exports.supervision-signature-sheets', compact('pages', 'schoolName'))
                ->setPaper('a4', 'portrait');

            $filename = 'Aufsicht_Unterschriftsboegen_' . now()->format('Y-m-d') . '.pdf';
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Unterschriftsbögen', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Gesamtaufsichtsplan aller Prüfungstage als Excel.
     * Pro Tag ein Sheet.
     */
    public function supervisionsOverviewExcel()
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        try {
            $dayBlocks = $this->buildSupervisionOverviewDayBlocks();
            $filename  = 'Aufsichtsplan_Gesamt_' . now()->format('Y-m-d') . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\WrittenExamSupervisionOverviewExport($dayBlocks),
                $filename
            );

        } catch (\Exception $e) {
            Log::error('Fehler beim Excel-Export des Gesamtaufsichtsplans', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Tagesweite Sitzpläne aller Räume als ein kombiniertes PDF.
     */
    public function daySeatingPlansPdf(string $day)
    {
        try {
            $examDate = \Carbon\Carbon::createFromFormat('Y-m-d', $day);

            $yearDates = WrittenExamYearDate::with(['slot'])
                ->whereDate('exam_date', $examDate)
                ->orderBy('id')
                ->get();

            if ($yearDates->isEmpty()) {
                return redirect()->back()->with('error', 'Keine Prüfungstermine für diesen Tag gefunden.');
            }

            $dateIds = $yearDates->pluck('id');
            $allAssignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
                ->with(['course.teacher', 'course.subject', 'room.seatingTemplateVariants.seatingTemplate.seats', 'seatAssignments.student', 'seatAssignments.externalApplicant', 'seatAssignments.seat'])
                ->get();

            $assignmentsWithSeats = $allAssignments->filter(fn($a) => $a->seatAssignments->isNotEmpty());

            if ($assignmentsWithSeats->isEmpty()) {
                return redirect()->back()->with('error', 'Noch keine Sitzplatzzuweisungen für diesen Tag vorhanden.');
            }

            $pages  = [];
            $colors = ['blue', 'green', 'purple', 'orange', 'pink', 'indigo'];

            $roomGroupKeys = $assignmentsWithSeats
                ->filter(fn($a) => (bool) $a->room_id)
                ->map(fn($a) => $a->room_id . '|' . ($a->is_hoerverstaendnis ? '1' : '0'))
                ->unique();
            foreach ($roomGroupKeys as $groupKey) {
                [$roomId, $hvFlag] = explode('|', $groupKey);
                $roomId = (int) $roomId;
                $isHv   = $hvFlag === '1';
                $roomAssignments = $allAssignments->filter(fn($a) => $a->room_id === $roomId && (bool) $a->is_hoerverstaendnis === $isHv)
                    // @phpstan-ignore-next-line nullsafe.neverNull
                    ->sortBy(fn($a) => (empty($a->student_ids) ? '0' : '1') . '_' . ($a->course?->course_code ?? ''))->values();
                if ($roomAssignments->isEmpty()) {
                    continue;
                }
                $refDate = $yearDates->firstWhere('id', $roomAssignments->first()->written_exam_year_date_id);
                $refDate = $refDate !== null ? $refDate : $yearDates->first();

                $page = $this->buildSeatingPlanPageData($roomAssignments, $isHv, $colors, $refDate);
                if ($page !== null) {
                    $pages[] = $page;
                }
            }

            if (empty($pages)) {
                return redirect()->back()->with('error', 'Keine Sitzpläne mit Zuweisungen für diesen Tag gefunden.');
            }

            // Seiten alphabetisch nach Kurscode sortieren (wie bei Platzkarten und Schülerlisten)
            $pages = collect($pages)->sortBy(function ($p) {
                $first = collect($p['assignments'])->first();
                // @phpstan-ignore-next-line nullsafe.neverNull
                return $first !== null ? ($first->course?->course_code ?? '') : '';
            })->values()->all();

            $examYear = $yearDates->first()->exam_year;
            $pdf      = Pdf::loadView('exports.all-rooms-seating-plans', compact('pages', 'examYear'))
                ->setPaper('a4', 'portrait');

            return $pdf->download('Sitzplaene_' . $examDate->format('Y-m-d') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Fehler beim Export der Tages-Sitzpläne', ['day' => $day, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Baut aus den Sitzplatz-Zuweisungen eines Raums (an einem Tag/einer
     * Standard-/Hörverstehen-Variante) eine Sitzplan-Seite für den PDF-Export:
     * Layout und Sitzplatznummern ermitteln, Kursfarben zuordnen,
     * Belegungsraster aufbauen. Gibt null zurück, wenn der Raum kein
     * Sitzplan-Layout hat (der Aufrufer überspringt die Gruppe dann).
     * Gemeinsame Grundlage für daySeatingPlansPdf() und
     * allRoomsSeatingPlansPdf().
     *
     * @param \Illuminate\Support\Collection $roomAssignments
     * @param mixed $writtenExamDate Für die Seite anzuzeigender Prüfungstermin
     * @return array{writtenExamDate: mixed, room: \App\Models\Room, layout: mixed, assignments: \Illuminate\Support\Collection, grid: mixed, courseColors: array, seatNumbers: array}|null
     */
    private function buildSeatingPlanPageData(
        \Illuminate\Support\Collection $roomAssignments,
        bool $isHv,
        array $colors,
        $writtenExamDate
    ): ?array {
        $room   = $roomAssignments->first()->room;
        $layout = $room !== null ? $room->seatingTemplateFor($isHv ? 'hoerverstaendnis' : 'standard') : null;
        if ($layout === null) {
            return null;
        }

        $seatNumbers = $layout->platznummern();

        ['courseColors' => $courseColors, 'allSeatAssignments' => $allSeatAssignments]
            = $this->buildCourseColorsAndSeatAssignments($roomAssignments, $colors);

        return [
            'writtenExamDate' => $writtenExamDate,
            'room'            => $room,
            'layout'          => $layout,
            'assignments'     => $roomAssignments,
            'grid'            => $this->prepareRoomGridData($layout, $allSeatAssignments),
            'courseColors'    => $courseColors,
            'seatNumbers'     => $seatNumbers,
        ];
    }

    /**
     * Tagesweite Platzkarten aller Räume als ein kombiniertes PDF.
     */
    public function daySeatCardsPdf(string $day)
    {
        return $this->buildDayRoomPagesPdf(
            $day,
            fn ($refDate, $room, $isHv) => $this->buildRoomSeatCardsPageData($refDate, $room, $isHv),
            'Keine Platzkarten für diesen Tag gefunden.',
            'Fehler beim Export der Tages-Platzkarten',
            'exports.room-seat-cards',
            'landscape',
            'Platzkarten'
        );
    }

    /**
     * Tagesweite Schülerlisten aller Räume als ein kombiniertes PDF.
     */
    public function dayStudentListsPdf(string $day)
    {
        return $this->buildDayRoomPagesPdf(
            $day,
            fn ($refDate, $room, $isHv) => $this->buildRoomStudentListPageData($refDate, $room, $isHv),
            'Keine Schülerlisten für diesen Tag gefunden.',
            'Fehler beim Export der Tages-Schülerlisten',
            'exports.room-student-list',
            'portrait',
            'Schuelerlisten'
        );
    }

    /**
     * Gemeinsames Grundgerüst der tagesweiten Raum-PDFs (Platzkarten,
     * Schülerlisten): Tag parsen, Raumgruppen laden, pro Raum eine Seite über
     * $pageDataBuilder erzeugen, sortieren, als PDF ausliefern.
     *
     * @param callable(WrittenExamYearDate, \App\Models\Room, bool): ?array $pageDataBuilder
     */
    private function buildDayRoomPagesPdf(
        string $day,
        callable $pageDataBuilder,
        string $emptyErrorMessage,
        string $logMessage,
        string $view,
        string $paperOrientation,
        string $filenamePrefix
    ) {
        try {
            $examDate = \Carbon\Carbon::createFromFormat('Y-m-d', $day);

            // Einen repräsentativen YearDate pro Raum ermitteln
            $dayGroups = $this->loadDayRoomGroups($examDate);
            if ($dayGroups === null) {
                return redirect()->back()->with('error', 'Keine Prüfungstermine für diesen Tag gefunden.');
            }
            ['yearDates' => $yearDates, 'roomGroups' => $roomGroups] = $dayGroups;

            $roomPages = collect();
            foreach ($roomGroups as $group) {
                $room    = \App\Models\Room::find($group['room_id']);
                $refDate = $yearDates->firstWhere('id', $group['date_id']);
                if ($room === null || $refDate === null) {
                    continue;
                }
                $pageData = $pageDataBuilder($refDate, $room, $group['is_hv']);
                if ($pageData !== null) {
                    $roomPages->push($pageData);
                }
            }

            if ($roomPages->isEmpty()) {
                return redirect()->back()->with('error', $emptyErrorMessage);
            }

            $roomPages = $this->sortRoomPagesByCourseCode($roomPages);

            $pdf = Pdf::loadView($view, compact('roomPages'))->setPaper('a4', $paperOrientation);
            return $pdf->download($filenamePrefix . '_' . $examDate->format('Y-m-d') . '.pdf');

        } catch (\Exception $e) {
            Log::error($logMessage, ['day' => $day, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Alle Sitzpläne aller Räume aller Prüfungstage als ein PDF (nur Ansicht von hinten).
     * Sortierung: nach Prüfungsdatum, dann nach Raumnummer.
     */
    public function allRoomsSeatingPlansPdf()
    {
        try {
            $activePeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
            $examYear     = $activePeriod !== null ? $activePeriod->year : (int) date('Y');

            // Alle YearDates des aktiven Jahres, chronologisch
            $yearDates = WrittenExamYearDate::with(['slot'])
                ->where('exam_year', $examYear)
                ->orderBy('exam_date')
                ->get();

            if ($yearDates->isEmpty()) {
                return redirect()->back()->with('error', 'Keine Prüfungstermine gefunden.');
            }

            // Alle Assignments mit Sitzplatzzuweisungen laden
            $allDateIds = $yearDates->pluck('id');
            $allAssignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $allDateIds)
                ->with(['course.teacher', 'course.subject', 'room.seatingTemplateVariants.seatingTemplate.seats', 'seatAssignments.student', 'seatAssignments.externalApplicant', 'seatAssignments.seat'])
                ->get();

            // Nur Räume mit mindestens einer Sitzplatzzuweisung berücksichtigen
            $assignmentsWithSeats = $allAssignments->filter(
                fn($a) => $a->seatAssignments->isNotEmpty()
            );

            if ($assignmentsWithSeats->isEmpty()) {
                return redirect()->back()->with('error', 'Noch keine Sitzplatzzuweisungen vorhanden.');
            }

            // Gruppen: pro Tag + Raum eine Seite
            // Struktur: [['date' => YearDate, 'room' => Room, 'layout' => ..., 'assignments' => ..., 'grid' => ..., 'courseColors' => ..., 'seatNumbers' => ...], ...]
            $pages = [];
            $colors = ['blue', 'green', 'purple', 'orange', 'pink', 'indigo'];

            // Eindeutige Tag+Raum-Kombinationen bestimmen (nach Datum sortiert, dann Raum)
            $processed = [];
            foreach ($yearDates as $yearDate) {
                $dayAssignments = $assignmentsWithSeats->filter(
                    fn($a) => $a->written_exam_year_date_id === $yearDate->id
                        || \App\Models\WrittenExamYearDate::find($a->written_exam_year_date_id)?->exam_date->isSameDay($yearDate->exam_date)
                );

                // Räume dieses Tages (getrennt nach Standard-/Hörverstehen-Gruppe, da
                // beide seit Modul 1.5 denselben Raum mit unterschiedlicher
                // Sitzplan-Variante teilen können)
                $dayDateStr = $yearDate->exam_date->format('Y-m-d');
                $roomGroupKeys = $allAssignments
                    ->filter(function($a) use ($yearDates, $dayDateStr) {
                        $aDateId = $a->written_exam_year_date_id;
                        $yd = $yearDates->firstWhere('id', $aDateId);
                        return $yd && $yd->exam_date->format('Y-m-d') === $dayDateStr;
                    })
                    ->filter(fn($a) => $a->seatAssignments->isNotEmpty() && $a->room_id)
                    ->map(fn($a) => $a->room_id . '|' . ($a->is_hoerverstaendnis ? '1' : '0'))
                    ->unique();

                foreach ($roomGroupKeys as $groupKey) {
                    [$roomId, $hvFlag] = explode('|', $groupKey);
                    $roomId = (int) $roomId;
                    $isHv   = $hvFlag === '1';
                    $key = $yearDate->exam_date->format('Y-m-d') . '_' . $groupKey;
                    if (in_array($key, $processed)) {
                        continue;
                    }
                    $processed[] = $key;

                    // Alle Assignments für diesen Tag + Raum + Variante (Haupt-Assignments zuerst)
                    $roomAssignments = $allAssignments->filter(fn($a) =>
                        $yearDates->where('id', $a->written_exam_year_date_id)->first()?->exam_date->format('Y-m-d') === $yearDate->exam_date->format('Y-m-d')
                        && $a->room_id === $roomId
                        && (bool) $a->is_hoerverstaendnis === $isHv
                    )->sortBy(fn($a) => (empty($a->student_ids) ? '0' : '1') . '_' . ($a->course?->course_code ?? ''))->values(); // @phpstan-ignore-line nullsafe.neverNull

                    if ($roomAssignments->isEmpty()) {
                        continue;
                    }

                    $page = $this->buildSeatingPlanPageData($roomAssignments, $isHv, $colors, $yearDate);
                    if ($page !== null) {
                        $pages[] = $page;
                    }
                }
            }

            if (empty($pages)) {
                return redirect()->back()->with('error', 'Keine Sitzpläne mit Zuweisungen gefunden.');
            }

            // Erst nach Prüfungstag, dann alphabetisch nach Kurscode sortieren
            $pages = collect($pages)->sortBy(function ($p) {
                $date  = $p['writtenExamDate']?->exam_date?->format('Y-m-d') ?? '';
                $first = collect($p['assignments'])->first();
                // @phpstan-ignore-next-line nullsafe.neverNull
                $code  = $first !== null ? ($first->course?->course_code ?? '') : '';
                return $date . '_' . $code;
            })->values()->all();

            $orientation = 'portrait'; // pro Seite individuell im View gesetzt
            $pdf = Pdf::loadView('exports.all-rooms-seating-plans', compact('pages', 'examYear'))
                ->setPaper('a4', 'portrait');

            $filename = 'Sitzplaene_Alle_Raeume_' . $examYear . '_' . now()->format('Y-m-d') . '.pdf';
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Fehler beim Gesamt-Sitzplan-Export', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Fehler beim Export: ' . $e->getMessage());
        }
    }

    /**
     * Lädt die YearDates eines Prüfungstages und gruppiert deren Assignments
     * nach Raum + Standard-/Hörverstehen-Variante (ein Raum kann seit Modul
     * 1.5 beide Varianten gleichzeitig haben, mit unterschiedlicher
     * Sitzplan-Variante). Gibt je Gruppe eine repräsentative date_id zurück.
     * Gemeinsame Grundlage für daySeatCardsPdf() und dayStudentListsPdf().
     * Gibt null zurück, wenn für den Tag keine Prüfungstermine existieren -
     * der Aufrufer zeigt dann seine eigene Fehlermeldung an.
     *
     * @return array{yearDates: \Illuminate\Support\Collection, roomGroups: \Illuminate\Support\Collection}|null
     */
    private function loadDayRoomGroups(\Carbon\Carbon $examDate): ?array
    {
        $yearDates = WrittenExamYearDate::whereDate('exam_date', $examDate)->orderBy('id')->get();
        if ($yearDates->isEmpty()) {
            return null;
        }

        $dateIds = $yearDates->pluck('id');
        $roomGroups = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
            ->whereNotNull('room_id')
            ->select('room_id', 'is_hoerverstaendnis', 'written_exam_year_date_id')
            ->get()
            ->groupBy(fn($a) => $a->room_id . '|' . ($a->is_hoerverstaendnis ? '1' : '0'))
            ->map(fn($group) => [
                'room_id' => $group->first()->room_id,
                'is_hv'   => (bool) $group->first()->is_hoerverstaendnis,
                'date_id' => $group->first()->written_exam_year_date_id,
            ]);

        return ['yearDates' => $yearDates, 'roomGroups' => $roomGroups];
    }

    /**
     * Sortiert Raumseiten (Elemente mit Schlüssel 'assignments') alphabetisch
     * nach dem Kurscode des ersten Assignments. Gemeinsame Grundlage für
     * daySeatCardsPdf() und dayStudentListsPdf().
     */
    private function sortRoomPagesByCourseCode(\Illuminate\Support\Collection $roomPages): \Illuminate\Support\Collection
    {
        return $roomPages->sortBy(function ($p) {
            $first = $p['assignments']->first();
            return $first !== null ? $first->course->course_code : '';
        })->values();
    }

    /**
     * Baut aus den Assignments EINES Raums (+ Variante) die Kursfarben-
     * Zuordnung und die flache Liste aller Sitzplatzzuweisungen für die
     * Sitzplan-Ansicht. Gemeinsame Grundlage für daySeatingPlansPdf() und
     * allRoomsSeatingPlansPdf().
     *
     * @return array{courseColors: array, allSeatAssignments: \Illuminate\Support\Collection}
     */
    private function buildCourseColorsAndSeatAssignments(\Illuminate\Support\Collection $roomAssignments, array $colors): array
    {
        $courseColors       = [];
        $allSeatAssignments = collect();
        foreach ($roomAssignments as $idx => $assignment) {
            $courseColors[$assignment->course_id] = $colors[$idx % count($colors)];
            foreach ($assignment->seatAssignments as $sa) {
                $studentObj = $sa->anzeigeObjekt();
                $allSeatAssignments->push([
                    'seat'    => $sa->seat,
                    'student' => $studentObj,
                    'course'  => $assignment->course,
                    'color'   => $colors[$idx % count($colors)],
                ]);
            }
        }

        return ['courseColors' => $courseColors, 'allSeatAssignments' => $allSeatAssignments];
    }
}
