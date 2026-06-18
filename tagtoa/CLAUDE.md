# CLAUDE.md — TAGTOA Project Master Context

> **Li fichye sa ANVAN ou touche yon sèl fichye nan projet an.**
> Sa se memwa konplè tout travay ki te deja fèt sou TAGTOA.

---

## 1. KI SA TAGTOA YE

TAGTOA se yon **SaaS NFC/QR business platform** ki baze ann Haïti (tagtoa.com).
Fondatè : Roosevelt Forestal (GOVIBE Ecosystem, Gonaïves/Port-au-Prince, Haïti).

Platfòm nan gen **7 modules** piblik :

| Module | Deskripsyon | Estati |
|--------|-------------|--------|
| **TAGTOA CONNECT** | Digital business card (NFC + QR) | ✅ Templates kreye |
| **TAGTOA MENU** | Digital restaurant/hotel/bar menu (NFC + QR) | ✅ Template kreye |
| **TAGTOA PAY** | Smart payment page (Manuel + Auto, Haïti + Entènasyonal) | 🔨 Pakè bati (modules/pay) |
| **TAGTOA LINKS** | Linktree-style + donation | ⏳ Base existante à enrichir |
| **TAGTOA LOYALTY** | NFC loyalty card system | ❌ À construire from scratch |
| **TAGTOA EVENT** | Billetterie + check-in NFC/QR + ventes in-event | ❌ Spec: TAGTOA_EVENT_SPEC.md |
| **TAGTOA POS** | Caisse tactile offline-first | ❌ Spec: TAGTOA_POS_SPEC.md |

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
```
resources/views/vcardTemplates/tagtoa1.blade.php   → "Noir Absolu"    (black luxury)
resources/views/vcardTemplates/tagtoa2.blade.php   → "Blanc Glacier"  (editorial white)
resources/views/vcardTemplates/tagtoa3.blade.php   → "Bleu Électrique"(bold blue hero)
```
Ils utilisent **uniquement** les variables déjà passées par `VcardController::show()`.
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
Migration associée : `database/migrations/2026_06_14_000001_add_tagtoa_menu_fields.php`
```sql
INSERT INTO wp_store_templates (name, image, status) VALUES ('tagtoa_menu', 'tagtoa-menu.png', 1);
```

---

## 4. MODULES À CONSTRUIRE (par priorité DEVEXPO)

### 4.1 TAGTOA PAY — Priorité 1 🔴  → **PAKÈ BATI : `modules/pay/`**
**Concept** : Chaque utilisateur crée une "page de paiement" publique avec les méthodes qu'il supporte.

Tables (préfixe `tagtoa_`) : `tagtoa_payment_pages`, `tagtoa_payment_methods`, `tagtoa_payment_proofs`.
Controllers : `TaGtoaPayController` (public), `TaGtoaPayDashboardController` (owner).
Notification : `TaGtoaPayProofReceived` (database + mail).

#### Flow public (customer)
```
GET tagtoa.com/pay/{alias}
 → affiche la page avec les méthodes actives
 → client sélectionne une méthode → voit QR + numéro + instructions
 → upload preuve (si required) → POST /pay/{alias}/submit-proof
 → notification au owner (DB + email)
```

#### Méthodes de paiement supportées
```php
const PAYMENT_METHODS = [
    'moncash'  => ['label' => 'MonCash',         'icon' => 'fa-mobile-screen-button', 'haiti' => true],
    'natcash'  => ['label' => 'NatCash',         'icon' => 'fa-mobile-screen-button', 'haiti' => true],
    'zelle'    => ['label' => 'Zelle',           'icon' => 'fa-dollar-sign',          'diaspora' => true],
    'paypal'   => ['label' => 'PayPal',          'icon' => 'fa-paypal',               'intl' => true],
    'stripe'   => ['label' => 'Stripe/Card',     'icon' => 'fa-credit-card',          'intl' => true],
    'crypto'   => ['label' => 'Crypto',          'icon' => 'fa-bitcoin-sign',         'intl' => true],
    'bank'     => ['label' => 'Bank Transfer',   'icon' => 'fa-building-columns',     'intl' => true],
    'cash'     => ['label' => 'Cash',            'icon' => 'fa-money-bill-wave',      'local' => true],
    'cod'      => ['label' => 'Cash on Delivery','icon' => 'fa-box-open',             'local' => true],
    'binance'  => ['label' => 'Binance Pay',     'icon' => 'fa-coins',               'intl' => true],
    'coinbase' => ['label' => 'Coinbase',        'icon' => 'fa-ethereum',             'intl' => true],
];
```

