<?php

namespace Tests\Tenant;

use App\Models\User;
use App\Services\SetupChecklistService;
use Database\Seeders\TestschuleSeeder;
use Illuminate\Console\Command;

/**
 * Modul 6.2 – Einrichtungsstand einer Schule.
 *
 * Die Checkliste ist die erste Seite, die ein neuer Anwender sieht. Sie muss
 * zwei Dinge richtig treffen: bei einer frisch angelegten Schule das Fehlende
 * benennen, und bei einer eingerichteten Schule nicht weiter mahnen.
 */
class SetupChecklistTest extends TenantTestCase
{
    /** Zentral angelegtes Testkonto; wird in jedem Test wieder entfernt. */
    private const PLANNER_EMAIL = 'planer-9999@test.invalid';

    protected function setUp(): void
    {
        parent::setUp();

        // Reste eines abgebrochenen Laufs: users ist zentral und wird vom
        // Aufräumen der Testschule nicht miterfasst.
        User::where('email', self::PLANNER_EMAIL)->delete();
    }

    public function test_frische_schule_meldet_die_offenen_bausteine(): void
    {
        $school = $this->createTestSchool();


        try {
            $items = $this->checkliste()->items();
            $progress = $this->checkliste()->progress();
        } finally {
        }

        $status = $this->statusNachSchluessel($items);

        // Pflicht sind nur die drei Dateien — und die fehlen alle.
        $this->assertSame(['erledigt' => 0, 'gesamt' => 3, 'prozent' => 0, 'offen' => 3], $progress);

        foreach (['lehrkraefte', 'schueler', 'stundenplan'] as $key) {
            $this->assertSame(SetupChecklistService::STATUS_OPEN, $status[$key], "{$key} sollte offen sein.");
        }

        // Was die Grundausstattung vorbelegt, steht unter „Anpassen" und zählt nicht.
        $anpassen = array_column(array_filter(
            $items,
            fn (array $item) => $item['gruppe'] === SetupChecklistService::GRUPPE_ANPASSEN
        ), 'required', 'key');
        $this->assertSame(
            ['schulstammdaten', 'raeume', 'sitzvorlagen', 'zeitslots', 'schuleinstellungen'],
            array_keys($anpassen)
        );
        $this->assertNotContains(true, $anpassen);

        $this->assertSame(SetupChecklistService::STATUS_DONE, $status['zeitslots']);
        $this->assertSame(SetupChecklistService::STATUS_DONE, $status['sitzvorlagen']);
        $this->assertSame(SetupChecklistService::STATUS_PARTIAL, $status['raeume'], 'Vorgabenamen = noch nicht angepasst.');

        // Keine eigenen Zeilen mehr: Kurse und Zuordnungen kommen mit dem Schülerimport.
        $this->assertArrayNotHasKey('kurse', $status);
        $this->assertArrayNotHasKey('pruefungszuordnungen', $status);
        $this->assertArrayNotHasKey('benutzerkonten', $status);
    }

    public function test_eingerichtete_schule_meldet_die_eigenen_bausteine_als_erledigt(): void
    {
        $school = $this->createTestSchool();


        try {
            $seeder = new TestschuleSeeder();
            $seeder->setCommand($this->stilleAusgabe());
            $seeder->run();

            $items = $this->checkliste()->items();
        } finally {
        }

        $einlesen = array_filter(
            $items,
            fn (array $item) => $item['gruppe'] === SetupChecklistService::GRUPPE_EINLESEN
        );

        foreach ($einlesen as $item) {
            $this->assertSame(
                SetupChecklistService::STATUS_DONE,
                $item['status'],
                "„{$item['title']}\" sollte nach dem Testschul-Seeder eingelesen sein ({$item['detail']})."
            );
        }

        $this->assertSame(0, $this->checkliste()->progress($items)['offen']);
    }

    /**
     * Die Seite selbst — Route, Controller, View und Partial.
     *
     * Die Prüfungen darüber testen nur den Service; ein Tippfehler im Blade
     * fiele dort nicht auf, und die Einrichtungsseite ist ausgerechnet die
     * erste, die ein neuer Anwender zu sehen bekommt.
     */
    public function test_einrichtungsseite_wird_ausgeliefert(): void
    {
        $school = $this->createTestSchool();

        $planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $school->getKey(),
        ]);


        try {
            $response = $this->actingAs($planner)->get(route('setup.index'));

            $response->assertOk();
            $response->assertViewIs('setup.index');
            $response->assertViewHas('progress');
            $response->assertSee('Drei Dateien einlesen');
            $response->assertSee('Vorbelegt');
            $response->assertSee('Lehrkräfte');
            $response->assertSee('Stundenplan');
            $response->assertDontSee('Administrator');
        } finally {
            User::where('email', self::PLANNER_EMAIL)->delete();
        }
    }

    /**
     * Jede hinterlegte Route muss es auch geben.
     *
     * Beim ersten Durchgang zeigten zwei Zeilen auf die falsche Seite — die
     * Zeitslots auf die Stundenraster-Verwaltung, die Sitzvorlagen auf die
     * Raumliste. Beides existierende Routen, der Fehler fiel also erst beim
     * Durchklicken auf. Diese Probe fängt wenigstens Tippfehler und später
     * entfernte Routen ab.
     */
    public function test_alle_verweise_zeigen_auf_vorhandene_routen(): void
    {
        $school = $this->createTestSchool();


        try {
            $items = $this->checkliste()->items();
        } finally {
        }

        foreach ($items as $item) {
            if ($item['route'] === null) {
                continue;
            }

            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($item['route']),
                "Baustein „{$item['title']}\" verweist auf die unbekannte Route {$item['route']}."
            );
        }
    }

    private function checkliste(): SetupChecklistService
    {
        return app(SetupChecklistService::class);
    }

    /**
     * @param list<array{key: string, status: string}> $items
     *
     * @return array<string, string>
     */
    private function statusNachSchluessel(array $items): array
    {
        $status = [];

        foreach ($items as $item) {
            $status[$item['key']] = $item['status'];
        }

        return $status;
    }

    private function stilleAusgabe(): Command
    {
        return new class extends Command
        {
            public function info($string, $verbosity = null): void
            {
            }

            public function warn($string, $verbosity = null): void
            {
            }

            public function line($string, $style = null, $verbosity = null): void
            {
            }
        };
    }
}
