<?php

namespace Tests\Feature;

use App\Models\SupportFall;
use App\Models\SupportNachricht;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aufbewahrungsfristen der Support-Fallakten (Modul 6.4, Entscheidung 53).
 *
 * Art. 5 Abs. 1 lit. e DSGVO verlangt eine Speicherbegrenzung. Umgesetzt ist
 * sie als Befehl `support:fallakten-aufraeumen` mit zwei Stufen:
 *
 *  – nach 90 Tagen fallen die personenbezogenen Kontextblöcke
 *    (`aktivitaeten`, `fehler`); `technik` und `einrichtung` bleiben,
 *  – nach 24 Monaten fällt die Akte selbst.
 *
 * **Beide Fristen zählen ab `geschlossen_am`.** Der wichtigste Test dieser
 * Datei ist deshalb der letzte: Ein *offener* Fall bleibt unangetastet, auch
 * nach drei Jahren. Eine Löschregel, die auch offene Fälle träfe, würde einen
 * laufenden Vorgang unter den Händen des Supports wegräumen.
 *
 * Ein Löschbefehl braucht seine Gegenproben zwingend: Jeder Test hier hat
 * einen Zwilling knapp diesseits der Frist. Ein Befehl, der zu viel löscht,
 * fällt sonst erst auf, wenn die Daten weg sind.
 */
class SupportAufbewahrungTest extends TestCase
{
    use RefreshDatabase;

