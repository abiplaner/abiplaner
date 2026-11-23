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
use App\Models\User;
use App\Models\ZusatzpruefAssignment;
use App\Models\ZusatzpruefDay;
use App\Models\ZusatzpruefDayRoom;
use App\Models\ZusatzpruefDayTimeslot;
use App\Models\ZusatzpruefExamScore;
use App\Models\Zusatzpruefung;
use App\Services\ExterneAbbruchService;
use App\Services\ExterneAbiturDataService;
use App\Services\VorgezogeneKolloquienService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Entscheidung 51 (01.09.2026) — der Abbruch als echter Zustand.
 *
 * Bis dahin waren D2 (§ 61 Abs. 4 S. 2) und E6 (§ 62 Abs. 1 S. 4) nur eine
 * Meldung in der Konfliktliste — AbbruchNachErstemTeilTest und
 * VorgezogeneKolloquienTest decken das ab. Was fehlte: eine Folge. Der
 * Koordinator konnte den Fall lesen, aber nichts damit tun außer die noch
 * offenen Kolloquiumstermine von Hand abzusagen.
 *
 * Zwei Entscheidungen Wilfrieds (01.09.2026) tragen diesen Test:
 * - Der Zustand wird bewusst gesetzt (ein Knopf), nicht automatisch aus der
 *   Rechnung abgeleitet — die Meldung bleibt eine Meldung, keine Vorentscheidung.
 * - Ist er gesetzt, sagt die Anwendung noch nicht gehaltene Prüfungstermine
 *   automatisch ab. Bereits benotete bleiben unangetastet — eine Note ist
 *   eine Tatsache, kein Termin.
 *
 * Ausführen:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter ExterneAbbruchServiceTest
 */
class ExterneAbbruchServiceTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2095;
    private const TEST_USER_EMAIL = 'planner-abbruch51-9999@test.invalid';

    /** Vier schriftliche Fächer; Mathematik ist immer eA (§ 61 Abs. 1 S. 5). */
    private const ERSTER_TEIL = ['Mathematik', 'Deutsch', 'Geschichte', 'Biologie'];

    private const ZWEITER_TEIL = ['Englisch', 'Geographie', 'Kunst', 'Musik'];

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
            'name' => 'Testperiode Abbruch51 (' . self::TEST_PERIOD_YEAR . ')',
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


        $this->room = Room::create(['room_number' => 'A51']);
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

    // ── Bewerber-Fixturen ────────────────────────────────────────────────

    /**
     * Ein Bewerber mit den vier Fächern des ersten Prüfungsteils (Punkte wie
     * übergeben, null = noch offen) und vier Kolloquiumsterminen im zweiten
     * Prüfungsteil — den Terminen, die ein Abbruch automatisch absagen soll.
     *
     * @param  list<int|null>  $ersterTeilPunkte
     * @return array{bewerber: ExternalApplicant, termine: list<ExamAssignment>, ersterTeil: list<StudentExam>}
     */
    private function bewerberMitOffenenTerminen(string $nachname, array $ersterTeilPunkte): array
    {
        $this->assertCount(4, $ersterTeilPunkte);

        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => $nachname]);

        $ersterTeil = [];

        foreach (self::ERSTER_TEIL as $nummer => $name) {
            $fachId = Subject::where('name', $name)->value('id');
            $pruefungsfach = ExternalExamSubject::create([
                'external_applicant_id' => $bewerber->id,
                'subject_id' => $fachId,
                'exam_part' => 'part1_written',
                'subject_number' => $nummer + 1,
            ]);
            $pruefung = StudentExam::create([
                'external_exam_subject_id' => $pruefungsfach->id,
                'exam_type' => 'written',
            ]);
            if ($ersterTeilPunkte[$nummer] !== null) {
                ZusatzpruefExamScore::create([
                    'student_exam_id' => $pruefung->id,
                    'exam_period_id' => $this->examPeriod->id,
                    'score' => $ersterTeilPunkte[$nummer],
                ]);
            }
            $ersterTeil[] = $pruefung;
        }

        $termine = [];

        foreach (self::ZWEITER_TEIL as $nummer => $name) {
            $fachId = Subject::where('name', $name)->value('id');
            $pruefungsfach = ExternalExamSubject::create([
                'external_applicant_id' => $bewerber->id,
                'subject_id' => $fachId,
                'exam_part' => 'part2_oral',
                'subject_number' => $nummer + 1,
            ]);
            $pruefung = StudentExam::create([
                'external_exam_subject_id' => $pruefungsfach->id,
                'exam_type' => 'oral',
            ]);

            $termine[] = ExamAssignment::create([
                'student_exam_id' => $pruefung->id,
                'exam_week' => 1,
                'exam_room_id' => $this->room->id,
                'status' => 'planned',
            ]);
        }

        return ['bewerber' => $bewerber, 'termine' => $termine, 'ersterTeil' => $ersterTeil];
    }

    /**
     * Eine platzierte Zusatzprüfung zu einer Prüfung des ersten
     * Prüfungsteils — mit Tag, Raum und Zeitslot, wie
     * `ZusatzpruefungController::place()` sie anlegt. `targetPoints=null`
     * heißt: noch nicht abgehalten, das ist der Fall, den ein Abbruch
     * deaktivieren soll.
     */
    private function zusatzpruefungZu(
        StudentExam $pruefung,
        ?int $targetPoints,
        bool $absent = false
    ): Zusatzpruefung {
        $day = ZusatzpruefDay::create([
            'exam_period_id' => $this->examPeriod->id,
            'date' => self::TEST_PERIOD_YEAR . '-05-06',
            'start_time' => '08:00',
        ]);
        $dayRoom = ZusatzpruefDayRoom::create([
            'zusatzpruef_day_id' => $day->id,
            'exam_room_id' => $this->room->id,
            'sort_order' => 0,
        ]);
        // Ohne konfigurierten Zeitslot stuft ZusatzpruefungController::index()
        // die Zuweisung als verwaisten "Timeslot-Orphan" ein und räumt sie
        // beim nächsten Aufruf selbst weg (zurück auf status=pending) — noch
        // bevor unser Fixture überhaupt getestet wird.
        ZusatzpruefDayTimeslot::create([
            'zusatzpruef_day_id' => $day->id,
            'start_time' => '09:00',
        ]);

        $zusatzpruefung = Zusatzpruefung::create([
            'exam_period_id' => $this->examPeriod->id,
            'student_exam_id' => $pruefung->id,
            'status' => 'placed',
            'absent' => $absent,
            'written_points' => 3,
            'target_points' => $targetPoints,
        ]);

        ZusatzpruefAssignment::create([
            'zusatzpruefung_id' => $zusatzpruefung->id,
            'zusatzpruef_day_id' => $day->id,
            'zusatzpruef_day_room_id' => $dayRoom->id,
            'start_time' => '09:00',
            'exam_duration_minutes' => 20,
            'prep_duration_minutes' => 30,
        ]);

        return $zusatzpruefung->fresh();
    }

    /**
     * Eine vorgezogene mündliche Prüfung (§ 61 Abs. 4) mit optionalem
     * Ergebnis — Grundlage für D2.
     */
    private function vorgezogeneZuweisung(
        ExternalApplicant $bewerber,
        string $fach,
        int $nummer,
        ?int $punkte
    ): ExamAssignment {
        $fachId = Subject::where('name', $fach)->value('id');
        $pruefungsfach = ExternalExamSubject::create([
            'external_applicant_id' => $bewerber->id,
            'subject_id' => $fachId,
            'exam_part' => 'part2_oral',
            'subject_number' => $nummer,
        ]);
        $pruefung = StudentExam::create([
            'external_exam_subject_id' => $pruefungsfach->id,
            'exam_type' => 'oral',
        ]);

        if ($punkte !== null) {
            ZusatzpruefExamScore::create([
                'student_exam_id' => $pruefung->id,
                'exam_period_id' => $this->examPeriod->id,
                'score' => $punkte,
            ]);
        }

        return ExamAssignment::create([
            'student_exam_id' => $pruefung->id,
            'exam_week' => ExamAssignment::WOCHE_VORGEZOGEN,
            'exam_room_id' => $this->room->id,
            'status' => 'planned',
            'free_date' => self::TEST_PERIOD_YEAR . '-04-10',
            'free_time_start' => '09:00',
            'free_time_end' => '09:30',
        ]);
    }

    // ── feststellen(): der Zustand und seine Folgen ─────────────────────

    #[Test]
    public function feststellen_setzt_den_zustand_und_sagt_offene_termine_ab(): void
    {
        ['bewerber' => $bewerber, 'termine' => $termine] =
            $this->bewerberMitOffenenTerminen('Zuwenig', [3, 3, 3, 3]);

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $bewerber->refresh();
        $this->assertTrue($bewerber->istAbgebrochen());
        $this->assertSame(ExterneAbbruchService::GRUND_E6, $bewerber->abbruch_grund);
        $this->assertNotNull($bewerber->abgebrochen_am);

        foreach ($termine as $termin) {
            $termin->refresh();
            $this->assertSame('cancelled', $termin->status);
            $this->assertSame('abbruch', $termin->cancelled_reason);
        }
    }

    /**
     * Eine Note ist eine Tatsache, kein Termin — sie wird nicht rückgängig
     * gemacht, nur weil der Bewerber danach abgebrochen wird.
     */
    #[Test]
    public function feststellen_laesst_bereits_benotete_termine_unangetastet(): void
    {
        ['bewerber' => $bewerber, 'termine' => $termine] =
            $this->bewerberMitOffenenTerminen('Teilbenotet', [3, 3, 3, 3]);

        ZusatzpruefExamScore::create([
            'student_exam_id' => $termine[0]->student_exam_id,
            'exam_period_id' => $this->examPeriod->id,
            'score' => 10,
        ]);

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $this->assertSame('planned', $termine[0]->fresh()->status);

        foreach (array_slice($termine, 1) as $termin) {
            $this->assertSame('cancelled', $termin->fresh()->status);
        }
    }

    #[Test]
    public function feststellen_ist_idempotent(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('Zweimal', [3, 3, 3, 3]);

        $service = app(ExterneAbbruchService::class);
        $service->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);
        $erstesDatum = $bewerber->fresh()->abgebrochen_am;

        $service->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $this->assertTrue($erstesDatum->equalTo($bewerber->fresh()->abgebrochen_am));
    }

    #[Test]
    public function rueckgaengig_setzt_den_zustand_zurueck_aber_termine_bleiben_abgesagt(): void
    {
        ['bewerber' => $bewerber, 'termine' => $termine] =
            $this->bewerberMitOffenenTerminen('Zurueck', [3, 3, 3, 3]);

        $service = app(ExterneAbbruchService::class);
        $service->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);
        $service->rueckgaengig($bewerber);

        $bewerber->refresh();
        $this->assertFalse($bewerber->istAbgebrochen());
        $this->assertNull($bewerber->abbruch_grund);
        $this->assertNull($bewerber->abgebrochen_am);

        foreach ($termine as $termin) {
            $this->assertSame(
                'cancelled',
                $termin->fresh()->status,
                'Bereits abgesagte Termine bleiben abgesagt.'
            );
        }
    }

    // ── Zusatzprüfungen: "abgebrochen ist abgebrochen" (Wilfried, 01.09.,
    //    nachmittags) ────────────────────────────────────────────────────

    #[Test]
    public function feststellen_deaktiviert_eine_noch_nicht_abgehaltene_zusatzpruefung(): void
    {
        ['bewerber' => $bewerber, 'ersterTeil' => $ersterTeil] =
            $this->bewerberMitOffenenTerminen('Zusatzoffen', [3, 3, 3, 3]);

        $zusatzpruefung = $this->zusatzpruefungZu($ersterTeil[0], targetPoints: null);

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $zusatzpruefung->refresh();
        $this->assertTrue($zusatzpruefung->absent);
        $this->assertNull($zusatzpruefung->assignment()->first());
    }

    /**
     * Eine gehaltene Zusatzprüfung ist wie eine Note: eine Tatsache, kein
     * Termin. Erkannt an `target_points` — steht sie, ist die Prüfung schon
     * abgehalten.
     */
    #[Test]
    public function feststellen_laesst_bereits_gehaltene_zusatzpruefungen_unangetastet(): void
    {
        ['bewerber' => $bewerber, 'ersterTeil' => $ersterTeil] =
            $this->bewerberMitOffenenTerminen('Zusatzgehalten', [3, 3, 3, 3]);

        $zusatzpruefung = $this->zusatzpruefungZu($ersterTeil[0], targetPoints: 8);

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $zusatzpruefung->refresh();
        $this->assertFalse($zusatzpruefung->absent);
        $this->assertNotNull($zusatzpruefung->assignment()->first());
    }

    #[Test]
    public function feststellen_deaktiviert_zusatzpruefungen_nur_einmal(): void
    {
        ['bewerber' => $bewerber, 'ersterTeil' => $ersterTeil] =
            $this->bewerberMitOffenenTerminen('Zusatzzweimal', [3, 3, 3, 3]);

        $this->zusatzpruefungZu($ersterTeil[0], targetPoints: null);

        $service = app(ExterneAbbruchService::class);
        $service->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);
        $service->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $this->assertSame(1, Zusatzpruefung::where('student_exam_id', $ersterTeil[0]->id)->count());
    }

    #[Test]
    public function rueckgaengig_laesst_deaktivierte_zusatzpruefungen_deaktiviert(): void
    {
        ['bewerber' => $bewerber, 'ersterTeil' => $ersterTeil] =
            $this->bewerberMitOffenenTerminen('Zusatzzurueck', [3, 3, 3, 3]);

        $zusatzpruefung = $this->zusatzpruefungZu($ersterTeil[0], targetPoints: null);

        $service = app(ExterneAbbruchService::class);
        $service->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);
        $service->rueckgaengig($bewerber);

        $this->assertTrue(
            $zusatzpruefung->fresh()->absent,
            'Eine deaktivierte Zusatzprüfung bleibt deaktiviert, wie ein abgesagter Termin.'
        );
    }

    /**
     * Der Planungspool zeigt eine deaktivierte Zusatzprüfung nicht mehr —
     * weder in der Seitenleiste (noch nicht platziert) noch im Tagesraster
     * (bereits platziert). Sie ist erledigt, kein offener Fall mehr.
     *
     * Geprüft über die View-Daten, nicht über den HTML-Text: Der Bewerbername
     * steht zusätzlich im Dropdown „neue Zusatzprüfung anlegen"
     * (`$prueflinge`), das unabhängig vom Abbruch jeden Bewerber des
     * Zeitraums führt — ein `assertDontSee` auf den Namen träfe dort falsch
     * an.
     */
    #[Test]
    public function eine_deaktivierte_zusatzpruefung_verschwindet_aus_dem_planungspool(): void
    {
        ['bewerber' => $bewerber, 'ersterTeil' => $ersterTeil] =
            $this->bewerberMitOffenenTerminen('ZusatzPool', [3, 3, 3, 3]);

        $zusatzpruefung = $this->zusatzpruefungZu($ersterTeil[0], targetPoints: null);

        // Vorher im Tagesraster, damit die Gegenprobe wirklich etwas findet.
        $vorher = $this->actingAs($this->planner)->get(route('zusatzpruefungen.index'));
        $vorher->assertOk();
        $this->assertTrue(
            collect($vorher->viewData('placed'))->flatten(1)->pluck('id')->contains($zusatzpruefung->id)
        );

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $nachher = $this->actingAs($this->planner)->get(route('zusatzpruefungen.index'));
        $nachher->assertOk();
        $this->assertFalse(
            collect($nachher->viewData('pending'))->pluck('id')->contains($zusatzpruefung->id)
        );
        $this->assertFalse(
            collect($nachher->viewData('placed'))->flatten(1)->pluck('id')->contains($zusatzpruefung->id)
        );
    }

    // ── Die Konfliktliste hört auf zu melden ────────────────────────────

    #[Test]
    public function eine_abgebrochene_e6_meldung_verschwindet_aus_der_konfliktliste(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('Gemeldet', [3, 3, 3, 3]);

        $service = app(ExterneAbiturDataService::class);
        $this->assertCount(1, $service->abbruchgruendeErsterTeil($this->examPeriod));

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $this->assertSame([], $service->abbruchgruendeErsterTeil($this->examPeriod));
    }

    #[Test]
    public function eine_abgebrochene_d2_meldung_verschwindet_aus_der_konfliktliste(): void
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => 'Nullpunkte']);

        $this->vorgezogeneZuweisung($bewerber, 'Englisch', 1, 0);

        $service = app(VorgezogeneKolloquienService::class);
        $this->assertCount(1, $service->abbruchgruende($this->examPeriod));

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_D2);

        $this->assertSame([], $service->abbruchgruende($this->examPeriod));
    }

    // ── Die beiden Erkenner, die den Knopf steuern ──────────────────────

    #[Test]
    public function abbruch_nach_erstem_teil_fuer_erkennt_den_gescheiterten_fall(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('Erkannt', [3, 3, 3, 3]);

        $this->assertTrue(
            app(ExterneAbiturDataService::class)->abbruchNachErstemTeilFuer($bewerber, $this->examPeriod)
        );
    }

    #[Test]
    public function abbruch_nach_erstem_teil_fuer_meldet_einen_bestandenen_ersten_teil_nicht(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('Bestanden51', [15, 15, 15, 15]);

        $this->assertFalse(
            app(ExterneAbiturDataService::class)->abbruchNachErstemTeilFuer($bewerber, $this->examPeriod)
        );
    }

    #[Test]
    public function abbruch_fuer_erkennt_die_null_punkte_in_der_vorgezogenen_pruefung(): void
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => 'Vorgezogennull']);

        $this->vorgezogeneZuweisung($bewerber, 'Englisch', 1, 0);

        $this->assertTrue(
            app(VorgezogeneKolloquienService::class)->abbruchFuer($bewerber, $this->examPeriod)
        );
    }

    #[Test]
    public function abbruch_fuer_meldet_einen_punkt_nicht(): void
    {
        $bewerber = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['first_name' => 'Vorname', 'last_name' => 'Vorgezogeneinpunkt']);

        $this->vorgezogeneZuweisung($bewerber, 'Englisch', 1, 1);

        $this->assertFalse(
            app(VorgezogeneKolloquienService::class)->abbruchFuer($bewerber, $this->examPeriod)
        );
    }

    // ── Der Weg des Koordinators: Routen und Masken ─────────────────────

    #[Test]
    public function die_feststellen_route_setzt_den_zustand_und_leitet_um(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('Route51', [3, 3, 3, 3]);

        $this->actingAs($this->planner)
            ->post(route('external-applicants.abbruch.feststellen', $bewerber), ['grund' => 'e6'])
            ->assertRedirect(route('external-applicants.edit', $bewerber))
            ->assertSessionHas('success');

        $this->assertTrue($bewerber->fresh()->istAbgebrochen());
    }

    #[Test]
    public function die_rueckgaengig_route_setzt_den_zustand_zurueck(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('RouteZurueck', [3, 3, 3, 3]);

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $this->actingAs($this->planner)
            ->delete(route('external-applicants.abbruch.rueckgaengig', $bewerber))
            ->assertRedirect(route('external-applicants.edit', $bewerber))
            ->assertSessionHas('success');

        $this->assertFalse($bewerber->fresh()->istAbgebrochen());
    }

    #[Test]
    public function ein_unbekannter_grund_wird_abgelehnt(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('Unbekannt51', [3, 3, 3, 3]);

        $this->actingAs($this->planner)
            ->post(route('external-applicants.abbruch.feststellen', $bewerber), ['grund' => 'x'])
            ->assertSessionHasErrors('grund');

        $this->assertFalse($bewerber->fresh()->istAbgebrochen());
    }

    #[Test]
    public function die_bearbeiten_seite_zeigt_die_feststellen_schaltflaeche(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('MaskeKnopf', [3, 3, 3, 3]);

        $this->actingAs($this->planner)
            ->get(route('external-applicants.edit', $bewerber))
            ->assertOk()
            ->assertSee('Abbruch feststellen')
            ->assertSee('§ 62 Abs. 1 S. 4', false);
    }

    #[Test]
    public function die_bearbeiten_seite_zeigt_die_abgebrochen_meldung(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('MaskeRot', [3, 3, 3, 3]);

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $this->actingAs($this->planner)
            ->get(route('external-applicants.edit', $bewerber))
            ->assertOk()
            ->assertSee('Feststellung zurücknehmen')
            ->assertDontSee('Abbruch feststellen');
    }

    #[Test]
    public function die_liste_zeigt_das_abgebrochen_badge(): void
    {
        ['bewerber' => $bewerber] =
            $this->bewerberMitOffenenTerminen('ListeBadge', [3, 3, 3, 3]);

        app(ExterneAbbruchService::class)->feststellen($bewerber, ExterneAbbruchService::GRUND_E6);

        $this->actingAs($this->planner)
            ->get(route('external-applicants.index', ['year' => self::TEST_PERIOD_YEAR]))
            ->assertOk()
            ->assertSee('⛔ Abgebrochen', false);
    }
}
