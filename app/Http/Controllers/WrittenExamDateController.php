<?php

namespace App\Http\Controllers;

use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamAssignment;
use App\Models\StudentExam;
use App\Models\Course;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Zeigt die schriftlichen Prüfungstermine (WrittenExamYearDate) für Planer.
 *
 * Die Verwaltung der Slots (Zeiten, Fachzuordnungen) und Jahrestermine erfolgt
 * ausschließlich durch Super-Admins über WrittenExamSlotController.
 * Planer sehen hier die Termine und verwalten Raumzuordnungen.
 */
class WrittenExamDateController extends Controller
{
    // -------------------------------------------------------------------------
    // Hilfsmethode: Eligible Courses für einen Slot ermitteln
    // -------------------------------------------------------------------------

    /**
     * Gibt die Kurse zurück, die inhaltlich zu einem Slot gehören.
     * Bei Sammelslots: alle Kurse des passenden Niveaus, deren Fach KEINEN
     * eigenen Einzelfach-Slot hat.
     *
     * @param \Illuminate\Support\Collection<int, Course> $allCourses
     * @param \Illuminate\Support\Collection<int, int>    $subjectsWithOwnSlot
     */
    private function eligibleCoursesForSlot(
        WrittenExamYearDate $date,
        \Illuminate\Support\Collection $allCourses,
        \Illuminate\Support\Collection $subjectsWithOwnSlot
    ): \Illuminate\Support\Collection {
        $slot         = $date->slot;
        $level        = $slot->requirement_level;
        $slotSubjects = $slot->subjects->pluck('id');

        // Immer: Kurse deren Fach im Slot-Fächerkreis liegt + richtiges Niveau
        // + mindestens ein Schüler mit schriftlicher Prüfung.
        return $allCourses->filter(fn($c) =>
            $slotSubjects->contains($c->subject_id) &&
            $c->level === $level &&
            $c->writtenStudents->count() > 0
        )->values();
    }

    /**
     * ID des Prüfungszeitraums zu einem Abiturjahr.
     *
     * Kurse hängen am Prüfungszeitraum, Termine am Jahr. Ohne diesen Filter
     * standen beim Abitur 2027 auch die Kurse des Abiturs 2026 (Codes „2…“)
     * in der Terminübersicht. Zusätzlich filtern die Abfragen mit derStufe('13'):
     * Im selben Zeitraum stehen auch die Kurse der Q12 mit schriftlichen
     * Prüfungen (ihren künftigen Abiturfächern).
     * Gibt es zum Jahr keinen Zeitraum, liefert die Methode 0 und die Abfrage
     * damit keine Kurse (null würde zu „IS NULL“).
     */
    private function zeitraumIdFuerJahr(int $jahr): int
    {
        return (int) (\App\Models\ExamPeriod::where('year', $jahr)->value('id') ?? 0);
    }

    // -------------------------------------------------------------------------
    // index – Übersicht
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $activeYear = null;
        if (app(\App\Services\AktuelleSchule::class)->aktiv()) {
            $activePeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
            $activeYear   = $activePeriod?->year;
        }
        if ($request->filled('year')) {
            $activeYear = (int) $request->year;
        }

        $availableYears = WrittenExamYearDate::select('exam_year')
            ->distinct()
            ->orderByDesc('exam_year')
            ->pluck('exam_year');

        $yearDates             = collect();
        $assignmentsByDate     = collect();
        $eligibleCoursesByDate = [];
        $writtenCountByCourse  = [];
        $statsTotal            = 0;
        $statsWithRoom         = 0;
        $statsWithSeats        = 0;

