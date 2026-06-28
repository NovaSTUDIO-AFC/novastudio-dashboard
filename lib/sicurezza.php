<?php
// ───────────────────────────────────────────────────────────────
// REPARTO SICUREZZA — stato dell'ultima scansione dei siti.
// SOLA LETTURA: la dashboard mostra l'ultimo report prodotto dallo
// sweep settimanale (sicurezza-scan.ts sul Mac mini), che lo scrive
// in data/sicurezza-report.json. Nessuna azione dalla dashboard:
// la bonifica resta un gesto umano + Max (gate di approvazione).
// Niente SQLite: il report è un singolo JSON, fonte unica.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);

function nova_sicurezza_report(): array {
  $file = __DIR__ . '/../data/sicurezza-report.json';
  if (!is_file($file)) return ['quando' => null, 'dominî' => [], 'allarmi' => []];
  $r = json_decode((string)@file_get_contents($file), true);
  return is_array($r) ? $r : ['quando' => null, 'dominî' => [], 'allarmi' => []];
}
