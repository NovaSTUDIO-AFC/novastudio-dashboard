#!/usr/bin/env bash
# ───────────────────────────────────────────────────────────────
# Deploy NovaSTUDIO dashboard → Hostinger
# Uso:  ./deploy.sh
# Carica solo i file cambiati nella cartella /dashboard sul server.
# ───────────────────────────────────────────────────────────────
set -euo pipefail

# ── Connessione (compilati una volta sola) ─────────────────────
SSH_HOST="46.202.156.83"            # IP o host SSH di Hostinger
SSH_PORT="65002"                    # Hostinger usa di solito la porta 65002
SSH_USER="u236567142"               # es. u123456789
SSH_KEY="$HOME/.ssh/id_ed25519"     # chiave privata sul Mac
REMOTE_DIR="domains/novastudio.company/public_html/dashboard-staging"  # destinazione sul server

# ── Non modificare sotto ───────────────────────────────────────
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)/"

# ── Sincronizza il catalogo del sistema dalla FONTE DI VERITÀ (repo hub) ──
# La dashboard "disegna" il catalogo: qui lo copiamo come JS globale (window.CATALOGO).
HUB_CATALOGO="$HOME/Workspace/progetti/hub/sistema/catalogo.json"
if [ -f "$HUB_CATALOGO" ]; then
  printf 'window.CATALOGO = %s;\n' "$(cat "$HUB_CATALOGO")" > "${LOCAL_DIR}app/assets/catalogo.js"
  echo "→ catalogo (desiderato) sincronizzato da hub ($HUB_CATALOGO)"
else
  echo "⚠️  catalogo non trovato in $HUB_CATALOGO — uso il catalogo.js esistente"
fi

# ── Sincronizza lo STATO OSSERVATO (misurato dall'osservatore) ──
# Il catalogo è il "desiderato"; questo è ciò che è DAVVERO vivo adesso. Vive
# fuori dal repo (sul Mac mini); lo pubblichiamo come window.STATO_OSSERVATO.
STATO_OSS="$HOME/.local/state/novastudio/stato-osservato.json"
if [ -f "$STATO_OSS" ]; then
  printf 'window.STATO_OSSERVATO = %s;\n' "$(cat "$STATO_OSS")" > "${LOCAL_DIR}app/assets/stato-osservato.js"
  echo "→ stato osservato (misurato) sincronizzato ($STATO_OSS)"
else
  echo "⚠️  stato osservato non trovato — la dashboard mostrerà solo il dichiarato"
  printf 'window.STATO_OSSERVATO = {"entita":[],"generato_il":null};\n' > "${LOCAL_DIR}app/assets/stato-osservato.js"
fi

echo "→ Deploy verso ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"

rsync -avz --human-readable \
  -e "ssh -i ${SSH_KEY} -o IdentitiesOnly=yes -p ${SSH_PORT}" \
  --rsync-path="mkdir -p ${REMOTE_DIR} && rsync" \
  --exclude '.git/' \
  --exclude '.DS_Store' \
  --exclude 'BRIEF.md' \
  --exclude 'STATO.md' \
  --exclude 'DEPLOY.md' \
  --exclude 'deploy.sh' \
  --exclude 'config.sample.php' \
  --exclude 'data/throttle.json' \
  --exclude 'data/forgot.json' \
  --exclude 'data/users.db' \
  --exclude 'data/users.db-wal' \
  --exclude 'data/users.db-shm' \
  --exclude '_test/' \
  "${LOCAL_DIR}" "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/"

echo "✅ Deploy completato → https://novastudio.company/dashboard-staging/"
