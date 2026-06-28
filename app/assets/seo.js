// ───────────────────────────────────────────────────────────────
// REPARTO SEO — coda di raccomandazioni (front-end).
// Dati iniettati server-side da assets/seo-dati.js:
//   window.SEO = { csrf, items:[{id,slug,titolo,url,tipo,priorita,
//                  descrizione,azione_suggerita,stato,commento,aggiornata_il}], feedback:[] }
// Stesso pattern di job.js / redazione.js. Le decisioni vivono nello store.
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.SEO || { csrf: "", items: [], feedback: [] };
  var lista = document.getElementById("lista-seo");
  var conta = document.getElementById("seo-conta");
  if (!lista) return;

  var STATO_LABEL = {
    da_decidere: "Da decidere",
    approvata: "Approvata",
    implementata: "Implementata",
    rifiutata: "Rifiutata",
  };

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function quando(ts) {
    if (!ts) return "";
    try { return new Date(Number(ts) * 1000).toLocaleString("it-IT"); }
    catch (e) { return ""; }
  }

  function render(items) {
    if (items) dati.items = items;
    var daDecidere = dati.items.filter(function (x) { return x.stato === "da_decidere"; }).length;
    if (conta) conta.textContent = daDecidere + " da decidere";

    if (!dati.items.length) {
      lista.innerHTML = '<li class="seo-vuoto">Nessuna raccomandazione in coda.</li>';
      return;
    }

    lista.innerHTML = dati.items.map(function (r) {
      var stato = r.stato || "da_decidere";
      var prio = r.priorita || "medio";
      return (
        '<li class="seo-card stato-' + esc(stato) + '" data-id="' + esc(r.id) + '">' +
          '<div class="seo-top">' +
            '<span class="seo-prio ' + esc(prio) + '">' + esc(prio.toUpperCase()) + "</span>" +
            (r.tipo ? '<span class="seo-tipo">' + esc(r.tipo) + "</span>" : "") +
            '<span class="seo-stato ' + esc(stato) + '">' + esc(STATO_LABEL[stato] || stato) + "</span>" +
          "</div>" +
          '<div class="seo-titolo">' + esc(r.titolo) + "</div>" +
          (r.url ? '<a class="seo-url" href="' + esc(r.url) + '" target="_blank" rel="noopener">🔗 ' + esc(r.url) + "</a>" : "") +
          (r.descrizione ? '<div class="seo-desc">' + esc(r.descrizione) + "</div>" : "") +
          (r.azione_suggerita
            ? '<div class="seo-box"><h4>Azione suggerita</h4><div class="body">' + esc(r.azione_suggerita) + "</div></div>"
            : "") +
          '<textarea class="seo-comm" placeholder="Commento per il reparto (obbligatorio se rifiuti)…">' + esc(r.commento) + "</textarea>" +
          '<div class="seo-actions">' +
            '<button class="b-appr" data-stato="approvata">✅ Approva</button>' +
            '<button class="b-impl" data-stato="implementata">📌 Fatta</button>' +
            '<button class="b-rif" data-stato="rifiutata">✕ Rifiuta</button>' +
          "</div>" +
          (r.aggiornata_il ? '<div class="seo-aggiornata">Aggiornata: ' + esc(quando(r.aggiornata_il)) + "</div>" : "") +
        "</li>"
      );
    }).join("");
  }

  function renderFeedback() {
    var fbList = document.getElementById("seo-fb-list");
    if (!fbList) return;
    fbList.innerHTML = (dati.feedback || []).map(function (f) {
      return '<li>' + esc(f.testo) + '<span class="when">' + esc(quando(f.creato_il)) + "</span></li>";
    }).join("");
  }

  function post(action, params) {
    var body = "csrf=" + encodeURIComponent(dati.csrf);
    for (var k in params) body += "&" + k + "=" + encodeURIComponent(params[k]);
    return fetch("?action=" + action, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body,
    }).then(function (res) { return res.json(); });
  }

  lista.addEventListener("click", function (e) {
    var btn = e.target.closest("button[data-stato]");
    if (!btn) return;
    var card = e.target.closest("li[data-id]");
    if (!card) return;
    var stato = btn.getAttribute("data-stato");
    var id = card.getAttribute("data-id");
    var commento = (card.querySelector(".seo-comm") || {}).value || "";
    if (stato === "rifiutata" && !commento.trim()) {
      alert("Aggiungi un commento: serve al reparto per capire perché.");
      return;
    }
    post("seo_set", { id: id, stato: stato, commento: commento }).then(function (j) {
      if (j && j.ok) { dati.feedback = j.feedback || dati.feedback; render(j.items); renderFeedback(); }
    });
  });

  var fbBtn = document.getElementById("seo-fb-invia");
  if (fbBtn) fbBtn.addEventListener("click", function () {
    var ta = document.getElementById("seo-fb-testo");
    if (!ta) return;
    var testo = ta.value.trim();
    if (!testo) { alert("Scrivi un feedback."); return; }
    post("seo_feedback", { testo: testo }).then(function (j) {
      if (j && j.ok) { ta.value = ""; dati.feedback = j.feedback || []; renderFeedback(); }
    });
  });

  render(dati.items);
  renderFeedback();
})();
