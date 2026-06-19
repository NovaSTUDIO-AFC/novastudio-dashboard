<?php
// Config SOLO per i test locali (caricata via env NOVA_CONFIG).
// reCAPTCHA disattivato; admin con password nota 'TestPass123'.
return [
  'username'      => 'fabio',
  'password_hash' => password_hash('TestPass123', PASSWORD_DEFAULT),
  'admin_email'   => 'info@afciaccio.it',
  'recaptcha_site'   => '',
  'recaptcha_secret' => '',
  'recaptcha_min_score' => 0.5,
  'brevo_api_key'      => '',
  'brevo_sender_email' => 'noreply@example.it',
  'brevo_sender_name'  => 'NovaSTUDIO',
  'session_max_age' => 60 * 60 * 8,
  'max_attempts'    => 50,
  'lockout_seconds' => 60,
];
