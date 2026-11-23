<?php

namespace App\Services;

use App\Models\TimetableEntry;
use App\Support\SchoolYear;
use Illuminate\Support\Collection;

/**
 * Service to detect course tracks (Schienen) - courses that run parallel in time
 * 
 * A "track" is a group of courses that have lessons at the same time slots,
 * which means they could have exams at the same time without conflicts.
 */
class CourseTrackDetectionService
{
    /**
     * Detect all course tracks for a given school year and grades
     * 
     * @param string|null $schoolYear e.g., "2026/27"; null = laufendes Schuljahr
     * @param array $grades e.g., ["Q12", "Q13"]
     * @param int $minCoursesPerTrack Minimum number of courses to form a track
     * @return Collection Collection of tracks with their metadata
     */
    public function detectTracks(
        ?string $schoolYear = null,
        array $grades = ['Q12', 'Q13'],
        int $minCoursesPerTrack = 2
    ): Collection {
        $schoolYear ??= SchoolYear::current();

        // Subjects that don't write exams
        // ins, eko, voc, itko, fko, spko removed - these are conversation courses with practical exams
        // They form a separate track "Mündliche Prüfungen"
        $excludedSubjects = ['smw'];
        
        $query = TimetableEntry::where('school_year', $schoolYear)
            ->whereNotIn('subject', $excludedSubjects);
            
        // Filter by grade based on course code prefix
        // 1... = Q12, 2... = Q13, 3... = Cross-grade (Jahrgangsübergreifend)
        if (in_array('Q12', $grades) && !in_array('Q13', $grades)) {
            $query->where('course_code', 'LIKE', '1%');
        } elseif (in_array('Q13', $grades) && !in_array('Q12', $grades)) {
            $query->where('course_code', 'LIKE', '2%');
        } elseif (in_array('JÜ', $grades)) {
            // For JÜ, we want to see the "full picture" of that time slot.
            // So we fetch Q12 (1%), Q13 (2%), and JÜ (3%) courses.
            // We'll filter later to ensure we only keep tracks that *actually contain* a JÜ course.
            $query->where(function($q) {
                $q->where('course_code', 'LIKE', '1%')
                  ->orWhere('course_code', 'LIKE', '2%')
                  ->orWhere('course_code', 'LIKE', '3%');
            });
        } else {
            // Fallback
            $query->whereIn('class_name', $grades);
        }

            
        // Get all timetable entries
        $entries = $query->orderBy('day_of_week')
            ->orderBy('period')
            ->get();
        
        // Filter out W-Seminare (courses ending with 'W', 'W1', 'W2', etc.)
        $entries = $entries->reject(function ($entry) {
            $courseCode = strtoupper($entry->course_code);
            // Match any course ending with W, W1, W2, etc.
            return preg_match('/W\d*$/i', $courseCode);
        });
        
        // Collect ALL time slots for each course
        $courseTimeSlots = [];
        foreach ($entries as $entry) {
            $courseCode = $entry->course_code;
            if (!isset($courseTimeSlots[$courseCode])) {
                $courseTimeSlots[$courseCode] = [];
            }
            $courseTimeSlots[$courseCode][] = [
                'day' => $entry->day_of_week,
                'period' => $entry->period,
                'day_short' => $this->getDayShort($entry->day_of_week),
            ];
        }
        
        // For grouping into tracks, use the FIRST occurrence of each course
        $courseFirstOccurrence = [];
        foreach ($entries as $entry) {
            $courseCode = $entry->course_code;
            if (!isset($courseFirstOccurrence[$courseCode])) {
                $timeSlotKey = $entry->day_of_week . '_' . $entry->period;
                $courseFirstOccurrence[$courseCode] = $timeSlotKey;
            }
        }
        
        // Filter entries to only include first occurrence of each course for grouping
        $entriesForGrouping = $entries->filter(function ($entry) use ($courseFirstOccurrence) {
            $timeSlotKey = $entry->day_of_week . '_' . $entry->period;
            return $courseFirstOccurrence[$entry->course_code] === $timeSlotKey;
        });

        // Group by time slot (day + period)
        $timeSlotGroups = $entriesForGrouping->groupBy(function ($entry) {
            return $entry->day_of_week . '_' . $entry->period;
        });

        $tracks = collect();
        $trackNumber = 1;

        foreach ($timeSlotGroups as $timeSlotKey => $groupEntries) {
            // Extract day and period from key
            [$day, $period] = explode('_', $timeSlotKey);
            
            // Get unique course codes in this time slot
            $uniqueCourses = $groupEntries->unique('course_code');
            
            // Only create track if we have enough courses
            if ($uniqueCourses->count() >= $minCoursesPerTrack) {
                // Determine course codes for this track
                $courseCodes = $uniqueCourses->pluck('course_code')->sort()->values()->toArray();

                // Validation for JÜ mode:
                // If detecting JÜ tracks, the track MUST contain at least one course starting with '3'.
                // Otherwise, it's just a regular Q12/Q13 track which (presumably) is handled in the other loops.
                if (in_array('JÜ', $grades)) {
                    $hasJuCourse = false;
                    foreach ($courseCodes as $cc) {
                        if (str_starts_with($cc, '3')) {
                            $hasJuCourse = true;
                            break;
                        }
                    }
                    if (!$hasJuCourse) {
                        continue;
                    }
                }
                
                // Collect all time slots for all courses in this track
                $allTimeSlots = [];
                $validDays = []; // Days where ALL courses have lessons
                
                foreach ($courseCodes as $courseCode) {
                    if (isset($courseTimeSlots[$courseCode])) {
                        foreach ($courseTimeSlots[$courseCode] as $slot) {
                            $slotKey = $slot['day'] . '_' . $slot['period'];
                            if (!isset($allTimeSlots[$slotKey])) {
                                $allTimeSlots[$slotKey] = [
                                    'day' => $slot['day'],
                                    'period' => $slot['period'],
                                    'day_short' => $slot['day_short'],
                                    'courses' => [],
                                ];
                            }
                            $allTimeSlots[$slotKey]['courses'][] = $courseCode;
                        }
                    }
                }
                
                // Find time slots where ALL courses are present (these are valid days for drop validation)
                $totalCourses = count($courseCodes);
                foreach ($allTimeSlots as $slotKey => $slotData) {
                    // Check if ALL unique courses of this track are present in this time slot
                    if (count(array_unique($slotData['courses'])) === $totalCourses) {
                        $validDays[] = $slotData['day'];
                    }
                }
                $validDays = array_values(array_unique($validDays));
                
                // Format time slots for display - group consecutive periods by day
                // Show ALL time slots where at least ONE course has lessons
                $timeSlotsByDay = [];
                foreach ($allTimeSlots as $slotKey => $slotData) {
                    // Show all time slots, not just where all courses are present
                    $day = $slotData['day'];
                    if (!isset($timeSlotsByDay[$day])) {
                        $timeSlotsByDay[$day] = [
                            'day_short' => $slotData['day_short'],
                            'periods' => [],
                        ];
                    }
                    $timeSlotsByDay[$day]['periods'][] = $slotData['period'];
                }
                
                // Sort periods and group consecutive ones
                $formattedSlots = [];
                foreach ($timeSlotsByDay as $day => $data) {
                    sort($data['periods']);
                    $periods = $data['periods'];
                    $groups = [];
                    $currentGroup = [$periods[0]];
                    
                    for ($i = 1; $i < count($periods); $i++) {
                        if ($periods[$i] === $periods[$i-1] + 1) {
                            // Consecutive period
                            $currentGroup[] = $periods[$i];
                        } else {
                            // New group
                            $groups[] = $currentGroup;
                            $currentGroup = [$periods[$i]];
                        }
                    }
                    $groups[] = $currentGroup;
                    
                    // Format each group
                    foreach ($groups as $group) {
                        if (count($group) === 1) {
                            $formattedSlots[] = $data['day_short'] . ' ' . $group[0] . '.';
                        } else {
                            $formattedSlots[] = $data['day_short'] . ' ' . $group[0] . '/' . end($group);
                        }
                    }
                }
                
                $timeSlotDisplay = implode(', ', $formattedSlots);

                // Prepare structured time slots
                $structuredTimeSlots = [];
                foreach ($formattedSlots as $slotStr) {
                    $structuredTimeSlots[] = $slotStr;
                }
                // Actually we want the raw data: day + periods
                $structuredTimeSlots = array_values($timeSlotsByDay);
                
                // Determine grade label
                $gradeLabel = $grades[0] ?? $uniqueCourses->first()->class_name;
                if (in_array('JÜ', $grades)) {
                    $gradeLabel = 'JÜ';
                } elseif (in_array('Q12', $grades) && !in_array('Q13', $grades)) {
                    $gradeLabel = 'Q12';
                } elseif (in_array('Q13', $grades) && !in_array('Q12', $grades)) {
                    $gradeLabel = 'Q13';
                }

                $tracks->push([
                    'id' => 'track_' . $trackNumber,
                    'number' => $trackNumber,
                    'day_of_week' => (int) $day,
                    'period' => (int) $period,
                    'day_name' => $this->getDayName((int) $day),
                    'day_short' => $this->getDayShort((int) $day),
                    'time_slot' => $this->getDayShort((int) $day) . ' ' . $period . '.',
                    'time_slots' => $structuredTimeSlots, // New structured field
                    'all_time_slots' => $timeSlotDisplay,
                    'valid_days' => $validDays,
                    'courses' => $courseCodes,
                    'course_count' => $uniqueCourses->count(),
                    'subjects' => $uniqueCourses->pluck('subject')->unique()->sort()->values()->toArray(),
                    'teachers' => $uniqueCourses->pluck('teacher_code')->unique()->sort()->values()->toArray(),
                    'rooms' => $uniqueCourses->pluck('room')->unique()->filter()->sort()->values()->toArray(),
                    'grade' => $gradeLabel, // Use valid label
                ]);
                
                $trackNumber++;
            }
        }

        // Sort tracks by day and period
        return $tracks->sortBy(function ($track) {
            return $track['day_of_week'] * 100 + $track['period'];
        })->values();
    }

    /**
     * Get tracks grouped by grade (Q12/Q13) and then by day
     */
    public function detectTracksByGradeAndDay(
        ?string $schoolYear = null,
        int $minCoursesPerTrack = 2
    ): Collection {
        $schoolYear ??= SchoolYear::current();
        $result = collect();
        
        // Process each grade separately, including Cross-Grade (JÜ)
        foreach (['Q12', 'Q13', 'JÜ'] as $grade) {
            // For JÜ (Cross-Grade), we allow tracks with just 1 course (e.g. Orchestra)
            // For Q12/Q13, we stick to the default (usually 2) to find real tracks
            $minForGrade = ($grade === 'JÜ') ? 1 : $minCoursesPerTrack;
            
            $tracks = $this->detectTracks($schoolYear, [$grade], $minForGrade);
            
            if ($tracks->isNotEmpty()) {
                $result->put($grade, $tracks);
            }
        }
        
        return $result;
    }

    /**
     * Get day name in German
     */
    private function getDayName(int $day): string
    {
        return match($day) {
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            default => 'Unbekannt',
        };
    }

    /**
     * Get short day name
     */
    private function getDayShort(int $day): string
    {
        return match($day) {
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            default => '?',
        };
    }
}
