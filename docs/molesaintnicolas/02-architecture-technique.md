# 02 — Architecture technique

Répond aux points 5, 7, 8, 9, 10 (partiel), 11 et 12 de la première tâche.

---

## 0. État des lieux du dépôt

Analyse du dépôt `rooseveltfhr/govibe` au moment de la rédaction. Ces faits
conditionnent toutes les recommandations qui suivent.

| Élément | Valeur constatée | Source |
|---|---|---|
| Framework | Laravel **13.8** | `composer.json` |
| PHP | **8.3** (plateforme figée à 8.3.31) | `composer.json` |
| Base de données | MySQL | `.env.example` |
| UI dynamique | **Livewire 4.3** | `composer.json` |
| CSS | **Tailwind 4** via `@tailwindcss/vite` | `package.json` |
| Build | **Vite 8** + `laravel-vite-plugin` 3.1 | `package.json` |
| Rôles et permissions | **spatie/laravel-permission 8.1** — déjà installé | `composer.json` |
| PDF | `barryvdh/laravel-dompdf` | `composer.json` |
| QR | `simplesoftwareio/simple-qrcode` | `composer.json` |
| Tests | PHPUnit 12.5 | `composer.json`, `phpunit.xml` |
| Style de code | **Laravel Pint** | `composer.json` |
| Stockage fichiers | `FILESYSTEM_DISK=local` — **disque du serveur** | `.env.example` |
| Cache | `CACHE_STORE=file` — **pas de Redis** | `.env.example` |
| File d'attente | `QUEUE_CONNECTION=database` | `.env.example` |
| Sessions | `SESSION_DRIVER=file` | `.env.example` |
| Déploiement | VPS, nginx + certbot, script `deploy.sh` | `deploy.sh`, `nginx.conf.example` |
| Domaine actuel | `govibeht.com` | `deploy.sh:14` |
| Middlewares nommés | `admin`, `erp` | `bootstrap/app.php` |
| Routes | Monolithe de 260 lignes | `routes/web.php` |

### Constats qui appellent une décision

