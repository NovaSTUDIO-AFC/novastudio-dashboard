# Audit UX + CRO + Accessibilità — Dashboard NovaSTUDIO

> 2026-06-28 · audit sul sorgente locale (`app/`, `login_view.php`, `index.php`).
> Frameworks: euristiche Nielsen, principi Norman, WCAG 2.2, mindset CRO.
> Login dietro reCAPTCHA → audit sul codice, non sul rendering live.

## Verdetto in una riga
Base solida e curata (design system a token, tema scuro coerente, contrasti ottimi, nav data-driven). I problemi veri sono **3**: accessibilità da tastiera, navigazione nascosta su desktop, e doppia fonte di verità per i menu. Tutti a basso costo di fix.

## Cosa è già fatto bene (tenere)
- **Design system a variabili CSS** coerente (`:root` token per colore/raggio/ombra). Pulito ed estendibile.
- **Contrasti testo**: misurati, quasi tutti PASS AA-AAA (testo 14.4:1, text-dim 6-7.5:1, accent 5.3:1). Tema scuro fatto come si deve.
- **Nav data-driven** (`TREE` in `site.js`): aggiungere una pagina = una riga.
- **Login**: `<label>` reali, `autocomplete` corretti, `type=email/password`, `autofocus`, reCAPTCHA v3 **invisibile** (zero attrito utente). Buono.
- Breakpoint responsive presenti; griglia card che collassa a 1 colonna su mobile.

## P0 — Accessibilità da tastiera (WCAG fail reali, fix economici)
1. **Nessun focus visibile** su link-nav, pulsanti e card (`<a class="card area">`). Esiste solo `:focus` sull'input del gate. → chi naviga da tastiera non vede dov'è. **WCAG 2.4.7 AA — FAIL.** Fix: 1 regola globale `:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }`.
2. **Nessuno skip-link** ("salta al contenuto"). 0/16 pagine. **WCAG 2.4.1 A — FAIL.** Fix: un `<a href="#main" class="skip">` nel layout condiviso.
3. **Nessun landmark `<main>`** (0/16 pagine; si usa `<div class="wrap">`). Gli screen reader non hanno regioni. Fix: avvolgere il contenuto in `<main id="main">`.
4. **Animazioni senza `prefers-reduced-motion`**: il pulse del badge, i tratteggi della mappa e degli edge girano sempre. **WCAG 2.3.3.** Fix: `@media (prefers-reduced-motion: reduce){ *{animation:none!important} }`.
5. **Testo bianco sul pulsante blu** (`#fff` su `--accent #4f8cff`) = **3.22:1 → FAIL AA** per testo normale (passa solo come UI/large). Tocca pulsante "Entra" e i bottoni. Fix: scurire leggermente l'accent per il fill dei bottoni (es. `#2f6fe0`) o testo più scuro.

## P1 — Navigazione & Architettura (euristiche Nielsen)
6. **Nav a cassetto anche su desktop** (`site.js`/CSS: hamburger sempre visibile, `.nav-toggle{display:block}` globale). Su schermo largo c'è spazio: nascondere TUTTA la navigazione dietro un click riduce scoperta e orientamento (*recognition over recall*). Fix: su ≥720px mostrare project-switch + sezioni inline o sidebar persistente.
7. **Doppia fonte di verità per i menu**: la nav usa `TREE`, la home usa un array separato `AREE` (13 voci, piatto, raggruppato diversamente). Due strutture da tenere allineate a mano → drift garantito + modello mentale incoerente. Fix: derivare la home dallo stesso `TREE`.
8. **Tutto è "attiva" col badge verde pulsante**: quando tutto è evidenziato, niente lo è. Il pallino verde animato perde significato. Fix: riservare l'enfasi a ciò che richiede attenzione (es. "3 da approvare").

## P1 — CRO / efficienza del compito
9. **Hero ingombrante in cima alla home**: 48px+ di padding, logo, sottotitolo generico ("Da qui accedi a tutte le aree"). Su uno strumento d'uso quotidiano spinge le azioni reali sotto la piega. Mindset CRO = ridurre l'attrito al compito primario. Fix: hero compatto e in cima ciò che **serve oggi** (es. "Articoli da approvare: 3", "Follow-up candidature oggi: 2") come scorciatoie cliccabili.
10. **Login coerente ma CSS duplicato**: lo stile del login è inline in `login_view.php` con token leggermente diversi (`--bg:#0b1018` vs `#0b0f17` di `styles.css`). Innocuo ma è debito. Fix: condividere i token.

## Piano di fix consigliato (ordine)
1. **Batch P0 accessibilità** (focus-visible + skip-link + `<main>` + reduced-motion + contrasto bottone) — ~1 CSS condiviso + ritocco layout. Alto valore, reversibile.
2. **Home dal `TREE`** + hero compatto con "cosa serve oggi" (#7 + #9).
3. **Nav desktop persistente** (#6).
4. Badge solo dove serve (#8) + token login condivisi (#10).

> Nota: questo è l'audit sul sorgente. La verifica *visiva* dal vivo (rendering, stati hover/focus reali, mobile) va fatta con Interceptor agganciato alla tua sessione loggata.
