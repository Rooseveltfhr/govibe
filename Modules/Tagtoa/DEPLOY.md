# TAGTOA — Runbook de déploiement sur Biztap (VPS)

Procédure copier-coller pour Termius (mobile) / SSH. Le module n'altère aucune
table existante (tout en `tagtoa_*`).

> ⚠️ **Production = site vivant** (clients vcard payants). Voir `RISKS.md`.
> Toujours suivre la **procédure SÛRE** ci-dessous, jamais un déploiement « brut ».

---

## ⭐ Déploiement SÛR (production) — à utiliser
Neutralise le risque #2 (casser la prod). `APP=...tapbiz` = racine de l'app.

```bash
APP=/home/admin/domains/tagtoa.com/public_html/tapbiz
cd "$APP"

# 1) Sauvegarde DB (filet de sécurité) — voir §1 plus bas pour les identifiants
#    (ex. via DirectAdmin, ou mysqldump)

# 2) Mode maintenance (le site affiche une page d'attente, pas d'erreur 500)
php artisan down --render="errors::503" || php artisan down

# 3) Récupérer le module (git clone/pull ou rsync) — voir §2

# 4) Autoload déterministe (enregistre le PSR-4 Tagtoa puis dump)
php composer.phar dump-autoload -o 2>&1 | tail -3

# 5) Activer + migrer
php artisan module:enable Tagtoa
php artisan migrate --force

# 6) SMOKE TEST (doit réussir avant de rouvrir)
php artisan package:discover >/dev/null 2>&1 && echo "providers OK" || { echo "ECHEC providers"; }
php artisan route:list 2>/dev/null | grep -q tagtoa && echo "routes OK" || echo "ECHEC routes"

# 7) Caches + réouverture
php artisan optimize:clear
php artisan up
```

**Rollback immédiat si le smoke test échoue (sans rouvrir cassé) :**
```bash
php -r '$f="modules_statuses.json";$j=json_decode(file_get_contents($f),true);$j["Tagtoa"]=false;file_put_contents($f,json_encode($j,JSON_PRETTY_PRINT));'
php artisan migrate:rollback --path=Modules/Tagtoa/Database/migrations --force
php artisan optimize:clear && php artisan up
```

---

## 0. Pré-requis (déjà OK sur Biztap)
Laravel 10.18, `nwidart/laravel-modules`, `simple-qrcode`, `stancl/tenancy`,
`spatie/permission`, `app/helpers.php`. Rien à installer.

## 1. Sauvegarde (toujours avant un déploiement)

