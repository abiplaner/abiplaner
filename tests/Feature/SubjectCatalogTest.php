<?php

namespace Tests\Feature;

use Database\Seeders\SubjectSeeder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hält fest, dass der zentrale Fächerkatalog und SubjectSeeder::definitions()
 * nicht auseinanderlaufen.
 *
 * Anlass (Modul 2.1, 25.08.2026): Die Korrekturen der Migration vom 18.06.2026
 * standen nur dort und nicht im Seeder. Weil Migrationen vor Seedern laufen,
 * bekam jede frische Installation den falschen Stand zurück, ohne dass ein
 * Test das bemerkt hätte. Ändert künftig eine Migration den Katalog, ohne den
 * Seeder nachzuziehen, schlägt test_datenbank_entspricht_dem_seeder fehl.
 *
 * Die übrigen Fälle sichern die Eigenschaften ab, auf die sich die
 * Fächerwahl-Validierung nach §§ 48 und 61 GSO stützt.
 */
class SubjectCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seit dem Basisschema (22.11.2026) füllt der Seeder den Katalog.
     * In setUp() statt per $seed: Laravel migriert die :memory:-Datenbank nur
     * einmal je Prozess, $seed wirkt dann nur für die erste Testklasse.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubjectSeeder::class);
    }

    /** @var list<string> */
    private const FLAGS = [
        'is_foreign_language',
        'is_science',
        'can_be_late_starting',
        'can_abitur',
    ];

    private function katalog(): Builder
    {
        return DB::table('subjects');
    }

    public function test_datenbank_entspricht_dem_seeder(): void
    {
        $bestand = $this->katalog()->get()->keyBy('code');
        $definitionen = SubjectSeeder::definitions();

        foreach ($definitionen as $definition) {
            $code = (string) $definition['code'];
            $zeile = $bestand->get($code);

            $this->assertNotNull($zeile, "Fach {$code} steht im Seeder, fehlt aber in der Datenbank.");

            $this->assertSame($definition['name'], $zeile->name, "Name von {$code}");
            $this->assertSame($definition['color'], $zeile->color, "Farbe von {$code}");
            $this->assertSame($definition['aufgabenfeld'], $zeile->aufgabenfeld, "Aufgabenfeld von {$code}");
            $this->assertSame((int) $definition['sort_order'], (int) $zeile->sort_order, "sort_order von {$code}");

            foreach (self::FLAGS as $flag) {
                $this->assertSame(
                    (bool) $definition[$flag],
                    (bool) $zeile->{$flag},
                    "{$flag} von {$code}"
                );
            }
        }

        $unbekannt = array_values(array_diff(
            $bestand->keys()->all(),
            array_column($definitionen, 'code')
        ));

        $this->assertSame([], $unbekannt, 'In der Datenbank stehen Fächer, die der Seeder nicht kennt.');
    }

    public function test_jedes_abiturfach_traegt_ein_aufgabenfeld(): void
    {
        // § 61 Abs. 2 S. 1: Die vier Fächer des ersten Prüfungsteils müssen
        // die drei Aufgabenfelder nach § 18 abdecken. Ein Abiturfach ohne
        // Aufgabenfeld ist für diese Prüfung nicht auswertbar. Sport trägt
        // bewusst 'sport' und gehört zu keinem der drei Felder.
        $ohneAufgabenfeld = $this->katalog()
            ->where('can_abitur', true)
            ->whereNull('aufgabenfeld')
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame(
            [],
            $ohneAufgabenfeld,
            'Abiturfächer ohne Aufgabenfeld: ' . implode(', ', $ohneAufgabenfeld)
        );
    }

    public function test_religionslehre_und_ethik_stehen_im_aufgabenfeld_zwei(): void
    {
        // Die katholische Religionslehre stand bis 25.08.2026 im Aufgabenfeld I,
        // weil sie im Seeder unter "Künstlerische Fächer" einsortiert war.
        foreach (['K', 'Ev', 'Ort', 'Eth'] as $code) {
            $this->assertSame(
                'gesellschaftswissenschaftlich',
                $this->katalog()->where('code', $code)->value('aufgabenfeld'),
                "Aufgabenfeld von {$code}"
            );
        }
    }

    public function test_alle_fremdsprachen_sind_als_fremdsprache_gekennzeichnet(): void
    {
        // § 61 Abs. 1 S. 2 verlangt zwei Fremdsprachen unter den acht
        // Prüfungsfächern; die Validierung zählt sie über dieses Flag.
        // Griechisch, Türkisch und Französisch spätbeginnend fehlten hier bis
        // 25.08.2026 — eine Wahl mit Griechisch als zweiter Fremdsprache wäre
        // fälschlich abgewiesen worden.
        $erwartet = ['E', 'F', 'Fs', 'Gr', 'It', 'L', 'Ru', 'Sp', 'Trs'];

        $tatsaechlich = $this->katalog()
            ->where('is_foreign_language', true)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        sort($tatsaechlich);

        $this->assertSame($erwartet, $tatsaechlich);
    }

    public function test_spaet_beginnende_sprachen_entsprechen_anlage_3(): void
    {
        // Anlage 3 führt je Sprache eine Spalte "fortgeführt" und eine Spalte
        // "spät beginnend". Ohne Eintrag in der zweiten Spalte stehen
        // Englisch, Griechisch und Latein; Türkisch gibt es umgekehrt nur
        // spät beginnend. Chinesisch, Polnisch und Tschechisch führt der
        // Katalog nicht — sie werden an der Schule nicht angeboten.
        $erwartet = ['F', 'Fs', 'It', 'Ru', 'Sp', 'Trs'];

        $tatsaechlich = $this->katalog()
            ->where('can_be_late_starting', true)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        sort($tatsaechlich);

        $this->assertSame($erwartet, $tatsaechlich);
    }

    public function test_nur_faecher_aus_anlage_3_und_anlage_4_nr_1_sind_abiturfaecher(): void
    {
        // § 48 Abs. 1 S. 8, über § 61 Abs. 1 S. 4 auch für externe Bewerber:
        // wählbar ist nur, was in Anlage 3 oder in Anlage 4 Nr. 1 steht.
        // Anlage 4 Nr. 1 umfasst allein Wirtschaftsinformatik und
        // Sozialwissenschaftliche Arbeitsfelder — die Nummern 2 und 3 der
        // Anlage 4 sind vom Verweis nicht erfasst. Damit fallen heraus:
        // Psychologie (Nr. 2.2), Philosophie (Nr. 3.2) und
        // Wirtschaftswissenschaften (in keiner der beiden Anlagen).
        // Beratungslehrer und Deutsch als Zweitsprache sind keine
        // Unterrichtsfächer der Qualifikationsphase.
        $erwartet = ['BL', 'DZ', 'Phi', 'Psy', 'Ww'];

        $keinAbiturfach = $this->katalog()
            ->where('can_abitur', false)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        sort($keinAbiturfach);

        $this->assertSame($erwartet, $keinAbiturfach);
    }

    public function test_sport_gehoert_zu_keinem_der_drei_aufgabenfelder(): void
    {
        // § 18 Abs. 1 nennt drei Aufgabenfelder; Anlage 3 führt Sport (wie das
        // W-Seminar) außerhalb davon. Der Katalog bildet das mit dem eigenen
        // Wert 'sport' ab, damit die Abdeckungsprüfung nach § 61 Abs. 2 S. 1
        // ein Sportfach nicht fälschlich einem Aufgabenfeld zurechnet.
        $sport = $this->katalog()->where('aufgabenfeld', 'sport')->orderBy('code')->pluck('code')->all();

        sort($sport);

        $this->assertSame(['Sm', 'Smw', 'Sw'], $sport);
    }

    public function test_der_seeder_ist_idempotent(): void
    {
        // created_at wird bewusst mitgeprüft: Der bisherige Seeder hat es bei
        // jedem Lauf neu gesetzt, weil updateOrInsert dieselben Werte für
        // Anlegen und Aktualisieren verwendet.
        $spalten = [
            'code', 'name', 'color', 'aufgabenfeld',
            'is_foreign_language', 'is_science', 'can_be_late_starting',
            'sort_order', 'can_abitur', 'created_at',
        ];

        $vorher = $this->katalog()->select($spalten)->orderBy('code')->get()->toArray();

        (new SubjectSeeder())->run();

        $nachher = $this->katalog()->select($spalten)->orderBy('code')->get()->toArray();

        $this->assertEquals($vorher, $nachher);
        $this->assertCount(count(SubjectSeeder::definitions()), $nachher);
    }
}
