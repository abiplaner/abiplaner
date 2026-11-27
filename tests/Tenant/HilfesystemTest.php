<?php

namespace Tests\Tenant;

use App\Models\HilfeKapitel;
use App\Models\HilfeKapitelVersion;
use App\Models\School;
use App\Models\User;
use App\Services\HilfeDeckungsPruefung;
use App\Services\HilfeService;
use Database\Seeders\HilfeKapitelSeeder;
use Illuminate\Support\Facades\Route;

/**
 * Hilfesystem und Handbuch (Roadmap 6.3).
 *
 * Der Kern dieser Tests ist die **Deckung**: Jede Maske, auf der ein Anwender
 * landen kann, muss am „?" ein Kapitel finden — und jedes Kapitel muss auf
 * Routen zeigen, die es wirklich gibt. Beides läuft sonst still auseinander.
 *
 * Seit die Kapitel in der Datenbank stehen (29.08.2026), prüft die CI das
 * gegen die **Grundfassung** aus `database/seeders/data/hilfe/` — den Bestand
 * einer laufenden Installation prüft `php artisan hilfe:pruefen`. Beide rufen
 * dieselbe Stelle, `HilfeDeckungsPruefung`; die Filterlisten gibt es einmal.
 *
 * Dazu die Redaktion: Der Seeder darf nichts überschreiben, was ein Mensch
 * geändert hat, und jede Änderung muss sich zurückholen lassen — die zentrale
 * Datenbank sichert die Backup-Maske der Anwendung nicht.
 */
class HilfesystemTest extends TenantTestCase
{
    private const TEST_USER_EMAIL = 'hilfe-9999@test.invalid';

    private const ADMIN_EMAIL = 'hilfe-admin-9999@test.invalid';

    /** Slug der Wegwerf-Kapitel dieses Tests — wird in tearDown entfernt. */
    private const TEST_SLUG = 'zzz-testkapitel';

    protected School $school;

    protected User $planner;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raeumeAuf();

        $this->school = $this->createTestSchool();

