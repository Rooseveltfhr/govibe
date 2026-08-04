# DJOUNES — identité, boutique et croissance

> Marque **américaine** (bureau aux États-Unis) vendant en ligne sur `djounes.com`.
> Boutique propulsée par Visermart (Laravel). État technique : `docs/DJOUNES.md`.

## 1. Positionnement

Huit lignes de produits qui, listées telles quelles, ressemblent à un bazar :
scrubs, chaussettes de compression, sous-vêtements, headband, tote bag, whipped
body butter, oil, savon. Elles racontent pourtant **une seule histoire** : la
journée d'une infirmière.

> **DJOUNES — built for the 12-hour shift.**
> Ce qu'on porte pendant la garde, ce dont la peau a besoin après.

Ce fil conducteur règle le risque de confusion : le client ne voit pas deux
boutiques collées ensemble, il voit une marque qui couvre la garde du début à la
fin. Tout le reste — navigation, photos, e-mails — doit répéter cette phrase.

**Cible** : personnel soignant aux États-Unis (infirmières, aides-soignantes,
techniciennes, étudiantes en soins infirmiers), très majoritairement des femmes,
qui achètent depuis leur téléphone entre deux gardes.

## 2. Palette

Vert foncé, noir, or — décidés par le fondateur. Valeurs exactes et, surtout,
**où chaque couleur a le droit d'apparaître** : c'est ce qui sépare une marque
chère d'une marque criarde.

| Rôle | Couleur | Hex | Usage |
|---|---|---|---|
| Primaire | Vert forêt | `#0B3B2E` | en-tête, pied de page, bandeaux, fonds de section |
| Base | Noir | `#0A0A0A` | textes, photos produit sur fond neutre |
| Accent | Or | `#D4AF37` | filets, icônes, prix promo, badges — **jamais** de grands aplats |
| Or clair | Or pâle | `#E8C87A` | texte or sur fond vert foncé (meilleur contraste) |
| Fond | Ivoire | `#F7F5F0` | fond de page, respiration |
| Texte secondaire | Gris | `#6B7280` | mentions, libellés |

### Contrastes vérifiés (WCAG, calculés)

| Combinaison | Ratio | Verdict |
|---|---|---|
| Blanc sur vert foncé | 12,5 | AAA — combinaison principale |
| Ivoire sur vert foncé | 11,5 | AAA |
| Or pâle `#E8C87A` sur vert foncé | 7,7 | AAA — pour du texte or lisible |
| Or `#D4AF37` sur noir | 9,4 | AAA |
| Or `#D4AF37` sur vert foncé | 6,0 | AA |
| **Or `#D4AF37` sur blanc** | **2,1** | **insuffisant — interdit pour du texte** |

Règle à retenir : **l'or ne se pose jamais sur du blanc pour du texte**. Sur fond
clair, un bouton doré doit avoir du texte noir, ou passer en or foncé `#8A6B22`
(ratio 5,0).

Ces deux couleurs se posent **depuis l'administration** (`base_color` = `0B3B2E`,
`secondary_color` = `D4AF37`) : aucune modification de code, donc rien à rejouer
après une mise à jour du script.

### Boutons

Le bouton « Add to cart » est l'élément le plus important du site. Il doit être
**vert foncé, texte blanc, pleine largeur sur mobile**. L'or reste au liseré et
aux badges : un bouton entièrement doré avec du texte blanc est illisible.

## 3. Logo

La marque n'avait pas de logo. Créé et **posé sur le site** le 4 août 2026 —
sources dans `docs/brand/`, rendus dans `docs/brand/png/` :

| Fichier | Usage | Installé en |
|---|---|---|
| `png/logo.png` (fond transparent) | en-tête du site | `assets/images/logo_icon/logo.png` |
| `png/logo-dark-bg.png` (fond vert) | pied de page, e-mails | `assets/images/logo_icon/logo_dark.png` |
| `png/favicon.png` (512 px) | onglet du navigateur | `assets/images/logo_icon/favicon.png` |
| `djounes-wordmark.svg` · `djounes-monogram.svg` | sources vectorielles, impression et broderie | — |

Le mot-symbole joue sur une lettrine dorée et une barre horizontale qui évoque à
la fois un liseré d'uniforme et une ligne de rythme cardiaque. Il fonctionne en
une seule couleur (nécessaire pour la broderie sur les scrubs et le marquage sur
les savons), et reste lisible à 24 px de haut.

## 4. Architecture de la boutique

Le catalogue est vide : c'est le bon moment pour poser une structure propre.

