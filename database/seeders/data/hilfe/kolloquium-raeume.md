---
titel: Räume und Tage des Kolloquiums
gruppe: Kolloquium
reihenfolge: 320
routen: planning.rooms
stichworte: räume, tage, prüfungsraum, vorbereitungsraum, warteraum, gang, rollen
---

Hier legen Sie fest, **welcher Raum an welchem Tag welche Rolle hat**. Der
Schritt liegt zwingend zwischen den Zeitslots und der Planung: Die automatische
Planung benutzt nur Räume, die hier als Prüfungsraum stehen.

## Vier Rollen

| Zeichen | Rolle |
|---|---|
| **P** | Prüfungsraum |
| **V** | Vorbereitungsraum |
| **W** | Warteraum |
| **G** | Gang |

## Erst global, dann je Tag

Oben wählen Sie die Räume aus, die **grundsätzlich** zur Verfügung stehen, und
geben ihnen eine Standard-Rolle. Sie gilt für alle Tage.

Darunter stehen zwei Matrizen — **Woche 1** und **Woche 2** —, in denen Sie für
einzelne Tage abweichen können. Gestrichelt dargestellte Felder sind aus dem
globalen Standard übernommen.

> **Nehmen Sie einem Raum die globale Rolle, verschwinden auch alle
> tagesspezifischen Einträge dieses Raums.** Wer nur einen einzelnen Tag ändern
> will, ändert ihn in der Matrix, nicht oben.

## Wenn die Matrix leer bleibt

Die Tage der Matrix stammen aus den vorhandenen **Zeitslots**. Steht dort
„Noch keine Zeitslots vorhanden", legen Sie zuerst die Zeitslots an. Steht
„Noch keine Räume ausgewählt", fehlt oben die globale Auswahl.

## Wofür die Rollen gebraucht werden

- **P** — die Planung verteilt die Prüfungen auf diese Räume.
- **V** — jeder Prüfungsslot braucht eine Aufsicht im genutzten
  Vorbereitungsraum.
- **W** — Warteraum wird nur bei Koppelgruppen gebraucht: Wer vor den anderen
  fertig ist, wartet.
- **G** — je Gang-Ort und Prüfungsslot rechnet die Aufsichtskontrolle mit einer
  Gangaufsicht.

Die Aufsichtsplanung leitet ihren Bedarf aus genau diesen Rollen ab. Wer hier
keinen Gang einträgt, bekommt auch keinen Gangaufsichtsbedarf gemeldet.
