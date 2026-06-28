<?php
// ───────────────────────────────────────────────────────────────
// GIUNTO SEO ↔ REDAZIONE — "Pezzo" in pipeline (due gate).
// Un pezzo (post/risorsa/pagina) nasce in Redazione (News Scout trova
// una notizia) OPPURE in SEO (scova una nicchia/topic). Per entrare in
// scrittura deve superare DUE gate:
//   - gate SEO        (Reparto SEO):      utile per ranking organico + AI/LLM? + spec COME.
//   - gate Rilevanza  (Redazione):        pertinente per la nicchia Simracing?
// Due "ok" → entra nella trafila Redazione (scrittura → bozza → 🔒 Fabio → pubblica).
// Un "no" su un gate → scartato. Tutto vive in dashboard (niente Asana).
// Stesso pattern di seo.php / job.php. Seed da data/pezzo-seed.json.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const NOVA_PEZZO_GATE   = ['in_attesa', 'ok', 'no'];   // esito di un gate
const NOVA_PEZZO_ORIGINI= ['redazione', 'seo'];
const NOVA_PEZZO_TIPI   = ['post', 'risorsa', 'pagina'];

// Stato derivato dai due gate (single source of truth = i due gate).
function nova_pezzo_stato_da_gate(string $seo, string $ril): string {
  if ($seo === 'no' || $ril === 'no')      return 'scartato';
  if ($seo === 'ok' && $ril === 'ok')      return 'in_pipeline'; // pronto per la scrittura
  return 'in_valutazione';
}

// Lista: prima quelli in valutazione (serve una decisione), poi in pipeline,
// infine scartati. Dentro: i piu recenti prima.
function nova_pezzo_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query(
    "SELECT * FROM pezzo ORDER BY
       CASE stato WHEN 'in_valutazione' THEN 0 WHEN 'in_pipeline' THEN 1 ELSE 2 END,
       ordine ASC, creata_il DESC"
  )->fetchAll();
}

// Imposta un gate ('seo' o 'rilevanza') a ok|no|in_attesa, con nota, e
// ricalcola lo stato. Ritorna false se input non valido.
function nova_pezzo_set_gate(int $id, string $gate, string $esito, string $nota): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  if (!in_array($esito, NOVA_PEZZO_GATE, true)) return false;
  if ($gate !== 'seo' && $gate !== 'rilevanza') return false;
  if (mb_strlen($nota) > 4000) $nota = mb_substr($nota, 0, 4000);

  $row = $pdo->prepare('SELECT gate_seo, gate_rilevanza FROM pezzo WHERE id = ?');
  $row->execute([$id]);
  $cur = $row->fetch();
  if (!$cur) return false;

  $seo = $gate === 'seo'       ? $esito : (string)$cur['gate_seo'];
  $ril = $gate === 'rilevanza' ? $esito : (string)$cur['gate_rilevanza'];
  $stato = nova_pezzo_stato_da_gate($seo, $ril);

  $col  = $gate === 'seo' ? 'gate_seo' : 'gate_rilevanza';
  $cnota= $gate === 'seo' ? 'gate_seo_nota' : 'gate_rilevanza_nota';
  $st = $pdo->prepare("UPDATE pezzo SET $col = ?, $cnota = ?, stato = ?, aggiornata_il = ? WHERE id = ?");
  $st->execute([$esito, $nota, $stato, time(), $id]);
  return true;
}

// Promuove un pezzo VALIDATO (stato in_pipeline) alla coda approvazioni:
// crea/collega una bozza (tabella bozze) col testo dell'articolo, pronta per
// la rielaborazione della Redazione secondo la come_spec e l'approvazione di
// Fabio. Se la bozza esiste gia (stesso slug), la collega senza duplicare.
// Idempotente. Ritorna false se il pezzo non e in_pipeline.
function nova_pezzo_promuovi(int $id): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  $r = $pdo->prepare('SELECT * FROM pezzo WHERE id = ?');
  $r->execute([$id]);
  $p = $r->fetch();
  if (!$p || $p['stato'] !== 'in_pipeline') return false;

  $slug = (string)$p['slug'];
  // categoria della bozza = tipo del pezzo (post|risorsa|pagina)
  $esiste = $pdo->prepare('SELECT id FROM bozze WHERE slug = ?');
  $esiste->execute([$slug]);
  if (!$esiste->fetchColumn()) {
    $ins = $pdo->prepare(
      'INSERT OR IGNORE INTO bozze
         (slug, titolo_en, corpo_en, corpo_it, brief_img, fonte, categoria, asana_gid, stato, creata_il)
       VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $ins->execute([
      $slug,
      (string)$p['titolo'],
      (string)$p['testo'],                 // il testo dell'articolo (pregresso) o vuoto (idea da scrivere)
      '',                                   // versione IT: la prepara la Redazione in rielaborazione
      '',                                   // brief_img: lo cura il Visual
      (string)$p['fonte'],
      (string)$p['tipo'],
      '',
      'da_approvare',
      time(),
    ]);
  }
  // collega e marca il pezzo come promosso
  $pdo->prepare('UPDATE pezzo SET stato = ?, bozza_slug = ?, aggiornata_il = ? WHERE id = ?')
      ->execute(['promosso', $slug, time(), $id]);
  return true;
}

// Sync da JSON (idempotente per slug). Inserisce i nuovi, aggiorna contenuto+ordine
// degli esistenti SENZA toccare i gate/le note/lo stato (le decisioni vivono nello store).
function nova_pezzo_seed(): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $file = __DIR__ . '/../data/pezzo-seed.json';
  if (!is_file($file)) return;
  $items = json_decode((string)@file_get_contents($file), true);
  if (!is_array($items)) return;

  $up = $pdo->prepare(
    'INSERT INTO pezzo
       (slug, titolo, origine, tipo, fonte, keyword_target, search_intent, discover, come_spec, testo,
        gate_seo, gate_rilevanza, stato, ordine, creata_il)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON CONFLICT(slug) DO UPDATE SET
       titolo=excluded.titolo, origine=excluded.origine, tipo=excluded.tipo, fonte=excluded.fonte,
       keyword_target=excluded.keyword_target, search_intent=excluded.search_intent,
       discover=excluded.discover, come_spec=excluded.come_spec, testo=excluded.testo, ordine=excluded.ordine'
  );
  $t = time();
  foreach (array_values($items) as $i => $p) {
    if (!is_array($p) || empty($p['slug'])) continue;
    $origine = in_array(($p['origine'] ?? ''), NOVA_PEZZO_ORIGINI, true) ? $p['origine'] : 'redazione';
    $tipo    = in_array(($p['tipo'] ?? ''), NOVA_PEZZO_TIPI, true) ? $p['tipo'] : 'post';
    $up->execute([
      (string)$p['slug'],
      (string)($p['titolo'] ?? ''),
      (string)$origine,
      (string)$tipo,
      (string)($p['fonte'] ?? ''),
      (string)($p['keyword_target'] ?? ''),
      (string)($p['search_intent'] ?? ''),
      !empty($p['discover']) ? 1 : 0,
      (string)($p['come_spec'] ?? ''),
      (string)($p['testo'] ?? ''),
      'in_attesa',     // gate_seo iniziale
      'in_attesa',     // gate_rilevanza iniziale
      'in_valutazione',// stato iniziale (nessun gate deciso)
      $i,
      $t - $i,
    ]);
  }
}
