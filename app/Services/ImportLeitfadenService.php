<?php

namespace App\Services;

/**
 * Geführte Importe (Roadmap 6.2, zweite Stufe).
 *
 * Die Einrichtungs-Checkliste sagt einer neuen Schule bisher nur, **dass** ein
 * Import fehlt, und verlinkt die Maske. Was dort hineingehört, weiß sie damit
 * noch nicht: welcher Export aus ASV oder Untis, welche Spalten in welcher
 * Reihenfolge, und wie eine Zeile aussieht, die durchgeht.
 *
 * Dieser Dienst führt genau das — je Import den Weg im Quellsystem, die
 * Spaltenbedeutung und eine Vorlage zum Herunterladen.
 *
 * **Die Vorlagen sind keine Prosa, sondern Daten.** Jede von ihnen wird in
 * `tests/Tenant/ImportLeitfadenTest.php` durch den echten Importdienst
 * geschickt: Eine Beispieldatei, die die eigene Maske abweist, wäre schlimmer
 * als gar keine — der Nutzer suchte den Fehler dann bei sich.
 */
class ImportLeitfadenService
{
    public const LEHRKRAEFTE = 'lehrkraefte';
    public const SCHUELER = 'schueler';
    public const STUNDENPLAN = 'stundenplan';

    /** Ablage der ASV-Berichtsvorlagen, relativ zu resources/. */
    public const BERICHTE_VERZEICHNIS = 'asv-berichte';

    /**
     * Alle Leitfäden in der Reihenfolge, in der sie sinnvoll abgearbeitet
     * werden — dieselbe wie in der Checkliste, aus demselben Grund: Erst die
     * Lehrkräfte, sonst legt der Schülerimport für unbekannte Kürzel den
     * Platzhalter „EXTERN" an.
     *
     * @return list<array{schluessel: string, titel: string, checklisten_key: string, quelle: string, route: string|null, dateiname: string, trennzeichen: string, kopfzeile: bool, beschreibung: string, exportweg: list<string>, spalten: list<array{feld: string, bedeutung: string}>, hinweise: list<string>, asv_berichte: list<array{datei: string, anzeigename: string, bereich: string}>}>
     */
    public function alle(): array
    {
        return [
            $this->lehrkraefte(),
            $this->schueler(),
            $this->stundenplan(),
        ];
    }

    /**
     * @return array{schluessel: string, titel: string, checklisten_key: string, quelle: string, route: string|null, dateiname: string, trennzeichen: string, kopfzeile: bool, beschreibung: string, exportweg: list<string>, spalten: list<array{feld: string, bedeutung: string}>, hinweise: list<string>, asv_berichte: list<array{datei: string, anzeigename: string, bereich: string}>}|null
     */
    public function fuer(string $schluessel): ?array
    {
        foreach ($this->alle() as $leitfaden) {
            if ($leitfaden['schluessel'] === $schluessel) {
                return $leitfaden;
            }
        }

        return null;
    }

    /**
     * Der Inhalt der Vorlagendatei: Kopfzeile (wo das Quellsystem eine
     * schreibt) plus die Beispielzeilen.
     *
     * **Ohne BOM und mit \r\n.** Excel öffnet die Datei damit sauber, und die
     * Parser stolpern nicht: `fgetcsv()` schneidet das \r ab, ein BOM stünde
     * dagegen mitten im ersten Feld — die erste Zeile wäre dann keine Nummer
     * mehr und fiele durch die Prüfung.
     */
    public function vorlage(string $schluessel): ?string
    {
        $leitfaden = $this->fuer($schluessel);

        if ($leitfaden === null) {
            return null;
        }

        $zeilen = $this->beispielzeilen($schluessel);

        if ($leitfaden['kopfzeile']) {
            array_unshift($zeilen, $this->kopfzeile($schluessel));
        }

        return implode("\r\n", $zeilen) . "\r\n";
    }

