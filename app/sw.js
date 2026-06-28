// ───────────────────────────────────────────────────────────────
// SERVICE WORKER — NovaSTUDIO PWA.
// Strategia: NETWORK-FIRST per tutto → l'app mostra sempre l'ultima
// versione (niente hard refresh su mobile/app). La cache serve SOLO
// come fallback quando si è offline. I dati per-utente non si cache-ano
// mai. skipWaiting + clients.claim → il nuovo SW prende subito il
// controllo; pwa.js poi ricarica la pagina una volta sola.
// ───────────────────────────────────────────────────────────────
const CACHE = "novastudio-v4";

// Mai in cache: contenuti per-utente o dati vivi (endpoint dinamici).
const NO_CACHE = [
  "assets/permessi.js", "assets/catalogo.js", "assets/stato-osservato.js",
  "assets/attivita-dati.js", "assets/redazione-dati.js",
  "assets/commerciale-dati.js",
  "assets/job-dati.js", "assets/seo-dati.js", "assets/pezzo-dati.js",
];

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
  if (url.origin !== self.location.origin) return;

  // Dati per-utente → sempre rete, niente cache.
  if (isNoCache(url)) return;

  // NETWORK-FIRST: prova la rete (fresco), aggiorna la cache; se offline,
  // ricade sulla cache, e per le navigazioni sull'ultima index salvata.
  e.respondWith(
    fetch(req)
      .then((res) => {
        if (res && res.ok && res.type === "basic") {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
        }
        return res;
      })
      .catch(() =>
        caches.match(req).then((r) => {
          if (r) return r;
          const isNav = req.mode === "navigate" ||
            (req.headers.get("accept") || "").includes("text/html");
          return isNav ? caches.match("index.html") : Response.error();
        })
      )
  );
});
