# djounes.com — accès & maintenance

> Rezime an kreyòl: `djounes.com` sou yon **kont ebèjman apa** (pa VPS tagtoa a).
> Pou m ka travay sou li, mete 3 sekrè GitHub yo (§3). Anvan sa, mwen pa gen okenn
> aksè — panèl la mande yon login, e rezo sesyon an bloke domèn nan.
>
> **Ce fichier est la mémoire de djounes.com.** `CLAUDE.md` n'en parle pas : c'est
> ici qu'il faut lire avant de toucher à ce site, et ici qu'il faut écrire après.

## 0. État de la mise à la marque (septembre 2026)

| Fait | État |
|---|---|
| Logo, couleurs (`#0B3B2E` vert forêt / `#0A0A0A` noir / `#D4AF37` or), devise USD | ✅ en ligne |
| 8 produits, 3 catégories, 5 attributs, 42 variantes, découpes PNG transparentes | ✅ en ligne |
| Vitrine d'accueil « Shift favorites », 3 pages légales, coupon FIRST15 | ✅ en ligne |
| Sauvegarde automatique quotidienne (`~/bin/djounes-backup.sh`, cron `17 3 * * *`) | ✅ installée |
| Panneau d'administration aux couleurs de la marque | ✅ en ligne |
| 8 bannières du carrousel, au **format natif 1284×345** | ✅ en ligne |
| Fiche produit à la charte, **en CSS seulement** (`custom.css`) | ✅ en ligne |
| Stripe + PayPal, SMTP, adresse/téléphone/e-mail du bureau US, Terms of Service, GA/Meta Pixel | ⏳ **action du fondateur** |

Deux pièges déjà payés, à ne pas redécouvrir :

- **Bannières** : les diapositives du thème mesurent **1284×345**. Un visuel dessiné
  dans un autre rapport est écrasé verticalement par le redimensionnement. On les
  dessine au double (2568×690) et le script réduit au format natif.
- **Fiche produit** : le thème habille le `<span>` intérieur
  (`.text-attribute` / `.color-attribute`), **pas** le `<button>`, et la classe de
  sélection posée par `product_details.js` est **`active`**. Habiller le bouton ou
  deviner la classe rend la sélection invisible — juste avant l'achat.

La vérification de licence ViserLab (`helpers.php`, `systemDetails()['name']`)
n'est **pas** touchée : la contourner serait un contournement de licence. Pour
retirer la dépendance, deux voies légitimes — migrer hors du script, ou prendre
une licence étendue / marque blanche chez ViserLab.

## 1. Ce qu'on sait (vérifié)