    /**
     * Eine ASV-Berichtsvorlage (.bsbj) zum Herunterladen.
     *
     * Manche Exporte liefert ASV nicht von Haus aus; die Schule muss den
     * Bericht einmalig in ihre Berichtsbibliothek importieren. Die Dateien
     * liegen gesammelt in resources/asv-berichte/ und werden je Leitfaden
     * unter `asv_berichte` eingetragen — eine neue Vorlage ist damit eine
     * Datei plus ein Eintrag, ohne Route und ohne Controller.
     *
     * **Ausgeliefert wird nur, was eingetragen ist.** Der Dateiname aus der
     * URL wird gegen die Liste geprüft und nie direkt an das Dateisystem
     * gereicht; `../.env` findet hier nichts.
     *
     * @return array{pfad: string, anzeigename: string}|null
     */
    public function berichtsvorlage(string $schluessel, string $datei): ?array
    {
        foreach ($this->fuer($schluessel)['asv_berichte'] ?? [] as $bericht) {
            if ($bericht['datei'] === $datei) {
                return [
                    'pfad' => resource_path(self::BERICHTE_VERZEICHNIS . '/' . $bericht['datei']),
                    'anzeigename' => $bericht['anzeigename'],
                ];
            }
        }

        return null;
    }

    /**
     * Die Kopfzeile im Wortlaut des Quellsystems. Sie wird von keinem Parser
     * ausgewertet — die Spaltenreihenfolge zählt —, steht aber in den echten
     * Exporten und gehört deshalb in die Vorlage.
     */
    private function kopfzeile(string $schluessel): string
    {
        return match ($schluessel) {
            self::LEHRKRAEFTE => 'Nr.;Name;Lehrkräfte.Kürzel;Lehrkräfte.Amtsbezeichnung (entschl.).Kurzform;'
                . 'Lehrkräfte.Lehrbefähigungen;Lehrkräfte.Beschäftigungsart.Kurzform;Lehrkräfte.UPZ',
            self::SCHUELER => 'Nr.;Name, Rufname;S1;;S2;;S3;;K1;;K2;',
            default => '',
        };
    }

    /**
     * Erfundene, aber regelkonforme Zeilen. Die Kürzel der Lehrkräfte sind in
     * allen drei Vorlagen dieselben, damit die drei Dateien zusammenpassen,
     * wenn jemand sie der Reihe nach einspielt.
     *
     * @return list<string>
     */
    private function beispielzeilen(string $schluessel): array
    {
        return match ($schluessel) {
            self::LEHRKRAEFTE => [
                '1;Muster, Anna;MUS;StRin;D - GY, G - GY;vz;23.0',
                '2;Beispiel, Bernd;BSP;OStR;M - GY, Ph - GY;tz;17.0',
                '3;Probe, Clara;PRB;StRefin;E - GY, Sw - GY;vd;16.0',
            ],
            self::SCHUELER => [
                ';Muster, Doris;;;;;;;;;;',
                '1;;2M-L1;BSP;2d1;MUS;2ph1;BSP;2g1;MUS;2e1;PRB',
                ';Beispiel, Emil;;;;;;;;;;',
                '2;;2M-L1;BSP;2d1;MUS;2e1;PRB;2g1;MUS;2ph1;BSP',
            ],
            self::STUNDENPLAN => [
                '1,Q12,MUS,2d1,A101,1,3',
                '2,Q12,BSP,2M-L1,A102,1,4',
                '3,Q13,PRB,2e1,A103,2,1',
                '4,Q13,MUS,2g1,A101,4,5',
            ],
            default => [],
        };
    }

