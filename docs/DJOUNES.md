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

### Générer la clé (sur ta machine, une seule fois)

```bash
ssh-keygen -t ed25519 -C "github-actions-djounes" -f ~/.ssh/djounes_deploy
```

1. Dans DirectAdmin : **Advanced Features → SSH Keys → Add Key**, colle le
   contenu de `~/.ssh/djounes_deploy.pub` (la clé **publique**), coche
   l'autorisation d'accès.
2. Dans GitHub : colle le contenu de `~/.ssh/djounes_deploy` (la clé **privée**,
   sans la partager ailleurs) dans le secret `DJOUNES_SSH_KEY`.

**Ne colle jamais un mot de passe, une clé ou un identifiant dans un message, une
issue ou une PR** — uniquement dans les secrets GitHub.

### Si l'hébergement ne propose pas SSH

Certaines formules mutualisées désactivent SSH. Dans ce cas, deux replis :

- activer l'accès SSH depuis DirectAdmin si l'option existe (souvent sur demande
  au support de l'hébergeur) ;
- sinon, exporter à la main ce qu'il faut : dans DirectAdmin, **File Manager**
  (arborescence de `public_html`), **MySQL Management** (nom des bases), et coller
  ici la liste. Sans SSH, l'automatisation n'est pas possible, mais le diagnostic
  et les correctifs de code le restent.

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
