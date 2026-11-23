<?php

namespace Tests\Feature;

use App\Services\FehlerKennung;
use Illuminate\Database\QueryException;
use RuntimeException;
use Tests\TestCase;

/**
 * Der Kurzbefund des letzten Fehlers (Modul 6.4).
 *
 * **Der Test, um den es hier wirklich geht, ist der zweite.**
 * `QueryException::getMessage()` traegt das SQL samt eingesetzter Bindings —
 * in dieser Anwendung also Schuelernamen. Der Kurzbefund wandert in eine
 * zentrale Tabelle und in eine Maske; kaeme die Meldung roh dort an, waere das
 * ein Datenleck, das niemandem auffiele.
 */
class FehlerKennungTest extends TestCase
{
    public function test_die_kennung_bleibt_im_request_dieselbe(): void
    {
        $erste = FehlerKennung::aktuelle();

        $this->assertSame($erste, FehlerKennung::aktuelle());
        $this->assertSame($erste, FehlerKennung::erfassen(new RuntimeException('x'))['id']);
    }

    public function test_ein_datenbankfehler_gibt_weder_sql_noch_werte_preis(): void
    {
        $roh = new QueryException(
            'mysql',
            'select * from students where last_name = ?',
            ['Huber'],
            new RuntimeException('SQLSTATE[42S22]: Column not found')
        );

        // Gegenprobe: Der Fehler selbst traegt beides. Faellt diese Zusicherung
        // eines Tages, hat das Framework die Meldung geaendert — und der Test
        // darunter prueft nichts mehr.
        $this->assertStringContainsString('Huber', $roh->getMessage());
        $this->assertStringContainsString('last_name', $roh->getMessage());

        $befund = FehlerKennung::erfassen($roh);

        $this->assertStringNotContainsString('Huber', $befund['meldung']);
        $this->assertStringNotContainsString('last_name', $befund['meldung']);
        $this->assertStringContainsString('SQL und Werte wurden entfernt', $befund['meldung']);
        $this->assertSame(QueryException::class, $befund['klasse']);
    }

    public function test_eine_lange_meldung_wird_gekuerzt(): void
    {
        $befund = FehlerKennung::erfassen(new RuntimeException(str_repeat('a', 900)));

        $this->assertLessThanOrEqual(510, strlen($befund['meldung']));
    }

    /** Framework-Rahmen fuellen den Kasten und sagen nichts. */
    public function test_der_aufrufweg_enthaelt_keine_framework_rahmen(): void
    {
        $befund = FehlerKennung::erfassen(new RuntimeException('x'));

        $this->assertLessThanOrEqual(5, count($befund['rahmen']));

        foreach ($befund['rahmen'] as $rahmen) {
            $this->assertStringNotContainsString('vendor/', $rahmen);
        }
    }

    /** Der Basispfad ist auf jedem Stack ein anderer und sagt nichts. */
    public function test_die_fehlerstelle_steht_ohne_basispfad(): void
    {
        $befund = FehlerKennung::erfassen(new RuntimeException('x'));

        $this->assertStringNotContainsString(base_path(), $befund['datei']);
        $this->assertStringContainsString('FehlerKennungTest.php', $befund['datei']);
    }
}
