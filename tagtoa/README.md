# TAGTOA — Workspace (source de vérité versionnée)

SaaS NFC/QR business platform pou Ayiti (tagtoa.com) — **GOVIBE Ecosystem**,
Roosevelt Forestal. Repo sa a se kote tout travay TAGTOA viv anba **git**, paske
codebase reyèl la (`saas_vcard`, Laravel 10) ap viv sou VPS Interserver la
(`/var/www/tagtoa/`). Chak modil isit la se yon **pakè otonòm** ou kopye sou VPS la.

> 📖 **Li `CLAUDE.md` ANVAN ou touche nenpòt fichye** — se memwa konplè pwojè a
> (achitekti, règ DB, design system, roadmap, pyèj pou evite).

## Estrikti

```
tagtoa/
├── CLAUDE.md                 # Master context (li an premye!)
└── modules/
    ├── menu/                 # TAGTOA MENU  ✅ template + migration
    │   ├── MENU_INTEGRATION.md
    │   ├── database/migrations/
    │   └── resources/views/whatsapp_stores/templates/tagtoa_menu/
    ├── pay/                  # TAGTOA PAY   🔨 modil konplè (Priorité 1)
    │   ├── PAY_INTEGRATION.md
    │   ├── app/{Models,Http/Controllers,Notifications}/
    │   ├── database/migrations/
    │   ├── resources/views/tagtoa/pay/
    │   └── routes/tagtoa_pay_routes.php
    └── loyalty/              # TAGTOA LOYALTY 🟡 modil konplè (Priorité 2)
        ├── LOYALTY_INTEGRATION.md
        ├── app/{Models,Services,Http/Controllers}/
        ├── database/migrations/
        ├── resources/views/tagtoa/loyalty/
        └── routes/tagtoa_loyalty_routes.php
    └── links/                # TAGTOA LINKS 🟢 modil konplè (Priorité 3)
        ├── LINKS_INTEGRATION.md
        ├── app/{Models,Http/Controllers}/
        ├── database/migrations/
        ├── resources/views/tagtoa/links/
        └── routes/tagtoa_links_routes.php
```

Chak dosye modil repwodui achitekti Laravel la, donk deplwaman = `cp -r` + `php artisan migrate`.

## Estati modil yo

| Modil | Estati | Kote |
|-------|--------|------|
| CONNECT | ✅ Templates kreye (sou VPS) | — |
| MENU | ✅ Pakè pare | `modules/menu/` |
| **PAY** | 🔨 **Pakè konplè bati** | `modules/pay/` → li `PAY_INTEGRATION.md` |
| **LOYALTY** | 🟡 **Pakè konplè bati** | `modules/loyalty/` → li `LOYALTY_INTEGRATION.md` |
| **LINKS** | 🟢 **Pakè konplè bati** | `modules/links/` → li `LINKS_INTEGRATION.md` |
| EVENT | ❌ Spec | CLAUDE.md §15 |
| POS | ❌ Spec | CLAUDE.md §16 |

## Deplwaman rapid (sou VPS la)

```bash
cd /var/www/tagtoa
# PAY (Priorité 1)
cp -r modules/pay/app/* app/
cp -r modules/pay/database/migrations/* database/migrations/
cp -r modules/pay/resources/views/tagtoa resources/views/
# + kole modules/pay/routes/tagtoa_pay_routes.php anba routes/web.php
php artisan migrate
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

⚠️ Verifye 5 pwen konpatibilite yo nan `modules/pay/PAY_INTEGRATION.md` (Étape 4)
sou kòd reyèl la anvan ou mete an pwodiksyon.
