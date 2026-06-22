# TAGTOA — Workspace (source de vérité versionnée)

SaaS NFC/QR business platform pou Ayiti (tagtoa.com) — **GOVIBE Ecosystem**,
Roosevelt Forestal. Bati kòm yon **modil nwidart** pou aplikasyon vcard SaaS
**Biztap**.

> 📖 **Li `CLAUDE.md` ANVAN ou touche nenpòt fichye** — memwa konplè pwojè a.

## Kote kòd la ye

➡️ **`Modules/Tagtoa/`** (nan rasin repo a) — modil prensipal la, fòma
`nwidart/laravel-modules` (menm jan ak Biztap). Tout fonksyonalite yo ladan,
òganize an sous-dosye:

| Fonksyonalite | Estati |
|---------------|--------|
| **Pay** (paj peman + prèv) | ✅ |
| **Loyalty** (kat NFC, pwen, rekonpans) | ✅ |
| **Links** (Linktree + don) | ✅ |
| **Event** (tikè + check-in PWA) | ✅ (senplifye: san wallet in-event) |
| **POS** (kès tactile offline-first) | ✅ (senplifye: san mouvman kès) |
| **Billing** (abònman + komisyon) | ✅ |

Enstalasyon: gade **`Modules/Tagtoa/INSTALL.md`**.

## `tagtoa/modules/menu/`
Template **TAGTOA MENU** pou WhatsApp Store ki egziste nan Biztap (drop-in
Blade + migration). Gade `tagtoa/modules/menu/MENU_INTEGRATION.md`.

## Prensip kle
- ❌ Pa touche okenn tab egzistan — sèlman nouvo tab `tagtoa_*`.
- ♻️ Reitilize `Vcard`, `Plan`/`Subscription`, helpers (`getLogInTenantId`…) via `App\Support\Tenant`.
- 🎨 Dashboard pwòp ak bèl (pa depann sou ansyen UI vcard la).
- 📱 Paj piblik: HTML standalone, vanilla JS, optimize pou 3G.
