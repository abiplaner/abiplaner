<?php

namespace Tests\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Throwable;

/**
 * Kein Attributzugriff in einer Maske darf ins Leere zeigen.
 *
 * Der dritte Test derselben Familie:
 *
 *   - SchemaAbgleichTest        $fillable und $casts gegen die Spalten
 *   - RoutennamenInMaskenTest   route('...') gegen die Routenliste
 *   - dieser hier               Attribute in den Masken gegen die Spalten
 *
 * Der wiederkehrende Fehler dieses Projekts ist ein Attribut ohne Spalte.
 * Eloquent liefert dafuer still null — kein Fehler, kein Logeintrag. In einer
 * Maske heisst das: ein leeres Feld, eine Bedingung, die nie zutrifft, oder ein
 * 500er, sobald eine Methode auf dem null aufgerufen wird. Am 30.08.2026 hat
 * diese eine Mechanik fuenf Seiten zerlegt, vier davon in ein und derselben
 * Maske ($assignment->layout, $assignment->room->name).
 *
 * Der Test arbeitet auf zwei Ebenen:
 *
 *   1. Typisiert. Laesst sich die Variable einem Modell zuordnen — ueber eine
 *      var-Angabe im Docblock der Maske oder, ersatzweise, ueber ihren Namen —, dann
 *      wird jedes Glied der Kette gegen genau dieses Modell geprueft und die
 *      Kette ueber die Beziehungen weiterverfolgt.
 *   2. Global. Traegt die Variable kein bekanntes Modell, bleibt die schwaechere
 *      Frage: Gibt es diesen Namen ueberhaupt irgendwo — als Spalte in einer der
 *      beiden Datenbanken oder als Methode, Akzessor oder erklaertes Attribut an
 *      irgendeinem Modell? Wenn nicht, ist er ein Fund.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter AttributeInMasken
 */
class AttributeInMaskenTest extends TenantTestCase
{
    /**
     * Variablen, die trotz ihres Namens kein Eloquent-Modell tragen.
     *
     * Das ist die einzige Positivliste, die dieser Test selbst fuehrt — und sie
     * bleibt bewusst klein. Alles andere gehoert dorthin, wo es hingehoert: Ein
     * Attribut, das ein Modell zur Laufzeit bekommt, ohne eine Spalte zu haben,
     * wird im Modell selbst als "@property" mit einer der Begruendungen aus
     * MARKIERUNGEN erklaert (siehe unten). Eine Maske, deren Variable einen
     * anderen Typ traegt als ihr Name vermuten laesst, sagt das mit einem
     * var-Docblock — das ist in diesem Projekt ohnehin ueblich und hilft auch
     * PHPStan und der IDE.
     *
     * Hier landet nur, was sich weder so noch so ausdruecken laesst.
     *
     * Schluessel: Pfad der Maske unterhalb resources/views + ':$' + Variable.
     *
     * @var array<string, string>
     */
    private const NICHT_MODELLGEBUNDEN = [
        // $studentsWithWrongCount kommt aus DB::table('students') im
        // PlanningController (Zeile 231 ff.), nicht aus dem Student-Modell.
        // extern, exam_count und soll sind Alias-Spalten dieser Abfrage. Ein
        // var-Docblock hilft hier nicht: dieselbe Maske fuehrt $student an
        // anderer Stelle als echtes Modell.
        'planning/index.blade.php:$student' => 'Zeilen aus DB::table("students"), kein Student-Modell',

        // fmtDate(\Carbon\Carbon $d, ...) — $d ist ein Datum, ->month und
        // ->year sind Carbon-Eigenschaften.
        'exports/aufgabenabgabe-pdf.blade.php:$d' => 'Carbon-Datum, kein Modell',
    ];

