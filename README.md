# NovaSTUDIO — Dashboard / Area riservata

> Documento di riferimento del progetto. Se stai leggendo questo da una nuova
> sessione di Claude Code/Codex: **parti da qui.** Spiega cos'è, com'è fatto,
> com'è protetto, come si aggiungono contenuti e come si pubblica.
>
> _Ultimo aggiornamento: 17 giugno 2026._

---

## 1. Cos'è

È il **cruscotto privato del progetto NovaSTUDIO**: un sito web ad accesso
riservato, pubblicato online su **https://novastudio.company/dashboard**.

Oggi contiene una sola sezione operativa — la **mappa dell'infrastruttura**
(rete Tailscale, macchine, servizi, scorciatoie) — ma è costruito per crescere:
è la "casa" da cui in futuro si raggiungeranno tutte le aree del progetto.

- **Dominio pubblico** `novastudio.company` → in futuro ospiterà il sito vetrina.
- **`novastudio.company/dashboard`** → questa area riservata (richiede login).
- **Email** del dominio: gestite da **Google Workspace** (record MX → Google).
  ⚠️ Non vanno toccate quando si lavora su hosting/DNS.

### Come si accede
- URL: **https://novastudio.company/dashboard/**
- **Utente:** `fabio`
- **Password:** scelta dall'utente (salvata solo come hash in `config.php`, mai in chiaro).
- Protezione anti-bot: **reCAPTCHA v3** invisibile (nessuna casella da spuntare).

---

## 2. Architettura in due righe

Il sito vero è **statico** (HTML/CSS/JS, data-driven), ma è "incartato" in un
**gate di autenticazione PHP**. Ogni richiesta passa da un **front controller**
(`index.php`) che verifica il login e **solo allora** serve i file. Nessun file
dell'app è raggiungibile direttamente: nemmeno gli asset con gli IP della rete.

```
Browser ──► /dashboard/... ──► .htaccess (rewrite) ──► index.php (gate)
                                                         │
                              non autenticato ───────────┤──► mostra login_view.php
                              autenticato ───────────────┘──► serve i file da app/
```

---

## 3. Struttura dei file

```
novastudio-dashboard/            ← cartella locale (era "infra-site")
│
├─ index.php          FRONT CONTROLLER: sessione, login, rate-limit, reCAPTCHA,
│                     e "server" dei file di app/ solo se autenticato.
├─ login_view.php     Schermata di login (HTML+CSS inline, reCAPTCHA v3).
├─ config.php         SEGRETI: utente, hash password, chiavi reCAPTCHA, soglie.
│                     ⚠️ NON committare, NON pubblicare altrove. Sta solo sul server.
├─ config.sample.php  Template di config senza segreti.
├─ .htaccess          Forza HTTPS, instrada tutto su index.php, header di sicurezza.
├─ deploy.sh          Deploy con un comando (rsync over SSH) verso Hostinger.
├─ data/              Cartella scrivibile dal server (rate-limit). Negata via .htaccess.
│  └─ .htaccess       "Require all denied"
│
├─ app/               IL SITO VERO (servito solo dopo login)
│  ├─ .htaccess       "Require all denied" (difesa extra: niente accesso diretto)
│  ├─ index.html      Home della dashboard (hero + griglia "Aree del progetto")
│  ├─ infrastruttura.html   Pagina mappa infrastruttura
│  └─ assets/
│     ├─ data.js      ★ DATI: macchine, server, servizi, scorciatoie (la "sorgente")
│     ├─ site.js      Layout condiviso: menu di navigazione (NAV) + aree home (AREE)
│     ├─ app.js       Render della dashboard infrastruttura (mappa SVG, card, ecc.)
│     ├─ styles.css   Tema dark + responsive
│     ├─ logo.svg / favicon.png / "NovaSTUDIO Logo.svg"
│
├─ README.md          ← questo file
├─ BRIEF.md           Brief originale (obiettivi di partenza)
├─ STATO.md           Storico di stato (parzialmente superato da questo README)
└─ DEPLOY.md          Guida dettagliata al deploy / sicurezza
```

I file `*.md`, `config.sample.php` e `deploy.sh` **non** vengono caricati sul
server (sono esclusi in `deploy.sh`).

---

## 4. Il sito è "data-driven": si estende modificando i DATI, non il codice

Quasi tutto ciò che si vede è generato da JavaScript a partire da due file.
**Per cambiare i contenuti, di solito basta toccare questi:**

### `app/assets/data.js` — i dati dell'infrastruttura
Oggetto `INFRA` con:
- `server` — il Mac mini (nodo centrale): `nome`, `ip`, `sistema`, `dns`, `icona`, `note`.
- `client[]` — i dispositivi attorno: ognuno con `id`, `nome`, `ip`, `sistema`, `ruolo`, `icona`.
- `scorciatoie{}` — gruppi di comandi terminale (`cmd` + `desc`), clic-per-copiare.
- `servizi[]` — servizi sul Mac mini (`nome`, `icona`, `desc`, `extra?`).

