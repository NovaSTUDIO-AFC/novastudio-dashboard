// ───────────────────────────────────────────────────────────────
// DATI DELL'INFRASTRUTTURA
// Tutto il sito si genera da qui. Per aggiungere macchine, servizi
// o sezioni in futuro, basta modificare questo file.
// ───────────────────────────────────────────────────────────────

const INFRA = {
  // Il server centrale "sempre acceso"
  server: {
    id: "mac-mini",
    nome: "mac-mini-di-fabio",
    ip: "100.126.71.100",
    sistema: "macOS 26.5.1",
    dns: "mac-mini-di-fabio.tailafe504.ts.net",
    ruolo: "Server sempre acceso",
    icona: "🗄️",
    note: "Claude · Codex · openclaw · LM Studio",
  },

  // I dispositivi client che si collegano via Tailscale
  client: [
    { id: "mba",   nome: "macbook-air-di-fabio", ip: "100.75.206.50",  sistema: "macOS",      ruolo: "Client",        icona: "💻" },
    { id: "minipc", nome: "minipc-fabio",         ip: "100.76.197.65",  sistema: "Windows 11", ruolo: "Client",        icona: "🖥️" },
    { id: "galaxy", nome: "galaxy-di-fabio",       ip: "100.103.85.111", sistema: "Android 16", ruolo: "Client mobile", icona: "📱" },
  ],

  // Scorciatoie da terminale
  scorciatoie: {
    "Da Mac e Windows": [
      { cmd: "macmini",  desc: "Entra nel Mac mini via SSH" },
      { cmd: "mwork",    desc: "Entra direttamente in ~/Workspace" },
      { cmd: "mclaude",  desc: "Avvia Claude Code nel Workspace" },
      { cmd: "mcodex",   desc: "Avvia Codex nel Workspace" },
      { cmd: "mclaw",    desc: "Avvia openclaw nel Workspace" },
      { cmd: "mcode",    desc: "Apre VS Code remoto sul Workspace" },
    ],
    "Dentro il Mac mini": [
      { cmd: "work",    desc: "Vai al Workspace" },
      { cmd: "wp",      desc: "Workspace progetti" },
      { cmd: "wclaude", desc: "Avvia Claude Code" },
      { cmd: "wcodex",  desc: "Avvia Codex" },
      { cmd: "wclaw",   desc: "Avvia openclaw" },
    ],
  },

  // Servizi attivi sul Mac mini
  servizi: [
    { nome: "SSH",                icona: "🔑", desc: "Solo a chiave, via Tailscale" },
    { nome: "VS Code remoto",     icona: "🧩", desc: "Remote-SSH" },
    { nome: "LM Studio",          icona: "🤖", desc: "API OpenAI-compatibile · /v1", extra: "deepseek-r1-0528-qwen3-8b · gemma-4-e4b" },
    { nome: "Condivisione schermo", icona: "🖥️", desc: "VNC, via Tailscale" },
    { nome: "Monitoraggio",       icona: "📊", desc: "Healthchecks.io · alert email/Slack/WhatsApp" },
  ],
};
