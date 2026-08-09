#!/usr/bin/env bash
#
# TAGTOA — test de charge basique des pages publiques (aucune dépendance
# obligatoire : utilise `ab` si disponible, sinon un fallback curl+xargs).
#
# But : répondre concrètement à « ce système a-t-il été testé dans des
# conditions réelles ? » — avant ce script, AUCUN test de charge n'existait.
# Usage :
#   bash Modules/Tagtoa/scripts/loadtest.sh [base_url] [concurrency] [requests]
#
# Exemples :
#   bash Modules/Tagtoa/scripts/loadtest.sh https://tagtoa.com 10 200
#   bash Modules/Tagtoa/scripts/loadtest.sh http://127.0.0.1:8000 5 50
#
# ⚠️ Ne JAMAIS pointer ce script vers la production sans prévenir — même à
# faible concurrence, il génère du trafic réel. Préférer un environnement de
# staging quand disponible. Les routes ci-dessous sont toutes des GET publics
# en lecture seule (aucune commande/achat n'est déclenché).

set -uo pipefail

BASE="${1:-http://127.0.0.1:8000}"
CONCURRENCY="${2:-5}"
REQUESTS="${3:-50}"

# Pages publiques à fort trafic potentiel (démo — adapter aux alias réels du
# marchand testé). Ce sont exactement les pages mises en cache dans
# Menu/Site/Links/Pay/Event\PublicController (voir leur commentaire
# PUBLIC_CACHE_TTL) — l'objectif est de mesurer l'effet réel du cache.
PATHS=(
  "/menu/demo-menu"
  "/pay/demo"
  "/links/demo-links"
  "/event/demo-concert"
  "/site/demo-site"
  "/book/demo-booking"
)

echo "==> TAGTOA loadtest — base=$BASE concurrency=$CONCURRENCY requests/page=$REQUESTS"
echo "==> $(date -u +%FT%TZ)"
echo

run_with_ab() {
  local url="$1"
  ab -n "$REQUESTS" -c "$CONCURRENCY" -q "$url" 2>/dev/null \
    | grep -E "Requests per second|Time per request|Failed requests|Percentage of the requests"
}

run_with_curl_fallback() {
  local url="$1"
  local start end
  start=$(date +%s.%N)
  seq 1 "$REQUESTS" | xargs -P "$CONCURRENCY" -I{} curl -s -o /dev/null -w "%{http_code} %{time_total}\n" "$url" > /tmp/tagtoa_loadtest_out.$$
  end=$(date +%s.%N)

  local total ok avg
  total=$(wc -l < /tmp/tagtoa_loadtest_out.$$)
  ok=$(grep -c "^200 " /tmp/tagtoa_loadtest_out.$$ || true)
  avg=$(awk '{s+=$2; n++} END {if (n>0) printf "%.3f", s/n; else print "n/a"}' /tmp/tagtoa_loadtest_out.$$)
  rm -f /tmp/tagtoa_loadtest_out.$$

  awk -v s="$start" -v e="$end" -v t="$total" 'BEGIN{printf "  Durée totale: %.2fs · Req/s: %.2f\n", (e-s), t/(e-s)}'
  echo "  OK (200): $ok / $total · Temps moyen/requête: ${avg}s"
}

HAS_AB=$(command -v ab || true)

for p in "${PATHS[@]}"; do
  url="$BASE$p"
  echo "-- $url"
  if [ -n "$HAS_AB" ]; then
    run_with_ab "$url"
  else
    run_with_curl_fallback "$url"
  fi
  echo
done

echo "==> Terminé. Points à surveiller côté serveur pendant/après ce test :"
echo "    - CPU/charge (top, htop)"
echo "    - Connexions MySQL actives (SHOW PROCESSLIST)"
echo "    - Taux de succès du cache (voir Modules/Tagtoa/app/Http/Controllers/*/PublicController.php)"
echo "    - Espace disque restant (df -h) si le test génère des logs volumineux"
