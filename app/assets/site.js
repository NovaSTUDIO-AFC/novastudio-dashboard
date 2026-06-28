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
// FONTE UNICA: la home (renderAree) è derivata da questo stesso albero — il
// campo `desc` alimenta le card della homepage. Aggiungere una pagina qui la
// fa comparire sia nel menu sia nella home (niente più doppia lista da allineare).
const TREE = [
  {
    id: "simracing", label: "Simracing.Fan", icon: "◆",
    sections: [
      { label: "Reparti", items: [
        { label: "Redazione", icon: "📰", children: [
          { label: "Coda approvazioni",   href: "redazione.html", icon: "✅", sez: "redazione", desc: "Coda di approvazione articoli: leggi l'italiano, approva, chiedi modifiche." },
          { label: "Manuale (istruzioni)", href: "manuale.html",   icon: "📋", sez: "redazione", desc: "Ruoli, flusso, routine e automazioni della Redazione." },
        ]},
        // Nota: la "Pipeline contenuti" (validazione SEO + pertinenza) e un PASSAGGIO
        // INTERNO del ciclo redazionale (come fact-check, editing): NON ha una voce di
        // menu. Pagina pezzi.html resta solo come vista interna/diagnostica (per-URL).
        { label: "SEO", icon: "🔍", children: [
          { label: "Raccomandazioni", href: "seo.html",         icon: "💡", sez: "seo", desc: "Azioni di ottimizzazione trovate sul sito live: approva, segna fatto o rifiuta con feedback." },
          { label: "Manuale",         href: "seo-manuale.html", icon: "📖", sez: "seo", desc: "Come è fatto il reparto SEO: ruoli, flusso, Discover e feed Shopping." },
        ]},
        { label: "Commerciale", icon: "🤝", children: [
          { label: "Coda prospect", href: "commerciale.html", icon: "✅", sez: "commerciale", desc: "Prospect outbound con email già pronta: approva, chiedi modifiche o rifiuta. Niente parte senza il tuo ok." },
        ]},
        { label: "Posta", href: "posta.html", icon: "📬", sez: "posta", desc: "Caselle email, agenti del reparto e automazioni della posta." },
      ]},
      { label: "Asset", items: [] }, // libreria foto, ecc. — in arrivo
    ],
  },
  {
    id: "novastudio", label: "NovaSTUDIO", icon: "▣",
    sections: [
      { label: "Reparti", items: [
        { label: "Sicurezza", icon: "🛡️", children: [
          { label: "Stato siti",         href: "sicurezza.html",           icon: "🟢", sez: "sicurezza", desc: "Esito dell'ultima scansione di sicurezza dei siti: pulito o da controllare, per dominio." },
          { label: "Checklist password", href: "sicurezza-checklist.html", icon: "🔑", sez: "sicurezza", desc: "Checklist del cambio password post-incidente: spunta man mano, l'ordine è per rischio." },
          { label: "Manuale",            href: "sicurezza-manuale.html",   icon: "📖", sez: "sicurezza", desc: "Come funziona il reparto Sicurezza: scansione settimanale, regola d'oro, incidente." },
        ]},
      ]},
      { label: "Asset", items: [
        { label: "Infrastruttura", href: "infrastruttura.html", icon: "🗺️", sez: "infrastruttura", desc: "Rete Tailscale, macchine, servizi attivi e scorciatoie." },
        { label: "Sistema",        href: "sistema.html",        icon: "🧩", sez: "sistema", desc: "Strumenti installati e connettori (MCP), con il loro stato." },
        { label: "Mappa",          href: "mappa.html",          icon: "🗂️", sez: "mappa", desc: "I blocchi di valore da accendere, automazioni, componenti e idee." },
        { label: "Automazioni",    href: "automazioni.html",    icon: "⚙️", sez: "automazioni", desc: "Tutto ciò che gira da solo: cosa fa, quando, dove, con che stato." },
      ]},
      { label: "Operativo", items: [
        { label: "Da fare", href: "attivita.html", icon: "✅", desc: "Le attività del progetto: aggiungi, spunta, elimina." },
      ]},
    ],
  },
  {
    id: "job", label: "Job", icon: "💼",
    sections: [
      { label: "Reparti", items: [
        { label: "Opportunità", icon: "🎯", children: [
          { label: "Tutte le offerte",     href: "job.html",             icon: "📋", sez: "job", desc: "Offerte selezionate: best in evidenza, tag, % attinenza, approva e dai feedback." },
          { label: "Candidature & Follow-up", href: "job-candidature.html", icon: "📨", sez: "job", desc: "Le candidature inviate, con note e promemoria di follow-up." },
          { label: "Manuale (funzioni & agenti)", href: "job-manuale.html", icon: "📖", sez: "job", desc: "Come funziona il reparto Job: funzioni e agenti." },
        ]},
      ]},
    ],
  },
];

