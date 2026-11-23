<?php

namespace Tests\Tenant;

use App\Models\School;
use App\Models\User;
use App\Models\WrittenExamSupervisionGrid;
use App\Models\WrittenExamSupervisionTimeSlot;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Nummer eines Aufsichts-Zeitslots zaehlt je Raster.
 *
 * Der UNIQUE-Index auf `slot_number` stammte aus der Zeit vor den Rastern und
 * war global. Als die Raster nachkamen, blieb er, wie er war — und der Code ist
 * ihm ausgewichen, statt daran anzustossen:
 *
 *   - Angelegt wurde mit `max('slot_number') + 1` ueber ALLE Raster. In der Pilotschule
 *     trug Raster 1 die Nummern 1–8 und Raster 2 die Nummern 9–16.
 *   - Nach dem Loeschen nummerierte eine Schleife ALLE Slots aller Raster neu
 *     durch. Ein Loeschen im ersten Raster verschob damit die Nummern im
 *     zweiten — an einer Stelle, die niemand angefasst hatte.
 *   - Beim Duplizieren eines Rasters bekam die Kopie fortlaufende globale
 *     Nummern statt derselben wie die Vorlage.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter AufsichtsrasterNummern
 */
class AufsichtsrasterNummernTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'raster-planer-9999@test.invalid';

    protected School $school;

    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();

        $this->school = $this->createTestSchool();
        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

    }

    protected function tearDown(): void
    {

        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    /**
     * Der Index selbst. Ohne die Migration steht hier der alte, globale — und
     * dann sind die Tests darunter nur noch Zufall.
     */
    #[Test]
    public function der_index_steht_ueber_raster_und_nummer(): void
    {
        $namen = collect(Schema::getIndexes('written_exam_supervision_time_slots'))
            ->keyBy('name');

        $this->assertArrayNotHasKey(
            'written_exam_supervision_time_slots_slot_number_unique',
            $namen->all(),
            'Der globale UNIQUE auf slot_number ist wieder da.'
        );

        $this->assertArrayHasKey('wests_grid_slot_unique', $namen->all());
        $this->assertSame(
            ['written_exam_supervision_grid_id', 'slot_number'],
            $namen->get('wests_grid_slot_unique')['columns']
        );
    }

    #[Test]
    public function zwei_raster_duerfen_dieselbe_nummer_fuehren(): void
    {
        $erstes = $this->raster('Vormittag');
        $zweites = $this->raster('Nachmittag');

        $this->slotAnlegen($erstes, '08:00', '09:30');
        $this->slotAnlegen($zweites, '13:00', '14:30');

        $this->assertSame(1, $this->nummer($erstes, '08:00:00'));
        $this->assertSame(
            1,
            $this->nummer($zweites, '13:00:00'),
            'Das zweite Raster muss bei 1 beginnen duerfen.'
        );
    }

    #[Test]
    public function ein_loeschen_im_einen_raster_laesst_das_andere_in_ruhe(): void
    {
        $erstes = $this->raster('Vormittag');
        $zweites = $this->raster('Nachmittag');

        $this->slotAnlegen($erstes, '08:00', '09:00');
        $this->slotAnlegen($erstes, '09:00', '10:00');
        $this->slotAnlegen($erstes, '10:00', '11:00');

        $this->slotAnlegen($zweites, '13:00', '14:00');
        $this->slotAnlegen($zweites, '14:00', '15:00');

        $vorher = $this->nummernVon($zweites);

        $zuLoeschen = WrittenExamSupervisionTimeSlot::query()
            ->where('written_exam_supervision_grid_id', $erstes->id)
            ->where('start_time', '09:00:00')
            ->firstOrFail();

        $this->actingAs($this->planner)
            ->delete(route('written-exams.supervisions.time-slots.destroy', $zuLoeschen))
            ->assertRedirect();

        $this->assertSame([1, 2], $this->nummernVon($erstes), 'Das eigene Raster wird neu durchgezaehlt.');
        $this->assertSame($vorher, $this->nummernVon($zweites), 'Das andere Raster bleibt unberuehrt.');
        $this->assertSame([1, 2], $vorher);
    }

    #[Test]
    public function die_kopie_eines_rasters_beginnt_wieder_bei_eins(): void
    {
        $vorlage = $this->raster('Vormittag');
        $this->slotAnlegen($vorlage, '08:00', '09:00');
        $this->slotAnlegen($vorlage, '09:00', '10:00');

        $antwort = $this->actingAs($this->planner)->post(
            route('written-exams.supervisions.grids.copy', $vorlage),
            ['name' => 'Vormittag (Kopie)']
        );

        $antwort->assertOk();

        $kopie = WrittenExamSupervisionGrid::query()->where('name', 'Vormittag (Kopie)')->firstOrFail();

        $this->assertSame([1, 2], $this->nummernVon($kopie));
        $this->assertSame([1, 2], $this->nummernVon($vorlage), 'Die Vorlage bleibt, wie sie war.');
    }

    private function raster(string $name): WrittenExamSupervisionGrid
    {
        return WrittenExamSupervisionGrid::create([
            'name' => $name,
            'is_default' => false,
        ]);
    }

    private function slotAnlegen(WrittenExamSupervisionGrid $raster, string $von, string $bis): void
    {
        $this->actingAs($this->planner)->post(
            route('written-exams.supervisions.time-slots.store'),
            [
                'start_time' => $von,
                'end_time' => $bis,
                'written_exam_supervision_grid_id' => $raster->id,
            ]
        )->assertRedirect();
    }

    private function nummer(WrittenExamSupervisionGrid $raster, string $start): int
    {
        return (int) WrittenExamSupervisionTimeSlot::query()
            ->where('written_exam_supervision_grid_id', $raster->id)
            ->where('start_time', $start)
            ->value('slot_number');
    }

    /**
     * @return list<int>
     */
    private function nummernVon(WrittenExamSupervisionGrid $raster): array
    {
        return WrittenExamSupervisionTimeSlot::query()
            ->where('written_exam_supervision_grid_id', $raster->id)
            ->orderBy('slot_number')
            ->pluck('slot_number')
            ->map(fn ($nummer): int => (int) $nummer)
            ->all();
    }
}
