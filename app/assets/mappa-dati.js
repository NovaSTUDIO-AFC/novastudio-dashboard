// ───────────────────────────────────────────────────────────────
// DATI della sezione "Mappa" — l'inventario riesumato dei 4 archivi.
// Fonte: ~/Workspace/INVENTARIO-RIESUMATO.md (+ automazioni-da-attivare.md).
// Per aggiornare: modifica i dati qui, non il renderer (mappa.js).
// stato: fatto | backbone | spento | abbozzo | placeholder | parziale
// ───────────────────────────────────────────────────────────────

window.MAPPA = {
  meta: {
    aggiornato: "2026-06-22",
    fonte: "INVENTARIO-RIESUMATO.md",
    nota: "Il lavoro non è costruire da zero: è ACCENDERE l'ultimo miglio, un blocco alla volta.",
  },

  // I blocchi di VALORE — quelli da trasformare in funzionanti (uno alla volta).
  blocchi: [
    { id: "V3", nome: "Outbound / Tubo Send", cosa: "Trova prospect, scrive il cold a nome di Fabio, invia via SMTP con anti-spam. Primo case-study.",
      dove: "Documents/Nova studio/slices", stato: "spento", priorita: 1,
      ultimo_miglio: "Allineare config/segreti + permessi launchd, dry-run live, primo cold reale → poi acceso." },
    { id: "V2", nome: "Contenuti Simracing.Fan", cosa: "RSS → bozza articolo IT → approvazione → pubblicazione. 200+ bozze già pronte.",
      dove: "engine/content_pipeline + Asana SF_CONTENT", stato: "placeholder", priorita: 2,
      ultimo_miglio: "Costruire il passo PUBLISH (sf-publish è placeholder) verso il sito V1." },
    { id: "V1", nome: "Sito Simracing.Fan (WordPress)", cosa: "Portale community + marketplace: WP + BuddyPress + WooCommerce/WCFM + Elementor + social login.",
      dove: "Documents/Sviluppo Web SF/simracingfan-dev", stato: "parziale", priorita: 3,
      ultimo_miglio: "Stabilizzare (fix ~4 bug PHP, pulizia log) + pubblicare i primi contenuti reali." },
    { id: "V4", nome: "Marketplace", cosa: "79 prodotti vendor importati, 6 store mappati, commission rules. Quasi go-live.",
      dove: "sito V1 (WooCommerce+WCFM)", stato: "parziale", priorita: 4,
      ultimo_miglio: "Attivare storefront + payment gateway; baseline affiliazioni." },
    { id: "V5", nome: "Reparto Posta", cosa: "Digest 07:30 + sentinella 13/18 → Slack. Già PAI-native e funzionante.",
      dove: "hub/reparti/posta", stato: "fatto", priorita: 9,
      ultimo_miglio: "Solo rischedulare il launchd quando serve." },
    { id: "V6", nome: "Osservatore + Guardia + Backup", cosa: "Auto-osservazione: reconcile, health check, backup cifrato. Bassa priorità (dopo il valore).",
      dove: "hub/sistema", stato: "backbone", priorita: 10,
      ultimo_miglio: "Gate osservatore sul Mac mini, poi schedulazione." },
  ],

  // Automazioni / job schedulati riesumati (tutti spenti dal 27/05).
  automazioni: [
    { nome: "sf-rss-monitor", cosa: "15 feed RSS → bozza articolo IT (200+ prodotte)", stato: "spento", famiglia: "contenuti" },
    { nome: "sf-publish", cosa: "pubblicare i contenuti", stato: "placeholder", famiglia: "contenuti" },
    { nome: "outbound-pipeline-daily", cosa: "Tubo Send: invio + follow-up + reply", stato: "spento", famiglia: "outbound" },
    { nome: "followup-pipeline-daily", cosa: "follow-up commerciale: triage HubSpot + bozze", stato: "spento", famiglia: "commerciale" },
    { nome: "ns-worker", cosa: "esegue task dal backlog Asana", stato: "spento", famiglia: "auto-osservazione" },
    { nome: "ns-pulse", cosa: "battito del sistema (ogni 30m)", stato: "spento", famiglia: "auto-osservazione" },
    { nome: "ns-deep", cosa: "deep work creativo (contenuti/ricerche)", stato: "spento", famiglia: "semi-valore" },
    { nome: "ns-daily", cosa: "report giornaliero (20:00)", stato: "spento", famiglia: "auto-osservazione" },
    { nome: "ns-event-collector", cosa: "raccolta eventi → telemetria", stato: "spento", famiglia: "auto-osservazione" },
    { nome: "ns-telemetry-refresh", cosa: "telemetria → dashboard Notion", stato: "spento", famiglia: "auto-osservazione" },
    { nome: "ns-system-audit", cosa: "audit architettura (04:45)", stato: "spento", famiglia: "auto-osservazione" },
    { nome: "ns-post-change-audit", cosa: "guardia drift strutturale", stato: "spento", famiglia: "auto-osservazione" },
    { nome: "ns-context-monitor", cosa: "monitor contesto", stato: "spento", famiglia: "auto-osservazione" },
  ],

  // Codice deterministico riutilizzabile (l'oro tecnico) — non prompt.
  componenti: [
    "engine/: daemon (runner/main.py) + content_pipeline state-machine + tools rss/asana/wp/publisher",
    "lib/asana_io/: client Asana completo (11 moduli)",
    "lib/event_logger/: collectors asana/slack/rss/attività-Fabio",
    "lib/novastudio_project/: config per-progetto · lib/telemetry/: token_tracker + value_audit",
    "outbound: sender.py (1241 righe) + scout.py + qualify.py + reply_assist.py + triage.py",
    "scripts orchestrazione: workmode, code-launch, slack-notify, healthcheck, budget-circuit-breaker",
    "tools: asana_export.py · build-capability-index (router 'Max', 36 risorse)",
  ],

  // Idee / concetti architetturali da tenere.
  idee: [
    "Architettura a 2 livelli: motore deterministico (Python/cron) + Claude orchestratore (MCP)",
    "Content pipeline come state-machine vincolata da DB (no prosa)",
    "Leve dinamiche outbound (entry point sostituibile senza toccare i tubi)",
    "Multi-casella con warm-up per-casella + cancello umano sull'invio",
    "Capability registry + router 'Max' (indicizza e instrada i compiti)",
    "Project-agnostic / Portability Test (framework riusabile su progetto N)",
    "Config-driven (project.yaml: niente hardcoding) · Budget circuit breaker + healthcheck",
    "#SimracingForGood (World Vision) come parte integrante",
  ],
};
