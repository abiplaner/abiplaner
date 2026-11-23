<?php

namespace Tests\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Jedes Feld in $fillable und $casts muss es in der Datenbank auch geben.
 *
 * Der wiederkehrende Fehler dieses Projekts hat immer dieselbe Form: Ein
 * Attribut, das keine Spalte hat, liefert stumm null. Nichts bricht, nichts
 * loggt — die Bedingung ist ab da einfach dauerhaft falsch, die Anzeige
 * dauerhaft leer. So sind 287 Datensaetze unsichtbar geworden, so lief die
 * LK-Zuteilung in ein dauerhaftes 403, so stand jeder Pruefungszeitraum als
 * "Abgeschlossen" da.
 *
 * Bei $fillable ist der Schaden groesser als eine leere Anzeige: Ein Feld, das
 * keine Spalte hat, laesst jedes create() mit diesem Schluessel in "Unknown
 * column" laufen.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter SchemaAbgleich
 */
class SchemaAbgleichTest extends TenantTestCase
{
    /**
     * ActivityLog schreibt in zwei unterschiedlich geschnittene Tabellen: Die
     * zentrale activity_logs fuehrt eine school_id, die des Mandanten nicht.
     * Der creating-Hook setzt das Feld nur ohne initialisierten Mandanten.
     */
    private const ERLAUBTE_AUSNAHMEN = [
        'App\Models\ActivityLog' => ['school_id'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Ohne Mandanten stehen die Tenant-Tabellen nicht zur Verfuegung; der
        // Test wuerde dann jedes Tenant-Modell faelschlich anmahnen.
    }

    protected function tearDown(): void
    {

        parent::tearDown();
    }

    #[Test]
    public function kein_modell_fuehrt_ein_feld_ohne_spalte(): void
    {
        $befunde = [];

        foreach ($this->modelle() as $klasse) {
            /** @var Model $modell */
            $modell = new $klasse();

            $verbindung = $modell->getConnectionName() ?? config('database.default');
            $tabelle = $modell->getTable();

            $schema = Schema::connection($verbindung);

            if (!$schema->hasTable($tabelle)) {
                $befunde[] = "{$klasse}: Tabelle {$tabelle} fehlt auf Verbindung {$verbindung}.";
                continue;
            }

            $spalten = $schema->getColumnListing($tabelle);
            $ausnahmen = self::ERLAUBTE_AUSNAHMEN[$klasse] ?? [];

            foreach ($modell->getFillable() as $feld) {
                if (in_array($feld, $spalten, true) || in_array($feld, $ausnahmen, true)) {
                    continue;
                }
                $befunde[] = "{$klasse}: fillable '{$feld}' hat keine Spalte in {$tabelle}.";
            }

            foreach (array_keys($modell->getCasts()) as $feld) {
                if (in_array($feld, $spalten, true) || in_array($feld, $ausnahmen, true)) {
                    continue;
                }
                // Akzessoren und angehaengte Attribute duerfen gecastet werden,
                // ohne eine Spalte zu haben.
                $akzessor = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $feld))).'Attribute';
                if (method_exists($modell, $akzessor) || in_array($feld, $modell->getAppends(), true)) {
                    continue;
                }
                $befunde[] = "{$klasse}: cast '{$feld}' hat keine Spalte in {$tabelle}.";
            }
        }

        sort($befunde);

        $this->assertSame([], $befunde, sprintf(
            "Diese Felder gibt es im Modell, aber nicht in der Datenbank:\n  %s\n".
            "Ein solches Attribut liefert stumm null — oder laesst create() in".
            " \"Unknown column\" laufen.",
            implode("\n  ", $befunde)
        ));
    }

    /**
     * Gegenprobe: Der Test findet ein erfundenes Feld auch wirklich. Ohne sie
     * waere er auch dann gruen, wenn die Schleife nie ein Modell erreicht.
     */
    #[Test]
    public function ein_erfundenes_feld_faellt_auf(): void
    {
        $modelle = iterator_to_array($this->modelle());

        $this->assertGreaterThan(50, count($modelle), 'Es muessen alle Modelle geprueft werden.');

        $spalten = Schema::getColumnListing('exam_periods');

        $this->assertContains('week_1_start', $spalten);
        $this->assertNotContains('exams_per_day', $spalten);
        $this->assertNotContains('exam_duration_minutes', $spalten);
    }

    /**
     * @return iterable<class-string<Model>>
     */
    private function modelle(): iterable
    {
        foreach (glob(app_path('Models/*.php')) ?: [] as $datei) {
            /** @var class-string $klasse */
            $klasse = 'App\\Models\\'.basename($datei, '.php');

            if (!class_exists($klasse)) {
                continue;
            }

            $spiegel = new ReflectionClass($klasse);

            if ($spiegel->isAbstract() || !$spiegel->isSubclassOf(Model::class)) {
                continue;
            }

            yield $klasse;
        }
    }
}