### Navigation (3 entrées, pas plus)

| Entrée | Contenu |
|---|---|
| **Scrubs & Apparel** | scrubs, sous-vêtements, chaussettes de compression, headbands |
| **Body Care** | whipped body butter, oil, savon |
| **Accessories** | tote bag |

Trois catégories que le client comprend en une seconde. Les regroupements
marketing (« New », « Best sellers », « Shift Kit ») passent par les
*collections*, pas par de nouvelles entrées de menu.

### Variantes

Le schéma gère les variantes avec photos par variante. À créer une fois, puis
réutiliser :

| Attribut | Valeurs | S'applique à |
|---|---|---|
| Size | XS · S · M · L · XL · 2XL · 3XL | scrubs, sous-vêtements |
| Color | selon la production | scrubs, sous-vêtements, headband, tote bag |
| Compression | 15–20 mmHg · 20–30 mmHg | chaussettes |
| Shoe size | S/M · L/XL | chaussettes |
| Size (body care) | 2 oz · 4 oz · 8 oz | body butter, oil |
| Scent | selon la production | body care |

**Le stock se gère par variante, pas par produit.** Une taille M épuisée doit
apparaître épuisée, sinon le client commande ce qui n'existe pas et la première
expérience de la marque est un remboursement.

## 5. Ce qui fait qu'un client achète

Par ordre d'impact réel sur le taux de conversion :

1. **Guide des tailles.** Sur des vêtements vendus en ligne, l'absence de guide
   est la première cause d'abandon et de retour. Un tableau en pouces (marché US)
   avec tour de poitrine, tour de taille, longueur d'entrejambe, plus « la
   mannequin mesure 5'6" et porte du M ».
2. **Photos.** Cinq par produit minimum : porté de face, porté de dos, détail
   tissu/poches, à plat, et une photo par couleur. Fond ivoire uniforme.
3. **Prix et livraison annoncés tôt.** Les frais de port découverts au dernier
   écran sont la deuxième cause d'abandon. Afficher « Free shipping over $75 »
   dans un bandeau permanent.
4. **Avis clients avec photos** — le schéma les gère, y compris les réponses.
   Solliciter un avis par e-mail 10 jours après livraison.
5. **Politique de retour visible.** « 30-day returns » écrit à côté du bouton
   d'achat, pas caché dans une page.
6. **Preuve que la marque est réelle** : adresse du bureau US, téléphone, e-mail
   de contact dans le pied de page. Une boutique sans adresse fait fuir.
7. **Paiement rapide** : carte, plus au moins un portefeuille (Apple Pay /
   PayPal). Le paiement mobile en trois taps double les conversions par rapport
   à un formulaire de carte long.

## 6. Marketing

### Bundles — le levier le plus rentable ici

Les huit lignes se combinent naturellement. Un panier moyen à un seul produit
tue la rentabilité quand les frais de port sont fixes.

- **The Shift Kit** — 1 scrub set + 1 paire de chaussettes de compression +
  1 body butter. Le produit vitrine de la marque.
- **Recovery Duo** — body butter + oil, après la garde.
- **Starter Set** — pour les étudiantes en soins infirmiers, à la rentrée.

### Calendrier

| Moment | Action |
|---|---|
| Lancement | code `FIRST15` (-15 %) contre inscription e-mail |
| Mai | **Nurses Week** (6–12 mai aux États-Unis) — le pic de l'année pour cette cible |
| Août–septembre | rentrée des écoles d'infirmières — Starter Set |
| Novembre | Black Friday / Cyber Monday |
| Décembre | idées cadeaux, cartes cadeaux |

### Canaux

- **E-mail** : la liste (`subscribers`) est le seul canal que la marque possède.
  Bandeau d'inscription avec la remise de bienvenue, puis une lettre par semaine :
  un produit, une histoire, un avis client.
- **Instagram et TikTok** : le contenu qui fonctionne sur cette cible est
  « get ready with me » avant la garde et le déballage. Republier les photos des
  clientes (avec autorisation) — la preuve sociale coûte moins cher que la pub.
- **SEO** : cibler des recherches précises (`jogger scrubs for women`,
  `compression socks for nurses 20-30 mmHg`) plutôt que `scrubs`, hors de portée.
  Une description unique par produit, jamais celle du fournisseur.

## 7. Obligations d'une marque américaine

- **Devise USD**, tailles en pouces, dates au format US.
- **Anglais en langue principale.** Le site n'a qu'une langue installée : la
  garder en anglais. Une version française/créole se justifie seulement si une
  clientèle haïtienne apparaît réellement.