### 4.2 TAGTOA LOYALTY — Priorité 2 🟡
Cartes NFC de fidélité. Tables : `tagtoa_loyalty_programs`, `tagtoa_loyalty_cards`,
`tagtoa_loyalty_transactions`, `tagtoa_loyalty_rewards`. Service : `LoyaltyCardService`
(numéro 16 chiffres préfixe `4297` + Luhn, CVC 4 chiffres hashé).

### 4.3 TAGTOA LINKS — Priorité 3 🟢
Base via `CustomLink` + `VcardPaymentLink`. Enrichir vue publique (logos auto-détectés),
ajouter section donation, template LINKS dédié.

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
    --tagtoa-font-head:   'Space Grotesk', sans-serif;
    --tagtoa-font-body:   'Nunito', -apple-system, sans-serif;
}
```
**Fonts CDN** :
```html
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
```
**Règles design impératives** :
- Mobile-first, max-width `480px` sur les pages publiques
- `loading="lazy"` sur toutes les images sauf above-the-fold
- Pas de jQuery, pas de Bootstrap JS dans les templates publics — vanilla JS uniquement
- Animations : `cubic-bezier(0.4, 0, 0.2, 1)`, durée max `0.3s`, respecter `prefers-reduced-motion`
- Bottom bar fixe pour navigation rapide (share, whatsapp, action principale)

---

## 6. INFRASTRUCTURE — VPS INTERSERVER
```
Provider   : InterServer.net          Web server : Nginx
Domain     : tagtoa.com → VPS         PHP        : 8.2 (FPM)
CDN/Proxy  : Cloudflare (activé)      DB         : MySQL 8
OS         : Ubuntu 24                SSL        : Let's Encrypt (Certbot)
Deployer   : SSH via Termius (mobile) / Git pull
```
### Commandes de déploiement standard
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan migrate --force                              # après migration
composer install --no-dev --optimize-autoloader         # après composer update
php artisan queue:restart                                # queues (supervisor)
```
### Variables .env critiques
```env
APP_NAME="TAGTOA"
APP_URL=https://tagtoa.com
APP_DOMAIN=tagtoa.com
TRUSTED_PROXIES=*
MONCASH_CLIENT_ID=
MONCASH_CLIENT_SECRET=
MONCASH_MODE=sandbox   # passer à 'live' quand prêt
STRIPE_KEY=
STRIPE_SECRET=
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
BINANCE_PAY_API_KEY=
COINBASE_COMMERCE_API_KEY=
```

---

## 7. PERFORMANCE — CRITIQUE POUR HAÏTI (connexion 3G lente)
```php
// 1. Cache agressif: php artisan config:cache && route:cache && view:cache
// 2. Images compressées: Image::make($f)->resize(800,null,...)->encode('webp',75); // max 80kb avatar
// 3. QR en SVG (léger): QrCode::format('svg')->size(200)->generate($url)
// 4. <img loading="lazy"> TOUJOURS sauf hero
// 5. Pas de jQuery/Vue/React/Bootstrap JS sur pages publiques — vanilla JS only
```
Objectifs : CONNECT < 2s · MENU < 3s · PAY < 1.5s (sur 3G).

---

## 8. MULTI-TENANCY
`stancl/tenancy` en **single-database**.
```php
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
// + 'tenant_id' dans $fillable
// + migration: $table->string('tenant_id');
getLogInTenantId()    // string tenant_id
getLogInUser()        // User model
```
**Règle** : tout nouveau modèle TAGTOA lié à un user utilise `BelongsToTenant`.

---

## 9. HELPERS GLOBAUX (déjà existants)
```php
checkFeature('social_links')          getLogInUser()
getSocialLink($vcard)                 getLogInTenantId()
currencyFormat($amount,$dec,$code)    getAppName()
getVcardFavicon($vcard)               getUserCurrencyIcon($userId)
YoutubeID($url)                       getLanguageIsoCode($name)
checkFrontLanguageSession()           getAllLanguageWithFullData()
```

