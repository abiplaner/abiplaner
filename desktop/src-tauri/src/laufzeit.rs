//! Start und Stopp der eingebauten Laravel-Instanz.

use std::fs::{self, OpenOptions};
use std::net::{SocketAddr, TcpListener, TcpStream};
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};
use std::sync::Mutex;
use std::time::{Duration, Instant};

use base64::Engine;
use serde::{Deserialize, Serialize};
use tauri::{AppHandle, Manager};

#[derive(Default)]
pub struct Zustand {
    server: Mutex<Option<Child>>,
    /// Wird für die ganze Dauer eines Starts (Vorbereitung + Webserver)
    /// gehalten, damit eine Aktualisierung nicht mitten in eine Migration fällt.
    start: Mutex<()>,
    /// Gesetzt, sobald eine Aktualisierung läuft: danach kein neuer Start mehr.
    aktualisierung: std::sync::atomic::AtomicBool,
}

/// Was die Startseite sich merkt (einstellungen.json im Datenordner).
#[derive(Serialize, Deserialize, Default, Clone)]
pub struct Einstellungen {
    /// "einzelplatz" | "server" | None (noch nicht gewählt)
    pub modus: Option<String>,
    pub server_url: Option<String>,
}

/// Datenordner — bewusst NICHT der App-Datenordner von Tauri (benannt nach
/// dem identifier): Den bietet die Windows-Deinstallation zum Löschen an.
/// Ein falscher Haken dort wäre ein Totalverlust.
pub fn datenordner() -> PathBuf {
    if let Ok(eigener) = std::env::var("ABIPLANER_DATEN") {
        return PathBuf::from(eigener);
    }
    #[cfg(target_os = "windows")]
    let basis = dirs::data_local_dir();
    #[cfg(not(target_os = "windows"))]
    let basis = dirs::data_dir();
    basis
        .unwrap_or_else(|| PathBuf::from("."))
        .join(if cfg!(target_os = "linux") { "abiplaner-daten" } else { "Abiplaner-Daten" })
}

fn einstellungen_datei() -> PathBuf {
    datenordner().join("einstellungen.json")
}

fn lies_einstellungen() -> Einstellungen {
    fs::read_to_string(einstellungen_datei())
        .ok()
        .and_then(|text| serde_json::from_str(&text).ok())
        .unwrap_or_default()
}

fn schreib_einstellungen(e: &Einstellungen) -> Result<(), String> {
    fs::create_dir_all(datenordner()).map_err(|f| format!("Datenordner: {f}"))?;
    let text = serde_json::to_string_pretty(e).map_err(|f| f.to_string())?;
    fs::write(einstellungen_datei(), text).map_err(|f| format!("Einstellungen: {f}"))
}

#[tauri::command]
pub fn einstellungen_lesen() -> Einstellungen {
    lies_einstellungen()
}

#[tauri::command]
pub fn modus_zuruecksetzen() -> Result<(), String> {
    schreib_einstellungen(&Einstellungen::default())
}

/// Startet im gewählten Modus und liefert die Adresse, die das Fenster öffnen soll.
#[tauri::command]
pub async fn starten(
    app: AppHandle,
    modus: String,
    server_url: Option<String>,
) -> Result<String, String> {
    match modus.as_str() {
        "server" => {
            let url = server_url.unwrap_or_default().trim().trim_end_matches('/').to_string();
            if !(url.starts_with("http://") || url.starts_with("https://")) {
                return Err("Bitte die Adresse des Schulservers mit http:// oder https:// angeben.".into());
            }
            schreib_einstellungen(&Einstellungen { modus: Some(modus), server_url: Some(url.clone()) })?;
            Ok(url)
        }
        "einzelplatz" => {
            schreib_einstellungen(&Einstellungen { modus: Some(modus), server_url: None })?;
            let app2 = app.clone();
            tauri::async_runtime::spawn_blocking(move || einzelplatz_starten(&app2))
                .await
                .map_err(|f| f.to_string())?
        }
        anders => Err(format!("Unbekannter Modus: {anders}")),
    }
}