        if ($activeYear) {
            $yearDates = WrittenExamYearDate::with(['slot.subjects'])
                ->where('exam_year', $activeYear)
                ->orderBy('exam_date')
                ->get();

            if (app(\App\Services\AktuelleSchule::class)->aktiv() && $yearDates->isNotEmpty()) {
                $dateIds = $yearDates->pluck('id');

                // Assignments (für Farb-Status + Raum-Label im Chip)
                $assignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
                    ->select('id', 'written_exam_year_date_id', 'course_id', 'room_id', 'is_hoerverstaendnis', 'student_ids')
                    ->with('room:id,room_number')
                    ->withCount('seatAssignments')
                    ->get();
                $assignmentsByDate = $assignments->groupBy('written_exam_year_date_id');

                // Schülerzahlen pro Kurs (schriftliche Prüfungen)
                $counts = StudentExam::where('exam_type', 'written')
                    ->select('course_id', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('course_id')
                    ->pluck('cnt', 'course_id');
                $writtenCountByCourse = $counts->toArray();

                // Kurse des Prüfungszeitraums für die eligible-Berechnung
                $allCourses = Course::with(['subject'])
                    ->where('exam_period_id', $this->zeitraumIdFuerJahr((int) $activeYear))
                    ->derStufe('13')
                    ->orderBy('course_code')
                    ->get();

                $subjectsWithOwnSlot = $yearDates
                    ->filter(fn($yd) => $yd->slot->subjects->count() === 1)
                    ->flatMap(fn($yd) => $yd->slot->subjects->pluck('id'))
                    ->unique()->values();

                foreach ($yearDates as $date) {
                    $eligibleCoursesByDate[$date->id] =
                        $this->eligibleCoursesForSlot($date, $allCourses, $subjectsWithOwnSlot);
                }

                // Statistik: Kurs-Slots gesamt / mit Raum / mit Sitzplätzen
                $statsTotal     = 0;
                $statsWithRoom  = 0;
                $statsWithSeats = 0;
                foreach ($eligibleCoursesByDate as $dateId => $courses) {
                    foreach ($courses as $course) {
                        if (($writtenCountByCourse[$course->id] ?? 0) === 0) {
                            continue;
                        }
                        $statsTotal++;
                        $assignment = $assignmentsByDate->get($dateId, collect())
                            ->firstWhere('course_id', $course->id);
                        if ($assignment && $assignment->room_id) {
                            $statsWithRoom++;
                            if (($assignment->seat_assignments_count ?? 0) > 0) {
                                $statsWithSeats++;
                            }
                        }
                    }
                }
            }
        }

        $datesByDay = $yearDates->groupBy(fn($d) => $d->exam_date->format('Y-m-d'));

        return view('written-exams.dates.index', compact(
            'datesByDay',
            'activeYear',
            'availableYears',
            'assignmentsByDate',
            'eligibleCoursesByDate',
            'writtenCountByCourse',
            'statsTotal',
            'statsWithRoom',
            'statsWithSeats'
        ));
    }

    // -------------------------------------------------------------------------
    // dayAssign – Formular: Kurse ↔ Räume für einen ganzen Tag
    // -------------------------------------------------------------------------

    public function dayAssign(Request $request, string $day)
    {
        $dayCarbon = Carbon::createFromFormat('Y-m-d', $day);

        $yearDates = WrittenExamYearDate::with(['slot.subjects'])
            ->whereDate('exam_date', $day)
            ->get()
            ->sortBy(fn($d) => $d->slot->start_time)
            ->values();

        abort_if($yearDates->isEmpty(), 404, 'Keine Prüfungstermine für diesen Tag.');

        $examYear = $yearDates->first()->exam_year;

        // Kurse des Prüfungszeitraums mit writtenStudents laden
        $allCourses = Course::with(['subject', 'writtenStudents'])
            ->where('exam_period_id', $this->zeitraumIdFuerJahr((int) $examYear))
            ->derStufe('13')
            ->orderBy('course_code')
            ->get();

        // Fächer mit eigenem Slot – ÜBER DAS GESAMTE PRÜFUNGSJAHR (nicht nur diesen Tag!)
        $allYearDates = WrittenExamYearDate::with(['slot.subjects'])
            ->where('exam_year', $examYear)
            ->get();

        $subjectsWithOwnSlot = $allYearDates
            ->filter(fn($yd) => $yd->slot->subjects->count() === 1)
            ->flatMap(fn($yd) => $yd->slot->subjects->pluck('id'))
            ->unique()->values();

        // Eligible courses + bestehende Assignments pro Slot
        $eligibleByDate     = [];
        $assignmentsByDate  = [];
        $preselectedByDate  = []; // [dateId => ['rooms' => [], 'courses' => [roomId => [courseId,...]], 'nta' => [roomId => [...]], 'hvRooms' => [], 'hvRoomCourses' => [roomId => [courseId,...]]]]

        $dateIds = $yearDates->pluck('id');
        $allAssignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)
            ->with(['course.writtenStudents', 'room'])
            ->get();

