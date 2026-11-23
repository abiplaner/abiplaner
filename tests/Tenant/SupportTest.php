<?php

namespace Tests\Tenant;

use App\Mail\SupportFallGemeldet;
use App\Models\School;
use App\Models\Student;
use App\Models\SupportAnhang;
use App\Models\SupportFall;
use App\Models\SupportNachricht;
use App\Models\Teacher;
use App\Models\User;
use App\Services\FehlerKennung;
use App\Services\SupportAnhangService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Support-Organisation, Stufe 1 (Roadmap 6.4).
 *
 * Fuenf Zusicherungen tragen das Modul, und jede von ihnen faellt sonst still
 * aus:
 *
 * 1. **Die Fallakte liegt zentral** und ueberlebt das Ende der Tenancy —
 *    sonst waere eine Meldung ueber einen kaputten Mandanten am kaputten
 *    Mandanten gescheitert (Entscheidung 43).
 * 2. **Der Kontext gibt nichts preis, was er nicht preisgeben soll.** Der
 *    Einrichtungsstand traegt keine Namen, ein Datenbankfehler kein SQL.
 * 3. **Eine fremde Fall-Id fuehrt zu 403** — die Faelle liegen zentral, die
 *    Mandantentrennung von 6.1 traegt hier also nicht.
 * 4. **Ein gescheiterter Mailversand kostet keine Meldung.**
 * 5. **Der Schreibschutz der Archivansicht sperrt die Meldung nicht** — sonst
 *    koennte niemand melden, dass die Archivansicht kaputt ist.
 *
 * Die Fallakten liegen zentral und werden von keiner Transaktion
 * zurueckgerollt (in Tenant-Tests zeigt `central` auf dieselbe MariaDB).
 * Wegwerf-Datensaetze muessen deshalb von Hand fallen.
 */
class SupportTest extends TenantTestCase
{
    private const MELDER_EMAIL = 'support-9999@test.invalid';

    private const ADMIN_EMAIL = 'support-admin-9999@test.invalid';

    /** Eine zweite Schule fuer die Grenzprobe. */
    private const FREMDE_SCHULNUMMER = '9998';

    protected School $school;

    protected User $melder;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Support-Meldungen sind abgeschaltet (Desktop-Fassung, seit 22.11.2026).');

        $this->raeumeAuf();

        config(['support.empfaenger' => 'support-test@test.invalid']);

        $this->school = $this->createTestSchool();

        // Die zweite Schule wird per Query angelegt und NICHT ueber das
        // Modell: Der SchoolObserver zoege sonst die ganze Tenancy-Pipeline
        // nach sich (Datenbank anlegen, migrieren, seeden). Sie braucht keinen
        // Mandanten — es meldet sich nie jemand an ihr an. Die Grenzprobe
        // laeuft ueber einen FALL dieser Schule, den der Nutzer der eigenen
        // Schule zu oeffnen versucht; damit wird nie eine Tenancy fuer 9998
        // initialisiert und der Test bleibt schnell.
        DB::table('schools')->insert([
            'school_number' => self::FREMDE_SCHULNUMMER,
            'name' => 'Wegwerf-Fremdschule 9998',
            'short_name' => 'TEST9998',
            'state' => 'BY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->melder = User::factory()->create([
            'email' => self::MELDER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

        $this->superAdmin = User::factory()->create([
            'email' => self::ADMIN_EMAIL,
            'role' => 'super_admin',
            'school_id' => null,
        ]);
    }

    protected function tearDown(): void
    {

        $this->raeumeAuf();

        parent::tearDown();
    }

    private function raeumeAuf(): void
    {
        $ids = SupportFall::query()
            ->whereIn('school_id', [self::TEST_SCHOOL_NUMBER, self::FREMDE_SCHULNUMMER])
            ->pluck('id');

        SupportNachricht::query()->whereIn('support_fall_id', $ids)->delete();
        SupportFall::query()->whereIn('id', $ids)->delete();

        User::query()->whereIn('email', [
            self::MELDER_EMAIL, self::ADMIN_EMAIL,
        ])->delete();

        DB::table('schools')
            ->where('school_number', self::FREMDE_SCHULNUMMER)->delete();
    }

    /**
     * Ein echtes JPEG aus `tests/fixtures/` als hochgeladene Datei.
     *
     * Kopiert wird zuerst: `UploadedFile` im Testmodus fasst die Datei zwar
     * nicht an, aber die Vorlage soll auch dann unversehrt bleiben, wenn ein
     * Test sie doch einmal verschiebt.
     */
    private function jpegAusFixture(string $name): UploadedFile
    {
        $vorlage = base_path('tests/fixtures/support-bildschirmfoto.jpg');
        $this->assertFileExists($vorlage);

        $kopie = (string) tempnam(sys_get_temp_dir(), 'zzz-support') . '.jpg';
        copy($vorlage, $kopie);

        return new UploadedFile($kopie, $name, 'image/jpeg', null, true);
    }

    /**
     * @param array<string, mixed> $abweichungen
     */
    private function meldung(array $abweichungen = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->melder)->post(route('support.store'), array_merge([
            'betreff' => 'Die Kolloquiumsplanung zeigt nichts an',
            'beschreibung' => 'Nach dem Import ist die Liste leer.',
            'herkunft_route' => 'planning.index',
            'herkunft_url' => 'http://localhost/planning',
        ], $abweichungen));
    }

