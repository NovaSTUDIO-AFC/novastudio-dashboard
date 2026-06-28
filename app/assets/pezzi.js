// ───────────────────────────────────────────────────────────────
// GIUNTO SEO ↔ REDAZIONE — pipeline contenuti a due gate (front-end).
// Dati iniettati da assets/pezzo-dati.js:
//   window.PEZZI = { csrf, items:[...], puoSeo:bool, puoRil:bool }
// Gate SEO lo setta chi ha permesso SEO; Gate Rilevanza chi ha Redazione.
// Stesso pattern di seo.js. Le decisioni vivono nello store.
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.PEZZI || { csrf: "", items: [], puoSeo: false, puoRil: false };
  var lista = document.getElementById("lista-pezzi");
  var conta = document.getElementById("pz-conta");
  if (!lista) return;

  var STATO_LABEL = { in_valutazione: "In valutazione", in_pipeline: "In pipeline", scartato: "Scartato", promosso: "Promosso → coda" };
  var ESITO_LABEL = { in_attesa: "In attesa", ok: "OK", no: "No" };

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function gateBlock(rec, gate, puo) {
    var esito = gate === "seo" ? (rec.gate_seo || "in_attesa") : (rec.gate_rilevanza || "in_attesa");
    var nota = gate === "seo" ? (rec.gate_seo_nota || "") : (rec.gate_rilevanza_nota || "");
    var titolo = gate === "seo" ? "Gate SEO — utile per ranking + AI/LLM?" : "Gate Rilevanza — pertinente nicchia Simracing?";
    var ph = gate === "seo" ? "Nota SEO (domanda, competizione, AI…)" : "Nota rilevanza (perché sì/no per la nicchia)";
    var controls = puo
      ? '<div class="row">' +
          '<button class="b-ok" data-gate="' + gate + '" data-esito="ok">✓ OK</button>' +
          '<button class="b-no" data-gate="' + gate + '" data-esito="no">✗ No</button>' +
        "</div>"
      : '<div class="locked">🔒 lo decide il reparto ' + (gate === "seo" ? "SEO" : "Redazione") + "</div>";
    return (
      '<div class="pz-gate ' + gate + '">' +
        "<h4>" + esc(titolo) + ' &nbsp; <span class="pz-esito ' + esc(esito) + '">' + esc(ESITO_LABEL[esito] || esito) + "</span></h4>" +
        '<textarea class="pz-nota" data-gate="' + gate + '" placeholder="' + esc(ph) + '">' + esc(nota) + "</textarea>" +
        controls +
      "</div>"
    );
  }

  function render(items) {
    if (items) dati.items = items;
    var daVal = dati.items.filter(function (x) { return x.stato === "in_valutazione"; }).length;
    if (conta) conta.textContent = daVal + " in valutazione";

    if (!dati.items.length) {
      lista.innerHTML = '<li class="pz-vuoto">Nessun pezzo in pipeline.</li>';
      return;
    }

    lista.innerHTML = dati.items.map(function (rec) {
      var stato = rec.stato || "in_valutazione";
      var orig = rec.origine || "redazione";
      return (
        '<li class="pz-card stato-' + esc(stato) + '" data-id="' + esc(rec.id) + '">' +
          '<div class="pz-top">' +
            '<span class="pz-badge pz-orig ' + esc(orig) + '">origine: ' + esc(orig) + "</span>" +
            '<span class="pz-tipo">' + esc(rec.tipo || "post") + "</span>" +
            (Number(rec.discover) ? '<span class="pz-disc">Discover</span>' : "") +
            '<span class="pz-stato ' + esc(stato) + '">' + esc(STATO_LABEL[stato] || stato) + "</span>" +
          "</div>" +
          '<div class="pz-titolo">' + esc(rec.titolo) + "</div>" +
          (rec.fonte ? '<div class="pz-fonte">' + esc(rec.fonte) + "</div>" : "") +
          '<div class="pz-meta">' +
            (rec.keyword_target ? "<b>kw:</b> " + esc(rec.keyword_target) + " &nbsp; " : "") +
            (rec.search_intent ? "<b>intent:</b> " + esc(rec.search_intent) : "") +
          "</div>" +
          (rec.come_spec
            ? '<div class="pz-come"><h4>Spec COME (Reparto SEO)</h4><div class="body">' + esc(rec.come_spec) + "</div></div>"
            : "") +
          (Number(rec.testo && rec.testo.length) ? '<div class="pz-meta">📄 <b>pregresso</b>: articolo già scritto (' + esc(String(rec.testo).length) + ' char) — la validazione legge il testo reale</div>' : "") +
          '<div class="pz-gates">' +
            gateBlock(rec, "seo", !!dati.puoSeo) +
            gateBlock(rec, "rilevanza", !!dati.puoRil) +
          "</div>" +
          (stato === "in_pipeline"
            ? '<div class="seo-actions" style="margin-top:10px"><button class="b-appr" data-promuovi="1">⏩ Promuovi a bozza (coda approvazioni)</button></div>'
            : "") +
          (stato === "promosso" && rec.bozza_slug
            ? '<div class="pz-fonte" style="margin-top:8px">✅ promosso in coda approvazioni come <b>' + esc(rec.bozza_slug) + "</b></div>"
            : "") +
        "</li>"
      );
    }).join("");
  }

  lista.addEventListener("click", function (e) {
    // Promozione → coda approvazioni
    var pbtn = e.target.closest("button[data-promuovi]");
    if (pbtn) {
      var pcard = e.target.closest("li[data-id]");
      if (!pcard) return;
      var pid = pcard.getAttribute("data-id");
      var pbody = "csrf=" + encodeURIComponent(dati.csrf) + "&id=" + encodeURIComponent(pid);
      fetch("?action=pezzo_promuovi", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: pbody,
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (j && j.ok) render(j.items);
      });
      return;
    }
    var btn = e.target.closest("button[data-gate][data-esito]");
    if (!btn) return;
    var card = e.target.closest("li[data-id]");
    if (!card) return;
    var gate = btn.getAttribute("data-gate");
    var esito = btn.getAttribute("data-esito");
    var id = card.getAttribute("data-id");
    var nota = "";
    var ta = card.querySelector('textarea.pz-nota[data-gate="' + gate + '"]');
    if (ta) nota = ta.value;
    if (esito === "no" && !nota.trim()) {
      alert("Aggiungi una nota: serve a spiegare perché è No.");
      return;
    }
    var body = "csrf=" + encodeURIComponent(dati.csrf) +
      "&id=" + encodeURIComponent(id) + "&gate=" + encodeURIComponent(gate) +
      "&esito=" + encodeURIComponent(esito) + "&nota=" + encodeURIComponent(nota);
    fetch("?action=pezzo_set", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body,
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.ok) render(j.items);
    });
  });

  render(dati.items);
})();
