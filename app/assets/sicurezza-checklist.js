// ───────────────────────────────────────────────────────────────
// CHECKLIST cambio password post-incidente (Reparto Sicurezza).
// Contenuto allineato a ~/Workspace/SICUREZZA-checklist-cambio-password.md.
// Stato spunte: localStorage per-device (checklist personale, una tantum →
// niente DB/endpoint). ponytail: se serve sync multi-device, passare allo store.
// ───────────────────────────────────────────────────────────────
(function () {
  var KEY = "nova_sic_checklist_v1";

  // gruppi → voci. `p` = priorità (1=più urgente). `id` stabile = chiave di salvataggio.
  var GRUPPI = [
    { titolo: "🔴 Priorità 1 — Credenziali DATABASE (erano nei backup pubblici)", p: "p1",
      nota: "I backup esposti contenevano i wp-config.php → password DB compromesse.",
      voci: [
        { id: "db-mysql", t: "hPanel → Database → Gestione MySQL: cambia la password dell'utente di OGNI database WordPress." },
        { id: "db-wpconfig", t: "Aggiorna la nuova password nel <code>wp-config.php</code> di ogni sito (define DB_PASSWORD). Senza questo il sito va in errore — se vuoi lo faccio io via SSH." },
        { id: "db-migliorpc", t: "Database certo da cambiare: <b>migliorpcgaming.it</b> (suo backup esposto). Se il DB di afciaccio.it esiste ancora, cambialo comunque." },
      ]},
    { titolo: "🔴 Priorità 2 — Admin WordPress (la backdoor poteva creare utenti)", p: "p2",
      nota: "Per ogni WP attivo: simracing.fan, migliorpcgaming.it, e i WP novastudio se presenti.",
      voci: [
        { id: "wp-utenti", t: "wp-admin → Utenti: cerca <b>admin sconosciuti</b> che non hai creato tu → eliminali (sono backdoor)." },
        { id: "wp-pass", t: "Cambia la password di TUTTI gli amministratori." },
        { id: "wp-2fa", t: "(Consigliato) Attiva 2FA sugli admin se il tema/plugin lo permette." },
      ]},
    { titolo: "🟠 Priorità 3 — Account Hostinger", p: "p3", nota: "",
      voci: [
        { id: "host-pass", t: "hPanel → Account → Sicurezza: cambia la password dell'account Hostinger." },
        { id: "host-2fa", t: "Attiva la verifica in due passaggi (2FA) sull'account." },
      ]},
    { titolo: "🟠 Priorità 4 — FTP", p: "p4", nota: "",
      voci: [
        { id: "ftp", t: "hPanel → File → Account FTP: resetta la password di ogni account (o elimina quelli che non usi)." },
      ]},
    { titolo: "🟡 Priorità 5 — Chiavi / segreti applicativi", p: "p5",
      nota: "Rischio più basso (i siti che li contenevano sono puliti), ma igiene post-incidente.",
      voci: [
        { id: "brevo", t: "Brevo: ruota le API key usate da dashboard/posta. Rigenera → aggiorna dove servono." },
        { id: "altre-api", t: "Altre API key in config.php/.env dei progetti → rigenera." },
      ]},
    { titolo: "🟢 Priorità 6 — Chiave SSH (precauzione, non urgente)", p: "p6",
      nota: "La chiave PRIVATA è sul tuo Mac, NON sul server: non è stata esposta dal malware web. Ruotala solo per igiene.",
      voci: [
        { id: "ssh-new", t: "Genera nuova coppia: <code>ssh-keygen -t ed25519 -f ~/.ssh/id_novastudio_2026</code>" },
        { id: "ssh-upload", t: "Carica la nuova chiave pubblica in hPanel → SSH, rimuovi la vecchia. Posso farlo io e aggiornare l'alias." },
      ]},
    { titolo: "✅ Dopo aver finito", p: "p6", nota: "",
      voci: [
        { id: "post-scan", t: "Avvisa Max → rifà una scansione di conferma su tutti i domini." },
        { id: "post-quar", t: "Quando sei tranquillo, Max elimina le quarantene (afciaccio / migliorpcgaming / manueltiago)." },
      ]},
  ];

  var stato = {};
  try { stato = JSON.parse(localStorage.getItem(KEY) || "{}") || {}; } catch (e) { stato = {}; }
  function salva() { try { localStorage.setItem(KEY, JSON.stringify(stato)); } catch (e) {} }

  var root = document.getElementById("ck-root");
  var elFatti = document.getElementById("ck-fatti");
  var elTot = document.getElementById("ck-tot");
  var elBar = document.getElementById("ck-bar");
  var elConta = document.getElementById("ck-conta");
  if (!root) return;

  var tot = GRUPPI.reduce(function (s, g) { return s + g.voci.length; }, 0);

  function aggiornaProgresso() {
    var fatti = Object.keys(stato).filter(function (k) { return stato[k]; }).length;
    elFatti.textContent = fatti;
    elTot.textContent = tot;
    elBar.style.width = tot ? Math.round((fatti / tot) * 100) + "%" : "0%";
    elConta.textContent = fatti + "/" + tot;
  }

  root.innerHTML = GRUPPI.map(function (g) {
    var voci = g.voci.map(function (v) {
      var on = !!stato[v.id];
      return '<li class="ck-item' + (on ? " done" : "") + '" data-id="' + v.id + '">' +
        '<input type="checkbox"' + (on ? " checked" : "") + ' />' +
        '<span class="txt"><span class="ck-p ' + g.p + '"></span> ' + v.t + "</span>" +
        "</li>";
    }).join("");
    return '<div class="ck-group"><h2>' + g.titolo + "</h2>" +
      (g.nota ? '<div class="ck-note">' + g.nota + "</div>" : "") +
      '<ul class="ck-list">' + voci + "</ul></div>";
  }).join("");

  // I pallini priorità sono solo decorativi (vuoti): tolgo lo span se non serve.
  Array.prototype.forEach.call(root.querySelectorAll(".ck-p"), function (s) { s.remove(); });

  function toggle(li) {
    var id = li.getAttribute("data-id");
    stato[id] = !stato[id];
    li.classList.toggle("done", stato[id]);
    var box = li.querySelector("input");
    if (box) box.checked = stato[id];
    salva();
    aggiornaProgresso();
  }

  Array.prototype.forEach.call(root.querySelectorAll(".ck-item"), function (li) {
    li.addEventListener("click", function (e) {
      if (e.target.tagName === "A") return; // lascia cliccabili eventuali link
      if (e.target.tagName !== "INPUT") { e.preventDefault(); }
      toggle(li);
    });
  });

  var reset = document.getElementById("ck-reset");
  if (reset) reset.addEventListener("click", function () {
    stato = {}; salva();
    Array.prototype.forEach.call(root.querySelectorAll(".ck-item"), function (li) {
      li.classList.remove("done"); var b = li.querySelector("input"); if (b) b.checked = false;
    });
    aggiornaProgresso();
  });

  aggiornaProgresso();
})();
