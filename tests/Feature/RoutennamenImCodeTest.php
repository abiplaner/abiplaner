<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Auch im PHP-Code darf kein Routenname ins Leere zeigen.
 *
 * Das Gegenstueck zu RoutennamenInMaskenTest, und der vierte Test der Familie:
 *
 *   - SchemaAbgleichTest         $fillable und $casts gegen die Spalten
 *   - AttributeInMaskenTest      Attribute in den Blades gegen die Spalten
 *   - RoutennamenInMaskenTest    route('...') in den Masken
 *   - dieser hier                route('...') im Code
 *
 * Der Masken-Test liest nur resources/views. Eine Weiterleitung im Controller
 * prueft niemand — und genau dort sass der naechste Fall: `store()`,
 * `update()` und `destroy()` des ExamMaterialController zeigten alle drei auf
 * `exam-materials.index`. Registriert ist der Name aber als
 * `written-exams.materials.index`. Jede erfolgreiche Eingabe endete deshalb in
 * einer RouteNotFoundException — der Datensatz war gespeichert, die Antwort
 * ein 500er. Aufgefallen ist es nie, weil das Modul in keinem Menue stand.
 *
 * Kommentare zaehlen nicht: Nach einer Reparatur steht der falsche Name
 * erfahrungsgemaess als Erklaerung daneben. Entfernt wird er ueber
 * token_get_all(), nicht ueber einen regulaeren Ausdruck — der Lexer weiss
 * genau, was Kommentar ist und was in einer Zeichenkette steht.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter RoutennamenImCode
 */
class RoutennamenImCodeTest extends TestCase
{
    /**
     * Verzeichnisse, in denen Routennamen vorkommen koennen.
     *
     * @var list<string>
     */
    private const VERZEICHNISSE = ['app', 'routes'];

    public function test_jeder_routenname_im_code_ist_registriert(): void
    {
        $unbekannt = [];
        $dateien = 0;

        foreach ($this->phpDateien() as $datei) {
            $dateien++;
            $kurz = str_replace(base_path().'/', '', $datei);

            foreach ($this->routennamen((string) file_get_contents($datei)) as [$name, $zeile]) {
                if (Route::has($name)) {
                    continue;
                }

                $unbekannt[] = $kurz.':'.$zeile.' → '.$name;
            }
        }

        $this->assertGreaterThan(100, $dateien, 'Es muss der ganze Code geprueft werden.');

        sort($unbekannt);

        $this->assertSame([], $unbekannt, sprintf(
            "Diese Stellen verweisen auf Routennamen, die es nicht gibt. Jede".
            " davon wirft beim Aufruf eine RouteNotFoundException — bei einer".
            " Weiterleitung nach dem Speichern heisst das: gespeichert, und".
            " trotzdem 500:\n  %s",
            implode("\n  ", $unbekannt)
        ));
    }

    /**
     * Kein Routenname wiederholt seinen eigenen Abschnitt.
     *
     * Die andere Richtung: Hier zeigt kein Verweis ins Leere, sondern der
     * NAME selbst ist falsch — und faellt genau deshalb nicht auf. Eine
     * Routengruppe mit `->name('written-exams.')` und darin ein
     * `->name('written-exams.supervisions.grids.index')` ergibt
     * `written-exams.written-exams.supervisions.grids.index`. Sieben Routen
     * der Aufsichtsraster hiessen so (31.08.2026); niemand hat es gemerkt,
     * weil die Masken sie ueber die Adresse ansprechen und nicht ueber den
     * Namen. Der erste route()-Aufruf darauf waere in eine
     * RouteNotFoundException gelaufen.
     */
    public function test_kein_routenname_wiederholt_seinen_praefix(): void
    {
        $doppelt = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            $teile = explode('.', $name);

            for ($i = 1; $i < count($teile); $i++) {
                if ($teile[$i] === $teile[$i - 1]) {
                    $doppelt[] = $name.'  ('.$route->uri().')';
                    break;
                }
            }
        }

