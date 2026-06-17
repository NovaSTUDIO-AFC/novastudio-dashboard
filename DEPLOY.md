# Deploy area riservata su Hostinger → novastudio.company/dashboard

Autenticazione **server-side** reale: utente + password (hash bcrypt) +
reCAPTCHA v2 + rate-limiting. Nessun file dell'app è raggiungibile senza login
(neanche `app/assets/data.js`): tutto passa dal front controller `index.php`.

## Struttura
```
dashboard/                 ← carica QUESTA cartella in public_html/dashboard
├── .htaccess              forza HTTPS + instrada tutto su index.php
├── index.php              front controller: login, sessione, serve i file
├── login_view.php         schermata di login (con reCAPTCHA)
├── config.php             SEGRETI: utente, hash password, chiavi reCAPTCHA
├── config.sample.php      template di riferimento
├── app/                   il sito vero (servito solo dopo login)
│   ├── index.html
│   ├── infrastruttura.html
│   └── assets/...
└── data/                  scritto dal server (rate-limit). Deve essere scrivibile.
```

## Passi

### 1. Genera l'hash della password (in locale, sul Mac)
La password NON va mai messa in chiaro. Nel Terminale:
```bash
htpasswd -nbBC 12 "" "LA_TUA_PASSWORD" | cut -d: -f2
```
Copia l'output (inizia con `$2y$12$...`).

### 2. Crea le chiavi reCAPTCHA v2
- Vai su https://www.google.com/recaptcha/admin/create
- Tipo: **reCAPTCHA v2 → "Non sono un robot" (checkbox)**
- Domini: `novastudio.company`
- Ottieni **Site key** (pubblica) e **Secret key** (privata)

### 3. Compila `config.php`
```php
'username'         => 'fabio',                  // l'utente che vuoi
'password_hash'    => '$2y$12$...',             // hash del passo 1
'recaptcha_site'   => '6Lc...',                 // Site key
'recaptcha_secret' => '6Lc...',                 // Secret key
```
(Lasciando vuote le due chiavi reCAPTCHA il login funziona lo stesso, senza captcha.)

### 4. Carica su Hostinger
- In hPanel → **File Manager** (o FTP), entra in `public_html`
- Crea/carica la cartella `dashboard/` con tutto il contenuto
- Assicurati che `dashboard/data/` sia scrivibile dal web server
  (di norma 755 va bene; se il rate-limit non scrive, prova 775).

### 5. SSL e dominio
- In hPanel attiva l'**SSL gratuito** sul dominio (se non già attivo):
  `.htaccess` forza già HTTPS.
- L'area sarà su **https://novastudio.company/dashboard/**

### 6. Verifica
- Apri `https://novastudio.company/dashboard/` → deve apparire il **login**.
- Prova ad aprire direttamente
  `https://novastudio.company/dashboard/app/assets/data.js`
  → deve **NON** mostrare i dati (login o 404), non il file.
- Fai login → vedi la dashboard. "🚪 Esci" in alto fa logout.

## Sicurezza in sintesi
- Password salvata solo come hash bcrypt, verificata lato server.
- reCAPTCHA verificato server-side (secret fuori dalla portata del browser).
- Blocco dopo 5 tentativi falliti per 15 minuti (configurabile in `config.php`).
- Sessione: cookie HttpOnly + Secure + SameSite=Strict, scadenza 8 ore,
  legata allo user-agent, `session_regenerate_id` al login.
- HTTPS forzato + HSTS + header anti-clickjacking/sniffing.
- `config.php` e `app/` non sono serviti direttamente.

## Opzionale: massima sicurezza
Sposta `config.php` **fuori** da `public_html` (es. in `~/private/config.php`)
e cambia in `index.php` la riga:
```php
$CONFIG = require __DIR__ . '/config.php';
```
con il nuovo percorso. Così i segreti non sono mai sotto il web root.
