<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Services\YearChangeWizardService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * Schuljahreswechsel legt den neuen Prüfungszeitraum selbst an (23.11.2026):
 * aus den Termindateien, erst nach den Kolloquien des laufenden Jahrgangs.
 * Den Knopf „Neuer Prüfungszeitraum" auf dem Dashboard gibt es nicht mehr.
 */
class YearChangeNeuerZeitraumTest extends TenantTestCase
{
    private function wizard(): YearChangeWizardService
    {
        return app(YearChangeWizardService::class);
    }

    #[Test]
    public function vor_den_kolloquien_ist_der_wechsel_gesperrt(): void
    {
        $this->createTestSchool();
        Carbon::setTestNow('2027-03-01 12:00:00');

        try {
            $preflight = $this->wizard()->preflight();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame('Abitur 2027', $preflight['source']?->name);
        $this->assertSame('Abitur 2028', $preflight['target']?->name);
        $this->assertFalse($preflight['target']->exists);
        $this->assertCount(1, $preflight['errors']);
        $this->assertStringContainsString('ab 12.06.2027', $preflight['errors'][0]);
    }

    #[Test]
    public function nach_den_kolloquien_legt_der_start_den_neuen_zeitraum_an(): void
    {
        $this->createTestSchool();
        Carbon::setTestNow('2027-07-01 12:00:00');

        try {
            $preflight = $this->wizard()->preflight();
            $this->assertSame([], $preflight['errors']);
            $this->assertFalse(ExamPeriod::where('year', 2028)->exists(), 'preflight() darf nichts anlegen.');

            $state = $this->wizard()->start();
        } finally {
            Carbon::setTestNow();
        }

        $neu = ExamPeriod::where('year', 2028)->firstOrFail();
        $this->assertSame('Abitur 2028', $neu->name);
        $this->assertSame('2028-05-22', $neu->week_1_start->toDateString());
        $this->assertSame('2028-06-02', $neu->week_2_end->toDateString());
        $this->assertSame($neu->id, $state['target_period_id']);
        $this->assertSame(ExamPeriod::where('year', 2027)->value('id'), $state['source_period_id']);
    }

    #[Test]
    public function ein_schon_angelegter_leerer_zeitraum_bleibt_das_ziel(): void
    {
        $this->createTestSchool();
        // Alter Weg: 2028 von Hand angelegt, Wechsel 2027 → 2028 noch nicht gelaufen.
        $alt = ExamPeriod::where('year', 2027)->firstOrFail();
        $neu = ExamPeriod::create([
            'name' => 'Abitur 2028', 'year' => 2028,
            'week_1_start' => '2028-05-22', 'week_1_end' => '2028-05-26',
            'week_2_start' => '2028-05-29', 'week_2_end' => '2028-06-02',
        ]);

        $preflight = $this->wizard()->preflight();

        $this->assertSame($neu->id, $preflight['target']?->id);
        $this->assertSame($alt->id, $preflight['source']?->id);
        $this->assertSame([], $preflight['errors']);
    }
}
