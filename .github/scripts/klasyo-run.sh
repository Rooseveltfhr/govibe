#!/usr/bin/env bash
# KLASYO — Phase 1 / Semaine 1, passe 2 : réparer le routing de /school (.htaccess)
# + vérifier le format APP_KEY de platform.
# Exécuté sur le VPS via GitHub Actions (klasyo-ops.yml). Logs publics : aucun secret.
# Idempotent ; backups avant toute écriture.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
S="$ROOT/school"
STAMP="$(date +%Y%m%d-%H%M%S)"

echo "########################################"
echo "== ÉTAPE 1 : .htaccess de /school (boucle de réécriture en sous-dossier) =="
echo "########################################"
if grep -q 'KLASYO-SUBDIR-SAFE' "$S/.htaccess" 2>/dev/null; then
  echo "  Déjà remplacé (marqueur présent) — rien à faire."
else
  cp -a "$S/.htaccess" "$S/.htaccess.bak-$STAMP" 2>/dev/null && echo "  backup : .htaccess.bak-$STAMP"
  cat > "$S/.htaccess" <<'HTA'
# KLASYO-SUBDIR-SAFE — Laravel servi depuis le sous-dossier /school/
# (l'original, prévu pour une racine de domaine, bouclait sur public/public/…)
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # 1) Ce qui est déjà sous public/ n'est PAS réécrit (stoppe la boucle)
    RewriteRule ^public/ - [L]

    # 2) Fichier statique existant dans public/ -> le servir
    RewriteCond %{DOCUMENT_ROOT}/school/public/$1 -f
    RewriteRule ^(.*)$ public/$1 [L]

    # 3) Tout le reste -> front controller Laravel
    RewriteRule ^ public/index.php [L]

    # En-tête Authorization pour l'API
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    # En-tête school-code (multi-écoles eSchool)
    RewriteCond %{HTTP:school-code} ^(.*)
    RewriteRule .* - [E=HTTP_SCHOOL_CODE:%1]
</IfModule>
HTA
  echo "  Nouveau .htaccess écrit."
fi
echo "  --- public/.htaccess de school présent ? "
[ -f "$S/public/.htaccess" ] && echo "  oui ($(wc -c < "$S/public/.htaccess") octets)" || echo "  (!) NON — front controller sans réécriture interne"

echo
echo "########################################"
echo "== ÉTAPE 2 : APP_KEY de platform (contrôle de format, valeur jamais affichée) =="
echo "########################################"
if grep -q '^APP_KEY=.\+' "$P/.env"; then
  KEYLEN=$(grep '^APP_KEY=' "$P/.env" | head -1 | cut -d= -f2- | tr -d '"' | wc -c)
  if grep -q '^APP_KEY=base64:' "$P/.env"; then
    echo "  APP_KEY présente au format base64 (longueur $KEYLEN)."
  else
    echo "  APP_KEY présente au format LEGACY (longueur $KEYLEN) — fonctionnelle, ne pas régénérer."
  fi
else
  echo "  (!) APP_KEY réellement vide — génération…"
  cp -a "$P/.env" "$P/.env.bak-key-$STAMP"
  (cd "$P" && php artisan key:generate --force 2>&1 | grep -iv 'base64')
fi

echo
echo "########################################"
echo "== ÉTAPE 3 : santé de school côté CLI (sans secrets) =="
echo "########################################"
(cd "$S" && php artisan about 2>/dev/null | grep -E 'Environment|Debug Mode|Cache|Laravel Version' | head -6) \
  || echo "  (artisan about indisponible)"

echo
echo "########################################"
echo "== ÉTAPE 4 : vérification HTTP (codes + redirections) =="
echo "########################################"
for u in "https://klasyo.org/school" \
         "https://klasyo.org/school/" \
         "https://klasyo.org/school/public/index.php" \
         "https://klasyo.org/school/login" \
         "https://klasyo.org/platform/index.php" \
         "https://klasyo.org/" ; do
  out=$(curl -sk -o /dev/null -m 12 -w "%{http_code} redir=%{redirect_url}" "$u" 2>&1)
  echo "  $u -> $out"
done
echo "  -- avec suivi de redirections :"
out=$(curl -skL -o /dev/null -m 15 -w "final=%{http_code} url=%{url_effective} redirs=%{num_redirects}" "https://klasyo.org/school/" 2>&1)
echo "  /school/ -> $out"

echo
echo "== ÉTAPE 5 : nouvelles erreurs Laravel school depuis aujourd'hui (messages tronqués) =="
L=$(ls -t "$S"/storage/logs/*.log 2>/dev/null | head -1)
if [ -n "$L" ]; then
  grep -oE "^\[$(date +%Y-%m-%d)[0-9 :]*\] \w+\.\w+: [^{]{0,120}" "$L" | tail -5 || echo "  (aucune erreur aujourd'hui)"
else
  echo "  (pas de log)"
fi

echo
echo "== FIN passe 2 =="
