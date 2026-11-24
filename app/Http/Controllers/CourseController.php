<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\Course;
use App\Services\CurrentExamPeriodService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Display a listing of all courses for an exam period.
     */
    public function index()
    {
        // Aktuellen Prüfungszeitraum finden (neuester)
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        
        if (!$examPeriod) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden.');
        }
        
        // Kurse der eigenen Schule für diesen Prüfungszeitraum
        $courses = Course::where('exam_period_id', $examPeriod->id)->derStufe('13')
            ->with(['studentExams' => fn ($q) => $q->derStufe('13'), 'subject', 'teacher', 'secondCorrector', 'studentExams.student', 'studentExams.assessor', 'studentExams.secondCorrector'])
            ->orderBy('course_code')
            ->get()
            /** @phpstan-ignore argument.type */
            ->map(function ($course) {
                $oralExams = $course->studentExams->filter(fn($exam) => $exam->isOralExam());
                $writtenExams = $course->studentExams->filter(fn($exam) => $exam->isWrittenExam());
                $allExams = $course->studentExams;
                
                // Get assessor info if one is assigned (from first oral exam)
                $assessorInfo = $oralExams->first()?->assessor;
                
                // Map ALL students with their individual assessors and coupling info
                $students = $allExams->map(function($exam) {
                    $isOral = $exam->isOralExam();
                    $isWritten = $exam->isWrittenExam();
                    return [
                        'id' => $exam->id,
                        'student_name' => $exam->student->lastname . ', ' . $exam->student->firstname,
                        'has_oral_exam' => $isOral,
                        'has_written_exam' => $isWritten,
                        'exam_type_display' => $exam->getExamTypeDisplayName(),
                        'assessor_id' => $isOral ? $exam->assessor_id : null,
                        'assessor_name' => ($isOral && $exam->assessor) ? $exam->assessor->lastname . ', ' . $exam->assessor->firstname : null,
                        'assessor_shortcode' => $isOral ? $exam->assessor?->shortcode : null,
                        'second_corrector_id' => $isWritten ? $exam->second_corrector_id : null,
                        'second_corrector_name' => ($isWritten && $exam->secondCorrector) ? $exam->secondCorrector->lastname . ', ' . $exam->secondCorrector->firstname : null,
                        'second_corrector_shortcode' => $isWritten ? $exam->secondCorrector?->shortcode : null,
                        'coupling_id' => $isOral ? $exam->coupling_id : null,
                        'is_coupled' => $isOral && $exam->isCoupled(),
                    ];
                })->sortBy('student_name');
                
                // Group students by coupling for display (only oral exams)
                $couplingGroups = $students
                    ->filter(fn($s) => $s['has_oral_exam'] && $s['is_coupled'])
                    ->groupBy('coupling_id');
                
                // Check if all ORAL exam students have an assessor assigned
                $allAssessorsAssigned = $oralExams->every(function($exam) {
                    return $exam->assessor_id !== null;
                });
                
                // Count coupled vs uncoupled students (only oral exams)
                $oralStudents = $students->filter(fn($s) => $s['has_oral_exam']);
                $coupledCount = $oralStudents->filter(fn($s) => $s['is_coupled'])->count();
                $uncoupledCount = $oralStudents->count() - $coupledCount;
                
                // @phpstan-ignore-next-line return.type
                return [
                    'id' => $course->id,
                    'course_code' => $course->course_code,
                    'subject_name' => $course->subject->name ?? 'Unbekannt',
                    'subject_code' => $course->subject->code ?? '',
                    'subject_id' => $course->subject_id,
                    'teacher_id' => $course->teacher_id,
                    'teacher_name' => $course->teacher ? $course->teacher->lastname . ', ' . $course->teacher->firstname : 'Nicht zugewiesen',
                    'teacher_shortcode' => $course->teacher->shortcode ?? '',
                    'second_corrector_id' => $course->second_corrector_id,
                    'second_corrector_name' => $course->secondCorrector ? $course->secondCorrector->lastname . ', ' . $course->secondCorrector->firstname : null,
                    'second_corrector_shortcode' => $course->secondCorrector?->shortcode,
                    'level' => $course->level ?? '',
                    'student_count' => $allExams->count(), // Total students in course
                    'colloquium_count' => $oralExams->count(), // Only oral exams
                    'written_count' => $writtenExams->count(), // Only written exams
                    'assessor_id' => $assessorInfo?->id,
                    'assessor_name' => $assessorInfo ? $assessorInfo->lastname . ', ' . $assessorInfo->firstname : null,
                    'assessor_shortcode' => $assessorInfo?->shortcode,
                    'students' => $students,
                    'all_assessors_assigned' => $allAssessorsAssigned && $oralExams->count() > 0,
                    'coupling_groups' => $couplingGroups,
                    'has_couplings' => $couplingGroups->count() > 0,
                    'coupled_count' => $coupledCount,
                    'uncoupled_count' => $uncoupledCount,
                ];
            });

        // Lehrkraft-Auslastung berechnen (alle Rollen)
        // Erstprüfer: aus course.teacher_id × Anzahl Prüfungen im Kurs
        $workloadMap = [];
        foreach ($courses as $course) {
            if ($course['teacher_id']) {
                $id = $course['teacher_id'];
                $workloadMap[$id] = ($workloadMap[$id] ?? 0) + $course['student_count'];
            }
        }
        // Beisitzer + Zweitkorrektor: direkt aus student_exams (dort zuverlässig gesetzt)
        $allStudentExams = \App\Models\StudentExam::whereIn(
            'course_id',
            $courses->pluck('id')
        )->get(['examiner_id', 'assessor_id', 'second_corrector_id']);
        foreach ($allStudentExams as $exam) {
            // examiner_id nur zählen wenn NICHT identisch mit course.teacher_id
            // (würde sonst doppelt zählen – nur für manuell überschriebene Erstprüfer)
            if ($exam->assessor_id) {
                $workloadMap[$exam->assessor_id] = ($workloadMap[$exam->assessor_id] ?? 0) + 1;
            }
            if ($exam->second_corrector_id) {
                $workloadMap[$exam->second_corrector_id] = ($workloadMap[$exam->second_corrector_id] ?? 0) + 1;
            }
        }

        // Äquivalenz-Mapping: Fach-Code des Kurses → qualifizierende Fakultas-Codes
        // Gemäß bayerischer Schulordnung:
        // - Wirtschaft/Recht (WR) kann von Lehrern mit Fakultas Wirtschaftswiss. (Ww) betreut werden
        // - Sport männl./weibl. (Smw) kann von Sm- und Sw-Lehrern betreut werden
        // - Französisch spätbeginnend (Fs) kann von F-Lehrern betreut werden
        $subjectEquivalences = [
            'WR'  => ['WR', 'Ww'],
            'Smw' => ['Smw', 'Sm', 'Sw'],
            'Fs'  => ['Fs', 'F'],
        ];

        // Alle Fächer nach Code laden, um IDs aufzulösen (zentrale DB)
        $allSubjectsByCode = \App\Models\Subject::pluck('id', 'code')->toArray();

        // Umgekehrtes Mapping: Fakultas-Code → zusätzliche Kurs-Fach-IDs
        /** @var array<string, list<int>> $fakultasToExtraIds */
        $fakultasToExtraIds = [];
        foreach ($subjectEquivalences as $courseCode => $fakultasCodes) {
            $courseSubjectId = $allSubjectsByCode[$courseCode] ?? null;
            if (!$courseSubjectId) {
                continue;
            }
            foreach ($fakultasCodes as $fCode) {
                $fakultasToExtraIds[$fCode][] = (int) $courseSubjectId;
            }
        }

        // Get all teachers with their subject qualifications (incl. Äquivalenzen)
        $teachers = \App\Models\Teacher::active()->with('subjects')
            ->orderBy('shortcode')
            ->get()
            ->map(function ($teacher) use ($workloadMap, $fakultasToExtraIds) {
                $subjectIds = $teacher->subjects->pluck('id')->map(fn($id) => (int) $id)->toArray();
                // Äquivalente Kurs-Fach-IDs ergänzen
                foreach ($teacher->subjects as $subject) {
                    $extras = $fakultasToExtraIds[$subject->code] ?? [];
                    $subjectIds = array_values(array_unique(array_merge($subjectIds, $extras)));
                }
                return [
                    'id' => $teacher->id,
                    'name' => $teacher->lastname . ', ' . $teacher->firstname,
                    'shortcode' => $teacher->shortcode,
                    'subject_ids' => $subjectIds,
                    'workload' => $workloadMap[$teacher->id] ?? 0,
                ];
            });

        return view('courses.index', compact('examPeriod', 'courses', 'teachers'));
    }

    /**
     * Show the edit form for a course.
     */
    public function edit(Course $course): \Illuminate\View\View
    {
        $subjects = \App\Models\Subject::orderBy('sort_order')->orderBy('name')->get();
        $teachers = \App\Models\Teacher::active()->orderBy('lastname')->orderBy('firstname')->get();

        return view('courses.edit', compact('course', 'subjects', 'teachers'));
    }

    /**
     * Update a course.
     */
    public function update(Request $request, Course $course): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'course_code'        => 'required|string|max:50',
            'subject_id'         => 'required|exists:subjects,id',
            'level'              => 'nullable|in:grundlegend,erhoeht',
            'teacher_id'         => 'nullable|exists:teachers,id',
            'second_corrector_id'=> 'nullable|exists:teachers,id',
        ]);

        // Duplikat-Prüfung beim Kursname
        $duplicate = Course::where('exam_period_id', $course->exam_period_id)
            ->where('course_code', $validated['course_code'])
            ->where('id', '!=', $course->id)
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['course_code' => "Der Kursname \"{$validated['course_code']}\" wird bereits von einem anderen Kurs verwendet."]);
        }

        $course->update($validated);

        // Examiner in bestehenden StudentExams synchronisieren (falls Erstprüfer geändert)
        if (array_key_exists('teacher_id', $validated)) {
            \App\Models\StudentExam::where('course_id', $course->id)
                ->update(['examiner_id' => $validated['teacher_id']]);
        }

        // Zweitkorrektor in schriftlichen StudentExams synchronisieren
        if (array_key_exists('second_corrector_id', $validated)) {
            \App\Models\StudentExam::where('course_id', $course->id)
                ->where('exam_type', 'written')
                ->update(['second_corrector_id' => $validated['second_corrector_id']]);
        }

        return redirect()
            ->route('courses.index')
            ->with('success', "Kurs \"{$course->course_code}\" erfolgreich aktualisiert.");
    }

    /**
     * Update the course code (Kursname) of a course.
     */
    public function updateCourseCode(Request $request, Course $course)
    {
        $validated = $request->validate([
            'course_code' => 'required|string|max:50',
        ]);

        // Prüfen ob der Kursname bereits von einem anderen Kurs im selben Prüfungszeitraum verwendet wird
        $duplicate = Course::where('exam_period_id', $course->exam_period_id)
            ->where('course_code', $validated['course_code'])
            ->where('id', '!=', $course->id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => "Der Kursname \"{$validated['course_code']}\" wird bereits von einem anderen Kurs verwendet.",
            ], 422);
        }

        $course->update(['course_code' => $validated['course_code']]);

        return response()->json([
            'success' => true,
            'message' => 'Kursname erfolgreich geändert.',
            'course_code' => $course->course_code,
        ]);
    }

    /**
     * Assign a second corrector (Zweitkorrektor) to a course.
     */
    public function assignSecondCorrector(Request $request, Course $course)
    {
        $validated = $request->validate([
            'second_corrector_id' => 'nullable|exists:teachers,id',
        ]);

        // Kurs-Level aktualisieren
        $course->update(['second_corrector_id' => $validated['second_corrector_id']]);

        // Alle schriftlichen StudentExams dieses Kurses ebenfalls aktualisieren
        \App\Models\StudentExam::where('course_id', $course->id)
            ->where('exam_type', 'written')
            ->update(['second_corrector_id' => $validated['second_corrector_id']]);

        $teacher = $validated['second_corrector_id']
            ? \App\Models\Teacher::find($validated['second_corrector_id'])
            : null;

        return response()->json([
            'success' => true,
            'message' => $teacher
                ? 'Zweitkorrektor erfolgreich zugeordnet.'
                : 'Zweitkorrektor erfolgreich entfernt.',
            'second_corrector_name' => $teacher
                ? $teacher->lastname . ', ' . $teacher->firstname
                : null,
            'second_corrector_shortcode' => $teacher->shortcode ?? '',
        ]);
    }

    /**
     * Update the teacher (Erstprüfer/Kursleiter) of a course.
     */
    public function updateTeacher(Request $request, Course $course)
    {
        $validated = $request->validate([
            'teacher_id' => 'nullable|exists:teachers,id',
        ]);

        // Kursleiter im Kurs aktualisieren
        $course->update(['teacher_id' => $validated['teacher_id']]);

        // Examiner_id in allen bestehenden StudentExams dieses Kurses synchronisieren,
        // damit die Schülerliste den aktuellen Erstprüfer anzeigt
        \App\Models\StudentExam::where('course_id', $course->id)
            ->update(['examiner_id' => $validated['teacher_id']]);

        $teacher = $validated['teacher_id']
            ? \App\Models\Teacher::find($validated['teacher_id'])
            : null;

        return response()->json([
            'success' => true,
            'message' => $teacher
                ? 'Kursleiter erfolgreich geändert.'
                : 'Kursleiter erfolgreich entfernt.',
            'teacher_name' => $teacher
                ? $teacher->lastname . ', ' . $teacher->firstname
                : 'Nicht zugewiesen',
            'teacher_shortcode' => $teacher->shortcode ?? '',
        ]);
    }

    /**
     * Assign a second corrector to a single written student exam.
     */
    public function assignSecondCorrectorToStudent(Request $request, \App\Models\StudentExam $studentExam)
    {
        $validated = $request->validate([
            'second_corrector_id' => 'nullable|exists:teachers,id',
        ]);

        $studentExam->update(['second_corrector_id' => $validated['second_corrector_id']]);

        return response()->json([
            'success' => true,
            'message' => $validated['second_corrector_id']
                ? 'Zweitkorrektor erfolgreich zugeordnet.'
                : 'Zweitkorrektor erfolgreich entfernt.',
        ]);
    }

    /**
     * Assign an assessor to all oral exam student exams of a course.
     */
    public function assignAssessor(Request $request, Course $course)
    {
        $validated = $request->validate([
            'assessor_id' => 'nullable|exists:teachers,id',
        ]);

        // Update only ORAL student_exams for this course
        \App\Models\StudentExam::where('course_id', $course->id)
            ->where('exam_type', 'oral')
            ->update(['assessor_id' => $validated['assessor_id']]);

        return response()->json([
            'success' => true,
            'message' => $validated['assessor_id'] 
                ? 'Beisitzer erfolgreich zugeordnet.' 
                : 'Beisitzer erfolgreich entfernt.',
        ]);
    }

    /**
     * Assign an assessor to a single student exam.
     */
    public function assignAssessorToStudent(Request $request, \App\Models\StudentExam $studentExam)
    {
        $validated = $request->validate([
            'assessor_id' => 'nullable|exists:teachers,id',
        ]);

        $studentExam->update(['assessor_id' => $validated['assessor_id']]);

        return response()->json([
            'success' => true,
            'message' => $validated['assessor_id'] 
                ? 'Beisitzer erfolgreich zugeordnet.' 
                : 'Beisitzer erfolgreich entfernt.',
        ]);
    }

    /**
     * Export written exams list as PDF (with second correctors)
     */
    public function exportWrittenPdf()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        $courses = \App\Models\Course::where('exam_period_id', $examPeriod->id)->derStufe('13')
            ->with(['studentExams' => fn ($q) => $q->derStufe('13'), 'subject', 'teacher', 'secondCorrector', 'studentExams.secondCorrector'])
            ->orderBy('course_code')
            ->get()
            ->map(function ($course) {
                $writtenExams = $course->studentExams->filter(fn($e) => $e->isWrittenExam());

                if ($writtenExams->isEmpty()) {
                    return null;
                }

                // Alle unterschiedlichen Zweitkorrektoren dieses Kurses sammeln
                $secondCorrectors = $writtenExams
                    ->filter(fn($e) => $e->secondCorrector !== null)
                    ->map(fn($e) => $e->secondCorrector)
                    ->unique('id')
                    ->map(fn($t) => [
                        'name' => $t->lastname . ', ' . $t->firstname,
                        'shortcode' => $t->shortcode,
                    ])
                    ->values();

                return [
                    'course_code'        => $course->course_code,
                    'subject_name'       => $course->subject->name ?? 'Unbekannt',
                    'level'              => $course->level ?? '',
                    'level_display'      => $course->getLevelDisplayName(),
                    'teacher_name'       => $course->teacher
                        ? $course->teacher->lastname . ', ' . $course->teacher->firstname
                        : null,
                    'teacher_shortcode'  => $course->teacher->shortcode ?? '',
                    'second_correctors'  => $secondCorrectors,
                    'written_count'      => $writtenExams->count(),
                ];
            })
            ->filter()   // nulls (Kurse ohne schriftliche Prüfung) entfernen
            ->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('courses.written-pdf', [
            'examPeriod' => $examPeriod,
            'courses'    => $courses,
            'exportDate' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = 'schriftliche_pruefungen_' . \Illuminate\Support\Str::slug($examPeriod->name) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export courses as PDF with couplings (only oral exams/colloquiums)
     */
    public function exportPdf()
    {
        // Aktuellen Prüfungszeitraum finden (neuester)
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        
        if (!$examPeriod) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        // Kurse der eigenen Schule für diesen Prüfungszeitraum
        $courses = \App\Models\Course::where('exam_period_id', $examPeriod->id)->derStufe('13')
            ->with(['studentExams' => fn ($q) => $q->derStufe('13'), 'subject', 'teacher', 'studentExams.student', 'studentExams.assessor'])
            ->orderBy('course_code')
            ->get()
            ->map(function ($course) {
                // Get ONLY students with oral exams (colloquiums) with coupling information
                $students = $course->studentExams
                    ->filter(fn($exam) => $exam->isOralExam())
                    ->map(function($exam) {
                        return [
                            'id' => $exam->id,
                            'student_name' => $exam->student->lastname . ', ' . $exam->student->firstname,
                            'assessor_name' => $exam->assessor ? $exam->assessor->lastname . ', ' . $exam->assessor->firstname : 'Nicht zugeordnet',
                            'assessor_shortcode' => $exam->assessor->shortcode ?? 'N.N.',
                            'coupling_id' => $exam->coupling_id,
                            'is_coupled' => $exam->isCoupled(),
                        ];
                    })->sortBy('student_name');

                // Group students by coupling
                $couplings = $students->filter(fn($s) => $s['is_coupled'])
                    ->groupBy('coupling_id');

                return [
                    'course_code' => $course->course_code,
                    'subject_name' => $course->subject->name ?? 'Unbekannt',
                    'subject_code' => $course->subject->code ?? '',
                    'teacher_name' => $course->teacher ? $course->teacher->lastname . ', ' . $course->teacher->firstname : 'Nicht zugewiesen',
                    'teacher_shortcode' => $course->teacher->shortcode ?? '',
                    'level' => $course->level ?? '',
                    'student_count' => $students->count(), // Only oral exams
                    'students' => $students,
                    'couplings' => $couplings,
                    'coupled_count' => $students->filter(fn($s) => $s['is_coupled'])->count(),
                ];
            })
            ->filter(fn($course) => $course['student_count'] > 0); // Only show courses with oral exams

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('courses.pdf', [
            'examPeriod' => $examPeriod,
            'courses' => $courses,
            'exportDate' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = "kolloquiumsliste_" . \Illuminate\Support\Str::slug($examPeriod->name) . ".pdf";
        return $pdf->download($filename);
    }

    /**
     * Hilfsmethode: Daten für alle drei Export-Abschnitte aufbereiten
     */
    private function buildCombinedExportData(\App\Models\ExamPeriod $examPeriod): array
    {
        // ---- 1. Schriftliche Prüfungen ----
        $writtenCourses = \App\Models\Course::where('exam_period_id', $examPeriod->id)->derStufe('13')
            ->with(['studentExams' => fn ($q) => $q->derStufe('13'), 'subject', 'teacher', 'studentExams.secondCorrector'])
            ->orderBy('course_code')
            ->get()
            ->map(function ($course) {
                $writtenExams = $course->studentExams->filter(fn($e) => $e->isWrittenExam());
                if ($writtenExams->isEmpty()) {
                    return null;
                }
                $secondCorrectors = $writtenExams
                    ->filter(fn($e) => $e->secondCorrector !== null)
                    ->groupBy('second_corrector_id')
                    ->map(fn($grp) => [
                        'shortcode' => $grp->first()->secondCorrector->shortcode,
                        'count'     => $grp->count(),
                    ])
                    ->values();
                return [
                    'course_code'      => $course->course_code,
                    'written_count'    => $writtenExams->count(),
                    'teacher_name'     => $course->teacher ? $course->teacher->lastname . ', ' . $course->teacher->firstname : null,
                    // @phpstan-ignore-next-line nullsafe.neverNull
                    'teacher_shortcode'=> $course->teacher?->shortcode ?? '',
                    'second_correctors'=> $secondCorrectors,
                ];
            })
            ->filter()
            ->values();

        // ---- 2. Kolloquien ----
        $oralCourses = \App\Models\Course::where('exam_period_id', $examPeriod->id)->derStufe('13')
            ->with(['studentExams' => fn ($q) => $q->derStufe('13'), 'subject', 'teacher', 'studentExams.assessor'])
            ->orderBy('course_code')
            ->get()
            ->map(function ($course) {
                $oralExams = $course->studentExams->filter(fn($e) => $e->isOralExam());
                if ($oralExams->isEmpty()) {
                    return null;
                }
                $assessors = $oralExams
                    ->filter(fn($e) => $e->assessor !== null)
                    ->groupBy('assessor_id')
                    ->map(fn($grp) => ['shortcode' => $grp->first()->assessor->shortcode, 'count' => $grp->count()])
                    ->values();

                $total = $assessors->count();
                if ($total === 1) {
                    $assessorStr = $assessors->first()['shortcode'];
                } elseif ($total > 1) {
                    $assessorStr = $assessors->map(fn($a) => $a['shortcode'] . ' (' . $a['count'] . ')')->implode(', ');
                } else {
                    $assessorStr = '';
                }
                return [
                    'course_code'      => $course->course_code,
                    'oral_count'       => $oralExams->count(),
                    // @phpstan-ignore-next-line nullsafe.neverNull
                    'teacher_shortcode'=> $course->teacher?->shortcode ?? '',
                    'assessor_str'     => $assessorStr,
                ];
            })
            ->filter()
            ->values();

        // ---- 3. Prüfungsstatistik ----
        $allExams = \App\Models\StudentExam::forAbiturStudentsOf($examPeriod)
            ->with(['examiner', 'secondCorrector', 'assessor'])
            ->get();

        $statsRaw = [];
        foreach ($allExams as $exam) {
            if ($exam->examiner_id) {
                $statsRaw[$exam->examiner_id] = $statsRaw[$exam->examiner_id] ?? ['written_first' => 0, 'written_second' => 0, 'oral_first' => 0, 'oral_assessor' => 0, 'name' => ''];
                $exam->isWrittenExam() ? $statsRaw[$exam->examiner_id]['written_first']++ : $statsRaw[$exam->examiner_id]['oral_first']++;
                if ($exam->examiner) $statsRaw[$exam->examiner_id]['name'] = $exam->examiner->lastname;
            }
            if ($exam->isWrittenExam() && $exam->second_corrector_id) {
                $statsRaw[$exam->second_corrector_id] = $statsRaw[$exam->second_corrector_id] ?? ['written_first' => 0, 'written_second' => 0, 'oral_first' => 0, 'oral_assessor' => 0, 'name' => ''];
                $statsRaw[$exam->second_corrector_id]['written_second']++;
                if ($exam->secondCorrector) $statsRaw[$exam->second_corrector_id]['name'] = $exam->secondCorrector->lastname;
            }
            if ($exam->isOralExam() && $exam->assessor_id) {
                $statsRaw[$exam->assessor_id] = $statsRaw[$exam->assessor_id] ?? ['written_first' => 0, 'written_second' => 0, 'oral_first' => 0, 'oral_assessor' => 0, 'name' => ''];
                $statsRaw[$exam->assessor_id]['oral_assessor']++;
                if ($exam->assessor) $statsRaw[$exam->assessor_id]['name'] = $exam->assessor->lastname;
            }
        }

        $stats = collect($statsRaw)
            ->map(fn($d) => array_merge($d, ['total' => $d['written_first'] + $d['written_second'] + $d['oral_first'] + $d['oral_assessor']]))
            ->sortBy('name')
            ->values();

        return compact('writtenCourses', 'oralCourses', 'stats');
    }

    /**
     * Kombinierter Excel-Export: 3 Tabs (Schriftlich, Kolloquien, Statistik)
     */
    public function exportCombinedExcel()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        if (!$examPeriod) {
            return redirect()->route('dashboard')->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        $filename = 'fachausschuesse_' . \Illuminate\Support\Str::slug($examPeriod->name) . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\CoursesOverviewExport($examPeriod),
            $filename
        );
    }

    /**
     * Kombiniertes PDF: 3 Seiten (Schriftlich, Kolloquien, Statistik)
     */
    public function exportCombinedPdf()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        if (!$examPeriod) {
            return redirect()->route('dashboard')->with('error', 'Kein Prüfungszeitraum gefunden.');
        }

        $data = $this->buildCombinedExportData($examPeriod);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('courses.combined-pdf', array_merge($data, [
            'examPeriod' => $examPeriod,
            'exportDate' => now(),
        ]))->setPaper('a4', 'portrait');

        $filename = 'fachausschuesse_' . \Illuminate\Support\Str::slug($examPeriod->name) . '.pdf';
        return $pdf->download($filename);
    }
}
