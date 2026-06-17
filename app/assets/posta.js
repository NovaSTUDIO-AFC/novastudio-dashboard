// ───────────────────────────────────────────────────────────────
// Render pagina "Posta" dal catalogo (window.CATALOGO).
// Mostra: caselle gestite, agenti del reparto, automazioni della posta.
// ───────────────────────────────────────────────────────────────

function classeStato(s) {
  const t = (s || "").toLowerCase();
  if (/(riautentic|invalid|verificare|da[ _]attivare|in arrivo|esclus|previsto|sospeso|in sospeso)/.test(t)) return "warn";
  if (/(conness|collegat|attiv|verificat|presente|in_uso|ok)/.test(t)) return "ok";
  return "";
}
function tagStato(s) {
  return `<span class="tag ${classeStato(s)}">${s || "—"}</span>`;
}

(function renderPosta() {
  const C = window.CATALOGO || {};

  // Caselle
  const caselle = C.caselle_email || [];
  document.getElementById("caselle").innerHTML = caselle.map((c) => `
    <div class="card machine">
      <div class="ic">📬</div>
      <div>
        <div class="nm">${c.indirizzo}</div>
        <div class="meta">interno: ${c.nome_interno} · ${c.provider}</div>
        ${c.alias && c.alias.length ? `<div class="meta">alias: ${c.alias.join(", ")}</div>` : ""}
        <div class="meta">accesso: ${c.accesso}${c.via && c.via !== "—" ? " · " + c.via : ""}</div>
        ${tagStato(c.stato)}
      </div>
    </div>`).join("");

  // Agenti
  const agenti = C.agenti || [];
  document.getElementById("agenti").innerHTML = agenti.map((a) => `
    <div class="card svc">
      <div class="ic">🤖</div>
      <div>
        <div class="nm">${a.id}</div>
        <div class="d">${a.scopo || ""}</div>
        ${tagStato(a.stato)}
      </div>
    </div>`).join("");

  // Automazioni del reparto posta
  const auto = (C.automazioni || []).filter((a) => a.reparto === "posta");
  document.getElementById("automazioni-posta").innerHTML = auto.map((a) => `
    <div class="card svc">
      <div class="ic">⚙️</div>
      <div>
        <div class="nm">${a.cosa}</div>
        <div class="d">⏰ ${a.quando} · ${a.dove_gira}</div>
        ${tagStato(a.stato)}
      </div>
    </div>`).join("");

  const cc = document.getElementById("caselle-count");
  if (cc) cc.textContent = `${caselle.length} caselle`;
  const cd = document.getElementById("cat-data");
  if (cd && C.meta) cd.textContent = `aggiornato ${C.meta.aggiornato || ""}`;
})();
