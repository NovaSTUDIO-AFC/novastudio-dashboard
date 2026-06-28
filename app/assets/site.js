// ───────────────────────────────────────────────────────────────
// LAYOUT CONDIVISO — navigazione ad ALBERO.
// Gerarchia: Progetto → (Reparti · Asset) → pagine. + viste globali.
// Menu a cassetto (drawer) su tutte le larghezze; breadcrumb in alto.
// L'albero è data-driven: per aggiungere una pagina si modifica TREE.
// I permessi per sezione (sez) restano enforced server-side; qui si
// nasconde solo ciò che l'utente non può vedere.
// ───────────────────────────────────────────────────────────────

// Voci globali (project-agnostic).
const GLOBAL = [
  { label: "Vista d'insieme", href: "index.html", icon: "🌐" },
];

// Progetti → sezioni → item (gli item-reparto possono avere pagine figlie).
const TREE = [
  {
    id: "simracing", label: "Simracing.Fan", icon: "◆",
    sections: [
      { label: "Reparti", items: [
        { label: "Redazione", icon: "📰", children: [
          { label: "Coda approvazioni",   href: "redazione.html", icon: "✅", sez: "redazione" },
          { label: "Manuale (istruzioni)", href: "manuale.html",   icon: "📋", sez: "redazione" },
        ]},
        { label: "SEO", icon: "🔍", children: [
          { label: "Raccomandazioni", href: "seo.html",         icon: "💡", sez: "seo" },
          { label: "Manuale",         href: "seo-manuale.html", icon: "📖", sez: "seo" },
        ]},
        { label: "Posta", href: "posta.html", icon: "📬", sez: "posta" },
      ]},
      { label: "Asset", items: [] }, // libreria foto, ecc. — in arrivo
    ],
  },
  {
    id: "novastudio", label: "NovaSTUDIO", icon: "▣",
    sections: [
      { label: "Asset", items: [
        { label: "Infrastruttura", href: "infrastruttura.html", icon: "🗺️", sez: "infrastruttura" },
        { label: "Sistema",        href: "sistema.html",        icon: "🧩", sez: "sistema" },
        { label: "Mappa",          href: "mappa.html",          icon: "🗂️", sez: "mappa" },
        { label: "Automazioni",    href: "automazioni.html",    icon: "⚙️", sez: "automazioni" },
      ]},
      { label: "Operativo", items: [
        { label: "Da fare", href: "attivita.html", icon: "✅" },
      ]},
    ],
  },
  {
    id: "job", label: "Job (lavoro)", icon: "💼",
    sections: [
      { label: "Reparti", items: [
        { label: "Opportunità", icon: "🎯", children: [
          { label: "Tutte le offerte",     href: "job.html",             icon: "📋", sez: "job" },
          { label: "Candidature & Follow-up", href: "job-candidature.html", icon: "📨", sez: "job" },
          { label: "Manuale (funzioni & agenti)", href: "job-manuale.html", icon: "📖", sez: "job" },
        ]},
      ]},
    ],
  },
];

const TRASVERSALI = [
  { label: "Guida AI", href: "guida-ai.html", icon: "📘" },
];

const PERM = window.PERMESSI || { isAdmin: true, sezioni: [], email: "" };
const CURRENT = location.pathname.split("/").pop() || "index.html";

function puoVedere(sez) {
  return !sez || PERM.isAdmin || (PERM.sezioni || []).indexOf(sez) !== -1;
}
function esc(s) {
  return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
  });
}

// Trova una pagina nell'albero → { project?, section?, reparto?, page, global? }.
function findPage(href) {
  for (const g of GLOBAL) if (g.href === href) return { global: true, page: g };
  for (const p of TREE) {
    for (const sec of p.sections) {
      for (const it of sec.items) {
        if (it.href === href) return { project: p, section: sec, page: it };
        if (it.children) for (const c of it.children)
          if (c.href === href) return { project: p, section: sec, reparto: it, page: c };
      }
    }
  }
  return null;
}

