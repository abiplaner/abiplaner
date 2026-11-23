<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Services\ExternalApplicantValidationService;
use App\Services\ExternalApplicantStudentExamService;
use Illuminate\Support\Facades\DB;

class ExternalApplicantSubjectsController extends Controller
{
    protected $validationService;
    protected $studentExamService;

    public function __construct(
        ExternalApplicantValidationService $validationService,
        ExternalApplicantStudentExamService $studentExamService
    ) {
        $this->validationService = $validationService;
        $this->studentExamService = $studentExamService;
    }

    /**
     * Show the subject selection form
     */
    public function edit(ExternalApplicant $externalApplicant)
    {
        $externalApplicant->load(['examSubjects.examiner', 'examPeriod']);
        
        // Gruppierte Fächer aus DB
        $groupedSubjects = $this->getGroupedSubjects();
        
        // Teachers mit ihren Fächern und Prüfungsbelastung für Prüferzuweisung (mündliche Prüfungen)
        $teachers = \App\Models\Teacher::active()->with('subjects')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(function($teacher) use ($externalApplicant) {
                // Anzahl der Kolloquien als Erstprüfer (oral exams).
                //
                // Über imPruefungszeitraum(): die Zahl steht in der Maske, in
                // der einem **externen** Bewerber ein Prüfer zugewiesen wird —
                // und zählte über `whereHas('student', …)` ausgerechnet dessen
                // Prüfungen nicht mit. Wer zwei Externe geprüft hat, stand hier
                // mit null Kolloquien.
                $examCount = \App\Models\StudentExam::where('examiner_id', $teacher->id)
                    ->where('exam_type', 'oral')
                    ->imPruefungszeitraum($externalApplicant->examPeriod)
                    ->count();
                
                return [
                    'id' => $teacher->id,
                    'name' => $teacher->full_name,
                    'shortcode' => $teacher->shortcode,
                    'subject_ids' => $teacher->subjects->pluck('id')->toArray(),
                    'subject_names' => $teacher->subjects->pluck('name')->toArray(),
                    'exam_count' => $examCount,
                ];
            });
        
        // Aufgabenfelder für Info
        $aufgabenfelder = [
            'sprachlich_literarisch' => 'Sprachlich-literarisch-künstlerisch',
            'gesellschaftswissenschaftlich' => 'Gesellschaftswissenschaftlich',
            'mint' => 'Mathematisch-naturwissenschaftlich-technisch',
        ];

        $katalog = $this->validationService->katalog();
        $mathematikId = $katalog->firstWhere('code', 'M')?->id;

        // Der Katalog für die Maske: Kürzel, Name und Fremdsprachen-Kennzeichen
        // je Fach-ID. Die Maske braucht das Kürzel, um Deutsch und Mathematik
        // aus der Auswahl des Leistungsfachs herauszuhalten — beide sind nach
        // § 61 Abs. 1 S. 5 ohnehin auf erhöhtem Niveau —, und das Kennzeichen,
        // um für die zweite Fremdsprache (§ 61 Abs. 3 S. 7) nur die gewählten
        // Fremdsprachen anzubieten.
        $katalogFuerMaske = $katalog
            ->where('can_abitur', true)
            ->mapWithKeys(fn ($fach) => [$fach->id => [
                'name' => $fach->name,
                'code' => $fach->code,
                'fremdsprache' => (bool) $fach->is_foreign_language,
            ]])
            ->all();

        // Die gespeicherte Wahl auf die acht Positionen der Anlage 14 legen.
        $gespeichert = [];

        foreach ($externalApplicant->examSubjects as $fach) {
            $gespeichert[$fach->position()] = [
                'subject_id' => $fach->subject_id,
                'examiner_id' => $fach->examiner_id,
                'ergebnis_quelle' => $fach->ergebnis_quelle,
                'eingebrachte_punktzahl' => $fach->eingebrachte_punktzahl,
            ];
        }

        // Anlage 14 legt Mathematik als erstes schriftliches Fach fest. Ältere
        // Wahlen führen es woanders — die frühere Maske fixierte es auf Fach 2.
        // Dann wird getauscht statt überschrieben, sonst verlöre die Wahl beim
        // blossen Öffnen der Seite ein Fach.
        $mathematikAufPosition = null;

        foreach ($gespeichert as $position => $eintrag) {
            if ($mathematikId !== null && $eintrag['subject_id'] === $mathematikId) {
                $mathematikAufPosition = $position;
                break;
            }
        }

        if ($mathematikAufPosition !== null && $mathematikAufPosition !== 1) {
            $aufEins = $gespeichert[1] ?? ['subject_id' => null, 'examiner_id' => null];
            $gespeichert[1] = $gespeichert[$mathematikAufPosition];
            $gespeichert[$mathematikAufPosition] = $aufEins;
        }

        if ($mathematikAufPosition === null) {
            $gespeichert[1] = [
                'subject_id' => $mathematikId,
                'examiner_id' => $gespeichert[1]['examiner_id'] ?? null,
            ];
        }

        $wahl = [];

        for ($position = 1; $position <= 8; $position++) {
            $wahl[] = [
                'position' => $position,
                'teil' => $position <= 4 ? 'part1_written' : 'part2_oral',
                'nummer' => $position <= 4 ? $position : $position - 4,
                'subject_id' => $gespeichert[$position]['subject_id'] ?? null,
                'examiner_id' => $gespeichert[$position]['examiner_id'] ?? null,
                // § 64 Abs. 2: nur die Positionen 7 und 8 können eingebracht
                // werden, und nur bei einem Ersatzschüler.
                'eingebracht' => ($gespeichert[$position]['ergebnis_quelle'] ?? 'pruefung') === 'einbringung',
                'eingebrachte_punktzahl' => $gespeichert[$position]['eingebrachte_punktzahl'] ?? null,
            ];
        }

        return view('external-applicants.subjects.edit', compact(
            'externalApplicant',
            'groupedSubjects',
            'teachers',
            'aufgabenfelder',
            'katalogFuerMaske',
            'mathematikId',
            'wahl'
        ));
    }

