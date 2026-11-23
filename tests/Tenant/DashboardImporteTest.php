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
        $antwort->assertSee('0 von 3 erledigt');
        $antwort->assertSee('Schüler und Kurse');
        $antwort->assertSee('Stundenplan');
        $antwort->assertSee(route('setup.imports.template', 'stundenplan'), false);
        // Das alte Banner („Bausteine“) zählte auch die Vorbelegung mit.
        $antwort->assertDontSee('Bausteinen fehlen');
        $antwort->assertSee(route('setup.imports') . '#lehrkraefte', false);
        $antwort->assertSee(route('setup.imports.template', 'schueler'), false);
        $antwort->assertSee(route('teachers.import.form'), false);
    }

    #[Test]
    public function kacheln_fuehren_zu_den_masken_und_es_gibt_keinen_knopf_neuer_zeitraum(): void
    {
        $antwort = $this->actingAs($this->planer())->get(route('dashboard'));

        $antwort->assertOk();
        foreach (['students.index', 'courses.index', 'teachers.index', 'rooms.index'] as $route) {
            $antwort->assertSee('href="' . route($route) . '"', false);
        }
        $antwort->assertDontSee('Neuer Prüfungszeitraum');
        $antwort->assertDontSee('createExamPeriodModal');
    }

    #[Test]
    public function eine_erfolgsmeldung_erscheint_nur_einmal(): void
    {
        $antwort = $this->actingAs($this->planer())
            ->withSession(['success' => 'Willkommen! Probe ist eingerichtet.'])
            ->get(route('dashboard'));

        $this->assertSame(1, substr_count($antwort->getContent(), 'Willkommen! Probe ist eingerichtet.'));
    }
}
