---
titel: Räume und Sitzvorlagen
gruppe: Stammdaten
reihenfolge: 140
routen: rooms, seating-templates
stichworte: räume, raumtyp, sitzvorlage, sitzplan, kapazität, platznummer, hörverstehen
---

## Räume

Pflicht sind **Raumnummer** und **Raumtyp**. Fünf Typen stehen zur Wahl:

- **Prüfungsraum** — nur dieser Typ erscheint in der Raumbelegung der
  schriftlichen Prüfungen und in den Raumbelegungs-PDFs.
- **Vorbereitungsraum**, **Warteraum**, **Absperrraum**, **Gangraum** — für das
  Kolloquium und die Aufsichten.

Dazu die **Sitzplan-Vorlage (Standard)** und, wenn nötig, eine abweichende
**Vorlage für das Hörverstehen** derselben Räume.

> **Achtung:** Das Schnell-Anlege-Fenster zeigt ein Feld **Kapazität**. Es hat
> keine Wirkung — die Kapazität eines Raums ergibt sich immer aus der Zahl der
> verfügbaren Plätze in seiner Sitzvorlage.

**Löschen eines Raums** entfernt stillschweigend alle seine Prüfungs-,
Vorbereitungs-, Aufsichts- und Klausurzuweisungen.

## Sitzvorlagen

Eine Vorlage ist ein Raster aus Reihen und Spalten. Beim Anlegen geben Sie
**Name**, **Reihen** und **Spalten** an; alle Plätze starten als *verfügbar*.
Danach passen Sie das Raster an.

**Platztypen** — ein Klick auf eine Zelle wechselt reihum:

Verfügbar → Lehrerpult → Blockiert → Gang → Reserve → Verfügbar

Türen markieren Sie außerhalb des Rasters an den Wänden. Die Kopfzeile zählt
mit, wie viele Plätze verfügbar und wie viele Reserve sind.

### Die Pult-Seite bestimmt die Nummerierung

Das Feld **Pult-Seite (Orientierung)** legt fest, wo Reihe 01 beginnt: Die
Nummerierung startet immer beim Pult. Wer den Raum umdreht, dreht damit auch
alle Platznummern.

### Größe ändern

Plätze außerhalb des neuen Rasters werden **gelöscht**, neue kommen als
*verfügbar* dazu.

### Löschen

Eine Vorlage lässt sich nicht löschen, solange Räume sie verwenden. Dieselbe
Vorlage darf gleichzeitig Standard- und Hörverstehen-Variante sein.

## Zwei Platznummern, die verschieden aussehen

Das verwirrt regelmäßig, ist aber Absicht:

- **In der Maske** trägt jeder Platz seine Rasterposition, zweistellig als
  `Zeile-Spalte`, etwa `03-05`.
- **In allen PDFs** steht eine fortlaufende Nummer, etwa `Platz 17`. Sie zählt
  erst alle verfügbaren Plätze zeilenweise durch und **danach** die
  Reserveplätze — Reserveplätze bekommen also immer die höchsten Nummern.

## Raumbelegung

Unter *Belegung* sehen Sie, welcher Prüfungsraum an welchem Termin belegt ist;
die Übersicht lässt sich als PDF ausgeben.