    /**
     * Begruendungen, die ein "@property" ohne Spalte im Modell rechtfertigen.
     *
     * Ein solcher Eintrag ist die Positivliste dieses Tests, nur eben am
     * richtigen Ort. Entscheidend ist die Begruendung dahinter: Ein blankes
     * "@property int|null $foo" — wie es der IDE-Helfer erzeugt — zaehlt
     * ausdruecklich NICHT. Sonst waere die Liste in dem Moment wertlos, in dem
     * jemand ein erfundenes Attribut dokumentiert, statt es zu beheben. Genau
     * das war bei Subject::$abbreviation passiert.
     *
     * @var list<string>
     */
    private const MARKIERUNGEN = [
        'Keine Spalte',      // wird zur Laufzeit gesetzt, z. B. beim Zusammenfuehren
        'Dynamisch gesetzt', // vom Controller an die Instanz gehaengt
        'Aggregat',          // withCount()-Alias, den die Namensregel nicht erkennt
    ];

    /** @var array<string, array<string, true>> Tabelle => Spalten, je Datenbank getrennt */
    private array $spaltenJeSchema = [];

    /** @var array<string, true> Jeder Name, den es irgendwo gibt: Spalte, Methode, Akzessor, erklaertes Attribut */
    private array $bekannteNamen = [];

    /** @var array<string, class-string<Model>> StudlyName => Modellklasse */
    private array $modelle = [];

    /** @var array<class-string<Model>, Model> */
    private array $instanzen = [];

    /** @var array<class-string<Model>, array<string, true>> */
    private array $erklaerteAttribute = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Ohne Mandanten gibt es die Tenant-Tabellen nicht; der Test wuerde
        // dann jedes Tenant-Attribut faelschlich anmahnen.

