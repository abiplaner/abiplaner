<?php

namespace Tests\Feature;

use App\Http\Middleware\ContentSecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Deckungstests zur CSP-Nonce (Modul 7.2).
 *
 * Der Kern ist nicht die Nonce selbst, sondern dass beim Scharfschalten kein
 * Inline-Block ohne sie zurueckbleibt: Ein vergessener Block faellt dann nicht
 * als Fehler auf, sondern als tote Interaktion — die Maske sieht heil aus und
 * tut nichts.
 */
class ContentSecurityPolicyTest extends TestCase
{
    // Jede Seite fragt (ErsteinrichtungErzwingen), ob die Schule eingerichtet ist.
    use RefreshDatabase;

    public function test_jedes_inline_script_traegt_die_nonce(): void
    {
        $ohneNonce = [];

        foreach ($this->bladeDateien() as $datei) {
            $inhalt = (string) file_get_contents($datei);

            if (preg_match_all('/<script\b[^>]*>/i', $inhalt, $treffer, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            /** @var array{0: string, 1: int} $fund */
            foreach ($treffer[0] as $fund) {
                [$tag, $position] = $fund;

                // Externe Skripte laufen ueber die Quellenliste ('self'),
                // nicht ueber die Nonce.
                if (str_contains($tag, 'src=')) {
                    continue;
                }

                if (str_contains($tag, '@cspNonce') || str_contains($tag, 'nonce=')) {
                    continue;
                }

                $zeile = substr_count(substr($inhalt, 0, $position), "\n") + 1;
                $ohneNonce[] = str_replace(resource_path('views').'/', '', $datei).':'.$zeile;
            }
        }

        $this->assertSame([], $ohneNonce, sprintf(
            "Diese Inline-Bloecke tragen keine Nonce und wuerden unter scharfer CSP".
            " stumm blockiert:\n  %s\nAbhilfe: <script @cspNonce>",
            implode("\n  ", $ohneNonce)
        ));
    }

    public function test_keine_views_laden_von_fremden_servern(): void
    {
        // Die CSP erlaubt nur 'self'. Zwei CDN-Einbindungen (Quill, Chart.js)
        // haben das seit Juli 2026 verletzt und sind still blockiert worden:
        // der Editor der Schuleinstellungen erschien nicht, das Diagramm der
        // LK-Wahlen blieb leer. Aufgefallen ist es niemandem, weil eine
        // blockierte Ressource keine Fehlermeldung erzeugt, sondern eine
        // Maske, die harmlos aussieht.
        //
        // Geprueft werden nur eingebundene Ressourcen (src, <link href>),
        // nicht <a href> — Verweise auf fremde Seiten sind erlaubt und
        // erwuenscht, etwa auf gesetze-bayern.de.
        $fremd = [];

        foreach ($this->bladeDateien() as $datei) {
            foreach (file($datei) ?: [] as $nummer => $zeile) {
                $trifft = preg_match('/\bsrc=["\']https?:\/\//i', $zeile)
                    || preg_match('/<link\b[^>]*href=["\']https?:\/\//i', $zeile)
                    || preg_match('/@import\s+(url\()?["\']?https?:\/\//i', $zeile);

                if ($trifft) {
                    $fremd[] = str_replace(resource_path('views').'/', '', $datei).':'.($nummer + 1);
                }
            }
        }

        $this->assertSame([], $fremd, sprintf(
            "Diese Views laden von fremden Servern und werden von der CSP".
            " blockiert:\n  %s\nAbhilfe: Bibliothek per npm ins Projekt holen".
            " und ueber Vite ausliefern.",
            implode("\n  ", $fremd)
        ));
    }

    /**
     * Was in den Views noch an Inline-Handlern steht (Modul 7.2).
     *
     * Die Liste geht nur nach unten: Wer einen Handler entfernt, traegt die
     * neue Zahl hier ein. Wer eine Datei nicht kennt, hat einen neuen
     * hinzugefuegt — und den soll der Test finden.
     *
     * @var array<string, int>
     */
    private const HANDLER_ALTBESTAND = [
        // Leer: seit dem 29.08.2026 traegt keine View mehr einen Inline-Handler.
    ];

    public function test_keine_neuen_inline_handler(): void
    {
        // Ein onclick="…" ist fuer den Browser ein Inline-Skript und faellt,
        // sobald die Nonce im script-src steht — eine Nonce laesst sich an ein
        // Attribut nicht haengen. Das gilt auch fuer Handler, die JavaScript
        // per innerHTML in die Seite schreibt; deshalb wird der ganze
        // Dateitext durchsucht und nicht nur das Blade-Markup.
        $gefunden = [];

        foreach ($this->bladeDateien() as $datei) {
            $anzahl = preg_match_all(
                '/\son[a-z]+\s*=\s*["\']/i',
                (string) file_get_contents($datei)
            );

            if ($anzahl > 0) {
                $gefunden[str_replace(resource_path('views').'/', '', $datei)] = $anzahl;
            }
        }

        ksort($gefunden);
        $erwartet = self::HANDLER_ALTBESTAND;
        ksort($erwartet);

        $this->assertSame($erwartet, $gefunden, sprintf(
            "Die Inline-Handler weichen vom vermerkten Altbestand ab.\n".
            "Steht hier eine unbekannte Datei oder eine hoehere Zahl, ist ein".
            " neuer Handler dazugekommen — bitte auf ein data-Attribut".
            " umstellen (siehe resources/js/actions.js).\n".
            "Ist die Zahl gesunken, bitte HANDLER_ALTBESTAND nachziehen.\n".
            "Offen sind derzeit %d Handler in %d Dateien.",
            array_sum($erwartet),
            count($erwartet)
        ));
    }

    public function test_die_middleware_haengt_in_der_web_gruppe(): void
    {
        // Ohne diese Registrierung erzeugt niemand eine Nonce — die Direktive
        // gaebe dann ueberall stillschweigend nichts aus.
        // Über den HTTP-Kernel: Der Router kennt die Gruppen erst, wenn der
        // Kernel gebaut ist (bis 22.11. tat das nebenbei der
        // TenancyServiceProvider).
        $gruppen = $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups();

        $this->assertContains(ContentSecurityPolicy::class, $gruppen['web']);
    }

    public function test_die_middleware_erzeugt_pro_request_eine_eigene_nonce(): void
    {
        $mittelstueck = new ContentSecurityPolicy();
        $gesehen = [];

        foreach ([1, 2] as $unused) {
            $mittelstueck->handle(Request::create('/'), function () use (&$gesehen) {
                $gesehen[] = Vite::cspNonce();

                return response('');
            });
        }

        $this->assertNotEmpty($gesehen[0]);
        $this->assertNotSame($gesehen[0], $gesehen[1], 'Eine Nonce, die sich wiederholt, ist keine.');
    }

    public function test_die_direktive_gibt_die_nonce_aus(): void
    {
        Vite::useCspNonce('PROBE-NONCE');

        $this->assertStringContainsString(
            'nonce="PROBE-NONCE"',
            Blade::render('<script @cspNonce>void 0;</script>')
        );
    }

    public function test_die_direktive_schreibt_kein_leeres_attribut(): void
    {
        // Ausserhalb eines Requests (Konsole, PDF-Rendering) gibt es keine
        // Nonce. Ein nonce="" waere unter scharfer CSP ungueltig und wuerde den
        // Block blockieren — also gehoert dort gar kein Attribut hin.
        Vite::useCspNonce('');

        $this->assertStringNotContainsString(
            'nonce',
            Blade::render('<script @cspNonce>void 0;</script>')
        );
    }

    // ── Stufe 2: der Header kommt aus PHP ─────────────────────────────────

    /**
     * Der Kern der Umstellung: 'unsafe-inline' ist bei script-src weg und
     * durch die Nonce dieses Requests ersetzt.
     *
     * Solange 'unsafe-inline' dort stand, war ein eingeschleustes <script>
     * erlaubt und die Direktive praktisch wirkungslos. Steht eine Nonce in
     * script-src, ignorieren Browser 'unsafe-inline' ohnehin — es stehenzulassen
     * waere also nicht falsch, aber irrefuehrend.
     */
    public function test_der_header_traegt_die_nonce_und_kein_unsafe_inline_bei_script_src(): void
    {
        $antwort = $this->get('/');

        $politik = $antwort->headers->get('Content-Security-Policy');
        $this->assertNotNull($politik, 'Die dynamische Antwort muss einen CSP-Header tragen.');

        preg_match("/script-src ([^;]+)/", $politik, $treffer);
        $this->assertNotEmpty($treffer, "script-src fehlt in: {$politik}");

        $scriptSrc = $treffer[1];
        $this->assertStringContainsString("'nonce-", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);

        // 'unsafe-eval' bleibt bewusst: Alpine wertet seine x-Ausdruecke ueber
        // Function() aus. Faellt es weg, fallen alle x-Ausdruecke aus — das
        // waere ein Ausfall, keine Verbesserung.
        $this->assertStringContainsString("'unsafe-eval'", $scriptSrc);
    }

    /**
     * Die Nonce im Header muss dieselbe sein wie die an den Skript-Tags.
     * Waeren es zwei verschiedene, blockierte der Browser jeden Inline-Block —
     * und der Test darueber waere trotzdem gruen.
     */
    public function test_die_nonce_im_header_passt_zu_der_im_dokument(): void
    {
        // '/' leitet nur noch weiter; die Anmeldeseite trägt Skript-Tags.
        $antwort = $this->get('/login');

        preg_match("/'nonce-([^']+)'/", (string) $antwort->headers->get('Content-Security-Policy'), $ausHeader);
        $this->assertNotEmpty($ausHeader, 'Im Header steht keine Nonce.');

        preg_match('/nonce="([^"]+)"/', $antwort->getContent(), $ausDokument);
        $this->assertNotEmpty($ausDokument, 'Im Dokument steht kein nonce-Attribut.');

        $this->assertSame($ausHeader[1], $ausDokument[1]);
    }

    /**
     * Zwei Aufrufe duerfen nicht dieselbe Nonce tragen — sonst waere sie
     * ratbar und der ganze Schutz Fassade.
     */
    public function test_die_nonce_im_header_wechselt_pro_request(): void
    {
        $erste = $this->get('/')->headers->get('Content-Security-Policy');
        $zweite = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($erste, $zweite);
    }

    /**
     * Die Meldestelle nimmt den Bericht des Browsers entgegen — ohne
     * CSRF-Token und ohne Anmeldung, denn beides hat der Browser an dieser
     * Stelle nicht. Faellt die Ausnahme weg, kaeme 419 statt 204 und wir
     * saehen von den Verstoessen nichts.
     */
    public function test_die_meldestelle_nimmt_einen_bericht_ohne_anmeldung_an(): void
    {
        $antwort = $this->postJson('/csp-bericht', [
            'csp-report' => [
                'document-uri' => 'https://example.test/planning',
                'violated-directive' => 'script-src',
                'blocked-uri' => 'inline',
            ],
        ]);

        $antwort->assertNoContent();
    }

    /**
     * Und sie faellt nicht ueber einen Rumpf, der nicht wie ein Bericht
     * aussieht — der Endpunkt steht offen im Netz.
     */
    public function test_die_meldestelle_haelt_unsinn_aus(): void
    {
        $this->postJson('/csp-bericht', ['irgendwas' => 'anderes'])->assertNoContent();
        $this->postJson('/csp-bericht', [])->assertNoContent();
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
