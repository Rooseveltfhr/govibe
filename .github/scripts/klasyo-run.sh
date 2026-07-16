#!/usr/bin/env bash
# KLASYO — Diagnostic I : platform sert du source malgré une config .htaccess désormais
# équivalente à school (qui exécute). On compare tout et on teste le cache LiteSpeed.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"; S="$ROOT/school"

echo "== I1. /platform/public/ DIRECT (sans passer par le rewrite racine) — corps ? =="
b=$(curl -sk -m 15 "https://klasyo.org/platform/public/" 2>/dev/null)
echo "  code=$(curl -sk -o /dev/null -m 15 -w '%{http_code}' 'https://klasyo.org/platform/public/')"
printf '%s' "$b" | grep -qiE '<\?php|Illuminate\\Contracts' && echo "  -> SOURCE" || { printf '%s' "$b" | grep -qiE '<!doctype|<html' && echo "  -> HTML" || echo "  -> autre: $(printf '%s' "$b" | head -c 40)"; }

echo
echo "== I2. Cache-buster : /platform/login?nocache=timestamp =="
b=$(curl -sk -m 15 "https://klasyo.org/platform/login?nocache=$(date +%s)" 2>/dev/null)
printf '%s' "$b" | grep -qiE '<\?php|Illuminate\\Contracts' && echo "  -> SOURCE (pas du cache)" || { printf '%s' "$b" | grep -qiE '<!doctype|<html' && echo "  -> HTML (c'était le cache !)" || echo "  -> autre"; }

echo
echo "== I3. En-têtes LiteSpeed cache (X-LiteSpeed-Cache / X-Powered-By) =="
curl -skI -m 12 "https://klasyo.org/platform/login" 2>/dev/null | grep -iE 'x-litespeed|x-powered|x-lsadc|content-type|server:' | head -8

echo
echo "== I4. index.php à la racine de platform ? (servirait en source directement) =="
ls -la "$P"/index.php 2>/dev/null && echo "  !!! index.php EXISTE à la racine platform" || echo "  pas d'index.php à la racine (OK)"
echo "  --- fichiers .php à la racine platform :"
ls "$P"/*.php 2>/dev/null | xargs -n1 basename 2>/dev/null | head -10

echo
echo "== I5. COMPARAISON root .htaccess : platform vs school =="
echo "--- platform/.htaccess :"; cat -A "$P/.htaccess" | head -40
echo "--- school/.htaccess :"; cat -A "$S/.htaccess" | head -40

echo
echo "== I6. COMPARAISON public/.htaccess : platform vs school =="
echo "--- platform/public/.htaccess :"; cat "$P/public/.htaccess"
echo "--- school/public/.htaccess :"; cat "$S/public/.htaccess"

echo
echo "== I7. Contexte PHP par répertoire (DirectAdmin/CloudLinux .user.ini, php version) =="
for d in "$P" "$P/public" "$S" "$S/public"; do
  for cfg in .htaccess.php_version .user.ini; do
    [ -f "$d/$cfg" ] && { echo "  [$d/$cfg]"; head -5 "$d/$cfg"; }
  done
done
echo "  --- date de modif des public/index.php :"
stat -c '%y %n' "$P/public/index.php" "$S/public/index.php" 2>/dev/null

echo
echo "== FIN diagnostic I =="
