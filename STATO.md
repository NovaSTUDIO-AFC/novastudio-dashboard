# STATO del progetto — NovaSTUDIO (sito privato)

> ⚠️ **Documento storico, in parte superato.** Il riferimento aggiornato ora è
> **`README.md`** (architettura, sicurezza reale, deploy). In particolare:
> il "cancello password lato client" descritto qui sotto è stato **rimosso** e
> sostituito da autenticazione PHP server-side; la cartella è stata rinominata
> da `infra-site` a `novastudio-dashboard`; i file statici stanno ora in `app/`.

> Promemoria per riprendere il lavoro in una nuova sessione di Claude Code.
> Leggi anche `BRIEF.md` (obiettivi originali) e il codice in `app/assets/`.

_Ultimo aggiornamento: 15 giugno 2026 (vedi README.md per lo stato al 17 giugno)._

## Dove gira
- I file vivono **sul Mac mini**: `/Users/fabio/Workspace/progetti/infra-site`
- Per continuare: aprire VS Code in **Remote-SSH sul Mac mini** (scorciatoia `mcode`),
  NON VS Code locale sul MacBook (lì i file non ci sono).
- Server di anteprima locale (statico):
  ```bash
  cd ~/Workspace/progetti/infra-site
  python3 -m http.server 8000 --bind 0.0.0.0
  ```
  Si apre da un altro dispositivo nella tailnet su:
  - http://100.126.71.100:8000
  - http://mac-mini-di-fabio.tailafe504.ts.net:8000
- Cancello password = **segnaposto**, password attuale: `fabio` (definita in `assets/site.js`).
  NON è sicurezza reale (la password è nel codice).

## Architettura attuale
Sito **statico** multi-pagina, **data-driven** (per estenderlo si modificano i dati, non il codice):
```
infra-site/
├─ index.html             ← Home NovaSTUDIO (hero + griglia "Aree del progetto")
├─ infrastruttura.html    ← Dashboard infrastruttura
├─ STATO.md               ← questo file
├─ BRIEF.md               ← brief originale
└─ assets/
   ├─ data.js    ← DATI infrastruttura (macchine, servizi, scorciatoie)
   ├─ site.js    ← layout condiviso: menu (NAV), aree home (AREE), cancello password
   ├─ app.js     ← render della dashboard (mappa SVG, macchine, scorciatoie, servizi)
   ├─ styles.css ← stile (tema dark dashboard) + responsive
   ├─ logo.svg          ← logo NovaSTUDIO (usato nella home, dentro un cerchio)
   ├─ favicon.png       ← favicon 256×256 (generata dal logo)
   └─ NovaSTUDIO Logo.svg ← originale caricato dall'utente (backup)
```

## Fatto finora
- [x] Dashboard infrastruttura: mappa SVG hub-and-spoke (Mac mini al centro, 3 client),
      elenco macchine, scorciatoie (clic per copiare), servizi.
- [x] Icona server Mac mini = 🗄️ (definita in `data.js` → `server.icona`).
- [x] Homepage NovaSTUDIO con menu di navigazione (Home · Infrastruttura).
- [x] Menu + cancello password condivisi via `site.js` (niente duplicazione).
- [x] Responsive: menu hamburger su mobile, griglie a 1 colonna, niente scroll orizzontale.
- [x] Logo NovaSTUDIO nella home dentro un cerchio (sfondo blu tenue + bordo) + favicon.

## Da fare / prossimi passi possibili
- [ ] **Sicurezza reale per la pubblicazione** (priorità quando si pubblica):
      consigliato `tailscale serve --bg 8000` → HTTPS vero su
      https://mac-mini-di-fabio.tailafe504.ts.net (accesso solo dalla tailnet, niente
      password nel codice). Poi eventualmente `tailscale funnel` o Cloudflare Access
      per accedere "da ovunque". Infine sostituire il cancello segnaposto.
- [ ] Aggiungere nuove **aree** del progetto (si aggiungono in `AREE` dentro `site.js`).
- [ ] Eventuale trasformazione in **PWA** (installabile su telefono/desktop).
- [ ] Possibile ottimizzazione `logo.svg` (286 KB: contiene immagini raster incorporate).

## Preferenze dell'utente emerse
- Vuole stack **semplice ma estendibile**; sicurezza **reale** alla pubblicazione (usa Tailscale).
- Lavora spesso da un **altro dispositivo** (MacBook/Windows) collegato al Mac mini via Tailscale,
  quindi `localhost` non va: usare l'IP/DNS Tailscale del Mac mini.
- Logo: lo vuole dentro il cerchio segnaposto, non a tutta dimensione.
