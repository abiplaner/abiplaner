<?php

namespace Tests\Tenant;

use App\Models\School;
use App\Models\User;
use App\Policies\SchoolPolicy;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Schulnummer ist eine Zeichenkette — vier Stellen, mit fuehrender Null.
 *
 * Sie ist in jeder zentralen Tabelle `varchar(4)`. Wird sie unterwegs zur Zahl,
 * verliert sie die fuehrende Null und passt danach zu nichts mehr: Aus '0042'
 * wird 42. Genau das hat schon dreimal zugeschlagen — 287 unsichtbare
 * Datensaetze (29.08.), Drag & Drop in der LK-Zuteilung mit dauerhaftem 403
 * und SchoolPolicy::view().
 *
 * Diese Tests halten den Grundsatz fest, damit die naechste int-Typisierung
 * auffaellt, bevor sie in Betrieb geht.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter Schulnummer
 */
class SchulnummerTest extends TenantTestCase
{
    /** Die zentralen Tabellen, die eine Schulnummer fuehren. */
    private const ZENTRALE_SPALTEN = [
        'schools' => 'school_number',
        'users' => 'school_id',
        'activity_logs' => 'school_id',
        'support_faelle' => 'school_id',
        'domains' => 'school_id',
    ];

    #[Test]
    public function jede_zentrale_spalte_fuehrt_die_schulnummer_als_varchar(): void
    {
        foreach (self::ZENTRALE_SPALTEN as $tabelle => $spalte) {
            // SQLite merkt sich den deklarierten Typ ohne Länge.
            $typ = collect(DB::select("select name, type as t from pragma_table_info('{$tabelle}')"))
                ->where('name', $spalte)->values()->all();

            $this->assertNotEmpty($typ, "{$tabelle}.{$spalte} fehlt.");
            $this->assertSame(
                'varchar',
                $typ[0]->t,
                "{$tabelle}.{$spalte} muesste varchar(4) sein, ist aber {$typ[0]->t}. "
                    . 'Eine Ganzzahlspalte kann die fuehrende Null nicht halten.'
            );
        }
    }

    /**
     * Die Gegenprobe zu dem, was schiefgehen kann: Wird die Nummer zur Zahl,
     * passt sie nicht mehr zur Datenbank. Der Test dokumentiert das Verhalten
     * von PHP, damit niemand es fuer eine Nebensaechlichkeit haelt.
     */
    #[Test]
    public function eine_schulnummer_als_zahl_passt_nicht_mehr_zur_datenbank(): void
    {
        $ausDerDatenbank = '0042';

        $alsZahl = (int) $ausDerDatenbank;

        $this->assertSame(42, $alsZahl);
        $this->assertNotSame($ausDerDatenbank, (string) $alsZahl);
        $this->assertFalse(
            $alsZahl === $ausDerDatenbank,
            'Ein strenger Vergleich zwischen Zahl und Zeichenkette trifft nie zu.'
        );
    }

    /**
     * Keine Methode darf die Schulnummer als `int` entgegennehmen — PHP
     * wandelt sie dann still um. Am 30.08.2026 waren es 29 Signaturen; 24
     * brauchten den Wert gar nicht, fuenf sind auf `string` umgestellt.
     */
    #[Test]
    public function keine_signatur_typisiert_die_schulnummer_als_zahl(): void
    {
        $treffer = [];

        foreach ($this->phpDateien(base_path('app')) as $datei) {
            $inhalt = (string) file_get_contents($datei);

            // Nur echte Typangaben, nicht der erklaerende Kommentar in
            // AbstractWahlImportService, der den Ausdruck in Backticks nennt.
            if (preg_match_all('/(?:\?\s*)?(?<![`\w])int\s+\$schoolId/', $inhalt, $funde, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($funde[0] as [$text, $position]) {
                $zeile = substr_count(substr($inhalt, 0, $position), "\n") + 1;
                $treffer[] = str_replace(base_path() . '/', '', $datei) . ':' . $zeile;
            }
        }

        $this->assertSame([], $treffer, sprintf(
            "Diese Stellen nehmen die Schulnummer als Zahl entgegen und verlieren".
            " dabei die fuehrende Null:\n  %s\nAbhilfe: string \$schoolId — oder".
            " den Parameter streichen, wenn er ohnehin nicht gebraucht wird.",
            implode("\n  ", $treffer)
        ));
    }

    // ── SchoolPolicy::view() ───────────────────────────────────────────────

    /**
     * Die Policy verglich `$user->school_id === $school->id`. Die Tabelle
     * `schools` hat gar keine Spalte `id` — der Wert war immer null, die
     * Pruefung damit fuer jeden Nicht-Super-Admin immer falsch. Irrefuehrend
     * war der Docblock des Modells, der ein `$id` als „Alias fuer
     * school_number" auswies; den gibt es nicht.
     */
    #[Test]
    public function die_schule_hat_kein_feld_id(): void
    {
        $schule = $this->createTestSchool();

        $this->assertNull($schule->id, 'schools hat keine Spalte id.');
        $this->assertSame($schule->school_number, $schule->getKey());
    }

    #[Test]
    public function ein_planer_darf_seine_eigene_schule_sehen(): void
    {
        $schule = $this->createTestSchool();

        $planer = User::factory()->create([
            'email' => 'planner-schulnummer-9999@test.invalid',
            'role' => 'planner',
            'school_id' => $schule->school_number,
        ]);

        try {
            $this->assertTrue(
                (new SchoolPolicy())->view($planer, $schule),
                'Ein Planer muss die Seite seiner eigenen Schule sehen duerfen.'
            );
        } finally {
            $planer->delete();
        }
    }

    /**
     * Gegenprobe: eine fremde Schule bleibt zu. Ohne sie waere der Test
     * darueber auch dann gruen, wenn die Policy schlicht `true` zurueckgaebe.
     */
    #[Test]
    public function ein_planer_darf_eine_fremde_schule_nicht_sehen(): void
    {
        $eigene = $this->createTestSchool();

        $fremde = new School(['school_number' => '0001', 'name' => 'Fremdes Gymnasium']);

        $planer = User::factory()->create([
            'email' => 'planner-fremd-9999@test.invalid',
            'role' => 'planner',
            'school_id' => $eigene->school_number,
        ]);

        try {
            $this->assertFalse((new SchoolPolicy())->view($planer, $fremde));
        } finally {
            $planer->delete();
        }
    }

    /**
     * @return iterable<string>
     */
    private function phpDateien(string $verzeichnis): iterable
    {
        $lauf = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($verzeichnis));

        /** @var \SplFileInfo $datei */
        foreach ($lauf as $datei) {
            if ($datei->isFile() && $datei->getExtension() === 'php') {
                yield $datei->getPathname();
            }
        }
    }
}
