<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\AbsenceCarryover;
use App\Models\AbsenceImport;
use App\Models\AbsenceMeasure;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\SlotTemplate;
use App\Models\SlotTemplateItem;
use App\Models\Student;
use App\Models\SupervisionGrid;
use App\Models\SupervisionTemplate;
use App\Models\SupervisionTemplateAssignment;
use App\Models\SupervisionTimeSlot;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;

/**
 * Geführter Schuljahreswechsel-Wizard (Modul 1.1, Phase 4).
 *
 * Orchestriert die in den Phasen 1–3 gebauten Bausteine zu einem
 * resumierbaren 9-Schritte-Ablauf (Konzept Kap. 4). Der Fortschritt
 * liegt als JSON in school_settings (Key SETTING_KEY) — pro Schritt
 * Abschlusszeitpunkt + Ergebniszahlen; damit ist der Wizard jederzeit
 * unterbrech- und wiederaufnehmbar. Die Schritte sind strikt linear
 * freigeschaltet (Schritt n erst nach Abschluss von 1..n-1) und
 * idempotent bzw. gegen Doppelausführung abgesichert.
 *
 * Schritte (Konzept Kap. 4):
 *   1 Voraussetzungen prüfen/bestätigen   (manuell)
 *   2 Auto-Backup                          (Aktion)
 *   3 Absenz-Aggregate einfrieren          (Aktion: purgeExcept + freeze)
 *   4 Absenz-Rohdaten löschen              (Aktion, destruktiv)
 *   5 Neuen Jahrgang importieren           (manuell, über Import-Seiten)
 *   6 Aggregate zuordnen + Klärungsliste   (Aktion + manueller Abschluss)
 *   7 Vorlagen kopieren                    (Aktion; exam_day_templates
 *                                           bewusst NICHT — datumsgebunden,
 *                                           Entscheidung Wilfried 06.07.2026)
 *   8 Lehrer-Abgleich                      (manuell, über Lehrer-Import mit
 *                                           Deaktivierungsvorschlägen)
 *   9 Abschlussbericht                     (manuell: Wizard abschließen)
 */
class YearChangeWizardService
{
    public const SETTING_KEY = 'yearchange.state';
    public const STEP_COUNT = 9;
    public const BACKUP_DESCRIPTION = 'auto_before_yearchange';

    /** Schritte, die serverseitig ausgeführt werden (runStep). */
    public const RUNNABLE_STEPS = [2, 3, 4, 6, 7];

    /** Schritte, die manuell abgeschlossen werden (completeStep). */
    public const CONFIRMABLE_STEPS = [1, 5, 6, 8];

    public function __construct(
        private AbsenceCarryoverService $carryovers,
        private TenantBackupService $backups,
    ) {}

    // =========================================================================
    // Zustand (school_settings, Key SETTING_KEY)
    // =========================================================================

