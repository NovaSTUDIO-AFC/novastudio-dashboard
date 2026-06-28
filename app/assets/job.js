// ───────────────────────────────────────────────────────────────
// Job — opportunità di lavoro.
// Dati e CSRF iniettati server-side da assets/job-dati.js
// (window.JOB = { csrf, items, feedback }). Le azioni passano dal
// front controller (?action=job_set / ?action=job_feedback).
// Le opportunità "candidata" (application inviata) NON si mostrano qui:
// vivono nella pagina Follow-up (job-candidature.html).
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.JOB || { csrf: "", items: [], feedback: [] };
  var top = document.getElementById("lista-top");
  var lista = document.getElementById("lista-job");
  var conta = document.getElementById("jb-conta");
  var fbList = document.getElementById("jb-fb-list");
  if (!lista) return;

  var STATO_LABEL = {
    da_decidere: "Da decidere",
    approvata: "Mossa approvata",
    tieni: "Tieni d'occhio",
    scartata: "Scartata",
    candidata: "Candidatura inviata",
  };

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function quando(ts) { return esc(new Date(Number(ts) * 1000).toLocaleString("it-IT")); }

  function cardHtml(o) {
    var stato = o.stato || "da_decidere";
    var agg = o.aggiornata_il
      ? '<div class="jb-aggiornata">Ultimo aggiornamento: ' + quando(o.aggiornata_il) + "</div>" : "";
    var link = o.link
      ? '<a class="jb-link" href="' + esc(o.link) + '" target="_blank" rel="noopener">🔗 Apri l\'annuncio</a>' : "";
    var ral = o.ral ? '<div class="jb-ral">💶 ' + esc(o.ral) + "</div>" : "";

    // Tag rapidi
    var tags = "";
    if (Number(o.parttime)) tags += '<span class="jb-tag pt">⏱️ Part-time</span>';
    if (o.ral) tags += '<span class="jb-tag">💶 ' + esc(String(o.ral).split(" · ")[0]) + "</span>";

    // Percentuali
    var fp = Math.max(0, Math.min(100, Number(o.fit_profilo) || 0));
    var fpr = Math.max(0, Math.min(100, Number(o.fit_preferenze) || 0));
    var metrics =
      '<div class="jb-metrics">' +
        '<div class="jb-metric"><div class="m-top"><span>🎯 Attinenza profilo</span><b>' + fp + '%</b></div>' +
          '<div class="m-bar"><i style="width:' + fp + '%"></i></div></div>' +
        '<div class="jb-metric"><div class="m-top"><span>❤️ Match preferenze</span><b>' + fpr + '%</b></div>' +
          '<div class="m-bar pref"><i style="width:' + fpr + '%"></i></div></div>' +
      "</div>";

    var alg = [];
    try { alg = o.allegati ? (typeof o.allegati === "string" ? JSON.parse(o.allegati) : o.allegati) : []; }
    catch (e) { alg = []; }
    var allegatiHtml = (alg && alg.length)
      ? '<div class="jb-allegati">📎 Allegati pronti: ' + alg.map(function (a) {
          return '<a href="' + esc(a.file) + '" target="_blank" rel="noopener">' + esc(a.label) + "</a>";
        }).join(" · ") + "</div>"
      : "";

    return (
      '<li class="jb-card stato-' + esc(stato) + (Number(o.top) ? " is-top" : "") + '" data-id="' + esc(o.id) + '">' +
      '<div class="jb-top">' +
        (o.match_stelle ? '<span class="jb-match">' + esc(o.match_stelle) + "</span>" : "") +
        '<span class="jb-stato ' + esc(stato) + '">' + esc(STATO_LABEL[stato] || stato) + "</span>" +
      "</div>" +
      '<div class="jb-nome">' + esc(o.nome) + "</div>" +
      '<div class="jb-azienda">' + esc(o.azienda) + (o.ruolo ? " · " + esc(o.ruolo) : "") + "</div>" +
      '<div class="jb-tags">' + tags + "</div>" +
      (o.materiali ? '<div class="jb-badge">📄 Materiali pronti — rivedi e dai un feedback</div>' : "") +
      link +
      metrics +
      '<div class="jb-desc">' + esc(o.descrizione) + "</div>" +
      '<div class="jb-box"><h4>📊 La mia valutazione</h4><div class="body">' + esc(o.valutazione) + "</div></div>" +
      '<div class="jb-box sugg"><h4>👉 Prossima mossa suggerita</h4><div class="body">' + esc(o.suggerimento) + "</div></div>" +
      (o.materiali ? '<button class="jb-mat-toggle" type="button">📄 Vedi i materiali pronti (CV + email) ▾</button><div class="jb-mat"><pre>' + esc(o.materiali) + "</pre></div>" : "") +
      allegatiHtml +
      '<textarea class="jb-comm" placeholder="' + (o.materiali ? "Feedback sui materiali o sulla mossa (modifiche, ok…)" : "Commento per il reparto (perché approvi/scarti, modifiche al pitch…)") + '">' + esc(o.commento) + "</textarea>" +
      '<div class="jb-actions">' +
        '<button class="b-appr" data-stato="approvata">✅ Approva mossa</button>' +
        '<button class="b-tieni" data-stato="tieni">⏸️ Tieni d\'occhio</button>' +
        '<button class="b-scarta" data-stato="scartata">✕ Scarta</button>' +
        '<button class="b-cand" data-stato="candidata">📨 Candidatura inviata</button>' +
      "</div>" +
      agg +
      "</li>"
    );
  }

  function render(items) {
    dati.items = items || [];
    var attive = dati.items.filter(function (x) { return x.stato !== "candidata"; });
    var daDecidere = attive.filter(function (x) { return x.stato === "da_decidere"; }).length;
    if (conta) conta.textContent = daDecidere + " da decidere";

    // Sezione in evidenza (le best 3 = top), escluse quelle già candidate.
    var featured = attive.filter(function (x) { return Number(x.top); });
    var resto = attive.filter(function (x) { return !Number(x.top); });

    if (top) {
      top.innerHTML = featured.length
        ? featured.map(cardHtml).join("")
        : '<li class="jb-vuoto">Nessuna offerta in evidenza.</li>';
    }
    lista.innerHTML = resto.length
      ? resto.map(cardHtml).join("")
      : '<li class="jb-vuoto">Nessuna altra offerta.</li>';
  }

  function renderFeedback(items) {
    dati.feedback = items || [];
    if (!fbList) return;
    fbList.innerHTML = dati.feedback.map(function (f) {
      return "<li>" + esc(f.testo) + '<span class="when">' + quando(f.creato_il) + "</span></li>";
    }).join("");
  }

  function post(action, extra) {
    var body = "csrf=" + encodeURIComponent(dati.csrf);
    for (var k in extra) body += "&" + k + "=" + encodeURIComponent(extra[k]);
    return fetch("?action=" + action, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body,
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.ok) { render(j.items); renderFeedback(j.feedback); }
      return j;
    });
  }

  function onClick(e) {
    var card = e.target.closest("li[data-id]");
    if (!card) return;
    if (e.target.classList.contains("jb-mat-toggle")) {
      var m = card.querySelector(".jb-mat"); if (m) m.classList.toggle("open");
      return;
    }
    var stato = e.target.getAttribute("data-stato");
    if (!stato) return;
    var id = card.getAttribute("data-id");
    var commento = (card.querySelector(".jb-comm") || {}).value || "";
    if (stato === "scartata" && !confirm("Scartare questa opportunità?")) return;
    if (stato === "candidata" && !confirm("Segnare la candidatura come INVIATA? Si sposta nella pagina Follow-up.")) return;
    post("job_set", { id: id, stato: stato, commento: commento });
  }
  if (top) top.addEventListener("click", onClick);
  lista.addEventListener("click", onClick);

  // Feedback per indirizzare le ricerche
  var fbBtn = document.getElementById("jb-fb-invia");
  var fbTesto = document.getElementById("jb-fb-testo");
  if (fbBtn && fbTesto) {
    fbBtn.addEventListener("click", function () {
      var t = fbTesto.value.trim();
      if (!t) { fbTesto.focus(); return; }
      post("job_feedback", { testo: t }).then(function (j) { if (j && j.ok) fbTesto.value = ""; });
    });
  }

  render(dati.items);
  renderFeedback(dati.feedback);
})();
