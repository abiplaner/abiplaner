<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\Room;
use App\Models\RoomSeatingTemplate;
use App\Models\School;
use App\Models\SeatingTemplate;
use App\Models\SeatingTemplateSeat;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamSeatAssignment;
use App\Models\WrittenExamSlot;
use App\Models\WrittenExamYearDate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Der externe Bewerber im schriftlichen Sitzplan.
 *
 * Vor dem 27.08.2026 konnte er dort nur als `guest_name` sitzen — als freier
 * Namensstring, den die Exporte an einem Komma zerlegen. Der String hing an
 * nichts: kein Bezug zum Bewerber, also **kein Nachteilsausgleich**, obwohl
 * der externe Bewerber seit dem 26.08. einen haben kann. Aufgefallen wäre das
 * am Prüfungstag, an der Uhr der Aufsicht.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter SitzplanExternerBewerber
 */
class SitzplanExternerBewerberTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2094;
    private const TEST_USER_EMAIL = 'planner-sitzplan-9999@test.invalid';

    protected School $school;
    protected User $planner;
    protected ExamPeriod $examPeriod;
    protected Room $room;
    protected SeatingTemplate $template;
    protected WrittenExamYearDate $yearDate;
    protected WrittenExamAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::create([
            'name' => 'Testperiode Sitzplan (' . self::TEST_PERIOD_YEAR . ')',
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


        $this->template = SeatingTemplate::create(['name' => 'Sitzplan-Test', 'rows' => 1, 'columns' => 3]);
        foreach ([1, 2, 3] as $spalte) {
            SeatingTemplateSeat::create([
                'template_id' => $this->template->id,
                'row_number' => 1,
                'column_number' => $spalte,
                'seat_type' => 'available',
            ]);
        }

        $this->room = Room::create(['room_number' => 'S101', 'room_type' => 'exam', 'is_active' => true]);
        RoomSeatingTemplate::create([
            'room_id' => $this->room->id,
            'variant' => RoomSeatingTemplate::VARIANT_STANDARD,
            'seating_template_id' => $this->template->id,
        ]);

        $slot = WrittenExamSlot::create([
            'name' => 'Deutsch',
            'requirement_level' => 'grundlegend',
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'sort_order' => 1,
        ]);

        $this->yearDate = WrittenExamYearDate::create([
            'exam_year' => self::TEST_PERIOD_YEAR,
            'written_exam_slot_id' => $slot->id,
            'exam_date' => self::TEST_PERIOD_YEAR . '-05-04',
        ]);

        $lehrkraft = Teacher::create(['shortcode' => 'SPL', 'firstname' => 'Test', 'lastname' => 'Sitzplan']);
        $kurs = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id' => Subject::query()->value('id'),
            'course_code' => 'sp1',
            'teacher_id' => $lehrkraft->id,
            'level' => 'grundlegend',
        ]);

        $this->assignment = WrittenExamAssignment::create([
            'written_exam_year_date_id' => $this->yearDate->id,
            'course_id' => $kurs->id,
            'room_id' => $this->room->id,
            'is_hoerverstaendnis' => false,
        ]);
    }

    protected function tearDown(): void
    {

        $this->cleanupCentralTestData();

        parent::tearDown();
    }

    /**
     * Raeumt die zentralen Testdaten ab — auch den Jahrestermin und seinen
     * Slot.
     *
     * Die beiden fehlten hier, und das blieb nicht folgenlos: setUp() legt sie
     * bei JEDER Testmethode neu an, und zwar in der echten zentralen
     * Datenbank. Am 31.08.2026 standen dort 769 fachlose Slots samt
     * Jahresterminen zum Jahr 2094 — angewachsen seit dem 27.08. mit jedem
     * Lauf der Tenant-Suite.
     *
     * Sichtbar wurden sie an anderer Stelle: Ein Slot ohne Fach laesst
     * WrittenExamYearDate::$subject auf null laufen, und der Dateiname jedes
     * PDF-Exports dieses Termins verlor sein Fachkuerzel.
     *
     * Erst der Termin, dann der Slot — der Termin zeigt auf ihn.
     */
    private function cleanupCentralTestData(): void
    {
        User::where('email', self::TEST_USER_EMAIL)->delete();
        ExamPeriod::where('year', self::TEST_PERIOD_YEAR)->delete();

        $slotIds = WrittenExamYearDate::where('exam_year', self::TEST_PERIOD_YEAR)
            ->pluck('written_exam_slot_id')
            ->all();

        WrittenExamYearDate::where('exam_year', self::TEST_PERIOD_YEAR)->delete();

        if ($slotIds !== []) {
            WrittenExamSlot::whereIn('id', $slotIds)->delete();
        }
    }

    private function platz(int $spalte): SeatingTemplateSeat
    {
        return SeatingTemplateSeat::where('template_id', $this->template->id)
            ->where('column_number', $spalte)
            ->firstOrFail();
    }

    private function bewerber(string $nachname, ?int $nta = null): ExternalApplicant
    {
        return ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create([
                'first_name' => 'Vorname',
                'last_name' => $nachname,
                'disability_compensation_percent' => $nta,
            ]);
    }

    /**
     * Gibt dem Bewerber eine schriftliche Prüfung am Termin dieses Raums —
     * das ist die Bedingung, unter der er in der Maske zur Auswahl steht.
     */
    private function mitSchriftlicherPruefung(ExternalApplicant $bewerber): StudentExam
    {
        $fach = ExternalExamSubject::create([
            'external_applicant_id' => $bewerber->id,
            'subject_id' => Subject::query()->value('id'),
            'exam_part' => 'part1_written',
            'subject_number' => 1,
        ]);

        return StudentExam::create([
            'external_exam_subject_id' => $fach->id,
            'exam_type' => 'written',
            'written_exam_year_date_id' => $this->yearDate->id,
        ]);
    }

    private function assignSeatRequest(array $daten): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->planner)->postJson(
            route('written-exams.assignments.assign-seat', [
                'writtenExamYearDate' => $this->yearDate->id,
                'writtenExamAssignment' => $this->assignment->id,
            ]),
            $daten
        );
    }

    // ------------------------------------------------------------------
    // Das Modell
    // ------------------------------------------------------------------

    #[Test]
    public function ein_externer_sitzplatz_kennt_den_bewerber_und_seinen_nachteilsausgleich(): void
    {
        $bewerber = $this->bewerber('Externus', 25);

        $sitz = WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $this->assignment->id,
            'external_applicant_id' => $bewerber->id,
            'seat_id' => $this->platz(1)->id,
        ]);

        $sitz = $sitz->fresh();

        $this->assertTrue($sitz->istExterner());
        $this->assertFalse($sitz->istGast());
        $this->assertSame('Externus, Vorname', $sitz->anzeigename());
        $this->assertSame('Externus', $sitz->nachname());
        $this->assertSame('Vorname', $sitz->vorname());
        $this->assertSame(25, $sitz->nachteilsausgleichProzent());
    }

    #[Test]
    public function ein_gastschueler_bleibt_ein_name_ohne_nachteilsausgleich(): void
    {
        $sitz = WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $this->assignment->id,
            'guest_name' => 'Gastmann, Gerd',
            'seat_id' => $this->platz(1)->id,
        ])->fresh();

        $this->assertTrue($sitz->istGast());
        $this->assertFalse($sitz->istExterner());
        $this->assertSame('Gastmann', $sitz->nachname());
        $this->assertSame('Gerd', $sitz->vorname());
        $this->assertSame(0, $sitz->nachteilsausgleichProzent());
        $this->assertNull($sitz->pruefling());
    }

    #[Test]
    public function ein_gastname_ohne_komma_gilt_ganz_als_nachname(): void
    {
        $sitz = WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $this->assignment->id,
            'guest_name' => 'Einname',
            'seat_id' => $this->platz(1)->id,
        ])->fresh();

        $this->assertSame('Einname', $sitz->nachname());
        $this->assertSame('', $sitz->vorname());
    }

    #[Test]
    public function das_anzeigeobjekt_hat_fuer_alle_drei_faelle_dieselbe_form(): void
    {
        $schueler = Student::create([
            'firstname' => 'Sina',
            'lastname' => 'Schuelerin',
            'external_id' => 'SP-0001',
            'exam_period_id' => $this->examPeriod->id,
        ]);
        $bewerber = $this->bewerber('Externus', 25);

        $sitzSchueler = WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $this->assignment->id,
            'student_id' => $schueler->id,
            'seat_id' => $this->platz(1)->id,
        ])->fresh();
        $sitzExtern = WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $this->assignment->id,
            'external_applicant_id' => $bewerber->id,
            'seat_id' => $this->platz(2)->id,
        ])->fresh();
        $sitzGast = WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $this->assignment->id,
            'guest_name' => 'Gastmann, Gerd',
            'seat_id' => $this->platz(3)->id,
        ])->fresh();

        foreach ([$sitzSchueler, $sitzExtern, $sitzGast] as $sitz) {
            $objekt = $sitz->anzeigeObjekt();
            $this->assertNotSame('', (string) $objekt->lastname, 'Jeder Platz muss einen Nachnamen anzeigen.');
            $this->assertNotSame('', (string) $objekt->firstname, 'Jeder Platz muss einen Vornamen anzeigen.');
        }

        $this->assertSame('Schuelerin', $sitzSchueler->anzeigeObjekt()->lastname);
        $this->assertSame('Externus', $sitzExtern->anzeigeObjekt()->lastname);
        $this->assertTrue($sitzExtern->anzeigeObjekt()->is_extern);
        $this->assertSame(25, $sitzExtern->anzeigeObjekt()->disability_compensation_percent);
        $this->assertTrue($sitzGast->anzeigeObjekt()->is_guest);
        $this->assertNull($sitzGast->anzeigeObjekt()->disability_compensation_percent);
    }

    #[Test]
    public function mit_dem_bewerber_faellt_sein_sitzplatz(): void
    {
        $bewerber = $this->bewerber('Externus');

        WrittenExamSeatAssignment::create([
            'written_exam_assignment_id' => $this->assignment->id,
            'external_applicant_id' => $bewerber->id,
            'seat_id' => $this->platz(1)->id,
        ]);

        $bewerber->delete();

        $this->assertSame(
            0,
            WrittenExamSeatAssignment::where('external_applicant_id', $bewerber->id)->count(),
            'Ein soft gelöschter Bewerber darf keinen besetzten Platz hinterlassen.'
        );
    }

    // ------------------------------------------------------------------
    // Die Zuweisung
    // ------------------------------------------------------------------

    #[Test]
    public function ein_externer_bewerber_laesst_sich_auf_einen_platz_setzen(): void
    {
        $bewerber = $this->bewerber('Externus', 50);

        $this->assignSeatRequest([
            'external_applicant_id' => $bewerber->id,
            'seat_id' => $this->platz(1)->id,
        ])->assertOk()->assertJson(['success' => true]);

        $sitz = WrittenExamSeatAssignment::where('external_applicant_id', $bewerber->id)->first();

        $this->assertNotNull($sitz);
        $this->assertNull($sitz->student_id);
        $this->assertNull($sitz->guest_name, 'Ein externer Bewerber ist kein Gastname.');
        $this->assertSame(50, $sitz->nachteilsausgleichProzent());
    }

    #[Test]
    public function derselbe_bewerber_bekommt_im_raum_keinen_zweiten_platz(): void
    {
        $bewerber = $this->bewerber('Externus');

        $this->assignSeatRequest([
            'external_applicant_id' => $bewerber->id,
            'seat_id' => $this->platz(1)->id,
        ])->assertOk();

        $this->assignSeatRequest([
            'external_applicant_id' => $bewerber->id,
            'seat_id' => $this->platz(2)->id,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(1, WrittenExamSeatAssignment::where('external_applicant_id', $bewerber->id)->count());
    }

    #[Test]
    public function ein_unbekannter_bewerber_wird_abgewiesen(): void
    {
        $this->assignSeatRequest([
            'external_applicant_id' => 999999,
            'seat_id' => $this->platz(1)->id,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(0, WrittenExamSeatAssignment::count());
    }

    #[Test]
    public function ohne_pruefling_passiert_nichts(): void
    {
        $this->assignSeatRequest(['seat_id' => $this->platz(1)->id])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(0, WrittenExamSeatAssignment::count());
    }

    // ------------------------------------------------------------------
    // Die Auswahlliste
    // ------------------------------------------------------------------

    #[Test]
    public function zur_auswahl_steht_nur_wer_an_diesem_tag_schriftlich_prueft(): void
    {
        $mitPruefung = $this->bewerber('Mitpruefung');
        $this->mitSchriftlicherPruefung($mitPruefung);

        $ohnePruefung = $this->bewerber('Ohnepruefung');

        $antwort = $this->actingAs($this->planner)->get(
            route('written-exams.room-seating-plan', [
                'writtenExamYearDate' => $this->yearDate->id,
                'room' => $this->room->id,
            ])
        );

        $antwort->assertOk();
        $antwort->assertSee('Mitpruefung, Vorname');
        $antwort->assertDontSee('Ohnepruefung, Vorname');
    }
}
