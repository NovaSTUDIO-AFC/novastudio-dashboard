# Brief — Sito privato "Mappa Infrastruttura" di Fabio

> Questo file è il punto di partenza per Claude Code. Leggilo prima di iniziare.
> Vedi anche `~/Workspace/SCHEDA-SERVER.md` per i dettagli completi.

## Obiettivo
Un **sito privato personale** (accesso protetto da password, solo per Fabio) che fa da
**cruscotto/mappa della propria infrastruttura**. Oggi si parte dalla mappa base;
in futuro si aggiungeranno altre sezioni e poi si pubblicherà online.

## Cosa deve contenere OGGI (versione 1)
1. **Mappa dell'infrastruttura** — il Mac mini come server centrale "sempre acceso",
   con attorno i dispositivi client che si collegano via Tailscale.
2. **Elenco macchine** (vedi tabella sotto).
3. **Scorciatoie** per lavorare (vedi sotto).
4. **Servizi attivi** sul Mac mini.

## Macchine (rete privata Tailscale)
| Nome | IP Tailscale | Sistema | Ruolo |
|------|--------------|---------|-------|
| mac-mini-di-fabio | 100.126.71.100 | macOS 26.5.1 | **Server** sempre acceso (Claude/Codex/openclaw/LM Studio) |
| macbook-air-di-fabio | 100.75.206.50 | macOS | Client |
| minipc-fabio | 100.76.197.65 | Windows 11 | Client |
| galaxy-di-fabio | 100.103.85.111 | Android 16 | Client mobile |

Nome DNS del server: `mac-mini-di-fabio.tailafe504.ts.net`

## Scorciatoie (identiche su Mac e Windows)
- `macmini` — entra nel Mac mini via SSH
- `mwork` — entra direttamente in ~/Workspace
- `mclaude` / `mcodex` / `mclaw` — avvia Claude Code / Codex / openclaw nel Workspace
- `mcode` — apre VS Code remoto sul Workspace

Dentro il Mac mini: `work`, `wp`, `wclaude`, `wcodex`, `wclaw`.

## Servizi sul Mac mini
- **SSH** (solo a chiave) via Tailscale
- **VS Code remoto** (Remote-SSH)
- **LM Studio** — API OpenAI-compatibile su `https://mac-mini-di-fabio.tailafe504.ts.net/v1`
  (modelli: `deepseek/deepseek-r1-0528-qwen3-8b`, `google/gemma-4-e4b`)
- **Condivisione schermo** (vnc, via Tailscale)
- **Monitoraggio** Healthchecks.io (alert email/Slack/WhatsApp)

## Requisiti tecnici e note
- **Stack semplice e manutenibile** (proposta: sito statico HTML/CSS/JS, niente dipendenze pesanti),
  così è facile da estendere e da pubblicare ovunque domani.
- **Estendibile**: struttura a sezioni, così in futuro si aggiungono pagine/blocchi.
- **Accesso privato**: per OGGI va bene un semplice "cancello" con password lato client
  COME SEGNAPOSTO — ma da segnalare chiaramente che NON è sicurezza reale (la password
  sarebbe visibile nel codice). La privacy VERA si farà al momento della pubblicazione con:
  autenticazione lato server (es. Basic Auth), oppure hosting dietro Tailscale/Cloudflare Access.
- Design pulito, leggibile, "dashboard".

## Come provarlo in locale
Sito statico: si può aprire il file index.html nel browser, oppure servirlo con un
piccolo server locale (es. `python3 -m http.server` dentro la cartella).
