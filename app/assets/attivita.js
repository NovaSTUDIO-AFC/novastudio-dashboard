// ───────────────────────────────────────────────────────────────
// "Da fare" — UI della lista attività.
// Dati e CSRF iniettati server-side da assets/attivita-dati.js
// (window.ATTIVITA = { csrf, items }). Le azioni passano dal front
// controller (?action=task_*) che risponde con la lista aggiornata.
// ───────────────────────────────────────────────────────────────
(function () {
  var dati = window.ATTIVITA || { csrf: "", items: [] };
  var lista = document.getElementById("lista-attivita");
  var conta = document.getElementById("todo-conta");
  var form = document.getElementById("todo-add");
  var input = document.getElementById("todo-input");
  if (!lista) return;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function render(items) {
    dati.items = items || [];
    var dafare = dati.items.filter(function (x) { return !Number(x.fatta); }).length;
    if (conta) conta.textContent = dafare + (dafare === 1 ? " da fare" : " da fare");

    if (!dati.items.length) {
      lista.innerHTML = '<li class="todo-vuoto">Niente da fare. 🎉</li>';
      return;
    }
    lista.innerHTML = dati.items.map(function (x) {
      var fatta = Number(x.fatta) ? " fatta" : "";
      return '<li class="' + fatta + '" data-id="' + x.id + '">' +
        '<input class="chk" type="checkbox" ' + (Number(x.fatta) ? "checked" : "") + ' />' +
        '<span class="txt">' + esc(x.testo) + "</span>" +
        '<button class="del" title="Elimina" aria-label="Elimina">✕</button>' +
        "</li>";
    }).join("");
  }

  function azione(action, extra) {
    var body = "csrf=" + encodeURIComponent(dati.csrf);
    for (var k in extra) body += "&" + k + "=" + encodeURIComponent(extra[k]);
    return fetch("?action=" + action, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body,
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.ok) render(j.items);
      return j;
    });
  }

  // Aggiungi
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var testo = (input.value || "").trim();
      if (!testo) return;
      input.value = "";
      azione("task_add", { testo: testo });
    });
  }

  // Spunta / elimina (delega)
  lista.addEventListener("click", function (e) {
    var li = e.target.closest("li[data-id]");
    if (!li) return;
    var id = li.getAttribute("data-id");
    if (e.target.classList.contains("chk")) {
      azione("task_toggle", { id: id });
    } else if (e.target.classList.contains("del")) {
      azione("task_del", { id: id });
    }
  });

  render(dati.items);
})();
