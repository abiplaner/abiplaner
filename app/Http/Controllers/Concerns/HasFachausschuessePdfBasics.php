<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ExamPeriod;
use App\Models\School;
use App\Services\CurrentExamPeriodService;
use Illuminate\Http\Request;
use TCPDF;

/**
 * Gemeinsame Bausteine für die Fachausschüsse-PDF-Controller
 * (OralExamFormController, WrittenExamFormController): ExamPeriod aus dem
 * year-Parameter auflösen, Lehrernamen als "Nachname V." formatieren, und
 * das TCPDF-Dokument mit optionaler Schulnamen-Kopfzeile initialisieren.
 *
 * (phpcpd-Befund 22.09.2026: 51 Zeilen Klon zwischen beiden Controllern,
 * siehe claude/code-duplizierung-22-09.md.) Die eigentliche Tabellen-
 * gestaltung der Fachausschüsse-PDFs (Titeltext, Spaltenbreiten,
 * Kopfzeilenfarbe, Zebra-Striping) unterscheidet sich bewusst zwischen
 * Kolloquium- und Schriftlich-Export und bleibt daher in den jeweiligen
 * buildFachausschuessePdf()-Methoden.
 */
trait HasFachausschuessePdfBasics
{
    /**
     * Ermittelt die ExamPeriod anhand des year-Parameters
     * (Fallback: aktuell gewählte Periode).
     */
    private function resolveExamPeriod(Request $request): ExamPeriod
    {
        $currentPeriod = app(CurrentExamPeriodService::class)->current();
        $year = $request->integer('year', $currentPeriod !== null ? $currentPeriod->year : (int) date('Y'));

        $period = ExamPeriod::where('year', $year)->first();
        return $period ?? ($currentPeriod ?? new ExamPeriod(['year' => $year, 'name' => (string) $year]));
    }

    /**
     * Gibt "Nachname V." zurück, z. B. "Kreil W.".
     *
     * @param \App\Models\Teacher|null $teacher
     */
    private function teacherLabel(?object $teacher): string
    {
        if ($teacher === null) {
            return '—';
        }
        $initial = ($teacher->firstname !== null && $teacher->firstname !== '')
            ? mb_substr($teacher->firstname, 0, 1) . '.'
            : '';
        return trim($teacher->lastname . ($initial !== '' ? ' ' . $initial : ''));
    }

    /**
     * Initialisiert ein TCPDF-Dokument (DIN A4 hoch, Ränder 20/15/20) und
     * schreibt optional den Schulnamen als erste Kopfzeile. Titel und
     * Tabelle fügt die aufrufende Methode selbst hinzu, da sich deren
     * Gestaltung zwischen den beiden Fachausschüsse-Exporten unterscheidet.
     */
    private function initFachausschuessePdf(?School $school): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 15, 20);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        $schoolName = $school !== null ? ($school->name ?? $school->short_name ?? '') : '';
        if ($schoolName !== '') {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(170.0, 6, $schoolName, 0, 1, 'C'); // 170 = 210 - 20 - 20 (Seitenbreite abzgl. Ränder)
        }

        return $pdf;
    }
}
