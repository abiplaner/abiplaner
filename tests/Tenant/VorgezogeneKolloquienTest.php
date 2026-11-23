<?php

namespace Tests\Tenant;

use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WrittenExamSlot;
use App\Models\WrittenExamYearDate;
use App\Models\ZusatzpruefExamScore;
use App\Services\VorgezogeneKolloquienService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Vorgezogene mündliche Prüfungen externer Bewerber — § 61 Abs. 4 BayGSO.
 *
 * Der Wortlaut:
 *
 *   (4) 1Auf Wunsch der Bewerberin oder des Bewerbers können zwei der vier
 *   mündlichen Prüfungen im Zeitraum zwei Wochen vor Beginn der schriftlichen
 *   Abiturprüfungen stattfinden. 2Sollten in diesen beiden Prüfungen die
 *   Bedingungen von § 62 Abs. 2 bereits nicht erfüllt werden, so wird die
 *   Prüfung abgebrochen. 3Die Ergebnisse werden ansonsten erst nach Abschluss
 *   der schriftlichen Prüfungen mitgeteilt.
 *
 * Geprüft wird über die echten Routen, nicht am Service vorbei — der Weg des
 * Koordinators ist der Weg, der tragen muss. Die Lehre vom 29.08.2026:
 * ConflictDetectionExternalTest deckte den Service ab und übersah trotzdem,
 * dass der Controller für Externe abbrach.
 *
 * Ausführen:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter VorgezogeneKolloquien
 */
class VorgezogeneKolloquienTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2094;
    private const TEST_USER_EMAIL = 'planner-vorgezogen-9999@test.invalid';

    /** Erster schriftlicher Prüfungstag des Testjahrgangs. */
    private const ERSTER_SCHRIFTLICHER = self::TEST_PERIOD_YEAR . '-04-21';

    /** Erster und letzter Tag des Fensters, das daraus folgen muss. */
    private const FENSTER_START = self::TEST_PERIOD_YEAR . '-04-07';
    private const FENSTER_ENDE = self::TEST_PERIOD_YEAR . '-04-20';

    protected School $school;
    protected User $planner;
    protected ExamPeriod $examPeriod;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::create([
            'name' => 'Testperiode Vorgezogen (' . self::TEST_PERIOD_YEAR . ')',
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


        $this->room = Room::create(['room_number' => 'V101']);
    }

    protected function tearDown(): void
    {
        $this->cleanupCentralTestData();

        parent::tearDown();
    }

    private function cleanupCentralTestData(): void
    {
        User::where('email', self::TEST_USER_EMAIL)->delete();
        WrittenExamYearDate::where('exam_year', self::TEST_PERIOD_YEAR)->delete();
        ExamPeriod::where('year', self::TEST_PERIOD_YEAR)->delete();
    }

    /**
     * Die schriftlichen Jahrestermine, aus denen das Fenster abgeleitet wird.
     *
     * Absichtlich zwei Termine, der spätere zuerst angelegt: Das Fenster hängt
     * am *frühesten* Tag, nicht am zuletzt eingetragenen.
     */
    private function schriftlicheTermineAnlegen(): void
    {
        // Zwei verschiedene Slots: (exam_year, written_exam_slot_id) ist
        // eindeutig — ein Jahrgang trägt je Slot genau ein Datum.
        $slotIds = WrittenExamSlot::query()->orderBy('id')->limit(2)->pluck('id')->all();
        $this->assertCount(2, $slotIds, 'Ohne zwei schriftliche Slots ist der Test nicht aussagekräftig.');

        WrittenExamYearDate::create([
            'exam_year' => self::TEST_PERIOD_YEAR,
            'written_exam_slot_id' => $slotIds[0],
            'exam_date' => self::TEST_PERIOD_YEAR . '-04-28',
        ]);

        WrittenExamYearDate::create([
            'exam_year' => self::TEST_PERIOD_YEAR,
            'written_exam_slot_id' => $slotIds[1],
            'exam_date' => self::ERSTER_SCHRIFTLICHER,
        ]);
    }

    private function lehrkraft(string $kuerzel): Teacher
    {
        return Teacher::firstOrCreate(
            ['shortcode' => $kuerzel],
            ['firstname' => 'Test', 'lastname' => 'Lehrkraft ' . $kuerzel]
        );
    }

    /**
     * Ein externer Bewerber mit einer mündlichen Prüfung.
     */
    private function externePruefung(string $nachname, string $fach = 'Mathematik'): StudentExam
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => $nachname]);

        return $this->weiteresFach($bewerber->id, $fach, 1, 'VZ' . substr($nachname, 0, 2));
    }

    /**
     * Ein weiteres mündliches Prüfungsfach desselben Bewerbers.
     */
    private function weiteresFach(int $bewerberId, string $fach, int $nummer, string $kuerzel): StudentExam
    {
        $fachId = Subject::where('name', $fach)->value('id');
        $this->assertNotNull($fachId, "Fach {$fach} fehlt im Katalog.");

        $pruefungsfach = ExternalExamSubject::create([
            'external_applicant_id' => $bewerberId,
            'subject_id' => $fachId,
            'exam_part' => 'part2_oral',
            'subject_number' => $nummer,
        ]);

        return StudentExam::create([
            'external_exam_subject_id' => $pruefungsfach->id,
            'exam_type' => 'oral',
            'examiner_id' => $this->lehrkraft($kuerzel)->id,
        ]);
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function vorziehen(StudentExam $pruefung, string $datum, string $von = '09:00', string $bis = '09:30')
    {
        return $this->actingAs($this->planner)->postJson(
            route('planning.vorgezogen.store', ['examPeriod' => $this->examPeriod->id]),
            [
                'student_exam_id' => $pruefung->id,
                'free_date' => $datum,
                'free_time_start' => $von,
                'free_time_end' => $bis,
                'exam_room_id' => $this->room->id,
            ]
        );
    }

    // ── D1: der Zeitraum ───────────────────────────────────────────────────

    /**
     * Das Fenster wird abgeleitet, nicht gepflegt: zwei Wochen, die dem
     * ersten schriftlichen Prüfungstag unmittelbar vorausgehen.
     */
    #[Test]
    public function das_fenster_endet_am_vortag_der_ersten_schriftlichen_pruefung(): void
    {
        $this->schriftlicheTermineAnlegen();

        $fenster = app(VorgezogeneKolloquienService::class)->fenster($this->examPeriod);

        $this->assertNotNull($fenster);
        $this->assertSame(self::FENSTER_START, $fenster['start']->format('Y-m-d'));
        $this->assertSame(self::FENSTER_ENDE, $fenster['ende']->format('Y-m-d'));
    }

    #[Test]
    public function eine_vorgezogene_pruefung_laesst_sich_eintragen(): void
    {
        $this->schriftlicheTermineAnlegen();

        $pruefung = $this->externePruefung('Vorziehbar');

        $this->vorziehen($pruefung, self::FENSTER_ENDE)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('exam_assignments', [
            'student_exam_id' => $pruefung->id,
            'exam_week' => ExamAssignment::WOCHE_VORGEZOGEN,
            'time_slot_id' => null,
        ]);
    }

    /**
     * Der erste schriftliche Tag selbst gehört nicht mehr dazu: „vor Beginn".
     */
    #[Test]
    public function der_tag_der_ersten_schriftlichen_pruefung_ist_zu_spaet(): void
    {
        $this->schriftlicheTermineAnlegen();

        $antwort = $this->vorziehen($this->externePruefung('Zuspaet'), self::ERSTER_SCHRIFTLICHER);

        $antwort->assertStatus(422);
        $this->assertStringContainsString('§ 61 Abs. 4 S. 1', (string) $antwort->json('message'));
    }

    #[Test]
    public function ein_tag_vor_dem_fenster_ist_zu_frueh(): void
    {
        $this->schriftlicheTermineAnlegen();

        $zuFrueh = \Carbon\Carbon::parse(self::FENSTER_START)->subDay()->format('Y-m-d');

        $this->vorziehen($this->externePruefung('Zufrueh'), $zuFrueh)->assertStatus(422);
    }

    /**
     * Gegenprobe zur Ableitung: Ohne schriftliche Jahrestermine ist das
     * Fenster nicht bestimmbar — dann wird abgewiesen, nicht durchgewinkt.
     */
    #[Test]
    public function ohne_schriftliche_jahrestermine_wird_abgewiesen(): void
    {
        $antwort = $this->vorziehen($this->externePruefung('Ohnetermin'), self::FENSTER_ENDE);

        $antwort->assertStatus(422);
        $this->assertNull(
            app(VorgezogeneKolloquienService::class)->fenster($this->examPeriod),
            'Ohne Jahrestermine darf es kein Fenster geben.'
        );
    }

    // ── D1: die beiden Regeln ──────────────────────────────────────────────

    #[Test]
    public function hoechstens_zwei_je_bewerber(): void
    {
        $this->schriftlicheTermineAnlegen();

        $erste = $this->externePruefung('Dreifach');
        $bewerberId = $erste->externalExamSubject->external_applicant_id;
        $zweite = $this->weiteresFach($bewerberId, 'Geschichte', 2, 'VZD2');
        $dritte = $this->weiteresFach($bewerberId, 'Englisch', 3, 'VZD3');

        $this->vorziehen($erste, self::FENSTER_ENDE)->assertOk();
        $this->vorziehen($zweite, self::FENSTER_START)->assertOk();

        $antwort = $this->vorziehen($dritte, self::TEST_PERIOD_YEAR . '-04-14');

        $antwort->assertStatus(422);
        $this->assertStringContainsString('höchstens zwei', (string) $antwort->json('message'));
        $this->assertSame(
            2,
            ExamAssignment::where('exam_week', ExamAssignment::WOCHE_VORGEZOGEN)->count()
        );
    }

    /**
     * C9 (§ 61 Abs. 3 S. 9) gilt weiter — und zwar auch hier, wo die Termine
     * keinen Zeitslot haben. Die Prüfung lief bis zum 31.08.2026 allein über
     * `timeSlot`; zwei vorgezogene Termine am selben Tag wären ihr entgangen.
     */
    #[Test]
    public function zwei_vorgezogene_am_selben_tag_werden_abgelehnt(): void
    {
        $this->schriftlicheTermineAnlegen();

        $erste = $this->externePruefung('Selbertag');
        $bewerberId = $erste->externalExamSubject->external_applicant_id;
        $zweite = $this->weiteresFach($bewerberId, 'Geschichte', 2, 'VZS2');

        $this->vorziehen($erste, self::FENSTER_ENDE, '09:00', '09:30')->assertOk();

        $antwort = $this->vorziehen($zweite, self::FENSTER_ENDE, '14:00', '14:30');

        $antwort->assertStatus(422);
        $this->assertStringContainsString('§ 61 Abs. 3 S. 9', (string) $antwort->json('message'));
    }

    /**
     * Gegenprobe: Für einen regulären Schüler gibt es § 61 Abs. 4 nicht.
     */
    #[Test]
    public function ein_regulaerer_schueler_kann_nicht_vorziehen(): void
    {
        $this->schriftlicheTermineAnlegen();

        $schueler = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id' => 'TEST-VZ-REG',
            'firstname' => 'Rea',
            'lastname' => 'Gulaer',
            'class_name' => '13a',
        ]);

        $pruefung = StudentExam::create([
            'student_id' => $schueler->id,
            'exam_type' => 'oral',
            'examiner_id' => $this->lehrkraft('VZRG')->id,
        ]);

        $antwort = $this->vorziehen($pruefung, self::FENSTER_ENDE);

        $antwort->assertStatus(422);
        $this->assertStringContainsString('externe Bewerber', (string) $antwort->json('message'));
    }

    /**
     * Gegenprobe zur Fachregel: Die vier Fächer des ersten Prüfungsteils
     * werden schriftlich geprüft und lassen sich nicht vorziehen.
     */
    #[Test]
    public function ein_fach_des_ersten_pruefungsteils_laesst_sich_nicht_vorziehen(): void
    {
        $this->schriftlicheTermineAnlegen();

        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => 'Erstteil']);

        $pruefungsfach = ExternalExamSubject::create([
            'external_applicant_id' => $bewerber->id,
            'subject_id' => Subject::where('name', 'Mathematik')->value('id'),
            'exam_part' => 'part1_written',
            'subject_number' => 1,
        ]);

        $pruefung = StudentExam::create([
            'external_exam_subject_id' => $pruefungsfach->id,
            'exam_type' => 'oral',
            'examiner_id' => $this->lehrkraft('VZE1')->id,
        ]);

        $antwort = $this->vorziehen($pruefung, self::FENSTER_ENDE);

        $antwort->assertStatus(422);
        $this->assertStringContainsString('zweiten Prüfungsteils', (string) $antwort->json('message'));
    }

    // ── D2: der Abbruchgrund ───────────────────────────────────────────────

    /**
     * § 61 Abs. 4 S. 2 mit § 62 Abs. 2 Nr. 1: ein Fach mit 0 Punkten.
     */
    #[Test]
    public function null_punkte_melden_den_abbruchgrund(): void
    {
        $this->schriftlicheTermineAnlegen();

        $pruefung = $this->externePruefung('Nullpunkte');
        $this->vorziehen($pruefung, self::FENSTER_ENDE)->assertOk();

        $this->punkteEintragen($pruefung, 0);

        $gruende = app(VorgezogeneKolloquienService::class)->abbruchgruende($this->examPeriod);

        $this->assertCount(1, $gruende);
        $this->assertStringContainsString('§ 61 Abs. 4 S. 2', $gruende[0]);
        $this->assertStringContainsString('Nullpunkte', $gruende[0]);

        // Und sie muss auch ankommen: `getConflicts()` füllte `warnings`,
        // gelesen hat sie in der Maske aber niemand. Die Meldung war bis zum
        // Abend des 31.08.2026 unsichtbar, und dieser Test prüfte nur den
        // Dienst. **Ein Feld, das niemand liest, ist keine Meldung.**
        $this->actingAs($this->planner)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertSee('§ 61 Abs. 4 S. 2', false);
    }

    /**
     * Gegenprobe, und zugleich die Rechnung hinter der Auslegung: Mit einem
     * Punkt ist § 62 Abs. 2 noch erreichbar — die beiden ausstehenden
     * Prüfungen tragen zusammen bis zu 120 Punkte bei, die Grenze liegt bei
     * 80. Nur die Null ist endgültig.
     */
    #[Test]
    public function ein_punkt_meldet_keinen_abbruchgrund(): void
    {
        $this->schriftlicheTermineAnlegen();

        $pruefung = $this->externePruefung('Einpunkt');
        $this->vorziehen($pruefung, self::FENSTER_ENDE)->assertOk();

        $this->punkteEintragen($pruefung, 1);

        $this->assertSame(
            [],
            app(VorgezogeneKolloquienService::class)->abbruchgruende($this->examPeriod)
        );
    }

    /**
     * Und die zweite Gegenprobe: Eine Null in einer *nicht* vorgezogenen
     * Prüfung ist kein Fall des § 61 Abs. 4 S. 2. Die Norm knüpft an „diese
     * beiden Prüfungen" an.
     */
    #[Test]
    public function null_punkte_in_einer_regulaeren_pruefung_melden_nichts(): void
    {
        $this->schriftlicheTermineAnlegen();

        $pruefung = $this->externePruefung('Regulaernull');
        $this->punkteEintragen($pruefung, 0);

        $this->assertSame(
            [],
            app(VorgezogeneKolloquienService::class)->abbruchgruende($this->examPeriod),
            'Ohne vorgezogenen Termin greift § 61 Abs. 4 S. 2 nicht.'
        );
    }

    private function punkteEintragen(StudentExam $pruefung, int $punkte): void
    {
        ZusatzpruefExamScore::create([
            'student_exam_id' => $pruefung->id,
            'exam_period_id' => $this->examPeriod->id,
            'score' => $punkte,
        ]);
    }

    // ── Nebenbefund aus derselben Familie ──────────────────────────────────

    /**
     * Termine ohne Zeitslot gibt es seit dem 31.08.2026 in zwei Ausprägungen:
     * Nachtermin nach Erkrankung (exam_week 0) und vorgezogene Prüfung
     * (exam_week 3). Beim Bauen der zweiten fiel an der ersten ein Fehler auf,
     * den kein Test abdeckte:
     *
     * `exam_assignments.exam_room_id` ist NOT NULL, der Bearbeiten-Dialog bot
     * aber „– kein Raum –" an, und `updateOutsideAssignment()` schrieb dafür
     * `null`. Gemessen am 31.08.2026 gegen die laufende Datenbank:
     * SQLSTATE[23000] 1048 „Column 'exam_room_id' cannot be null" — ein 500er.
     * Jetzt bleibt der bisherige Raum stehen.
     */
    #[Test]
    public function ein_ausserhalb_termin_ohne_raumauswahl_behaelt_seinen_raum(): void
    {
        $pruefung = $this->externePruefung('Ohneraum');

        $termin = ExamAssignment::create([
            'student_exam_id' => $pruefung->id,
            'time_slot_id' => null,
            'exam_room_id' => $this->room->id,
            'exam_week' => ExamAssignment::WOCHE_AUSSERHALB,
            'status' => 'planned',
            'free_date' => self::TEST_PERIOD_YEAR . '-06-01',
            'free_time_start' => '09:00',
            'free_time_end' => '09:30',
        ]);

        $this->actingAs($this->planner)->patchJson(
            route('planning.update-outside', [
                'examPeriod' => $this->examPeriod->id,
                'assignment' => $termin->id,
            ]),
            [
                'free_date' => self::TEST_PERIOD_YEAR . '-06-02',
                'free_time_start' => '10:00',
                'free_time_end' => '10:30',
                'exam_room_id' => null,
            ]
        )->assertOk()->assertJson(['success' => true]);

        $this->assertSame($this->room->id, $termin->fresh()->exam_room_id);
    }

    // ── Die Maske ──────────────────────────────────────────────────────────

    /**
     * Die Planungsmaske muss den Block rendern — eine Blade-Änderung, die
     * erst im Browser auffällt, ist eine, die niemand bemerkt.
     */
    #[Test]
    public function die_planungsmaske_zeigt_den_block(): void
    {
        $this->schriftlicheTermineAnlegen();

        $pruefung = $this->externePruefung('Sichtbar');
        $this->vorziehen($pruefung, self::FENSTER_ENDE)->assertOk();

        $this->actingAs($this->planner)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertSee('Vorgezogene mündliche Prüfungen', false)
            ->assertSee('07.04.' . self::TEST_PERIOD_YEAR . ' bis 20.04.' . self::TEST_PERIOD_YEAR, false);
    }
}
