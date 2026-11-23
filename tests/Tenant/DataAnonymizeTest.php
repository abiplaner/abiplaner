<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\School;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

/**
 * `data:anonymize` — schliesst die Luecke aus dem alten Anonymisierungs-
 * konzept (first_name/last_name sind NOT NULL, ein simples Nullen ging nie).
 *
 * Jeder Aufruf mit `--school=9999`, damit der Test nicht ueber
 * School::all() auch die echten lokalen Mandanten (z. B. 9990, 9991)
 * anfasst.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter DataAnonymize
 */
class DataAnonymizeTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2096;

    protected School $school;
    protected ExamPeriod $examPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        $this->examPeriod = ExamPeriod::create([
            'name' => 'Testperiode Anonymisierung (' . self::TEST_PERIOD_YEAR . ')',
            'year' => self::TEST_PERIOD_YEAR,
            'week_1_start' => self::TEST_PERIOD_YEAR . '-05-04',
            'week_1_end' => self::TEST_PERIOD_YEAR . '-05-08',
            'week_2_start' => self::TEST_PERIOD_YEAR . '-05-11',
            'week_2_end' => self::TEST_PERIOD_YEAR . '-05-15',
        ]);
    }

    protected function tearDown(): void
    {

        // Ohne diesen Aufruf bleibt die zentrale ExamPeriod (Jahr 2096) nach
        // jedem Lauf liegen — und CurrentExamPeriodService::latest() waehlt
        // das hoechste Jahr. Genau das hat am 01.09. AbbruchNachErstemTeilTest
        // zum Scheitern gebracht, obwohl der Fehler in keinem Wort mit ihm zu
        // tun hatte: eine verwaiste Zeile aus einem frueheren, laengst
        // beendeten Testlauf reichte.
        $this->cleanupCentralTestData();

        parent::tearDown();
    }

    /**
     * cleanupCentralTestData() gibt es in TenantTestCase nicht als eigene
     * Methode — die zentrale ExamPeriod hängt an keiner anderen Testklasse
     * und wird hier direkt entfernt, statt einen Namen vorauszusetzen, den
     * es nicht gibt.
     */
    private function cleanupCentralTestData(): void
    {
        ExamPeriod::where('year', self::TEST_PERIOD_YEAR)->delete();
    }

    #[Test]
    public function anonymisiert_faellige_bewerber(): void
    {
        $faellig = ExternalApplicant::factory()->forExamPeriod($this->examPeriod)->create([
            'first_name' => 'Anna',
            'last_name' => 'Faellig',
            'email' => 'anna@example.test',
            'notes' => 'enthaelt vielleicht einen Namen',
            'anonymize_at' => now()->subDay(),
        ]);

        Artisan::call('data:anonymize', ['--school' => self::TEST_SCHOOL_NUMBER]);

        $faellig->refresh();
        $this->assertTrue((bool) $faellig->is_anonymized);
        $this->assertSame('Bewerber', $faellig->first_name);
        $this->assertSame((string) $faellig->id, $faellig->last_name);
        $this->assertNull($faellig->email);
        $this->assertNull($faellig->notes);
    }

    #[Test]
    public function laesst_noch_nicht_faellige_bewerber_unangetastet(): void
    {
        $nochNicht = ExternalApplicant::factory()->forExamPeriod($this->examPeriod)->create([
            'first_name' => 'Bruno',
            'last_name' => 'Wartend',
            'anonymize_at' => now()->addYear(),
        ]);

        Artisan::call('data:anonymize', ['--school' => '9999']);

        $nochNicht->refresh();
        $this->assertFalse((bool) $nochNicht->is_anonymized);
        $this->assertSame('Bruno', $nochNicht->first_name);
    }

    #[Test]
    public function laesst_bereits_anonymisierte_bewerber_unangetastet(): void
    {
        $schon = ExternalApplicant::factory()->forExamPeriod($this->examPeriod)->anonymized()->create([
            'first_name' => 'Bewerber',
            'last_name' => '999999',
            'anonymize_at' => now()->subDay(),
        ]);

        Artisan::call('data:anonymize', ['--school' => '9999']);

        $schon->refresh();
        $this->assertSame('999999', $schon->last_name);
    }

    #[Test]
    public function probe_aendert_nichts(): void
    {
        $faellig = ExternalApplicant::factory()->forExamPeriod($this->examPeriod)->create([
            'first_name' => 'Clara',
            'last_name' => 'Sofortig',
            'anonymize_at' => now()->subDay(),
        ]);

        Artisan::call('data:anonymize', ['--school' => '9999', '--probe' => true]);

        $faellig->refresh();
        $this->assertFalse((bool) $faellig->is_anonymized);
        $this->assertSame('Clara', $faellig->first_name);
    }

    #[Test]
    public function unbekannte_schulnummer_meldet_fehler(): void
    {
        $exitCode = Artisan::call('data:anonymize', ['--school' => '00xx-nicht-vorhanden']);

        $this->assertSame(1, $exitCode);
    }
}
