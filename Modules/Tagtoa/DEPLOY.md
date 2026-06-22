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
