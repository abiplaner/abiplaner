---
titel: Stundenplan
gruppe: Stammdaten
reihenfolge: 150
routen: timetable
stichworte: stundenplan, untis, import, klausurenplan, unterricht, freie tage
---

Der Stundenplan hat in der Anwendung **einen** entscheidenden Zweck: Die
Kolloquiumsplanung liest daraus, an welchen Wochentagen eine Lehrkraft ohnehin
nicht an der Schule ist, und schont diese Tage. Daneben dient er als Nachschlage-
werk für Klassen- und Lehrerpläne.

## Import

Zwei Dateien, eine Pflicht:

- **Stundenplan-Datei** aus Untis (Datei → Export → Text, Tabelle *Unterricht*,
  Felder Klasse, Lehrer, Fach/Kurs, Raum, Tag, Stunde), höchstens 10 MB.
- **Lehrbefähigungs-Datei** aus ASV, freiwillig — sie reichert nur Namen und
  Amtsbezeichnungen an.

Dazu wählen Sie das **Schuljahr** und können auf **„Nur Oberstufe importieren
(Q12/Q13)"** einschränken.

**Der Stundenplan ist die maßgebliche Quelle dafür, welche Lehrkräfte aktiv
sind** — importiert werden nur Lehrkräfte, die im Stundenplan vorkommen.

Der Ablauf ist zweistufig: hochladen, **Vorschau** prüfen (dort lassen sich
Lehrerzuordnungen korrigieren und Zeilen entfernen), dann bestätigen. Beim
Bestätigen können Sie die Einträge des Schuljahres vorher löschen lassen. Wird
die Sitzung unterbrochen, laden Sie die Datei neu hoch. In der Archivansicht ist
der Import gesperrt.

Der Dateiaufbau steht im Kapitel *Geführte Importe*, samt Vorlage.

## Ansichten

- **Stundenplan ansehen** — gefiltert nach Schuljahr und entweder Klasse oder
  Lehrerkürzel.
- **Klassenplan** und **Lehrerplan** als eigene Seiten.
- **Klausurenplan** — die Übersicht der Schulaufgaben; `/timetable` führt
  direkt dorthin.
- **Lehrerverfügbarkeit** — siehe eigenes Kapitel.

## Wo die freien Tage wirken

Nur in der automatischen Kolloquiumsplanung, und dort nur als **weiche
Präferenz** über die Option „Stundenplanfreie Tage möglichst meiden".

**Zwei Dinge dazu, die man wissen muss:** Ohne Stundenplanimport bleibt die
Option wirkungslos. Und eine Lehrkraft ohne jeden Stundenplaneintrag taucht in
dieser Auswertung gar nicht auf — „keine Daten" heißt nicht „die ganze Woche
frei".

Wer verlässlich freihalten will, trägt eine **Abwesenheit** bei der Lehrkraft
ein. Das ist ein hartes Kriterium.