        foreach ($yearDates as $date) {
            $eligible = $this->eligibleCoursesForSlot($date, $allCourses, $subjectsWithOwnSlot);
            $eligibleByDate[$date->id] = $eligible;

            $dateAssignments = $allAssignments->where('written_exam_year_date_id', $date->id);
            $assignmentsByDate[$date->id] = $dateAssignments;

            // Vorauswahl aufbauen
            $rooms         = [];
            $courses       = [];
            $nta           = [];
            $splits        = [];
            $hvRooms       = [];
            $hvRoomCourses = [];
            foreach ($dateAssignments as $a) {
                $rid = $a->room_id;
                if ($a->is_hoerverstaendnis) {
                    if (!in_array($rid, $hvRooms)) $hvRooms[] = $rid;
                    $hvRoomCourses[$rid][] = $a->course_id;
                } elseif ($a->split_label !== null) {
                    if (!in_array($rid, $rooms)) $rooms[] = $rid;
                    $splits[$a->course_id][] = [
                        'label'      => $a->split_label,
                        'roomId'     => $a->room_id,
                        'studentIds' => $a->student_ids ?? [],
                    ];
                } elseif ($a->student_ids) {
                    if (!in_array($rid, $rooms)) $rooms[] = $rid;
                    foreach ($a->student_ids as $sid) {
                        $nta[$rid][] = ['key' => 'nta_'.$a->course_id.'_'.$sid, 'courseId' => $a->course_id, 'studentId' => $sid];
                    }
                } else {
                    if (!in_array($rid, $rooms)) $rooms[] = $rid;
                    $courses[$rid][] = $a->course_id;
                }
            }
            $preselectedByDate[$date->id] = compact('rooms', 'courses', 'nta', 'splits', 'hvRooms', 'hvRoomCourses');
        }

        // Räume mit Sitzplan-Vorlage
        $rooms = Room::where('room_type', 'exam')
            ->with(['seatingTemplateVariants.seatingTemplate.seats'])
            ->orderBy('room_number')
            ->get();

        // Fremdsprachen-Fächer (für Hörverstehen-Sektion)
        $foreignLanguageSubjectIds = \App\Models\Subject::where('is_foreign_language', true)->pluck('id');

        // Raum-Auslastung (über alle Slots des Tages)
        $roomOccupancy = [];
        foreach ($rooms as $room) {
            $roomAssignments = $allAssignments->where('room_id', $room->id);
            $occupied = $roomAssignments->sum(fn($a) => $a->course?->writtenStudents->count() ?? 0);
            $capacity = $room->seatingTemplateFor('standard')?->seats->where('seat_type', 'available')->count() ?? 0;
            $roomOccupancy[$room->id] = [
                'occupied'    => $occupied,
                'total'       => $capacity,
                'free'        => max(0, $capacity - $occupied),
                'assignments' => $roomAssignments,
            ];
        }