// Progetto attivo: quello della pagina corrente, poi l'ultimo scelto, poi il primo.
function projettoAttivo() {
  const f = findPage(CURRENT);
  if (f && f.project) return f.project.id;
  try { var s = localStorage.getItem("nova_proj"); if (s) return s; } catch (e) {}
  return TREE[0].id;
}

function linkHtml(item) {
  if (!puoVedere(item.sez)) return "";
  const active = item.href === CURRENT ? " active" : "";
  return '<a class="nav-link' + active + '" href="' + item.href + '">' +
    (item.icon ? item.icon + " " : "") + esc(item.label) + "</a>";
}

function renderNav() {
  const host = document.getElementById("site-nav");
  if (!host) return;
  const attivo = projettoAttivo();
  const f = findPage(CURRENT);

  // Voci globali
  let menu = GLOBAL.map(linkHtml).join("");

  // Switcher progetto
  menu += '<div class="nav-proj-switch">' + TREE.map(function (p) {
    const on = p.id === attivo ? " active" : "";
    return '<button class="proj-chip' + on + '" data-proj="' + p.id + '">' + p.icon + " " + esc(p.label) + "</button>";
  }).join("") + "</div>";

  // Sezioni del progetto attivo
  const proj = TREE.find(function (p) { return p.id === attivo; });
  proj.sections.forEach(function (sec) {
    const blocchi = sec.items.map(function (it) {
      if (it.children) {
        const figli = it.children.map(linkHtml).filter(Boolean).join("");
        if (!figli) return "";
        const aperta = (f && f.reparto && f.reparto.label === it.label) ? " open" : "";
        return '<button class="nav-group" type="button">' + (it.icon || "") + " " + esc(it.label) +
          '<span class="caret">▾</span></button>' +
          '<div class="nav-sub' + aperta + '">' + figli + "</div>";
      }
      return linkHtml(it);
    }).filter(Boolean).join("");
    if (blocchi) menu += '<div class="nav-section">' + esc(sec.label) + "</div>" + blocchi;
  });

  // Trasversali + admin + staging + logout
  let altro = TRASVERSALI.map(linkHtml).filter(Boolean).join("");
  if (PERM.isAdmin) {
    altro += '<a class="nav-link" href="?action=admin">👤 Utenti</a>';
    altro += '<a class="nav-link" href="https://novastudio.company/dashboard-staging/" target="_blank" rel="noopener">🧪 Staging</a>';
  }
  if (altro) menu += '<div class="nav-section">Altro</div>' + altro;
  menu += '<a class="nav-link nav-logout" href="?action=logout">🚪 Esci</a>';

  host.innerHTML =
    '<div class="nav-inner">' +
      '<a class="brand" href="index.html">▣ NovaSTUDIO</a>' +
      '<button id="nav-toggle" class="nav-toggle" aria-label="Menu">☰</button>' +
      '<nav id="nav-links" class="nav-links">' + menu + "</nav>" +
    "</div>";

  // Toggle cassetto
  const toggle = host.querySelector("#nav-toggle");
  const linksEl = host.querySelector("#nav-links");
  toggle.addEventListener("click", function () { linksEl.classList.toggle("open"); });

  // Switch progetto → ricorda e ridisegna
  host.querySelectorAll(".proj-chip").forEach(function (b) {
    b.addEventListener("click", function () {
      try { localStorage.setItem("nova_proj", b.getAttribute("data-proj")); } catch (e) {}
      renderNav();
      document.getElementById("nav-links").classList.add("open");
    });
  });

  // Espandi/collassa reparto
  host.querySelectorAll(".nav-group").forEach(function (g) {
    g.addEventListener("click", function () {
      const sub = g.nextElementSibling;
      if (sub && sub.classList.contains("nav-sub")) sub.classList.toggle("open");
    });
  });
}

