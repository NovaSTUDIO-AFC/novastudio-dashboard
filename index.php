<?php
// ───────────────────────────────────────────────────────────────
// FRONT CONTROLLER — autenticazione + permessi per la dashboard.
//
// Ogni richiesta sotto /dashboard passa di qui (vedi .htaccess).
// Nessun file in app/ è raggiungibile direttamente: vengono serviti
// SOLO dopo login valido e SOLO se l'utente ha accesso alla sezione.
//
// Multi-utente con ruoli per sezione (store SQLite in data/users.db).
// Se SQLite non è disponibile, si torna all'auth legacy a utente
// singolo (config.php) → non si resta mai chiusi fuori.
// ───────────────────────────────────────────────────────────────

declare(strict_types=1);

// Config di default = config.php. Sovrascrivibile via env solo per i test locali.
$CONFIG = require (getenv('NOVA_CONFIG') ?: __DIR__ . '/config.php');
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/users.php';
require_once __DIR__ . '/lib/reset.php';
require_once __DIR__ . '/lib/attivita.php';
require_once __DIR__ . '/lib/redazione.php';
require_once __DIR__ . '/lib/commerciale.php';
require_once __DIR__ . '/lib/job.php';
require_once __DIR__ . '/lib/seo.php';
require_once __DIR__ . '/lib/pezzo.php';
require_once __DIR__ . '/lib/sicurezza.php';

// ── Sessione sicura ────────────────────────────────────────────
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => $https,
  'httponly' => true,
  'samesite' => 'Strict',
]);
session_name('nova_sess');
session_start();

// Migra il login attuale di Fabio come primo admin (gira una volta sola).
nova_seed_admin($CONFIG);
nova_attivita_seed();  // popola "Da fare" con gli item aperti, se vuota
nova_bozze_seed();     // popola la coda Redazione dal dry-run, se vuota
nova_prospect_seed();  // popola la coda Commerciale (prospect outbound) dal dry-run, se vuota
nova_passaggi_seed();  // popola lo storico passaggi (scheda articolo), se vuoto
nova_job_seed();       // popola le opportunità del reparto Job, se vuoto
nova_seo_seed();       // popola la coda Reparto SEO dal dry-run, se vuota
nova_pezzo_seed();     // popola il giunto SEO<->Redazione (pezzi a due gate)
$MULTIUSER = nova_db() !== null;

