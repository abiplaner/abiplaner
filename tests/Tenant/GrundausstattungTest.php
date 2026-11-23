<?php

namespace Tests\Tenant;

use App\Support\Grundausstattung;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Grundausstattung einer neuen Schule (TenantSeeder, 23.11.2026):
 * Räume mit Sitzplänen, Kolloquiumsslots Mo–Do nachmittags und freitags
 * vormittags, freie Feiertage, Aufsichtsraster.
 */
class GrundausstattungTest extends TenantTestCase
{
    #[Test]
    public function jeder_pruefungsraum_bekommt_seinen_sitzplan(): void
    {
        $this->createTestSchool();

        $this->assertSame(count(Grundausstattung::RAEUME), DB::table('rooms')->count());
        $this->assertSame(count(Grundausstattung::SITZPLAENE), DB::table('seating_templates')->count());

        $pruefungsraeume = DB::table('rooms')->where('room_type', 'exam')->pluck('id');
        $this->assertCount(12, $pruefungsraeume);
        $this->assertSame(12, DB::table('room_seating_templates')->whereIn('room_id', $pruefungsraeume)->where('variant', 'standard')->count());

        // Sitzplätze je Plan wie im Bestand der Pilotschule: 25, 28, 4, 12.
        $plaetze = DB::table('seating_template_seats')->join('seating_templates', 'seating_templates.id', '=', 'seating_template_seats.template_id')
            ->where('seat_type', 'available')->groupBy('seating_templates.name')
            ->selectRaw('seating_templates.name as name, count(*) as n')->pluck('n', 'name')->map(fn ($n) => (int) $n)->all();
        $this->assertSame(25, $plaetze['Klassenzimmer 8×4 (25 Plätze)']);
        $this->assertSame(28, $plaetze['Klassenzimmer 8×4, letzte Reihe frei (28 Plätze)']);
        $this->assertSame(4, $plaetze['Kleiner Raum 4×4 (4 Plätze)']);
        $this->assertSame(12, $plaetze['Fachraum 6×3 (12 Plätze)']);
    }

    #[Test]
    public function kolloquien_laufen_mo_bis_do_nachmittags_und_freitags_vormittags(): void
    {
        $this->createTestSchool();
        $periode = DB::table('exam_periods')->orderByDesc('year')->first();

        $slots = DB::table('time_slots')->where('exam_period_id', $periode->id)->orderBy('date')->orderBy('slot_number')->get();
        $this->assertCount(50, $slots, '10 Tage × 5 Slots');

        foreach ($slots->groupBy('date') as $datum => $tag) {
            $erster = substr((string) $tag->first()->exam_start_time, 0, 5);
            $this->assertSame(Grundausstattung::istFreitag($datum) ? '08:30' : '13:10', $erster, $datum);
        }

        $aufsicht = DB::table('supervision_time_slots')->where('exam_period_id', $periode->id)->get();
        $this->assertCount(30, $aufsicht);
        $this->assertSame(0, $aufsicht->whereNull('supervision_grid_id')->count());

        $this->assertSame(2, DB::table('written_exam_supervision_grids')->count());
        $this->assertSame(16, DB::table('written_exam_supervision_time_slots')->count());
    }

    #[Test]
    public function feiertage_in_den_pruefungswochen_bleiben_frei(): void
    {
        // Abitur 2028: Christi Himmelfahrt (25.05.) in der ersten Woche.
        DB::table('exam_periods')->insert([
            'name' => 'Abitur 2028', 'year' => 2028,
            'week_1_start' => '2028-05-22', 'week_1_end' => '2028-05-26',
            'week_2_start' => '2028-05-29', 'week_2_end' => '2028-06-02',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->createTestSchool();
        $periode = DB::table('exam_periods')->where('year', 2028)->first();

        $tage = DB::table('time_slots')->where('exam_period_id', $periode->id)->distinct()->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();

        $this->assertCount(9, $tage);
        $this->assertNotContains('2028-05-25', $tage);
    }

    #[Test]
    public function ein_zweiter_lauf_legt_nichts_doppelt_an(): void
    {
        $this->createTestSchool();
        $vorher = [DB::table('rooms')->count(), DB::table('time_slots')->count(), DB::table('written_exam_supervision_grids')->count()];

        Artisan::call('db:seed', ['--class' => 'TenantSeeder', '--force' => true]);

        $this->assertSame($vorher, [DB::table('rooms')->count(), DB::table('time_slots')->count(), DB::table('written_exam_supervision_grids')->count()]);
    }
}
