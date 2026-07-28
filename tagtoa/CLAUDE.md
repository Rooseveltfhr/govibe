# CLAUDE.md — TAGTOA Project Master Context

> **Li fichye sa ANVAN ou touche yon sèl fichye nan projet an.**
> Sa se memwa konplè tout travay ki te deja fèt sou TAGTOA.

---

## 1. KI SA TAGTOA YE

TAGTOA se yon **SaaS NFC/QR business platform** ki baze ann Haïti (tagtoa.com).
Fondatè : Roosevelt Forestal (GOVIBE Ecosystem, Gonaïves/Port-au-Prince, Haïti).

Platfòm nan gen **5 modules** piblik :

| Module | Deskripsyon | Estati |
|--------|-------------|--------|
| **TAGTOA CONNECT** | Digital business card (NFC + QR) | ✅ Templates kreye |
| **TAGTOA MENU** | Digital restaurant/hotel/bar menu (NFC + QR) | ✅ Template kreye |
| **TAGTOA PAY** | Smart payment page (Manuel + Auto, Haïti + Entènasyonal) | 🔨 À construire |
| **TAGTOA LINKS** | Linktree-style + donation | ⏳ Base existante à enrichir |
| **TAGTOA LOYALTY** | NFC loyalty card system | ❌ À construire from scratch |

---

## 2. BASE DE CODE EXISTANTE — `saas_vcard`

**OU PÀ KREYE YON NOUVO PROJET.** Nou travay sou yon **codebase existant achte** :

```
Dossier racine  : /var/www/tagtoa/   (ou chemin VPS réel)
Framework       : Laravel 10.x  (^10.18)
PHP requis      : 8.2+
DB              : MySQL 8
Multi-tenancy   : stancl/tenancy
Media           : spatie/laravel-medialibrary
Permissions     : spatie/laravel-permission
QR Code         : simplesoftwareio/simple-qrcode
Frontend        : Blade + Bootstrap (existant) + CSS personnalisé TAGTOA (ajouté)
```

### Chiffres clés du projet existant
- **324 migrations** — NE PAS les modifier, seulement ajouter de nouvelles
- **98 modèles Eloquent** — Utiliser les existants, ajouter seulement ce qui manque
- **82 controllers** — Idem
- **538 routes web.php** — Ajouter en bas du fichier uniquement

### Modèles clés à connaître

```php
// CONNECT / LINKS
App\Models\Vcard               // profile public, alias URL, template_id, show_qr_code
App\Models\SocialLink          // réseaux sociaux liés à un vcard
App\Models\CustomLink          // liens personnalisés (Linktree-style)
App\Models\VcardPaymentLink    // liens de paiement sur la page vcard
App\Models\Product             // produits basiques (price, description, vcard_id)
App\Models\ProductCategory     // catégories de produits (name, image, whatsapp_store_id)
App\Models\BusinessHour        // horaires par jour_of_week (1=Lun...7=Dim)

// MENU (WhatsApp Store réutilisé pour TAGTOA MENU)
App\Models\WhatsappStore       // store (logo, cover, store_name, address, whatsapp_no, business_hours)
App\Models\WhatsappStoreProduct // items menu (name, description, selling_price, net_price, images_url)
App\Models\WpOrder             // commandes (PENDING=0, DISPATCHED=1, DELIVERED=2, CANCELLED=3)
App\Models\WpOrderItem         // lignes de commande (product_id, price, qty, total_price)

// SUBSCRIPTION / PLANS
App\Models\Plan                // Free, Pro, Business
App\Models\PlanFeature         // feature flags par plan
App\Models\Subscription        // abonnements actifs

// NFC
App\Models\NfcCardOrder        // commandes cartes NFC physiques
```

---

## 3. DÉCISIONS D'ARCHITECTURE PRISES

### 3.1 Règle absolue sur la DB
> ❌ **JAMAIS** modifier ou renommer une table ou colonne existante.
> ✅ **TOUJOURS** ajouter de nouvelles colonnes `nullable` ou avec `default`.
> ✅ **TOUJOURS** créer de nouvelles tables préfixées `tagtoa_*` pour les nouveaux modules.

