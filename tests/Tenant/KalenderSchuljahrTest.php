<?php

namespace Tests\Tenant;

use App\Models\CalendarEvent;
use App\Models\GlobalCalendarEvent;
use App\Models\School;
use App\Models\User;
use App\Support\SchoolYear;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * Der Terminkalender zeigt nur ein Schuljahr (21.09.2026).
 *
 * Gemeldet: Unter /calendar standen die Termine aller Jahre in einer Liste
 * (bei mehr als 50 Terminen auf mehreren Seiten). Jetzt zeigt die Liste
 * das laufende Schuljahr (1. August bis 31. Juli); ueber den Umschalter
 * ueber dem Filter waehlt man ein anderes.
 *
 * Die Tests filtern auf den Typ "exam_oral" (Schultermine) bzw. "holiday"
 * (globale Termine), damit die echten globalen Termine der lokalen zentralen
 * Datenbank die erste Seite nicht fuellen und die Testtermine verdraengen.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter KalenderSchuljahr
 */
class KalenderSchuljahrTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'planner-kalender-9999@test.invalid';
    private const PREFIX = 'KalTest-';

    protected School $school;
    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();
        $this->loescheGlobale();

        $this->school = $this->createTestSchool();

        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->loescheGlobale();
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    /** Globale Termine liegen in der zentralen Datenbank und bleiben sonst stehen. */
    private function loescheGlobale(): void
    {
        GlobalCalendarEvent::where('title', 'like', self::PREFIX . '%')->delete();
    }

    private function termin(string $titel, string $datum, ?string $ende = null): CalendarEvent
    {
        return CalendarEvent::create([
            'title' => self::PREFIX . $titel,
            'event_date' => $datum,
            'end_date' => $ende,
            'event_type' => 'exam_oral',
            'color' => '#3B82F6',
        ]);
    }

    /** Die Kalenderseite als Planer; $query wird um den Typfilter ergaenzt. */
    private function kalender(array $query = [], string $typ = 'exam_oral')
    {
        return $this->actingAs($this->planner)
            ->get(route('calendar.index', ['event_type' => $typ] + $query));
    }

    // ── Vorgabe und Umschalten ─────────────────────────────────────────────

    #[Test]
    public function ohne_angabe_zeigt_der_kalender_nur_das_laufende_schuljahr(): void
    {
        $this->termin('Vorjahr', '2026-05-10');
        $this->termin('Laufend', '2026-10-05');
        $this->termin('Naechstes', '2027-09-15');

        $this->kalender()
            ->assertOk()
            ->assertSee(self::PREFIX . 'Laufend')
            ->assertDontSee(self::PREFIX . 'Vorjahr')
            ->assertDontSee(self::PREFIX . 'Naechstes');
    }

    #[Test]
    public function der_umschalter_zeigt_ein_anderes_schuljahr(): void
    {
        $this->termin('Vorjahr', '2026-05-10');
        $this->termin('Laufend', '2026-10-05');

        $this->kalender(['school_year' => '2025/26'])
            ->assertOk()
            ->assertSee(self::PREFIX . 'Vorjahr')
            ->assertDontSee(self::PREFIX . 'Laufend');
    }

    #[Test]
    public function globale_termine_folgen_demselben_schuljahr(): void
    {
        GlobalCalendarEvent::create([
            'title' => self::PREFIX . 'Global-Vorjahr',
            'event_date' => '2026-03-02',
            'event_type' => 'holiday',
            'color' => '#EF4444',
        ]);
        GlobalCalendarEvent::create([
            'title' => self::PREFIX . 'Global-Laufend',
            'event_date' => '2026-08-02',
            'event_type' => 'holiday',
            'color' => '#EF4444',
        ]);

        $this->kalender([], 'holiday')
            ->assertSee(self::PREFIX . 'Global-Laufend')
            ->assertDontSee(self::PREFIX . 'Global-Vorjahr');

        $this->kalender(['school_year' => '2025/26'], 'holiday')
            ->assertSee(self::PREFIX . 'Global-Vorjahr')
            ->assertDontSee(self::PREFIX . 'Global-Laufend');
    }

    #[Test]
    public function ein_unbrauchbares_schuljahr_gilt_als_keine_angabe(): void
    {
        $this->termin('Vorjahr', '2026-05-10');
        $this->termin('Laufend', '2026-10-05');

        foreach (['irgendwas', '2026/28', '26/27', ''] as $unbrauchbar) {
            $this->kalender(['school_year' => $unbrauchbar])
                ->assertOk()
                ->assertSee(self::PREFIX . 'Laufend')
                ->assertDontSee(self::PREFIX . 'Vorjahr');
        }

        // Ein Feld statt einer Zeichenkette (?school_year[]=x) darf nicht abstuerzen.
        $this->kalender(['school_year' => ['x']])->assertOk()->assertSee(self::PREFIX . 'Laufend');
    }

    // ── Grenzen des Schuljahres ────────────────────────────────────────────

    #[Test]
    public function das_schuljahr_reicht_vom_ersten_august_bis_zum_einunddreissigsten_juli(): void
    {
        $this->termin('Ende-Alt', '2026-07-31');
        $this->termin('Anfang-Neu', '2026-08-01');
        $this->termin('Ende-Neu', '2027-07-31');
        $this->termin('Anfang-Folge', '2027-08-01');

        $this->kalender(['school_year' => '2026/27'])
            ->assertSee(self::PREFIX . 'Anfang-Neu')
            ->assertSee(self::PREFIX . 'Ende-Neu')
            ->assertDontSee(self::PREFIX . 'Ende-Alt')
            ->assertDontSee(self::PREFIX . 'Anfang-Folge');

        $this->kalender(['school_year' => '2025/26'])
            ->assertSee(self::PREFIX . 'Ende-Alt')
            ->assertDontSee(self::PREFIX . 'Anfang-Neu');
    }

    #[Test]
    public function ein_mehrtaegiger_termin_ueber_den_schuljahreswechsel_steht_in_beiden(): void
    {
        // Sommerferien: beginnen im alten, enden im neuen Schuljahr.
        $this->termin('Sommerferien', '2026-07-30', '2026-09-07');

        foreach (['2025/26', '2026/27'] as $schuljahr) {
            $this->kalender(['school_year' => $schuljahr])
                ->assertSee(self::PREFIX . 'Sommerferien');
        }

        $this->kalender(['school_year' => '2027/28'])
            ->assertDontSee(self::PREFIX . 'Sommerferien');
    }

    // ── Monat innerhalb des Schuljahres ────────────────────────────────────

    #[Test]
    public function der_monat_grenzt_innerhalb_des_schuljahres_ein_und_bestimmt_das_kalenderjahr(): void
    {
        $this->termin('Okt-Neu', '2026-10-05');
        $this->termin('Feb-Neu', '2027-02-08');
        $this->termin('Okt-Alt', '2025-10-06');
        $this->termin('Feb-Alt', '2026-02-09');

        // August bis Dezember liegen im ersten, Januar bis Juli im zweiten Jahr.
        $this->kalender(['school_year' => '2026/27', 'month' => 10])
            ->assertSee(self::PREFIX . 'Okt-Neu')
            ->assertDontSee(self::PREFIX . 'Feb-Neu')
            ->assertDontSee(self::PREFIX . 'Okt-Alt');

        $this->kalender(['school_year' => '2026/27', 'month' => 2])
            ->assertSee(self::PREFIX . 'Feb-Neu')
            ->assertDontSee(self::PREFIX . 'Okt-Neu')
            ->assertDontSee(self::PREFIX . 'Feb-Alt');

        $this->kalender(['school_year' => '2025/26', 'month' => 2])
            ->assertSee(self::PREFIX . 'Feb-Alt')
            ->assertDontSee(self::PREFIX . 'Feb-Neu');
    }

    // ── Der Umschalter selbst ──────────────────────────────────────────────

    #[Test]
    public function der_umschalter_markiert_das_gewaehlte_schuljahr_und_behaelt_die_filter(): void
    {
        $this->kalender(['month' => 3])
            ->assertOk()
            ->assertSeeInOrder(['aria-current="page"', 'bg-blue-600 text-white', '2026/27'], false)
            // Die Links zu den anderen Schuljahren behalten den Monat.
            ->assertSee('school_year=2025%2F26', false)
            ->assertSee('month=3', false);

        // Das Formular gibt das Schuljahr beim Filtern mit.
        $this->kalender(['school_year' => '2025/26'])
            ->assertSee('<input type="hidden" name="school_year" value="2025/26">', false);
    }

    #[Test]
    public function ein_schuljahr_mit_terminen_steht_in_der_auswahl_auch_wenn_es_weit_zurueckliegt(): void
    {
        $this->termin('Uralt', '2019-11-05');

        $this->kalender()->assertSee('2019/20');
    }

    #[Test]
    public function die_seitenlinks_behalten_das_schuljahr(): void
    {
        // 55 Termine im Schuljahr 2025/26: zwei Seiten zu je 50.
        for ($i = 1; $i <= 55; $i++) {
            $this->termin('Masse-' . $i, '2026-03-02');
        }

        $this->kalender(['school_year' => '2025/26'])
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('school_year=2025%2F26', false);

        $this->kalender(['school_year' => '2025/26', 'page' => 2])
            ->assertOk()
            ->assertSee(self::PREFIX . 'Masse-', false);
    }

    // ── SchoolYear: Pruefung und Zeitraum ──────────────────────────────────

    #[Test]
    public function ein_schuljahr_ist_nur_gueltig_wenn_das_folgejahr_stimmt(): void
    {
        $this->assertTrue(SchoolYear::isValid('2026/27'));
        $this->assertTrue(SchoolYear::isValid('2099/00'), 'Der Jahrhundertwechsel geht auf 00.');

        foreach (['2026/28', '2026/26', '26/27', '2026-27', '2026/2027', '', 'irgendwas', null] as $falsch) {
            $this->assertFalse(SchoolYear::isValid($falsch), var_export($falsch, true));
        }
    }

    #[Test]
    public function der_zeitraum_eines_schuljahres_passt_zum_stichtag(): void
    {
        [$von, $bis] = SchoolYear::range('2026/27');

        $this->assertSame('2026-08-01', $von->toDateString());
        $this->assertSame('2027-07-31', $bis->toDateString());

        // Beide Enden gehoeren zum selben Schuljahr, die Tage daneben nicht.
        $this->assertSame('2026/27', SchoolYear::forDate($von));
        $this->assertSame('2026/27', SchoolYear::forDate($bis));
        $this->assertSame('2025/26', SchoolYear::forDate($von->copy()->subDay()));
        $this->assertSame('2027/28', SchoolYear::forDate($bis->copy()->addDay()));
    }
}
