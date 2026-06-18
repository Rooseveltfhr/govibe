# TAGTOA PAY — Guide d'intégration

Module **Priorité 1 🔴** de la roadmap DEVEXPO. Page de paiement publique
(`tagtoa.com/pay/{alias}`) où le client choisit une méthode (MonCash, NatCash,
Zelle, PayPal…), voit le QR + le numéro de compte, et upload une preuve de
paiement. Le propriétaire est notifié (DB + email) et approuve/rejette depuis
son dashboard.

> ⚠️ Respecte la règle DB absolue : **aucune table existante n'est modifiée**.
> Tout est dans de nouvelles tables `tagtoa_payment_*`.

---

## Fichiers fournis

| Fichier (dans ce pakè) | Destination dans `saas_vcard` |
|---|---|
| `database/migrations/2026_06_18_000001_create_tagtoa_payment_pages_table.php`   | `database/migrations/` |
| `database/migrations/2026_06_18_000002_create_tagtoa_payment_methods_table.php` | `database/migrations/` |
| `database/migrations/2026_06_18_000003_create_tagtoa_payment_proofs_table.php`  | `database/migrations/` |
| `app/Models/TaGtoaPaymentPage.php`        | `app/Models/` |
| `app/Models/TaGtoaPaymentMethod.php`      | `app/Models/` |
| `app/Models/TaGtoaPaymentProof.php`       | `app/Models/` |
| `app/Http/Controllers/TaGtoaPayController.php`           | `app/Http/Controllers/` |
| `app/Http/Controllers/TaGtoaPayDashboardController.php`  | `app/Http/Controllers/` |
| `app/Notifications/TaGtoaPayProofReceived.php`           | `app/Notifications/` |
| `resources/views/tagtoa/pay/show.blade.php`             | `resources/views/tagtoa/pay/` |
| `resources/views/tagtoa/pay/dashboard/*.blade.php`      | `resources/views/tagtoa/pay/dashboard/` |
| `routes/tagtoa_pay_routes.php`            | contenu à coller en bas de `routes/web.php` |

---

## Étape 1 — Copier les fichiers

```bash
cd /var/www/tagtoa     # racine du projet saas_vcard

cp -r modules/pay/app/* app/
cp -r modules/pay/database/migrations/* database/migrations/
cp -r modules/pay/resources/views/tagtoa resources/views/
```

## Étape 2 — Migrations

```bash
php artisan migrate
```

Crée 3 tables : `tagtoa_payment_pages`, `tagtoa_payment_methods`,
`tagtoa_payment_proofs`. (Si la table `notifications` n'existe pas encore :
`php artisan notifications:table && php artisan migrate`.)

## Étape 3 — Routes

Coller le contenu de `routes/tagtoa_pay_routes.php` **en bas** de `routes/web.php`
(ou ajouter les `use ...` en haut et le reste en bas). Vérifier que le middleware
du groupe dashboard correspond à celui du projet (souvent `['web','auth']` ou un
groupe tenant). La route publique `/pay/{alias}` ne doit **pas** être protégée.

```bash
php artisan route:cache
```

## Étape 4 — Vérifications de compatibilité (à confirmer sur le vrai code)

Ces 4 points dépendent du codebase réel — vérifier puis ajuster si besoin :

1. **`App\Models\Vcard`** doit exposer :
   - `user()` (relation) — utilisée pour notifier le propriétaire. Sinon le code
     retombe sur `vcard->email`. Adapter `TaGtoaPayController::notifyOwner()`.
   - colonne d'alias public. Le dashboard liste `id, name, urlAlias` — remplacer
     `urlAlias` par le vrai nom de colonne si différent (`url_alias`, `slug`…).
2. **`getLogInTenantId()`** existe (helper global cité dans CLAUDE.md). Sert à
   remplir `tenant_id` à la création.
3. **`BelongsToTenant`** (`stancl/tenancy`) scope automatiquement les pages au
   tenant. Si la version diffère, vérifier le trait importé.
4. **`spatie/laravel-medialibrary`** est installé (QR méthode + image preuve).
5. **Layout admin** : les vues dashboard font `@extends('layouts.app')`.
   Remplacer par le layout réel du back-office (ex: `admin.app`) et adapter le
   `@section('content')` si le nom de section diffère.

## Étape 5 — Enregistrer le module (optionnel)

Ajouter un lien dans le menu du dashboard owner vers `route('tagtoa.pay.dashboard.index')`,
et éventuellement une `PlanFeature` (`tagtoa_pay`) si tu veux gater l'accès par plan
via `checkFeature('tagtoa_pay')`.

---

## Modèle de données

```
tagtoa_payment_pages   1 ──< tagtoa_payment_methods   1 ──< tagtoa_payment_proofs
        │                                                          │
        └──────────────────────< tagtoa_payment_proofs >──────────┘
vcard_id → vcards.id          QR image  : medialibrary 'payment-qr'
tenant_id (BelongsToTenant)   preuve    : medialibrary 'proof-image'
```

`tagtoa_payment_proofs.status` : `0=pending`, `1=approved`, `2=rejected`.

---

## Flow complet

```
CLIENT                                   OWNER
  │ GET /pay/{alias}                        │
  │ choisit méthode → voit QR + n°          │
  │ remplit nom/montant/réf + upload preuve │
  │ POST /pay/{alias}/submit-proof  ───────►│ Notification (DB + email)
  │ "Preuve reçue ✔"                        │ GET /tagtoa/pay/{id}/proofs
  │                                         │ Approuve / Rejette (+ note)
```

---

## Performance (Haïti / 3G) — déjà appliqué

- Page publique = **HTML standalone** unique, CSS inline, **zéro** librairie JS
  (vanilla seulement), Font Awesome + Google Fonts via CDN.
- `loading="lazy"` sur QR et previews. Pas d'images lourdes.
- Compteur de vues via `incrementQuietly` (pas de table analytics).
- Objectif page PAY : **< 1.5s sur 3G**.

---

## Brancher TAGTOA MENU → PAY

Le template MENU (`MENU_INTEGRATION.md`, section "Brancher l'upload de preuve")
poste sur `/payment-proofs`. Pour unifier avec ce module, pointer plutôt vers
`/pay/{alias}/submit-proof` avec `payment_method_id`, ou créer une page PAY
dédiée au store et y rediriger le checkout MENU. (Phase 5-6 de la roadmap.)
