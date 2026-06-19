// ───────────────────────────────────────────────────────────────
// PWA — registrazione service worker + bottone "Installa l'app".
// Il bottone compare quando il browser segnala che l'app è
// installabile (Android/desktop Chrome-Edge). Su iOS Safari non
// esiste l'evento: mostriamo una breve istruzione manuale.
// ───────────────────────────────────────────────────────────────
(function () {
  // 1) Registra il service worker (scope = cartella della dashboard).
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("sw.js").catch(function () { /* non bloccante */ });
    });
  }

  // 2) Bottone d'installazione.
  var deferred = null;

  function makeBtn() {
    if (document.getElementById("pwa-install")) return document.getElementById("pwa-install");
    var b = document.createElement("button");
    b.id = "pwa-install";
    b.type = "button";
    b.textContent = "⬇️ Installa l'app";
    b.style.cssText = [
      "position:fixed", "right:16px", "bottom:16px", "z-index:9999",
      "padding:11px 16px", "border:0", "border-radius:999px",
      "background:#4f8cff", "color:#fff", "font:600 14px/1 -apple-system,Segoe UI,Roboto,sans-serif",
      "box-shadow:0 8px 24px rgba(0,0,0,.35)", "cursor:pointer"
    ].join(";");
    b.style.display = "none";
    document.body.appendChild(b);
    return b;
  }

  function isStandalone() {
    return window.matchMedia("(display-mode: standalone)").matches ||
           window.navigator.standalone === true;
  }

  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferred = e;
    var b = makeBtn();
    b.style.display = "block";
    b.onclick = function () {
      b.style.display = "none";
      deferred.prompt();
      deferred.userChoice.finally(function () { deferred = null; });
    };
  });

  window.addEventListener("appinstalled", function () {
    var b = document.getElementById("pwa-install");
    if (b) b.style.display = "none";
  });

  // iOS: nessun beforeinstallprompt → istruzione manuale (solo se non già installata).
  document.addEventListener("DOMContentLoaded", function () {
    var ua = navigator.userAgent || "";
    var isIOS = /iPhone|iPad|iPod/.test(ua);
    if (isIOS && !isStandalone()) {
      var b = makeBtn();
      b.textContent = "⬇️ Installa";
      b.style.display = "block";
      b.onclick = function () {
        alert("Per installare: tocca il pulsante Condividi di Safari e scegli \"Aggiungi a Home\".");
      };
    }
  });
})();
