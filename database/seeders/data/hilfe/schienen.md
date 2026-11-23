---
titel: Kursschienen
gruppe: Stammdaten
reihenfolge: 155
routen: timetable.tracks
stichworte: schienen, kursschienen, track, asv, parallelkurse
---

Eine **Schiene** bündelt Kurse, die zur selben Zeit stattfinden — etwa alle
Mathematikkurse eines Jahrgangs am Montag und Donnerstag. Schienen hängen am
**Schuljahr**, nicht am Prüfungszeitraum.

## Von Hand anlegen

Pflicht sind **Jahrgangsstufe** (Q12, Q13 oder jahrgangsübergreifend), **Name**
und die **Kurse**. Die Kurskürzel geben Sie mit Komma getrennt ein, etwa
`1m1, 1m2, 1m3`. Zeitfenster werden im Format Wochentag plus Stunden notiert
(`Mo 1./2.`), mehrere ebenfalls mit Komma getrennt.

## Aus dem Stundenplan erkennen

Der Knopf **Erkennen** wertet den importierten Stundenplan des gewählten
Schuljahres aus und schlägt Schienen vor. Sie wählen aus, welche davon
gespeichert werden. **Ein Stundenplanimport ist dafür Voraussetzung.**

## Aus ASV übernehmen

Über **„ASV-Schienen importieren"** mit zwei Pflichtdateien:

- `STDSCHIE.txt` — die Schienendefinitionen,
- `STDKURSSCHIE.txt` — die Kurszuordnungen,

dazu das Schuljahr.
