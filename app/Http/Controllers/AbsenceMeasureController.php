<?php

namespace App\Http\Controllers;

use App\Models\AbsenceMeasure;
use App\Models\Student;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Dokumentierte Maßnahmen zu Absenzen/Verhalten (Roadmap 8.3).
 */
class AbsenceMeasureController extends Controller
{
    public function store(Request $request, Student $student)
    {
        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(AbsenceMeasure::availableTypes())),
            'aspect' => 'required|in:' . implode(',', array_keys(AbsenceMeasure::ASPECT_LABELS)),
            'measure_date' => 'required|date',
            'note' => 'nullable|string|max:5000',
        ]);

        $measure = AbsenceMeasure::create([
            'student_id' => $student->id,
            'type' => $validated['type'],
            'aspect' => $validated['aspect'],
            'measure_date' => $validated['measure_date'],
            'note' => $validated['note'] ?? null,
            'created_by_user_id' => auth()->id(),
        ]);

        ActivityLogger::log(
            'absence_measure_created',
            'Student',
            $student->id,
            AbsenceMeasure::labelFor($measure->type) . " für {$student->full_name} dokumentiert"
        );

        return back()->with('success', 'Maßnahme wurde dokumentiert.');
    }

    public function destroy(Student $student, AbsenceMeasure $measure)
    {
        abort_if($measure->student_id !== $student->id, 404);

        $measure->delete();

        ActivityLogger::log(
            'absence_measure_deleted',
            'Student',
            $student->id,
            "Maßnahme für {$student->full_name} gelöscht"
        );

        return back()->with('success', 'Maßnahme wurde gelöscht.');
    }
}
