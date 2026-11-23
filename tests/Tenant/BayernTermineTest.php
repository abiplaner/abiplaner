<?php

namespace Tests\Tenant;

use App\Services\BayernTermine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Bayernweite Termine aus database/data/termine (23.11.2026).
 *
 * Löst Kalender202627SeederTest ab. Geprüft werden die Dateien selbst
 * (Plausibilität, Feiertage gegen eine unabhängige Osterrechnung) und der
 * Abgleich: idempotent, berichtigt statt verdoppelt, übernimmt den Bestand
 * der früheren Seeder und lässt von Hand angelegte Termine in Ruhe.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter BayernTermine
 */
class BayernTermineTest extends TenantTestCase
{
    private function termine(): BayernTermine
    {
        return new BayernTermine();
    }

    private function tabelle(): \Illuminate\Database\Query\Builder
    {
        return DB::table('global_calendar_events');
    }

    /** Der Termin dieses Titels im Schuljahr (Kurzform "2026/27"). */
    private function termin(string $titel, string $schuljahr): object
    {
        $zeile = $this->tabelle()->where('schluessel', str_replace('/', '-', $schuljahr) . '/' . \Illuminate\Support\Str::slug($titel))->first();
        $this->assertNotNull($zeile, "Termin fehlt: {$titel} ({$schuljahr})");

        return $zeile;
    }

    /** Ostersonntag nach Gauß, unabhängig von den Dateien. */
    private function ostern(int $jahr): Carbon
    {
        $a = $jahr % 19;
        $b = intdiv($jahr, 100);
        $c = $jahr % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $monat = intdiv($h + $l - 7 * $m + 114, 31);
        $tag = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($jahr, $monat, $tag)->startOfDay();
    }

    #[Test]
    public function alle_dateien_laden_und_bestehen_die_plausibilitaetsprobe(): void
    {
        $jahre = $this->termine()->jahre();
        $this->assertGreaterThanOrEqual(3, count($jahre));

        foreach ($jahre as $jahr) {
            $this->assertSame([], $this->termine()->pruefen($jahr), "Warnungen in {$jahr['datei']}");
            $this->assertGreaterThan(20, count($this->termine()->eintraege($jahr)), $jahr['datei']);
        }
    }

    #[Test]
    public function jedes_abitur_vergibt_den_ganzen_slot_katalog_genau_einmal(): void
    {
        $katalog = DB::table('written_exam_slots')->pluck('sort_order')->map(fn ($s) => (int) $s)->sort()->values()->all();
        $this->assertCount(23, $katalog);

        foreach ($this->termine()->jahre() as $jahr) {
            $vergeben = array_merge(...array_column($jahr['abitur']['schriftlich'], 'slots'));
            sort($vergeben);
            $this->assertSame($katalog, $vergeben, "Abitur {$jahr['abitur']['jahr']}");
        }

        // Und angekommen: je Jahr 23 Prüfungstage im Slot-Raster.
        foreach ([2026, 2027, 2028] as $jahr) {
            $this->assertSame(23, DB::table('written_exam_year_dates')->where('exam_year', $jahr)->count(), "Abitur {$jahr}");
        }
    }

    #[Test]
    public function ein_zweiter_abgleich_aendert_nichts(): void
    {
        $vorher = $this->tabelle()->count();
        $bericht = $this->termine()->einspielen();

        $this->assertSame(0, $bericht['neu'] + $bericht['geaendert'] + $bericht['geloescht'] + $bericht['schriftlich']);
        $this->assertSame($vorher, $bericht['unveraendert']);
        $this->assertSame(0, $this->tabelle()->whereNull('schluessel')->count(), 'Jeder eingespielte Termin trägt einen Schlüssel.');
    }

    #[Test]
    public function ein_berichtigtes_datum_ersetzt_den_alten_eintrag_statt_ihn_zu_verdoppeln(): void
    {
        // Stand der früheren Seeder: Pfingstferien 2027 ab Pfingstmontag, ohne Schlüssel.
        $this->tabelle()->where('schluessel', '2026-27/pfingstferien')->update([
            'schluessel' => null, 'event_date' => '2027-05-17', 'end_date' => '2027-05-30',
        ]);

        $bericht = $this->termine()->einspielen();

        $this->assertSame(1, $bericht['geaendert']);
        $zeilen = $this->tabelle()->where('title', 'Pfingstferien')->whereBetween('event_date', ['2026-08-01', '2027-07-31'])->get();
        $this->assertCount(1, $zeilen);
        $this->assertSame('2027-05-18', substr($zeilen[0]->event_date, 0, 10));
        $this->assertSame('2027-05-28', substr($zeilen[0]->end_date, 0, 10));
    }

