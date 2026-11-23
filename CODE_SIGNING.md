# Code signing policy

Free code signing provided by [SignPath.io](https://signpath.io), certificate by [SignPath Foundation](https://signpath.org).

*Deutsche Fassung weiter unten.*

## What is signed

| Platform | File | Signature |
|---|---|---|
| Windows | `Abiplaner_<version>_x64-setup.exe` | Authenticode via SignPath Foundation (see above) |
| macOS | `Abiplaner_<version>_aarch64.dmg` | Apple Developer ID *Wilfried Trinkl (3LJ9TS45R3)*, notarized by Apple |
| all | update packages (`*.sig`, `latest.json`) | Tauri updater key (minisign) |

All release files are built exclusively by GitHub Actions on GitHub-hosted runners from the tagged
source code of this repository ([`.github/workflows/release.yml`](.github/workflows/release.yml)).
Nothing that is signed is built on a developer machine. Releases are published at
<https://github.com/abiplaner/abiplaner/releases>.

Third-party components shipped inside the package are included as published upstream and are **not**
signed with the project certificate: the PHP runtime [FrankenPHP](https://frankenphp.dev) (including
PHP and its extensions) and, on Windows, the Microsoft Visual C++ runtime DLLs.

## Team roles

| Role | Members |
|---|---|
| Authors (committers) | [Wilfried Trinkl (@wilfriedtrinkl)](https://github.com/wilfriedtrinkl) |
| Reviewers | [Wilfried Trinkl (@wilfriedtrinkl)](https://github.com/wilfriedtrinkl) |
| Approvers | [Wilfried Trinkl (@wilfriedtrinkl)](https://github.com/wilfriedtrinkl) |

Members of the GitHub organization: <https://github.com/orgs/abiplaner/people>.
All team members use multi-factor authentication (hardware security keys) for GitHub and SignPath.

Changes by anyone outside the team are only accepted as pull requests and are reviewed by a reviewer
before they are merged. Every signing request is approved manually by an approver in SignPath after
checking that it belongs to a tagged release of this repository.

## Privacy

This program will not transfer any information to other networked systems unless specifically
requested by the user or the person installing or operating it.

- **Update check:** At start the program asks
  `https://abiplaner-bayern.de/downloads/desktop/latest.json` (forwarded to GitHub) whether a new
  version exists. Only the usual technical connection data (such as the IP address) is transmitted.
  An update is downloaded from GitHub only after the user clicks “Jetzt aktualisieren”.
  The check can be switched off with the environment variable `ABIPLANER_OHNE_UPDATES=1`.
- **Single-user mode:** All data stays in a database file in the user's profile on the local computer.
- **School-server mode:** Data is exchanged only with the school server whose address the user
  enters.
- There is no telemetry, analytics or advertising.

Privacy policies: [abiplaner-bayern.de](https://abiplaner-bayern.de/privacy) ·
[GitHub General Privacy Statement](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement)

---

# Richtlinie zur Code-Signatur (deutsch)

Kostenlose Code-Signatur durch [SignPath.io](https://signpath.io), Zertifikat der
[SignPath Foundation](https://signpath.org).

**Was signiert wird:** der Windows-Installer (Authenticode über die SignPath Foundation), die
macOS-DMG (Apple Developer ID *Wilfried Trinkl*, von Apple notarisiert) und alle Update-Pakete
(Tauri-Update-Schlüssel). Alle Dateien eines Releases entstehen ausschließlich in GitHub Actions aus
dem getaggten Quelltext dieses Repositorys. Mitgelieferte Fremdkomponenten (FrankenPHP/PHP,
Visual-C++-Laufzeit) werden unverändert übernommen und nicht mit dem Projektzertifikat signiert.

**Rollen:** Autor, Prüfer und Freigeber ist Wilfried Trinkl
([@wilfriedtrinkl](https://github.com/wilfriedtrinkl)). Alle Beteiligten melden sich bei GitHub und
SignPath mit Hardware-Sicherheitsschlüsseln an. Beiträge Dritter werden nur als Pull Request
angenommen und vor dem Zusammenführen geprüft. Jeder Signierauftrag wird in SignPath von Hand
freigegeben.

**Datenschutz:** Das Programm überträgt keine Informationen an andere Systeme, außer wenn Nutzer
oder Betreiber das ausdrücklich veranlassen. Beim Start fragt es nach einer neuen Version
(abiplaner-bayern.de, weitergeleitet an GitHub); dabei gehen nur die technisch üblichen
Verbindungsdaten (etwa die IP-Adresse) hinaus. Ein Update wird erst nach Klick auf „Jetzt aktualisieren“
geladen; die Prüfung lässt sich mit `ABIPLANER_OHNE_UPDATES=1` abschalten. Im Einzelplatzbetrieb
bleiben alle Daten im Benutzerprofil, im Schulserver-Betrieb werden sie nur mit dem eingetragenen
Schulserver ausgetauscht. Es gibt keine Telemetrie, Analyse oder Werbung.
[Datenschutzerklärung](https://abiplaner-bayern.de/privacy) ·
[Datenschutzerklärung von GitHub](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement)