1. **`Modules/Tagtoa` n'est pas chargé par l'application.** L'autoload PSR-4 de
   `composer.json` ne déclare que `App\`, `Database\Factories\` et
   `Database\Seeders\`. Le dossier `Modules/Tagtoa` est un paquet dormant, pas un
   module actif. *Il ne faut donc pas considérer qu'un système de modules existe
   dans ce dépôt : il est à mettre en place.*
2. **`routes/web.php` fait 260 lignes et mélange site public, admin et ERP.**
   Ajouter une plateforme entière dedans le rendrait ingérable. Le module
   molesaintnicolas doit avoir son propre fichier de routes.
3. **Aucune stratégie de sauvegarde des médias n'est visible.** Avec
   `FILESYSTEM_DISK=local`, une réinstallation ou une perte du VPS détruit
   l'intégralité des photographies. Pour un projet dont le contenu visuel est
   l'actif principal, c'est le risque le plus grave du dossier.
4. **Le déploiement cible un domaine unique codé en dur** (`DOMAIN="govibeht.com"`,
   `deploy.sh:14`). Servir un second domaine demande une évolution du script et
   de la configuration nginx.

---

## 1. Décision structurante : monorepo Laravel

### La question

Construire molesaintnicolas.com (a) comme un module du dépôt `govibe` existant,
(b) comme une application Laravel séparée, ou (c) avec une stack moderne
découplée (Next.js + CMS headless) ?

### Comparaison honnête

| Critère | (a) Module dans `govibe` | (b) App Laravel séparée | (c) Next.js + headless |
|---|---|---|---|
| Réutilisation auth/rôles/déploiement | ✅ Immédiate | ⚠️ À dupliquer | ❌ Tout à refaire |
| Compétence de l'équipe | ✅ Stack maîtrisée | ✅ Stack maîtrisée | ❌ Nouvelle stack |
| Coût d'infrastructure | ✅ Serveur partagé | ⚠️ Second serveur ou vhost | ❌ Hébergement Node + CMS |
| Vitesse de mise en production | ✅ La plus rapide | ⚠️ Moyenne | ❌ La plus lente |
| Performance front brute | ⚠️ Bonne | ⚠️ Bonne | ✅ Excellente |
| Risque de régression sur govibeht.com | ❌ Réel, à maîtriser | ✅ Nul | ✅ Nul |
| Synergie produit avec TAGTOA | ✅ Forte | ⚠️ Via API | ⚠️ Via API |
| Maintenance à 5 ans, petite équipe | ✅ Une seule base | ❌ Deux bases à maintenir | ❌ Deux stacks à maintenir |

### Recommandation : (a) module dans le monorepo

**Justification.** Le facteur décisif n'est pas la performance théorique du
front — c'est la capacité d'une petite équipe à maintenir la plateforme pendant
cinq ans. L'option (c) est objectivement supérieure sur le rendu et le
découplage, et objectivement plus coûteuse sur tout le reste : deuxième stack,
deuxième chaîne de déploiement, deuxième corpus de compétences, et un CMS
headless à payer ou à héberger. Pour une équipe qui maîtrise déjà Laravel et
Livewire, ce coût n'est pas justifié par le gain.

L'option (b) évite le risque de régression, mais impose de dupliquer
l'authentification, la gestion des rôles, le pipeline de médias et le script de
déploiement — pour finir par les faire diverger.

**Le risque de l'option (a) — casser govibeht.com — est réel et se maîtrise
par construction :**

- espace de noms dédié `Modules\Mole\`, aucun code dans `app/` ;
- **toutes les tables préfixées `mole_`**, aucune table partagée avec l'ERP ;
- fichier de routes dédié `Modules/Mole/routes/web.php`, chargé par domaine ;
- garde d'authentification séparée `mole_admin`, session distincte de l'ERP ;
- tests de non-régression sur les routes publiques existantes de govibeht.com,
  exécutés à chaque modification du module.

> Si Roosevelt préfère l'isolation totale (option b), l'architecture décrite dans
> ce dossier reste valable à l'identique : seul le mode de chargement change. La
> décision est réversible à faible coût **tant que la règle « aucune table
> partagée » est respectée**. C'est précisément pour préserver cette réversibilité
> que la règle est posée.

### Mise en œuvre du chargement du module

```php
// composer.json — ajout à l'autoload PSR-4
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\Mole\\": "Modules/Mole/app/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/"
    }
}
```

```php
// bootstrap/providers.php
return [
    App\Providers\AppServiceProvider::class,
    Modules\Mole\Providers\MoleServiceProvider::class,
];
```

Le fournisseur enregistre les routes, les vues (`mole::`), les migrations, les
traductions et la configuration du module. Le routage public est monté **par
domaine**, ce qui garantit qu'aucune route du module ne peut répondre sur
`govibeht.com` :

```php
// Modules/Mole/Providers/MoleServiceProvider.php (extrait)
Route::domain(config('mole.domain'))
    ->middleware('web')
    ->group(base_path('Modules/Mole/routes/web.php'));
```

En développement, `MOLE_DOMAIN` pointe vers `mole.localhost` ou reste nul pour
servir le module sur le domaine par défaut.

---

## 2. Architecture générale

```
                        ┌──────────────────────────────┐
                        │      Cloudflare (CDN)        │
                        │  cache statique · WAF · TLS  │
                        └───────────────┬──────────────┘
                                        │
                        ┌───────────────▼──────────────┐
                        │      nginx (VPS unique)      │
                        │  vhost govibeht.com          │
                        │  vhost molesaintnicolas.com  │
                        └───────────────┬──────────────┘
                                        │ PHP-FPM 8.3
                        ┌───────────────▼──────────────┐
                        │      Laravel 13 (monorepo)   │
                        │                              │
                        │  app/          → GOVIBE+ERP  │
                        │  Modules/Mole/ → tourisme    │
                        └──┬─────────────┬─────────────┘
                           │             │
              ┌────────────▼───┐   ┌─────▼──────────────┐
              │  MySQL         │   │  Stockage objet S3 │
              │  tables mole_* │   │  (R2 / B2) médias  │
              └────────────────┘   └────────────────────┘
