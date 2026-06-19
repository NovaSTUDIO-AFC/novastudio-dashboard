// ───────────────────────────────────────────────────────────────
// Render pagina "Automazioni" dal catalogo (window.CATALOGO).
// Vista operativa di TUTTO ciò che gira da solo, in tutti i reparti.
// ───────────────────────────────────────────────────────────────

function classeStato(s) {
  const t = (s || "").toLowerCase();
  if (/(riautentic|invalid|verificare|da[ _]attivare|in arrivo|esclus|previsto|sospeso|in sospeso)/.test(t)) return "warn";
  if (/(attiv|conness|collegat|verificat|presente|ok)/.test(t)) return "ok";
  return "";
}
function tagStato(s) {
  return `<span class="tag ${classeStato(s)}">${s || "—"}</span>`;
}

(function renderAutomazioni() {
  const C = window.CATALOGO || {};
  const auto = C.automazioni || [];

  document.getElementById("automazioni").innerHTML = auto.map((a) => `
    <div class="card svc">
      <div class="ic">⚙️</div>
      <div>
        <div class="nm">${a.cosa}</div>
        <div class="d">reparto: ${a.reparto || "—"}</div>
        <div class="d">⏰ ${a.quando} · 🖥️ ${a.dove_gira}</div>
        <div class="d">📈 ${a.monitoraggio || "—"}</div>
        ${tagStato(a.stato)} ${typeof badgeOsservato === "function" ? badgeOsservato(a.id) : ""}
      </div>
    </div>`).join("");

  const ac = document.getElementById("auto-count");
  if (ac) {
    const attive = auto.filter((a) => classeStato(a.stato) === "ok").length;
    const nota = typeof notaOsservato === "function" ? ` · ${notaOsservato()}` : "";
    ac.textContent = `${attive}/${auto.length} attive${nota}`;
  }
  const cd = document.getElementById("cat-data");
  if (cd && C.meta) cd.textContent = `aggiornato ${C.meta.aggiornato || ""}`;
})();