    /**
     * @return array<string, mixed>|null
     */
    public function state(): ?array
    {
        $raw = SchoolSetting::get(self::SETTING_KEY);

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Läuft gerade ein (noch nicht abgeschlossener) Wechsel? */
    public function isRunning(): bool
    {
        $state = $this->state();

        return $state !== null && ($state['completed_at'] ?? null) === null;
    }

    /** Ist der letzte gestartete Wechsel abgeschlossen? */
    public function isCompleted(): bool
    {
        $state = $this->state();

        return $state !== null && ($state['completed_at'] ?? null) !== null;
    }

    /**
     * Voraussetzungs-Check ohne Seiteneffekte: ermittelt Ziel- (= neueste)
     * und Quell-Periode (höchstes Jahr unterhalb des Ziels) und sammelt
     * Fehler/Hinweise für die Startseite bzw. Schritt 1.
     *
     * @return array{target: ExamPeriod|null, source: ExamPeriod|null, errors: list<string>, warnings: list<string>}
     */
    public function preflight(): array
    {
        $target = ExamPeriod::orderByDesc('year')->orderByDesc('created_at')->first();
        $source = $target !== null
            ? ExamPeriod::where('year', '<', $target->year)->orderByDesc('year')->first()
            : null;

        $errors = [];
        $warnings = [];

        if ($target === null) {
            $errors[] = 'Es existiert keine Prüfungsperiode. Bitte zuerst die neue Periode anlegen.';
        } elseif ($source === null) {
            $errors[] = 'Es existiert keine ältere Periode unterhalb von "' . $target->name . '" — '
                . 'die neue Periode (z. B. "Abitur ' . ($target->year + 1) . '") muss zuerst zentral angelegt werden.';
        } else {
            $targetStudents = Student::where('exam_period_id', $target->id)->count();
            if ($targetStudents > 0) {
                $warnings[] = 'Die Ziel-Periode "' . $target->name . '" enthält bereits ' . $targetStudents . ' Schüler.';
            }
        }

        return compact('target', 'source', 'errors', 'warnings');
    }

    /**
     * Startet einen neuen Wechsel: Perioden fixieren, State anlegen.
     * Ein bereits abgeschlossener State wird überschrieben; ein laufender
     * Wechsel muss zuerst zurückgesetzt werden.
     *
     * @return array<string, mixed> der neue State
     */
    public function start(): array
    {
        if ($this->isRunning()) {
            throw new \RuntimeException('Es läuft bereits ein Schuljahreswechsel. Bitte zuerst abschließen oder zurücksetzen.');
        }

        $preflight = $this->preflight();

        if ($preflight['errors'] !== [] || $preflight['target'] === null || $preflight['source'] === null) {
            throw new \RuntimeException(implode(' ', $preflight['errors']) ?: 'Voraussetzungen nicht erfüllt.');
        }

        $state = [
            'source_period_id' => $preflight['source']->id,
            'target_period_id' => $preflight['target']->id,
            'source_school_year' => self::schoolYearForAbiturYear($preflight['source']->year),
            'started_at' => now()->toIso8601String(),
            'started_by' => auth()->user()?->email,
            'steps' => [],
            'completed_at' => null,
        ];

        $this->syncSchoolMasterData();
        $this->saveState($state);

        ActivityLogger::log(
            'yearchange_started',
            'ExamPeriod',
            $preflight['target']->id,
            "Schuljahreswechsel gestartet: {$preflight['source']->name} → {$preflight['target']->name}"
        );

        return $state;
    }

    /**
     * Bricht den laufenden Wechsel ab (State löschen). Bereits ausgeführte
     * Schritte werden NICHT rückgängig gemacht — der Wizard ist nur die
     * Orchestrierung, die Daten bleiben, wie sie sind.
     */
    public function reset(): void
    {
        SchoolSetting::where('key', self::SETTING_KEY)->delete();

        ActivityLogger::log(
            'yearchange_reset',
            null,
            null,
            'Schuljahreswechsel-Wizard zurückgesetzt (bereits ausgeführte Schritte bleiben wirksam).'
        );
    }

    // =========================================================================
    // Schritt-Verwaltung
    // =========================================================================

    public function stepCompleted(int $step): bool
    {
        $state = $this->state();

        return $state !== null
            && (($state['steps'][(string) $step]['completed_at'] ?? null) !== null);
    }

    /**
     * Gespeichertes Ergebnis eines Schritts (auch vor dessen Abschluss,
     * z. B. Auto-Zuordnung in Schritt 6).
     *
     * @return array<string, mixed>|null
     */
    public function stepResult(int $step): ?array
    {
        $state = $this->state();
        $result = $state['steps'][(string) $step]['result'] ?? null;

        return is_array($result) ? $result : null;
    }

    /** Linear: Schritt n ist verfügbar, sobald 1..n-1 abgeschlossen sind. */
    public function stepAvailable(int $step): bool
    {
        if (!$this->isRunning() || $step < 1 || $step > self::STEP_COUNT) {
            return false;
        }

        for ($i = 1; $i < $step; $i++) {
            if (!$this->stepCompleted($i)) {
                return false;
            }
        }

        return true;
    }

    /** Erster nicht abgeschlossener Schritt (1..9); 9, wenn alles fertig. */
    public function currentStep(): int
    {
        for ($i = 1; $i <= self::STEP_COUNT; $i++) {
            if (!$this->stepCompleted($i)) {
                return $i;
            }
        }

        return self::STEP_COUNT;
    }

    /**
     * Führt einen serverseitigen Schritt aus (2, 3, 4, 7 schließen sich
     * dabei selbst ab; 6 speichert nur das Ergebnis — Abschluss erfolgt
     * nach der Klärungsliste manuell über completeStep()).
     *
     * @return array<string, mixed> Ergebniszahlen des Schritts
     */
    public function runStep(int $step): array
    {
        if (!in_array($step, self::RUNNABLE_STEPS, true)) {
            throw new \InvalidArgumentException("Schritt {$step} ist keine ausführbare Aktion.");
        }

        if (!$this->stepAvailable($step)) {
            throw new \RuntimeException("Schritt {$step} ist noch nicht freigeschaltet — bitte die vorherigen Schritte abschließen.");
        }

        if ($step !== 6 && $this->stepCompleted($step)) {
            throw new \RuntimeException("Schritt {$step} ist bereits abgeschlossen.");
        }

        $result = match ($step) {
            2 => $this->runBackup(),
            3 => $this->runFreeze(),
            4 => $this->runPurgeRawData(),
            6 => $this->runMatch(),
            7 => $this->runCopyTemplates(),
        };

        if ($step === 6) {
            $this->storeStepResult($step, $result);
        } else {
            $this->completeStep($step, $result);
        }

        return $result;
    }

    /**
     * Schließt einen Schritt ab (manuelle Schritte 1/5/6/8 aus dem
     * Controller; ausführbare Schritte intern über runStep()).
     * Serverseitige Plausibilitätsprüfungen pro Schritt inklusive.
     *
     * @param array<string, mixed> $result
     */
    public function completeStep(int $step, array $result = []): void
    {
        if (!$this->stepAvailable($step)) {
            throw new \RuntimeException("Schritt {$step} ist noch nicht freigeschaltet.");
        }

        if ($this->stepCompleted($step)) {
            throw new \RuntimeException("Schritt {$step} ist bereits abgeschlossen.");
        }

        // Serverseitige Guards für manuell bestätigte Schritte
        if ($step === 5) {
            $count = Student::where('exam_period_id', $this->targetPeriodId())->count();
            if ($count === 0) {
                throw new \RuntimeException('In der Ziel-Periode sind noch keine Schüler vorhanden — bitte zuerst den neuen Jahrgang importieren.');
            }
            $result += ['students_in_target' => $count];
        }

        if ($step === 6 && $this->stepResult(6) === null) {
            throw new \RuntimeException('Bitte zuerst die automatische Zuordnung ausführen.');
        }

        $state = $this->requireState();
        $existing = $state['steps'][(string) $step] ?? [];
        $state['steps'][(string) $step] = [
            'completed_at' => now()->toIso8601String(),
            'result' => $result + ($existing['result'] ?? []),
        ];
        $this->saveState($state);

        ActivityLogger::log(
            'yearchange_step_completed',
            null,
            $step,
            "Schuljahreswechsel: Schritt {$step} ({$this->stepTitle($step)}) abgeschlossen."
        );
    }

    /**
     * Schließt den gesamten Wechsel ab (Schritt 9): setzt completed_at.
     * Voraussetzung: Schritte 1–8 sind abgeschlossen.
     */
    public function finish(): void
    {
        if (!$this->stepAvailable(9)) {
            throw new \RuntimeException('Der Wechsel kann erst abgeschlossen werden, wenn die Schritte 1–8 erledigt sind.');
        }

        $state = $this->requireState();
        $state['steps']['9'] = [
            'completed_at' => now()->toIso8601String(),
            'result' => [],
        ];
        $state['completed_at'] = now()->toIso8601String();
        $this->saveState($state);

        $sourceName = $this->sourcePeriod()->name ?? '?';
        $targetName = $this->targetPeriod()->name ?? '?';

        ActivityLogger::log(
            'yearchange_completed',
            'ExamPeriod',
            $this->targetPeriodId(),
            "Schuljahreswechsel abgeschlossen: {$sourceName} → {$targetName}"
        );
    }

    // =========================================================================
    // Schritt-Aktionen (2, 3, 4, 6, 7)
    // =========================================================================

    /**
     * Schritt 2: Auto-Backup über den bestehenden TenantBackupService.
     *
     * @return array<string, mixed>
     */
    private function runBackup(): array
    {
        $school = app(AktuelleSchule::class)->schule();

        if (!$school instanceof School) {
            throw new \RuntimeException('Keine Schule im Tenant-Kontext gefunden.');
        }

        $backup = $this->backups->createBackup($school, self::BACKUP_DESCRIPTION);

        return [
            'filename' => $backup['filename'],
            'size' => $backup['size'],
        ];
    }

    /**
     * Schritt 3: Carryovers des Vor-Vorjahres löschen (keine Kettenbildung),
     * dann Kennzahlen der Quell-Periode einfrieren. Beides idempotent.
     *
     * @return array<string, int>
     */
    private function runFreeze(): array
    {
        $sourceSchoolYear = $this->sourceSchoolYear();

        $purged = $this->carryovers->purgeExcept($sourceSchoolYear);
        $frozen = $this->carryovers->freezeForPeriod($this->requireSourcePeriod(), $sourceSchoolYear);

        return [
            'purged_old_carryovers' => $purged,
            'frozen' => $frozen,
        ];
    }

    /**
     * Schritt 4: Absenz-Rohdaten der Quell-Periode löschen — absences,
     * absence_measures (inkl. Freitext-Gründen mit Art.-9-Gesundheitsdaten,
     * erfüllt Roadmap 8.4), danach verwaiste absence_imports. Nur Daten von
     * Schülern der Quell-Periode; eventuell schon vorhandene Absenzen des
     * neuen Jahrgangs bleiben unberührt.
     *
     * @return array<string, int>
     */
    private function runPurgeRawData(): array
    {
        $sourcePeriodId = $this->requireSourcePeriod()->id;

        return DB::transaction(function () use ($sourcePeriodId) {
            $byPeriod = fn ($q) => $q->where('exam_period_id', $sourcePeriodId);

            // Bewusst nur Schüler: Absenzen und Maßnahmen hängen an
            // `students`; externe Bewerber führen im Datenmodell keine.
            $measures = AbsenceMeasure::whereHas('student', $byPeriod)->delete();
            $absences = Absence::whereHas('student', $byPeriod)->delete();
            $imports = AbsenceImport::whereDoesntHave('absences')->delete();

            return [
                'deleted_absences' => (int) $absences,
                'deleted_measures' => (int) $measures,
                'deleted_imports' => (int) $imports,
            ];
        });
    }

    /**
     * Schritt 6: automatischer Namensabgleich (external_id, dann exakter
     * Name, dann 8.1-Toleranz Rufname/Doppelname/Partikel — siehe
     * AbsenceCarryoverService/NameMatchingService). Mehrfach
     * ausführbar — es werden nur noch unzugeordnete Carryovers verarbeitet.
     *
     * @return array<string, int>
     */
    private function runMatch(): array
    {
        $sourceSchoolYear = $this->sourceSchoolYear();

        $matchedNow = $this->carryovers->matchCarryovers($sourceSchoolYear, $this->requireTargetPeriod());

        $base = AbsenceCarryover::where('source_school_year', $sourceSchoolYear);

        return [
            'matched_now' => $matchedNow,
            'matched_total' => (clone $base)->whereNotNull('matched_student_id')->count(),
            'unmatched' => (clone $base)->whereNull('matched_student_id')->count(),
        ];
    }

    /**
     * Manuelle Zuordnung aus der Klärungsliste (Schritt 6): ein noch
     * unzugeordnetes Carryover einem Schüler der Ziel-Periode zuweisen.
     */
    public function manualMatch(int $carryoverId, int $studentId): AbsenceCarryover
    {
        if (!$this->stepAvailable(6)) {
            throw new \RuntimeException('Schritt 6 ist noch nicht freigeschaltet.');
        }

        /** @var AbsenceCarryover $carryover */
        $carryover = AbsenceCarryover::where('source_school_year', $this->sourceSchoolYear())
            ->whereNull('matched_student_id')
            ->findOrFail($carryoverId);

        /** @var Student $student */
        $student = Student::where('exam_period_id', $this->targetPeriodId())
            ->findOrFail($studentId);

        $carryover->update([
            'matched_student_id' => $student->id,
            'matched_at' => now(),
            'match_method' => 'manuell',
        ]);

        return $carryover;
    }

    /**
     * Schritt 7: Schulstammdaten sichern/übernehmen sowie slot_templates
     * (+ Items), time_slots (Woche 1 & 2 auf die neuen Wochentage gemappt),
     * supervision_grids, supervision_time_slots und supervision_templates
     * (+ Assignments) in die Ziel-Periode übertragen.
     *
     * exam_day_templates werden bewusst NICHT kopiert — die Zuordnung
     * Prüfungstag → Slot-Vorlage wird jedes Jahr neu getroffen
     * (Entscheidung Wilfried 06.07.2026, bestätigt 22.10.2026). Die
     * Zeitslots selbst dagegen schon: Bewährte Tagesraster sollen bleiben.
     *
     * @return array<string, int|bool>
     */
    private function runCopyTemplates(): array
    {
        return $this->copyScheduleAndTemplates(
            $this->requireSourcePeriod(),
            $this->requireTargetPeriod()
        );
    }

    /**
     * Dieselbe Übernahme wie Schritt 7, aber ohne laufenden Wechsel —
     * für Schulen, deren Schuljahreswechsel schon ohne den Wizard
     * stattgefunden hat (Produktion, Oktober 2026). Quelle und Ziel
     * bestimmt preflight(); ein laufender Wechsel hat Vorrang, dort ist
     * Schritt 7 der Weg. Idempotent: Vorhandenes wird übersprungen.
     *
     * @return array<string, int|bool>
     */
    public function copyFromPreviousPeriod(): array
    {
        if ($this->isRunning()) {
            throw new \RuntimeException('Es läuft ein Schuljahreswechsel — bitte dort Schritt 7 verwenden.');
        }

        $preflight = $this->preflight();

        if ($preflight['source'] === null || $preflight['target'] === null) {
            throw new \RuntimeException(implode(' ', $preflight['errors']) ?: 'Keine Vor- und Zielperiode gefunden.');
        }

        $result = $this->copyScheduleAndTemplates($preflight['source'], $preflight['target']);

        ActivityLogger::log(
            'yearchange_templates_copied',
            'ExamPeriod',
            $preflight['target']->id,
            "Zeitslots und Vorlagen übernommen: {$preflight['source']->name} → {$preflight['target']->name}"
        );

        return $result;
    }

    /**
     * Synchronisiert Schulstammdaten (Anschrift, PLZ, Ort, E-Mail, Telefon)
     * bidirektional zwischen dem zentralen School-Datensatz und school_settings,
     * sodass sie bei Schuljahreswechseln und Backup-Restores erhalten bleiben.
     */
    public function syncSchoolMasterData(?School $school = null): bool
    {
        $school = $school ?? app(AktuelleSchule::class)->schule();

        if (!$school instanceof School) {
            return false;
        }

        $fields = ['address', 'postal_code', 'city', 'state', 'email', 'phone'];
        $updatesOnSchool = [];

        foreach ($fields as $field) {
            $settingKey = 'school.' . $field;
            $schoolValue = trim((string) ($school->{$field} ?? ''));
            $settingValue = trim((string) (SchoolSetting::get($settingKey) ?? ''));

            if ($schoolValue !== '') {
                if ($settingValue !== $schoolValue) {
                    SchoolSetting::set($settingKey, $schoolValue);
                }
            } elseif ($settingValue !== '') {
                $updatesOnSchool[$field] = $settingValue;
            }
        }

        if ($updatesOnSchool !== []) {
            $school->update($updatesOnSchool);
        }

        return true;
    }

    /**
     * Überträgt Zeitslot-Vorlagen, Kolloquiums-Zeitslots, Aufsichtsraster,
     * Aufsichts-Zeitslots und Aufsichtsvorlagen von einer Quell-Periode in
     * eine Ziel-Periode (Wochentags-Mapping Woche 1 & 2, Index 0..9).
     *
     * @return array<string, int|bool>
     */
    public function copyScheduleAndTemplates(ExamPeriod $sourcePeriod, ExamPeriod $targetPeriod): array
    {
        $sourceId = $sourcePeriod->id;
        $targetId = $targetPeriod->id;

        $schoolSynced = $this->syncSchoolMasterData();

        return DB::transaction(function () use ($sourcePeriod, $targetPeriod, $sourceId, $targetId, $schoolSynced) {
            $slotCopied = 0;
            $slotSkipped = 0;

            $slotTemplates = SlotTemplate::where('exam_period_id', $sourceId)->with('items')->get();
            foreach ($slotTemplates as $template) {
                $exists = SlotTemplate::where('exam_period_id', $targetId)
                    ->where('name', $template->name)
                    ->exists();

                if ($exists) {
                    $slotSkipped++;
                    continue;
                }

                $copy = SlotTemplate::create([
                    'exam_period_id' => $targetId,
                    'name' => $template->name,
                    'description' => $template->description,
                ]);

                foreach ($template->items as $item) {
                    // getRawOriginal(): die datetime:H:i-Casts würden sonst
                    // volle Datetime-Strings in die TIME-Spalten schreiben
                    SlotTemplateItem::create([
                        'slot_template_id' => $copy->id,
                        'slot_number' => $item->slot_number,
                        'prep_start_time' => $item->getRawOriginal('prep_start_time'),
                        'prep_end_time' => $item->getRawOriginal('prep_end_time'),
                        'exam_start_time' => $item->getRawOriginal('exam_start_time'),
                        'exam_end_time' => $item->getRawOriginal('exam_end_time'),
                        'preference_weight' => $item->preference_weight,
                        'notes' => $item->notes,
                    ]);
                }

                $slotCopied++;
            }

            // Wochentage (Woche 1 Mo–Fr + Woche 2 Mo–Fr) zwischen Quell- und Zielperiode abbilden
            $sourceDates = $sourcePeriod->getExamDates(1)->concat($sourcePeriod->getExamDates(2))->values();
            $targetDates = $targetPeriod->getExamDates(1)->concat($targetPeriod->getExamDates(2))->values();

            $timeSlotsCopied = 0;

            foreach ($sourceDates as $idx => $sourceDate) {
                if (!isset($targetDates[$idx])) {
                    continue;
                }

                $srcDateStr = $sourceDate->format('Y-m-d');
                $tgtDateStr = $targetDates[$idx]->format('Y-m-d');

                $sourceSlots = TimeSlot::where('exam_period_id', $sourceId)
                    ->whereDate('date', $srcDateStr)
                    ->orderBy('slot_number')
                    ->get();

                if ($sourceSlots->isEmpty()) {
                    continue;
                }

                foreach ($sourceSlots as $slot) {
                    $exists = TimeSlot::where('exam_period_id', $targetId)
                        ->whereDate('date', $tgtDateStr)
                        ->where('slot_number', $slot->slot_number)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    TimeSlot::create([
                        'exam_period_id' => $targetId,
                        'date' => $tgtDateStr,
                        'slot_number' => $slot->slot_number,
                        'prep_start_time' => $slot->getRawOriginal('prep_start_time'),
                        'prep_end_time' => $slot->getRawOriginal('prep_end_time'),
                        'exam_start_time' => $slot->getRawOriginal('exam_start_time'),
                        'exam_end_time' => $slot->getRawOriginal('exam_end_time'),
                        'preference_weight' => $slot->preference_weight,
                        'is_active' => $slot->is_active,
                        'notes' => $slot->notes,
                    ]);

                    $timeSlotsCopied++;
                }
            }

            // Aufsichtsraster (SupervisionGrid) + Aufsichts-Zeitslots (SupervisionTimeSlot) kopieren
            $gridsCopied = 0;
            /** @var array<int, int> $gridMap old_grid_id => new_grid_id */
            $gridMap = [];
            $sourceGrids = SupervisionGrid::where('exam_period_id', $sourceId)->get();
            foreach ($sourceGrids as $grid) {
                $targetGrid = SupervisionGrid::firstOrCreate(
                    [
                        'exam_period_id' => $targetId,
                        'name' => $grid->name,
                    ],
                    [
                        'description' => $grid->description,
                        'color' => $grid->color,
                        'is_default' => $grid->is_default,
                    ]
                );
                if ($targetGrid->wasRecentlyCreated) {
                    $gridsCopied++;
                }
                $gridMap[$grid->id] = $targetGrid->id;
            }

            $supSlotsCopied = 0;
            foreach ($sourceDates as $idx => $sourceDate) {
                if (!isset($targetDates[$idx])) {
                    continue;
                }

                $srcDateStr = $sourceDate->format('Y-m-d');
                $tgtDateStr = $targetDates[$idx]->format('Y-m-d');

                $sourceSupSlots = SupervisionTimeSlot::where('exam_period_id', $sourceId)
                    ->whereDate('date', $srcDateStr)
                    ->orderBy('slot_number')
                    ->get();

                foreach ($sourceSupSlots as $supSlot) {
                    $mappedGridId = $supSlot->supervision_grid_id !== null
                        ? ($gridMap[$supSlot->supervision_grid_id] ?? null)
                        : null;

                    $exists = SupervisionTimeSlot::where('exam_period_id', $targetId)
                        ->whereDate('date', $tgtDateStr)
                        ->where('slot_number', $supSlot->slot_number)
                        ->where('supervision_grid_id', $mappedGridId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    SupervisionTimeSlot::create([
                        'exam_period_id' => $targetId,
                        'supervision_grid_id' => $mappedGridId,
                        'date' => $tgtDateStr,
                        'start_time' => $supSlot->getRawOriginal('start_time'),
                        'end_time' => $supSlot->getRawOriginal('end_time'),
                        'slot_number' => $supSlot->slot_number,
                        'notes' => $supSlot->notes,
                    ]);

                    $supSlotsCopied++;
                }
            }

            $supCopied = 0;
            $supSkipped = 0;

            $supTemplates = SupervisionTemplate::where('exam_period_id', $sourceId)
                ->with('assignments')
                ->get();

            foreach ($supTemplates as $template) {
                $exists = SupervisionTemplate::where('exam_period_id', $targetId)
                    ->where('name', $template->name)
                    ->exists();

                if ($exists) {
                    $supSkipped++;
                    continue;
                }

                $copy = SupervisionTemplate::create([
                    'exam_period_id' => $targetId,
                    'name' => $template->name,
                    'description' => $template->description,
                    'is_active' => $template->is_active,
                ]);

                foreach ($template->assignments as $assignment) {
                    SupervisionTemplateAssignment::create([
                        'template_id' => $copy->id,
                        'room_id' => $assignment->room_id,
                        'supervision_type' => $assignment->supervision_type,
                        'teacher_id' => $assignment->teacher_id,
                        'slot_number' => $assignment->slot_number,
                        'day_of_week' => $assignment->day_of_week,
                    ]);
                }

                $supCopied++;
            }

            return [
                'slot_templates_copied' => $slotCopied,
                'slot_templates_skipped' => $slotSkipped,
                'time_slots_copied' => $timeSlotsCopied,
                'supervision_grids_copied' => $gridsCopied,
                'supervision_time_slots_copied' => $supSlotsCopied,
                'supervision_templates_copied' => $supCopied,
                'supervision_templates_skipped' => $supSkipped,
                'school_master_data_synced' => $schoolSynced,
            ];
        });
    }

    // =========================================================================
    // Perioden- und Schuljahres-Helfer
    // =========================================================================

    public function sourcePeriod(): ?ExamPeriod
    {
        $id = $this->sourcePeriodId();

        return $id !== null ? ExamPeriod::find($id) : null;
    }

    public function targetPeriod(): ?ExamPeriod
    {
        $id = $this->targetPeriodId();

        return $id !== null ? ExamPeriod::find($id) : null;
    }

    public function sourcePeriodId(): ?int
    {
        $state = $this->state();

        return isset($state['source_period_id']) ? (int) $state['source_period_id'] : null;
    }

    public function targetPeriodId(): ?int
    {
        $state = $this->state();

        return isset($state['target_period_id']) ? (int) $state['target_period_id'] : null;
    }

    public function sourceSchoolYear(): string
    {
        $state = $this->state();
        $year = $state['source_school_year'] ?? null;

        if (!is_string($year) || $year === '') {
            throw new \RuntimeException('Kein laufender Schuljahreswechsel (source_school_year fehlt).');
        }

        return $year;
    }

    /**
     * Abiturjahr → Schuljahres-String, z. B. 2026 → "2025/26"
     * (gleiche Konvention wie timetable_entries.school_year).
     */
    public static function schoolYearForAbiturYear(int $year): string
    {
        return ($year - 1) . '/' . str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Titel für Anzeige und Aktivitätslog. */
    public function stepTitle(int $step): string
    {
        return match ($step) {
            1 => 'Voraussetzungen prüfen',
            2 => 'Auto-Backup',
            3 => 'Absenz-Aggregate einfrieren',
            4 => 'Absenz-Rohdaten löschen',
            5 => 'Neuen Jahrgang importieren',
            6 => 'Vorjahreswerte zuordnen',
            7 => 'Vorlagen kopieren',
            8 => 'Lehrer aktualisieren',
            9 => 'Abschlussbericht',
            default => "Schritt {$step}",
        };
    }

    // =========================================================================
    // Interne Helfer
    // =========================================================================

    /**
     * Ergebnis eines Schritts speichern, ohne ihn abzuschließen
     * (Schritt 6: Auto-Zuordnung vor der Klärungsliste).
     *
     * @param array<string, mixed> $result
     */
    private function storeStepResult(int $step, array $result): void
    {
        $state = $this->requireState();
        $existing = $state['steps'][(string) $step] ?? [];
        $state['steps'][(string) $step] = [
            'completed_at' => $existing['completed_at'] ?? null,
            'result' => $result,
        ];
        $this->saveState($state);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireState(): array
    {
        $state = $this->state();

        if ($state === null) {
            throw new \RuntimeException('Kein Schuljahreswechsel gestartet.');
        }

        return $state;
    }

    private function requireSourcePeriod(): ExamPeriod
    {
        $period = $this->sourcePeriod();

        if ($period === null) {
            throw new \RuntimeException('Quell-Periode des Schuljahreswechsels nicht gefunden.');
        }

        return $period;
    }

    private function requireTargetPeriod(): ExamPeriod
    {
        $period = $this->targetPeriod();

        if ($period === null) {
            throw new \RuntimeException('Ziel-Periode des Schuljahreswechsels nicht gefunden.');
        }

        return $period;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveState(array $state): void
    {
        SchoolSetting::set(
            self::SETTING_KEY,
            json_encode($state, JSON_UNESCAPED_UNICODE) ?: null
        );
    }
}
