#!/usr/bin/env bash
# KLASYO — Étape Z2 (ANALYSE) : confirmer que l'API JSON des cours est publique + voir la forme JSON
# (pour afficher catégories / cours populaires / cours récents en direct sur la landing klasyo.org).
set -uo pipefail
UA='Mozilla/5.0 klasyo-check'
hdr='Accept: application/json'

probe () {
  local url="$1"
  local code ct
  code=$(curl -sS -m 20 -o /tmp/api.json -w '%{http_code}' -H "$hdr" -A "$UA" "$url" 2>/dev/null)
  ct=$(curl -sS -m 20 -o /dev/null -w '%{content_type}' -H "$hdr" -A "$UA" "$url" 2>/dev/null)
  echo "== $url"
  echo "   HTTP $code | type: $ct | octets: $(wc -c < /tmp/api.json 2>/dev/null)"
  # aperçu compact du JSON (clés de haut niveau + 600 premiers caractères)
  head -c 600 /tmp/api.json 2>/dev/null | tr '\n' ' '
  echo; echo "   ----"
}

for base in "https://klasyo.org/platform/api" "https://klasyo.org/platform"; do
  echo "######## PREFIXE: $base ########"
  probe "$base/home/category-course"
  probe "$base/home/courses"
  probe "$base/upcoming-list"
  probe "$base/courses-list"
done

echo "== FIN étape Z2 =="
