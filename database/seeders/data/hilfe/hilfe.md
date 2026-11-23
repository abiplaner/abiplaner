---
titel: Hilfe und Handbuch
gruppe: Erste Schritte
reihenfolge: 90
routen: hilfe
stichworte: hilfe, handbuch, suche, anleitung, dokumentation, pdf
---

Die Hilfe hat zwei Zugänge, weil man auf zwei Arten nach ihr greift.

## In der Anwendung

Oben rechts steht auf jeder Seite ein **„?"**. Es öffnet seitlich das Kapitel zu
der Maske, auf der Sie gerade stehen — ohne dass Sie die Seite verlassen oder
Ihre Eingaben verlieren. Die Taste **Esc** schließt es wieder.

Unter *Hilfe* liegt die Übersicht: alle Kapitel nach Bereichen geordnet und eine
**Suche**, die Titel, Stichworte und den vollständigen Text durchsieht. Zu jedem
Treffer steht die Fundstelle, damit Sie nicht jedes Kapitel öffnen müssen, um zu
sehen, warum es dabei ist.

## Zum Ausdrucken

Auf derselben Seite lädt der Knopf **„Handbuch als PDF"** alle Kapitel als ein
Dokument herunter, mit Inhaltsverzeichnis. Das ist dieselbe Hilfe, nur am
Stück — geeignet zum Durchblättern, Ausdrucken und Weitergeben, etwa an eine
Kollegin, die die Vertretung übernimmt.

## Warum beides immer zusammenpasst

Kapitel und Handbuch stammen aus **derselben Quelle**. Es gibt keine zweite
Fassung der Texte, die getrennt gepflegt werden müsste und darum irgendwann
etwas anderes sagen würde als die Anwendung.

> **Für die Weiterentwicklung:** Die Kapitel liegen als Markdown-Dateien unter
> `resources/help/`. Der Kopf jeder Datei nennt Titel, Gruppe, Reihenfolge und
> die Routenpräfixe, unter denen das Kapitel am „?" erscheinen soll. Das
> Handbuch entsteht daraus mit `php artisan handbuch:erzeugen`.
