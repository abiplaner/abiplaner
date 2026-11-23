<?php

namespace App\Services;

use App\Models\HilfeKapitel;
use Illuminate\Support\Str;

/**
 * Hilfesystem und Handbuch (Roadmap 6.3).
 *
 * Die Roadmap verlangt zwei Zugaenge — Hilfe in der Anwendung und ein Handbuch
 * zum Durchblaettern — und **einen** Pflegeort. Genau das ist hier gebaut: Die
 * Kapitel stehen in der zentralen Tabelle `hilfe_kapitel` und versorgen von
 * dort das Panel am „?", die Seite `/hilfe`, die Suche, den Handbuch-Export
 * und das PDF.
 *
 * Seit dem 29.08.2026 liegen sie in der Datenbank statt als Dateien: Ein
 * Tippfehler kostete vorher einen Deploy. Die Markdown-Dateien unter
 * `database/seeders/data/hilfe/` sind seither nur noch die Grundfassung fuer
 * frische Installationen — **zur Laufzeit liest niemand mehr die Platte.**
 *
 * Die Zuordnung zur Maske laeuft ueber **Routenpraefixe**, nicht ueber eine
 * Liste einzelner Routen: Die Anwendung fuehrt ueber 500 benannte Routen,
 * davon die meisten schreibend. Gesucht wird das **laengste** passende
 * Praefix, damit ein Sonderkapitel ein allgemeines schlagen kann.
 */
class HilfeService
{
    /**
     * Geladene Kapitel, nach Slug. Wird je Anfrage einmal aufgebaut — Panel,
     * Suche und Handbuch fragen sonst dieselbe Tabelle mehrfach.
     *
     * @var array<string, HilfeKapitel>|null
     */
    private ?array $kapitel = null;

    /**
     * Alle Kapitel, sortiert nach `reihenfolge`, bei Gleichstand nach Titel.
     *
     * @return array<string, HilfeKapitel>
     */
    public function alle(): array
    {
        if ($this->kapitel !== null) {
            return $this->kapitel;
        }

        /** @var array<string, HilfeKapitel> $geladen */
        $geladen = HilfeKapitel::query()
            ->orderBy('reihenfolge')
            ->orderBy('titel')
            ->get()
            ->keyBy('slug')
            ->all();

        return $this->kapitel = $geladen;
    }

    /**
     * Verwirft den Zwischenspeicher — noetig, wenn im selben Ablauf erst
     * geschrieben und dann gelesen wird (Verwaltungsmaske, Tests).
     */
    public function neuLaden(): void
    {
        $this->kapitel = null;
    }

    public function kapitel(string $slug): ?HilfeKapitel
    {
        return $this->alle()[$slug] ?? null;
    }

    /**
     * Die Kapitel nach Gruppen, in der Reihenfolge, in der die Gruppen zum
     * ersten Mal vorkommen. Damit bestimmt `reihenfolge` beides — die Folge
     * der Gruppen und die der Kapitel darin.
     *
     * @return array<string, list<HilfeKapitel>>
     */
    public function gruppen(): array
    {
        $gruppen = [];

        foreach ($this->alle() as $kapitel) {
            $gruppen[$kapitel->gruppe][] = $kapitel;
        }

        return $gruppen;
    }

    /**
     * Das Kapitel zur aktuellen Maske — ueber das **laengste** passende
     * Routenpraefix.
     *
     * Ein Praefix passt, wenn der Routenname ihm gleicht oder mit ihm plus
     * Punkt beginnt. `written-exams.supervisions` schlaegt damit
     * `written-exams`, und `supervisions` faengt nicht versehentlich
     * `written-exams.supervisions.*` ab.
     */
    public function fuerRoute(?string $routenname): ?HilfeKapitel
    {
        if ($routenname === null || $routenname === '') {
            return null;
        }

        $treffer = null;
        $laenge = -1;

        foreach ($this->alle() as $kapitel) {
            foreach ($kapitel->routenListe() as $praefix) {
                if ($routenname !== $praefix && ! str_starts_with($routenname, $praefix . '.')) {
                    continue;
                }

                if (strlen($praefix) > $laenge) {
                    $laenge = strlen($praefix);
                    $treffer = $kapitel;
                }
            }
        }

        return $treffer;
    }

    /**
     * Volltextsuche ueber Titel, Stichworte und Fliesstext.
     *
     * Die Fundstelle wird mitgeliefert, sonst muesste der Nutzer jedes
     * Suchergebnis oeffnen, um zu sehen, warum es dabei ist. Gesucht wird ohne
     * Ruecksicht auf Gross- und Kleinschreibung; die Anwendung ist deutsch,
     * und niemand tippt „Kolloquium" versehentlich richtig.
     *
     * Bewusst in PHP und nicht als SQL-LIKE: Die Kapitelzahl ist zweistellig,
     * und die Fundstelle braucht ohnehin den ganzen Text.
     *
     * @return list<array{kapitel: HilfeKapitel, fundstelle: string}>
     */
    public function suche(string $begriff): array
    {
        $begriff = trim($begriff);

        if (mb_strlen($begriff) < 2) {
            return [];
        }

        $treffer = [];

        foreach ($this->alle() as $kapitel) {
            $imTitel = mb_stripos($kapitel->titel, $begriff) !== false;
            $imStichwort = false;

            foreach ($kapitel->stichwortListe() as $stichwort) {
                if (mb_stripos($stichwort, $begriff) !== false) {
                    $imStichwort = true;
                    break;
                }
            }

            $fundstelle = $this->fundstelle($kapitel->markdown, $begriff);

            if (! $imTitel && ! $imStichwort && $fundstelle === null) {
                continue;
            }

            $treffer[] = [
                'kapitel' => $kapitel,
                'fundstelle' => $fundstelle ?? Str::limit($this->klartext($kapitel->markdown), 160),
            ];
        }

        return $treffer;
    }

