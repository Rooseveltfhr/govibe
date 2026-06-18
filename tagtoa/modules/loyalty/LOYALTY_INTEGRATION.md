# TAGTOA LOYALTY — Guide d'intégration

Module **Priorité 2 🟡**. Cartes NFC de fidélité : chaque carte a un numéro
16 chiffres (Luhn-valide, préfixe TAGTOA `4297`), un CVC, une date d'expiration,
un solde + des points, et une page publique (NFC tap / QR) `tagtoa.com/loyalty/card/{token}`.

> ⚠️ Respecte la règle DB absolue : **aucune table existante n'est modifiée**.
> Tout est dans de nouvelles tables `tagtoa_loyalty_*`.

---

## Fichiers fournis

| Fichier (dans ce pakè) | Destination dans `saas_vcard` |
|---|---|
| `database/migrations/2026_06_18_000010_create_tagtoa_loyalty_programs_table.php`     | `database/migrations/` |
| `database/migrations/2026_06_18_000011_create_tagtoa_loyalty_cards_table.php`        | `database/migrations/` |
| `database/migrations/2026_06_18_000012_create_tagtoa_loyalty_rewards_table.php`      | `database/migrations/` |
| `database/migrations/2026_06_18_000013_create_tagtoa_loyalty_transactions_table.php` | `database/migrations/` |
| `app/Models/TaGtoaLoyaltyProgram.php` · `TaGtoaLoyaltyCard.php` · `TaGtoaLoyaltyTransaction.php` · `TaGtoaLoyaltyReward.php` | `app/Models/` |
| `app/Services/LoyaltyCardService.php` | `app/Services/` |
| `app/Http/Controllers/TaGtoaLoyaltyController.php` · `TaGtoaLoyaltyDashboardController.php` | `app/Http/Controllers/` |
| `resources/views/tagtoa/loyalty/card-public.blade.php` | `resources/views/tagtoa/loyalty/` |
| `resources/views/tagtoa/loyalty/dashboard/*.blade.php` | `resources/views/tagtoa/loyalty/dashboard/` |
| `routes/tagtoa_loyalty_routes.php` | contenu à coller en bas de `routes/web.php` |

---

## Déploiement

```bash
cd /var/www/tagtoa
cp -r modules/loyalty/app/* app/
cp -r modules/loyalty/database/migrations/* database/migrations/
cp -r modules/loyalty/resources/views/tagtoa resources/views/
# + coller routes/tagtoa_loyalty_routes.php en bas de routes/web.php
php artisan migrate
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Ordre des migrations** (important — FK entre tables) :
`programs (000010)` → `cards (000011)` → `rewards (000012)` → `transactions (000013)`.

---

## Sécurité des cartes

- **`card_number`** (16 chiffres) : stocké en clair pour permettre l'unicité +
  la recherche, **mais** masqué par défaut (`$hidden`) et jamais exposé en public.
  La page publique utilise `public_token` (opaque), pas le numéro.
- **`card_number_encrypted`** : version chiffrée (`Crypt`) pour restitution owner.
- **`cvc`** : **hashé** (`Hash::make`). Le CVC en clair n'apparaît **qu'une fois**,
  à l'émission (flash session `new_card`). Vérification via `LoyaltyCardService::verifyCvc()`.
- **Luhn** : `generateCardNumber()` produit des numéros 16 chiffres Luhn-valides ;
  `isValidLuhn()` les valide.

---

## API du service (`LoyaltyCardService`)

```php
$svc = app(\App\Services\LoyaltyCardService::class);

// Émettre
$res = $svc->issueCard($program, ['cardholder_name' => 'Jean B.', 'balance' => 0]);
$res['card'];  // TaGtoaLoyaltyCard
$res['cvc'];   // CVC clair (à montrer UNE fois)

// Recharger (crédite solde + points selon barème)
$svc->topUp($card, 500.00, ['payment_method' => 'moncash', 'reference' => 'MC123']);

// Utiliser (débite le solde ; lève RuntimeException si insuffisant)
$svc->redeem($card, 120.00);

// Échanger une récompense (dépense des points)
$svc->redeemReward($card, $reward);
```

Recharges/débits sont **atomiques** (`DB::transaction` + `lockForUpdate`) et
écrivent une ligne dans `tagtoa_loyalty_transactions` avec `balance_after` /
`points_after` pour un historique fiable.

---

## Points de compatibilité à vérifier (sur le vrai code)

1. **`getLogInTenantId()`** — utilisé pour `tenant_id` à la création du programme.
2. **`BelongsToTenant`** scope les programmes au tenant courant.
3. **`spatie/laravel-medialibrary`** — logo du programme (collection `program-logo`).
4. **`simplesoftwareio/simple-qrcode`** — QR de la carte (fallback API externe si absent).
5. **Layout admin** — vues dashboard en `@extends('layouts.app')` → adapter.
6. **`App\Models\Vcard`** — relation facultative (`vcard_id` nullable).

---

## Demo DEVEXPO (3 cartes NFC)

1. Créer un programme (`/tagtoa/loyalty/create`), ex. "Restaurant X Fidélité".
2. Émettre 3 cartes → noter les n° + CVC + écrire le `public_url` sur les puces NFC.
3. Recharger une carte (top-up MonCash) en live, montrer le solde/points mis à jour
   sur la page publique après un tap NFC.
4. Ajouter 1-2 récompenses pour montrer le déblocage par points.
