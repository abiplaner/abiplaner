# AbiPlaner

Planung der Abiturprüfungen an bayerischen Gymnasien: mündliche Prüfungen und Kolloquium,
schriftliche Prüfungen mit Räumen, Sitzplänen und Aufsichten, Zusatzprüfungen, externe Bewerber
(§ 61 GSO), Leistungskurs- und W-Seminar-Wahlen, Klausurenplan und Schultermine – mit
Druckvorlagen für den Prüfungstag.

Entstanden in der Oberstufenkoordination eines bayerischen Gymnasiums.
Homepage: **[abiplaner-bayern.de](https://abiplaner-bayern.de)**

## Download

Die Desktop-App gibt es für **Windows**, **macOS** (Apple Silicon) und **Linux**:
**[Neueste Version herunterladen](https://github.com/abiplaner/abiplaner/releases/latest)** (unter „Assets“).

| System | Datei |
|---|---|
| Windows 10/11 | `Abiplaner_<version>_x64-setup.exe` – installiert pro Benutzer, ohne Adminrechte |
| macOS | `Abiplaner_<version>_aarch64.dmg` – signiert und von Apple notarisiert |
| Linux | `Abiplaner_<version>_amd64.AppImage` |

Die App braucht keinen Browser und keine Internetverbindung. Beim ersten Start wählt man:

- **Auf diesem Rechner** – alle Daten in einer SQLite-Datei im Benutzerprofil
  (macOS `~/Library/Application Support/Abiplaner-Daten`, Windows `%LOCALAPPDATA%\Abiplaner-Daten`,
  Linux `~/.local/share/abiplaner-daten`). Ein Assistent legt die Schule an; das erste Konto wird Super-Admin.
- **Mit dem Schulserver verbinden** – mehrere Lehrkräfte arbeiten gemeinsam (siehe unten).

Neue Versionen bietet die App beim Start an; installiert wird nur auf Klick, und vor dem ersten Start
der neuen Version sichert sie die Datenbank.

## Schulserver (mehrere Benutzer)

Ein einzelner Docker-Container mit FrankenPHP und SQLite, Daten im Volume `abiplaner-daten`:

```bash
git clone https://github.com/abiplaner/abiplaner.git && cd abiplaner
docker compose -f docker/schule/compose.yaml up -d --build
# Update
git pull && docker compose -f docker/schule/compose.yaml up -d --build
```

Erreichbar unter `http://<server>:8080`. Der Container sichert die Datenbank jede Nacht und vor
jeder Migration (`/data/sicherungen`). Außerhalb eines geschützten Schulnetzes gehört ein
HTTPS-Proxy davor; für Traefik gibt es [`docker/schule/compose.traefik.yaml`](docker/schule/compose.traefik.yaml).

## Aufbau des Repositorys

| Pfad | Inhalt |
|---|---|
| `app/`, `resources/`, `routes/`, `database/` | Laravel-Anwendung (eine Schule, SQLite) |
| `desktop/` | Desktop-App: Tauri-Hülle, Bauskripte `bauen.sh` (macOS/Linux) und `bauen.ps1` (Windows) |
| `docker/schule/` | Schulserver-Image und Compose-Dateien |
| `website/` | Homepage abiplaner-bayern.de (statisches HTML + nginx) |
| `deploy.sh` | Ausrollen auf dem Server von abiplaner-bayern.de (Homepage + Schulserver hinter Traefik) |
| `database/uebernahme/` | Einmalige Übernahme aus der früheren MariaDB-Mehrschul-Fassung |
| `.github/workflows/` | Tests (`ci.yml`) und Release der Desktop-App (`release.yml`) |

## Entwicklung

```bash
docker compose up -d                          # App (PHP-FPM) + nginx, http://localhost
docker compose exec abiplaner_app php artisan test
npm ci --include=dev && npm run dev           # Oberfläche mit Vite
desktop/bauen.sh --unsigniert                 # Desktop-App für das eigene System bauen
```

Die Datenbank ist eine SQLite-Datei (`ABIPLANER_DATENBANK`). Eine MariaDB gibt es nur noch unter dem
Compose-Profil `mariadb`, um alte Bestände zu übernehmen.

## Neue Version veröffentlichen

1. Versionsnummer in `desktop/src-tauri/tauri.conf.json` **und** `desktop/src-tauri/Cargo.toml` erhöhen, committen.
2. `git tag -a v1.2.3 -m "Was ist neu …"` und `git push origin v1.2.3` – der Text der Tag-Nachricht erscheint
   im Update-Dialog der App.
3. GitHub Actions baut Windows, macOS (signiert, notarisiert) und Linux und legt einen **Release-Entwurf** an.
4. Entwurf prüfen und „Publish release“ klicken – erst dann sehen die installierten Apps die neue Version
   (über `https://abiplaner-bayern.de/downloads/desktop/latest.json`).

## Code signing policy

Free code signing provided by [SignPath.io](https://signpath.io), certificate by [SignPath Foundation](https://signpath.org).

Was signiert wird, wer im Team welche Rolle hat und welche Daten das Programm überträgt:
[**CODE_SIGNING.md**](CODE_SIGNING.md).

## ⚖️ Lizenz

Copyright © 2025–2026 Wilfried Trinkl

AbiPlaner ist freie Software: Sie können sie unter den Bedingungen der
**GNU Affero General Public License** in Version 3 oder (nach Ihrer Wahl)
jeder späteren Version weitergeben und/oder verändern, wie sie von der
Free Software Foundation veröffentlicht wurde. Den vollständigen Lizenztext
enthält die Datei [`LICENSE`](LICENSE).

Wer eine veränderte Fassung als Webanwendung für andere betreibt, muss deren
Nutzerinnen und Nutzern den Quelltext dieser Fassung zugänglich machen
(§ 13 AGPL).

Die Software wird ohne jede Gewährleistung bereitgestellt. Sie ersetzt nicht
die Prüfung der Ergebnisse anhand der GSO und der Vorgaben des Staatsministeriums.
