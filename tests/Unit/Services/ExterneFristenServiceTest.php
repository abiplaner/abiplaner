<?php

namespace Tests\Unit\Services;

use App\Models\ExamPeriod;
use App\Services\ExterneFristenService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * A8 (§ 61 Abs. 1 S. 7) und B5 (§ 61 Abs. 2 S. 5) — reine Anzeigefristen fuer
 * externe Bewerber, siehe App\Services\ExterneFristenService.
 *
 * A8 wird aus dem Ende der zweiten Pruefungswoche abgeleitet (Wilfried,
 * 01.09.2026: die Ergebnisse werden am letzten Tag der Kolloquiumspruefungen
 * bekanntgegeben), B5 ist ein fester Kalendertag.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter ExterneFristenService
 */
class ExterneFristenServiceTest extends TestCase
{
    private ExterneFristenService $svc;

    protected function setUp(): void
    {
        $this->svc = new ExterneFristenService();
    }

    /**
     * setRawAttributes() statt der Setter: Ein 'date'-gecastetes Attribut
     * braucht beim Schreiben wie beim Lesen eine aufgeloeste DB-Verbindung
     * (Model::getConnectionResolver()) — die dieser reine Unit-Test bewusst
     * nicht hat. Genau wie bei einer echten Eloquent-Hydration aus der
     * Datenbank landen die Werte so unveraendert im rohen Attribut-Array.
     */
    private function zeitraum(int $year, string $week2End): ExamPeriod
    {
        $zeitraum = new ExamPeriod();
        // sync=true: wie Model::newFromBuilder() beim echten Laden aus der
        // Datenbank — ohne sync bliebe $original leer und getRawOriginal()
        // laeuft ins Leere.
        $zeitraum->setRawAttributes([
            'year' => $year,
            'week_2_end' => $week2End,
        ], true);

        return $zeitraum;
    }

    public function test_a8_ist_der_tag_nach_ende_der_zweiten_pruefungswoche(): void
    {
        $zeitraum = $this->zeitraum(2027, '2027-05-14');

        $frist = $this->svc->a8Frist($zeitraum);

        $this->assertTrue($frist->isSameDay(CarbonImmutable::create(2027, 5, 15)));
    }

    public function test_b5_ist_der_31_januar_des_pruefungsjahrs(): void
    {
        $zeitraum = $this->zeitraum(2027, '2027-05-14');

        $frist = $this->svc->b5Frist($zeitraum);

        $this->assertTrue($frist->isSameDay(CarbonImmutable::create(2027, 1, 31)));
    }

    public function test_eine_kuenftige_frist_ist_nicht_verstrichen(): void
    {
        $kuenftig = CarbonImmutable::now()->addYear();

        $this->assertFalse($this->svc->istVerstrichen($kuenftig));
    }

    public function test_eine_vergangene_frist_ist_verstrichen(): void
    {
        $vergangen = CarbonImmutable::now()->subYear();

        $this->assertTrue($this->svc->istVerstrichen($vergangen));
    }

    /**
     * Gegenprobe: heute selbst zaehlt noch nicht als verstrichen — sonst
     * meldete die Anzeige eine Frist schon an ihrem eigenen letzten Tag als
     * ueberschritten.
     */
    public function test_der_frist_tag_selbst_ist_noch_nicht_verstrichen(): void
    {
        $heute = CarbonImmutable::now()->startOfDay();

        $this->assertFalse($this->svc->istVerstrichen($heute));
    }
}
