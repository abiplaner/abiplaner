<?php

namespace Tests\Tenant;

use App\Models\Absence;
use App\Models\AbsenceCarryover;
use App\Models\AbsenceImport;
use App\Models\ExamDayTemplate;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\SlotTemplate;
use App\Models\SlotTemplateItem;
use App\Models\Student;
use App\Models\SupervisionTemplate;
use App\Services\YearChangeWizardService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Geführter Schuljahreswechsel-Wizard (Modul 1.1, Phase 4).
 *
 * Testet die Orchestrierung: State-Verwaltung in school_settings, lineare
 * Schrittfreigabe, die Schritt-Aktionen (Einfrieren, Rohdaten löschen,
 * Zuordnen, Vorlagen kopieren) und den Abschluss. Backup (Schritt 2) läuft
 * einmal echt mit (TenantBackupService ist zusätzlich durch
 * BackupRestoreTest abgedeckt).
 *
 * Fiktive Jahre weit in der Zukunft (2104/2105), damit reale Perioden das
 * "neueste Periode"-Verhalten von preflight() nicht stören und keine
 * Kollision mit den Jahren des AbsenceCarryoverTest (2097/98) entsteht.
 */
class YearChangeWizardTest extends TenantTestCase
{
    // Schritt 2 legt eine echte Sicherung an (VACUUM INTO braucht eine Datei).
    use \Tests\Tenant\Concerns\MitDateiDatenbank;

    private const YEAR_OLD = 2104;
    private const YEAR_NEW = 2105;
    private const SCHOOL_YEAR = '2103/04';

    protected School $school;
    protected ExamPeriod $oldPeriod;
    protected ExamPeriod $newPeriod;
    protected Student $oldStudent;
    protected Student $newStudent;
    protected AbsenceImport $importOld;
    protected AbsenceImport $importNew;
    protected YearChangeWizardService $wizard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $this->oldPeriod = $this->createExamPeriod(self::YEAR_OLD);
        $this->newPeriod = $this->createExamPeriod(self::YEAR_NEW);


        $this->oldStudent = Student::create([
            'firstname' => 'Willi',
            'lastname' => 'Wechseltest',
            'external_id' => 'YRC-001',
            'grade' => '12',
            'exam_period_id' => $this->oldPeriod->id,
        ]);

        $this->newStudent = Student::create([
            'firstname' => 'Willi',
            'lastname' => 'Wechseltest',
            'external_id' => 'YRC-001',
            'grade' => '13',
            'exam_period_id' => $this->newPeriod->id,
        ]);

        // Rohdaten: je eine Absenz in Quell- und Ziel-Periode, getrennte Importe
        $this->importOld = AbsenceImport::create(['original_filename' => 'wizard-old.csv']);
        $this->importNew = AbsenceImport::create(['original_filename' => 'wizard-new.csv']);

        Absence::create([
            'student_id' => $this->oldStudent->id,
            'absence_import_id' => $this->importOld->id,
            'source_key' => 'wizard-old-1',
            'date_from' => self::YEAR_OLD . '-03-02',
            'date_to' => self::YEAR_OLD . '-03-04',
            'days' => 3,
            'hours' => 0,
            'minutes' => 0,
            'art_raw' => 'krank',
            'category' => 'krank',
            'excuse_status' => 'schriftlich',
        ]);

        Absence::create([
            'student_id' => $this->newStudent->id,
            'absence_import_id' => $this->importNew->id,
            'source_key' => 'wizard-new-1',
            'date_from' => self::YEAR_NEW . '-03-02',
            'date_to' => self::YEAR_NEW . '-03-02',
            'days' => 1,
            'hours' => 0,
            'minutes' => 0,
            'art_raw' => 'krank',
            'category' => 'krank',
            'excuse_status' => 'schriftlich',
        ]);

