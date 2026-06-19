<?php
// ───────────────────────────────────────────────────────────────
// INVIO EMAIL via Brevo (API transazionale v3).
// La dashboard PHP invia direttamente con la API key Brevo (un
// segreto in config.php). NB: è cosa diversa dal connettore MCP
// Brevo (quello serve a Claude, non al sito live).
// Ritorna [ok(bool), messaggio(string)].
// ───────────────────────────────────────────────────────────────
declare(strict_types=1);

function nova_invia_email(array $cfg, string $aEmail, string $oggetto, string $html, string $testo = ''): array {
  $key    = (string)($cfg['brevo_api_key'] ?? '');
  $mitt   = (string)($cfg['brevo_sender_email'] ?? '');
  $nome   = (string)($cfg['brevo_sender_name'] ?? 'NovaSTUDIO');
  if ($key === '' || $mitt === '') {
    return [false, 'Email non configurata (manca brevo_api_key / brevo_sender_email).'];
  }

  $payload = json_encode([
    'sender'      => ['email' => $mitt, 'name' => $nome],
    'to'          => [['email' => $aEmail]],
    'subject'     => $oggetto,
    'htmlContent' => $html,
    'textContent' => $testo !== '' ? $testo : strip_tags($html),
  ]);

  $url     = 'https://api.brevo.com/v3/smtp/email';
  $headers = ['accept: application/json', 'content-type: application/json', 'api-key: ' . $key];

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $payload,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_TIMEOUT        => 12,
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) return [false, 'Errore di rete: ' . $err];
  } else {
    $ctx = stream_context_create(['http' => [
      'method'        => 'POST',
      'header'        => implode("\r\n", $headers),
      'content'       => $payload,
      'timeout'       => 12,
      'ignore_errors' => true,
    ]]);
    $res  = @file_get_contents($url, false, $ctx);
    $code = 0;
    $hdrs = function_exists('http_get_last_response_headers')
          ? (http_get_last_response_headers() ?: [])
          : ($http_response_header ?? []);
    if (isset($hdrs[0]) && preg_match('/\s(\d{3})\s/', $hdrs[0], $m)) {
      $code = (int)$m[1];
    }
    if ($res === false) return [false, 'Errore di rete nell\'invio email.'];
  }

  if ($code >= 200 && $code < 300) return [true, 'Email inviata.'];
  return [false, 'Brevo ha risposto ' . $code . ': ' . substr((string)$res, 0, 200)];
}
