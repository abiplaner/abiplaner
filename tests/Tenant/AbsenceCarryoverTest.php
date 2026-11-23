<?php

namespace Tests\Tenant;

use App\Models\Absence;
use App\Models\AbsenceCarryover;
use App\Models\AbsenceImport;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\Student;
use App\Services\AbsenceCarryoverService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Vorjahres-Absenzkennzahlen einfrieren + zuordnen (Modul 1.1, Phase 3).
 *
 * Fiktive Jahre weit in der Zukunft (2097 = Quell-, 2098 = Ziel-Periode),
 * damit echte Perioden das "neueste Periode"-Verhalten nicht stören.
 */
class AbsenceCarryoverTest extends TenantTestCase
{
    private const YEAR_OLD = 2097;
    private const YEAR_NEW = 2098;
    private const SCHOOL_YEAR = '2096/97';

    protected School $school;
    protected ExamPeriod $oldPeriod;
    protected ExamPeriod $newPeriod;
    protected Student $oldStudent;
    protected Student $newStudent;
    protected AbsenceCarryoverService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $this->oldPeriod = $this->createExamPeriod(self::YEAR_OLD);
        $this->newPeriod = $this->createExamPeriod(self::YEAR_NEW);


        $this->oldStudent = Student::create([
            'firstname' => 'Theresa',
            'lastname' => 'Carrytest',
            'external_id' => 'CARRY-001',
            'grade' => '12',
            'exam_period_id' => $this->oldPeriod->id,
        ]);

        $this->newStudent = Student::create([
            'firstname' => 'Theresa',
            'lastname' => 'Carrytest',
            'external_id' => 'CARRY-001',
            'grade' => '13',
            'exam_period_id' => $this->newPeriod->id,
        ]);

        $import = AbsenceImport::create([
            'original_filename' => 'carryover-test.csv',
        ]);

        // 3 Krankheitstage
        Absence::create([
            'student_id' => $this->oldStudent->id,
            'absence_import_id' => $import->id,
            'source_key' => 'carry-test-1',
            'date_from' => self::YEAR_OLD . '-03-02',
            'date_to' => self::YEAR_OLD . '-03-04',
            'days' => 3,
            'hours' => 0,
            'minutes' => 0,
            'art_raw' => 'krank',
            'category' => 'krank',
            'excuse_status' => 'schriftlich',
        ]);

        // 1 Verspätung mit 10 Minuten
        Absence::create([
            'student_id' => $this->oldStudent->id,
            'absence_import_id' => $import->id,
            'source_key' => 'carry-test-2',
            'date_from' => self::YEAR_OLD . '-03-10',
            'date_to' => self::YEAR_OLD . '-03-10',
            'days' => 0,
            'hours' => 0,
            'minutes' => 10,
            'minutes_late' => 10,
            'art_raw' => 'verspaetet',
            'category' => 'verspaetet',
            'excuse_status' => 'offen',
        ]);

        // 2 unentschuldigte Fehlstunden
        Absence::create([
            'student_id' => $this->oldStudent->id,
            'absence_import_id' => $import->id,
            'source_key' => 'carry-test-3',
            'date_from' => self::YEAR_OLD . '-03-12',
            'date_to' => self::YEAR_OLD . '-03-12',
            'days' => 0,
            'hours' => 2,
            'minutes' => 0,
            'art_raw' => 'fehlt',
            'category' => 'fehlt',
            'excuse_status' => 'nein',
        ]);