    private const SCHULNUMMER = '9999';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // `support_faelle.school_id` hängt am Fremdschlüssel auf
        // `schools.school_number`. Die Schule entsteht per Query und NICHT
        // über das Modell: Der SchoolObserver zöge sonst die ganze
        // Tenancy-Pipeline nach sich (Datenbank anlegen, migrieren, seeden) —
        // für einen Test, der nie einen Mandanten betritt.
        DB::table('schools')->insert([
            'school_number' => self::SCHULNUMMER,
            'name' => 'Testschule Aufbewahrung',
            'short_name' => 'TESTAUF',
            'state' => 'BY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Ein vollständig erhobener Kontext — die vier Blöcke, wie sie
     * SupportKontextService::erheben() liefert.
     *
     * @return array<string, mixed>
     */
    private function vollerKontext(): array
    {
        return [
            'technik' => ['schule' => '9999', 'route' => 'planning.index'],
            'einrichtung' => ['fortschritt' => ['erledigt' => 3, 'gesamt' => 10]],
            'aktivitaeten' => [
                ['zeitpunkt' => '01.01.2026', 'aktion' => 'student_updated', 'beschreibung' => 'Huber, Anna'],
            ],
            'fehler' => ['id' => 'abc', 'klasse' => 'RuntimeException', 'meldung' => 'kaputt'],
        ];
    }

    /**
     * @param  array<string, mixed>  $abweichungen
     */
    private function fall(array $abweichungen = []): SupportFall
    {
        // `created_at` steht nicht in $fillable — ein Wert im create()-Array
        // würde stillschweigend verworfen und die Zeile trüge das Heute. Genau
        // daran hätte der Test zum Hinweis auf lange offene Fälle nichts
        // gemessen.
        $angelegt = $abweichungen['created_at'] ?? null;
        unset($abweichungen['created_at']);

        /** @var SupportFall $fall */
        $fall = SupportFall::query()->create(array_merge([
            'school_id' => self::SCHULNUMMER,
            'schule_name' => 'Testschule',
            'melder_name' => 'Test Melder',
            'melder_email' => 'melder@test.invalid',
            'betreff' => 'Eine Meldung',
            'beschreibung' => 'Etwas ist kaputt.',
            'status' => SupportFall::STATUS_ERLEDIGT,
            'kontext' => $this->vollerKontext(),
            'geschlossen_am' => now()->subDays(100),
        ], $abweichungen));

        if ($angelegt !== null) {
            $fall->forceFill(['created_at' => $angelegt])->save();
        }

        return $fall;
    }

    private function aufraeumen(bool $probe = false): int
    {
        return $this->artisan(
            'support:fallakten-aufraeumen' . ($probe ? ' --probe' : '')
        )->run();
    }

    // ── Erste Frist: der Kontext ───────────────────────────────────────────

    #[Test]
    public function nach_neunzig_tagen_fallen_die_personenbezogenen_bloecke(): void
    {
        $fall = $this->fall(['geschlossen_am' => now()->subDays(91)]);

        $this->assertSame(0, $this->aufraeumen());

        $kontext = $fall->fresh()?->kontext;

        $this->assertIsArray($kontext);
        $this->assertArrayNotHasKey('aktivitaeten', $kontext);
        $this->assertArrayNotHasKey('fehler', $kontext);

        // Was bleibt, trägt keinen Personenbezug — und beantwortet weiter,
        // welches Modul Support erzeugt.
        $this->assertArrayHasKey('technik', $kontext);
        $this->assertArrayHasKey('einrichtung', $kontext);

        // Ein leeres Feld muss sagen können, warum es leer ist.
        $this->assertSame(now()->toDateString(), $fall->fresh()?->kontextGekuerztAm());
    }

    /** Gegenprobe: einen Tag vorher steht noch alles. */
    #[Test]
    public function kurz_vor_der_frist_bleibt_der_kontext_vollstaendig(): void
    {
        $fall = $this->fall(['geschlossen_am' => now()->subDays(89)]);

        $this->aufraeumen();

        $kontext = $fall->fresh()?->kontext;

        $this->assertIsArray($kontext);
        $this->assertArrayHasKey('aktivitaeten', $kontext);
        $this->assertArrayHasKey('fehler', $kontext);
        $this->assertNull($fall->fresh()?->kontextGekuerztAm());
    }

    /**
     * Der Lauf ist idempotent: Ein zweiter Durchgang findet nichts mehr und
     * überschreibt insbesondere nicht das Datum der ersten Kürzung.
     */
    #[Test]
    public function ein_zweiter_lauf_kuerzt_nicht_noch_einmal(): void
    {
        $fall = $this->fall(['geschlossen_am' => now()->subDays(120)]);

        $this->aufraeumen();
        $ergebnis = $this->artisan('support:fallakten-aufraeumen');

        $ergebnis->expectsOutput('Nichts aufzuräumen.')->run();

        $this->assertNotNull($fall->fresh()?->kontextGekuerztAm());
    }

    // ── Zweite Frist: die Akte ─────────────────────────────────────────────

    #[Test]
    public function nach_vierundzwanzig_monaten_faellt_die_fallakte(): void
    {
        $fall = $this->fall(['geschlossen_am' => now()->subMonths(25)]);

        SupportNachricht::query()->create([
            'support_fall_id' => $fall->id,
            'autor_rolle' => SupportNachricht::ROLLE_SUPPORT,
            'text' => 'Wir sehen es uns an.',
        ]);

        $this->aufraeumen();

        $this->assertNull($fall->fresh());
        $this->assertSame(
            0,
            SupportNachricht::query()->where('support_fall_id', $fall->id)->count(),
            'Mit der Akte fallen ihre Nachrichten.'
        );
    }

    /** Gegenprobe: einen Monat vorher steht die Akte noch — nur gekürzt. */
    #[Test]
    public function kurz_vor_der_frist_bleibt_die_fallakte(): void
    {
        $fall = $this->fall(['geschlossen_am' => now()->subMonths(23)]);

        $this->aufraeumen();

        $this->assertNotNull($fall->fresh());
        $this->assertNotNull(
            $fall->fresh()?->kontextGekuerztAm(),
            'Die erste Frist hat sie längst erreicht.'
        );
    }

    // ── Die tragende Gegenprobe ────────────────────────────────────────────

    /**
     * Alle Fristen hängen an `geschlossen_am`. Ein Fall, der noch bearbeitet
     * wird, ist nicht alt — er ist offen. Würde die Regel ihn treffen, räumte
     * der Lauf einen laufenden Vorgang unter den Händen des Supports weg.
     */
    #[Test]
    public function ein_offener_fall_bleibt_auch_nach_drei_jahren_unangetastet(): void
    {
        $fall = $this->fall([
            'status' => SupportFall::STATUS_IN_ARBEIT,
            'geschlossen_am' => null,
            'created_at' => now()->subYears(3),
        ]);

        $this->aufraeumen();

        $frisch = $fall->fresh();

        $this->assertNotNull($frisch);
        $this->assertIsArray($frisch->kontext);
        $this->assertArrayHasKey('aktivitaeten', $frisch->kontext);
        $this->assertArrayHasKey('fehler', $frisch->kontext);
        $this->assertNull($frisch->kontextGekuerztAm());
    }

    // ── Vorkehrungen ───────────────────────────────────────────────────────

    #[Test]
    public function die_probe_aendert_nichts(): void
    {
        $zuKuerzen = $this->fall(['geschlossen_am' => now()->subDays(100)]);
        $zuLoeschen = $this->fall(['geschlossen_am' => now()->subMonths(30)]);

        $this->assertSame(0, $this->aufraeumen(probe: true));

        $this->assertNotNull($zuLoeschen->fresh(), 'Die Probe darf nichts löschen.');
        $this->assertNull($zuKuerzen->fresh()?->kontextGekuerztAm());
    }

    /**
     * Eine Frist von null hieße „sofort beim Abschluss löschen" — fast sicher
     * ein Konfigurationsfehler, und nicht rückgängig zu machen. Der Befehl
     * bricht ab, statt ihn auszuführen.
     */
    #[Test]
    public function eine_frist_von_null_bricht_ab_ohne_zu_loeschen(): void
    {
        config(['support.aufbewahrung.fallakte_monate' => 0]);

        $fall = $this->fall(['geschlossen_am' => now()->subYears(5)]);

        $this->assertSame(1, $this->aufraeumen(), 'Der Befehl muss scheitern.');
        $this->assertNotNull($fall->fresh(), 'Und dabei nichts anfassen.');
    }

    /**
     * Ein Fall ohne erhobenen Kontext (von Hand angelegt, oder die Erhebung
     * ist am kaputten Mandanten gescheitert) darf den Lauf nicht umwerfen.
     */
    #[Test]
    public function ein_fall_ohne_kontext_wirft_den_lauf_nicht_um(): void
    {
        $fall = $this->fall(['kontext' => null, 'geschlossen_am' => now()->subDays(200)]);

        $this->assertSame(0, $this->aufraeumen());
        $this->assertNotNull($fall->fresh());
    }

    // ── Der Hinweis auf lange offene Fälle ─────────────────────────────────

    /**
     * Damit „offen lassen" nicht der bequeme Weg wird, die Frist zu umgehen.
     */
    #[Test]
    public function das_dashboard_mahnt_lange_offene_faelle_an(): void
    {
        $this->markTestSkipped('Support-Meldungen sind abgeschaltet (Desktop-Fassung, seit 22.11.2026).');

        $this->fall([
            'status' => SupportFall::STATUS_NEU,
            'geschlossen_am' => null,
            'created_at' => now()->subMonths(13),
        ]);

        $this->adminAntwort()
            ->assertOk()
            ->assertSee('seit mehr als 12 Monaten offen', false);
    }

    /** Gegenprobe: ein frischer offener Fall löst den Hinweis nicht aus. */
    #[Test]
    public function ein_frischer_offener_fall_loest_den_hinweis_nicht_aus(): void
    {
        $this->markTestSkipped('Support-Meldungen sind abgeschaltet (Desktop-Fassung, seit 22.11.2026).');

        $this->fall([
            'status' => SupportFall::STATUS_NEU,
            'geschlossen_am' => null,
            'created_at' => now()->subMonths(2),
        ]);

        $this->adminAntwort()
            ->assertOk()
            ->assertDontSee('Monaten offen', false);
    }

    /**
     * Und in der Akte selbst: Ein gekürzter Kontext sagt, warum er leer ist.
     */
    #[Test]
    public function die_akte_erklaert_den_gekuerzten_kontext(): void
    {
        $this->markTestSkipped('Support-Meldungen sind abgeschaltet (Desktop-Fassung, seit 22.11.2026).');

        $fall = $this->fall(['geschlossen_am' => now()->subDays(100)]);

        $this->aufraeumen();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.support.show', $fall))
            ->assertOk()
            ->assertSee('nach Ablauf der', false)
            ->assertDontSee('Außer den technischen Angaben oben wird nichts mitgeschickt.', false);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'school_id' => null,
        ]);
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function adminAntwort()
    {
        return $this->actingAs($this->superAdmin())->get(route('admin.support.index'));
    }
}