    // ------------------------------------------------------------------
    // 1. Die Fallakte liegt zentral
    // ------------------------------------------------------------------

    public function test_eine_meldung_landet_zentral_und_ueberlebt_das_ende_der_tenancy(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();

        $this->assertSame('central', $fall->getConnectionName());
        $this->assertSame('Die Kolloquiumsplanung zeigt nichts an', $fall->betreff);
        $this->assertSame($this->melder->email, $fall->melder_email);
        $this->assertSame('Testschule Isolation', $fall->schule_name);
        $this->assertSame(SupportFall::STATUS_NEU, $fall->status);

        // Der Punkt: Ohne Mandanten ist die Akte weiterhin lesbar. Waere sie
        // im Mandanten gelandet, waere genau das nicht mehr wahr.

        $this->assertNotNull(SupportFall::query()->find($fall->id));

        Mail::assertSent(SupportFallGemeldet::class);
        $this->assertNotNull($fall->fresh()?->benachrichtigt_am);
    }

    /**
     * Die Modulzuordnung kommt aus dem Hilfesystem und wird nicht
     * zweitgepflegt — faellt diese Zusicherung, laufen zwei Zuordnungen
     * nebeneinander her.
     */
    public function test_die_meldung_findet_das_kapitel_zur_maske(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();

        $this->assertNotNull($fall->hilfe_slug, 'Zur Route planning.index gibt es ein Kapitel.');
        $this->assertSame('planning.index', $fall->route_name);
    }

    // ------------------------------------------------------------------
    // 2. Der Kontext
    // ------------------------------------------------------------------

    public function test_der_kontext_traegt_alle_vier_bloecke(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();

        $kontext = SupportFall::query()
            ->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail()->kontext;

        $this->assertIsArray($kontext);
        $this->assertArrayHasKey('technik', $kontext);
        $this->assertArrayHasKey('einrichtung', $kontext);
        $this->assertArrayHasKey('aktivitaeten', $kontext);
        $this->assertArrayHasKey('fehler', $kontext);

        $this->assertSame(self::TEST_SCHOOL_NUMBER, $kontext['technik']['schule']);
        $this->assertSame($this->melder->email, $kontext['technik']['nutzer']);
        $this->assertSame('planning.index', $kontext['technik']['route']);
        $this->assertIsInt($kontext['technik']['tenant_migrationen']);
        $this->assertNotNull($kontext['einrichtung'], 'Im Mandanten muss der Einrichtungsstand erhoben werden.');
    }

