#!/usr/bin/env bash
# Baut die Desktop-App für das System, auf dem das Skript läuft
# (macOS arm64 oder Linux). Windows: desktop/bauen.ps1 auf einem Windows-Rechner.
#
#   desktop/bauen.sh               # Bundle unter desktop/src-tauri/target/release/bundle
#   desktop/bauen.sh --nur-app     # nur build/app + build/laufzeit (für `tauri dev`)
#   desktop/bauen.sh --unsigniert  # macOS: ohne Signatur und Notarisierung
#
# Braucht: php + composer, npm, Rust (rustup), git.
#
# macOS-Signatur: Ist im Schlüsselbund eine „Developer ID Application“-Identität
# (oder APPLE_SIGNING_IDENTITY gesetzt), wird signiert. Notarisiert wird, wenn
# APPLE_API_KEY/APPLE_API_ISSUER/APPLE_API_KEY_PATH gesetzt sind – sonst aus
# ~/.config/abiplaner/notarisierung.env gelesen.
set -euo pipefail

UNSIGNIERT=0
NUR_APP=0
for arg in "$@"; do
    case "$arg" in
        --unsigniert) UNSIGNIERT=1 ;;
        --nur-app)    NUR_APP=1 ;;
        *) echo "Unbekannte Option: $arg" >&2; exit 1 ;;
    esac
done

DESKTOP="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(dirname "$DESKTOP")"
BUILD="$DESKTOP/build"
FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-v1.13.5}"
CACHE="$DESKTOP/.cache/$FRANKENPHP_VERSION"
schritt() { printf '\n== %s\n' "$*"; }

[ -f "$HOME/.cargo/env" ] && . "$HOME/.cargo/env"

