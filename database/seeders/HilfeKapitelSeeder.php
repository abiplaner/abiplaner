<?php

namespace Database\Seeders;

use App\Models\HilfeKapitel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Spielt die Hilfekapitel ein (Modul 6.3).
 *
 * **Die Markdown-Dateien unter `database/seeders/data/hilfe/` sind die
 * Hilfe** (seit 27.11.2026). Die Kapitel werden nicht mehr in der Oberfläche
 * bearbeitet, sondern zentral im Repository gepflegt und kommen mit jedem
 * Programm-Update; `abiplaner:vorbereiten` ruft den Seeder bei jedem Start.
 * Die Datenbank ist nur noch der Suchbestand: Abweichungen werden auf den
 * Stand der Dateien gebracht, Kapitel ohne Datei entfernt.
 *
 * Dasselbe Muster wie beim Faecherkatalog — eine Migration mit eigener Kopie
 * der Daten gäbe jeder frischen Installation den falschen Stand zurück
 * (siehe `2026_08_25_230500_sync_subjects_catalog_with_seeder`).
 */
class HilfeKapitelSeeder extends Seeder
{
    /**
     * Zaehler des letzten Laufs — fuer den Befehl und die Tests.
     *
     * @var array{neu: int, aktualisiert: int, unveraendert: int, entfernt: int}
     */
    public array $bilanz = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'entfernt' => 0];

    public function run(): void
    {
        $this->bilanz = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'entfernt' => 0];

        $grundfassung = $this->grundfassung();

        // Ohne lesbare Dateien nichts löschen: Ein kaputtes Paket darf die
        // Hilfe nicht leeren.
        if ($grundfassung !== []) {
            $this->bilanz['entfernt'] = HilfeKapitel::query()
                ->whereNotIn('slug', array_keys($grundfassung))
                ->delete();
        }

        foreach ($grundfassung as $slug => $werte) {
            $kapitel = HilfeKapitel::query()->where('slug', $slug)->first();
            $hash = HilfeKapitel::fingerabdruckAus($werte);

            if (! $kapitel instanceof HilfeKapitel) {
                HilfeKapitel::query()->create($werte + [
                    'slug' => $slug,
                    'grundfassung_hash' => $hash,
                ]);
                $this->bilanz['neu']++;

                continue;
            }

            if ($kapitel->grundfassung_hash === $hash && ! $kapitel->istRedigiert()) {
                $this->bilanz['unveraendert']++;

                continue;
            }

            $kapitel->fill($werte);
            $kapitel->grundfassung_hash = $hash;
            $kapitel->save();
            $this->bilanz['aktualisiert']++;
        }
    }

    /**
     * Die Grundfassung aus den Markdown-Dateien, nach Slug.
     *
     * @return array<string, array{titel: string, gruppe: string, reihenfolge: int, routen: string|null, stichworte: string|null, markdown: string}>
     */
    public function grundfassung(): array
    {
        $verzeichnis = database_path('seeders/data/hilfe');

        if (! File::isDirectory($verzeichnis)) {
            return [];
        }

        $kapitel = [];

        foreach (File::files($verzeichnis) as $datei) {
            if ($datei->getExtension() !== 'md') {
                continue;
            }

            $werte = $this->parse((string) File::get($datei->getPathname()));

            if ($werte !== null) {
                $kapitel[$datei->getFilenameWithoutExtension()] = $werte;
            }
        }

        ksort($kapitel);

        return $kapitel;
    }

    /**
     * Liest den Kopf einer Kapiteldatei: `schluessel: wert`, Listen mit Komma
     * getrennt, keine Verschachtelung. Eine Datei ohne Kopf oder ohne Titel
     * ist kein Kapitel und wird uebergangen, statt mit halben Angaben in der
     * Uebersicht zu stehen.
     *
     * @return array{titel: string, gruppe: string, reihenfolge: int, routen: string|null, stichworte: string|null, markdown: string}|null
     */
    private function parse(string $inhalt): ?array
    {
        $inhalt = str_replace("\r\n", "\n", $inhalt);

        if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $inhalt, $treffer) !== 1) {
            return null;
        }

        $kopf = [];

        foreach (explode("\n", $treffer[1]) as $zeile) {
            if (! str_contains($zeile, ':')) {
                continue;
            }

            [$schluessel, $wert] = explode(':', $zeile, 2);
            $kopf[trim($schluessel)] = trim($wert);
        }

        $titel = $kopf['titel'] ?? '';

        if ($titel === '') {
            return null;
        }

        return [
            'titel' => $titel,
            'gruppe' => $kopf['gruppe'] ?? 'Weiteres',
            'reihenfolge' => (int) ($kopf['reihenfolge'] ?? 999),
            'routen' => $this->normiert($kopf['routen'] ?? null),
            'stichworte' => $this->normiert($kopf['stichworte'] ?? null),
            'markdown' => trim($treffer[2]),
        ];
    }

    /**
     * Kommaliste in einheitlicher Schreibweise — sonst weicht der
     * Fingerabdruck schon an einem zusaetzlichen Leerzeichen ab und der
     * Seeder hielte jedes Kapitel fuer redigiert.
     */
    private function normiert(?string $wert): ?string
    {
        $liste = HilfeKapitel::liste($wert);

        return $liste === [] ? null : implode(', ', $liste);
    }
}
