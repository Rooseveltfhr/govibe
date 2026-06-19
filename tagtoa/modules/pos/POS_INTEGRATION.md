# TAGTOA POS — Guide d'intégration (Module 7)

Caisse tactile **offline-first** : 1 bouton = 1 article (emoji + couleur), sons
Web Audio (zéro fichier), paiement multi-méthodes + **split**, reçu imprimante
thermique / WhatsApp, rapport Z, historique, stats.

> ⚠️ Aucune table existante modifiée. Tables `tagtoa_pos_*` (5).
> Revenu : commission câblée via **module BILLING** (déployer BILLING d'abord).

## Tables (ordre)
`terminals (40)` → `products (41)` → `sales (42)` → `sale_items (43)` → `cash_movements (44)`.

## Déploiement
```bash
cd /var/www/tagtoa
cp -r modules/pos/app/* app/
cp -r modules/pos/database/migrations/* database/migrations/
cp -r modules/pos/resources/views/tagtoa resources/views/
# coller modules/pos/routes/tagtoa_pos_routes.php en bas de routes/web.php
php artisan migrate
php artisan view:cache && php artisan route:cache
```

## Composants
- **Models** : TaGtoaPosTerminal, TaGtoaPosProduct, TaGtoaPosSale (PAYMENT_METHODS),
  TaGtoaPosSaleItem, TaGtoaPosCashMovement.
- **Service** : `TaGtoaPosService::recordSale()` — atomique, **idempotent**
  (`client_uuid`), décrément stock, encaissement cash → mouvement de caisse +
  solde terminal, et **commission** via `TaGtoaRevenueService`. `cashMovement()`
  pour fond/entrée/sortie/clôture.
- **Controller** : `TaGtoaPosController` — register (caisse), sale/sync (JSON),
  report (Z), products (CRUD), cash.

## Caisse (`/tagtoa/pos/{id}/register`)
- Grille de produits colorés (emoji), panier live, remise, **encaissement**.
- Méthodes : Cash, MonCash, NatCash, Zelle, PayPal, Carte (VISA/Mastercard),
  Virement Unibank/Sogebank, Cash on Delivery, USDT, Bitcoin, Loyalty NFC.
- **Split** (ex. moitié MonCash + moitié cash).
- **Offline-first** : si pas de réseau, la vente est mise en file (`localStorage`)
  et synchronisée via `/sync` au retour en ligne (dédup `client_uuid`).
- **Reçu** : lien WhatsApp pré-rempli (`wa.me`) + bouton impression
  (navigateur ; pour l'imprimante thermique Bluetooth ESC/POS, brancher le flux
  d'octets ESC/POS sur le bouton « Imprimer » selon ton SDK matériel).

## Reçu thermique ESC/POS (extension)
Le bouton « Imprimer » appelle `print()` (impression navigateur). Pour une
imprimante Bluetooth ESC/POS, remplacer par l'envoi des commandes ESC/POS via
Web Bluetooth / pont natif (hors périmètre web standard).

## Points à vérifier
1. Module **BILLING** déployé. 2. `getLogInTenantId()` + `BelongsToTenant`.
3. Layout admin (`@extends('layouts.app')`). 4. HTTPS requis pour caméra/Web Audio
sur mobile. 5. Loyalty NFC (méthode `loyalty`) : pour débiter une carte LOYALTY,
brancher sur `LoyaltyCardService::redeem()` (module LOYALTY).