        $this->ladeSpalten();
        $this->ladeModelle();
    }

    protected function tearDown(): void
    {

        parent::tearDown();
    }

    #[Test]
    public function keine_maske_liest_ein_attribut_ohne_spalte(): void
    {
        $befunde = [];
        $masken = 0;

        foreach ($this->bladeDateien() as $datei) {
            $masken++;
            $kurz = str_replace(resource_path('views').'/', '', $datei);
            foreach ($this->pruefeMaske((string) file_get_contents($datei), $kurz) as $befund) {
                $befunde[] = $befund;
            }
        }

        $this->assertGreaterThan(200, $masken, 'Es muessen alle Masken geprueft werden.');

        sort($befunde);

        $this->assertSame([], $befunde, sprintf(
            "Diese Masken lesen ein Attribut, das es nicht gibt. Eloquent".
            " liefert dafuer still null — die Anzeige bleibt leer, die".
            " Bedingung trifft nie zu, und ein Methodenaufruf darauf beendet".
            " die Seite mit 500:\n  %s\n".
            "Ist das Attribut zur Laufzeit doch da, gehoert die Begruendung als".
            " \"@property\" ins Modell (siehe MARKIERUNGEN) — nicht in diesen Test.",
            implode("\n  ", $befunde)
        ));
    }

    /**
     * Gegenprobe: Die Auswertung findet erfundene Attribute auch wirklich.
     *
     * Ohne sie waere der Test auch dann gruen, wenn der regulaere Ausdruck nie
     * greift oder die Spaltenlisten leer bleiben. Die echten Fundstellen vom
     * 30.08.2026 taugen dafuer nicht mehr — sie sind alle behoben. Deshalb eine
     * eigene Vorrichtung: erfundener Maskeninhalt, direkt durch die Auswertung.
     */
    #[Test]
    public function die_auswertung_findet_ein_erfundenes_attribut(): void
    {
        // Die Grundlage muss stehen, sonst prueft die Auswertung gegen nichts.
        $this->assertArrayHasKey('rooms', $this->spaltenJeSchema);
        $this->assertArrayHasKey('room_number', $this->spaltenJeSchema['rooms']);
        $this->assertGreaterThan(50, count($this->modelle));

        // 1. Typisiert: $room traegt das Modell Room, rooms hat kein 'erfunden'.
        $this->assertSame(
            ['probe.blade.php:1 App\Models\Room->erfunden ($room->erfunden)'],
            $this->pruefeMaske('{{ $room->erfunden }}', 'probe.blade.php')
        );

        // 2. Ueber die Beziehung: derselbe Fund eine Kette weiter. Genau so lag
        //    der Fund vom 30.08. ($assignment->room->name).
        $this->assertSame(
            ['probe.blade.php:1 App\Models\Room->erfunden ($writtenExamAssignment->room->erfunden)'],
            $this->pruefeMaske('{{ $writtenExamAssignment->room->erfunden }}', 'probe.blade.php')
        );

        // 3. Global: $irgendwas traegt kein Modell, den Namen gibt es nirgends.
        $this->assertSame(
            ['probe.blade.php:1 (kein Modell) voellig_erfunden ($irgendwas->voellig_erfunden)'],
            $this->pruefeMaske('{{ $irgendwas->voellig_erfunden }}', 'probe.blade.php')
        );

        // 4. Echte Spalten, Akzessoren, Beziehungen und Methoden bleiben still.
        $this->assertSame([], $this->pruefeMaske(
            '{{ $room->room_number }} {{ $room->seatingTemplate }} {{ $teacher->full_name }}'
            .' {{ $room->getKey() }} {{ $irgendwas->room_number }}',
            'probe.blade.php'
        ));

        // 5. Ein var-Docblock schlaegt den Namen: $timeSlot ist hier kein
        //    TimeSlot, und das Attribut steht am wirklichen Modell.
        $this->assertSame([], $this->pruefeMaske(
            '@php /** @var \App\Models\WrittenExamSupervisionTimeSlot $timeSlot */ @endphp'
            .'{{ $timeSlot->start_time }}',
            'probe.blade.php'
        ));

        // 6. Kommentare zaehlen nicht. Sonst meldet der Test genau die
        //    Erklaerungen, die nach der Reparatur dort stehen.
        $this->assertSame([], $this->pruefeMaske(
            "{{-- {{ \$room->erfunden }} --}}\n@php // kein \$room->erfunden mehr\n@endphp",
            'probe.blade.php'
        ));

        // 7. Die Zeilennummer stimmt auch nach entfernten Kommentaren.
        $this->assertSame(
            ['probe.blade.php:3 App\Models\Room->erfunden ($room->erfunden)'],
            $this->pruefeMaske("{{-- ein\n   Kommentar --}}\n{{ \$room->erfunden }}", 'probe.blade.php')
        );
    }

    /**
     * Wertet eine einzelne Maske aus — der Kern des Tests, und der Punkt, an
     * dem die Gegenprobe ihn mit erfundenem Inhalt aufruft.
     *
     * @return list<string>
     */
    private function pruefeMaske(string $inhalt, string $kurz): array
    {
        // Erst die Typangaben lesen, dann die Kommentare entfernen: Ein
        // var-Docblock IST ein Kommentar.
        $typen = $this->varTypenAusDocblocks($inhalt);
        $inhalt = $this->ohneKommentare($inhalt);
        $befunde = [];

        // $variable, gefolgt von einer oder mehreren ->Gliedern. Die letzte
        // Gruppe faengt eine oeffnende Klammer ein: ein Methodenaufruf ist kein
        // Attributzugriff.
        preg_match_all(
            '/\$([A-Za-z_][A-Za-z0-9_]*)((?:\s*->\s*[A-Za-z_][A-Za-z0-9_]*)+)\s*(\()?/',
            $inhalt,
            $treffer,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($treffer as $t) {
            $variable = $t[1][0];

            // $loop ist Blades eigenes Schleifenobjekt (index, iteration,
            // first, last, remaining, depth). Es hat mit der Datenbank nichts
            // zu tun und traegt nie ein Modell.
            if ($variable === 'loop') {
                continue;
            }

            if (isset(self::NICHT_MODELLGEBUNDEN[$kurz.':$'.$variable])) {
                continue;
            }

            $zeile = substr_count(substr($inhalt, 0, (int) $t[0][1]), "\n") + 1;
            $glieder = preg_split('/\s*->\s*/', trim($t[2][0]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $istAufruf = isset($t[3]) && $t[3][0] === '(';

            // array_key_exists, nicht ??: Eine var-Angabe auf einen Typ
            // ausserhalb von App\Models steht hier als null und soll die
            // Namensregel gerade abschalten, nicht auf sie zurueckfallen.
            $traeger = array_key_exists($variable, $typen)
                ? $typen[$variable]
                : $this->modellFuer($variable);
            $pfad = '$'.$variable;

            foreach ($glieder as $i => $glied) {
                $pfad .= '->'.$glied;

                // Das letzte Glied vor einer Klammer ist ein Methodenaufruf.
                if ($istAufruf && $i === count($glieder) - 1) {
                    break;
                }

                $befund = $this->pruefeGlied($traeger, $glied, $kurz, $zeile, $pfad);

                if ($befund !== null) {
                    $befunde[] = $befund;
                    break;
                }

                $traeger = $traeger instanceof Model
                    ? $this->beziehungsZiel($traeger, $glied)
                    : null;
            }
        }

        return $befunde;
    }

    /**
     * Ein einzelnes Glied der Kette. Gibt den Befund zurueck oder null.
     *
     * Geprueft werden nur Glieder in Kleinschreibung — genau die Form, die ein
     * Spaltenname hat. Ein camelCase-Glied ist im Zweifel ein Methodenname; die
     * Kette laeuft ueber ihn weiter, beurteilt wird er nicht.
     */
    private function pruefeGlied(?Model $traeger, string $glied, string $kurz, int $zeile, string $pfad): ?string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $glied) !== 1) {
            return null;
        }

        if ($traeger instanceof Model) {
            if ($this->kenntAttribut($traeger, $glied)) {
                return null;
            }

            return sprintf('%s:%d %s->%s (%s)', $kurz, $zeile, get_class($traeger), $glied, $pfad);
        }

        if (isset($this->bekannteNamen[$glied]) || $this->istAggregat($glied)) {
            return null;
        }

        return sprintf('%s:%d (kein Modell) %s (%s)', $kurz, $zeile, $glied, $pfad);
    }

    /**
     * withCount() haengt das Ergebnis als '<beziehung>_count' an die Instanz.
     * Eine Spalte gibt es dafuer nie, und eine Positivliste braucht es auch
     * nicht: Die Beziehung selbst ist der Beleg.
     */
    private function istAggregat(string $glied): bool
    {
        if (!str_ends_with($glied, '_count')) {
            return false;
        }

        return isset($this->bekannteNamen[substr($glied, 0, -6)]);
    }

    /**
     * Kennt dieses Modell das Attribut? Spalte, Akzessor, Anhang, Cast,
     * Methode, withCount-Aggregat oder ein begruendetes "@property".
     */
    private function kenntAttribut(Model $modell, string $attribut): bool
    {
        $spalten = $this->spaltenFuer($modell);

        if (isset($spalten[$attribut])) {
            return true;
        }

        if (in_array($attribut, $modell->getAppends(), true)
            || array_key_exists($attribut, $modell->getCasts())
        ) {
            return true;
        }

        foreach ($this->methodenNamen($attribut) as $methode) {
            if (method_exists($modell, $methode)) {
                return true;
            }
        }

        if (str_ends_with($attribut, '_count')) {
            foreach ($this->methodenNamen(substr($attribut, 0, -6)) as $methode) {
                if (method_exists($modell, $methode)) {
                    return true;
                }
            }
        }

        return isset($this->erklaerteAttribute[get_class($modell)][$attribut]);
    }

    /**
     * @return list<string>
     */
    private function methodenNamen(string $attribut): array
    {
        return [$attribut, Str::camel($attribut), 'get'.Str::studly($attribut).'Attribute'];
    }

    /**
     * Die Spalten der Tabelle dieses Modells — aus beiden Datenbanken
     * zusammengefuehrt. Fast jeder Tabellenname kommt nur in einer der beiden
     * vor — subjects liegt zentral, rooms beim Mandanten —, und auf
     * $connection ist kein Verlass: ein paar Modelle sagen es gar nicht.
     *
     * Doppelt gibt es nur activity_logs (zentral mit school_id, beim Mandanten
     * ohne — dieselbe Ausnahme wie in SchemaAbgleichTest) und migrations. Fuer
     * die ist die zusammengefuehrte Liste eine Spur zu grosszuegig; beide
     * kommen in keiner Maske vor.
     *
     * @return array<string, true>
     */
    private function spaltenFuer(Model $modell): array
    {
        return $this->spaltenJeSchema[$modell->getTable()] ?? [];
    }

    /**
     * Das Ziel einer Beziehung, damit die Kette weiterlaufen kann. Ist das
     * Glied keine Beziehung (sondern Spalte oder Akzessor), endet sie hier.
     */
    private function beziehungsZiel(Model $modell, string $glied): ?Model
    {
        foreach ([$glied, Str::camel($glied)] as $methode) {
            if (!method_exists($modell, $methode)) {
                continue;
            }

            try {
                $ergebnis = $modell->{$methode}();
            } catch (Throwable) {
                return null;
            }

            if ($ergebnis instanceof Relation) {
                return $ergebnis->getRelated();
            }
        }

        return null;
    }

    private function modellFuer(string $variable): ?Model
    {
        $klasse = $this->modelle[Str::studly($variable)] ?? null;

        return $klasse === null ? null : $this->instanz($klasse);
    }

    /**
     * @param class-string<Model> $klasse
     */
    private function instanz(string $klasse): Model
    {
        return $this->instanzen[$klasse] ??= new $klasse();
    }

    /**
     * Entfernt Blade- und PHP-Kommentare, laesst aber die Zeilenumbrueche
     * stehen — sonst stimmen die gemeldeten Zeilennummern nicht mehr.
     *
     * Wichtig fuer die Aussagekraft des Tests: Nach jeder Reparatur steht in
     * der Maske ein Kommentar, der den entfernten Zugriff beim Namen nennt
     * ("Kein $assignment->layout: die Beziehung gibt es nicht."). Ohne diesen
     * Schritt meldet der Test genau diese Erklaerungen als neue Funde.
     *
     * Bei '//' bleibt ein Doppelpunkt davor ausgenommen, damit aus einem
     * "https://..." im Markup kein halber Rest wird. Was diese Faustregel zu
     * viel entfernt, kostet hoechstens einen Fund — nie einen falschen Alarm.
     */
    private function ohneKommentare(string $inhalt): string
    {
        $leeren = static fn (array $t): string => str_repeat("\n", substr_count($t[0], "\n"));

        foreach (['/\{\{--.*?--\}\}/s', '#/\*.*?\*/#s', '#(?<!:)//[^\n]*#'] as $muster) {
            $inhalt = (string) preg_replace_callback($muster, $leeren, $inhalt);
        }

        return $inhalt;
    }

    /**
     * Liest die var-Docblocks der Maske: Sie sagen genauer, was eine Variable
     * traegt, als ihr Name es kann. In dieser Anwendung stehen sie ohnehin
     * ueberall dort, wo der Name in die Irre fuehrt — etwa
     * "@ var \App\Models\WrittenExamSupervisionTimeSlot|null $timeSlot", das
     * eben kein TimeSlot ist.
     *
     * Eine Angabe auf einen Typ ausserhalb von App\Models schaltet die
     * Namensregel fuer diese Variable ab: Der Typ ist bekannt, und er ist kein
     * Modell.
     *
     * @return array<string, Model|null>
     */
    private function varTypenAusDocblocks(string $inhalt): array
    {
        preg_match_all(
            '/@var\s+([^\s$]+)\s+\$([A-Za-z_][A-Za-z0-9_]*)/',
            $inhalt,
            $treffer,
            PREG_SET_ORDER
        );

        $typen = [];

        foreach ($treffer as $t) {
            $modell = null;

            foreach (explode('|', $t[1]) as $einzeln) {
                /** @var class-string $klasse */
                $klasse = ltrim($einzeln, '\\?');

                if (isset($this->modelle[class_basename($klasse)])
                    && $this->modelle[class_basename($klasse)] === $klasse
                ) {
                    $modell = $this->instanz($this->modelle[class_basename($klasse)]);
                    break;
                }
            }

            $typen[$t[2]] = $modell;
        }

        return $typen;
    }

    /** Alle Spalten aller Tabellen der (einen) Datenbank. */
    private function ladeSpalten(): void
    {
        foreach (\Illuminate\Support\Facades\Schema::getTableListing(schemaQualified: false) as $tabelle) {
            foreach (\Illuminate\Support\Facades\Schema::getColumnListing($tabelle) as $spalte) {
                $this->spaltenJeSchema[$tabelle][$spalte] = true;
                $this->bekannteNamen[$spalte] = true;
            }
        }

        $this->assertNotEmpty($this->spaltenJeSchema, 'Ohne Spaltenliste prueft dieser Test gegen nichts.');
    }

    /**
     * Sammelt die Modelle und daraus das Namensuniversum fuer die globale
     * Ebene: jede oeffentliche Methode, jeder Akzessor, jedes angehaengte
     * Attribut und jedes begruendete "@property".
     */
    private function ladeModelle(): void
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

            /** @var class-string<Model> $klasse */
            $this->modelle[$spiegel->getShortName()] = $klasse;

            foreach ($spiegel->getMethods(ReflectionMethod::IS_PUBLIC) as $methode) {
                $name = $methode->getName();

                if (preg_match('/^get(.+)Attribute$/', $name, $teile) === 1) {
                    $name = $teile[1];
                }

                $this->bekannteNamen[Str::snake($name)] = true;
            }

            foreach ($this->instanz($klasse)->getAppends() as $anhang) {
                $this->bekannteNamen[$anhang] = true;
            }

            foreach ($this->begruendeteAttribute($spiegel) as $attribut) {
                $this->erklaerteAttribute[$klasse][$attribut] = true;
                $this->bekannteNamen[$attribut] = true;
            }
        }
    }

    /**
     * Die "@property"-Zeilen des Modells, die eine Begruendung aus
     * MARKIERUNGEN tragen — und nur die. Ein blankes "@property int|null $foo"
     * ist keine Erklaerung, sondern meist nur der IDE-Helfer.
     *
     * Gelesen wird der Docblock der Klasse und der ihrer Elternklassen.
     *
     * @param ReflectionClass<Model> $spiegel
     * @return list<string>
     */
    private function begruendeteAttribute(ReflectionClass $spiegel): array
    {
        $attribute = [];

        for ($k = $spiegel; $k !== false; $k = $k->getParentClass()) {
            $doc = $k->getDocComment();

            if ($doc === false) {
                continue;
            }

            preg_match_all(
                '/@property(?:-read|-write)?\s+\S+\s+\$([A-Za-z_][A-Za-z0-9_]*)([^\n]*)/',
                $doc,
                $treffer,
                PREG_SET_ORDER
            );

            foreach ($treffer as $t) {
                foreach (self::MARKIERUNGEN as $markierung) {
                    if (str_contains($t[2], $markierung)) {
                        $attribute[] = $t[1];
                        break;
                    }
                }
            }
        }

        return $attribute;
    }

    /**
     * @return iterable<string>
     */
    private function bladeDateien(): iterable
    {
        $lauf = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        /** @var SplFileInfo $datei */
        foreach ($lauf as $datei) {
            if ($datei->isFile() && str_ends_with($datei->getFilename(), '.blade.php')) {
                yield $datei->getPathname();
            }
        }
    }
}