```

### Couches applicatives du module

```
Modules/Mole/
├── app/
│   ├── Models/            Territoire, Site, Hotel, Restaurant, Article…
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Public/    Pages publiques (lecture seule)
│   │   │   └── Admin/     CMS
│   │   ├── Requests/      Validation — jamais de $request->all()
│   │   └── Middleware/
│   ├── Livewire/          Composants interactifs (recherche, carte, filtres)
│   ├── Services/          Logique métier
│   │   ├── MediaService.php        variantes, thumbnails, AVIF/WebP
│   │   ├── SearchService.php       recherche globale
│   │   ├── SeoService.php          meta, OG, JSON-LD
│   │   ├── BookingRequestService.php
│   │   └── ContentStatusService.php  brouillon → à vérifier → vérifié
│   ├── Policies/          Autorisation par rôle
│   └── Providers/
├── Database/
│   ├── migrations/        Toutes les tables préfixées mole_
│   └── Seeders/           Données de référence, jamais de faits inventés
├── resources/
│   ├── views/             Blade — public/ et admin/
│   ├── js/                Îlots JS (carte Leaflet, lightbox)
│   └── css/
├── routes/
│   ├── web.php            Public, monté par domaine
│   ├── admin.php          CMS
│   └── api.php            API JSON publique (lecture)
├── config/mole.php
├── lang/{fr,ht,en}/
└── tests/{Unit,Feature}/
```

### Principes appliqués

- **Contrôleurs minces, services épais.** Toute logique métier testable
  unitairement vit dans `Services/`.
- **Aucune requête N+1 tolérée.** Chargement anticipé systématique ; le mode
  strict d'Eloquent (`Model::preventLazyLoading()`) est activé hors production.
- **Aucune donnée client n'est utilisée sans validation.** `FormRequest`
  obligatoire pour toute écriture.
- **Le contenu public est en lecture seule.** Aucune route publique n'écrit en
  base, à trois exceptions explicitement listées et strictement limitées :
  demande de réservation, formulaire de contact, soumission de contenu.

---

## 3. Stack technologique recommandée

| Couche | Choix | Justification |
|---|---|---|
| **Langage** | PHP 8.3 | Déjà en place, déjà déployé, compétence acquise |
| **Framework** | Laravel 13 | Déjà en place ; Eloquent, validation, files d'attente, tests inclus |
| **Base de données** | MySQL 8 | Déjà en place ; FULLTEXT et JSON suffisent au besoin |
| **Rendu** | **Blade côté serveur** | HTML complet au premier octet = SEO et perception de rapidité optimales sur réseau lent. Décisif pour ce projet. |
| **Interactivité** | **Livewire 4** (déjà installé) | Filtres, recherche, CMS sans construire une SPA |
| **JS complémentaire** | Alpine.js + îlots ciblés | Menus, lightbox, carte. Pas de framework front global. |
| **CSS** | Tailwind 4 (déjà installé) | Design system par tokens, purge automatique |
| **Cartographie** | **Leaflet + OpenStreetMap** | Sans clé d'API, sans coût, sans quota. Couche d'abstraction permettant de basculer vers Mapbox/Google si besoin. |
| **Images** | Intervention Image + variantes AVIF/WebP | Traitement à l'upload, en file d'attente |
| **Stockage** | **S3-compatible (Cloudflare R2 ou Backblaze B2)** | Voir §8 — non négociable |
| **CDN** | **Cloudflare (offre gratuite)** | Cache statique, TLS, protection ; effet majeur sur la latence depuis Haïti |
| **Recherche** | MySQL FULLTEXT (V1) → Meilisearch (phase 6) | Adapté au volume ; pas de dépendance supplémentaire en V1 |
| **Éditeur** | **TipTap** ou EditorJS | Sortie JSON structurée, assainie côté serveur. **Jamais de HTML brut stocké.** |
| **Rôles** | spatie/laravel-permission (déjà installé) | Aucune raison de réimplémenter |
| **Analytics** | Umami auto-hébergé ou Plausible | Sans cookie, léger, conforme RGPD |
| **E-mail** | SMTP existant, à migrer vers un service transactionnel | Gmail SMTP ne tiendra pas la charge ni la délivrabilité |
| **Tests** | PHPUnit 12 (déjà en place) | Continuité |
| **Style** | Laravel Pint (déjà en place) | Continuité |

### Dépendances à ajouter (liste complète et volontairement courte)

```
intervention/image        traitement d'images
league/flysystem-aws-s3-v3  stockage objet
spatie/laravel-sitemap    génération du sitemap
ueberauth/…               ✗ non requis
```

Rien d'autre. **Chaque dépendance ajoutée est une dette de maintenance sur cinq
ans.** Le CMS, la recherche et le SEO sont construits sur les briques Laravel
natives plutôt que sur un paquet tiers (Filament, Nova) qui imposerait ses
conventions et sa cadence de mise à jour.

> **Arbitrage assumé :** Filament ferait gagner deux à trois semaines sur le CMS.
> Il ajoute en contrepartie une dépendance lourde, une montée de version majeure
> à suivre, et une interface difficile à adapter au besoin très spécifique de la
> gestion territoriale. Sur un horizon de cinq ans, l'artisanat maîtrisé est
> préférable. **Point ouvert à discussion si le délai devient la contrainte
> dominante.**

---

## 4. Architecture des pages publiques

```
/                                   Accueil
/histoire                           Timeline des périodes
/histoire/{slug}                    Période, personnage ou événement

