// ───────────────────────────────────────────────────────────────
// Render della dashboard infrastruttura a partire da INFRA (data.js)
// Il menu e il cancello password sono gestiti da site.js.
// ───────────────────────────────────────────────────────────────

// ── Mappa hub-and-spoke (SVG) ──
function renderMap() {
  const W = 760, H = 380, cx = W / 2, cy = H / 2, R = 135;
  const clients = INFRA.client;
  const svg = [`<svg id="map" viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg">`];

  // posiziona i client su un semicerchio/cerchio attorno al centro
  const pts = clients.map((c, i) => {
    const ang = (-Math.PI / 2) + (i + 0.5) * (2 * Math.PI / clients.length) - (Math.PI / clients.length) + Math.PI;
    // distribuzione uniforme su 360°
    const a = (2 * Math.PI / clients.length) * i - Math.PI / 2;
    return { ...c, x: cx + R * Math.cos(a), y: cy + R * 0.78 * Math.sin(a) };
  });

  // archi (linee animate "flusso" verso il server)
  pts.forEach((p) => {
    svg.push(`<line class="edge" x1="${cx}" y1="${cy}" x2="${p.x}" y2="${p.y}"/>`);
    svg.push(`<line class="edge-flow" x1="${p.x}" y1="${p.y}" x2="${cx}" y2="${cy}"/>`);
  });

  // nodi client
  pts.forEach((p) => {
    // se il nodo è sopra il centro, etichetta e IP vanno SOPRA l'icona
    // così non si sovrappongono alla linea che scende verso il Mac mini
    const above = p.y < cy;
    const nameY = above ? p.y - 48 : p.y + 46;
    const ipY   = above ? p.y - 32 : p.y + 62;
    svg.push(`
      <g>
        <circle cx="${p.x}" cy="${p.y}" r="26" fill="#1b2436" stroke="#243049" stroke-width="2"/>
        <text x="${p.x}" y="${p.y + 6}" text-anchor="middle" font-size="20">${p.icona}</text>
        <text class="node-label" x="${p.x}" y="${nameY}" text-anchor="middle">${p.nome.replace("-di-fabio","")}</text>
        <text class="node-sub"   x="${p.x}" y="${ipY}" text-anchor="middle">${p.ip}</text>
      </g>`);
  });

  // nodo server centrale
  svg.push(`
    <g>
      <circle cx="${cx}" cy="${cy}" r="44" fill="#13233a" stroke="#4f8cff" stroke-width="2.5"/>
      <text x="${cx}" y="${cy + 6}" text-anchor="middle" font-size="30">${INFRA.server.icona}</text>
      <text class="node-label" x="${cx}" y="${cy + 60}" text-anchor="middle" style="font-size:14px">Mac mini · Server</text>
      <text class="node-sub"   x="${cx}" y="${cy + 76}" text-anchor="middle">${INFRA.server.ip}</text>
    </g>`);

  svg.push(`</svg>`);
  document.getElementById("map-wrap").innerHTML = svg.join("");
}

// ── 3. Macchine ──
function renderMachines() {
  const all = [
    { ...INFRA.server, server: true },
    ...INFRA.client,
  ];
  const html = all.map((m) => `
    <div class="card machine">
      <div class="ic">${m.icona}</div>
      <div>
        <div class="nm">${m.nome}</div>
        <div class="meta">${m.sistema}</div>
        <div class="ip">${m.ip}</div>
        <div class="meta">${m.note || m.ruolo}</div>
        <span class="tag ${m.server ? "server" : ""}">${m.ruolo}</span>
      </div>
    </div>`).join("");
  document.getElementById("machines").innerHTML = html;
}

// ── 4. Scorciatoie ──
function renderShortcuts() {
  const groups = Object.entries(INFRA.scorciatoie).map(([titolo, items]) => `
    <div class="short-group">
      <h3>${titolo}</h3>
      ${items.map((s) => `
        <div class="short-row">
          <code title="Clicca per copiare">${s.cmd}</code>
          <span class="d">${s.desc}</span>
        </div>`).join("")}
    </div>`).join("");
  document.getElementById("shortcuts").innerHTML = groups;

  // clic-per-copiare
  document.querySelectorAll(".short-row code").forEach((el) => {
    el.addEventListener("click", () => {
      navigator.clipboard?.writeText(el.textContent);
      const old = el.textContent;
      el.textContent = "copiato ✓"; el.classList.add("copied");
      setTimeout(() => { el.textContent = old; el.classList.remove("copied"); }, 900);
    });
  });
}

// ── 5. Servizi ──
function renderServices() {
  const html = INFRA.servizi.map((s) => `
    <div class="card svc">
      <div class="ic">${s.icona}</div>
      <div>
        <div class="nm">${s.nome}</div>
        <div class="d">${s.desc}</div>
        ${s.extra ? `<div class="ex">${s.extra}</div>` : ""}
      </div>
    </div>`).join("");
  document.getElementById("services").innerHTML = html + cardGuardia();
}

// Card live della Guardia Mac mini — dati da window.MACMINI_STATUS (pubblicato
// dalla guardia ogni 10 min). Se manca o è vecchio, lo dice chiaramente.
function cardGuardia() {
  const s = window.MACMINI_STATUS;
  if (!s) return `
    <div class="card svc">
      <div class="ic">🛡️</div>
      <div><div class="nm">Guardia Mac mini</div>
        <div class="d">In attesa del primo aggiornamento…</div></div>
    </div>`;
  const eta = (Date.now() - new Date(s.ts).getTime()) / 60000; // minuti dall'ultimo dato
  const vecchio = eta > 25; // la guardia gira ogni 10 min: oltre 25 = probabilmente ferma
  const stato = vecchio ? "⚪️ dati vecchi" : (s.sistema === "bad" ? "🔴 sotto sforzo" : "🟢 tutto ok");
  const ora = new Date(s.ts).toLocaleTimeString("it-IT", { hour: "2-digit", minute: "2-digit" });
  const dett = `🌡️ ${Math.round(s.tempC)}°C · carico ${s.load1.toFixed(1)} · disco ${s.diskPct}%` +
               (s.throttle ? " · 🔥 throttling" : "");
  const probl = s.sistema === "bad" && s.problemi?.length ? ` — ${s.problemi.join(", ")}` : "";
  return `
    <div class="card svc">
      <div class="ic">🛡️</div>
      <div>
        <div class="nm">Guardia Mac mini · ${stato}</div>
        <div class="d">${dett}${probl}</div>
        <div class="ex">aggiornato alle ${ora}${vecchio ? " · controlla se la guardia è attiva" : ""}</div>
      </div>
    </div>`;
}

renderMap();
renderMachines();
renderShortcuts();
renderServices();
