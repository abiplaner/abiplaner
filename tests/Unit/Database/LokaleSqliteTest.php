<?php

namespace Tests\Unit\Database;

use App\Database\DeutscheKollation;
use App\Database\LokaleSqliteVerbindung;
use App\Support\Sql;
use PDO;
use Tests\TestCase;

/**
 * Lokale Edition (SQLite): deutsche Sortierung und die portablen SQL-Bausteine.
 * Läuft gegen eine eigene SQLite-:memory:-Verbindung, unabhängig von der
 * Edition, in der die Suite läuft.
 */
class LokaleSqliteTest extends TestCase
{
    private LokaleSqliteVerbindung $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new LokaleSqliteVerbindung(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        $this->db->statement('create table personen (id integer primary key, nachname text, tag text, rang integer)');
        foreach ([
            ['Zander', '2026-05-15', 10],   // Freitag
            ['Ärztin', '2026-05-11', 2],    // Montag
            ['müller', '2026-05-16', 3],    // Samstag
            ['Abel', '2026-05-17', 1],      // Sonntag
            ['Öztürk', '2026-05-12', 20],
            ['Mayer', '2026-05-13', 5],
        ] as [$name, $tag, $rang]) {
            $this->db->table('personen')->insert(['nachname' => $name, 'tag' => $tag, 'rang' => $rang]);
        }
    }

    public function test_namen_sortieren_wie_mariadb(): void
    {
        $this->assertSame(
            ['Abel', 'Ärztin', 'Mayer', 'müller', 'Öztürk', 'Zander'],
            $this->db->table('personen')->orderBy('nachname')->pluck('nachname')->all()
        );
    }

    public function test_zahlen_bleiben_numerisch_sortiert(): void
    {
        $this->assertSame(
            [1, 2, 3, 5, 10, 20],
            $this->db->table('personen')->orderBy('rang')->pluck('rang')->map(fn ($r) => (int) $r)->all()
        );
    }

    public function test_ersatzschluessel_ohne_intl_folgt_din_5007(): void
    {
        $this->assertSame('arztin', DeutscheKollation::schluessel('Ärztin'));
        $this->assertSame('strasse', DeutscheKollation::schluessel('Straße'));
        $this->assertLessThan(0, strcmp(DeutscheKollation::schluessel('Öztürk'), DeutscheKollation::schluessel('Zander')));
    }

    public function test_wochentag_freitag_unter_sqlite(): void
    {
        $treffer = $this->db->table('personen')
            ->whereRaw(Sql::wochentagIst('sqlite', 'tag', 5))
            ->pluck('nachname')->all();

        $this->assertSame(['Zander'], $treffer);
        $this->assertSame("DAYOFWEEK(date) = 6", Sql::wochentagIst('mysql', 'date', 5));
        $this->assertSame("DAYOFWEEK(date) = 1", Sql::wochentagIst('mysql', 'date', 7));
    }

    public function test_position_in_wie_field(): void
    {
        $this->db->statement('create table plaetze (id integer primary key, seat_type text)');
        foreach (['reserve', 'blocked', 'available', 'reserve'] as $typ) {
            $this->db->table('plaetze')->insert(['seat_type' => $typ]);
        }

        // FIELD liefert 0 für Werte außerhalb der Liste — die stehen vorn.
        $this->assertSame(
            ['blocked', 'available', 'reserve', 'reserve'],
            $this->db->table('plaetze')
                ->orderByRaw(Sql::positionIn('seat_type', ['available', 'reserve']))
                ->orderBy('id')
                ->pluck('seat_type')->all()
        );
    }

    public function test_verketten_unter_sqlite_und_mariadb(): void
    {
        $ausdruck = Sql::verketten('sqlite', "'__keep_'", 'id');
        $this->assertSame(
            '__keep_1',
            $this->db->table('personen')->where('id', 1)->value($ausdruck)
        );
        $this->assertSame(
            "CONCAT('__keep_', id)",
            Sql::verketten('mysql', "'__keep_'", 'id')->getValue($this->db->getQueryGrammar())
        );
    }
}
