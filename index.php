<?php
// ───────────────────────────────────────────────────────────────
// FRONT CONTROLLER — gate di autenticazione per la dashboard.
//
// Ogni richiesta sotto /dashboard passa di qui (vedi .htaccess).
// Nessun file in app/ è raggiungibile direttamente: vengono serviti
// SOLO dopo login valido. Così anche app/assets/data.js (IP, comandi
// SSH) è protetto, non solo le pagine HTML.
// ───────────────────────────────────────────────────────────────

declare(strict_types=1);

$CONFIG = require __DIR__ . '/config.php';

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
  // azzera il contatore se l'ultimo tentativo è vecchio
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

// ── Verifica reCAPTCHA v2 lato server ──────────────────────────
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
  // reCAPTCHA v3: la risposta include un punteggio 0.0–1.0; applichiamo una soglia.
  // (Il v2 non ha 'score', quindi questo controllo viene saltato.)
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

// ── Routing ────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

// Logout
if ($action === 'logout') {
  $_SESSION = [];
  session_destroy();
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

// Submit login
$error = '';
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $user  = trim((string)($_POST['username'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');
  $token = (string)($_POST['g-recaptcha-response'] ?? '');
  $csrf  = (string)($_POST['csrf'] ?? '');

  if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    $error = 'Sessione scaduta, riprova.';
  } elseif (is_locked($CONFIG)) {
    $error = 'Troppi tentativi falliti. Riprova tra qualche minuto.';
  } elseif (!verify_recaptcha($CONFIG, $token)) {
    $error = 'Verifica anti-bot non superata.';
  } else {
    $userOk = hash_equals($CONFIG['username'], $user);
    $passOk = password_verify($pass, $CONFIG['password_hash']);
    // confronto sempre entrambi (no early-exit) per non distinguere i casi
    if ($userOk && $passOk) {
      clear_failures();
      session_regenerate_id(true);
      $_SESSION['auth']       = true;
      $_SESSION['login_time'] = time();
      $_SESSION['ua']         = $_SERVER['HTTP_USER_AGENT'] ?? '';
      header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
      exit;
    }
    register_failure();
    $error = 'Credenziali non valide.';
  }
}

// ── Non autenticato → mostra login ─────────────────────────────
if (!is_authed($CONFIG)) {
  http_response_code($action === 'login' ? 401 : 200);
  require __DIR__ . '/login_view.php';
  exit;
}

// ── Autenticato → servi il file richiesto da app/ ──────────────
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // es. /dashboard
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$rel  = urldecode((string)$uri);
if ($base !== '' && strpos($rel, $base) === 0) {
  $rel = substr($rel, strlen($base));
}
$rel = ltrim($rel, '/');
if ($rel === '' || substr($rel, -1) === '/') $rel .= 'index.html';

// blocca path traversal
if (strpos($rel, '..') !== false || strpos($rel, "\0") !== false) {
  http_response_code(400); exit('Bad request');
}

$appDir = __DIR__ . '/app';
$full   = realpath($appDir . '/' . $rel);

// deve restare dentro app/ ed esistere
if ($full === false || strpos($full, realpath($appDir)) !== 0 || !is_file($full)) {
  http_response_code(404);
  echo '<h1>404</h1>';
  exit;
}

// MIME corretto
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
  'woff2'=> 'font/woff2', 'woff' => 'font/woff',
];
header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');
readfile($full);
