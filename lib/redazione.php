<?php
// ───────────────────────────────────────────────────────────────
// REPARTO REDAZIONE — coda di approvazione bozze.
// La dashboard disegna le bozze; Fabio approva / chiede modifiche /
// rifiuta lasciando un commento. Stato e commenti vivono nello store
// SQLite (non in git): il reparto li legge per la rilavorazione.
// Seed iniziale da data/bozze-seed.json (generato dal dry-run).
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const NOVA_BOZZA_STATI = ['da_approvare', 'approvato', 'modifiche', 'rifiutato'];

// Lista bozze: prima quelle da approvare, poi modifiche, approvate, rifiutate.
// Ogni bozza porta con sé il suo storico passaggi (scheda articolo).
function nova_bozze_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  $bozze = $pdo->query(
    "SELECT * FROM bozze ORDER BY
       CASE stato WHEN 'da_approvare' THEN 0 WHEN 'modifiche' THEN 1
                  WHEN 'approvato' THEN 2 ELSE 3 END,
       creata_il DESC"
  )->fetchAll();
  foreach ($bozze as &$b) {
    $b['passaggi'] = nova_passaggi_bozza((int)$b['id']);
  }
  return $bozze;
}

// Storico passaggi di una bozza (ordinati).
function nova_passaggi_bozza(int $bozzaId): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  $st = $pdo->prepare('SELECT ord, passo, esito, nota FROM bozza_passaggi WHERE bozza_id = ? ORDER BY ord ASC');
  $st->execute([$bozzaId]);
  return $st->fetchAll();
}

// Aggiorna stato + commento di Fabio su una bozza.
function nova_bozza_aggiorna(int $id, string $stato, string $commento): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  if (!in_array($stato, NOVA_BOZZA_STATI, true)) return false;
  if (mb_strlen($commento) > 4000) $commento = mb_substr($commento, 0, 4000);
  $st = $pdo->prepare('UPDATE bozze SET stato = ?, commento = ?, aggiornata_il = ? WHERE id = ?');
  $st->execute([$stato, $commento, time(), $id]);
  return true;
}

// Seed: se la tabella è vuota, carica le bozze del dry-run da JSON.
function nova_bozze_seed(): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $n = (int)$pdo->query('SELECT COUNT(*) FROM bozze')->fetchColumn();
  if ($n > 0) return;
  $file = __DIR__ . '/../data/bozze-seed.json';
  if (!is_file($file)) return;
  $items = json_decode((string)@file_get_contents($file), true);
  if (!is_array($items)) return;
  $st = $pdo->prepare(
    'INSERT OR IGNORE INTO bozze
       (slug, titolo_en, corpo_en, corpo_it, brief_img, fonte, categoria, asana_gid, stato, creata_il)
     VALUES (?,?,?,?,?,?,?,?,?,?)'
  );
  $t = time();
  foreach (array_values($items) as $i => $b) {
    if (!is_array($b)) continue;
    $st->execute([
      (string)($b['slug'] ?? ('bozza-' . $i)),
      (string)($b['titolo_en'] ?? ''),
      (string)($b['corpo_en'] ?? ''),
      (string)($b['corpo_it'] ?? ''),
      (string)($b['brief_img'] ?? ''),
      (string)($b['fonte'] ?? ''),
      (string)($b['categoria'] ?? ''),
      (string)($b['asana_gid'] ?? ''),
      'da_approvare',
      $t - $i, // ordine stabile
    ]);
  }
}

// Seed dei passaggi (storico): indipendente dal seed bozze, così popola anche
// ambienti in cui le bozze esistono già. Matcha per slug.
function nova_passaggi_seed(): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $n = (int)$pdo->query('SELECT COUNT(*) FROM bozza_passaggi')->fetchColumn();
  if ($n > 0) return;
  $file = __DIR__ . '/../data/bozze-seed.json';
  if (!is_file($file)) return;
  $items = json_decode((string)@file_get_contents($file), true);
  if (!is_array($items)) return;
  $find = $pdo->prepare('SELECT id FROM bozze WHERE slug = ?');
  $ins = $pdo->prepare(
    'INSERT INTO bozza_passaggi (bozza_id, ord, passo, esito, nota, creata_il) VALUES (?,?,?,?,?,?)'
  );
  $t = time();
  foreach ($items as $b) {
    if (empty($b['slug']) || empty($b['passaggi']) || !is_array($b['passaggi'])) continue;
    $find->execute([(string)$b['slug']]);
    $bid = $find->fetchColumn();
    if (!$bid) continue;
    foreach ($b['passaggi'] as $p) {
      $ins->execute([
        (int)$bid,
        (int)($p['ord'] ?? 0),
        (string)($p['passo'] ?? ''),
        (string)($p['esito'] ?? ''),
        (string)($p['nota'] ?? ''),
        $t,
      ]);
    }
  }
}
