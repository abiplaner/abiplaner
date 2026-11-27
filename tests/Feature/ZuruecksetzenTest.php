<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\TenantBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * „Abiplaner zurücksetzen“ gegen eine echte Datenbankdatei: Danach ist die
 * Datenbank wie frisch installiert (Stammdaten ja, Schule und Konten nein),
 * und der vorige Stand liegt als Sicherung im Sicherungsordner.
 */
class ZuruecksetzenTest extends TestCase
{
    private string $ordner;

    private string $vorher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vorher = (string) config('database.connections.sqlite.database');
        $this->ordner = sys_get_temp_dir() . '/abiplaner-zuruecksetzen-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);

        config(['database.connections.sqlite.database' => $this->ordner . '/abiplaner.sqlite']);
        DB::purge('sqlite');
        $this->assertSame(0, Artisan::call('abiplaner:vorbereiten'));
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config(['database.connections.sqlite.database' => $this->vorher]);
        DB::purge('sqlite');

        foreach (glob($this->ordner . '/{,.}*', GLOB_BRACE) ?: [] as $eintrag) {
            if (is_dir($eintrag) && ! in_array(basename($eintrag), ['.', '..'], true)) {
                array_map('unlink', glob($eintrag . '/*') ?: []);
                rmdir($eintrag);
            } elseif (is_file($eintrag)) {
                unlink($eintrag);
            }
        }
        @rmdir($this->ordner);

        parent::tearDown();
    }

    public function test_zuruecksetzen_leert_die_datenbank_und_sichert_vorher(): void
    {
        School::query()->create(['school_number' => '4321', 'name' => 'Altschule', 'short_name' => 'AS', 'state' => 'Bayern', 'is_active' => true]);
        User::factory()->create(['role' => 'admin', 'school_id' => '4321']);

        $dienst = app(TenantBackupService::class);
        $sicherung = $dienst->zuruecksetzen();

        $this->assertFalse(School::query()->exists());
        $this->assertSame(0, User::query()->count());
        $this->assertTrue(DB::table('subjects')->exists(), 'Stammdaten fehlen nach dem Zurücksetzen.');
        $this->assertFileExists($dienst->sicherungsVerzeichnis() . '/' . $sicherung);
        $this->assertStringContainsString('vor-zuruecksetzen', $sicherung);
        $this->assertSame([], glob($this->ordner . '/abiplaner.sqlite.zuruecksetzen-*'));
    }
}
