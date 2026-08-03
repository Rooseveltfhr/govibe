#!/usr/bin/env bash
# KLASYO — Test S : confirmer quel schéma d'URL fonctionne (propre /platform/login via server.php,
# ou seulement /platform/public/login), pour câbler correctement les CTA de la landing.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"

echo "== .htaccess racine platform : route vers quoi ? =="
grep -nE 'RewriteRule \^ (server.php|public/index.php|index.php)' "$P/.htaccess" | head -3

echo
echo "== CLI: /platform/login via server.php (SCRIPT_NAME=/platform/server.php) =="
cd "$P"
timeout 30 php -d display_errors=1 -r '
  $_SERVER["REQUEST_URI"]="/platform/login";
  $_SERVER["REQUEST_METHOD"]="GET";
  $_SERVER["HTTP_HOST"]="klasyo.org";
  $_SERVER["SCRIPT_NAME"]="/platform/server.php";
  $_SERVER["SCRIPT_FILENAME"]=__DIR__."/server.php";
  $_SERVER["PHP_SELF"]="/platform/server.php";
  require "server.php";
' > /tmp/kl_clean.html 2>&1 || true
if grep -qi 'klasyo-auth' /tmp/kl_clean.html; then
  echo "  ✅ /platform/login route CORRECTEMENT vers la page login (URLs propres OK via server.php)"
elif grep -qiE '404|not found|Sorry, the page' /tmp/kl_clean.html && ! grep -qi 'klasyo-auth' /tmp/kl_clean.html; then
  echo "  ❌ /platform/login -> 404 (Laravel ne matche pas la route en sous-dossier)"
  echo "     début: $(head -c 160 /tmp/kl_clean.html | tr -d '\n\r')"
else
  echo "  ? réponse ($(wc -c < /tmp/kl_clean.html)o): $(head -c 160 /tmp/kl_clean.html | tr -d '\n\r')"
fi
rm -f /tmp/kl_clean.html

echo
echo "== CLI: /platform/public/login via public/index.php (référence connue-OK) =="
cd "$P/public"
timeout 30 php -r '
  $_SERVER["REQUEST_URI"]="/login"; $_SERVER["REQUEST_METHOD"]="GET";
  $_SERVER["HTTP_HOST"]="klasyo.org"; $_SERVER["SCRIPT_NAME"]="/platform/public/index.php";
  $_SERVER["SCRIPT_FILENAME"]=__DIR__."/index.php"; require "index.php";
' > /tmp/kl_pub.html 2>&1 || true
grep -qi 'klasyo-auth' /tmp/kl_pub.html && echo "  ✅ /platform/public/login OK" || echo "  ? $(head -c 120 /tmp/kl_pub.html | tr -d '\n\r')"
rm -f /tmp/kl_pub.html

echo
echo "== 1 requête HTTP réelle (si le firewall laisse passer) =="
code=$(curl -sk -o /dev/null -m 15 -w '%{http_code}' "https://klasyo.org/platform/login" 2>/dev/null || echo ERR)
echo "  HTTP /platform/login -> $code"

echo
echo "== FIN test S =="
