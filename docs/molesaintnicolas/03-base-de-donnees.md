# 03 — Schéma de base de données

Répond aux points 3, 4 et 24 de la première tâche.

**Conventions.** Toutes les tables sont préfixées `mole_` — c'est la garantie
d'isolation vis-à-vis de l'ERP GOVIBE et ce qui rend la décision D1 réversible.
Moteur InnoDB, `utf8mb4_unicode_ci`. Clés primaires `BIGINT UNSIGNED`.
Horodatage `created_at` / `updated_at` partout, `deleted_at` sur les entités de
contenu (suppression logique).

---

## 1. Vue d'ensemble

```
                    ┌──────────────────────┐
                    │  HIÉRARCHIE          │
                    │  TERRITORIALE        │
                    └──────────────────────┘
  departements
       │ 1..n
  arrondissements
       │ 1..n
  communes
       │ 1..n
  sections_communales
       │ 1..n
  localites

        Les entités géolocalisées pointent vers un niveau territorial :

  historical_sites ─┐
  hotels ───────────┤
  restaurants ──────┼──► commune_id (obligatoire)
  activities ───────┤    section_communale_id (facultatif)
  events ───────────┘

                    ┌──────────────────────┐
                    │  CONTENU ÉDITORIAL   │
                    └──────────────────────┘
  historical_periods ──n..n── historical_figures
  articles ──n..n── categories, tags
  pages (contenu statique piloté par le CMS)

                    ┌──────────────────────┐
                    │  TRANSVERSAL         │
                    └──────────────────────┘
  media ──n..n── (polymorphe) toute entité
  galleries ──n..n── media
  booking_requests ──► (polymorphe) hotel | restaurant | activity | event
  reviews ──► (polymorphe)          [phase 6]
  search_index ──► (polymorphe)
  redirects · audit_logs · users · partners · content_submissions
```

---

## 2. Utilisateurs, rôles et audit

Les rôles et permissions s'appuient sur **spatie/laravel-permission**, déjà
installé (tables `roles`, `permissions`, `model_has_roles`…). Elles ne sont pas
redéfinies ici.

### `mole_users`

Table distincte de la table `users` de l'ERP — condition de l'isolation.

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `name` | VARCHAR(150) | |
| `email` | VARCHAR(191) UNIQUE | |
| `password` | VARCHAR(255) | bcrypt, 12 tours |
| `phone` | VARCHAR(30) NULL | |
| `whatsapp` | VARCHAR(30) NULL | |
| `avatar_media_id` | BIGINT NULL FK → `mole_media` | |
| `locale` | VARCHAR(5) DEFAULT 'fr' | |
| `is_active` | BOOLEAN DEFAULT 1 | |
| `last_login_at` | TIMESTAMP NULL | |
| `last_login_ip` | VARCHAR(45) NULL | IPv6 |
| `email_verified_at` | TIMESTAMP NULL | |
| `remember_token` | VARCHAR(100) NULL | |

### `mole_audit_logs`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `user_id` | BIGINT NULL FK → `mole_users` | NULL si action système |
| `action` | VARCHAR(50) | `created`, `updated`, `deleted`, `published`, `verified` |
| `auditable_type` | VARCHAR(191) | |
| `auditable_id` | BIGINT UNSIGNED | |
| `old_values` | JSON NULL | |
| `new_values` | JSON NULL | |
| `ip_address` | VARCHAR(45) NULL | |
| `user_agent` | VARCHAR(255) NULL | |
| `created_at` | TIMESTAMP | |

Index : `(auditable_type, auditable_id)`, `(user_id, created_at)`.

---

## 3. Hiérarchie territoriale

Le modèle suit le découpage administratif haïtien : département →
arrondissement → commune → section communale → localité.

> **Périmètre à arbitrer (décision D3).** Le cahier des charges mentionne
> l'arrondissement. Le schéma ci-dessous est **générique** : il accepte aussi
> bien la seule commune du Môle-Saint-Nicolas que l'arrondissement entier, sans
> aucune modification de structure. Seul le contenu injecté change. Les noms et
> découpages réels seront saisis depuis le CMS à partir de **sources officielles
> (CNIGS, IHSI)** — ils ne sont ni codés en dur, ni inventés dans les seeders.

