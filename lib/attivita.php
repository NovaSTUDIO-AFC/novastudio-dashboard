<?php
// ───────────────────────────────────────────────────────────────
// ATTIVITÀ / "Da fare" — lista task del progetto.
// Stato volatile (si aggiunge/spunta/elimina): vive nello store SQLite,
// non in git. La dashboard la disegna; le azioni passano dal front controller.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// Lista attività: prima le da fare (recenti in alto), poi le fatte di recente.
function nova_attivita_lista(): array {
  $pdo = nova_db();
  if (!$pdo) return [];
  return $pdo->query(
    'SELECT * FROM attivita ORDER BY fatta ASC, creata_il DESC'
  )->fetchAll();
}

function nova_attivita_aggiungi(string $testo): bool {
  $pdo = nova_db();
  if (!$pdo) return false;
  $testo = trim($testo);
  if ($testo === '' || mb_strlen($testo) > 500) return false;
  $st = $pdo->prepare('INSERT INTO attivita (testo, fatta, creata_il) VALUES (?,0,?)');
  $st->execute([$testo, time()]);
  return true;
}

// Spunta/de-spunta.
function nova_attivita_toggle(int $id): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $st = $pdo->prepare(
    'UPDATE attivita
        SET fatta = CASE fatta WHEN 1 THEN 0 ELSE 1 END,
            fatta_il = CASE fatta WHEN 1 THEN NULL ELSE ? END
      WHERE id = ?'
  );
  $st->execute([time(), $id]);
}

function nova_attivita_elimina(int $id): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $pdo->prepare('DELETE FROM attivita WHERE id = ?')->execute([$id]);
}

// Seed iniziale: se la tabella è vuota, mette gli item aperti già noti
// (dogfooding — "Da fare" diventa la casa di questi promemoria).
function nova_attivita_seed(): void {
  $pdo = nova_db();
  if (!$pdo) return;
  $n = (int)$pdo->query('SELECT COUNT(*) FROM attivita')->fetchColumn();
  if ($n > 0) return;
  $iniziali = [
    'Aprire la PR accessi-utenti-pwa → main (PAT senza scope PR: aprirla a mano o rigenerare il token)',
    'Far ri-verificare la dashboard a Codex/openclaw col prompt blindato',
    'Rifinire questa sezione "Da fare" (priorità, scadenze, chi)',
  ];
  $st = $pdo->prepare('INSERT INTO attivita (testo, fatta, creata_il) VALUES (?,0,?)');
  $t = time();
  foreach ($iniziali as $i => $testo) {
    $st->execute([$testo, $t - $i]); // ordine stabile
  }
}
