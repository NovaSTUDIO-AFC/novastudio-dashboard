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

  // Multi-utente: email del primo amministratore. Al primo avvio viene
  // creato nello store SQLite usando l'hash qui sopra (stessa password).
  'admin_email' => 'tu@esempio.it',

  // Brevo (recupero password via email transazionale). API key dal pannello
  // Brevo → SMTP & API → API Keys. Vuota = invio disattivato.
  'brevo_api_key'      => '',
  'brevo_sender_email' => 'noreply@tuodominio.it',
  'brevo_sender_name'  => 'NovaSTUDIO',

  // Sicurezza / sessione
  'session_max_age' => 60 * 60 * 8,  // durata massima sessione: 8 ore
  'max_attempts'    => 5,            // tentativi falliti prima del blocco
  'lockout_seconds' => 15 * 60,      // durata blocco: 15 minuti
];
