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

**Copie hors site chiffrée (BK-1b).** Sans elle, un serveur perdu emporte les sauvegardes avec lui. `php artisan tagtoa:backup-offsite`
envoie la dernière sauvegarde de la base et la dernière archive d'images, **chiffrées**, vers un bucket S3-compatible (Cloudflare R2
d'abord ; Backblaze B2 / AWS S3 avec le même code). INACTIVE tant que les variables ci-dessous ne sont pas dans le `.env`.

*Principe de sécurité* : le serveur ne détient que la clé PUBLIQUE — il sait chiffrer, pas déchiffrer. La clé PRIVÉE reste chez vous.
Quiconque prend le serveur, son `.env` ou le bucket ne peut rien lire. **Clé privée perdue = copies hors site définitivement illisibles.**

*Mise en place (une fois)* :
1. **Sur votre poste** (pas sur le serveur) : `php artisan tagtoa:backup-keygen`. Gardez la clé PRIVÉE dans un gestionnaire de mots de passe
   + une copie papier. Copiez la clé PUBLIQUE.
2. **Cloudflare R2** : créez un bucket **privé** (jamais d'accès public). Créez un jeton API R2 limité à CE bucket, permission « Object Read & Write »
   (R2 n'offre pas de jeton « écriture seule »). Notez l'identifiant de compte, l'Access Key ID et le Secret.
3. **Protection contre l'effacement** : un serveur piraté avec ce jeton pourrait supprimer les copies. Activez dans le bucket une règle de
   **verrouillage (Bucket Lock / rétention)** de 30 jours, plus une règle de **cycle de vie** qui supprime les objets après 60 jours
   (vérifiez dans le tableau de bord que ces deux options existent pour votre compte). Sur B2, préférez une clé d'application SANS la
   capacité `deleteFiles`, avec Object Lock.
4. Dans le `.env` du serveur :

| Variable | Valeur |
|---|---|
| `TAGTOA_OFFSITE_ENDPOINT` | R2 : `https://<ID_COMPTE>.r2.cloudflarestorage.com` (https obligatoire, sans chemin) |
| `TAGTOA_OFFSITE_BUCKET` | nom du bucket |
| `TAGTOA_OFFSITE_KEY_ID` / `TAGTOA_OFFSITE_SECRET` | Access Key ID / Secret du jeton |
| `TAGTOA_OFFSITE_REGION` | `auto` pour R2 (défaut) ; B2 : la région de l'endpoint (ex. `us-west-004`) |
| `TAGTOA_OFFSITE_PUBLIC_KEY` | clé PUBLIQUE de l'étape 1 |
| `TAGTOA_OFFSITE_PREFIX` | dossier dans le bucket (défaut `tagtoa/`) |
| `TAGTOA_OFFSITE_SKIP=1` | ignore la copie hors site |
| `TAGTOA_OFFSITE_REQUIRED=1` | un échec annule le déploiement avant la maintenance (par défaut : avertissement bruyant) |

Au déploiement suivant, le journal affiche « Hors site OK : … ». Chaque fichier est relu : taille, et empreinte (ETag) quand le service renvoie
un MD5 simple (R2 : oui ; sur B2 ou S3 avec chiffrement serveur, seule la taille est comparable). Un fichier est considéré « déjà hors site »
seulement si le poids ET l'empreinte du contenu en clair (métadonnée `tagtoa-sha256` écrite à l'envoi) correspondent. La sauvegarde de la base
doit dater de moins de 30 min (celle de ce déploiement) : sinon rien d'ancien n'est présenté comme frais. Les réglages `TAGTOA_BACKUP_*`,
`TAGTOA_UPLOADS_*` et `TAGTOA_OFFSITE_*` sont relus dans le `.env` par le script de déploiement (une variable déjà présente dans le shell a priorité).

*Limites assumées* : le chiffrement protège la CONFIDENTIALITÉ, pas l'authenticité face à quelqu'un qui détient le jeton d'écriture et la clé publique
(il peut déposer un faux fichier ; sans clé privée il ne peut rien lire) — d'où le verrouillage du bucket. Les noms portent l'horodatage à la seconde ;
un envoi n'écrase en pratique jamais une copie précédente (deux déploiements dans la même seconde sont exclus par un verrou). Un envoi simple est limité
à 5 Go chez R2/B2 : bien au-delà du plafond `TAGTOA_UPLOADS_MAX_MB`. Ne lancez pas `tagtoa:backup-keygen` en CI (sa sortie contient la clé privée).

*Restaurer depuis le hors site* (sur votre poste, ou un serveur de secours) : téléchargez l'objet `.enc` depuis le bucket, puis
`TAGTOA_OFFSITE_PRIVATE_KEY='<clé privée>' php artisan tagtoa:backup-decrypt fichier.sql.gz.enc` (ou saisie masquée si la variable est absente ;
jamais la clé en argument). Un fichier modifié ou tronqué est refusé ; rien n'est écrasé. Puis suivez « Restaurer » ci-dessous.

⚠️ Non couvert : le premier envoi réel vers R2 se juge sur le journal du déploiement (le code est testé contre l'exemple officiel de signature AWS,
un faux bucket et un vrai serveur HTTP local, mais pas contre le vrai service).

⚠️ Le dump contient TOUT, secrets des passerelles compris. Sans la copie hors site ci-dessus, il reste sur le même
serveur que la base — et les archives d'images restent sur le même serveur aussi. Copiez-les
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