**Automatique (BK-1a).** `deploy/remote-deploy.sh` lance `php artisan tagtoa:backup-db` à
**CHAQUE déploiement**, AVANT la mise en maintenance (et pas seulement quand une migration est
en attente : un bug qui abîme des données sans migration n'avait aucune sauvegarde récente).
Le dump est compressé, vérifié (archive lisible + pied de page du dump présent), écrit en
`0600` dans `storage/app/backups/` (dossier `0700`).

**Rétention par niveaux** : les 10 dernières, PLUS la plus récente de chaque jour sur ≈ 15
jours, PLUS la plus récente de chaque semaine sur ≈ 9 semaines. Le reste est supprimé ; un
fichier nommé à la main n'est jamais compté ni supprimé.

**Vérification** : juste après, `php artisan tagtoa:backup-verify` relit la dernière sauvegarde
(lecture seule, jamais bloquant) : archive complète, **chaque table de la base présente dans le
dump**, taille cohérente avec la précédente, **fraîcheur** (elle doit dater du déploiement en cours, sinon alerte), place prise. Un dump orphelin (`.partial`) laissé par un processus tué est nettoyé au passage. Le dump n'inclut pas les `EVENT` MySQL. C'est un contrôle du FICHIER, pas une
restauration : la restauration réelle (vrai `mysqldump` → vraie base MySQL 8) est prouvée à
chaque PR par le job CI « migrations + sauvegarde ».

| Variable | Effet |
|---|---|
| `TAGTOA_BACKUP_REQUIRED=1` | Si la sauvegarde échoue, le déploiement s'arrête AVANT la maintenance (site intact). Par défaut : avertissement bruyant puis poursuite. |
| `TAGTOA_BACKUP_SKIP=1` | Ignore la sauvegarde (et sa vérification). |
| `TAGTOA_BACKUP_IF_PENDING=1` | Ancien comportement : sauvegarde seulement si une migration est en attente. |
| `TAGTOA_BACKUP_KEEP=N` | Dernières sauvegardes toujours conservées (défaut 10). |
| `TAGTOA_BACKUP_KEEP_DAILY=N` / `TAGTOA_BACKUP_KEEP_WEEKLY=N` | Jours / semaines couverts par « une par jour » / « une par semaine » (défaut 14 / 8). |
| `TAGTOA_BACKUP_TIMEOUT=S` | Durée maximale du dump, en secondes (défaut 300) : un dump qui pend ne bloque pas le déploiement. |
| `TAGTOA_SEED_DEMO=1` | Réactive le seed de démo (désormais DÉSACTIVÉ par défaut en production). |

**Images téléversées (BK-1c).** Le dump ne contient PAS les photos (menu, boutique), QR et justificatifs de paiement :
`php artisan tagtoa:backup-uploads` les archive à chaque déploiement (`<disque public>/tagtoa` → `tagtoa-uploads-AAAAMMJJ-HHMMSS.tar.gz`,
`0600`, relu jusqu'au bout avant d'être renommé). Les liens symboliques ne sont jamais suivis. Si rien n'a changé depuis la dernière archive
(fichier ajouté, modifié ou supprimé), aucune nouvelle archive n'est faite. Plafond de taille et place disque vérifiés : au-delà, échec CLAIR
et aucune archive. Rétention par niveaux : 5 dernières + 1 par jour (≈8 j) + 1 par semaine (≈5 sem.).

| Variable | Effet |
|---|---|
| `TAGTOA_UPLOADS_BACKUP_SKIP=1` | Ignore la sauvegarde des images. |
| `TAGTOA_UPLOADS_MAX_MB=N` | Taille maximale des images à archiver, en Mo (défaut 2048). |
| `TAGTOA_UPLOADS_KEEP` / `_KEEP_DAILY` / `_KEEP_WEEKLY` | Rétention (défaut 5 / 7 / 4). |
| `TAGTOA_UPLOADS_TIMEOUT=S` | Durée maximale de l'archivage (défaut 300 s). |

Un échec est un avertissement bruyant (la base, elle, reste sauvegardée) ; attention : des images au-dessus de `TAGTOA_UPLOADS_MAX_MB` sont un échec, donc avec `TAGTOA_BACKUP_REQUIRED=1` elles bloquent TOUS les déploiements tant que le plafond n'est pas relevé. « Rien n'a changé » se juge sur la liste (chemin, taille, date) des fichiers, pas sur la date de l'archive. Un fichier téléversé pendant l'archivage peut être tronqué dans cette archive (avertissement affiché) ; avec `TAGTOA_BACKUP_REQUIRED=1` il annule le déploiement avant la maintenance.
**Restaurer les images** : `tar -xzf storage/app/backups/tagtoa-uploads-….tar.gz -C <racine du disque public>` (l'archive contient `tagtoa/…`).

⚠️ Le dump contient TOUT, secrets des passerelles compris. Il reste sur le même
serveur que la base : ce n'est pas une sauvegarde hors site (BK-1b : copie chiffrée
ailleurs) — et les archives d'images restent sur le même serveur aussi. Copiez-les
ailleurs en attendant.

**Restaurer** (le site en maintenance : `php artisan down`) :
```bash
gunzip -c storage/app/backups/tagtoa-db-AAAAMMJJ-HHMMSS.sql.gz | mysql -u <DB_USER> -p <DB_NAME>
php artisan optimize:clear && php artisan up
```
Le fichier restaure l'état d'AVANT la migration : toute vente enregistrée entre le
dump et la restauration est perdue — d'où la maintenance pendant la migration.

**À la main** (hors déploiement) :
```bash
cd /var/www/biztap
mysqldump -u <DB_USER> -p <DB_NAME> > ~/backup-$(date +%F-%H%M).sql
cp -r Modules ~/Modules-backup-$(date +%F-%H%M)   # au cas où
```

## 2. Récupérer le module
**Option A — git (recommandé)** : depuis la branche TAGTOA
```bash
cd /var/www/biztap
# copier le dossier Modules/Tagtoa depuis le dépôt govibe (branche claude/serene-brahmagupta-do562e)
# ex. via un clone temporaire :
git clone -b claude/serene-brahmagupta-do562e https://github.com/Rooseveltfhr/govibe.git /tmp/tagtoa-src
cp -r /tmp/tagtoa-src/Modules/Tagtoa /var/www/biztap/Modules/Tagtoa
```
**Option B — upload zip** : transférer `Modules/Tagtoa/` par SFTP dans `/var/www/biztap/Modules/`.

## 3. Activer + autoload
```bash
cd /var/www/biztap
composer dump-autoload
php artisan module:enable Tagtoa
php artisan module:list           # doit afficher Tagtoa = Enabled
```

## 4. Migrations (crée les tables tagtoa_*)
```bash
php artisan migrate --force
# vérifier :
php artisan migrate:status | grep tagtoa
```

## 5. Stockage public (QR, preuves, avatars, logos)
```bash
php artisan storage:link          # si pas déjà fait
```

## 6. Caches
```bash
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 7. Données de démo (optionnel — DEVEXPO)
```bash
php artisan db:seed --class="Modules\Tagtoa\Database\Seeders\TagtoaDemoSeeder"
```

## 8. Vérifications fonctionnelles
- Back-office : se connecter (rôle admin) → ouvrir **`/tagtoa`** (hub).
  - Créer une page Pay → ouvrir l'URL publique `/(pay)/{alias}` en navigation privée.
  - Émettre une carte Loyalty → ouvrir `/loyalty/card/{token}`.
  - Créer un événement publié → `/event/{alias}` → acheter un billet gratuit →
    scanner `/tagtoa/event/{id}/scanner`.
  - POS : créer une caisse, des produits, faire une vente.
- `php artisan route:list | grep tagtoa` → toutes les routes présentes.

## 9. Rollback (si besoin)
```bash
cd /var/www/biztap
php artisan module:disable Tagtoa
# annuler uniquement les migrations TAGTOA (elles ont des down()):
php artisan migrate:rollback --path=Modules/Tagtoa/Database/migrations --force
rm -rf Modules/Tagtoa
composer dump-autoload && php artisan optimize:clear
```

## Notes
- Si le groupe back-office diffère, ajuster le middleware dans
  `Modules/Tagtoa/routes/web.php` (`auth,valid.user,role:admin,multi_tenant`).
- HTTPS requis pour la caméra du scanner (Event) et le Web Audio (POS) sur mobile.
- Aucune table existante n'est modifiée ; rollback sans risque pour les données vcard.
