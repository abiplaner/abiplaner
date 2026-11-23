<?php

namespace App\Console\Commands;

use App\Models\SupportFall;
use App\Services\SupportAnhangService;
use Illuminate\Console\Command;

/**
 * Haelt die Aufbewahrungsfristen der Fallakten ein (Modul 6.4,
 * Entscheidung 53).
 *
 * Art. 5 Abs. 1 lit. e DSGVO verlangt eine Speicherbegrenzung. Sie gilt auch
 * fuer eine Speicherung, die das Haus nie verlaesst — die Streichung der
 * KI-Stufe am 31.08.2026 hat daran nichts geaendert, sie hat nur die
 * Uebermittlung an einen Dritten erledigt.
 *
 * Zwei Schritte, gestaffelt nach Risiko. Was am ehesten Schuelernamen
 * enthaelt, geht zuerst:
 *
 * 1. **Der Kontext wird gekuerzt** (`kontext_tage`, Vorgabe 90). Die Bloecke
 *    `aktivitaeten` und `fehler` fallen — sie koennen Namen enthalten und
 *    dienten allein der Diagnose des konkreten Falls. `technik` und
 *    `einrichtung` bleiben: kein Personenbezug, und sie beantworten weiter,
 *    welches Modul Support erzeugt.
 * 2. **Die Fallakte faellt** (`fallakte_monate`, Vorgabe 24). Mit ihr
 *    Nachrichten und Anhaenge — die Fremdschluessel kaskadieren.
 *
 * **Beide Fristen zaehlen ab `geschlossen_am`, und ein offener Fall wird nie
 * angefasst.** Damit „offen lassen" nicht zum Aufbewahrungstrick wird, mahnt
 * das Dashboard lange offene Faelle an (`offen_hinweis_monate`).
 *
 * Findet der Befehl nichts, sagt er das und tut nichts; er kann deshalb
 * gefahrlos taeglich laufen. `--probe` zeigt nur an.
 *
 * Bauform bewusst dieselbe wie `support:anhaenge-aufraeumen` — der Befehl hat
 * sich bewaehrt, und zwei Aufraeumbefehle sollen sich gleich bedienen lassen.
 */
class SupportFallaktenAufraeumen extends Command
{
    protected $signature = 'support:fallakten-aufraeumen {--probe : Nur anzeigen, nichts loeschen}';

    protected $description = 'Kuerzt den Kontext und loescht Fallakten nach Ablauf der Aufbewahrungsfristen';

    /**
     * Die Kontextbloecke, die mit der ersten Frist fallen.
     *
     * @var list<string>
     */
    private const PERSONENBEZOGENE_BLOECKE = ['aktivitaeten', 'fehler'];

    public function handle(SupportAnhangService $anhaenge): int
    {
        $kontextTage = (int) config('support.aufbewahrung.kontext_tage');
        $fallakteMonate = (int) config('support.aufbewahrung.fallakte_monate');

        // Eine Frist von null hiesse „sofort beim Abschluss loeschen". Das ist
        // fast sicher ein Konfigurationsfehler und waere nicht rueckgaengig zu
        // machen — deshalb nicht ausfuehren, sondern melden.
        if ($kontextTage < 1 || $fallakteMonate < 1) {
            $this->error(
                'Ungültige Aufbewahrungsfrist in config/support.php: '
                . "kontext_tage={$kontextTage}, fallakte_monate={$fallakteMonate}. "
                . 'Beide müssen mindestens 1 sein. Es wurde nichts gelöscht.'
            );

            return self::FAILURE;
        }

        $probe = (bool) $this->option('probe');

        $loeschGrenze = now()->subMonths($fallakteMonate);

        // Erst loeschen, dann kuerzen — und die schon faelligen Akten aus dem
        // Kuerzen heraushalten. Sonst stuende derselbe Fall in beiden Listen,
        // und in der Probe waere nicht zu sehen, dass er ohnehin ganz faellt.
        $geloescht = $this->fallaktenLoeschen($loeschGrenze, $probe, $anhaenge);
        $gekuerzt = $this->kontextKuerzen($kontextTage, $loeschGrenze, $probe);

        if ($gekuerzt === 0 && $geloescht === 0) {
            $this->info('Nichts aufzuräumen.');

            return self::SUCCESS;
        }

        $this->info($probe
            ? "Probe — nichts geändert ({$gekuerzt} zu kürzen, {$geloescht} zu löschen)."
            : "{$gekuerzt} Kontext(e) gekürzt, {$geloescht} Fallakte(n) gelöscht.");

        return self::SUCCESS;
    }

    /**
     * Schritt 1: die personenbezogenen Kontextbloecke leeren.
     *
     * Ein Fall wird genau einmal gekuerzt: Danach sind die Schluessel weg, und
     * die Bedingung trifft nicht mehr zu. `gekuerzt_am` bleibt als Vermerk
     * stehen — **ein leeres Feld muss sagen koennen, warum es leer ist**,
     * sonst steht in der Akte „kein Kontext erfasst" neben „nach Frist
     * gekuerzt", ohne dass man beides unterscheiden kann.
     */
    private function kontextKuerzen(int $tage, \Illuminate\Support\Carbon $loeschGrenze, bool $probe): int
    {
        $grenze = now()->subDays($tage);
        $anzahl = 0;

        $faelle = SupportFall::query()
            ->whereNotNull('geschlossen_am')
            ->where('geschlossen_am', '<=', $grenze)
            ->where('geschlossen_am', '>', $loeschGrenze)
            ->whereNotNull('kontext')
            ->orderBy('id')
            ->get();

        foreach ($faelle as $fall) {
            $kontext = $fall->kontext;

            if (! is_array($kontext)) {
                continue;
            }

            $betroffen = array_filter(
                self::PERSONENBEZOGENE_BLOECKE,
                static fn (string $block): bool => array_key_exists($block, $kontext)
            );

            if ($betroffen === []) {
                continue;
            }

            $anzahl++;
            $this->line('Fall ' . $fall->id . ': Kontext kürzen ('
                . implode(', ', $betroffen) . '), geschlossen am '
                . $fall->geschlossen_am?->format('d.m.Y'));

            if ($probe) {
                continue;
            }

            foreach (self::PERSONENBEZOGENE_BLOECKE as $block) {
                unset($kontext[$block]);
            }

            $kontext['gekuerzt_am'] = now()->toDateString();

            $fall->forceFill(['kontext' => $kontext])->save();
        }

        return $anzahl;
    }

    /**
     * Schritt 2: die Fallakte selbst.
     *
     * Die Anhaenge sind beim Abschluss gefallen (Entscheidung 48); der Aufruf
     * hier ist die Gegenprobe fuer alles, was daran vorbeikam — die Zeilen
     * kaskadieren mit, die **Dateien** taeten es nicht.
     */
    private function fallaktenLoeschen(\Illuminate\Support\Carbon $grenze, bool $probe, SupportAnhangService $anhaenge): int
    {
        $anzahl = 0;

        $faelle = SupportFall::query()
            ->whereNotNull('geschlossen_am')
            ->where('geschlossen_am', '<=', $grenze)
            ->orderBy('id')
            ->get();

        foreach ($faelle as $fall) {
            $anzahl++;
            $this->line('Fall ' . $fall->id . ': Akte löschen, geschlossen am '
                . $fall->geschlossen_am?->format('d.m.Y'));

            if ($probe) {
                continue;
            }

            $anhaenge->loesche($fall);
            $fall->delete();
        }

        return $anzahl;
    }
}
