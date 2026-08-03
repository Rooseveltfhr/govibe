#!/usr/bin/env bash
# KLASYO — Capture U : comprendre le wizard openWiz() + cibles de soumission (WhatsApp?)
# et vérifier que /platform répond, pour décider comment câbler les CTA vers le système.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
LAND="$ROOT/index.html"
[ -f "$LAND" ] || { echo "(!) landing introuvable"; exit 0; }

echo "== 1) Définition de la fonction openWiz (JS) =="
awk '/function openWiz/{f=1} f{print} f&&/^\s*}\s*$/{c++; if(c>=1){exit}}' "$LAND" | head -60
echo "   --- (recherche large openWiz / wizStep / submit) ---"
grep -nE 'function openWiz|function wiz|wizSubmit|function submitWiz|wa\.me|whatsapp|api\.whatsapp|window\.location|location\.href|location\.assign' "$LAND" | head -40

echo
echo "== 2) Le wizard soumet-il vers WhatsApp / une URL / un formulaire ? =="
grep -noE 'https?://[a-zA-Z0-9./?=_&%+-]{0,80}' "$LAND" | grep -iE 'wa\.me|whatsapp|klasyo\.org|platform|school|/login|/sign' | sort -u | head -30

echo
echo "== 3) Combien de fois openWiz est appelé et avec quels arguments =="
grep -oE "openWiz\([^)]*\)" "$LAND" | sort | uniq -c | sort -rn

echo
echo "== 4) /platform répond-il ? (contenu, pas juste statut) =="
for U in "https://klasyo.org/platform/login" "https://klasyo.org/platform" "https://klasyo.org/school"; do
  BODY="$(curl -sS -m 20 -A 'Mozilla/5.0 klasyo-check' "$U" 2>/dev/null)"
  CODE="$(curl -sS -m 20 -o /dev/null -w '%{http_code}' -A 'Mozilla/5.0 klasyo-check' "$U" 2>/dev/null)"
  KIND="?"
  case "$BODY" in
    *"<?php"*|*"Illuminate\\"*) KIND="SOURCE-PHP(!)" ;;
    *"<!doctype"*|*"<!DOCTYPE"*|*"<html"*) KIND="HTML" ;;
    "") KIND="VIDE/timeout" ;;
  esac
  TITLE="$(printf '%s' "$BODY" | grep -oiE '<title>[^<]*</title>' | head -1)"
  echo "  $U -> HTTP $CODE | $KIND | $TITLE"
done

echo
echo "== 5) La landing a-t-elle déjà une section de cours/catégories dynamiques ? =="
grep -noiE 'data-i18n="[^"]*(cours|course|categor|formation)[^"]*"|class="[^"]*(course|categor|slide)[^"]*"' "$LAND" | head -20

echo
echo "== FIN capture U =="
