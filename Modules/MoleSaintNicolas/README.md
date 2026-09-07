# molesaintnicolas.com

Plateforme touristique, culturelle et territoriale de Môle-Saint-Nicolas, Haïti.
Projet **client**, hébergé indépendamment de l'infrastructure GOVIBE.

> Architecture, roadmap et décisions produit : voir `docs/molesaintnicolas/00-PLANIFICATION-STRATEGIQUE.md`
> à la racine du repo.

## Stack

Laravel 13 / PHP 8.4, Livewire 4, Alpine.js, Tailwind CSS 4, MySQL (prod) / SQLite (dev),
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
- [x] Navigation complète (menu desktop + menu mobile Alpine.js) et footer professionnel (navigation, informations, contact, mentions légales)
- [x] Pages statiques CMS (`Page` : à propos, mentions légales) — `/a-propos`, `/mentions-legales`, CRUD `/admin/pages`
- [x] Homepage avec structure des sections prévues par le brief (contenu à venir phase par phase)
- [x] Déploiement en production (DirectAdmin, CI GitHub Actions — voir `deploy/README.md`)

**Phase 2 — Territoire & Histoire**
- [x] Modèles + migrations : `Department` → `Arrondissement` → `Commune` → `SectionCommunale` → `Localite`
- [x] Statut de contenu (`verified`/`submitted`/`needs_review`) avec traçabilité (`created_by`, `verified_by`, `verified_at`)
- [x] CRUD admin (Communes, Sections communales) — `/admin/territoire/{communes,sections}`
- [x] Pages publiques dynamiques `/territoire`, `/territoire/{commune}`, `/territoire/{commune}/{section}`
- [x] Histoire : périodes, événements, personnages (`/histoire`) + CRUD admin
- [x] Lieux historiques (`HistoricalSite`) : liste + fiche détaillée avec carte, CRUD admin — `/lieux-historiques`
- [x] Carte interactive (Leaflet + OpenStreetMap) — `/carte`, agrège communes/lieux historiques/établissements géolocalisés
- [x] Données réelles seedées (`TerritorySeeder`, `HistorySeeder`, `HistoricalSiteSeeder`) : sourcées, marquées `needs_review` — à vérifier sur place avant de marquer "vérifié"
- [x] Centre-ville (`/centre-ville`) : page dédiée réutilisant le modèle `Page`, avec carte si la commune a des coordonnées

**Phase 2 terminée.**

**Phase 3 — Tourisme**
- [x] Hôtels/Restaurants/Bars (`Establishment`) : listes `/hotels`, `/restaurants`, `/etablissements` (vue unifiée) + fiches détaillées, CRUD admin
- [x] Demandes de réservation visiteur → notification admin par email (`Booking`, sans paiement en ligne — conforme au MVP)
- [x] Explorer / Activités (`Activity`) : liste + fiche, contact direct (téléphone/WhatsApp, lien `wa.me`), sans réservation en ligne — `/explorer`

**Phase 3 terminée.**

**Phase 5 — Contenu**
- [x] Actualités (`Post`) : liste + article, publication différée (`published_at`), CRUD admin — `/actualites`
- [x] Événements (`Event`) : agenda public/passé, CRUD admin — `/evenements`
- [x] Galerie photos (`Photo`) : upload admin réel (validation type/taille, `storage:link`), filtrable par catégorie — `/galerie`

**Phase 5 terminée.**

**Hors plan initial (ajouté à la demande du client)**
- [x] Projets communautaires (`CommunityProject`) + avis visiteurs modérés avant publication — `/projets`
- [x] Espace admin "Mon compte" (changement de mot de passe), throttle login, cookie de session sécurisé en production, en-têtes de sécurité de base
- [x] Page "À propos" et page "Contact" (`ContactMessage`, formulaire → notification email admin) rattachées au menu principal — `/a-propos`, `/contact`, CRUD lecture/suppression `/admin/messages`

**À noter**
- Aucune vraie photo n'est encore hébergée pour les entités sans upload dédié (établissements, sites historiques, activités) :
  un composant `<x-photo-placeholder>` (SVG, pas de dépendance externe) marque visuellement chaque emplacement en attendant
  que l'admin ajoute les images réelles — jamais une photo générique présentée comme si elle montrait le lieu réel. La
  galerie, elle, accepte déjà de vraies photos via Admin → Galerie.
- Reste hors MVP (Phase 6, brief §14) : espace partenaires en libre-service, avis utilisateurs sur les établissements,
  multilingue actif, paiement en ligne.

## Tests

```bash
php artisan test
```

## Déploiement

Voir `deploy/README.md` — hébergement DirectAdmin du client, déploiement via GitHub Actions
(`.github/workflows/deploy-molesaintnicolas.yml`, déclenché sur `Modules/MoleSaintNicolas/**`).
