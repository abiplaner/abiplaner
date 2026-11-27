---
titel: Eigenes Konto und Zwei-Faktor
gruppe: Weitere Aufgaben
reihenfolge: 660
routen: profile, two-factor
stichworte: profil, passwort, zwei-faktor, 2fa, konto, anmeldung, gerät
---

Unter *Profil* liegen vier Blöcke: Profilinformationen, Passwort ändern,
Zwei-Faktor-Authentifizierung und Konto löschen.

## Profil

Name und E-Mail-Adresse. **Ändern Sie die E-Mail-Adresse, muss sie neu bestätigt
werden** — Sie bekommen eine Nachricht an die neue Adresse.

## Zwei-Faktor-Authentifizierung

Freiwillig, aber empfohlen: Nach dem Passwort verlangt die Anmeldung dann
zusätzlich einen sechsstelligen Code aus einer Authenticator-App.

Zum Einrichten scannen Sie den QR-Code mit Ihrer App und geben einen gültigen
Code ein. Erst damit ist die Zwei-Faktor-Anmeldung aktiv.

Geräte, an denen Sie sich bestätigt haben, gelten als vertrauenswürdig und
werden gezählt. **„Alle Geräte abmelden"** erzwingt beim nächsten Anmelden
überall wieder einen Code — der richtige Griff, wenn ein Gerät verloren ging.

> **Es gibt keine Wiederherstellungscodes.** Verlieren Sie den Zugang zu Ihrer
> Authenticator-App, setzt ein Schuladmin die Zwei-Faktor-Anmeldung zurück, und
> Sie richten sie neu ein. In der Desktop-App geht das auch über „Passwort
> vergessen?“ auf der Anmeldeseite.

## Passwort vergessen

- **Desktop-App:** Auf der Anmeldeseite „Passwort vergessen?“ wählen, das Konto
  aussuchen und ein neues Passwort vergeben. Das geht nur in der App auf dem
  Rechner, auf dem die Daten liegen.
- **Schulserver:** „Passwort vergessen?“ schickt einen Link an Ihre
  E-Mail-Adresse, sofern der Server E-Mails verschicken kann. Sonst setzt ein
  Schuladmin das Passwort unter *Verwaltung → Benutzer* neu. Wer den Server
  betreut, kann außerdem `php artisan abiplaner:passwort <E-Mail>` ausführen.

## Konto löschen

Verlangt Ihr aktuelles Passwort, meldet Sie ab und löscht das Konto sofort.
Der letzte Schuladmin kann sein Konto nicht löschen.

## Rollen

Es gibt zwei: **Planer** — alle Planungsfunktionen — und **Schuladmin** —
zusätzlich das Menü *Verwaltung* (Benutzer, Fächer, Termine, Sicherungen).
Den ersten Schuladmin legt die Einrichtung an; weitere Konten legen
Schuladmins an. Sie können Ihre eigene Rolle nicht ändern.
