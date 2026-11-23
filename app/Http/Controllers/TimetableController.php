<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\ExamScheduleEntry;
use App\Models\ExamSchedulePeriod;
use App\Models\SchoolEvent;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Services\KlausurenplanZeitraeumeService;
use App\Services\TimetableImportPreviewService;
use App\Support\SchoolYear;
use App\Services\TimetableImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TimetableController extends Controller
{
    protected TimetableImportService $timetableImportService;

    public function __construct(TimetableImportService $timetableImportService)
    {
        $this->timetableImportService = $timetableImportService;
    }
    /**
     * Show timetable import page
     */
    public function view(Request $request)
    {
        $schoolYear = $request->get('school_year') ?: SchoolYear::current();
        $className = $request->get('class', 'Q12');
        $teacherCode = $request->get('teacher');
        
        $query = TimetableEntry::with('teacher')->where('school_year', $schoolYear);
        
        if ($teacherCode) {
            $query->whereHas('teacher', function($q) use ($teacherCode) {
                $q->where('shortcode', $teacherCode);
            });
            $title = "Stundenplan: $teacherCode";
        } else {
            $query->where('class_name', $className);
            $title = "Stundenplan: $className";
        }
        
        $entries = $query->orderBy('day_of_week')
            ->orderBy('period')
            ->get()
            ->groupBy('day_of_week');
        
        // Get available classes and teachers for filter
        $classes = TimetableEntry::where('school_year', $schoolYear)
            ->distinct()
            ->pluck('class_name')
            ->sort()
            ->values();
            
        $teachers = TimetableEntry::where('school_year', $schoolYear)
            ->distinct()
            ->pluck('teacher_id');
            
        $teachers = Teacher::whereIn('id', $teachers)
            ->pluck('shortcode')
            ->sort()
            ->values();
        
        $days = [
            1 => 'Montag',
            2 => 'Dienstag', 
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag'
        ];

        $maxPeriod = TimetableEntry::where('school_year', $schoolYear)->max('period') ?? 12;

        $subjectCodeMap = Subject::query()
            ->get(['name', 'code'])
            ->mapWithKeys(fn($subject) => [mb_strtolower($subject->name) => $subject->code])
            ->toArray();

        return view('timetable.view', compact(
            'entries',
            'days',
            'maxPeriod',
            'schoolYear',
            'className',
            'teacherCode',
            'classes',
            'teachers',
            'title',
            'subjectCodeMap'
        ));
    }

    /**
     * Show upload form
     */
    public function upload()
    {
        return view('timetable.upload');
    }

    /**
     * Preview timetable import with validation
     */
    public function preview(Request $request)
    {
        $request->validate([
            'timetable_file' => 'required|file|mimes:txt,csv|max:10240',
            'teachers_file' => 'nullable|file|mimes:txt,csv|max:2048',
            'school_year' => 'required|string|max:10',
            'only_oberstufe' => 'sometimes|boolean',
        ]);

        $timetableFile = $request->file('timetable_file');
        $teachersFile = $request->file('teachers_file');
        $schoolYear = $request->input('school_year') ?: SchoolYear::current();
        $onlyOberstufe = $request->boolean('only_oberstufe');

        // Store files temporarily for preview
        $timetablePath = $timetableFile->storeAs('temp', 'timetable_preview_' . uniqid() . '.txt');
        $timetableFullPath = storage_path('app/' . $timetablePath);

        $teachersPath = null;
        $teachersFullPath = null;
        
        if ($teachersFile) {
            $teachersPath = $teachersFile->storeAs('temp', 'teachers_preview_' . uniqid() . '.csv');
            $teachersFullPath = storage_path('app/' . $teachersPath);
        }

        try {
            $previewService = new TimetableImportPreviewService();
            
            // Parse ASV teacher details first if provided (for enrichment)
            if ($teachersFullPath) {
                $previewService->parseTeacherDetails($teachersFullPath);
            }
            
            // Parse timetable - this defines which teachers are "active"
            $previewData = $previewService->parseFile($timetableFullPath, $schoolYear, $onlyOberstufe);

            // Store preview data in session for later import
            session([
                'timetable_preview' => [
                    'timetable_path' => $timetablePath,
                    'teachers_path' => $teachersPath,
                    'school_year' => $schoolYear,
                    'only_oberstufe' => $onlyOberstufe,
                    'data' => $previewData,
                ]
            ]);

            return redirect()->route('timetable.preview.show');

        } catch (\Exception $e) {
            // Clean up on error
            if (Storage::exists($timetablePath)) {
                Storage::delete($timetablePath);
            }
            if ($teachersPath && Storage::exists($teachersPath)) {
                Storage::delete($teachersPath);
            }

            return back()
                ->withInput()
                ->withErrors(['preview' => 'Vorschau fehlgeschlagen: ' . $e->getMessage()]);
        }
    }

    /**
     * Show preview page (GET)
     */
    public function showPreview()
    {
        $previewSession = session('timetable_preview');

        if (!$previewSession) {
            return redirect()->route('timetable.upload');
        }

        return view('timetable.preview', [
            'preview' => $previewSession['data'],
            'schoolYear' => $previewSession['school_year'],
            'onlyOberstufe' => $previewSession['only_oberstufe'],
        ]);
    }

    /**
     * Confirm and execute import from preview
     */
    public function confirmImport(Request $request)
    {
        $request->validate([
            'truncate' => 'sometimes|boolean',
            'clear_teachers' => 'sometimes|boolean',
        ]);

        $truncate = $request->boolean('truncate');
        $clearTeachers = $request->boolean('clear_teachers');

        // Get preview data from session
        $previewSession = session('timetable_preview');
        
        if (!$previewSession) {
            return redirect()
                ->route('timetable.upload')
                ->withErrors(['import' => 'Keine Vorschau-Daten gefunden. Bitte laden Sie die Datei erneut hoch.']);
        }

        $timetablePath = storage_path('app/' . $previewSession['timetable_path']);
        $teachersPath = $previewSession['teachers_path'] 
            ? storage_path('app/' . $previewSession['teachers_path']) 
            : null;
        $schoolYear = $previewSession['school_year'];
        $previewData = $previewSession['data'];

        try {
            $t0 = microtime(true);
            Log::info('[Import] Start', ['memory_mb' => round(memory_get_usage(true)/1024/1024)]);

            DB::beginTransaction();

            // Get teacher edits and deletions from session
            $teacherEdits = $previewSession['teacher_edits'] ?? [];
            $teacherDeletions = $previewSession['teacher_deletions'] ?? [];
            Log::info('[Import] Session geladen', ['entries' => count($previewData['entries']), 'teachers' => count($previewData['active_teachers']), 'ms' => round((microtime(true)-$t0)*1000)]);

            // 0. Clear teachers table if requested (only keeps teachers with shortcode from timetable)
            if ($clearTeachers) {
                // Get list of teacher shortcodes that should be kept (from timetable data)
                $activeTeacherCodes = collect($previewData['active_teachers'])
                    ->pluck('code')
                    ->diff($teacherDeletions) // Exclude deleted teachers
                    ->toArray();
                
                // Get IDs of teachers to be deleted
                $teachersToDelete = Teacher::whereNotIn('shortcode', $activeTeacherCodes)->pluck('id');
                
                if ($teachersToDelete->isNotEmpty()) {
                    // 1. Delete TimetableEntries for these teachers
                    TimetableEntry::whereIn('teacher_id', $teachersToDelete)->delete();
                    
                    // 2. Delete StudentExams where these teachers are examiners
                    // AND StudentExams for courses led by these teachers
                    $coursesToDelete = Course::whereIn('teacher_id', $teachersToDelete)->pluck('id');
                    
                    StudentExam::whereIn('examiner_id', $teachersToDelete)
                        ->orWhereIn('course_id', $coursesToDelete)
                        ->delete();
                        
                    // 3. Delete Courses led by these teachers
                    Course::whereIn('teacher_id', $teachersToDelete)->delete();
                    
                    // 4. Finally delete the teachers
                    Teacher::whereIn('id', $teachersToDelete)->delete();
                }
            }

            // 1. Import or Update teachers
            $teachersImported = 0;
            $teachersUpdated  = 0;
            $subjectSyncQueue = []; // Subject-Sync nach dem Commit durchführen (cross-DB, nicht in Transaktion)

            if (!empty($previewData['active_teachers'])) {
                // Alle existierenden Teacher in einer Query voraufladen (vermeidet N+1)
                $existingTeachers = Teacher::all()->keyBy('shortcode');

                foreach ($previewData['active_teachers'] as $teacherData) {
                    $teacherCode = $teacherData['code'];

                    if (in_array($teacherCode, $teacherDeletions)) {
                        continue;
                    }

                    $edits = $teacherEdits[$teacherCode] ?? null;

                    $firstname = $teacherCode;
                    $lastname  = 'N/A';
                    $position  = null;

                    if ($edits && ($edits['firstname'] || $edits['lastname'])) {
                        $firstname = $edits['firstname'] ?: $teacherCode;
                        $lastname  = $edits['lastname']  ?: 'N/A';
                        $position  = $edits['position']  ?? null;
                    } elseif ($teacherData['name']) {
                        $nameParts = explode(',', $teacherData['name'], 2);
                        $lastname  = trim($nameParts[0] ?? 'N/A');
                        $firstname = trim($nameParts[1] ?? $teacherCode);
                        $position  = $teacherData['position'] ?? null;
                    }

                    $employmentType = $edits['employment_type'] ?? $teacherData['employment_type'] ?? null;
                    $hours          = isset($teacherData['hours']) ? (float) $teacherData['hours'] : null;
                    $subjectsRaw    = $edits['subjects'] ?? $teacherData['subjects'] ?? null;

                    if ($teacherData['exists_in_db']) {
                        if ($teacherData['name'] || $edits) {
                            $teacher = $existingTeachers->get($teacherCode);
                            if ($teacher) {
                                $teacher->update([
                                    'firstname'       => $firstname,
                                    'lastname'        => $lastname,
                                    'title'           => $position,
                                    'employment_type' => $employmentType ?: null,
                                    'hours'           => $hours,
                                ]);
                                if ($subjectsRaw) {
                                    $subjectSyncQueue[$teacher->id] = $subjectsRaw;
                                }
                            }
                            $teachersUpdated++;
                        }
                    } else {
                        $teacher = Teacher::create([
                            'shortcode'                    => $teacherCode,
                            'firstname'                    => $firstname,
                            'lastname'                     => $lastname,
                            'title'                        => $position,
                            'employment_type'              => $employmentType ?: null,
                            'hours'                        => $hours,
                            'can_supervise'                => true,
                            'is_available_for_supervision' => true,
                        ]);
                        if ($subjectsRaw) {
                            $subjectSyncQueue[$teacher->id] = $subjectsRaw;
                        }
                        $teachersImported++;
                    }
                }
            }

            Log::info('[Import] Teachers fertig', ['updated' => $teachersUpdated, 'created' => $teachersImported, 'ms' => round((microtime(true)-$t0)*1000)]);

            // 2.-4. Stundenplan schreiben. Die Abbildung einer geparsten
            // Zeile auf eine Datenbankzeile steht seit dem 28.08.2026 einmal
            // im TimetableImportService -- der kombinierte Import und der
            // Kommandozeilen-Weg rufen dieselbe Stelle. Vorher gab es eine
            // zweite Fassung in ImportTimetable.php, und die war falsch.
            $deletions = array_values(array_map(
                static fn ($code): string => (string) $code,
                $teacherDeletions
            ));

            $written = $this->timetableImportService->storeEntries(
                $previewData['entries'],
                $schoolYear,
                $truncate,
                $deletions
            );

            $imported = $written['imported'];
            $skipped  = $written['skipped'];

            Log::info('[Import] Stundenplan geschrieben', [
                'imported'        => $imported,
                'skipped'         => $skipped,
                'without_teacher' => $written['without_teacher'],
                'ms'              => round((microtime(true) - $t0) * 1000),
            ]);

            DB::commit();
            Log::info('[Import] Commit fertig', ['ms' => round((microtime(true)-$t0)*1000)]);

            // Subject-Sync NACH dem Commit (cross-DB: zentrale Subjects ↔ Tenant teacher_subject)
            if (!empty($subjectSyncQueue)) {
                $teachersById = Teacher::whereIn('id', array_keys($subjectSyncQueue))->get()->keyBy('id');
                foreach ($subjectSyncQueue as $teacherId => $subjectsRaw) {
                    $teacher = $teachersById->get($teacherId);
                    if ($teacher) {
                        $this->syncTeacherSubjectsFromAsv($teacher, $subjectsRaw);
                    }
                }
                Log::info('[Import] Subject-Sync fertig', ['count' => count($subjectSyncQueue), 'ms' => round((microtime(true)-$t0)*1000)]);
            }

            // Clean up temp files
            if ($timetablePath && file_exists($timetablePath)) {
                unlink($timetablePath);
            }
            if ($teachersPath && file_exists($teachersPath)) {
                unlink($teachersPath);
            }
            session()->forget('timetable_preview');

            // Klartext statt HTML. Die Flash-Meldung wird mit {{ }} ausgegeben
            // und damit escaped -- ein <br> stand woertlich auf der Seite
            // (Fund beim Durchklicken am 28.08.2026).
            $teile = [];

            if ($clearTeachers) {
                $teile[] = 'Lehrer-Tabelle bereinigt';
            }

            if ($teachersImported > 0) {
                $teile[] = "{$teachersImported} Lehrkräfte neu angelegt";
            }

            if ($teachersUpdated > 0) {
                $teile[] = "{$teachersUpdated} Lehrkräfte aktualisiert";
            }

            if (!empty($teacherDeletions)) {
                $teile[] = count($teacherDeletions) . ' Lehrkräfte ausgeschlossen';
            }

            $teile[] = "{$imported} Stundenplaneinträge importiert";

            if ($skipped > 0) {
                $teile[] = "{$skipped} Einträge übersprungen";
            }

            if ($written['without_teacher'] > 0) {
                $teile[] = "{$written['without_teacher']} davon ohne Lehrerzuordnung";
            }

            return redirect()
                ->route('teachers.index')
                ->with('success', 'Import abgeschlossen: ' . implode(', ', $teile) . '.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up on error
            if ($timetablePath && file_exists($timetablePath)) {
                unlink($timetablePath);
            }
            if ($teachersPath && file_exists($teachersPath)) {
                unlink($teachersPath);
            }

            return back()
                ->withErrors(['import' => 'Import fehlgeschlagen: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancel import and clean up
     */
    public function cancelImport()
    {
        $previewSession = session('timetable_preview');
        
        if ($previewSession && isset($previewSession['timetable_path'])) {
            $timetablePath = storage_path('app/' . $previewSession['timetable_path']);
            if (file_exists($timetablePath)) {
                unlink($timetablePath);
            }
        }
        
        if ($previewSession && isset($previewSession['teachers_path'])) {
            $teachersPath = storage_path('app/' . $previewSession['teachers_path']);
            if (file_exists($teachersPath)) {
                unlink($teachersPath);
            }
        }

        session()->forget('timetable_preview');

        return redirect()
            ->route('timetable.upload')
            ->with('info', 'Import abgebrochen.');
    }

    /**
     * Update preview session with teacher edits and deletions
     */
    public function updatePreviewSession(Request $request)
    {
        $previewSession = session('timetable_preview');
        
        if (!$previewSession) {
            return response()->json(['success' => false, 'message' => 'No preview session found'], 404);
        }

        // Store teacher edits and deletions in session
        $previewSession['teacher_edits'] = $request->input('teacher_edits', []);
        $previewSession['teacher_deletions'] = $request->input('teacher_deletions', []);

        session(['timetable_preview' => $previewSession]);

        return response()->json(['success' => true]);
    }

    /**
     * Show exam schedule (Klausurenplan) for Q12/Q13
     */
    public function examSchedule(Request $request)
    {
        $schoolYear = $request->input('school_year');
        if (!$schoolYear) {
            $schoolYear = $request->session()->get('timetable.school_year') ?: SchoolYear::current();
        }
        $request->session()->put('timetable.school_year', $schoolYear);

        // Die vier Zeiträume (Q12/1, Q12/2, Q13/1, Q13/2) gibt es jedes Jahr und
        // ergeben sich aus dem Terminkalender. Ein neues Schuljahr hat noch keine;
        // sie werden beim ersten Aufruf angelegt, nicht von Hand eingetragen.
        $fehlendeTermine = [];
        if (!ExamSchedulePeriod::forSchoolYear($schoolYear)->exists()) {
            $zeitraeume = app(KlausurenplanZeitraeumeService::class);
            $zeitraeume->anlegen($schoolYear);
            $fehlendeTermine = $zeitraeume->ableiten($schoolYear)['fehlend'];
        }

        $sessionKey = 'timetable.exam_period.' . $schoolYear;
        $periodName = $request->input('period');

        if (!$periodName) {
            $periodName = $request->session()->get($sessionKey);
        }

        // Get exam period
        $query = ExamSchedulePeriod::forSchoolYear($schoolYear);
        
        if ($periodName) {
            $query->where('name', $periodName);
        } else {
            $query->active();
        }
        
        $period = $query->first();

        // Get ALL available periods for this school year for the dropdown
        $availablePeriods = ExamSchedulePeriod::forSchoolYear($schoolYear)
            ->orderBy('start_date')
            ->get();

        if (!$period) {
            // No period found - show empty state
            return view('timetable.exam-schedule', [
                'period' => null,
                'weeks' => [],
                'schoolYear' => $schoolYear,
                'periodName' => $periodName,
                'courseTracks' => collect(),
                'availablePeriods' => $availablePeriods,
                'fehlendeTermine' => $fehlendeTermine,
            ]);
        }

        $request->session()->put($sessionKey, $period->name);
        
        // Detect course tracks (Schienen) - load from saved tracks in DB
        // Separate for Q12 and Q13, but only show relevant grade based on period
        $savedTracks = \App\Models\CourseTrack::where('school_year', $schoolYear)->get();
        
        // Filter tracks based on period name (Q12/1, Q12/2 → Q12, Q13/1, Q13/2 → Q13)
        $relevantGrade = $this->relevantGradeForPeriod($periodName, $period);
        
        // Helper to parse day from time slot (string "Mo 1." or array ['day_short' => 'Mo', ...])
        $getDayFromSlot = function($slot) {
            $dayStr = '';
            if (is_array($slot)) {
                $dayStr = $slot['day_short'] ?? '';
            } else {
                $dayStr = (string) $slot;
            }
            
            $dayStr = trim($dayStr);
            if (str_starts_with($dayStr, 'Mo')) return 1;
            if (str_starts_with($dayStr, 'Di')) return 2;
            if (str_starts_with($dayStr, 'Mi')) return 3;
            if (str_starts_with($dayStr, 'Do')) return 4;
            if (str_starts_with($dayStr, 'Fr')) return 5;
            return null;
        };
        
        // Helper to format slot as string
        $formatSlot = function($slot) {
            if (is_string($slot)) return $slot;
            if (is_array($slot) && isset($slot['day_short']) && isset($slot['periods'])) {
                $periods = $slot['periods'];
                sort($periods);
                if (count($periods) === 0) return $slot['day_short'];
                if (count($periods) === 1) return $slot['day_short'] . ' ' . $periods[0] . '.';
                return $slot['day_short'] . ' ' . $periods[0] . './' . end($periods);
            }
            return '';
        };

        // Load all exam entries for this period to assume what is already planned
        $existingExamEntries = ExamScheduleEntry::where('exam_schedule_period_id', $period->id)->get();
        
        // Flatten course codes from all existing entries into a single lookup array
        // (Kurzarbeiten zählen nicht: eine Schiene mit Kurzarbeit braucht trotzdem noch ihre Klausur)
        $assignedCourses = [];
        foreach ($existingExamEntries as $entry) {
            if ($entry->exam_type === 'kurzarbeit') {
                continue;
            }
            foreach ($entry->course_codes as $code) {
                 $assignedCourses[$code] = true;
            }
        }

        // Structure tracks by Grade -> Day -> ['tracks' => []]
        $courseTracks = collect();
        // Alle Schienen der Jahrgangsstufe, auch bereits verplante — Auswahl für „+ Kurzarbeit"
        $kurzarbeitSchienen = collect();

        foreach ($savedTracks as $track) {
            // Filter by relevant grade if set
            if ($relevantGrade && $track->grade !== $relevantGrade && $track->grade !== 'JÜ') {
                continue;
            }

            // Check if track is already fully assigned
            $allAssigned = true;
            if (is_array($track->course_codes)) {
                foreach ($track->course_codes as $course) {
                    if (!isset($assignedCourses[$course])) {
                        $allAssigned = false;
                        break;
                    }
                }
            }
            // Determine valid days from time_slots
            $validDays = [];
            $formattedSlots = [];
            $firstPeriod = null; // We need a fallback period for the 'assignTrack' validation
            $dayPeriodRanges = []; // Map Day -> ['start' => ..., 'end' => ...]
            $timeSlots = $track->time_slots ?? [];
            
            // Check if this is a track without specific time slots
            // (e.g., "Mündliche Schulaufgaben Fremdsprachen", "Türkisch", "Russisch")
            $hasNoTimeSlots = empty($timeSlots);
            
            if ($hasNoTimeSlots) {
                // Track without time slots - allow assignment on any day
                // Set all weekdays as valid
                $validDays = [1, 2, 3, 4, 5]; // Mo-Fr
                $firstPeriod = 0; // Default period 0 for flexible tracks
                $formattedSlots[] = '(flexible Zeiten)';
            } else {
                // Normal track with time slots
                foreach ($timeSlots as $slot) {
                    // Determine Day and all Periods for this slot
                    $currentSlotPeriods = [];
                    if (is_array($slot) && !empty($slot['periods'])) {
                        // Array format: ['day_short' => 'Do', 'periods' => [3, 4]]
                        $currentSlotPeriods = $slot['periods'];
                    } elseif (is_string($slot)) {
                        // String format: "Do 3./4." or "Do 3-5" or "Mo 1."
                        // Extract all standalone numbers (not part of day abbreviation)
                        // Match pattern: number followed by optional dot, slash, or dash
                        if (preg_match_all('/\b(\d+)(?:[\.\/\-]|$)/', $slot, $matches)) {
                            $currentSlotPeriods = array_map('intval', $matches[1]);
                            $currentSlotPeriods = array_unique($currentSlotPeriods);
                            sort($currentSlotPeriods);
                        }
                    }

                    // Parse first period if not set yet (global fallback)
                    if ($firstPeriod === null && !empty($currentSlotPeriods)) {
                        $firstPeriod = min($currentSlotPeriods);
                    }

                    $day = $getDayFromSlot($slot);
                    if ($day && !empty($currentSlotPeriods)) {
                        $validDays[] = $day;
                        
                        // Store the start and end period for this day
                        $minPeriod = min($currentSlotPeriods);
                        $maxPeriod = max($currentSlotPeriods);
                        
                        if (!isset($dayPeriodRanges[$day])) {
                            $dayPeriodRanges[$day] = ['start' => $minPeriod, 'end' => $maxPeriod];
                        } else {
                            // If there are multiple slots for the same day, expand the range
                            $dayPeriodRanges[$day]['start'] = min($dayPeriodRanges[$day]['start'], $minPeriod);
                            $dayPeriodRanges[$day]['end'] = max($dayPeriodRanges[$day]['end'], $maxPeriod);
                        }
                    }
                    $formattedSlots[] = $formatSlot($slot);
                }
            }
            $validDays = array_unique($validDays);

            // Enhance track object for View
            $trackForView = $track->toArray();
            $trackForView['number'] = $track->id; // Use ID as number
            $trackForView['period'] = $firstPeriod ?? 0; // Default to 0 if no period found (fallback)
            $trackForView['day_period_ranges'] = $dayPeriodRanges; // Map: Day -> ['start' => ..., 'end' => ...]
            $trackForView['all_time_slots'] = implode(', ', array_filter($formattedSlots));
            $trackForView['valid_days'] = array_values($validDays);
            
            // Keep ALL courses for assignment (drag & drop will use this)
            $trackForView['courses'] = $track->course_codes;
            
            // Filter courses for DISPLAY based on relevantGrade
            // For jahrgangsübergreifende tracks (JÜ), only show relevant courses for the current grade
            $filteredCourses = $track->course_codes;
            if ($relevantGrade === 'Q12') {
                // Q12 view: Show only courses starting with "1" (Q12) or "3" (jahrgangsübergreifend)
                $filteredCourses = array_filter($track->course_codes, function($course) {
                    return str_starts_with($course, '1') || str_starts_with($course, '3');
                });
            } elseif ($relevantGrade === 'Q13') {
                // Q13 view: Show only courses starting with "2" (Q13) or "3" (jahrgangsübergreifend)
                $filteredCourses = array_filter($track->course_codes, function($course) {
                    return str_starts_with($course, '2') || str_starts_with($course, '3');
                });
            }
            
            // Skip this track if no relevant courses for the current grade
            if (empty($filteredCourses)) {
                continue;
            }
            
            $trackForView['courses_display'] = array_values($filteredCourses); // Re-indexed for display only

            $kurzarbeitSchienen->push($trackForView);

            // Bereits vollständig verplante Schienen erscheinen nicht mehr in der Seitenleiste
            if ($allAssigned) {
                continue;
            }

            // Add track to the Grade collection (ONCE)
            // We use a single generic bucket 'all' because the view iterates over groups but doesn't show group headers
            if (!$courseTracks->has($track->grade)) {
                $courseTracks->put($track->grade, collect([
                    'all' => ['tracks' => collect()]
                ]));
            }

            // Add to the 'all' bucket
            $courseTracks->get($track->grade)['all']['tracks']->push($trackForView);
        }

        // Get all weeks in this period
        $weeks = $this->generateWeeks($period);

        // Load all exam entries for this period, grouped by date
        $examEntries = $this->examEntriesForPeriod($period);

        // Load GLOBAL calendar events (Ferien, Feiertage) - applies to ALL schools
        [$allGlobalEvents, $globalEvents] = $this->globalEventsForPeriod($period);

        // Load SCHOOL-SPECIFIC events (Exkursionen, Veranstaltungen)
        $allSchoolEvents = $this->schoolEventsForExamSchedule($schoolYear, $period, $relevantGrade);
            
        $schoolEvents = $allSchoolEvents->groupBy(function ($event) {
                return $event->start_date->format('Y-m-d');
            });

        // Combine data into week structure
        foreach ($weeks as &$week) {
            // Check if entire week is holiday/vacation (including adjacent public holidays);
            // sets $week['is_full_week_event']/['full_week_event_data'] and, if not a full week
            // event, resets $week['days'] = [] to be filled below (see markFullWeekEvent()).
            if (!$this->markFullWeekEvent($week, $allGlobalEvents)) {
                continue;
            }

            for ($d = 0; $d < 5; $d++) { // Monday to Friday
                $date = $week['start']->copy()->addDays($d);
                $dateKey = $date->format('Y-m-d');
                
                // Get events that start on this specific day
                $dayGlobalEvents = $globalEvents[$dateKey] ?? collect();
                
                // ALSO check for multi-day events that cover this day (but don't start on this day)
                $coveringEvents = $allGlobalEvents->filter(function ($event) use ($date, $dateKey) {
                    // Skip events that already start on this day (they're already in $dayGlobalEvents)
                    if ($event->event_date->format('Y-m-d') === $dateKey) {
                        return false;
                    }
                    
                    // Event covers this day if:
                    // - it has an end_date AND
                    // - it starts before this day AND
                    // - it ends on or after this day
                    return $event->end_date && 
                           $event->event_date < $date && 
                           $event->end_date >= $date;
                });
                
                // Merge both collections and remove duplicates
                $allDayGlobalEvents = $dayGlobalEvents->merge($coveringEvents)->unique('id');
                
                // Check for public holidays that are adjacent to vacation periods
                // and replace them with the vacation event
                $publicHolidays = $allDayGlobalEvents->filter(fn($e) => $e->event_type === 'public_holiday');
                if ($publicHolidays->isNotEmpty()) {
                    foreach ($publicHolidays as $holiday) {
                        // Check if there's a vacation on the day before or day after
                        $dayBefore = $date->copy()->subDay();
                        $dayAfter = $date->copy()->addDay();
                        
                        $adjacentVacation = $allGlobalEvents->first(function($event) use ($dayBefore, $dayAfter) {
                            if ($event->event_type !== 'holiday') {
                                return false;
                            }
                            
                            // Check if vacation covers the day before or day after
                            $vacationStart = $event->event_date;
                            $vacationEnd = $event->end_date ?? $event->event_date;
                            
                            // Check if vacation overlaps with [dayBefore, dayAfter] range
                            return ($vacationStart <= $dayAfter && $vacationEnd >= $dayBefore);
                        });
                        
                        if ($adjacentVacation) {
                            // Remove the public holiday and add the vacation event instead
                            $allDayGlobalEvents = $allDayGlobalEvents->reject(fn($e) => $e->id === $holiday->id);
                            $allDayGlobalEvents = $allDayGlobalEvents->push($adjacentVacation)->unique('id');
                        }
                    }
                }
                
                // --- School Events Logic ---
                $daySchoolEvents = $schoolEvents[$dateKey] ?? collect();
                
                // Check for multi-day school events that cover this day
                $coveringSchoolEvents = $allSchoolEvents->filter(function ($event) use ($date, $dateKey) {
                    // Skip events that already start on this day
                    if ($event->start_date->format('Y-m-d') === $dateKey) {
                        return false;
                    }
                    
                    // Event covers this day if:
                    // - it has an end_date AND
                    // - it starts before this day AND
                    // - it ends on or after this day
                    return $event->end_date && 
                           $event->start_date < $date && 
                           $event->end_date >= $date;
                });
                
                $allDaySchoolEvents = $daySchoolEvents->merge($coveringSchoolEvents)->unique('id');
                
                $week['days'][] = [
                    'date' => $date,
                    'exams' => $examEntries[$dateKey] ?? collect(),
                    'global_events' => $allDayGlobalEvents,
                    'school_events' => $allDaySchoolEvents,
                    'isWeekend' => $date->isWeekend(),
                ];
            }
        }

        return view('timetable.exam-schedule', [
            'period' => $period,
            'weeks' => $weeks,
            'schoolYear' => $schoolYear,
            'periodName' => $periodName,
            'courseTracks' => $courseTracks,
            'kurzarbeitSchienen' => $kurzarbeitSchienen->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'availablePeriods' => $availablePeriods,
        ]);
    }

    /**
     * Generate weeks array for the period
     */
    private function generateWeeks(ExamSchedulePeriod $period): array
    {
        $weeks = [];
        $current = $period->start_date->copy()->startOfWeek();
        $end = $period->end_date->copy()->endOfWeek();

        while ($current <= $end) {
            $weekStart = $current->copy();
            $weekEnd = $current->copy()->addDays(4); // Friday (not Sunday!)
            
            // Format: "13.10. – 17.10."
            $label = $weekStart->format('d.m.') . ' – ' . $weekEnd->format('d.m.');
            
            $weeks[] = [
                'start' => $weekStart,
                'end' => $weekEnd,
                'label' => $label,
                'week_number' => $weekStart->weekOfYear,
                'days' => [], // Will be filled later
                'is_full_week_event' => false,
            ];
            
            $current->addWeek();
        }

        return $weeks;
    }

    /**
     * Check if entire week is covered by an event (e.g., vacation)
     * Also considers public holidays that are adjacent to vacations
     */
    private function checkFullWeekEvent(array $week, $allGlobalEvents): ?\App\Models\GlobalCalendarEvent
    {
        $weekStart = $week['start']; // Monday
        $weekEnd = $week['start']->copy()->addDays(4); // Friday (not Sunday!)

        // Iterate over all events (not grouped by date)
        foreach ($allGlobalEvents as $event) {
            if ($event->event_type !== 'holiday') {
                continue;
            }
            
            $vacationStart = $event->event_date;
            $vacationEnd = $event->end_date ?? $event->event_date;
            
            // Check if vacation alone covers the entire workweek (Mon-Fri)
            if ($vacationStart <= $weekStart && $vacationEnd >= $weekEnd) {
                return $event;
            }
            
            // Check if vacation + adjacent public holidays cover the entire week
            // Example: Pfingstmontag + Pfingstferien (Tue-Fri) = full week
            
            // Find public holidays on days adjacent to this vacation
            $dayBeforeVacation = $vacationStart->copy()->subDay();
            $dayAfterVacation = $vacationEnd->copy()->addDay();
            
            $hasHolidayBefore = $allGlobalEvents->contains(function($e) use ($dayBeforeVacation) {
                return $e->event_type === 'public_holiday' && 
                       $e->event_date->isSameDay($dayBeforeVacation);
            });
            
            $hasHolidayAfter = $allGlobalEvents->contains(function($e) use ($dayAfterVacation) {
                return $e->event_type === 'public_holiday' && 
                       $e->event_date->isSameDay($dayAfterVacation);
            });
            
            // Extend vacation period if there are adjacent holidays
            $effectiveStart = $hasHolidayBefore ? $dayBeforeVacation : $vacationStart;
            $effectiveEnd = $hasHolidayAfter ? $dayAfterVacation : $vacationEnd;
            
            // Check if extended period covers the entire workweek
            if ($effectiveStart <= $weekStart && $effectiveEnd >= $weekEnd) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Show teacher availability for Kolloquium planning
     */
    public function teacherAvailability()
    {
        // Get all teachers with Q12/Q13 courses (via teacher relationship)
        $teachers = TimetableEntry::oberstufe()
            ->join('teachers', 'timetable_entries.teacher_id', '=', 'teachers.id')
            ->select('teachers.shortcode')
            ->distinct()
            ->orderBy('teachers.shortcode')
            ->pluck('shortcode');

        return view('timetable.teacher-availability', compact('teachers'));
    }

    /**
     * Get teacher schedule via AJAX
     */
    public function getTeacherSchedule(string $teacherCode)
    {
        $schedule = TimetableEntry::getTeacherSchedule($teacherCode);
        
        return response()->json([
            'teacher_code' => $teacherCode,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Show detailed view for a specific class
     */
    public function classSchedule(string $className)
    {
        $schedule = TimetableEntry::getClassSchedule($className);
        
        return view('timetable.class-schedule', compact('className', 'schedule'));
    }
    
    /**
     * Manage school events (Exkursionen, Veranstaltungen)
     */
    public function manageEvents(Request $request)
    {
        $schoolYear = $request->input('school_year') ?: SchoolYear::current();
        
        $events = SchoolEvent::forSchoolYear($schoolYear)
            ->orderBy('start_date')
            ->get();
        
        return view('timetable.events.index', compact('events', 'schoolYear'));
    }
    
    /**
     * Store a new school event
     */
    public function storeEvent(Request $request)
    {
        $validated = $request->validate([
            'school_year' => 'required|string|max:10',
            'name' => 'required|string|max:255',
            'type' => 'required|in:veranstaltung,exkursion,pruefung,sonstiges',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'blocks_exams' => 'boolean',
            'affected_grades' => 'nullable|array',
            'affected_grades.*' => 'in:Q12,Q13',
        ]);
        
        SchoolEvent::create($validated);
        
        return redirect()
            ->route('timetable.events.index', ['school_year' => $validated['school_year']])
            ->with('success', 'Termin wurde erfolgreich angelegt.');
    }
    
    /**
     * Update a school event
     */
    public function updateEvent(Request $request, SchoolEvent $event)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:veranstaltung,exkursion,pruefung,sonstiges',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'blocks_exams' => 'boolean',
            'affected_grades' => 'nullable|array',
            'affected_grades.*' => 'in:Q12,Q13',
        ]);
        
        $event->update($validated);
        
        return redirect()
            ->route('timetable.events.index', ['school_year' => $event->school_year])
            ->with('success', 'Termin wurde erfolgreich aktualisiert.');
    }
    
    /**
     * Delete a school event
     */
    public function deleteEvent(SchoolEvent $event)
    {
        $schoolYear = $event->school_year;
        $event->delete();
        
        return redirect()
            ->route('timetable.events.index', ['school_year' => $schoolYear])
            ->with('success', 'Termin wurde erfolgreich gelöscht.');
    }
    
    /**
     * Assign a course track (Schiene) to an exam date
     * Creates exam entries for all courses in the track
     */
    public function assignTrack(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:exam_schedule_periods,id',
            'track' => 'required|array',
            'track.courses' => 'required|array',
            'track.number' => 'required',
            'track.period' => 'required',
            'track.day_period_ranges' => 'sometimes|array',
            'track.all_time_slots' => 'required|string',
            'exam_date' => 'required|date',
            'exam_end_date' => 'nullable|date|after_or_equal:exam_date',
            'exam_type' => 'nullable|in:schriftlich,muendlich,kolloquium,kurzarbeit',
            'is_conflict' => 'boolean',
        ]);
        $examType = $validated['exam_type'] ?? 'schriftlich';
        $istKurzarbeit = $examType === 'kurzarbeit';
        
        $period = ExamSchedulePeriod::findOrFail($validated['period_id']);
        $track = $validated['track'];
        $examDate = $validated['exam_date'];
        $isConflict = $validated['is_conflict'] ?? false;
        
        // Determine relevant grade from period name
        $relevantGrade = null;
        if (str_starts_with($period->name, 'Q12')) {
            $relevantGrade = 'Q12';
        } elseif (str_starts_with($period->name, 'Q13')) {
            $relevantGrade = 'Q13';
        }
        
        // Filter courses based on relevant grade (same logic as in examSchedule)
        $coursesToSave = $track['courses'];
        if ($relevantGrade === 'Q12') {
            // Q12: Only save courses starting with "1" or "3"
            $coursesToSave = array_filter($track['courses'], function($course) {
                return str_starts_with($course, '1') || str_starts_with($course, '3');
            });
        } elseif ($relevantGrade === 'Q13') {
            // Q13: Only save courses starting with "2" or "3"
            $coursesToSave = array_filter($track['courses'], function($course) {
                return str_starts_with($course, '2') || str_starts_with($course, '3');
            });
        }
        $coursesToSave = array_values($coursesToSave); // Re-index array
        
        // If no courses left after filtering, return error
        if (empty($coursesToSave)) {
            return response()->json([
                'success' => false,
                'message' => 'Keine relevanten Kurse für diesen Prüfungszeitraum',
            ], 400);
        }
        
        // Determine start_period and end_period based on the day of week
        $dayOfWeek = \Carbon\Carbon::parse($examDate)->dayOfWeekIso; // 1=Monday, 7=Sunday
        $dayPeriodRanges = $track['day_period_ranges'] ?? [];
        
        if (isset($dayPeriodRanges[$dayOfWeek])) {
            // Use the period range for this specific day
            $startPeriod = $dayPeriodRanges[$dayOfWeek]['start'];
            $endPeriod = $dayPeriodRanges[$dayOfWeek]['end'];
        } else {
            // Fallback: use the generic period
            $startPeriod = $track['period'];
            $endPeriod = $track['period'];
        }
        
        $createdCount = 0;
        
        // ==========================================
        // COLLISION DETECTION
        // Je Kurs und Zeitraum eine Klausur (bzw. mdl./prakt. Prüfung) und
        // höchstens eine Kurzarbeit. Beide zählen getrennt; am selben Tag
        // darf ein Kurs aber nie zweimal stehen.
        // ==========================================
        $conflictingCourses = [];
        ExamScheduleEntry::where('exam_schedule_period_id', $period->id)
            ->get()
            ->each(function($entry) use ($coursesToSave, $istKurzarbeit, $examDate, &$conflictingCourses) {
                $gleicheArt = ($entry->exam_type === 'kurzarbeit') === $istKurzarbeit;
                $gleicherTag = $entry->exam_date->isSameDay(\Carbon\Carbon::parse($examDate))
                    || ($entry->exam_end_date && \Carbon\Carbon::parse($examDate)->between($entry->exam_date, $entry->exam_end_date));
                if (!$gleicheArt && !$gleicherTag) {
                    return;
                }

                foreach ($coursesToSave as $course) {
                    if (in_array($course, $entry->course_codes) && !isset($conflictingCourses[$course])) {
                        $conflictingCourses[$course] = [
                            'date' => $entry->exam_date,
                            'type' => $entry->exam_type,
                        ];
                    }
                }
            });
        
        // If there are conflicts, return error with details
        if (!empty($conflictingCourses)) {
            $conflictDetails = collect($conflictingCourses)->map(function($info, $course) {
                $date = \Carbon\Carbon::parse($info['date'])->format('d.m.Y');
                $type = match ($info['type']) {
                    'muendlich' => 'mündlich',
                    'kolloquium' => 'Kolloquium',
                    'kurzarbeit' => 'Kurzarbeit',
                    default => 'schriftlich',
                };
                return "$course ($type am $date)";
            })->join(', ');
            
            return response()->json([
                'success' => false,
                'message' => "Kollision: Folgende Kurse sind bereits zugewiesen: $conflictDetails",
                'conflicting_courses' => array_keys($conflictingCourses),
            ], 409); // 409 Conflict
        }
        
        // No conflicts - proceed with creating the entry
        $notes = null;
        if ($isConflict) {
            $notes = '⚠️ Konflikt: Kein Unterricht an diesem Wochentag!';
        }
        
        // Extract subject from FIRST course code (e.g., 1m1 -> m, 3inf1 -> inf)
        $firstCourse = $coursesToSave[0];
        $subject = preg_replace('/^[0-9]+([a-z]+).*$/i', '$1', $firstCourse);
        
        ExamScheduleEntry::create([
            'exam_schedule_period_id' => $period->id,
            'exam_date' => $examDate,
            'exam_end_date' => $istKurzarbeit ? null : ($validated['exam_end_date'] ?? null),
            'exam_type' => $examType,
            // time_slot is computed from start_period and end_period - don't set it manually!
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'course_codes' => $coursesToSave, // Model will implode to string
            'subject' => $subject,
            'notes' => $notes,
        ]);
        $createdCount = 1;
        
        return response()->json([
            'success' => true,
            'message' => "$createdCount Kurse zugewiesen",
            'created' => $createdCount,
            'skipped' => 0,
            'has_conflict' => $isConflict,
        ]);
    }

    /**
     * Delete an exam assignment
     */
    public function destroyAssignment(ExamScheduleEntry $entry)
    {
        $entry->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Prüfungstermin erfolgreich gelöscht.');
    }

    /**
     * Move an exam assignment to a different date
     */
    public function moveAssignment(Request $request, ExamScheduleEntry $entry)
    {
        $validated = $request->validate([
            'new_date' => 'required|date',
        ]);

        $newDate = \Carbon\Carbon::parse($validated['new_date']);
        
        // ==========================================
        // COLLISION DETECTION: Check if moving would create conflicts
        // ==========================================
        $coursesToMove = $entry->course_codes;
        
        $conflictingCourses = [];
        ExamScheduleEntry::where('exam_schedule_period_id', $entry->exam_schedule_period_id)
            ->where('exam_date', $newDate)
            ->where('id', '!=', $entry->id) // Exclude current entry
            ->get()
            ->each(function($existingEntry) use ($coursesToMove, &$conflictingCourses) {
                $existingCourses = $existingEntry->course_codes;
                
                foreach ($coursesToMove as $course) {
                    if (in_array($course, $existingCourses)) {
                        if (!isset($conflictingCourses[$course])) {
                            $conflictingCourses[$course] = [
                                'type' => $existingEntry->exam_type,
                            ];
                        }
                    }
                }
            });
        
        // If there are conflicts, return error
        if (!empty($conflictingCourses)) {
            $conflictDetails = collect($conflictingCourses)->map(function($info, $course) use ($newDate) {
                $date = $newDate->format('d.m.Y');
                $type = $info['type'] === 'muendlich' ? 'mündlich' : ($info['type'] === 'kolloquium' ? 'Kolloquium' : 'schriftlich');
                return "$course ($type bereits am $date)";
            })->join(', ');
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Kollision: $conflictDetails",
                    'conflicting_courses' => array_keys($conflictingCourses),
                ], 409);
            }
            
            return back()->withErrors(['error' => "Kollision: $conflictDetails"]);
        }

        $entry->update([
            'exam_date' => $newDate,
        ]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Prüfungstermin erfolgreich verschoben.');
    }

    /**
     * Toggle highlight status of an exam assignment
     */
    public function toggleHighlight(ExamScheduleEntry $entry)
    {
        $entry->update([
            'is_highlighted' => !$entry->is_highlighted
        ]);
        
        return response()->json([
            'success' => true,
            'is_highlighted' => $entry->is_highlighted
        ]);
    }

    /**
     * Update notes/comments for an exam entry
     */
    public function updateNotes(Request $request, ExamScheduleEntry $entry)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);
        
        $entry->update([
            'notes' => $validated['notes']
        ]);
        
        return response()->json([
            'success' => true,
            'notes' => $entry->notes
        ]);
    }

    /**
     * Export Exam Schedule as PDF
     */
    public function exportExamSchedulePdf(Request $request)
    {
        $schoolYear = $request->input('school_year') ?: SchoolYear::current();
        $periodName = $request->input('period', null);

        // Get exam period
        $query = ExamSchedulePeriod::forSchoolYear($schoolYear);
        
        if ($periodName) {
            $query->where('name', $periodName);
        } else {
            $query->active();
        }
        
        $period = $query->first();

        if (!$period) {
            return redirect()->back()->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        // Get all weeks in this period
        $weeks = $this->generateWeeks($period);

        // Load all exam entries for this period, grouped by date
        $examEntries = $this->examEntriesForPeriod($period);

        // Load GLOBAL calendar events
        [$allGlobalEvents, $globalEvents] = $this->globalEventsForPeriod($period);

        // Filter tracks based on period name (Q12/1, Q12/2 → Q12, Q13/1, Q13/2 → Q13)
        $relevantGrade = $this->relevantGradeForPeriod($periodName, $period);

        // Load SCHOOL-SPECIFIC events (Fetch all first, handle day-check in loop)
        $allSchoolEvents = $this->schoolEventsForExamSchedule($schoolYear, $period, $relevantGrade);

        // Combine data into week structure
        foreach ($weeks as &$week) {
            if (!$this->markFullWeekEvent($week, $allGlobalEvents)) {
                continue;
            }

            // POPULATE DAYS
            for ($d = 0; $d < 5; $d++) { // Monday to Friday
                $date = $week['start']->copy()->addDays($d);
                $dateKey = $date->format('Y-m-d');
                
                // Get GLOBAL events for this day (including multi-day)
                $dayGlobalEvents = $globalEvents[$dateKey] ?? collect();
                $coveringGlobalEvents = $allGlobalEvents->filter(function ($event) use ($date, $dateKey) {
                    if ($event->event_date->format('Y-m-d') === $dateKey) {
                        return false;
                    }
                    return $event->end_date && 
                           $event->event_date < $date && 
                           $event->end_date >= $date;
                });
                $currentGlobalEvents = $dayGlobalEvents->merge($coveringGlobalEvents)->unique('id');

                // Get SCHOOL events for this day (including multi-day)
                $currentSchoolEvents = $allSchoolEvents->filter(function ($event) use ($date) {
                    return $event->isOnDate($date);
                });
                
                $week['days'][] = [
                    'date' => $date,
                    'date_string' => $dateKey,
                    'exams' => $examEntries[$dateKey] ?? collect(),
                    'global_events' => $currentGlobalEvents,
                    'school_events' => $currentSchoolEvents,
                    'isWeekend' => $date->isWeekend(),
                ];
            }
        }
    
    // DEBUG: Check data structure
    // dd($weeks);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('timetable.exports.exam-schedule-pdf', [
            'period' => $period,
            'weeks' => $weeks,
            'schoolYear' => $schoolYear,
        ]);

        $pdf->setPaper('a4', 'landscape');

        $filename = 'Klausurplan_' . str_replace(['/', '\\'], '_', $period->name) . '_' . date('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Leitet aus dem Periodennamen (Q12/1, Q12/2, Q13/1, Q13/2), hilfsweise aus
     * dem geladenen Zeitraum, ab, welche Jahrgangsstufe gerade betrachtet wird.
     * Wird sowohl fuer die Schienen-Anzeige als auch fuer die Schultermine-
     * Filterung in examSchedule() und exportExamSchedulePdf() gebraucht.
     */
    private function relevantGradeForPeriod(?string $periodName, ?ExamSchedulePeriod $period): ?string
    {
        if ($periodName && str_starts_with($periodName, 'Q12')) {
            return 'Q12';
        }
        if ($periodName && str_starts_with($periodName, 'Q13')) {
            return 'Q13';
        }
        if ($period) {
            if (str_starts_with($period->name, 'Q12')) {
                return 'Q12';
            }
            if (str_starts_with($period->name, 'Q13')) {
                return 'Q13';
            }
        }

        return null;
    }

    /**
     * Schulinterne Termine (SchoolEvent) fuer den Klausurenplan-Zeitraum, nur
     * die Termine, die die aktuell betrachtete Jahrgangsstufe betreffen.
     * Leere/fehlende affected_grades bedeutet laut Verwaltungsmaske "alle
     * Jahrgangsstufen". Gemeinsame Grundlage fuer examSchedule() (HTML) und
     * exportExamSchedulePdf() (PDF) - beide zeigten vor dem 22.09.2026
     * unabhaengig voneinander denselben ungefilterten Datensatz.
     */
    private function schoolEventsForExamSchedule(string $schoolYear, ExamSchedulePeriod $period, ?string $relevantGrade)
    {
        return SchoolEvent::forSchoolYear($schoolYear)
            ->betweenDates($period->start_date, $period->end_date)
            ->whereNotIn('type', ['ferien', 'feiertag'])
            ->get()
            ->filter(function ($event) use ($relevantGrade) {
                return $relevantGrade === null
                    || empty($event->affected_grades)
                    || in_array($relevantGrade, $event->affected_grades, true);
            })
            ->values();
    }

    /**
     * Prüfungstermine (ExamScheduleEntry) für den Zeitraum, gruppiert nach Datum
     * (mehrtägige Prüfungen erscheinen unter jedem ihrer Tage). Gemeinsame
     * Grundlage für examSchedule() (HTML) und exportExamSchedulePdf() (PDF).
     */
    private function examEntriesForPeriod(ExamSchedulePeriod $period)
    {
        $allExamEntries = ExamScheduleEntry::where('exam_schedule_period_id', $period->id)
            ->with('period')
            ->orderBy('exam_date')
            ->get();

        $examEntries = collect();
        foreach ($allExamEntries as $entry) {
            $dates = $entry->getExamDates(); // Returns array of all dates covered
            foreach ($dates as $date) {
                $dateKey = $date->format('Y-m-d');
                if (!$examEntries->has($dateKey)) {
                    $examEntries[$dateKey] = collect();
                }
                $examEntries[$dateKey]->push($entry);
            }
        }

        return $examEntries;
    }

    /**
     * Globale Kalenderereignisse (Ferien, Feiertage) für den Zeitraum - gilt für
     * alle Schulen. Gibt [$allGlobalEvents, $globalEventsByDate] zurück: die
     * ungruppierte Collection (für Ganzwochen-/mehrtägige Prüfung eines Tages)
     * sowie die nach Datum gruppierte Collection (für die Tagesanzeige).
     * Gemeinsame Grundlage für examSchedule() (HTML) und exportExamSchedulePdf() (PDF).
     */
    private function globalEventsForPeriod(ExamSchedulePeriod $period): array
    {
        $globalEventsCollection = \App\Models\GlobalCalendarEvent::betweenDates($period->start_date, $period->end_date)
            ->whereIn('event_type', ['holiday', 'public_holiday'])
            ->get();

        $allGlobalEvents = $globalEventsCollection;
        $globalEvents = $globalEventsCollection->groupBy(function ($event) {
            return $event->event_date->format('Y-m-d');
        });

        return [$allGlobalEvents, $globalEvents];
    }

    /**
     * Markiert eine Woche als Ganzwochen-Ferien/Feiertag, falls zutreffend
     * (setzt $week['is_full_week_event'] und $week['full_week_event_data']).
     * Setzt in jedem Fall $week['days'] = [] zurück, damit der Aufrufer die Tage
     * neu befüllt. Gibt false zurück, wenn die Woche komplett abgedeckt ist (der
     * Aufrufer soll dann die Tagesbefüllung überspringen), sonst true.
     * Gemeinsame Grundlage für examSchedule() (HTML) und exportExamSchedulePdf() (PDF).
     */
    private function markFullWeekEvent(array &$week, $allGlobalEvents): bool
    {
        $fullWeekEvent = $this->checkFullWeekEvent($week, $allGlobalEvents);
        $week['is_full_week_event'] = $fullWeekEvent !== null;
        $week['full_week_event_data'] = $fullWeekEvent;
        $week['days'] = [];

        return $fullWeekEvent === null;
    }

    /**
     * Synchronisiert die Subjects (Fakultas) einer Lehrkraft anhand der ASV-Rohdaten.
     *
     * Beispiel-Format ASV: "D - GY, F - GY"
     * → Codes extrahieren: ["D", "F"]
     * → Matching Subjects aus der zentralen DB holen
     * → teacher_subject-Pivot syncen (bestehende Einträge bleiben erhalten, neue werden ergänzt)
     */
    private function syncTeacherSubjectsFromAsv(Teacher $teacher, string $subjectsRaw): void
    {
        if (empty(trim($subjectsRaw))) {
            return;
        }

        // Fächer aufteilen: "D - GY, F - GY" → ["D - GY", "F - GY"]
        $parts = array_map('trim', explode(',', $subjectsRaw));

        $codes = [];
        foreach ($parts as $part) {
            // Nur den Fachcode vor dem " - " nehmen
            $code = trim(explode('-', $part)[0]);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        if (empty($codes)) {
            return;
        }

        // Subject-IDs aus der zentralen DB holen (case-insensitive)
        $subjectIds = Subject::whereIn('code', $codes)->pluck('id')->toArray();

        if (empty($subjectIds)) {
            return;
        }

        // Pivot syncen ohne detach – vorhandene Zuordnungen bleiben erhalten
        $teacher->subjects()->syncWithoutDetaching($subjectIds);
    }
}