/// Vor einer Aktualisierung: einen laufenden Start abwarten, dann den
/// Webserver beenden. Die Sperre bleibt nicht bestehen – nach der Installation
/// startet die App ohnehin neu.
pub fn anhalten_fuer_aktualisierung(app: &AppHandle) {
    if let Some(zustand) = app.try_state::<Zustand>() {
        let _warten = zustand.start.lock();
        zustand.aktualisierung.store(true, std::sync::atomic::Ordering::SeqCst);
        beenden(app);
    }
}

pub fn beenden(app: &AppHandle) {
    if let Some(zustand) = app.try_state::<Zustand>() {
        if let Ok(mut server) = zustand.server.lock() {
            if let Some(mut kind) = server.take() {
                let _ = kind.kill();
                let _ = kind.wait();
            }
        }
    }
}

fn zufall_bytes<const N: usize>() -> Result<[u8; N], String> {
    let mut puffer = [0u8; N];
    getrandom::fill(&mut puffer).map_err(|f| format!("Zufallszahlen: {f}"))?;
    Ok(puffer)
}

/// Windows liefert Programmpfade teils als `\\?\C:\…`. PHP, Caddy und
/// Laravel kommen mit diesem Präfix nicht zurecht.
fn ohne_unc(pfad: PathBuf) -> PathBuf {
    let gekuerzt = pfad
        .to_string_lossy()
        .strip_prefix(r"\\?\")
        .filter(|rest| !rest.starts_with("UNC\\"))
        .map(PathBuf::from);
    gekuerzt.unwrap_or(pfad)
}

fn freier_port() -> Result<u16, String> {
    let lauscher = TcpListener::bind("127.0.0.1:0").map_err(|f| format!("Kein freier Port: {f}"))?;
    let port = lauscher.local_addr().map_err(|f| f.to_string())?.port();
    Ok(port)
}

/// APP_KEY einmal erzeugen und im Datenordner aufheben — ein neuer Schlüssel
/// machte alle 2FA-Einrichtungen und Sitzungen unbrauchbar.
fn app_schluessel(daten: &Path) -> Result<String, String> {
    let datei = daten.join("app-key");
    if let Ok(vorhanden) = fs::read_to_string(&datei) {
        let vorhanden = vorhanden.trim().to_string();
        if vorhanden.starts_with("base64:") {
            return Ok(vorhanden);
        }
    }
    let neu = format!(
        "base64:{}",
        base64::engine::general_purpose::STANDARD.encode(zufall_bytes::<32>()?)
    );
    fs::write(&datei, &neu).map_err(|f| format!("Schlüssel: {f}"))?;
    Ok(neu)
}

fn protokoll(daten: &Path, name: &str) -> Result<fs::File, String> {
    OpenOptions::new()
        .create(true)
        .append(true)
        .open(daten.join(name))
        .map_err(|f| format!("Protokoll {name}: {f}"))
}

fn kommando(programm: &Path) -> Command {
    #[allow(unused_mut)]
    let mut befehl = Command::new(programm);
    #[cfg(target_os = "windows")]
    {
        use std::os::windows::process::CommandExt;
        const CREATE_NO_WINDOW: u32 = 0x0800_0000;
        befehl.creation_flags(CREATE_NO_WINDOW);
    }
    befehl
}

fn einzelplatz_starten(app: &AppHandle) -> Result<String, String> {
    let zustand = app.try_state::<Zustand>().ok_or("Zustand fehlt")?;
    let _start = zustand.start.lock().map_err(|_| "Zustand gesperrt".to_string())?;
    if zustand.aktualisierung.load(std::sync::atomic::Ordering::SeqCst) {
        return Err("Abiplaner wird gerade aktualisiert und startet gleich neu.".into());
    }

    // Läuft schon (z. B. Seite neu geladen)? Dann nicht zweimal starten.
    beenden(app);

    let ressourcen = ohne_unc(app.path().resource_dir().map_err(|f| format!("Programmordner: {f}"))?);
    let anwendung = ressourcen.join("app");
    let laufzeit = ressourcen.join("laufzeit");
    let php = laufzeit.join(if cfg!(target_os = "windows") { "frankenphp.exe" } else { "frankenphp" });
    if !php.exists() {
        return Err(format!("PHP-Laufzeit fehlt: {}", php.display()));
    }

    let daten = datenordner();
    for unter in ["storage/app", "storage/logs", "storage/framework/cache", "storage/framework/sessions", "storage/framework/views", "caddy"] {
        fs::create_dir_all(daten.join(unter)).map_err(|f| format!("Datenordner: {f}"))?;
    }

    let port = freier_port()?;
    let token: String = zufall_bytes::<24>()?.iter().map(|b| format!("{b:02x}")).collect();
    let schluessel = app_schluessel(&daten)?;
    let pfad = |p: &Path| p.to_string_lossy().to_string();

    let umgebung: Vec<(&str, String)> = vec![
        ("APP_ENV", "production".into()),
        ("APP_DEBUG", "false".into()),
        ("APP_KEY", schluessel),
        ("APP_URL", format!("http://127.0.0.1:{port}")),
        ("ABIPLANER_APP", pfad(&anwendung)),
        ("ABIPLANER_PORT", port.to_string()),
        ("ABIPLANER_DATENBANK", pfad(&daten.join("abiplaner.sqlite"))),
        ("ABIPLANER_DESKTOP_TOKEN", token.clone()),
        ("LARAVEL_STORAGE_PATH", pfad(&daten.join("storage"))),
        ("LOG_CHANNEL", "daily".into()),
        ("LOG_LEVEL", "warning".into()),
        ("SESSION_DRIVER", "database".into()),
        ("CACHE_STORE", "file".into()),
        ("QUEUE_CONNECTION", "sync".into()),
        ("MAIL_MAILER", "log".into()),
        ("XDG_CONFIG_HOME", pfad(&daten.join("caddy"))),
        ("XDG_DATA_HOME", pfad(&daten.join("caddy"))),
        ("PHPRC", pfad(&laufzeit)),
    ];

    // 1. Datenbank vorbereiten (sichert vor Updates, migriert, Stammdaten).
    let status = kommando(&php)
        .arg("php-cli")
        .arg("artisan")
        .arg("abiplaner:vorbereiten")
        .arg("--no-interaction")
        .current_dir(&anwendung)
        .envs(umgebung.iter().map(|(k, v)| (*k, v.as_str())))
        .stdin(Stdio::null())
        .stdout(protokoll(&daten, "start-protokoll.txt")?)
        .stderr(protokoll(&daten, "start-protokoll.txt")?)
        .status()
        .map_err(|f| format!("Vorbereitung nicht gestartet: {f}"))?;
    if !status.success() {
        return Err(format!(
            "Die Datenbank konnte nicht vorbereitet werden. Einzelheiten: {}",
            daten.join("start-protokoll.txt").display()
        ));
    }

    // 2. Webserver nur auf 127.0.0.1 starten.
    let kind = kommando(&php)
        .arg("run")
        .arg("--config")
        .arg(laufzeit.join("Caddyfile"))
        .current_dir(&anwendung)
        .envs(umgebung.iter().map(|(k, v)| (*k, v.as_str())))
        .stdin(Stdio::null())
        .stdout(protokoll(&daten, "webserver-protokoll.txt")?)
        .stderr(protokoll(&daten, "webserver-protokoll.txt")?)
        .spawn()
        .map_err(|f| format!("Webserver nicht gestartet: {f}"))?;

    if let Some(zustand) = app.try_state::<Zustand>() {
        *zustand.server.lock().map_err(|_| "Zustand gesperrt".to_string())? = Some(kind);
    }

    // 3. Warten, bis er antwortet.
    let adresse: SocketAddr = ([127, 0, 0, 1], port).into();
    let beginn = Instant::now();
    while TcpStream::connect_timeout(&adresse, Duration::from_millis(200)).is_err() {
        if beginn.elapsed() > Duration::from_secs(30) {
            beenden(app);
            return Err(format!(
                "Der Webserver antwortet nicht. Einzelheiten: {}",
                daten.join("webserver-protokoll.txt").display()
            ));
        }
        std::thread::sleep(Duration::from_millis(150));
    }

    Ok(format!("http://127.0.0.1:{port}/desktop/start?token={token}"))
}