### `mole_departements`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `name` | VARCHAR(150) | |
| `slug` | VARCHAR(191) UNIQUE | |
| `code` | VARCHAR(10) NULL | Code administratif officiel |
| `description` | TEXT NULL | |

### `mole_arrondissements`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `departement_id` | BIGINT FK → `mole_departements` | RESTRICT |
| `name` | VARCHAR(150) | |
| `slug` | VARCHAR(191) UNIQUE | |
| `description` | TEXT NULL | |
| `chef_lieu_commune_id` | BIGINT NULL FK → `mole_communes` | Ajouté après création des communes |

### `mole_communes`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `arrondissement_id` | BIGINT FK | RESTRICT |
| `name` | VARCHAR(150) | |
| `slug` | VARCHAR(191) UNIQUE | |
| `code` | VARCHAR(10) NULL | |
| `intro` | TEXT NULL | Chapô affiché en tête de page |
| `history` | LONGTEXT NULL | Contenu riche (JSON structuré) |
| `geography` | LONGTEXT NULL | |
| `economy` | LONGTEXT NULL | |
| `culture` | LONGTEXT NULL | |
| `population` | INT UNSIGNED NULL | **Toujours accompagné de sa source** |
| `population_source` | VARCHAR(255) NULL | |
| `population_year` | SMALLINT NULL | |
| `area_km2` | DECIMAL(10,2) NULL | |
| `latitude` | DECIMAL(10,7) NULL | |
| `longitude` | DECIMAL(10,7) NULL | |
| `geojson` | JSON NULL | Contour cartographique |
| `cover_media_id` | BIGINT NULL FK → `mole_media` | |
| `practical_info` | JSON NULL | Accès, transport, réseau, santé |
| `status` | ENUM('draft','review','published') DEFAULT 'draft' | |
| `verification_status` | ENUM('unverified','to_verify','verified') DEFAULT 'unverified' | |
| `verified_by` / `verified_at` / `sources` | BIGINT NULL / TIMESTAMP NULL / JSON NULL | Règle 27 |
| `locale` / `translation_group_id` | VARCHAR(5) / UUID | Préparation i18n |
| + colonnes SEO | | voir §9 |

### `mole_sections_communales`

Structure identique à `mole_communes`, avec :

| Colonne | Type | Notes |
|---|---|---|
| `commune_id` | BIGINT FK → `mole_communes` | CASCADE |
| `traditions` | LONGTEXT NULL | |
| `agriculture` | LONGTEXT NULL | |
| `notable_people` | LONGTEXT NULL | |

**Unicité :** `UNIQUE (commune_id, slug)` — deux communes peuvent avoir une
section homonyme. L'URL publique
`/territoire/{commune}/{section}` reste donc toujours non ambiguë.

### `mole_localites`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `section_communale_id` | BIGINT FK | CASCADE |
| `name` | VARCHAR(150) | |
| `slug` | VARCHAR(191) | UNIQUE avec `section_communale_id` |
| `type` | ENUM('village','habitation','quartier','autre') | |
| `description` / `latitude` / `longitude` | TEXT NULL / DECIMAL / DECIMAL | |

Les localités n'ont pas de page dédiée en V1 : elles s'affichent sur la page de
leur section et sur la carte.

---

## 4. Histoire

### `mole_historical_periods`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `name` | VARCHAR(200) | |
| `slug` | VARCHAR(191) UNIQUE | |
| `start_year` | SMALLINT NULL | Négatif pour avant J.-C. |
| `end_year` | SMALLINT NULL | NULL = en cours |
| `date_precision` | ENUM('exact','year','decade','century','approx') | **Essentiel en histoire** |
| `summary` | TEXT NULL | |
| `content` | LONGTEXT NULL | |
| `cover_media_id` | BIGINT NULL FK | |
| `sort_order` | INT DEFAULT 0 | |
| `status` / `verification_status` / `sources` | | Règle 27 |

### `mole_historical_events`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `period_id` | BIGINT NULL FK | |
| `title` / `slug` | VARCHAR | |
| `event_date` | DATE NULL | |
| `date_precision` | ENUM(…) | |
| `summary` / `content` | TEXT / LONGTEXT | |
| `historical_site_id` | BIGINT NULL FK | Lieu de l'événement |
| `sources` | JSON NULL | |

