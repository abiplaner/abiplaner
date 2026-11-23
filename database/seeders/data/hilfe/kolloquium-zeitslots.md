---
titel: Zeitslots und Zeitraster
gruppe: Kolloquium
reihenfolge: 310
routen: planning.time-slots, slot-templates
stichworte: zeitslot, zeitraster, vorlage, template, vorbereitungszeit, präferenz
---

**Ohne Zeitslots plant die Anwendung nichts.** Sie sind der erste Schritt jeder
Kolloquiumsplanung.

## Was ein Zeitslot ist

Ein Zeitslot beschreibt **einen Prüfungsdurchgang an einem Tag**: Datum,
Slot-Nummer, Vorbereitungszeit (Beginn und Ende) und Prüfungszeit (Beginn und
Ende). Vorbereitung und Prüfung stehen also in einem Datensatz.

Dazu kommt ein **Präferenz-Gewicht** von 0 bis 200 (Vorgabe 100). Es steuert,
wie gern die automatische Planung diesen Slot benutzt:

| Gewicht | Anzeige |
|---|---|
| ab 120 | Sehr bevorzugt |
| ab 100 | Bevorzugt |
| ab 80 | Neutral |
| ab 60 | Weniger bevorzugt |
| darunter | Vermeiden |

So halten Sie etwa den letzten Slot des Tages frei, ohne ihn zu löschen.

## Die Übersicht

Zwei Tabellen, **Woche 1** und **Woche 2**: Zeilen sind die Slot-Nummern,
Spalten die Tage. Jede Zelle zeigt Vorbereitungs- und Prüfungszeit sowie die
Präferenz.

## Zeitraster anlegen und anwenden

Ein **Zeitraster** (im Text auch „Vorlage") ist ein benanntes Muster von Slots,
etwa „Montag Standard" oder „Freitag verkürzt". Sie legen es einmal an und
wenden es auf beliebig viele Tage an.

In der Bearbeiten-Maske eines Rasters stehen drei Blöcke: die Grunddaten, die
**Zeitslots** (Slot-Nummer, die vier Zeiten, Präferenz-Gewicht — alle Pflicht —
sowie Notizen) und **„Template auf Tage anwenden"** mit Ankreuzfeldern je Tag.

Beim Anlegen eines Slots im Raster gilt: Vorbereitungsende nach
Vorbereitungsbeginn, Prüfungsbeginn nicht vor Vorbereitungsende, Prüfungsende
nach Prüfungsbeginn.

## Zwei Wege, die sich unterschiedlich verhalten

Das ist die häufigste Verwechslung in diesem Bereich:

- **Das Auswahlfeld über einer Tagesspalte** in der Zeitslot-Übersicht
  **ersetzt alle Slots dieses Tages**. Die Rückfrage sagt es. Der Tag merkt sich
  danach sein Raster, das Feld ist beim nächsten Mal vorbelegt.
- **„Template auf ausgewählte Tage anwenden"** aus der Raster-Maske heraus
  **löscht nichts** und legt nur fehlende Slots an.

Ein Tag, an dem schon Prüfungen zugewiesen sind, lässt sich nicht über das
Auswahlfeld umstellen — die Anwendung weist das ab. Setzen Sie vorher die
Zuweisungen zurück.

Ein einzelner Slot lässt sich nicht löschen, solange Zuweisungen daran hängen.

## Und dann?

Nach den Zeitslots kommen die **Räume und Tage**, erst danach die Planung.
