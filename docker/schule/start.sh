#!/bin/sh
# Start des Schulservers: Datenordner vorbereiten, Schlüssel sichern,
# Datenbank vorbereiten (sichert vor Updates), Scheduler und Webserver starten.
set -eu

DATEN=/data
mkdir -p "$DATEN/storage/app" "$DATEN/storage/logs" \
         "$DATEN/storage/framework/cache" "$DATEN/storage/framework/sessions" \
         "$DATEN/storage/framework/views"

# APP_KEY: verschlüsselt Sitzungen und 2FA-Geheimnisse. Ohne Vorgabe einmal
# erzeugen und im Volume ablegen — ein neuer Schlüssel nach einem Update
# würde alle 2FA-Einrichtungen unbrauchbar machen.
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$DATEN/app-key" ]; then
        umask 077
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$DATEN/app-key"
    fi
    APP_KEY="$(cat "$DATEN/app-key")"
    export APP_KEY
fi

cd /app
php artisan abiplaner:vorbereiten --no-interaction
php artisan optimize --no-interaction >/dev/null

# Scheduler (nächtliche Sicherung 02:00 u. a.) — einfache Schleife, kein cron
# im Container nötig. Stirbt der Webserver, beendet Docker den Container samt
# Schleife.
(
    while true; do
        php artisan schedule:run --no-interaction >/dev/null 2>&1 || true
        sleep 60
    done
) &

exec frankenphp run --config /etc/frankenphp/Caddyfile