    /**
     * Update the subject selection
     */
    public function update(Request $request, ExternalApplicant $externalApplicant)
    {
        $validated = $this->auswahlAusRequest($request);

        $katalog = $this->validationService->katalog();
        $unbekannt = [];
        $gewaehlteIds = [];

        foreach ($validated['subjects'] as $subjectData) {
            $id = (int) $subjectData['subject_id'];
            $gewaehlteIds[] = $id;

            if (! $katalog->has($id)) {
                $unbekannt[] = $id;
            }
        }

        if ($unbekannt !== []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Die Fächerwahl verweist auf Fächer, die es im Katalog nicht gibt.');
        }

        // Ein doppelt gewähltes Fach schlägt sonst erst am Unique-Index fehl —
        // mitten in der Transaktion und damit als 500er. § 61 Abs. 3 S. 1
        // verlangt ohnehin, dass die Fächer des zweiten Prüfungsteils nicht
        // bereits Gegenstand der schriftlichen Prüfung sind.
        $doppelte = array_keys(array_filter(
            array_count_values($gewaehlteIds),
            static fn (int $anzahl): bool => $anzahl > 1
        ));

        if ($doppelte !== []) {
            $namen = array_map(static fn (int $id): string => $katalog[$id]->name, $doppelte);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Jedes Fach darf nur einmal gewählt werden. Mehrfach gewählt: '
                    . implode(', ', $namen) . '.');
        }

        $leistungsfachId = $validated['leistungsfach_subject_id'];

