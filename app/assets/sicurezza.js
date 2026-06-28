// ───────────────────────────────────────────────────────────────
// REPARTO SICUREZZA — stato siti (front-end, sola lettura).
// Dati iniettati server-side da assets/sicurezza-dati.js:
//   window.SICUREZZA = { report: { quando, dominî:[{dominio, ioc_file,
//     cartelle_rogue, firme, php_in_upload, php_recenti, errore?}], allarmi:[] } }
// Niente azioni: la bonifica è un gesto umano con Max (gate di approvazione).
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.SICUREZZA || { report: null };
  var rep = dati.report || { quando: null, "dominî": [], allarmi: [] };
  var domini = rep["dominî"] || [];
  var lista = document.getElementById("lista-sec");
  var conta = document.getElementById("sec-conta");
  var banner = document.getElementById("sec-banner");
  var quandoEl = document.getElementById("sec-quando");
  if (!lista) return;

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // Un dominio è in allarme se ha indicatori ad alta confidenza (php_recenti è solo contesto).
  function allarme(e) {
    return !!e.errore || e.ioc_file > 0 || e.cartelle_rogue > 0 || e.firme > 0 || e.php_in_upload > 0;
  }

  if (!rep.quando) {
    banner.className = "sec-banner ko";
    banner.textContent = "Nessuna scansione disponibile ancora. Il primo report comparirà dopo lo sweep settimanale (o chiedi a Max di lanciarlo).";
    conta.textContent = "—";
    return;
  }

  var inAllarme = domini.filter(allarme);
  quandoEl.textContent = "Ultima scansione: " + esc(rep.quando);
  conta.textContent = domini.length + " domini · " + inAllarme.length + " da controllare";

  if (inAllarme.length === 0) {
    banner.className = "sec-banner ok";
    banner.innerHTML = "🟢 <b>Tutti i siti puliti.</b> Nessuna firma malware, IOC o PHP sospetto nell'ultima scansione.";
  } else {
    banner.className = "sec-banner ko";
    banner.innerHTML = "🔴 <b>" + inAllarme.length + " sito/i da controllare.</b> Chiedi a Max: <i>\"scansiona a fondo &lt;dominio&gt;\"</i> per la verifica completa.";
  }

  lista.innerHTML = domini.map(function (e) {
    var bad = allarme(e);
    var dettaglio;
    if (e.errore) {
      dettaglio = "errore: " + esc(e.errore);
    } else if (bad) {
      var p = [];
      if (e.firme) p.push(e.firme + " firme malware");
      if (e.ioc_file) p.push(e.ioc_file + " file IOC");
      if (e.cartelle_rogue) p.push(e.cartelle_rogue + " cartelle rogue");
      if (e.php_in_upload) p.push(e.php_in_upload + " .php in upload");
      dettaglio = p.join(" · ");
    } else {
      dettaglio = "pulito" + (e.php_recenti ? " · " + e.php_recenti + " .php aggiornati di recente (normale)" : "");
    }
    return '<li class="sec-card' + (bad ? " allarme" : "") + '">' +
      '<span class="sec-dot">' + (bad ? "🔴" : "🟢") + "</span>" +
      '<span class="sec-dom">' + esc(e.dominio) + "</span>" +
      '<span class="sec-meta' + (bad ? " bad" : "") + '">' + dettaglio + "</span>" +
      "</li>";
  }).join("");
})();
