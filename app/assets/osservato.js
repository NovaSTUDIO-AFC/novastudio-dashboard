// ───────────────────────────────────────────────────────────────
// Stato OSSERVATO (misurato dall'osservatore di NovaSTUDIO).
// window.STATO_OSSERVATO arriva da stato-osservato.js (sincronizzato dal
// deploy a partire da ~/.local/state/novastudio/stato-osservato.json).
//
// Mentre window.CATALOGO dice cosa DOVREBBE esistere (dichiarato), questo
// dice cosa è DAVVERO vivo adesso (misurato). La dashboard mostra entrambi:
// così "letti" insieme si vede a colpo d'occhio cosa funziona e cosa è in drift.
// ───────────────────────────────────────────────────────────────

// Mappa entity_id -> osservazione (l'ultima misura per quell'entità).
window.OSSERVATO_MAP = (function () {
  const S = window.STATO_OSSERVATO || {};
  const map = {};
  (S.entita || []).forEach((e) => { map[e.entity_id] = e; });
  return map;
})();

// Verde = misurato attivo · Rosso = drift/fermo · Grigio = non verificato.
function classeOsservato(stato) {
  const t = (stato || "").toLowerCase();
  if (/(drift|fermo|irraggiungibile|non_dichiarato|assente)/.test(t)) return "bad";
  if (/(attiv|conness|raggiungibile|pulito|visto|acceso)/.test(t)) return "ok";
  return ""; // non_verificato, modificato, ecc. → neutro
}

// Badge "misurato" per un'entità (per id catalogo o nome connettore).
// Ritorna "" se l'osservatore non ha una misura per quell'entità.
function badgeOsservato(entityId) {
  const e = (window.OSSERVATO_MAP || {})[entityId];
  if (!e) return "";
  const cls = classeOsservato(e.observed_state);
  const pallino = cls === "ok" ? "🟢" : cls === "bad" ? "🔴" : "⚪";
  const quando = (e.observed_at || "").replace("T", " ").slice(0, 16);
  const titolo = `misurato ${quando} UTC · fonte: ${e.provenance || "—"}`;
  return `<span class="tag ${cls}" title="${titolo}">${pallino} misurato: ${e.observed_state}</span>`;
}

// Riga "stato misurato aggiornato il…" da appendere a un contatore di pagina.
function notaOsservato() {
  const S = window.STATO_OSSERVATO || {};
  if (!S.generato_il) return "stato misurato: non disponibile";
  const q = S.generato_il.replace("T", " ").slice(0, 16);
  return `stato misurato: ${q} UTC`;
}
