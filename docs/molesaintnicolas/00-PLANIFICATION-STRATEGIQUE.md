# Molesaintnicolas.com — Phase 0 : Analyse stratégique & Architecture

> Statut : **EN ATTENTE DE VALIDATION**. Aucun code applicatif n'est développé tant que ce document n'est pas approuvé (voir CLAUDE.md — workflow obligatoire, §2). Ce fichier vit dans le repo GOVIBE pour rester la référence unique du projet au fil des phases.

## 0. Où ce projet vit dans le monorepo GOVIBE

Ce repo héberge déjà plusieurs produits **indépendants** sous un même toit, chacun avec son propre déploiement :

| Produit | Domaine | Emplacement | Déploiement |
|---|---|---|---|
| GOVIBE Academy + ERP | govibeht.com | racine du repo (`app/`, `routes/`) | `deploy.sh` / `deploy-govibe.yml`, VPS |
| TAGTOA | tagtoa.com | `Modules/Tagtoa/` (app Laravel autonome, son propre `composer.json`) | `Modules/Tagtoa/deploy/`, GitHub Actions ciblé sur `Modules/Tagtoa/**` |

**Décision proposée :** traiter *molesaintnicolas.com* comme un **troisième produit autonome**, sur le même modèle que TAGTOA :

```
Modules/MoleSaintNicolas/
├── composer.json          # app Laravel indépendante (pas nwidart/laravel-modules)
├── module.json
├── app/
├── database/migrations/
├── routes/{web,api}.php
├── resources/views/
├── deploy/remote-deploy.sh
└── .github/... (workflow dédié, path-filtré)
```

Pourquoi pas un module « branché » dans l'app principale (comme le suggère `Modules/` avec nwidart) : `Tagtoa` prouve que ce n'est **pas** le pattern réellement utilisé ici — chaque produit a son propre `composer.json`, ses propres dépendances, son propre cycle de déploiement, sur son propre nom de domaine et (ici) son propre hébergement mutualisé. Môle-Saint-Nicolas a une audience, un modèle de données et un rythme de release totalement différents de l'ERP ou de TAGTOA : le coupler au même déploiement serait un risque de régression croisée sans bénéfice.

**Hébergement fourni :** `vda3700.is.cc:2222` — le port `2222` est la signature de **DirectAdmin** (confirmé par `Modules/Tagtoa/deploy/README.md`, qui documente déjà un serveur DirectAdmin/CSF pour tagtoa.com). On réutilise donc telle quelle la recette de déploiement déjà validée sur TAGTOA (clé SSH dédiée → `authorized_keys`, secrets GitHub, script de déploiement sûr avec maintenance/rollback), adaptée à ce nouvel hébergement.

⚠️ **Sécurité immédiate :** le mot de passe DirectAdmin a été transmis en clair dans la conversation. Recommandation : le **changer** dès que l'accès est confirmé, puis ne plus jamais l'utiliser directement — on met en place une clé SSH dédiée (comme pour TAGTOA) et/ou l'accès Git de DirectAdmin, stockés uniquement dans les secrets GitHub Actions. Le mot de passe ne sera à aucun moment écrit dans le repo, un fichier `.env` commité, ou un log.

---

## 1. Analyse stratégique

**Constat :** Môle-Saint-Nicolas est un site à forte densité patrimoniale (premier point de contact de Christophe Colomb en Haïti, fort historique, position maritime stratégique) mais sans présence numérique structurée aujourd'hui. La demande couvre un spectre inhabituellement large pour un « site vitrine » : destination touristique, encyclopédie territoriale (arrondissement → sections communales), plateforme de réservation, et outil de développement économique local.

**Ce que ça implique architecturalement :**
- Le **contenu** (histoire, territoire, sites) est le cœur de valeur à long terme — c'est un CMS territorial avant d'être un site touristique. Il doit être conçu pour survivre à 10 ans de contributions, pas pour un lancement ponctuous.
- La **réservation** (booking) est un second système avec ses propres invariants (disponibilité, double-réservation, paiement) — il ne doit pas être bricolé à l'intérieur du CMS de contenu.
- Le volume de contenu réel au lancement sera **faible** (peu de zones/sites déjà documentés) : le risque n°1 n'est pas technique, c'est l'absence de contenu vérifié. Le MVP doit rendre visible qu'une fiche est incomplète (`[Information à compléter]`) plutôt que de bloquer la publication.