    /**
     * @return array{schluessel: string, titel: string, checklisten_key: string, quelle: string, route: string|null, dateiname: string, trennzeichen: string, kopfzeile: bool, beschreibung: string, exportweg: list<string>, spalten: list<array{feld: string, bedeutung: string}>, hinweise: list<string>, asv_berichte: list<array{datei: string, anzeigename: string, bereich: string}>}
     */
    private function lehrkraefte(): array
    {
        return [
            'schluessel' => self::LEHRKRAEFTE,
            'titel' => 'Lehrkräfte aus ASV',
            'checklisten_key' => 'lehrkraefte',
            'quelle' => 'ASV (Amtliche Schulverwaltung)',
            'route' => 'teachers.import.form',
            'dateiname' => 'vorlage-lehrkraefte.csv',
            'trennzeichen' => ';',
            'kopfzeile' => true,
            'beschreibung' => 'Kürzel, Name, Amtsbezeichnung, Lehrbefähigungen und '
                . 'Beschäftigungsumfang. Die Lehrbefähigungen steuern später, wer als Prüfer '
                . 'oder Beisitzer in einem Fach vorgeschlagen wird.',
            'exportweg' => [
                'Die Berichtsdatei „Lehrerliste - Kürzel Name Amtsbez.bsbj" herunterladen und einmalig in ASV in die Berichtsbibliothek (Bereich „Lehrkräfte") importieren.',
                'Den Bericht „Lehrerliste - Kürzel Name Amtsbez" in der ASV-Berichtsbibliothek ausführen (enthält Nummer, Name, Kürzel, Amtsbezeichnung, Lehrbefähigungen, Beschäftigungsart und UPZ).',
                'Als CSV mit Semikolon ausgeben und die Datei unverändert hier hochladen.',
            ],
            'spalten' => [
                ['feld' => 'Nr.', 'bedeutung' => 'Laufende Nummer. Muss eine Zahl sein — Zeilen ohne Zahl werden übersprungen, so fallen Summen- und Zwischenzeilen von selbst heraus.'],
                ['feld' => 'Name', 'bedeutung' => 'Nachname, Vorname — mit Komma. Ohne Komma wird die Zeile übergangen.'],
                ['feld' => 'Kürzel', 'bedeutung' => 'Das Kürzel, mit dem der Stundenplan und die Kurslisten arbeiten. Muss dort gleich geschrieben sein.'],
                ['feld' => 'Amtsbezeichnung', 'bedeutung' => 'Kurzform, etwa StR, OStR, StRefin. Erscheint auf Niederschriften.'],
                ['feld' => 'Lehrbefähigungen', 'bedeutung' => 'Fachkürzel, durch Komma getrennt. Ein Zusatz wie „ - GY" wird abgeschnitten.'],
                ['feld' => 'Beschäftigungsart', 'bedeutung' => 'vz, tz, vd oder wh.'],
                ['feld' => 'UPZ', 'bedeutung' => 'Unterrichtspflichtzeit in Stunden. Komma oder Punkt als Dezimaltrennzeichen.'],
            ],
            'hinweise' => [
                'Vor dem Schülerimport einlesen: Sonst legt dieser für unbekannte Kürzel den Platzhalter „EXTERN" an.',
                'Fachkürzel, die der Fächerkatalog nicht kennt, werden übergangen — die Lehrkraft wird trotzdem angelegt.',
            ],
            'asv_berichte' => [
                [
                    'datei' => 'lehrerliste-kuerzel-name-amtsbez.bsbj',
                    'anzeigename' => 'Lehrerliste - Kürzel Name Amtsbez.bsbj',
                    'bereich' => 'Lehrkräfte',
                ],
            ],
        ];
    }

    /**
     * @return array{schluessel: string, titel: string, checklisten_key: string, quelle: string, route: string|null, dateiname: string, trennzeichen: string, kopfzeile: bool, beschreibung: string, exportweg: list<string>, spalten: list<array{feld: string, bedeutung: string}>, hinweise: list<string>, asv_berichte: list<array{datei: string, anzeigename: string, bereich: string}>}
     */
    private function schueler(): array
    {
        return [
            'schluessel' => self::SCHUELER,
            'titel' => 'Schüler und Kurse aus ASV',
            'checklisten_key' => 'schueler',
            'quelle' => 'ASV (Amtliche Schulverwaltung)',
            'route' => 'import.form',
            'dateiname' => 'vorlage-schueler-kurse.csv',
            'trennzeichen' => ';',
            'kopfzeile' => true,
            'beschreibung' => 'Der Jahrgang mit seinen fünf Abiturprüfungen. Kurse und '
                . 'fehlende Lehrkräfte legt dieser Import selbst mit an.',
            'exportweg' => [
                'In ASV die Berichtsbibliothek öffnen und im Bereich „Schüler" den Bericht „Abiturfächer Kurse" für die Jahrgangsstufe aufrufen.',
                'Die drei schriftlichen Fächer (S1 bis S3) und die beiden Kolloquien (K1, K2) '
                    . 'jeweils mit Kurs und Lehrkraft ausgeben.',
                'Als CSV mit Semikolon speichern.',
            ],
            'spalten' => [
                ['feld' => 'Zeile 1: Name', 'bedeutung' => 'Je Prüfling steht der Name allein in einer eigenen Zeile — erste Spalte leer, zweite „Nachname, Rufname".'],
                ['feld' => 'Zeile 2: Nr.', 'bedeutung' => 'Die folgende Zeile trägt die Schülernummer in der ersten Spalte und lässt die zweite leer. Beide Zeilen zusammen ergeben einen Prüfling.'],
                ['feld' => 'S1 bis S3', 'bedeutung' => 'Je zwei Spalten: Kurscode und Lehrerkürzel. Das sind die schriftlichen Prüfungen.'],
                ['feld' => 'K1 und K2', 'bedeutung' => 'Ebenfalls je zwei Spalten. Das sind die Kolloquien.'],
            ],
            'hinweise' => [
                'Die Zweizeiligkeit ist kein Fehler im Export, sondern seine Form — die Vorlage zeigt sie.',
                'Fehlt zu einem Kurs das Lehrerkürzel, trägt der Import „EXTERN" ein; die Kursliste weist den Kurs danach als „ohne Lehrkraft" aus.',
                'Der Import schreibt in den aktuellen Prüfungszeitraum. Vorher prüfen, dass der richtige eingestellt ist.',
            ],
            // „Abiturfächer Kurse" bringt ASV selbst mit — keine Datei nötig.
            'asv_berichte' => [],
        ];
    }

