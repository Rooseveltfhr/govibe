# TAGTOA EVENT — Guide d'intégration (Module 6)

Billetterie + check-in NFC/QR (offline-first) + ventes in-event, pour
événements payants ou gratuits. URL publique : `tagtoa.com/event/{alias}`.

> ⚠️ Aucune table existante modifiée. Tables `tagtoa_ev_*` (7).
> Revenu : commission câblée via **module BILLING** (déployer BILLING d'abord).

## Tables (ordre de migration)
`events (30)` → `ticket_types (31)` → `orders (32)` → `tickets (33)` →
`checkins (34)` → `sale_items (35)` → `sale_transactions (36)`.

## Fichiers → destinations
```bash
cd /var/www/tagtoa
cp -r modules/event/app/* app/
cp -r modules/event/database/migrations/* database/migrations/
cp -r modules/event/resources/views/tagtoa resources/views/
# coller modules/event/routes/tagtoa_event_routes.php en bas de routes/web.php
php artisan migrate
php artisan view:cache && php artisan route:cache
```

## Composants
- **Models** : TaGtoaEvent, TaGtoaEvTicketType, TaGtoaEvOrder, TaGtoaEvTicket,
  TaGtoaEvCheckin, TaGtoaEvSaleItem, TaGtoaEvSaleTransaction.
- **Services** :
  - `TaGtoaTicketService` — createOrder() (stock + émission billets) / markPaid().
  - `TaGtoaCheckinService::processScan()` — renvoie `{valid,color,sound,message,ticket}`
    (vert/rouge/orange + son success/error/warning) ; `sync()` pour le lot offline.
- **Controllers** : public (vitrine/achat/billet), organisateur (CRUD/commandes/
  analytics/export CSV), check-in (scanner + scan + sync).

## Scanner PWA (`/tagtoa/event/{id}/scanner`)
- Caméra QR via `html5-qrcode` (CDN) + saisie manuelle + (NFC tap = même endpoint).
- **Offline-first** : scans bufferisés en `localStorage`, envoyés via `/sync` au
  retour réseau (dédup sur `client_uuid`). Sons **Web Audio** (zéro fichier),
  vibration, feedback couleur plein écran, compteurs live.

## Revenu — commission
À la confirmation de paiement d'une commande, `TaGtoaEventPublicController`
appelle `TaGtoaRevenueService::record('event_order', …, 'event', $total, $tenantId)`.
Pour les commandes payées via TAGTOA PAY après coup, appeler `markPaid()` puis
`record(...)` depuis le webhook/handler de validation de paiement.

## Points à vérifier
1. Module **BILLING** déployé (commission). 2. `getLogInTenantId()` + `BelongsToTenant`.
3. `spatie/medialibrary` (cover). 4. `simple-qrcode` (QR billets/ticket). 5. Layout
admin (`@extends('layouts.app')`). 6. Page TAGTOA PAY pour `pay_page_id` (paiement billets).