### `mole_historical_figures`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `name` / `slug` | VARCHAR | |
| `birth_year` / `death_year` | SMALLINT NULL | |
| `role` | VARCHAR(200) NULL | |
| `biography` | LONGTEXT NULL | |
| `portrait_media_id` | BIGINT NULL FK | |
| `sources` | JSON NULL | |

**Tables pivots :** `mole_figure_period`, `mole_event_figure`.

> **Note de conception.** `date_precision` n'est pas un détail. Une plateforme
> patrimoniale qui affiche « 12 mars 1697 » pour un fait connu « au XVII<sup>e</sup>
> siècle » fabrique une fausse certitude. La colonne permet à l'interface
> d'afficher « vers 1697 » ou « XVII<sup>e</sup> siècle » selon le niveau réel de
> connaissance. C'est ce qui rend le contenu citable par le persona P4.

---

## 5. Sites et patrimoine

### `mole_site_categories`

`id`, `name`, `slug`, `icon`, `description`, `sort_order`.
Valeurs de départ, issues du point 5 du cahier : sites historiques, monuments,
forts, églises, architecture, sites naturels, patrimoine maritime, lieux
culturels, autres.

### `mole_historical_sites`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `category_id` | BIGINT FK | |
| `commune_id` | BIGINT FK | Obligatoire |
| `section_communale_id` | BIGINT NULL FK | |
| `name` / `slug` | VARCHAR(200) / VARCHAR(191) UNIQUE | |
| `summary` | TEXT NULL | |
| `description` / `history` | LONGTEXT NULL | |
| `latitude` / `longitude` | DECIMAL(10,7) NULL | |
| `address` | VARCHAR(255) NULL | |
| `opening_hours` | JSON NULL | Structure par jour |
| `entry_fee` | DECIMAL(10,2) NULL | |
| `currency` | VARCHAR(3) DEFAULT 'HTG' | |
| `guide_available` | BOOLEAN DEFAULT 0 | |
| `contact_phone` / `contact_whatsapp` / `contact_email` | VARCHAR NULL | |
| `accessibility` | JSON NULL | |
| `recommendations` | TEXT NULL | |
| `cover_media_id` | BIGINT NULL FK | |
| `is_featured` | BOOLEAN DEFAULT 0 | |
| `view_count` | INT UNSIGNED DEFAULT 0 | |
| `status` / `verification_status` / `sources` | | |
| + colonnes SEO | | |

**Sites similaires :** table pivot auto-référencée `mole_site_related`
(`site_id`, `related_site_id`, `sort_order`) plutôt qu'un calcul à la volée —
le choix éditorial produit de meilleures suggestions qu'un algorithme sur un
corpus de cette taille.

---

## 6. Hébergement, restauration, activités

### `mole_hotels`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `partner_id` | BIGINT NULL FK → `mole_partners` | NULL = fiche saisie par l'admin |
| `commune_id` / `section_communale_id` | BIGINT FK / NULL FK | |
| `name` / `slug` | VARCHAR(200) / VARCHAR(191) UNIQUE | |
| `description` | LONGTEXT NULL | |
| `address` / `latitude` / `longitude` | VARCHAR / DECIMAL / DECIMAL | |
| `phone` / `whatsapp` / `email` / `website` | VARCHAR NULL | |
| `stars` | TINYINT NULL | 1–5, NULL si non classé |
| `price_min` / `price_max` | DECIMAL(10,2) NULL | Fourchette indicative |
| `currency` | VARCHAR(3) DEFAULT 'HTG' | |
| `amenities` | JSON NULL | wifi, générateur, climatisation, piscine… |
| `policies` | JSON NULL | arrivée, départ, annulation |
| `room_count` | SMALLINT NULL | Indicatif — **pas un inventaire** |
| `accepts_booking_requests` | BOOLEAN DEFAULT 1 | |
| `social_links` | JSON NULL | |
| `cover_media_id` | BIGINT NULL FK | |
| `is_featured` / `is_verified` | BOOLEAN | `is_verified` = établissement contacté et accord obtenu |
| `status` | ENUM('draft','review','published') | |
| + colonnes SEO | | |

### `mole_rooms` — **créée mais non exploitée en V1**

