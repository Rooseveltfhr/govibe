# djounes.com — accès & maintenance

> Rezime an kreyòl: `djounes.com` sou yon **kont ebèjman apa** (pa VPS tagtoa a).
> Pou m ka travay sou li, mete 3 sekrè GitHub yo (§3). Anvan sa, mwen pa gen okenn
> aksè — panèl la mande yon login, e rezo sesyon an bloke domèn nan.

## 1. Ce qu'on sait (vérifié)

| Élément | Valeur |
|---|---|
| Domaine | `djounes.com` (et `www.djounes.com`) |
| IP du site | `173.225.109.155` |
| Panneau de contrôle | DirectAdmin — `https://vda8400.is.cc:2222` |
| IP du panneau | `173.225.109.154` (même bloc réseau que le site) |
| Stack du site | **inconnue** — indéterminable sans accès (voir §4) |

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
| `vider_cache` | `artisan optimize:clear` (Laravel) ou vidage de `wp-content/cache` (WordPress) | oui, le cache se régénère |
| `mettre_en_pause` | `public_html` → `public_html.paused` + page « Maintenance » | oui, via `reprendre_site` |
| `reprendre_site` | restaure `public_html.paused` | oui |

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

## 5. Ensuite

Une fois l'inventaire disponible, on saura ce qu'héberge réellement djounes.com et
on pourra décider de la suite : remise en état, refonte, migration vers le VPS
TAGTOA, ou déploiement continu depuis ce dépôt. **Ce document sera mis à jour avec
les résultats de l'inventaire.**