schritt "Anwendung zusammenstellen"
rm -rf "$BUILD/app" "$BUILD/laufzeit"
mkdir -p "$BUILD/app" "$BUILD/laufzeit" "$CACHE"
# Eigene Liste statt .dockerignore: rsync liest „!“ als „Liste leeren“.
rsync -a --delete \
    --include=/database/schema/basis.sql \
    --exclude='/.*' --exclude=/desktop --exclude=/docker --exclude=/tests --exclude=/docs \
    --exclude=/node_modules --exclude=/vendor --exclude=/storage --exclude=/backups \
    --exclude=/dist --exclude=/public/build --exclude=/public/hot --exclude=/bootstrap/cache/*.php \
    --exclude='/database/*.sqlite*' --exclude='*.sql' --exclude='/*.md' \
    --exclude='/docker-compose*.yml' --exclude=/deploy.sh --exclude=/install.sh \
    --exclude=/backup.sh --exclude=/Makefile --exclude=/phpunit.xml --exclude='/phpstan*' \
    "$ROOT/" "$BUILD/app/"
mkdir -p "$BUILD/app/storage"   # Laravel erwartet den Ordner; benutzt wird LARAVEL_STORAGE_PATH

( cd "$BUILD/app" \
  && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet \
  && sh "$ROOT/docker/build-tcpdf-fonts.sh" )

schritt "Oberfläche bauen"
( cd "$ROOT" && npm run build --silent )
rsync -a --delete "$ROOT/public/build/" "$BUILD/app/public/build/"
rm -f "$BUILD/app/bootstrap/cache/"*.php
git -C "$ROOT" describe --tags --always --dirty > "$BUILD/app/VERSION"

schritt "PHP-Laufzeit (FrankenPHP $FRANKENPHP_VERSION)"
case "$(uname -s)-$(uname -m)" in
    Darwin-arm64)  datei=frankenphp-mac-arm64 ;;
    Linux-x86_64)  datei=frankenphp-linux-x86_64 ;;
    Linux-aarch64) datei=frankenphp-linux-aarch64 ;;
    *) echo "Nicht unterstützt: $(uname -s)-$(uname -m) (Intel-Macs werden nicht unterstützt)" >&2; exit 1 ;;
esac
if [ ! -s "$CACHE/$datei" ]; then
    curl -fsSL -o "$CACHE/$datei.part" \
        "https://github.com/php/frankenphp/releases/download/$FRANKENPHP_VERSION/$datei"
    mv "$CACHE/$datei.part" "$CACHE/$datei"
fi
cp "$CACHE/$datei" "$BUILD/laufzeit/frankenphp"
chmod +x "$BUILD/laufzeit/frankenphp"
cp "$DESKTOP/laufzeit/Caddyfile" "$BUILD/laufzeit/Caddyfile"
echo "$FRANKENPHP_VERSION" > "$BUILD/laufzeit/VERSION"

du -sh "$BUILD/app" "$BUILD/laufzeit"
[ "$NUR_APP" = 1 ] && exit 0

# --- macOS: Signatur und Notarisierung vorbereiten --------------------------
NOTARISIEREN=0
if [ "$(uname -s)" = Darwin ] && [ "$UNSIGNIERT" = 0 ]; then
    if [ -z "${APPLE_SIGNING_IDENTITY:-}" ]; then
        APPLE_SIGNING_IDENTITY="$(security find-identity -v -p codesigning \
            | sed -n 's/.*"\(Developer ID Application: [^"]*\)".*/\1/p' | head -1)"
    fi
    if [ -z "$APPLE_SIGNING_IDENTITY" ]; then
        echo "Keine „Developer ID Application“-Identität gefunden – App bleibt unsigniert." >&2
        echo "(Ohne Signatur: desktop/bauen.sh --unsigniert)" >&2
        exit 1
    fi
    export APPLE_SIGNING_IDENTITY

    if [ -z "${APPLE_API_KEY:-}" ] && [ -f "$HOME/.config/abiplaner/notarisierung.env" ]; then
        set -a; . "$HOME/.config/abiplaner/notarisierung.env"; set +a
    fi
    if [ -n "${APPLE_API_KEY:-}" ] && [ -n "${APPLE_API_ISSUER:-}" ] && [ -f "${APPLE_API_KEY_PATH:-}" ]; then
        NOTARISIEREN=1
    else
        # Ohne diese Variablen signiert Tauri nur und überspringt die Notarisierung.
        unset APPLE_API_KEY APPLE_API_ISSUER APPLE_API_KEY_PATH
        echo "Hinweis: keine Notarisierungs-Zugangsdaten – App wird nur signiert." >&2
    fi

    # Die Laufzeit liegt als Ressource im Bundle; Tauri signiert nur das
    # Hauptprogramm. Jedes Mach-O im Bundle muss für die Notarisierung aber
    # selbst mit Hardened Runtime und Zeitstempel signiert sein.
    schritt "PHP-Laufzeit signieren ($APPLE_SIGNING_IDENTITY)"
    codesign --force --options runtime --timestamp \
        --entitlements "$DESKTOP/laufzeit/frankenphp.entitlements" \
        -s "$APPLE_SIGNING_IDENTITY" "$BUILD/laufzeit/frankenphp"
    codesign --verify --strict "$BUILD/laufzeit/frankenphp"
fi

# --- Update-Signatur (tauri-plugin-updater) ---------------------------------
# Mit createUpdaterArtifacts erzeugt Tauri neben dem Paket ein .sig. Dafür
# braucht es den privaten Update-Schlüssel – in GitHub Actions aus den Secrets,
# lokal aus ~/.config/abiplaner (Passwort im macOS-Schlüsselbund). Ohne
# Schlüssel wird ohne Update-Artefakte gebaut (reicht für Tests).
TAURI_EXTRA=()
if [ -z "${TAURI_SIGNING_PRIVATE_KEY:-}" ] && [ -f "$HOME/.config/abiplaner/tauri-update.key" ]; then
    TAURI_SIGNING_PRIVATE_KEY="$(cat "$HOME/.config/abiplaner/tauri-update.key")"
    if [ -z "${TAURI_SIGNING_PRIVATE_KEY_PASSWORD:-}" ] && command -v security >/dev/null; then
        TAURI_SIGNING_PRIVATE_KEY_PASSWORD="$(security find-generic-password -a abiplaner -s abiplaner-tauri-update-key -w 2>/dev/null || true)"
    fi
    export TAURI_SIGNING_PRIVATE_KEY TAURI_SIGNING_PRIVATE_KEY_PASSWORD
