// Abiplaner Desktop — Tauri-Hülle um die Laravel-Anwendung.
//
// Ablauf: Die eingebaute Startseite (src/index.html) fragt die Einstellungen
// ab und ruft `starten` auf. Im Modus „einzelplatz“ startet die App die
// mitgelieferte PHP-Laufzeit (FrankenPHP) auf 127.0.0.1, im Modus „server“
// öffnet sie den Schulserver (Docker). Beim Beenden stoppt sie den Webserver.
// Parallel sucht sie nach einer neuen Programmversion (aktualisierung.rs).
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

mod aktualisierung;
mod laufzeit;

fn main() {
    let app = tauri::Builder::default()
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_updater::Builder::new().build())
        .manage(laufzeit::Zustand::default())
        .invoke_handler(tauri::generate_handler![
            laufzeit::einstellungen_lesen,
            laufzeit::starten,
            laufzeit::modus_zuruecksetzen,
        ])
        .setup(|app| {
            #[cfg(target_os = "macos")]
            menue_mit_aktualisierung(app)?;
            aktualisierung::beim_start(app.handle());
            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("Abiplaner konnte nicht gestartet werden");

    app.run(|handle, ereignis| {
        if let tauri::RunEvent::Exit = ereignis {
            laufzeit::beenden(handle);
        }
    });
}

/// macOS: Standardmenü plus „Nach Updates suchen …“ im Programm-Menü.
/// (Windows und Linux haben kein Programm-Menü; dort prüft die App bei jedem Start.)
#[cfg(target_os = "macos")]
fn menue_mit_aktualisierung(app: &mut tauri::App) -> tauri::Result<()> {
    use tauri::menu::{Menu, MenuItem, PredefinedMenuItem};

    let menue = Menu::default(app.handle())?;
    let eintrag = MenuItem::with_id(app, "nach-updates-suchen", "Nach Updates suchen …", true, None::<&str>)?;
    // Das erste Untermenü ist auf dem Mac das Programm-Menü („Abiplaner“).
    if let Some(programm) = menue.items()?.into_iter().next().and_then(|e| e.as_submenu().cloned()) {
        programm.insert(&eintrag, 1)?;
        programm.insert(&PredefinedMenuItem::separator(app)?, 2)?;
    }
    app.set_menu(menue)?;
    app.on_menu_event(|app, ereignis| {
        if ereignis.id().as_ref() == "nach-updates-suchen" {
            aktualisierung::auf_wunsch(app);
        }
    });
    Ok(())
}
