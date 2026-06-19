// ───────────────────────────────────────────────────────────────
// SERVICE WORKER — NovaSTUDIO PWA.
// Strategia prudente per un cruscotto PRIVATO:
//  - permessi/dati per-utente: SEMPRE rete, MAI cache (no fughe di dati).
//  - navigazioni (HTML): network-first, con fallback alla cache se offline.
//  - asset statici (css/js/svg/png/font): cache-first.
// Non si cache-a mai una risposta non-OK (es. 401/403/redirect login).
// ───────────────────────────────────────────────────────────────
const CACHE = "novastudio-v1";

// Mai in cache: contenuti per-utente o dati vivi.
const NO_CACHE = ["assets/permessi.js", "assets/catalogo.js", "assets/stato-osservato.js"];

self.addEventListener("install", (e) => {
  self.skipWaiting();
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

function isNoCache(url) {
  return NO_CACHE.some((p) => url.pathname.endsWith(p));
}

self.addEventListener("fetch", (e) => {
  const req = e.request;
  if (req.method !== "GET") return;

  const url = new URL(req.url);
  // Solo stessa origine.
  if (url.origin !== self.location.origin) return;

  // Endpoint per-utente o dati vivi → sempre rete, niente cache.
  if (isNoCache(url)) return;

  const isNav = req.mode === "navigate" ||
    (req.headers.get("accept") || "").includes("text/html");

  if (isNav) {
    // Network-first: pagina fresca quando online, cache se offline.
    e.respondWith(
      fetch(req)
        .then((res) => {
          if (res && res.ok) caches.open(CACHE).then((c) => c.put(req, res.clone()));
          return res;
        })
        .catch(() => caches.match(req).then((r) => r || caches.match("index.html")))
    );
    return;
  }

  // Asset statici → cache-first, poi rete (e popola la cache).
  e.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req).then((res) => {
        if (res && res.ok && res.type === "basic") {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
        }
        return res;
      });
    })
  );
});
