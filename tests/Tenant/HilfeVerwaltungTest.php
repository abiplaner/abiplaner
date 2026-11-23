<?php

namespace Tests\Tenant;

use App\Models\HilfeKapitel;
use App\Models\HilfeKapitelVersion;
use App\Models\School;
use App\Models\User;

/**
 * Redaktion der Hilfekapitel (Roadmap 6.3, zweite Stufe).
 *
 * Seit dem 29.08.2026 stehen die Texte in der zentralen Datenbank und lassen
 * sich vom Super-Admin bearbeiten. Damit entstehen zwei Pflichten, die es
 * vorher nicht gab:
 *
 * - **Jede Aenderung muss umkehrbar sein.** Die zentrale Datenbank sichert die
 *   Backup-Maske der Anwendung nicht; ohne Versionen waere ein versehentlich
 *   geleertes Kapitel endgueltig weg.
 * - **Redigiertes bleibt redigiert.** Ein Kapitel, das ein Mensch angefasst
 *   hat, darf der Seeder beim naechsten Deploy nicht zurueckfallen lassen.
 */
class HilfeVerwaltungTest extends TenantTestCase
{
    private const SLUG = 'zzz-verwaltungstest';

    private const PLANNER_EMAIL = 'hilfe-planner-9999@test.invalid';

    private const ADMIN_EMAIL = 'hilfe-sa-9999@test.invalid';

    protected School $school;

    protected User $planner;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raeumeAuf();

        $this->school = $this->createTestSchool();

        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