/territoire                         Vue d'ensemble de l'arrondissement
/territoire/{commune}               Page commune
/territoire/{commune}/{section}     Page section communale
                                    ex. /territoire/mole-saint-nicolas/marouge

/sites-historiques                  Liste + filtres par catégorie
/sites-historiques/{slug}           Fiche site

/centre-ville                       Section dédiée
/centre-ville/{slug}                Point d'intérêt

/explorer                           Expériences et activités
/explorer/{slug}                    Fiche expérience

/hotels            /hotels/{slug}
/restaurants       /restaurants/{slug}
/evenements        /evenements/{slug}
/blog              /blog/{slug}
/galerie           /galerie/{categorie}
/carte                              Carte interactive plein écran
/recherche?q=                       Recherche globale
/partenaires                        Annuaire des partenaires
/contact
/mentions-legales

/sitemap.xml   /robots.txt   /feed.xml
```

### Conventions de routage

- **Slugs stables et jamais réutilisés.** Un slug modifié génère une redirection
  301 persistante en base (table `mole_redirects`). Une URL publiée ne meurt
  jamais — c'est une exigence SEO absolue.
- **URL canonique unique par ressource.** Une section communale est accessible
  uniquement par `/territoire/{commune}/{section}`. Les autres chemins
  redirigent.
- **Pas de slug dans plusieurs langues sur la même URL.** Le préfixe de locale
  (`/en/…`, `/ht/…`) est réservé pour la phase 6.

---

## 5. Architecture du CMS

### Principe directeur

> Le CMS n'est pas une fonctionnalité de la plateforme. **C'est la plateforme.**
> Le site public n'est qu'une vue de rendu du contenu qu'il produit.

Cela impose trois règles, dont dépend la tenue du calendrier :

1. **Aucune page publique n'est codée en dur.** Toute page a une entité en base.
2. **Le CMS est livré avant les pages publiques riches** — production éditoriale
   et développement avancent en parallèle.
3. **Le CMS doit être utilisable sur mobile, avec une connexion instable.**
   Sauvegarde automatique du brouillon toutes les 20 secondes en stockage local
   du navigateur, téléversement reprenable, aucune perte de saisie.

### Cycle de vie du contenu

```
   brouillon  ──►  en revue  ──►  publié
       ▲              │              │
       └──────────────┴──────────────┘
                  (retour possible)

   Axe orthogonal — fiabilité de l'information (règle 27 du cahier) :
   non vérifié  ·  à vérifier  ·  vérifié (source + date + vérificateur)
