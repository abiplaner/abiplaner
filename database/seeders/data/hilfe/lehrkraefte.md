---
titel: Lehrkräfte
gruppe: Stammdaten
reihenfolge: 110
routen: teachers
stichworte: lehrkräfte, lehrer, kürzel, fakultas, lehrbefähigung, abwesenheit, import
---

## Anlegen und Bearbeiten

Pflicht sind nur **Kürzel**, **Vorname** und **Nachname**. Dazu kommen
Amtsbezeichnung, Geschlecht, Externe ID, Beschäftigungsart (vz, tz, vd, wh) und
die UPZ.

**Das Kürzel ist der Schlüssel zu allem anderen.** Stundenplan, Kursliste und
Schülerimport verweisen darüber auf die Lehrkraft — es muss überall gleich
geschrieben sein.

Die **Externe ID** darf nur einmal vergeben werden.

## Fakultas

Hinter *Fächerliste öffnen* kreuzen Sie an, welche Fächer die Lehrkraft
unterrichten darf. Die Fakultas steuert, wer in einem Fach als Prüfer, Beisitzer
oder Kursleiter vorgeschlagen wird. Drei Gleichsetzungen sind fest eingebaut:

- **WR** deckt auch **Ww** ab,
- **Smw** deckt **Sm** und **Sw** ab,
- **Fs** deckt **F** ab.

## Nur beim Bearbeiten

Zwei Blöcke erscheinen erst, wenn die Lehrkraft schon angelegt ist:

- **Kontakt für Zusatzprüfungen** — Telefon, private und dienstliche E-Mail,
  ByCS-Messenger. Diese Angaben erscheinen auf dem Schüler-Fahrplan zur
  Zusatzprüfung.
- **Aktiv** — nur aktive Lehrkräfte werden in Prüfer-, Beisitzer- und
  Aufsichtsauswahlen angeboten. Bestehende Zuweisungen bleiben bestehen.

Beides lässt sich beim Neuanlegen noch nicht setzen; legen Sie die Lehrkraft an
und öffnen Sie sie danach erneut.

## Abwesenheiten für das Kolloquium

Über *Abwesenheiten* öffnen Sie die Kolloquiumstage des Zeitraums und klicken
an, wann die Lehrkraft nicht kann; ein **Grund** ist freiwillig. Gespeichert
wird ganztägig. Über *„Anderen Prüfungszeitraum / Uhrzeit eintragen"* geht es
auch stundengenau.

**Abwesenheiten sind ein hartes Kriterium:** Die automatische Kolloquiumsplanung
plant an solchen Tagen nicht. Verwechseln Sie sie nicht mit den
**stundenplanfreien Tagen** — die sind nur eine weiche Präferenz (siehe
*Lehrerverfügbarkeit und freie Tage*).

Abwesenheiten hängen am Prüfungszeitraum. Für einen neuen Jahrgang sind sie neu
zu erfassen. Erscheint „Keine Kolloquiumstage gefunden", fehlen dem Zeitraum die
Datumsgrenzen.

Die Übersicht aller Abwesenheiten lässt sich als PDF ausgeben.

## Import aus ASV

Der gelebte Weg. Aufbau der Datei und der Ablauf stehen im Kapitel *Geführte
Importe*. Zwei Eigenheiten sind gut zu wissen: Bei mehreren Kürzeln in einer
Zeile gilt **das letzte**, und Fächer, die es noch nicht gibt, werden angelegt.

## Löschen

Mehrere auf einmal über die Ankreuzfelder und *Ausgewählte löschen*. Eine
Lehrkraft wird **nicht** gelöscht, solange sie Kursen zugeordnet ist, Prüfungen
als Prüfer hat oder Aufsichten trägt — die Meldung nennt die Gründe. Wer nicht
gelöscht werden kann, aber nicht mehr auftauchen soll, wird stattdessen auf
*inaktiv* gesetzt.
