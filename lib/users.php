<?php
// ───────────────────────────────────────────────────────────────
// UTENTI & PERMESSI (per sezione).
// - admin: vede tutto + pannello admin (Fabio, sempre).
// - utente normale: vede solo le sezioni elencate nel suo profilo.
// 'home' e 'guida' sono sempre accessibili a chi è loggato.
// L'enforcement vero è SERVER-SIDE nel front controller (index.php):
// nascondere le voci di menu non basta.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// Sezioni soggette a permesso.
const NOVA_SEZIONI = ['infrastruttura', 'sistema', 'posta', 'automazioni', 'mappa', 'redazione', 'job', 'seo'];

// Etichette leggibili (per il pannello admin).
const NOVA_SEZIONI_LABEL = [
  'infrastruttura' => 'Infrastruttura',
  'sistema'        => 'Sistema',
  'posta'          => 'Posta',
  'automazioni'    => 'Automazioni',
  'mappa'          => 'Mappa',
  'redazione'      => 'Redazione',
  'job'            => 'Job (lavoro)',
  'seo'            => 'SEO (Reparto)',
];

// File richiesto → sezione di appartenenza.
// null = risorsa condivisa (layout, dati globali) → sempre servita ai loggati.
function nova_sezione_di(string $rel): ?string {
  static $map = [
    'infrastruttura.html'      => 'infrastruttura',
    'assets/data.js'           => 'infrastruttura', // IP, comandi SSH
    'assets/app.js'            => 'infrastruttura', // renderer mappa infra
    'sistema.html'             => 'sistema',
    'assets/sistema.js'        => 'sistema',
    'posta.html'               => 'posta',
    'assets/posta.js'          => 'posta',
    'automazioni.html'         => 'automazioni',
    'assets/automazioni.js'    => 'automazioni',
    'mappa.html'               => 'mappa',
    'assets/mappa.js'          => 'mappa',
    'assets/mappa-dati.js'     => 'mappa',
    'redazione.html'           => 'redazione',
    'assets/redazione.js'      => 'redazione',
    'assets/redazione-dati.js' => 'redazione',
    'manuale.html'             => 'redazione',
    'job.html'                 => 'job',
    'assets/job.js'            => 'job',
    'assets/job-dati.js'       => 'job',
    'job-candidature.html'     => 'job',
    'assets/job-candidature.js'=> 'job',
    'job-manuale.html'         => 'job',
    'seo.html'                 => 'seo',
    'assets/seo.js'            => 'seo',
    'assets/seo-dati.js'       => 'seo',
    'seo-manuale.html'         => 'seo',
  ];
  return $map[$rel] ?? null;
}

// Normalizza una email.
function nova_norm_email(string $e): string {
  return strtolower(trim($e));
}

// Recupera un utente attivo per email.
function nova_utente_per_email(string $email): ?array {
  $pdo = nova_db();
  if (!$pdo) return null;
  $st = $pdo->prepare('SELECT * FROM utenti WHERE email = ? AND attivo = 1');
  $st->execute([nova_norm_email($email)]);
  $u = $st->fetch();
  return $u ?: null;
}

// Seed del primo admin dal config (migra il login attuale di Fabio).
// Gira solo se la tabella è vuota → la password resta quella di oggi.
function nova_seed_admin(array $cfg): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $n = (int)$pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn();
  if ($n > 0) return;

  $email = nova_norm_email((string)($cfg['admin_email'] ?? ''));
  $hash  = (string)($cfg['password_hash'] ?? '');
  if ($email === '' || $hash === '') return;

  $st = $pdo->prepare(
    'INSERT INTO utenti (email, pass_hash, is_admin, sezioni, attivo, creato_il)
     VALUES (?, ?, 1, ?, 1, ?)'
  );
  $st->execute([$email, $hash, json_encode(NOVA_SEZIONI), time()]);
}

// Sezioni permesse per un utente (admin = tutte).
function nova_sezioni_utente(array $user): array {
  if (!empty($user['is_admin'])) return NOVA_SEZIONI;
  $s = json_decode((string)($user['sezioni'] ?? '[]'), true);
  return is_array($s) ? array_values(array_intersect($s, NOVA_SEZIONI)) : [];
}

// L'utente può accedere alla sezione?
function nova_puo(array $user, string $sezione): bool {
  if (!empty($user['is_admin'])) return true;
  return in_array($sezione, nova_sezioni_utente($user), true);
}

// ── CRUD usato dal pannello admin ──────────────────────────────
function nova_lista_utenti(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query('SELECT * FROM utenti ORDER BY is_admin DESC, email ASC')->fetchAll();
}

function nova_crea_utente(string $email, string $password, array $sezioni, bool $isAdmin): array {
  $pdo = nova_db();
  if (!$pdo) return [false, 'Store utenti non disponibile.'];
  $email = nova_norm_email($email);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Email non valida.'];
  if (strlen($password) < 10) return [false, 'La password deve avere almeno 10 caratteri.'];
  $sez = array_values(array_intersect($sezioni, NOVA_SEZIONI));
  try {
    $st = $pdo->prepare(
      'INSERT INTO utenti (email, pass_hash, is_admin, sezioni, attivo, creato_il)
       VALUES (?, ?, ?, ?, 1, ?)'
    );
    $st->execute([
      $email,
      password_hash($password, PASSWORD_DEFAULT),
      $isAdmin ? 1 : 0,
      json_encode($sez),
      time(),
    ]);
    return [true, 'Utente creato.'];
  } catch (Throwable $e) {
    return [false, 'Email già esistente.'];
  }
}

function nova_aggiorna_sezioni(int $id, array $sezioni, bool $isAdmin): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $sez = array_values(array_intersect($sezioni, NOVA_SEZIONI));
  $st = $pdo->prepare('UPDATE utenti SET sezioni = ?, is_admin = ? WHERE id = ?');
  $st->execute([json_encode($sez), $isAdmin ? 1 : 0, $id]);
}

function nova_imposta_attivo(int $id, bool $attivo): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $st = $pdo->prepare('UPDATE utenti SET attivo = ? WHERE id = ?');
  $st->execute([$attivo ? 1 : 0, $id]);
}

function nova_imposta_password(string $email, string $password): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  if (strlen($password) < 10) return false;
  $st = $pdo->prepare('UPDATE utenti SET pass_hash = ? WHERE email = ?');
  $st->execute([password_hash($password, PASSWORD_DEFAULT), nova_norm_email($email)]);
  return $st->rowCount() > 0;
}

// Quanti admin attivi restano (per non sospendere/declassare l'ultimo).
function nova_conta_admin_attivi(?int $esclusoId = null): int {
  $pdo = nova_db();
  if (!$pdo) return 0;
  if ($esclusoId !== null) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM utenti WHERE is_admin = 1 AND attivo = 1 AND id <> ?');
    $st->execute([$esclusoId]);
    return (int)$st->fetchColumn();
  }
  return (int)$pdo->query('SELECT COUNT(*) FROM utenti WHERE is_admin = 1 AND attivo = 1')->fetchColumn();
}
