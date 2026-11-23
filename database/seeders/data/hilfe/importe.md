---
titel: Geführte Importe
gruppe: Erste Schritte
reihenfolge: 30
routen: setup.imports, import, teachers.import
stichworte: import, csv, asv, untis, vorlage, beispieldatei, lehrkräfte, schüler
---

Drei Dateien bringen eine Schule bis zur Planungsreife. Zu jeder steht unter
*Einrichtung → Geführte Importe* der Weg im Quellsystem, die Bedeutung jeder
Spalte und eine **Vorlage zum Herunterladen** mit erfundenen, aber gültigen
Beispieldaten.

| Datei | Quelle | Trennzeichen | Kopfzeile |
|---|---|---|---|
| `vorlage-lehrkraefte.csv` | ASV | Semikolon | ja |
| `vorlage-schueler-kurse.csv` | ASV | Semikolon | ja |
| `vorlage-stundenplan.txt` | Untis | Komma | **nein** |

Die Lehrerkürzel sind in allen drei Vorlagen dieselben. Wer sie der Reihe nach
einspielt, bekommt einen in sich stimmigen Miniaturbestand zum Ausprobieren.

## Die Reihenfolge

**Erst die Lehrkräfte.** Die beiden anderen Dateien verweisen über das Kürzel
auf sie. Fehlt eine Lehrkraft, trägt der Schülerimport den Platzhalter `EXTERN`
ein, und der Stundenplanimport lässt die Zeile ohne Zuordnung — die Auswertung
der freien Tage übergeht sie dann.

## Lehrkräfte aus ASV

Sieben Spalten in fester Reihenfolge: Nummer, Name („Nachname, Vorname" — **mit
Komma**), Kürzel, Amtsbezeichnung, Lehrbefähigungen, Beschäftigungsart, UPZ.

- Zeilen ohne Zahl in der ersten Spalte werden übersprungen. Summen- und
  Zwischenzeilen fallen so von selbst heraus.
- Bei den Lehrbefähigungen wird ein Zusatz wie `- GY` abgeschnitten; Fächer, die
  der Katalog nicht kennt, werden übergangen — die Lehrkraft entsteht trotzdem.
- Der Import hat zwei Knöpfe: **„🔍 Nur Änderungen anzeigen"** zeigt neu,
  geändert und Deaktivierungsvorschläge; **„Vorschau anzeigen"** zeigt alles.
  Geschrieben wird erst nach dem Bestätigen.

## Schüler und Kurse aus ASV

Der Export ist **zweizeilig je Prüfling** — das ist kein Fehler, sondern seine
Form: eine Zeile mit dem Namen, die nächste mit der Nummer und den fünf
Prüfungen (S1 bis S3 schriftlich, K1 und K2 mündlich), jede mit Kurscode und
Lehrerkürzel.

- In der Maske wählen Sie zusätzlich **Trennzeichen** und **Jahrgangsstufe**
  (Q13 ist vorbelegt).
- Kurse legt dieser Import selbst an; Lehrkräfte nicht.
- Fehlt ein Lehrerkürzel, steht der Kurs danach als „ohne Lehrkraft" in der
  Kursliste.
- Geschrieben wird in den **aktuellen Prüfungszeitraum**. Prüfen Sie vorher
  oben rechts, dass der richtige eingestellt ist.

Für Jahrgänge ohne Prüfungszuordnung — etwa Q12 — gibt es daneben die **einfache
Jahrgangsliste**: nur Namen, keine Kurse.

## Stundenplan aus Untis

Sieben Spalten, **ohne Kopfzeile**: laufende Nummer, Klasse, Lehrerkürzel,
Kurscode, Raum, Wochentag (1 bis 5), Unterrichtsstunde.

- Das Schuljahr wählen Sie in der Maske, es steht nicht in der Datei.
- Pseudo-Klassen wie BP, Dir oder NA werden übergangen, ebenso Zeilen mit dem
  Wochentag 0.
- Der Import läuft in zwei Schritten: hochladen, Vorschau prüfen, bestätigen.
  Brechen Sie ab oder läuft die Sitzung ab, laden Sie die Datei neu hoch.
- In der Archivansicht ist der Import gesperrt.

## Warum die Vorlagen verlässlich sind

Jede ausgelieferte Vorlage läuft im automatischen Test durch **denselben
Importdienst**, für den sie gedacht ist. Eine Beispieldatei, die die eigene
Maske abweist, wäre schlimmer als gar keine: Sie hätten den Fehler bei sich
gesucht.
