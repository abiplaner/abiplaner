<?php

namespace Tests\Tenant;

use App\Models\ExamSchedulePeriod;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Schulinterne Termine (SchoolEvent) auf /timetable/exam-schedule: die
 * „Betroffene Jahrgangsstufen"-Auswahl aus der Verwaltungsmaske muss auch
 * beim Anzeigen greifen.
 *
 * Gemeldet (22.09.2026): Auf /timetable/exam-schedule?period=Q13/1 tauchen
 * Schultermine auf, die nur für Q12 angelegt wurden. Ursache: Q12/1 und Q13/1
 * beginnen am selben Tag (Kurswahländerung) und Q13/1 endet früher (Zeugnis
 * 13/1 vor Zeugnis 12/1) — Q13/1s Datumsfenster liegt also vollständig
 * innerhalb des Fensters von Q12/1. `examSchedule()` lud SchoolEvents bisher
 * rein über `betweenDates()`, ohne `affected_grades` zu prüfen; ein nur für
 * Q12 markierter Termin, dessen Datum in beide Fenster fällt, erschien damit
 * auch in Q13/1.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit tests/Tenant/SchultermineJahrgangsstufenTest.php
 */
class SchultermineJahrgangsstufenTest extends TenantTestCase
{
    private const JAHR = '2040/41';
    private const PLANNER_EMAIL = 'planner-schultermine-9999@test.invalid';

    protected School $school;
    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();

        $this->school = $this->createTestSchool();

        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);


        // Q12/1 und Q13/1 wie sie sich real aus dem Terminkalender ergeben:
        // beide beginnen mit der Kurswahländerung, Q13/1 endet früher (Zeugnis
        // 13/1 kommt vor Zeugnis 12/1) — ihr Fenster liegt also innerhalb des
        // von Q12/1.
        ExamSchedulePeriod::create([
            'name' => 'Q12/1', 'school_year' => self::JAHR,
            'start_date' => '2040-10-08', 'end_date' => '2041-02-15', 'is_active' => true,
        ]);
        ExamSchedulePeriod::create([
            'name' => 'Q13/1', 'school_year' => self::JAHR,
            'start_date' => '2040-10-08', 'end_date' => '2041-01-25', 'is_active' => false,
        ]);
    }

    protected function tearDown(): void
    {
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    #[Test]
    public function ein_nur_fuer_q12_angelegter_termin_erscheint_nicht_in_q13(): void
    {
        SchoolEvent::create([
            'school_year' => self::JAHR,
            'name' => 'Nur-Q12-Veranstaltung',
            'type' => 'veranstaltung',
            'start_date' => '2040-11-12', // liegt in beiden Fenstern
            'affected_grades' => ['Q12'],
        ]);

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q13/1']))
            ->assertOk()
            ->assertDontSee('Nur-Q12-Veranstaltung');

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q12/1']))
            ->assertOk()
            ->assertSee('Nur-Q12-Veranstaltung');
    }

    #[Test]
    public function ein_nur_fuer_q13_angelegter_termin_erscheint_nicht_in_q12(): void
    {
        SchoolEvent::create([
            'school_year' => self::JAHR,
            'name' => 'Nur-Q13-Exkursion',
            'type' => 'exkursion',
            'start_date' => '2040-11-12',
            'affected_grades' => ['Q13'],
        ]);

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q12/1']))
            ->assertOk()
            ->assertDontSee('Nur-Q13-Exkursion');

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q13/1']))
            ->assertOk()
            ->assertSee('Nur-Q13-Exkursion');
    }

    #[Test]
    public function ein_termin_ohne_jahrgangsstufen_gilt_fuer_alle(): void
    {
        SchoolEvent::create([
            'school_year' => self::JAHR,
            'name' => 'Termin-Fuer-Alle',
            'type' => 'sonstiges',
            'start_date' => '2040-11-12',
            'affected_grades' => null,
        ]);

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q12/1']))
            ->assertOk()
            ->assertSee('Termin-Fuer-Alle');

        $this->actingAs($this->planner)
            ->get(route('timetable.exam-schedule', ['school_year' => self::JAHR, 'period' => 'Q13/1']))
            ->assertOk()
            ->assertSee('Termin-Fuer-Alle');
    }

    /**
     * Gemeldet (22.09.2026): Derselbe Fehler wie oben trat auch im PDF-Export
     * des Klausurplans auf (exportExamSchedulePdf() hatte dieselbe
     * SchoolEvent-Abfrage ohne affected_grades-Filter dupliziert). Da der
     * PDF-Inhalt (DomPDF, komprimiert) sich nicht per assertSee prüfen lässt,
     * verifizieren wir hier nur, dass der Export für beide Zeiträume ohne
     * Fehler ein gültiges PDF liefert – die eigentliche Filterlogik ist
     * identisch zu der oben für die HTML-Ansicht getesteten.
     */
    #[Test]
    public function pdf_export_liefert_fuer_beide_zeitraeume_ein_gueltiges_pdf(): void
    {
        SchoolEvent::create([
            'school_year' => self::JAHR,
            'name' => 'Nur-Q12-Veranstaltung',
            'type' => 'veranstaltung',
            'start_date' => '2040-11-12',
            'affected_grades' => ['Q12'],
        ]);

        foreach (['Q12/1', 'Q13/1'] as $period) {
            $response = $this->actingAs($this->planner)
                ->get(route('timetable.exam-schedule.export-pdf', ['school_year' => self::JAHR, 'period' => $period]));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }
}
