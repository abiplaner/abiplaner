<?php

namespace Tests\Tenant;

use App\Models\CourseTrack;
use App\Models\School;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Schienenplanung im Schuljahr 2026/27 (21.09.2026).
 *
 * Gemeldet wurde: Auf /timetable/tracks ist das laufende Schuljahr nicht
 * verfuegbar, die aktuelle Schienenplanung laesst sich nicht importieren.
 * Dahinter standen drei zusammenhaengende Fehler:
 *
 *  1. Die Auswahl kannte nur 2025/26 und 2024/25, der Vorgabewert war fest
 *     "2025/26" — ebenso in den uebrigen Masken der Stundenplanung.
 *  2. Die Zuordnung Abiturjahr -> Stufe war fest auf 2025/26 verdrahtet. Im
 *     Schuljahr 2026/27 waere die Q13 stillschweigend als Q12 gespeichert
 *     worden, die Q12 haette gefehlt.
 *  3. Die Maske zeigte Importfehler nie an, und ein Import ohne passende
 *     Jahrgaenge loeschte die vorhandenen Schienen und meldete Erfolg.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter SchienenSchuljahr
 */
class SchienenSchuljahrTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'planner-schienen-9999@test.invalid';

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

    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    // ── Schuljahr und Auswahl ──────────────────────────────────────────────

    #[Test]
    public function das_laufende_schuljahr_kippt_am_ersten_august(): void
    {
        $this->assertSame('2026/27', CourseTrack::currentSchoolYear(Carbon::parse('2026-09-21')));
        $this->assertSame('2026/27', CourseTrack::currentSchoolYear(Carbon::parse('2026-08-01')));
        $this->assertSame('2025/26', CourseTrack::currentSchoolYear(Carbon::parse('2026-07-31')));
        $this->assertSame('2025/26', CourseTrack::currentSchoolYear(Carbon::parse('2026-05-15')));
    }

    #[Test]
    public function die_auswahl_fuehrt_naechstes_laufendes_und_voriges_schuljahr_neueste_zuerst(): void
    {
        $optionen = CourseTrack::schoolYearOptions('2026/27', ['2024/25', '2026/27'], Carbon::parse('2026-09-21'));

        $this->assertSame(['2027/28', '2026/27', '2025/26', '2024/25'], $optionen);
    }

    #[Test]
    public function ein_gewaehltes_fremdes_schuljahr_bleibt_in_der_auswahl(): void
    {
        $optionen = CourseTrack::schoolYearOptions('2019/20', [], Carbon::parse('2026-09-21'));

        $this->assertContains('2019/20', $optionen);
    }

    #[Test]
    public function die_stufe_folgt_dem_schuljahr_nicht_einer_festen_tabelle(): void
    {
        // Schuljahr 2025/26: Abitur 2025/26 = Q13, Abitur 2026/27 = Q12.
        $this->assertSame('Q13', CourseTrack::gradeForAbiturjahr('2025/26', '2025/26'));
        $this->assertSame('Q12', CourseTrack::gradeForAbiturjahr('2026/27', '2025/26'));

        // Schuljahr 2026/27: eine Stufe weiter.
        $this->assertSame('Q13', CourseTrack::gradeForAbiturjahr('2026/27', '2026/27'));
        $this->assertSame('Q12', CourseTrack::gradeForAbiturjahr('2027/28', '2026/27'));

        // Nicht in der Q-Phase: schon fertig bzw. noch nicht soweit.
        $this->assertNull(CourseTrack::gradeForAbiturjahr('2025/26', '2026/27'));
        $this->assertNull(CourseTrack::gradeForAbiturjahr('2028/29', '2026/27'));

        // Unbrauchbare Angaben werden nicht geraten.
        $this->assertNull(CourseTrack::gradeForAbiturjahr('', '2026/27'));
        $this->assertNull(CourseTrack::gradeForAbiturjahr('2026/27', 'irgendwas'));
    }

    #[Test]
    public function die_maske_bietet_das_laufende_schuljahr_an_und_waehlt_es_vor(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $this->actingAs($this->planner)
            ->get(route('timetable.tracks.index'))
            ->assertOk()
            ->assertSee('<option value="2026/27" selected>', false)
            ->assertSee('<option value="2025/26" >', false)
            ->assertSee('<option value="2027/28" >', false);
    }

    // ── ASV-Import ─────────────────────────────────────────────────────────

    /**
     * STDSCHIE: Schienennummer Spalte 4, Bezeichnung 5, Stunden 6, Abiturjahr 7.
     *
     * @param list<array{0: string, 1: string, 2: string}> $zeilen [Nummer, Bezeichnung, Abiturjahr]
     */
    private function stdSchie(array $zeilen): UploadedFile
    {
        $inhalt = "\"A\",\"B\",\"C\",\"Schiene\",\"Bezeichnung\",\"Stunden\",\"Abiturjahr\"\n";
        foreach ($zeilen as [$nummer, $bezeichnung, $abiturjahr]) {
            $inhalt .= "\"1\",\"2\",\"3\",\"{$nummer}\",\"{$bezeichnung}\",\"2\",\"{$abiturjahr}\"\n";
        }

        return UploadedFile::fake()->createWithContent('STDSCHIE.txt', $inhalt);
    }

    /**
     * STDKURSSCHIE: Schienennummer Spalte 4, Fach 7, Kurs 10, Abiturjahr 11.
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $zeilen [Nummer, Fach, Kurs, Abiturjahr]
     */
    private function stdKursSchie(array $zeilen): UploadedFile
    {
        $inhalt = "\"A\",\"B\",\"C\",\"Schiene\",\"E\",\"F\",\"Fach\",\"H\",\"I\",\"Kurs\",\"Abiturjahr\"\n";
        foreach ($zeilen as [$nummer, $fach, $kurs, $abiturjahr]) {
            $inhalt .= "\"1\",\"2\",\"3\",\"{$nummer}\",\"5\",\"6\",\"{$fach}\",\"8\",\"9\",\"{$kurs}\",\"{$abiturjahr}\"\n";
        }

        return UploadedFile::fake()->createWithContent('STDKURSSCHIE.txt', $inhalt);
    }

    #[Test]
    public function der_asv_import_ordnet_die_stufen_nach_dem_gewaehlten_schuljahr_zu(): void
    {
        $this->actingAs($this->planner)
            ->post(route('timetable.tracks.import-asv'), [
                'school_year' => '2026/27',
                'stdschie_file' => $this->stdSchie([
                    ['1', 'Schiene A', '2026/27'],   // im Schuljahr 2026/27 die Q13
                    ['1', 'Schiene B', '2027/28'],   // im Schuljahr 2026/27 die Q12
                    ['1', 'Schiene Alt', '2025/26'], // schon fertig, darf nicht ankommen
                ]),
                'stdkursschie_file' => $this->stdKursSchie([
                    ['1', 'm', '2m1', '2026/27'],
                    ['1', 'm', '1m1', '2027/28'],
                    ['1', 'm', '2m9', '2025/26'],
                ]),
            ])
            ->assertRedirect(route('timetable.tracks.index', ['school_year' => '2026/27']))
            ->assertSessionHas('success');

        $schienen = CourseTrack::where('school_year', '2026/27')->get()->keyBy('name');

        $this->assertCount(2, $schienen, 'Der Jahrgang 2025/26 ist im Schuljahr 2026/27 nicht mehr in der Q-Phase.');
        $this->assertSame('Q13', $schienen['Schiene A']->grade);
        $this->assertSame(['2m1'], $schienen['Schiene A']->course_codes);
        $this->assertSame('Q12', $schienen['Schiene B']->grade);
        $this->assertSame(['1m1'], $schienen['Schiene B']->course_codes);
    }

    #[Test]
    public function ein_import_ohne_passende_jahrgaenge_loescht_nichts_und_sagt_es(): void
    {
        CourseTrack::create([
            'school_year' => '2026/27',
            'grade' => 'Q12',
            'name' => 'Vorhanden',
            'course_codes' => ['1m1'],
        ]);

        // Nur der Jahrgang 2025/26 — im Schuljahr 2026/27 nicht mehr in der Q-Phase.
        $this->actingAs($this->planner)
            ->from(route('timetable.tracks.index', ['school_year' => '2026/27']))
            ->post(route('timetable.tracks.import-asv'), [
                'school_year' => '2026/27',
                'stdschie_file' => $this->stdSchie([['1', 'Alt', '2025/26']]),
                'stdkursschie_file' => $this->stdKursSchie([['1', 'm', '2m1', '2025/26']]),
            ])
            ->assertRedirect(route('timetable.tracks.index', ['school_year' => '2026/27']))
            ->assertSessionHasErrors('import');

        $this->assertTrue(
            CourseTrack::where('school_year', '2026/27')->where('name', 'Vorhanden')->exists(),
            'Die vorhandene Schiene darf ein leerer Import nicht loeschen.'
        );
    }

    #[Test]
    public function die_maske_zeigt_einen_importfehler_an(): void
    {
        $fehler = (new ViewErrorBag())->put(
            'default',
            new MessageBag(['import' => 'Import fehlgeschlagen: Testmeldung'])
        );

        $this->actingAs($this->planner)
            ->withSession(['errors' => $fehler])
            ->get(route('timetable.tracks.index', ['school_year' => '2026/27']))
            ->assertOk()
            ->assertSee('Import fehlgeschlagen: Testmeldung');
    }

    // ── Kein festes Schuljahr mehr ─────────────────────────────────────────

    #[Test]
    public function die_masken_der_stundenplanung_waehlen_das_laufende_schuljahr_vor(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        foreach (['timetable.upload', 'timetable.view', 'timetable.events.index', 'timetable.exam-schedule'] as $route) {
            $this->actingAs($this->planner)
                ->get(route($route))
                ->assertOk()
                ->assertSee('<option value="2026/27" selected>', false);
        }
    }

    #[Test]
    public function kein_schuljahr_steht_fest_im_code(): void
    {
        $treffer = [];
        $wurzel = base_path();

        foreach (['app', 'resources/views'] as $ordner) {
            $dateien = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$wurzel}/{$ordner}"));

            foreach ($dateien as $datei) {
                $pfad = $datei->getPathname();

                // Die Startseite zeigt eine feste Vorschau, keine Vorgabe.
                if (!preg_match('/\.php$/', $pfad) || str_contains($pfad, 'resources/views/landing/')) {
                    continue;
                }

                foreach (file($pfad) as $nr => $zeile) {
                    // Kommentare duerfen Beispiele nennen.
                    if (preg_match('#^\s*(\*|//|/\*|\#|\{\{--)#', $zeile)) {
                        continue;
                    }

                    if (preg_match('/[\'"]20\d{2}\/\d{2}[\'"]/', $zeile)) {
                        $treffer[] = str_replace($wurzel . '/', '', $pfad) . ':' . ($nr + 1) . '  ' . trim($zeile);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $treffer,
            "Ein Schuljahr darf nicht als Zeichenkette im Code stehen — App\\Support\\SchoolYear benutzen:\n"
            . implode("\n", $treffer)
        );
    }
}