        return view('written-exams.dates.day-assign', compact(
            'dayCarbon',
            'yearDates',
            'eligibleByDate',
            'assignmentsByDate',
            'preselectedByDate',
            'rooms',
            'roomOccupancy',
            'foreignLanguageSubjectIds'
        ));
    }

    // -------------------------------------------------------------------------
    // storeDay – Speichern aller Zuordnungen für einen Tag
    // -------------------------------------------------------------------------

    public function storeDay(Request $request, string $day): \Illuminate\Http\RedirectResponse
    {
        $yearDates = WrittenExamYearDate::whereDate('exam_date', $day)->pluck('id');
        abort_if($yearDates->isEmpty(), 404);

        $request->validate([
            'slots'                                             => 'nullable|array',
            'slots.*.date_id'                                   => 'required|exists:written_exam_year_dates,id',
            'slots.*.assignments'                               => 'nullable|array',
            'slots.*.assignments.*.room_id'                     => 'required|exists:rooms,id',
            'slots.*.assignments.*.course_ids'                  => 'required|array|min:1',
            'slots.*.assignments.*.course_ids.*'                => 'required|exists:courses,id',
            'slots.*.nta_assignments'                           => 'nullable|array',
            'slots.*.nta_assignments.*.room_id'                 => 'required|exists:rooms,id',
            'slots.*.nta_assignments.*.course_id'               => 'required|exists:courses,id',
            'slots.*.nta_assignments.*.student_ids'             => 'required|array|min:1',
            'slots.*.nta_assignments.*.student_ids.*'           => 'required|exists:students,id',
            'slots.*.split_assignments'                         => 'nullable|array',
            'slots.*.split_assignments.*.room_id'               => 'required|exists:rooms,id',
            'slots.*.split_assignments.*.course_id'             => 'required|exists:courses,id',
            'slots.*.split_assignments.*.split_label'           => 'required|string|max:50',
            'slots.*.split_assignments.*.student_ids'           => 'required|array|min:1',
            'slots.*.split_assignments.*.student_ids.*'         => 'required|exists:students,id',
            'slots.*.hv_assignments'                            => 'nullable|array',
            'slots.*.hv_assignments.*.room_id'                  => 'required|exists:rooms,id',
            'slots.*.hv_assignments.*.course_ids'               => 'required|array|min:1',
            'slots.*.hv_assignments.*.course_ids.*'             => 'required|exists:courses,id',
        ]);

        try {
            DB::beginTransaction();

            // Fingerprint: eindeutiger Schlüssel pro Assignment-Kombination
            // Sitzpläne hängen an der Assignment-ID – nur wirklich geänderte Assignments
            // löschen/anlegen, unveränderte bleiben unangetastet (Sitzpläne bleiben erhalten).
            $fingerprint = static function (
                int $dateId, int $courseId, int $roomId,
                bool $isHv, ?string $splitLabel, ?array $studentIds
            ): string {
                $sids = $studentIds ?? [];
                sort($sids);
                return $dateId . '|' . $courseId . '|' . $roomId
                    . '|' . ($isHv ? '1' : '0')
                    . '|' . ($splitLabel ?? '')
                    . '|' . implode(',', $sids);
            };

            // Bestehende Assignments laden
            $existingAssignments = WrittenExamAssignment::whereIn('written_exam_year_date_id', $yearDates)->get();

            /** @var array<string, WrittenExamAssignment> $existingMap */
            $existingMap = [];
            foreach ($existingAssignments as $a) {
                $sids = $a->student_ids ? array_map('intval', (array) $a->student_ids) : [];
                $fp = $fingerprint(
                    (int) $a->written_exam_year_date_id,
                    (int) $a->course_id,
                    (int) $a->room_id,
                    (bool) $a->is_hoerverstaendnis,
                    $a->split_label,
                    $sids !== [] ? $sids : null
                );
                $existingMap[$fp] = $a;
            }

            // Neue Assignments aus dem Request aufbauen
            /** @var array<string, array<string, mixed>> $newMap */
            $newMap = [];
            foreach ($request->slots ?? [] as $slotData) {
                $dateId = (int) $slotData['date_id'];

                foreach ($slotData['assignments'] ?? [] as $aData) {
                    foreach ($aData['course_ids'] as $courseId) {
                        $fp = $fingerprint($dateId, (int) $courseId, (int) $aData['room_id'], false, null, null);
                        $newMap[$fp] = [
                            'written_exam_year_date_id' => $dateId,
                            'course_id'                 => $courseId,
                            'room_id'                   => $aData['room_id'],
                            'student_ids'               => null,
                            'split_label'               => null,
                            'is_hoerverstaendnis'       => false,
                        ];
                    }
                }

                foreach ($slotData['nta_assignments'] ?? [] as $ntaData) {
                    $sids = array_map('intval', (array) $ntaData['student_ids']);
                    $fp = $fingerprint($dateId, (int) $ntaData['course_id'], (int) $ntaData['room_id'], false, null, $sids);
                    $newMap[$fp] = [
                        'written_exam_year_date_id' => $dateId,
                        'course_id'                 => $ntaData['course_id'],
                        'room_id'                   => $ntaData['room_id'],
                        'student_ids'               => $ntaData['student_ids'],
                        'split_label'               => null,
                        'is_hoerverstaendnis'       => false,
                    ];
                }

                foreach ($slotData['split_assignments'] ?? [] as $splitData) {
                    $sids = array_map('intval', (array) $splitData['student_ids']);
                    $fp = $fingerprint($dateId, (int) $splitData['course_id'], (int) $splitData['room_id'], false, $splitData['split_label'], $sids);
                    $newMap[$fp] = [
                        'written_exam_year_date_id' => $dateId,
                        'course_id'                 => $splitData['course_id'],
                        'room_id'                   => $splitData['room_id'],
                        'student_ids'               => $splitData['student_ids'],
                        'split_label'               => $splitData['split_label'],
                        'is_hoerverstaendnis'       => false,
                    ];
                }

                foreach ($slotData['hv_assignments'] ?? [] as $hvData) {
                    foreach ($hvData['course_ids'] as $courseId) {
                        $fp = $fingerprint($dateId, (int) $courseId, (int) $hvData['room_id'], true, null, null);
                        $newMap[$fp] = [
                            'written_exam_year_date_id' => $dateId,
                            'course_id'                 => $courseId,
                            'room_id'                   => $hvData['room_id'],
                            'student_ids'               => null,
                            'split_label'               => null,
                            'is_hoerverstaendnis'       => true,
                        ];
                    }
                }
            }

            // Assignments entfernen, die nicht mehr im neuen Set enthalten sind
            // (inkl. deren Sitzpläne via Cascade)
            $deleted = 0;
            foreach ($existingMap as $fp => $existing) {
                if (!isset($newMap[$fp])) {
                    $existing->delete();
                    $deleted++;
                }
            }

            // Nur wirklich neue Assignments anlegen (bestehende unverändert lassen)
            $created = 0;
            foreach ($newMap as $fp => $data) {
                if (!isset($existingMap[$fp])) {
                    WrittenExamAssignment::create($data);
                    $created++;
                }
            }

            DB::commit();

            $dayFormatted = Carbon::parse($day)->format('d.m.Y');
            $msg = ($created === 0 && $deleted === 0)
                ? 'Keine Änderungen – Zuordnungen für den ' . $dayFormatted . ' unverändert gespeichert.'
                : $created . ' neu, ' . $deleted . ' entfernt – Zuordnungen für den ' . $dayFormatted . ' gespeichert.';

            return redirect()
                ->route('written-exams.dates.index')
                ->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('storeDay Fehler', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Fehler: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // show – Detailansicht eines Prüfungstermins
    // -------------------------------------------------------------------------

    public function show(int|string $writtenExamYearDate)
    {
        $writtenExamYearDate = WrittenExamYearDate::with(['slot.subjects'])->findOrFail($writtenExamYearDate);

        $assignments       = collect();
        $assignmentsByRoom = collect();
        $relevantCourses   = collect();
        $missingCourses    = collect();

        if (app(\App\Services\AktuelleSchule::class)->aktiv()) {
            $assignments = WrittenExamAssignment::where('written_exam_year_date_id', $writtenExamYearDate->id)
                ->with(['course.writtenStudents', 'room'])
                ->withCount('seatAssignments')
                ->orderBy('room_id')
                ->get();

            $assignmentsByRoom = $assignments->groupBy('room_id');

            $subjectIds = $writtenExamYearDate->slot->subjects->pluck('id');
            $level      = $writtenExamYearDate->slot->requirement_level;

            $relevantCourses = Course::whereIn('subject_id', $subjectIds)
                ->where('exam_period_id', $this->zeitraumIdFuerJahr((int) $writtenExamYearDate->exam_year))
                ->derStufe('13')
                ->where('level', $level)
                ->with(['writtenStudents', 'subject'])
                ->orderBy('course_code')
                ->get();

            $assignedCourseIds = $assignments->pluck('course_id')->unique();
            $missingCourses    = $relevantCourses->filter(
                fn($c) => !$assignedCourseIds->contains($c->id)
            );
        }

        return view('written-exams.dates.show')
            ->with('writtenExamYearDate', $writtenExamYearDate)
            ->with('date', $writtenExamYearDate)
            ->with('assignments', $assignments)
            ->with('assignmentsByRoom', $assignmentsByRoom)
            ->with('relevantCourses', $relevantCourses)
            ->with('missingCourses', $missingCourses);
    }
}