> Es.: per aggiungere una macchina basta aggiungere un oggetto in `client[]`.
> La mappa SVG si ridisegna da sola (i nodi sopra il centro mettono l'etichetta
> sopra l'icona, quelli sotto la mettono sotto).

### `app/assets/site.js` — navigazione e "aree" della home
- `NAV[]` — voci del menu in alto (Home, Infrastruttura, …). Aggiungi qui una voce
  per ogni nuova pagina.
- `AREE[]` — le card mostrate nella home (`titolo`, `desc`, `href`, `icon`, `stato`).
  `stato: "in arrivo"` + `href: null` = card disabilitata (segnaposto).

### `app/assets/app.js` — render della pagina infrastruttura
Disegna: mappa SVG hub-and-spoke, elenco macchine, scorciatoie, servizi.
Si tocca solo se si cambia la *forma* della dashboard, non i dati.

### Aggiungere una NUOVA pagina/area alla dashboard
1. Crea `app/nuova-sezione.html` (copia la struttura di `infrastruttura.html`:
   stesso header `#site-nav`, stessi `<script>` finali).
2. Aggiungi la voce in `NAV[]` (menu) e/o una card in `AREE[]` (home) dentro `site.js`.
3. (Opzionale) crea un `assets/nuova-sezione.js` per il contenuto dinamico.
4. `./deploy.sh`. Fatto: la nuova pagina è protetta dal login automaticamente,
   perché passa dallo stesso front controller.

> Le pagine usano link **relativi** (es. `infrastruttura.html`, `assets/...`):
> il front controller li risolve servendoli da `app/`. Non servono URL assoluti.

---

## 5. Sicurezza (cosa garantisce e come)

Tutto è **lato server**. La vecchia "password nel codice" (segnaposto) è stata
**rimossa**. In sintesi:

- **Password** salvata solo come **hash bcrypt** (`password_hash`), verificata con
  `password_verify`. Mai in chiaro, mai nel browser.
- **reCAPTCHA v3** verificato lato server (la *secret key* sta in `config.php`,
  fuori dalla portata del browser); si applica una **soglia di punteggio**
  (`recaptcha_min_score`, default 0.5).
- **Rate limiting**: dopo `max_attempts` (5) tentativi falliti, blocco per
  `lockout_seconds` (15 min), tracciato per IP in `data/throttle.json`.
- **Sessione PHP** robusta: cookie `HttpOnly` + `Secure` + `SameSite=Strict`,
  scadenza assoluta (8h), legata allo User-Agent, `session_regenerate_id` al login.
- **CSRF token** nel form di login.
- **Protezione file**: `app/` e `config.php` non sono serviti direttamente
  (rewrite + `.htaccess` "Require all denied"). Verificato: l'accesso diretto a
  `app/assets/data.js` o a `config.php` **non** espone i dati (403 / pagina di login).
- **HTTPS forzato** + HSTS + header anti-clickjacking/sniffing.

I parametri (utente, hash, chiavi reCAPTCHA, soglie, durate) sono tutti in
**`config.php`**.

---

## 6. Deploy — pubblicare le modifiche

Tutto già configurato. Dopo qualsiasi modifica in locale:

```bash
cd /Users/fabio/Workspace/progetti/novastudio-dashboard
./deploy.sh
```

Carica **solo i file cambiati** (rsync), in pochi secondi, **senza password**
(usa la chiave SSH `~/.ssh/id_ed25519`, già autorizzata su Hostinger).

### Dettagli connessione (in `deploy.sh`)
- Host SSH: `46.202.156.83` — Porta: `65002` — Utente: `u236567142`
- Destinazione sul server:
  `domains/novastudio.company/public_html/dashboard`
- Esclusi dal deploy: `.git`, `.DS_Store`, i `*.md`, `deploy.sh`,
  `config.sample.php`, `data/throttle.json`.

### Hosting (Hostinger)
- Piano hosting Hostinger; il dominio `novastudio.company` è agganciato come sito.
- DNS gestiti da Hostinger (nameserver `*.dns-parking.com`); A record → server hosting.
- SSL gratuito attivo sul dominio.

---

## 7. Stato attuale

**Fatto:**
- [x] Sito statico data-driven (mappa infrastruttura, macchine, scorciatoie, servizi).
- [x] Home NovaSTUDIO con menu e griglia "Aree del progetto".
- [x] **Autenticazione server-side reale** (PHP): utente+password (bcrypt),
      reCAPTCHA v3, rate-limit, sessione sicura, CSRF.
- [x] Tutti i file protetti dietro il login (verificato).
- [x] **Pubblicato** su https://novastudio.company/dashboard con HTTPS.
- [x] **Deploy con un comando** (`./deploy.sh`, rsync over SSH).
- [x] Cartella rinominata da `infra-site` a `novastudio-dashboard`.

**Da decidere / prossimi passi:**
- [ ] **Quali aree/sezioni mettere nella dashboard** (è il tema su cui si sta
      lavorando in un'altra sessione). Punti di estensione: §4.
- [ ] **Home pubblica** di `novastudio.company` (oggi mostra ancora "Default page"
      di Hostinger): va creata e caricata in `public_html/` (NON dentro `dashboard/`).
- [ ] Eventuale pagina "cambio password" dall'area riservata.
- [ ] Eventuale PWA / installabile.
- [ ] `logo.svg` pesa ~286 KB (immagini raster incorporate): ottimizzabile.

---

## 8. Note per chi sviluppa
- **Non** rimettere password o segreti nel codice JS/HTML: l'auth è solo PHP.
- Per provare in locale serve PHP (il front controller è PHP). In alternativa si
  può testare la sola parte statica aprendo `app/index.html`, ma il gate non gira.
- Modifiche tipiche = `data.js` (dati) o `site.js` (menu/aree). Raramente `app.js`.
- Dopo ogni modifica: `./deploy.sh`.
