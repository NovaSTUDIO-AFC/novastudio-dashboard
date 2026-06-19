#!/usr/bin/env bash
# Test d'integrazione HTTP del front controller (login, gate per sezione,
# permessi.js, pannello admin). Avvia un server PHP locale e usa curl.
set -u
export PATH="/opt/homebrew/bin:$PATH"
cd "$(dirname "$0")/.."

PORT=8099
BASE="http://127.0.0.1:$PORT"
JAR="$(mktemp)"
JAR2="$(mktemp)"
pass=0; fail=0
ck() { if eval "$2"; then pass=$((pass+1)); echo "  ✓ $1"; else fail=$((fail+1)); echo "  ✗ $1"; fi; }

# DB pulito
rm -f data/users.db data/users.db-wal data/users.db-shm data/forgot.json data/throttle.json

# Avvia server con config di test
NOVA_CONFIG="$PWD/_test/config.test.php" php -S 127.0.0.1:$PORT index.php >/tmp/nova_srv.log 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null; rm -f "$JAR" "$JAR2"' EXIT
sleep 1

csrf() { grep -oE 'name="csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }

echo "== Pre-login =="
HOME_OUT=$(curl -s -c "$JAR" "$BASE/")
ck "GET / mostra login" '[[ "$HOME_OUT" == *"Area riservata"* ]]'
ck "campo email presente"  '[[ "$HOME_OUT" == *"name=\"email\""* ]]'
ck "link password dimenticata" '[[ "$HOME_OUT" == *"action=forgot"* ]]'
T=$(echo "$HOME_OUT" | csrf)

echo "== Asset PWA pubblici (senza login) =="
CTM=$(curl -s -o /dev/null -w '%{http_code}|%{content_type}' "$BASE/manifest.webmanifest")
ck "manifest pubblico = 200 + manifest+json" '[[ "$CTM" == 200*manifest+json* ]]'
CTS=$(curl -s -o /dev/null -w '%{http_code}|%{content_type}' "$BASE/sw.js")
ck "sw.js pubblico = 200 + javascript" '[[ "$CTS" == 200*javascript* ]]'
CTP=$(curl -s -o /dev/null -w '%{http_code}|%{content_type}' "$BASE/assets/pwa.js")
ck "pwa.js pubblico = 200 + javascript" '[[ "$CTP" == 200*javascript* ]]'
CTI=$(curl -s -o /dev/null -w '%{http_code}|%{content_type}' "$BASE/assets/icon-192.png")
ck "icona pubblica = 200 + png" '[[ "$CTI" == 200*image/png* ]]'
# Un asset NON pubblico resta protetto:
ck "catalogo.js senza login → login (no leak)" 'curl -s "$BASE/assets/catalogo.js" | grep -q "Area riservata"'

echo "== Login admin =="
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" \
  --data-urlencode "email=info@afciaccio.it" --data-urlencode "password=TestPass123" \
  --data-urlencode "csrf=$T" "$BASE/?action=login")
ck "login → redirect 302" '[[ "$CODE" == "302" ]]'
ck "home accessibile dopo login" 'curl -s -b "$JAR" "$BASE/" | grep -q "Aree del progetto"'

echo "== permessi.js admin =="
P=$(curl -s -b "$JAR" "$BASE/assets/permessi.js")
ck "permessi.js isAdmin:true" '[[ "$P" == *"\"isAdmin\":true"* ]]'

echo "== sezioni admin (tutte servite) =="
for s in sistema posta automazioni infrastruttura; do
  C=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/$s.html")
  ck "admin GET $s.html = 200" '[[ "'"$C"'" == "200" ]]'
done

echo "== pannello admin: crea utente limitato =="
AT=$(curl -s -b "$JAR" "$BASE/?action=admin" | csrf)
CRE=$(curl -s -b "$JAR" --data-urlencode "csrf=$AT" \
  --data-urlencode "email=collab@x.it" --data-urlencode "password=PostaSolo123" \
  --data-urlencode "sezioni[]=posta" "$BASE/?action=admin_create")
ck "utente creato" '[[ "$CRE" == *"Utente creato"* ]]'

echo "== logout + login utente limitato =="
curl -s -b "$JAR" "$BASE/?action=logout" >/dev/null
T2=$(curl -s -c "$JAR2" "$BASE/" | csrf)
C2=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR2" -c "$JAR2" \
  --data-urlencode "email=collab@x.it" --data-urlencode "password=PostaSolo123" \
  --data-urlencode "csrf=$T2" "$BASE/?action=login")
ck "login collab → 302" '[[ "$C2" == "302" ]]'

echo "== permessi e gate per l'utente limitato =="
P2=$(curl -s -b "$JAR2" "$BASE/assets/permessi.js")
ck "permessi.js isAdmin:false" '[[ "$P2" == *"\"isAdmin\":false"* ]]'
ck "permessi.js sezioni=[posta]" '[[ "$P2" == *"\"sezioni\":[\"posta\"]"* ]]'
ck "posta.html = 200 (permesso)"        '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/posta.html")" == "200" ]]'
ck "sistema.html = 403 (negato)"        '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/sistema.html")" == "403" ]]'
ck "automazioni.html = 403 (negato)"    '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/automazioni.html")" == "403" ]]'
ck "data.js (infra) = 403 (negato)"     '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/assets/data.js")" == "403" ]]'
ck "guida-ai.html = 200 (sempre)"       '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/guida-ai.html")" == "200" ]]'
ck "pannello admin = 403 per non-admin" '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/?action=admin")" == "403" ]]'

echo "== PWA assets serviti =="
ck "manifest.webmanifest = 200" '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/manifest.webmanifest")" == "200" ]]'
ck "sw.js = 200"                '[[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR2" "$BASE/sw.js")" == "200" ]]'

echo "== Sicurezza: niente accesso senza sessione =="
ck "posta.html senza cookie → login(200 HTML login)" 'curl -s "$BASE/posta.html" | grep -q "Area riservata"'

echo ""
echo "RISULTATO HTTP: $pass passati, $fail falliti"
[[ $fail -eq 0 ]]
