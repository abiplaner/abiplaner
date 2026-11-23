<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\TeacherUnavailability;
use App\Models\TimetableEntry;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Services\ImportService;
use App\Support\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TeacherController extends Controller
{
    use AuthorizesRequests;
    
    public function __construct(private ImportService $importService)
    {
    }
    
    public function index(Request $request)
    {
        $this->authorize('viewAny', Teacher::class);
        
        $query = Teacher::with('subjects')
            ->withCount('examinerAssignments');
        
        // Suchfunktion
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('shortcode', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }
        
        // Filter nach Fach
        if ($subjectId = $request->input('subject')) {
            $query->whereHas('subjects', function($q) use ($subjectId) {
                $q->where('subjects.id', $subjectId);
            });
        }
        
        // Sortierung
        $sortBy = $request->input('sort_by', 'shortcode');
        $sortOrder = $request->input('sort_order', 'asc');
        
        if (in_array($sortBy, ['shortcode', 'firstname', 'lastname', 'title'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('shortcode', 'asc');
        }
        
        $teachers = $query->paginate(20)->withQueryString();
        
        // Fächer für Filter laden
        $subjects = \App\Models\Subject::orderBy('name')->get();
        
        return view('teachers.index', compact('teachers', 'subjects'));
    }
    
    public function create()
    {
        $this->authorize('create', Teacher::class);

        $subjects = Subject::orderedForDisplay()->get();

        return view('teachers.create', compact('subjects'));
    }

    
    public function store(Request $request)
    {
        $this->authorize('create', Teacher::class);
        
        $validated = $request->validate([
            'shortcode'       => 'required|string|max:50',
            'firstname'       => 'required|string|max:100',
            'lastname'        => 'required|string|max:100',
            'title'           => 'nullable|string|max:100',
            'gender'          => 'nullable|in:m,w,d',
            'external_id'     => 'nullable|string|max:255|unique:teachers,external_id',
            'hours'           => 'nullable|numeric|min:0',
            'employment_type' => 'nullable|string|max:255',
            'subject_ids'     => 'nullable|array',
            'subject_ids.*'   => 'exists:subjects,id',
        ]);
        
        $teacher = Teacher::create(\Arr::except($validated, ['subject_ids']));

        $teacher->subjects()->sync($request->input('subject_ids', []));
        
        ActivityLogger::log(
            'teacher_created',
            'Teacher',
            $teacher->id,
            "Lehrer {$teacher->shortcode} erstellt"
        );
        
        return redirect()->route('teachers.index')
            ->with('success', 'Lehrkraft erfolgreich erstellt.');
    }

    
    public function show(Teacher $teacher)
    {
        $this->authorize('view', $teacher);
        
        $teacher->load([
            'examinerAssignments.course.subject',
            'assessorAssignments',
            'unavailabilities'
        ]);
        
        return view('teachers.show', compact('teacher'));
    }
    
    /**
     * Stundenplan einer Lehrkraft anzeigen
     */
    public function timetable(Teacher $teacher, Request $request)
    {
        $this->authorize('view', $teacher);

        $schoolYear = $request->input('school_year') ?: SchoolYear::current();

        $entries = TimetableEntry::whereHas('teacher', function ($q) use ($teacher) {
                $q->where('id', $teacher->id);
            })
            ->where('school_year', $schoolYear)
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get()
            ->groupBy('day_of_week');

        $maxPeriod = TimetableEntry::where('school_year', $schoolYear)->max('period') ?? 12;

        $subjectCodeMap = Subject::query()
            ->get(['name', 'code'])
            ->mapWithKeys(fn($subject) => [mb_strtolower($subject->name) => $subject->code])
            ->toArray();

        $days = [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
        ];

        // Verfügbare Schuljahre aus der DB ermitteln
        $availableYears = TimetableEntry::distinct()->pluck('school_year')->sort()->values();

        // Prüfungszeiträume und Abwesenheiten laden
        $periodService = app(\App\Services\CurrentExamPeriodService::class);
        $examPeriods = $periodService->all();
        $activeExamPeriod = $periodService->current();

        // Kolloquiumstage des aktiven Zeitraums (Woche 1 + 2, nur Werktage)
        $kolloquiumDays = collect();
        if ($activeExamPeriod) {
            foreach ([1, 2] as $week) {
                $start = $week === 1 ? $activeExamPeriod->week_1_start : $activeExamPeriod->week_2_start;
                $end   = $week === 1 ? $activeExamPeriod->week_1_end   : $activeExamPeriod->week_2_end;
                if ($start && $end) {
                    $day = $start->copy();
                    while ($day->lte($end)) {
                        if ($day->isWeekday()) {
                            $kolloquiumDays->push($day->copy());
                        }
                        $day->addDay();
                    }
                }
            }
        }

        $unavailabilities = TeacherUnavailability::where('teacher_id', $teacher->id)
            ->with('examPeriod')
            ->orderBy('date')
            ->get();

        return view('teachers.timetable', compact(
            'teacher',
            'entries',
            'days',
            'maxPeriod',
            'schoolYear',
            'subjectCodeMap',
            'availableYears',
            'examPeriods',
            'activeExamPeriod',
            'kolloquiumDays',
            'unavailabilities'
        ));
    }

    public function edit(Teacher $teacher)
    {
        $this->authorize('update', $teacher);

        $teacher->load('subjects');
        $subjects = Subject::orderedForDisplay()->get();

        return view('teachers.edit', compact('teacher', 'subjects'));
    }
    
    public function update(Request $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        
        $validated = $request->validate([
            'shortcode'       => 'required|string|max:50',
            'firstname'       => 'required|string|max:100',
            'lastname'        => 'required|string|max:100',
            'title'           => 'nullable|string|max:100',
            'gender'          => 'nullable|in:m,w,d',
            'external_id'     => 'nullable|string|max:255|unique:teachers,external_id,' . $teacher->id,
            'hours'           => 'nullable|numeric|min:0',
            'employment_type' => 'nullable|string|max:255',
            'contact_phone'   => 'nullable|string|max:100',
            'contact_email'   => 'nullable|email|max:191',
            'contact_bycs'    => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'subject_ids'     => 'nullable|array',
            'subject_ids.*'   => 'exists:subjects,id',
        ]);
        $validated['contact_bycs'] = $request->boolean('contact_bycs');
        $validated['is_active']    = $request->boolean('is_active');
        
        $teacher->update(\Arr::except($validated, ['subject_ids']));

        $teacher->subjects()->sync($request->input('subject_ids', []));
        
        ActivityLogger::log(
            'teacher_updated',
            'Teacher',
            $teacher->id,
            "Lehrer {$teacher->shortcode} aktualisiert"
        );
        
        return redirect()->route('teachers.index', ['page' => $request->input('page')])
            ->with('success', 'Lehrkraft erfolgreich aktualisiert.');
    }
    
    public function destroy(Teacher $teacher)
    {
        $this->authorize('delete', $teacher);
        
        $shortcode = $teacher->shortcode;
        $teacher->delete();
        
        ActivityLogger::log(
            'teacher_deleted',
            'Teacher',
            $teacher->id,
            "Lehrer {$shortcode} gelöscht"
        );
        
        return redirect()->route('teachers.index')
            ->with('success', 'Lehrkraft erfolgreich gelöscht.');
    }
    
    /**
     * Mehrere Lehrer auf einmal löschen
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorize('create', Teacher::class);
        
        $validated = $request->validate([
            'teacher_ids' => 'required|array',
            'teacher_ids.*' => 'exists:teachers,id'
        ]);
        
        $ids = $validated['teacher_ids'];
        $blockedIds = [];
        $reasons = [];
        
        // 1. Check Course Assignments
        $teachersWithCourses = \App\Models\Course::whereIn('teacher_id', $ids)
            ->distinct()
            ->pluck('teacher_id')
            ->toArray();
            
        foreach ($teachersWithCourses as $id) {
            $blockedIds[$id] = true;
            $reasons[$id] = "Lehrer ist Kursen zugeordnet.";
        }
        
        // 2. Check Exam Assignments (Examiner) - Only check remaining IDs
        $idsToCheck = array_diff($ids, array_keys($blockedIds));
        if (!empty($idsToCheck)) {
            $teachersWithExams = \App\Models\StudentExam::whereIn('examiner_id', $idsToCheck)
                ->distinct()
                ->pluck('examiner_id')
                ->toArray();
                
            foreach ($teachersWithExams as $id) {
                $blockedIds[$id] = true;
                $reasons[$id] = "Lehrer ist Prüfungen als Prüfer zugeordnet.";
            }
        }
        
        // 3. Check Supervisions - Only check remaining IDs
        $idsToCheck = array_diff($ids, array_keys($blockedIds));
        if (!empty($idsToCheck)) {
            $teachersWithSupervisions = \App\Models\Supervision::whereIn('teacher_id', $idsToCheck)
                ->distinct()
                ->pluck('teacher_id')
                ->toArray();
                
            foreach ($teachersWithSupervisions as $id) {
                $blockedIds[$id] = true;
                $reasons[$id] = "Lehrer ist Aufsichten zugeordnet.";
            }
        }
        
        // 4. Delete the "Clean" Teachers
        $idsToDelete = array_diff($ids, array_keys($blockedIds));
        $deletedCount = 0;
        
        if (!empty($idsToDelete)) {
            // Disable foreign key checks to avoid Lock Wait Timeouts
            // We already manually checked the critical constraints (courses, exams) above.
            \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
            
            try {
                // Use smaller chunks (10) and explicit child deletion
                foreach (array_chunk($idsToDelete, 10) as $chunk) {
                    try {
                        \DB::transaction(function () use ($chunk, &$deletedCount) {
                             // 1. Delete Unavailabilities
                            \DB::table('teacher_unavailability')->whereIn('teacher_id', $chunk)->delete();
                            
                            // 2. Delete Teacher Subjects (Pivot)
                            \DB::table('teacher_subject')->whereIn('teacher_id', $chunk)->delete();
                            
                            // 3. Delete Template Assignments
                            \DB::table('supervision_template_assignments')
                               ->whereIn('teacher_id', $chunk)
                               ->update(['teacher_id' => null]);
                            
                            // 4. Finally delete the teachers
                            $deletedCount += Teacher::whereIn('id', $chunk)->delete();
                        });
                    } catch (\Exception $e) {
                         \Log::error("Bulk delete chunk failed: " . $e->getMessage());
                    }
                }
            } finally {
                // ALWAYS re-enable foreign keys
                \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
            }
            
            ActivityLogger::log(
                'teachers_bulk_deleted',
                'Teacher',
                null,
                "{$deletedCount} Lehrer wurden gelöscht"
            );
        }
        
        // 5. Build Response
        $response = redirect()->route('teachers.index');
        
        if ($deletedCount > 0) {
            $response->with('success', "{$deletedCount} Lehrkräfte wurden erfolgreich gelöscht.");
        }
        
        if (!empty($blockedIds)) {
            $totalBlocked = count($blockedIds);
            
            // Fetch names for better error messages
            $blockedTeachers = Teacher::whereIn('id', array_keys($blockedIds))->get()->keyBy('id');
            
            $errorDetails = [];
            $limit = 3;
            $i = 0;
            
            foreach ($blockedIds as $id => $_) {
                if ($i >= $limit) break;
                $name = $blockedTeachers[$id]->shortcode ?? "ID $id";
                $reason = $reasons[$id];
                $errorDetails[] = "$name: $reason";
                $i++;
            }
            
            $errorString = implode(' ', $errorDetails);
            if ($totalBlocked > $limit) {
                $errorString .= ' ... (und ' . ($totalBlocked - $limit) . ' weitere)';
            }
            
            $response->with('error', ($deletedCount > 0 ? "Einige Lehrer konnten nicht gelöscht werden: " : "") . $errorString);
        }
        
        return $response;
    }
    
    /**
     * Übersicht aller Lehrer-Abwesenheiten (für Modal + PDF)
     */
    public function unavailabilityOverview(Request $request)
    {
        $this->authorize('viewAny', Teacher::class);

        $examPeriodId = $request->input('exam_period_id');
        $periodService = app(\App\Services\CurrentExamPeriodService::class);
        $examPeriods  = $periodService->all();
        $activeExamPeriod = $examPeriodId
            ? $examPeriods->firstWhere('id', $examPeriodId)
            : $periodService->current();

        $unavailabilities = collect();
        if ($activeExamPeriod) {
            $unavailabilities = TeacherUnavailability::with('teacher')
                ->where('exam_period_id', $activeExamPeriod->id)
                ->orderBy('date')
                ->get()
                ->groupBy('teacher_id');
        }

        // Alle Lehrer laden (auch ohne Abwesenheiten, damit Übersicht vollständig)
        $teachers = Teacher::orderBy('shortcode')->get()->keyBy('id');

        return response()->json([
            'examPeriods'     => $examPeriods,
            'activeExamPeriod'=> $activeExamPeriod,
            'unavailabilities'=> $unavailabilities->map(fn($entries) => $entries->map(fn($e) => [
                'id'     => $e->id,
                'date'   => $e->date->format('d.m.Y'),
                'date_iso'=> $e->date->format('Y-m-d'),
                'weekday'=> $e->date->translatedFormat('D'),
                'reason' => $e->reason,
            ])),
            'teachers' => $teachers->map(fn($t) => [
                'id'        => $t->id,
                'shortcode' => $t->shortcode,
                'name'      => $t->lastname . ', ' . $t->firstname,
            ]),
        ]);
    }

    /**
     * PDF-Export aller Lehrer-Abwesenheiten für einen Prüfungszeitraum
     */
    public function exportUnavailabilityPdf(Request $request)
    {
        $this->authorize('viewAny', Teacher::class);

        $examPeriodId = $request->input('exam_period_id');
        $periodService = app(\App\Services\CurrentExamPeriodService::class);
        $examPeriods  = $periodService->all();
        $activeExamPeriod = $examPeriodId
            ? $examPeriods->firstWhere('id', $examPeriodId)
            : $periodService->current();

        $rows = collect();
        if ($activeExamPeriod) {
            // Alle Lehrer mit ihren Abwesenheiten - sortiert nach Lehrer-Kürzel, dann Datum
            $rows = TeacherUnavailability::with('teacher')
                ->where('exam_period_id', $activeExamPeriod->id)
                ->get()
                ->sortBy(fn($u) => [$u->teacher->shortcode ?? 'zzz', $u->date->format('Y-m-d')])
                ->values();
        }

        $html = view('teachers.unavailability-pdf', compact('activeExamPeriod', 'rows'))->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'abwesenheiten_'
            . ($activeExamPeriod ? str_replace([' ', '/'], ['_', '-'], strtolower($activeExamPeriod->name . '_' . $activeExamPeriod->year)) : 'alle')
            . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Verfügbarkeit eines Lehrers abrufen
     */
    public function getAvailability(Teacher $teacher, Request $request)
    {
        $examPeriod = $request->input('exam_period_id');
        
        $unavailabilities = TeacherUnavailability::where('teacher_id', $teacher->id)
            ->where('exam_period_id', $examPeriod)
            ->get();
        
        return response()->json($unavailabilities);
    }
    
    /**
     * Nicht-Verfügbarkeit setzen
     */
    public function setUnavailability(Request $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        
        $validated = $request->validate([
            // 'exam_periods' ist eine zentrale Tabelle (mysql-Connection), nicht tenant.
            // Nach tenancy()->initialize() ist die Default-Connection 'tenant', daher
            // muss die Connection explizit angegeben werden.
            'exam_period_id' => 'required|exists:exam_periods,id',
            'date' => 'required|date',
            'all_day' => 'boolean',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ]);
        
        $validated['teacher_id'] = $teacher->id;
        $validated['all_day'] = $request->boolean('all_day', true);
        
        $unavailability = TeacherUnavailability::create($validated);
        
        ActivityLogger::log(
            'teacher_unavailability_set',
            'TeacherUnavailability',
            $unavailability->id,
            "Nicht-Verfügbarkeit für {$teacher->shortcode} eingetragen"
        );
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'unavailability' => $unavailability
            ]);
        }

        return redirect()->route('teachers.timetable', $teacher)
            ->with('success', 'Abwesenheit wurde eingetragen.');
    }

    /**
     * Nicht-Verfügbarkeit löschen
     */
    public function destroyUnavailability(Teacher $teacher, TeacherUnavailability $unavailability)
    {
        $this->authorize('update', $teacher);

        // Sicherstellen, dass der Eintrag zur Lehrkraft gehört
        abort_if($unavailability->teacher_id !== $teacher->id, 403);

        $unavailability->delete();

        ActivityLogger::log(
            'teacher_unavailability_deleted',
            'TeacherUnavailability',
            $unavailability->id,
            "Nicht-Verfügbarkeit für {$teacher->shortcode} gelöscht"
        );

        return redirect()->route('teachers.timetable', $teacher)
            ->with('success', 'Abwesenheit wurde gelöscht.');
    }

    /**
     * Zeigt Import-Formular für Lehrer
     */
    public function showImportForm()
    {
        $this->authorize('create', Teacher::class);
        
        return view('teachers.import');
    }

    /**
     * Importiert Lehrer aus CSV
     */
    public function import(Request $request)
    {
        $this->authorize('create', Teacher::class);
        
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048'
        ]);
        
        try {
            $schoolId = auth()->user()->school_id;
            $filePath = $request->file('file')->getRealPath();
            
            $result = $this->importService->importTeachersFromCsv($schoolId, $filePath);
            
            $message = "Import erfolgreich: {$result['imported']} Lehrer importiert";
            
            if ($result['skipped'] > 0) {
                $message .= ", {$result['skipped']} übersprungen";
            }
            
            if (!empty($result['errors'])) {
                $message .= ". Fehler: " . implode(', ', $result['errors']);
            }
            
            return redirect()->route('teachers.index')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            return back()
                ->withErrors(['file' => 'Import fehlgeschlagen: ' . $e->getMessage()])
                ->withInput();
        }
    }
}