        // Abgleichen, nicht löschen und neu anlegen. Seit student_exams über
        // external_exam_subject_id an der Fächerwahl hängt (ON DELETE
        // CASCADE), nähme ein Löschen die Prüfungen mit — und über deren
        // eigene Kaskaden die bereits geplanten Termine und Zusatzprüfungen.
        // Eine Korrektur an einem einzigen Fach würde so den ganzen Plan des
        // Bewerbers kosten. Ein Fach, das in der Wahl bleibt, behält deshalb
        // seine Zeile und damit seine Prüfung; gelöscht wird nur, was
        // tatsächlich aus der Wahl fällt.
        DB::transaction(function () use ($externalApplicant, $validated, $katalog, $leistungsfachId) {
            $externalApplicant->update([
                'leistungsfach_subject_id' => $leistungsfachId,
                'zweite_fremdsprache_subject_id' => $validated['zweite_fremdsprache_subject_id'],
            ]);

            $vorhandene = $externalApplicant->examSubjects()->get()->keyBy('subject_id');
            $behalten = [];

            foreach ($validated['subjects'] as $subjectData) {
                /** @var \App\Models\Subject $subject */
                $subject = $katalog[(int) $subjectData['subject_id']];

                $felder = [
                    'exam_part' => $subjectData['exam_part'],
                    'subject_number' => $subjectData['subject_number'],
                    'examiner_id' => $subjectData['examiner_id'] ?? null,
                ];

                // § 64 Abs. 2: nur übernehmen, was die Maske auch geschickt
                // hat. Ein Formular ohne diese Felder darf eine bestehende
                // Einbringung nicht stillschweigend in eine Prüfung
                // zurückverwandeln.
                if (($subjectData['ergebnis_quelle'] ?? null) !== null) {
                    $felder['ergebnis_quelle'] = $subjectData['ergebnis_quelle'];
                    $felder['eingebrachte_punktzahl'] = $subjectData['ergebnis_quelle'] === 'einbringung'
                        ? ($subjectData['eingebrachte_punktzahl'] ?? null)
                        : null;
                }

                $fach = $vorhandene->get($subject->id);

                if ($fach instanceof ExternalExamSubject) {
                    $fach->update($felder);
                    $behalten[] = $fach->id;

                    continue;
                }

                $behalten[] = ExternalExamSubject::create($felder + [
                    'external_applicant_id' => $externalApplicant->id,
                    'subject_id' => $subject->id,
                ])->id;
            }

            $externalApplicant->examSubjects()
                ->whereNotIn('id', $behalten)
                ->delete();
        });

        // Validierung durchführen
        $externalApplicant->load('examSubjects');
        $validation = $this->validationService->validateSubjectSelection($externalApplicant);
        
        if (!$validation['valid']) {
            return redirect()
                ->back()
                ->with('warning', 'Fächerwahl gespeichert, aber es gibt Validierungsfehler.')
                ->with('validation_errors', $validation['errors']);
        }
        
        // Validierung erfolgreich → Prüfungen automatisch erzeugen.
        // Der Dienst wirft, wenn er nicht schreiben kann. Die Fächerwahl ist
        // an dieser Stelle bereits gespeichert; der Fehler wird deshalb
        // gemeldet statt verschluckt, aber nicht als 500er durchgereicht.
        try {
            $ergebnis = $this->studentExamService->generateStudentExams($externalApplicant);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('external-applicants.index')
                ->with('warning', 'Fächerwahl gespeichert, aber die Prüfungen konnten nicht erzeugt werden: '
                    . $e->getMessage());
        }

        $message = 'Fächerwahl erfolgreich gespeichert und validiert.';

        if ($ergebnis['created'] > 0 || $ergebnis['updated'] > 0) {
            $message .= " Prüfungen generiert: {$ergebnis['created']} neu, {$ergebnis['updated']} aktualisiert.";
        }

