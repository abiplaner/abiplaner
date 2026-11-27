<?php

namespace Tests\Tenant;

use App\Http\Middleware\DesktopZugang;
use App\Models\GlobalCalendarEvent;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Rückmeldungen zur Einzelplatz-Version (27.11.2026): Sicherungsseite,
 * Zurücksetzen, Passwort vergessen, Rollen Schuladmin/Planer, deutsches
 * Profil, Schuljahr in Verwaltung und globalem Kalender.
 */
class EinzelplatzVerwaltungTest extends TenantTestCase
{
    private const TOKEN = 'testtoken-einzelplatz';

    protected School $school;

    protected User $admin;

    protected User $planer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 10:00:00');

        $this->school = $this->createTestSchool();
        $this->admin = User::factory()->create([
            'name' => 'Ada Admin',
            'role' => 'admin',
            'school_id' => $this->school->school_number,
        ]);
        $this->planer = User::factory()->create([
            'name' => 'Paul Planer',
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Desktop-Modus: Token setzen und das Cookie der App mitschicken. */
    private function alsDesktop(): static
    {
        config(['abiplaner.desktop_token' => self::TOKEN]);

        return $this->withUnencryptedCookie(DesktopZugang::COOKIE, hash('sha256', self::TOKEN));
    }

    // --- Sicherungen -----------------------------------------------------

    public function test_die_sicherungsseite_steht_im_layout_und_zeigt_den_speicherort(): void
    {
        $this->actingAs($this->admin)->get(route('backups.index'))
            ->assertOk()
            // Navigation = Layout wurde gerendert (vorher hing ein verwaistes
            // @else an der Layout-Komponente, die Seite kam ohne CSS).
            ->assertSee('Verwaltung')
            ->assertSee('Speicherort der Sicherungen')
            ->assertSee('Abiplaner zurücksetzen')
            ->assertDontSee('Im Explorer öffnen');
    }

    public function test_in_der_desktop_app_gibt_es_den_knopf_zum_ordner(): void
    {
        $antwort = $this->alsDesktop()->actingAs($this->admin)->get(route('backups.index'))->assertOk();
        $antwort->assertSee(match (PHP_OS_FAMILY) {
            'Windows' => 'Im Explorer öffnen',
            'Darwin' => 'Im Finder öffnen',
            default => 'Ordner öffnen',
        });
    }

    public function test_ordner_oeffnen_gibt_es_nur_in_der_desktop_app(): void
    {
        $this->actingAs($this->admin)->post(route('backups.ordner'))->assertNotFound();
    }

    public function test_planer_kommen_nicht_an_die_sicherungen(): void
    {
        $this->actingAs($this->planer)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($this->planer)->post(route('backups.zuruecksetzen'), [
            'bestaetigung' => 'ZURÜCKSETZEN', 'password' => 'password',
        ])->assertForbidden();
    }

    public function test_zuruecksetzen_verlangt_wort_und_passwort(): void
    {
        $this->actingAs($this->admin)->post(route('backups.zuruecksetzen'), [
            'bestaetigung' => 'zurücksetzen', 'password' => 'falsch',
        ])->assertSessionHasErrors(['bestaetigung', 'password']);

        $this->assertTrue(School::query()->exists());
    }

    // --- Passwort vergessen ----------------------------------------------

    public function test_passwort_vergessen_in_der_desktop_app_setzt_direkt(): void
    {
        $this->alsDesktop()->get(route('password.request'))
            ->assertOk()
            ->assertSee('Ada Admin')
            ->assertSee('Neues Passwort');

        $this->alsDesktop()->post(route('password.email'), [
            'user_id' => $this->planer->id,
            'password' => 'Neu-Passwort-2026',
            'password_confirmation' => 'Neu-Passwort-2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Neu-Passwort-2026', (string) $this->planer->fresh()?->password));
    }

    public function test_passwort_vergessen_auf_dem_server_schickt_eine_mail(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Link senden')
            ->assertDontSee('Ada Admin');

        // Unbekannte Adresse: dieselbe Antwort, kein Hinweis auf vorhandene Konten.
        $this->post(route('password.email'), ['email' => 'niemand@example.org'])
            ->assertSessionHas('status');
    }

    public function test_der_befehl_setzt_ein_passwort_und_macht_zum_schuladmin(): void
    {
        $this->artisan('abiplaner:passwort', ['email' => $this->planer->email, '--admin' => true])
            ->expectsQuestion('Neues Passwort', 'Befehl-Passwort-2026')
            ->expectsQuestion('Passwort wiederholen', 'Befehl-Passwort-2026')
            ->assertExitCode(0);

        $planer = $this->planer->fresh();
        $this->assertTrue($planer?->isAdmin());
        $this->assertTrue(Hash::check('Befehl-Passwort-2026', (string) $planer?->password));
    }

    // --- Rollen ------------------------------------------------------------

    public function test_der_schuladmin_plant_und_verwaltet(): void
    {
        $this->assertTrue($this->admin->isAdmin());
        $this->assertTrue($this->admin->isPlanner());
        $this->assertFalse($this->planer->isAdmin());

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Verwaltung');
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->assertSee('Schuladmin');
        $this->actingAs($this->planer)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_der_letzte_schuladmin_bleibt(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'planner',
            'is_active' => 1,
        ])->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()?->isAdmin());
    }

