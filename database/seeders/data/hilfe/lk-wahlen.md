---
titel: Leistungsfach-Wahlen
gruppe: Wahlen der 11. Klasse
reihenfolge: 510
routen: lk-wahlen, lk-kurse
stichworte: leistungsfach, lk, wahlen, zuteilung, erstwahl, zweitwahl, kurse, kapazität
---

Der Weg hat drei Schritte: **Wahlen importieren → Zuteilung planen → Kurse
nacharbeiten.**

## Wahlen importieren

CSV mit **Semikolon**, UTF-8, höchstens 2 MB. Der Aufbau:

| Spalte | Inhalt |
|---|---|
| A | Klasse |
| B | Vollständiger Name |
| C | Kommentar (wird übergangen) |
| ab D | je eine Spalte pro Fach |

In den Fachspalten steht **1** für die Erstwahl und **2** für die Zweitwahl;
alles andere wird übergangen. Die Kopfzeile darf hinter dem Fachnamen ein
`" - Kürzel"` tragen — das wird abgeschnitten. Steht `(extern)` in der
Kopfzeile, gilt die Wahl als extern.

Der Name wird am **letzten Leerzeichen** getrennt: davor Vornamen, danach der
Nachname.

Schüler werden über Vor- und Nachnamen im aktuellen Prüfungszeitraum gesucht.
Wer nicht gefunden wird, wird als Jahrgangsstufe 11 neu angelegt — mit Warnung.

> **Der Import ist immer ein Voll-Ersatz.** Alle vorhandenen Wahlen dieses
> Schuljahres werden vorher gelöscht; die Meldung sagt, wie viele. Es gibt
> keinen Weg, nur einzelne Zeilen nachzuschieben.

Importiert wird immer in **Jahrgangsstufe 11** und das laufende Schuljahr.

## Zuteilung planen

Drei Einstellungen:

- **Anzahl Kurse (ohne externe)** — 3 bis 15, vorbelegt mit einem Kurs je 15
  Schülern.
- **Strategie** — „Niemand ohne Wahl" (Vorgabe), „Möglichst viele Erstwahlen"
  oder „Ausgeglichene Kursgrößen".
- **Externe Kurse** — sie werden immer eingerichtet und zählen **nicht** gegen
  die Höchstzahl.

**So wird gerechnet:** Zuerst die externen Fächer, dann die internen absteigend
nach Zahl der Erstwahlen, je ein Kurs. Reicht die Höchstzahl nicht, meldet die
Vorschau „Kein Kursplatz mehr für Fach …". Ein Parallelkurs entsteht erst, wenn
mehr als 25 Erstwahlen auf einen Kurs kämen.

Dann die Schüler: erst alle Erstwahlen in externe Kurse (**ohne
Kapazitätsprüfung**), dann Erstwahlen in interne Kurse bis zur Grenze, dann die
Zweitwahlen. Bei „Ausgeglichen" wird jeweils der kleinste passende Kurs
genommen.

**Die Kapazität liegt fest bei 25 Schülern je Kurs** und lässt sich nicht
einstellen.

**Wer weder Erst- noch Zweitwahl bekommt, bleibt unzugeteilt** und erscheint als
solcher in der Ergebnisliste und im Excel-Export. Eine automatische
Restverteilung auf freie Plätze gibt es nicht.

Die Zuteilung ist **wiederholbar**: Gleiche Eingaben ergeben dasselbe Ergebnis,
Vorschau und Durchführung stimmen überein.

## Vorsicht beim Durchführen

> **„Durchführen" löscht alle LK-Kurse des Jahrgangs und legt sie neu an.**
> Eingetragene **Lehrkräfte und Notizen gehen dabei verloren.** Dasselbe gilt
> für „Zuteilung zurücksetzen".
>
> Tragen Sie die Lehrkräfte deshalb **erst ein, wenn die Zuteilung steht** — und
> planen Sie nicht erneut, ohne das zu bedenken.

## Nacharbeiten

In der Kursmaske stehen **Fach**, die feste Kursnummer, die **Lehrkraft** (nur
Lehrkräfte mit passender Fakultas) und **Notizen**.

*Schüler hinzufügen* bietet **nur unzugeteilte Schüler** an — ein Umhängen aus
einem anderen Kurs ist auf diesem Weg nicht möglich; entfernen Sie den Schüler
dort erst.

In der Wahlmaske lässt sich die Zuteilung auch direkt setzen. **Der angezeigte
Prioritätsstatus wird dabei nicht neu berechnet** und kann veralten.

## Ausdrucke

- **Wahlenseite, Excel** — Vorname, Nachname, Erstwahl, Zweitwahl, Zugeteilt,
  Priorität. **Ohne Filter: die Datei enthält alle Schuljahre und Jahrgänge.**
- **Kursseite, Excel** — Blatt *Übersicht*, ein Block mit den nicht zugeteilten
  Schülern und je Kurs ein eigenes Blatt.
- **Kursseite, PDF** — derselbe Umfang auf A4.
