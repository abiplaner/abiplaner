---
titel: Kolloquiumsplanung
gruppe: Kolloquium
reihenfolge: 330
routen: planning
stichworte: planung, auto-planung, zuweisung, fixieren, validierung, krankheit, nachtermin, wochentausch, vorgezogene pruefung, gso 61 abs 4
---

Das Herzstück. Hier werden die mündlichen Prüfungen auf Zeitslots, Räume und
Kommissionen verteilt.

## Voraussetzungen

1. Jeder Prüfling der Jahrgangsstufe 13 hat **genau zwei** Kolloquien.
2. **Zeitslots** sind angelegt.
3. Unter **Räume & Tage** stehen Prüfungsräume.

Fehlt das Erste, meldet die Seite es oben mit Namensliste. Fehlt das Zweite oder
Dritte, plant die Automatik nichts — **sie legt selbst keine Zeitslots an.**

## Die Kennzahlen

Prüflinge (mit Soll-Zahl), Prüfungen je Prüfling, Planungsfortschritt, **Ohne
Beisitzer** und **Konflikte**. Gezählt wird nur Jahrgangsstufe 13.

## Automatische Planung

Der Knopf öffnet ein Fenster mit diesen Einstellungen; sie werden für Ihre
Schule gemerkt:

- **Bestehende Zuweisungen vorher löschen** — fixierte Prüfungen (🔒) bleiben
  auch dann erhalten.
- **Max. aufeinanderfolgende Prüfungen pro Prüfer** (1 bis 10, Vorgabe 5).
- **Prüfungstage pro Lehrkraft bündeln** — wer schon da ist, prüft weiter.
- **Stundenplanfreie Tage möglichst meiden** — eine *weiche* Präferenz, die
  einen Stundenplanimport voraussetzt.
- **Leistungsfach-Kolloquien bevorzugt in Woche 1 oder Woche 2.**

### Was die Automatik niemals verletzt

- Eine Lehrkraft steht nie zweimal im selben Slot — als Prüfer **und** als
  Beisitzer gezählt.
- Ein Raum ist nie doppelt belegt.
- Ein Prüfling hat höchstens eine Prüfung je Woche.
- **Gesperrte Tage** des Prüflings und **Abwesenheiten** der Lehrkraft werden
  eingehalten.

### Was sie nur anstrebt

Die Slot-Präferenz, das Bündeln der Prüfungstage (eine Lehrkraft, die an dem Tag
schon da ist, wiegt schwer), das Zusammenhalten eingespielter Prüfer-Beisitzer-
Paare, das Entzerren über den Tag, das Meiden stundenplanfreier Wochentage und
die Schonung der ersten Wochentage für Prüflinge, deren letzte schriftliche
Prüfung kurz zuvor lag.

**Koppelgruppen werden zuerst geplant** und wandern immer als Block.

Am Ende stehen Warnungen — etwa „Schüler hat bereits beide Wochen belegt",
„Keine Kapazität gefunden" oder ein Hinweis, dass in einer Woche mehr Prüfungen
als Plätze anstehen.

## Von Hand zuweisen

Per Ziehen und Ablegen. Danach fragt die Anwendung nach dem **Beisitzer**; ohne
Auswahl können Sie ausdrücklich *ohne Beisitzer zuweisen*.

Drei Dinge weist sie hart ab:

1. Der Prüfling hat in dieser Woche schon eine Prüfung.
2. Der Prüfer ist zu dieser Zeit bereits eingeteilt.
3. Dasselbe für den Beisitzer.

Ein **gesperrter Tag** ergibt dagegen nur eine Warnung — die Zuweisung wird
gespeichert. Die Wochenregel entfällt bei Nachterminen.

Für **externe Bewerber** gilt statt der Wochenregel: **nur eine mündliche
Prüfung an einem Tag** (§ 61 Abs. 3 S. 9). Sie legen alle Fächer ab und haben
in der Regel vier Kolloquien; eine Wochenregel wäre für sie nicht erfüllbar.

## Vorgezogene Prüfungen externer Bewerber

Über dem Wochenraster steht der Block **Vorgezogene mündliche Prüfungen**. Zwei
der vier Kolloquien eines externen Bewerbers dürfen nach § 61 Abs. 4 in den zwei
Wochen vor Beginn der schriftlichen Abiturprüfungen liegen. Diese Termine
tragen Sie dort von Hand ein — die Automatik plant sie nicht. Einzelheiten im
Kapitel *Externe Bewerber*.

## Fixieren

Das Schloss-Symbol fixiert eine Prüfung; die Automatik verschiebt sie danach
nicht mehr. Sie können einen Grund hinterlegen, etwa „Externer Prüfer".

## Planung prüfen

Der Knopf listet **Fehler** und **Warnungen** getrennt:

- *Fehler*: nicht genau zwei Prüfungen je Prüfling, zwei Prüfungen desselben
  Prüflings in einer Woche, doppelt eingeteilte Lehrkraft, doppelt belegter Raum.
- *Warnungen*: fehlender Beisitzer, Einteilung an einem Abwesenheitstag, hohe
  Tagesbelastung einer Lehrkraft.

Lassen Sie das vor jedem Export laufen.

## Wochentausch

Verschiebt eine Prüfung in die andere Woche. **Sie wird dabei aus dem Plan
entfernt** und steht danach unter *Ungeplante Prüfungen* mit der neuen Zielwoche.
Hat der Prüfling in der Zielwoche bereits eine Prüfung, wird auch diese
zurückgesetzt. Der Tausch hängt also nicht direkt um, sondern gibt beide zur
Neuplanung frei.

## Erkrankung

Vier Wege, je nach Lage:

- **Absagen** — der Termin fällt weg, die Prüfung wandert in die Seitenleiste
  und kann dort neu eingeplant werden. Es entsteht kein Nachtermin.
- **Neu einplanen** — im Erkrankungs-Modus ziehen Sie den Ersatztermin in Woche
  1 oder 2. Der alte Termin bleibt als abgesagt vermerkt, der neue verweist auf
  ihn.
- **Nachtermin außerhalb der Prüfungswochen** — mit Datum, Beginn, Ende,
  wahlweise Raum und Notiz. **Prüferkonflikte werden hier nicht geprüft.** Solche
  Termine stehen in einem eigenen Block über dem Raster.
- **Rückgängig machen** — löscht den Nachtermin und stellt den Originaltermin
  wieder her. Geht nur bei einem als Erkrankung abgesagten Termin.

## Zwei Arten zurückzusetzen

**„Zuweisungen zurücksetzen"** löscht nur die Termine; die Prüfungen selbst —
Prüfling, Fach, Lehrkräfte — bleiben. Das ist der Knopf, den Sie meinen.

Daneben gibt es einen zweiten Weg, der **alle Prüfungen des Zeitraums** löscht.
Er ist in der Oberfläche bewusst nicht verlinkt.

## Ausdrucke

Über *Exportieren*, mit vorgeschalteter Auswahl der Tage: Kolloquiumsplan (PDF
und Excel), Planungsmatrix kompakt, Niederschriften, Aufgabenabgabe, Schüler-
und Lehrerliste, Raum-Tagespläne, Vorbereitungsräume, Vorbereitungsprotokoll,
Tagesübersicht Fächer und der Raumbelegungsplan.

Für die **Niederschriften** gibt es eine eigene Auswahlseite mit Filtern nach
Prüfling, Woche, Datum, Fach, Kurs und Prüfer — damit lassen sich einzelne
Formulare gezielt nachdrucken.
