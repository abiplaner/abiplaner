---
titel: Support-Fälle bearbeiten
gruppe: Weitere Aufgaben
reihenfolge: 680
routen: admin.support
stichworte: support, fälle, meldungen, bearbeiten, antworten, eskalation, benachrichtigung, aufbewahrungsfrist, loeschfrist, datenschutz
---

Unter **Support** stehen die Meldungen **aller Schulen** in einer Liste. Der
Bereich ist dem Super-Admin vorbehalten und läuft ohne Schulzuordnung — genau
dafür liegen die Fallakten in der zentralen Datenbank.

## Die Liste

Die Filterleiste zeigt zunächst die **offenen** Fälle, also alles außer
„Erledigt". Die Spalte **Mail** sagt, ob die Benachrichtigung zugestellt wurde.
Steht dort **„steht aus"**, ist der Fall gespeichert, aber der Mailversand ist
gescheitert:

```
php artisan support:nachbenachrichtigen
```

Der Befehl holt alle ausstehenden Benachrichtigungen nach und kann gefahrlos
regelmäßig laufen. Ob der Mailweg überhaupt eingerichtet ist, beantwortet:

```
php artisan support:testmail
```

## Der einzelne Fall

Oben stehen Schule, Melder und die Maske, auf der gemeldet wurde. Ist ein
Hilfekapitel zugeordnet, führt der Verweis dorthin — die Zuordnung kommt aus
dem Hilfesystem und wird nicht getrennt gepflegt.

Ganz unten steht der **Kontext zum Zeitpunkt der Meldung**: technische Angaben,
Einrichtungsstand, die letzten Schritte des Melders und der zuletzt
aufgetretene Fehler. Das ist ein Schnappschuss und wird nicht nachgeladen — der
Einrichtungsstand von damals ist oft die halbe Antwort.

Trägt der Fall eine **Fehlerkennung**, steht dieselbe Kennung in der Logzeile
auf dem Server. Damit findet man den vollständigen Fehler, ohne Zeitstempel zu
vergleichen:

```
grep <kennung> storage/logs/laravel.log
```

## Bildschirmfotos

Hängen Bilder am Fall, stehen sie unter der Meldung. Sie liegen außerhalb des
Web-Verzeichnisses und werden nur über die Anwendung ausgeliefert, hinter
derselben Rechteprüfung wie die Fallakte.

**„Erledigt" löscht sie.** Der Text der Akte und der Verlauf bleiben, die Bilder
nicht — ein Bildschirmfoto aus dieser Anwendung zeigt fast immer Namen. Die
Maske sagt vorher, wie viele es trifft.

Was am Regelweg vorbeigekommen ist, holt der Aufräumbefehl:

```
php artisan support:anhaenge-aufraeumen --probe
php artisan support:anhaenge-aufraeumen
```

Er entfernt Bilder erledigter Fälle und Dateien ohne Eintrag (abgebrochene
Uploads). Findet er nichts, tut er nichts — er kann täglich laufen.

> **Die Bilder sind nicht gesichert.** Das nächtliche Backup auf dem Server
> sichert Datenbanken, keine Dateien. Bei einer Lebensdauer bis zum Abschluss
> des Falls ist das gewollt.

## Antworten und Status

Eine Antwort setzt den Status auf **Beantwortet**; die meldende Schule sieht
sie als „neue Antwort". Fragt die Schule zurück, springt der Fall wieder auf
**Neu**.

Den Status können Sie jederzeit von Hand setzen. **Erledigt** trägt zusätzlich
den Abschlusszeitpunkt ein und nimmt den Fall aus der Ansicht „Offen".

## Aufbewahrungsfristen

Die Fallakten enthalten Freitexte, und die können Schülernamen tragen. Deshalb
räumt die Anwendung sie nach festen Fristen wieder ab — gestaffelt danach, was
am ehesten Namen enthält:

| Wann | Was fällt |
|---|---|
| beim Abschluss | die Bildschirmfotos |
| 90 Tage danach | „Ihre letzten Schritte" und „Zuletzt aufgetretener Fehler" |
| 24 Monate danach | die ganze Akte samt Nachrichten |

Die technischen Angaben und der Einrichtungsstand bleiben auch nach der ersten
Frist stehen — sie tragen keinen Personenbezug. Ein gekürzter Kontext sagt in
der Akte, dass und wann er gekürzt wurde; ein leeres Feld soll nicht mit einer
gescheiterten Erhebung zu verwechseln sein.

**Zwei Jahre**, weil der AbiPlaner im Jahresrhythmus läuft: Ein Fall aus dem
Abitur 2027 wird im Abitur 2028 gern wieder erzählt.

> **Alle Fristen zählen ab dem Abschluss. Ein offener Fall wird nie gelöscht.**
> Damit „offen lassen" nicht der bequeme Weg wird, die Frist zu umgehen, meldet
> die Liste Fälle, die länger als zwölf Monate offen stehen. Schließen Sie ab,
> was erledigt ist.

Der Lauf geschieht nachts von selbst. Von Hand:

```
php artisan support:fallakten-aufraeumen --probe
php artisan support:fallakten-aufraeumen
```

Die drei Zahlen stehen in `config/support.php` und lassen sich ändern, wenn der
Datenschutzbeauftragte andere verlangt.

## Was der Melder gesehen hat

Der Kontext wird dem Melder vor dem Absenden vollständig angezeigt. Es steht
also nichts in der Akte, was der Melder nicht kannte — das ist beim Nachfragen
gelegentlich hilfreich.
