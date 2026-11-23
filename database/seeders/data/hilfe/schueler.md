---
titel: Schüler und Prüfungsfächer
gruppe: Stammdaten
reihenfolge: 120
routen: students
stichworte: schüler, prüflinge, prüfungsfächer, nachteilsausgleich, gesperrte tage, jahrgangsstufe
---

## Die Maske

Pflicht sind **Schüler-Nummer**, **Vorname**, **Nachname** und **alle fünf
Prüfungsfächer**: S1 bis S3 schriftlich, K1 und K2 als Kolloquium. Die fünf
müssen paarweise verschieden sein.

**Nachteilsausgleich** wird in Prozent eingetragen — 20 bedeutet 20 % mehr Zeit.
Die Anwendung rechnet daraus überall die verlängerten Vorbereitungs- und
Prüfungszeiten: im Sitzplan, auf dem Mantelbogen, im Kolloquiumsplan.

Ändern Sie ein Prüfungsfach, das schon einen Termin hat, warnt die Maske — die
Terminzuordnung geht dabei verloren.

Beim Bearbeiten gibt es zusätzlich das Häkchen **„Abitur bestanden"**
(standardmäßig gesetzt). Ohne dieses Häkchen fehlt der Prüfling in der
Schülerliste der Abiturfeier und bei der Preisvergabe.

## Gesperrte Tage für das Kolloquium

Im eigenen Kasten der Bearbeiten-Maske klicken Sie Werktage der beiden
Prüfungswochen an. **Die automatische Planung überspringt gesperrte Tage
vollständig; bei manueller Zuweisung erscheint nur eine Warnung** — die Zuweisung
wird trotzdem gespeichert. Ein erneuter Klick hebt die Sperre auf.

## Die Jahrgangsstufe steht nicht in der Maske

Sie wird **allein beim Import** gesetzt. Ein von Hand angelegter Schüler hat
keine — und fehlt damit in allen Auswertungen, die auf Jahrgangsstufe 13
filtern, etwa der Kolloquiumsplanung und dem Prüfungsausschuss. Wer einen
Prüfling nachträglich aufnimmt, spielt ihn besser über den Import ein.

## Import

Zwei Wege, beide im Kapitel *Geführte Importe* beschrieben:

- **Vollständiger Import** mit Kursen und Prüfungen — Fächer und Lehrerkürzel
  müssen vorher existieren.
- **Einfache Jahrgangsliste** — nur Namen, für Jahrgänge ohne Prüfungszuordnung.

Beide brauchen einen eingestellten Prüfungszeitraum.

## Zwei Kontrollen

- Die Liste zeigt oben den Kasten **„Prüfungszuordnungs-Probleme"** mit
  Direktverweis auf die betroffenen Prüflinge.
- Die **Fächerübersicht** rechnet nach, ob die Zahl der Prüfungen zur Zahl der
  Prüflinge passt (drei schriftliche und zwei mündliche je Kopf), und meldet
  Abweichungen.

Beide Anzeigen lohnen einen Blick, bevor Sie die Kolloquiumsplanung starten:
Ein Prüfling ohne genau zwei Kolloquien lässt sich nicht planen.

## Was hier nicht steht

**Defizite und Punkte** gehören nicht in die Schülerstammdaten. Sie werden im
Bereich *Zusatzprüfungen* geführt, wo auch die Mindestpunktzahl gerechnet wird.
