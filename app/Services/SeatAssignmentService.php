<?php

namespace App\Services;

use App\Models\SeatingTemplate;
use App\Models\SeatingTemplateSeat;
use App\Models\Student;
use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamSeatAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for automatic seat assignment in written exams
 */
class SeatAssignmentService
{
    /**
     * Automatically assign students to available seats in row-wise order
     *
     * @param WrittenExamAssignment $assignment The exam assignment
     * @param Collection $students Collection of Student models to assign
     * @return Collection Collection of created WrittenExamSeatAssignment models
     * @throws \Exception
     */
    public function autoAssign(WrittenExamAssignment $assignment, Collection $students): Collection
    {
        try {
            DB::beginTransaction();

            // Get available seats in row-wise order
            $template = $assignment->getSeatingTemplate();
            if (!$template) {
                throw new \Exception('Dem Raum ist keine passende Sitzplan-Vorlage zugewiesen.');
            }
            $availableSeats = $this->getAvailableSeatsRowwise($template, $assignment->id);
            
            Log::info('Debug Auto-Assign', [
                'students_count' => $students->count(),
                'available_seats_count' => $availableSeats->count(),
                'template_id' => $template->id,
                'room_id' => $assignment->room->id
            ]);

            // Check capacity
            if ($students->count() > $availableSeats->count()) {
                throw new \Exception(
                    "Nicht genug verfügbare Plätze. Benötigt: {$students->count()}, Verfügbar: {$availableSeats->count()}"
                );
            }

            // Sort students by course name, then alphabetically (Umlaut-korrekt via Collator, DIN 5007-2: ä=ae)
            $collator = new \Collator('de_DE@collation=phonebook');
            $sortedStudents = $students->sortBy([
                function ($a, $b) use ($collator) {
                    // Primary sort: Course name
                    $courseComparison = $collator->compare(
                        $a->exams()->first()->course->name ?? '',
                        $b->exams()->first()->course->name ?? ''
                    );

                    if ($courseComparison !== 0) {
                        return $courseComparison;
                    }

                    // Secondary sort: Last name
                    $lastnameComparison = $collator->compare($a->lastname, $b->lastname);
                    if ($lastnameComparison !== 0) {
                        return $lastnameComparison;
                    }

                    // Tertiary sort: First name
                    return $collator->compare($a->firstname, $b->firstname);
                }
            ])->values(); // Reset keys

            // Assign students to seats
            $assignments = collect();
            $sortOrder = 1;

            foreach ($sortedStudents as $index => $student) {
                $seat = $availableSeats[$index];
                
                $seatAssignment = WrittenExamSeatAssignment::create([
                    'written_exam_assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                    'seat_id' => $seat->id,
                    'sort_order' => $sortOrder++,
                    'assigned_at' => now(),
                ]);
                
                $assignments->push($seatAssignment);
            }

            DB::commit();

            Log::info("Auto-assigned {$assignments->count()} students to seats", [
                'assignment_id' => $assignment->id,
                'course_id' => $assignment->course_id,
                'room_id' => $assignment->room_id,
            ]);

            return $assignments;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to auto-assign seats", [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get available seats in row-wise order (top to bottom, left to right)
     *
     *
     * @param SeatingTemplate $template The room template
     * @return Collection Collection of SeatingTemplateSeat models
     */
    public function getAvailableSeatsRowwise(SeatingTemplate $template, ?int $assignmentId = null): Collection
    {
        // Nur Plätze ausschließen, die innerhalb desselben Assignments belegt sind.
        // Plätze anderer Assignments (z.B. andere Kurse im selben Raum) bleiben verfügbar.
        $occupiedSeatIds = $assignmentId
            ? WrittenExamSeatAssignment::where('written_exam_assignment_id', $assignmentId)->pluck('seat_id')
            : collect();

        return $template->seats()
            ->whereIn('seat_type', ['available', 'reserve'])
            ->when($occupiedSeatIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $occupiedSeatIds))
            ->orderByRaw(\App\Support\Sql::positionIn('seat_type', ['available', 'reserve']))
            ->orderBy('row_number', 'asc')
            ->orderBy('column_number', 'asc')
            ->get();
    }

    /**
     * Validate seat assignments for an exam assignment
     *
     * @param WrittenExamAssignment $assignment The exam assignment
     * @return array Array with 'valid' (bool), 'errors' (array), 'warnings' (array)
     */
    public function validateAssignment(WrittenExamAssignment $assignment): array
    {
        $errors = [];
        $warnings = [];

        // Check for duplicate student assignments
        $studentIds = $assignment->seatAssignments()->pluck('student_id');
        $duplicateStudents = $studentIds->duplicates();
        
        if ($duplicateStudents->count() > 0) {
            $errors[] = "Doppelte Schülerzuweisungen gefunden: " . $duplicateStudents->count() . " Schüler";
        }

        // Check for duplicate seat assignments
        $seatIds = $assignment->seatAssignments()->pluck('seat_id');
        $duplicateSeats = $seatIds->duplicates();
        
        if ($duplicateSeats->count() > 0) {
            $errors[] = "Doppelte Sitzplatzzuweisungen gefunden: " . $duplicateSeats->count() . " Plätze";
        }

        // Check for students assigned to blocked or aisle seats
        $invalidSeats = $assignment->seatAssignments()
            ->whereHas('seat', function ($query) {
                $query->whereIn('seat_type', ['blocked', 'aisle']);
            })
            ->count();
        
        if ($invalidSeats > 0) {
            $errors[] = "{$invalidSeats} Schüler sind blockierten oder Gang-Plätzen zugewiesen";
        }

        // Check capacity utilization
        $assignedCount = $assignment->seatAssignments()->count();
        $totalCapacity = $assignment->getSeatingTemplate()?->getTotalCapacity() ?? 0;
        $utilizationPercent = $totalCapacity > 0 ? ($assignedCount / $totalCapacity) * 100 : 0;

        if ($utilizationPercent < 50) {
            $warnings[] = "Geringe Raumauslastung: {$utilizationPercent}% ({$assignedCount}/{$totalCapacity})";
        }

        // Check for students from different courses (if assignment is course-specific)
        if ($assignment->course_id) {
            $wrongCourseStudents = $assignment->seatAssignments()
                ->whereHas('student.exams', function ($query) use ($assignment) {
                    $query->where('course_id', '!=', $assignment->course_id);
                })
                ->count();
            
            if ($wrongCourseStudents > 0) {
                $warnings[] = "{$wrongCourseStudents} Schüler gehören zu einem anderen Kurs";
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'statistics' => [
                'assigned_students' => $assignedCount,
                'total_capacity' => $totalCapacity,
                'remaining_capacity' => $totalCapacity - $assignedCount,
                'utilization_percent' => round($utilizationPercent, 1),
            ],
        ];
    }

    /**
     * Automatically assign all students from multiple assignments to seats using a strategy.
     *
     * @param array $studentItems  Each item: ['student' => Student, 'assignment_id' => int, 'course_id' => int, 'course_code' => string, 'has_compensation' => bool]
     * @param SeatingTemplate $template  The room template
     * @param string $strategy  'standard' | 'max_distance' | 'compensation_separate'
     * @return int Number of created assignments
     * @throws \Exception
     */
    public function autoAssignRoomWithStrategy(array $studentItems, SeatingTemplate $template, string $strategy): int
    {
        // Bereits belegte Seat-IDs NUR innerhalb der aktuellen Assignments ermitteln
        $assignmentIds = array_unique(array_column($studentItems, 'assignment_id'));
        $occupiedSeatIds = WrittenExamSeatAssignment::whereIn('written_exam_assignment_id', $assignmentIds)
            ->pluck('seat_id');

        $availableSeats = $template->seats()
            ->whereIn('seat_type', ['available', 'reserve'])
            ->whereNotIn('id', $occupiedSeatIds->isEmpty() ? [0] : $occupiedSeatIds)
            ->orderByRaw(\App\Support\Sql::positionIn('seat_type', ['available', 'reserve']))
            ->orderBy('row_number', 'asc')
            ->orderBy('column_number', 'asc')
            ->get();

        $studentCount = count($studentItems);

        if ($studentCount > $availableSeats->count()) {
            throw new \Exception(
                'Nicht genug verfügbare Plätze. Benötigt: ' . $studentCount . ', Verfügbar: ' . $availableSeats->count()
            );
        }

        // Schüler sortieren (bei allen Strategien: nach Kurs + Nachname)
        $orderedItems = match ($strategy) {
            'compensation_separate' => $this->arrangeCompensationSeparate($studentItems),
            default                 => $this->arrangeStandard($studentItems),
        };

        // Plätze auswählen (je nach Strategie mit Lücken)
        $selectedSeats = match ($strategy) {
            'max_distance' => $this->selectSeatsWithMaxDistance($availableSeats, $studentCount),
            default        => $availableSeats->take($studentCount),
        };

        $sortOrder = 1;
        $count = 0;

        foreach ($orderedItems as $index => $item) {
            $seat = $selectedSeats[$index];

            WrittenExamSeatAssignment::create([
                'written_exam_assignment_id' => $item['assignment_id'],
                'student_id' => $item['student']->id,
                'seat_id' => $seat->id,
                'sort_order' => $sortOrder++,
                'assigned_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Wählt Plätze mit maximalem Abstand aus.
     * Beispiel: 10 Schüler, 20 Plätze → Schrittweite 2, also jeder zweite Platz.
     */
    private function selectSeatsWithMaxDistance(Collection $seats, int $studentCount): array
    {
        $total = $seats->count();

        // Schrittweite: floor(Gesamtplätze / Schüler), mindestens 1
        $step = max(1, (int) floor($total / $studentCount));

        $selected = [];
        $index = 0;

        while (count($selected) < $studentCount) {
            if ($index >= $total) {
                // Wenn nicht genug Plätze mit Abstand, auffüllen (sollte nicht passieren)
                foreach ($seats as $seat) {
                    if (!in_array($seat, $selected, true)) {
                        $selected[] = $seat;
                        if (count($selected) >= $studentCount) break;
                    }
                }
                break;
            }
            $selected[] = $seats[$index];
            $index += $step;
        }

        return $selected;
    }

    /** Standard: alphabetisch nach Kursname, dann Nachname (Umlaut-korrekt via Collator, DIN 5007-2: ä=ae) */
    private function arrangeStandard(array $items): array
    {
        $collator = new \Collator('de_DE@collation=phonebook');
        usort($items, function ($a, $b) use ($collator) {
            $c = $collator->compare($a['course_code'] ?? '', $b['course_code'] ?? '');
            if ($c !== 0) return $c;
            $l = $collator->compare($a['student']->lastname, $b['student']->lastname);
            if ($l !== 0) return $l;
            return $collator->compare($a['student']->firstname, $b['student']->firstname);
        });
        return $items;
    }

    /** Zeitverlängerung separat: Schüler ohne Nachteilsausgleich zuerst, danach die mit Nachteilsausgleich */
    private function arrangeCompensationSeparate(array $items): array
    {
        $normal = [];
        $compensation = [];

        foreach ($items as $item) {
            if ($item['has_compensation']) {
                $compensation[] = $item;
            } else {
                $normal[] = $item;
            }
        }

        // Beide Gruppen intern alphabetisch nach Kurs + Nachname sortieren (Umlaut-korrekt via Collator, DIN 5007-2: ä=ae)
        $collator = new \Collator('de_DE@collation=phonebook');
        $sortFn = function ($a, $b) use ($collator) {
            $c = $collator->compare($a['course_code'] ?? '', $b['course_code'] ?? '');
            if ($c !== 0) return $c;
            $l = $collator->compare($a['student']->lastname, $b['student']->lastname);
            return $l !== 0 ? $l : $collator->compare($a['student']->firstname, $b['student']->firstname);
        };
        usort($normal, $sortFn);
        usort($compensation, $sortFn);

        return array_merge($normal, $compensation);
    }

    /**
     * Clear all seat assignments for an exam assignment
     *
     * @param WrittenExamAssignment $assignment The exam assignment
     * @return int Number of deleted assignments
     */
    public function clearAssignments(WrittenExamAssignment $assignment): int
    {
        $count = $assignment->seatAssignments()->count();
        $assignment->seatAssignments()->delete();

        Log::info("Cleared seat assignments", [
            'assignment_id' => $assignment->id,
            'deleted_count' => $count,
        ]);

        return $count;
    }

    /**
     * Assign a single student to a specific seat
     *
     * @param WrittenExamAssignment $assignment The exam assignment
     * @param Student $student The student to assign
     * @param \App\Models\SeatingTemplateSeat $seat The seat to assign to
     * @param int|null $sortOrder Optional sort order
     * @return WrittenExamSeatAssignment
     * @throws \Exception
     */
    public function assignSeat(
        WrittenExamAssignment $assignment, 
        Student $student, 
        \App\Models\SeatingTemplateSeat $seat,
        ?int $sortOrder = null
    ): WrittenExamSeatAssignment {
        // Validate seat is available
        if (!in_array($seat->seat_type, ['available', 'reserve'])) {
            throw new \Exception("Platz {$seat->row_number}-{$seat->column_number} ist nicht verfügbar (Typ: {$seat->seat_type})");
        }

        // Check if seat is already occupied in this assignment
        $existingAssignment = WrittenExamSeatAssignment::where('written_exam_assignment_id', $assignment->id)
            ->where('seat_id', $seat->id)
            ->first();
        
        if ($existingAssignment) {
            throw new \Exception("Platz {$seat->row_number}-{$seat->column_number} ist bereits belegt");
        }

        // Check if student is already assigned in this assignment
        $existingStudentAssignment = WrittenExamSeatAssignment::where('written_exam_assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();
        
        if ($existingStudentAssignment) {
            throw new \Exception("Schüler {$student->full_name} ist bereits einem Platz zugewiesen");
        }

        // Create assignment
        return WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'seat_id' => $seat->id,
            'sort_order' => $sortOrder,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Remove a specific seat assignment
     *
     * @param WrittenExamSeatAssignment $seatAssignment The assignment to remove
     * @return bool
     */
    public function removeAssignment(WrittenExamSeatAssignment $seatAssignment): bool
    {
        Log::info("Removing seat assignment", [
            'assignment_id' => $seatAssignment->assignment->id,
            'student_id' => $seatAssignment->student_id,
            'seat_id' => $seatAssignment->seat_id,
        ]);

        return $seatAssignment->delete();
    }
}
