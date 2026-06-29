# CLAUDE.md — TAGTOA (mémoire vivante / living memory)

> **LIS FICHYE SA ANVAN OU TOUCHE OKENN FICHYE.**
> Sa se memwa otorite a sou eta AKTYÈL pwojè TAGTOA. Claude Code li l otomatikman
> nan kòmansman chak sesyon. Mete l AJOU lè yon bagay enpòtan chanje.
>
> Memwa istorik/detay konplè: voir `tagtoa/CLAUDE.md` (brief orijinal).

---

## 1. Ki sa TAGTOA ye
SaaS **NFC/QR business platform** pou Ayiti (tagtoa.com), GOVIBE Ecosystem.
Fondatè: Roosevelt Forestal. Pwojè « $1M » — egzijans: jeni durab, kalite ekspè.

Grafe sou yon SaaS vcard achte ki rele **Biztap** (Laravel 10, `nwidart/laravel-modules`,
`stancl/tenancy`, `spatie/laravel-permission`). **Tout TAGTOA nan YON sèl modil**:
`Modules/Tagtoa/` (Opsyon 3 — sou-dosye pa fonksyonalite).

## 2. Règ ABSOLI
- **Baz done**: PA JANM modifye tab egzistan yo. Sèlman ajoute tab nouvo ak prefiks
  `tagtoa_*`. Kolòn nouvo dwe nullable/default.
- **Devlopman**: branch `claude/serene-brahmagupta-do562e`. Reset sou `origin/main`
  AVAN chak nouvo travay; `--force-with-lease` apre. PR an draft → CI vèt → merge squash.
- **Deplwaman**: merge nan `main` deklanche auto-deploy (GitHub Actions → VPS).
- **Idantite modèl**: pa janm mete ID modèl nan commit/PR/kòd.

## 3. Eta modil yo (AKTYÈL)
| Modil | Eta | Wout piblik | Dashboard |
|---|---|---|---|
| SITE (sitwèb pa abònman) | ✅ Bati + live | `/site/{alias}` | `/tagtoa/site` |
| MENU (restoran/club/lounge/otèl) | ✅ Bati + live | `/menu/{alias}` | `/tagtoa/menu` |
| PAY (24 metòd, prèv manyèl) | ✅ Bati + live | `/pay/{alias}` | `/tagtoa/pay` |
| LOYALTY (kat NFC, pwen) | ✅ Bati + live | `/loyalty/card/{token}` | `/tagtoa/loyalty` |
| LINKS (Linktree + don) | ✅ Bati + live | `/links/{alias}` | `/tagtoa/links` |
| EVENT (tikè + checkin) | ✅ Bati + live | `/event/{alias}` | `/tagtoa/event` |
| POS (kès offline) | ✅ Bati + live | — | `/tagtoa/pos` |
| BILLING (revni/komisyon) | ✅ Bati + live | — | `/tagtoa/billing` |
| CONNECT (vcard) | ↩️ Biztap egzistan | `/{alias}` | Biztap |

Hub dashboard: `/tagtoa/home` (PA `/tagtoa` — li antre an konfli ak vcard `{alias}`).
24 tab `tagtoa_*`.

## 4. i18n + Lajan (Faz 1 — ✅ live)
- Lang: **fr (sous) · ht · en · es** — `resources/lang/{en,ht,es}.json` (313 kle),
  chaje via `loadJsonTranslationsFrom`. Middleware `App\Http\Middleware\SetLocale`
  (?lang → session → cookie → Accept-Language → default) sou tout wout web modil la.
- Sèlktè lang: `resources/views/partials/lang.blade.php` (san JS), nan dashboard topbar
  + paj piblik (menu, pay, links, event).
- Lajan pa lang: ht→HTG, en→USD, fr→EUR, es→DOP (+ CAD). Config: `config/config.php`.
- Helpers: `App\Support\Locale` (lang/devise kouran), `App\Support\Money` (fòmataj,
  tolerab — `Money::DEFAULTS` mache menm san config/Laravel).