## 2. Proposition de valeur

| Cible | Valeur |
|---|---|
| Touriste / voyageur | Un point d'entrée unique fiable (histoire, hébergement, activités, réservation) là où l'information est aujourd'hui dispersée ou absente |
| Diaspora | Reconnexion culturelle et historique à leur commune/section d'origine |
| Chercheur / historien | Une base structurée, sourcée, avec statut de vérification explicite |
| Investisseur / entreprise | Visibilité du territoire, opportunités économiques, contact direct avec porteurs de projet local |
| Hôtelier / restaurateur / guide local | Visibilité gratuite au lancement, canal de réservation à terme, sans dépendre des OTA internationales |
| Administration communale | Vitrine institutionnelle et point de diffusion d'actualités/événements |

## 3. Personas

1. **Diaspora Marie, 42 ans, Miami** — planifie un voyage familial, veut comprendre l'histoire, trouver un hébergement fiable, réserver depuis son téléphone.
2. **Backpacker Tom, 29 ans, Europe** — découvre Môle-Saint-Nicolas via une recherche « Haïti tourisme historique », veut des infos pratiques (accès, sécurité, budget) et des photos réelles.
3. **Chercheuse Nadège, historienne, Port-au-Prince** — veut consulter la timeline historique, les sources, contribuer du contenu vérifié.
4. **Hôtelier local Jean** — veut un profil gratuit, gérable sans compétence technique, qui génère des réservations.
5. **Agent communal** — publie actualités/événements officiels, a besoin d'un accès éditeur restreint (pas admin complet).
6. **Administrateur GOVIBE (Roosevelt / équipe)** — gère la structure territoriale, valide le contenu soumis, supervise les rôles.

## 4. MVP recommandé

Objectif du MVP : **prouver la valeur du cœur territorial + histoire + vitrine tourisme**, avec un CMS opérable par un non-développeur, **sans** construire tout de suite le moteur de réservation transactionnel complet.

**Dans le MVP (Phase 1+2+3 partielle, voir roadmap §11) :**
- Accueil immersive, navigation, recherche globale simple
- Page Histoire (timeline)
- Territoire : arrondissement → commune → sections communales, pages dynamiques pilotées par CMS
- Sites historiques (liste + fiche détaillée + carte)
- Centre-ville (page dédiée)
- Hôtels & Restaurants : fiches complètes **avec contact direct (téléphone/WhatsApp)**, sans moteur de réservation en ligne transactionnel
- Blog/News + Événements (lecture seule publique, gestion admin)
- Galerie photos
- Carte interactive (OpenStreetMap/Leaflet — voir §10)
- Admin dashboard : CRUD contenu, gestion média, rôles (SUPER ADMIN/ADMIN/EDITOR), statut de contenu (vérifié / soumis / à vérifier)
- SEO de base (sitemap, meta, schema.org sur les entités clés)

**Reporté après le MVP (voir §14) :** réservation transactionnelle avec disponibilité temps réel, paiement en ligne, comptes partenaires en libre-service, multilingue actif, recherche par facettes avancée, avis utilisateurs, recommandations.

## 5. Architecture technique recommandée

**Modèle : monolithe Laravel server-rendered (Blade + Livewire + Alpine.js + Tailwind)**, déployé comme application Laravel indépendante dans `Modules/MoleSaintNicolas/`, base MySQL dédiée, stockage média sur disque local (`storage/app/public`) avec pipeline d'optimisation d'image.

```
┌─────────────────────────────────────────────┐
│  Navigateur (mobile-first)                   │
│  Blade + Alpine.js (micro-interactions)      │
│  Livewire (recherche, filtres, formulaires)  │
└───────────────┬───────────────────────────────┘
                │ HTTPS
┌───────────────▼───────────────────────────────┐
│  Laravel 13 / PHP 8.3 (Modules/MoleSaintNicolas)│
│  ├─ Http/Controllers (public + admin)         │
│  ├─ Livewire\Components (recherche, booking)  │
│  ├─ Models (Eloquent)                         │
│  ├─ Services (Booking, Media, Search, SEO)    │
│  ├─ Policies + spatie/laravel-permission      │
│  └─ Jobs (queue: emails, thumbnails, sitemap) │
└───────────────┬───────────────────────────────┘
                │
        ┌───────┴────────┐
        ▼                ▼
   MySQL (données)   Storage (médias)
```

