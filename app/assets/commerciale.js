// ───────────────────────────────────────────────────────────────
// Commerciale — coda di approvazione prospect outbound.
// Dati e CSRF iniettati server-side da assets/commerciale-dati.js
// (window.COMMERCIALE = { csrf, items }). Le azioni passano dal front
// controller (?action=comm_set) che risponde con la lista aggiornata.
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.COMMERCIALE || { csrf: "", items: [] };
  var lista = document.getElementById("lista-prospect");
  var conta = document.getElementById("cm-conta");
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
      lista.innerHTML = '<li class="cm-vuoto">Nessun prospect in coda.</li>';
      return;
    }

    lista.innerHTML = dati.items.map(function (p) {
      var stato = p.stato || "da_approvare";
      var quando = p.aggiornata_il
        ? '<div class="cm-aggiornata">Ultimo aggiornamento: ' +
          esc(new Date(Number(p.aggiornata_il) * 1000).toLocaleString("it-IT")) + "</div>"
        : "";
      var role = Number(p.email_role_based)
        ? '<span class="cm-tag">✉️ role-based</span>' : "";
      var src = p.enrich ? '<span class="cm-tag">fonte: ' + esc(p.enrich) + "</span>" : "";

      return (
        '<li class="cm-card stato-' + esc(stato) + '" data-id="' + esc(p.id) + '">' +
        '<div class="cm-top">' +
          (p.lingua ? '<span class="cm-tag">' + esc(p.lingua) + "</span>" : "") +
          role + src +
          '<span class="cm-stato ' + esc(stato) + '">' + esc(STATO_LABEL[stato] || stato) + "</span>" +
        "</div>" +
        '<div class="cm-azienda">' + esc(p.company) + "</div>" +
        '<div class="cm-email">→ ' + esc(p.email) +
          (p.domain ? ' · <a href="https://' + esc(p.domain) + '" target="_blank" rel="noopener">' + esc(p.domain) + "</a>" : "") +
        "</div>" +
        '<div class="cm-subject"><b>Oggetto:</b> ' + esc(p.subject) + "</div>" +
        '<div class="cm-corpo">' + esc(p.corpo) + "</div>" +
        (p.note ? '<button class="cm-toggle" type="button">Mostra nota tecnica ▾</button>' +
          '<div class="cm-extra"><div class="nota">' + esc(p.note) + "</div></div>" : "") +
        '<textarea class="cm-comm" placeholder="Commento per il reparto (obbligatorio se chiedi modifiche o rifiuti)…">' + esc(p.commento) + "</textarea>" +
        '<div class="cm-actions">' +
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
    return fetch("?action=comm_set", {
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

    if (e.target.classList.contains("cm-toggle")) {
      var extra = card.querySelector(".cm-extra");
      if (extra) extra.classList.toggle("open");
      return;
    }

    var stato = e.target.getAttribute("data-stato");
    if (!stato) return;
    var id = card.getAttribute("data-id");
    var commento = (card.querySelector(".cm-comm") || {}).value || "";

    if ((stato === "modifiche" || stato === "rifiutato") && !commento.trim()) {
      alert("Aggiungi un commento: serve al reparto per capire cosa cambiare.");
      return;
    }
    if (stato === "rifiutato" && !confirm("Rifiutare questo prospect?")) return;

    azione({ id: id, stato: stato, commento: commento });
  });

  render(dati.items);
})();