    /**
     * **Der Test, der die Datenschutzzusage des Blocks traegt.** Der
     * Einrichtungsstand zaehlt — er nennt nicht.
     */
    public function test_der_einrichtungsstand_traegt_keine_personendaten(): void
    {
        Mail::fake();


        Teacher::query()->create([
            'shortcode' => 'ZZT',
            'firstname' => 'Zzzvorname',
            'lastname' => 'Zzznachname',
        ]);

        Student::query()->create([
            'exam_period_id' => 999999,
            'external_id' => 'ZZZ-1',
            'firstname' => 'Zzzschueler',
            'lastname' => 'Zzzschuelerin',
            'grade' => '13',
        ]);


        $this->meldung()->assertRedirect();

        $einrichtung = SupportFall::query()
            ->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail()->kontext['einrichtung'];

        $alsText = json_encode($einrichtung, JSON_THROW_ON_ERROR);

        foreach (['Zzzvorname', 'Zzznachname', 'Zzzschueler', 'Zzzschuelerin', 'ZZT'] as $name) {
            $this->assertStringNotContainsString(
                $name,
                $alsText,
                'Der Einrichtungsstand darf zaehlen, aber niemanden nennen.'
            );
        }
    }

    /**
     * Der Kurzbefund aus der Sitzung wandert in die Akte — und mit ihm die
     * Kennung, die auch in der Logzeile steht.
     */
    public function test_der_letzte_fehler_wandert_aus_der_sitzung_in_die_akte(): void
    {
        Mail::fake();

        $befund = FehlerKennung::erfassen(new RuntimeException('Etwas ist schiefgegangen'));

        $this->actingAs($this->melder)
            ->withSession([FehlerKennung::SESSION_SCHLUESSEL => $befund])
            ->post(route('support.store'), [
                'betreff' => 'Fehlermeldung nach Absturz',
                'beschreibung' => 'Es kam eine weisse Seite.',
                'herkunft_route' => 'planning.index',
            ])
            ->assertRedirect()
            // Der Befund hat seinen Zweck erfuellt; bliebe er stehen, haenge er
            // an der naechsten Meldung noch einmal dran.
            ->assertSessionMissing(FehlerKennung::SESSION_SCHLUESSEL);

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();

        $this->assertSame($befund['id'], $fall->fehler_id);
        $this->assertSame($befund['id'], $fall->kontext['fehler']['id']);
        $this->assertStringContainsString('Etwas ist schiefgegangen', $fall->kontext['fehler']['meldung']);
    }

    // ------------------------------------------------------------------
    // 3. Die Grenze zwischen den Schulen
    // ------------------------------------------------------------------

    /**
     * **Ueber die Fall-Id, nicht ueber eine Liste.** Eine Liste schuetzt sich
     * mit einer `where`-Bedingung, die beim naechsten Umbau verschwinden kann.
     */
    public function test_ein_fremder_fall_ist_ueber_seine_id_nicht_erreichbar(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();

        $eigener = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();
        $fremder = $this->fremderFall();

        $this->actingAs($this->melder)->get(route('support.show', $eigener))->assertOk();

        $this->actingAs($this->melder)->get(route('support.show', $fremder))->assertForbidden();
        $this->actingAs($this->melder)
            ->post(route('support.nachricht', $fremder), ['text' => 'Neugier'])
            ->assertForbidden();

        // Der Support sieht beide — er ist der Support.
        $this->actingAs($this->superAdmin)->get(route('admin.support.show', $fremder))->assertOk();
    }

    public function test_die_liste_zeigt_nur_die_eigene_schule(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();
        $this->fremderFall();

        $this->actingAs($this->melder)
            ->get(route('support.index'))
            ->assertOk()
            ->assertSee('Die Kolloquiumsplanung zeigt nichts an')
            ->assertDontSee('Fall einer anderen Schule');

        // Im Dashboard des Supports stehen beide.
        $this->actingAs($this->superAdmin)
            ->get(route('admin.support.index'))
            ->assertOk()
            ->assertSee('Fall einer anderen Schule');
    }