**Pourquoi pas un frontend séparé (Next.js/SPA) :** l'hébergement fourni est du mutualisé DirectAdmin (PHP + MySQL, pas de process Node persistant garanti). Un monolithe Blade/Livewire élimine tout un axe de risque de déploiement, et c'est la stack déjà maîtrisée par l'équipe sur ce repo (Livewire 4, Tailwind, Alpine déjà en dépendances). Un rendu **côté serveur** est aussi objectivement meilleur pour le SEO qu'une SPA — cohérent avec l'exigence §18 du brief. Le jour où une app mobile ou un frontend headless est nécessaire (Phase 6), on expose une API JSON (`routes/api.php`) au-dessus des mêmes services métier, sans réécrire le cœur.

**Modularité interne** (sans sur-ingénierie) : séparer par domaine métier en namespaces (`Territoire`, `Patrimoine`, `Hebergement`, `Booking`, `Contenu`, `Cms`) plutôt qu'en couches techniques planes, pour que chaque futur module (Phase 3+) s'ajoute sans toucher aux précédents.

## 6. Architecture des pages

```
/                              Accueil
/histoire                      Timeline historique
/territoire                    Liste arrondissement/communes
/territoire/{commune}          Page commune
/territoire/{commune}/{section}  Page section communale
/sites-historiques             Liste + filtres par catégorie
/sites-historiques/{slug}       Fiche détaillée
/centre-ville                  Page dédiée
/explorer                      Expériences/activités (liste)
/explorer/{slug}                Fiche expérience
/hotels                        Liste + filtres
/hotels/{slug}                  Fiche hôtel
/restaurants                   Liste + filtres
/restaurants/{slug}              Fiche restaurant
/evenements                    Liste
/evenements/{slug}               Fiche événement
/blog, /news                   Liste + /{slug} fiche article
/galerie                       Galerie filtrable par catégorie
/carte                         Carte interactive globale
/recherche?q=...               Recherche globale groupée par catégorie
/booking/...                   Réservation (Phase 4)
/partners/...                  Espace prestataires (Phase 6)
/admin/...                     CMS / back-office
```

Toutes les pages « zone » et « site » sont **pilotées par base de données**, jamais codées en dur — un slug inconnu retourne 404, un contenu incomplet affiche les champs manquants comme `[Information à compléter]` plutôt que de masquer la page.

## 7. Architecture du CMS

- **Contenu polymorphe réutilisable :** `Media` (photos/vidéos), `Gallery`, `SeoMeta`, `GpsLocation` sont des concepts transverses attachés par relation polymorphe (`morphMany`/`morphOne`) aux entités `SectionCommunale`, `HistoricalSite`, `Hotel`, `Restaurant`, `Activity`, `Event`, `Article` — un seul module média/SEO sert toutes les entités, au lieu de dupliquer les champs partout.
- **Statut de contenu explicite** (exigence §27 du brief) : chaque entité contenu porte un champ `content_status` enum(`verified`,`submitted`,`needs_review`) + `verified_by`/`verified_at`. L'affichage public distingue visuellement le contenu vérifié.
- **Rôles (spatie/laravel-permission, déjà une dépendance du repo) :**
  - `super_admin` — tout, y compris gestion des rôles
  - `admin` — CRUD complet contenu + médias, pas de gestion des rôles système
  - `editor` — CRUD sur ses propres contributions, publication soumise à validation admin
  - `moderator` — modération des avis/soumissions
  - `partner` — gère uniquement sa propre fiche (hôtel/restaurant), Phase 6
- **Éditeur de contenu riche** pour Blog/News : Livewire + un éditeur WYSIWYG léger (Trix, déjà intégrable nativement à Laravel) plutôt qu'un éditeur JS lourd — suffisant pour du contenu éditorial, pas un CMS générique.
- **Gestion média :** upload → job de compression + génération de thumbnails en queue (`Jobs\ProcessMedia`), `alt_text` obligatoire à la publication (accessibilité + SEO), organisation par dossier logique (par entité liée, pas par arborescence libre).