// ── Helpers ────────────────────────────────────────────────────
function client_ip(): string {
  return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function is_authed(array $cfg): bool {
  if (empty($_SESSION['auth']) || empty($_SESSION['login_time'])) return false;
  // scadenza assoluta della sessione
  if (time() - (int)$_SESSION['login_time'] > $cfg['session_max_age']) return false;
  // lega la sessione allo user-agent (mitiga furto cookie)
  if (($_SESSION['ua'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) return false;
  return true;
}

// Utente corrente (dalla sessione) come array compatibile con nova_puo().
function utente_corrente(): array {
  return [
    'email'    => $_SESSION['email'] ?? '',
    'is_admin' => !empty($_SESSION['is_admin']) ? 1 : 0,
    'sezioni'  => $_SESSION['sezioni'] ?? json_encode(NOVA_SEZIONI),
  ];
}

// URL base assoluto della dashboard (per i link nelle email).
function link_base(): string {
  global $https;
  $scheme = $https ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
  return $scheme . '://' . $host . $base . '/';
}

// ── Rate limiting su file (per IP) ─────────────────────────────
function throttle_file(): string {
  $dir = __DIR__ . '/data';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  return $dir . '/throttle.json';
}
function load_throttle(): array {
  $f = throttle_file();
  if (!is_file($f)) return [];
  $j = json_decode((string)@file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function save_throttle(array $data): void {
  @file_put_contents(throttle_file(), json_encode($data), LOCK_EX);
}
function is_locked(array $cfg): bool {
  $data = load_throttle();
  $rec  = $data[client_ip()] ?? null;
  if (!$rec) return false;
  if (($rec['count'] ?? 0) >= $cfg['max_attempts']
      && time() - ($rec['last'] ?? 0) < $cfg['lockout_seconds']) {
    return true;
  }
  return false;
}
function register_failure(): void {
  $data = load_throttle();
  $ip   = client_ip();
  $rec  = $data[$ip] ?? ['count' => 0, 'last' => 0];
  if (time() - ($rec['last'] ?? 0) > 3600) $rec['count'] = 0;
  $rec['count']++;
  $rec['last'] = time();
  $data[$ip] = $rec;
  save_throttle($data);
}
function clear_failures(): void {
  $data = load_throttle();
  unset($data[client_ip()]);
  save_throttle($data);
}

// Cooldown semplice per "password dimenticata" (anti-spam invio email).
function forgot_cooldown_attivo(int $secondi = 60): bool {
  $dir = __DIR__ . '/data';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $f = $dir . '/forgot.json';
  $data = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
  $ip = client_ip();
  $last = (int)($data[$ip] ?? 0);
  if (time() - $last < $secondi) return true;
  $data[$ip] = time();
  @file_put_contents($f, json_encode($data), LOCK_EX);
  return false;
}

// ── Verifica reCAPTCHA v3 lato server ──────────────────────────
function verify_recaptcha(array $cfg, string $token): bool {
  if (empty($cfg['recaptcha_secret'])) return true; // disattivato se non configurato
  if ($token === '') return false;
  $post = http_build_query([
    'secret'   => $cfg['recaptcha_secret'],
    'response' => $token,
    'remoteip' => client_ip(),
  ]);
  $url = 'https://www.google.com/recaptcha/api/siteverify';

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $post,
      CURLOPT_TIMEOUT        => 8,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
  } else {
    $ctx = stream_context_create(['http' => [
      'method'  => 'POST',
      'header'  => 'Content-Type: application/x-www-form-urlencoded',
      'content' => $post,
      'timeout' => 8,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
  }
  if ($res === false) return false;
  $j = json_decode((string)$res, true);
  if (empty($j['success'])) return false;
  if (isset($j['score']) && $j['score'] < ($cfg['recaptcha_min_score'] ?? 0.5)) {
    return false;
  }
  return true;
}

// ── CSRF ───────────────────────────────────────────────────────
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_ok(): bool {
  return hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''));
}

// Calcola il file richiesto (relativo a app/) dalla URL.
function file_richiesto(): string {
  $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // es. /dashboard
  $uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
  $rel  = urldecode((string)$uri);
  if ($base !== '' && strpos($rel, $base) === 0) $rel = substr($rel, strlen($base));
  $rel = ltrim($rel, '/');
  if ($rel === '' || substr($rel, -1) === '/') $rel .= 'index.html';
  return $rel;
}

// Serve un file da app/ con MIME e header di sicurezza corretti (poi esce).
function serve_app_file(string $rel): void {
  if (strpos($rel, '..') !== false || strpos($rel, "\0") !== false) {
    http_response_code(400); exit('Bad request');
  }
  $appDir = __DIR__ . '/app';
  $full   = realpath($appDir . '/' . $rel);
  if ($full === false || strpos($full, realpath($appDir)) !== 0 || !is_file($full)) {
    http_response_code(404); echo '<h1>404</h1>'; exit;
  }
  $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
  $mimes = [
    'html' => 'text/html; charset=utf-8',
    'js'   => 'application/javascript; charset=utf-8',
    'css'  => 'text/css; charset=utf-8',
    'svg'  => 'image/svg+xml',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'ico'  => 'image/x-icon',
    'json' => 'application/json; charset=utf-8',
    'webmanifest' => 'application/manifest+json; charset=utf-8',
    'woff2'=> 'font/woff2', 'woff' => 'font/woff',
  ];
  header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
  header('Referrer-Policy: no-referrer');
  // SW e manifest devono poter essere rivalidati; il resto privato.
  if ($rel === 'sw.js' || $ext === 'webmanifest') {
    header('Cache-Control: public, max-age=0, must-revalidate');
  } else {
    header('Cache-Control: private, no-store');
  }
  readfile($full);
  exit;
}

// Asset PWA serviti SENZA login (nessun dato sensibile): così manifest e
// service worker sono raggiungibili anche dalla pagina di accesso e l'app
// risulta installabile a prescindere dallo stato di sessione.
const NOVA_ASSET_PUBBLICI = [
  'manifest.webmanifest', 'sw.js', 'assets/pwa.js',
  'assets/icon-192.png', 'assets/icon-512.png', 'assets/icon-maskable-512.png',
];

// ── Routing ────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

// Logout
if ($action === 'logout') {
  $_SESSION = [];
  session_destroy();
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

// ── Password dimenticata (pre-login) ───────────────────────────
if ($action === 'forgot' && $MULTIUSER) {
  $error = ''; $info = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string)($_POST['email'] ?? '');
    $token = (string)($_POST['g-recaptcha-response'] ?? '');
    if (!csrf_ok()) {
      $error = 'Sessione scaduta, riprova.';
    } elseif (!verify_recaptcha($CONFIG, $token)) {
      $error = 'Verifica anti-bot non superata.';
    } elseif (forgot_cooldown_attivo()) {
      $error = 'Hai appena richiesto un reset. Aspetta un minuto.';
    } else {
      nova_reset_richiedi($CONFIG, $email, link_base());
      // Messaggio identico in ogni caso (anti-enumerazione).
      $info = 'Se l\'email è registrata, riceverai un link per reimpostare la password.';
    }
  }
  require __DIR__ . '/views/forgot.php';
  exit;
}

// ── Reset password con token (pre-login) ───────────────────────
if ($action === 'reset' && $MULTIUSER) {
  $error = ''; $info = '';
  $raw   = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
  $valido = nova_reset_valida($raw) !== null;
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
      $error = 'Sessione scaduta, riprova.';
    } else {
      [$ok, $msg] = nova_reset_completa($raw, (string)($_POST['password'] ?? ''));
      if ($ok) { $info = $msg; $valido = false; }
      else     { $error = $msg; }
    }
  } elseif (!$valido) {
    $error = 'Link non valido o scaduto. Richiedine uno nuovo.';
  }
  require __DIR__ . '/views/reset.php';
  exit;
}

// ── Submit login ───────────────────────────────────────────────
$error = '';
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? $_POST['username'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');
  $token = (string)($_POST['g-recaptcha-response'] ?? '');

  if (!csrf_ok()) {
    $error = 'Sessione scaduta, riprova.';
  } elseif (is_locked($CONFIG)) {
    $error = 'Troppi tentativi falliti. Riprova tra qualche minuto.';
  } elseif (!verify_recaptcha($CONFIG, $token)) {
    $error = 'Verifica anti-bot non superata.';
  } else {
    $ok = false; $sess = null;

    if ($MULTIUSER) {
      // Auth multi-utente dal DB.
      $u = nova_utente_per_email($email);
      if ($u && password_verify($pass, $u['pass_hash'])) {
        $ok   = true;
        $sess = [
          'email'    => $u['email'],
          'is_admin' => !empty($u['is_admin']) ? 1 : 0,
          'sezioni'  => json_encode(nova_sezioni_utente($u)),
        ];
      }
    } else {
      // Fallback legacy: utente singolo da config. Accetta sia lo username
      // storico ('fabio') sia l'email admin, così il login funziona comunque.
      $adminEmail = nova_norm_email((string)($CONFIG['admin_email'] ?? ''));
      $userOk = hash_equals($CONFIG['username'], $email)
             || ($adminEmail !== '' && hash_equals($adminEmail, nova_norm_email($email)));
      $passOk = password_verify($pass, $CONFIG['password_hash']);
      if ($userOk && $passOk) {
        $ok   = true;
        $sess = ['email' => $CONFIG['username'], 'is_admin' => 1, 'sezioni' => json_encode(NOVA_SEZIONI)];
      }
    }

    if ($ok && $sess) {
      clear_failures();
      session_regenerate_id(true);
      $_SESSION['auth']       = true;
      $_SESSION['login_time'] = time();
      $_SESSION['ua']         = $_SERVER['HTTP_USER_AGENT'] ?? '';
      $_SESSION['email']      = $sess['email'];
      $_SESSION['is_admin']   = $sess['is_admin'];
      $_SESSION['sezioni']    = $sess['sezioni'];
      header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
      exit;
    }
    register_failure();
    $error = 'Credenziali non valide.';
  }
}

// ── Asset PWA pubblici (prima del cancello di login) ───────────
$rel = file_richiesto();
if (in_array($rel, NOVA_ASSET_PUBBLICI, true)) {
  serve_app_file($rel);
}

// ── Non autenticato → mostra login ─────────────────────────────
if (!is_authed($CONFIG)) {
  http_response_code($action === 'login' ? 401 : 200);
  require __DIR__ . '/login_view.php';
  exit;
}

// ════════════════ DA QUI IN POI: UTENTE AUTENTICATO ════════════
$ME = utente_corrente();

// ── Pannello admin (solo admin) ────────────────────────────────
if ($action === 'admin' || strpos((string)$action, 'admin_') === 0) {
  if (empty($ME['is_admin']) || !$MULTIUSER) {
    http_response_code(403);
    exit('<h1>403</h1><p>Riservato agli amministratori.</p>');
  }
  $msg = ''; $err = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
      $err = 'Sessione scaduta, riprova.';
    } elseif ($action === 'admin_create') {
      [$r, $m] = nova_crea_utente(
        (string)($_POST['email'] ?? ''),
        (string)($_POST['password'] ?? ''),
        (array)($_POST['sezioni'] ?? []),
        !empty($_POST['is_admin'])
      );
      $r ? $msg = $m : $err = $m;
    } elseif ($action === 'admin_update') {
      $id      = (int)($_POST['id'] ?? 0);
      $isAdmin = !empty($_POST['is_admin']);
      // non declassare l'ultimo admin rimasto
      if (!$isAdmin && nova_conta_admin_attivi($id) === 0) {
        $err = 'Deve restare almeno un amministratore.';
      } else {
        nova_aggiorna_sezioni($id, (array)($_POST['sezioni'] ?? []), $isAdmin);
        $msg = 'Permessi aggiornati.';
      }
    } elseif ($action === 'admin_toggle') {
      $id     = (int)($_POST['id'] ?? 0);
      $attivo = !empty($_POST['attivo']);
      if (!$attivo && nova_conta_admin_attivi($id) === 0) {
        $err = 'Non puoi sospendere l\'ultimo amministratore.';
      } else {
        nova_imposta_attivo($id, $attivo);
        $msg = $attivo ? 'Utente riattivato.' : 'Utente sospeso.';
      }
    } elseif ($action === 'admin_setpw') {
      $email = (string)($_POST['email'] ?? '');
      $ok    = nova_imposta_password($email, (string)($_POST['password'] ?? ''));
      $ok ? $msg = 'Password reimpostata.' : $err = 'Password troppo corta o utente assente.';
    }
  }

  $utenti = nova_lista_utenti();
  require __DIR__ . '/views/admin.php';
  exit;
}

