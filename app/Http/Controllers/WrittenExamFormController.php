<?php

namespace App\Http\Controllers;

use App\Exports\FachausschuesseExport;
use App\Http\Controllers\Concerns\HasFachausschuessePdfBasics;
use App\Models\Course;
use App\Models\WrittenExamYearDate;
use App\Models\WrittenExamAssignment;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\Student;
use App\Services\CurrentExamPeriodService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;

/**
 * Controller für Formulare der Schriftlichen Prüfungen.
 * Aktuell: Mantelbögen (DIN A3 quer, ein Bogen pro Schüler).
 * Verwendet TCPDF für präzise mm-genaue Positionierung.
 */
class WrittenExamFormController extends Controller
{
    use HasFachausschuessePdfBasics;

    /**
     * Übersicht: alle Prüfungstermine mit Checkboxen zur Auswahl.
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $examPeriod = $this->resolveExamPeriod($request);
        $year = $examPeriod->year;

        $yearDates = WrittenExamYearDate::with(['slot.subjects'])
            ->where('exam_year', $year)
            ->orderBy('exam_date')
            ->get();

        $assignmentCounts = WrittenExamAssignment::selectRaw('written_exam_year_date_id, count(*) as cnt')
            ->groupBy('written_exam_year_date_id')
            ->pluck('cnt', 'written_exam_year_date_id');

        $studentCounts = [];
        foreach ($yearDates as $yd) {
            $assignments = WrittenExamAssignment::where('written_exam_year_date_id', $yd->id)
                ->with('course.writtenStudents')
                ->get();
            $total = 0;
            foreach ($assignments as $asgn) {
                $total += $asgn->getEffectiveStudents()->count();
            }
            $studentCounts[$yd->id] = $total;
        }

        $examPeriods = app(CurrentExamPeriodService::class)->all();

        // ── Fachausschüsse-Daten ermitteln ──
        $courses = Course::where('exam_period_id', $examPeriod->id)
            ->with(['teacher', 'secondCorrector', 'studentExams'])
            ->orderBy('course_code')
            ->get();

        $fachausschuesseRows = [];
        foreach ($courses as $course) {
            $writtenCount = $course->studentExams->filter(fn($e) => $e->isWrittenExam())->count();
            if ($writtenCount === 0) {
                continue;
            }
            $fachausschuesseRows[] = [
                'kurs'     => $course->course_code,
                'anzahl'   => $writtenCount,
                'erst'     => $this->teacherLabel($course->teacher),
                'zweit'    => $this->teacherLabel($course->secondCorrector),
            ];
        }

        return view('written-exams.forms.index', compact(
            'yearDates', 'assignmentCounts', 'studentCounts', 'year', 'examPeriods', 'fachausschuesseRows'
        ));
    }

    /**
     * Mantelbögen als PDF erzeugen (DIN A3 quer, TCPDF, mm-präzise).
     */
    public function mantelbogen(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate([
            'year_date_ids'   => 'required|array|min:1',
            'year_date_ids.*' => 'integer',
        ]);

        $selectedIds = $request->input('year_date_ids');

        $yearDates = WrittenExamYearDate::with(['slot.subjects'])
            ->whereIn('id', $selectedIds)
            ->orderBy('exam_date')
            ->get();

        $school = School::first();

        // Alle Seiten sammeln
        $pages = [];
        foreach ($yearDates as $yearDate) {
            $assignments = WrittenExamAssignment::where('written_exam_year_date_id', $yearDate->id)
                ->with([
                    'course.subject',
                    'course.teacher',
                    'course.secondCorrector',
                    'room',
                    'seatAssignments.student', 'seatAssignments.externalApplicant',
                    'seatAssignments.seat',
                ])
                ->get();

            foreach ($assignments as $assignment) {
                $seatAssignments = $assignment->seatAssignments;
                if ($seatAssignments->isNotEmpty()) {
                    // Die Platznummer stand bis zum 27.08. immer auf null:
                    // gelesen wurde seat->seat_number, eine Spalte, die es
                    // nicht gibt. Die Nummern kommen jetzt aus dem Layout.
                    $platznummern = $assignment->platznummern();
                    foreach ($seatAssignments as $sa) {
                        if ($sa->student) {
                            $pages[] = [
                                'student'    => $sa->student,
                                'assignment' => $assignment,
                                'yearDate'   => $yearDate,
                                'seatNumber' => isset($platznummern[$sa->seat_id])
                                    ? (string) $platznummern[$sa->seat_id]
                                    : null,
                            ];
                        }
                    }
                } else {
                    foreach ($assignment->getEffectiveStudents() as $student) {
                        $pages[] = [
                            'student'    => $student,
                            'assignment' => $assignment,
                            'yearDate'   => $yearDate,
                            'seatNumber' => null,
                        ];
                    }
                }
            }
        }

        $pdf = $this->buildPdf($pages, $school);

        $filename = 'Mantelboegen_' . now()->format('Y-m-d') . '.pdf';

        return response($pdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Baut das TCPDF-Dokument mit allen Mantelbögen.
     *
     * A3 landscape = 420 × 297 mm
     * Linke Hälfte: 0–210 mm (leer, für Prüfungsblätter)
     * Rechte Hälfte: 210–420 mm = Formular (~DIN A4 hoch)
     *
     * @param array<int, array{student: Student, assignment: WrittenExamAssignment, yearDate: WrittenExamYearDate, seatNumber: string|null}> $pages
     */
    private function buildPdf(array $pages, ?School $school): TCPDF
    {
        // ---- TCPDF initialisieren ----
        $pdf = new TCPDF('L', 'mm', 'A3', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont('helvetica', '', 9);

        // ---- Formular-Konstanten (alle in mm, bezogen auf rechte Seitenhälfte) ----
        // X-Offset der rechten Hälfte
        $ox = 213.0; // linke Kante des Formulars
        $ow = 203.0; // Breite des Formulars (bis 416 mm)
        $oy = 8.0;   // oberer Rand

        $schoolName = $school ? ($school->name ?? $school->short_name ?? '') : '';

        foreach ($pages as $page) {
            /** @var Student $student */
            $student    = $page['student'];
            $assignment = $page['assignment'];
            $yearDate   = $page['yearDate'];
            $seatNumber = $page['seatNumber'];

            $examYear    = $yearDate->exam_year;
            $examDate    = Carbon::parse($yearDate->exam_date)->locale('de')->translatedFormat('d.m.Y');
            $startTime   = Carbon::parse($yearDate->slot->start_time)->format('H:i');
            $endTime     = $yearDate->getEndTime($student->disability_compensation_percent)->format('H:i');
            $examTime    = $startTime . ' – ' . $endTime . ' Uhr';
            $firstSubject = $yearDate->slot->subjects->first();
            $subjectName  = $firstSubject !== null
                            ? $firstSubject->name
                            : ($assignment->course->subject->name ?? '—');
            $courseCode   = $assignment->course->course_code ?? '—';

            $t1 = $assignment->course->teacher;
            $t2 = $assignment->course->secondCorrector;
            $t1Name = $t1 ? trim(($t1->title ? $t1->title . ' ' : '') . $t1->lastname . ($t1->shortcode ? ', ' . $t1->shortcode : '')) : '';
            $t2Name = $t2 ? trim(($t2->title ? $t2->title . ' ' : '') . $t2->lastname . ($t2->shortcode ? ', ' . $t2->shortcode : '')) : '';

            $pdf->AddPage();

            // ── Mittellinie ──────────────────────────────────────────────
            // Keine Mittellinie gewünscht → weggelassen

            $this->drawFormular($pdf, $ox, $oy, $ow,
                $schoolName, $student->full_name,
                (string) $examYear, $examDate, $examTime,
                $subjectName, $courseCode,
                $seatNumber ?? '',
                $t1Name, $t2Name
            );
        }

        return $pdf;
    }

    /**
     * Zeichnet ein Mantelbogen-Formular auf die rechte A4-Hälfte.
     *
     * @param float $ox  X-Start des Formulars in mm
     * @param float $oy  Y-Start (oberer Rand) in mm
     * @param float $ow  Breite des Formulars in mm
     */
    private function drawFormular(
        TCPDF $pdf,
        float $ox, float $oy, float $ow,
        string $schoolName,
        string $studentName,
        string $examYear,
        string $examDate,
        string $examTime,
        string $subjectName,
        string $courseCode,
        string $seatNumber,
        string $t1Name,
        string $t2Name
    ): void {
        // Hilfsfunktionen
        // $line(x1,y1,x2,y2) – Unterlinie
        $line = function(float $x1, float $y1, float $x2, float $y2) use ($pdf): void {
            $pdf->Line($x1, $y1, $x2, $y2);
        };
        // $text(x,y,w,h,str,align,font,size,style)
        $text = function(float $x, float $y, float $w, float $h, string $str,
                         string $align = 'L', float $size = 9.0, string $style = '') use ($pdf): void {
            $pdf->SetFont('helvetica', $style, $size);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $str, 0, 0, $align);
        };
        $small = function(float $x, float $y, float $w, string $str, string $align = 'C') use ($pdf): void {
            $pdf->SetFont('helvetica', '', 7.0);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 4, $str, 0, 0, $align);
        };

        // ═══════════════════════════════════════════════════════════
        // 1. Schulname (zentriert über das gesamte Formular)
        // ═══════════════════════════════════════════════════════════
        $text($ox, $oy, $ow, 5, $schoolName, 'C', 10, 'B');

        // ═══════════════════════════════════════════════════════════
        // 2. Name + Abiturprüfung-Titel
        // ═══════════════════════════════════════════════════════════
        $y = $oy + 10;

        // Linke Seite: Name (Unterlinie bis ~halbe Breite)
        $nameW = $ow * 0.52;
        $text($ox, $y, $nameW, 6, $studentName, 'L', 12, 'B');
        $line($ox, $y + 6, $ox + $nameW, $y + 6);
        $small($ox, $y + 7, $nameW, '(Name)', 'C');

        // Rechte Seite: "Abiturprüfung 2026"
        $text($ox + $nameW, $y, $ow - $nameW, 6, 'Abiturprüfung ' . $examYear, 'R', 13, 'B');

        // ═══════════════════════════════════════════════════════════
        // 3. Tag der Prüfung / Prüfungszeit (nur rechte Spalte)
        // ═══════════════════════════════════════════════════════════
        $y += 16;
        $labelX = $ox + $ow * 0.52;
        $valX   = $labelX + 33;

        $text($labelX, $y,      33, 5, 'Tag der Prüfung:', 'L', 9);
        $text($valX,   $y,      60, 5, $examDate,          'L', 9);
        $text($labelX, $y + 5,  33, 5, 'Prüfungszeit:',    'L', 9);
        $text($valX,   $y + 5,  60, 5, $examTime,          'L', 9);

        // ═══════════════════════════════════════════════════════════
        // 4. Platznummer + Fach / Kurs
        // ═══════════════════════════════════════════════════════════
        $y += 17;
        $col2X = $ox + $ow * 0.52; // X-Start der rechten Infospalte

        // Platznummer (links)
        $text($ox, $y, 30, 5, 'Platznummer:', 'L', 9);
        $line($ox + 31, $y + 5, $ox + 31 + 22, $y + 5);
        $text($ox + 31, $y, 22, 5, $seatNumber, 'L', 9);

        // Fach (rechts)
        $text($col2X, $y, 14, 5, 'Fach:', 'L', 9);
        $fachW = $ow - ($col2X - $ox) - 14;
        $text($col2X + 14, $y, $fachW, 5, $subjectName, 'L', 11, 'B');
        $line($col2X + 14, $y + 5, $ox + $ow, $y + 5);
        $small($col2X + 14, $y + 6, $fachW, '(Prüfungsfach)', 'C');

        // Kurs (rechts, nächste Zeile)
        $y += 10;
        $text($col2X, $y, 14, 5, 'Kurs:', 'L', 9);
        $text($col2X + 14, $y, $fachW, 5, $courseCode, 'L', 11, 'B');
        $line($col2X + 14, $y + 5, $ox + $ow, $y + 5);

        // ═══════════════════════════════════════════════════════════
        // 5. Bewertung
        // ═══════════════════════════════════════════════════════════
        $y += 12;
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY($ox, $y);
        $pdf->Cell($ow, 5, 'Bewertung:', 0, 0, 'L');
        $line($ox, $y + 5, $ox + $ow, $y + 5);

        $y += 7;
        $this->drawPunktRow($pdf, $line, $small, $text, $ox, $ow, $y, '1. Berichterstatter/in:', $t1Name);
        $y += 14;
        $this->drawPunktRow($pdf, $line, $small, $text, $ox, $ow, $y, '2. Berichterstatter/in:', $t2Name);
        $y += 14;

        // Einvernehmlich
        $text($ox, $y, $ow, 5, 'Einvernehmlich erzielte Punkte/Note:', 'L', 8.5);
        $y += 6;
        $this->drawPunktRowShort($pdf, $line, $small, $text, $ox, $ow, $y);
        $y += 13;

        // Drittentscheid
        $text($ox, $y, 50, 5, 'Drittentscheid durch:', 'L', 8.5);
        $line($ox + 51, $y + 5, $ox + 51 + 55, $y + 5);
        $y += 7;
        $this->drawPunktRow($pdf, $line, $small, $text, $ox, $ow, $y, '', '');
        $y += 14;

        // ═══════════════════════════════════════════════════════════
        // 6. Fußbereich: Zeitangaben + Einsichtnahme
        // ═══════════════════════════════════════════════════════════
        $this->drawFussbereich($pdf, $line, $text, $ox, $oy, $ow, $y);
    }

    /**
     * Punkte/Note-Zeile mit Berichterstatter-Label und Unterschrift.
     *
     * @param callable $line
     * @param callable $small
     * @param callable $text
     */
    private function drawPunktRow(
        TCPDF $pdf,
        callable $line,
        callable $small,
        callable $text,
        float $ox, float $ow, float $y,
        string $label,
        string $teacherName
    ): void {
        if ($label !== '') {
            $text($ox, $y, $ow, 5, $label, 'L', 8.5);
            $y += 5.5;
        }

        // Spaltenbreiten (relativ zu $ow)
        $c1 = $ow * 0.18; // Punkte-Feld
        $c2 = $ow * 0.25; // Note-Feld
        $c3 = $ow * 0.03; // "="
        $c4 = $ow * 0.22; // Wortbezeichnung
        $c5 = $ow * 0.32; // Unterschrift

        $x1 = $ox;
        $x2 = $x1 + $c1;
        $x3 = $x2 + $c2;
        $x4 = $x3 + $c3;
        $x5 = $x4 + $c4;

        $this->drawPunkteNoteFields($line, $small, $text, $x1, $c1, $x2, $c2, $x3, $c3, $x4, $c4, $y);

        // Unterschrift
        $text($x5, $y, 22, 5, 'Unterschrift:', 'L', 8.5);
        $line($x5 + 23, $y + 5, $x5 + 23 + ($c5 - 24), $y + 5);

        // Lehrername (klein, rechtsbündig unter Unterschriftslinie)
        if ($teacherName !== '') {
            $pdf->SetFont('helvetica', '', 7.0);
            $pdf->SetXY($x5, $y + 6);
            $pdf->Cell($c5, 4, $teacherName, 0, 0, 'R');
        }
    }

    /**
     * Verkürzte Punkte-Zeile für "Einvernehmlich" (ohne Unterschrift).
     *
     * @param callable $line
     * @param callable $small
     * @param callable $text
     */
    private function drawPunktRowShort(
        TCPDF $pdf,
        callable $line,
        callable $small,
        callable $text,
        float $ox, float $ow, float $y
    ): void {
        $c1 = $ow * 0.18;
        $c2 = $ow * 0.25;
        $c3 = $ow * 0.03;
        $c4 = $ow * 0.22;

        $x1 = $ox;
        $x2 = $x1 + $c1;
        $x3 = $x2 + $c2;
        $x4 = $x3 + $c3;

        $this->drawPunkteNoteFields($line, $small, $text, $x1, $c1, $x2, $c2, $x3, $c3, $x4, $c4, $y);
    }

    /**
     * Zeichnet den gemeinsamen Punkte-/Note-/Wortbezeichnung-Block einer
     * Punkte-Zeile. Genutzt von drawPunktRow() (mit Unterschrift) und
     * drawPunktRowShort() (ohne Unterschrift, für "Einvernehmlich").
     *
     * @param callable $line
     * @param callable $small
     * @param callable $text
     */
    private function drawPunkteNoteFields(
        callable $line,
        callable $small,
        callable $text,
        float $x1, float $c1,
        float $x2, float $c2,
        float $x3, float $c3,
        float $x4, float $c4,
        float $y
    ): void {
        $text($x1, $y, $c1, 5, 'Punkte:', 'L', 8.5);
        $line($x1 + 17, $y + 5, $x1 + 17 + 12, $y + 5);

        $text($x2, $y, $c2, 5, 'Note:', 'L', 8.5);
        $line($x2 + 13, $y + 5, $x2 + 13 + 16, $y + 5);
        $small($x2 + 10, $y + 5.5, 20, '(mit Tendenz)', 'C');

        $text($x3, $y, $c3, 5, '=', 'C', 9);

        $line($x4, $y + 5, $x4 + $c4 - 2, $y + 5);
        $small($x4, $y + 5.5, $c4 - 2, '(Wortbezeichnung)', 'C');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Fachausschüsse
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Fachausschüsse als Excel-Datei exportieren.
     */
    public function fachausschuesseExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $examPeriod = $this->resolveExamPeriod($request);
        $filename   = 'Fachausschuesse_' . $examPeriod->year . '.xlsx';

        return Excel::download(new FachausschuesseExport($examPeriod), $filename);
    }

    /**
     * Fachausschüsse als PDF (TCPDF, DIN A4 hoch).
     */
    public function fachausschuessePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $examPeriod = $this->resolveExamPeriod($request);
        $school     = School::first();

        $courses = Course::where('exam_period_id', $examPeriod->id)
            ->with(['teacher', 'secondCorrector', 'studentExams'])
            ->orderBy('course_code')
            ->get();

        $rows = [];
        foreach ($courses as $course) {
            $writtenCount = $course->studentExams->filter(fn($e) => $e->isWrittenExam())->count();
            if ($writtenCount === 0) {
                continue;
            }
            $rows[] = [
                'kurs'     => $course->course_code,
                'anzahl'   => $writtenCount,
                'erst'     => $this->teacherLabel($course->teacher),
                'zweit'    => $this->teacherLabel($course->secondCorrector),
            ];
        }

        $pdf = $this->buildFachausschuessePdf($rows, $examPeriod, $school);
        $filename = 'Fachausschuesse_' . $examPeriod->year . '.pdf';

        return response($pdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Baut das TCPDF-Dokument für die Fachausschüsse (DIN A4 hoch).
     *
     * @param array<int, array{kurs: string, anzahl: int, erst: string, zweit: string}> $rows
     */
    private function buildFachausschuessePdf(array $rows, ExamPeriod $examPeriod, ?School $school): TCPDF
    {
        $pdf = $this->initFachausschuessePdf($school);
        $pageW = 170.0; // 210 - 20 - 20

        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell($pageW, 7, 'Fachausschüsse für die schriftlichen Abiturprüfungen ' . $examPeriod->year, 0, 1, 'C');

        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell($pageW, 5, 'Stand: ' . now()->format('d.m.Y'), 0, 1, 'C');
        $pdf->Ln(4);

        // ── Tabellenkopf ────────────────────────────────────────────
        $colW = [30, 22, 55, 55]; // Kurs | Anzahl | Erstprüfer | Zweitprüfer

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(31, 56, 100);   // Dunkelblau
        $pdf->SetTextColor(255, 255, 255);

        $headers = ['Kurs', 'Anzahl', 'Erstprüfer', 'Zweitprüfer'];
        $aligns  = ['L', 'C', 'L', 'L'];
        foreach ($headers as $i => $h) {
            $pdf->Cell($colW[$i], 7, $h, 1, 0, $aligns[$i], true);
        }
        $pdf->Ln();

        // ── Datenzeilen ─────────────────────────────────────────────
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);

        foreach ($rows as $idx => $row) {
            $fill = $idx % 2 === 0;
            if ($fill) {
                $pdf->SetFillColor(242, 242, 242);
            }
            $pdf->Cell($colW[0], 6, $row['kurs'],              1, 0, 'L', $fill);
            $pdf->Cell($colW[1], 6, (string) $row['anzahl'],   1, 0, 'C', $fill);
            $pdf->Cell($colW[2], 6, $row['erst'],              1, 0, 'L', $fill);
            $pdf->Cell($colW[3], 6, $row['zweit'],             1, 0, 'L', $fill);
            $pdf->Ln();
        }

        return $pdf;
    }

    /**
     * Fußbereich: Beginn/Abgabe/Bogen/Blätter/Ausgetreten + Einsichtnahme-Box.
     *
     * @param callable $line
     * @param callable $text
     */
    private function drawFussbereich(
        TCPDF $pdf,
        callable $line,
        callable $text,
        float $ox, float $oy, float $ow,
        float $y
    ): void {
        $fieldW = 15; // Breite der Schreibfelder
        $lineH  = 7;  // Zeilenabstand

        // Linke Spalte Zeitangaben
        $rows = [
            ['Beginn um:',       $fieldW, 'Uhr'],
            ['Abgegeben um:',    $fieldW, 'Uhr'],
            ['Anzahl der Bogen:', 12,     ''],
            ['Anzahl der Blätter:', 10,   ''],
        ];
        $cy = $y;
        foreach ($rows as $row) {
            [$label, $fw, $suffix] = $row;
            $lw = 46;
            $text($ox, $cy, $lw, 5, $label, 'L', 8.5);
            $line($ox + $lw, $cy + 5, $ox + $lw + $fw, $cy + 5);
            if ($suffix !== '') {
                $text($ox + $lw + $fw + 1, $cy, 10, 5, $suffix, 'L', 8.5);
            }
            $cy += $lineH;
        }

        // Angabe liegt bei: ja □ nein □
        $text($ox, $cy, 38, 5, 'Angabe liegt bei:', 'L', 8.5);
        $text($ox + 38, $cy, 6, 5, 'ja', 'L', 8.5);
        $pdf->Rect($ox + 44, $cy + 0.5, 3.5, 3.5); // Checkbox ja
        $text($ox + 49, $cy, 8, 5, 'nein', 'L', 8.5);
        $pdf->Rect($ox + 57, $cy + 0.5, 3.5, 3.5); // Checkbox nein
        $cy += $lineH + 1;

        // Ausgetreten von ... bis ... Uhr (3×)
        for ($i = 0; $i < 3; $i++) {
            $text($ox, $cy, 32, 5, 'Ausgetreten von', 'L', 8.5);
            $line($ox + 33, $cy + 5, $ox + 33 + $fieldW, $cy + 5);
            $text($ox + 33 + $fieldW + 1, $cy, 7, 5, 'bis', 'L', 8.5);
            $line($ox + 33 + $fieldW + 9, $cy + 5, $ox + 33 + $fieldW + 9 + $fieldW, $cy + 5);
            $text($ox + 33 + $fieldW + 9 + $fieldW + 1, $cy, 8, 5, 'Uhr', 'L', 8.5);
            $cy += $lineH;
        }

        // ── Einsichtnahme-Box (rechts unten) ──────────────────────
        // Box-Dimension
        $bx = $ox + $ow * 0.63;
        // Boden der Seite
        $pageH    = 297.0;
        $boxH     = 30.0;
        $boxW     = $ow - ($bx - $ox);
        $by       = $pageH - 13 - $boxH; // 13 mm Rand von unten

        $pdf->Rect($bx, $by, $boxW, $boxH);

        // Titel
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetXY($bx, $by + 1.5);
        $pdf->Cell($boxW, 5, 'Einsichtnahme', 0, 0, 'C');
        $line($bx, $by + 7, $bx + $boxW, $by + 7);

        // am ___
        $text($bx + 2, $by + 8.5, 6, 5, 'am', 'L', 8);
        $line($bx + 9, $by + 13, $bx + $boxW - 2, $by + 13);

        // von ___ bis ___
        $text($bx + 2, $by + 14.5, 8, 5, 'von', 'L', 8);
        $line($bx + 11, $by + 19, $bx + 11 + 14, $by + 19);
        $text($bx + 11 + 15, $by + 14.5, 6, 5, 'bis', 'L', 8);
        $line($bx + 11 + 22, $by + 19, $bx + $boxW - 2, $by + 19);

        // Unterschrift-Linie + Label
        $line($bx + 2, $by + $boxH - 7, $bx + $boxW - 2, $by + $boxH - 7);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($bx, $by + $boxH - 6);
        $pdf->Cell($boxW, 5, 'Unterschrift', 0, 0, 'C');
    }
}
