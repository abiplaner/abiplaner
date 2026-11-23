<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\OralExamDayRoom;
use App\Models\Room;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Services\KolloquiumSupervisionCoverageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SupervisionController extends Controller
{
    public function index(ExamPeriod $examPeriod)
    {
        [$timeSlots, $oralDayRooms] = $this->loadKolloquiumSupervisionBaseData($examPeriod);

        $oralDayRooms = $oralDayRooms->map(fn($r) => [
            'id'        => $r->id,
            'date'      => $r->date->format('Y-m-d'),
            'room_role' => $r->room_role,
            'room_id'   => $r->room_id,
            'sort_order' => $r->sort_order,
            'room'      => ['id' => $r->room->id, 'room_number' => $r->room->room_number],
        ]);

        // Alle Lehrkräfte (für Fallback und Filterung)
        $allTeachers = Teacher::active()->where('can_supervise', true)
            ->orderBy('shortcode')
            ->get(['id', 'shortcode', 'lastname', 'firstname', 'title']);

        // Alle Aufsichten (preparation, waiting, corridor mit room_id) – direkt am echten Zeitslot
        $slotIds = $timeSlots->pluck('id');
        $supervisions = Supervision::whereIn('time_slot_id', $slotIds)
            ->whereIn('supervision_type', ['preparation', 'waiting', 'corridor'])
            ->whereNotNull('room_id')
            ->with('teacher')->get();

        // Alle Prüfungstage (Wochentage)
        $allExamDates = collect();
        foreach ([[$examPeriod->week_1_start, $examPeriod->week_1_end], [$examPeriod->week_2_start, $examPeriod->week_2_end]] as [$start, $end]) {
            $d = $start->copy();
            while ($d->lte($end)) {
                if ($d->isWeekday()) $allExamDates->push($d->format('Y-m-d'));
                $d->addDay();
            }
        }

        $slotDates = $timeSlots->pluck('date')->map(fn($dt) => $dt->format('Y-m-d'))->unique()->sort()->values();

        $timeSlotsJs = $timeSlots->map(fn($s) => [
            'id' => $s->id, 'date' => $s->date->format('Y-m-d'),
            'prep_start_time' => $s->prep_start_time, 'prep_end_time' => $s->prep_end_time,
            'exam_start_time' => $s->exam_start_time, 'exam_end_time' => $s->exam_end_time,
            'slot_number' => $s->slot_number,
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // Verfügbare Lehrkräfte pro Datum berechnen
        //
        // Ausgeschlossen werden NUR Studienreferendare (grundsätzlich nicht
        // einsetzbar) und Lehrkräfte, die an diesem Tag selbst als Prüfer/
        // Beisitzer eingeteilt sind. Alle anderen bleiben wählbar; Lehrkräfte
        // mit Unterricht in der Q13 an diesem Tag werden in der Liste priorisiert
        // (zuerst angezeigt), da sie ohnehin vor Ort sind.
        // ─────────────────────────────────────────────────────────────────────

        // 1) StRef-IDs
        $strefIds = $allTeachers->filter(fn($t) => $t->title === 'StRef')
            ->pluck('id')->flip()->toArray(); // Als Set (key=id)

        // 2) Stundenpläne für relevante Wochentage laden (nur für Q13-Priorisierung)
        $relevantDows = $allExamDates->map(fn($d) => (int)\Carbon\Carbon::parse($d)->dayOfWeekIso)->unique()->values()->toArray();
        $timetableEntries = DB::connection()
            ->table('timetable_entries')
            ->whereIn('day_of_week', $relevantDows)
            ->whereNotNull('teacher_id')
            ->get(['teacher_id', 'class_name', 'day_of_week']);

        // 3) Prüfer/Beisitzer pro Datum (oral exam assignments)
        $examinerAssessorByDate = [];
        $assignmentsWithDates = DB::connection()
            ->table('exam_assignments')
            ->join('time_slots', 'exam_assignments.time_slot_id', '=', 'time_slots.id')
            ->join('student_exams', 'exam_assignments.student_exam_id', '=', 'student_exams.id')
            ->where('time_slots.exam_period_id', $examPeriod->id)
            ->get(['time_slots.date', 'student_exams.examiner_id', 'student_exams.assessor_id']);

        foreach ($assignmentsWithDates as $row) {
            $date = \Carbon\Carbon::parse($row->date)->format('Y-m-d');
            if ($row->examiner_id) $examinerAssessorByDate[$date][$row->examiner_id] = true;
            if ($row->assessor_id) $examinerAssessorByDate[$date][$row->assessor_id] = true;
        }

        // 4) Verfügbare Lehrkräfte pro Datum (gefiltert + Q13-priorisiert sortiert)
        $availableTeachersByDate = [];
        foreach ($allExamDates as $date) {
            $dow = (int)\Carbon\Carbon::parse($date)->dayOfWeekIso;

            // Prüfer/Beisitzer an diesem Tag
            $examExcluded = $examinerAssessorByDate[$date] ?? [];

            // Lehrkräfte mit Q13-Unterricht an diesem Tag (für Priorisierung)
            $teachersWithQ13Today = $timetableEntries
                ->where('day_of_week', $dow)
                ->where('class_name', 'Q13')
                ->pluck('teacher_id')->flip()->toArray();

            $availableTeachersByDate[$date] = $allTeachers
                ->filter(function ($t) use ($strefIds, $examExcluded) {
                    if (isset($strefIds[$t->id]))     return false; // Studienreferendar
                    if (isset($examExcluded[$t->id]))  return false; // Prüfer / Beisitzer an diesem Tag
                    return true;
                })
                ->sortBy(fn($t) => (isset($teachersWithQ13Today[$t->id]) ? '0_' : '1_') . $t->shortcode)
                ->map(fn($t) => [
                    'id' => $t->id, 'shortcode' => $t->shortcode, 'lastname' => $t->lastname,
                    'has_q13' => isset($teachersWithQ13Today[$t->id]),
                ])
                ->values()
                ->toArray();
        }

        // Alle Lehrkräfte für Modals (ohne StRef-Filter, ohne Datum-Filter – Fallback)
        $teachers = $allTeachers->filter(fn($t) => $t->title !== 'StRef')
            ->map(fn($t) => ['id' => $t->id, 'shortcode' => $t->shortcode, 'lastname' => $t->lastname])
            ->values();

        // Aufsichten-Kontrolle: richtige Anzahl anhand des echten Kolloquiumsplans (Modul 4.4)
        $coverage = (new KolloquiumSupervisionCoverageService())->compute($examPeriod);

        return view('supervisions.index', compact(
            'examPeriod', 'timeSlots', 'timeSlotsJs', 'oralDayRooms', 'teachers',
            'supervisions', 'allExamDates', 'slotDates',
            'availableTeachersByDate', 'coverage'
        ));
    }

    /**
     * Aufsichten-Kontrolle neu berechnen (nach Zuweisen/Entfernen einer Aufsicht
     * per AJAX abgerufen).
     */
    public function coverage(ExamPeriod $examPeriod)
    {
        $coverage = (new KolloquiumSupervisionCoverageService())->compute($examPeriod);

        return response()->json([
            'missingCellKeys'  => array_keys($coverage['missingCellKeys']),
            'orphanedCellKeys' => array_keys($coverage['orphanedCellKeys']),
            'uncoveredCount'   => $coverage['uncoveredCount'],
            'orphanedCount'    => $coverage['orphanedCount'],
        ]);
    }

    public function searchTeachers(Request $request, ExamPeriod $examPeriod)
    {
        $query = $request->input('q', '');
        if (strlen($query) < 2) return response()->json([]);
        $teachers = Teacher::active()->where('can_supervise', true)
            ->where(fn($q) => $q->where('shortcode', 'like', "%{$query}%")
                ->orWhere('lastname', 'like', "%{$query}%")
                ->orWhere('firstname', 'like', "%{$query}%"))
            ->get()
            ->map(fn($t) => ['id' => $t->id, 'shortcode' => $t->shortcode, 'name' => $t->lastname . ', ' . $t->firstname]);
        return response()->json($teachers);
    }

    /** Aufsicht für Vorbereitungs-/Warteraum (1 pro Prüfungsslot+Raum) */
    public function assign(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'time_slot_id'      => 'required|exists:time_slots,id',
            'room_id'           => 'required|exists:rooms,id',
            'teacher_id'        => 'required|exists:teachers,id',
            'supervision_type'  => 'required|in:preparation,waiting,corridor',
        ]);
        $timeSlot = TimeSlot::findOrFail($validated['time_slot_id']);
        if ($timeSlot->exam_period_id !== $examPeriod->id)
            return response()->json(['success' => false, 'message' => 'Prüfungsslot gehört nicht zu diesem Prüfungszeitraum'], 422);

        $supervision = Supervision::updateOrCreate(
            ['time_slot_id'     => $validated['time_slot_id'],
             'room_id'          => $validated['room_id'],
             'supervision_type' => $validated['supervision_type']],
            ['teacher_id' => $validated['teacher_id']]
        );
        return response()->json(['success' => true, 'supervision' => $supervision->load('teacher', 'room')]);
    }

    /** Gangaufsicht anlegen */
    public function assignCorridor(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'time_slot_id'   => 'required|exists:time_slots,id',
            'teacher_id'     => 'required|exists:teachers,id',
            'location_text'  => 'required|string|max:200',
        ]);
        $timeSlot = TimeSlot::findOrFail($validated['time_slot_id']);
        if ($timeSlot->exam_period_id !== $examPeriod->id)
            return response()->json(['success' => false, 'message' => 'Prüfungsslot gehört nicht zu diesem Prüfungszeitraum'], 422);

        $supervision = Supervision::create([
            'time_slot_id'      => $validated['time_slot_id'],
            'room_id'           => null,
            'teacher_id'        => $validated['teacher_id'],
            'supervision_type'  => 'corridor',
            'location_text'     => $validated['location_text'],
        ]);
        return response()->json(['success' => true, 'supervision' => $supervision->load('teacher')]);
    }

    /** Gangaufsicht aktualisieren */
    public function updateCorridor(Request $request, ExamPeriod $examPeriod, Supervision $supervision)
    {
        if ($supervision->supervision_type !== 'corridor')
            return response()->json(['success' => false, 'message' => 'Keine Gangaufsicht'], 422);
        $validated = $request->validate([
            'teacher_id'    => 'sometimes|exists:teachers,id',
            'location_text' => 'sometimes|string|max:200',
        ]);
        $supervision->update($validated);
        return response()->json(['success' => true, 'supervision' => $supervision->load('teacher')]);
    }

    public function destroy(ExamPeriod $examPeriod, Supervision $supervision)
    {
        $slotId = $supervision->time_slot_id;
        if ($slotId !== null) {
            $slot = TimeSlot::find($slotId);
            if ($slot !== null && $slot->exam_period_id !== $examPeriod->id)
                return response()->json(['success' => false, 'message' => 'Aufsicht gehört nicht zu diesem Prüfungszeitraum'], 422);
        }
        $supervision->delete();
        return response()->json(['success' => true]);
    }

    // =========================================================================
    // EXPORTS (PDF & Excel)
    // =========================================================================

    /**
     * Lädt die gemeinsame Rohdatenbasis für Aufsichts-Planung und -Export:
     * echte Prüfungs-Zeitslots (mit mind. einer aktiven Prüfung) und die
     * Kolloquiums-Nebenräume (Vorbereitung/Warten/Gang) des Prüfungszeitraums.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function loadKolloquiumSupervisionBaseData(ExamPeriod $examPeriod): array
    {
        $timeSlots = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->whereHas('examAssignments', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->orderBy('date')->orderBy('slot_number')->get();

        $oralDayRooms = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->whereIn('room_role', ['preparation', 'waiting', 'corridor'])
            ->with('room')
            ->orderBy('date')->orderBy('room_role')->orderBy('sort_order')
            ->get();

        return [$timeSlots, $oralDayRooms];
    }

    /** Gemeinsame Datenlast für alle Kolloquium-Aufsichtsexporte */
    private function loadKolloquiumExportData(ExamPeriod $examPeriod): array
    {
        [$timeSlots, $oralDayRooms] = $this->loadKolloquiumSupervisionBaseData($examPeriod);

        $slotIds = $timeSlots->pluck('id');
        $supervisions = Supervision::whereIn('time_slot_id', $slotIds)
            ->whereIn('supervision_type', ['preparation', 'waiting', 'corridor'])
            ->with('teacher')
            ->get();

        $school     = School::find(auth()->user()->school_id);
        $schoolName = $school !== null ? $school->name : '';

        return [$timeSlots, $oralDayRooms, $supervisions, $schoolName];
    }

    /**
     * Baut die dayBlocks-Struktur für PDF-Gesamtplan und Excel.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildKolloquiumDayBlocks(
        $timeSlots,
        $oralDayRooms,
        $supervisions
    ): array {
        $slotsByDate = $timeSlots->groupBy(fn($s) => $s->date->format('Y-m-d'));
        $dayBlocks   = [];

        foreach ($slotsByDate as $dateStr => $daySlots) {
            $dayRooms     = $oralDayRooms->filter(fn($r) => $r->date->format('Y-m-d') === $dateStr);
            $daySlotIdSet = $daySlots->pluck('id')->flip()->toArray();

            $prepRooms     = $dayRooms->where('room_role', 'preparation')->sortBy('sort_order')->values();
            $waitRooms     = $dayRooms->where('room_role', 'waiting')->sortBy('sort_order')->values();
            $corridorRooms = $dayRooms->where('room_role', 'corridor')->sortBy('sort_order')->values();

            // Gang-Aufsichten OHNE Raum (location_text)
            $freeCorridorSups = $supervisions->filter(
                fn($s) => isset($daySlotIdSet[$s->time_slot_id])
                       && $s->supervision_type === 'corridor'
                       && $s->room_id === null
            );

            $dayBlocks[$dateStr] = [
                'slots'            => $daySlots->sortBy('slot_number')->values(),
                'prepRooms'        => $prepRooms,
                'waitRooms'        => $waitRooms,
                'corridorRooms'    => $corridorRooms,
                'freeCorridorSups' => $freeCorridorSups,
            ];
        }

        return $dayBlocks;
    }

    /**
     * Baut lesbare Warnhinweis-Texte für die Aufsichten-Kontrolle (Modul 4.4),
     * zur Anzeige im PDF-/Excel-Export.
     *
     * @return array<int, string>
     */
    private function buildCoverageWarnings(ExamPeriod $examPeriod, $timeSlots, $oralDayRooms): array
    {
        $coverage = (new KolloquiumSupervisionCoverageService())->compute($examPeriod);

        $slotsById = $timeSlots->keyBy('id');
        $roomsById = $oralDayRooms->pluck('room', 'room_id');

        $typeLabel = fn (string $type) => match ($type) {
            'preparation' => 'Vorbereitungsraum',
            'waiting'     => 'Warteraum',
            'corridor'    => 'Gangaufsicht',
            default       => 'Aufsicht',
        };

        $warnings = [];

        foreach (array_keys($coverage['missingCellKeys']) as $key) {
            [$slotId, $roomId, $type] = explode('_', $key, 3);
            $slot = $slotsById->get((int)$slotId);
            $room = $roomsById->get((int)$roomId);
            if ($slot === null || $room === null) {
                continue;
            }
            $warnings[] = sprintf(
                'Fehlt: %s %s am %s, Slot %d (%s–%s Uhr)',
                $typeLabel($type),
                $room->room_number,
                $slot->date->locale('de')->isoFormat('DD.MM.'),
                $slot->slot_number,
                substr($slot->prep_start_time, 0, 5),
                substr($slot->exam_end_time, 0, 5)
            );
        }

        foreach (array_keys($coverage['orphanedCellKeys']) as $key) {
            [$slotId, $roomId, $type] = explode('_', $key, 3);
            $slot = $slotsById->get((int)$slotId);
            $room = $roomsById->get((int)$roomId);
            if ($slot === null || $room === null) {
                continue;
            }
            $warnings[] = sprintf(
                'Überflüssig: %s %s am %s, Slot %d (%s–%s Uhr) – laut Plan aktuell keine Prüfung',
                $typeLabel($type),
                $room->room_number,
                $slot->date->locale('de')->isoFormat('DD.MM.'),
                $slot->slot_number,
                substr($slot->prep_start_time, 0, 5),
                substr($slot->exam_end_time, 0, 5)
            );
        }

        return $warnings;
    }

    /** PDF Gesamtaufsichtsplan – kompakt (1 Woche = 1 Seite) */
    public function exportOverviewPdf(ExamPeriod $examPeriod)
    {
        [$timeSlots, $oralDayRooms, $supervisions, $schoolName] = $this->loadKolloquiumExportData($examPeriod);
        $dayBlocks = $this->buildKolloquiumDayBlocks($timeSlots, $oralDayRooms, $supervisions);
        $coverageWarnings = $this->buildCoverageWarnings($examPeriod, $timeSlots, $oralDayRooms);

        // Wochentage Woche 1 und Woche 2 aufbauen
        $buildWeekDates = function (\Carbon\Carbon $start, \Carbon\Carbon $end): \Illuminate\Support\Collection {
            $dates = collect();
            $d = $start->copy();
            while ($d->lte($end)) {
                if ($d->isWeekday()) {
                    $dates->push($d->format('Y-m-d'));
                }
                $d->addDay();
            }
            return $dates;
        };

        $week1Dates = $buildWeekDates($examPeriod->week_1_start, $examPeriod->week_1_end);
        $week2Dates = $buildWeekDates($examPeriod->week_2_start, $examPeriod->week_2_end);

        // Schuleinstellungen (Hinweistexte)
        $notes = [
            'gangaufsicht' => SchoolSetting::get('supervision_note.gangaufsicht'),
            'vorbereitung' => SchoolSetting::get('supervision_note.vorbereitung'),
            'warteraum'    => SchoolSetting::get('supervision_note.warteraum'),
        ];

        $pdf = Pdf::loadView('exports.kolloquium-supervision-overview', [
            'examPeriod'       => $examPeriod,
            'dayBlocks'        => $dayBlocks,
            'supervisions'     => $supervisions,
            'schoolName'       => $schoolName,
            'week1Dates'       => $week1Dates,
            'week2Dates'   => $week2Dates,
            'notes'        => $notes,
            'coverageWarnings' => $coverageWarnings,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Aufsichtsplan_Kolloquium_' . $examPeriod->year . '.pdf');
    }

    /** Excel Gesamtaufsichtsplan (je Sheet ein Tag) */
    public function exportOverviewExcel(ExamPeriod $examPeriod)
    {
        [$timeSlots, $oralDayRooms, $supervisions, $schoolName] = $this->loadKolloquiumExportData($examPeriod);
        $dayBlocks = $this->buildKolloquiumDayBlocks($timeSlots, $oralDayRooms, $supervisions);
        $coverageWarnings = $this->buildCoverageWarnings($examPeriod, $timeSlots, $oralDayRooms);

        $filename = 'Aufsichtsplan_Kolloquium_' . $examPeriod->year . '.xlsx';
        return Excel::download(
            new \App\Exports\KolloquiumSupervisionOverviewExport($examPeriod, $dayBlocks, $supervisions, $coverageWarnings),
            $filename
        );
    }

    /** PDF Aufsichtsliste alphabetisch nach Lehrkräften */
    public function exportTeacherListPdf(ExamPeriod $examPeriod)
    {
        [$timeSlots, $oralDayRooms, $supervisions, $schoolName] = $this->loadKolloquiumExportData($examPeriod);

        $slotById = $timeSlots->keyBy('id');

        $rows = [];
        foreach ($supervisions as $s) {
            $slot    = $slotById->get($s->time_slot_id);
            $teacher = $s->teacher;
            if ($slot === null || $teacher === null) continue;

            $typeLabel = match($s->supervision_type) {
                'preparation' => 'Vorbereitungsraum',
                'waiting'     => 'Warteraum',
                'corridor'    => 'Gangaufsicht',
                default       => 'Aufsicht',
            };

            if ($s->room_id !== null) {
                $dateStr      = $slot->date->format('Y-m-d');
                $matchingRoom = $oralDayRooms->first(
                    fn($r) => $r->room_id === $s->room_id && $r->date->format('Y-m-d') === $dateStr
                );
                $roomNr        = $matchingRoom !== null ? $matchingRoom->room->room_number : '?';
                $positionLabel = $typeLabel . ' ' . $roomNr;
            } else {
                $positionLabel = 'Gangaufsicht – ' . ($s->location_text ?: '–');
            }

            $rows[] = [
                'teacher_id'     => $teacher->id,
                'teacher'        => $teacher,
                'date'           => $slot->date,
                'sort_date'      => $slot->date->format('Y-m-d'),
                'slot_sort'      => $slot->slot_number,
                'time_range'     => substr($slot->prep_start_time, 0, 5) . '–' . substr($slot->exam_end_time, 0, 5) . ' Uhr',
                'position_label' => $positionLabel,
                'type'           => $s->supervision_type,
            ];
        }

        $byTeacher  = collect($rows)->groupBy('teacher_id');
        $teacherIds = $byTeacher->keys()->filter()->values();
        $teachers   = Teacher::whereIn('id', $teacherIds)->orderBy('lastname')->orderBy('firstname')
                          ->get()->keyBy('id');

        $teacherRows = [];
        foreach ($teachers as $teacher) {
            $sups = $byTeacher->get($teacher->id, collect())
                ->sortBy(fn($r) => $r['sort_date'] . sprintf('%04d', $r['slot_sort']))
                ->values();
            $teacherRows[] = ['teacher' => $teacher, 'supervisions' => $sups];
        }

        $pdf = Pdf::loadView('exports.kolloquium-teacher-supervision-list', compact(
            'examPeriod', 'teacherRows', 'schoolName'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('Aufsichtsliste_Lehrkraefte_Kolloquium_' . $examPeriod->year . '.pdf');
    }

    /** PDF Unterschriftsbögen (je Tag eine Seite) */
    public function exportSignatureSheetsPdf(ExamPeriod $examPeriod)
    {
        [$timeSlots, $oralDayRooms, $supervisions, $schoolName] = $this->loadKolloquiumExportData($examPeriod);

        $slotsByDate         = $timeSlots->groupBy(fn($s) => $s->date->format('Y-m-d'));
        $supervisionsBySlotId = $supervisions->groupBy('time_slot_id');

        $pages = [];
        foreach ($slotsByDate as $dateStr => $daySlots) {
            $dayRooms = $oralDayRooms->filter(fn($r) => $r->date->format('Y-m-d') === $dateStr);

            $entries = [];
            foreach ($daySlots->sortBy('slot_number') as $slot) {
                $slotSups = $supervisionsBySlotId->get($slot->id, collect())
                    ->sortBy(fn($s) => match($s->supervision_type) {
                        'preparation' => 0,
                        'waiting'     => 1,
                        default       => 2,
                    });

                foreach ($slotSups as $s) {
                    $teacher = $s->teacher;
                    if ($teacher === null) continue;

                    $typeLabel = match($s->supervision_type) {
                        'preparation' => 'Vorbereitungsraum',
                        'waiting'     => 'Warteraum',
                        'corridor'    => 'Gangaufsicht',
                        default       => 'Aufsicht',
                    };

                    if ($s->room_id !== null) {
                        $matchingRoom = $dayRooms->first(fn($r) => $r->room_id === $s->room_id);
                        $roomNr       = $matchingRoom !== null ? $matchingRoom->room->room_number : '?';
                        $position     = $typeLabel . ' ' . $roomNr;
                    } else {
                        $position = 'Gangaufsicht – ' . ($s->location_text ?: '–');
                    }

                    $entries[] = [
                        'time_label' => substr($slot->prep_start_time, 0, 5) . '–' . substr($slot->exam_end_time, 0, 5),
                        'slot_sort'  => $slot->slot_number,
                        'type_label' => $typeLabel,
                        'type'       => $s->supervision_type,
                        'position'   => $position,
                        'teacher'    => $teacher,
                    ];
                }
            }

            if (!empty($entries)) {
                $pages[] = [
                    'dateStr'    => $dateStr,
                    'examDate'   => \Carbon\Carbon::parse($dateStr)->locale('de'),
                    'entries'    => $entries,
                    'schoolName' => $schoolName,
                ];
            }
        }

        $pdf = Pdf::loadView('exports.kolloquium-supervision-signature-sheets', compact(
            'examPeriod', 'pages', 'schoolName'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('Aufsicht_Unterschriftsboegen_Kolloquium_' . $examPeriod->year . '.pdf');
    }
}