        $this->wizard = app(YearChangeWizardService::class);
    }

    protected function tearDown(): void
    {

        $this->cleanupCentralTestData();

        parent::tearDown();
    }

    private function cleanupCentralTestData(): void
    {
        ExamPeriod::whereIn('year', [self::YEAR_OLD, self::YEAR_NEW])->delete();
    }

    private function createExamPeriod(int $year): ExamPeriod
    {
        return ExamPeriod::create([
            'name' => "Testperiode Wizard ({$year})",
            'year' => $year,
            'week_1_start' => "{$year}-05-04",
            'week_1_end' => "{$year}-05-08",
            'week_2_start' => "{$year}-05-11",
            'week_2_end' => "{$year}-05-15",
        ]);
    }

    /** Schritte 1..n über completeStep() abhaken (Gating-Abkürzung). */
    private function completeStepsThrough(int $lastStep): void
    {
        for ($step = 1; $step <= $lastStep; $step++) {
            $this->wizard->completeStep($step);
        }
    }

    #[Test]
    public function schuljahr_wird_aus_abiturjahr_abgeleitet(): void
    {
        $this->assertSame('2025/26', YearChangeWizardService::schoolYearForAbiturYear(2026));
        $this->assertSame('2103/04', YearChangeWizardService::schoolYearForAbiturYear(2104));
        $this->assertSame('2099/00', YearChangeWizardService::schoolYearForAbiturYear(2100));
    }

    #[Test]
    public function preflight_ermittelt_quell_und_zielperiode(): void
    {
        $preflight = $this->wizard->preflight();

        $this->assertSame([], $preflight['errors']);
        $this->assertSame($this->newPeriod->id, $preflight['target']?->id);
        $this->assertSame($this->oldPeriod->id, $preflight['source']?->id);
        // newStudent existiert bereits in der Ziel-Periode → Warnung
        $this->assertNotEmpty($preflight['warnings']);
    }

    #[Test]
    public function start_legt_state_an_und_fixiert_perioden(): void
    {
        $this->assertFalse($this->wizard->isRunning());

        $state = $this->wizard->start();

        $this->assertTrue($this->wizard->isRunning());
        $this->assertSame($this->oldPeriod->id, $state['source_period_id']);
        $this->assertSame($this->newPeriod->id, $state['target_period_id']);
        $this->assertSame(self::SCHOOL_YEAR, $state['source_school_year']);
        $this->assertSame(1, $this->wizard->currentStep());
    }

    #[Test]
    public function doppelter_start_wird_abgelehnt(): void
    {
        $this->wizard->start();

        $this->expectException(\RuntimeException::class);
        $this->wizard->start();
    }

    #[Test]
    public function schritte_sind_strikt_linear_freigeschaltet(): void
    {
        $this->wizard->start();

        $this->assertTrue($this->wizard->stepAvailable(1));
        $this->assertFalse($this->wizard->stepAvailable(2));

        // Schritt 3 ohne 1+2 → gesperrt
        try {
            $this->wizard->runStep(3);
            $this->fail('runStep(3) hätte eine RuntimeException werfen müssen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('nicht freigeschaltet', $e->getMessage());
        }

        $this->wizard->completeStep(1);
        $this->assertTrue($this->wizard->stepAvailable(2));
        $this->assertSame(2, $this->wizard->currentStep());
    }

    #[Test]
    public function abgeschlossener_schritt_kann_nicht_erneut_abgeschlossen_werden(): void
    {
        $this->wizard->start();
        $this->wizard->completeStep(1);

        $this->expectException(\RuntimeException::class);
        $this->wizard->completeStep(1);
    }

    #[Test]
    public function schritt5_verlangt_schueler_in_der_zielperiode(): void
    {
        $this->wizard->start();
        $this->completeStepsThrough(4);

        // Ziel-Periode leeren (Absenz zuerst wegen FK)
        Absence::where('student_id', $this->newStudent->id)->delete();
        $this->newStudent->delete();

        $this->expectException(\RuntimeException::class);
        $this->wizard->completeStep(5);
    }

    #[Test]
    public function kompletter_durchlauf_mit_allen_aktionen(): void
    {
        $this->wizard->start();

        // Schritt 1: Voraussetzungen bestätigen
        $this->wizard->completeStep(1);

        // Schritt 2: echtes Backup
        $backup = $this->wizard->runStep(2);
        $this->assertTrue($this->wizard->stepCompleted(2));
        $this->assertStringContainsString('auto-before-yearchange', $backup['filename']);

        // Schritt 3: Einfrieren (nur Quell-Periode, 1 Schüler mit Absenzen)
        $freeze = $this->wizard->runStep(3);
        $this->assertSame(1, $freeze['frozen']);
        $this->assertSame(0, $freeze['purged_old_carryovers']);
        $this->assertSame(1, AbsenceCarryover::where('source_school_year', self::SCHOOL_YEAR)->count());

        // Schritt 4: Rohdaten der Quell-Periode löschen — Ziel-Periode bleibt
        $purge = $this->wizard->runStep(4);
        $this->assertSame(1, $purge['deleted_absences']);
        $this->assertSame(0, $purge['deleted_measures']);
        $this->assertSame(1, $purge['deleted_imports']);
        $this->assertSame(0, Absence::where('student_id', $this->oldStudent->id)->count());
        $this->assertSame(1, Absence::where('student_id', $this->newStudent->id)->count());
        $this->assertNull(AbsenceImport::find($this->importOld->id));
        $this->assertNotNull(AbsenceImport::find($this->importNew->id));

        // Schritt 5: Schüler sind in der Ziel-Periode vorhanden
        $this->wizard->completeStep(5);
        $this->assertSame(1, $this->wizard->stepResult(5)['students_in_target'] ?? null);

        // Schritt 6: Auto-Zuordnung (external_id) + manueller Abschluss
        $match = $this->wizard->runStep(6);
        $this->assertSame(1, $match['matched_now']);
        $this->assertSame(0, $match['unmatched']);
        $this->assertFalse($this->wizard->stepCompleted(6), 'Schritt 6 schließt sich nicht selbst ab (Klärungsliste).');
        $this->wizard->completeStep(6);
        $this->assertTrue($this->wizard->stepCompleted(6));

        // Schritt 7: Vorlagen kopieren (Quelle: 2 Slot-Vorlagen, 1 Aufsichtsvorlage,
        // 1 Prüfungstag-Zuordnung; Ziel hat 'Nachmittag' bereits → wird übersprungen)
        $vormittag = SlotTemplate::create([
            'exam_period_id' => $this->oldPeriod->id,
            'name' => 'Vormittag',
            'description' => 'Slots 1-4',
        ]);
        SlotTemplateItem::create([
            'slot_template_id' => $vormittag->id,
            'slot_number' => 1,
            'prep_start_time' => '08:00',
            'prep_end_time' => '08:30',
            'exam_start_time' => '08:30',
            'exam_end_time' => '09:00',
            'preference_weight' => 1,
        ]);
        SlotTemplate::create(['exam_period_id' => $this->oldPeriod->id, 'name' => 'Nachmittag']);
        SlotTemplate::create(['exam_period_id' => $this->newPeriod->id, 'name' => 'Nachmittag']);
        SupervisionTemplate::create([
            'exam_period_id' => $this->oldPeriod->id,
            'name' => 'Aufsicht Standard',
            'is_active' => true,
        ]);
        ExamDayTemplate::create([
            'exam_period_id' => $this->oldPeriod->id,
            'date' => self::YEAR_OLD . '-05-04',
            'slot_template_id' => $vormittag->id,
        ]);

        $copy = $this->wizard->runStep(7);
        $this->assertSame(1, $copy['slot_templates_copied']);
        $this->assertSame(1, $copy['slot_templates_skipped']);
        $this->assertSame(1, $copy['supervision_templates_copied']);

        $copiedTemplate = SlotTemplate::where('exam_period_id', $this->newPeriod->id)
            ->where('name', 'Vormittag')->firstOrFail();
        $this->assertSame(1, $copiedTemplate->items()->count());
        $this->assertSame(
            1,
            SupervisionTemplate::where('exam_period_id', $this->newPeriod->id)->count()
        );
        // Entscheidung A: Prüfungstag-Zuordnungen werden NICHT kopiert
        $this->assertSame(
            0,
            ExamDayTemplate::where('exam_period_id', $this->newPeriod->id)->count()
        );

        // Schritt 8 + Abschluss
        $this->wizard->completeStep(8);
        $this->assertFalse($this->wizard->isCompleted());
        $this->wizard->finish();

        $this->assertTrue($this->wizard->isCompleted());
        $this->assertFalse($this->wizard->isRunning());
        $this->assertNotNull($this->wizard->state()['completed_at']);
    }

    #[Test]
    public function finish_verlangt_alle_acht_schritte(): void
    {
        $this->wizard->start();
        $this->completeStepsThrough(4);

        $this->expectException(\RuntimeException::class);
        $this->wizard->finish();
    }

    #[Test]
    public function manuelle_zuordnung_aus_der_klaerungsliste(): void
    {
        // Mehrdeutigkeit erzeugen: external_id weg, zweiter gleichnamiger Schüler
        $this->newStudent->update(['external_id' => '']);
        Student::create([
            'firstname' => 'Willi',
            'lastname' => 'Wechseltest',
            'external_id' => 'YRC-002',
            'grade' => '13',
            'exam_period_id' => $this->newPeriod->id,
        ]);

        $this->wizard->start();
        $this->wizard->completeStep(1);
        $this->wizard->completeStep(2);
        $this->wizard->runStep(3);
        $this->wizard->completeStep(4);
        $this->wizard->completeStep(5);

        $match = $this->wizard->runStep(6);
        $this->assertSame(0, $match['matched_now']);
        $this->assertSame(1, $match['unmatched']);

        $carryover = AbsenceCarryover::where('source_school_year', self::SCHOOL_YEAR)->firstOrFail();
        $this->wizard->manualMatch($carryover->id, $this->newStudent->id);

        $carryover->refresh();
        $this->assertSame($this->newStudent->id, $carryover->matched_student_id);
        $this->assertSame('manuell', $carryover->match_method);
        $this->assertNotNull($carryover->matched_at);
    }

    /**
     * Die Klärungsliste trennt Abgänger von echten Fällen.
     *
     * Beim Probelauf mit den Echtdaten (25.08.2026) waren 114 der 118
     * unzugeordneten Vorjahreswerte Abiturienten, die die Schule verlassen
     * haben — die vier Fälle, die wirklich Arbeit bedeuten, gingen darin
     * unter.
     */
    #[Test]
    public function klaerungsliste_trennt_abgaenger_von_echten_faellen(): void
    {
        $mk = function (string $lastname, ?string $grade): AbsenceCarryover {
            return AbsenceCarryover::create([
                'firstname' => 'Test',
                'lastname' => $lastname,
                'source_school_year' => self::SCHOOL_YEAR,
                'source_grade' => $grade,
                'sick_days' => 1,
                'unexcused_hours' => 0,
                'tardiness_count' => 0,
                'tardiness_minutes' => 0,
                'had_attest_requirement' => false,
            ]);
        };

        $mk('Abgaenger', '13');
        $mk('Bleibt', '12');
        $mk('Jungjahrgang', '11');
        $mk('OhneAngabe', null);

        $base = AbsenceCarryover::where('source_school_year', self::SCHOOL_YEAR)
            ->whereNull('matched_student_id');

        $this->assertSame(1, (clone $base)->fromGraduatingGrade()->count());

        // Ohne Jahrgangsangabe zählt bewusst zu den echten Fällen: lieber ein
        // Eintrag zu viel in der Liste als einer, der stillschweigend verschwindet.
        $continuing = (clone $base)->fromContinuingGrades()->pluck('lastname')->sort()->values()->all();
        $this->assertSame(['Bleibt', 'Jungjahrgang', 'OhneAngabe'], $continuing);
    }

    /**
     * Ein Wiederholer muss zuordenbar bleiben — deshalb werden die
     * Carryovers des Abiturjahrgangs nicht verworfen, sondern nur getrennt
     * ausgewiesen.
     */
    #[Test]
    public function abgaenger_bleiben_fuer_wiederholer_zuordenbar(): void
    {
        // Wiederholer: derselbe Mensch, aber der Automatik-Abgleich greift
        // nicht (abweichender Nachname) — genau der Fall, für den die
        // Abgänger-Liste zuordenbar bleiben muss.
        $wiederholer = Student::create([
            'firstname' => 'Wieder',
            'lastname' => 'Holer-Neu',
            'external_id' => 'YRC-WH',
            'grade' => '13',
            'exam_period_id' => $this->newPeriod->id,
        ]);

        $this->wizard->start();
        $this->completeStepsThrough(5);

        $carryover = AbsenceCarryover::create([
            'firstname' => 'Wieder',
            'lastname' => 'Holer',
            'source_school_year' => self::SCHOOL_YEAR,
            'source_grade' => '13',
            'sick_days' => 5,
            'unexcused_hours' => 0,
            'tardiness_count' => 0,
            'tardiness_minutes' => 0,
            'had_attest_requirement' => false,
        ]);

        $this->wizard->runStep(6);
        $carryover->refresh();
        $this->assertNull($carryover->matched_student_id, 'Automatik darf hier nicht greifen');

        $updated = $this->wizard->manualMatch($carryover->id, $wiederholer->id);

        $this->assertSame($wiederholer->id, $updated->matched_student_id);
        $this->assertSame('manuell', $updated->match_method);
    }


    #[Test]
    public function reset_loescht_den_state(): void
    {
        $this->wizard->start();
        $this->wizard->completeStep(1);

        $this->wizard->reset();

        $this->assertNull($this->wizard->state());
        $this->assertFalse($this->wizard->isRunning());
    }

    #[Test]
    public function schritt7_uebernimmt_zeitslots_aufsichtsslots_und_schulstammdaten(): void
    {
        $this->school->update([
            'address' => 'Albrechtstr. 4-6',
            'postal_code' => '80636',
            'city' => 'München',
            'email' => 'schule@test.invalid',
            'phone' => '089-12345',
        ]);

        $firstSourceDay = $this->oldPeriod->getExamDates(1)->first()->format('Y-m-d');
        $firstTargetDay = $this->newPeriod->getExamDates(1)->first()->format('Y-m-d');

        // Kolloquiums-Zeitslot am 1. Tag der Quell-Periode (Woche 1, 1. Wochentag)
        \App\Models\TimeSlot::create([
            'exam_period_id' => $this->oldPeriod->id,
            'date' => $firstSourceDay,
            'slot_number' => 1,
            'prep_start_time' => '12:40:00',
            'prep_end_time' => '13:10:00',
            'exam_start_time' => '13:10:00',
            'exam_end_time' => '13:40:00',
            'preference_weight' => 120,
            'is_active' => true,
        ]);

        $grid = \App\Models\SupervisionGrid::create([
            'exam_period_id' => $this->oldPeriod->id,
            'name' => 'Standard',
            'color' => 'blue',
            'is_default' => true,
        ]);

        \App\Models\SupervisionTimeSlot::create([
            'exam_period_id' => $this->oldPeriod->id,
            'supervision_grid_id' => $grid->id,
            'date' => $firstSourceDay,
            'start_time' => '13:30:00',
            'end_time' => '14:30:00',
            'slot_number' => 1,
        ]);

        $result = $this->wizard->copyScheduleAndTemplates($this->oldPeriod, $this->newPeriod);

        $this->assertSame(1, $result['time_slots_copied']);
        $this->assertSame(1, $result['supervision_grids_copied']);
        $this->assertSame(1, $result['supervision_time_slots_copied']);
        $this->assertTrue($result['school_master_data_synced']);

        $newSlot = \App\Models\TimeSlot::where('exam_period_id', $this->newPeriod->id)->firstOrFail();
        $this->assertSame($firstTargetDay, $newSlot->date->format('Y-m-d'));
        $this->assertSame('12:40:00', $newSlot->getRawOriginal('prep_start_time'));

        $newSupSlot = \App\Models\SupervisionTimeSlot::where('exam_period_id', $this->newPeriod->id)->firstOrFail();
        $this->assertSame($firstTargetDay, $newSupSlot->date->format('Y-m-d'));
        $this->assertSame('13:30:00', $newSupSlot->getRawOriginal('start_time'));
        $this->assertSame('Albrechtstr. 4-6', \App\Models\SchoolSetting::get('school.address'));
    }

    /**
     * Die Einzelaktion für Schulen, deren Wechsel ohne Wizard lief: Sie
     * braucht keinen gestarteten Wechsel, darf beliebig oft laufen, ohne
     * etwas doppelt anzulegen, und weicht einem laufenden Wechsel aus.
     */
    #[Test]
    public function vorlagen_lassen_sich_ohne_wizard_wiederholbar_uebernehmen(): void
    {
        \App\Models\TimeSlot::create([
            'exam_period_id' => $this->oldPeriod->id,
            'date' => $this->oldPeriod->getExamDates(1)->first()->format('Y-m-d'),
            'slot_number' => 1,
            'prep_start_time' => '08:00:00',
            'prep_end_time' => '08:30:00',
            'exam_start_time' => '08:30:00',
            'exam_end_time' => '09:00:00',
            'preference_weight' => 100,
            'is_active' => true,
        ]);

        $this->assertFalse($this->wizard->isRunning());

        $erster = $this->wizard->copyFromPreviousPeriod();
        $zweiter = $this->wizard->copyFromPreviousPeriod();

        $this->assertSame(1, $erster['time_slots_copied']);
        $this->assertSame(0, $zweiter['time_slots_copied']);
        $this->assertSame(1, \App\Models\TimeSlot::where('exam_period_id', $this->newPeriod->id)->count());
        $this->assertNull($this->wizard->state(), 'Die Einzelaktion darf keinen Wechsel anlegen.');

        $this->wizard->start();
        $this->expectException(\RuntimeException::class);
        $this->wizard->copyFromPreviousPeriod();
    }

    #[Test]
    public function q12_und_q13_schueler_und_absenzen_koennen_parallel_ohne_ueberschreiben_importiert_werden(): void
    {
        $studentService = app(\App\Services\StudentCsvImportService::class);

        $csvQ13 = tempnam(sys_get_temp_dir(), 'q13_');
        file_put_contents($csvQ13, implode("\r\n", [
            'Nr.;Name, Rufname;S1;;S2;;S3;;K1;;K2;',
            ';Achleitner, Evelyne;;;;;;;;;;',
            '1;;2d1;EXTERN;2m1;EXTERN;2e1;EXTERN;2g1;EXTERN;2b1;EXTERN',
        ]));

        $csvQ12 = tempnam(sys_get_temp_dir(), 'q12_');
        file_put_contents($csvQ12, implode("\r\n", [
            'Nr.;Name, Rufname;S1;;S2;;S3;;K1;;K2;',
            ';Ostler, Jonas;;;;;;;;;;',
            '1;;1d1;EXTERN;1m1;EXTERN;1e1;EXTERN;1g1;EXTERN;1b1;EXTERN',
        ]));

        try {
            // Automatische Jahrgangserkennung anhand der Kurscodes prüfen
            $preview13 = $studentService->parseCSVPreview($csvQ13, ';', $this->newPeriod);
            $preview12 = $studentService->parseCSVPreview($csvQ12, ';', $this->newPeriod);
            $this->assertSame('13', $studentService->detectGradeFromPreview($preview13));
            $this->assertSame('12', $studentService->detectGradeFromPreview($preview12));

            // 1. Q13 importieren
            $studentService->clearExistingData($this->newPeriod, '13');
            $studentService->processCSV($csvQ13, ';', $this->newPeriod, [], '13', $this->school->school_number);

            // 2. Q12 mit derselben external_id = '1' und clearExistingData('12') importieren
            $studentService->clearExistingData($this->newPeriod, '12');
            $studentService->processCSV($csvQ12, ';', $this->newPeriod, [], '12', $this->school->school_number);

            // Beide Schüler (Q13 und Q12) sowie ihre Kurse müssen nebeneinander existieren
            $q13Student = Student::where('exam_period_id', $this->newPeriod->id)
                ->where('grade', '13')
                ->where('lastname', 'Achleitner')
                ->first();
            $q12Student = Student::where('exam_period_id', $this->newPeriod->id)
                ->where('grade', '12')
                ->where('lastname', 'Ostler')
                ->first();

            $this->assertNotNull($q13Student, 'Q13-Schülerin darf durch Q12-Import nicht überschrieben werden.');
            $this->assertNotNull($q12Student, 'Q12-Schüler muss angelegt sein.');
            $this->assertSame(5, $q13Student->exams()->count(), 'Q13-Prüfungen müssen erhalten bleiben.');
            $this->assertSame(5, $q12Student->exams()->count(), 'Q12-Prüfungen müssen angelegt sein.');
        } finally {
            @unlink($csvQ13);
            @unlink($csvQ12);
        }
    }
}
