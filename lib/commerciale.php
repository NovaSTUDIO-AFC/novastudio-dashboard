<?php
// ───────────────────────────────────────────────────────────────
// REPARTO COMMERCIALE — coda di approvazione prospect outbound.
// Il motore (qualify) trova aziende e-commerce idonee con email
// role-based; la dashboard le mostra con l'email gia' compilata.
// Fabio approva / chiede modifiche / rifiuta. Approvato = pronto
// per l'invio (lo SMTP resta spento finche' non lo si collega).
// Seed iniziale da data/commerciale-seed.json (generato dal dry-run).
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const NOVA_PROSPECT_STATI = ['da_approvare', 'approvato', 'modifiche', 'rifiutato'];

// Lista prospect: prima da approvare, poi modifiche, approvati, rifiutati.
function nova_prospect_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query(
    "SELECT * FROM prospect ORDER BY
       CASE stato WHEN 'da_approvare' THEN 0 WHEN 'modifiche' THEN 1
                  WHEN 'approvato' THEN 2 ELSE 3 END,
       creata_il DESC"
  )->fetchAll();
}

// Aggiorna stato + commento di Fabio su un prospect.
function nova_prospect_aggiorna(int $id, string $stato, string $commento): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  if (!in_array($stato, NOVA_PROSPECT_STATI, true)) return false;
  if (mb_strlen($commento) > 4000) $commento = mb_substr($commento, 0, 4000);
  $st = $pdo->prepare('UPDATE prospect SET stato = ?, commento = ?, aggiornata_il = ? WHERE id = ?');
  $st->execute([$stato, $commento, time(), $id]);
  return true;
}

// Seed: se la tabella e' vuota, carica i prospect del dry-run da JSON.
function nova_prospect_seed(): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $n = (int)$pdo->query('SELECT COUNT(*) FROM prospect')->fetchColumn();
  if ($n > 0) return;
  $file = __DIR__ . '/../data/commerciale-seed.json';
  if (!is_file($file)) return;
  $items = json_decode((string)@file_get_contents($file), true);
  if (!is_array($items)) return;
  $st = $pdo->prepare(
    'INSERT OR IGNORE INTO prospect
       (company_id, company, domain, email, email_role_based, enrich, lingua, lever,
        subject, corpo, note, stato, creata_il)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
  );
  $t = time();
  foreach (array_values($items) as $i => $p) {
    if (!is_array($p)) continue;
    $st->execute([
      (string)($p['company_id'] ?? ''),
      (string)($p['company'] ?? ''),
      (string)($p['domain'] ?? ''),
      (string)($p['email'] ?? ''),
      !empty($p['email_role_based']) ? 1 : 0,
      (string)($p['enrich'] ?? ''),
      (string)($p['lingua'] ?? 'EN'),
      (string)($p['lever'] ?? ''),
      (string)($p['subject'] ?? ''),
      (string)($p['corpo'] ?? ''),
      (string)($p['note'] ?? ''),
      'da_approvare',
      $t - $i, // ordine stabile
    ]);
  }
}
