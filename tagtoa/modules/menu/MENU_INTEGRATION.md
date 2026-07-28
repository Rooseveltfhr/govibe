# TAGTOA MENU — Guide d'intégration

## Fichiers fournis

| Fichier | Destination |
|---------|-------------|
| `2026_06_14_000001_add_tagtoa_menu_fields.php` | `database/migrations/` |
| `tagtoa-menu-index.blade.php` | `resources/views/whatsapp_stores/templates/tagtoa_menu/index.blade.php` |
| `partials_item-card.blade.php` | `resources/views/whatsapp_stores/templates/tagtoa_menu/partials/item-card.blade.php` |

---

## Étape 1 — Migration

```bash
cp 2026_06_14_000001_add_tagtoa_menu_fields.php database/migrations/
php artisan migrate
```

Cette migration **n'altère aucune table existante au-delà d'ajouter des
colonnes nullable / avec defaults** — totalement rétrocompatible avec
les autres templates de boutique WhatsApp (grocery_store, cloth_store, etc.)

Nouvelles colonnes :

**`whatsapp_store_products`**
- `discount_price` (float, nullable) — prix barré si renseigné
- `prep_time` (int, nullable) — minutes
- `featured` (bool, default false) — badge "Chef's pick"
- `is_available` (bool, default true) — "Sold out" si false
- `dine_in`, `takeout`, `delivery` (bool) — modes de service par item

**`whatsapp_stores`**
- `business_type` (string, default "restaurant") — restaurant|hotel|bar|lounge|cafe|club|fastfood
- `delivery_available` (bool, default false)

---

## Étape 2 — Copier les vues

```bash
mkdir -p resources/views/whatsapp_stores/templates/tagtoa_menu/partials

cp tagtoa-menu-index.blade.php \
   resources/views/whatsapp_stores/templates/tagtoa_menu/index.blade.php

cp partials_item-card.blade.php \
   resources/views/whatsapp_stores/templates/tagtoa_menu/partials/item-card.blade.php
```

---

## Étape 3 — Enregistrer le template dans WpStoreTemplate

Le controller `WhatsappStoreController::show()` route vers :
```php
view('whatsapp_stores.templates.' . $whatsappStore->template->name . '.index', ...)
```

Ajouter une ligne dans `wp_store_templates` :

```sql
INSERT INTO wp_store_templates (name, image, status, created_at, updated_at)
VALUES ('tagtoa_menu', 'tagtoa-menu.png', 1, NOW(), NOW());
```

`name` doit correspondre **exactement** au nom du dossier : `tagtoa_menu`.

---

## Étape 4 — Variables déjà fournies par le controller (aucun changement requis)

```php
$whatsappStore       // logo_url, cover_url, store_name, address, whatsapp_no,
                     // region_code, store_announcement, slider_video_banner,
                     // discount, enable_download_qr_code, business_type (nouveau),
                     // delivery_available (nouveau)

$business_hours      // bool
$businessDaysTime    // array [1..7 => "HH:MM - HH:MM" | null]
$whatsappStoreUrl    // URL publique pour QR + partage
$discount            // float|null — remise globale
```

Relations utilisées par le template :
```php
$whatsappStore->categories()  // ProductCategory : name, image_url
$whatsappStore->products()    // WhatsappStoreProduct : name, description,
                               // selling_price, discount_price, currency,
                               // images_url, category_id, featured,
                               // is_available, prep_time, dine_in,
                               // takeout, delivery
```

---

## Étape 5 — Activer pour un business owner

Dans le dashboard TAGTOA (`whatsapp.stores.edit`), exposer 2 nouveaux champs :

```html
<select name="business_type">
    <option value="restaurant">Restaurant</option>
    <option value="hotel">Hôtel</option>
    <option value="bar">Bar</option>
    <option value="lounge">Lounge</option>
    <option value="cafe">Café</option>
    <option value="club">Club</option>
    <option value="fastfood">Fast Food</option>
</select>

<input type="checkbox" name="delivery_available" value="1">
```

Et dans le formulaire produit, ajouter :
```html
<input type="number" name="prep_time" placeholder="Temps de préparation (min)">
<input type="number" name="discount_price" placeholder="Prix promo (optionnel)">
<input type="checkbox" name="featured">       <!-- Chef's pick -->
<input type="checkbox" name="is_available" checked>
<input type="checkbox" name="dine_in" checked>
<input type="checkbox" name="takeout" checked>
<input type="checkbox" name="delivery">
```

---

## Fonctionnalités incluses dans le template

### Header noir TAGTOA
- Logo en haut, badge NFC/QR clignotant
- Cover (image ou vidéo YouTube en boucle)
- Status "Open now / Closed" calculé automatiquement depuis `businessDaysTime`
- Chips : adresse (Google Maps), WhatsApp, Delivery

### Marquee
- Bandeau bleu défilant pour `store_announcement`

### Order mode toggle
- Dine-in / Takeout / Delivery
- Filtre dynamiquement les items selon `data-dine_in`, `data-takeout`, `data-delivery`
- Affiche le champ adresse uniquement en mode Delivery

### Catégories sticky
- Tabs horizontaux avec scroll-spy (mise en évidence auto pendant le scroll)

### Featured / Chef's picks
- Carousel horizontal si au moins 1 produit a `featured = true`

### Cartes produit
- Image, nom, description (2 lignes max), badges (prep time, sold out, dine-in/takeout/delivery)
- Prix barré si `discount_price` renseigné
- Bouton "+" pour ajouter au panier (désactivé si `is_available = false`)

### Panier (JS pur, sans framework)
- Bottom sheet modal avec quantités +/-
- Calcul sous-total, remise globale (`$discount`), total
- Bascule automatiquement entre bottom bar (share/whatsapp/map) et cart bar

### Checkout
- Formulaire nom / téléphone / adresse (si delivery)
- TAGTOA PAY : Cash, Cash on Delivery, MonCash, NatCash
- Upload preuve de paiement si MonCash/NatCash sélectionné (input file — à brancher
  sur l'endpoint `payment-proofs` de TAGTOA PAY)
- Envoi final de la commande formatée via WhatsApp (`wa.me`)

### QR Code
- Génération SVG via `simplesoftwareio/simple-qrcode` (déjà utilisé dans le projet)

---

## Performance Haïti — déjà optimisé

- Toutes les images : `loading="lazy"` sauf logo/cover (above the fold)
- Aucune librairie JS lourde — vanilla JS uniquement
- Font Awesome + Google Fonts via CDN (peut être self-hosted, voir guide CONNECT)
- `prefers-reduced-motion` respecté
- Scroll-spy via `IntersectionObserver` natif (pas de polyfill)

---

## Brancher l'upload de preuve de paiement (TAGTOA PAY)

Le champ `#tm-payment-proof` est prêt côté UI. Pour le connecter :

```js
// Dans tm-place-order-btn handler, avant window.open(waUrl) :
if (payment === 'moncash' || payment === 'natcash') {
    var fileInput = document.getElementById('tm-payment-proof');
    if (fileInput.files.length) {
        var formData = new FormData();
        formData.append('proof', fileInput.files[0]);
        formData.append('whatsapp_store_id', @json($whatsappStore->id));
        formData.append('payment_method', payment);
        formData.append('amount', grandTotal);

        fetch('/payment-proofs', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: formData
        });
    }
}
```

Ceci suppose la route `POST /payment-proofs` du module TAGTOA PAY (Phase 2
de la roadmap — `PaymentProofController@store`).
