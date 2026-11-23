<?php

namespace App\Http\Controllers;

use App\Exports\OralFachausschuesseExport;
use App\Http\Controllers\Concerns\HasFachausschuessePdfBasics;
use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Services\CurrentExamPeriodService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;

class OralExamFormController extends Controller
{
    use HasFachausschuessePdfBasics;

    /**
     * Fachausschüsse Kolloquium Übersicht
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $examPeriod = $this->resolveExamPeriod($request);
        $year = $examPeriod->year;
        $examPeriods = app(CurrentExamPeriodService::class)->all();

        $fachausschuesseRows = $this->getFachausschuesseRows($examPeriod);

        return view('kolloquium.forms.index', compact(
            'year', 'examPeriods', 'fachausschuesseRows'
        ));
    }

    /**
     * Fachausschüsse als Excel-Datei exportieren.
     */
    public function fachausschuesseExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $examPeriod = $this->resolveExamPeriod($request);
        $filename   = 'Fachausschuesse_Kolloquium_' . $examPeriod->year . '.xlsx';

        return Excel::download(new OralFachausschuesseExport($examPeriod), $filename);
    }

    /**
     * Fachausschüsse als PDF (TCPDF, DIN A4 hoch).
     */
    public function fachausschuessePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $examPeriod = $this->resolveExamPeriod($request);
        $school     = School::first();

        $rows = $this->getFachausschuesseRows($examPeriod);

        $pdf = $this->buildFachausschuessePdf($rows, $examPeriod, $school);
        $filename = 'Fachausschuesse_Kolloquium_' . $examPeriod->year . '.pdf';

        return response($pdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Hilfsmethode zur Ermittlung der Datenzeilen
     */
    public function getFachausschuesseRows(ExamPeriod $examPeriod): array
    {
        $courses = Course::where('exam_period_id', $examPeriod->id)
            ->with(['studentExams' => function($q) {
                $q->where('exam_type', 'oral')->with(['examiner', 'assessor']);
            }])
            ->orderBy('course_code')
            ->get();

        $fachausschuesseRows = [];
        foreach ($courses as $course) {
            $oralExams = $course->studentExams;
            if ($oralExams->isEmpty()) {
                continue;
            }

            $anzahl = $oralExams->count();

            // Group first examiners (Erstprüfer)
            $examiners = [];
            foreach ($oralExams as $exam) {
                $name = $this->teacherLabel($exam->examiner);
                $examiners[$name] = ($examiners[$name] ?? 0) + 1;
            }
            
            // Group assessors (Zweitprüfer)
            $assessors = [];
            foreach ($oralExams as $exam) {
                $name = $this->teacherLabel($exam->assessor);
                $assessors[$name] = ($assessors[$name] ?? 0) + 1;
            }

            $formatTeacherCounts = function($teachers, $total) {
                if (empty($teachers)) return '—';
                // If there is only one distinct teacher for the whole course, just return the name
                if (count($teachers) === 1) {
                    $keys = array_keys($teachers);
                    $firstKey = $keys[0];
                    if ($firstKey === '—') return '—';
                    return $firstKey;
                }
                // Multiple teachers: append counts
                $parts = [];
                foreach ($teachers as $name => $count) {
                    if ($name === '—') continue;
                    $parts[] = "{$name} ({$count})";
                }
                return empty($parts) ? '—' : implode(', ', $parts);
            };

            $fachausschuesseRows[] = [
                'kurs'     => $course->course_code,
                'anzahl'   => $anzahl,
                'erst'     => $formatTeacherCounts($examiners, $anzahl),
                'zweit'    => $formatTeacherCounts($assessors, $anzahl),
            ];
        }

        return $fachausschuesseRows;
    }

    /**
     * Baut das TCPDF-Dokument für die Fachausschüsse Kolloquium (DIN A4 hoch).
     *
     * @param array<int, array{kurs: string, anzahl: int, erst: string, zweit: string}> $rows
     */
    private function buildFachausschuessePdf(array $rows, ExamPeriod $examPeriod, ?School $school): TCPDF
    {
        $pdf = $this->initFachausschuessePdf($school);
        $pageW = 170.0; // 210 - 20 - 20

        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell($pageW, 7, 'Fachausschüsse Kolloquium ' . $examPeriod->year, 0, 1, 'C');

        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell($pageW, 5, 'Stand: ' . now()->format('d.m.Y'), 0, 1, 'R');
        $pdf->Ln(4);

        // ── Tabellenkopf ────────────────────────────────────────────
        $colW = [30, 22, 55, 63]; // Kurs | Anzahl | Erstprüfer | Zweitprüfer

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(200, 200, 200);   // Hellgrau entsprechend Screenshot
        $pdf->SetTextColor(0, 0, 0);

        $headers = ['Kurs', 'Anzahl', 'Erstprüfer', 'Zweitprüfer'];
        $aligns  = ['L', 'L', 'L', 'L'];
        foreach ($headers as $i => $h) {
            $pdf->Cell($colW[$i], 7, $h, 1, 0, $aligns[$i], true);
        }
        $pdf->Ln();

        // ── Datenzeilen ─────────────────────────────────────────────
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);

        foreach ($rows as $idx => $row) {
            // No striped filling in screenshot, but we can do a subtle one or none
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($colW[0], 6, $row['kurs'],              1, 0, 'L', true);
            $pdf->Cell($colW[1], 6, (string) $row['anzahl'],   1, 0, 'L', true);
            $pdf->Cell($colW[2], 6, $row['erst'],              1, 0, 'L', true);
            $pdf->Cell($colW[3], 6, $row['zweit'],             1, 0, 'L', true);
            $pdf->Ln();
        }

        return $pdf;
    }
}
