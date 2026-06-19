<?php
/** @var array $utenti */ /** @var array $ME */ /** @var string $msg */ /** @var string $err */
function sezioni_di_riga(array $u): array {
  if (!empty($u['is_admin'])) return NOVA_SEZIONI;
  $s = json_decode((string)($u['sezioni'] ?? '[]'), true);
  return is_array($s) ? $s : [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Utenti & permessi — NovaSTUDIO</title>
  <style>
    :root { --bg:#0b1018; --card:#121a28; --line:#243049; --text:#e7edf6; --muted:#8aa0bd; --accent:#4f8cff; --bad:#ff6b81; --ok:#5ad19a; }
    * { box-sizing:border-box; }
    body { margin:0; background:radial-gradient(1200px 600px at 50% -10%, #16213a, var(--bg));
           color:var(--text); font:15px/1.5 -apple-system,Segoe UI,Roboto,sans-serif; }
    .wrap { max-width:980px; margin:0 auto; padding:28px 18px 60px; }
    .top { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
    h1 { font-size:22px; margin:0; }
    h2 { font-size:16px; margin:28px 0 10px; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; }
    a { color:var(--accent); text-decoration:none; }
    .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px 18px; margin-bottom:14px; }
    .row { display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
    .email { font-weight:600; }
    .tag { font-size:11px; padding:2px 8px; border-radius:999px; border:1px solid var(--line); color:var(--muted); }
    .tag.adm { color:#ffd479; border-color:#6d551f; }
    .tag.off { color:var(--bad); border-color:#6d2535; }
    label.chk { display:inline-flex; gap:6px; align-items:center; font-size:13px; color:var(--text); }
    input[type=email], input[type=password] { padding:9px 10px; background:#0e1622; border:1px solid var(--line);
      border-radius:9px; color:var(--text); font-size:14px; outline:none; }
    input:focus { border-color:var(--accent); }
    button { padding:8px 13px; border:0; border-radius:9px; background:var(--accent); color:#fff; font-size:13px; font-weight:600; cursor:pointer; }
    button.ghost { background:#1b2638; border:1px solid var(--line); color:var(--text); }
    button.warn { background:#3a1620; border:1px solid #6d2535; color:#ffb3c0; }
    .msg { padding:10px 12px; border-radius:10px; font-size:13px; margin-bottom:12px; }
    .msg.ok { background:#16301f; border:1px solid #265a39; color:#a8e6bf; }
    .msg.err { background:#3a1620; border:1px solid #6d2535; color:#ffb3c0; }
    .sezint { display:flex; flex-wrap:wrap; gap:10px 16px; margin:6px 0; }
    form.inline { display:contents; }
    .sub { color:var(--muted); font-size:13px; }
    hr { border:0; border-top:1px solid var(--line); margin:14px 0; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <h1>Utenti &amp; permessi</h1>
      <a href="?">← Dashboard</a>
    </div>
    <p class="sub">Accesso per sezione. Tu (<?= htmlspecialchars($ME['email'], ENT_QUOTES) ?>) sei amministratore: vedi tutto.</p>

    <?php if (!empty($msg)): ?><div class="msg ok"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="msg err"><?= htmlspecialchars($err, ENT_QUOTES) ?></div><?php endif; ?>

    <h2>Utenti</h2>
    <?php foreach ($utenti as $u):
      $sez = sezioni_di_riga($u);
      $isAdmin = !empty($u['is_admin']);
      $attivo  = !empty($u['attivo']); ?>
      <div class="card">
        <div class="row" style="justify-content:space-between">
          <div class="row">
            <span class="email"><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></span>
            <?php if ($isAdmin): ?><span class="tag adm">admin</span><?php endif; ?>
            <?php if (!$attivo): ?><span class="tag off">sospeso</span><?php endif; ?>
          </div>
          <div class="row">
            <form class="inline" method="post" action="?action=admin_toggle">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" />
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
              <input type="hidden" name="attivo" value="<?= $attivo ? '0' : '1' ?>" />
              <button class="<?= $attivo ? 'warn' : 'ghost' ?>" type="submit"><?= $attivo ? 'Sospendi' : 'Riattiva' ?></button>
            </form>
          </div>
        </div>

        <hr />

        <form method="post" action="?action=admin_update">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" />
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
          <label class="chk"><input type="checkbox" name="is_admin" value="1" <?= $isAdmin ? 'checked' : '' ?> /> Amministratore (accesso totale)</label>
          <div class="sezint">
            <?php foreach (NOVA_SEZIONI as $s): ?>
              <label class="chk">
                <input type="checkbox" name="sezioni[]" value="<?= $s ?>" <?= in_array($s, $sez, true) ? 'checked' : '' ?> />
                <?= htmlspecialchars(NOVA_SEZIONI_LABEL[$s] ?? $s, ENT_QUOTES) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <button type="submit">Salva permessi</button>
        </form>

        <hr />

        <form class="row" method="post" action="?action=admin_setpw">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" />
          <input type="hidden" name="email" value="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>" />
          <input type="password" name="password" placeholder="nuova password (min 10)" minlength="10" />
          <button class="ghost" type="submit">Reimposta password</button>
        </form>
      </div>
    <?php endforeach; ?>

    <h2>Nuovo utente</h2>
    <div class="card">
      <form method="post" action="?action=admin_create">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" />
        <div class="row">
          <input type="email" name="email" placeholder="email" required />
          <input type="password" name="password" placeholder="password (min 10)" minlength="10" required />
        </div>
        <label class="chk" style="margin-top:10px"><input type="checkbox" name="is_admin" value="1" /> Amministratore</label>
        <div class="sezint">
          <?php foreach (NOVA_SEZIONI as $s): ?>
            <label class="chk"><input type="checkbox" name="sezioni[]" value="<?= $s ?>" /> <?= htmlspecialchars(NOVA_SEZIONI_LABEL[$s] ?? $s, ENT_QUOTES) ?></label>
          <?php endforeach; ?>
        </div>
        <button type="submit">Crea utente</button>
      </form>
    </div>
  </div>
</body>
</html>
