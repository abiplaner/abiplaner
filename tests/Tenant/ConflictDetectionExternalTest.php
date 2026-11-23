<?php

namespace Tests\Tenant;

use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\Room;
use App\Models\School;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ConflictDetectionService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Konflikterkennung mit externen Prüflingen.
 *
 * Die Kandidatenabfragen des ConflictDetectionService liefen über
 * `whereHas('student', …)`. Für externe Bewerber ist `student_id` seit dem
 * Öffnen von student_exams null — ihre Prüfungen fielen aus **jeder** dieser
 * Abfragen heraus und damit aus jeder Konflikterkennung. Das ist die stille
 * Sorte Fehler: kein 500er, keine Warnung, der Plan sieht sauber aus.
 *
 * Der zweite Fehler steckte eine Ebene tiefer und wäre durch die Korrektur
 * des ersten erst scharf geworden: `groupBy('studentExam.student_id')` macht
 * aus null den Array-Schlüssel "" — **alle** externen Prüflinge eines
 * Zeitraums landeten in einer Gruppe und hätten sich gegenseitig als
 * Doppelbelegung gemeldet.
 *
 * Bis zu diesen Tests gab es für den Service überhaupt keine Abdeckung.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter ConflictDetectionExternal
 */
class ConflictDetectionExternalTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2095;
    private const TEST_USER_EMAIL = 'planner-konflikt-9999@test.invalid';

    protected School $school;
    protected User $planner;
    protected ExamPeriod $examPeriod;
    protected ConflictDetectionService $service;
    protected TimeSlot $slot;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::create([
            'name' => 'Testperiode Konflikte (' . self::TEST_PERIOD_YEAR . ')',
            'year' => self::TEST_PERIOD_YEAR,
            'week_1_start' => self::TEST_PERIOD_YEAR . '-05-04',
            'week_1_end' => self::TEST_PERIOD_YEAR . '-05-08',
            'week_2_start' => self::TEST_PERIOD_YEAR . '-05-11',
            'week_2_end' => self::TEST_PERIOD_YEAR . '-05-15',
        ]);

        $this->planner = User::factory()->create([
            'email' => self::TEST_USER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);


        $this->service = app(ConflictDetectionService::class);
        $this->room = Room::create(['room_number' => 'K101']);
        $this->slot = TimeSlot::create([
            'exam_period_id' => $this->examPeriod->id,
            'date' => self::TEST_PERIOD_YEAR . '-05-04',
            'slot_number' => 1,
            'prep_start_time' => '08:00:00',
            'prep_end_time' => '08:30:00',
            'exam_start_time' => '08:30:00',
            'exam_end_time' => '09:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanupCentralTestData();

        parent::tearDown();
    }

    private function cleanupCentralTestData(): void
    {
        User::where('email', self::TEST_USER_EMAIL)->delete();
        ExamPeriod::where('year', self::TEST_PERIOD_YEAR)->delete();
    }

    /**
     * Ein externer Bewerber mit einer mündlichen Prüfung, verplant im Slot.
     */
    private function externerMitPruefung(string $nachname, string $fach, ?Teacher $pruefer = null): StudentExam
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => $nachname]);

        $fachId = Subject::where('name', $fach)->value('id');
        $this->assertNotNull($fachId, "Fach {$fach} fehlt im Katalog.");

        $pruefungsfach = ExternalExamSubject::create([
            'external_applicant_id' => $bewerber->id,
            'subject_id' => $fachId,
            'exam_part' => 'part2_oral',
            'subject_number' => 1,
        ]);

        return StudentExam::create([
            'external_exam_subject_id' => $pruefungsfach->id,
            'exam_type' => 'oral',
            'examiner_id' => $pruefer?->id,
        ]);
    }

    private function verplane(StudentExam $pruefung): ExamAssignment
    {
        return ExamAssignment::create([
            'student_exam_id' => $pruefung->id,
            'time_slot_id' => $this->slot->id,
            'exam_room_id' => $this->room->id,
            'exam_week' => 1,
        ]);
    }

    private function lehrkraft(string $kuerzel): Teacher
    {
        return Teacher::create([
            'shortcode' => $kuerzel,
            'firstname' => 'Test',
            'lastname' => 'Lehrkraft ' . $kuerzel,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fehlerVomTyp(string $typ): array
    {
        $ergebnis = $this->service->detectAll($this->examPeriod);

        return array_values(array_filter(
            $ergebnis['errors'],
            fn (array $k): bool => $k['type'] === $typ
        ));
    }

    // ── Der eigentliche Befund ─────────────────────────────────────────────

    /**
     * Zwei Prüfungen desselben externen Bewerbers im selben Zeitfenster sind
     * eine Doppelbelegung — und wurden bisher nicht gemeldet, weil die
     * Kandidatenabfrage externe Prüfungen gar nicht sah.
     */
    #[Test]
    public function zwei_pruefungen_desselben_externen_im_selben_slot_sind_ein_konflikt(): void
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Anna', 'last_name' => 'Extern']);

        foreach (['Geschichte', 'Physik'] as $nummer => $fach) {
            $pruefungsfach = ExternalExamSubject::create([
                'external_applicant_id' => $bewerber->id,
                'subject_id' => Subject::where('name', $fach)->value('id'),
                'exam_part' => 'part2_oral',
                'subject_number' => $nummer + 1,
            ]);

            $this->verplane(StudentExam::create([
                'external_exam_subject_id' => $pruefungsfach->id,
                'exam_type' => 'oral',
            ]));
        }

        $konflikte = $this->fehlerVomTyp('student_double_booking');

        $this->assertCount(1, $konflikte, 'Die Doppelbelegung eines externen Prüflings wurde nicht erkannt.');
        $this->assertStringContainsString('Extern', $konflikte[0]['message']);
        $this->assertSame('e' . $bewerber->id, $konflikte[0]['affected']['pruefling']);
    }

    /**
     * Zwei **verschiedene** externe Bewerber im selben Zeitfenster sind kein
     * Konflikt. Der Test sichert die Stelle, an der die Korrektur des ersten
     * Fehlers einen zweiten erzeugt hätte: `groupBy('studentExam.student_id')`
     * macht aus null den Schlüssel "", und beide wären in einer Gruppe
     * gelandet — als Doppelbelegung einer Person, die es nicht gibt.
     */
    #[Test]
    public function zwei_verschiedene_externe_im_selben_slot_sind_kein_konflikt(): void
    {
        $this->verplane($this->externerMitPruefung('Einer', 'Geschichte'));
        $this->verplane($this->externerMitPruefung('Zweiter', 'Physik'));

        $this->assertSame(
            [],
            $this->fehlerVomTyp('student_double_booking'),
            'Zwei verschiedene externe Prüflinge wurden als eine Person gezählt.'
        );
    }

    /**
     * Eine Lehrkraft, die im selben Zeitfenster zwei externe Bewerber prüft,
     * ist doppelt gebucht — auch das blieb unentdeckt.
     */
    #[Test]
    public function doppelt_gebuchte_lehrkraft_wird_auch_bei_externen_erkannt(): void
    {
        $pruefer = $this->lehrkraft('XT');

        $this->verplane($this->externerMitPruefung('Einer', 'Geschichte', $pruefer));
        $this->verplane($this->externerMitPruefung('Zweiter', 'Physik', $pruefer));

        $konflikte = $this->fehlerVomTyp('teacher_double_booking');

        $this->assertCount(1, $konflikte, 'Die Doppelbuchung der Lehrkraft wurde nicht erkannt.');
        $this->assertStringContainsString('XT', $konflikte[0]['message']);
    }

    /**
     * Zwei Prüfungen im selben Raum und Zeitfenster kollidieren, gleich wer
     * geprüft wird.
     */
    #[Test]
    public function raumkonflikt_wird_auch_bei_externen_erkannt(): void
    {
        $this->verplane($this->externerMitPruefung('Einer', 'Geschichte'));
        $this->verplane($this->externerMitPruefung('Zweiter', 'Physik'));

        $this->assertCount(
            1,
            $this->fehlerVomTyp('room_conflict'),
            'Der Raumkonflikt zwischen zwei externen Prüfungen wurde nicht erkannt.'
        );
    }

    /**
     * Und der Gegenprobe wegen: ein einzelner externer Prüfling, sauber
     * verplant, erzeugt keinen Konflikt.
     */
    #[Test]
    public function ein_sauber_verplanter_externer_erzeugt_keinen_konflikt(): void
    {
        $this->verplane($this->externerMitPruefung('Einzeln', 'Geschichte', $this->lehrkraft('EZ')));

        $ergebnis = $this->service->detectAll($this->examPeriod);

        $this->assertSame([], $ergebnis['errors']);
    }
}