        $this->service = app(AbsenceCarryoverService::class);
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
            'name' => "Testperiode Carryover ({$year})",
            'year' => $year,
            'week_1_start' => "{$year}-05-04",
            'week_1_end' => "{$year}-05-08",
            'week_2_start' => "{$year}-05-11",
            'week_2_end' => "{$year}-05-15",
        ]);
    }

    #[Test]
    public function freeze_aggregiert_kennzahlen_der_quellperiode(): void
    {
        $count = $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);

        $this->assertSame(1, $count);

        $carryover = AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail();
        $this->assertSame(3, $carryover->sick_days);
        $this->assertSame(2, $carryover->unexcused_hours);
        $this->assertSame(1, $carryover->tardiness_count);
        $this->assertSame(10, $carryover->tardiness_minutes);
        $this->assertFalse($carryover->had_attest_requirement);
        $this->assertSame(self::SCHOOL_YEAR, $carryover->source_school_year);
        $this->assertSame('12', $carryover->source_grade);
        $this->assertNull($carryover->matched_student_id);
    }

    #[Test]
    public function freeze_ist_idempotent(): void
    {
        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);

        $this->assertSame(1, AbsenceCarryover::where('lastname', 'Carrytest')->count());
    }

    #[Test]
    public function match_ordnet_ueber_external_id_zu(): void
    {
        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);

        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(1, $matched);

        $carryover = AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail();
        $this->assertSame($this->newStudent->id, $carryover->matched_student_id);
        $this->assertSame('auto', $carryover->match_method);
        $this->assertNotNull($carryover->matched_at);
    }

    #[Test]
    public function match_faellt_auf_eindeutigen_namen_zurueck(): void
    {
        // external_id des Ziel-Schülers entfernen → Namensabgleich greift
        $this->newStudent->update(['external_id' => '']);

        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(1, $matched);
        $this->assertSame(
            $this->newStudent->id,
            AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail()->matched_student_id
        );
    }

    #[Test]
    public function match_laesst_mehrdeutige_namen_unzugeordnet(): void
    {
        $this->newStudent->update(['external_id' => '']);

        // Zweiter gleichnamiger Schüler in der Ziel-Periode → mehrdeutig
        Student::create([
            'firstname' => 'Theresa',
            'lastname' => 'Carrytest',
            'external_id' => 'CARRY-002',
            'grade' => '13',
            'exam_period_id' => $this->newPeriod->id,
        ]);

        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(0, $matched);
        $this->assertNull(
            AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail()->matched_student_id
        );
    }

    /*
     * 8.1-Toleranzstufe (Rufname/Doppelname/Namenspartikel) im
     * Carryover-Abgleich — siehe NameMatchingService (Modul 1.1, Punkt 3).
     */

    #[Test]
    public function match_toleriert_rufname(): void
    {
        // Vorjahr führte den Rufnamen, neue Stammdaten den vollen Vornamen
        $this->oldStudent->update(['firstname' => 'Theo']);
        $this->newStudent->update(['firstname' => 'Theodor', 'external_id' => '']);

        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(1, $matched);

        $carryover = AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail();
        $this->assertSame($this->newStudent->id, $carryover->matched_student_id);
        $this->assertSame('auto', $carryover->match_method);
    }

    #[Test]
    public function match_toleriert_doppelnamen(): void
    {
        $this->oldStudent->update(['firstname' => 'Lilly']);
        $this->newStudent->update(['firstname' => 'Lilly-Marie', 'external_id' => '']);

        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(1, $matched);
        $this->assertSame(
            $this->newStudent->id,
            AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail()->matched_student_id
        );
    }

    #[Test]
    public function match_toleriert_namenspartikel(): void
    {
        // Eine Seite führt das Adelsprädikat, die andere nicht
        $this->oldStudent->update(['lastname' => 'Zeppelin']);
        $this->newStudent->update(['lastname' => 'von Zeppelin', 'external_id' => '']);

        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(1, $matched);
        $this->assertSame(
            $this->newStudent->id,
            AbsenceCarryover::where('lastname', 'Zeppelin')->firstOrFail()->matched_student_id
        );
    }

    #[Test]
    public function match_laesst_mehrdeutige_toleranz_treffer_unzugeordnet(): void
    {
        // "Theo" passt per Präfix-Toleranz auf BEIDE Zielschüler → Klärungsliste
        $this->oldStudent->update(['firstname' => 'Theo']);
        $this->newStudent->update(['firstname' => 'Theodor', 'external_id' => '']);

        Student::create([
            'firstname' => 'Theobald',
            'lastname' => 'Carrytest',
            'external_id' => 'CARRY-003',
            'grade' => '13',
            'exam_period_id' => $this->newPeriod->id,
        ]);

        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(0, $matched);
        $this->assertNull(
            AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail()->matched_student_id
        );
    }

    #[Test]
    public function exakter_treffer_gewinnt_vor_mehrdeutiger_toleranz(): void
    {
        // Exakt "Theo" existiert eindeutig — dass die Toleranzstufe zusätzlich
        // "Theodor" träfe, darf den exakten Treffer nicht entwerten
        $this->oldStudent->update(['firstname' => 'Theo']);
        $this->newStudent->update(['firstname' => 'Theo', 'external_id' => '']);

        Student::create([
            'firstname' => 'Theodor',
            'lastname' => 'Carrytest',
            'external_id' => 'CARRY-004',
            'grade' => '13',
            'exam_period_id' => $this->newPeriod->id,
        ]);

        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);
        $matched = $this->service->matchCarryovers(self::SCHOOL_YEAR, $this->newPeriod);

        $this->assertSame(1, $matched);
        $this->assertSame(
            $this->newStudent->id,
            AbsenceCarryover::where('lastname', 'Carrytest')->firstOrFail()->matched_student_id
        );
    }

    #[Test]
    public function purge_loescht_nur_fremde_schuljahre(): void
    {
        $this->service->freezeForPeriod($this->oldPeriod, self::SCHOOL_YEAR);

        AbsenceCarryover::create([
            'firstname' => 'Alt',
            'lastname' => 'Vorvorjahr',
            'source_school_year' => '2095/96',
        ]);

        $deleted = $this->service->purgeExcept(self::SCHOOL_YEAR);

        $this->assertSame(1, $deleted);
        $this->assertSame(0, AbsenceCarryover::where('source_school_year', '2095/96')->count());
        $this->assertSame(1, AbsenceCarryover::where('source_school_year', self::SCHOOL_YEAR)->count());
    }
}
