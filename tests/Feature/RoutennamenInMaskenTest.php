<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Kein route('...') in einer Maske darf ins Leere zeigen.
 *
 * Ein unbekannter Routenname ist kein leeres Feld und keine tote Schaltflaeche
 * — er wirft beim Rendern eine RouteNotFoundException und zerlegt die ganze
 * Seite. Am 30.08.2026 traf das zwei Super-Admin-Seiten: Sowohl
 * admin/exam-periods/create als auch admin/exam-periods/{id} verlinkten auf
 * exam-periods.index, einen Namen, den es nie gab. Beide antworteten mit 500,
 * und nichts im Code deutete darauf hin.
 *
 * Der Test liest nur die Masken und die Routenliste; er braucht keine
 * Datenbank. Dynamische Namen (route($variable)) bleiben aussen vor — die kann
 * er nicht pruefen.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter Routennamen
 */
class RoutennamenInMaskenTest extends TestCase
{
    public function test_jeder_routenname_in_den_masken_ist_registriert(): void
    {
        $unbekannt = [];

        foreach ($this->bladeDateien() as $datei) {
            $inhalt = (string) file_get_contents($datei);

            // Nur Aufrufe der Helferfunktion mit festem Namen. Die Rueckschau
            // schliesst Route::has(), $x->route() und aehnliches aus.
            $anzahl = preg_match_all(
                "/(?<![\\w:>\\-])route\\(\\s*'([A-Za-z0-9._\\-]+)'/",
                $inhalt,
                $treffer,
                PREG_OFFSET_CAPTURE
            );

            if ($anzahl === 0) {
                continue;
            }

            foreach ($treffer[1] as $index => $fund) {
                [$name] = $fund;

                if (Route::has($name)) {
                    continue;
                }

                [, $position] = $treffer[0][$index];
                $zeile = substr_count(substr($inhalt, 0, $position), "\n") + 1;

                $unbekannt[] = str_replace(resource_path('views').'/', '', $datei)
                    .':'.$zeile.' → '.$name;
            }
        }

        sort($unbekannt);

        $this->assertSame([], $unbekannt, sprintf(
            "Diese Masken verweisen auf Routennamen, die es nicht gibt. Jede".
            " davon antwortet beim Aufruf mit 500:\n  %s",
            implode("\n  ", $unbekannt)
        ));
    }

    /**
     * Gegenprobe: Der Test wuerde einen erfundenen Namen auch wirklich
     * bemerken. Ohne sie waere er auch dann gruen, wenn der regulaere Ausdruck
     * nie greift.
     */
    public function test_ein_erfundener_name_gilt_als_unbekannt(): void
    {
        $this->assertFalse(Route::has('exam-periods.index'));
        $this->assertTrue(Route::has('admin.exam-periods.index'));

        $anzahl = preg_match(
            "/(?<![\\w:>\\-])route\\(\\s*'([A-Za-z0-9._\\-]+)'/",
            "<a href=\"{{ route('admin.exam-periods.index') }}\">",
            $treffer
        );

        $this->assertSame(1, $anzahl);
        $this->assertSame('admin.exam-periods.index', $treffer[1]);
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