## 8. Schéma de base de données (entités clés, simplifié)

Hiérarchie territoriale fidèle à la structure administrative haïtienne (Département → Arrondissement → Commune → Section communale → Localité) :

```
departments (id, name)
arrondissements (id, department_id, name)
communes (id, arrondissement_id, name, slug)
sections_communales (id, commune_id, name, slug, population, lat, lng, content_status, ...)
localites (id, section_communale_id, name)

historical_periods (id, name, starts_on, ends_on, order, description)
historical_events (id, historical_period_id, title, happened_on, description)  -- items de la timeline
historical_figures (id, name, bio, historical_period_id nullable)

attraction_categories (id, name, slug)  -- monument, fort, église, site naturel, ...
historical_sites (id, slug, name, category_id, section_communale_id nullable,
                   description, lat, lng, opening_hours, entry_price,
                   guide_available, content_status, ...)

hotels (id, slug, name, address, lat, lng, phone, whatsapp, email, policies, content_status)
rooms (id, hotel_id, name, capacity, price, amenities_json)
restaurants (id, slug, name, cuisine_type, address, lat, lng, phone, whatsapp, hours, content_status)
activities (id, slug, name, category, description, duration, price, content_status)

events (id, slug, title, starts_at, location_text, section_communale_id nullable,
        organizer, price, content_status)

articles (id, slug, title, author_id, category_id, body, published_at, seo_title, meta_description)
categories (id, name, type)   -- polymorphe: blog, galerie, activité...
tags (id, name); taggables (tag_id, taggable_id, taggable_type)

media (id, mediable_id, mediable_type, path, type, alt_text, order)
galleries (id, name, category)

bookings (id, bookable_id, bookable_type, user_id nullable, guest_contact_json,
          starts_at, ends_at, guests_count, status, payment_status, source)
reviews (id, reviewable_id, reviewable_type, author_name, rating, comment, status)

partners (id, user_id, partnerable_id, partnerable_type, status)  -- Phase 6
users, roles, permissions (spatie/laravel-permission)
pages (id, slug, title, body, seo_title, meta_description)  -- pages statiques (à propos, mentions légales...)
notifications (table par défaut Laravel)
audit_logs (id, user_id, action, auditable_id, auditable_type, old_values, new_values, created_at)
```

Points de conception à noter :
- `bookable_type`/`reviewable_type`/`mediable_type` (relations polymorphes) évitent une table de jointure par type d'entité réservable/évaluable/illustrée — nouvelle catégorie réservable ou illustrable = zéro migration structurelle.
- Toutes les entités « contenu » portent `content_status`, `created_by`, `verified_by` — cohérence avec l'exigence de traçabilité (§27).
- Coordonnées GPS en colonnes `lat`/`lng` (DECIMAL) plutôt qu'un type géospatial MySQL dès le MVP — suffisant pour affichage carte, évite la dépendance à des extensions spatiales sur un hébergement mutualisé dont on ne maîtrise pas la config MySQL.

## 9. Architecture des réservations

Le MVP **n'implémente pas** de paiement réel (conforme à la demande §8). L'architecture prépare cependant le terrain :

- **`Bookable` (interface/trait polymorphe)** implémentée par `Hotel`/`Room`, `Restaurant`, `Activity` — chacun expose `isAvailable($start, $end)` selon sa propre logique (chambre = calendrier de dispo, restaurant = créneau/couverts, activité = capacité par date).
- **`Booking`** est le point d'entrée unique : statut `pending → confirmed → completed/cancelled`, `payment_status` séparé (`unpaid → pending → paid → refunded`) pour ne jamais coupler logique de réservation et logique de paiement.
- **`PaymentGateway` (interface)** avec une implémentation `ManualPaymentGateway` au MVP (le prestataire est notifié, confirme lui-même par téléphone/WhatsApp) — les implémentations futures (`MonCashGateway`, `NatCashGateway`, `PaypalGateway`, carte bancaire) branchent la même interface sans toucher au modèle `Booking`.
- Verrouillage pessimiste (`lockForUpdate`) sur la vérification de disponibilité au moment de la confirmation, pour éviter la double-réservation en cas de requêtes concurrentes — même sans paiement réel, c'est le bug le plus visible et le plus dommageable pour la confiance des prestataires.
- Notifications (email + éventuellement WhatsApp via lien `wa.me`) au prestataire ET au visiteur à chaque changement de statut.