// ── Azioni "Da fare" (qualsiasi utente loggato) → JSON ─────────
if (strpos((string)$action, 'task_') === 0) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: private, no-store');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'richiesta non valida']);
    exit;
  }
  if ($action === 'task_add')         nova_attivita_aggiungi((string)($_POST['testo'] ?? ''));
  elseif ($action === 'task_toggle')  nova_attivita_toggle((int)($_POST['id'] ?? 0));
  elseif ($action === 'task_del')     nova_attivita_elimina((int)($_POST['id'] ?? 0));
  echo json_encode(['ok' => true, 'items' => nova_attivita_lista()], JSON_UNESCAPED_UNICODE);
  exit;
}

// ── Azioni Redazione/Approvazioni (sezione redazione) → JSON ───
if (strpos((string)$action, 'appr_') === 0) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'redazione')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'forbidden']);
    exit;
  }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'richiesta non valida']);
    exit;
  }
  if ($action === 'appr_set') {
    nova_bozza_aggiorna(
      (int)($_POST['id'] ?? 0),
      (string)($_POST['stato'] ?? ''),
      (string)($_POST['commento'] ?? '')
    );
  }
  echo json_encode(['ok' => true, 'items' => nova_bozze_lista()], JSON_UNESCAPED_UNICODE);
  exit;
}

