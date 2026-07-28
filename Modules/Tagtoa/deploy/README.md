# TAGTOA — Auto-déploiement CI/CD (GitHub Actions → VPS)

À chaque déclenchement, GitHub Actions synchronise `Modules/Tagtoa` sur le VPS
(rsync via SSH) puis exécute `deploy/remote-deploy.sh` (déploiement SÛR :
maintenance → autoload → migrate → smoke test → up, avec rollback automatique).

## Déclenchement
- **Manuel** : onglet *Actions* → *Deploy TAGTOA to VPS* → *Run workflow* (recommandé).
- **Auto** : push sur `main` touchant `Modules/Tagtoa/**`.

## Configuration UNIQUE (GitHub → Settings → Secrets and variables → Actions)
Ajouter ces *Repository secrets* :

| Secret | Valeur | Exemple |
|--------|--------|---------|
| `VPS_HOST` | IP ou hôte du serveur | `198.51.100.10` |
| `VPS_USER` | utilisateur SSH | `admin` |
| `VPS_APP_PATH` | racine de l'app Biztap | `/home/admin/domains/tagtoa.com/public_html/tapbiz` |
| `VPS_SSH_KEY` | clé privée de déploiement (contenu complet) | `-----BEGIN OPENSSH PRIVATE KEY----- …` |
| `VPS_PORT` | (optionnel) port SSH | `22` |

## Générer la clé de déploiement (une fois)
Sur le VPS (utilisateur `admin`) :
```bash
ssh-keygen -t ed25519 -f ~/.ssh/tagtoa_deploy -N "" -C "tagtoa-ci"
cat ~/.ssh/tagtoa_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
echo "----- COPIER CECI dans le secret VPS_SSH_KEY : -----"
cat ~/.ssh/tagtoa_deploy
```
Coller la **clé privée** (`tagtoa_deploy`, tout le contenu) dans `VPS_SSH_KEY`.

## ⚠️ Pare-feu (DirectAdmin / CSF)
Le serveur utilise CSF (blocage des connexions échouées). L'auth par **clé**
fonctionne normalement, mais si les runners GitHub sont bloqués, autoriser les
plages d'IP GitHub Actions, ou déployer depuis un runner self-hosted / une IP fixe.

## Sécurité
- La clé privée vit uniquement dans les secrets GitHub (chiffrés), jamais dans le repo.
- Utiliser une clé **dédiée** au déploiement (révocable en retirant la ligne de
  `authorized_keys`).
- `remote-deploy.sh` ne stocke aucun secret ; il lit les identifiants DB depuis le
  `.env` existant de Biztap.

## Test manuel (sans CI)
Le script marche aussi à la main sur le VPS :
```bash
bash /home/admin/domains/tagtoa.com/public_html/tapbiz/Modules/Tagtoa/deploy/remote-deploy.sh \
     /home/admin/domains/tagtoa.com/public_html/tapbiz
```