- **Pages légales** (7 pages statiques déjà présentes à remplir) : Shipping
  Policy, Return & Refund Policy, Privacy Policy, Terms of Service, Contact,
  About, FAQ. Ce sont aussi des exigences des processeurs de paiement — Stripe et
  PayPal les vérifient avant de valider un compte marchand.
- **Coordonnées du bureau US** dans le pied de page et sur la page Contact.
- **Taxe de vente** : à collecter dans l'État où l'entreprise a un lien fiscal
  (*nexus*), et dans les autres au-delà de certains seuils. À valider avec un
  comptable — ce n'est pas un réglage qu'on devine.

## 8. Livraison — recommandation

La table `shipping_methods` est vide et le schéma ne prévoit **aucune intégration
transporteur** : Visermart ne sait faire que des méthodes saisies à la main
(forfait, gratuit au-dessus d'un montant). Les tarifs en temps réel demandent du
développement.

### Étape 1 — vendre tout de suite, sans code

| Méthode | Tarif |
|---|---|
| Standard (3–5 jours) | 5,95 $ |
| Free Shipping | à partir de 75 $ d'achat |
| Express (1–2 jours) | 14,95 $ |

Un forfait couvre 90 % des cas pour des colis légers et homogènes, et le seuil de
gratuité pousse le panier vers le haut. C'est ce qu'il faut au lancement.

### Étape 2 — API multi-transporteurs

Quand le volume le justifie (à partir d'une dizaine de commandes par jour, quand
le forfait commence à coûter cher sur les gros colis) :

| Service | Pourquoi | Réserve |
|---|---|---|
| **Shippo** — recommandé pour démarrer | tarifs USPS/UPS/FedEx négociés, pas d'abonnement obligatoire, paiement à l'étiquette, API simple | moins d'automatisation avancée |
| **EasyPost** | la plus complète (tarifs, étiquettes, suivi, assurance), très bonne doc | facturation à l'appel, pensée pour un vrai volume |
| **ShipStation** | excellent poste de travail expédition, impression en lot | abonnement mensuel, et il faut de toute façon un point d'entrée pour importer les commandes |

Pour DJOUNES : **Shippo**. Coût nul tant qu'on n'imprime pas d'étiquette, tarifs
USPS remisés — décisifs pour des colis de vêtements légers — et l'intégration se
limite à deux points : un appel de tarification au moment du choix de livraison,
un appel d'achat d'étiquette au moment de l'expédition.

**Ce que ça implique** : c'est du code ajouté dans une application achetée. Une
mise à jour de l'éditeur peut l'écraser. Il faut donc l'isoler dans ses propres
fichiers, la documenter, et la rejouer après chaque mise à jour — la même règle
que pour tout changement décrit au chapitre suivant.

## 9. Renommage Visermart → DJOUNES — ce que le scan a réellement trouvé

Scan complet du code le 4 août 2026, hors `vendor/`. Résultat : **six occurrences,
et aucune n'est visible par un client.**

| Emplacement | Nature | Décision |
|---|---|---|
| `core/app/Http/Helpers/helpers.php:28` — `$system['name'] = 'visermart';` | identifiant système du script (licence, mises à jour) | **ne pas toucher** — le modifier risque de casser la vérification de licence |
| `layouts/master.blade.php:97` — `<x-frontend.visermart-script />` | nom d'un composant Blade, pointe vers `components/frontend/visermart-script.blade.php` | **ne pas toucher** — purement interne, invisible ; le renommer oblige à renommer le fichier et n'apporte rien |
| `partials/cart_bottom.blade.php:21` | ligne **commentée** contenant un code promo d'exemple | sans effet |
| `core/storage/framework/views/*.php` (2 fichiers) | **cache de vues compilées**, régénéré tout seul | disparaît au premier vidage de cache |
| `install.desactive-…/index.php` | installeur déjà neutralisé | hors ligne |

**Ce que le client voit ne vient pas du code, mais de deux réglages :**

1. `general_settings.site_name` en base — le nom affiché partout : titre des pages,
   en-tête, e-mails, factures ;
2. `APP_NAME` dans `.env` — utilisé par certaines notifications.

Mettre ces deux valeurs à `DJOUNES` suffit à faire disparaître Visermart de tout ce
qu'un client peut voir. Un remplacement global dans le code n'apporterait rien de
visible et casserait l'identifiant de licence — **on ne le fait pas.**

**À savoir pour la suite** : toute modification de fichier du script sera écrasée
par une mise à jour de l'éditeur. Les changements sont donc consignés ici pour être
rejoués, et on privilégie toujours un réglage d'administration quand il existe.

## 10. État au 4 août 2026 — ce qui est posé

Appliqué sur le site en production, après sauvegarde complète de la base, des
logos et du `.env` (`~/djounes-backups/`), et vérifié : accueil, `/categories` et
`/products` répondent 200.

| Élément | Valeur posée |
|---|---|
| Nom du site | DJOUNES (déjà en place), `APP_NAME` aligné |
| `APP_URL` | slash final retiré |
| `base_color` | `0B3B2E` (remplaçait un vert vif `159913`) |
| `secondary_color` | `D4AF37` (remplaçait un gris `ebebeb`) |
| Devise | USD `$` |
| Logos | `logo.png`, `logo_dark.png`, `favicon.png` dans `assets/images/logo_icon/` |
| Paiement à la livraison | **désactivé** — sans objet pour une marque qui expédie |
| Commande sans compte | activée |
| Avis clients | actifs, **sans** approbation automatique |
| Catégories | Scrubs & Apparel · Body Care · Accessories |
| Marque | DJOUNES |
| Produits | 8, publiés, avec prix, SKU, stock 25 et image de remplacement |
| Livraison | Standard 5,95 $ · Free over 75 $ · Express 14,95 $ |

Les 8 produits sont des **modèles à modifier** : chacun a un nom, un résumé, un
prix, un SKU et une image marquée « photo à remplacer ». Deux portent un prix
barré (Nurse Scrub Set 64 → 54 $, Body Butter 26 → 22 $) pour montrer comment une
promotion s'affiche.

L'action est **rejouable sans risque** : elle ne sème le catalogue que si la
boutique est vide, donc elle ne dupliquera ni n'écrasera de vrais produits.

### Textes, politiques et exploitation (même jour)

| Élément | Détail |
|---|---|
| Accroche d'accueil | *Built for the 12-hour shift* + sous-titre, sur bannière, encarts et citation |
| Arguments (4) | Free shipping over $75 · 30-day returns · Made for healthcare · Secure checkout |
| Services (4) | Scrubs & apparel · Body care · Accessories · Support that answers |
| About Us | histoire de marque en trois paragraphes |
| Newsletter | *Get 15% off your first order* |
| Pied de page | description et mention de copyright |
| **Politiques (3)** | Shipping Policy · Return & Refund Policy · Privacy Policy, rédigées |
| SEO | titre, description et mots-clés de la page d'accueil |
| Coupon | **FIRST15**, 15 %, une utilisation par client, valable un an |
| **Sauvegarde quotidienne** | `~/bin/djounes-backup.sh` via cron à 3h17 — base + `assets/images`, rotation 14 jours |

Le contenu du thème est du JSON dont la forme appartient au script acheté. Le
script de contenu ne le reconstruit pas : il le relit et ne remplace que les clés
déjà présentes portant une chaîne. Images, objets imbriqués et valeurs numériques
restent intacts. Vérifié après coup : `/`, `/categories`, `/products`, `/contact`,
`/about-us` et `/faq` répondent tous 200.

Deux points à confirmer dans l'administration : le sens de `discount_type` sur le
coupon (pourcentage ou montant fixe, le script ne le documente pas), et le fait
que la section `services.content` n'existe pas dans cette installation — seuls ses
quatre éléments existent, et ils sont remplis.

## 11. Ce qu'il reste à ajouter

Par ordre : ce qui empêche de vendre, puis ce qui fait vendre plus.

### Bloquant — sans ça, une commande ne peut pas aboutir

| À faire | Où | Pourquoi |
|---|---|---|
| Configurer **Stripe** et **PayPal** | admin → passerelles | 32 passerelles listées, **aucune configurée** : aujourd'hui personne ne peut payer. Demande les comptes marchands. |
| Configurer l'**envoi d'e-mails** (`mail_config`) | admin | sans SMTP, aucune confirmation de commande ne part. Un client qui paie sans rien recevoir ouvre un litige. |
| **Adresse du bureau US**, téléphone, e-mail de contact | pied de page + page Contact | exigé par Stripe et PayPal à l'ouverture du compte marchand, et décisif pour la confiance. |
| ~~Politiques Shipping, Returns, Privacy~~ | `policy_pages` | ✅ rédigées. Reste **Terms of Service**, et l'adresse US à insérer dedans. |

### Important — le catalogue réel

| À faire | Note |
|---|---|
| **Variantes** taille et couleur | à créer depuis l'administration : l'encodage du lien variante ↔ valeurs d'attribut est propre au script, l'écrire directement en base est fragile. |
| **Vraies photos** (5 par produit) | remplacent les images de démonstration, mêmes noms de fichiers ou via l'administration. |
| **Guide des tailles** en pouces | première cause d'abandon et de retour sur du vêtement. |
| Prix, stocks et SKU réels | les modèles portent des valeurs d'exemple. |

### Croissance

| À faire | Note |
|---|---|
| ~~Coupon de bienvenue `FIRST15`~~ | ✅ créé — reste à vérifier le sens de `discount_type` |
| ~~Sections d'accueil~~ | ✅ écrites — `counter` (chiffres) reste à remplir quand il y aura des chiffres réels |
| Liens sociaux (`social_icon`, 3 emplacements) | Instagram et TikTok en premier — demande les comptes |
| **Analytics** (Google Analytics, Meta Pixel) | via `global_shortcodes` — sans mesure, aucune décision marketing n'est vérifiable |
| `seo.data` et métadonnées produits | descriptions uniques, jamais celles d'un fournisseur |
| Collections « Shift Kit », « New », « Best sellers » | via `product_collections`, sans toucher au menu |
| Sollicitation d'avis 10 jours après livraison | les avis avec photo sont gérés nativement |

### Exploitation

| À faire | Note |
|---|---|
| ~~**Sauvegardes régulières**~~ | ✅ cron quotidien à 3h17, base + images, rotation 14 jours |
| Changer le mot de passe administrateur | l'installeur est resté exposé un temps |
| Taxe de vente selon le *nexus* | à valider avec un comptable |
| API de livraison (Shippo) | quand le volume dépasse une dizaine de commandes par jour — chapitre 8 |

## 12. Carte de l'administration

Ce que le scan a révélé des réglages disponibles — utile pour savoir quoi faire
sans toucher au code.

### Réglages généraux (`general_settings`)

`site_name` · **`base_color`** · **`secondary_color`** · `cur_text` / `cur_sym` /
`currency_format` · `email_from` / `email_from_name` / `email_template` ·
`mail_config` · `sms_config` · `firebase_config` · `socialite_credentials` ·
`force_ssl` · `maintenance_mode` · `multi_language` · `registration` ·
`guest_checkout` · `cod` · `product_review` / `product_review_auto_approval` ·
`product_wishlist` / `product_compare` · `recently_viewed_items` ·
`homepage_layout` · `active_template` · `paginate_number` · `subscriber_module`

**Bonne nouvelle pour la charte** : `base_color` et `secondary_color` sont des
réglages. Le vert forêt et l'or se posent depuis l'administration, sans modifier
une seule ligne de code — donc sans rien à rejouer après une mise à jour.

Autres décisions à prendre là : activer **`guest_checkout`** (un compte obligatoire
fait perdre des ventes), désactiver **`cod`** (paiement à la livraison, sans objet
pour une marque US qui expédie), laisser `product_review` actif mais
`product_review_auto_approval` **désactivé** (modération avant publication).

### Sections de la page d'accueil (`frontends`)

`banner` (+8 éléments) · `feature` (4) · `services` (4) · `counter` (4) ·
`featured_categories` · `featured_brands` · `top_selling_products` ·
`recent_viewed` · `quote` · `newsletter` / `subscribe` · `about_us` ·
`contact_page` (3) · `faq_page` · **`policy_pages` (3)** · `footer` ·
`footer_menu` · `social_icon` (3) · `seo.data` · `cookie.data` ·
`order_confirmation` · `header_one` / `header_two` / `header_three` (+ `headers.order`)

Trois dispositions d'en-tête sont fournies : en choisir une et s'y tenir.
Les politiques légales du chapitre 7 se remplissent dans `policy_pages`.

### Pages existantes

Home · About Us · All Categories · All Brands · Contact · Offer · FAQ

### Passerelles de paiement (32 disponibles, 0 configurée)

Pour une marque américaine, trois comptent : **Stripe** (trois variantes fournies —
Checkout, Hosted, Storefront), **PayPal** (et PayPal Express), **Authorize.net**.
Les autres — bKash, Aamarpay, PayTM, Instamojo, SslCommerz, Flutterwave, Paystack,
les passerelles crypto — visent d'autres marchés : les laisser désactivées pour ne
pas encombrer la page de paiement.

Recommandation : **Stripe Checkout + PayPal**. Stripe apporte les cartes et Apple
Pay / Google Pay, PayPal rassure une partie de la clientèle américaine. Deux
options à l'écran de paiement, pas douze — au-delà, le choix fait hésiter.
