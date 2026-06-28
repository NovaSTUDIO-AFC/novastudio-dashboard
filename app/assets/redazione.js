// ───────────────────────────────────────────────────────────────
// Redazione — coda di approvazione bozze.
// Dati e CSRF iniettati server-side da assets/redazione-dati.js
// (window.REDAZIONE = { csrf, items }). Le azioni passano dal front
// controller (?action=appr_set) che risponde con la lista aggiornata.
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.REDAZIONE || { csrf: "", items: [] };
  var lista = document.getElementById("lista-bozze");
  var conta = document.getElementById("rz-conta");
  if (!lista) return;

  var STATO_LABEL = {
    da_approvare: "Da approvare",
    approvato: "Approvato",
    modifiche: "Modifiche",
    rifiutato: "Rifiutato",
  };

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function render(items) {
    dati.items = items || [];
    var daApprovare = dati.items.filter(function (x) { return x.stato === "da_approvare"; }).length;
    if (conta) conta.textContent = daApprovare + " da approvare";

    if (!dati.items.length) {
      lista.innerHTML = '<li class="rz-vuoto">Nessuna bozza in coda.</li>';
      return;
    }

    lista.innerHTML = dati.items.map(function (b) {
      var stato = b.stato || "da_approvare";
      var quando = b.aggiornata_il
        ? '<div class="rz-aggiornata">Ultimo aggiornamento: ' +
          esc(new Date(Number(b.aggiornata_il) * 1000).toLocaleString("it-IT")) + "</div>"
        : "";
      var img = b.immagine
        ? '<div class="rz-img"><img src="' + esc(b.immagine) + '" alt="" loading="lazy" /></div>'
        : '<div class="rz-img rz-img-vuota">🖼️ Immagine da generare — vedi il brief qui sotto</div>';

      var pass = Array.isArray(b.passaggi) ? b.passaggi : [];
      var passHtml = pass.length
        ? '<ol class="rz-steps">' + pass.map(function (p) {
            return '<li><span class="st-passo">' + esc(p.passo) + "</span>" +
              (p.esito ? '<span class="st-esito">' + esc(p.esito) + "</span>" : "") +
              (p.nota ? '<span class="st-nota">' + esc(p.nota) + "</span>" : "") + "</li>";
          }).join("") + "</ol>"
        : '<div class="rz-vuoto">Nessuno storico.</div>';

      return (
        '<li class="rz-card stato-' + esc(stato) + '" data-id="' + esc(b.id) + '">' +
        '<div class="rz-top">' +
          (b.categoria ? '<span class="rz-cat">' + esc(b.categoria) + "</span>" : "") +
          '<span class="rz-stato ' + esc(stato) + '">' + esc(STATO_LABEL[stato] || stato) + "</span>" +
        "</div>" +
        '<div class="rz-titolo">' + esc(b.titolo_en) + "</div>" +
        img +
        '<div class="rz-lang">🇮🇹 Italiano — versione per la tua approvazione</div>' +
        '<div class="rz-it">' + esc(b.corpo_it) + "</div>" +
        '<button class="rz-toggle" type="button">Mostra articolo EN (da pubblicare), brief immagine e fonte ▾</button>' +
        '<div class="rz-extra">' +
          '<h4>🇬🇧 Articolo EN (è ciò che va online)</h4>' +
          '<div class="body">' + esc(b.corpo_en) + "</div>" +
          "<h4>🖼️ Brief immagine</h4>" +
          '<div class="body">' + esc(b.brief_img) + "</div>" +
          (b.fonte ? '<h4>🔗 Fonte</h4><div class="body"><a href="' + esc(b.fonte) + '" target="_blank" rel="noopener">' + esc(b.fonte) + "</a></div>" : "") +
        "</div>" +
        '<button class="rz-toggle rz-toggle-storico" type="button">📜 Storico passaggi (' + pass.length + ") ▾</button>" +
        '<div class="rz-storico">' + passHtml + "</div>" +
        '<textarea class="rz-comm" placeholder="Commento per il reparto (obbligatorio se chiedi modifiche o rifiuti)…">' + esc(b.commento) + "</textarea>" +
        '<div class="rz-actions">' +
          '<button class="b-appr" data-stato="approvato">✅ Approva</button>' +
          '<button class="b-mod" data-stato="modifiche">✍️ Modifiche</button>' +
          '<button class="b-rif" data-stato="rifiutato">✕ Rifiuta</button>' +
        "</div>" +
        quando +
        "</li>"
      );
    }).join("");
  }

  function azione(extra) {
    var body = "csrf=" + encodeURIComponent(dati.csrf);
    for (var k in extra) body += "&" + k + "=" + encodeURIComponent(extra[k]);
    return fetch("?action=appr_set", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body,
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.ok) render(j.items);
      return j;
    });
  }

  lista.addEventListener("click", function (e) {
    var card = e.target.closest("li[data-id]");
    if (!card) return;

    if (e.target.classList.contains("rz-toggle-storico")) {
      var st = card.querySelector(".rz-storico");
      if (st) st.classList.toggle("open");
      return;
    }
    if (e.target.classList.contains("rz-toggle")) {
      var extra = card.querySelector(".rz-extra");
      if (extra) extra.classList.toggle("open");
      return;
    }

    var stato = e.target.getAttribute("data-stato");
    if (!stato) return;
    var id = card.getAttribute("data-id");
    var commento = (card.querySelector(".rz-comm") || {}).value || "";

    if ((stato === "modifiche" || stato === "rifiutato") && !commento.trim()) {
      alert("Aggiungi un commento: serve al reparto per capire cosa cambiare.");
      return;
    }
    if (stato === "rifiutato" && !confirm("Rifiutare questa bozza?")) return;

    azione({ id: id, stato: stato, commento: commento });
  });

  render(dati.items);
})();
