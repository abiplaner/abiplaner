<?php

namespace Tests;

use App\Models\School;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF-Validierung in Tests deaktivieren: Die automatische Test-
        // Umgehung greift nicht zuverlässig, POST-Requests bekämen sonst 419.
        // Ab Laravel 13 ist die registrierte Middleware PreventRequestForgery.
        $this->withoutMiddleware(PreventRequestForgery::class);

        // Session-Treiber 'array': Die .env stellt 'database' ein, und ohne
        // migrierte sessions-Tabelle bräche StartSession mit einem 500 ab.
        Config::set('session.driver', 'array');

        $this->grundschuleAnlegen();
    }

    /**
     * Eine eingerichtete Schule für Tests mit RefreshDatabase. Ohne sie
     * leitet ErsteinrichtungErzwingen jede Seite zum Einrichtungsassistenten.
     * Tests, die selbst eine Schule anlegen (TenantTestCase) oder die
     * Ersteinrichtung prüfen, setzen $mitSchule = false.
     *
     * Bewusst in setUp() und nicht in afterRefreshingDatabase(): Den Haken
     * überdeckt der leere Trait-Rumpf in jeder Klasse, die RefreshDatabase
     * selbst einbindet.
     */
    protected bool $mitSchule = true;

    private function grundschuleAnlegen(): void
    {
        if (! $this->mitSchule
            || ! in_array(RefreshDatabase::class, class_uses_recursive($this), true)
            || School::query()->exists()) {
            return;
        }

        School::create([
            'school_number' => '0001',
            'name' => 'Grundschule der Tests',
            'short_name' => 'TEST',
            'state' => 'BY',
            'is_active' => true,
        ]);
    }
}
