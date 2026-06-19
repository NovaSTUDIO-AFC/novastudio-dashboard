<?php /** @var array $CONFIG */ /** @var string $error */ /** @var string $info */ ?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Recupero password — NovaSTUDIO</title>
  <?php if (!empty($CONFIG['recaptcha_site'])): ?>
  <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($CONFIG['recaptcha_site'], ENT_QUOTES) ?>"></script>
  <?php endif; ?>
  <style>
    :root { --bg:#0b1018; --card:#121a28; --line:#243049; --text:#e7edf6; --muted:#8aa0bd; --accent:#4f8cff; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:grid; place-items:center;
           background:radial-gradient(1200px 600px at 50% -10%, #16213a, var(--bg));
           color:var(--text); font:15px/1.5 -apple-system,Segoe UI,Roboto,sans-serif; }
    .box { width:min(92vw,360px); background:var(--card); border:1px solid var(--line);
           border-radius:16px; padding:28px; box-shadow:0 20px 60px rgba(0,0,0,.45); }
    h1 { margin:0 0 4px; font-size:20px; }
    p.sub { margin:0 0 20px; color:var(--muted); font-size:13px; }
    label { display:block; font-size:12px; color:var(--muted); margin:14px 0 6px; }
    input[type=email] { width:100%; padding:11px 12px; background:#0e1622; border:1px solid var(--line);
      border-radius:10px; color:var(--text); font-size:15px; outline:none; }
    input:focus { border-color:var(--accent); }
    button { width:100%; margin-top:18px; padding:12px; border:0; border-radius:10px;
      background:var(--accent); color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
    button:hover { filter:brightness(1.08); }
    .err { margin-top:14px; background:#3a1620; border:1px solid #6d2535; color:#ffb3c0; padding:10px 12px; border-radius:10px; font-size:13px; }
    .ok  { margin-top:14px; background:#16301f; border:1px solid #265a39; color:#a8e6bf; padding:10px 12px; border-radius:10px; font-size:13px; }
    .brand { text-align:center; font-size:13px; color:var(--muted); margin-bottom:18px; letter-spacing:.5px; }
    a.link { display:block; text-align:center; margin-top:16px; color:var(--muted); font-size:13px; text-decoration:none; }
    a.link:hover { color:var(--accent); }
  </style>
</head>
<body>
  <form class="box" method="post" action="?action=forgot" autocomplete="off">
    <div class="brand">◆ NovaSTUDIO</div>
    <h1>Password dimenticata</h1>
    <p class="sub">Inserisci la tua email: ti invieremo un link per reimpostarla.</p>

    <?php if (!empty($error)): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <?php if (!empty($info)): ?><div class="ok"><?= htmlspecialchars($info, ENT_QUOTES) ?></div><?php endif; ?>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" required autofocus />

    <?php if (!empty($CONFIG['recaptcha_site'])): ?>
      <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" />
    <?php endif; ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" />
    <button type="submit">Invia il link</button>
    <a class="link" href="?">← Torna all'accesso</a>
  </form>

  <?php if (!empty($CONFIG['recaptcha_site'])): ?>
  <script>
    (function () {
      var form = document.querySelector('form.box');
      var siteKey = <?= json_encode($CONFIG['recaptcha_site']) ?>;
      var sending = false;
      form.addEventListener('submit', function (e) {
        if (sending) return;
        e.preventDefault();
        grecaptcha.ready(function () {
          grecaptcha.execute(siteKey, { action: 'forgot' }).then(function (token) {
            document.getElementById('g-recaptcha-response').value = token;
            sending = true; form.submit();
          });
        });
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>
