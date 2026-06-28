// ───────────────────────────────────────────────────────────────
// Job — Candidature & Follow-up.
// Mostra SOLO le opportunità con stato "candidata" (application inviata).
// Stessi dati di job-dati.js (window.JOB.items). Le note di follow-up
// usano il campo commento via ?action=job_set.
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.JOB || { csrf: "", items: [] };
  var lista = document.getElementById("lista-cand");
  var conta = document.getElementById("jb-conta");
  if (!lista) return;

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function quando(ts) { return esc(new Date(Number(ts) * 1000).toLocaleString("it-IT")); }

  function render(items) {
    dati.items = items || [];
    var cand = dati.items.filter(function (x) { return x.stato === "candidata"; });
    if (conta) conta.textContent = cand.length + " candidature";

    if (!cand.length) {
      lista.innerHTML = '<li class="jb-vuoto">Nessuna candidatura inviata. Quando segni "Candidatura inviata" su un\'offerta, compare qui.</li>';
      return;
    }
    lista.innerHTML = cand.map(function (o) {
      var agg = o.aggiornata_il
        ? '<div class="jb-aggiornata">Ultimo aggiornamento: ' + quando(o.aggiornata_il) + "</div>" : "";
      var link = o.link
        ? '<a class="jb-link" href="' + esc(o.link) + '" target="_blank" rel="noopener">🔗 Apri l\'annuncio</a>' : "";
      return (
        '<li class="jb-card" data-id="' + esc(o.id) + '">' +
        '<div class="jb-top">' +
          (o.match_stelle ? '<span class="jb-match">' + esc(o.match_stelle) + "</span>" : "") +
          '<span class="jb-stato">📨 Candidatura inviata</span>' +
        "</div>" +
        '<div class="jb-nome">' + esc(o.nome) + "</div>" +
        '<div class="jb-azienda">' + esc(o.azienda) + (o.ruolo ? " · " + esc(o.ruolo) : "") + "</div>" +
        link +
        '<textarea class="jb-comm" placeholder="Note follow-up: data invio · contatto · esito · prossimo passo…">' + esc(o.commento) + "</textarea>" +
        '<div class="jb-hint">Suggerimento follow-up: se non rispondono entro 5-7 giorni, un messaggio breve e cortese rilancia la candidatura.</div>' +
        '<div class="jb-actions">' +
          '<button class="b-save" data-act="save">💾 Salva nota</button>' +
          '<button class="b-back" data-act="back">↩︎ Riporta in lista</button>' +
        "</div>" +
        agg +
        "</li>"
      );
    }).join("");
  }

  function post(extra) {
    var body = "csrf=" + encodeURIComponent(dati.csrf);
    for (var k in extra) body += "&" + k + "=" + encodeURIComponent(extra[k]);
    return fetch("?action=job_set", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body,
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.ok) render(j.items);
      return j;
    });
  }

  lista.addEventListener("click", function (e) {
    var act = e.target.getAttribute("data-act");
    if (!act) return;
    var card = e.target.closest("li[data-id]");
    if (!card) return;
    var id = card.getAttribute("data-id");
    var commento = (card.querySelector(".jb-comm") || {}).value || "";
    if (act === "save") {
      post({ id: id, stato: "candidata", commento: commento });
    } else if (act === "back") {
      if (!confirm("Riportare l'offerta tra le opportunità da decidere?")) return;
      post({ id: id, stato: "da_decidere", commento: commento });
    }
  });

  render(dati.items);
})();
