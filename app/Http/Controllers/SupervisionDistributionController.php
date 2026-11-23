<?php

namespace App\Http\Controllers;

use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamSupervisionGrid;
use App\Models\WrittenExamSupervisionTimeSlot;
use App\Models\WrittenExamCentralSupervisionType;
use App\Models\Supervision;
use App\Models\Teacher;
use App\Services\SupervisionDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupervisionDistributionController extends Controller
{
    protected SupervisionDistributionService $distributionService;

    public function __construct(SupervisionDistributionService $distributionService)
    {
        $this->distributionService = $distributionService;
    }

    /**
     * Overview of all written exam dates for supervision planning.
     */
    public function index()
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        $examDates = WrittenExamYearDate::with(['slot.subjects'])
            ->orderBy('exam_date')
            ->get();

        // assignments kommen aus der Tenant-DB – daher separat laden
        // und manuell als Relation setzen (Cross-DB eager loading nicht möglich)
        $examDateIds = $examDates->pluck('id');
        $assignmentsByDateId = WrittenExamAssignment::whereIn('written_exam_year_date_id', $examDateIds)
            ->get()
            ->groupBy('written_exam_year_date_id');

        $examDates->each(function (WrittenExamYearDate $examDate) use ($assignmentsByDateId) {
            $examDate->setRelation('assignments', $assignmentsByDateId->get($examDate->id, collect()));
        });

        $examDates = $examDates->map(function (WrittenExamYearDate $examDate) {
                // Anzahl zugewiesener Aufsichten pro Termin
                $roomSupervisions = Supervision::where('written_exam_year_date_id', $examDate->id)
                    ->whereNotNull('written_exam_assignment_id')
                    ->count();
                $corridorSupervisions = Supervision::where('written_exam_year_date_id', $examDate->id)
                    ->whereNull('written_exam_assignment_id')
                    ->where('supervision_type', 'corridor')
                    ->count();
                $roomCount = $examDate->assignments->pluck('room_id')->unique()->count();

                $examDate->supervision_room_count     = $roomSupervisions;
                $examDate->supervision_corridor_count = $corridorSupervisions;
                $examDate->room_count                 = $roomCount;
                return $examDate;
            });

        // Nach Datum gruppieren
        $datesByDate = $examDates->groupBy(fn($d) => $d->exam_date->format('Y-m-d'));

        return view('written-exams.supervisions.index', compact('examDates', 'datesByDate'));
    }

    /**
     * Display the supervision overview for an exam date.
     */
    public function show(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        $schoolId = auth()->user()->school_id;

        $statistics = $this->distributionService->getTeacherStatistics($writtenExamYearDate, $schoolId);
        $expectedLoad = $this->distributionService->calculateExpectedLoad($writtenExamYearDate, $schoolId);
        $validation = $this->distributionService->validateDistribution($writtenExamYearDate, $schoolId);

        return view('written-exams.supervisions.show', compact(
            'writtenExamYearDate',
            'statistics',
            'expectedLoad',
            'validation'
        ));
    }

    /**
     * Manual planning view: matrix of time slots × rooms.
     */
    public function plan(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('viewAny', \App\Models\Supervision::class);

        $writtenExamYearDate->load('slot.subjects');

        // Finde alle WrittenExamYearDate IDs für diesen Kalendertag
        $dateIds = WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');

        // Sammeltermin: mehrere Termine des gleichen Anforderungsniveaus am selben Tag
        $sameLevelCount = WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)
            ->whereHas('slot', fn($q) => $q->where('requirement_level', $writtenExamYearDate->slot->requirement_level))
            ->count();
        $isCollectiveDay = $sameLevelCount > 1;

        // Alle Raumzuordnungen für diesen Prüfungstermin
        $assignments = WrittenExamAssignment::with([
                'room',
                'course.teacher',
                'course.subject',
                'supervisions.teacher',
                'supervisions.writtenExamSupervisionTimeSlot',
            ])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->get();

        // Gangaufsichten (fest, supervision_type='corridor', kein central_type)
        $corridorSupervisions = Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->where('supervision_type', 'corridor')
            ->whereNull('written_exam_assignment_id')
            ->whereNull('central_supervision_type_id')
            ->get();

        // Reserve / Lehrerzimmer (fest, supervision_type='reserve')
        $reserveSupervisions = Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->where('supervision_type', 'reserve')
            ->whereNull('written_exam_assignment_id')
            ->get();

        // Terminspezifische Zusatz-Aufsichtstypen (z.B. "2. Gangaufsicht")
        $centralTypes = WrittenExamCentralSupervisionType::whereIn('written_exam_year_date_id', $dateIds)
            ->orderBy('sort_order')->orderBy('name')->get();
        $centralSupervisions = Supervision::with(['teacher', 'writtenExamSupervisionTimeSlot'])
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->where('supervision_type', 'extra')
            ->whereNull('written_exam_assignment_id')
            ->whereNotNull('central_supervision_type_id')
            ->get()
            ->groupBy('central_supervision_type_id');

        // Zeitslots
        $timeSlots = WrittenExamSupervisionTimeSlot::orderBy('slot_number')->get();

        // Alle Raster laden
        $grids = WrittenExamSupervisionGrid::withCount('timeSlots')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $defaultGrid   = $grids->where('is_default', true)->first() ?? $grids->first();
        $defaultGridId = $defaultGrid?->id;

        // Lehrer-Empfehlungen für jeden Timeslot berechnen
        $schoolId = auth()->user()->school_id;
        $teacherSuggestions = [];
        $teacherSuggestions['none'] = collect($this->distributionService->getTeacherSuggestionsForTimeSlot($writtenExamYearDate, null, $schoolId));
        
        foreach ($timeSlots as $slot) {
            $teacherSuggestions[$slot->id] = collect($this->distributionService->getTeacherSuggestionsForTimeSlot($writtenExamYearDate, $slot, $schoolId));
        }

        // Kategorie-Map für farbliche Markierung bereits zugewiesener Aufsichten
        $teacherCategoryMaps = [];
        $teacherCategoryMaps['none'] = $this->distributionService->getTeacherCategoryMap($writtenExamYearDate, null, $schoolId);
        foreach ($timeSlots as $slot) {
            $teacherCategoryMaps[$slot->id] = $this->distributionService->getTeacherCategoryMap($writtenExamYearDate, $slot, $schoolId);
        }

        // Nach Raum gruppieren
        $assignmentsByRoom = $assignments->groupBy('room_id');

        // Prüfen ob an diesem Tag eine Fremdsprache geprüft wird
        // (erkennbar an Räumen mit is_hoerverstaendnis = true)
        $hasForeignLanguage = $assignments->some(fn($a) => $a->is_hoerverstaendnis);

        // ---- Prüfungszeiten für diesen Tag berechnen ----
        $yearDatesWithSlots = WrittenExamYearDate::with(['slot', 'slot.subjects'])
            ->whereIn('id', $dateIds)
            ->get()
            ->sortBy('slot.start_time')
            ->values();

        // Früheste Startzeit / späteste Endzeit aus allen Slots des Tages
        $examStartTime = $yearDatesWithSlots
            ->map(fn($yd) => \Carbon\Carbon::parse($yd->slot->start_time))
            ->sort()->first() ?? \Carbon\Carbon::parse('08:00');

        $examEndTime = $yearDatesWithSlots
            ->map(fn($yd) => \Carbon\Carbon::parse($yd->slot->end_time))
            ->sort()->last() ?? \Carbon\Carbon::parse('12:00');

        // Raster-IDs die tatsächlich Räumen zugewiesen sind (für Coverage-Check)
        $usedGridIds = $assignments
            ->pluck('written_exam_supervision_grid_id')
            ->filter()
            ->unique()
            ->push($defaultGridId) // Standard-Raster immer einbeziehen
            ->filter()
            ->unique()
            ->values();

        // Max NTA-Prozent aus allen Schülern der Raumzuordnungen ermitteln
        $maxNtaPercent = 0;
        foreach ($assignments as $assignment) {
            $ntaMax = $assignment->getEffectiveStudents()
                ->max('disability_compensation_percent') ?? 0;
            if ((int) $ntaMax > $maxNtaPercent) {
                $maxNtaPercent = (int) $ntaMax;
            }
        }

        // Späteste Endzeit MIT NTA
        $examEndTimeWithMaxNta = $maxNtaPercent > 0
            ? $yearDatesWithSlots
                ->map(fn($yd) => $yd->slot->getEndTimeWithNta($maxNtaPercent))
                ->sort()->last() ?? $examEndTime
            : $examEndTime;

        return view('written-exams.supervisions.plan', compact(
            'writtenExamYearDate',
            'assignments',
            'assignmentsByRoom',
            'corridorSupervisions',
            'reserveSupervisions',
            'centralTypes',
            'centralSupervisions',
            'timeSlots',
            'grids',
            'defaultGridId',
            'teacherSuggestions',
            'teacherCategoryMaps',
            'hasForeignLanguage',
            'examStartTime',
            'examEndTime',
            'examEndTimeWithMaxNta',
            'maxNtaPercent',
            'yearDatesWithSlots',
            'usedGridIds',
            'isCollectiveDay'
        ));
    }

    /**
     * Assign a teacher (by Kürzel) to a room assignment or corridor.
     */
    public function assignTeacher(Request $request, WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $request->validate([
            'kuerzel'                              => 'required|string|max:10',
            'written_exam_assignment_id'            => 'nullable|integer|exists:written_exam_assignments,id',
            'supervision_type'                      => 'required|in:waiting,corridor,reserve,extra',
            'written_exam_supervision_time_slot_id' => 'nullable|integer|exists:written_exam_supervision_time_slots,id',
            'central_supervision_type_id'           => 'nullable|integer|exists:written_exam_central_supervision_types,id',
        ]);

        $teacher = Teacher::where('shortcode', strtoupper(trim($request->kuerzel)))->first();

        if (!$teacher) {
            return back()->withErrors(['kuerzel' => "Kein Lehrer mit Kuerzel \"{$request->kuerzel}\" gefunden."]);
        }

        $roomId = null;
        $targetYearDateId = $writtenExamYearDate->id;

        if ($request->written_exam_assignment_id) {
            $assignment = WrittenExamAssignment::find($request->written_exam_assignment_id);
            $roomId = $assignment?->room_id;
            if ($assignment) {
                $targetYearDateId = $assignment->written_exam_year_date_id;
            }
        }

        // Doppelzuweisung prüfen: Lehrkraft darf im selben Zeitslot keine weitere Aufsicht haben
        // (gilt tagesübergreifend über alle YearDate-IDs, z.B. bei Sammelterminen)
        $dateIds = WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');

        $alreadyAssigned = Supervision::where('teacher_id', $teacher->id)
            ->whereIn('written_exam_year_date_id', $dateIds)
            ->where('written_exam_supervision_time_slot_id', $request->written_exam_supervision_time_slot_id)
            ->exists();

        if ($alreadyAssigned) {
            return back()->withErrors(['kuerzel' => "\"{$teacher->shortcode}\" ist für diesen Zeitslot bereits als Aufsicht eingeteilt."]);
        }

        Supervision::create([
            'teacher_id'                            => $teacher->id,
            'room_id'                               => $roomId,
            'supervision_type'                      => $request->supervision_type,
            'written_exam_assignment_id'            => $request->written_exam_assignment_id,
            'written_exam_year_date_id'             => $targetYearDateId,
            'written_exam_supervision_time_slot_id' => $request->written_exam_supervision_time_slot_id ?: null,
            'central_supervision_type_id'           => $request->central_supervision_type_id ?: null,
        ]);

        return back()->with('success', "\"{$teacher->shortcode}\" erfolgreich zugeteilt.");
    }

    /**
     * Remove a supervision assignment.
     */
    public function unassignTeacher(WrittenExamYearDate $writtenExamYearDate, Supervision $supervision)
    {
        $this->authorize('delete', $supervision);

        $kuerzel = $supervision->teacher->shortcode ?? 'Lehrer';
        $supervision->delete();

        return back()->with('success', "\"{$kuerzel}\" wurde entfernt.");
    }

    /**
     * Update the required supervisor count for a room assignment.
     */
    public function setRequiredSupervisors(Request $request, WrittenExamYearDate $writtenExamYearDate, WrittenExamAssignment $assignment)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $request->validate([
            'required_supervisors' => 'required|integer|min:1|max:10',
        ]);

        $assignment->update(['required_supervisors' => $request->required_supervisors]);

        return back()->with('success', 'Anzahl Aufsichten aktualisiert.');
    }

    /**
     * Automatically distribute supervisions.
     */
    public function autoDistribute(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $schoolId = auth()->user()->school_id;

        try {
            $result = $this->distributionService->distributeForExamDate($writtenExamYearDate, $schoolId);

            if ($result['success']) {
                return redirect()->back()
                    ->with('success', $result['message'])
                    ->with('statistics', $result['statistics']);
            } else {
                return redirect()->back()->with('error', $result['message']);
            }

        } catch (\Exception $e) {
            Log::error('Fehler bei automatischer Aufsichtsverteilung', [
                'exam_date_id' => $writtenExamYearDate->id,
                'school_id' => $schoolId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return redirect()->back()->with('error', 'Fehler bei der Aufsichtsverteilung: ' . $e->getMessage());
        }
    }

    /**
     * Terminspezifischen Zusatz-Aufsichtstyp anlegen.
     */
    public function storeCentralType(Request $request, WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('create', \App\Models\Supervision::class);

        $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|in:orange,teal,pink,yellow,blue,purple',
        ]);

        $maxOrder = WrittenExamCentralSupervisionType::where('written_exam_year_date_id', $writtenExamYearDate->id)
            ->max('sort_order') ?? 0;

        WrittenExamCentralSupervisionType::create([
            'written_exam_year_date_id' => $writtenExamYearDate->id,
            'name'                      => $request->name,
            'color'                     => $request->color,
            'sort_order'                => $maxOrder + 1,
        ]);

        return back()->with('success', "Aufsichtsposition \"{$request->name}\" hinzugefügt.");
    }

    /**
     * Terminspezifischen Zusatz-Aufsichtstyp löschen.
     */
    public function destroyCentralType(WrittenExamYearDate $writtenExamYearDate, WrittenExamCentralSupervisionType $centralType)
    {
        $this->authorize('delete', \App\Models\Supervision::class);

        if ($centralType->written_exam_year_date_id !== $writtenExamYearDate->id) {
            abort(403);
        }

        $name = $centralType->name;
        Supervision::where('central_supervision_type_id', $centralType->id)->delete();
        $centralType->delete();

        return back()->with('success', "Aufsichtsposition \"{$name}\" entfernt.");
    }

    /**
     * Clear all supervisions for an exam date.
     */
    public function clear(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('delete', \App\Models\Supervision::class);

        $schoolId = auth()->user()->school_id;

        try {
        $dateIds = WrittenExamYearDate::whereDate('exam_date', $writtenExamYearDate->exam_date)->pluck('id');
        $assignmentIds = WrittenExamAssignment::whereIn('written_exam_year_date_id', $dateIds)->pluck('id');
        
        $deletedCount = Supervision::whereIn('written_exam_year_date_id', $dateIds)
            ->orWhereIn('written_exam_assignment_id', $assignmentIds)
            ->delete();

        return redirect()->back()->with('success', "{$deletedCount} Aufsichten für diesen Tag wurden gelöscht.");
    } catch (\Exception $e) {
        Log::error('Fehler beim Löschen der Aufsichten', [
            'exam_date_id' => $writtenExamYearDate->id,
            'school_id' => $schoolId,
            'error' => $e->getMessage(),
            'user_id' => auth()->id()
        ]);
        return redirect()->back()->with('error', 'Fehler beim Löschen der Aufsichten: ' . $e->getMessage());
        }
    }
}
