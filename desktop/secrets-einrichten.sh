#!/usr/bin/env bash
# Hinterlegt die Signier-Schlüssel als GitHub-Secrets für .github/workflows/release.yml.
# Einmalig auf dem Mac ausführen, auf dem die Schlüssel liegen:
#
#   brew install gh && gh auth login     # falls noch nicht geschehen
#   desktop/secrets-einrichten.sh
#
# Liest aus ~/.config/abiplaner und dem macOS-Schlüsselbund. Die Werte gehen
# direkt an GitHub (verschlüsselt) und erscheinen nirgends im Klartext.
set -euo pipefail

REPO="${REPO:-abiplaner/abiplaner}"
D="$HOME/.config/abiplaner"

command -v gh >/dev/null || { echo "gh fehlt: brew install gh && gh auth login" >&2; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Bitte zuerst: gh auth login" >&2; exit 1; }

kennwort() { security find-generic-password -a abiplaner -s "$1" -w; }
setze() { printf '%s' "$2" | gh secret set "$1" --repo "$REPO" >/dev/null && echo "  ✓ $1"; }

p8="$(ls "$D"/AuthKey_*.p8 | head -1)"
key_id="$(basename "$p8" .p8 | sed 's/^AuthKey_//')"
issuer="$(sed -n 's/^APPLE_API_ISSUER=//p' "$D/notarisierung.env")"

echo "Secrets für $REPO:"
setze APPLE_CERTIFICATE                  "$(base64 < "$D/developer-id.p12" | tr -d '\n')"
setze APPLE_CERTIFICATE_PASSWORD         "$(kennwort abiplaner-developer-id-p12)"
setze APPLE_API_KEY                      "$key_id"
setze APPLE_API_ISSUER                   "$issuer"
setze APPLE_API_KEY_P8                   "$(cat "$p8")"
setze TAURI_SIGNING_PRIVATE_KEY          "$(cat "$D/tauri-update.key")"
setze TAURI_SIGNING_PRIVATE_KEY_PASSWORD "$(kennwort abiplaner-tauri-update-key)"
echo "Fertig. Kontrolle: gh secret list --repo $REPO"