## 5. Plan faz yo
- **Faz 1 — Lang + Lajan** ✅ FÈT (PR #9)
- **Faz 2 — Kolòn revni** 🔨 AN KOU:
  - ✅ Kòmand MENU nan DB (`tagtoa_menu_orders` + `_order_items`) — kaptire sou paj
    piblik (`MenuOrderService`, pri enpoze sèvè, idempotan via client_uuid),
    jesyon kòmand dashboard (`/tagtoa/menu/{id}/orders`, estati + ankese),
    komisyon otomatik sou kòmand peye (`RevenueService::record('menu_order',…)`).
  - ✅ Relve & règleman komisyon (BILLING): rezime pa deviz (brut/komisyon/à régler/réglé),
    bouton « Régler » (accrued→settled, `settled_at`), export CSV.
  - ✅ Fondasyon pasrèl PAY: rejis auto/manyèl (`Support/PaymentGateway`), deteksyon
    aktivasyon (`Support/GatewayManager`, kredansyèl nan config/.env), afichaj piblik
    rich (logo+koulè mak, institution, nom du compte, numéro, QR), chan institution+logo
    nan dashboard. Doc kredansyèl: `Modules/Tagtoa/PAYMENTS.md`.
  - ⏳ RES: drivers API reyèl (1 PR pa pasrèl, teste ak kredansyèl): MonCash, PayPal(+kat),
    CoinPayments (USDT/USDC/BTC/ETH), Stripe, Authorize.Net — route `tagtoa.pay.checkout`
    + webhook/IPN. Metòd manyèl yo (NatCash, Zelle, CashApp, Unibank, Sogebank, Capital
    Bank, BNC) rete sou prèv.
- **Faz 3 — Abonman + plan gating** ✅ FÈT: `tagtoa_subscriptions` + config `tagtoa.plans`
  (free/pro/enterprise, limit pa modil), `PlanService` (limit/usage/canCreate),
  trait `EnforcesPlan` (guard nan store() tout modil), paj `/tagtoa/plan` (usage +
  chanjman fòfè self-service). Peman fòfè otomatik = ap vini ak pasrèl PAY.
- **Faz 4 — QR nan dashboard, notifikasyon (WhatsApp/email), CRM kliyan, analytics**
- **Faz 5 — Booking, reviews, estòk, PWA POS, tès, jounal odit**

## 6. Deplwaman & URL
- App sèvi nan `public/`: base = **https://tagtoa.com/tapbiz/public**
- Login admin: `/tapbiz/public/login`
- **Paj akèy piblik** (`LandingController` → `landing.blade.php`) sou wout rasin `/`
  (modil la override akèy Biztap la — wout anrejistre apre). Parèt sou `<base>/`.
  Pou l parèt sou **bare tagtoa.com**: pwente docroot domèn nan sou dosye `public/`
  Laravel la nan DirectAdmin (oswa redireksyon nan public_html/).
- CI: `.github/workflows/ci.yml` (lint + `phpunit --testsuite Unit`, bootstrap
  `tests/bootstrap.php` chaje sous pi yo manyèlman — ajoute nouvo klas pi la).
- Deploy: `.github/workflows/deploy.yml` → `Modules/Tagtoa/deploy/remote-deploy.sh`
  (down → dump-autoload → migrate → smoke → seed demo → up; rollback otomatik).
- Seed demo idempotent sou chak deploy (`TAGTOA_SEED_DEMO=0` pou koupe).
- Smoke piblik opsyonèl: mete `TAGTOA_SMOKE_BASE=https://tagtoa.com/tapbiz/public`.

## 7. Done demo (pou teste)
- MENU: `demo-menu` · PAY: `demo` · LINKS: `demo-links` · EVENT: `demo-concert`
- LOYALTY token: `uvcudqvm9xsie6knrkhbdcok` · POS terminal id: `1`
- Teste lang: ajoute `?lang=ht|en|es|fr` sou nenpòt paj.

## 8. Pyèj konnen (gotchas)
- merge-plugin PA mèj composer modil sou `dump-autoload` sèl → PSR-4 enjekte nan
  composer.json rasin VPS la (remote-deploy.sh fè l idempotan).
- `role:admin|super_admin` (fondatè a se super_admin).
- `route:list` kase sou VPS akoz yon GooglePlayService Biztap (pa pwoblèm TAGTOA).
- Tès Unit = `PHPUnit\Framework\TestCase` pi (san Laravel) → klas teste yo dwe
  tolerab (gade `Money`).
