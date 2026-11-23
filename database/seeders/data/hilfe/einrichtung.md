---
titel: Einrichtung einer Schule
gruppe: Erste Schritte
reihenfolge: 20
routen: setup
stichworte: einrichtung, checkliste, onboarding, erste schritte, planungsreife, import
---

Die Seite *Einrichtung* zeigt, was Ihrer Schule bis zur Planung noch fehlt. Sie
hat zwei Teile.

## Drei Dateien einlesen

Notwendig sind nur drei Dateien. Der Fortschrittsbalken zählt nur sie:

1. **Lehrkräfte** aus ASV.
2. **Schüler und Kurse** aus ASV. Der Import legt die Kurse und die fünf
   Prüfungen je Prüfling gleich mit an. Fehlt danach einem Kurs die Lehrkraft
   oder einem Prüfling eine Prüfung, steht das in der Zeile.
3. **Stundenplan** aus Untis. Daraus liest die Kolloquiumsplanung, an welchen
   Tagen eine Lehrkraft ohnehin nicht an der Schule ist.

Jede Zeile hat drei Verweise: **Anleitung** (Exportweg im Quellsystem und
Spaltenbedeutung), **Vorlage** (eine Beispieldatei) und **Einlesen** (die
Importmaske). Dieselben Zeilen stehen auf der Startseite, solange eine Datei
fehlt.

**Erst die Lehrkräfte, dann die Schüler.** Der Schülerimport legt für jedes
Lehrerkürzel, das er nicht kennt, den Platzhalter `EXTERN` an.

## Vorbelegt — bei Gelegenheit anpassen

Beim Einrichten legt das Programm ein Gerüst an, mit dem sofort geplant werden
kann. Ein Punkt ● bedeutet: noch die Vorgabe oder unvollständig.

- **Schulstammdaten** — Anschrift, Ort, Postleitzahl und E-Mail für
  Niederschriften und Exporte.
- **Räume** — zwölf Prüfungsräume (neun Klassenzimmer 8×4, zwei kleine Räume
  4×4, ein Fachraum 6×3), Vorbereitungsraum, Warteraum und eine Fluraufsicht,
  benannt „Raum 001“ bis „Raum 207“ und „Flur 2. OG“. Benennen Sie sie nach
  Ihren Raumnummern um, löschen Sie überzählige, legen Sie fehlende an.
- **Sitzpläne** — jeder Prüfungsraum hat einen Sitzplan mit Pult und
  gesperrten Plätzen.
- **Zeitslots der Kolloquien** — montags bis donnerstags fünf Prüfungen am
  Nachmittag (13:10 bis 17:00), freitags fünf am Vormittag (08:30 bis 13:40),
  je 30 Minuten Vorbereitung und Prüfung. Feiertage in den Prüfungswochen
  bleiben frei. Dazu drei Aufsichtsfenster je Tag.
- **Schuleinstellungen** — Hinweistexte und Schwellenwerte; die Vorgabewerte
  sind brauchbar.

Außerdem angelegt, aber ohne eigene Zeile: zwei Aufsichtsraster für die
schriftlichen Prüfungen (Standard und moderne Fremdsprachen mit Hörverstehen).

## Nach dem Schuljahreswechsel

Schüler und Stundenplan hängen am Jahrgang bzw. Schuljahr. Nach dem Wechsel
stehen diese Zeilen wieder offen, und die Startseite zeigt die Dateien erneut.
Räume, Sitzpläne und Lehrkräfte bleiben.
