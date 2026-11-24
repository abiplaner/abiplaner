<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\StudentExam;
use App\Models\Supervision;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class TeacherDeploymentController extends Controller
{
    /**
     * Baut die Statistik für alle Lehrkräfte auf.
     *
     * written_courses     = Anzahl Schüler mit schriftl. Prüfung im eigenen Kurs (Erstkorrektur)
     * second_corrections  = Anzahl Kurse als Zweitkorrektor
     * oral_examiner       = Anzahl mündl. Prüfungen als Prüfer
     * oral_assessor       = Anzahl mündl. Prüfungen als Beisitzer
     * supervisions        = Aufsichten Kolloquium + Aufsichten schriftl. Prüfungen
     *
     * @return \Illuminate\Support\Collection<int, array{teacher: \App\Models\Teacher, written_courses: int, second_corrections: int, oral_examiner: int, oral_assessor: int, supervisions: int, total: int}>
     */
    private function buildStats(): Collection
    {
        $teachers = Teacher::orderBy('lastname')->orderBy('firstname')->get();

        // Alles nur für den laufenden Prüfungszeitraum. Vorher zählten die
        // Kurse, Kolloquien und Aufsichten aller Abiturjahrgänge zusammen —
        // beim Abitur 2027 also auch die des Abiturs 2026.
        $zeitraum   = app(\App\Services\CurrentExamPeriodService::class)->current();
        $zeitraumId = $zeitraum !== null ? $zeitraum->id : 0;
        $jahr       = $zeitraum !== null ? $zeitraum->year : 0;

        // --- Schüleranzahl pro Kursleiter (Erstkorrektur) in schriftl. Prüfungen ---
        // Aggregation direkt in der DB: gezählte StudentExams je course->teacher_id
        // Nur Q13: Im selben Zeitraum stehen auch die schriftlichen Prüfungen
        // (künftigen Abiturfächer) der Q12 — die korrigiert niemand.
        $writtenCounts = \Illuminate\Support\Facades\DB::table('student_exams')
            ->join('courses', 'courses.id', '=', 'student_exams.course_id')
            ->join('students', 'students.id', '=', 'student_exams.student_id')
            ->where('courses.exam_period_id', $zeitraumId)
            ->where(fn ($q) => $q->where('students.grade', '13')->orWhereNull('students.grade'))
            ->where('student_exams.exam_type', 'written')
            ->whereNotNull('courses.teacher_id')
            ->selectRaw('courses.teacher_id, COUNT(*) as cnt')
            ->groupBy('courses.teacher_id')
            ->get();

        /** @var array<int, int> $writtenByTeacher */
        $writtenByTeacher = [];
        foreach ($writtenCounts as $row) {
            $writtenByTeacher[(int) $row->teacher_id] = (int) $row->cnt;
        }

        // --- Zweitkorrektur: Anzahl Kurse als second_corrector ---
        $secondCourses = Course::whereNotNull('second_corrector_id')
            ->where('exam_period_id', $zeitraumId)
            ->derStufe('13')
            ->select('second_corrector_id')
            ->get();

        /** @var array<int, int> $secondByTeacher */
        $secondByTeacher = [];
        foreach ($secondCourses as $course) {
            if ($course->second_corrector_id) {
                $secondByTeacher[$course->second_corrector_id] = ($secondByTeacher[$course->second_corrector_id] ?? 0) + 1;
            }
        }

        // --- Mündliche Prüfungen (Kolloquien) ---
        // Über imPruefungszeitraum(): enthält die externen Bewerber und
        // lässt Prüfungen anderer Jahrgänge außen vor.
        $oralExams = $zeitraum !== null
            ? StudentExam::imPruefungszeitraum($zeitraum)
                ->where('exam_type', 'oral')
                ->select('examiner_id', 'assessor_id')
                ->get()
            : collect();

        /** @var array<int, int> $oralExaminerCount */
        $oralExaminerCount = [];
        /** @var array<int, int> $oralAssessorCount */
        $oralAssessorCount = [];

        foreach ($oralExams as $exam) {
            if ($exam->examiner_id) {
                $oralExaminerCount[$exam->examiner_id] = ($oralExaminerCount[$exam->examiner_id] ?? 0) + 1;
            }
            if ($exam->assessor_id) {
                $oralAssessorCount[$exam->assessor_id] = ($oralAssessorCount[$exam->assessor_id] ?? 0) + 1;
            }
        }

        // --- Aufsichten: Kolloquium (time_slot_id) + Schriftl. (written_exam_year_date_id) ---
        $allSupervisions = Supervision::where(function ($q) use ($zeitraumId, $jahr): void {
            $q->whereHas('timeSlot', fn ($t) => $t->where('exam_period_id', $zeitraumId))
              ->orWhereHas('writtenExamYearDate', fn ($d) => $d->where('exam_year', $jahr));
        })->select('teacher_id')->get();

        /** @var array<int, int> $supervisionCount */
        $supervisionCount = [];
        foreach ($allSupervisions as $sup) {
            if ($sup->teacher_id) {
                $supervisionCount[$sup->teacher_id] = ($supervisionCount[$sup->teacher_id] ?? 0) + 1;
            }
        }

        // --- Zusammenbauen ---
        return $teachers->map(function (Teacher $teacher) use (
            $writtenByTeacher,
            $secondByTeacher,
            $oralExaminerCount,
            $oralAssessorCount,
            $supervisionCount
        ): array {
            $id = $teacher->id;
            $wc = $writtenByTeacher[$id]  ?? 0;
            $sc = $secondByTeacher[$id]   ?? 0;
            $oe = $oralExaminerCount[$id] ?? 0;
            $oa = $oralAssessorCount[$id] ?? 0;
            $sv = $supervisionCount[$id]  ?? 0;

            return [
                'teacher'            => $teacher,
                'written_courses'    => $wc,
                'second_corrections' => $sc,
                'oral_examiner'      => $oe,
                'oral_assessor'      => $oa,
                'supervisions'       => $sv,
                'total'              => $wc + $sc + $oe + $oa + $sv,
            ];
        });
    }

    /**
     * Übersichtsseite Lehrereinsatz.
     */
    public function index(): \Illuminate\View\View
    {
        $this->authorize('viewAny', Teacher::class);

        $stats = $this->buildStats();

        return view('written-exams.teacher-deployment.index', compact('stats'));
    }

    /**
     * PDF-Export (übernimmt Filter+Sortierung via Query-Parameter).
     */
    public function exportPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('viewAny', Teacher::class);

        $stats   = $this->applyRequestFilters($this->buildStats());

        $pdf = Pdf::loadView('exports.teacher-deployment-pdf', [
            'stats' => $stats,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Lehrereinsatz_' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Excel-Export (übernimmt Filter+Sortierung via Query-Parameter).
     */
    public function exportExcel(): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('viewAny', Teacher::class);

        $stats = $this->applyRequestFilters($this->buildStats());

        return Excel::download(
            new \App\Exports\TeacherDeploymentExport($stats),
            'Lehrereinsatz_' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Wendet Filter und Sortierung aus dem Request an.
     *
     * @param  \Illuminate\Support\Collection<int, array{teacher: \App\Models\Teacher, written_courses: int, second_corrections: int, oral_examiner: int, oral_assessor: int, supervisions: int, total: int}> $stats
     * @return \Illuminate\Support\Collection<int, array{teacher: \App\Models\Teacher, written_courses: int, second_corrections: int, oral_examiner: int, oral_assessor: int, supervisions: int, total: int}>
     */
    private function applyRequestFilters(Collection $stats): Collection
    {
        $search  = trim((string) request()->query('search', ''));
        $sortBy  = (string) request()->query('sort', 'name');
        $sortDir = (string) request()->query('dir', 'asc');

        if ($search !== '') {
            $lower = mb_strtolower($search);
            $stats = $stats->filter(function (array $row) use ($lower): bool {
                /** @var Teacher $teacher */
                $teacher  = $row['teacher'];
                $haystack = mb_strtolower(
                    $teacher->lastname . ' ' . $teacher->firstname . ' ' . $teacher->shortcode
                );
                return str_contains($haystack, $lower);
            });
        }

        return $this->applySorting($stats, $sortBy, $sortDir);
    }

    /**
     * Sortiert die Stats-Collection.
     *
     * @param  \Illuminate\Support\Collection<int, array{teacher: \App\Models\Teacher, written_courses: int, second_corrections: int, oral_examiner: int, oral_assessor: int, supervisions: int, total: int}> $stats
     * @return \Illuminate\Support\Collection<int, array{teacher: \App\Models\Teacher, written_courses: int, second_corrections: int, oral_examiner: int, oral_assessor: int, supervisions: int, total: int}>
     */
    private function applySorting(Collection $stats, string $sortBy, string $sortDir): Collection
    {
        $sorted = match ($sortBy) {
            'written'       => $stats->sortBy('written_courses'),
            'second'        => $stats->sortBy('second_corrections'),
            'oral_examiner' => $stats->sortBy('oral_examiner'),
            'oral_assessor' => $stats->sortBy('oral_assessor'),
            'supervisions'  => $stats->sortBy('supervisions'),
            'total'         => $stats->sortBy('total'),
            default         => $stats->sortBy(fn(array $r) => $r['teacher']->lastname . ' ' . $r['teacher']->firstname),
        };

        return ($sortDir === 'desc') ? $sorted->reverse()->values() : $sorted->values();
    }
}