// ── Breadcrumb (Progetto › Reparto › Pagina) ──────────────────
function renderBreadcrumb() {
  const wrap = document.querySelector(".wrap");
  if (!wrap) return;
  const f = findPage(CURRENT);
  if (!f) return;
  const parts = [];
  if (f.project) { parts.push(f.project.label); if (f.reparto) parts.push(f.reparto.label); }
  parts.push(f.page.label);
  const html = parts.map(function (p, i) {
    return i < parts.length - 1
      ? "<span>" + esc(p) + '</span><span class="sep">›</span>'
      : "<strong>" + esc(p) + "</strong>";
  }).join("");
  const bar = document.createElement("div");
  bar.className = "nav-bread";
  bar.innerHTML = html;
  wrap.parentNode.insertBefore(bar, wrap);
}

// ── Aree del progetto (homepage) — invariata per ora ──────────
const AREE = [
  { titolo: "Redazione", desc: "Coda di approvazione articoli: leggi l'italiano, approva, chiedi modifiche.", href: "redazione.html", icon: "📰", stato: "attiva", sez: "redazione" },
  { titolo: "Manuale reparto", desc: "Ruoli, flusso, routine e automazioni della Redazione.", href: "manuale.html", icon: "📋", stato: "attiva", sez: "redazione" },
  { titolo: "SEO — Raccomandazioni", desc: "Azioni di ottimizzazione trovate sul sito live: approva, segna fatto o rifiuta con feedback.", href: "seo.html", icon: "🔍", stato: "attiva", sez: "seo" },
  { titolo: "SEO — Manuale", desc: "Come è fatto il reparto SEO: ruoli, flusso, Discover e feed Shopping.", href: "seo-manuale.html", icon: "📖", stato: "attiva", sez: "seo" },
  { titolo: "Posta", desc: "Caselle email, agenti del reparto e automazioni della posta.", href: "posta.html", icon: "📬", stato: "attiva", sez: "posta" },
  { titolo: "Da fare", desc: "Le attività del progetto: aggiungi, spunta, elimina.", href: "attivita.html", icon: "✅", stato: "attiva" },
  { titolo: "Mappa", desc: "I blocchi di valore da accendere, automazioni, componenti e idee.", href: "mappa.html", icon: "🗂️", stato: "attiva", sez: "mappa" },
  { titolo: "Infrastruttura", desc: "Rete Tailscale, macchine, servizi attivi e scorciatoie.", href: "infrastruttura.html", icon: "🗺️", stato: "attiva", sez: "infrastruttura" },
  { titolo: "Sistema", desc: "Strumenti installati e connettori (MCP), con il loro stato.", href: "sistema.html", icon: "🧩", stato: "attiva", sez: "sistema" },
  { titolo: "Automazioni", desc: "Tutto ciò che gira da solo: cosa fa, quando, dove, con che stato.", href: "automazioni.html", icon: "⚙️", stato: "attiva", sez: "automazioni" },
  { titolo: "Job — Opportunità", desc: "Offerte selezionate: best 3 in evidenza, tag, % attinenza, approva e dai feedback.", href: "job.html", icon: "💼", stato: "attiva", sez: "job" },
  { titolo: "Job — Candidature", desc: "Le candidature inviate, con note e promemoria di follow-up.", href: "job-candidature.html", icon: "📨", stato: "attiva", sez: "job" },
  { titolo: "Guida AI", desc: "Come creare agenti, automazioni e funzioni ben integrate.", href: "guida-ai.html", icon: "📘", stato: "attiva" },
];
function renderAree() {
  const host = document.getElementById("aree");
  if (!host) return;
  host.innerHTML = AREE.filter(function (a) { return puoVedere(a.sez); }).map(function (a) {
    const inner = '<div class="area-ic">' + a.icon + '</div>' +
      '<div class="area-titolo">' + esc(a.titolo) + "</div>" +
      '<div class="area-desc">' + esc(a.desc) + "</div>" +
      '<span class="area-tag">' + a.stato + "</span>";
    return '<a class="card area" href="' + a.href + '">' + inner + "</a>";
  }).join("");
}

renderNav();
renderBreadcrumb();
renderAree();
