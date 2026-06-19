// ───────────────────────────────────────────────────────────────
// PWA — registrazione service worker + bottone "Installa l'app".
// Il bottone è SEMPRE visibile (tranne se l'app è già installata):
//  - se il browser offre l'installazione nativa → la lancia;
//  - altrimenti mostra le istruzioni giuste per quel browser
//    (Chrome/Edge, Safari Mac, Safari iOS, Android, Firefox).
// ───────────────────────────────────────────────────────────────
(function () {
  // 1) Registra il service worker (scope = cartella della dashboard).
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("sw.js").catch(function () { /* non bloccante */ });
    });
  }

  var deferred = null;
  var ua = navigator.userAgent || "";
  var isIOS = /iPhone|iPad|iPod/.test(ua) ||
              (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  var isAndroid = /Android/.test(ua);
  var isEdge = /Edg\//.test(ua);
  var isChrome = /Chrome\//.test(ua) && !isEdge && !isAndroid;
  var isFirefox = /Firefox\//.test(ua);
  var isSafari = /Safari\//.test(ua) && !/Chrome|Chromium|Edg|CriOS|FxiOS/.test(ua);

  function isStandalone() {
    return window.matchMedia("(display-mode: standalone)").matches ||
           window.navigator.standalone === true;
  }

  function istruzioni() {
    if (isIOS && isSafari) return "Per installare: tocca Condividi (il quadrato con la freccia ↑) in basso, poi «Aggiungi a Home».";
    if (isAndroid)        return "Per installare: apri il menu ⋮ in alto a destra e scegli «Installa app» (o «Aggiungi a schermata Home»).";
    if (isEdge)           return "Per installare: menu ••• in alto a destra → «App» → «Installa questo sito come app».";
    if (isChrome)         return "Per installare: clicca l'icona «Installa» (⊕/monitor) a destra nella barra degli indirizzi, oppure menu ⋮ → «Installa NovaSTUDIO».";
    if (isSafari)         return "Per installare: menu File di Safari → «Aggiungi al Dock» (Safari 17+). In alternativa apri la dashboard con Chrome o Edge.";
    if (isFirefox)        return "Firefox non supporta l'installazione come app: apri la dashboard con Chrome, Edge o Safari per installarla.";
    return "Per installare: cerca l'opzione «Installa app» o «Aggiungi a Home» nel menu del tuo browser.";
  }

  function overlay(testo) {
    var bg = document.createElement("div");
    bg.style.cssText = "position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);display:grid;place-items:center;padding:20px";
    var card = document.createElement("div");
    card.style.cssText = "max-width:340px;background:#121a28;color:#e7edf6;border:1px solid #243049;border-radius:14px;padding:22px;font:15px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;box-shadow:0 20px 60px rgba(0,0,0,.5)";
    card.innerHTML = '<div style="font-weight:600;margin-bottom:8px">📲 Installa NovaSTUDIO</div>' +
                     '<div style="color:#aebbd0;font-size:14px">' + testo + '</div>';
    var ok = document.createElement("button");
    ok.textContent = "Ho capito";
    ok.style.cssText = "margin-top:16px;width:100%;padding:10px;border:0;border-radius:9px;background:#4f8cff;color:#fff;font-weight:600;cursor:pointer";
    ok.onclick = function () { document.body.removeChild(bg); };
    card.appendChild(ok);
    bg.appendChild(card);
    bg.addEventListener("click", function (e) { if (e.target === bg) document.body.removeChild(bg); });
    document.body.appendChild(bg);
  }

  function makeBtn() {
    var b = document.getElementById("pwa-install");
    if (b) return b;
    b = document.createElement("button");
    b.id = "pwa-install";
    b.type = "button";
    b.textContent = "⬇️ Installa l'app";
    b.style.cssText = [
      "position:fixed", "right:16px", "bottom:16px", "z-index:9999",
      "padding:11px 16px", "border:0", "border-radius:999px",
      "background:#4f8cff", "color:#fff",
      "font:600 14px/1 -apple-system,Segoe UI,Roboto,sans-serif",
      "box-shadow:0 8px 24px rgba(0,0,0,.35)", "cursor:pointer"
    ].join(";");
    b.onclick = function () {
      if (deferred) {
        deferred.prompt();
        deferred.userChoice.finally(function () { deferred = null; });
      } else {
        overlay(istruzioni());
      }
    };
    document.body.appendChild(b);
    return b;
  }

  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferred = e;          // installazione nativa disponibile
  });

  window.addEventListener("appinstalled", function () {
    var b = document.getElementById("pwa-install");
    if (b) b.style.display = "none";
  });

  // Mostra il bottone sempre (tranne se l'app è già installata).
  document.addEventListener("DOMContentLoaded", function () {
    if (!isStandalone()) makeBtn();
  });
})();
