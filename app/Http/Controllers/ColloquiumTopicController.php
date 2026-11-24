<?php

namespace App\Http\Controllers;

use App\Models\ColloquiumTopic;
use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\StudentExam;
use App\Services\ColloquiumTopicImportService;
use App\Services\CouplingLetterService;
use App\Services\CurrentExamPeriodService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kolloquiums-Themenkatalog (Modul 4.7).
 *
 * Ablauf: Vorlage hochladen -> Vorschau prüfen (Zuordnung, Überschreibungen)
 * -> ausgewählte Kurse übernehmen. Die Vorschau ist nötig, weil die Kurskürzel
 * einen Jahrgangs-Präfix tragen und eine Vorlage des falschen Jahrgangs sonst
 * unbemerkt ins Leere liefe.
 */
class ColloquiumTopicController extends Controller
{
    private const UPLOAD_DIR = 'colloquium-topic-imports';

    public function __construct(
        private ColloquiumTopicImportService $importer
    ) {}

    /**
     * Übersicht: aktueller Themenstand je Kurs + Upload der Vorlage.
     */
    public function index(Request $request): View
    {
        $examPeriod  = $this->resolveExamPeriod($request);
        $examPeriods = app(CurrentExamPeriodService::class)->all();

        $courses = Course::with(['subject', 'teacher', 'colloquiumTopics'])
            ->where('exam_period_id', $examPeriod->id)
            ->derStufe('13')
            ->get()
            ->sortBy('course_code');

        return view('kolloquium.topics.index', [
            'examPeriod'  => $examPeriod,
            'examPeriods' => $examPeriods,
            'year'        => $examPeriod->year,
            'courses'     => $courses,
            'topicCount'  => $courses->sum(fn (Course $c): int => $c->colloquiumTopics->count()),
        ]);
    }

