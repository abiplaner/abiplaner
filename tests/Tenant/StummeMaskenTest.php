<?php

namespace Tests\Tenant;

use App\Models\CalendarEvent;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Zwei Masken, die stumm ausfielen — beide gefunden bei der Bestandsaufnahme
 * zu school_id (30.08.2026), beide mit derselben Wurzel: Eine Abfrage bzw.
 * eine Rechtepruefung greift auf eine Spalte zu, die es in der
 * Mandantendatenbank gar nicht gibt.
 *
 * In einer Datenbank je Schule ist `school_id` ueberfluessig. Die Spalte wurde
 * deshalb aus den meisten Mandantentabellen entfernt — die Zugriffe darauf
 * blieben aber stehen. Wo sie in einer WHERE-Klausel landeten, gab es einen
 * 500er; wo sie in einer Rechtepruefung landeten, gab es einen 403, den
 * niemand erklaeren konnte.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter StummeMasken
 */
class StummeMaskenTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2095;
    private const PLANNER_EMAIL = 'planner-stumm-9999@test.invalid';

    protected School $school;
    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aufraeumen();

        $this->school = $this->createTestSchool();

        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

    }

    protected function tearDown(): void
    {
        $this->aufraeumen();

        parent::tearDown();
    }

    private function aufraeumen(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();
        ExamPeriod::where('year', self::TEST_PERIOD_YEAR)->delete();
    }

    // ── Die Maske „Neue LK-Wahl anlegen" war ein 500er ─────────────────────

    /**
     * LkWahlController::create() filterte die Schueler ueber `school_id` UND
     * ueber `jahrgang`. Beide Spalten gibt es in `students` nicht; die
     * Klassenstufe heisst `grade`. Die Abfrage brach mit
     * „Unknown column 'school_id' in 'WHERE'" ab.
     *
     * Aufgefallen ist es nie, weil die Wahlen bisher importiert und nicht von
     * Hand angelegt wurden.
     */
    #[Test]
    public function die_maske_neue_lk_wahl_laedt(): void
    {
        $this->actingAs($this->planner)
            ->get(route('lk-wahlen.create'))
            ->assertOk();
    }

    /**
     * Und sie fuehrt die Schueler auch tatsaechlich. Ohne diese Zusicherung
     * waere der Test darueber auch dann gruen, wenn die Liste leer bliebe —
     * ein `whereIn('grade', [11, 12, 13])` mit Zahlen statt Zeichenketten
     * liefert gegen ein enum('11','12','13') genau das.
     */
    #[Test]
    public function die_maske_fuehrt_die_schueler_der_stufen_11_bis_13(): void
    {
        $examPeriod = ExamPeriod::create([
            'name' => 'Testperiode Stumm (' . self::TEST_PERIOD_YEAR . ')',
            'year' => self::TEST_PERIOD_YEAR,
            'week_1_start' => self::TEST_PERIOD_YEAR . '-05-04',
            'week_1_end' => self::TEST_PERIOD_YEAR . '-05-08',
            'week_2_start' => self::TEST_PERIOD_YEAR . '-05-11',
            'week_2_end' => self::TEST_PERIOD_YEAR . '-05-15',
        ]);

        Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id' => 'STUMM-1',
            'grade' => '12',
            'firstname' => 'Lea',
            'lastname' => 'Stummschuelerin',
        ]);

        $antwort = $this->actingAs($this->planner)->get(route('lk-wahlen.create'));

        $antwort->assertOk();
        $this->assertTrue(
            $antwort->viewData('students')->contains(
                fn (Student $s): bool => $s->lastname === 'Stummschuelerin'
            ),
            'Die Auswahlliste muss die Schueler der Stufen 11 bis 13 fuehren.'
        );
    }

    // ── Planer kamen an ihre eigenen Kalendertermine nicht heran ───────────

    /**
     * CalendarEventPolicy::update() verlangte
     * `$calendarEvent->school_id === $user->school_id` und zusaetzlich
     * `school_id !== null`. Die Spalte gibt es in `calendar_events` nicht, der
     * Wert war also immer null — die Bedingung damit immer falsch. Planer
     * bekamen auf ihre eigenen Termine einen 403.
     *
     * Entscheidung Wilfrieds (30.08.2026): Schuleigene Termine darf der Planer
     * aendern, globale nur der Super-Admin. Globale liegen zentral in
     * `global_calendar_events`, in dieser Tabelle steht nur Schuleigenes.
     */
    #[Test]
    public function ein_planer_darf_einen_schuleigenen_termin_aendern(): void
    {
        $termin = CalendarEvent::create([
            'title' => 'Notenkonferenz',
            'event_date' => self::TEST_PERIOD_YEAR . '-05-04',
            'event_type' => 'other',
            'created_by' => $this->planner->id,
        ]);

        $this->assertTrue(
            $this->planner->can('update', $termin),
            'Ein Planer muss die Termine seiner Schule aendern duerfen.'
        );
        $this->assertTrue(
            $this->planner->can('delete', $termin),
            'Loeschen folgt derselben Regel wie Aendern.'
        );
    }

    /**
     * Gegenprobe: Die Regel haengt weiterhin an der Rolle — es duerfen nicht
     * einfach alle. Ohne diese Probe waere der Test darueber auch dann gruen,
     * wenn update() schlicht `true` zurueckgaebe.
     *
     * Die Rolle wird hier NICHT gespeichert: `users.role` ist ein
     * enum('super_admin','planner'), eine dritte Rolle laesst die Datenbank
     * gar nicht zu (der erste Anlauf dieses Tests scheiterte genau daran).
     * Die Policy arbeitet auf dem Modell, nicht auf der Zeile — die Pruefung
     * im Speicher trifft also genau das, was sie treffen soll, und der
     * `false`-Zweig ist die Vorsorge fuer eine Rolle, die es heute noch nicht
     * gibt.
     */
    #[Test]
    public function eine_andere_rolle_darf_einen_termin_nicht_aendern(): void
    {
        $termin = CalendarEvent::create([
            'title' => 'Notenkonferenz',
            'event_date' => self::TEST_PERIOD_YEAR . '-05-04',
            'event_type' => 'other',
            'created_by' => $this->planner->id,
        ]);

        $fremdeRolle = new User(['role' => 'lehrkraft']);
        $policy = new \App\Policies\CalendarEventPolicy();

        $this->assertFalse($policy->update($fremdeRolle, $termin));
        $this->assertFalse($policy->delete($fremdeRolle, $termin));
        $this->assertTrue(
            $policy->view($fremdeRolle, $termin),
            'Sehen darf jeder angemeldete Nutzer.'
        );
    }
}
