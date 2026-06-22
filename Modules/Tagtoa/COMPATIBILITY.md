# TAGTOA × Biztap — Rapport de compatibilité

Vérifié contre le vrai code Biztap (`composer.json` + `routes/web.php` fournis,
juin 2026). Statut : ✅ compatible, 1 ajustement appliqué.

## Stack confirmée (composer.json)
| Élément | Biztap | TAGTOA |
|--------|--------|--------|
| Laravel | `^10.18` | ✅ ciblé 10.x |
| PHP | `^8.1` | ✅ code 8.1-compatible (CI lint 8.2) |
| Modules | `nwidart/laravel-modules ^v10.0` + merge-plugin `Modules/*/composer.json` | ✅ `Modules/Tagtoa` au bon format |
| PSR-4 | `Modules\\ => Modules/` | ✅ `Modules\Tagtoa\...` |
| QR | `simplesoftwareio/simple-qrcode ^4.2` | ✅ façade `\SimpleSoftwareIO\QrCode\Facades\QrCode` (+ fallback) |
| Tenancy | `stancl/tenancy ^3.7` | ✅ via `tenant_id` + scoping explicite (pas de couplage au trait) |
| Permissions | `spatie/laravel-permission ^5.8` | ✅ middleware `role:admin` réutilisé |
| Media | `spatie/laravel-medialibrary ^10.7` | ➖ non requis (stockage filesystem `public`, plus simple) |
| Helpers | `app/helpers.php` (autoload files) | ✅ `getLogInTenantId()` / `getLogInUser()` via `App\Support\Tenant` |

## Routes — pas de conflit
- Public Biztap : `Route::get('{alias}', VcardController@show)` (**1 segment**) + sous-routes
  `{alias}/contact`, `{alias}/blog`, … (2e segment **littéral**), et `whatsapp-store/{alias}`.
- Public TAGTOA : `/pay/{alias}`, `/links/{alias}`, `/event/{alias}` (2 seg, **1er segment
  littéral** distinct), `/loyalty/card/{token}`, `/event/ticket/{code}` (3 seg).
- ✅ Aucun chevauchement : aucun vcard n'a pour préfixe `pay|links|event|loyalty|pos|tagtoa`,
  et le wildcard 1-segment ne capture pas les chemins 2-segments.
- API : préfixe `api/v1/tagtoa` — pas de collision.

## Ajustement appliqué
Le back-office Biztap utilise :
```php
Route::prefix('admin')->middleware('subscription','auth','valid.user','role:admin','multi_tenant')
```
→ Le dashboard TAGTOA passe de `['auth']` à **`['auth','valid.user','role:admin','multi_tenant']`**
(`multi_tenant` initialise le tenant courant nécessaire à `getLogInTenantId()`).
`subscription` volontairement **non** exigé (l'accès TAGTOA n'est pas gaté par un
abonnement actif ; le module Billing gère la monétisation). À activer si souhaité.

## Reste à vérifier sur le code complet (non bloquant)
1. `App\Models\Vcard` : relation `user()` (utilisée pour notifier le owner d'une
   preuve Pay) + colonnes `id`, `name`. Si `user()` n'existe pas, le code retombe
   déjà sur `vcard->email`. → envoyer `app/Models/Vcard.php` pour confirmer.
2. `app/helpers.php` : signatures exactes de `getLogInTenantId()` / `getLogInUser()`.
3. Layout admin (pour, si désiré, intégrer le dashboard TAGTOA dans le menu Biztap).

## Conclusion
Le module `Modules/Tagtoa` est **prêt à déployer** dans Biztap (voir `INSTALL.md`).
Aucune table existante touchée ; tout en `tagtoa_*`. Les 2 points restants sont des
confirmations mineures, pas des blocages.
