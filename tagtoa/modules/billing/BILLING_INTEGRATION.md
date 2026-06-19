# TAGTOA BILLING — Modèle de revenu (abonnement + commission)

Module **transversal** de monétisation TAGTOA. Deux sources de revenu, au choix
du marchand (ou défaut plateforme) :

| Modèle | Abonnement | Commission sur ventes |
|--------|:---------:|:---------------------:|
| `subscription` | ✅ (Plan/Subscription existants) | ❌ |
| `commission`   | ❌ | ✅ (% + frais fixe) |
| `both`         | ✅ (réduit) | ✅ (réduite) |

> ⚠️ Aucune table existante modifiée. Nouvelles tables `tagtoa_revenue_settings`
> et `tagtoa_commissions`. L'abonnement réutilise **tel quel** les modèles
> `Plan` / `PlanFeature` / `Subscription` déjà présents dans `saas_vcard`.

---

## Fichiers

| Fichier | Destination |
|---|---|
| `database/migrations/2026_06_18_000005_create_tagtoa_revenue_settings_table.php` | `database/migrations/` |
| `database/migrations/2026_06_18_000006_create_tagtoa_commissions_table.php`      | `database/migrations/` |
| `app/Models/TaGtoaRevenueSetting.php` · `TaGtoaCommission.php` | `app/Models/` |
| `app/Services/TaGtoaRevenueService.php` | `app/Services/` |
| `app/Http/Controllers/TaGtoaBillingController.php` | `app/Http/Controllers/` |
| `resources/views/tagtoa/billing/index.blade.php` | `resources/views/tagtoa/billing/` |
| `routes/tagtoa_billing_routes.php` | bas de `routes/web.php` |

```bash
cp -r modules/billing/app/* app/
cp -r modules/billing/database/migrations/* database/migrations/
cp -r modules/billing/resources/views/tagtoa resources/views/
php artisan migrate
```

> ⚠️ Déployer **avant** EVENT et POS (ils appellent `TaGtoaRevenueService`).
> Migrations `000005/000006` < EVENT `0003x` < POS `0004x` → bon ordre.

---

## Comment ça marche

1. Le marchand choisit son modèle sur `/tagtoa/billing` (radio + % + frais fixe).
   Une ligne globale `tenant_id = null` peut servir de défaut plateforme.
2. Chaque vente confirmée appelle le service :

```php
app(\App\Services\TaGtoaRevenueService::class)->record(
    sourceType: 'event_order',   // ou pos_sale, pay_proof, loyalty_topup
    sourceId:   $order->id,
    module:     'event',         // event|pos|pay|loyalty
    grossAmount:(float) $order->total,
    tenantId:   $order->event->tenant_id,
    currency:   $order->currency,
);
```

3. Si le modèle est `commission` ou `both`, une ligne `tagtoa_commissions` est
   créée (idempotente par source). Sinon (`subscription`), rien n'est prélevé.

Déjà câblé dans :
- **EVENT** : `TaGtoaEventPublicController` à la confirmation de paiement d'une commande.
- **POS**   : `TaGtoaPosController` à l'enregistrement d'une vente.

Le dashboard `/tagtoa/billing` affiche le total brut, les commissions TAGTOA et
le net marchand + le journal détaillé.

---

## Abonnement (rappel)
Le volet abonnement n'introduit pas de nouveau code : il s'appuie sur le système
`Plan` / `PlanFeature` / `Subscription` existant + `checkFeature('tagtoa_pay')`,
`checkFeature('tagtoa_event')`, etc. pour gater l'accès aux modules par plan.
