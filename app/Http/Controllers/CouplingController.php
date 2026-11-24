<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\StudentExam;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CouplingController extends Controller
{
    /**
     * Show the coupling management view for a specific course
     */
    public function index(Course $course)
    {
        // Nur mündliche Prüfungen (Kolloquien) können gekoppelt werden
        $studentExams = StudentExam::where('course_id', $course->id)
            ->where('exam_type', 'oral')
            ->derStufe('13')
            ->with(['student', 'assignment'])
            ->orderBy('coupling_id')
            ->orderBy('id')
            ->get();

        // Group by coupling_id for display
        $couplings = $studentExams->filter(fn($exam) => $exam->isCoupled())
            ->groupBy('coupling_id');
        
        $uncoupledExams = $studentExams->filter(fn($exam) => !$exam->isCoupled());

        return view('couplings.index', compact('course', 'couplings', 'uncoupledExams'));
    }

    /**
     * Create a new coupling from selected student exams
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_exam_ids' => 'required|array|min:2',
            'student_exam_ids.*' => 'required|exists:student_exams,id',
        ]);

        // Verify all exams are from the same course
        $exams = StudentExam::whereIn('id', $validated['student_exam_ids'])->get();
        
        if ($exams->pluck('course_id')->unique()->count() > 1) {
            return back()->with('error', 'Alle Prüfungen müssen aus dem gleichen Kurs sein.');
        }

        // Verify none are already coupled
        if ($exams->filter(fn($exam) => $exam->isCoupled())->count() > 0) {
            return back()->with('error', 'Eine oder mehrere Prüfungen sind bereits gekoppelt.');
        }

        // Create coupling
        $couplingId = (string) Str::uuid();
        
        StudentExam::whereIn('id', $validated['student_exam_ids'])
            ->update(['coupling_id' => $couplingId]);

        return back()->with('success', count($validated['student_exam_ids']) . ' Prüfungen wurden gekoppelt.');
    }

    /**
     * Remove a coupling
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'coupling_id' => 'required|string|size:36',
        ]);

        $count = StudentExam::where('coupling_id', $validated['coupling_id'])
            ->update(['coupling_id' => null]);

        return back()->with('success', $count . ' Prüfungen wurden entkoppelt.');
    }
}
