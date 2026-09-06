# molesaintnicolas.com — Déploiement (DirectAdmin, hébergement du client)

⚠️ Cet hébergement est **personnel/client**, indépendant de l'infra GOVIBE. Ne jamais réutiliser
les secrets `VPS_*` de TAGTOA/GOVIBE ici — ce module a son propre jeu de secrets, préfixé `MSN_`.

## Pré-requis côté serveur (à vérifier une fois, dans le panneau DirectAdmin)

1. **PHP 8.4+** sélectionné pour le domaine (sélecteur PHP DirectAdmin — Laravel 13/Symfony 8 l'exigent réellement, malgré la borne `^8.3` qui apparaissait dans composer.json avant vérification du lock file).
2. **Une base MySQL** créée pour l'app, avec un utilisateur dédié.
3. **Accès SSH** activé pour le compte (souvent un port différent du panneau `:2222` — à
   confirmer auprès de l'hébergeur/support DirectAdmin ; `2222` est le panneau web, pas SSH).
4. `public_html` du domaine doit être un **symlink vers `app/public`** (setup manuel une fois,
   voir plus bas) — DirectAdmin impose `public_html` comme racine, on ne peut pas la déplacer.

## Structure sur le serveur (volontairement simple — pas de releases/rollback)

```
<BASE>/                     # ex. /home/molesain/domains/molesaintnicolas.com
├── app/                    # tout le code Laravel — rsyncé par la CI à chaque déploiement
│   ├── .env                # créé UNE FOIS à la main sur le serveur, jamais dans le repo,
│   │                       # jamais écrasé par la CI (exclu du rsync)
│   └── storage/            # logs, uploads, sessions — persiste entre déploiements (exclu du rsync)
└── public_html -> app/public   # symlink, créé une fois à la main
```

Pas de dossier `releases/` horodaté ni de bascule atomique : pour un site à faible trafic sur
hébergement mutualisé, ce modèle plus simple (une CI red = le prochain push corrige, pas de
rollback en un clic) réduit nettement le nombre d'étapes de mise en place — ce qui comptait plus
ici que le zéro-downtime.

## Secrets GitHub à configurer (Settings → Secrets and variables → Actions)

| Secret | Valeur |
|---|---|
| `MSN_VPS_HOST` | `vda3700.is.cc` (ou l'IP fournie par l'hébergeur pour SSH) |
| `MSN_VPS_PORT` | port SSH réel (à confirmer — probablement pas `2222`, qui est le panneau DirectAdmin) |
| `MSN_VPS_USER` | utilisateur SSH (`molesain` ou équivalent) |
| `MSN_VPS_APP_PATH` | `<BASE>` tel que défini ci-dessus (le parent de `app/`, PAS `app/` lui-même) |
| `MSN_VPS_SSH_KEY` | clé privée dédiée, encodée en **base64 sur une seule ligne** (voir génération ci-dessous) |

## Générer la clé de déploiement (une fois, sur le serveur)

```bash
ssh-keygen -t ed25519 -f ~/.ssh/msn_deploy -N "" -C "molesaintnicolas-ci"
cat ~/.ssh/msn_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
base64 -w0 ~/.ssh/msn_deploy   # coller CETTE ligne (une seule) dans le secret MSN_VPS_SSH_KEY
```

⚠️ Ne pas coller le bloc `-----BEGIN OPENSSH PRIVATE KEY-----...` multi-lignes tel quel dans
le secret : un copier-coller depuis un mobile corrompt facilement les sauts de ligne/tirets,
ce qui casse la clé côté runner (`Load key: error in libcrypto`). Le base64 sur une ligne est
purement alphanumérique — bien plus robuste au copier-coller. Le workflow décode
automatiquement (`base64 -d`) avant utilisation.

## Premier déploiement (setup manuel, une seule fois)

```bash
BASE=/home/molesain/domains/molesaintnicolas.com   # = MSN_VPS_APP_PATH

# 1) public_html -> app/public (app/ n'existe pas encore, le symlink devient
#    valide dès le premier déploiement CI qui crée app/public)
rm -rf "$BASE/public_html"
ln -s "$BASE/app/public" "$BASE/public_html"

# 2) Config de production — app/ n'existe pas encore non plus, on le crée ici
mkdir -p "$BASE/app"
cat > "$BASE/app/.env" <<'ENV'
APP_NAME="Môle-Saint-Nicolas"
APP_ENV=production
APP_KEY=                          # générer : php -r "echo 'base64:'.base64_encode(random_bytes(32));"
APP_DEBUG=false
APP_URL=https://molesaintnicolas.com
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
MSN_ADMIN_EMAIL=admin@molesaintnicolas.com
MSN_ADMIN_PASSWORD=               # choisir un mot de passe fort
LOG_CHANNEL=stack
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=                      # nom de la base créée dans DirectAdmin
DB_USERNAME=
DB_PASSWORD=
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
ENV
```

Ensuite, chaque push sur `main` touchant `Modules/MoleSaintNicolas/**` (ou un déclenchement manuel
de l'action *Deploy molesaintnicolas.com*) construit l'app, la rsync vers `app/`, migre, régénère
les caches et fait un smoke test — voir `remote-deploy.sh`.

Après le tout premier déploiement réussi, se connecter une fois en SSH et lancer
`cd app && php artisan db:seed --force` pour créer les rôles et le compte admin.

## Sécurité

- Le mot de passe du panneau DirectAdmin ne doit **jamais** être utilisé pour le déploiement — seule
  la clé SSH dédiée ci-dessus doit avoir ce rôle, révocable en retirant la ligne de `authorized_keys`.
- `.env` de production ne vit que dans `app/.env` sur le serveur, jamais dans le repo ni dans les
  logs CI.