        $this->planner = User::factory()->create([
            'email' => self::TEST_USER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

        $this->superAdmin = User::factory()->create([
            'email' => self::ADMIN_EMAIL,
            'role' => 'admin',
            'school_id' => null,
        ]);

    }

    protected function tearDown(): void
    {

        $this->raeumeAuf();

        parent::tearDown();
    }

    /**
     * Die Kapitel liegen ZENTRAL und werden von keiner Transaktion
     * zurückgerollt — Wegwerf-Datensätze müssen deshalb von Hand fallen.
     */
    private function raeumeAuf(): void
    {
        HilfeKapitel::query()->where('slug', 'like', self::TEST_SLUG . '%')->delete();
        User::query()->whereIn('email', [self::TEST_USER_EMAIL, self::ADMIN_EMAIL])->delete();
    }

    private function hilfe(): HilfeService
    {
        $hilfe = app(HilfeService::class);
        $hilfe->neuLaden();

        return $hilfe;
    }

    private function wegwerfKapitel(array $abweichungen = []): HilfeKapitel
    {
        return HilfeKapitel::query()->create(array_merge([
            'slug' => self::TEST_SLUG,
            'titel' => 'Wegwerf-Kapitel',
            'gruppe' => 'Weiteres',
            'reihenfolge' => 9990,
            'routen' => null,
            'stichworte' => null,
            'markdown' => "Erster Absatz.\n\n## Ein Abschnitt\n\nZweiter Absatz.",
        ], $abweichungen));
    }

    // ------------------------------------------------------------------
    // Bestand und Deckung
    // ------------------------------------------------------------------

    public function test_es_gibt_kapitel(): void
    {
        $kapitel = $this->hilfe()->alle();

        $this->assertNotEmpty($kapitel, 'Die Grundfassung ist nicht eingespielt (Migration oder hilfe:einspielen).');

        foreach ($kapitel as $slug => $eintrag) {
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9-]+$/',
                $slug,
                "Der Slug '{$slug}' passt nicht zur Routenbeschraenkung [a-z0-9-]+ und waere nicht aufrufbar."
            );
            $this->assertNotSame('', trim($eintrag->titel));
            $this->assertNotSame('', trim($eintrag->markdown), "Kapitel '{$slug}' hat keinen Inhalt.");
        }
    }

    /**
     * Jede aufrufbare Seite hat ein Kapitel.
     *
     * Die Probe, die beim naechsten neuen Modul anschlaegt: Wer eine Maske
     * baut und kein Kapitel schreibt, bekommt am „?" einen leeren Kasten.
     */
    public function test_jede_seite_hat_ein_kapitel(): void
    {
        $luecken = app(HilfeDeckungsPruefung::class)->luecken();

        $this->assertSame(
            [],
            $luecken,
            "Fuer diese Seiten gibt es kein Hilfekapitel:\n  " . implode("\n  ", $luecken)
        );
    }

    /**
     * Unbenannte Routen verlangen kein Kapitel.
     *
     * `route:cache` tauft jede unbenannte Route auf `generated::<zufall>`. Das
     * Hilfesystem ordnet ueber Routennamen zu — ein Name, der bei jedem
     * Cache-Lauf ein anderer ist, kann kein Kapitel treffen.
     *
     * Gefunden nach dem Deploy auf Produktion, nicht lokal: Dort baut
     * `php artisan optimize` den Cache, hier nicht. Die Pruefung meldete
     * `/up` und ein unbenanntes `GET import/preview` als Luecke. Deshalb wird
     * der Fall hier von Hand hergestellt statt auf die Umgebung zu bauen.
     */
    public function test_unbenannte_routen_verlangen_kein_kapitel(): void
    {
        Route::get('/zzz-ohne-eigenen-namen', static fn (): string => 'x')
            ->name('generated::ZzzTestZufall');

        $seiten = app(HilfeDeckungsPruefung::class)->seitenrouten();

        $this->assertNotContains('generated::ZzzTestZufall', $seiten);
        $this->assertNotContains('generated::ZzzTestZufall', app(HilfeDeckungsPruefung::class)->luecken());
    }

    /** Die Gegenrichtung: ein umgetaufter Routenname macht ein Kapitel unerreichbar. */
    public function test_jedes_kapitel_zeigt_auf_echte_routen(): void
    {
        $tote = app(HilfeDeckungsPruefung::class)->toteVerweise();

        $this->assertSame([], $tote, "Tote Routenverweise in Kapiteln:\n  " . implode("\n  ", $tote));
    }

    /** Das laengste Praefix gewinnt — sonst schluckt ein allgemeines Kapitel das besondere. */
    public function test_das_laengste_routenpraefix_gewinnt(): void
    {
        $hilfe = $this->hilfe();

        $stundenplan = $hilfe->fuerRoute('timetable.view');
        $verfuegbarkeit = $hilfe->fuerRoute('timetable.teacher-availability');

        $this->assertNotNull($stundenplan);
        $this->assertNotNull($verfuegbarkeit);
        $this->assertNotSame(
            $stundenplan->slug,
            $verfuegbarkeit->slug,
            'timetable.teacher-availability muss das eigene Kapitel treffen, nicht das des Stundenplans.'
        );

        $this->assertNull($hilfe->fuerRoute(''));
        $this->assertNull($hilfe->fuerRoute('gibt.es.nicht'));
    }

    /**
     * Ein leeres Routenfeld darf nicht auf alles passen.
     *
     * Ein versehentliches Komma am Ende der Eingabe erzeugte sonst ein leeres
     * Praefix — und `str_starts_with($name, '.')` ist zwar falsch, aber
     * `$name === ''` waere es nicht. Die Liste faengt das ab.
     */
    public function test_ein_leeres_routenfeld_faengt_keine_route(): void
    {
        $this->wegwerfKapitel(['routen' => ' , , ']);

        $hilfe = $this->hilfe();
        $treffer = $hilfe->fuerRoute('planning.index');

        $this->assertNotNull($treffer);
        $this->assertNotSame(self::TEST_SLUG, $treffer->slug);
    }

    public function test_die_suche_findet_ueber_titel_und_text(): void
    {
        $hilfe = $this->hilfe();

        $this->assertSame([], $hilfe->suche('a'), 'Ein einzelnes Zeichen darf nichts liefern.');

        $treffer = $hilfe->suche('Nachteilsausgleich');
        $this->assertNotEmpty($treffer, 'Ein Begriff aus dem Fliesstext muss gefunden werden.');

        foreach ($treffer as $eintrag) {
            $this->assertInstanceOf(HilfeKapitel::class, $eintrag['kapitel']);
            $this->assertNotSame('', trim($eintrag['fundstelle']));
            $this->assertStringNotContainsString('**', $eintrag['fundstelle'], 'Die Fundstelle ist Klartext.');
        }

        $this->assertNotEmpty($hilfe->suche('kolloquium'), 'Die Suche achtet nicht auf Gross- und Kleinschreibung.');
    }

    // ------------------------------------------------------------------
    // Handbuch
    // ------------------------------------------------------------------

    public function test_das_handbuch_traegt_jedes_kapitel(): void
    {
        $hilfe = $this->hilfe();
        $handbuch = $hilfe->handbuchMarkdown('Musterschule');

        $this->assertStringContainsString('# AbiPlaner — Benutzerhandbuch', $handbuch);
        $this->assertStringContainsString('Musterschule', $handbuch);

        foreach ($hilfe->alle() as $kapitel) {
            $this->assertStringContainsString(
                '## ' . $kapitel->titel,
                $handbuch,
                "Kapitel '{$kapitel->slug}' fehlt im Handbuch."
            );
        }

        foreach (array_keys($hilfe->gruppen()) as $gruppe) {
            $this->assertStringContainsString('# ' . $gruppe, $handbuch);
        }
    }

    /** Im Handbuch rutscht jede Kapitelueberschrift eine Ebene tiefer. */
    public function test_die_ueberschriften_rutschen_im_handbuch_eine_ebene_tiefer(): void
    {
        $this->wegwerfKapitel();

        $handbuch = $this->hilfe()->handbuchMarkdown();

        $this->assertStringContainsString('### Ein Abschnitt', $handbuch);
        $this->assertStringNotContainsString("\n## Ein Abschnitt", $handbuch);
    }

    public function test_der_befehl_schreibt_das_handbuch(): void
    {
        $ziel = 'storage/app/handbuch-test-' . uniqid() . '.md';

        try {
            $this->artisan('handbuch:erzeugen', ['--ausgabe' => $ziel])->assertExitCode(0);

            $this->assertFileExists(base_path($ziel));
            $this->assertStringContainsString('## Inhalt', (string) file_get_contents(base_path($ziel)));
        } finally {
            if (file_exists(base_path($ziel))) {
                unlink(base_path($ziel));
            }
        }
    }

    // ------------------------------------------------------------------
    // Grundfassung und Seeder
    // ------------------------------------------------------------------

    /** Die Grundfassung im Repo muss lesbar sein — sonst startet keine neue Installation. */
    public function test_die_grundfassung_ist_lesbar(): void
    {
        $grundfassung = (new HilfeKapitelSeeder())->grundfassung();

        $this->assertNotEmpty($grundfassung, 'Unter database/seeders/data/hilfe/ liegt keine lesbare Kapiteldatei.');

        foreach ($grundfassung as $slug => $werte) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertNotSame('', trim($werte['titel']));
            $this->assertNotSame('', trim($werte['markdown']));
        }
    }

    /** Ein zweiter Lauf darf nichts anfassen. */
    public function test_der_seeder_ist_idempotent(): void
    {
        $seeder = new HilfeKapitelSeeder();
        $seeder->run();

        $this->assertSame(0, $seeder->bilanz['neu']);
        $this->assertSame(0, $seeder->bilanz['aktualisiert']);
        $this->assertGreaterThan(0, $seeder->bilanz['unveraendert']);
    }

    /**
     * Seit 27.11.2026 sind die Dateien die einzige Quelle: Eine Abweichung in
     * der Datenbank bringt der nächste Lauf auf den Stand der Datei zurück.
     */
    public function test_der_seeder_bringt_abweichende_kapitel_auf_den_stand_der_dateien(): void
    {
        $slug = array_key_first((new HilfeKapitelSeeder())->grundfassung());
        $this->assertNotNull($slug);

        $kapitel = HilfeKapitel::query()->where('slug', $slug)->firstOrFail();
        $vorher = $kapitel->markdown;
        $kapitel->update(['markdown' => $vorher . "\n\nVon Hand ergaenzter Absatz.", 'grundfassung_hash' => null]);

        $seeder = new HilfeKapitelSeeder();
        $seeder->run();

        $this->assertSame(1, $seeder->bilanz['aktualisiert']);
        $this->assertSame($vorher, $kapitel->fresh()?->markdown);
    }

    /** Ein Kapitel ohne Datei verschwindet beim nächsten Lauf. */
    public function test_der_seeder_entfernt_kapitel_ohne_datei(): void
    {
        HilfeKapitel::query()->create([
            'slug' => 'zzz-ohne-datei',
            'titel' => 'Verwaist',
            'gruppe' => 'Weiteres',
            'reihenfolge' => 9999,
            'markdown' => 'Nur in der Datenbank.',
        ]);

        $seeder = new HilfeKapitelSeeder();
        $seeder->run();

        $this->assertSame(1, $seeder->bilanz['entfernt']);
        $this->assertFalse(HilfeKapitel::query()->where('slug', 'zzz-ohne-datei')->exists());
    }

    /** Die Bearbeitungsmaske gibt es nicht mehr. */
    public function test_die_hilfe_laesst_sich_nicht_mehr_in_der_oberflaeche_bearbeiten(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.hilfe.index'));
        $this->actingAs($this->superAdmin)->get('/admin/hilfe')->assertNotFound();
    }

    /**
     * Der Befehl meldet eine Luecke — und zwar gegen den echten Bestand, nicht
     * gegen die Grundfassung im Repo (aus HilfeVerwaltungTest übernommen).
     */
    public function test_hilfe_pruefen_meldet_eine_geloeschte_abdeckung(): void
    {
        $this->artisan('hilfe:pruefen')->assertExitCode(0);

        $kapitel = HilfeKapitel::query()->where('slug', 'kolloquium-planung')->firstOrFail();
        $kapitel->update(['routen' => null]);

        $this->artisan('hilfe:pruefen')->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // Die Ausgaben in der Anwendung
    // ------------------------------------------------------------------

    public function test_die_uebersicht_und_die_suche_antworten(): void
    {
        $this->actingAs($this->planner)->get(route('hilfe.index'))->assertOk()->assertSee('Inhalt');
        $this->actingAs($this->planner)->get(route('hilfe.index', ['q' => 'Kolloquium']))->assertOk();
        $this->actingAs($this->planner)->get(route('hilfe.index', ['q' => 'zzzgibtesnicht']))->assertOk();
    }

    public function test_jedes_kapitel_laesst_sich_als_seite_oeffnen(): void
    {
        foreach ($this->hilfe()->alle() as $slug => $kapitel) {
            $this->actingAs($this->planner)
                ->get(route('hilfe.show', $slug))
                ->assertOk()
                ->assertSee($kapitel->titel, false);
        }

        $this->actingAs($this->planner)->get('/hilfe/gibt-es-nicht')->assertNotFound();
    }

    /** Ein „?", das gelegentlich einen Fehler zeigt, sieht kaputt aus. */
    public function test_das_panel_antwortet_immer(): void
    {
        $this->actingAs($this->planner)
            ->get(route('hilfe.panel', ['route' => 'planning.index']))
            ->assertOk()
            ->assertSee('Kolloquiumsplanung');

        $this->actingAs($this->planner)
            ->get(route('hilfe.panel', ['route' => 'gibt.es.nicht']))
            ->assertOk()
            ->assertSee('noch kein Kapitel hinterlegt');

        $this->actingAs($this->planner)->get(route('hilfe.panel'))->assertOk();
    }

    public function test_das_handbuch_kommt_als_pdf(): void
    {
        $antwort = $this->actingAs($this->planner)->get(route('hilfe.handbuch'));

        $antwort->assertOk();
        $antwort->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'abiplaner-handbuch.pdf',
            (string) $antwort->headers->get('content-disposition')
        );
    }

    /**
     * Die Hilfe liegt zentral und braucht keinen Mandanten — der Super-Admin
     * kommt deshalb hin. Vorher lag sie hinter der Tenancy, und ausgerechnet
     * derjenige, der die Texte schreibt, konnte sie nicht ansehen.
     */
    public function test_auch_der_super_admin_erreicht_die_hilfe(): void
    {
        $this->actingAs($this->superAdmin)->get(route('hilfe.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('hilfe.handbuch'))->assertOk();
    }

    public function test_die_hilfe_verlangt_eine_anmeldung(): void
    {
        $this->get(route('hilfe.index'))->assertRedirect();
    }
}
