//! Programm-Aktualisierung über tauri-plugin-updater.
//!
//! Beim Start fragt die App im Hintergrund `latest.json` ab (Adresse in
//! tauri.conf.json > plugins.updater.endpoints). Gibt es eine neuere Version,
//! fragt ein Dialog nach – installiert wird nur auf Klick. Vor der Installation
//! wird der PHP-Webserver beendet (unter Windows hielte er sonst Dateien im
//! Programmordner offen). Die Daten liegen außerhalb des Programmordners;
//! `abiplaner:vorbereiten` sichert die Datenbank beim ersten Start der neuen
//! Version, bevor es migriert.

use serde::Serialize;
use tauri::{AppHandle, Emitter, Manager, Url};
use tauri_plugin_dialog::{DialogExt, MessageDialogButtons, MessageDialogKind};
use tauri_plugin_updater::UpdaterExt;

use crate::laufzeit;

#[derive(Clone, Serialize)]
struct Fortschritt {
    geladen: u64,
    gesamt: Option<u64>,
    phase: &'static str,
}

/// Beim Programmstart: still prüfen, nur bei einer neuen Version nachfragen.
pub fn beim_start(app: &AppHandle) {
    if cfg!(debug_assertions) || std::env::var_os("ABIPLANER_OHNE_UPDATES").is_some() {
        return;
    }
    let app = app.clone();
    tauri::async_runtime::spawn(async move {
        if let Err(fehler) = pruefen(&app, false).await {
            // Kein Netz, Server nicht erreichbar … beim Start kein Grund für einen Dialog.
            eprintln!("Update-Prüfung: {fehler}");
        }
    });
}

/// Über das Menü: auch „keine neue Version“ und Fehler melden.
pub fn auf_wunsch(app: &AppHandle) {
    let app = app.clone();
    tauri::async_runtime::spawn(async move {
        if let Err(fehler) = pruefen(&app, true).await {
            meldung(&app, "Aktualisierung", &format!("Die Suche nach Aktualisierungen ist fehlgeschlagen:\n\n{fehler}"), true).await;
        }
    });
}

async fn meldung(app: &AppHandle, titel: &str, text: &str, fehler: bool) {
    let app = app.clone();
    let (titel, text) = (titel.to_string(), text.to_string());
    let _ = tauri::async_runtime::spawn_blocking(move || {
        app.dialog()
            .message(text)
            .title(titel)
            .kind(if fehler { MessageDialogKind::Error } else { MessageDialogKind::Info })
            .blocking_show()
    })
    .await;
}

async fn pruefen(app: &AppHandle, meldung_wenn_aktuell: bool) -> Result<(), String> {
    let update = app
        .updater()
        .map_err(|f| f.to_string())?
        .check()
        .await
        .map_err(|f| f.to_string())?;

    let Some(update) = update else {
        if meldung_wenn_aktuell {
            let text = format!("Abiplaner {} ist die neueste Version.", app.package_info().version);
            meldung(app, "Keine Aktualisierung", &text, false).await;
        }
        return Ok(());
    };

    let hinweise = update
        .body
        .as_deref()
        .map(str::trim)
        .filter(|t| !t.is_empty())
        .map(|t| format!("\n\nNeu in dieser Version:\n{t}"))
        .unwrap_or_default();
    let text = format!(
        "Abiplaner {neu} ist verfügbar (installiert: {alt}).{hinweise}\n\n\
         Abiplaner wird dafür kurz beendet und startet danach neu. \
         Ihre Daten bleiben erhalten und werden vor dem ersten Start der neuen Version gesichert.",
        neu = update.version,
        alt = update.current_version,
    );

    let app2 = app.clone();
    let jetzt = tauri::async_runtime::spawn_blocking(move || {
        app2.dialog()
            .message(text)
            .title("Aktualisierung verfügbar")
            .kind(MessageDialogKind::Info)
            .buttons(MessageDialogButtons::OkCancelCustom(
                "Jetzt aktualisieren".into(),
                "Später".into(),
            ))
            .blocking_show()
    })
    .await
    .map_err(|f| f.to_string())?;
    if !jetzt {
        return Ok(());
    }

    // Nicht mitten in `abiplaner:vorbereiten` (Migration/Sicherung) eingreifen:
    // warten, bis ein laufender Start fertig ist, dann den Webserver beenden.
    let app2 = app.clone();
    tauri::async_runtime::spawn_blocking(move || laufzeit::anhalten_fuer_aktualisierung(&app2))
        .await
        .map_err(|f| f.to_string())?;
    fortschritt_seite_zeigen(app);

    let app_laden = app.clone();
    let app_fertig = app.clone();
    let mut geladen: u64 = 0;
    let mut gemeldet: u64 = 0;
    let ergebnis = update
        .download_and_install(
            move |stueck, gesamt| {
                geladen += stueck as u64;
                // Höchstens etwa ein Ereignis pro MB an die Oberfläche.
                if geladen - gemeldet >= 1_048_576 || Some(geladen) == gesamt {
                    gemeldet = geladen;
                    let _ = app_laden.emit("aktualisierung", Fortschritt { geladen, gesamt, phase: "laden" });
                }
            },
            move || {
                let _ = app_fertig.emit("aktualisierung", Fortschritt { geladen: 0, gesamt: None, phase: "installieren" });
            },
        )
        .await;

    match ergebnis {
        Ok(()) => app.restart(),
        Err(fehler) => {
            meldung(
                app,
                "Aktualisierung fehlgeschlagen",
                &format!(
                    "Die neue Version konnte nicht installiert werden. Abiplaner startet in der bisherigen Version neu.\n\n{fehler}"
                ),
                true,
            )
            .await;
            app.restart()
        }
    }
}

/// Die Laravel-Seite ist nach dem Beenden des Webservers tot – das Fenster
/// zeigt stattdessen die eingebaute Startseite im Modus „Aktualisierung“.
fn fortschritt_seite_zeigen(app: &AppHandle) {
    let Some(fenster) = app.get_webview_window("main") else { return };
    let basis = if cfg!(target_os = "windows") { "http://tauri.localhost" } else { "tauri://localhost" };
    if let Ok(url) = Url::parse(&format!("{basis}/index.html?aktualisierung")) {
        let _ = fenster.navigate(url);
    }
    // Der Seite einen Moment geben, damit sie die ersten Ereignisse empfängt.
    std::thread::sleep(std::time::Duration::from_millis(400));
}
