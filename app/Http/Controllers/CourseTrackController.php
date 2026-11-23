<?php

namespace App\Http\Controllers;

use App\Models\CourseTrack;
use App\Services\CourseTrackDetectionService;
use App\Support\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseTrackController extends Controller
{
    protected $trackService;

    public function __construct(CourseTrackDetectionService $trackService)
    {
        $this->trackService = $trackService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $schoolYear = $request->get('school_year') ?: CourseTrack::currentSchoolYear();
        
        $tracks = CourseTrack::where('school_year', $schoolYear)
            ->get()
            ->groupBy('grade');

        $schoolYears = CourseTrack::schoolYearOptions(
            $schoolYear,
            CourseTrack::query()->distinct()->pluck('school_year')
        );

        return view('timetable.tracks.index', compact('tracks', 'schoolYear', 'schoolYears'));
    }

    /**
     * Show preview of detected tracks
     */
    public function detect(Request $request)
    {
        $schoolYear = $request->get('school_year') ?: CourseTrack::currentSchoolYear();
        
        // Detect tracks
        $detected = $this->trackService->detectTracksByGradeAndDay($schoolYear);
        
        return view('timetable.tracks.detect', compact('detected', 'schoolYear'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $schoolYear = $request->get('school_year') ?: CourseTrack::currentSchoolYear();
        
        return view('timetable.tracks.create', compact('schoolYear'));
    }

    /**
     * Store detected tracks OR manually created track
     */
    public function store(Request $request)
    {
        // Check if this is a manual create or batch import from detection
        if ($request->has('tracks')) {
            // Batch import from detection
            return $this->storeBatchTracks($request);
        } else {
            // Manual single track creation
            return $this->storeManualTrack($request);
        }
    }

    /**
     * Store a manually created track
     */
    protected function storeManualTrack(Request $request)
    {
        $validated = $request->validate([
            'school_year' => 'required|string',
            'grade' => 'required|string|in:Q12,Q13,JÜ',
            'name' => 'required|string|max:255',
            'course_codes_input' => 'required|string',
        ]);

        // Convert comma-separated string to array and trim whitespace
        $courseCodes = array_filter(array_map('trim', explode(',', $validated['course_codes_input'])));

        CourseTrack::create([
            'school_year' => $validated['school_year'],
            'grade' => $validated['grade'],
            'name' => $validated['name'],
            'course_codes' => array_values($courseCodes),
            'time_slots' => $this->decodeTimeSlotsJson($request),
        ]);

        return redirect()->route('timetable.tracks.index', ['school_year' => $validated['school_year']])
            ->with('success', 'Schiene erfolgreich erstellt.');
    }

    /**
     * Store detected tracks (batch)
     */
    protected function storeBatchTracks(Request $request)
    {
        $validated = $request->validate([
            'school_year' => 'required|string',
            'tracks' => 'required|array',
            'tracks.*.grade' => 'required|string',
            'tracks.*.name' => 'required|string',
            'tracks.*.course_codes' => 'required|array',
            'tracks.*.selected' => 'sometimes', // Allow 'selected' to pass through validation
            'tracks.*.time_slots' => 'sometimes', // Can be string (JSON) or array
        ]);

        // Clear existing tracks for this school year to avoid duplicates
        CourseTrack::where('school_year', $validated['school_year'])->delete();

        $savedCount = 0;

        foreach ($validated['tracks'] as $trackData) {
            if (!isset($trackData['selected'])) {
                continue;
            }
            
            // Decode time_slots if it came as a JSON string from hidden input
            $timeSlots = isset($trackData['time_slots']) 
                ? (is_string($trackData['time_slots']) ? json_decode($trackData['time_slots'], true) : $trackData['time_slots']) 
                : null;

            CourseTrack::create([
                'school_year' => $validated['school_year'],
                'grade' => $trackData['grade'],
                'name' => $trackData['name'],
                'course_codes' => $trackData['course_codes'],
                'time_slots' => $timeSlots,
            ]);
            
            $savedCount++;
        }

        return redirect()->route('timetable.tracks.index', ['school_year' => $validated['school_year']])
            ->with('success', $savedCount . ' Schienen erfolgreich gespeichert.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CourseTrack $track)
    {
        return view('timetable.tracks.edit', compact('track'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CourseTrack $track)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'course_codes_input' => 'required|string',
        ]);

        // Convert comma-separated string back to array and trim whitespace
        $courseCodes = array_filter(array_map('trim', explode(',', $validated['course_codes_input'])));

        $track->update([
            'name' => $validated['name'],
            'course_codes' => array_values($courseCodes),
            'time_slots' => $this->decodeTimeSlotsJson($request),
        ]);

        return redirect()->route('timetable.tracks.index')->with('success', 'Schiene aktualisiert.');
    }

    /**
     * Zeitfenster aus dem visuellen Raster lesen: Das Textfeld "time_slots_input"
     * ist in beiden Formularen (create/edit) readonly und dient nur der
     * lesbaren Anzeige — die eigentlichen Werte kommen ausschließlich aus dem
     * versteckten Feld "time_slots_json", das per JS aus dem Raster befüllt wird
     * (auch ohne JS steht es mit "[]" im Formular).
     */
    private function decodeTimeSlotsJson(Request $request): ?array
    {
        $json = $request->input('time_slots_json');

        if (empty($json)) {
            return null;
        }

        $timeSlots = json_decode($json, true);

        return !empty($timeSlots) ? $timeSlots : null;
    }

    /**
     * Import tracks from ASV export files (STDSCHIE.txt and STDKURSSCHIE.txt)
     */
    public function importFromAsv(Request $request)
    {
        $request->validate([
            'school_year' => ['required', 'string', 'regex:#^\d{4}/\d{2}$#'],
            'stdschie_file' => 'required|file|mimes:txt,csv',
            'stdkursschie_file' => 'required|file|mimes:txt,csv',
        ]);

        $schoolYear = $request->input('school_year');
        
        try {
            // Die hochgeladenen Dateien werden direkt vom Upload-Pfad gelesen —
            // ein Zwischenspeichern unter storage/app/temp (und Aufraeumen
            // danach) ist nicht noetig.
            $schieFullPath = $request->file('stdschie_file')->getRealPath();
            $kursSchieFullPath = $request->file('stdkursschie_file')->getRealPath();
            
            // Parse both files
            $schienen = $this->parseStdSchie($schieFullPath);
            $kursData = $this->parseStdKursSchie($kursSchieFullPath);
            
            // Merge and build tracks
            $tracks = $this->buildTracksFromAsv($schienen, $kursData, $schoolYear);
            
            // Ergibt die Datei fuer dieses Schuljahr keine Schiene, wird weder
            // geloescht noch "erfolgreich" gemeldet — sonst wuerde ein
            // falsch gewaehltes Schuljahr die vorhandenen Schienen leeren.
            if ($tracks === []) {
                $start = (int) substr($schoolYear, 0, 4);
                $q12Jahrgang = sprintf('%d/%02d', $start + 1, ($start + 2) % 100);

                return back()
                    ->withInput()
                    ->withErrors(['import' => "Die Dateien enthalten keine Schienen für das Schuljahr {$schoolYear} "
                        . "(gesucht: Abiturjahrgang {$schoolYear} als Q13 und {$q12Jahrgang} als Q12). "
                        . 'Bitte das Schuljahr prüfen. Es wurde nichts geändert.']);
            }
            
            // Bestehende Schienen dieses Schuljahres ersetzen — in einer
            // Transaktion, damit ein Fehler beim Schreiben nicht mit leerer
            // Tabelle endet.
            $savedCount = 0;
            \DB::transaction(function () use ($tracks, $schoolYear, &$savedCount) {
                \App\Models\CourseTrack::where('school_year', $schoolYear)->delete();

                foreach ($tracks as $track) {
                    \App\Models\CourseTrack::create($track);
                    $savedCount++;
                }
            });
            
            return redirect()
                ->route('timetable.tracks.index', ['school_year' => $schoolYear])
                ->with('success', "$savedCount Schienen erfolgreich aus ASV-Dateien importiert.");
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['import' => 'Import fehlgeschlagen: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Parse STDSCHIE.txt file
     */
    private function parseStdSchie($filePath)
    {
        $schienen = [];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            fgetcsv($handle); // Skip header
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 7) continue;
                
                $schienennummer = trim($row[3], '"');
                $abiturjahr = trim($row[6], '"');
                
                // Skip empty Schienennummer
                if (empty($schienennummer)) continue;
                
                $schienen[$abiturjahr][$schienennummer] = [
                    'nummer' => $schienennummer,
                    'bezeichnung' => trim($row[4], '"'),
                    'stunden' => trim($row[5], '"'),
                    'abiturjahr' => $abiturjahr,
                ];
            }
            
            fclose($handle);
        }
        
        return $schienen;
    }
    
    /**
     * Parse STDKURSSCHIE.txt file
     * Returns array with two keys:
     * - 'schienen': Courses assigned to tracks (with Schienennummer)
     * - 'ohne_schiene': Courses without track assignment (grouped by subject and grade)
     */
    private function parseStdKursSchie($filePath)
    {
        $zuordnungen = [];
        $ohneSchiene = []; // Courses without track assignment, grouped by [abiturjahr][subject]
        
        // Excluded subjects (lowercase for comparison)
        $excluded = ['mus', 'abo', 'tss', 'cho', 'dst', 'il', 'smw', 'md', 'dd'];
        
        // Special subjects that form their own tracks (practical/oral exams)
        // ins, voc, eko, itko, fko, spko: Combined into ONE track "Mündliche Prüfungen"
        // trs, ru: Each gets its own separate track
        // These subjects are filtered out from regular time-based tracks
        $specialSubjects = ['ins', 'voc', 'eko', 'itko', 'fko', 'spko', 'trs', 'ru'];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            fgetcsv($handle); // Skip header
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 11) continue;
                
                $schienennummer = trim($row[3], '"');
                $abiturjahr = trim($row[10], '"');
                $kursbezeichnung = trim($row[9], '"');
                $fach = strtolower(trim($row[6], '"'));
                
                // Skip excluded subjects
                if (in_array($fach, $excluded)) {
                    continue;
                }
                
                // Skip W-Seminare (pattern-based: contains "W-", "W_", "-W", or "_W")
                if (stripos($kursbezeichnung, 'W-') !== false || 
                    stripos($kursbezeichnung, 'W_') !== false ||
                    stripos($kursbezeichnung, '-W') !== false ||
                    stripos($kursbezeichnung, '_W') !== false) {
                    continue;
                }
                
                // Skip smw courses (pattern in course code)
                if (stripos($kursbezeichnung, 'smw') !== false) {
                    continue;
                }
                
                // Skip Differenzierungskurse (md, dd in course code)
                if (stripos($kursbezeichnung, 'md') !== false || stripos($kursbezeichnung, 'dd') !== false) {
                    continue;
                }
                
                // Handle special subjects (practical/oral exams) separately
                // These are filtered out from regular time-based tracks
                // regardless of whether they have a Schienennummer or not
                if (in_array($fach, $specialSubjects)) {
                    if (!isset($ohneSchiene[$abiturjahr][$fach])) {
                        $ohneSchiene[$abiturjahr][$fach] = [];
                    }
                    $ohneSchiene[$abiturjahr][$fach][] = $kursbezeichnung;
                    continue;
                }
                
                // Skip courses without Schienennummer (only for non-special subjects)
                if (empty($schienennummer)) {
                    continue;
                }
                
                // Regular courses with Schienennummer
                if (!isset($zuordnungen[$abiturjahr][$schienennummer])) {
                    $zuordnungen[$abiturjahr][$schienennummer] = [];
                }
                
                $zuordnungen[$abiturjahr][$schienennummer][] = $kursbezeichnung;
            }
            
            fclose($handle);
        }
        
        return [
            'schienen' => $zuordnungen,
            'ohne_schiene' => $ohneSchiene,
        ];
    }
    
    /**
     * Build CourseTrack entries from parsed ASV data
     * $kursData contains: ['schienen' => [...], 'ohne_schiene' => [...]]
     */
    private function buildTracksFromAsv($schienen, $kursData, $schoolYear)
    {
        $tracks = [];
        
        // Extract data
        $kursZuordnungen = $kursData['schienen'];
        $ohneSchiene = $kursData['ohne_schiene'];
        
        // Abiturjahr -> Stufe, bezogen auf das Schuljahr, das importiert wird:
        // Abiturjahr = Schuljahr -> Q13, Abiturjahr = Schuljahr + 1 -> Q12.
        // Alle anderen Jahrgaenge sind in diesem Schuljahr nicht in der Q-Phase.
        
        // Process regular tracks (with Schienennummer)
        foreach ($schienen as $abiturjahr => $schienenData) {
            $grade = CourseTrack::gradeForAbiturjahr((string) $abiturjahr, $schoolYear);
            if ($grade === null) continue;
            
            foreach ($schienenData as $nummer => $schieneInfo) {
                // Get courses for this Schiene
                $courses = $kursZuordnungen[$abiturjahr][$nummer] ?? [];
                
                if (empty($courses)) continue;
                
                // Get time slots from timetable
                $timeSlots = $this->getTimeSlotsForCourses($courses, $schoolYear);
                
                $tracks[] = [
                    'school_year' => $schoolYear,
                    'grade' => $grade,
                    'name' => $schieneInfo['bezeichnung'] ?: "Schiene $nummer",
                    'course_codes' => array_values($courses),
                    'time_slots' => $timeSlots,
                ];
            }
        }
        
        // Process special subjects (practical/oral exams)
        // ins, voc, eko, itko, fko, spko are combined into ONE track per grade: "Mündliche Prüfungen"
        // trs, ru each get their own separate track per grade
        foreach ($ohneSchiene as $abiturjahr => $subjectGroups) {
            $grade = CourseTrack::gradeForAbiturjahr((string) $abiturjahr, $schoolYear);
            if ($grade === null) continue;
            
            // Combine all conversation courses into one track
            $muendlicheCourses = [];
            foreach (['ins', 'voc', 'eko', 'itko', 'fko', 'spko'] as $muendlichFach) {
                if (isset($subjectGroups[$muendlichFach])) {
                    $muendlicheCourses = array_merge($muendlicheCourses, $subjectGroups[$muendlichFach]);
                }
            }
            
            if (!empty($muendlicheCourses)) {
                $timeSlots = $this->getTimeSlotsForCourses($muendlicheCourses, $schoolYear);
                $tracks[] = [
                    'school_year' => $schoolYear,
                    'grade' => $grade,
                    'name' => 'Mündliche Prüfungen',
                    'course_codes' => array_values($muendlicheCourses),
                    'time_slots' => $timeSlots,
                ];
            }
            
            // Process other special subjects individually (trs, ru)
            foreach ($subjectGroups as $subject => $courses) {
                // Skip conversation courses as they're already handled above
                if (in_array($subject, ['ins', 'voc', 'eko', 'itko', 'fko', 'spko'])) {
                    continue;
                }
                
                if (empty($courses)) continue;
                
                // Get time slots from timetable
                $timeSlots = $this->getTimeSlotsForCourses($courses, $schoolYear);
                
                // Generate track name based on subject
                $trackName = match(strtolower($subject)) {
                    'trs' => 'Türkisch',
                    'ru' => 'Russisch',
                    default => strtoupper($subject),
                };
                
                $tracks[] = [
                    'school_year' => $schoolYear,
                    'grade' => $grade,
                    'name' => $trackName,
                    'course_codes' => array_values($courses),
                    'time_slots' => $timeSlots,
                ];
            }
        }
        
        // Redistribute Leistungsfächer from LF track to their corresponding subject tracks
        $tracks = $this->redistributeLeistungsfaecher($tracks);
        
        // Split mixed foreign language tracks for Q12 oral exams (Ausbildungsabschnitt 12/2)
        // Original track stays intact, additional tracks created for mdl. and schriftl.
        $tracks = $this->splitMixedForeignLanguageTracks($tracks, $schoolYear);
        
        return $tracks;
    }

    /**
     * Redistribute Leistungsfächer (-L courses) to their corresponding subject tracks
     * and remove them from the LF track
     */
    private function redistributeLeistungsfaecher($tracks)
    {
        // Step 1: For each Leistungsfach, find where it should belong
        $lfToMove = []; // LFs that need to be added to their subject tracks
        $lfToRemove = []; // Track which LFs to remove from which tracks
        
        foreach ($tracks as $trackIndex => $track) {
            foreach ($track['course_codes'] as $course) {
                // Check if this is a Leistungsfach: ends with "-L" or "-L1", "-L2", etc.
                if (!preg_match('/-L\d*$/i', $course)) {
                    continue;
                }
                
                // Parse Leistungsfach (e.g., "1G-L" -> grade: "1", subject: "g")
                // Also handles "2E-L1", "2E-L2" -> grade: "2", subject: "e"
                if (!preg_match('/^(\d+)([A-Za-z]+)-L\d*$/i', $course, $matches)) {
                    continue;
                }
                
                $grade = $matches[1];
                $subject = strtolower($matches[2]);
                
                // Map course grade number to Q12/Q13
                // "1" in course code → Q12, "2" in course code → Q13
                $expectedTrackGrade = ($grade == '1') ? 'Q12' : 'Q13';
                
                // Find the subject track (track with grundlegende courses)
                $subjectTrackIndex = null;
                $lfAlreadyInSubjectTrack = false;
                
                foreach ($tracks as $otherIndex => $otherTrack) {
                    if ($otherIndex === $trackIndex) {
                        continue; // Skip current track
                    }
                    
                    // IMPORTANT: Track must have matching grade (Q12 for "1", Q13 for "2")
                    if ($otherTrack['grade'] !== $expectedTrackGrade) {
                        continue; // Skip tracks with wrong grade
                    }
                    
                    // Check if this track contains grundlegende courses of same subject
                    $hasGrundlegend = false;
                    foreach ($otherTrack['course_codes'] as $otherCourse) {
                        // Match pattern: 1e1, 1e2, etc. OR 3e1 (jahrgangsübergreifend)
                        // For Q12 (grade=1): Accept 1subject\d+ OR 3subject\d+
                        // For Q13 (grade=2): Accept 2subject\d+ OR 3subject\d+
                        $pattern = '/^(' . $grade . '|3)' . $subject . '\d+$/i';
                        if (preg_match($pattern, $otherCourse)) {
                            $hasGrundlegend = true;
                            break;
                        }
                    }
                    
                    if ($hasGrundlegend) {
                        $subjectTrackIndex = $otherIndex;
                        
                        // Check if LF is already in this track
                        if (in_array($course, $otherTrack['course_codes'])) {
                            $lfAlreadyInSubjectTrack = true;
                        }
                        
                        break; // Found the subject track, stop searching
                    }
                }
                
                // If subject track exists
                if ($subjectTrackIndex !== null) {
                    // If LF is not yet in subject track, mark for addition
                    if (!$lfAlreadyInSubjectTrack) {
                        $lfToMove[$course] = $subjectTrackIndex;
                    }
                    
                    // Always mark for removal from current track (e.g., LF-Schiene)
                    if (!isset($lfToRemove[$trackIndex])) {
                        $lfToRemove[$trackIndex] = [];
                    }
                    $lfToRemove[$trackIndex][] = $course;
                }
            }
        }
        
        // Step 2: Add LFs to their target tracks
        foreach ($lfToMove as $course => $targetIndex) {
            if (!in_array($course, $tracks[$targetIndex]['course_codes'])) {
                $tracks[$targetIndex]['course_codes'][] = $course;
            }
        }
        
        // Step 3: Remove LFs from source tracks
        foreach ($lfToRemove as $trackIndex => $coursesToRemove) {
            $tracks[$trackIndex]['course_codes'] = array_values(
                array_filter(
                    $tracks[$trackIndex]['course_codes'],
                    fn($c) => !in_array($c, $coursesToRemove)
                )
            );
        }
        
        // Step 4: Remove empty tracks
        $tracks = array_values(array_filter($tracks, fn($track) => !empty($track['course_codes'])));
        
        return $tracks;
    }
    
    /**
     * Get time slots for a set of courses from timetable entries
     * Only returns time slots where ALL courses in the track have lessons
     * Note: Leistungsfächer (-L, -L1, -L2) are excluded from this check as they have additional hours
     */
    private function getTimeSlotsForCourses($courses, $schoolYear)
    {
        if (empty($courses)) return null;
        
        // Separate Leistungsfächer from regular courses
        // Only check for common time slots among regular courses
        // Leistungsfächer can be "-L", "-L1", "-L2", etc.
        $regularCourses = array_filter($courses, fn($c) => !preg_match('/-L\d*$/i', $c));
        
        if (empty($regularCourses)) {
            // If only Leistungsfächer, we can't determine common slots
            return null;
        }
        
        // Get all timetable entries for these courses
        $entries = \App\Models\TimetableEntry::where('school_year', $schoolYear)
            ->whereIn('course_code', $courses)
            ->get();
        
        if ($entries->isEmpty()) return null;
        
        // Group by day and period
        $schedule = [];
        foreach ($entries as $entry) {
            $key = $entry->day_of_week . '-' . $entry->period;
            if (!isset($schedule[$key])) {
                $schedule[$key] = [
                    'day' => $entry->day_of_week,
                    'period' => $entry->period,
                    'courses' => [],
                ];
            }
            $schedule[$key]['courses'][] = $entry->course_code;
        }
        
        // Find time slots where ALL REGULAR courses are present (common lessons)
        // Leistungsfächer have additional hours, so we ignore them for this check
        $totalRegularCourses = count($regularCourses);
        $timeSlots = [];
        
        foreach ($schedule as $slot) {
            // Count unique REGULAR courses in this slot (exclude Leistungsfächer)
            $regularCoursesInSlot = array_filter(
                array_unique($slot['courses']),
                fn($c) => !preg_match('/-L\d*$/i', $c)
            );
            
            // Only include if ALL regular courses from the track are present
            if (count($regularCoursesInSlot) >= $totalRegularCourses) {
                $dayShort = match($slot['day']) {
                    1 => 'Mo',
                    2 => 'Di',
                    3 => 'Mi',
                    4 => 'Do',
                    5 => 'Fr',
                    default => 'N/A',
                };
                
                // Group consecutive periods by day
                $dayKey = $slot['day'];
                if (!isset($timeSlots[$dayKey])) {
                    $timeSlots[$dayKey] = [
                        'day_short' => $dayShort,
                        'periods' => [],
                    ];
                }
                $timeSlots[$dayKey]['periods'][] = $slot['period'];
            }
        }
        
        // Sort periods within each day
        foreach ($timeSlots as &$slot) {
            sort($slot['periods']);
        }
        
        return array_values($timeSlots) ?: null;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseTrack $track)
    {
        $track->delete();

        return back()->with('success', 'Schiene gelöscht.');
    }
    /**
     * Move a course from one track to another
     */
    public function moveCourse(Request $request)
    {
        $validated = $request->validate([
            'source_track_id' => 'required|exists:course_tracks,id',
            'target_track_id' => 'required|exists:course_tracks,id',
            'course_code' => 'required|string',
        ]);

        $sourceTrack = CourseTrack::findOrFail($validated['source_track_id']);
        $targetTrack = CourseTrack::findOrFail($validated['target_track_id']);
        $courseCode = $validated['course_code'];

        // Remove from source
        $sourceCodes = $sourceTrack->course_codes;
        if (($key = array_search($courseCode, $sourceCodes)) !== false) {
            unset($sourceCodes[$key]);
            $sourceTrack->course_codes = array_values($sourceCodes);
            $sourceTrack->save();
        } else {
            return response()->json(['success' => false, 'message' => 'Course not found in source track'], 404);
        }

        // Add to target if not exists
        $targetCodes = $targetTrack->course_codes;
        if (!in_array($courseCode, $targetCodes)) {
            $targetCodes[] = $courseCode;
            $targetTrack->course_codes = array_values($targetCodes);
            // Recalculate timeslots for target track? 
            // Ideally yes, but for now we just move it. 
            // The user might want to manually check timeslots later or we can try to update them.
            // Let's stick to just moving for now as requested.
            $targetTrack->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Split mixed foreign language tracks for Q12 oral exams (Ausbildungsabschnitt 12/2)
     * 
     * ONLY for Q12 (grade = 'Q12'):
     * - Original track stays intact (all courses)
     * - If track contains BOTH modern foreign languages AND other subjects:
     *   - Create additional track with "(mdl.)" suffix → only modern FL courses
     *   - Create additional track with "(schriftl.)" suffix → only other courses
     * - If track contains ONLY modern foreign languages: no split needed
     * 
     * Modern foreign languages (oral exams): e, f, it, sp (lowercase) + E, F, IT, SP (uppercase)
     * Classical languages (written exams): l, L, gr (NOT split)
     * 
     * @param array $tracks
     * @param string $schoolYear
     * @return array
     */
    private function splitMixedForeignLanguageTracks($tracks, $schoolYear)
    {
        $additionalTracks = [];
        
        // Modern foreign languages patterns
        // Lowercase: e, f, it, sp (e.g., "1e1", "1f2", "1it1", "1sp1")
        // Uppercase: E, F, IT, SP (e.g., "1E-L", "1F-L1", "1IT-L2", "1SP-L")
        $modernForeignLanguages = ['e', 'f', 'it', 'sp', 'E', 'F', 'IT', 'SP'];
        
        foreach ($tracks as $track) {
            // Only process Q12 tracks (12/2 = 2. Halbjahr with oral exams)
            if ($track['grade'] !== 'Q12') {
                continue;
            }
            
            // Categorize courses
            $modernFLCourses = [];
            $otherCourses = [];
            
            foreach ($track['course_codes'] as $courseCode) {
                $isModernFL = false;
                
                // Check lowercase pattern: digit(s) + subject + digit(s)
                // e.g., "1e1", "1f2", "1it1", "1sp1"
                if (preg_match('/^\d+([a-z]+)\d+$/i', $courseCode, $matches)) {
                    $subject = strtolower($matches[1]);
                    if (in_array($subject, array_map('strtolower', $modernForeignLanguages))) {
                        $isModernFL = true;
                    }
                }
                
                // Check uppercase pattern: digit(s) + SUBJECT + "-L" + optional digit
                // e.g., "1E-L", "1F-L1", "1IT-L", "1SP-L2"
                if (preg_match('/^\d+([A-Z]+)-L\d*$/i', $courseCode, $matches)) {
                    $subject = strtoupper($matches[1]);
                    if (in_array($subject, array_map('strtoupper', $modernForeignLanguages))) {
                        $isModernFL = true;
                    }
                }
                
                if ($isModernFL) {
                    $modernFLCourses[] = $courseCode;
                } else {
                    $otherCourses[] = $courseCode;
                }
            }
            
            // Only split if track has BOTH modern FL AND other courses (mixed track)
            if (!empty($modernFLCourses) && !empty($otherCourses)) {
                // Create track for oral exams (mdl.)
                $timeSlotsMdl = $this->getTimeSlotsForCourses($modernFLCourses, $schoolYear);
                $additionalTracks[] = [
                    'school_year' => $schoolYear,
                    'grade' => $track['grade'],
                    'name' => $track['name'] . ' (mdl.)',
                    'course_codes' => $modernFLCourses,
                    'time_slots' => $timeSlotsMdl,
                ];
                
                // Create track for written exams (schriftl.)
                $timeSlotsSchriftl = $this->getTimeSlotsForCourses($otherCourses, $schoolYear);
                $additionalTracks[] = [
                    'school_year' => $schoolYear,
                    'grade' => $track['grade'],
                    'name' => $track['name'] . ' (schriftl.)',
                    'course_codes' => $otherCourses,
                    'time_slots' => $timeSlotsSchriftl,
                ];
            }
            // If track has ONLY modern FL or ONLY other courses: no split needed
            // Original track stays as is
        }
        
        // Merge original tracks with additional split tracks
        return array_merge($tracks, $additionalTracks);
    }

}