        sort($doppelt);

        $this->assertSame([], $doppelt, sprintf(
            "Diese Routennamen tragen denselben Abschnitt zweimal — fast immer".
            " ein Praefix, das die Gruppe schon setzt:\n  %s",
            implode("\n  ", $doppelt)
        ));
    }

    /**
     * Gegenprobe: Die Auswertung findet einen erfundenen Namen auch wirklich,
     * uebersieht die Ausnahmen nicht — und meldet keine Kommentare.
     */
    public function test_die_auswertung_findet_einen_erfundenen_namen(): void
    {
        $this->assertTrue(Route::has('written-exams.materials.index'));
        $this->assertFalse(Route::has('exam-materials.index'));

        // 1. Der Helfer und die Weiterleitung — beide Schreibweisen.
        $this->assertSame(
            [['a.b', 2], ['c.d', 3]],
            $this->routennamen("<?php\n\$x = route('a.b');\nreturn redirect()->route('c.d');")
        );

        // 2. to_route() zaehlt auch.
        $this->assertSame(
            [['e.f', 1]],
            $this->routennamen("<?php return to_route('e.f');")
        );

        // 3. Kommentare nicht. Genau so steht die Erklaerung nach einer
        //    Reparatur im Code.
        $this->assertSame([], $this->routennamen(
            "<?php\n// zeigte frueher auf route('exam-materials.index')\n"
            ."/** Siehe route('exam-materials.index'). */\n"
        ));

        // 4. $request->route('foo') liest einen Routenparameter, keinen Namen.
        $this->assertSame([], $this->routennamen("<?php \$request->route('kurs');"));

        // 5. Die Zeilennummer stimmt auch nach einem mehrzeiligen Kommentar.
        $this->assertSame(
            [['g.h', 5]],
            $this->routennamen("<?php\n/* eine\n   Erklaerung */\n\nreturn route('g.h');")
        );
    }

    /**
     * Sammelt die Routennamen aus PHP-Quelltext, mit Zeilennummer.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function routennamen(string $quelltext): array
    {
        $ohneKommentare = '';

        foreach (token_get_all($quelltext) as $token) {
            if (is_array($token)) {
                $ohneKommentare .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
                    // Zeilenumbrueche behalten, damit die Zeilennummern stimmen.
                    ? str_repeat("\n", substr_count($token[1], "\n"))
                    : $token[1];
                continue;
            }

            $ohneKommentare .= $token;
        }

        // route('...'), ->route('...') und to_route('...'), aber nicht
        // $request->route('parameter') — das liest einen Routenparameter.
        //
        // Die Rueckschau steht bewusst direkt vor `route` und schliesst nicht
        // ein optionales `->` mit ein: Waere `->` Teil des Treffers, koennte
        // der Ausdruck ihn einfach eine Stelle spaeter beginnen lassen und
        // die Ausnahme damit umgehen.
        $anzahl = preg_match_all(
            "/(?<!\\\$request->)\\b(?:to_)?route\\(\\s*'([A-Za-z0-9._\\-]+)'/",
            $ohneKommentare,
            $treffer,
            PREG_OFFSET_CAPTURE
        );

        if ($anzahl === 0) {
            return [];
        }

        $namen = [];

        foreach ($treffer[1] as $index => $fund) {
            [, $position] = $treffer[0][$index];

            $namen[] = [$fund[0], substr_count(substr($ohneKommentare, 0, $position), "\n") + 1];
        }

        return $namen;
    }

    /**
     * @return iterable<string>
     */
    private function phpDateien(): iterable
    {
        foreach (self::VERZEICHNISSE as $verzeichnis) {
            $lauf = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path($verzeichnis))
            );

            /** @var SplFileInfo $datei */
            foreach ($lauf as $datei) {
                if ($datei->isFile() && $datei->getExtension() === 'php') {
                    yield $datei->getPathname();
                }
            }
        }
    }
}