| Élément | Valeur |
|---|---|
| Domaine | `djounes.com` (et `www.djounes.com`) |
| IP du site | `173.225.109.155` |
| Panneau de contrôle | DirectAdmin — `https://vda8400.is.cc:2222` |
| IP du panneau | `173.225.109.154` (même bloc réseau que le site) |
| SSH | `vda8400.is.cc`, **port 22** (le 2222 du panneau n'expose pas SSH) |
| Stack du site | **Visermart** — e-commerce Laravel, voir §5 |

**Important : ce n'est PAS le serveur de TAGTOA.** `tagtoa.com` et `govibepay.com`
répondent depuis `67.217.56.29`, sur le VPS décrit dans `CLAUDE.md` §6.
`djounes.com` vit sur un hébergement web distinct, avec son propre panneau, son
propre compte et ses propres identifiants. Les secrets GitHub `VPS_*` existants
ne donnent **aucun** accès à cet hébergement, et le workflow de djounes.com
refuse volontairement de les utiliser (se tromper de serveur serait pire que ne
rien faire).

> Note : `diagnose.yml` cite `djounes.com` dans sa liste `SITES_TO_PAUSE`. Cette
> liste ne s'applique qu'aux domaines du compte VPS ; sur ce serveur-là, le
> domaine est simplement signalé « introuvable (ignoré) ».

## 2. Pourquoi il faut des identifiants

Depuis une session Claude Code, l'accès réseau sortant est filtré : `djounes.com`
est refusé par la passerelle (403 sur le CONNECT), et le panneau DirectAdmin
demande de toute façon un login. Le lien `https://vda8400.is.cc:2222/evo/database`
(gestion des bases MySQL) n'est donc pas exploitable directement.

La solution retenue est la même que pour TAGTOA : **GitHub Actions** dispose du
réseau et des secrets ; on y déclenche un workflow qui se connecte en SSH à
l'hébergement et rapporte tout dans son journal.

## 3. Les 3 secrets à créer (action requise)

Dépôt GitHub → **Settings → Secrets and variables → Actions → New repository secret** :

| Secret | Valeur | Où la trouver |
|---|---|---|
| `DJOUNES_SSH_HOST` | `vda8400.is.cc` (ou `173.225.109.155`) | l'adresse du panneau, sans `https://` ni `:2222` |
| `DJOUNES_SSH_USER` | le nom d'utilisateur du compte | affiché en haut à droite dans DirectAdmin |
| `DJOUNES_SSH_KEY` | la **clé privée** SSH (contenu complet, `-----BEGIN` … `-----END`) | générée à l'étape ci-dessous |
| `DJOUNES_SSH_PORT` | *(optionnel)* `22` par défaut ; certains serveurs DirectAdmin utilisent `2222` | à tester si la connexion échoue |

### Le mot de passe DirectAdmin ne sert pas ici — et ne doit pas circuler

Le mot de passe du panneau ouvre **tout** le compte (fichiers, bases, e-mails,
DNS) et ne peut ni être révoqué séparément ni limité. Une clé SSH dédiée, au
contraire, se supprime en un clic dans le panneau sans toucher au reste. C'est
donc une clé, jamais le mot de passe, qu'on met dans les secrets GitHub — et un
mot de passe collé dans un message, une issue ou une PR est à considérer comme
compromis, donc à changer.

### Créer la clé depuis le panneau (le plus simple)

Le panneau tourne sur la skin Evolution (`/evo/` dans l'URL) et sait générer la
paire de clés lui-même — rien à installer.

1. Se connecter sur `https://vda8400.is.cc:2222` avec le compte de djounes.com.
2. Menu **Advanced Features → SSH Keys**.
   *Si l'entrée n'existe pas, l'offre n'inclut pas SSH → voir la section suivante.*
3. Bouton **Create Key** :
   - *Key Type* : `ed25519` (à défaut `rsa` en 4096 bits) ;
   - *Key Name* : `github-actions-djounes` ;
   - *Passphrase* : **laisser vide** — un runner GitHub ne peut pas la saisir ;
   - cocher **Authorize** (ajoute la clé aux `authorized_keys`).
4. Valider : le navigateur télécharge la **clé privée** (un fichier sans
   extension ou en `.key`). C'est le seul moment où elle est téléchargeable.
5. Ouvrir ce fichier dans un éditeur de texte et copier **tout** le contenu, de
   `-----BEGIN OPENSSH PRIVATE KEY-----` à `-----END OPENSSH PRIVATE KEY-----`
   incluses, retour à la ligne final compris.
6. GitHub → dépôt `Rooseveltfhr/govibe` → **Settings → Secrets and variables →
   Actions → New repository secret** → créer `DJOUNES_SSH_KEY` avec ce contenu,
   puis `DJOUNES_SSH_HOST` et `DJOUNES_SSH_USER` (valeurs du tableau ci-dessus).
7. Supprimer le fichier téléchargé une fois le secret enregistré.

Variante en ligne de commande, si tu préfères garder la clé sur ta machine :
`ssh-keygen -t ed25519 -C "github-actions-djounes" -f ~/.ssh/djounes_deploy`,
puis coller le `.pub` dans **SSH Keys → Add Key** et le fichier privé dans le
secret GitHub.

### Vérifier que ça marche

Fusionner la PR dans `main` (le bouton n'apparaît dans l'onglet **Actions**
qu'une fois le workflow présent sur la branche par défaut), puis lancer
*Djounes.com — inventaire & maintenance* en laissant `action = inventaire`.

- Le job passe → l'inventaire est dans le journal, rien n'a été modifié.
- `Permission denied (publickey)` → la clé n'est pas autorisée (revoir l'étape 3)
  ou `DJOUNES_SSH_USER` n'est pas le bon nom d'utilisateur.
- `Connection refused` / *timeout* → mauvais port : créer `DJOUNES_SSH_PORT` avec
  `2222`, certains serveurs DirectAdmin y placent aussi SSH.

### Si l'hébergement ne propose pas SSH

Sur beaucoup de formules mutualisées, SSH est désactivé. Dans ce cas :

- demander l'activation au support de l'hébergeur (un shell restreint « jailed »
  suffit largement pour ce workflow) ;
- en attendant, relever à la main dans le panneau : **File Manager** (contenu de
  `domains/djounes.com/public_html` — la présence de `wp-config.php`, `artisan`
  ou d'un simple `index.html` suffit à identifier la stack), **MySQL Management**
  (noms des bases) et la version PHP. Sans SSH l'automatisation est impossible,
  mais le diagnostic et les correctifs de code restent faisables.

## 4. Ce qui est déjà en place dans ce dépôt

| Fichier | Rôle |
|---|---|
| `.github/workflows/djounes.yml` | bouton manuel (Actions → « Djounes.com — inventaire & maintenance ») |
| `.github/scripts/djounes-ops.sh` | script exécuté à distance sur l'hébergement |

### Lancer l'inventaire

Onglet **Actions** → *Djounes.com — inventaire & maintenance* → **Run workflow** →
laisser `action = inventaire` → **Run**.

Le journal remonte : arborescence du domaine et du docroot, espace disque et
quota, **stack détectée** (WordPress + version + extensions, Laravel, PHP
générique ou site statique), configuration DB (mots de passe masqués), liste des
bases et nombre de tables, version de PHP, code HTTP et **expiration du
certificat TLS**, 30 dernières lignes des journaux d'erreurs, tâches cron.

### Les autres actions

| Action | Effet | Réversible |
|---|---|---|
| `inventaire` | lecture seule, ne modifie rien | — |
| `scan_*` (`scan_banner`, `scan_accueil`, `scan_collection`, `scan_fiche`, `scan_marque`, `scan_admin`, `scan_modeles`, `schema_catalogue`, `diag_catalogue`) | lecture seule : relèvent la structure réelle (gabarits, modèles, casts, classes CSS) avant toute écriture | — |
| `demarrage_marque` | logo, couleurs, devise, catalogue de départ | oui, dump + sauvegarde fichiers avant |
| `contenu_marque` | textes d'accueil, pages légales, coupon, sauvegarde quotidienne | oui, dump avant |
| `catalogue_variantes` | vitrine d'accueil, attributs, variantes, découpes transparentes | oui, dump avant |
| `bannieres_marque` | remplace les 8 diapositives du carrousel et leur donne une destination | oui, dump + anciennes images conservées |
| `fiche_marque` | fiche produit à la charte, **en CSS seulement** (bloc délimité dans `custom.css`) | oui, `custom.css` sauvegardée avant |
| `admin_marque` | couleurs et nom de la marque dans le panneau d'administration | oui, sauvegarde avant |
| `securiser_install` | neutralise le dossier `install/` | oui, dossier renommé |
| `vider_cache` | `artisan optimize:clear` (Laravel) ou vidage de `wp-content/cache` (WordPress) | oui, le cache se régénère |
| `mettre_en_pause` | `public_html` → `public_html.paused` + page « Maintenance » | oui, via `reprendre_site` |
| `reprendre_site` | restaure `public_html.paused` | oui |

Méthode suivie pour toute écriture : **d'abord un `scan_*` en lecture seule**
pour relever la structure réelle (schéma, casts Eloquent, constantes, classes
CSS, classe basculée par le JS), **puis** l'action qui écrit. Deviner coûte
plus cher que lire : c'est ce qui a évité de casser la sélection de taille et
de couleur sur la fiche produit, où le thème habille le `<span>` intérieur et
non le `<button>`.

Chaque action qui écrit prend un `mysqldump` et une copie des fichiers
concernés **avant**, vérifie que les pages répondent 200 **après**, et
restaure d'elle-même sinon. Les sauvegardes restent dans
`~/djounes-backups/`.

Le champ `fetch_file` récupère un fichier de configuration (chemin **relatif** au
dossier du domaine, ex. `public_html/wp-config.php`) sous forme d'artefact, sans
passer par le journal — le masquage automatique des secrets corromprait le
contenu.

### Garde-fous du script

- aucune écriture en base : uniquement `SHOW` / `SELECT` sur `information_schema` ;
- la mise en pause **renomme**, elle ne supprime jamais le site ;
- à la reprise, si `public_html` contient autre chose que la page d'attente posée
  par le script, son contenu est **archivé** (`public_html.avant-reprise-<date>`)
  au lieu d'être écrasé ;
- les mots de passe lus dans `wp-config.php` / `.env` ne sont jamais affichés
  (`[présent]` / `[absent]`), et les journaux passent par un filtre de masquage ;
- les entrées du workflow sont validées côté runner (liste blanche pour l'action,
  caractères autorisés pour le domaine et le chemin, `..` interdit).

## 5. Résultat de l'inventaire (4 août 2026)

### Ce qui tourne

**Visermart**, un e-commerce Laravel vendu clé en main (ViserLab) : `index.php` de
façade au docroot, application dans `core/`, thème `basic` dans
`core/resources/views/templates`. PHP 8.3.31, `APP_ENV=production`,
`APP_DEBUG=false`, HTTP 200, certificat Let's Encrypt valable jusqu'au
21 octobre 2026. 119 Mo sur disque. Le compte n'héberge que ce domaine.

### La boutique est vide

Base de 59 tables, 1,5 Mo. `products`, `categories`, `brands`, `orders`, `users`,
`carts` : **0 ligne**. 1 compte admin, 32 passerelles de paiement disponibles mais
**aucune configurée** (`gateway_currencies` vide), 1 langue, 58 sections de contenu
front (`frontends`), 7 pages, 19 modèles de notification.

### Ce que le schéma permet déjà, sans développement

- **Variantes** : `attributes`, `attribute_values`, `attribute_product`,
  `product_variants`, `variant_media` → taille et couleur par produit, avec photos
  par variante. C'est ce dont DJOUNES a besoin pour les scrubs, sous-vêtements,
  chaussettes et headbands — et c'est précisément ce qui manque au module STORE de
  TAGTOA. **Conclusion : garder Visermart pour la boutique, ne pas la reconstruire
  sur TAGTOA.**
- Marques, catégories, collections, types de produits
- Stock avec journal (`stock_logs`), livraison (`shipping_methods`, `shipping_addresses`)
- Coupons, offres, bannières promotionnelles
- Avis clients avec photos et réponses, wishlist, comparateur
- Portefeuille client (`deposits`), tickets de support, newsletter

### Sécurité

- `install/` était toujours présent dans le docroot — un installeur accessible
  permet souvent de reconfigurer le site et sa base sans être connecté. **Neutralisé
  le 4 août 2026** → `install.desactive-20260804183724` (renommé, pas supprimé ;
  `mv` inverse si besoin).
- `.env` est sous le docroot mais protégé par `<Files .env> Deny from all` dans le
  `.htaccess` : correct.
- `APP_URL=https://djounes.com/` a un slash final, ce qui produit des URLs à double
  slash — à corriger.

## 6. Mise à la marque DJOUNES — plan

8 lignes de produits : scrubs infirmières, chaussettes de compression, tote bag,
whipped body butter, oil, savon, headband, sous-vêtements.

| Étape | Contenu | Où |
|---|---|---|
| 1. Sécurité | installeur neutralisé ✅ · changer le mot de passe admin · corriger `APP_URL` | SSH / panneau |
| 2. Identité | logo, favicon, couleurs, bannière d'accueil, « À propos », pied de page, réseaux sociaux, 7 pages statiques | admin (`frontends`, `general_settings`) |
| 3. Catalogue | 3 catégories : **Vêtements médicaux** (scrubs, sous-vêtements, chaussettes de compression, headband) · **Soins du corps** (body butter, oil, savon) · **Accessoires** (tote bag) | admin |
| 4. Variantes | Taille XS→3XL et Couleur pour les vêtements · mmHg pour les chaussettes · format (2/4/8 oz) et parfum pour les soins | admin (`attributes`) |
| 5. Paiement & livraison | activer les passerelles utiles parmi les 32, définir les méthodes de livraison | admin |
| 6. Contenu | photos, descriptions, prix, stock par variante | admin |

Répartition : les étapes en admin demandent le compte administrateur du site (à ne
pas partager). Le travail sur les fichiers — thème, `.env`, sécurité, performance,
sauvegardes — passe par SSH et ce dépôt.
