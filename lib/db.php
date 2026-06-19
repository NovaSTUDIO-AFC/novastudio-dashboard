<?php
// ───────────────────────────────────────────────────────────────
// STORE UTENTI — SQLite (PDO).
// Vive in data/users.db: FUORI da git e NON sovrascritto dal deploy
// (vedi .gitignore + esclusione in deploy.sh) → persiste sul server.
//
// Se SQLite non è disponibile sul piano hosting, nova_db() ritorna
// null e il front controller torna automaticamente all'auth legacy
// (utente singolo da config.php). Così non si resta MAI chiusi fuori.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);

function nova_db(): ?PDO {
  static $pdo   = null;
  static $tried = false;
  if ($tried) return $pdo;
  $tried = true;

  if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    return $pdo = null; // SQLite non disponibile → fallback legacy
  }

  $dir = __DIR__ . '/../data';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);

  try {
    $pdo = new PDO('sqlite:' . $dir . '/users.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    nova_db_init($pdo);
  } catch (Throwable $e) {
    $pdo = null; // qualunque problema → fallback sicuro
  }
  return $pdo;
}

// Crea lo schema se manca (idempotente).
function nova_db_init(PDO $pdo): void {
  $pdo->exec('CREATE TABLE IF NOT EXISTS utenti (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    email     TEXT    NOT NULL UNIQUE,
    pass_hash TEXT    NOT NULL,
    is_admin  INTEGER NOT NULL DEFAULT 0,
    sezioni   TEXT    NOT NULL DEFAULT "[]",  -- JSON array di sezioni permesse
    attivo    INTEGER NOT NULL DEFAULT 1,
    creato_il INTEGER NOT NULL
  )');

  $pdo->exec('CREATE TABLE IF NOT EXISTS reset_token (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT    NOT NULL,
    token_hash TEXT    NOT NULL,              -- sha256 del token (mai il token in chiaro)
    scade_il   INTEGER NOT NULL,
    usato      INTEGER NOT NULL DEFAULT 0,
    creato_il  INTEGER NOT NULL
  )');
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reset_hash ON reset_token(token_hash)');

  // Attività / "Da fare": stato VOLATILE (si aggiunge e si spunta) → vive qui
  // nello store, NON in git. La dashboard la disegna come fa col catalogo.
  $pdo->exec('CREATE TABLE IF NOT EXISTS attivita (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    testo     TEXT    NOT NULL,
    fatta     INTEGER NOT NULL DEFAULT 0,
    creata_il INTEGER NOT NULL,
    fatta_il  INTEGER
  )');
}