    /**
     * Vorlage hochladen und Zuordnung prüfen, bevor etwas geschrieben wird.
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [], ['file' => 'Vorlage']);

        $examPeriod = $this->resolveExamPeriod($request);

        // Datei zwischenspeichern: Die Übernahme erfolgt in einem zweiten
        // Request, die Vorschau darf den Upload nicht verlieren.
        $storedPath = $request->file('file')->store(self::UPLOAD_DIR);

        if ($storedPath === false) {
            return back()->with('error', 'Die Datei konnte nicht zwischengespeichert werden.');
        }

        $preview = $this->importer->parsePreview(
            Storage::path($storedPath),
            $examPeriod->id
        );

        return view('kolloquium.topics.preview', [
            'examPeriod'   => $examPeriod,
            'year'         => $examPeriod->year,
            'preview'      => $preview,
            'storedPath'   => $storedPath,
            'originalName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    /**
     * Übernahme der in der Vorschau ausgewählten Kurse.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stored_path'   => ['required', 'string'],
            'course_codes'  => ['required', 'array', 'min:1'],
            'course_codes.*' => ['string'],
        ], [
            'course_codes.required' => 'Es wurde kein Kurs zur Übernahme ausgewählt.',
        ]);

        $examPeriod = $this->resolveExamPeriod($request);
        $storedPath = $validated['stored_path'];

        // Nur Dateien aus dem eigenen Upload-Verzeichnis akzeptieren.
        if (!str_starts_with($storedPath, self::UPLOAD_DIR . '/') || !Storage::exists($storedPath)) {
            return redirect()
                ->route('kolloquium.topics.index', ['year' => $examPeriod->year])
                ->with('error', 'Die hochgeladene Datei wurde nicht mehr gefunden. Bitte erneut hochladen.');
        }

        $result = $this->importer->import(
            Storage::path($storedPath),
            $examPeriod->id,
            array_values($validated['course_codes'])
        );

        Storage::delete($storedPath);

        $message = sprintf(
            '%d Kurse übernommen, %d Themen gespeichert.',
            $result['imported_count'],
            $result['topic_count']
        );

        if ($result['unmatched_count'] > 0) {
            $message .= sprintf(' %d Zeilen ohne passenden Kurs übersprungen.', $result['unmatched_count']);
        }

        return redirect()
            ->route('kolloquium.topics.index', ['year' => $examPeriod->year])
            ->with('success', $message);
    }

    /**
     * Themen eines Kurses verwerfen.
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        ColloquiumTopic::where('course_id', $course->id)->delete();

        return back()->with('success', 'Themen für Kurs ' . $course->course_code . ' entfernt.');
    }

    /**
     * Koppelformular als PDF: ein Blatt je Kurs mit Themenkatalog und der
     * Liste der Kolloquiums-Schüler. Die Spalte "Gruppe" bleibt leer – die
     * Lehrkraft trägt dort A, B, C ... für die inhaltlichen Kopplungen ein.
     *
     * Gedruckt werden nur Kurse, für die bereits Themen erfasst sind: Ohne
     * Themenkatalog fehlt der Lehrkraft die Grundlage für die Kopplung.
     */
    public function formPdf(Request $request): Response
    {
        $examPeriod = $this->resolveExamPeriod($request);
        $school     = School::first();

        $courses = Course::with([
                'subject',
                'teacher',
                'colloquiumTopics',
                'studentExams' => function ($query): void {
                    $query->where('exam_type', 'oral')->derStufe('13')->with(['student', 'examiner']);
                },
            ])
            ->where('exam_period_id', $examPeriod->id)
            ->derStufe('13')
            ->get()
            ->filter(fn (Course $course): bool => $course->studentExams->isNotEmpty()
                && $course->colloquiumTopics->isNotEmpty())
            ->sortBy('course_code')
            ->values();

        $pdf = Pdf::loadView('exports.colloquium-topic-forms', [
            'examPeriod' => $examPeriod,
            'school'     => $school,
            'courses'    => $courses,
        ])->setPaper('a4', 'portrait');

        $filename = 'Kolloquium_Kopplungen_' . $examPeriod->year . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Erfassung der Gruppenbuchstaben von den zurückgelaufenen Formularen.
     *
     * Eine lange Liste über alle Kurse: Die OSK arbeitet den Formularstapel
     * von oben nach unten ab.
     */
    public function couplings(Request $request): View
    {
        $examPeriod  = $this->resolveExamPeriod($request);
        $examPeriods = app(CurrentExamPeriodService::class)->all();

        $courses = Course::with([
                'subject',
                'teacher',
                'colloquiumTopics',
                'studentExams' => function ($query): void {
                    $query->where('exam_type', 'oral')
                        ->derStufe('13')
                        ->with('student')
                        ->orderBy('id');
                },
            ])
            ->where('exam_period_id', $examPeriod->id)
            ->derStufe('13')
            ->get()
            ->filter(fn (Course $course): bool => $course->studentExams->isNotEmpty())
            ->sortBy('course_code')
            ->values();

        $letters = new CouplingLetterService();

        // Aktueller Stand als Buchstaben, damit die Felder vorbelegt sind.
        $currentLetters = [];
        foreach ($courses as $course) {
            $currentLetters += $letters->lettersFor($course->studentExams);
        }

        return view('kolloquium.topics.couplings', [
            'examPeriod'     => $examPeriod,
            'examPeriods'    => $examPeriods,
            'year'           => $examPeriod->year,
            'courses'        => $courses,
            'currentLetters' => $currentLetters,
        ]);
    }

    /**
     * Übernahme der eingetippten Gruppenbuchstaben.
     */
    public function saveCouplings(Request $request, CouplingLetterService $letters): RedirectResponse
    {
        $validated = $request->validate([
            'letters'   => ['array'],
            'letters.*' => ['nullable', 'string', 'max:3'],
        ]);

        $examPeriod  = $this->resolveExamPeriod($request);
        $letterInput = $validated['letters'] ?? [];

        // Nur Kurse anfassen, deren Prüfungen auch tatsächlich im Formular
        // standen. Sonst würde apply() bei einem Kurs, der gar nicht angezeigt
        // wurde, dessen bestehende Kopplungen zurücksetzen.
        $examIds = array_map('intval', array_keys($letterInput));

        $courseIds = StudentExam::whereIn('id', $examIds)
            ->where('exam_type', 'oral')
            ->pluck('course_id')
            ->unique();

        $courses = Course::whereIn('id', $courseIds)
            ->where('exam_period_id', $examPeriod->id)
            ->derStufe('13')
            ->get();

        $totalGroups = 0;
        $totalSingle = 0;

        foreach ($courses as $course) {
            $stats = $letters->apply($course, $letterInput);

            $totalGroups += $stats['groups'];
            $totalSingle += $stats['single'];
        }

        $message = sprintf('%d Koppelgruppen gespeichert.', $totalGroups);

        if ($totalSingle > 0) {
            $message .= sprintf(
                ' Achtung: %d Buchstaben kommen in ihrem Kurs nur einmal vor und ergeben keine Gruppe.',
                $totalSingle
            );
        }

        return redirect()
            ->route('kolloquium.topics.couplings', ['year' => $examPeriod->year])
            ->with($totalSingle > 0 ? 'warning' : 'success', $message);
    }

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
}
