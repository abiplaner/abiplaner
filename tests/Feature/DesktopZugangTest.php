<?php

namespace Tests\Feature;

use App\Http\Middleware\DesktopZugang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Der Riegel der Desktop-App: Mit gesetztem Startschlüssel antwortet die
 * lokale Instanz nur dem Fenster, das ihn kennt.
 */
class DesktopZugangTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'start-schluessel-fuer-den-test';

    public function test_ohne_startschluessel_ist_alles_wie_immer(): void
    {
        config(['abiplaner.desktop_token' => '']);

        $this->get('/login')->assertOk();
    }

    public function test_ohne_cookie_wird_abgewiesen(): void
    {
        config(['abiplaner.desktop_token' => self::TOKEN]);

        $this->get('/login')->assertForbidden();
        $this->get('/')->assertForbidden();
    }

    public function test_falscher_schluessel_setzt_kein_cookie(): void
    {
        config(['abiplaner.desktop_token' => self::TOKEN]);

        $this->get('/desktop/start?token=falsch')
            ->assertForbidden()
            ->assertCookieMissing(DesktopZugang::COOKIE);
    }

    public function test_richtiger_schluessel_oeffnet_die_tuer(): void
    {
        config(['abiplaner.desktop_token' => self::TOKEN]);

        $this->get('/desktop/start?token=' . self::TOKEN)
            ->assertRedirect('/')
            ->assertPlainCookie(DesktopZugang::COOKIE, hash('sha256', self::TOKEN));

        $this->withUnencryptedCookie(DesktopZugang::COOKIE, hash('sha256', self::TOKEN))
            ->get('/login')
            ->assertOk();
    }
}