    /**
     * @return array{schluessel: string, titel: string, checklisten_key: string, quelle: string, route: string|null, dateiname: string, trennzeichen: string, kopfzeile: bool, beschreibung: string, exportweg: list<string>, spalten: list<array{feld: string, bedeutung: string}>, hinweise: list<string>, asv_berichte: list<array{datei: string, anzeigename: string, bereich: string}>}
     */
    private function stundenplan(): array
    {
        return [
            'schluessel' => self::STUNDENPLAN,
            'titel' => 'Stundenplan aus Untis',
            'checklisten_key' => 'stundenplan',
            'quelle' => 'Untis',
            'route' => 'timetable.upload',
            'dateiname' => 'vorlage-stundenplan.txt',
            'trennzeichen' => ',',
            'kopfzeile' => false,
            'beschreibung' => 'Der Stundenplan des Kollegiums. Die Kolloquiumsplanung liest '
                . 'daraus, an welchen Wochentagen eine Lehrkraft ohnehin nicht an der Schule '
                . 'ist, und schont diese Tage.',
            'exportweg' => [
                'In Untis „Datei → Import/Export → Export TXT-Datei (CSV)" wählen.',
                'Als Datenart den Unterricht ausgeben, mit Klasse, Lehrer, Kurs, Raum, Tag und Stunde.',
                'Die Datei ohne Nachbearbeitung hochladen — die Vorschau zeigt vor dem Schreiben, was ankommt.',
            ],
            'spalten' => [
                ['feld' => '1 — laufende Nummer', 'bedeutung' => 'Wird nicht ausgewertet, muss aber dastehen.'],
                ['feld' => '2 — Klasse', 'bedeutung' => 'Etwa Q12 oder Q13. Pseudo-Klassen wie BP, Dir oder NA werden übergangen.'],
                ['feld' => '3 — Lehrerkürzel', 'bedeutung' => 'Muss zum Kürzel aus dem Lehrkräfte-Import passen, sonst bleibt die Zeile ohne Zuordnung.'],
                ['feld' => '4 — Kurscode', 'bedeutung' => 'Daraus wird das Fach abgeleitet, etwa 2d1 → Deutsch.'],
                ['feld' => '5 — Raum', 'bedeutung' => 'Darf leer bleiben.'],
                ['feld' => '6 — Wochentag', 'bedeutung' => '1 bis 5 für Montag bis Freitag. Zeilen mit 0 überspringt der Import stillschweigend.'],
                ['feld' => '7 — Unterrichtsstunde', 'bedeutung' => '1 bis 12.'],
            ],
            'hinweise' => [
                'Untis schreibt keine Kopfzeile, die Vorlage deshalb auch nicht — eine zusätzliche Zeile würde als fehlerhaft gezählt.',
                'Nach den Lehrkräften einlesen: Ohne passendes Kürzel steht der Eintrag ohne Lehrkraft in der Tabelle, und die Auswertung der freien Tage übergeht ihn.',
                'Das Schuljahr wählen Sie in der Maske, es steht nicht in der Datei.',
            ],
            'asv_berichte' => [],
        ];
    }
}
