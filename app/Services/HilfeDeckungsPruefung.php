<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

/**
 * Deckung zwischen Anwendung und Hilfesystem (Roadmap 6.3).
 *
 * Zwei Fragen, beide still, wenn sie niemand stellt:
 *
 * 1. **Gibt es eine Maske ohne Kapitel?** Dann zeigt das „?" dort einen leeren
 *    Kasten. Das faellt nur dem Anwender auf — und der haelt es fuer kaputt.
 * 2. **Zeigt ein Kapitel auf eine Route, die es nicht mehr gibt?** Dann ist es
 *    unerreichbar, ohne dass irgendetwas kracht.
 *
 * Solange die Kapitel Dateien im Repo waren, hat der Test das beantwortet.
 * Seit sie in der Datenbank stehen, beantwortet der Test es nur noch fuer die
 * **Grundfassung** — der Bestand einer laufenden Installation kann davon
 * abweichen. Deshalb steht die Pruefung hier und nicht im Test: Test und
 * `hilfe:pruefen` rufen dieselbe Stelle, und die Filterlisten gibt es einmal.
 */
class HilfeDeckungsPruefung
{
    /**
     * Routen, die kein Kapitel brauchen: Ausgaben (PDF, Excel), reine
     * Datenendpunkte und alles, was zum Vorlauf der Anmeldung gehoert.
     */
    private const OHNE_KAPITEL = '/(export|pdf|excel|search|preview|json|token|download|template|feed|data)/i';

    // ersteinrichtung: Assistent der lokalen Edition vor der ersten Anmeldung
    // (wie login ohne Hilfe-Knopf; erklärt sich auf der Seite selbst).
    private const OHNE_KAPITEL_PRAEFIX = '/^(login|logout|password|verification|two-factor|home|register|storage|hilfe|ersteinrichtung|desktop)/';

    /**
     * Routen ohne eigenen Namen.
     *
     * `route:cache` vergibt jeder unbenannten Route einen Namen der Form
     * `generated::<zufall>`. Das Hilfesystem ordnet ueber Routennamen zu — eine
     * Route, deren Name bei jedem Cache-Lauf ein anderer ist, kann kein Kapitel
     * treffen, und ein Kapitel dafuer zu verlangen ist sinnlos.
     *
     * **Gefunden auf Produktion, nicht lokal:** Der Deploy baut den Cache
     * (`php artisan optimize`), die Entwicklungsumgebung nicht. Die Pruefung
     * meldete dort `/up` (Laravels Health-Check) und ein unbenanntes
     * `GET import/preview` als Luecke. Gruen hing an der Umgebung.
     */
    private const OHNE_NAMEN = '/^generated::/';

    public function __construct(private readonly HilfeService $hilfe)
    {
    }

    /**
     * Aufrufbare Seiten ohne Kapitel.
     *
     * @return list<string>
     */
    public function luecken(): array
    {
        $ohne = [];

        foreach ($this->seitenrouten() as $name) {
            if ($this->hilfe->fuerRoute($name) === null) {
                $ohne[] = $name;
            }
        }

        sort($ohne);

        return $ohne;
    }

    /**
     * Routenpraefixe von Kapiteln, die keine einzige Route treffen.
     *
     * @return list<string> je Eintrag „slug: praefix"
     */
    public function toteVerweise(): array
    {
        $namen = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name !== null) {
                $namen[] = $name;
            }
        }

        $tote = [];

        foreach ($this->hilfe->alle() as $slug => $kapitel) {
            foreach ($kapitel->routenListe() as $praefix) {
                $trifft = false;

                foreach ($namen as $name) {
                    if ($name === $praefix || str_starts_with($name, $praefix . '.')) {
                        $trifft = true;
                        break;
                    }
                }

                if (! $trifft) {
                    $tote[] = $slug . ': ' . $praefix;
                }
            }
        }

        sort($tote);

        return $tote;
    }

    /**
     * Die benannten GET-Routen, die eine Maske sind — Ausgaben und
     * Datenendpunkte herausgefiltert.
     *
     * @return list<string>
     */
    public function seitenrouten(): array
    {
        $seiten = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (preg_match(self::OHNE_KAPITEL, $name) === 1
                || preg_match(self::OHNE_KAPITEL_PRAEFIX, $name) === 1
                || preg_match(self::OHNE_NAMEN, $name) === 1) {
                continue;
            }

            $seiten[] = $name;
        }

        sort($seiten);

        return $seiten;
    }
}