| Colonne | Type | Notes |
|---|---|---|
| `id` / `hotel_id` | BIGINT | |
| `name` / `description` | VARCHAR / TEXT | |
| `capacity` | TINYINT | |
| `bed_type` | VARCHAR(50) NULL | |
| `base_price` / `currency` | DECIMAL / VARCHAR(3) | |
| `quantity` | SMALLINT DEFAULT 1 | Nombre de chambres de ce type |
| `amenities` | JSON NULL | |

En V1, les types de chambres sont **affichés à titre informatif**. Aucun
calcul de disponibilité. La table existe pour que la phase 6 n'impose pas de
migration. Voir [`04-booking.md`](04-booking.md).

### `mole_restaurants`

Structure proche de `mole_hotels`, avec : `cuisine_types` (JSON),
`price_range` (ENUM `$`, `$$`, `$$$`), `opening_hours` (JSON),
`accepts_reservations` (BOOLEAN), `menu_media_id` (carte en image ou PDF),
`has_delivery`, `has_takeaway`.

**Décision :** pas de table `menu_items` en V1. Une carte structurée exige une
tenue à jour qu'aucun restaurant local ne fera. Une photographie ou un PDF de la
carte est un compromis réaliste. Le module `MENU` de TAGTOA couvrira ce besoin
plus tard pour les établissements qui le souhaitent — synergie produit réelle.

### `mole_activity_categories` et `mole_activities`

Catégories issues du point 7 du cahier : plages, excursions, randonnées, visites
historiques, activités nautiques, expériences culturelles, gastronomie,
aventure, visites guidées.

| Colonne | Type | Notes |
|---|---|---|
| `id` / `category_id` / `commune_id` | BIGINT | |
| `name` / `slug` / `summary` / `description` | | |
| `duration_minutes` | SMALLINT NULL | |
| `difficulty` | ENUM('easy','moderate','hard') NULL | |
| `price_from` / `currency` | DECIMAL / VARCHAR(3) | |
| `min_participants` / `max_participants` | TINYINT NULL | |
| `included` / `not_included` / `what_to_bring` | JSON NULL | |
| `meeting_point` / `latitude` / `longitude` | VARCHAR / DECIMAL | |
| `provider_partner_id` | BIGINT NULL FK | |
| `season` | JSON NULL | Mois recommandés |

---

## 7. Événements

### `mole_events`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `title` / `slug` | VARCHAR | |
| `description` | LONGTEXT | |
| `starts_at` / `ends_at` | DATETIME / DATETIME NULL | |
| `is_all_day` | BOOLEAN DEFAULT 0 | |
| `recurrence` | JSON NULL | Événements annuels (patronale, festival) |
| `venue_name` / `address` | VARCHAR NULL | |
| `commune_id` / `section_communale_id` | BIGINT FK / NULL | |
| `historical_site_id` | BIGINT NULL FK | Si l'événement a lieu sur un site |
| `latitude` / `longitude` | DECIMAL NULL | |
| `organizer_name` / `organizer_phone` / `organizer_whatsapp` / `organizer_email` | VARCHAR NULL | |
| `price` / `currency` / `is_free` | DECIMAL NULL / VARCHAR(3) / BOOLEAN | |
| `booking_url` | VARCHAR(255) NULL | Billetterie externe |
| `accepts_booking_requests` | BOOLEAN DEFAULT 0 | |
| `category` | VARCHAR(50) | culturel, festival, concert, conférence, communautaire |
| `cover_media_id` | BIGINT NULL FK | |
| `status` | ENUM(…) | |

Index : `(starts_at, status)` — la requête dominante est « prochains
événements publiés ».

---

## 8. Contenu éditorial

### `mole_categories` et `mole_tags`

`id`, `name`, `slug` (UNIQUE), `description`, `color`, `sort_order`.
`mole_categories` est auto-référencée (`parent_id`) pour permettre une
hiérarchie.

