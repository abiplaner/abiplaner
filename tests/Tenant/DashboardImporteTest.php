<?php

namespace Tests\Tenant;

use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * Startseite (23.11.2026): die drei Importe mit Anleitung, Vorlage und Maske,
 * solange einer fehlt; „Neuer Prüfungszeitraum" mit den Kolloquiumswochen
 * aus den Termindateien vorbelegt.
 */
class DashboardImporteTest extends TenantTestCase
{
    private function planer(): User
    {
        $schule = $this->createTestSchool();

        return User::factory()->create([
            'role' => 'planner',
            'school_id' => $schule->school_number,
        ]);
    }

    #[Test]
    public function eine_frische_schule_sieht_die_drei_importe_mit_anleitung(): void
    {
        $antwort = $this->actingAs($this->planer())->get(route('dashboard'));

        $antwort->assertOk();
        $antwort->assertSee('Daten einlesen');
        $antwort->assertSee('Lehrkräfte aus ASV');
        $antwort->assertSee('Schüler und Kurse aus ASV');
        $antwort->assertSee('Stundenplan aus Untis');
        $antwort->assertSee(route('setup.imports') . '#lehrkraefte', false);
        $antwort->assertSee(route('setup.imports.template', 'schueler'), false);
        $antwort->assertSee(route('teachers.import.form'), false);
    }

    #[Test]
    public function der_neue_pruefungszeitraum_ist_mit_dem_naechsten_abitur_vorbelegt(): void
    {
        Carbon::setTestNow('2026-11-23 12:00:00');

        try {
            $antwort = $this->actingAs($this->planer())->get(route('dashboard'));
        } finally {
            Carbon::setTestNow();
        }

        $antwort->assertOk();
        $antwort->assertSee('value="Abitur 2028"', false);
        $antwort->assertSee('value="2028-05-22"', false);
        $antwort->assertSee('value="2028-06-02"', false);
    }
}