## 10. Stack technologique recommandée (avec justification)

| Composant | Choix | Justification |
|---|---|---|
| Framework | Laravel 13 / PHP 8.3 | Cohérence avec le reste du monorepo GOVIBE, équipe déjà formée, hébergement DirectAdmin le supporte nativement |
| Interactivité | Livewire 4 + Alpine.js | Déjà une dépendance du repo ; évite un build JS séparé sur un hébergement mutualisé ; SSR natif = SEO |
| CSS | Tailwind CSS | Déjà utilisé dans GOVIBE, permet le design premium/visuel demandé sans framework de composants lourd |
| Base de données | MySQL | Fournie nativement par l'hébergement DirectAdmin, cohérent avec le reste du repo |
| Rôles/permissions | spatie/laravel-permission | Déjà une dépendance du repo — zéro nouveau package pour un besoin déjà couvert |
| Éditeur riche | Trix (natif Laravel) | Suffisant pour du contenu éditorial, pas de dépendance JS lourde |
| Carte interactive | **Leaflet.js + OpenStreetMap** au MVP | Gratuit, pas de clé API ni de quota à gérer pour un budget associatif/local ; migration vers Mapbox/Google possible plus tard via une interface `MapProvider` sans réécrire les vues — *à valider avec vous (voir question ci-dessous)* |
| PDF (fiches à imprimer, factures futures) | barryvdh/laravel-dompdf | Déjà une dépendance du repo |
| Export (listes admin) | maatwebsite/excel | Déjà une dépendance du repo |
| QR codes (fiches site, billetterie événement) | simplesoftwareio/simple-qrcode | Déjà une dépendance du repo |
| Recherche | Recherche SQL (LIKE/full-text MySQL) au MVP | Le volume de contenu au lancement est faible ; un moteur dédié (Meilisearch/Algolia) est une sur-ingénierie tant que le catalogue ne justifie pas la pertinence avancée — à réévaluer Phase 5+ |
| Multilingue | `spatie/laravel-translatable` (colonnes JSON traduisibles) | Le brief demande une architecture prête, pas activée au lancement ; cette approche évite de dupliquer les tables par langue |
| CI/CD | GitHub Actions, calqué sur `Modules/Tagtoa/deploy/` | Pattern déjà éprouvé sur ce repo (clé SSH dédiée, script de déploiement avec rollback) |

## 11. Roadmap en phases

Reprend et affine la structure proposée dans le brief, alignée sur ce qui est réellement livrable en un module à la fois (CLAUDE.md §2) :

| Phase | Contenu | Sortie |
|---|---|---|
| **0** (ce document) | Analyse, architecture, validation | Ce fichier |
| **1 — Fondations** | Squelette app Laravel indépendante, design system (Tailwind), BDD de base, auth admin, dashboard admin vide, homepage, navigation | App déployable, vide de contenu métier |
| **2 — Territoire & Histoire** | Territoire (arrondissement→section), Histoire (timeline), Sites historiques, Centre-ville, Carte | CMS territorial fonctionnel |
| **3 — Tourisme** | Hôtels, Restaurants, Activités/Explorer (fiches + contact direct, sans paiement) | Vitrine tourisme complète |
| **4 — Booking** | Recherche/disponibilité, réservation manuelle, notifications, interface `PaymentGateway` prête (sans implémentation réelle) | Réservation fonctionnelle sans paiement en ligne |
| **5 — Contenu** | Blog/News, Événements (public), Galerie | Contenu éditorial vivant |
| **6 — Avancé** | Espace partenaires en libre-service, avis utilisateurs, multilingue activé, analytics, recommandations | Post-MVP, selon traction réelle |

## 12. Risques techniques et business

**Techniques :**
- *Hébergement mutualisé DirectAdmin* : ressources limitées (CPU/RAM/quota disque), pas de contrôle total sur la version PHP/extensions — valider dès la Phase 1 que PHP 8.3+, les extensions Laravel requises, et Composer sont disponibles avant d'investir plus loin.
- *Stockage média* : le stockage local sur mutualisé a un plafond ; si la galerie photo/vidéo grossit vite, prévoir une bascule vers un stockage S3-compatible (Phase 5+) — l'architecture (`Media` polymorphe avec disque configurable) le permet sans migration de schéma.
- *Volume de contenu au lancement* : risque qu'un CMS vide donne une mauvaise première impression — mitigé par le statut `needs_review`/placeholders explicites plutôt que de cacher les pages incomplètes.