### 3.2 URL Publiques TAGTOA

```
tagtoa.com/{alias}              → VcardController::show()     [route: vcard.show]
tagtoa.com/whatsapp-store/{alias} → WhatsappStoreController::show() [route: whatsapp.store.show]
tagtoa.com/add-contact/{vcard}  → VcardController::addContact() [route: add-contact]
```

**NE PAS** changer ces routes. Pour TAGTOA CONNECT ajouter des alias :

```php
// À ajouter en bas de web.php (TAGTOA branded URLs)
Route::get('/u/{alias}',    [VcardController::class, 'show'])->name('tagtoa.connect.u');
Route::get('/card/{alias}', [VcardController::class, 'show'])->name('tagtoa.connect.card');
Route::get('/menu/{alias}', [WhatsappStoreController::class, 'show'])->name('tagtoa.menu.show');
Route::get('/pay/{alias}',  [TaGtoaPayController::class, 'show'])->name('tagtoa.pay.show');
```

### 3.3 Templates CONNECT (déjà créés)
Les 3 templates Blade TAGTOA CONNECT sont dans :

```
resources/views/vcardTemplates/tagtoa1.blade.php   → "Noir Absolu"    (black luxury)
resources/views/vcardTemplates/tagtoa2.blade.php   → "Blanc Glacier"  (editorial white)
resources/views/vcardTemplates/tagtoa3.blade.php   → "Bleu Électrique"(bold blue hero)
```

Ils utilisent **uniquement** les variables déjà passées par `VcardController::show()`.
Font Awesome 6 CDN + Google Fonts + CSS inline (pas de Webpack requis).
Pour les enregistrer :

```sql
INSERT INTO vcard_templates (name, image, status) VALUES
('TAGTOA Noir Absolu',     'tagtoa1.png', 1),
('TAGTOA Blanc Glacier',   'tagtoa2.png', 1),
('TAGTOA Bleu Électrique', 'tagtoa3.png', 1);
```

### 3.4 Template MENU (déjà créé)

```
resources/views/whatsapp_stores/templates/tagtoa_menu/index.blade.php
resources/views/whatsapp_stores/templates/tagtoa_menu/partials/item-card.blade.php
```

Migration associée (À lancer) :

```
database/migrations/2026_06_14_000001_add_tagtoa_menu_fields.php
```

Enregistrer dans DB :

```sql
INSERT INTO wp_store_templates (name, image, status) VALUES ('tagtoa_menu', 'tagtoa-menu.png', 1);
```

---

## 4. MODULES À CONSTRUIRE (par priorité DEVEXPO)

---

### 4.1 TAGTOA PAY — Priorité 1 🔴
**Concept** : Chaque utilisateur crée une "page de paiement" publique avec les méthodes qu'il supporte.

#### Tables à créer

```php
// Migration: create_tagtoa_payment_pages_table
Schema::create('tagtoa_payment_pages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vcard_id')->constrained()->cascadeOnDelete();
    $table->string('tenant_id');
    $table->string('title')->nullable();          // "Payez Jean Baptiste"
    $table->string('alias')->unique();            // URL: tagtoa.com/pay/jean-baptiste
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Migration: create_tagtoa_payment_methods_table
Schema::create('tagtoa_payment_methods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payment_page_id')->constrained('tagtoa_payment_pages')->cascadeOnDelete();
    $table->string('type');       // moncash|natcash|zelle|paypal|stripe|crypto|bank|cash|cod
    $table->string('label')->nullable();          // "Mon compte MonCash"
    $table->string('account_holder')->nullable();
    $table->string('account_number')->nullable(); // numéro / adresse wallet
    $table->string('instructions')->nullable();
    $table->boolean('requires_proof')->default(true);  // upload preuve requis
    $table->boolean('is_active')->default(true);
    $table->unsignedTinyInteger('sort')->default(0);
    // Media (QR image) via spatie/medialibrary — collection: 'payment-qr'
    $table->timestamps();
});

// Migration: create_tagtoa_payment_proofs_table
Schema::create('tagtoa_payment_proofs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payment_method_id')->constrained('tagtoa_payment_methods');
    $table->string('payer_name');
    $table->string('payer_phone')->nullable();
    $table->decimal('amount', 10, 2)->nullable();
    $table->string('currency', 10)->default('HTG');
    $table->string('reference')->nullable();      // numéro de transaction
    $table->tinyInteger('status')->default(0);    // 0=pending, 1=approved, 2=rejected
    $table->text('note')->nullable();
    // Image preuve via spatie/medialibrary — collection: 'proof-image'
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamps();
});
```

