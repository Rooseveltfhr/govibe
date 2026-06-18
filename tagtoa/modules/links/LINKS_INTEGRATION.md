# TAGTOA LINKS — Guide d'intégration

Module **Priorité 3 🟢**. Page Linktree-style TAGTOA : avatar, bio, liens en
boutons, **logos de plateforme auto-détectés** depuis l'URL, icônes sociales en
bas, et section **don** qui réutilise une page **TAGTOA PAY** existante.
URL publique : `tagtoa.com/links/{alias}`.

> ⚠️ Respecte la règle DB absolue : **aucune table existante n'est modifiée**.
> Nouvelles tables `tagtoa_link_pages` et `tagtoa_links`.
> NB : le module LINKS dédié ici **coexiste** avec `CustomLink` / `VcardPaymentLink`
> du projet (il ne les touche pas). On part sur des tables propres pour rester
> indépendant du schéma exact de l'existant.

---

## Fichiers fournis

| Fichier | Destination |
|---|---|
| `database/migrations/2026_06_18_000020_create_tagtoa_link_pages_table.php` | `database/migrations/` |
| `database/migrations/2026_06_18_000021_create_tagtoa_links_table.php`       | `database/migrations/` |
| `app/Models/TaGtoaLinkPage.php` · `TaGtoaLink.php` | `app/Models/` |
| `app/Http/Controllers/TaGtoaLinkController.php` · `TaGtoaLinkDashboardController.php` | `app/Http/Controllers/` |
| `resources/views/tagtoa/links/show.blade.php` | `resources/views/tagtoa/links/` |
| `resources/views/tagtoa/links/dashboard/*.blade.php` | `resources/views/tagtoa/links/dashboard/` |
| `routes/tagtoa_links_routes.php` | contenu à coller en bas de `routes/web.php` |

---

## Déploiement

```bash
cd /var/www/tagtoa
cp -r modules/links/app/* app/
cp -r modules/links/database/migrations/* database/migrations/
cp -r modules/links/resources/views/tagtoa resources/views/
# + coller routes/tagtoa_links_routes.php en bas de routes/web.php
php artisan migrate
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Détection automatique de plateforme

`TaGtoaLink::detectPlatform($url)` analyse le host (et `mailto:` / `tel:`) et
renvoie `facebook|instagram|tiktok|youtube|twitter|linkedin|telegram|whatsapp|
snapchat|twitch|pinterest|discord|spotify|github|email|phone|website|custom`.
L'owner n'a **rien à choisir** : il colle l'URL, le bon logo Font Awesome apparaît
(map `TaGtoaLink::PLATFORM_ICONS`).

Rendu public :
- liens **featured** ou non-sociaux → gros boutons ;
- réseaux sociaux → rangée d'icônes en bas ;
- section **don** affichée si `pay_page_id` pointe vers une page TAGTOA PAY active.

Clics comptés via `/links/go/{link}` (redirection 302 + `incrementQuietly('clicks')`).

---

## Dépendance : TAGTOA PAY (pour les dons)

Le champ `pay_page_id` référence `tagtoa_payment_pages.id`. Déploie d'abord le
module **PAY** (`modules/pay/`) si tu veux la section don. Sinon laisse vide :
la page LINKS fonctionne sans don.

---

## Points de compatibilité à vérifier

1. `getLogInTenantId()` + `BelongsToTenant` (scope tenant des pages).
2. `spatie/laravel-medialibrary` (avatar, collection `avatar`).
3. Layout admin des vues dashboard (`@extends('layouts.app')` → adapter).
4. `App\Models\Vcard` (relation facultative, `vcard_id` nullable).
5. Module PAY déployé si la section don est utilisée.

---

## Demo

1. Créer une page (`/tagtoa/links/create`), thème "blue", avatar + bio.
2. Coller 4-5 URLs (Instagram, TikTok, YouTube, WhatsApp, site web) → logos auto.
3. Marquer 1 lien "Top" (featured) pour le gros bouton bleu.
4. Optionnel : sélectionner une page PAY pour activer le don.
5. Écrire le `public_url` sur une puce NFC / générer un QR.
