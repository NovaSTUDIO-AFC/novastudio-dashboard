<?php
// ───────────────────────────────────────────────────────────────
// CONFIGURAZIONE SEGRETA — NON pubblicare, NON committare.
// Su Hostinger questo file resta protetto da .htaccess (e PHP non
// ne mostra mai il sorgente). Compila i valori qui sotto.
// ───────────────────────────────────────────────────────────────

return [
  // Credenziali di accesso
  'username'      => 'fabio',

  // Hash bcrypt della password (NON la password in chiaro!).
  // Genera l'hash con _genhash.php (poi cancella quel file) e incollalo qui.
  'password_hash' => 'INCOLLA_QUI_HASH_BCRYPT',

  // reCAPTCHA v2 "Non sono un robot".
  // Crea le chiavi su https://www.google.com/recaptcha/admin
  // Lascia stringhe vuote per disattivare temporaneamente il reCAPTCHA.
  'recaptcha_site'   => '',   // Site key (pubblica)
  'recaptcha_secret' => '',   // Secret key (privata)

  // Sicurezza / sessione
  'session_max_age' => 60 * 60 * 8,  // durata massima sessione: 8 ore
  'max_attempts'    => 5,            // tentativi falliti prima del blocco
  'lockout_seconds' => 15 * 60,      // durata blocco: 15 minuti
];