        $this->superAdmin = User::factory()->create([
            // Fester Name, kein erfundener: test_jede_maske_der_verwaltung_rendert
            // sucht ihn mit assertSee(..., false), also unescaped, in der
            // Versionsliste. Faker liefert gelegentlich einen Namen mit
            // Apostroph ("Prof. Rhett O'Hara V"); Blade schreibt daraus
            // &#039;, und der Test schlug dann als Einziger im ganzen Lauf
            // fehl — je nach Zufall. (31.08.2026)
            'name' => 'Hilfe Verwaltung Testkonto',
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

    /** Zentrale Tabellen rollt keine Transaktion zurueck. */
    private function raeumeAuf(): void
    {
        HilfeKapitel::query()->where('slug', 'like', 'zzz-%')->delete();
        User::query()->whereIn('email', [self::PLANNER_EMAIL, self::ADMIN_EMAIL])->delete();
    }

    private function kapitel(array $abweichungen = []): HilfeKapitel
    {
        return HilfeKapitel::query()->create(array_merge([
            'slug' => self::SLUG,
            'titel' => 'Erste Fassung',
            'gruppe' => 'Weiteres',
            'reihenfolge' => 9990,
            'routen' => null,
            'stichworte' => null,
            'markdown' => 'Erster Stand.',
            'grundfassung_hash' => null,
        ], $abweichungen));
    }

    /**
     * @return array<string, mixed>
     */
    private function formular(array $abweichungen = []): array
    {
        return array_merge([
            'slug' => self::SLUG,
            'titel' => 'Zweite Fassung',
            'gruppe' => 'Weiteres',
            'reihenfolge' => 9990,
            'routen' => '',
            'stichworte' => '',
            'markdown' => 'Zweiter Stand.',
        ], $abweichungen);
    }

    // ------------------------------------------------------------------

    public function test_nur_der_super_admin_kommt_in_die_verwaltung(): void
    {
        $this->actingAs($this->planner)->get(route('admin.hilfe.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('admin.hilfe.index'))->assertOk();
    }

    /**
     * Jede Maske der Verwaltung wird einmal gerendert.
     *
     * Ein Blade-Fehler faellt sonst erst dem Nutzer auf: PHPStan sieht Views
     * nicht an, und ein Zugriff auf ein Attribut, das es nicht gibt, ist dort
     * einfach `null` — bis er es nicht ist.
     */
    public function test_jede_maske_der_verwaltung_rendert(): void
    {
        $kapitel = $this->kapitel();

        $this->actingAs($this->superAdmin)->get(route('admin.hilfe.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('admin.hilfe.create'))->assertOk();
        $this->actingAs($this->superAdmin)
            ->get(route('admin.hilfe.edit', $kapitel))
            ->assertOk()
            ->assertSee('Erste Fassung', false);
        $this->actingAs($this->superAdmin)
            ->get(route('admin.hilfe.versionen', $kapitel))
            ->assertOk()
            ->assertSee('Noch keine Versionen', false);

        // Und mit einer Version darin — die Schleife muss auch laufen.
        $this->actingAs($this->superAdmin)
            ->put(route('admin.hilfe.update', $kapitel), $this->formular())
            ->assertSessionHasNoErrors();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.hilfe.versionen', $kapitel))
            ->assertOk()
            ->assertSee($this->superAdmin->name, false);
    }

    public function test_ein_kapitel_laesst_sich_anlegen(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.hilfe.store'), $this->formular(['titel' => 'Frisch angelegt']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $kapitel = HilfeKapitel::query()->where('slug', self::SLUG)->first();

        $this->assertNotNull($kapitel);
        $this->assertSame('Frisch angelegt', $kapitel->titel);
        // Ohne Eintrag bleibt das Feld null statt leerer Zeichenkette — sonst
        // erzeugte ein leeres Praefix spaeter einen Treffer auf alles.
        $this->assertNull($kapitel->routen);
    }

    public function test_ein_doppelter_schluessel_wird_abgewiesen(): void
    {
        $this->kapitel();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.hilfe.store'), $this->formular())
            ->assertSessionHasErrors('slug');
    }

    public function test_ein_schluessel_mit_sonderzeichen_wird_abgewiesen(): void
    {
        // Der Slug steht in der Adresse und ist dort auf [a-z0-9-] beschraenkt.
        // Ein Kapitel mit Punkt oder Umlaut waere angelegt, aber nicht aufrufbar.
        $this->actingAs($this->superAdmin)
            ->post(route('admin.hilfe.store'), $this->formular(['slug' => 'Kapitel Über.Alles']))
            ->assertSessionHasErrors('slug');
    }

    /**
     * Bearbeiten sichert die bisherige Fassung — nicht die neue.
     */
    public function test_bearbeiten_schreibt_die_bisherige_fassung_weg(): void
    {
        $kapitel = $this->kapitel(['grundfassung_hash' => str_repeat('a', 40)]);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.hilfe.update', $kapitel), $this->formular())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $kapitel->refresh();

        $this->assertSame('Zweiter Stand.', $kapitel->markdown);
        $this->assertNull(
            $kapitel->grundfassung_hash,
            'Nach dem Bearbeiten muss das Kapitel als redigiert gelten, damit der Seeder es in Ruhe laesst.'
        );

        $versionen = $kapitel->versionen()->get();

        $this->assertCount(1, $versionen);
        $this->assertSame('Erster Stand.', $versionen->first()?->markdown, 'Gesichert wird der Stand VOR der Aenderung.');
        $this->assertSame('Erste Fassung', $versionen->first()?->titel);
        $this->assertSame($this->superAdmin->name, $versionen->first()?->benutzername);
    }

    /**
     * Zurueckholen stellt wieder her — und sichert vorher, damit auch das
     * Zurueckholen umkehrbar ist.
     */
    public function test_eine_fassung_laesst_sich_zurueckholen(): void
    {
        $kapitel = $this->kapitel();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.hilfe.update', $kapitel), $this->formular())
            ->assertSessionHasNoErrors();

        $version = $kapitel->versionen()->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.hilfe.zurueckholen', [$kapitel, $version]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $kapitel->refresh();

        $this->assertSame('Erster Stand.', $kapitel->markdown);
        $this->assertSame('Erste Fassung', $kapitel->titel);
        $this->assertCount(2, $kapitel->versionen()->get(), 'Auch vor dem Zurueckholen wird gesichert.');
    }

    /** Eine Version eines anderen Kapitels darf nicht zurueckgeholt werden. */
    public function test_eine_fremde_version_wird_abgewiesen(): void
    {
        $eines = $this->kapitel();
        $anderes = $this->kapitel(['slug' => 'zzz-verwaltungstest-zwei', 'reihenfolge' => 9991]);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.hilfe.update', $eines), $this->formular())
            ->assertSessionHasNoErrors();

        $fremd = $eines->versionen()->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.hilfe.zurueckholen', [$anderes, $fremd]))
            ->assertNotFound();
    }

    public function test_loeschen_entfernt_das_kapitel_samt_versionen(): void
    {
        $kapitel = $this->kapitel();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.hilfe.update', $kapitel), $this->formular())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $kapitel->versionen()->count());

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.hilfe.destroy', $kapitel))
            ->assertRedirect();

        $this->assertNull(HilfeKapitel::query()->where('slug', self::SLUG)->first());
        $this->assertSame(
            0,
            HilfeKapitelVersion::query()->where('hilfe_kapitel_id', $kapitel->id)->count(),
            'Die Versionen fallen per Fremdschluessel mit.'
        );
    }

    /**
     * Die Vorschau benutzt dieselbe Umwandlung wie die Hilfe — und entfernt
     * rohes HTML. Das war richtig, als die Kapitel Dateien im Repo waren; seit
     * sie ueber eine Maske hereinkommen, ist es die Absicherung des Editors.
     */
    public function test_die_vorschau_rendert_und_entfernt_rohes_html(): void
    {
        $antwort = $this->actingAs($this->superAdmin)->postJson(route('admin.hilfe.vorschau'), [
            'markdown' => "## Titel\n\n<script>alert(1)</script>\n\nText mit **Fettdruck**.",
        ]);

        $antwort->assertOk();

        $html = (string) $antwort->json('html');

        $this->assertStringContainsString('<h2>Titel</h2>', $html);
        $this->assertStringContainsString('<strong>Fettdruck</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    /**
     * Der Befehl meldet eine Luecke — und zwar gegen den echten Bestand, nicht
     * gegen die Grundfassung im Repo. Genau dafuer gibt es ihn.
     */
    public function test_hilfe_pruefen_meldet_eine_geloeschte_abdeckung(): void
    {
        $this->artisan('hilfe:pruefen')->assertExitCode(0);

        $kapitel = HilfeKapitel::query()->where('slug', 'kolloquium-planung')->firstOrFail();
        $inhalt = $kapitel->inhaltFuerVersion();
        $hash = $kapitel->grundfassung_hash;

        try {
            $kapitel->update(['routen' => null]);

            $this->artisan('hilfe:pruefen')->assertExitCode(1);
        } finally {
            $kapitel->update($inhalt + ['grundfassung_hash' => $hash]);
        }
    }
}
