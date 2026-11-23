#!/bin/sh
# Ausrollen auf dem Server von abiplaner-bayern.de: Homepage (website/) und
# Schulserver hinter Traefik (docker/schule/compose.traefik.yaml).
#
#   ./deploy.sh            # git pull, Homepage, Schulserver neu bauen/starten
#   ./deploy.sh website    # nur die Homepage
#   ./deploy.sh status     # Container und Gesundheitszustand
#   ./deploy.sh logs       # Protokoll des Schulservers
#
# Name des Compose-Projekts (bestimmt den Namen des Daten-Volumes!) und des
# Containers stehen in der .env: ABIPLANER_PROJEKT, ABIPLANER_CONTAINER.
# Vor neuen Datenbank-Migrationen sichert der Container selbst
# (abiplaner:vorbereiten); die Sicherungen liegen im Volume unter /data/sicherungen.
set -eu
cd "$(dirname "$0")"

PROJEKT="$(sed -n 's/^ABIPLANER_PROJEKT=//p' .env 2>/dev/null)"
PROJEKT="${PROJEKT:-abiplaner-schule}"
SCHULE="docker compose -p $PROJEKT --env-file .env -f docker/schule/compose.yaml -f docker/schule/compose.traefik.yaml"
WEBSITE="docker compose -f website/compose.yaml"

case "${1:-alles}" in
    alles)
        git pull --ff-only
        $WEBSITE up -d --force-recreate   # nginx.conf ist als Einzeldatei eingebunden
        $SCHULE up -d --build
        $SCHULE ps
        ;;
    website)
        git pull --ff-only
        $WEBSITE up -d --force-recreate   # nginx.conf ist als Einzeldatei eingebunden
        ;;
    status)
        $WEBSITE ps
        $SCHULE ps
        ;;
    logs)
        $SCHULE logs --tail=100 -f
        ;;
    *)
        echo "Aufruf: $0 [alles|website|status|logs]" >&2
        exit 1
        ;;
esac
