---
titel: W-Seminar-Wahlen
gruppe: Wahlen der 11. Klasse
reihenfolge: 520
routen: wseminar-wahlen, wseminar-kurse
stichworte: w-seminar, seminar, wahlen, zuteilung, drittwahl, ringtausch, teilnehmer
---

Derselbe Dreischritt wie bei den Leistungsfächern — mit einigen wichtigen
Unterschieden. Die W-Seminar-Wahl gibt es nur für **Q11**.

## Importieren

CSV mit Semikolon, gleicher Aufbau wie bei den LK-Wahlen: Klasse, Name,
Kommentar, dann je eine Spalte pro Seminar. In den Seminarspalten steht **1**,
**2** oder **3** für Erst-, Zweit- und Drittwahl; alle angegebenen Wahlen müssen
verschieden sein.

**Die Kopfzeile muss genau `Fach - Titel` lauten.** Ohne diesen Trenner findet
die Anwendung das Seminar nicht.

> **Kurse entstehen beim Import.** Ein Seminar, das es noch nicht gibt, wird
> angelegt — mit **15 Plätzen**. Diese Zahl lässt sich in der Oberfläche nicht
> ändern.

Nach dem Import zeigt die Seite eine Importstatistik. **Der Zähler „Schüler neu
angelegt" bleibt immer 0**; neu angelegte Schüler erscheinen stattdessen als
Warnung in der Liste darunter.

## Planen

Oben stehen die Kennzahlen — Schüler im Jahrgang, Schüler mit Wahlen, Höchstzahl
je Seminar, Wahlbeteiligung. In der Seminarübersicht werden Seminare mit weniger
als 10 Interessenten rot markiert.

> Die Zahl „Schüler im Jahrgang" wird über Klassenbezeichnungen ermittelt, die
> mit **11** beginnen. Heißen Ihre Klassen anders, stimmt die Quote nicht.

Zwei Strategien:

- **Optimierte Verteilung** (Vorgabe) mit dem Feld *Geplante Anzahl Kurse*. Nur
  die nachfragestärksten Seminare werden eingerichtet. Zuerst werden schwach
  nachgefragte Kurse mit allen Interessenten gefüllt, dann die übrigen Schüler in
  drei Durchgängen (Erst-, Zweit-, Drittwahl) verteilt — wer wenige Wünsche
  angegeben hat, kommt zuerst dran. Zum Schluss versucht ein **Ringtausch**,
  Unversorgte doch noch unterzubringen: Ein bereits Zugeteilter wechselt dafür in
  einen anderen seiner Wunschkurse mit freiem Platz.
- **Viele Erstwahlen** — ein einfacher Durchgang über Erst-, Zweit- und
  Drittwahl, danach derselbe Ringtausch.

## Der wichtigste Unterschied zur LK-Zuteilung

> **Die W-Seminar-Zuteilung ist nicht wiederholbar.** Die Reihenfolge der Schüler
> wird gemischt, jeder Lauf ergibt ein anderes Ergebnis. **„Zuteilung
> durchführen" rechnet komplett neu** — das gespeicherte Ergebnis kann von der
> Vorschau abweichen, die Sie gerade betrachtet haben.
>
> Wer eine bestimmte Vorschau haben will, muss sie also festhalten, bevor er
> durchführt — oder das Ergebnis danach prüfen.

Dafür ist das Durchführen hier **ungefährlicher** als bei den Leistungsfächern:
Es schreibt nur die Zuordnungen. **Kurse, Lehrkräfte und Kursdaten bleiben
erhalten**, auch beim Zurücksetzen.

## Wer keinen Platz bekommt

Die Statistik unterscheidet zwei Fälle: **„nicht zugeteilt"** — der Schüler hatte
Wünsche, es ging nicht auf — und **„gar nicht gewählt"**. Beide stehen in der
Ergebnisliste.

## Nacharbeiten

Hier ist wenig möglich: Die Wahlmaske ändert nur Erst-, Zweit- und Drittwahl
(die Erstwahl ist Pflicht), **nicht die Zuteilung**. Die Kursansicht ist reine
Anzeige — Teilnehmer lassen sich nicht von Hand hinzufügen oder entfernen.

Wer einzelne Fälle geraderücken muss, ändert die Wahlen und lässt neu zuteilen.

## Ausdrucke

Vier Stück: auf der Planungsseite **PDF** und **Excel** der Wahlübersicht (mit
der Rotmarkierung schwacher Seminare), auf der Kursseite **PDF** und **Excel**
mit Übersicht, je Kurs einem Blatt und einer Liste der nicht Zugeteilten.

**Kurse ohne Teilnehmer fehlen im Excel-Export.**