    public function test_die_einrichtung_macht_den_ersten_benutzer_zum_schuladmin(): void
    {
        User::query()->delete();
        School::query()->delete();

        $this->post(route('ersteinrichtung.store'), [
            'school_number' => '1234',
            'name' => 'Gymnasium Neu',
            'short_name' => 'GN',
            'admin_name' => 'Erste Person',
            'admin_email' => 'erste@example.org',
            'password' => 'Erst-Passwort-2026',
            'password_confirmation' => 'Erst-Passwort-2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('admin', User::query()->where('email', 'erste@example.org')->value('role'));
    }

    // --- Profil, Schuljahre --------------------------------------------------

    public function test_das_profil_ist_deutsch(): void
    {
        $this->actingAs($this->planer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profilangaben')
            ->assertSee('Passwort ändern')
            ->assertSee('Konto löschen')
            ->assertDontSee('Profile Information');
    }

    public function test_schriftliche_pruefungen_heben_das_laufende_abitur_hervor(): void
    {
        $this->actingAs($this->admin)->get(route('admin.written-exam-slots.index'))
            ->assertOk()
            ->assertSee('Abitur 2027');
    }

    public function test_globaler_kalender_zeigt_das_gewaehlte_schuljahr(): void
    {
        $termin = fn (string $titel, string $datum) => GlobalCalendarEvent::query()->create([
            'title' => $titel,
            'event_date' => $datum,
            'event_type' => 'info',
        ]);
        $termin('Termin im laufenden Schuljahr', '2027-03-10');
        $termin('Termin im Vorjahr', '2026-03-10');

        $this->actingAs($this->admin)->get(route('admin.global-calendar.index'))
            ->assertOk()
            ->assertSee('2026/27')
            ->assertSee('Termin im laufenden Schuljahr')
            ->assertDontSee('Termin im Vorjahr');

        $this->actingAs($this->admin)->get(route('admin.global-calendar.index', ['schuljahr' => '2025/26']))
            ->assertOk()
            ->assertSee('Termin im Vorjahr')
            ->assertDontSee('Termin im laufenden Schuljahr');

        // „Alle“ zeigt auch das Vorjahr (die Stammdaten bringen viele Termine
        // mit; der spätere landet deshalb auf einer Folgeseite).
        $this->actingAs($this->admin)->get(route('admin.global-calendar.index', ['schuljahr' => 'alle']))
            ->assertOk()
            ->assertSee('Termin im Vorjahr');
    }
}
