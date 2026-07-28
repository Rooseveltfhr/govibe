# TAGTOA — Installation dans Biztap (nwidart/laravel-modules)

Module unique `Tagtoa` (Pay, Loyalty, Links, Event, POS, Billing) pour le SaaS
vcard **Biztap**. Code organisé par sous-dossiers de fonctionnalité ; routes,
migrations et vues s'enregistrent **automatiquement** (pas d'édition de
`routes/web.php`).

> Règle absolue respectée : aucune table existante modifiée. Toutes les tables
> sont préfixées `tagtoa_*`. Réutilise `Vcard`, `Plan`/`Subscription` et les
> helpers existants (`getLogInTenantId`, `getLogInUser`) via `App\Support\Tenant`.

## 1. Copier le module
```bash
cp -r Modules/Tagtoa /var/www/biztap/Modules/Tagtoa
```

## 2. Autoload + activation
```bash
cd /var/www/biztap
composer dump-autoload
php artisan module:enable Tagtoa     # si nécessaire
php artisan migrate                  # crée les tables tagtoa_*
php artisan storage:link             # QR / preuves / avatars (disk public)
php artisan optimize:clear
```

## 3. Accès
- Dashboard marchand : `/tagtoa` (sidebar : Paiements, Fidélité, Liens,
  Événements, Caisse, Revenu & forfait). Protégé par middleware `auth`.
- Pages publiques (NFC/QR, sans auth) :
  - `/(pay)/{alias}` · `/loyalty/card/{token}` · `/links/{alias}`
  - `/event/{alias}` · billets `/event/ticket/{code}`

## 4. Dépendances
- `simplesoftwareio/simple-qrcode` (déjà présent dans Biztap) — QR cartes/billets.
  Sinon fallback automatique vers une API QR externe.
- Stockage fichiers via le disk `public` (pas de medialibrary requis).

## 5. Modèle de revenu (2 options)
`/tagtoa/billing` : **Abonnement** (réutilise Plan/Subscription), **Commission**
(% + fixe sur EVENT/POS), ou **les deux**. La commission est prélevée
automatiquement à chaque commande EVENT payée et chaque vente POS.

## 6. Points à vérifier sur Biztap réel
1. `App\Models\Vcard` : relation `user()` (notif Pay) + colonnes `id`,`name`.
2. Helpers `getLogInTenantId()` / `getLogInUser()` présents (sinon `App\Support\Tenant`
   retombe sur `auth()`).
3. Middleware `auth` = celui du back-office Biztap (adapter dans `routes/web.php`
   si le groupe diffère).
4. `php artisan module:list` montre bien `Tagtoa` activé.

## Architecture interne
```
Modules/Tagtoa/
├── app/
│   ├── Http/Controllers/{Hub,Pay,Loyalty,Links,Event,Pos,Billing}/
│   ├── Models/{Pay,Loyalty,Links,Event,Pos,Billing}/
│   ├── Services/{Loyalty,Event,Pos,Billing}/
│   ├── Notifications/  ·  Support/Tenant.php
│   └── Providers/{TagtoaServiceProvider,RouteServiceProvider}.php
├── Database/migrations/        # toutes les tables tagtoa_*
├── resources/views/{layouts,hub,pay,loyalty,links,event,pos,billing}/
├── routes/{web.php,api.php}
├── config/config.php · module.json · composer.json
```
