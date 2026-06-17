// ───────────────────────────────────────────────────────────────
// Render pagina "Sistema" dal catalogo (window.CATALOGO).
// Mostra: strumenti installati + connettori MCP, con il loro stato.
// ───────────────────────────────────────────────────────────────

// Classe del tag di stato (verde = ok, giallo = attenzione, grigio = neutro)
function classeStato(s) {
  const t = (s || "").toLowerCase();
  if (/(riautentic|invalid|verificare|da[ _]attivare|in arrivo|esclus|previsto|sospeso)/.test(t)) return "warn";
  if (/(conness|collegat|attiv|verificat|presente|in_uso|ok)/.test(t)) return "ok";
  return "";
}
function tagStato(s) {
  return `<span class="tag ${classeStato(s)}">${s || "—"}</span>`;
}

(function renderSistema() {
  const C = window.CATALOGO || {};

  // Strumenti
  const strumenti = C.stack || [];
  document.getElementById("strumenti").innerHTML = strumenti.map((s) => `
    <div class="card svc">
      <div class="ic">🛠️</div>
      <div>
        <div class="nm">${s.nome}</div>
        <div class="d">${s.categoria || ""}</div>
        ${s.percorso ? `<div class="ex">${s.percorso}</div>` : ""}
        ${s.note ? `<div class="d">${s.note}</div>` : ""}
        ${tagStato(s.stato)}
      </div>
    </div>`).join("");

  // Connettori MCP
  const conn = C.connettori_mcp || [];
  document.getElementById("connettori").innerHTML = conn.map((c) => `
    <div class="card svc">
      <div class="ic">🔌</div>
      <div>
        <div class="nm">${c.nome}</div>
        ${tagStato(c.stato)}
      </div>
    </div>`).join("");

  const ok = conn.filter((c) => classeStato(c.stato) === "ok").length;
  const cc = document.getElementById("conn-count");
  if (cc) cc.textContent = `${ok}/${conn.length} connettori attivi`;
  const cd = document.getElementById("cat-data");
  if (cd && C.meta) cd.textContent = `aggiornato ${C.meta.aggiornato || ""}`;
})();
