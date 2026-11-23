<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Services\PlanningSimulationService;

/**
 * Modul 4.6 – Optimierungssimulation, Phase 1: Dry-Run-Fähigkeit.
 *
 * Beweisziele:
 *  - Ein Dry-Run gegen hypothetische, injizierte In-Memory-Ressourcen liefert
 *    plausible Kennzahlen (Prüfungen werden geplant).
 *  - KERNBEWEIS: Der Echtdatenbestand bleibt unverändert – der Dry-Run legt
 *    KEINE echten TimeSlot-, Room- oder ExamAssignment-Datensätze an.
 *  - Mehr Ressourcen führen zu mindestens ebenso vielen geplanten Prüfungen
 *    (Monotonie-Plausibilität).
 *
 * Läuft gegen die echte Tenant-DB der Testschule 9999.
 */
class SimulationDryRunTest extends TenantTestCase
{
    private int $seq = 0;

    private function makeOralExam(ExamPeriod $examPeriod, Teacher $examiner, ?Teacher $assessor = null): StudentExam
    {
        $this->seq++;

        $course = Course::create([
            'exam_period_id' => $examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'sim' . $this->seq,
            'teacher_id'     => $examiner->id,
            'level'          => 'grundlegend',
        ]);

        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id'    => 'SIM-' . $this->seq,
            'grade'          => '13',
            'firstname'      => 'Sim',
            'lastname'       => 'Schueler' . $this->seq,
        ]);

        return StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $examiner->id,
            'assessor_id' => $assessor?->id,
            'exam_type'   => 'oral',
        ]);
    }

    /**
     * Ein Dry-Run plant die realen mündlichen Prüfungen gegen hypothetische
     * Ressourcen ein und liefert plausible Kennzahlen.
     */
    public function test_dry_run_plant_pruefungen_und_liefert_kennzahlen(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'SIMA', 'firstname' => 'Anna', 'lastname' => 'Sim']);
        $b = Teacher::create(['shortcode' => 'SIMB', 'firstname' => 'Bert', 'lastname' => 'Sim']);

        // Vier mündliche Prüfungen (Prüfer + Beisitzer real vorhanden).
        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $b, $a);
        $this->makeOralExam($examPeriod, $b, $a);

        $result = app(PlanningSimulationService::class)->dryRun($examPeriod, days: 4, slotsPerDay: 4, rooms: 3);

        $this->assertSame(4, $result['total_oral'], 'Es gibt vier mündliche Prüfungen.');
        $this->assertGreaterThan(0, $result['scheduled'], 'Der Dry-Run muss Prüfungen einplanen.');
        $this->assertSame(4 * 4 * 3, $result['capacity'], 'Kapazität = Tage × Slots/Tag × Räume.');
        $this->assertArrayHasKey('exams_scheduled', $result['stats']);

    }

    /**
     * KERNBEWEIS: Der Dry-Run verändert den Echtbestand nicht.
     */
    public function test_dry_run_veraendert_den_echtbestand_nicht(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'SIMC', 'firstname' => 'Cora', 'lastname' => 'Sim']);
        $b = Teacher::create(['shortcode' => 'SIMD', 'firstname' => 'Dirk', 'lastname' => 'Sim']);

        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $b, $a);

        // Echtbestand vor dem Dry-Run.
        $slotsBefore       = TimeSlot::count();
        $roomsBefore       = Room::count();
        $assignmentsBefore = ExamAssignment::count();

        app(PlanningSimulationService::class)->dryRun($examPeriod, days: 3, slotsPerDay: 4, rooms: 3);

        // Nichts darf sich geändert haben – keine hypothetischen Ressourcen
        // und keine Zuweisungen wurden persistiert.
        $this->assertSame($slotsBefore, TimeSlot::count(), 'Der Dry-Run darf keine TimeSlots anlegen.');
        $this->assertSame($roomsBefore, Room::count(), 'Der Dry-Run darf keine Räume anlegen.');
        $this->assertSame($assignmentsBefore, ExamAssignment::count(), 'Der Dry-Run darf keine Zuweisungen anlegen.');

        // Insbesondere keine Sentinel-Raumnamen aus der Simulation.
        $this->assertSame(0, Room::where('room_number', 'like', 'SIM-%')->count());

    }

    /**
     * Monotonie-Plausibilität: Mehr Ressourcen dürfen nicht zu WENIGER
     * geplanten Prüfungen führen. Eine sehr knappe Kombination (1 Tag, 1
     * Slot, 1 Raum) kann nicht alle vier Prüfungen unterbringen; eine großzügige
     * Kombination schon.
     */
    public function test_mehr_ressourcen_plant_mindestens_ebenso_viele_pruefungen(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'SIME', 'firstname' => 'Eva', 'lastname' => 'Sim']);
        $b = Teacher::create(['shortcode' => 'SIMF', 'firstname' => 'Finn', 'lastname' => 'Sim']);

        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $b, $a);
        $this->makeOralExam($examPeriod, $b, $a);

        $sim = app(PlanningSimulationService::class);

        $tight = $sim->dryRun($examPeriod, days: 1, slotsPerDay: 1, rooms: 1);
        $ample = $sim->dryRun($examPeriod, days: 5, slotsPerDay: 5, rooms: 5);

        $this->assertLessThanOrEqual(
            $ample['scheduled'],
            $tight['scheduled'],
            'Mehr Ressourcen dürfen nicht weniger geplante Prüfungen ergeben.'
        );
        $this->assertGreaterThanOrEqual(
            $tight['scheduled'],
            $ample['scheduled'],
            'Die großzügige Kombination plant mindestens so viele Prüfungen.'
        );

        // Die knappe 1×1×1-Kombination kann nicht alle vier unterbringen.
        $this->assertLessThan(
            $ample['total_oral'],
            $tight['scheduled'],
            'Eine 1×1×1-Ausstattung kann nicht alle Prüfungen aufnehmen.'
        );

    }

    /**
     * Phase 2 – arithmetische Schranke: analyzeDemand zählt Prüfer- und
     * Beisitzer-Einsätze korrekt zusammen und findet die höchste Einzellast.
     */
    public function test_analyze_demand_zaehlt_pruefer_und_beisitzer_einsaetze(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $vielprüfer = Teacher::create(['shortcode' => 'SIMV', 'firstname' => 'Vera', 'lastname' => 'Viel']);
        $partner    = Teacher::create(['shortcode' => 'SIMP', 'firstname' => 'Pia', 'lastname' => 'Part']);

        // Vera prüft dreimal und ist zweimal Beisitzerin => Last 5.
        $this->makeOralExam($examPeriod, $vielprüfer, $partner);
        $this->makeOralExam($examPeriod, $vielprüfer, $partner);
        $this->makeOralExam($examPeriod, $vielprüfer, $partner);
        $this->makeOralExam($examPeriod, $partner, $vielprüfer);
        $this->makeOralExam($examPeriod, $partner, $vielprüfer);

        $demand = app(PlanningSimulationService::class)->analyzeDemand($examPeriod);

        $this->assertSame(5, $demand['total']);
        $this->assertSame(5, $demand['max_teacher_load'], 'Vera hat 3 Prüfungen + 2 Beisitze = 5 Einsätze.');

    }

    /**
     * KERN von Phase 2: Der Prüfer-Engpass macht eine Kombination unmöglich,
     * egal wie viele Räume verfügbar sind. Eine Lehrkraft mit 5 Einsätzen
     * passt nie in 1 Tag × 2 Slots (= 2 Zeitpunkte), auch nicht mit 50 Räumen.
     */
    public function test_pruefer_engpass_ist_raumunabhaengig(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $engpass = Teacher::create(['shortcode' => 'SIMG', 'firstname' => 'Gustl', 'lastname' => 'Eng']);
        $partner = Teacher::create(['shortcode' => 'SIMH', 'firstname' => 'Hanna', 'lastname' => 'Part']);

        for ($i = 0; $i < 5; $i++) {
            $this->makeOralExam($examPeriod, $engpass, $partner);
        }

        $sim = app(PlanningSimulationService::class);
        $demand = $sim->analyzeDemand($examPeriod);

        // 1 Tag × 2 Slots = 2 Zeitpunkte, aber Gustl hat 5 Einsätze.
        $tight = $sim->isArithmeticallyFeasible($demand, days: 1, slotsPerDay: 2, rooms: 50);
        $this->assertFalse($tight['feasible'], 'Prüfer-Engpass darf nicht durch Räume umgangen werden.');
        $this->assertNotEmpty($tight['reasons']);

        // 3 Tage × 2 Slots = 6 Zeitpunkte ≥ 5 Einsätze => Engpass aufgelöst.
        $ample = $sim->isArithmeticallyFeasible($demand, days: 3, slotsPerDay: 2, rooms: 2);
        $this->assertTrue($ample['feasible'], 'Mit genug Zeitpunkten ist der Engpass aufgelöst.');

    }

    /**
     * Die Gesamtkapazität greift, wenn schlicht zu wenige Plätze da sind,
     * auch wenn kein einzelner Prüfer überlastet ist.
     */
    public function test_gesamtkapazitaet_greift_bei_zu_wenigen_plaetzen(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        // 6 Prüfungen mit je eigenem Prüfer (keine Einzellast > 1).
        for ($i = 0; $i < 6; $i++) {
            $t = Teacher::create(['shortcode' => 'SIMK' . $i, 'firstname' => 'K' . $i, 'lastname' => 'Solo']);
            $this->makeOralExam($examPeriod, $t);
        }

        $sim = app(PlanningSimulationService::class);
        $demand = $sim->analyzeDemand($examPeriod);

        $this->assertSame(1, $demand['max_teacher_load'], 'Kein Prüfer ist überlastet.');

        // 1 Tag × 1 Slot × 4 Räume = 4 Plätze < 6 Prüfungen.
        $result = $sim->isArithmeticallyFeasible($demand, days: 1, slotsPerDay: 1, rooms: 4);
        $this->assertFalse($result['feasible']);

        // 1 Tag × 2 Slots × 4 Räume = 8 Plätze ≥ 6 => machbar (arithmetisch).
        $ok = $sim->isArithmeticallyFeasible($demand, days: 1, slotsPerDay: 2, rooms: 4);
        $this->assertTrue($ok['feasible']);

    }

    /**
     * Phase 3 – Suche: liefert Vorschläge, und die arithmetische Schranke
     * spart echte Dry-Runs (skipped_arithmetic > 0 im gemischten Suchraum).
     */
    public function test_suche_liefert_vorschlaege_und_spart_dry_runs(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'SIMS', 'firstname' => 'Sina', 'lastname' => 'Such']);
        $b = Teacher::create(['shortcode' => 'SIMT', 'firstname' => 'Timo', 'lastname' => 'Such']);

        // Vier mündliche Prüfungen.
        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $b, $a);
        $this->makeOralExam($examPeriod, $b, $a);

        $ranges = [
            'days'          => [1, 4],
            'slots_per_day' => [1, 4],
            'rooms'         => [1, 4],
        ];

        $out = app(PlanningSimulationService::class)->search($examPeriod, $ranges);

        $this->assertGreaterThan(0, $out['feasible_count'], 'Es muss machbare Kombinationen geben.');
        $this->assertNotEmpty($out['suggestions'], 'Es müssen Vorschläge geliefert werden.');
        $this->assertGreaterThan(0, $out['skipped_arithmetic'], 'Die Schranke muss Kombinationen aussortieren.');

        // Jeder Vorschlag ist wirklich machbar (alle Prüfungen platziert).
        foreach ($out['suggestions'] as $label => $s) {
            $this->assertTrue($s['feasible'], "Vorschlag '$label' muss machbar sein.");
            $this->assertSame(0, $s['unplaced'], "Vorschlag '$label' darf keine offenen Prüfungen haben.");
        }

    }

    /**
     * Die Vorschlags-Semantik stimmt: 'compact' hat nicht mehr Tage als
     * 'spread', und 'spread' hat nicht mehr Slots/Tag als 'compact'.
     */
    public function test_compact_hat_wenige_tage_spread_wenige_slots(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'SIMU', 'firstname' => 'Uwe', 'lastname' => 'Sem']);
        $b = Teacher::create(['shortcode' => 'SIMW', 'firstname' => 'Wera', 'lastname' => 'Sem']);

        for ($i = 0; $i < 6; $i++) {
            $this->makeOralExam($examPeriod, $i % 2 === 0 ? $a : $b, $i % 2 === 0 ? $b : $a);
        }

        $ranges = [
            'days'          => [1, 5],
            'slots_per_day' => [1, 5],
            'rooms'         => [1, 5],
        ];

        $out = app(PlanningSimulationService::class)->search($examPeriod, $ranges);

        if (isset($out['suggestions']['compact'], $out['suggestions']['spread'])) {
            $compact = $out['suggestions']['compact'];
            $spread  = $out['suggestions']['spread'];

            $this->assertLessThanOrEqual(
                $spread['days'],
                $compact['days'],
                "'compact' darf nicht mehr Tage als 'spread' haben."
            );
            $this->assertLessThanOrEqual(
                $compact['slots_per_day'],
                $spread['slots_per_day'],
                "'spread' darf nicht mehr Slots/Tag als 'compact' haben."
            );
        } else {
            // Fallen compact und spread auf dieselbe Kombination, ist das ein
            // gültiges Ergebnis (kleiner Suchraum) – dann nur Existenz prüfen.
            $this->assertNotEmpty($out['suggestions']);
        }

    }

    /**
     * Auch die Suche verändert den Echtbestand nicht (viele Dry-Runs, aber
     * keine Persistenz).
     */
    public function test_suche_veraendert_den_echtbestand_nicht(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'SIMX', 'firstname' => 'Xena', 'lastname' => 'Such']);
        $b = Teacher::create(['shortcode' => 'SIMY', 'firstname' => 'Yann', 'lastname' => 'Such']);
        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $b, $a);

        $slotsBefore       = TimeSlot::count();
        $roomsBefore       = Room::count();
        $assignmentsBefore = ExamAssignment::count();

        app(PlanningSimulationService::class)->search($examPeriod, [
            'days'          => [1, 3],
            'slots_per_day' => [1, 3],
            'rooms'         => [1, 3],
        ]);

        $this->assertSame($slotsBefore, TimeSlot::count());
        $this->assertSame($roomsBefore, Room::count());
        $this->assertSame($assignmentsBefore, ExamAssignment::count());
        $this->assertSame(0, Room::where('room_number', 'like', 'SIM-%')->count());

    }

    /**
     * Phase 5 (Export): dryRun($withPlan = true) liefert unter 'plan' die nach
     * Tag → Slot gruppierte exemplarische Belegung samt lesbarer Namen.
     */
    public function test_dry_run_mit_plan_liefert_gruppierte_belegung(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'PLNA', 'firstname' => 'Alea', 'lastname' => 'Plan']);
        $b = Teacher::create(['shortcode' => 'PLNB', 'firstname' => 'Bodo', 'lastname' => 'Plan']);

        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $b, $a);

        $result = app(PlanningSimulationService::class)
            ->dryRun($examPeriod, days: 3, slotsPerDay: 4, rooms: 3, withPlan: true);

        $this->assertArrayHasKey('plan', $result, 'Mit withPlan muss ein Plan geliefert werden.');
        $this->assertNotEmpty($result['plan'], 'Der Plan darf nicht leer sein, es gibt geplante Prüfungen.');

        // Struktur des ersten Tages prüfen.
        $day = $result['plan'][0];
        $this->assertArrayHasKey('date', $day);
        $this->assertArrayHasKey('weekday', $day);
        $this->assertArrayHasKey('count', $day);
        $this->assertArrayHasKey('slots', $day);
        $this->assertNotEmpty($day['slots']);

        // Erste Zeile: Namen wurden korrekt aufgelöst (keine Platzhalter).
        // makeOralExam erzeugt Schüler "Schueler{n}, Sim".
        $firstExam = $day['slots'][0]['exams'][0];
        $this->assertStringContainsString('Schueler', $firstExam['student']);
        $this->assertStringContainsString(',', $firstExam['student']);
        $this->assertStringStartsWith('SIM-', $firstExam['room']);
        $this->assertContains($firstExam['examiner'], ['PLNA', 'PLNB']);

        // Summe der Belegungszeilen über alle Tage = Anzahl geplanter Prüfungen.
        $planned = 0;
        foreach ($result['plan'] as $d) {
            foreach ($d['slots'] as $slot) {
                $planned += count($slot['exams']);
            }
        }
        $this->assertSame($result['scheduled'], $planned, 'Jede geplante Prüfung erscheint genau einmal im Plan.');

    }

    /**
     * Ohne das Flag bleibt der Plan-Aufbau (und das Namens-Laden) aus – der
     * Rückgabewert enthält keinen 'plan'-Schlüssel.
     */
    public function test_dry_run_ohne_flag_baut_keinen_plan(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'NOPA', 'firstname' => 'Nora', 'lastname' => 'Ohne']);
        $b = Teacher::create(['shortcode' => 'NOPB', 'firstname' => 'Olaf', 'lastname' => 'Ohne']);
        $this->makeOralExam($examPeriod, $a, $b);

        $result = app(PlanningSimulationService::class)
            ->dryRun($examPeriod, days: 3, slotsPerDay: 4, rooms: 3);

        $this->assertArrayNotHasKey('plan', $result);

    }

    /**
     * search() reichert die finalen Vorschläge mit dem Detailplan an.
     */
    public function test_suche_reichert_vorschlaege_mit_plan_an(): void
    {
        $school = $this->createTestSchool();

        $examPeriod = ExamPeriod::first();

        $a = Teacher::create(['shortcode' => 'SUPA', 'firstname' => 'Pia', 'lastname' => 'Such']);
        $b = Teacher::create(['shortcode' => 'SUPB', 'firstname' => 'Rolf', 'lastname' => 'Such']);
        $this->makeOralExam($examPeriod, $a, $b);
        $this->makeOralExam($examPeriod, $b, $a);

        $result = app(PlanningSimulationService::class)->search($examPeriod, [
            'days'          => [1, 3],
            'slots_per_day' => [1, 3],
            'rooms'         => [1, 3],
        ]);

        $this->assertNotEmpty($result['suggestions'], 'Es sollte mindestens einen Vorschlag geben.');
        foreach ($result['suggestions'] as $suggestion) {
            $this->assertArrayHasKey('plan', $suggestion, 'Jeder Vorschlag trägt einen Detailplan.');
        }

        // Der Echtbestand bleibt trotz Plan-Aufbau unverändert.
        $this->assertSame(0, Room::where('room_number', 'like', 'SIM-%')->count());

    }
}