const TRASVERSALI = [
  { label: "Guida AI", href: "guida-ai.html", icon: "📘", desc: "Come creare agenti, automazioni e funzioni ben integrate." },
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

  // Progetti come ACCORDION: tutti elencati, quello attivo aperto.
  menu += TREE.map(function (p) {
    const aperto = p.id === attivo;
    const sezioni = p.sections.map(function (sec) {
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
      return blocchi ? '<div class="nav-section">' + esc(sec.label) + "</div>" + blocchi : "";
    }).join("");
    if (!sezioni) return ""; // progetto senza voci visibili → salta
    return '<button class="nav-proj' + (aperto ? " open" : "") + '" type="button" data-proj="' + p.id +
        '" aria-expanded="' + (aperto ? "true" : "false") + '">' +
        '<span class="nav-proj-nm">' + p.icon + " " + esc(p.label) + '</span><span class="caret">▾</span></button>' +
      '<div class="nav-proj-body' + (aperto ? " open" : "") + '">' + sezioni + "</div>";
  }).join("");

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
      '<button id="nav-toggle" class="nav-toggle" aria-label="Apri menu" aria-expanded="false">☰ Menu</button>' +
      '<nav id="nav-links" class="nav-links">' + menu + "</nav>" +
    "</div>";

  // Toggle cassetto
  const toggle = host.querySelector("#nav-toggle");
  const linksEl = host.querySelector("#nav-links");
  toggle.addEventListener("click", function () {
    const open = linksEl.classList.toggle("open");
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  });

  // Accordion progetti: apri quello cliccato, chiudi gli altri, ricorda la scelta.
  host.querySelectorAll(".nav-proj").forEach(function (b) {
    b.addEventListener("click", function () {
      const giaAperto = b.classList.contains("open");
      host.querySelectorAll(".nav-proj").forEach(function (x) {
        x.classList.remove("open"); x.setAttribute("aria-expanded", "false");
        const bd = x.nextElementSibling;
        if (bd && bd.classList.contains("nav-proj-body")) bd.classList.remove("open");
      });
      if (!giaAperto) {
        b.classList.add("open"); b.setAttribute("aria-expanded", "true");
        const body = b.nextElementSibling;
        if (body && body.classList.contains("nav-proj-body")) body.classList.add("open");
        try { localStorage.setItem("nova_proj", b.getAttribute("data-proj")); } catch (e) {}
      }
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

// ── Aree del progetto (homepage) — DERIVATA dal TREE (fonte unica) ──
// Appiattisce l'albero in card (una per pagina visibile), in ordine di progetto.
// Niente più badge "attiva" su ogni card: quando tutto è evidenziato, niente lo è.
function areeFromTree() {
  const out = [];
  TREE.forEach(function (p) {
    p.sections.forEach(function (sec) {
      sec.items.forEach(function (it) {
        if (it.href) out.push({ titolo: it.label, desc: it.desc, href: it.href, icon: it.icon, sez: it.sez });
        if (it.children) it.children.forEach(function (c) {
          out.push({ titolo: it.label + " — " + c.label, desc: c.desc, href: c.href, icon: c.icon, sez: c.sez });
        });
      });
    });
  });
  TRASVERSALI.forEach(function (t) {
    out.push({ titolo: t.label, desc: t.desc, href: t.href, icon: t.icon, sez: t.sez });
  });
  return out;
}
function renderAree() {
  const host = document.getElementById("aree");
  if (!host) return;
  host.innerHTML = areeFromTree().filter(function (a) { return puoVedere(a.sez); }).map(function (a) {
    const inner = '<div class="area-ic">' + (a.icon || "") + '</div>' +
      '<div class="area-titolo">' + esc(a.titolo) + "</div>" +
      '<div class="area-desc">' + esc(a.desc || "") + "</div>";
    return '<a class="card area" href="' + a.href + '">' + inner + "</a>";
  }).join("");
}

// ── Accessibilità: skip-link + landmark <main> (vale per tutte le pagine) ──
function a11yLandmarks() {
  const main = document.querySelector(".wrap");
  if (main && !main.id) {
    main.id = "main"; main.setAttribute("role", "main"); main.setAttribute("tabindex", "-1");
  }
  if (!document.querySelector(".skip-link")) {
    const skip = document.createElement("a");
    skip.className = "skip-link"; skip.href = "#main"; skip.textContent = "Salta al contenuto";
    document.body.insertBefore(skip, document.body.firstChild);
  }
}

a11yLandmarks();
renderNav();
renderBreadcrumb();
renderAree();
