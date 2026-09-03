#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-fiche.sh — la fiche produit à la charte DJOUNES, en CSS seulement.
#
# Le bloc d'achat (galerie, choix de taille et de couleur, ajout au panier)
# est piloté par un greffon jQuery, `product_details.js`, qui bascule une
# classe sur le bouton choisi et recharge les images par AJAX. Toucher au
# gabarit, c'est risquer de casser l'étape juste avant l'achat.
#
# Donc : AUCUNE modification de Blade, AUCUN JavaScript. Tout passe par
# `custom.css`, la dernière feuille chargée par le thème — elle gagne à
# spécificité égale, et le retour en arrière est la suppression d'un bloc.
#
# Le bloc est délimité par deux marqueurs et réécrit à l'identique à chaque
# passage : relancer l'action ne l'empile pas.
# ---------------------------------------------------------------------------
set -uo pipefail

DOM="${DOMAIN:-djounes.com}"
STAMP=$(date +%Y%m%d%H%M%S)
grp() { echo "::group::$*"; }
egrp() { echo "::endgroup::"; }

PUB="$HOME/domains/$DOM/public_html"; APP="$PUB/core"
[ -f "$APP/artisan" ] || { echo "::error::application introuvable"; exit 1; }
CSS="$PUB/assets/templates/basic/css/custom.css"
[ -f "$CSS" ] || : > "$CSS"

DEB="/* ===== DJOUNES : fiche produit — DEBUT (ne pas modifier a la main) ===== */"
FIN="/* ===== DJOUNES : fiche produit — FIN ===== */"

grp "Sauvegarde"
BK="$HOME/djounes-backups"; mkdir -p "$BK"
cp -a "$CSS" "$BK/custom.css-$STAMP" && echo "custom.css : $BK/custom.css-$STAMP ($(wc -c < "$CSS") octets)"
egrp

grp "Écriture du bloc de marque"
# On retire un éventuel bloc précédent (idempotence) sans toucher au reste.
TMP=$(mktemp)
awk -v d="$DEB" -v f="$FIN" '
  $0 == d { skip = 1; next }
  $0 == f { skip = 0; next }
  !skip   { print }
' "$CSS" > "$TMP"

{
  cat "$TMP"
  echo ""
  echo "$DEB"
  cat <<'CSS'
/* Palette de la marque. Déclarée une seule fois, sur la ligne qui contient
   toute la fiche : les variables sont héritées par tout ce qui est dedans.
   Une seule valeur à changer si la charte bouge. */
.product-details-container {
    --dj-green: #0B3B2E;
    --dj-green-dark: #082C22;
    --dj-black: #0A0A0A;
    --dj-gold: #D4AF37;
    --dj-gold-soft: #E8C87A;
    --dj-ivory: #F7F5F0;
}

/* --- 1. La boîte verte de la marque, derrière le produit ------------------
   Le visuel du produit est une découpe sur fond transparent, et la plupart
   des produits DJOUNES sont vert forêt : posés directement sur le vert de la
   marque, ils disparaissent. La boîte verte encadre donc une scène ivoire,
   et c'est sur l'ivoire que le produit se détache.

   Le fond est un pseudo-élément et non un fond de colonne : les colonnes
   Bootstrap portent la gouttière en padding, et le repeindre décalerait
   l'alignement de la ligne. On s'aligne sur la boîte de contenu en retirant
   exactement la demi-gouttière de chaque côté. */
.product-details-container #variantImages {
    position: relative;
}

.product-details-container #variantImages::before {
    content: "";
    position: absolute;
    z-index: 0;
    top: 0;
    bottom: 0;
    left: calc(var(--bs-gutter-x, 1.5rem) * .5);
    right: calc(var(--bs-gutter-x, 1.5rem) * .5);
    border-radius: 26px;
    background: linear-gradient(165deg, var(--dj-green) 0%, var(--dj-green-dark) 100%);
    box-shadow: 0 18px 44px rgba(8, 44, 34, .22);
}

