---
titel: Absenzen
gruppe: Weitere Aufgaben
reihenfolge: 610
routen: absences
stichworte: absenzen, fehlzeiten, krankheit, verspätung, attest, attestpflicht, maßnahme, infoportal
---

## Import aus dem Infoportal

Der Absenzenbericht als CSV, **semikolongetrennt**, mit diesen Spaltenüber-
schriften:

`Name`, `Vorname`, `Datum von`, `Datum bis`, `Tage`, `Stunden`, `Minuten`,
`Art der Absenz`, `entschuldigt`, `Grund`, `Ist Schulabsenz`,
`Absenz eingetragen am`, `Eingetragen von`

Die Anwendung liest die *Art der Absenz* aus und ordnet sie einer Kategorie zu:
krank, verspätet, fehlt in einzelnen Stunden, sonstige.

> **Befreiungen und vorab genehmigte Freistellungen werden absichtlich nicht
> importiert.** Sie sind keine Fehlzeit im hier gemeinten Sinn.

### Namen zuordnen

Zuerst über den Nachnamen (auch bei Adelsprädikaten, wenn der Stammdatenname auf
den Exportnamen endet), dann mit einer Toleranz beim Vornamen. Genau ein Treffer
wird automatisch übernommen; bei keinem oder mehreren fragt die Vorschau nach.

Die Vorschau zeigt oben **„N Zuordnung(en) zu klären"** mit einem Auswahlfeld je
Fall — dort können Sie einen Prüfling zuweisen oder die Zeile **ignorieren**.
Darunter steht, was automatisch zugeordnet wurde.

### Erneut importieren

Der Import ist wiederholbar: Jede Absenz bekommt einen Schlüssel aus Zeitraum,
Art, Dauer, Name und der Reihenfolge am selben Tag. Mehrfacheinträge desselben
Tages bleiben getrennt.

> **Achtung:** Ändert die Quelle den Zeitstempel *Absenz eingetragen am*, kann
> sich die Reihenfolge verschieben — dann entstehen Doppel. Ein Blick in die
> Zahlen nach dem Import lohnt.

## Die Übersicht

Reiter für **Q13** und **Q12**. Oben steht, wann zuletzt importiert wurde; nach
sieben Tagen erscheint eine Warnung, dass die Daten veraltet sein könnten.

Überschrittene Schwellen werden rot hervorgehoben. Welche Schwellen gelten und
wie die Bezugsgröße „Schultage bisher" zustande kommt, steht in den
*Schuleinstellungen*.

**„Aktion nötig"** heißt: mindestens eine Schwelle überschritten **oder** ein
Attest überfällig. Diese Liste lässt sich je Jahrgangsstufe als PDF ausgeben,
mit dem jeweiligen Grund.

## Zwei verschiedene Atteste

Das wird leicht verwechselt:

- **Die Attest-Marke** in der Übersicht zählt offene Absenzen, die im Export als
  attestpflichtig geführt sind. Die Frist läuft zehn Tage ab Beginn; danach ist
  sie rot.
- **Die interne Attestpflicht** ist eine von Ihnen dokumentierte Maßnahme —
  *Attestpflicht verhängt* und *Attestpflicht aufgehoben*. Beide Typen sind fest
  eingebaut.

## Maßnahmen

Auf der Detailseite eines Prüflings. Pflicht sind **Typ**, **Aspekt** und
**Datum**; eine Notiz ist freiwillig.

> **Der Aspekt setzt einen Zähler zurück.** Wählen Sie „Krankheit /
> Attestpflicht", „Unentschuldigtes Fehlen" oder „Verspätung", zählt die
> Übersicht ab diesem Datum neu — die Detailseite zeigt weiterhin die volle
> Geschichte. Mit „— (kein Reset)" dokumentieren Sie nur.

Das ist der Weg, nach einem geführten Gespräch wieder bei null anzufangen, ohne
Daten zu verlieren.

Die Maßnahmen-Typen pflegen Sie in den *Schuleinstellungen*.

## Überschneidende Zeiträume

Die Anwendung fasst überschneidende Zeiträume **nicht** zusammen und warnt auch
nicht davor — Tage und Stunden werden schlicht summiert. Wer zwei Exporte mit
überlappenden Zeiträumen einliest, sollte die Zahlen daher prüfen.

## Vorjahr

Absenzen aus dem Vorjahr tragen die Marke **Vorjahr**. Sie sind ein Hinweis und
zählen nicht in „Aktion nötig".