// ── Azioni Commerciale (sezione commerciale) → JSON ────────────
if (strpos((string)$action, 'comm_') === 0) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'commerciale')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'forbidden']);
    exit;
  }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'richiesta non valida']);
    exit;
  }
  if ($action === 'comm_set') {
    nova_prospect_aggiorna(
      (int)($_POST['id'] ?? 0),
      (string)($_POST['stato'] ?? ''),
      (string)($_POST['commento'] ?? '')
    );
  }
  echo json_encode(['ok' => true, 'items' => nova_prospect_lista()], JSON_UNESCAPED_UNICODE);
  exit;
}

// ── Azioni Job (sezione job) → JSON ────────────────────────────
if (strpos((string)$action, 'job_') === 0) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'job')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'forbidden']);
    exit;
  }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'richiesta non valida']);
    exit;
  }
  if ($action === 'job_set') {
    nova_job_aggiorna(
      (int)($_POST['id'] ?? 0),
      (string)($_POST['stato'] ?? ''),
      (string)($_POST['commento'] ?? '')
    );
  } elseif ($action === 'job_feedback') {
    nova_job_feedback_aggiungi((string)($_POST['testo'] ?? ''));
  }
  echo json_encode([
    'ok'       => true,
    'items'    => nova_job_lista(),
    'feedback' => nova_job_feedback_lista(),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ── Azioni SEO (sezione seo) → JSON ────────────────────────────
if (strpos((string)$action, 'seo_') === 0) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'seo')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'forbidden']);
    exit;
  }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'richiesta non valida']);
    exit;
  }
  if ($action === 'seo_set') {
    nova_seo_aggiorna(
      (int)($_POST['id'] ?? 0),
      (string)($_POST['stato'] ?? ''),
      (string)($_POST['commento'] ?? '')
    );
  } elseif ($action === 'seo_feedback') {
    nova_seo_feedback_aggiungi((string)($_POST['testo'] ?? ''));
  }
  echo json_encode([
    'ok'       => true,
    'items'    => nova_seo_lista(),
    'feedback' => nova_seo_feedback_lista(),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ── Azioni Pezzo (giunto SEO<->Redazione, due gate) → JSON ─────
if (strpos((string)$action, 'pezzo_') === 0) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: private, no-store');
  // Il giunto e visibile a chi ha SEO o Redazione. Il singolo gate richiede
  // il permesso del reparto che lo governa: SEO setta il gate SEO, la
  // Redazione il gate Rilevanza. Fabio (admin) puo entrambi.
  $puoSeo = nova_puo($ME, 'seo');
  $puoRed = nova_puo($ME, 'redazione');
  if (!$puoSeo && !$puoRed) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'forbidden']);
    exit;
  }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'richiesta non valida']);
    exit;
  }
  if ($action === 'pezzo_set') {
    $gate = (string)($_POST['gate'] ?? '');
    $consentito = ($gate === 'seo' && $puoSeo) || ($gate === 'rilevanza' && $puoRed);
    if ($consentito) {
      nova_pezzo_set_gate(
        (int)($_POST['id'] ?? 0),
        $gate,
        (string)($_POST['esito'] ?? ''),
        (string)($_POST['nota'] ?? '')
      );
    }
  } elseif ($action === 'pezzo_promuovi') {
    // promozione di un pezzo validato (in_pipeline) → coda approvazioni (bozze).
    nova_pezzo_promuovi((int)($_POST['id'] ?? 0));
  }
  echo json_encode(['ok' => true, 'items' => nova_pezzo_lista()], JSON_UNESCAPED_UNICODE);
  exit;
}

