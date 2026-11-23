// Startseite der Desktop-App: Modus wählen bzw. gespeicherten Modus starten.
const { invoke } = window.__TAURI__.core;

const zeige = (id) => {
  for (const s of ['laden', 'auswahl', 'fehler', 'aktualisierung']) {
    document.getElementById(s).hidden = s !== id;
  }
};

async function starte(modus, serverUrl = null) {
  zeige('laden');
  document.getElementById('laden-text').textContent =
    modus === 'server' ? 'Verbindung zum Schulserver …' : 'Abiplaner wird gestartet …';
  try {
    const adresse = await invoke('starten', { modus, serverUrl });
    window.location.replace(adresse);
  } catch (fehler) {
    document.getElementById('fehler-text').textContent = String(fehler);
    zeige('fehler');
  }
}

document.getElementById('einzelplatz').addEventListener('click', () => starte('einzelplatz'));
document.getElementById('server-form').addEventListener('submit', (e) => {
  e.preventDefault();
  starte('server', document.getElementById('server-url').value);
});
document.getElementById('nochmal').addEventListener('click', () => location.reload());
document.getElementById('zurueck').addEventListener('click', async () => {
  await invoke('modus_zuruecksetzen');
  zeige('auswahl');
});

// Während einer Programm-Aktualisierung zeigt das Fenster nur den Fortschritt
// (aktualisierung.rs navigiert hierher, nachdem der Webserver beendet ist).
function aktualisierungAnzeigen() {
  zeige('aktualisierung');
  const text = document.getElementById('aktualisierung-text');
  const balken = document.getElementById('aktualisierung-balken');
  const mb = (b) => (b / 1048576).toFixed(0);
  window.__TAURI__.event.listen('aktualisierung', ({ payload }) => {
    if (payload.phase === 'installieren') {
      text.textContent = 'Neue Version wird installiert …';
      balken.removeAttribute('value');
    } else if (payload.gesamt) {
      balken.value = Math.round((payload.geladen / payload.gesamt) * 100);
      text.textContent = `Neue Version wird geladen … ${mb(payload.geladen)} von ${mb(payload.gesamt)} MB`;
    } else {
      text.textContent = `Neue Version wird geladen … ${mb(payload.geladen)} MB`;
    }
  });
}

(async () => {
  if (new URLSearchParams(location.search).has('aktualisierung')) {
    aktualisierungAnzeigen();
    return;
  }
  const einstellungen = await invoke('einstellungen_lesen');
  if (einstellungen.modus) {
    starte(einstellungen.modus, einstellungen.server_url);
  } else {
    zeige('auswahl');
  }
})();
