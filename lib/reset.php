<?php
// ───────────────────────────────────────────────────────────────
// RECUPERO PASSWORD — token monouso, con scadenza, hashato nel DB.
// Anti-enumerazione: la pagina "password dimenticata" mostra SEMPRE
// lo stesso messaggio, esista o no l'email.
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/mailer.php';

const NOVA_RESET_TTL = 3600; // validità token: 1 ora

// Crea un token e invia la mail. Ritorna sempre true verso l'utente
// (no enumerazione); logga internamente l'esito reale.
function nova_reset_richiedi(array $cfg, string $email, string $linkBase): bool {
  $pdo = nova_db();
  if (!$pdo) return true;
  $email = nova_norm_email($email);

  $u = nova_utente_per_email($email);
  if (!$u) return true; // email inesistente: fingi successo

  // invalida eventuali token precedenti per questa email
  $pdo->prepare('UPDATE reset_token SET usato = 1 WHERE email = ? AND usato = 0')->execute([$email]);

  $raw  = bin2hex(random_bytes(32));
  $hash = hash('sha256', $raw);
  $now  = time();
  $st = $pdo->prepare(
    'INSERT INTO reset_token (email, token_hash, scade_il, usato, creato_il) VALUES (?,?,?,0,?)'
  );
  $st->execute([$email, $hash, $now + NOVA_RESET_TTL, $now]);

  $link = $linkBase . '?action=reset&token=' . urlencode($raw);
  $html = '<div style="font-family:sans-serif;font-size:15px;color:#1a2233">'
        . '<p>Hai chiesto di reimpostare la password della dashboard NovaSTUDIO.</p>'
        . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '" '
        . 'style="display:inline-block;padding:10px 16px;background:#4f8cff;color:#fff;'
        . 'border-radius:8px;text-decoration:none">Imposta una nuova password</a></p>'
        . '<p style="color:#667">Il link vale 1 ora. Se non sei stato tu, ignora questa email.</p>'
        . '</div>';

  [$ok] = nova_invia_email($cfg, $email, 'Reimposta la password — NovaSTUDIO', $html);
  return $ok; // l'esito reale (usato solo lato server per logica/diagnostica)
}

// Valida un token grezzo. Ritorna la riga token valida o null.
function nova_reset_valida(string $rawToken): ?array {
  $pdo = nova_db();
  if (!$pdo || $rawToken === '') return null;
  $hash = hash('sha256', $rawToken);
  $st = $pdo->prepare('SELECT * FROM reset_token WHERE token_hash = ? AND usato = 0 AND scade_il > ?');
  $st->execute([$hash, time()]);
  $row = $st->fetch();
  return $row ?: null;
}

// Consuma il token e imposta la nuova password. Ritorna [ok, messaggio].
function nova_reset_completa(string $rawToken, string $nuovaPassword): array {
  $pdo = nova_db();
  if (!$pdo) return [false, 'Store non disponibile.'];
  $row = nova_reset_valida($rawToken);
  if (!$row) return [false, 'Link non valido o scaduto. Richiedine uno nuovo.'];
  if (strlen($nuovaPassword) < 10) return [false, 'La password deve avere almeno 10 caratteri.'];

  $pdo->beginTransaction();
  try {
    nova_imposta_password($row['email'], $nuovaPassword);
    $pdo->prepare('UPDATE reset_token SET usato = 1 WHERE id = ?')->execute([$row['id']]);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    return [false, 'Errore durante il salvataggio. Riprova.'];
  }
  return [true, 'Password aggiornata. Ora puoi accedere.'];
}