### `mole_articles`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `author_id` | BIGINT FK → `mole_users` | |
| `title` / `slug` | VARCHAR(255) / VARCHAR(191) | UNIQUE `(slug, locale)` |
| `excerpt` | TEXT NULL | |
| `content` | LONGTEXT | **JSON structuré (TipTap), jamais du HTML brut** |
| `content_html` | LONGTEXT NULL | Rendu mis en cache, régénéré à la publication |
| `type` | ENUM('news','blog','interview','portrait','annonce') | Couvre `/blog` et `/news` |
| `cover_media_id` | BIGINT NULL FK | |
| `gallery_id` | BIGINT NULL FK → `mole_galleries` | |
| `commune_id` / `section_communale_id` | BIGINT NULL FK | Article rattaché à une zone |
| `published_at` | TIMESTAMP NULL | Publication programmée |
| `reading_time` | SMALLINT NULL | Calculé |
| `view_count` | INT UNSIGNED DEFAULT 0 | |
| `is_featured` | BOOLEAN DEFAULT 0 | |
| `status` / `verification_status` | ENUM | |
| `locale` / `translation_group_id` | | |
| + colonnes SEO | | |

Pivots : `mole_article_category`, `mole_article_tag`.

> `/blog` et `/news` partagent la même table, distingués par `type`. Deux tables
> pour la même structure serait une duplication sans bénéfice.

### `mole_pages`

Pages statiques pilotées par le CMS (à propos, mentions légales, contact,
opportunités économiques) : `id`, `title`, `slug` UNIQUE, `content` (JSON),
`template`, `status`, colonnes SEO, `locale`.

---

## 9. Médias et galeries

### `mole_media`

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `uploaded_by` | BIGINT NULL FK | |
| `folder_id` | BIGINT NULL FK → `mole_media_folders` | |
| `disk` | VARCHAR(30) DEFAULT 's3' | |
| `path` | VARCHAR(255) | Chemin de l'original |
| `filename` | VARCHAR(255) | Nom généré (UUID) |
| `original_filename` | VARCHAR(255) | Nom d'origine, jamais utilisé pour le chemin |
| `mime_type` | VARCHAR(100) | Vérifié sur le contenu |
| `size` | INT UNSIGNED | Octets |
| `width` / `height` | SMALLINT NULL | |
| `duration` | INT NULL | Vidéo, en secondes |
| `hash` | CHAR(64) | SHA-256 — déduplication |
| `alt_text` | VARCHAR(255) NULL | **Obligatoire avant publication** |
| `caption` | TEXT NULL | |
| `credit` | VARCHAR(255) NULL | **Obligatoire — droit d'auteur** |
| `type` | ENUM('image','video','document') | |
| `external_url` | VARCHAR(255) NULL | YouTube / Vimeo |
| `variants` | JSON NULL | `{thumb:{avif,webp,jpg},card:{…},…}` |
| `processing_status` | ENUM('pending','processing','done','failed') | |

Index : `hash` (déduplication), `(type, folder_id)`.

### `mole_media_folders`

Auto-référencée : `id`, `parent_id`, `name`, `slug`, `path`.

### `mole_mediables` — pivot polymorphe

| Colonne | Type |
|---|---|
| `media_id` | BIGINT FK |
| `mediable_type` / `mediable_id` | VARCHAR(191) / BIGINT |
| `collection` | VARCHAR(50) — `gallery`, `cover`, `menu` |
| `sort_order` | INT |

`UNIQUE (media_id, mediable_type, mediable_id, collection)`.

### `mole_galleries`

`id`, `title`, `slug`, `description`, `category` (paysages, plages,
centre-ville, histoire, culture, personnes, événements, tourisme,
architecture), `cover_media_id`, `status`.
Pivot `mole_gallery_media` (`gallery_id`, `media_id`, `sort_order`).

### Colonnes SEO — trait `HasSeo`

Ajoutées à toute entité publiable :

```
seo_title        VARCHAR(70)  NULL
seo_description  VARCHAR(180) NULL
og_media_id      BIGINT       NULL
canonical_url    VARCHAR(255) NULL
noindex          BOOLEAN      DEFAULT 0
```

---

## 10. Réservations, partenaires, soumissions

### `mole_booking_requests`

Voir [`04-booking.md`](04-booking.md) pour le détail du flux.

