---
titel: Schuleinstellungen
gruppe: Stammdaten
reihenfolge: 180
routen: school-settings
stichworte: einstellungen, schlusstext, platzhalter, schwellenwerte, hinweistexte, maßnahmen
---

Sechs Abschnitte, kein Pflichtfeld. Was Sie hier nicht setzen, läuft auf einem
Vorgabewert; ein geleertes Feld heißt „nicht gesetzt" und stellt den Vorgabewert
wieder her. Gespeichert wird alles gemeinsam am Seitenende.

## 1. Kolloquiumsplan — Optionen

**„Aufsichtstabelle im PDF anzeigen"** (ab Werk an) steuert, ob Vorbereitungs-
raum, Warteraum und Gangaufsicht im Kolloquiumsplan-PDF erscheinen.

## 2. Kolloquiumsplan — Schlusstext

Formatierbarer Text am Ende von PDF **und** Excel. Zwei Platzhalter werden beim
Export ersetzt:

- `[[warteraum]]` — die Warteraumbezeichnungen aus der Raumkonfiguration,
- `[[nta_liste]]` — die Prüflinge mit Nachteilsausgleich samt Zeitzuschlag.

So bleibt der Schlusstext richtig, auch wenn sich Räume oder Nachteilsausgleiche
später ändern.

## 3. Kolloquium — Hinweistexte für die Aufsichten

Drei getrennte Felder für **Gangaufsicht**, **Vorbereitungsraum** und
**Warteraum**. Sie erscheinen im Aufsichtsplan-PDF unter der Tabelle — der Ort
für Anweisungen an die aufsichtführenden Lehrkräfte.

## 4. Zusatzprüfungen — Termine

Diese Angaben stehen auf dem Schüler-Fahrplan und den Anmeldezetteln:
**Anmeldeschluss** (Datum und Uhrzeit, ab Werk 08:00), **Anmeldestelle**,
**Prüfungszeitraum von / bis** und **Ergebnisbekanntgabe** (Ort und Zeit).

## 5. Zusatzprüfungen — Schlusstext

Text am Ende des Zusatzprüfungen-PDF. Hier gibt es **keine Platzhalter**.

## 6. Absenzen — Schwellenwerte

Sie steuern allein die **Rotmarkierung** in der Absenzenübersicht. Es wird
nichts automatisch bewertet oder verhängt.

- **Bisherige Schultage im laufenden Schuljahr** — wird automatisch berechnet
  (Montag bis Freitag ab dem Tag nach den letzten Sommerferien, ohne Ferien und
  Feiertage). Das Eingabefeld ist eine freiwillige Übersteuerung; leer lassen
  heißt: automatischer Wert.
- **Krankheitstage** — Schwelle in Prozent, ab Werk 10.
- **Unentschuldigte Fehlstunden** — Anzahl, ab Werk 3.
- **Verspätungen** — Anzahl, ab Werk 5.
- **Maßnahmen-Typen** — freie Liste, einer je Zeile. Ab Werk „Gespräch geführt",
  „Verweis", „Sonstige Maßnahme". *Attestpflicht verhängt* und *Attestpflicht
  aufgehoben* sind fest eingebaut und stehen nicht in der Liste.

**Änderungen an den Maßnahmen-Typen wirken nur auf künftig erfasste Maßnahmen.**
Bereits dokumentierte behalten ihren Wortlaut.

## In der Archivansicht

Dort fehlt der Speichern-Knopf; die Seite sagt es an seiner Stelle.