        return redirect()
            ->route('external-applicants.index')
            ->with('success', $message);
    }

    /**
     * Der Fächerkatalog für die Auswahlfelder, nach Aufgabenfeld gruppiert.
     *
     * § 61 Abs. 1 S. 4 lässt nur Fächer zu, die auch für Schülerinnen und
     * Schüler der Gymnasien als Prüfungsfächer wählbar sind. Ohne den Filter
     * bot die Maske "Beratungslehrer" und "Deutsch als Zweitsprache" in einer
     * Gruppe "Unbekannt" an.
     */
    private function getGroupedSubjects()
    {
        return \App\Models\Subject::query()
            ->where('can_abitur', true)
            ->orderedForDisplay()
            ->get()
            ->groupBy(fn ($subject) => $subject->getAufgabenfeldLabel());
    }

    /**
     * Die Eingabe der Maske prüfen und in eine einheitliche Form bringen.
     *
     * @return array{subjects: list<array<string, mixed>>, leistungsfach_subject_id: int|null, zweite_fremdsprache_subject_id: int|null}
     */
    private function auswahlAusRequest(Request $request): array
    {
        $validated = $request->validate([
            'subjects' => 'required|array|size:8',
            'subjects.*.subject_id' => 'required|integer|min:1',
            'subjects.*.exam_part' => 'required|in:part1_written,part2_oral',
            'subjects.*.subject_number' => 'required|integer|min:1|max:4',
            'subjects.*.examiner_id' => 'nullable|exists:teachers,id',
            // § 64 Abs. 2: geprüft oder aus 13/II eingebracht. Fehlt die
            // Angabe, bleibt der bisherige Stand des Fachs stehen.
            'subjects.*.ergebnis_quelle' => 'nullable|in:pruefung,einbringung',
            'subjects.*.eingebrachte_punktzahl' => 'nullable|integer|min:0|max:15',
            // Kein exists: subjects ist zentral, die Regel liefe gegen die
            // Mandantendatenbank. Geprüft wird gegen den geladenen Katalog.
            'leistungsfach_subject_id' => 'nullable|integer|min:1',
            'zweite_fremdsprache_subject_id' => 'nullable|integer|min:1',
        ]);

        return [
            'subjects' => array_values($validated['subjects']),
            'leistungsfach_subject_id' => isset($validated['leistungsfach_subject_id'])
                ? (int) $validated['leistungsfach_subject_id']
                : null,
            'zweite_fremdsprache_subject_id' => isset($validated['zweite_fremdsprache_subject_id'])
                ? (int) $validated['zweite_fremdsprache_subject_id']
                : null,
        ];
    }

    /**
     * Prüft eine noch nicht gespeicherte Fächerwahl und gibt die Verstöße
     * zurück. Die Maske ruft das bei jeder Änderung auf, damit es die Regeln
     * nur einmal gibt — in ExternalApplicantValidationService und nirgends
     * sonst (Entscheidung Wilfried, 26.08.2026).
     */
    public function pruefen(Request $request, ExternalApplicant $externalApplicant)
    {
        $auswahl = $this->auswahlAusRequest($request);

        $beschreibung = $this->validationService->ausAuswahl(
            $auswahl['subjects'],
            $auswahl['leistungsfach_subject_id'],
            $auswahl['zweite_fremdsprache_subject_id'],
        );

        return response()->json($this->validationService->pruefeWahl(
            $beschreibung,
            $externalApplicant->istErsatzschueler(),
        ));
    }

    /**
     * Manually trigger StudentExam creation (falls noch nicht geschehen)
     */
    public function createStudentExams(Request $request, ExternalApplicant $externalApplicant)
    {
        // Prüfen ob Fächerwahl vollständig ist
        $validation = $this->validationService->validateSubjectSelection($externalApplicant);
        
        if (!$validation['valid']) {
            return redirect()
                ->back()
                ->with('error', 'StudentExams können nicht erstellt werden. Bitte korrigieren Sie zuerst die Fächerwahl.')
                ->with('validation_errors', $validation['errors']);
        }
        
        // Prüfungen erstellen bzw. aktualisieren
        try {
            $ergebnis = $this->studentExamService->generateStudentExams($externalApplicant);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', 'Die Prüfungen konnten nicht erzeugt werden: ' . $e->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', "Prüfungen erfolgreich erstellt: {$ergebnis['created']} neu, {$ergebnis['updated']} aktualisiert.");
    }
}
