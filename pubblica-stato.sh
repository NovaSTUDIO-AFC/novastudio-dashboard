#!/usr/bin/env bash
# ───────────────────────────────────────────────────────────────
# pubblica-stato.sh — tiene VIVA la dashboard.
#
# Rigenera SOLO app/assets/stato-osservato.js da ~/.local/state/novastudio/
# (scritto dall'osservatore ogni 15 min) e lo rsync-a come SINGOLO file sul
# sito. NON ridistribuisce tutta la dashboard: è un aggiornamento dati leggero.
#
# Pensato per girare schedulato (launchd) → la dashboard mostra lo stato MISURATO
# quasi in tempo reale, non una foto dell'ultimo deploy.
#
# Best-effort: se la rete/SSH non va, esce senza rumore (la dashboard resta
# all'ultimo stato pubblicato; il sistema non si rompe).
# ───────────────────────────────────────────────────────────────
set -euo pipefail

SSH_HOST="46.202.156.83"
SSH_PORT="65002"
SSH_USER="u236567142"
SSH_KEY="$HOME/.ssh/id_ed25519"
REMOTE_DIR="domains/novastudio.company/public_html/dashboard"

DIR="$(cd "$(dirname "$0")" && pwd)"
STATO_OSS="$HOME/.local/state/novastudio/stato-osservato.json"
ASSET="$DIR/app/assets/stato-osservato.js"

[ -f "$STATO_OSS" ] || { echo "stato osservato assente: $STATO_OSS" >&2; exit 0; }

# rigenera l'asset come window.STATO_OSSERVATO (stesso formato del deploy)
printf 'window.STATO_OSSERVATO = %s;\n' "$(cat "$STATO_OSS")" > "$ASSET"

# pubblica SOLO quel file
rsync -az --timeout=30 \
  -e "ssh -i ${SSH_KEY} -o IdentitiesOnly=yes -o ConnectTimeout=15 -p ${SSH_PORT}" \
  "$ASSET" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/app/assets/stato-osservato.js"

echo "pubblicato stato-osservato.js → dashboard ($(date -u +%FT%TZ))"