    /**
     * Ein Kapitel als HTML fuer die Anzeige in der Anwendung.
     */
    public function html(string $slug): ?string
    {
        $kapitel = $this->kapitel($slug);

        if (! $kapitel instanceof HilfeKapitel) {
            return null;
        }

        return $this->alsHtml($kapitel->markdown);
    }

    /**
     * Markdown zu HTML — **an einer Stelle**, damit Panel, Kapitelseite,
     * Vorschau und PDF dieselben Einstellungen benutzen.
     *
     * `html_input: strip` sperrt rohes HTML aus. Das war schon richtig, als
     * die Kapitel Dateien im Repo waren; seit sie ueber eine Maske
     * hereinkommen, ist es die Absicherung, die den Editor ungefaehrlich
     * macht.
     */
    public function alsHtml(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Das gesamte Handbuch als Markdown — Titel, Inhaltsverzeichnis und alle
     * Kapitel, nach Gruppen geordnet.
     *
     * Dieselbe Zeichenkette geht in den Datei-Export und, durch
     * `alsHtml()` geschickt, in das PDF. **Es gibt keine zweite Fassung des
     * Textes.**
     */
    public function handbuchMarkdown(?string $schulname = null): string
    {
        $zeilen = [];
        $zeilen[] = '# AbiPlaner — Benutzerhandbuch';
        $zeilen[] = '';

        if ($schulname !== null && $schulname !== '') {
            $zeilen[] = '**' . $schulname . '**';
            $zeilen[] = '';
        }

        $zeilen[] = 'Stand: ' . now()->format('d.m.Y');
        $zeilen[] = '';
        $zeilen[] = 'Dieses Handbuch ist **erzeugt, nicht geschrieben**: Es fügt die Kapitel des';
        $zeilen[] = 'Hilfesystems zusammen, die in der Anwendung unter „?" und auf `/hilfe` stehen.';
        $zeilen[] = 'Änderungen gehören deshalb in die Hilfe-Verwaltung, nicht in diese Datei.';
        $zeilen[] = '';
        $zeilen[] = '---';
        $zeilen[] = '';
        $zeilen[] = '## Inhalt';
        $zeilen[] = '';

        foreach ($this->gruppen() as $gruppe => $kapitelliste) {
            $zeilen[] = '**' . $gruppe . '**';
            $zeilen[] = '';

            foreach ($kapitelliste as $kapitel) {
                $zeilen[] = '- ' . $kapitel->titel;
            }

            $zeilen[] = '';
        }

        foreach ($this->gruppen() as $gruppe => $kapitelliste) {
            $zeilen[] = '---';
            $zeilen[] = '';
            $zeilen[] = '# ' . $gruppe;
            $zeilen[] = '';

            foreach ($kapitelliste as $kapitel) {
                $zeilen[] = '## ' . $kapitel->titel;
                $zeilen[] = '';
                $zeilen[] = $this->ueberschriftenTiefer($kapitel->markdown);
                $zeilen[] = '';
            }
        }

        return rtrim(implode("\n", $zeilen)) . "\n";
    }

    /**
     * Verschiebt jede Ueberschrift um eine Ebene nach unten, damit die Kapitel
     * unter der Gruppen- und Kapitelueberschrift des Handbuchs richtig
     * einsortiert sind.
     *
     * **Zeilen in Code-Bloecken bleiben unangetastet** — eine Raute am
     * Zeilenanfang ist dort ein Kommentar, keine Ueberschrift.
     */
    private function ueberschriftenTiefer(string $markdown): string
    {
        $imBlock = false;
        $ergebnis = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $zeile) {
            if (preg_match('/^\s*(```|~~~)/', $zeile) === 1) {
                $imBlock = ! $imBlock;
                $ergebnis[] = $zeile;

                continue;
            }

            if (! $imBlock && preg_match('/^(#{1,5})\s/', $zeile) === 1) {
                $zeile = '#' . $zeile;
            }

            $ergebnis[] = $zeile;
        }

        return trim(implode("\n", $ergebnis));
    }

    /**
     * Ein Satzstueck um den Suchbegriff herum, in Klartext.
     */
    private function fundstelle(string $markdown, string $begriff): ?string
    {
        $text = $this->klartext($markdown);
        $position = mb_stripos($text, $begriff);

        if ($position === false) {
            return null;
        }

        $start = max(0, $position - 60);
        $ausschnitt = mb_substr($text, $start, mb_strlen($begriff) + 140);

        return ($start > 0 ? '… ' : '') . trim($ausschnitt) . ' …';
    }

    /**
     * Markdown ohne Auszeichnung — fuer Fundstellen und Kurzfassungen.
     */
    private function klartext(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/^\s*[#>\-|*]+\s*/m', '', $text) ?? $text;
        $text = str_replace(['**', '`', '__'], '', $text);
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