// $rel è già stato calcolato sopra con file_richiesto().

// ── Endpoint dinamico: permessi dell'utente per il front-end ───
if ($rel === 'assets/permessi.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  echo 'window.PERMESSI = ' . json_encode([
    'email'   => $ME['email'],
    'isAdmin' => !empty($ME['is_admin']),
    'sezioni' => nova_sezioni_utente($ME),
  ], JSON_UNESCAPED_SLASHES) . ";\n";
  exit;
}

// ── Endpoint dinamico: dati "Da fare" + csrf per il front-end ──
if ($rel === 'assets/attivita-dati.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  echo 'window.ATTIVITA = ' . json_encode([
    'csrf'  => csrf_token(),
    'items' => nova_attivita_lista(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
  exit;
}

// ── Endpoint dinamico: bozze Redazione + csrf per il front-end ─
if ($rel === 'assets/redazione-dati.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'redazione')) {
    http_response_code(403);
    echo "window.REDAZIONE = { csrf: '', items: [] };\n";
    exit;
  }
  echo 'window.REDAZIONE = ' . json_encode([
    'csrf'  => csrf_token(),
    'items' => nova_bozze_lista(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
  exit;
}

// ── Endpoint dinamico: opportunità Job + feedback + csrf ───────
if ($rel === 'assets/commerciale-dati.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'commerciale')) {
    http_response_code(403);
    echo "window.COMMERCIALE = { csrf: '', items: [] };\n";
    exit;
  }
  echo 'window.COMMERCIALE = ' . json_encode([
    'csrf'  => csrf_token(),
    'items' => nova_prospect_lista(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
  exit;
}
if ($rel === 'assets/job-dati.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'job')) {
    http_response_code(403);
    echo "window.JOB = { csrf: '', items: [], feedback: [] };\n";
    exit;
  }
  echo 'window.JOB = ' . json_encode([
    'csrf'     => csrf_token(),
    'items'    => nova_job_lista(),
    'feedback' => nova_job_feedback_lista(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
  exit;
}

// ── Endpoint dinamico: raccomandazioni SEO + feedback + csrf ───
if ($rel === 'assets/seo-dati.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'seo')) {
    http_response_code(403);
    echo "window.SEO = { csrf: '', items: [], feedback: [] };\n";
    exit;
  }
  echo 'window.SEO = ' . json_encode([
    'csrf'     => csrf_token(),
    'items'    => nova_seo_lista(),
    'feedback' => nova_seo_feedback_lista(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
  exit;
}

// ── Endpoint dinamico: stato Reparto Sicurezza (sola lettura) ──
if ($rel === 'assets/sicurezza-dati.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'sicurezza')) {
    http_response_code(403);
    echo "window.SICUREZZA = { report: null };\n";
    exit;
  }
  echo 'window.SICUREZZA = ' . json_encode([
    'report' => nova_sicurezza_report(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
  exit;
}

// ── Endpoint dinamico: giunto SEO<->Redazione (pezzi) + csrf ───
if ($rel === 'assets/pezzo-dati.js') {
  header('Content-Type: application/javascript; charset=utf-8');
  header('Cache-Control: private, no-store');
  if (!nova_puo($ME, 'seo') && !nova_puo($ME, 'redazione')) {
    http_response_code(403);
    echo "window.PEZZI = { csrf: '', items: [], puoSeo:false, puoRil:false };\n";
    exit;
  }
  echo 'window.PEZZI = ' . json_encode([
    'csrf'   => csrf_token(),
    'items'  => nova_pezzo_lista(),
    'puoSeo' => nova_puo($ME, 'seo'),
    'puoRil' => nova_puo($ME, 'redazione'),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
  exit;
}

// ── Gate per sezione (enforcement server-side) ─────────────────
$sezione = nova_sezione_di($rel);
if ($sezione !== null && !nova_puo($ME, $sezione)) {
  http_response_code(403);
  exit('<h1>403</h1><p>Non hai accesso a questa sezione.</p>');
}

// ── Servi il file richiesto da app/ ────────────────────────────
serve_app_file($rel);
