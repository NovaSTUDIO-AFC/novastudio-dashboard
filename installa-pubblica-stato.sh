#!/bin/zsh
# Installa (o rimuove) la pubblicazione automatica dello stato osservato sulla
# dashboard. Ogni 10 minuti rigenera e rsync-a SOLO stato-osservato.js sul sito,
# così la dashboard resta un cruscotto VIVO (non una foto dell'ultimo deploy).
#
#   zsh installa-pubblica-stato.sh            # installa e avvia
#   zsh installa-pubblica-stato.sh --rimuovi  # ferma e disinstalla
#
# Non contiene segreti. Usa la chiave SSH di deploy (~/.ssh/id_ed25519).

set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
SCRIPT="$DIR/pubblica-stato.sh"
LA="$HOME/Library/LaunchAgents"
STATE="$HOME/.local/state/novastudio"
UID_N=$(id -u)
LABEL="com.novastudio.dashboard.pubblica-stato"
PLIST="$LA/$LABEL.plist"

if [[ "$1" == "--rimuovi" ]]; then
  launchctl bootout "gui/$UID_N/$LABEL" 2>/dev/null || launchctl unload "$PLIST" 2>/dev/null || true
  rm -f "$PLIST"
  echo "Pubblicazione automatica: rimossa."
  exit 0
fi

mkdir -p "$LA" "$STATE"
chmod +x "$SCRIPT"

cat > "$PLIST" <<PLISTEOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>$LABEL</string>
  <key>ProgramArguments</key><array>
    <string>/bin/bash</string><string>$SCRIPT</string>
  </array>
  <key>StartInterval</key><integer>600</integer>
  <key>RunAtLoad</key><true/>
  <key>EnvironmentVariables</key><dict><key>PATH</key><string>/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin</string></dict>
  <key>StandardOutPath</key><string>$STATE/launchd-pubblica-stato.out</string>
  <key>StandardErrorPath</key><string>$STATE/launchd-pubblica-stato.err</string>
</dict></plist>
PLISTEOF

launchctl bootout "gui/$UID_N/$LABEL" 2>/dev/null || launchctl unload "$PLIST" 2>/dev/null || true
if launchctl bootstrap "gui/$UID_N" "$PLIST" 2>/dev/null || launchctl load -w "$PLIST" 2>/dev/null; then
  echo "  caricato: $LABEL (ogni 10 min)"
else
  echo "  ATTENZIONE: caricamento di $LABEL fallito"
fi

echo ""
launchctl list | grep "$LABEL" && echo "attivo ✓" || echo "(non trovato)"