    #[Test]
    public function entfernte_termine_verschwinden_von_hand_angelegte_bleiben(): void
    {
        $basis = ['event_date' => '2027-03-03', 'event_type' => 'info', 'color' => '#000000', 'created_at' => now(), 'updated_at' => now()];
        $this->tabelle()->insert($basis + ['title' => 'Aus der Datei gestrichen', 'schluessel' => '2026-27/aus-der-datei-gestrichen']);
        $this->tabelle()->insert($basis + ['title' => 'Elternabend Q12', 'schluessel' => null]);

        $bericht = $this->termine()->einspielen();

        $this->assertSame(1, $bericht['geloescht']);
        $this->assertFalse($this->tabelle()->where('title', 'Aus der Datei gestrichen')->exists());
        $this->assertTrue($this->tabelle()->where('title', 'Elternabend Q12')->exists());
    }

    #[Test]
    public function die_probe_schreibt_nichts(): void
    {
        $this->tabelle()->where('schluessel', '2027-28/sommerferien')->delete();

        $bericht = $this->termine()->einspielen(probe: true);

        $this->assertSame(1, $bericht['neu']);
        $this->assertFalse($this->tabelle()->where('schluessel', '2027-28/sommerferien')->exists());
    }

    #[Test]
    public function zeitraeume_entstehen_nur_bis_zum_laufenden_schuljahr_der_naechste_wird_vorgeschlagen(): void
    {
        Carbon::setTestNow('2026-11-23 12:00:00');

        try {
            DB::table('exam_periods')->delete();
            $bericht = $this->termine()->einspielen(mitZeitraeumen: true);

            $this->assertSame(['Abitur 2026', 'Abitur 2027'], $bericht['zeitraeume']);
            $this->assertSame([2026, 2027], DB::table('exam_periods')->orderBy('year')->pluck('year')->map(fn ($j) => (int) $j)->all());

            $this->assertSame([
                'name' => 'Abitur 2028', 'year' => 2028,
                'week_1_start' => '2028-05-22', 'week_1_end' => '2028-05-26',
                'week_2_start' => '2028-05-29', 'week_2_end' => '2028-06-02',
            ], $this->termine()->zeitraumVorschlag());

            // Ohne den Wunsch legt der Abgleich (beim Start) nie einen Zeitraum an.
            DB::table('exam_periods')->where('year', 2027)->delete();
            $this->assertSame([], $this->termine()->einspielen()['zeitraeume']);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function die_feiertage_folgen_dem_osterdatum(): void
    {
        foreach (['2026/27' => 2027, '2027/28' => 2028] as $schuljahr => $jahr) {
            $ostern = $this->ostern($jahr);
            $erwartet = [
                'Rosenmontag' => $ostern->copy()->subDays(48),
                'Karfreitag' => $ostern->copy()->subDays(2),
                'Ostersonntag' => $ostern->copy(),
                'Ostermontag' => $ostern->copy()->addDay(),
                'Christi Himmelfahrt' => $ostern->copy()->addDays(39),
                'Pfingstsonntag' => $ostern->copy()->addDays(49),
                'Pfingstmontag' => $ostern->copy()->addDays(50),
                'Fronleichnam' => $ostern->copy()->addDays(60),
            ];

            foreach ($erwartet as $titel => $datum) {
                $this->assertSame($datum->toDateString(), substr($this->termin($titel, $schuljahr)->event_date, 0, 10), "{$titel} {$jahr}");
            }
        }
    }

    #[Test]
    public function die_ferien_folgen_der_ferienordnung(): void
    {
        // BayMBl. 2022 Nr. 747, erster und letzter Ferientag.
        $erwartet = [
            ['2025/26', 'Osterferien', '2026-03-30', '2026-04-10'],
            ['2025/26', 'Weihnachtsferien', '2025-12-22', '2026-01-05'],
            ['2026/27', 'Sommerferien', '2026-08-03', '2026-09-14'],
            ['2026/27', 'Pfingstferien', '2027-05-18', '2027-05-28'],
            ['2027/28', 'Sommerferien', '2027-08-02', '2027-09-13'],
            ['2027/28', 'Pfingstferien', '2028-06-06', '2028-06-16'],
        ];

        foreach ($erwartet as [$schuljahr, $titel, $von, $bis]) {
            $zeile = $this->termin($titel, $schuljahr);
            $this->assertSame([$von, $bis], [substr($zeile->event_date, 0, 10), substr((string) $zeile->end_date, 0, 10)], "{$titel} {$schuljahr}");
            $this->assertSame('holiday', $zeile->event_type);
        }
    }

    #[Test]
    public function die_seminararbeit_wird_2026_27_von_der_q13_abgegeben(): void
    {
        $this->assertSame([12], json_decode($this->termin('Abgabe der Seminararbeit', '2025/26')->grade_levels, true));
        $this->assertSame([13], json_decode($this->termin('Abgabe der Seminararbeit', '2026/27')->grade_levels, true));
    }
}
