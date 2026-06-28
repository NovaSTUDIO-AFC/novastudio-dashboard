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

  // Reparto Redazione: coda di approvazione delle bozze. Lo stato (approvato/
  // modifiche/rifiutato) e il commento di Fabio vivono qui nello store; il
  // reparto li legge per la rilavorazione e il loop di miglioramento.
  $pdo->exec("CREATE TABLE IF NOT EXISTS bozze (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    slug          TEXT    NOT NULL UNIQUE,
    titolo_en     TEXT    NOT NULL,
    corpo_en      TEXT    NOT NULL,
    corpo_it      TEXT    NOT NULL,
    brief_img     TEXT,
    fonte         TEXT,
    categoria     TEXT,
    asana_gid     TEXT,
    stato         TEXT    NOT NULL DEFAULT 'da_approvare',
    commento      TEXT,
    creata_il     INTEGER NOT NULL,
    aggiornata_il INTEGER
  )");

  // Migrazione idempotente: colonna immagine (URL/path della featured image).
  $cols = $pdo->query("PRAGMA table_info(bozze)")->fetchAll();
  $hasImg = false;
  foreach ($cols as $c) { if (($c['name'] ?? '') === 'immagine') { $hasImg = true; break; } }
  if (!$hasImg) {
    try { $pdo->exec('ALTER TABLE bozze ADD COLUMN immagine TEXT'); } catch (Throwable $e) {}
  }

  // Storico passaggi della pipeline per ogni bozza (scheda articolo).
  $pdo->exec('CREATE TABLE IF NOT EXISTS bozza_passaggi (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    bozza_id  INTEGER NOT NULL,
    ord       INTEGER NOT NULL,
    passo     TEXT    NOT NULL,
    esito     TEXT,
    nota      TEXT,
    creata_il INTEGER NOT NULL
  )');
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_passaggi_bozza ON bozza_passaggi(bozza_id)');

  // Reparto Job: opportunità di lavoro selezionate e valutate. Lo stato
  // (approvata/tieni/scartata) e il commento di Fabio vivono qui nello
  // store; il reparto li legge per procedere con la mossa approvata.
  $pdo->exec("CREATE TABLE IF NOT EXISTS job_opportunita (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    slug          TEXT    NOT NULL UNIQUE,
    nome          TEXT    NOT NULL,
    azienda       TEXT,
    ruolo         TEXT,
    link          TEXT,
    formato       TEXT,
    match_stelle  TEXT,
    ral           TEXT,
    descrizione   TEXT,
    valutazione   TEXT,
    suggerimento  TEXT,
    stato         TEXT    NOT NULL DEFAULT 'da_decidere',
    commento      TEXT,
    ordine        INTEGER NOT NULL DEFAULT 0,
    top           INTEGER NOT NULL DEFAULT 0,   -- 1 = tra le best 3 in evidenza
    fit_profilo   INTEGER NOT NULL DEFAULT 0,   -- % attinenza col profilo (competenze)
    fit_preferenze INTEGER NOT NULL DEFAULT 0,  -- % match con le preferenze (remoto/part-time/salario/eleggibilità)
    parttime      INTEGER NOT NULL DEFAULT 0,   -- 1 = part-time
    materiali     TEXT,                         -- materiali personalizzati pronti (CV+cover) da revisionare
    allegati      TEXT,                         -- JSON array [{label,file}] di allegati scaricabili (PDF)
    creata_il     INTEGER NOT NULL,
    aggiornata_il INTEGER
  )");

  // Migrazione idempotente: aggiunge le colonne nuove ai DB già esistenti.
  $jobCols = array_column($pdo->query("PRAGMA table_info(job_opportunita)")->fetchAll(), 'name');
  foreach (['top' => 'INTEGER NOT NULL DEFAULT 0',
            'fit_profilo' => 'INTEGER NOT NULL DEFAULT 0',
            'fit_preferenze' => 'INTEGER NOT NULL DEFAULT 0',
            'parttime' => 'INTEGER NOT NULL DEFAULT 0',
            'materiali' => 'TEXT',
            'allegati' => 'TEXT'] as $col => $type) {
    if (!in_array($col, $jobCols, true)) {
      try { $pdo->exec("ALTER TABLE job_opportunita ADD COLUMN $col $type"); } catch (Throwable $e) {}
    }
  }

  // Feedback di Fabio che indirizza le RICERCHE successive (append-only):
  // il reparto li legge per affinare i criteri delle prossime ricerche.
  $pdo->exec('CREATE TABLE IF NOT EXISTS job_ricerca_feedback (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    testo     TEXT    NOT NULL,
    creato_il INTEGER NOT NULL
  )');

  // ── REPARTO SEO: raccomandazioni di ottimizzazione (coda approvazione) ──
  $pdo->exec("CREATE TABLE IF NOT EXISTS seo_raccomandazioni (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    slug             TEXT    NOT NULL UNIQUE,
    titolo           TEXT    NOT NULL,
    url              TEXT    NOT NULL DEFAULT '',
    tipo             TEXT    NOT NULL DEFAULT '',
    priorita         TEXT    NOT NULL DEFAULT 'medio',
    descrizione      TEXT    NOT NULL DEFAULT '',
    azione_suggerita TEXT    NOT NULL DEFAULT '',
    stato            TEXT    NOT NULL DEFAULT 'da_decidere',
    commento         TEXT    NOT NULL DEFAULT '',
    ordine           INTEGER NOT NULL DEFAULT 0,
    creata_il        INTEGER NOT NULL,
    aggiornata_il    INTEGER
  )");

  // Feedback di Fabio che indirizza i PROSSIMI giri del reparto (append-only).
  $pdo->exec('CREATE TABLE IF NOT EXISTS seo_feedback (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    testo     TEXT    NOT NULL,
    creato_il INTEGER NOT NULL
  )');

  // ── GIUNTO SEO ↔ REDAZIONE: "pezzo" in pipeline a due gate ─────────────
  $pdo->exec("CREATE TABLE IF NOT EXISTS pezzo (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    slug                 TEXT    NOT NULL UNIQUE,
    titolo               TEXT    NOT NULL,
    origine              TEXT    NOT NULL DEFAULT 'redazione',  -- redazione|seo
    tipo                 TEXT    NOT NULL DEFAULT 'post',       -- post|risorsa|pagina
    fonte                TEXT    NOT NULL DEFAULT '',
    keyword_target       TEXT    NOT NULL DEFAULT '',
    search_intent        TEXT    NOT NULL DEFAULT '',
    discover             INTEGER NOT NULL DEFAULT 0,
    come_spec            TEXT    NOT NULL DEFAULT '',           -- spec COME del Reparto SEO
    gate_seo             TEXT    NOT NULL DEFAULT 'in_attesa',  -- in_attesa|ok|no
    gate_seo_nota        TEXT    NOT NULL DEFAULT '',
    gate_rilevanza       TEXT    NOT NULL DEFAULT 'in_attesa',  -- in_attesa|ok|no
    gate_rilevanza_nota  TEXT    NOT NULL DEFAULT '',
    stato                TEXT    NOT NULL DEFAULT 'in_valutazione', -- in_valutazione|in_pipeline|scartato|promosso
    commento             TEXT    NOT NULL DEFAULT '',
    testo                TEXT    NOT NULL DEFAULT '',           -- testo dell'articolo (pregresso) per la validazione
    bozza_slug           TEXT    NOT NULL DEFAULT '',           -- link alla bozza (coda approvazioni) dopo promozione
    ordine               INTEGER NOT NULL DEFAULT 0,
    creata_il            INTEGER NOT NULL,
    aggiornata_il        INTEGER
  )");
  // Migrazioni idempotenti per i DB pezzo gia esistenti (staging/prod).
  foreach (['testo' => 'TEXT', 'bozza_slug' => 'TEXT'] as $col => $type) {
    $has = false;
    foreach ($pdo->query('PRAGMA table_info(pezzo)') as $c) { if (($c['name'] ?? '') === $col) { $has = true; break; } }
    if (!$has) { try { $pdo->exec("ALTER TABLE pezzo ADD COLUMN $col $type NOT NULL DEFAULT ''"); } catch (Throwable $e) {} }
  }

  // ── REPARTO COMMERCIALE: coda approvazione prospect outbound ───────────
  // Il motore (qualify) produce i prospect idonei con email role-based; Fabio
  // approva qui PRIMA di ogni invio. Approvato = pronto per l'invio (che resta
  // gated/spento finche' non si collega lo SMTP). Stato e commento nello store.
  $pdo->exec("CREATE TABLE IF NOT EXISTS prospect (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id        TEXT    NOT NULL DEFAULT '',
    company           TEXT    NOT NULL,
    domain            TEXT    NOT NULL DEFAULT '',
    email             TEXT    NOT NULL DEFAULT '',
    email_role_based  INTEGER NOT NULL DEFAULT 0,
    enrich            TEXT    NOT NULL DEFAULT '',           -- sorgente email (site/apollo/record)
    lingua            TEXT    NOT NULL DEFAULT 'EN',
    lever             TEXT    NOT NULL DEFAULT '',
    subject           TEXT    NOT NULL DEFAULT '',
    corpo             TEXT    NOT NULL DEFAULT '',           -- email gia' compilata per l'approvazione
    note              TEXT    NOT NULL DEFAULT '',
    stato             TEXT    NOT NULL DEFAULT 'da_approvare', -- da_approvare|approvato|modifiche|rifiutato
    commento          TEXT    NOT NULL DEFAULT '',
    creata_il         INTEGER NOT NULL,
    aggiornata_il     INTEGER,
    UNIQUE(company_id, domain)
  )");
}
