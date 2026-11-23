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
use PHPUnit\Framework\Attributes\Test;

/**
 * Einen externen Bewerber im Kolloquium verplanen — über den Controller.
 *
 * Der ConflictDetectionExternalTest deckt seit dem 27.08. die Konflikterkennung
 * für Externe ab. Der Weg, den der Oberstufenkoordinator tatsächlich geht —
 * eine Prüfung in eine Zelle ziehen — lief aber über keinen einzigen Test:
 * die Datei ruft nirgends einen Controller auf.
 *
 * Deshalb blieb unbemerkt, dass PlanningController::assignExam() für externe
 * Prüflinge abbrach:
 *
 *   checkStudentWeekConflict(): Argument #1 ($studentId) must be of type int,
 *   null given
 *
 * `student_id` ist bei Externen null. Dieselbe Wurzel wie im
 * ConflictDetectionService, nur eine Etage höher und dort nicht mitbehoben.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter PlanungExternerBewerber
 */
class PlanungExternerBewerberTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2096;
    private const TEST_USER_EMAIL = 'planner-extern-9998@test.invalid';

    protected School $school;
    protected User $planner;
    protected ExamPeriod $examPeriod;
    protected Room $room;
    protected TimeSlot $slotWoche1;
    protected TimeSlot $zweiterSlotWoche1;
    protected TimeSlot $dritterSlotWoche1;
    protected TimeSlot $slotWoche2;
    /** Zweiter Slot am selben Tag wie $slotWoche1 — fuer die Tagesregel C9. */
    protected TimeSlot $zweiterSlotSelberTag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::create([
            'name' => 'Testperiode Extern (' . self::TEST_PERIOD_YEAR . ')',
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


        $this->room = Room::create(['room_number' => 'X101']);
        $this->slotWoche1 = $this->slot(self::TEST_PERIOD_YEAR . '-05-04', 1, '08:30:00', '09:00:00');
        $this->zweiterSlotWoche1 = $this->slot(self::TEST_PERIOD_YEAR . '-05-06', 2, '10:00:00', '10:30:00');
        $this->dritterSlotWoche1 = $this->slot(self::TEST_PERIOD_YEAR . '-05-07', 3, '11:00:00', '11:30:00');
        $this->slotWoche2 = $this->slot(self::TEST_PERIOD_YEAR . '-05-11', 1, '08:30:00', '09:00:00');
        $this->zweiterSlotSelberTag = $this->slot(self::TEST_PERIOD_YEAR . '-05-04', 4, '14:00:00', '14:30:00');
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

    private function slot(string $datum, int $nummer, string $von, string $bis): TimeSlot
    {
        return TimeSlot::create([
            'exam_period_id' => $this->examPeriod->id,
            'date' => $datum,
            'slot_number' => $nummer,
            'prep_start_time' => '08:00:00',
            'prep_end_time' => '08:30:00',
            'exam_start_time' => $von,
            'exam_end_time' => $bis,
        ]);
    }

    /**
     * Ein externer Bewerber mit einer mündlichen Prüfung — noch unverplant.
     */
    private function externePruefung(string $nachname, string $fach = 'Mathematik'): StudentExam
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
            'examiner_id' => $this->lehrkraft('EX' . substr($nachname, 0, 1))->id,
        ]);
    }

    /**
     * Ein weiteres Pruefungsfach desselben externen Bewerbers.
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

    private function lehrkraft(string $kuerzel): Teacher
    {
        return Teacher::firstOrCreate(
            ['shortcode' => $kuerzel],
            ['firstname' => 'Test', 'lastname' => 'Lehrkraft ' . $kuerzel]
        );
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function zuweisen(StudentExam $pruefung, TimeSlot $slot)
    {
        return $this->actingAs($this->planner)->postJson(route('planning.assign'), [
            'student_exam_id' => $pruefung->id,
            'time_slot_id' => $slot->id,
            'exam_room_id' => $this->room->id,
        ]);
    }

    // ── Der gemeldete Fehler ───────────────────────────────────────────────

    #[Test]
    public function ein_externer_bewerber_laesst_sich_zuweisen(): void
    {
        $pruefung = $this->externePruefung('Ohnestudent');

        $antwort = $this->zuweisen($pruefung, $this->slotWoche1);

        $antwort->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('exam_assignments', [
            'student_exam_id' => $pruefung->id,
            'time_slot_id' => $this->slotWoche1->id,
            'exam_week' => 1,
        ]);
    }

    // ── Die Wochenregel gilt fuer Externe nicht ────────────────────────────

    /**
     * Externe Bewerber legen nach BayGSO alle Faecher ab — typischerweise vier
     * muendliche Pruefungen. Die Regel "hoechstens eine Pruefung pro Woche"
     * stammt aus der Kolloquiumsplanung regulaerer Schueler mit genau zwei
     * Pruefungen und waere fuer Externe nicht erfuellbar.
     */
    #[Test]
    public function mehrere_pruefungen_eines_externen_duerfen_in_dieselbe_woche(): void
    {
        $ersteP = $this->externePruefung('Vierfach');
        $bewerberId = $ersteP->externalExamSubject->external_applicant_id;

        $zweiteP = $this->weiteresFach($bewerberId, 'Geschichte', 2, 'EXV2');
        $dritteP = $this->weiteresFach($bewerberId, 'Englisch', 3, 'EXV3');

        $this->zuweisen($ersteP, $this->slotWoche1)->assertOk();
        $this->zuweisen($zweiteP, $this->zweiterSlotWoche1)->assertOk();
        $this->zuweisen($dritteP, $this->dritterSlotWoche1)->assertOk();

        $this->assertSame(
            3,
            ExamAssignment::whereIn('student_exam_id', [$ersteP->id, $zweiteP->id, $dritteP->id])->count(),
            'Alle drei Pruefungen des Bewerbers muessen in Woche 1 liegen duerfen.'
        );
    }

    /**
     * Gegenprobe: Fuer regulaere Schueler muss die Regel weiter greifen —
     * der Ausstieg fuer Externe darf sie nicht generell abschalten.
     */
    #[Test]
    public function fuer_regulaere_schueler_gilt_die_wochenregel_weiter(): void
    {
        $schueler = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id' => 'TEST-REG-1',
            'firstname' => 'Rea',
            'lastname' => 'Gulaer',
            'class_name' => '12a',
        ]);

        $ersteP = StudentExam::create([
            'student_id' => $schueler->id,
            'exam_type' => 'oral',
            'examiner_id' => $this->lehrkraft('REG1')->id,
        ]);
        $zweiteP = StudentExam::create([
            'student_id' => $schueler->id,
            'exam_type' => 'oral',
            'examiner_id' => $this->lehrkraft('REG2')->id,
        ]);

        $this->zuweisen($ersteP, $this->slotWoche1)->assertOk();

        $antwort = $this->zuweisen($zweiteP, $this->zweiterSlotWoche1);

        $antwort->assertStatus(422);
        $this->assertStringContainsString(
            'bereits eine Prüfung in Woche 1',
            (string) $antwort->json('message')
        );
    }

    // ── Die Tagesregel gilt nur fuer Externe (C9) ──────────────────────────

    /**
     * § 61 Abs. 3 S. 9: "Sofern die Belange der pruefenden Schule es erlauben,
     * findet nur eine muendliche Pruefung an einem Tag statt." Entscheidung
     * Wilfrieds vom 30.08.2026: hart durchsetzen.
     *
     * Die Gegenprobe steckt im Test darueber: Dieselben drei Pruefungen an
     * drei *verschiedenen* Tagen derselben Woche gehen weiterhin durch. Ohne
     * ihn koennte diese Sperre alles blockieren und der Test waere trotzdem
     * gruen.
     */
    #[Test]
    public function eine_zweite_pruefung_am_selben_tag_wird_fuer_externe_abgelehnt(): void
    {
        $ersteP = $this->externePruefung('Tagesregel');
        $bewerberId = $ersteP->externalExamSubject->external_applicant_id;
        $zweiteP = $this->weiteresFach($bewerberId, 'Geschichte', 2, 'EXT2');

        $this->zuweisen($ersteP, $this->slotWoche1)->assertOk();

        $antwort = $this->zuweisen($zweiteP, $this->zweiterSlotSelberTag);

        $antwort->assertStatus(422);
        $this->assertStringContainsString(
            '§ 61 Abs. 3 S. 9',
            (string) $antwort->json('message')
        );
        $this->assertDatabaseMissing('exam_assignments', ['student_exam_id' => $zweiteP->id]);
    }

    /**
     * Die Sperre gilt je Bewerber, nicht je Tag: Zwei verschiedene externe
     * Bewerber duerfen sehr wohl am selben Tag geprueft werden. Ohne diese
     * Gegenprobe wuerde eine zu grob gefasste Abfrage — etwa eine ueber alle
     * externen Pruefungen des Tages — nicht auffallen.
     */
    #[Test]
    public function zwei_verschiedene_bewerber_duerfen_am_selben_tag_geprueft_werden(): void
    {
        $einer = $this->externePruefung('Tagesteiler');
        $anderer = $this->externePruefung('Tagesnachbar');

        $this->zuweisen($einer, $this->slotWoche1)->assertOk();
        $this->zuweisen($anderer, $this->zweiterSlotSelberTag)->assertOk()->assertJson(['success' => true]);
    }

    /**
     * Die andere Haelfte der Tagesregel: Was schon dasteht, muss auffallen.
     * Eine Haeufung aus einer Auto-Planung von vor dieser Regel oder aus einem
     * Import kommt an der Zuweisungsmaske vorbei — deshalb prueft der
     * ConflictDetectionService den Bestand.
     *
     * Wichtig ist die Gruppierung nach *Tag*: detectStudentConflicts gruppiert
     * nach Zeitslot und haette zwei Pruefungen desselben Bewerbers um 08:30 und
     * 14:00 nie gesehen.
     */
    #[Test]
    public function eine_bestehende_haeufung_am_selben_tag_wird_als_konflikt_gemeldet(): void
    {
        $ersteP = $this->externePruefung('Bestandshaeufung');
        $bewerberId = $ersteP->externalExamSubject->external_applicant_id;
        $zweiteP = $this->weiteresFach($bewerberId, 'Geschichte', 2, 'BST2');

        // Bewusst an der Maske vorbei — genau so entsteht der Bestand.
        foreach ([[$ersteP, $this->slotWoche1], [$zweiteP, $this->zweiterSlotSelberTag]] as [$pruefung, $slot]) {
            ExamAssignment::create([
                'student_exam_id' => $pruefung->id,
                'time_slot_id' => $slot->id,
                'exam_room_id' => $this->room->id,
                'exam_week' => 1,
                'status' => 'planned',
            ]);
        }

        $konflikte = app(\App\Services\ConflictDetectionService::class)
            ->detectAll($this->examPeriod);

        $typen = array_column($konflikte['errors'], 'type');
        $this->assertContains(
            'external_same_day',
            $typen,
            'Zwei muendliche Pruefungen desselben Bewerbers an einem Tag muessen als Fehler erscheinen.'
        );
    }

    /**
     * Gegenprobe: An verschiedenen Tagen ist dieselbe Konstellation richtig
     * und darf nichts melden.
     */
    #[Test]
    public function zwei_pruefungen_an_verschiedenen_tagen_sind_kein_konflikt(): void
    {
        $ersteP = $this->externePruefung('Sauberverteilt');
        $bewerberId = $ersteP->externalExamSubject->external_applicant_id;
        $zweiteP = $this->weiteresFach($bewerberId, 'Geschichte', 2, 'SBR2');

        $this->zuweisen($ersteP, $this->slotWoche1)->assertOk();
        $this->zuweisen($zweiteP, $this->zweiterSlotWoche1)->assertOk();

        $konflikte = app(\App\Services\ConflictDetectionService::class)
            ->detectAll($this->examPeriod);

        $this->assertNotContains('external_same_day', array_column($konflikte['errors'], 'type'));
    }

    // ── Die Soll-Zahl steht in der Faecherwahl ─────────────────────────────

    /**
     * Nicht "vier" als feste Zahl: Fuer einen Ersatzschueler nach § 64 Abs. 2
     * werden zwei der vier Faecher des zweiten Teils aus 13/II eingebracht
     * statt geprueft. Eine Konstante haette bei ihm dauerhaft eine Luecke
     * gemeldet, die keine ist.
     */
    #[Test]
    public function die_soll_zahl_zaehlt_eingebrachte_faecher_nicht_mit(): void
    {
        $pruefung = $this->externePruefung('Ersatzschueler');
        $bewerberId = $pruefung->externalExamSubject->external_applicant_id;

        $this->weiteresFach($bewerberId, 'Geschichte', 2, 'ERS2');

        // Zwei weitere Faecher des zweiten Teils, aber eingebracht statt geprueft
        foreach ([['Englisch', 3], ['Biologie', 4]] as [$fach, $nummer]) {
            ExternalExamSubject::create([
                'external_applicant_id' => $bewerberId,
                'subject_id' => Subject::where('name', $fach)->value('id'),
                'exam_part' => 'part2_oral',
                'subject_number' => $nummer,
                'ergebnis_quelle' => 'einbringung',
            ]);
        }

        $bewerber = ExternalApplicant::with('oralSubjects')->findOrFail($bewerberId);

        $this->assertSame(4, $bewerber->oralSubjects->count(), 'Vier Faecher im zweiten Teil.');
        $this->assertSame(
            2,
            $bewerber->sollMuendlichePruefungen(),
            'Nur die beiden geprueften Faecher zaehlen als muendliche Pruefung.'
        );
    }

    // ── Pruefungen ohne Pruefer ────────────────────────────────────────────

    /**
     * examiner_id ist nullable, und die Liste der ungeplanten Pruefungen
     * fuehrt solche Faelle als "Fach (ohne Pruefer)". Der Versuch endete in
     * einem Typfehler mitten in checkTeacherTimeSlotConflict(). Jetzt kommt
     * eine Meldung, die sagt, was zu tun ist.
     */
    #[Test]
    public function eine_pruefung_ohne_pruefer_wird_mit_klarer_meldung_abgelehnt(): void
    {
        $pruefung = $this->externePruefung('Ohnepruefer');
        $pruefung->examiner_id = null;
        $pruefung->save();

        $antwort = $this->zuweisen($pruefung, $this->slotWoche1);

        $antwort->assertStatus(422);
        $this->assertStringContainsString(
            'kein Prüfer eingetragen',
            (string) $antwort->json('message')
        );
        $this->assertDatabaseMissing('exam_assignments', ['student_exam_id' => $pruefung->id]);
    }

    /**
     * Falls doch einmal eine Zuweisung ohne Pruefer im Bestand steht — aus
     * Altdaten oder der Auto-Planung — darf die Maske daran nicht scheitern:
     * _week_grid gab den Pruefer ungeschuetzt aus. Der Absturz waere erst
     * beim naechsten Aufruf der Planung sichtbar geworden, nicht beim
     * Zuweisen.
     */
    #[Test]
    public function die_planungsmaske_laedt_auch_mit_einer_zuweisung_ohne_pruefer(): void
    {
        $pruefung = $this->externePruefung('Maskenpruefung');
        $pruefung->examiner_id = null;
        $pruefung->save();

        ExamAssignment::create([
            'student_exam_id' => $pruefung->id,
            'time_slot_id' => $this->slotWoche1->id,
            'exam_room_id' => $this->room->id,
            'exam_week' => 1,
            'status' => 'planned',
        ]);

        $this->actingAs($this->planner)
            ->get(route('planning.index', ['exam_period_id' => $this->examPeriod->id]))
            ->assertOk();
    }

    // ── Die Kennzahlen der Planungsmaske ──────────────────────────────────

    /**
     * Die Kachel "Pruefungen je Pruefling" fragte ueber die Tabelle `students`
     * ab — dort steht kein externer Bewerber. Ein Bewerber mit einer statt
     * zwei angelegten muendlichen Pruefungen fiel deshalb nicht auf; die
     * Kachel meldete "✓ Korrekt".
     */
    #[Test]
    public function ein_bewerber_mit_zu_wenigen_pruefungen_faellt_in_der_maske_auf(): void
    {
        // Zwei Faecher im zweiten Teil, aber nur fuer eines eine Pruefung
        $pruefung = $this->externePruefung('Luecke');
        $bewerberId = $pruefung->externalExamSubject->external_applicant_id;

        ExternalExamSubject::create([
            'external_applicant_id' => $bewerberId,
            'subject_id' => Subject::where('name', 'Geschichte')->value('id'),
            'exam_part' => 'part2_oral',
            'subject_number' => 2,
        ]);

        $antwort = $this->actingAs($this->planner)
            ->get(route('planning.index', ['exam_period_id' => $this->examPeriod->id]));

        $antwort->assertOk();

        // Ueber die View-Daten und nicht ueber den Seitentext: Der Name des
        // Bewerbers steht ohnehin in der Liste der ungeplanten Pruefungen, ein
        // assertSee() wuerde ihn dort finden und nichts beweisen.
        $zeile = collect($antwort->viewData('studentsWithWrongCount'))
            ->firstWhere('lastname', 'Luecke');

        $this->assertNotNull($zeile, 'Der Bewerber mit fehlender Pruefung muss gemeldet werden.');
        $this->assertTrue($zeile->extern);
        $this->assertSame(1, (int) $zeile->exam_count);
        $this->assertSame(2, (int) $zeile->soll, 'Das Soll kommt aus der Faecherwahl, nicht aus einer Konstante.');
    }

    /**
     * Gegenprobe: Ist die Faecherwahl vollstaendig verplant, darf die Kachel
     * nicht meckern — sonst waere der Test darueber auch dann gruen, wenn
     * jeder externe Bewerber gemeldet wuerde.
     */
    #[Test]
    public function ein_vollstaendiger_bewerber_wird_nicht_gemeldet(): void
    {
        $pruefung = $this->externePruefung('Vollstaendig');
        $bewerberId = $pruefung->externalExamSubject->external_applicant_id;
        $this->weiteresFach($bewerberId, 'Geschichte', 2, 'VLL2');

        $antwort = $this->actingAs($this->planner)
            ->get(route('planning.index', ['exam_period_id' => $this->examPeriod->id]));

        $antwort->assertOk();

        $this->assertNull(
            collect($antwort->viewData('studentsWithWrongCount'))->firstWhere('lastname', 'Vollstaendig'),
            'Ein vollstaendig angelegter Bewerber darf nicht in der Warnung stehen.'
        );
    }

    // ── Der Rueckweg ───────────────────────────────────────────────────────

    #[Test]
    public function die_zuweisung_eines_externen_laesst_sich_entfernen(): void
    {
        $pruefung = $this->externePruefung('Wiederweg');
        $this->zuweisen($pruefung, $this->slotWoche1)->assertOk();

        /** @var ExamAssignment $zuweisung */
        $zuweisung = ExamAssignment::where('student_exam_id', $pruefung->id)->firstOrFail();

        // Der Protokolleintrag griff hier frueher auf ->student->full_name zu.
        $antwort = $this->actingAs($this->planner)->deleteJson(
            route('planning.remove', [
                'examPeriod' => $this->examPeriod->id,
                'assignment' => $zuweisung->id,
            ])
        );

        $antwort->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('exam_assignments', ['id' => $zuweisung->id]);
    }
}
