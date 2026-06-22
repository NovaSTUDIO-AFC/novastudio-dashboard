// ───────────────────────────────────────────────────────────────
// Render della sezione "Mappa" (window.MAPPA da mappa-dati.js).
// L'inventario riesumato: blocchi di valore da accendere, automazioni,
// componenti riusabili, idee. È il tabellone: cosa c'è e a che punto è.
// ───────────────────────────────────────────────────────────────

(function renderMappa() {
  const M = window.MAPPA || {};

  // stato → classe colore + etichetta leggibile
  const STATI = {
    fatto:       { cls: "ok",   txt: "fatto" },
    backbone:    { cls: "ok",   txt: "backbone" },
    parziale:    { cls: "warn", txt: "parziale" },
    spento:      { cls: "warn", txt: "spento" },
    abbozzo:     { cls: "warn", txt: "abbozzo" },
    placeholder: { cls: "warn", txt: "placeholder" },
  };
  const tag = (s) => {
    const v = STATI[s] || { cls: "", txt: s || "—" };
    return `<span class="tag ${v.cls}">${v.txt}</span>`;
  };

  // ── Blocchi di valore (ordinati per priorità) ──────────────────
  const blocchi = (M.blocchi || []).slice().sort((a, b) => (a.priorita || 99) - (b.priorita || 99));
  const elB = document.getElementById("blocchi");
  if (elB) {
    elB.innerHTML = blocchi.map((b) => `
      <div class="card svc">
        <div class="ic">${b.priorita <= 4 ? "🎯" : "✅"}</div>
        <div>
          <div class="nm">${b.id} · ${b.nome} ${tag(b.stato)}</div>
          <div class="d">${b.cosa}</div>
          <div class="d">📍 ${b.dove}</div>
          <div class="d">➡️ <strong>ultimo miglio:</strong> ${b.ultimo_miglio}</div>
        </div>
      </div>`).join("");
  }

  // ── Automazioni / job ──────────────────────────────────────────
  const elA = document.getElementById("mappa-automazioni");
  if (elA) {
    elA.innerHTML = (M.automazioni || []).map((a) => `
      <div class="card svc">
        <div class="ic">⚙️</div>
        <div>
          <div class="nm">${a.nome} ${tag(a.stato)}</div>
          <div class="d">${a.cosa}</div>
          <div class="d">famiglia: ${a.famiglia || "—"}</div>
        </div>
      </div>`).join("");
  }

  // ── Componenti riusabili + Idee (liste) ────────────────────────
  const lista = (arr) => `<ul class="mappa-list">${(arr || []).map((x) => `<li>${x}</li>`).join("")}</ul>`;
  const elC = document.getElementById("componenti");
  if (elC) elC.innerHTML = lista(M.componenti);
  const elI = document.getElementById("idee");
  if (elI) elI.innerHTML = lista(M.idee);

  // ── Conteggio (badge in alto) ──────────────────────────────────
  const cnt = document.getElementById("mappa-count");
  if (cnt) {
    const daAccendere = blocchi.filter((b) => (STATI[b.stato] || {}).cls === "warn").length;
    cnt.textContent = `${daAccendere} blocchi da accendere · ${(M.automazioni || []).length} automazioni`;
  }
  const cd = document.getElementById("mappa-data");
  if (cd && M.meta) cd.textContent = `aggiornato ${M.meta.aggiornato || ""} · fonte ${M.meta.fonte || ""}`;
})();
