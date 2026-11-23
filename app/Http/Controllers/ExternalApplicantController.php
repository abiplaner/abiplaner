<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExternalApplicant;
use App\Models\ExamPeriod;
use App\Services\ExternalApplicantValidationService;
use App\Services\CurrentExamPeriodService;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ExternalApplicantController extends Controller
{
    protected $validationService;

    public function __construct(ExternalApplicantValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Standardjahr: Aktuell gewählte Periode oder Prüfungsjahr basierend auf Schuljahr
        // Ab September: nächstes Jahr ist Prüfungsjahr
        $currentExamYear = (int)date('n') >= 9 ? (int)date('Y') + 1 : (int)date('Y');
        
        $currentExamPeriod = app(CurrentExamPeriodService::class)->current();
        $defaultYear = $currentExamPeriod ? $currentExamPeriod->year : $currentExamYear;
        
        $year = $request->get('year', $defaultYear);
        
        // examSubjects.studentExam eager laden: die Zahl der bereits angelegten
        // Prüfungen hängt seit der Septemberrunde an der Gegenrichtung.
        $applicants = ExternalApplicant::with(['examSubjects.studentExam', 'examPeriod'])
            ->forYear($year)
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        
        // Berechne Statistiken für jeden Bewerber
        $applicants->each(function ($applicant) {
            $applicant->subject_count = $applicant->examSubjects->count();
            // Über die Beziehung, nicht über eine Spalte: die frühere Fassung
            // las `examSubjects.student_exam_id` — eine Spalte, die mit
            // `drop_spiegelspalten_from_external_exam_subjects` (26.08.2026)
            // gefallen ist. Der Zugriff lieferte seitdem stumm null, die
            // Spalte „Geplant" stand dauerhaft auf 0/8, und der Knopf
            // „Planen" erschien auch dann noch, wenn alle acht Prüfungen
            // längst existierten. Den Schlüssel trägt jetzt die Prüfung.
            $applicant->planned_count = $applicant->examSubjects
                ->filter(fn ($fach): bool => $fach->studentExam !== null)
                ->count();
            // Wie viele Prüfungen es überhaupt geben kann. Für einen
            // Ersatzschüler nach § 64 Abs. 2 sind das sechs statt acht: die
            // beiden eingebrachten Fächer werden nicht geprüft. Ohne diese
            // Zahl stünde dort dauerhaft „6/8" und der Knopf „Planen" bliebe
            // stehen, obwohl nichts mehr zu planen ist.
            $applicant->plannable_count = $applicant->examSubjects
                ->reject(fn ($fach): bool => $fach->istEingebracht())
                ->count();
            
            // Validierungsstatus
            $validation = $this->validationService->validateSubjectSelection($applicant);
            $applicant->validation_status = $validation['valid'] ? 'valid' : 'invalid';
            $applicant->validation_errors = $validation['errors'];
        });
        
        // Verfügbare Jahre: Aus ExamPeriods + bereits vorhandene externe Bewerber
        $yearsFromExamPeriods = ExamPeriod::select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');
        
        // Jahre von externen Bewerbern: Hole exam_period_ids und dann die Jahre
        $applicantExamPeriodIds = ExternalApplicant::select('exam_period_id')
            ->distinct()
            ->pluck('exam_period_id');
        
        $yearsFromApplicants = ExamPeriod::whereIn('id', $applicantExamPeriodIds)
            ->pluck('year');
        
        // Kombiniere und sortiere
        $years = $yearsFromExamPeriods
            ->concat($yearsFromApplicants)
            ->unique()
            ->sortDesc()
            ->values();
        
        // Falls keine Jahre vorhanden, zeige zumindest aktuelles Jahr
        if ($years->isEmpty()) {
            $years = collect([(int) date('Y')]);
        }

        // A8/B5-Fristen (§ 61 Abs. 1 S. 7 / Abs. 2 S. 5): nur anzeigbar, wenn
        // für das gewählte Jahr eine zentrale Prüfungsperiode existiert — ein
        // Jahr kann allein aus vorhandenen Bewerbern stammen (siehe oben).
        $fristenZeitraum = ExamPeriod::where('year', $year)->first();
        $fristen = null;

        if ($fristenZeitraum !== null) {
            $fristenService = app(\App\Services\ExterneFristenService::class);
            $a8 = $fristenService->a8Frist($fristenZeitraum);
            $b5 = $fristenService->b5Frist($fristenZeitraum);

            $fristen = [
                'a8' => ['datum' => $a8, 'verstrichen' => $fristenService->istVerstrichen($a8)],
                'b5' => ['datum' => $b5, 'verstrichen' => $fristenService->istVerstrichen($b5)],
            ];
        }

        return view('external-applicants.index', compact('applicants', 'years', 'year', 'fristen'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Prüfungsjahr von Index-Seite übernehmen oder neueste ExamPeriod verwenden
        $year = $request->get('year');
        
        if (!$year) {
            // Fallback: Aktuell gewählte Periode oder Prüfungsjahr basierend auf Schuljahr
            $currentExamPeriod = app(CurrentExamPeriodService::class)->current();
            $currentExamYear = (int)date('n') >= 9 ? (int)date('Y') + 1 : (int)date('Y');
            $year = $currentExamPeriod ? $currentExamPeriod->year : $currentExamYear;
        }
        
        // Hole die ExamPeriod für das Jahr
        $examPeriod = ExamPeriod::where('year', $year)->first();
        
        // Falls keine ExamPeriod existiert, hole alle verfügbaren Jahre
        $examPeriods = ExamPeriod::orderByDesc('year')->get();
        
        $ersatzschulen = \App\Models\Ersatzschule::orderBy('name')->get();

        return view('external-applicants.create', compact('examPeriod', 'examPeriods', 'year', 'ersatzschulen'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'birth_date' => 'required|date',
            'exam_period_id' => 'required|exists:exam_periods,id',
            // Nachteilsausgleich wie bei Schülern: ein Prozentsatz, um den
            // sich die Prüfungszeiten verlängern.
            'disability_compensation_percent' => 'nullable|integer|min:0|max:100',
            'disability_compensation_notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        
        $validated['ersatzschule_id'] = $this->ersatzschuleAusRequest($request);

        // Hole das Jahr aus der ExamPeriod für die Anonymisierung
        $examPeriod = ExamPeriod::findOrFail($validated['exam_period_id']);
        
        // Anonymisierung: 2 Jahre nach Abitur
        $validated['anonymize_at'] = now()->setYear($examPeriod->year + 2)->setMonth(12)->setDay(31);
        
        $applicant = ExternalApplicant::create($validated);
        
        return redirect()
            ->route('external-applicants.subjects.edit', $applicant)
            ->with('success', 'Externer Bewerber erfolgreich angelegt. Bitte wählen Sie nun die Prüfungsfächer.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ExternalApplicant $externalApplicant)
    {
        $externalApplicant->load('examPeriod');
        $examPeriods = ExamPeriod::orderByDesc('year')->get();
        $ersatzschulen = \App\Models\Ersatzschule::orderBy('name')->get();

        // Entscheidung 51 (01.09.2026): D2/E6 nur prüfen, solange noch nicht
        // abgebrochen — für einen bereits abgebrochenen Bewerber ist die
        // Frage entschieden, nicht mehr offen.
        $abbruchD2 = false;
        $abbruchE6 = false;

        if (! $externalApplicant->istAbgebrochen() && $externalApplicant->examPeriod !== null) {
            $abbruchD2 = app(\App\Services\VorgezogeneKolloquienService::class)
                ->abbruchFuer($externalApplicant, $externalApplicant->examPeriod);
            $abbruchE6 = app(\App\Services\ExterneAbiturDataService::class)
                ->abbruchNachErstemTeilFuer($externalApplicant, $externalApplicant->examPeriod);
        }

        return view('external-applicants.edit', compact(
            'externalApplicant',
            'examPeriods',
            'ersatzschulen',
            'abbruchD2',
            'abbruchE6'
        ));
    }

    /**
     * Entscheidung 51 (01.09.2026): den Abbruch bewusst feststellen.
     */
    public function abbruchFeststellen(Request $request, ExternalApplicant $externalApplicant)
    {
        $validated = $request->validate([
            'grund' => 'required|in:' . implode(',', array_keys(\App\Services\ExterneAbbruchService::GRUENDE_TEXT)),
        ]);

        app(\App\Services\ExterneAbbruchService::class)->feststellen($externalApplicant, $validated['grund']);

        return redirect()
            ->route('external-applicants.edit', $externalApplicant)
            ->with('success', 'Abbruch festgestellt. Noch nicht gehaltene Prüfungstermine wurden abgesagt.');
    }

    /**
     * Die Feststellung zurücknehmen (Tippfehler, Fehlklick).
     */
    public function abbruchRueckgaengig(ExternalApplicant $externalApplicant)
    {
        app(\App\Services\ExterneAbbruchService::class)->rueckgaengig($externalApplicant);

        return redirect()
            ->route('external-applicants.edit', $externalApplicant)
            ->with('success', 'Abbruch-Feststellung zurückgenommen. Bereits abgesagte Termine bleiben abgesagt.');
    }

    /**
     * Der Mindestpunkte-Kalkulator nach § 62 für einen externen Bewerber.
     *
     * Eigene Seite und nicht nur das Modal der Zusatzprüfungen: dort hängt der
     * Knopf an der Zusatzprüfungs-Liste, und wer keine Zusatzprüfung hat, steht
     * dort gar nicht. Für externe Bewerber gilt § 62 aber unabhängig davon —
     * der zweite Prüfungsteil betrifft jeden von ihnen, und der Restbedarf ist
     * gerade dann die interessante Auskunft.
     *
     * Gerechnet wird nichts in dieser Methode. Die Seite holt sich das fertige
     * Ergebnis über `zusatzpruefungen.pruefling.mindestpunkte-62` und speichert
     * Noten über `zusatzpruefungen.pruefling.exam-score.store` — dieselben
     * Endpunkte wie das Modal.
     */
    public function mindestpunkte(ExternalApplicant $externalApplicant)
    {
        $externalApplicant->load('examPeriod');

        $faecher = app(\App\Services\ExterneAbiturDataService::class)
            ->faecherFuer($externalApplicant, (int) $externalApplicant->exam_period_id);

        return view('external-applicants.mindestpunkte', [
            'externalApplicant' => $externalApplicant,
            'pruefling' => 'e' . $externalApplicant->id,
            // Nur für die Vorab-Meldung „noch nichts zu rechnen"; die Seite
            // selbst lädt ihre Zahlen über den AJAX-Endpunkt nach.
            'faecherwahlVollstaendig' => $faecher !== null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExternalApplicant $externalApplicant)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'birth_date' => 'required|date',
            'exam_period_id' => 'required|exists:exam_periods,id',
            // Nachteilsausgleich wie bei Schülern: ein Prozentsatz, um den
            // sich die Prüfungszeiten verlängern.
            'disability_compensation_percent' => 'nullable|integer|min:0|max:100',
            'disability_compensation_notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        
        $validated['ersatzschule_id'] = $this->ersatzschuleAusRequest($request);

        $externalApplicant->update($validated);
        
        return redirect()
            ->route('external-applicants.index')
            ->with('success', 'Externer Bewerber erfolgreich aktualisiert.');
    }

    /**
     * Die Ersatzschule aus der Eingabe — § 64.
     *
     * Zwei Wege: eine vorhandene auswählen oder eine neue mit ihrem Namen
     * anlegen. Der Name entscheidet über die Gleichheit; er trägt einen
     * Unique-Index, damit nicht jeder Bewerber seine eigene Kopie derselben
     * Schule bekommt (Entscheidung 37).
     *
     * Ohne Angabe ist der Bewerber ein anderer Bewerber nach § 60 — kein
     * Fehler, sondern der Regelfall.
     */
    private function ersatzschuleAusRequest(Request $request): ?int
    {
        $eingabe = $request->validate([
            'ersatzschule_id' => 'nullable|integer|exists:ersatzschulen,id',
            'ersatzschule_neu' => 'nullable|string|max:255',
            'ersatzschule_neu_ort' => 'nullable|string|max:255',
        ]);

        $neu = trim((string) ($eingabe['ersatzschule_neu'] ?? ''));

        if ($neu !== '') {
            return \App\Models\Ersatzschule::firstOrCreate(
                ['name' => $neu],
                ['ort' => trim((string) ($eingabe['ersatzschule_neu_ort'] ?? '')) ?: null],
            )->id;
        }

        return isset($eingabe['ersatzschule_id']) ? (int) $eingabe['ersatzschule_id'] : null;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExternalApplicant $externalApplicant)
    {
        $name = $externalApplicant->full_name;
        
        $externalApplicant->delete();
        
        return redirect()
            ->route('external-applicants.index')
            ->with('success', "Externer Bewerber '$name' wurde gelöscht.");
    }

    /**
     * Export exam schedule as PDF
     */
    public function exportPdf(ExternalApplicant $externalApplicant)
    {
        // Lade alle Fächer mit StudentExam und deren Assignments
        $externalApplicant->load([
            'examPeriod',
            'examSubjects.studentExam.assignment.timeSlot',
            'examSubjects.studentExam.assignment.examRoom',
            'examSubjects.studentExam.examiner',
            'examSubjects.studentExam.assessor',
            'examSubjects.studentExam.writtenExamDate',
        ]);
        
        // Gruppiere nach Prüfungsart
        $writtenExams = $externalApplicant->examSubjects
            ->where('exam_part', 'part1_written')
            ->sortBy('subject_number');
        
        $oralExams = $externalApplicant->examSubjects
            ->where('exam_part', 'part2_oral')
            ->sortBy('subject_number');
        
        // Schule laden
        $school = \App\Models\School::first();

        // Fachname und Anforderungsniveau kommen aus derselben Herleitung wie
        // in der Fächerwahl-Maske, nicht mehr aus denormalisierten Spalten.
        // Schlüssel ist die Position 1–8 nach Anlage 14.
        $faecher = collect($this->validationService->ausFaechern(
            $externalApplicant->examSubjects,
            $externalApplicant->leistungsfach_subject_id,
            $externalApplicant->zweite_fremdsprache_subject_id,
        ))->keyBy('position');

        $pdf = Pdf::loadView('exports.external-applicant-schedule-pdf', [
            'applicant' => $externalApplicant,
            'writtenExams' => $writtenExams,
            'oralExams' => $oralExams,
            'faecher' => $faecher,
            'school' => $school,
        ]);
        
        $filename = 'Pruefungsplan_' . str_replace(' ', '_', $externalApplicant->full_name) . '_' . $externalApplicant->examPeriod->year . '.pdf';
        
        return $pdf->download($filename);
    }
}