---

## 10. ROADMAP DEVEXPO
```
SEM 1-2  Foundation : déployer saas_vcard, .env, migrate, Nginx+SSL+CF, routes alias, templates DB
SEM 3-4  CONNECT + PAY : 3 templates CONNECT, template MENU, TaGtoaPayController, migrations PAY, vue + dashboard PAY, notifications
SEM 5-6  MENU dashboard : formulaire produit (prep_time/featured/discount/modes), champs store, upload preuve→tagtoa_payment_proofs
SEM 7-8  LINKS + polish : template LINKS logos auto, donation, wizard onboarding
SEM 9-10 LOYALTY + DEVEXPO : migrations loyalty, LoyaltyCardService, vue carte+QR, 3 cartes NFC demo, demo pack (CONNECT+MENU+PAY MonCash)
```

---

## 11. CONVENTIONS DE CODE
```php
app/Http/Controllers/TaGtoaPayController.php       // PascalCase, prefix TaGtoa
app/Models/TaGtoaPaymentPage.php
app/Services/LoyaltyCardService.php
database/migrations/2026_XX_XX_XXXXXX_create_tagtoa_*.php
Route::prefix('tagtoa')->group(fn() => /* ... */);
resources/views/tagtoa/pay/show.blade.php          // standalone HTML, pas d'@extends
```

---

## 12. PIÈGES À ÉVITER
```
❌ php artisan migrate:fresh  — détruirait les 324 tables existantes
❌ modifier App\Models\Vcard, WhatsappStore, Product  — seulement étendre
❌ TailwindCSS CDN  — projet en Bootstrap, CSS inline pour templates publics
❌ toucher mix('assets/css/...') sans vérifier webpack
❌ jQuery dans les templates publics (lent sur 3G)
❌ hardcoder des prix en USD  — passer par currencyFormat() ou rendre configurable
❌ oublier BelongsToTenant sur les nouveaux modèles liés aux users
```

---

## 13. FICHIERS DÉJÀ PRODUITS (à copier dans le projet)
Ce dépôt git est la **source de vérité versionnée** de TAGTOA. Chaque module est un
pakè autonome sous `tagtoa/modules/<module>/` qui reproduit l'arborescence Laravel.
```bash
# MENU (prêt)
cp modules/menu/database/migrations/2026_06_14_000001_add_tagtoa_menu_fields.php database/migrations/
cp -r modules/menu/resources/views/whatsapp_stores/templates/tagtoa_menu resources/views/whatsapp_stores/templates/

# PAY (prêt) — voir modules/pay/PAY_INTEGRATION.md
cp -r modules/pay/app/* app/
cp -r modules/pay/database/migrations/* database/migrations/
cp -r modules/pay/resources/views/tagtoa resources/views/
# puis ajouter le contenu de modules/pay/routes/tagtoa_pay_routes.php en bas de web.php
php artisan migrate
```

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
- Scanner PWA : sons + vibration + temps réel
- Ventes in-event : participant achète avec wallet NFC de son ticket
- Dashboard organisateur : commandes, analytics, export CSV

---

## 16. MODULE 7 — TAGTOA POS (spec complète dans TAGTOA_POS_SPEC.md)
Tables : `tagtoa_pos_terminals`, `tagtoa_pos_products`, `tagtoa_pos_sales`,
         `tagtoa_pos_sale_items`, `tagtoa_pos_cash_movements`
Controller principal : `TaGtoaPosController`
Fonctionnalités :
- Interface caisse tactile (1 bouton = 1 article, emoji + couleur personnalisable)
- Sons natifs Web Audio API (pas de fichiers audio) : add/success/error/warning
- OFFLINE-FIRST : IndexedDB + sync automatique quand connexion revient
- Paiements : Cash, MonCash, NatCash, Zelle, PayPal, Carte (VISA/Mastercard),
  Virement Bank (Unibank, Sogebank), Cash on delivery, USDT, Bitcoin, Loyalty NFC
- Paiement split (moitié cash + moitié MonCash)
- Reçu imprimante thermique Bluetooth (ESC/POS) + envoi WhatsApp
- Rapport journalier Z, historique ventes, stats produits

---

*Dènye mizajou : Jen 2026 — Roosevelt Forestal × Claude (Anthropic)*
