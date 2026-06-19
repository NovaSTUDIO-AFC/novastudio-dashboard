<?php
// Test di logica (no HTTP, no rete). Esegui con: php _test/logic.php
declare(strict_types=1);

// DB pulito per il test (file gitignored + escluso dal deploy).
foreach (['users.db','users.db-wal','users.db-shm'] as $f) @unlink(__DIR__ . '/../data/' . $f);

require __DIR__ . '/../lib/users.php';
require __DIR__ . '/../lib/reset.php';

$pass = 0; $fail = 0;
function check(string $what, bool $cond): void {
  global $pass, $fail;
  if ($cond) { $pass++; echo "  ✓ $what\n"; }
  else       { $fail++; echo "  ✗ $what\n"; }
}

echo "== Store & seed ==\n";
check('SQLite disponibile', nova_db() !== null);
$cfg = ['admin_email' => 'info@afciaccio.it', 'password_hash' => password_hash('TestPass123', PASSWORD_DEFAULT)];
nova_seed_admin($cfg);
nova_seed_admin($cfg); // idempotente: non deve duplicare
$admin = nova_utente_per_email('info@afciaccio.it');
check('admin creato dal seed', $admin !== null);
check('admin è is_admin', !empty($admin['is_admin']));
check('seed idempotente (1 solo utente)', (int)nova_db()->query('SELECT COUNT(*) FROM utenti')->fetchColumn() === 1);

echo "== Login ==\n";
check('password corretta verifica', $admin && password_verify('TestPass123', $admin['pass_hash']));
check('password errata non verifica', $admin && !password_verify('sbagliata', $admin['pass_hash']));
check('admin vede tutte le sezioni', $admin && nova_sezioni_utente($admin) === NOVA_SEZIONI);
check('admin nova_puo(sistema)', $admin && nova_puo($admin, 'sistema'));

echo "== Utente limitato (solo posta) ==\n";
[$ok, $msg] = nova_crea_utente('collab@x.it', 'PostaSolo123', ['posta'], false);
check('crea utente ok', $ok === true);
[$ok2] = nova_crea_utente('collab@x.it', 'AltroLungo123', ['posta'], false);
check('email duplicata rifiutata', $ok2 === false);
[$ok3] = nova_crea_utente('cattiva', 'AltroLungo123', ['posta'], false);
check('email non valida rifiutata', $ok3 === false);
[$ok4] = nova_crea_utente('corta@x.it', 'breve', ['posta'], false);
check('password corta rifiutata', $ok4 === false);

$u = nova_utente_per_email('collab@x.it');
check('utente esiste', $u !== null);
check('non è admin', empty($u['is_admin']));
check('può posta', nova_puo($u, 'posta'));
check('NON può sistema', !nova_puo($u, 'sistema'));
check('NON può infrastruttura', !nova_puo($u, 'infrastruttura'));
check('sezioni = [posta]', nova_sezioni_utente($u) === ['posta']);

echo "== Aggiorna permessi ==\n";
nova_aggiorna_sezioni((int)$u['id'], ['posta', 'sistema'], false);
$u = nova_utente_per_email('collab@x.it');
check('ora può sistema', nova_puo($u, 'sistema'));
check('ancora NON automazioni', !nova_puo($u, 'automazioni'));

echo "== Sospensione ==\n";
nova_imposta_attivo((int)$u['id'], false);
check('utente sospeso non trovato (login bloccato)', nova_utente_per_email('collab@x.it') === null);
nova_imposta_attivo((int)$u['id'], true);
check('riattivato', nova_utente_per_email('collab@x.it') !== null);

echo "== Guardia ultimo admin ==\n";
check('niente altri admin oltre a quello', nova_conta_admin_attivi((int)$admin['id']) === 0);
check('un admin attivo totale', nova_conta_admin_attivi() === 1);

echo "== Reset password (token) ==\n";
$raw  = bin2hex(random_bytes(16));
$hash = hash('sha256', $raw);
$now  = time();
nova_db()->prepare('INSERT INTO reset_token (email, token_hash, scade_il, usato, creato_il) VALUES (?,?,?,0,?)')
         ->execute(['collab@x.it', $hash, $now + 3600, $now]);
check('token valido riconosciuto', nova_reset_valida($raw) !== null);
check('token falso NON valido', nova_reset_valida('xxxx') === null);
[$rok, $rmsg] = nova_reset_completa($raw, 'NuovaPass9999');
check('reset completato', $rok === true);
$u2 = nova_utente_per_email('collab@x.it');
check('nuova password attiva', $u2 && password_verify('NuovaPass9999', $u2['pass_hash']));
check('token consumato (monouso)', nova_reset_valida($raw) === null);

// Token scaduto
$raw2 = bin2hex(random_bytes(16));
nova_db()->prepare('INSERT INTO reset_token (email, token_hash, scade_il, usato, creato_il) VALUES (?,?,?,0,?)')
         ->execute(['collab@x.it', hash('sha256', $raw2), $now - 10, $now - 3600]);
check('token scaduto NON valido', nova_reset_valida($raw2) === null);

echo "\nRISULTATO: $pass passati, $fail falliti\n";
exit($fail === 0 ? 0 : 1);