```

Ces deux axes sont **indépendants** : un contenu peut être publié tout en étant
signalé « à vérifier », avec un bandeau explicite sur la page publique. C'est ce
qui permet de publier tôt sans compromettre la crédibilité — et c'est la
traduction directe de la règle 27.

### Rôles et permissions

| Rôle | Périmètre |
|---|---|
| `SUPER_ADMIN` | Tout, y compris utilisateurs, rôles et paramètres |
| `ADMIN` | Tout le contenu, les médias, la modération. Pas les rôles. |
| `EDITOR` | Créer et modifier du contenu, soumettre en revue. **Ne publie pas.** |
| `MODERATOR` | Valider les soumissions, marquer comme vérifié, modérer |
| `PARTNER` | Uniquement ses propres établissements (phase 5) |

Implémenté avec spatie/laravel-permission, déjà présent. Les autorisations
passent par des `Policy` Laravel, jamais par des vérifications de rôle
disséminées dans les vues.

### Journal d'audit

Toute écriture en base par un utilisateur authentifié écrit dans
`mole_audit_logs` : acteur, action, entité, valeurs avant/après, IP,
horodatage. Non désactivable. Exigence de sécurité du cahier des charges
(point 22) et exigence pratique : sur un contenu patrimonial contesté, il faut
pouvoir dire qui a écrit quoi et quand.

---

## 6. Architecture de la recherche

### V1 — MySQL FULLTEXT

Une table d'index dénormalisée, alimentée par les événements de modèle Eloquent :

```sql
CREATE TABLE mole_search_index (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    searchable_type VARCHAR(191) NOT NULL,
    searchable_id   BIGINT UNSIGNED NOT NULL,
    locale        VARCHAR(5)   NOT NULL DEFAULT 'fr',
    title         VARCHAR(255) NOT NULL,
    excerpt       TEXT         NULL,
    body          LONGTEXT     NULL,
    category      VARCHAR(50)  NOT NULL,  -- zone, site, hotel, article…
    url           VARCHAR(255) NOT NULL,
    thumbnail_url VARCHAR(255) NULL,
    weight        TINYINT      NOT NULL DEFAULT 0,
    is_published  BOOLEAN      NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_searchable (searchable_type, searchable_id, locale),
    KEY idx_cat (category, is_published),
    FULLTEXT KEY ft_search (title, excerpt, body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- Réindexation via un observateur, en file d'attente (`QUEUE_CONNECTION=database`,
  déjà configuré).
- Résultats **groupés par catégorie**, comme demandé au point 17 du cahier.
- Pondération : le titre pèse plus que le corps ; les zones et sites passent
  devant les articles.
- Recherche en mode booléen naturel, avec repli sur `LIKE` pour les requêtes de
  moins de trois caractères.

**Limite connue et acceptée :** FULLTEXT ne gère ni la tolérance aux fautes de
frappe, ni la recherche approximative. Sur un corpus francophone de quelques
milliers de documents, c'est acceptable. Meilleure recherche = phase 6.

### Phase 6 — Meilisearch

Tolérance aux fautes, facettes, synonymes, recherche instantanée. L'interface
`SearchService` est conçue dès la V1 pour que la bascule ne touche qu'une
implémentation, sans modifier les contrôleurs.

---

## 7. Architecture SEO

Le SEO n'est pas une couche ajoutée à la fin. C'est **le mécanisme d'acquisition
principal** du projet et une contrainte de conception.

### Par page

Chaque entité publiable porte, via un trait `HasSeo` :

- `seo_title` (repli sur le titre), `seo_description` (repli sur l'extrait)
- `og_image_id` (repli sur l'image principale)
- `canonical_url`, `noindex`
- Données structurées JSON-LD générées par `SeoService`

### Schémas schema.org par type

| Entité | Schéma |
|---|---|
| Accueil, page territoire | `TouristDestination` + `Place` |
| Site historique | `TouristAttraction` + `LandmarksOrHistoricalBuildings` |
| Hôtel | `Hotel` (sous-type de `LodgingBusiness`) |
| Restaurant | `Restaurant` |
| Événement | `Event` |
| Article | `Article` + `BreadcrumbList` |
| Partenaire | `LocalBusiness` |
| Toutes | `BreadcrumbList`, `Organization`, `WebSite` + `SearchAction` |

### Génération automatique

- `sitemap.xml` — index sectionné (`sitemap-pages.xml`, `sitemap-sites.xml`,
  `sitemap-territoire.xml`, `sitemap-blog.xml`), régénéré la nuit et à chaque
  publication.
- `robots.txt` — dynamique selon `APP_ENV` ; **`Disallow: /` en préproduction**
  (erreur classique et coûteuse).
- Flux RSS `/feed.xml` pour le blog.
- Redirections 301 persistantes à chaque changement de slug.

### Performance — budget contraignant

| Métrique | Budget | Vérification |
|---|---|---|
| Poids transféré, page d'accueil | **< 500 Ko** | CI, bloquant |
| LCP (3G simulée) | **< 2,5 s** | Lighthouse CI |
| CLS | < 0,1 | Lighthouse CI |
| INP | < 200 ms | Lighthouse CI |
| Requêtes SQL par page publique | **< 20** | Test automatisé |

Moyens : images AVIF/WebP responsives avec `srcset` et `width`/`height`
systématiques, chargement différé sous la ligne de flottaison, cache de
fragments Blade, cache HTTP agressif sur les pages publiques via Cloudflare,
police système ou une seule police web en `font-display: swap`.

---

## 8. Stockage des médias

### Le problème, énoncé sans détour

`FILESYSTEM_DISK=local` signifie que les photographies vivent sur le disque du
VPS. Pour ce projet, cela pose trois problèmes cumulés :

1. **Aucune sauvegarde.** Perte du serveur = perte de tout le patrimoine
   photographique. Ce contenu est irremplaçable : il suppose un déplacement
   terrain.
2. **Aucune diffusion optimisée.** Chaque image est servie par PHP-FPM depuis
   Haïti ou l'Europe, sans cache de bordure.
3. **Aucune élasticité.** Un site à forte densité photographique atteint
   rapidement plusieurs dizaines de gigaoctets.

### Solution recommandée

```
Upload (CMS)
   │
   ├─► Fichier original          → S3 (bucket privé, conservation)
   │
   └─► File d'attente : MediaVariantsJob
          ├─ thumb   320px   AVIF + WebP + JPEG
          ├─ card    640px   AVIF + WebP + JPEG
          ├─ hero   1280px   AVIF + WebP + JPEG
          └─ full   1920px   AVIF + WebP + JPEG
                                → S3 (bucket public)
                                → servi via Cloudflare CDN
```

- **Fournisseur :** Cloudflare R2 (pas de frais de sortie — décisif) ou
  Backblaze B2. Coût estimé : **3 à 6 $/mois** pour ce volume.
- **Métadonnées en base** (`mole_media`) : dimensions, poids, type MIME, hash,
  texte alternatif, légende, crédit photo, dossier.
- **Texte alternatif obligatoire** avant publication — accessibilité et SEO.
- **Crédit photo obligatoire** — question de droit d'auteur, souvent négligée et
  source de litiges.
- **Traitement en file d'attente**, jamais pendant la requête HTTP.

### Sécurité des téléversements (point 22 du cahier)

- Liste blanche stricte de types MIME, **vérifiée sur le contenu du fichier**,
  pas sur l'extension ni sur l'en-tête déclaré par le client.
- Réencodage systématique des images — neutralise les charges utiles dissimulées.
- Nom de fichier régénéré (UUID), nom d'origine conservé en base uniquement.
- Taille maximale appliquée côté serveur, jamais seulement côté client.
- Aucune exécution possible depuis le bucket ; en-tête
  `Content-Disposition` approprié.

---

## 9. Sécurité

| Exigence (point 22) | Mise en œuvre |
|---|---|
| Authentification | Laravel natif, garde `mole_admin` **distincte** de `web` et `erp` |
| Hachage des mots de passe | bcrypt, `BCRYPT_ROUNDS=12` (déjà configuré) |
| RBAC | spatie/laravel-permission + `Policy` par modèle |
| Validation | `FormRequest` obligatoire ; `$request->all()` interdit |
| XSS | Blade échappe par défaut ; `{!! !!}` interdit sauf sur HTML assaini par une liste blanche |
| CSRF | Middleware Laravel, actif sur tous les formulaires |
| Injection SQL | Eloquent et requêtes paramétrées ; aucune concaténation de chaîne |
| Limitation de débit | Recherche 30/min, demande de réservation 5/h par IP, connexion 5/min |
| Téléversements | Voir §8 |
| Journal d'audit | `mole_audit_logs`, non désactivable |
| Sessions | Régénération à la connexion, expiration, cookies `secure` + `httponly` + `SameSite=Lax` |
| En-têtes | CSP, `X-Frame-Options`, `X-Content-Type-Options`, HSTS |
| Sauvegarde | Base quotidienne chiffrée hors serveur + médias sur stockage objet versionné |

**Isolation vis-à-vis de l'ERP :** aucune table partagée, aucune session
partagée, aucune classe de `app/` importée dans `Modules/Mole/`. Une faille sur
le site touristique public ne doit ouvrir aucun accès aux données de l'ERP
GOVIBE. Cette contrainte est vérifiée par un test automatisé qui échoue si
`Modules/Mole` importe un espace de noms `App\`.

---

## 10. Internationalisation

**V1 : français uniquement. Schéma prêt dès la première migration.**

Approche retenue — **colonne `locale` sur les tables de contenu**, plutôt que
tables de traduction séparées ou colonnes JSON :

```
mole_articles(id, locale, translation_group_id, slug, title, …)
UNIQUE (slug, locale)
INDEX (translation_group_id)
```

Une même information dans trois langues = trois lignes partageant le même
`translation_group_id`. Ce choix garde les requêtes simples et rapides (aucune
jointure supplémentaire pour le cas nominal à une langue), permet des slugs
réellement différents par langue — important pour le SEO — et autorise une
traduction partielle sans ligne vide.

*Alternative écartée :* colonnes JSON par langue. Plus compacte, mais rend
impossible l'index FULLTEXT par langue et complique la recherche.

Coût aujourd'hui : deux colonnes et un index. Coût si oublié : migration de
toutes les tables de contenu avec reprise de données.

---

## 11. Déploiement

### Cible : le VPS existant, second vhost

```nginx
server {
    server_name molesaintnicolas.com www.molesaintnicolas.com;
    root /var/www/govibe/public;   # même application

    # ... configuration PHP-FPM identique à govibeht.com
}
```

L'application distingue les deux sites par `Route::domain()`. Une seule base de
code, un seul déploiement, deux domaines.

### Évolutions nécessaires de `deploy.sh`

Le script code le domaine en dur (`DOMAIN="govibeht.com"`, ligne 14) et génère
un unique bloc `server` nginx. À faire évoluer :

- lire la liste des domaines depuis une variable ou un fichier de configuration ;
- générer un vhost par domaine ;
- appeler `certbot` avec l'ensemble des domaines ;
- `php artisan mole:sitemap` après migration.

### Intégration continue

Le dépôt contient déjà `.github/`. Le pipeline doit exécuter :

```
pint --test          style
phpunit              tests (unitaires + fonctionnels)
lighthouse-ci        budget de performance — bloquant
composer audit       vulnérabilités des dépendances
npm audit            vulnérabilités front
```

Conformément au fichier `CLAUDE.md` (§3.3), une revue automatisée du diff est
exécutée sur chaque pull request, et le merge est refusé en cas de violation
d'isolation ou d'écriture non sécurisée.

### Sauvegardes — à mettre en place avant la mise en production

| Élément | Fréquence | Destination | Conservation |
|---|---|---|---|
| Base MySQL | Quotidienne | Stockage objet chiffré, hors VPS | 30 jours |
| Médias | Continue | Bucket versionné | 90 jours |
| `.env` et secrets | À chaque modification | Gestionnaire de secrets, hors dépôt | — |

**Une restauration non testée n'est pas une sauvegarde.** Un test de
restauration complète est planifié avant la mise en production et répété
trimestriellement.

---

**Suite :** [`03-base-de-donnees.md`](03-base-de-donnees.md)
