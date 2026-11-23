<?php

namespace Tests\Tenant;

use App\Models\CourseTrack;
use App\Models\School;
use PHPUnit\Framework\Attributes\Test;

/**
 * Zeitfenster der Schienen aus einer Datendatei einspielen (21.09.2026).
 *
 * Die Zeitfenster errechnet der ASV-Import aus den Lehrerstundenplänen. Für
 * 2026/27 lagen sie noch nicht vor; der Untis-Stundenplan der Oberstufe (PDF)
 * ließ sich aber ablesen. `tracks:zeitfenster-einspielen` bringt das Ergebnis
 * aus `database/data/schienen-zeitfenster-2026-27.json` in die Schienen — als
 * Vorschau oder mit --apply, und nur `course_tracks.time_slots` berührend.
 *
 * Ausführen:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter SchienenZeitfensterEinspielen
 */
class SchienenZeitfensterEinspielenTest extends TenantTestCase
{
    private const JAHR = '2026/27';

    protected School $school;

    /** @var list<string> */
    private array $tempDateien = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createTestSchool();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDateien as $datei) {
            @unlink($datei);
        }

        parent::tearDown();
    }

    /**
     * @param list<array<string, mixed>> $schienen
     */
    private function datei(array $schienen, string $schuljahr = self::JAHR): string
    {
        $pfad = tempnam(sys_get_temp_dir(), 'zeitfenster');
        file_put_contents($pfad, json_encode(['schuljahr' => $schuljahr, 'schienen' => $schienen]));
        $this->tempDateien[] = $pfad;

        return $pfad;
    }

    private function schiene(string $stufe, string $name, array $kurse, ?array $slots = null): CourseTrack
    {
        return CourseTrack::create([
            'school_year' => self::JAHR,
            'grade' => $stufe,
            'name' => $name,
            'course_codes' => $kurse,
            'time_slots' => $slots,
        ]);
    }

    private function befehl(string $datei, bool $apply): \Illuminate\Testing\PendingCommand
    {
        $optionen = ['--year' => self::JAHR, '--file' => $datei];
        if ($apply) {
            $optionen['--apply'] = true;
        }

        return $this->artisan('tracks:zeitfenster-einspielen', $optionen);
    }

    #[Test]
    public function ohne_apply_gibt_es_nur_eine_vorschau(): void
    {
        $schiene = $this->schiene('Q12', 'D', ['1d1', '1d2']);

        $datei = $this->datei([[
            'grade' => 'Q12', 'name' => 'D', 'course_codes' => ['1d1', '1d2'],
            'time_slots' => [['day_short' => 'Mo', 'periods' => [5, 6]]],
        ]]);

        $this->befehl($datei, false)->assertExitCode(0);

        $this->assertNull($schiene->fresh()->time_slots, 'Die Vorschau darf nichts schreiben.');
    }

    #[Test]
    public function mit_apply_werden_die_zeitfenster_gesetzt_sortiert_und_null_setzt_zurueck(): void
    {
        $d = $this->schiene('Q12', 'D', ['1d1', '1d2']);
        // Ein altes, falsches Zeitfenster; die Datei sagt: nicht ableitbar → flexibel.
        $fs = $this->schiene('Q12', 'FS2', ['1e2'], [['day_short' => 'Mi', 'periods' => [1, 2]]]);

        $datei = $this->datei([
            [
                'grade' => 'Q12', 'name' => 'D', 'course_codes' => ['1d2', '1d1'],
                'time_slots' => [
                    ['day_short' => 'Mi', 'periods' => [2, 1]],
                    ['day_short' => 'Mo', 'periods' => [5, 6]],
                ],
            ],
            ['grade' => 'Q12', 'name' => 'FS2', 'course_codes' => ['1e2'], 'time_slots' => null],
        ]);

        $this->befehl($datei, true)->assertExitCode(0);

        $this->assertSame(
            [
                ['day_short' => 'Mo', 'periods' => [5, 6]],
                ['day_short' => 'Mi', 'periods' => [1, 2]],
            ],
            $d->fresh()->time_slots
        );
        $this->assertNull($fs->fresh()->time_slots);
    }

    #[Test]
    public function eine_schiene_mit_anderen_kursen_wird_uebersprungen(): void
    {
        $alt = [['day_short' => 'Di', 'periods' => [1]]];
        $schiene = $this->schiene('Q13', 'M', ['2m1', '2m2', '2m7'], $alt);

        $datei = $this->datei([[
            'grade' => 'Q13', 'name' => 'M', 'course_codes' => ['2m1', '2m2'],
            'time_slots' => [['day_short' => 'Do', 'periods' => [3, 4]]],
        ]]);

        $this->befehl($datei, true)->assertExitCode(0);

        $this->assertSame($alt, $schiene->fresh()->time_slots, 'Bei abweichenden Kursen darf nichts überschrieben werden.');
    }

    #[Test]
    public function eine_fehlende_schiene_und_eine_unbekannte_schiene_der_datenbank_stoeren_nicht(): void
    {
        $vorhanden = $this->schiene('Q12', 'Nur in der DB', ['1x1']);

        $datei = $this->datei([[
            'grade' => 'Q12', 'name' => 'Nur in der Datei', 'course_codes' => ['1y1'],
            'time_slots' => [['day_short' => 'Fr', 'periods' => [1]]],
        ]]);

        $this->befehl($datei, true)->assertExitCode(0);

        $this->assertNull($vorhanden->fresh()->time_slots);
        $this->assertSame(1, CourseTrack::where('school_year', self::JAHR)->count(), 'Es wird keine Schiene angelegt.');
    }

    #[Test]
    public function schienen_anderer_schuljahre_bleiben_unberuehrt(): void
    {
        $vorjahr = CourseTrack::create([
            'school_year' => '2025/26', 'grade' => 'Q12', 'name' => 'D',
            'course_codes' => ['1d1'], 'time_slots' => [['day_short' => 'Mi', 'periods' => [3, 4]]],
        ]);

        $datei = $this->datei([[
            'grade' => 'Q12', 'name' => 'D', 'course_codes' => ['1d1'],
            'time_slots' => [['day_short' => 'Mo', 'periods' => [5, 6]]],
        ]]);

        $this->befehl($datei, true)->assertExitCode(0);

        $this->assertSame([['day_short' => 'Mi', 'periods' => [3, 4]]], $vorjahr->fresh()->time_slots);
    }

    #[Test]
    public function schuljahr_der_datei_und_der_aufruf_muessen_zusammenpassen(): void
    {
        $datei = $this->datei([], '2025/26');

        $this->befehl($datei, true)->assertExitCode(1);
    }

    #[Test]
    public function ohne_schuljahr_oder_ohne_datei_bricht_der_befehl_ab(): void
    {
        $this->artisan('tracks:zeitfenster-einspielen', ['--file' => $this->datei([])])->assertExitCode(1);
        $this->artisan('tracks:zeitfenster-einspielen', ['--year' => 'irgendwas'])->assertExitCode(1);
        $this->artisan('tracks:zeitfenster-einspielen', ['--year' => self::JAHR, '--file' => '/nicht/vorhanden.json'])
            ->assertExitCode(1);
        // Der frühere Fall „ohne Mandant“ entfällt: Es gibt genau eine Schule.
    }

    #[Test]
    public function die_mitgelieferte_datei_fuer_2026_27_ist_in_sich_stimmig(): void
    {
        $pfad = database_path('data/schienen-zeitfenster-2026-27.json');
        $this->assertFileExists($pfad);

        $daten = json_decode((string) file_get_contents($pfad), true);
        $this->assertSame('2026/27', $daten['schuljahr']);
        $this->assertNotEmpty($daten['schienen']);

        $schluessel = [];
        foreach ($daten['schienen'] as $s) {
            $bezeichnung = "{$s['grade']} {$s['name']}";

            $this->assertContains($s['grade'], ['Q12', 'Q13'], $bezeichnung);
            $this->assertNotEmpty($s['course_codes'], $bezeichnung);
            $this->assertArrayNotHasKey($bezeichnung, $schluessel, "{$bezeichnung} steht doppelt in der Datei.");
            $schluessel[$bezeichnung] = true;

            if ($s['time_slots'] === null) {
                continue;
            }

            $this->assertNotEmpty($s['time_slots'], $bezeichnung);
            foreach ($s['time_slots'] as $slot) {
                $this->assertContains($slot['day_short'], ['Mo', 'Di', 'Mi', 'Do', 'Fr'], $bezeichnung);
                $this->assertNotEmpty($slot['periods'], $bezeichnung);

                $vorher = 0;
                foreach ($slot['periods'] as $stunde) {
                    $this->assertIsInt($stunde, $bezeichnung);
                    $this->assertGreaterThanOrEqual(1, $stunde, $bezeichnung);
                    $this->assertLessThanOrEqual(11, $stunde, $bezeichnung);
                    $this->assertGreaterThan($vorher, $stunde, "{$bezeichnung}: Stunden nicht aufsteigend.");
                    $vorher = $stunde;
                }
            }
        }
    }
}