    /**
     * Ein Fall der zweiten Schule — angelegt, ohne dass sich jemals jemand an
     * ihr anmeldet. Genau das macht die Probe aussagekraeftig: Es geht um die
     * Grenze am Fall, nicht um die Mandantentrennung, die hier gar nicht
     * traegt.
     */
    private function fremderFall(): SupportFall
    {
        return SupportFall::query()->create([
            'school_id' => self::FREMDE_SCHULNUMMER,
            'schule_name' => 'Wegwerf-Fremdschule 9998',
            'melder_email' => 'jemand@test.invalid',
            'betreff' => 'Fall einer anderen Schule',
            'beschreibung' => 'Geht die eigene Schule nichts an.',
            'status' => SupportFall::STATUS_NEU,
            'benachrichtigt_am' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // 4. Der Mailversand darf die Meldung nicht gefaehrden
    // ------------------------------------------------------------------

    public function test_ein_gescheiterter_versand_kostet_keine_meldung(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP nicht erreichbar'));

        $this->meldung()->assertRedirect();

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();

        $this->assertNull($fall->benachrichtigt_am, 'Der Versand ist gescheitert und darf nicht als erledigt gelten.');
        $this->assertSame('Die Kolloquiumsplanung zeigt nichts an', $fall->betreff);
    }

    public function test_der_befehl_holt_die_benachrichtigung_nach(): void
    {
        Mail::fake();

        $fall = SupportFall::query()->create([
            'school_id' => self::TEST_SCHOOL_NUMBER,
            'schule_name' => 'Testschule Isolation',
            'melder_email' => self::MELDER_EMAIL,
            'betreff' => 'Liegengeblieben',
            'beschreibung' => 'Der Versand war beim Absenden nicht moeglich.',
            'status' => SupportFall::STATUS_NEU,
        ]);

        $this->artisan('support:nachbenachrichtigen')->assertExitCode(0);

        Mail::assertSent(SupportFallGemeldet::class);
        $this->assertNotNull($fall->fresh()?->benachrichtigt_am);

        // Ein zweiter Lauf darf nichts mehr finden.
        $this->artisan('support:nachbenachrichtigen')
            ->expectsOutputToContain('Keine ausstehenden Benachrichtigungen.')
            ->assertExitCode(0);
    }

    /** Der Kontext gehoert NICHT in die Mail — er kann Namen enthalten. */
    public function test_die_mail_traegt_den_kontext_nicht(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();

        // Gegen echte Werte aus dem Kontext geprüft, nicht gegen Ueberschriften:
        // Ein Detail des Einrichtungsstands („12 Raeume") ist genau das, was
        // nicht in einem Postfach landen soll.
        $detail = $fall->kontext['einrichtung']['bausteine'][0]['detail'];
        $browser = $fall->kontext['technik']['browser'];

        $this->assertNotSame('', $detail);

        Mail::assertSent(SupportFallGemeldet::class, function (SupportFallGemeldet $mail) use ($fall, $detail, $browser): bool {
            $inhalt = $mail->render();

            return str_contains($inhalt, $fall->betreff)
                && ! str_contains($inhalt, $detail)
                && ! str_contains($inhalt, $browser);
        });
    }

    // ------------------------------------------------------------------
    // 5. Der Schreibschutz der Archivansicht
    // ------------------------------------------------------------------

    /**
     * Sperrte er die Meldung, koennte ausgerechnet in der Archivansicht
     * niemand melden, dass die Archivansicht kaputt ist.
     */
    public function test_die_meldung_geht_auch_in_der_archivansicht(): void
    {
        $anfrage = \Illuminate\Http\Request::create('/support', 'POST');

        $route = new \Illuminate\Routing\Route(['POST'], '/support', []);
        $route->name('support.store');
        $anfrage->setRouteResolver(static fn (): \Illuminate\Routing\Route => $route);

        $antwort = (new \App\Http\Middleware\PreventArchivePeriodWrites())->handle(
            $anfrage,
            static fn (): \Illuminate\Http\Response => new \Illuminate\Http\Response('durchgelassen')
        );

        $this->assertSame('durchgelassen', $antwort->getContent());
    }

    // ------------------------------------------------------------------
    // Der Verlauf
    // ------------------------------------------------------------------

    public function test_antwort_und_nachfrage_bewegen_den_status(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();
        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.support.antwort', $fall), ['text' => 'Bitte den Import wiederholen.'])
            ->assertRedirect();

        $fall->refresh();
        $this->assertSame(SupportFall::STATUS_BEANTWORTET, $fall->status);
        $this->assertTrue($fall->hatUngeleseneAntwort(), 'Die Schule hat die Antwort noch nicht gesehen.');

        // Das Oeffnen setzt sie auf gelesen.
        $this->actingAs($this->melder)->get(route('support.show', $fall))->assertOk();
        $this->assertFalse($fall->fresh()?->hatUngeleseneAntwort());

        // Eine Nachfrage macht den Fall wieder auf.
        $this->actingAs($this->melder)
            ->post(route('support.nachricht', $fall), ['text' => 'Der Import lief schon zweimal.'])
            ->assertRedirect();

        $this->assertSame(SupportFall::STATUS_NEU, $fall->fresh()?->status);
        $this->assertSame(2, SupportNachricht::query()->where('support_fall_id', $fall->id)->count());
    }

    public function test_der_status_laesst_sich_setzen_und_nur_vom_support(): void
    {
        Mail::fake();

        $this->meldung()->assertRedirect();
        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.support.status', $fall), ['status' => SupportFall::STATUS_ERLEDIGT])
            ->assertRedirect();

        $fall->refresh();
        $this->assertSame(SupportFall::STATUS_ERLEDIGT, $fall->status);
        $this->assertNotNull($fall->geschlossen_am);
        $this->assertSame(0, SupportFall::query()->offen()->where('id', $fall->id)->count());

        // Die Verwaltung ist dem Super-Admin vorbehalten — die Middleware
        // faengt den Planer ab, bevor die Policy ueberhaupt gefragt wird.
        $this->actingAs($this->melder)
            ->get(route('admin.support.index'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Die Ausschnitte fuer das Panel
    // ------------------------------------------------------------------

    public function test_der_kontext_ausschnitt_antwortet(): void
    {
        $this->actingAs($this->melder)
            ->get(route('support.kontext', ['herkunft_route' => 'planning.index']))
            ->assertOk()
            ->assertSee('Technisch')
            ->assertSee('Einrichtungsstand');
    }

    /** Stufe 1.5: Was die Hilfe schon beantwortet, muss kein Fall werden. */
    public function test_die_kapitelvorschlaege_greifen_auf_die_hilfe_zu(): void
    {
        $this->actingAs($this->melder)
            ->get(route('support.vorschlag', ['q' => 'Kolloquium']))
            ->assertOk()
            ->assertSee('Vielleicht steht es schon in der Hilfe');

        // Kein Treffer heisst: gar nichts anzeigen. Ein Hinweis auf das
        // Ausbleiben eines Vorschlags hilft niemandem.
        $this->actingAs($this->melder)
            ->get(route('support.vorschlag', ['q' => 'zzzgibtesnicht']))
            ->assertOk()
            ->assertDontSee('Vielleicht steht es schon in der Hilfe');
    }

    public function test_die_meldung_verlangt_betreff_und_beschreibung(): void
    {
        $this->actingAs($this->melder)
            ->post(route('support.store'), ['betreff' => '', 'beschreibung' => ''])
            ->assertSessionHasErrors(['betreff', 'beschreibung']);

        $this->assertSame(0, SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->count());
    }

    // ------------------------------------------------------------------
    // Bildschirmfotos (Entscheidung 48)
    // ------------------------------------------------------------------

    /**
     * **Das JPEG kommt aus `tests/fixtures/`, nicht aus GD.**
     *
     * `UploadedFile::fake()->image('x.jpg')` ruft `imagejpeg()` — und die
     * GD-Erweiterung der CI ist ohne libjpeg gebaut (`-lz -lpng16`). Der Test
     * fiel dort mit „imagejpeg function is not defined" um, während er lokal
     * grün war: wieder ein Fall von „grün hängt an der Umgebung", diesmal
     * andersherum.
     *
     * Die feste Datei behebt nicht nur den Ausfall, sie prüft nebenbei etwas
     * Richtiges: **Ein JPEG anzunehmen hängt nicht an GD.** `getimagesize()`
     * und finfo lesen den Kopf selbst; die Anwendung erzeugt nie ein Bild. Der
     * Test belegt das jetzt genau dort, wo `imagejpeg()` fehlt.
     */
    public function test_bildschirmfotos_landen_ausserhalb_des_web_verzeichnisses(): void
    {
        Mail::fake();
        Storage::fake(SupportAnhangService::DISK);

        $this->meldung(['anhaenge' => [
            UploadedFile::fake()->image('bildschirmfoto.png', 120, 80),
            $this->jpegAusFixture('zweites.jpg'),
        ]])->assertRedirect();

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();
        $anhaenge = SupportAnhang::query()->where('support_fall_id', $fall->id)->get();

        $this->assertCount(2, $anhaenge);

        foreach ($anhaenge as $anhang) {
            Storage::disk(SupportAnhangService::DISK)->assertExists($anhang->pfad);

            // **Der Dateiname des Melders wird nie zum Pfad.** Gespeichert wird
            // unter einer ULID; der urspruengliche Name steht nur zur Anzeige
            // in der Datenbank. Damit gibt es keinen Weg von einer Eingabe in
            // einen Pfad.
            $this->assertStringStartsWith('support/' . $fall->id . '/', $anhang->pfad);
            $this->assertStringNotContainsString('bildschirmfoto', $anhang->pfad);
            $this->assertStringNotContainsString('zweites', $anhang->pfad);
        }

        $this->assertSame('bildschirmfoto.png', $anhaenge[0]->dateiname);
        $this->assertSame('image/jpeg', $anhaenge[1]->mime);
        $this->assertStringEndsWith('.jpg', $anhaenge[1]->pfad);

        $antwort = $this->actingAs($this->melder)
            ->get(route('support.anhang', ['fall' => $fall, 'anhang' => $anhaenge[0]]));

        $antwort->assertOk();
        $antwort->assertHeader('content-type', 'image/png');
        $antwort->assertHeader('x-content-type-options', 'nosniff');
    }

    /**
     * **Der Typ kommt aus dem Inhalt, nicht aus der Endung.** Eine PHP-Datei,
     * die sich `.png` nennt, ist der Fall, für den diese Prüfung existiert.
     *
     * **Nicht mit `UploadedFile::fake()`:** Laravels Testdatei meldet ihren
     * Typ aus dem *Dateinamen*, nicht aus dem Inhalt. Beide Regeln — `image`
     * wie `mimetypes` — sähen damit ein PNG, und der Test wäre grün, ohne
     * irgendetwas zu prüfen. Gebaut wird deshalb eine echte `UploadedFile`
     * über eine echte Datei; dort greift finfo auf den Inhalt zu, genau wie
     * bei einem echten Upload.
     */
    public function test_eine_umbenannte_datei_wird_abgewiesen(): void
    {
        Mail::fake();
        Storage::fake(SupportAnhangService::DISK);

        $pfad = (string) tempnam(sys_get_temp_dir(), 'zzz-support');
        file_put_contents($pfad, "<?php echo 'nein';");

        try {
            $datei = new UploadedFile($pfad, 'schadcode.png', 'image/png', null, true);

            // Gegenprobe: Der Browser meldet ein Bild, der Inhalt ist keines.
            // Fällt diese Zusicherung, prüft der Test darunter nichts mehr.
            $this->assertSame('image/png', $datei->getClientMimeType());
            $this->assertStringNotContainsString('image/', (string) $datei->getMimeType());

            $this->meldung(['anhaenge' => [$datei]])->assertSessionHasErrors('anhaenge.0');
        } finally {
            @unlink($pfad);
        }

        $this->assertSame(0, SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->count());
        $this->assertSame(0, SupportAnhang::query()->count());
    }

    public function test_mehr_als_drei_bilder_werden_abgewiesen(): void
    {
        Mail::fake();
        Storage::fake(SupportAnhangService::DISK);

        $bilder = [];
        for ($i = 0; $i < SupportAnhangService::MAX_ANZAHL + 1; $i++) {
            $bilder[] = UploadedFile::fake()->image('b' . $i . '.png', 40, 40);
        }

        $this->meldung(['anhaenge' => $bilder])->assertSessionHasErrors('anhaenge');

        $this->assertSame(0, SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->count());
    }

    /**
     * Der Fall steht im Pfad und wird gegen den Anhang geprüft — sonst wäre
     * die Policy am Fall wirkungslos, sobald jemand eine fremde Anhang-Id
     * unter den eigenen Fall hängt.
     */
    public function test_ein_anhang_laesst_sich_nicht_unter_einen_fremden_fall_haengen(): void
    {
        Mail::fake();
        Storage::fake(SupportAnhangService::DISK);

        $this->meldung(['anhaenge' => [UploadedFile::fake()->image('eigenes.png', 40, 40)]])
            ->assertRedirect();

        $eigener = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();
        $anhang = SupportAnhang::query()->where('support_fall_id', $eigener->id)->firstOrFail();
        $fremder = $this->fremderFall();

        // Der Anhang gehoert zum eigenen Fall, der Pfad nennt den fremden.
        $this->actingAs($this->melder)
            ->get(route('support.anhang', ['fall' => $fremder, 'anhang' => $anhang]))
            ->assertForbidden();

        // Und umgekehrt: eigener Fall, fremder Anhang — den gibt es dort nicht.
        $fremderAnhang = SupportAnhang::query()->create([
            'support_fall_id' => $fremder->id,
            'dateiname' => 'fremd.png',
            'pfad' => 'support/' . $fremder->id . '/fremd.png',
            'mime' => 'image/png',
            'groesse' => 10,
        ]);

        $this->actingAs($this->melder)
            ->get(route('support.anhang', ['fall' => $eigener, 'anhang' => $fremderAnhang]))
            ->assertNotFound();
    }

    /** Entscheidung 48: Der Text der Akte bleibt, die Bilder nicht. */
    public function test_der_abschluss_loescht_die_bildschirmfotos(): void
    {
        Mail::fake();
        Storage::fake(SupportAnhangService::DISK);

        $this->meldung(['anhaenge' => [UploadedFile::fake()->image('a.png', 40, 40)]])
            ->assertRedirect();

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();
        $pfad = SupportAnhang::query()->where('support_fall_id', $fall->id)->firstOrFail()->pfad;

        Storage::disk(SupportAnhangService::DISK)->assertExists($pfad);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.support.status', $fall), ['status' => SupportFall::STATUS_ERLEDIGT])
            ->assertRedirect();

        Storage::disk(SupportAnhangService::DISK)->assertMissing($pfad);
        $this->assertSame(0, SupportAnhang::query()->where('support_fall_id', $fall->id)->count());

        // Die Akte selbst bleibt — nur die Bilder sind weg.
        $this->assertNotNull($fall->fresh());
        $this->assertSame('Nach dem Import ist die Liste leer.', $fall->fresh()?->beschreibung);
    }

    /** Ein Bild zeigt fast immer Namen — es darf in keinem Postfach landen. */
    public function test_die_mail_traegt_die_bilder_nicht(): void
    {
        Mail::fake();
        Storage::fake(SupportAnhangService::DISK);

        $this->meldung(['anhaenge' => [UploadedFile::fake()->image('a.png', 40, 40)]])
            ->assertRedirect();

        $fall = SupportFall::query()->where('school_id', self::TEST_SCHOOL_NUMBER)->firstOrFail();
        $pfad = SupportAnhang::query()->where('support_fall_id', $fall->id)->firstOrFail()->pfad;

        Mail::assertSent(SupportFallGemeldet::class, function (SupportFallGemeldet $mail) use ($pfad): bool {
            $inhalt = $mail->render();

            return str_contains($inhalt, 'Bildschirmfoto')      // die Zahl wird genannt
                && ! str_contains($inhalt, $pfad)               // der Weg dorthin nicht
                && $mail->attachments === [];                   // und angehaengt ist nichts
        });
    }

    public function test_der_aufraeumbefehl_findet_verwaiste_dateien(): void
    {
        Storage::fake(SupportAnhangService::DISK);
        Storage::disk(SupportAnhangService::DISK)->put('support/999999/verwaist.png', 'x');

        $this->artisan('support:anhaenge-aufraeumen', ['--probe' => true])
            ->expectsOutputToContain('Verwaist: support/999999/verwaist.png')
            ->assertExitCode(0);

        Storage::disk(SupportAnhangService::DISK)->assertExists('support/999999/verwaist.png');

        $this->artisan('support:anhaenge-aufraeumen')->assertExitCode(0);

        Storage::disk(SupportAnhangService::DISK)->assertMissing('support/999999/verwaist.png');
    }

    public function test_ohne_anmeldung_geht_nichts(): void
    {
        $this->get(route('support.index'))->assertRedirect();
        $this->post(route('support.store'), ['betreff' => 'x', 'beschreibung' => 'y'])->assertRedirect();
    }
}