/* Le nom de la marque, discret, en haut de la boîte. */
.product-details-container #variantImages::after {
    content: "DJOUNES";
    position: absolute;
    z-index: 2;
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .34em;
    color: rgba(232, 200, 122, .78);
    pointer-events: none;
}

/* Le contenu de la galerie repasse au-dessus du fond, avec la marge qui
   laisse voir le cadre vert. On vise l'enfant direct plutôt que sa classe :
   la galerie est rechargée en AJAX au changement de variante. */
.product-details-container #variantImages > * {
    position: relative;
    z-index: 1;
    padding: 44px 18px 18px;
}

/* La scène ivoire, sur laquelle le produit se détache. Elle ne concerne que
   l'image principale, visée par son identifiant : les vignettes ont leur
   propre traitement plus bas, et la loupe xzoom pose ses éléments hors de
   ce conteneur — elle n'est pas touchée. */
.product-details-container #variantImages #xzoom-magnific {
    background: var(--dj-ivory);
    border: 1px solid rgba(212, 175, 55, .55);
    border-radius: 18px;
    padding: 5%;
}

.product-details-container #variantImages .xzoom-thumbs img {
    background: var(--dj-ivory);
    border: 1px solid rgba(212, 175, 55, .45);
    border-radius: 10px;
    padding: 6px;
}

/* --- 2. Titre, prix ------------------------------------------------------- */
.product-details-container .product-details .product-title {
    font-family: Georgia, "Times New Roman", serif;
    font-weight: 700;
    letter-spacing: -.015em;
    line-height: 1.15;
    color: var(--dj-black);
}

.product-details-container .product-details .product-price {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 30px;
    font-weight: 700;
    color: var(--dj-green);
}

.product-details-container .product-details .product-price del {
    font-size: 18px;
    font-weight: 400;
    color: rgba(10, 10, 10, .45);
}

.product-details-container .product-detail-price {
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(11, 59, 46, .12);
}

.product-details-container .product-summary {
    color: rgba(10, 10, 10, .72);
    line-height: 1.65;
}

/* --- 3. Tailles et couleurs ----------------------------------------------
   Le thème habille le <span> intérieur, pas le <button> : .text-attribute
   pour une taille, .color-attribute pour une couleur. C'est donc le span
   qu'on reprend — habiller le bouton laisserait le petit cadre de 30px du
   thème à l'intérieur du nôtre.

   Le gabarit, lui, ne distingue pas les deux types d'attribut : les deux
   sortent dans le même .attribute-value-wrapper. :has() lit la différence
   dans le contenu, sans toucher au HTML ; là où il manque, la disposition
   reste celle du thème et rien n'est cassé. */
.product-details-container .attribute-value-wrapper {
    margin-bottom: 22px;
}

.product-details-container .attribute-name {
    display: block;
    width: 100%;
    margin-bottom: 10px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: rgba(10, 10, 10, .6);
}

/* Tailles : des pavés larges, faciles à viser au pouce. */
.product-details-container .attribute-value-wrapper:has(.text-attribute) {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(74px, 1fr));
    gap: 10px;
}

.product-details-container .attribute-value-wrapper:has(.text-attribute) .attribute-name {
    grid-column: 1 / -1;
    margin-bottom: 0;
}

.product-details-container .attribute-value-wrapper:has(.text-attribute) .attribute-value {
    display: block;
    width: 100%;
}

.product-details-container .attribute-value-wrapper .text-attribute {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 52px;
    padding: 0 8px;
    line-height: 1;
    border: 1.5px solid rgba(11, 59, 46, .22);
    border-radius: 12px;
    background: #fff;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--dj-black);
    transition: border-color .15s, background .15s, color .15s;
}

/* Couleurs : des pastilles plus larges. La teinte vient du style en ligne
   posé par le gabarit — on ne repeint jamais le fond, sinon on afficherait
   une autre couleur que celle vendue. */
.product-details-container .attribute-value-wrapper:has(.color-attribute) {
    gap: 12px;
}

