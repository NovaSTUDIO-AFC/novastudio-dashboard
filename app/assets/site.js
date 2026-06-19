// ───────────────────────────────────────────────────────────────
// LAYOUT CONDIVISO — menu di navigazione, cancello password, aree.
// Caricato da TUTTE le pagine. Per aggiungere una voce di menu o
// un'area del progetto in futuro, modifica gli array qui sotto.
// ───────────────────────────────────────────────────────────────

// Voci del menu
const NAV = [
  { label: "Home",           href: "index.html",          icon: "🏠" },
  { label: "Infrastruttura", href: "infrastruttura.html", icon: "🗺️" },
  { label: "Sistema",        href: "sistema.html",        icon: "🧩" },
  { label: "Posta",          href: "posta.html",          icon: "📬" },
  { label: "Automazioni",    href: "automazioni.html",    icon: "⚙️" },
  { label: "Guida AI",       href: "guida-ai.html",       icon: "📘" },
];

// Aree del progetto NovaSTUDIO mostrate in homepage
const AREE = [
  {
    titolo: "Infrastruttura",
    desc: "Mappa della rete Tailscale, macchine, servizi attivi e scorciatoie.",
    href: "infrastruttura.html",
    icon: "🗺️",
    stato: "attiva",
  },
  {
    titolo: "Sistema",
    desc: "Strumenti installati e connettori (MCP) collegati, con il loro stato.",
    href: "sistema.html",
    icon: "🧩",
    stato: "attiva",
  },
  {
    titolo: "Posta",
    desc: "Caselle email gestite, agenti del reparto e automazioni della posta.",
    href: "posta.html",
    icon: "📬",
    stato: "attiva",
  },
  {
    titolo: "Automazioni",
    desc: "Tutto ciò che gira da solo: cosa fa, quando, dove e con che stato.",
    href: "automazioni.html",
    icon: "⚙️",
    stato: "attiva",
  },
  {
    titolo: "Guida AI",
    desc: "I passi da seguire per creare agenti, automazioni e funzioni ben integrate in NovaSTUDIO.",
    href: "guida-ai.html",
    icon: "📘",
    stato: "attiva",
  },
];

// Nota: l'autenticazione è gestita SERVER-SIDE dal front controller
// PHP (index.php). Qui niente password lato client.

// ── Menu di navigazione ────────────────────────────────────────
function renderNav() {
  const host = document.getElementById("site-nav");
  if (!host) return;

  const current = location.pathname.split("/").pop() || "index.html";
  const links = NAV.map((n) => {
    const active = n.href === current ? " active" : "";
    return `<a class="nav-link${active}" href="${n.href}">${n.icon} ${n.label}</a>`;
  }).join("");

  host.innerHTML = `
    <div class="nav-inner">
      <a class="brand" href="index.html">◆ NovaSTUDIO</a>
      <button id="nav-toggle" class="nav-toggle" aria-label="Menu">☰</button>
      <nav id="nav-links" class="nav-links">${links}
        <a class="nav-link nav-logout" href="?action=logout">🚪 Esci</a>
      </nav>
    </div>`;

  const toggle = host.querySelector("#nav-toggle");
  const linksEl = host.querySelector("#nav-links");
  toggle.addEventListener("click", () => linksEl.classList.toggle("open"));
}

// ── Aree del progetto (homepage) ───────────────────────────────
function renderAree() {
  const host = document.getElementById("aree");
  if (!host) return;

  host.innerHTML = AREE.map((a) => {
    const placeholder = !a.href;
    const tag = `<span class="area-tag ${placeholder ? "soon" : ""}">${a.stato}</span>`;
    const inner = `
      <div class="area-ic">${a.icon}</div>
      <div class="area-titolo">${a.titolo}</div>
      <div class="area-desc">${a.desc}</div>
      ${tag}`;
    return placeholder
      ? `<div class="card area disabled">${inner}</div>`
      : `<a class="card area" href="${a.href}">${inner}</a>`;
  }).join("");
}

renderNav();
renderAree();
