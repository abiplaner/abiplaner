<?php

namespace Tests\Tenant;

use App\Models\ExamPeriod;
use App\Models\LkKurs;
use App\Models\LkWahl;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\WseminarKurs;
use App\Models\WseminarWahl;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Der Rueckbau von school_id aus den vier Wahl- und Kurstabellen (30.08.2026).
 *
 * In einer Datenbank pro Schule traegt die Spalte keine Information. Sie war
 * aber nicht bloss ueberfluessig, sondern gefaehrlich: `bigint` gegen die
 * zentrale Schulnummer als `varchar(4)`. Aus '0042' wird beim Vergleich die
 * Zahl 42, und `181 = '0042'` ergibt 0 — daran waren am 29.08. 287
 * Datensaetze unsichtbar geworden, ohne Fehler und ohne Meldung.
 *
 * Diese Tests halten fest, dass die Spalte weg ist UND dass die Masken ohne
 * sie arbeiten. Das zweite ist das eigentliche Risiko des Rueckbaus: 47
 * Filterausdruecke sind entfallen, und ein uebersehener haette einen 500er
 * hinterlassen.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter SchoolIdRueckbau
 */
class SchoolIdRueckbauTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'planner-rueckbau-9999@test.invalid';

    /** @var list<string> */
    private const TABELLEN = ['lk_kurse', 'lk_wahlen', 'wseminar_kurse', 'wseminar_wahlen'];

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
     * Ein Schueler fuer die Wahlen. `schueler_id` ist NOT NULL — eine Wahl
     * ohne Prueflinge gibt es nicht.
     */
    private function schueler(string $nachname): Student
    {
        $zeitraum = ExamPeriod::first();
        $this->assertNotNull($zeitraum, 'Die Testschule hat keinen Pruefungszeitraum.');

        return Student::create([
            'exam_period_id' => $zeitraum->id,
            'external_id' => 'RB-' . $nachname,
            'grade' => '11',
            'firstname' => 'Rueck',
            'lastname' => $nachname,
        ]);
    }

    private function fach(): Subject
    {
        $fach = Subject::first();
        $this->assertNotNull($fach, 'Der Faecherkatalog ist leer.');

        return $fach;
    }

    #[Test]
    public function die_spalte_steht_in_keiner_der_vier_tabellen_mehr(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            $this->assertFalse(
                Schema::hasColumn($tabelle, 'school_id'),
                "In {$tabelle} steht school_id noch."
            );
        }
    }

    /**
     * Die Gegenprobe zur Zusicherung darueber: Die Tabellen gibt es
     * ueberhaupt. Ohne sie waere hasColumn() auch dann false, wenn die
     * Migration die Tabelle mitgerissen haette.
     */
    #[Test]
    public function die_vier_tabellen_stehen_weiterhin(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            $this->assertTrue(
                Schema::hasTable($tabelle),
                "Die Tabelle {$tabelle} fehlt."
            );
        }
    }

    /**
     * Anlegen ohne school_id — frueher stand der Schluessel in jedem
     * create()-Aufruf und in $fillable.
     */
    #[Test]
    public function eine_lk_wahl_laesst_sich_ohne_school_id_anlegen(): void
    {
        $fach = $this->fach();

        $wahl = LkWahl::create([
            'schueler_id' => $this->schueler('Lkwahl')->id,
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'erstwahl_subject_id' => $fach->id,
            'zweitwahl_subject_id' => $fach->id,
            'prioritaet_erhalten' => 'keine',
        ]);

        $this->assertDatabaseHas('lk_wahlen', ['id' => $wahl->id, 'schuljahr' => '2096/97']);
    }

    #[Test]
    public function ein_wseminar_kurs_laesst_sich_ohne_school_id_anlegen(): void
    {
        $fach = $this->fach();

        $kurs = WseminarKurs::create([
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'subject_id' => $fach->id,
            'titel' => 'Rueckbau-Probe',
            'max_teilnehmer' => 15,
            'kurs_nummer' => 1,
        ]);

        $this->assertDatabaseHas('wseminar_kurse', ['id' => $kurs->id, 'titel' => 'Rueckbau-Probe']);
    }

    /**
     * Die vier Masken, aus denen die Filter entfallen sind. Ein uebersehener
     * Ausdruck haette hier einen 500er hinterlassen — genau die Klasse von
     * Fehler, die der Rueckbau beseitigen soll.
     *
     * @return list<array{0: string}>
     */
    public static function maskenAnbieten(): array
    {
        return [
            ['lk-wahlen.index'],
            ['lk-wahlen.create'],
            ['lk-kurse.index'],
            ['wseminar-wahlen.index'],
            ['wseminar-kurse.index'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('maskenAnbieten')]
    public function die_masken_der_wahlen_und_kurse_laden(string $routenName): void
    {
        $this->actingAs($this->planner)
            ->get(route($routenName))
            ->assertOk();
    }

    /**
     * Und die beiden Planungsmasken, in denen die meisten Filter standen
     * (LkZuteilungController und WseminarZuteilungController).
     */
    #[Test]
    public function die_zuteilungsmasken_laden(): void
    {
        // Ohne Wahlen leiten beide Masken bewusst auf die Wahlliste zurueck
        // („Keine Wahlen vorhanden") — dann pruefte der Test die Weiterleitung
        // und nicht die Maske. Also erst je eine Wahl anlegen.
        $fach = $this->fach();
        LkWahl::create([
            'schueler_id' => $this->schueler('Planen1')->id,
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'erstwahl_subject_id' => $fach->id,
            'zweitwahl_subject_id' => $fach->id,
            'prioritaet_erhalten' => 'keine',
        ]);
        WseminarWahl::create([
            'schueler_id' => $this->schueler('Planen2')->id,
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'prioritaet_erhalten' => 'keine',
        ]);

        // Schuljahr und Jahrgang stehen in der Adresse; ohne sie nimmt die
        // Maske das laufende Schuljahr und Jahrgang 12 an und faende die eben
        // angelegten Wahlen nicht.
        foreach (['lk-kurse.planen', 'wseminar-kurse.planen'] as $routenName) {
            $this->actingAs($this->planner)
                ->get(route($routenName, ['schuljahr' => '2096/97', 'jahrgang' => 11]))
                ->assertOk();
        }
    }

    /**
     * Drag & Drop in der LK-Zuteilung war kaputt — und niemand konnte sagen,
     * warum.
     *
     * `updateZuteilung()` verglich `$kurs->school_id !== $user->school_id`,
     * also **streng**. Die Ganzzahlspalte trug 189 (eine bigint-Spalte kann
     * die fuehrende Null einer Schulnummer nicht halten), zentral steht
     * '0042' — '42' !== '0042' trifft immer zu, die Antwort war stets
     * „Keine Berechtigung" mit Status 403.
     *
     * Derselbe Datenfehler traf die Methode daneben nicht: `update()` verglich
     * mit `!=`, und lose sind '42' und '0042' numerisch gleich.
     */
    #[Test]
    public function die_zuteilung_per_drag_and_drop_wird_gespeichert(): void
    {
        $fach = $this->fach();

        $kurs = LkKurs::create([
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'subject_id' => $fach->id,
            'kurs_nummer' => 1,
            'ist_extern' => false,
            'lehrer_id' => null,
            'notizen' => null,
        ]);

        $wahl = LkWahl::create([
            'schueler_id' => $this->schueler('Ziehen')->id,
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'erstwahl_subject_id' => $fach->id,
            'zweitwahl_subject_id' => $fach->id,
            'prioritaet_erhalten' => 'keine',
        ]);

        $antwort = $this->actingAs($this->planner)->postJson(
            route('lk-kurse.update-zuteilung', ['id' => $kurs->id]),
            ['wahl_ids' => [$wahl->id]]
        );

        $antwort->assertOk()->assertJson(['success' => true]);

        // Und die Zuweisung steht auch wirklich in der Datenbank. Ohne diese
        // zweite Zusicherung waere der Test auch dann gruen, wenn die
        // Schleife die Wahl still uebergeht — genau das tat sie vorher.
        $this->assertSame(
            $kurs->id,
            $wahl->fresh()->zuteilung_lk_kurs_id,
            'Die Wahl muss nach dem Ziehen dem Kurs zugeordnet sein.'
        );
    }

    /**
     * Nichts geht verloren: Ein angelegter Kurs ist ueber das Modell wieder
     * auffindbar, obwohl kein Filter mehr auf die Schule zeigt.
     */
    #[Test]
    public function angelegte_datensaetze_bleiben_auffindbar(): void
    {
        $fach = $this->fach();

        $kurs = LkKurs::create([
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'subject_id' => $fach->id,
            'kurs_nummer' => 1,
            'ist_extern' => false,
            'lehrer_id' => null,
            'notizen' => null,
        ]);

        $this->assertTrue(
            LkKurs::query()->where('schuljahr', '2096/97')->get()->contains('id', $kurs->id),
            'Der eben angelegte Kurs muss ueber die Modellabfrage wieder auftauchen.'
        );

        $wahl = WseminarWahl::create([
            'schueler_id' => $this->schueler('Wsemwahl')->id,
            'schuljahr' => '2096/97',
            'jahrgang' => 11,
            'prioritaet_erhalten' => 'keine',
        ]);

        $this->assertTrue(
            WseminarWahl::query()->where('schuljahr', '2096/97')->get()->contains('id', $wahl->id)
        );
    }
}
