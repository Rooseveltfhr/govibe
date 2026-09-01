# molesaintnicolas.com — Déploiement (DirectAdmin, hébergement du client)

⚠️ Cet hébergement est **personnel/client**, indépendant de l'infra GOVIBE. Ne jamais réutiliser
les secrets `VPS_*` de TAGTOA/GOVIBE ici — ce module a son propre jeu de secrets, préfixé `MSN_`.

## Pré-requis côté serveur (à vérifier une fois, dans le panneau DirectAdmin)

1. **PHP 8.3+** sélectionné pour le domaine (sélecteur PHP DirectAdmin).
2. **Une base MySQL** créée pour l'app, avec un utilisateur dédié.
3. **Accès SSH** activé pour le compte (souvent un port différent du panneau `:2222` — à
   confirmer auprès de l'hébergeur/support DirectAdmin ; `2222` est le panneau web, pas SSH).
4. Le **docroot du domaine** doit pointer vers `<BASE>/current/public` — soit en configurant le
   docroot directement sur ce chemin (le plus propre), soit via un symlink `public_html ->
   current/public` si DirectAdmin impose `public_html` comme racine.

## Structure sur le serveur

```
<BASE>/                     # ex. /home/molesain/molesaintnicolas
├── releases/<timestamp>/   # une release par déploiement (créée par la CI)
├── shared/
│   ├── .env                # créé UNE FOIS à la main sur le serveur, jamais dans le repo
│   └── storage/            # logs, uploads, sessions — persiste entre releases
└── current -> releases/<dernière-release-verte>
```

## Secrets GitHub à configurer (Settings → Secrets and variables → Actions)

| Secret | Valeur |
|---|---|
| `MSN_VPS_HOST` | `vda3700.is.cc` (ou l'IP fournie par l'hébergeur pour SSH) |
| `MSN_VPS_PORT` | port SSH réel (à confirmer — probablement pas `2222`, qui est le panneau DirectAdmin) |
| `MSN_VPS_USER` | utilisateur SSH (`molesain` ou équivalent) |
| `MSN_VPS_APP_PATH` | `<BASE>` tel que défini ci-dessus |
| `MSN_VPS_SSH_KEY` | clé privée dédiée au déploiement (voir génération ci-dessous) |

## Générer la clé de déploiement (une fois, sur le serveur)

```bash
ssh-keygen -t ed25519 -f ~/.ssh/msn_deploy -N "" -C "molesaintnicolas-ci"
cat ~/.ssh/msn_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
cat ~/.ssh/msn_deploy   # coller tout le contenu dans le secret MSN_VPS_SSH_KEY
```

## Premier déploiement (setup manuel, une seule fois)

```bash
mkdir -p <BASE>/releases <BASE>/shared/storage/{app,framework,logs}
mkdir -p <BASE>/shared/storage/framework/{cache,sessions,testing,views}
cp Modules/MoleSaintNicolas/.env.example <BASE>/shared/.env
# éditer <BASE>/shared/.env : APP_ENV=production, APP_DEBUG=false, DB_*, APP_URL=https://molesaintnicolas.com
php artisan key:generate --env=production   # ou générer une clé et la coller dans shared/.env
```

Ensuite, chaque push sur `main` touchant `Modules/MoleSaintNicolas/**` (ou un déclenchement manuel
de l'action *Deploy molesaintnicolas.com*) construit l'app, l'envoie dans une nouvelle release, migre,
fait un smoke test, et ne bascule `current` que si tout est vert — voir `remote-deploy.sh`.

## Sécurité

- Le mot de passe du panneau DirectAdmin ne doit **jamais** être utilisé pour le déploiement — seule
  la clé SSH dédiée ci-dessus doit avoir ce rôle, révocable en retirant la ligne de `authorized_keys`.
- `.env` de production ne vit que dans `shared/.env` sur le serveur, jamais dans le repo ni dans les
  logs CI.