.product-details-container .attribute-value-wrapper .color-attribute {
    width: 36px;
    height: 36px;
    border: 2px solid #fff;
    outline: 1px solid rgba(11, 59, 46, .18);
}

/* --- 4. Le choix retenu --------------------------------------------------
   product_details.js bascule la classe « active » sur le bouton choisi : la
   sélection s'appuie sur elle, et sur rien d'autre. La coche que le thème
   pose au centre d'une pastille active est conservée. */
.product-details-container .attribute-value-wrapper .attribute-value.active .text-attribute {
    border-color: var(--dj-green);
    background: var(--dj-green);
    color: #fff;
}

.product-details-container .attribute-value-wrapper .attribute-value.active .color-attribute {
    outline: 2px solid var(--dj-gold);
    box-shadow: 0 0 0 4px rgba(212, 175, 55, .22);
}

/* --- 5. Ajout au panier --------------------------------------------------
   Sur mobile la fiche est longue : le bouton reste au bas de l'écran tant
   qu'on lit, au lieu d'obliger à remonter. En CSS seul, donc sans rien
   changer au comportement du bouton. */
.product-details-container .product-add-to-cart .addToCart {
    flex-grow: 1;
    min-height: 54px;
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.product-details-container .add-to-wishlist-btn {
    border-radius: 999px;
}

@media (max-width: 991.98px) {
    .product-details-container .product-add-to-cart {
        position: sticky;
        bottom: 0;
        z-index: 20;
        margin: 0 calc(var(--bs-gutter-x, 1.5rem) * -.5);
        padding: 12px calc(var(--bs-gutter-x, 1.5rem) * .5);
        background: var(--dj-ivory);
        border-top: 1px solid rgba(11, 59, 46, .14);
        box-shadow: 0 -8px 24px rgba(10, 10, 10, .07);
    }

    /* La boîte verte prend toute la largeur : c'est la première chose vue. */
    .product-details-container #variantImages::before {
        left: 0;
        right: 0;
        border-radius: 22px;
    }

    .product-details-container .product-details .product-title {
        font-size: 26px;
    }
}
CSS
  echo "$FIN"
} > "$CSS.new"

if ! grep -q "DJOUNES : fiche produit — FIN" "$CSS.new"; then
  echo "::error::bloc mal écrit — rien n'est remplacé."; rm -f "$TMP" "$CSS.new"; exit 1
fi
mv "$CSS.new" "$CSS"
chmod 644 "$CSS"
rm -f "$TMP"
echo "custom.css : $(wc -l < "$CSS") lignes, $(wc -c < "$CSS") octets"
egrp

grp "Vidage des caches"
( cd "$APP" && php artisan optimize:clear 2>&1 | head -4 ) || true
egrp

grp "Vérification"
OK=1
for p in "" "products" "product/nurse-scrub-set" "product/compression-socks"; do
  C=$(curl -sS -o /dev/null -m 25 -w '%{http_code}' "https://$DOM/$p" 2>/dev/null)
  echo "https://$DOM/$p → $C"
  [ "$C" = "200" ] || OK=0
done

# Une feuille qui ne part pas au navigateur ne sert à rien : on la demande
# comme le ferait un visiteur.
SERVED=$(curl -sS -m 25 "https://$DOM/assets/templates/basic/css/custom.css" 2>/dev/null)
case "$SERVED" in
  *"DJOUNES : fiche produit"*) echo "custom.css servie et contient le bloc ($(printf '%s' "$SERVED" | wc -c) octets)";;
  *) echo "::warning::custom.css est servie mais sans le bloc — cache serveur ou chemin d'assets différent."; OK=0;;
esac

if [ "$OK" != "1" ]; then
  echo "::error::vérification échouée — restauration de custom.css."
  cp -a "$BK/custom.css-$STAMP" "$CSS"
  ( cd "$APP" && php artisan optimize:clear >/dev/null 2>&1 ) || true
  exit 1
fi
echo "Retour en arrière : cp -a $BK/custom.css-$STAMP $CSS"
egrp
echo "Terminé."
