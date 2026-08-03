#!/usr/bin/env bash
# KLASYO — Étape V : (a) suivre les redirections /platform & /school pour connaître les URLs finales,
# (b) repérer un catalogue public de cours à lier, (c) CORRIGER le bouton "Connexion" mort (href="#")
# -> https://klasyo.org/platform/login. Backup + remplacement PHP indépendant de l'ordre des attributs,
# validation stricte, rollback auto si le fichier change de taille anormalement.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
LAND="$ROOT/index.html"
[ -f "$LAND" ] || { echo "(!) landing introuvable"; exit 0; }

UA='Mozilla/5.0 klasyo-check'
echo "== A) Où redirigent /platform et /school ? (suivre Location) =="
for U in "https://klasyo.org/platform" "https://klasyo.org/platform/" "https://klasyo.org/school" "https://klasyo.org/school/"; do
  LOC="$(curl -sS -m 20 -o /dev/null -w '%{http_code} -> %{redirect_url}' -A "$UA" "$U" 2>/dev/null)"
  echo "  $U : $LOC"
done

echo
echo "== B) Catalogue public de cours sur la LMS ? (chemins usuels LMSZAI) =="
for U in "https://klasyo.org/platform/login" "https://klasyo.org/platform/courses" "https://klasyo.org/platform/all-courses" "https://klasyo.org/platform/course" "https://klasyo.org/platform/home"; do
  read -r CODE FINAL < <(curl -sSL -m 25 -o /tmp/kk.html -w '%{http_code} %{url_effective}' -A "$UA" "$U" 2>/dev/null; echo)
  N_COURSE=$(grep -oiE 'course|cours|formation' /tmp/kk.html 2>/dev/null | wc -l)
  TITLE=$(grep -oiE '<title>[^<]*</title>' /tmp/kk.html 2>/dev/null | head -1)
  echo "  $U -> HTTP $CODE (final: $FINAL) | ~mots cours:$N_COURSE | $TITLE"
done

echo
echo "== C) Correction du bouton Connexion (href=\"#\" -> /platform/login) =="
STAMP="$(date +%Y%m%d-%H%M%S)"
BK="$ROOT/index.html.bak-connexion-$STAMP"
cp -p "$LAND" "$BK" && echo "  backup: $BK"
SZ_BEFORE=$(wc -c < "$LAND")

PHPX="$(mktemp /tmp/klasyo_fix_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
$f = $argv[1];
$html = file_get_contents($f);
$before = $html;
$done = 0;
// Cible : la balise <a ...> qui contient data-i18n="nav.login" (le bouton Connexion),
// quel que soit l'ordre des attributs. On n'y remplace QUE href="#".
$html = preg_replace_callback(
  '/<a\b[^>]*\bdata-i18n="nav\.login"[^>]*>/i',
  function($m) use (&$done){
    $tag = $m[0];
    $new = preg_replace('/href="#"/', 'href="https://klasyo.org/platform/login"', $tag, 1, $c);
    if ($c > 0) { $done++; return $new; }
    return $tag;
  },
  $html
);
if ($done !== 1) {
  fwrite(STDERR, "ATTENTION: $done bouton(s) nav.login modifié(s) (attendu 1). Aucune écriture.\n");
  exit(3);
}
if (strlen($html) === strlen($before)) {
  fwrite(STDERR, "ATTENTION: aucune modification effective. Aucune écriture.\n");
  exit(4);
}
file_put_contents($f, $html);
echo "  OK: 1 bouton Connexion recâblé -> /platform/login\n";
PHPEOF
if php "$PHPX" "$LAND"; then
  SZ_AFTER=$(wc -c < "$LAND")
  DELTA=$((SZ_AFTER - SZ_BEFORE))
  echo "  taille: $SZ_BEFORE -> $SZ_AFTER (Δ=$DELTA octets)"
  # garde-fou : le delta attendu est faible (l'ajout de l'URL ~ +30 octets). Si énorme, rollback.
  if [ "$DELTA" -lt 0 ] || [ "$DELTA" -gt 200 ]; then
    echo "  (!) delta anormal -> ROLLBACK"; cp -p "$BK" "$LAND"
  else
    echo "  Vérif: occurrences href=\"https://klasyo.org/platform/login\" ->"
    grep -oE 'href="https://klasyo.org/platform/login"' "$LAND" | wc -l
    echo "  Vérif: le fichier commence toujours par du HTML ->"
    head -c 60 "$LAND"; echo
  fi
else
  echo "  (!) échec du script PHP -> ROLLBACK par sécurité"; cp -p "$BK" "$LAND"
fi
rm -f "$PHPX" /tmp/kk.html

echo
echo "== D) Contrôle final : Connexion pointe-t-il bien vers le login ? =="
grep -noE '<a[^>]*data-i18n="nav.login"[^>]*>' "$LAND" | head -3

echo
echo "== FIN étape V =="