#### Méthodes de paiement supportées

```php
const PAYMENT_METHODS = [
    'moncash'  => ['label' => 'MonCash',       'icon' => 'fa-mobile-screen-button', 'haiti' => true],
    'natcash'  => ['label' => 'NatCash',        'icon' => 'fa-mobile-screen-button', 'haiti' => true],
    'zelle'    => ['label' => 'Zelle',          'icon' => 'fa-dollar-sign',          'diaspora' => true],
    'paypal'   => ['label' => 'PayPal',         'icon' => 'fa-paypal',               'intl' => true],
    'stripe'   => ['label' => 'Stripe/Card',    'icon' => 'fa-credit-card',          'intl' => true],
    'crypto'   => ['label' => 'Crypto',         'icon' => 'fa-bitcoin-sign',         'intl' => true],
    'bank'     => ['label' => 'Bank Transfer',  'icon' => 'fa-building-columns',     'intl' => true],
    'cash'     => ['label' => 'Cash',           'icon' => 'fa-money-bill-wave',      'local' => true],
    'cod'      => ['label' => 'Cash on Delivery','icon' => 'fa-box-open',            'local' => true],
    'binance'  => ['label' => 'Binance Pay',    'icon' => 'fa-coins',               'intl' => true],
    'coinbase' => ['label' => 'Coinbase',       'icon' => 'fa-ethereum',             'intl' => true],
];
```

#### Flow public (customer)

```
GET tagtoa.com/pay/{alias}
 → affiche la page avec les méthodes actives
 → client sélectionne une méthode
 → voit QR + numéro de compte + instructions
 → upload preuve (si required)
 → POST /tagtoa-pay/{alias}/submit-proof
 → notification au owner via DB notification + email
```

#### Controller à créer

```php
App\Http\Controllers\TaGtoaPayController
  show($alias)           // page publique
  submitProof(Request)   // POST proof upload

App\Http\Controllers\TaGtoaPayDashboardController
  index()                // liste des pages PAY du user
  create() / store()     // créer une page
  edit($id) / update()   // modifier
  proofs($pageId)        // voir les preuves reçues
  approveProof($id)      // approuver
  rejectProof($id)       // rejeter
```

#### Notification au owner

```php
// Utiliser le système de notifications Laravel existant
// App\Notifications\TaGtoaPayProofReceived
// Database channel + Mail channel
```

> ✅ **STATUT : pakè bati** dans `tagtoa/modules/pay/` — voir `PAY_INTEGRATION.md`.

---

### 4.2 TAGTOA LOYALTY — Priorité 2 🟡
**Concept** : Cartes NFC de fidélité pour boutiques/restaurants. Chaque carte a un numéro 16 chiffres chiffré, CVC, expiry, QR.

#### Tables à créer

