# GOVIBE Innovation Hub

Plateforme complète **GOVIBE Innovation Hub** — Academy + ERP — construite avec Laravel 12.

## Fonctionnalités

### GOVIBE Academy (public)
- Formulaire d'inscription aux formations
- Numéro d'inscription automatique (GVB-2026-XXXX)
- QR Code par participant
- Notifications email (participant + admin)
- Redirect WhatsApp après inscription
- Tableau de bord admin : stats, export Excel/CSV, PDF, impression

### GOVIBE ERP (privé)
- **CRM** — Clients, prospects, pipeline
- **Projets** — Kanban, gestion des tâches, progression
- **Finance** — Factures, devis, paiements
- **RH** — Employés, congés, présences
- **POS** — Caisse (espèces, Moncash, Natcash, virement, PayPal)
- **Académie** — Lié aux formations publiques
- **Services** — Catalogue des 14 unités business
- **Rapports** — Revenus, analyses, graphiques
- **Super Admin** — Utilisateurs, rôles, unités business

## Tech Stack

- **Backend**: Laravel 12, PHP 8.3+
- **DB**: MySQL (prod) / SQLite (dev)
- **Frontend**: Tailwind CSS, AlpineJS, Bootstrap Icons, Chart.js
- **Packages**: maatwebsite/excel, barryvdh/laravel-dompdf, simplesoftwareio/simple-qrcode, spatie/laravel-permission

## Installation locale

```bash
git clone https://github.com/Rooseveltfhr/govibe.git
cd govibe
composer install
cp .env.example .env
# Editer .env: DB_CONNECTION=sqlite (dev) ou mysql (prod)
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=ERPSeeder
php artisan serve
```

**Compte admin:**
- Email: `govibeht@gmail.com`
- Mot de passe: `admin@govibe2024`

## URLs

| Environnement | URL |
|---|---|
| Inscription Academy | `/inscription` |
| Admin Academy | `/admin/login` |
| ERP | `/erp/login` |

## Déploiement VPS (govibeht.com)

```bash
# Sur le VPS (Ubuntu 22.04+), en root:
git clone https://github.com/Rooseveltfhr/govibe.git /var/www/govibe
cd /var/www/govibe
bash deploy.sh
```

Le script `deploy.sh` installe automatiquement:
- PHP 8.3-FPM, Nginx, MySQL
- Composer dependencies
- Migrations + seeders
- Nginx virtual host pour govibeht.com
- Certificat SSL Let's Encrypt
- Systemd worker pour les queues

### Publier une mise à jour

`deploy.sh` sert **uniquement à la première installation** (il provisionne le
serveur, demande un mot de passe MySQL et relance les seeders). Pour publier de
nouveaux commits sur un serveur déjà en place:

```bash
# Sur le VPS, en root:
cd /var/www/govibe
git fetch origin main && git checkout main && git pull origin main
bash update.sh
```

`update.sh` récupère la branche, installe les dépendances, applique les
migrations, reconstruit les caches Laravel, restaure les permissions et
redémarre Nginx / PHP-FPM / le worker — le tout derrière `php artisan down`.

### Déploiement automatique

Le workflow `.github/workflows/deploy-govibe.yml` exécute ces étapes tout seul
à chaque push sur `main` (ou à la demande, via *Actions → Deploy GOVIBE to VPS
→ Run workflow*).

Il réutilise les secrets `VPS_HOST` / `VPS_USER` / `VPS_SSH_KEY` / `VPS_PORT`.
Si govibeht.com vit sur une autre machine que tagtoa.com, définir à la place
`GOVIBE_VPS_HOST` / `GOVIBE_VPS_USER` / `GOVIBE_VPS_SSH_KEY` /
`GOVIBE_VPS_PORT` — ils ont priorité. `GOVIBE_APP_DIR` permet de surcharger
`/var/www/govibe`.

Avant toute modification, le workflow vérifie que le répertoire cible est bien
le dépôt `govibe`; si les secrets pointent ailleurs, il s'arrête sans rien
toucher. Il termine par un contrôle HTTP sur https://govibeht.com/.

> Ne pas confondre avec `deploy.yml`, qui déploie **le module TAGTOA** sur
> tagtoa.com et ne se déclenche que sur `Modules/Tagtoa/**`.

### Configuration email (Gmail)

Dans `/var/www/govibe/.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=govibeht@gmail.com
MAIL_PASSWORD=votre_app_password_gmail
```

> Créer un "App Password" dans Sécurité Google (2FA requis).

## Structure des routes

```
/                       → redirect vers /inscription
/inscription            → formulaire public Academy
/admin/*                → tableau de bord Academy admin
/erp/*                  → ERP complet
/erp/login              → connexion ERP
```
