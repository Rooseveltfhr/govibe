# molesaintnicolas.com

Plateforme touristique, culturelle et territoriale de Môle-Saint-Nicolas, Haïti.
Projet **client**, hébergé indépendamment de l'infrastructure GOVIBE.

> Architecture, roadmap et décisions produit : voir `docs/molesaintnicolas/00-PLANIFICATION-STRATEGIQUE.md`
> à la racine du repo.

## Stack

Laravel 13 / PHP 8.3, Livewire 4, Alpine.js, Tailwind CSS 4, MySQL (prod) / SQLite (dev),
spatie/laravel-permission pour les rôles.

## Installation locale

```bash
cd Modules/MoleSaintNicolas
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed          # crée les rôles + le compte super_admin (voir sortie console pour le mot de passe)
npm install && npm run build # ou `npm run dev` en développement
php artisan serve
```

## État

**Phase 1 — Fondation**
- [x] Squelette Laravel indépendant
- [x] Rôles (`super_admin`, `admin`, `editor`, `moderator`, `partner`) via spatie/laravel-permission
- [x] Authentification admin (`/admin/login`) + dashboard protégé par rôle (`/admin`)
- [x] Design system de base (palette mer/patrimoine, Tailwind, layouts public/admin)
- [x] Homepage avec structure des sections prévues par le brief (contenu à venir phase par phase)

**Phase 2 — Territoire (en cours)**
- [x] Modèles + migrations : `Department` → `Arrondissement` → `Commune` → `SectionCommunale` → `Localite`
- [x] Statut de contenu (`verified`/`submitted`/`needs_review`) avec traçabilité (`created_by`, `verified_by`, `verified_at`)
- [x] CRUD admin (Communes, Sections communales) — `/admin/territoire/{communes,sections}`
- [x] Pages publiques dynamiques `/territoire`, `/territoire/{commune}`, `/territoire/{commune}/{section}`
- [x] Données réelles seedées (`TerritorySeeder`) : structure administrative sourcée (Wikipedia), marquée `needs_review` — à vérifier sur place avant de la marquer "vérifiée"
- [ ] Histoire, Sites historiques, Centre-ville, Carte interactive → reste de la Phase 2
- [ ] Hôtels, Restaurants, Explorer → Phase 3
- [ ] Booking → Phase 4
- [ ] Blog/News, Événements, Galerie → Phase 5

## Tests

```bash
php artisan test
```

## Déploiement

Voir `deploy/README.md` — hébergement DirectAdmin du client, déploiement via GitHub Actions
(`.github/workflows/deploy-molesaintnicolas.yml`, déclenché sur `Modules/MoleSaintNicolas/**`).
