<?php

namespace Tests\Tenant;

use App\Models\School;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Basisklasse für die Schul-Tests.
 *
 * Seit 22.11.2026 gibt es keine Mandanten-Datenbanken mehr: Jeder Test läuft
 * gegen eine frische SQLite-:memory:-Datenbank mit Basisschema und
 * Stammdaten (DatabaseSeeder). Die frühere Sperre gegen parallele Läufe und
 * das Aufräumen der Testschule in der echten MariaDB entfallen damit.
 *
 * Name und Verzeichnis (tests/Tenant) bleiben vorerst, damit die ~60
 * Testklassen unverändert erben können.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected const TEST_SCHOOL_NUMBER = '9999';

    /** Die Testschule legt createTestSchool() an, nicht die Basisklasse. */
    protected bool $mitSchule = false;

    /**
     * Stammdaten (Fächer, Perioden, Termine, Hilfe) vor jedem Test. In
     * setUp() statt per $seed: Laravel migriert die :memory:-Datenbank nur
     * einmal je Prozess und rollt danach jeden Test per Transaktion zurück —
     * $seed wirkte nur für den ersten.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Legt die Testschule 9999 an und spielt ihre Grunddaten ein — wie der
     * Einrichtungsassistent (Räume, Zeitraster, Vorlagen).
     */
    protected function createTestSchool(array $overrides = []): School
    {
        $schule = School::create(array_merge([
            'school_number' => self::TEST_SCHOOL_NUMBER,
            'name' => 'Testschule Isolation',
            'short_name' => 'TEST9999',
            'state' => 'BY',
            'is_active' => true,
        ], $overrides));

        Artisan::call('db:seed', ['--class' => 'TenantSeeder', '--force' => true]);

        return $schule;
    }

    /** Aus der Mandantenzeit; es gibt nichts mehr aufzuräumen. */
    protected function cleanupTestSchool(): void
    {
    }
}
