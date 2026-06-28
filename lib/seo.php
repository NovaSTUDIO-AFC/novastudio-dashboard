<?php
// ───────────────────────────────────────────────────────────────
// REPARTO SEO — raccomandazioni di ottimizzazione per Simracing.Fan.
// La dashboard disegna le raccomandazioni (prodotte dall'Head SEO dal
// dry-run sul sito live); Fabio le approva / rifiuta con un commento.
// Stato e commenti vivono nello store SQLite (non in git): il reparto
// li legge per procedere. In piu Fabio puo lasciare un FEEDBACK che
// indirizza i prossimi giri (competitor da monitorare, keyword da
// escludere, pagine da privilegiare). Seed iniziale da data/seo-seed.json.
// Stesso pattern di lib/job.php e lib/redazione.php.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const NOVA_SEO_STATI    = ['da_decidere', 'approvata', 'rifiutata', 'implementata'];
const NOVA_SEO_PRIORITA = ['alto', 'medio', 'basso'];

// Lista raccomandazioni: prima da decidere, poi approvate, poi
// implementate, infine rifiutate. Dentro ogni gruppo: priorita alta
// prima, poi ordine di inserimento.
function nova_seo_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query(
    "SELECT * FROM seo_raccomandazioni ORDER BY
       CASE stato WHEN 'da_decidere' THEN 0 WHEN 'approvata' THEN 1
                  WHEN 'implementata' THEN 2 ELSE 3 END,
       CASE priorita WHEN 'alto' THEN 0 WHEN 'medio' THEN 1 ELSE 2 END,
       ordine ASC"
  )->fetchAll();
}

// Aggiorna stato + commento di Fabio su una raccomandazione.
function nova_seo_aggiorna(int $id, string $stato, string $commento): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  if (!in_array($stato, NOVA_SEO_STATI, true)) return false;
  if (mb_strlen($commento) > 4000) $commento = mb_substr($commento, 0, 4000);
  $st = $pdo->prepare('UPDATE seo_raccomandazioni SET stato = ?, commento = ?, aggiornata_il = ? WHERE id = ?');
  $st->execute([$stato, $commento, time(), $id]);
  return true;
}

// Feedback che indirizza i PROSSIMI giri del reparto (append-only).
function nova_seo_feedback_aggiungi(string $testo): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  $testo = trim($testo);
  if ($testo === '') return false;
  if (mb_strlen($testo) > 4000) $testo = mb_substr($testo, 0, 4000);
  $st = $pdo->prepare('INSERT INTO seo_feedback (testo, creato_il) VALUES (?, ?)');
  $st->execute([$testo, time()]);
  return true;
}

function nova_seo_feedback_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query('SELECT * FROM seo_feedback ORDER BY creato_il DESC LIMIT 50')->fetchAll();
}

// Sync da JSON (idempotente per slug). Inserisce le raccomandazioni nuove
// e aggiorna contenuto + ordine di quelle esistenti, SENZA toccare stato e
// commento (le decisioni di Fabio). Cosi aggiungere raccomandazioni =
// editare il JSON: le approvazioni gia date restano, le novita compaiono
// come "da decidere". Il JSON e la fonte di verita per contenuto e ordine.
function nova_seo_seed(): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $file = __DIR__ . '/../data/seo-seed.json';
  if (!is_file($file)) return;
  $items = json_decode((string)@file_get_contents($file), true);
  if (!is_array($items)) return;

  // UPSERT (SQLite >=3.24): inserisce le nuove, aggiorna contenuto+ordine
  // delle esistenti. stato/commento/aggiornata_il NON sono nel DO UPDATE →
  // le decisioni di Fabio restano intatte.
  $up = $pdo->prepare(
    'INSERT INTO seo_raccomandazioni
       (slug, titolo, url, tipo, priorita, descrizione, azione_suggerita, stato, ordine, creata_il)
     VALUES (?,?,?,?,?,?,?,?,?,?)
     ON CONFLICT(slug) DO UPDATE SET
       titolo=excluded.titolo, url=excluded.url, tipo=excluded.tipo, priorita=excluded.priorita,
       descrizione=excluded.descrizione, azione_suggerita=excluded.azione_suggerita, ordine=excluded.ordine'
  );
  $t = time();
  foreach (array_values($items) as $i => $r) {
    if (!is_array($r) || empty($r['slug'])) continue;
    $prio = (string)($r['priorita'] ?? 'medio');
    if (!in_array($prio, NOVA_SEO_PRIORITA, true)) $prio = 'medio';
    $up->execute([
      (string)$r['slug'],
      (string)($r['titolo'] ?? ''),
      (string)($r['url'] ?? ''),
      (string)($r['tipo'] ?? ''),
      $prio,
      (string)($r['descrizione'] ?? ''),
      (string)($r['azione_suggerita'] ?? ''),
      'da_decidere',
      $i,
      $t,
    ]);
  }

  // Rimuovi le raccomandazioni non piu nel JSON, MA solo quelle ancora "da
  // decidere": cio che Fabio ha approvato/rifiutato/implementato resta sempre.
  $slugs = array_values(array_filter(array_map(
    fn($r) => is_array($r) ? (string)($r['slug'] ?? '') : '',
    $items
  )));
  if ($slugs) {
    $ph = implode(',', array_fill(0, count($slugs), '?'));
    $del = $pdo->prepare("DELETE FROM seo_raccomandazioni WHERE stato = 'da_decidere' AND slug NOT IN ($ph)");
    $del->execute($slugs);
  }
}
