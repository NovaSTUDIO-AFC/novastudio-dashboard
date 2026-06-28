<?php
// ───────────────────────────────────────────────────────────────
// REPARTO JOB — opportunità di lavoro per Fabio.
// La dashboard disegna le opportunità (selezionate e valutate dal
// reparto); Fabio approva la prossima mossa suggerita / la tiene
// d'occhio / la scarta, lasciando un commento. Stato e commenti
// vivono nello store SQLite (non in git): il reparto li legge per
// procedere. In più Fabio può lasciare un FEEDBACK che indirizza le
// ricerche successive. Seed iniziale da data/job-seed.json.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const NOVA_JOB_STATI = ['da_decidere', 'approvata', 'tieni', 'scartata', 'candidata'];

// Lista opportunità: prima da decidere, poi approvate, poi tenute
// d'occhio, infine scartate. Dentro ogni gruppo: match più alto prima.
function nova_job_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query(
    "SELECT * FROM job_opportunita ORDER BY
       CASE stato WHEN 'da_decidere' THEN 0 WHEN 'approvata' THEN 1
                  WHEN 'tieni' THEN 2 ELSE 3 END,
       ordine ASC"
  )->fetchAll();
}

// Aggiorna stato + commento di Fabio su un'opportunità.
function nova_job_aggiorna(int $id, string $stato, string $commento): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  if (!in_array($stato, NOVA_JOB_STATI, true)) return false;
  if (mb_strlen($commento) > 4000) $commento = mb_substr($commento, 0, 4000);
  $st = $pdo->prepare('UPDATE job_opportunita SET stato = ?, commento = ?, aggiornata_il = ? WHERE id = ?');
  $st->execute([$stato, $commento, time(), $id]);
  return true;
}

// Feedback che indirizza le RICERCHE successive (append-only).
function nova_job_feedback_aggiungi(string $testo): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  $testo = trim($testo);
  if ($testo === '') return false;
  if (mb_strlen($testo) > 4000) $testo = mb_substr($testo, 0, 4000);
  $st = $pdo->prepare('INSERT INTO job_ricerca_feedback (testo, creato_il) VALUES (?, ?)');
  $st->execute([$testo, time()]);
  return true;
}

function nova_job_feedback_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query('SELECT * FROM job_ricerca_feedback ORDER BY creato_il DESC LIMIT 50')->fetchAll();
}

// Sync da JSON (idempotente per slug). Inserisce le opportunità nuove e
// aggiorna contenuto + ordine di quelle esistenti, SENZA toccare stato e
// commento (le decisioni di Fabio). Così aggiungere offerte = editare il
// JSON: le approvazioni già date restano, le novità compaiono come "da
// decidere". Il JSON è la fonte di verità per contenuto e ordine.
function nova_job_seed(): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $file = __DIR__ . '/../data/job-seed.json';
  if (!is_file($file)) return;
  $items = json_decode((string)@file_get_contents($file), true);
  if (!is_array($items)) return;

  // UPSERT (SQLite >=3.24): inserisce le nuove, aggiorna contenuto+ordine
  // delle esistenti. stato/commento/aggiornata_il NON sono nel DO UPDATE →
  // le decisioni di Fabio restano intatte.
  $up = $pdo->prepare(
    'INSERT INTO job_opportunita
       (slug, nome, azienda, ruolo, link, formato, match_stelle, ral, descrizione, valutazione, suggerimento, materiali, allegati,
        top, fit_profilo, fit_preferenze, parttime, stato, ordine, creata_il)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON CONFLICT(slug) DO UPDATE SET
       nome=excluded.nome, azienda=excluded.azienda, ruolo=excluded.ruolo, link=excluded.link,
       formato=excluded.formato, match_stelle=excluded.match_stelle, ral=excluded.ral,
       descrizione=excluded.descrizione, valutazione=excluded.valutazione, suggerimento=excluded.suggerimento,
       materiali=excluded.materiali, allegati=excluded.allegati, top=excluded.top, fit_profilo=excluded.fit_profilo,
       fit_preferenze=excluded.fit_preferenze, parttime=excluded.parttime, ordine=excluded.ordine'
  );
  $t = time();
  foreach (array_values($items) as $i => $o) {
    if (!is_array($o) || empty($o['slug'])) continue;
    $slug = (string)$o['slug'];
    $vals = [
      (string)($o['nome'] ?? ''),
      (string)($o['azienda'] ?? ''),
      (string)($o['ruolo'] ?? ''),
      (string)($o['link'] ?? ''),
      (string)($o['formato'] ?? ''),
      (string)($o['match'] ?? ''),
      (string)($o['ral'] ?? ''),
      (string)($o['descrizione'] ?? ''),
      (string)($o['valutazione'] ?? ''),
      (string)($o['suggerimento'] ?? ''),
      (string)($o['materiali'] ?? ''),
      isset($o['allegati']) && is_array($o['allegati'])
        ? json_encode($o['allegati'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : (string)($o['allegati'] ?? ''),
    ];
    $metrics = [
      !empty($o['top']) ? 1 : 0,
      (int)($o['fit_profilo'] ?? 0),
      (int)($o['fit_preferenze'] ?? 0),
      !empty($o['parttime']) ? 1 : 0,
    ];
    $up->execute(array_merge([$slug], $vals, $metrics, ['da_decidere', $i, $t]));
  }

  // Rimuovi le opportunità non più nel JSON, MA solo quelle ancora "da
  // decidere": ciò che Fabio ha approvato/tenuto/scartato resta sempre.
  $slugs = array_values(array_filter(array_map(
    fn($o) => is_array($o) ? (string)($o['slug'] ?? '') : '',
    $items
  )));
  if ($slugs) {
    $ph = implode(',', array_fill(0, count($slugs), '?'));
    $del = $pdo->prepare("DELETE FROM job_opportunita WHERE stato = 'da_decidere' AND slug NOT IN ($ph)");
    $del->execute($slugs);
  }
}