**Business :**
- *Adoption par les prestataires locaux* (hôtels/restaurants) sans connectivité ou compétence numérique fiable — le MVP mise sur contact direct (téléphone/WhatsApp) plutôt que sur l'autonomie complète, réduisant la barrière d'entrée.
- *Exactitude du contenu historique* : toute erreur factuelle publiée nuit à la crédibilité — d'où l'exigence stricte de ne jamais inventer de contenu (§27) et le statut de vérification visible.
- *Dépendance à une seule personne* pour l'alimentation du contenu au lancement — risque de stagnation ; prévoir dès la Phase 2 un rôle `editor` limité pour distribuer la charge à des contributeurs locaux de confiance.

## 13. Opportunités de monétisation

Alignées avec le brief (§28), classées par facilité de mise en œuvre :
1. **Listings premium** (mise en avant hôtel/restaurant/activité en tête de liste) — faible complexité technique, revenu dès la Phase 3.
2. **Publicité locale** (bannière commerces/services) — faible complexité, Phase 3+.
3. **Commissions de réservation** — nécessite Phase 4 (booking) + paiement en ligne réel.
4. **Abonnements établissements** (accès à des statistiques, mise en avant continue) — nécessite l'espace partenaires (Phase 6).
5. **Visites guidées / marketplace d'expériences** — nécessite un système de paiement + gestion de disponibilité de guides, Phase 6.
6. **Partenariats institutionnels** (mairie, ministère du Tourisme, ONG patrimoniales) — non technique, activable dès le lancement du contenu Histoire/Territoire.

## 14. Fonctionnalités à éviter dans le MVP

- Paiement en ligne réel (MonCash/NatCash/carte/PayPal) — préparer l'interface, ne pas l'implémenter tant que les accès marchands ne sont pas disponibles.
- Comptes partenaires en libre-service (auto-gestion par les prestataires) — au MVP, c'est l'équipe GOVIBE qui saisit/valide le contenu des fiches.
- Avis utilisateurs publics non modérés — activer uniquement avec un flux de modération (Phase 6).
- Multilingue actif (KR/EN) — préparer l'architecture, ne traduire qu'après validation du contenu français.
- Recherche avancée à facettes / moteur de recherche dédié — le SQL suffit tant que le catalogue est petit.
- Application mobile native — l'API JSON n'est utile que lorsqu'un client mobile est réellement priorisé.
- Recommandations « intelligentes »/IA — nécessite un volume de données comportementales qui n'existera pas au lancement.

## 15. Plan de développement module par module

Un seul module actif à la fois, chacun livré avec : objectif, fichiers concernés, tests, vérification, rapport de complétion (CLAUDE.md §6) :

1. `Foundation` — squelette app, design system, auth admin, dashboard vide
2. `Territoire` — modèles + CRUD admin + pages publiques arrondissement/commune/section
3. `Histoire` — périodes, événements, personnages, timeline publique
4. `SitesHistoriques` — catégories, fiches, carte
5. `CentreVille` — page dédiée + points d'intérêt liés
6. `CarteInteractive` — composant Leaflet réutilisable, alimenté par toutes les entités géolocalisées
7. `Hebergement` (Hôtels) — modèles, CRUD admin, fiches publiques
8. `Restauration` (Restaurants) — idem
9. `Explorer` (Activités/expériences) — idem
10. `Booking` — moteur de réservation manuel + notifications
11. `Contenu` (Blog/News/Événements) — éditeur, publication, SEO par article
12. `Galerie`
13. `RechercheGlobale`
14. `SEO` — sitemap.xml, robots.txt, schema.org transverses
15. `Partenaires`, `Avis`, `Multilingue` — Phase 6, au cas par cas selon traction

---

## Questions ouvertes avant de démarrer la Phase 1

Voir le message de chat associé — quelques décisions vous reviennent avant tout premier commit de code applicatif.
