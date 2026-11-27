---
titel: Datensicherung
gruppe: Weitere Aufgaben
reihenfolge: 650
routen: backups
stichworte: backup, sicherung, wiederherstellen, restore, datensicherung
---

Die Seite (nur für Schuladmins) sichert **die gesamte Datenbank** — alles, was
in der Anwendung gespeichert ist, auch die Benutzerkonten.

Abiplaner sichert außerdem selbst: einmal täglich und vor jedem
Programm-Update. Oben steht der **Speicherort** der Sicherungen; in der
Desktop-App öffnet ein Knopf den Ordner im Explorer bzw. Finder. Kopieren Sie
die Sicherungen regelmäßig auf ein anderes Laufwerk — eine Sicherung auf
demselben Rechner hilft nicht, wenn der Rechner ausfällt.

## Erstellen

Ein Knopf, dazu ein freiwilliges Feld **Beschreibung** (höchstens 100 Zeichen).
Sie wird in den Dateinamen übernommen — nutzen Sie sie, etwa „vor
Schuljahreswechsel" oder „vor Neuimport Stundenplan".

Jede Sicherung besteht aus der komprimierten Datenbank und einer Beschreibungs-
datei mit Schema-Version, letzter Migration, Tabellenzahl und Prüfsumme. Die
Liste zeigt Dateiname, Größe, Version, Status und Zeitpunkt.

## Wiederherstellen

> **Das Wiederherstellen überschreibt die gesamte Datenbank Ihrer Schule.** Alles,
> was seit der Sicherung entstanden ist, ist danach fort. Legen Sie vorher eine
> aktuelle Sicherung an — auch dann, wenn Sie sicher sind.

Vor dem Wiederherstellen prüft die Anwendung, ob die Sicherung zum heutigen
Datenbankschema passt. Ist sie älter, warnt sie zusätzlich und empfiehlt danach
eine Migration.

## Wann sich eine Sicherung lohnt

Vor jedem Schritt, der viel auf einmal verändert:

- vor dem **Schuljahreswechsel** (der Assistent legt in Schritt 2 selbst eine an),
- vor einem großen **Import**, der bestehende Daten ersetzt,
- vor dem Löschen eines Prüfungszeitraums,
- vor einer **LK-Zuteilung**, die Kurse neu anlegt.

## Abiplaner zurücksetzen

Ganz unten auf der Seite: löscht **alle Daten** — Schule, Benutzerkonten,
Schüler, Lehrkräfte, Planungen — und startet neu mit der Einrichtung. Zur
Bestätigung tippen Sie das Wort ZURÜCKSETZEN ein und geben Ihr Passwort an.

Vorher legt Abiplaner automatisch eine Sicherung „vor-zuruecksetzen“ an. Sie
bleibt im Sicherungsordner; nach der neuen Einrichtung können Sie sie hier
wiederherstellen, falls das Zurücksetzen ein Versehen war.

## In der Archivansicht

Dort sind alle Aktionen abgeschaltet.