fi
if [ -z "${TAURI_SIGNING_PRIVATE_KEY:-}" ]; then
    echo "Hinweis: kein Update-Schlüssel – Pakete ohne Update-Signatur." >&2
    TAURI_EXTRA=(--config '{"bundle":{"createUpdaterArtifacts":false}}')
fi

schritt "Desktop-App bauen"
cd "$DESKTOP"
[ -d node_modules/@tauri-apps ] || npm install --include=dev --no-audit --no-fund --silent
BUNDLE="$DESKTOP/src-tauri/target/release/bundle"
if [ "$(uname -s)" = Darwin ]; then
    # Tauris eigene DMG-Erzeugung richtet das Fenster per Finder/AppleScript ein
    # und scheitert bei großen Abbildern sporadisch an „Ressource ist belegt“
    # beim Aushängen. Deshalb nur die .app von Tauri, die DMG bauen wir selbst.
    npx tauri build --bundles app ${TAURI_EXTRA[@]+"${TAURI_EXTRA[@]}"}
    # Die gebaute App nicht in Launchpad/Spotlight als zweite Installation führen.
    /System/Library/Frameworks/CoreServices.framework/Frameworks/LaunchServices.framework/Support/lsregister \
        -u "$BUNDLE/macos/Abiplaner.app" 2>/dev/null || true
    VERSION_APP="$(sed -n 's/^ *"version": *"\([^"]*\)".*/\1/p' "$DESKTOP/src-tauri/tauri.conf.json" | head -1)"
    schritt "DMG erzeugen"
    rm -rf "$BUNDLE/dmg"; mkdir -p "$BUNDLE/dmg"
    ablage="$(mktemp -d)"
    ditto "$BUNDLE/macos/Abiplaner.app" "$ablage/Abiplaner.app"
    ln -s /Applications "$ablage/Programme"
    # hdiutil scheitert auf den GitHub-Runnern gelegentlich mit „Resource
    # busy“ (Release 1.0.2, 27.11.2026) — deshalb bis zu fünf Versuche.
    for versuch in 1 2 3 4 5; do
        if hdiutil create -volname "Abiplaner" -srcfolder "$ablage" -ov -format UDZO \
            "$BUNDLE/dmg/Abiplaner_${VERSION_APP}_aarch64.dmg"; then
            break
        fi
        [ "$versuch" = 5 ] && { echo "DMG ließ sich nicht erzeugen." >&2; exit 1; }
        echo "hdiutil fehlgeschlagen, neuer Versuch in 15 s …" >&2
        sleep 15
    done
    rm -rf "$ablage"
else
    npx tauri build --bundles appimage ${TAURI_EXTRA[@]+"${TAURI_EXTRA[@]}"}
fi

# --- macOS: DMG signieren und notarisieren ----------------------------------
# Tauri notarisiert die .app; die DMG darum herum signieren und notarisieren
# wir selbst, damit auch das heruntergeladene Abbild ohne Rückfrage öffnet.
if [ "$(uname -s)" = Darwin ] && [ "$UNSIGNIERT" = 0 ]; then
    for dmg in "$BUNDLE"/dmg/*.dmg; do
        [ -f "$dmg" ] || continue
        schritt "DMG signieren: $(basename "$dmg")"
        codesign --force --timestamp -s "$APPLE_SIGNING_IDENTITY" "$dmg"
        if [ "$NOTARISIEREN" = 1 ]; then
            schritt "DMG notarisieren (dauert einige Minuten)"
            xcrun notarytool submit "$dmg" --key "$APPLE_API_KEY_PATH" \
                --key-id "$APPLE_API_KEY" --issuer "$APPLE_API_ISSUER" --wait
            xcrun stapler staple "$dmg"
        fi
    done
    app="$BUNDLE/macos/Abiplaner.app"
    if [ -d "$app" ]; then
        schritt "Prüfung durch Gatekeeper"
        spctl -a -vvv -t exec "$app" 2>&1 || true
        for dmg in "$BUNDLE"/dmg/*.dmg; do
            [ -f "$dmg" ] && { spctl -a -vvv -t open --context context:primary-signature "$dmg" 2>&1 || true; }
        done
    fi
fi

echo
find "$BUNDLE" -maxdepth 2 \( -name '*.dmg' -o -name '*.app' -o -name '*.AppImage' \) -print