| Colonne | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `reference` | VARCHAR(20) UNIQUE | Ex. `MSN-2026-00042` |
| `bookable_type` / `bookable_id` | VARCHAR(191) / BIGINT | hotel, restaurant, activity, event |
| `guest_name` / `guest_email` / `guest_phone` / `guest_whatsapp` | VARCHAR | |
| `party_size` | TINYINT | |
| `check_in` / `check_out` | DATE NULL | Hôtels |
| `requested_at` | DATETIME NULL | Restaurants, activités |
| `message` | TEXT NULL | |
| `status` | ENUM('pending','sent','confirmed','declined','cancelled','expired') | |
| `partner_notified_at` / `partner_responded_at` | TIMESTAMP NULL | |
| `notification_channel` | ENUM('whatsapp','email','both') | |
| `estimated_total` / `currency` | DECIMAL NULL / VARCHAR(3) | Indicatif |
| `payment_status` | ENUM('none','pending','paid','refunded') DEFAULT 'none' | **Inutilisé en V1** — prépare la phase 6 |
| `payment_provider` / `payment_reference` | VARCHAR NULL | Inutilisés en V1 |
| `ip_address` / `user_agent` | | Anti-abus |

### `mole_partners`

| Colonne | Type | Notes |
|---|---|---|
| `id` / `user_id` | BIGINT / NULL FK | `user_id` NULL tant que le partenaire n'a pas de compte |
| `business_name` / `slug` | VARCHAR | |
| `type` | ENUM('hotel','restaurant','guide','transport','commerce','institution','autre') | |
| `contact_name` / `phone` / `whatsapp` / `email` | VARCHAR | |
| `logo_media_id` | BIGINT NULL FK | |
| `subscription_tier` | ENUM('free','premium') DEFAULT 'free' | Prépare la monétisation |
| `is_verified` / `verified_at` | BOOLEAN / TIMESTAMP NULL | |
| `status` | ENUM('pending','active','suspended') | |

### `mole_content_submissions`

Contributions du public (règle 27 : distinguer contenu vérifié / soumis / à
vérifier).

`id`, `submitter_name`, `submitter_email`, `type` (correction, ajout, photo,
témoignage), `target_type`/`target_id` (polymorphe, nullable),
`content` (TEXT), `media_ids` (JSON), `status` (ENUM pending / accepted /
rejected), `reviewed_by`, `reviewed_at`, `review_note`, `ip_address`.

### `mole_reviews` — **phase 6, table créée sans interface**

`id`, `reviewable_type`/`reviewable_id`, `author_name`, `author_email`,
`rating` (TINYINT 1–5), `title`, `body`, `status` (pending/approved/rejected),
`moderated_by`, `moderated_at`, `ip_address`.

### `mole_redirects`

| Colonne | Type |
|---|---|
| `id` | BIGINT UNSIGNED PK |
| `from_path` | VARCHAR(255) UNIQUE |
| `to_path` | VARCHAR(255) |
| `status_code` | SMALLINT DEFAULT 301 |
| `hit_count` | INT UNSIGNED DEFAULT 0 |
| `reason` | VARCHAR(100) NULL |

Alimentée automatiquement à chaque changement de slug. **Aucune URL publiée ne
doit jamais renvoyer 404.**

### `mole_search_index`

Défini en [`02-architecture-technique.md` §6](02-architecture-technique.md#6-architecture-de-la-recherche).

---

## 11. Décisions de schéma à retenir

| Décision | Raison |
|---|---|
| **Préfixe `mole_` partout** | Isolation ERP ; rend la décision D1 réversible |
| **`locale` + `translation_group_id` dès la V1** | Évite une migration lourde en phase 6 |
| **`status` et `verification_status` séparés** | Permet de publier un contenu tout en le signalant à vérifier (règle 27) |
| **`date_precision` sur les entités historiques** | N'affiche jamais une fausse certitude |
| **`sources` en JSON sur le contenu patrimonial** | Contenu citable, contestation traçable |
| **Contenu riche en JSON + `content_html` en cache** | Sécurité (pas de HTML brut) et performance |
| **Pivot médias polymorphe** | Une image sert plusieurs entités sans duplication |
| **`mole_rooms` et `payment_*` créés mais inutilisés** | Prépare la phase 6 sans migration |
| **`credit` obligatoire sur les médias** | Droit d'auteur photographique |
| **`mole_redirects`** | Aucune URL publiée ne meurt |
| **Suppression logique sur le contenu** | Une suppression accidentelle de contenu patrimonial est irréversible autrement |

---

**Suite :** [`04-booking.md`](04-booking.md)