```php
// create_tagtoa_loyalty_programs_table
Schema::create('tagtoa_loyalty_programs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vcard_id')->nullable()->constrained();
    $table->string('tenant_id');
    $table->string('name');                        // "TAGTOA Fidélité"
    $table->string('alias')->unique();
    $table->text('description')->nullable();
    $table->decimal('points_per_dollar', 8, 2)->default(1);
    $table->decimal('dollar_per_point', 8, 4)->default(0.01);
    $table->boolean('is_active')->default(true);
    // Logo via spatie media
    $table->timestamps();
});

// create_tagtoa_loyalty_cards_table
Schema::create('tagtoa_loyalty_cards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('program_id')->constrained('tagtoa_loyalty_programs');
    $table->string('card_number', 16)->unique();   // 16 chiffres chiffrés
    $table->string('card_number_encrypted');       // version chiffrée stockée
    $table->string('cvc', 4);                      // 3-4 chiffres (hashé)
    $table->date('expiry_date');
    $table->string('cardholder_name');
    $table->string('cardholder_phone')->nullable();
    $table->string('cardholder_email')->nullable();
    $table->decimal('balance', 10, 2)->default(0);
    $table->unsignedInteger('points')->default(0);
    $table->tinyInteger('status')->default(1);     // 1=active, 0=suspended, 2=expired
    $table->tinyInteger('delivery_type')->default(0); // 0=pickup, 1=home, 2=authorized_point
    $table->text('delivery_address')->nullable();
    $table->timestamp('issued_at')->nullable();
    $table->timestamps();
});

// create_tagtoa_loyalty_transactions_table
Schema::create('tagtoa_loyalty_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('card_id')->constrained('tagtoa_loyalty_cards');
    $table->string('type');                        // top_up|redeem|adjustment|refund
    $table->decimal('amount', 10, 2)->default(0); // montant financier
    $table->integer('points_delta')->default(0);  // +/- points
    $table->string('payment_method')->nullable(); // moncash|natcash|cash|paypal|zelle
    $table->string('reference')->nullable();
    $table->text('note')->nullable();
    $table->tinyInteger('status')->default(1);    // 1=confirmed, 0=pending, 2=failed
    $table->timestamps();
});

// create_tagtoa_loyalty_rewards_table
Schema::create('tagtoa_loyalty_rewards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('program_id')->constrained('tagtoa_loyalty_programs');
    $table->string('name');
    $table->text('description')->nullable();
    $table->unsignedInteger('points_required');
    $table->decimal('discount_value', 8, 2)->nullable();  // valeur en $ ou %
    $table->string('discount_type')->default('fixed');    // fixed|percent
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### Génération du numéro de carte

```php
// Dans App\Services\LoyaltyCardService
public function generateCardNumber(): string
{
    do {
        // Format: TAGTOA xxxx xxxx xxxx
        $prefix = '4297';  // préfixe TAGTOA
        $number = $prefix . str_pad(random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $luhn   = $this->applyLuhn($number);
    } while (TaGtoaLoyaltyCard::where('card_number', $luhn)->exists());
    return $luhn;
}
public function generateCvc(): string
{
    return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}
```

> ✅ **STATUT : pakè bati** dans `tagtoa/modules/loyalty/` — voir `LOYALTY_INTEGRATION.md`.

---

### 4.3 TAGTOA LINKS — Priorité 3 🟢
La base existe via `CustomLink` et `VcardPaymentLink`. Il faut :
1. **Enrichir** la vue publique avec logos auto-détectés par plateforme
2. **Ajouter section donation** (Moncash/NatCash/Zelle/PayPal/Crypto)
3. **Créer template LINKS dédié** (séparé de CONNECT)

```php
// Mapping plateformes → logos (à utiliser dans Blade)
const PLATFORM_ICONS = [
    'facebook'  => 'fa-brands fa-facebook',
    'instagram' => 'fa-brands fa-instagram',
    'tiktok'    => 'fa-brands fa-tiktok',
    'youtube'   => 'fa-brands fa-youtube',
    'twitter'   => 'fa-brands fa-x-twitter',
    'linkedin'  => 'fa-brands fa-linkedin',
    'telegram'  => 'fa-brands fa-telegram',
    'whatsapp'  => 'fa-brands fa-whatsapp',
    'snapchat'  => 'fa-brands fa-snapchat',
    'twitch'    => 'fa-brands fa-twitch',
    'pinterest' => 'fa-brands fa-pinterest',
    'discord'   => 'fa-brands fa-discord',
];
```

> ✅ **STATUT : pakè bati** dans `tagtoa/modules/links/` — voir `LINKS_INTEGRATION.md`.
> Détection auto de plateforme + don via une page TAGTOA PAY.

---

## 5. PALETTE & DESIGN SYSTEM TAGTOA
> **Tout nouveau code CSS doit respecter ces tokens.**

```css
:root {
    --tagtoa-black:       #0A0A0A;
    --tagtoa-white:       #FFFFFF;
    --tagtoa-bg:          #F5F5F3;
    --tagtoa-surface:     #FFFFFF;
    --tagtoa-blue:        #0055FF;  /* Electric blue — couleur principale */
    --tagtoa-blue-deep:   #0040CC;
    --tagtoa-blue-pale:   rgba(0,85,255,0.08);
    --tagtoa-green:       #1D9E75;  /* Success, open, approved */
    --tagtoa-red:         #E0473E;  /* Error, closed, rejected */
    --tagtoa-border:      rgba(0,0,0,0.08);
    --tagtoa-font-head:   'Space Grotesk', sans-serif;   /* Titres */
    --tagtoa-font-body:   'Nunito', -apple-system, sans-serif; /* Corps */
}
```

**Fonts CDN** (à inclure dans tout nouveau template) :

```html
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
```

**Règles design impératives** :
- Mobile-first, max-width `480px` sur les pages publiques
- `loading="lazy"` sur toutes les images sauf above-the-fold
- Pas de jQuery, pas de Bootstrap JS dans les templates publics — vanilla JS uniquement
- Animations : `cubic-bezier(0.4, 0, 0.2, 1)`, durée max `0.3s`
- Toujours respecter `prefers-reduced-motion`
- Bottom bar fixe pour navigation rapide (share, whatsapp, action principale)

---

## 6. INFRASTRUCTURE — VPS INTERSERVER

```
Provider   : InterServer.net
Domain     : tagtoa.com → pointe sur VPS
CDN/Proxy  : Cloudflare (activé)
OS         : Ubuntu 24
Web server : Nginx
PHP        : 8.2 (FPM)
DB         : MySQL 8
SSL        : Let's Encrypt (via Certbot)
Deployer   : SSH via Termius (mobile) / Git pull
```

### Commandes de déploiement standard

```bash
# Après chaque changement
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Après migration
php artisan migrate --force
# Après composer update
composer install --no-dev --optimize-autoloader
# Queues (supervisor process)
php artisan queue:restart
```

### Variables .env critiques à configurer

```env
APP_NAME="TAGTOA"
APP_URL=https://tagtoa.com
APP_DOMAIN=tagtoa.com
# Cloudflare (pour proxy correct)
TRUSTED_PROXIES=*
# Paiements Haïti (Phase MVP — manuel d'abord)
MONCASH_CLIENT_ID=
MONCASH_CLIENT_SECRET=
MONCASH_MODE=sandbox   # passer à 'live' quand prêt
# Paiements Internationaux
STRIPE_KEY=
STRIPE_SECRET=
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
BINANCE_PAY_API_KEY=
COINBASE_COMMERCE_API_KEY=
```

---

## 7. PERFORMANCE — CRITIQUE POUR HAÏTI (connexion 3G lente)
Chaque décision de code doit tenir compte des connexions lentes.

### Règles obligatoires

```php
// 1. Cache agressif en production
php artisan config:cache && php artisan route:cache && php artisan view:cache
// 2. Images — toujours compresser avec Intervention Image
use Intervention\Image\Facades\Image;
$image = Image::make($file)->resize(800, null, fn($c) => $c->aspectRatio())
              ->encode('webp', 75);  // WebP 75% qualité, max 80kb pour les avatars
// 3. QR Code — utiliser SVG (léger) pas PNG
QrCode::format('svg')->size(200)->generate($url)
// 4. Lazy loading strict
<img loading="lazy" src="...">   // TOUJOURS sauf hero image
// 5. Pas de librairies JS lourdes sur les pages publiques
// ❌ jQuery, ❌ Vue, ❌ React, ❌ Bootstrap JS
// ✅ Vanilla JS uniquement
```

### Objectifs de performance
- Page CONNECT : chargement < 2s sur 3G
- Page MENU : chargement < 3s sur 3G (images lazy)
- Page PAY : chargement < 1.5s (pas d'images lourdes)

---

## 8. MULTI-TENANCY — COMMENT ÇA MARCHE
Le projet utilise `stancl/tenancy` en mode **single-database** (pas de DB séparée par tenant).

```php
// Chaque model appartenant à un tenant DOIT avoir :
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
// + 'tenant_id' dans $fillable
// + foreign key: $table->string('tenant_id'); + ->foreign('tenant_id')->references('id')->on('tenants')
// Récupérer le tenant courant
getLogInTenantId()    // string tenant_id
getLogInUser()        // User model
// Scope automatique — les modèles avec BelongsToTenant filtrent automatiquement par tenant
```

**Règle** : Tout nouveau modèle TAGTOA lié à un utilisateur doit utiliser `BelongsToTenant`.

---

## 9. HELPERS ET FONCTIONS GLOBALES (déjà existants)

```php
// Vcard helpers (app/helpers.php ou Vcard model)
checkFeature('social_links')    // bool — vérifie si le plan a la feature
getSocialLink($vcard)           // array de HTML <a> pour chaque réseau social
currencyFormat($amount, $dec, $code)  // formatage monétaire
getLogInUser()                  // User authentifié
getLogInTenantId()              // tenant_id string
getAppName()                    // APP_NAME depuis settings
getVcardFavicon($vcard)         // URL favicon du vcard
getUserCurrencyIcon($userId)    // symbole monétaire
// WhatsApp store helpers
YoutubeID($url)                 // extrait l'ID YouTube d'une URL
TrendingYoutubeID($url)         // idem pour trending
getLanguageIsoCode($name)       // "French" → "fr"
checkFrontLanguageSession()     // langue courante ('ar', 'fr', 'en'...)
getAllLanguageWithFullData()     // collection Language
```

---

## 10. ORDRE DES TÂCHES — ROADMAP DEVEXPO

```
SEMAINE 1-2  : Foundation
  [ ] Déployer saas_vcard sur VPS Interserver
  [ ] Configurer .env (APP_URL=tagtoa.com, DB, mail)
  [ ] php artisan migrate (324 migrations)
  [ ] Configurer Nginx + SSL + Cloudflare
  [ ] Ajouter routes /u/{alias}, /card/{alias}, /menu/{alias}
  [ ] Insérer templates CONNECT + MENU dans les tables DB
  [ ] Lancer migration 2026_06_14_000001_add_tagtoa_menu_fields.php
SEMAINE 3-4  : TAGTOA CONNECT + PAY
  [ ] Installer les 3 templates CONNECT (tagtoa1/2/3.blade.php)
  [ ] Installer le template MENU (tagtoa_menu/)
  [ ] Créer TaGtoaPayController + routes
  [ ] Créer migrations tagtoa_payment_pages, _methods, _proofs
  [ ] Créer models TaGtoaPaymentPage, TaGtoaPaymentMethod, TaGtoaPaymentProof
  [ ] Créer vue publique PAY (style TAGTOA, même palette)
  [ ] Créer dashboard owner pour PAY (gérer méthodes, voir preuves)
  [ ] Notification par DB + email à l'approbation/rejet
SEMAINE 5-6  : TAGTOA MENU (dashboard owner)
  [ ] Enrichir formulaire produit (prep_time, featured, discount_price, dine_in/takeout/delivery)
  [ ] Ajouter champs business_type + delivery_available au formulaire store
  [ ] Brancher upload preuve de paiement depuis template MENU → tagtoa_payment_proofs
SEMAINE 7-8  : TAGTOA LINKS + Polish
  [ ] Créer template LINKS dédié avec logos auto-détectés
  [ ] Ajouter section donation (réutiliser TaGtoaPaymentMethod structure)
  [ ] Wizard onboarding user (choisir module au premier login)
SEMAINE 9-10 : LOYALTY + DEVEXPO
  [ ] Créer migrations loyalty (programs, cards, transactions, rewards)
  [ ] Créer LoyaltyCardService (génération numéro 16 chiffres + CVC)
  [ ] Créer vue publique carte fidélité + QR
  [ ] Préparer 3 cartes NFC physiques pour DEVEXPO
  [ ] Demo pack : 1 CONNECT + 1 MENU restaurant + 1 PAY MonCash
```

---

## 11. CONVENTIONS DE CODE

```php
// Nommage des nouveaux fichiers TAGTOA
app/Http/Controllers/TaGtoaPayController.php       // PascalCase avec TaGtoa prefix
app/Models/TaGtoaPaymentPage.php
app/Services/LoyaltyCardService.php
database/migrations/2026_XX_XX_XXXXXX_create_tagtoa_*.php
// Routes TAGTOA (web.php)
Route::prefix('tagtoa')->group(function () {
    // nouvelles routes ici
});
// Vues TAGTOA
resources/views/tagtoa/pay/show.blade.php
resources/views/tagtoa/pay/dashboard/index.blade.php
resources/views/tagtoa/loyalty/card-public.blade.php
// Noms de templates Blade publics (standalone HTML, pas d'@extends)
// Les pages publiques NFC/QR sont des fichiers HTML complets autonomes
// (comme les templates vcard existants)
```

---

## 12. PIÈGES À ÉVITER

```
❌ NE PAS faire php artisan migrate:fresh  — détruirait les 324 tables existantes
❌ NE PAS modifier App\Models\Vcard, WhatsappStore, Product  — seulement étendre
❌ NE PAS utiliser TailwindCSS CDN — le projet existant utilise Bootstrap. CSS inline pour les templates publics.
❌ NE PAS toucher mix('assets/css/...') sans vérifier que webpack est configuré
❌ NE PAS utiliser jQuery dans les templates publics (lent sur 3G)
❌ NE PAS hardcoder des prix en USD — toujours passer par currencyFormat() ou laisser configurable
❌ NE PAS oublier BelongsToTenant sur les nouveaux modèles liés aux users
```

---

## 13. FICHIERS DÉJÀ PRODUITS (à copier dans le projet)
Ces fichiers sont prêts et testés — les copier aux bons endroits :

```bash
# Templates CONNECT (3 fichiers)
cp tagtoa-connect-templates/tagtoa-connect-1.blade.php resources/views/vcardTemplates/tagtoa1.blade.php
cp tagtoa-connect-templates/tagtoa-connect-2.blade.php resources/views/vcardTemplates/tagtoa2.blade.php
cp tagtoa-connect-templates/tagtoa-connect-3.blade.php resources/views/vcardTemplates/tagtoa3.blade.php
# Template MENU
mkdir -p resources/views/whatsapp_stores/templates/tagtoa_menu/partials
cp tagtoa-menu-template/tagtoa-menu-index.blade.php resources/views/whatsapp_stores/templates/tagtoa_menu/index.blade.php
cp tagtoa-menu-template/partials_item-card.blade.php resources/views/whatsapp_stores/templates/tagtoa_menu/partials/item-card.blade.php
# Migration MENU
cp tagtoa-menu-template/2026_06_14_000001_add_tagtoa_menu_fields.php database/migrations/
php artisan migrate
```

> Dépôt git de référence : ce repo. Chaque module est un pakè autonome sous
> `tagtoa/modules/<module>/` qui reproduit l'arborescence Laravel.

---

## 14. CONTACT & CONTEXTE BUSINESS
- **Fondateur** : Roosevelt Forestal
- **Entreprise mère** : GOVIBE Ecosystem (govibeht.com)
- **VPS** : Interserver.net
- **Marché principal** : Haïti + diaspora haïtienne
- **Événement cible** : DEVEXPO Artibonite 2026 (BANJ × GOVIBE, Gonaïves)
- **Langues** : Français, Kreyòl, Anglais (trilingue)
- **Monnaies** : HTG (gourde haïtienne) primaire + USD secondaire

---

*Dènye mizajou : Jen 2026 — Roosevelt Forestal × Claude (Anthropic)*

---

## 15. MODULE 6 — TAGTOA EVENT (spec complète dans TAGTOA_EVENT_SPEC.md)
Tables : `tagtoa_ev_events`, `tagtoa_ev_ticket_types`, `tagtoa_ev_orders`,
         `tagtoa_ev_tickets`, `tagtoa_ev_checkins`, `tagtoa_ev_sale_items`,
         `tagtoa_ev_sale_transactions`
Controllers : `TaGtoaEventPublicController`, `TaGtoaEventController`, `TaGtoaEventCheckinController`
Service clé : `TaGtoaCheckinService::processScan()` — retourne JSON avec `valid`, `color`, `sound`
Fonctionnalités :
- Événements payants ou gratuits, multi-type (concert, expo, mariage, sport…)
- Types de tickets (VIP, Standard, Gratuit) avec quantités et dates de vente
- Check-in / check-out NFC tap + QR scan + manuel (offline-first avec sync)
- Scanner PWA (vue fournie dans spec) : sons + vibration + temps réel
- Ventes in-event : participant achète avec wallet NFC de son ticket
- Dashboard organisateur : commandes, analytics, export CSV

> ✅ **STATUT : pakè bati** dans `tagtoa/modules/event/` — voir `EVENT_INTEGRATION.md`.
> Commission plateforme câblée via le module BILLING.

---

## 16. MODULE 7 — TAGTOA POS (spec complète dans TAGTOA_POS_SPEC.md)
Tables : `tagtoa_pos_terminals`, `tagtoa_pos_products`, `tagtoa_pos_sales`,
         `tagtoa_pos_sale_items`, `tagtoa_pos_cash_movements`
Controller principal : `TaGtoaPosController`
Fonctionnalités :
- Interface caisse tactile (vue HTML complète fournie dans spec)
- 1 bouton = 1 article, emoji + couleur personnalisable
- Sons natifs Web Audio API (pas de fichiers audio) : add/success/error/warning
- OFFLINE-FIRST : IndexedDB + sync automatique quand connexion revient
- Cash, MonCash, NatCash, Zelle, PayPal, Carte Bancaire (VISA, Mastercard), Virement Bank, Unibank, Sogebank, Cash on delivery, USDT Crypto, Bitcoin, Loyalty card NFC
- Paiement split (moitié cash + moitié MonCash)
- Reçu imprimante thermique Bluetooth (ESC/POS) + envoi WhatsApp
- Rapport journalier Z, historique ventes, stats produits

> ✅ **STATUT : pakè bati** dans `tagtoa/modules/pos/` — voir `POS_INTEGRATION.md`.
> Commission plateforme câblée via le module BILLING.

---

## 17. MODÈLE DE REVENU — TAGTOA BILLING (`tagtoa/modules/billing/`)
TAGTOA génère ses revenus de **2 façons au choix** du marchand (ou défaut plateforme) :
- **`subscription`** : abonnement (réutilise `Plan`/`PlanFeature`/`Subscription` existants), aucune commission.
- **`commission`** : commission `%` + frais fixe sur chaque vente (EVENT, POS, …).
- **`both`** : abonnement réduit + commission réduite.

Tables : `tagtoa_revenue_settings`, `tagtoa_commissions`.
Service : `TaGtoaRevenueService::record($sourceType,$sourceId,$module,$gross,$tenantId,$currency)`.
Dashboard : `/tagtoa/billing` (choix du modèle + journal des commissions + net marchand).
Câblé dans : EVENT (commande payée) et POS (vente). ⚠️ Déployer BILLING avant EVENT/POS.
