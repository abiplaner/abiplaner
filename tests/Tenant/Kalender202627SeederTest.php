<?php

namespace Tests\Tenant;

use Carbon\Carbon;
use Database\Seeders\Kalender202627Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Kalender202627Seeder: Fristen, Zeugnistermine und Feiertage 2026/27 (21.09.2026).
 *
 * Die Daten sind aus den Regeln des Vorjahres abgeleitet, weil der ISB-
 * Terminplaner 2026/27 noch fehlt. Diese Tests rechnen die Regeln unabhängig
 * nach — und wenden sie zur Gegenprobe auf 2025/26 an, wo das Ergebnis bekannt ist.
 *
 * Der Test schreibt in die zentrale Tabelle `global_calendar_events`. Er läuft
 * deshalb in einer Transaktion, die am Ende zurückgerollt wird: Was der Seeder
 * lokal schon eingespielt hat, kommt unverändert zurück, und nichts bleibt liegen.
 *
 * Ausführen:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter Kalender202627Seeder
 */
class Kalender202627SeederTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->zentral()->beginTransaction();

        // Lokal ist der Seeder womöglich schon gelaufen: seine Termine hier
        // entfernen, damit "eingefügt" gezählt werden kann. Der Rollback holt sie zurück.
        foreach (Kalender202627Seeder::termine() as $termin) {
            $this->tabelle()->where('title', $termin['titel'])->where('event_date', $termin['von'])->delete();
        }
    }

    protected function tearDown(): void
    {
        while ($this->zentral()->transactionLevel() > 0) {
            $this->zentral()->rollBack();
        }

        parent::tearDown();
    }

    private function zentral(): \Illuminate\Database\Connection
    {
        return DB::connection();
    }

    private function tabelle(): \Illuminate\Database\Query\Builder
    {
        return $this->zentral()->table('global_calendar_events');
    }

    private function seede(): void
    {
        Artisan::call('db:seed', ['--class' => Kalender202627Seeder::class, '--force' => true]);
    }

    /** Der Termin dieses Titels im Schuljahr 2026/27. */
    private function termin(string $titel): object
    {
        $zeile = $this->tabelle()->where('title', $titel)
            ->whereBetween('event_date', ['2026-08-01', '2027-07-31'])->first();

        $this->assertNotNull($zeile, "Termin fehlt: {$titel}");

        return $zeile;
    }

    /** Ostersonntag nach Gauß, unabhängig vom Seeder und ohne PHP-Erweiterung `calendar`. */
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
    public function der_seeder_legt_alle_termine_an_und_ein_zweiter_lauf_aendert_nichts(): void
    {
        $soll = count(Kalender202627Seeder::termine());
        $this->assertGreaterThan(20, $soll);

        $this->seede();
        $nach1 = $this->tabelle()->whereBetween('event_date', ['2026-08-01', '2027-07-31'])->count();
        $hoechsteId = $this->tabelle()->max('id');

        $this->seede();

        $this->assertSame($hoechsteId, $this->tabelle()->max('id'), 'Der zweite Lauf hat Zeilen eingefügt.');
        $this->assertSame($nach1, $this->tabelle()->whereBetween('event_date', ['2026-08-01', '2027-07-31'])->count());

        foreach (Kalender202627Seeder::termine() as $termin) {
            $this->assertSame(
                1,
                $this->tabelle()->where('title', $termin['titel'])->where('event_date', $termin['von'])->count(),
                "{$termin['titel']} ({$termin['von']}) muss genau einmal stehen."
            );
        }
    }

    #[Test]
    public function die_termine_stehen_am_richtigen_tag(): void
    {
        $this->seede();

        $erwartet = [
            'Änderung der Kurswahl mit Genehmigung der Schulleitung' => '2026-10-12',
            'Abgabe der Seminararbeit' => '2026-11-10',
            'Meldung anderer Bewerber für Abiturprüfung' => '2026-12-15',
            'Zeugnis AA 13/1' => '2027-01-25',
            // Freitag: Der 31.01.2027 ist ein Sonntag, letzter Termin ist der 29.01. (Wilfried, 21.09.2026).
            'Endgültige Festlegung der schriftlichen Abiturprüfungsfächer' => '2027-01-29',
            'Zeugnis AA 12/1' => '2027-02-19',
            'Endgültige Festlegung der mündlichen Abiturprüfungsfächer' => '2027-03-10',
            'Letzter Termin zur Wahl des Kursprogramms' => '2027-04-15',
            'Meldeschluss für zentrales Vergabeverfahren' => '2027-07-15',
            'Zeugnis AA 12/2' => '2027-07-30',
            'Tag des Gedenkens an die Opfer des Nationalsozialismus' => '2027-01-27',
        ];

        foreach ($erwartet as $titel => $datum) {
            $this->assertSame($datum, substr($this->termin($titel)->event_date, 0, 10), $titel);
        }
    }

    #[Test]
    public function die_seminararbeit_wird_fuer_die_q13_abgegeben(): void
    {
        $this->seede();

        $termin = $this->termin('Abgabe der Seminararbeit');

        $this->assertSame('deadline', $termin->event_type);
        $this->assertSame([13], json_decode($termin->grade_levels, true));
    }

    #[Test]
    public function der_reformationstag_ist_dabei(): void
    {
        $this->seede();

        $tag = $this->termin('Reformationstag');

        $this->assertSame('2026-10-31', substr($tag->event_date, 0, 10));
        $this->assertSame('public_holiday', $tag->event_type);
    }

    #[Test]
    public function die_feiertage_folgen_dem_osterdatum(): void
    {
        $this->seede();

        $ostern = $this->ostern(2027);
        $this->assertSame('2027-03-28', $ostern->toDateString(), 'Kontrolle der Osterrechnung.');

        $erwartet = [
            'Rosenmontag' => $ostern->copy()->subDays(48),
            'Karfreitag' => $ostern->copy()->subDays(2),
            'Ostersonntag' => $ostern->copy(),
            'Ostermontag' => $ostern->copy()->addDay(),
            'Pfingstsonntag' => $ostern->copy()->addDays(49),
            'Pfingstmontag' => $ostern->copy()->addDays(50),
            'Fronleichnam' => $ostern->copy()->addDays(60),
        ];

        foreach ($erwartet as $titel => $datum) {
            $this->assertSame($datum->toDateString(), substr($this->termin($titel)->event_date, 0, 10), $titel);
        }
    }

    #[Test]
    public function die_regeln_der_fristen_stimmen_und_treffen_im_vorjahr_das_bekannte_ergebnis(): void
    {
        $this->seede();

        // Kurswahl-Änderung: Ende der ersten vier Wochen ab Unterrichtsbeginn.
        $kurswahl = fn (string $beginn) => Carbon::parse($beginn)->addDays(27)->toDateString();
        $this->assertSame('2025-10-13', $kurswahl('2025-09-16'), 'Gegenprobe 2025/26');
        $this->assertSame($kurswahl('2026-09-15'), substr($this->termin('Änderung der Kurswahl mit Genehmigung der Schulleitung')->event_date, 0, 10));

        // Festlegung der mündlichen Fächer: sechs Wochen vor dem ersten schriftlichen Tag.
        $muendlich = fn (string $ersterTag) => Carbon::parse($ersterTag)->subDays(42)->toDateString();
        $this->assertSame('2026-03-11', $muendlich('2026-04-22'), 'Gegenprobe 2025/26');
        $this->assertSame($muendlich('2027-04-21'), substr($this->termin('Endgültige Festlegung der mündlichen Abiturprüfungsfächer')->event_date, 0, 10));

        // Zeugnis AA 13/1: erster Unterrichtstag (Mo–Fr) nach dem 23. Januar.
        $zeugnis = function (int $jahr): string {
            $tag = Carbon::create($jahr, 1, 24);
            while ($tag->isWeekend()) {
                $tag->addDay();
            }

            return $tag->toDateString();
        };
        $this->assertSame('2026-01-26', $zeugnis(2026), 'Gegenprobe 2025/26');
        $this->assertSame($zeugnis(2027), substr($this->termin('Zeugnis AA 13/1')->event_date, 0, 10));
    }

    #[Test]
    public function schultermine_fallen_nicht_auf_ein_wochenende(): void
    {
        $gepruefte = 0;

        foreach (Kalender202627Seeder::termine() as $termin) {
            if (empty($termin['schultag'])) {
                continue;
            }

            $gepruefte++;
            $this->assertLessThan(
                6,
                (int) date('N', strtotime($termin['von'])),
                "{$termin['titel']} ({$termin['von']}) liegt auf einem Wochenende."
            );
        }

        $this->assertGreaterThan(5, $gepruefte, 'Die Probe hat kaum etwas geprüft.');
    }

    #[Test]
    public function die_felder_folgen_dem_bestand_2025_26(): void
    {
        $this->seede();

        $verglichen = 0;

        foreach (Kalender202627Seeder::termine() as $termin) {
            $vorjahr = $this->tabelle()->where('title', $termin['titel'])->where('event_date', '<', '2026-08-01')->first();

            if ($vorjahr === null) {
                continue;
            }

            $neu = $this->termin($termin['titel']);
            $verglichen++;

            foreach (['event_type', 'color', 'is_recurring', 'priority', 'grade_levels'] as $feld) {
                // Bewusste Abweichung: Die Seminararbeit wird 2026/27 für die Q13 abgegeben
                // (Wilfried, 21.09.2026); der Bestand 2025/26 führt sie bei Stufe 12.
                if ($termin['titel'] === 'Abgabe der Seminararbeit' && $feld === 'grade_levels') {
                    continue;
                }

                $this->assertEquals($vorjahr->$feld, $neu->$feld, "{$termin['titel']}: Feld {$feld} weicht vom Vorjahr ab.");
            }
        }

        if ($verglichen === 0) {
            $this->markTestSkipped('Kein Bestand 2025/26 in dieser zentralen Datenbank zum Vergleichen.');
        }

        $this->assertGreaterThan(15, $verglichen);
    }
}
