<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\StudentExam;
use Illuminate\Support\Collection;

/**
 * Fach-Statistik für einen Prüfungszeitraum: Anzahl schriftlicher/mündlicher
 * Prüfungen je Fach und Anspruchsniveau (grundlegend/erhöht), sortiert nach
 * Gesamtzahl absteigend.
 *
 * Vorher als private Methode in StudentController dupliziert (dort
 * `calculateSubjectStats()`) und ein zweites Mal inline in
 * SubjectStatsExport::collection() (phpcpd-Befund 22.09.2026, siehe
 * claude/code-duplizierung-22-09.md).
 */
class SubjectStatsService
{
    /**
     * @return Collection<int, array{
     *     subject_id: int, subject_name: string, is_deutsch_or_math: bool,
     *     grundlegend_written: int, grundlegend_oral: int,
     *     erhoeht_written: int, erhoeht_oral: int, total: int
     * }>
     */
    public function calculate(ExamPeriod $examPeriod, string $stufe = '13'): Collection
    {
        // Eine Jahrgangsstufe. Vorher über forAbiturStudentsOf(), das über
        // class_name filtert — der Import setzt keine Klasse, also zählten
        // Q12 und Q13 zusammen.
        $allExams = StudentExam::whereHas(
                'student',
                fn ($q) => $q->where('exam_period_id', $examPeriod->id)->derStufe($stufe)
            )
            ->with(['course.subject', 'course'])
            ->get();

        return $allExams
            ->groupBy(fn($exam) => $exam->course->subject->id)
            ->map(function ($exams) {
                $subject         = $exams->first()->course->subject;
                $isDeutschOrMath = in_array($subject->name, ['Deutsch', 'Mathematik']);

                if ($isDeutschOrMath) {
                    $grundlegendWritten = 0;
                    $grundlegendOral    = 0;
                    $erhoehtWritten     = $exams->where('exam_type', 'written')->count();
                    $erhoehtOral        = $exams->where('exam_type', 'oral')->count();
                } else {
                    $grundlegendWritten = $exams->filter(fn($e) => $e->course->level === 'grundlegend' && $e->exam_type === 'written')->count();
                    $grundlegendOral    = $exams->filter(fn($e) => $e->course->level === 'grundlegend' && $e->exam_type === 'oral')->count();
                    $erhoehtWritten     = $exams->filter(fn($e) => $e->course->level === 'erhoeht' && $e->exam_type === 'written')->count();
                    $erhoehtOral        = $exams->filter(fn($e) => $e->course->level === 'erhoeht' && $e->exam_type === 'oral')->count();
                }

                return [
                    'subject_id'          => (int) $subject->id,
                    'subject_name'        => (string) $subject->name,
                    'is_deutsch_or_math'  => $isDeutschOrMath,
                    'grundlegend_written' => (int) $grundlegendWritten,
                    'grundlegend_oral'    => (int) $grundlegendOral,
                    'erhoeht_written'     => (int) $erhoehtWritten,
                    'erhoeht_oral'        => (int) $erhoehtOral,
                    'total'               => (int) ($grundlegendWritten + $grundlegendOral + $erhoehtWritten + $erhoehtOral),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
