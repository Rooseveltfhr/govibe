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
| EVENT (tikè + checkin + wallet NFC) | ✅ Bati + live | `/event/{alias}` | `/tagtoa/event` |
| BOOKING (rendez-vous) | ✅ Bati + live | `/book/{alias}` | `/tagtoa/booking` |
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
- **Faz 4 — Eksperyans machann** 🔨 AN KOU:
  - ✅ QR & Partage (`/tagtoa/qr`): QR pa resous piblik (Site/Menu/Pay/Links/Event),
    telechaje SVG, afich enprimab (`Support/Qr` simple-qrcode + fallback qrserver).
  - ✅ Analytics (`/tagtoa/analytics`): `AnalyticsService` (revni pa deviz, kòmand,
    vizit, komisyon, graf 14 jou, top pwodwi). CRM (`/tagtoa/customers`):
    `CrmService` agrege kliyan an lekti depi menu/event/pay/pos/loyalty (dedoub pa telefòn).
  - ✅ Notifikasyon imèl (opt-in): `Services/Notifications/NotificationService` (compose +
    validRecipient = lojik pi teste; voye tolerab via `Mail::raw`, try/catch, no-op si
    dezaktive). Branche sou kreyasyon randevou (alèt machann + konfimasyon kliyan).
    Aktive ak `TAGTOA_NOTIFY=true` + config MAIL_* sou VPS la (bezwen aksyon itilizatè).
  - ⏳ RES: notifikasyon WhatsApp (bezwen API/kredansyèl — bloke).
- **Faz 5 — Booking, reviews, estòk, PWA POS, tès, jounal odit** 🔨 AN KOU:
  - ✅ BOOKING (rendez-vous): `tagtoa_booking_pages` + `_booking_services` + `_bookings`.
    Paj piblik `/book/{alias}` (chwazi prestation + dat/lè + koordone → JSON, idempotan
    via client_uuid, pri enpoze sèvè depi prestation aktif), `BookingService` (placeBooking
    + markCompleted → komisyon `RevenueService::record('booking',…)`), dashboard
    `/tagtoa/booking` (CRUD paj + prestations répétables + lis randevou + estati),
    `EnforcesPlan` guard (`booking`: free=0, pro/ent=null), demo `demo-booking`.
  - ✅ Notifikasyon imèl opt-in branche sou randevou (gade Faz 4 RES).
  - ✅ REVIEWS (avis kliyan): tab `tagtoa_reviews` (polimòfik via subject_type+subject_id:
    menu/booking/site/event). `ReviewService` (average/clampRating/distribution = lojik pi
    teste; submit idempotan via client_uuid, note bòne 1..5, stati `pending`). Soumèt piblik
    (`POST /reviews`, tenant_id+alias dérivés sèvè anti-spoof), seksyon piblik reutilizab
    `partials/reviews.blade.php` (rezime+etwal+fòm) branche sou paj menu+booking. Moderasyon
    dashboard `/tagtoa/reviews` (pibliye/rejte/reponn/efase, filtre pa stati). Demo reviews.
  - ✅ ESTÒK (inventory): `StockService` pi (canFulfill/remaining/isLow/isOut, null=san limit,
    teste). POS te gen stòk deja (kolòn + dekremante sou lavant). Ajoute pou MENU: kolòn
    `stock` nullable sou `tagtoa_menu_items` (migrasyon 000073), kapti nan fòm dashboard
    (chak atik), enpoze + dekremante nan `MenuOrderService` (refize kòmand si ripti),
    badj « Épuisé » + dezaktive sou paj piblik, kont « stock faible » sou lis menu. Demo stòk.
  - ✅ JOUNAL ODIT (audit): tab `tagtoa_audit_logs`, `AuditService` (log tolerab + actionLabel
    pi teste). Branche sou aksyon sansib: moderasyon avi (approve/reject/reply/delete),
    estati randevou (completed/cancelled/confirmed), kòmand menu ankese, billing settle/update,
    chanjman fòfè. Viewer lekti sèl `/tagtoa/audit` (filtre pa aksyon, pajine).
  - ✅ PWA POS: kès la enstalab + offline. Manifeste pa terminal (`/tagtoa/pos/{id}/app.webmanifest`),
    service worker (`/tagtoa/pos/sw.js`, network-first navigation + cache-first asset, scope
    `/tagtoa/pos/`), ikòn SVG (`/tagtoa/pos/icon.svg`), bouton « Installer » (beforeinstallprompt).
    Lavant offline (localStorage queue + sync) te deja egziste — PWA ajoute app shell hors-ligne.
  - ⏳ RES: notifikasyon WhatsApp (API — bloke, bezwen kredansyèl).
- **EVENT WALLET (closed-loop + NFC)** 🔨 AN KOU (plan: `Modules/Tagtoa/docs/EVENT_WALLET_PLAN.md`):
  - ✅ 4 tab `tagtoa_ev_*` (nfc_tags UID hashé, wallet_accounts, wallet_txns, wallet_entries).
    Ledger **double-entry immuab** (`Support/Event/Ledger` pi, teste; Σdébits==Σcrédits,
    montan an unités mineures). Actions (`PostLedgerTransaction` atomik + `lockForUpdate` +
    idempotans, `TopUp/Charge/Refund/Payout`, `IssueNfcTag/ResolveNfcTag`,
    `OpenEventWalletAccounts`). Dashboard `/tagtoa/event/{id}/wallet` (recharge, tags,
    réconciliation stands, payout, export CSV) + terminal vandè (Web NFC tap → encaisse).
    `Money::toMinor/fromMinor/formatMinor`. Tès Feature `WalletFlowTest` (kouri nan Biztap).
  - ✅ Notifikasyon: kanal email (egziste) + **WhatsApp via Twilio** (`NotificationService::whatsapp`,
    tolerab/opt-in, dòman san credentials), `Job SendNotification` (queue, milti-kanal),
    `normalizePhone` (pi, teste). Branche sou booking + wallet (top-up/achat). Aktive ak
    `TAGTOA_WA_NOTIFY=true` + `TAGTOA_TWILIO_SID/TOKEN/WHATSAPP_FROM` sou VPS (bezwen itilizatè).
  - ✅ Check-in NFC: `CheckinService::resolveNfcCode` (UID→billet), endpoint `scan-nfc`,
    bouton NFC nan scanner. Notifikasyon antre: òganizatè pa imèl (`tagtoa_ev_events.notify_email`)
    + patisipan pa WhatsApp. Encodage kat: `EncodeParticipantCard` (billet+wallet+rechaj) sou
    dashboard wallet. Gid konplè: `docs/EVENT_NFC_GUIDE.md`.
  - ⏳ RES: top-up API reyèl (drivers PAY — bloke).
- **EVENT STAFF + BIYÈT HYBRIDE** ✅ FÈT (PR #42/#44/#45, patèn « Festival Couleur » adapte multi-tenant):
  - Tab: `tagtoa_ev_staff` (scoped event_id, wòl admin|vente|checkin, pin_hash bcrypt),
    `tagtoa_ev_sync_conflicts`, `staff_id` sou checkins, `checkin_mode` (qr|nfc|both) sou events.
  - Lojik pi teste: `StaffPinService` (PIN 4-6 chif, matris wòl→ekran), `SyncReconciler`
    (dedoub client_uuid + doub antre). Nan `tests/bootstrap.php`.
  - Òganizatè `/tagtoa/event/{id}/staff`: CRUD staff (limit pa fòfè: free=0/pro=10/ent=∞,
    kle `staff` nan config plans), konfli sync, export CSV check-ins pa staff. Tout odite.
  - Terminal teren `/event/staff/{alias}` (san login Laravel): PIN + rate-limit 8/min,
    ekran pa wòl — check-in (kod/UID + Web NFC, **offline IndexedDB** + sync pa lo),
    vant (WhatsApp obligatwa, kat NFC oswa e-biyè QR), retrè kat (lye biyè an liy → UID,
    refize si kòmand pa peye), suivi admin. Demo: Admin/1234, Vente/2222, Checkin/3333.
  - Kòmand an liy: bouton « Encaisser » (`orders.paid`) → markPaid + komisyon + odit.
- **ROADMAP KALITE (Faz 4/5/7)** ✅ FÈT (PR #65/#66 + NFC):
  - **Faz 4 — Tès entegrite wout** (PR #65): `RouteIntegrityTest` + `Support/Dev/RouteNames`
    (analiz estatik pi) verifye chak `route('tagtoa.*')` nan vi/kontrolè byen defini nan
    `routes/web.php` — anpeche 500 `RouteNotFoundException` (CI pa bòt Laravel). 153 defini/132 itilize/0 manke.
  - **Faz 5 — Asèt souveren** (PR #66): scanner biyè a te chaje html5-qrcode depi unpkg (SPOF
    ekstèn sou chèk-in). Kounye a vendored lokalman + sèvi via `AssetController` (wout piblik
    `tagtoa.asset`, kach imuab). `resources/assets/vendor/`.
  - **Faz 7 — Sekirite NFC (anti-klonaj/anti-rejeu)** ⏳ DÒMAN (kòd pi teste): `Support/Nfc/AesCmac`
    (RFC 4493, pwouve vs vektè ofisyèl) + `Support/Nfc/Ntag424` (SUN/SDM: dérivation kle sesyon SV2,
    troncature, verif CMAC, ekstrè UID/kontè, anti-rejeu). `AesCmacTest`+`Ntag424Test` (13 tès).
    Gid aktivasyon: `docs/NFC_SECURITY.md`. **Bezwen aksyon fondatè**: pwovizyone kle NTAG424 +
    valide SV2 kont tag reyèl AVAN kable nan check-in/wallet (pa modifye kòd live san tès entegrasyon).

## 6. Deplwaman & URL
- ✅ **RESTRUKTIRASYON FINI** (jiyè 2026): app la nan
  `$HOME/domains/tagtoa.com/laravel/`, docroot = `public_html/` (front controller
  `public_html/index.php` pwente sou `../laravel`). Base = **rasin `https://tagtoa.com/`**.
  Kont lan se **govibepay.com** (tagtoa.com = addon domain), donk `$HOME` = kont govibepay.
- ⚠️ **PYÈJ chemen**: `/home` PA lizib → glob `/home/*/domains` **echwe**. Sèvi ak
  `$HOME/domains/tagtoa.com`. deploy.yml gen yon fallback `$HOME/domains` (li mache).
- ✅ **500 « Mix manifest not found » REPARE**: `mix()` chèche `laravel/public/mix-manifest.json`
  men asèt bati yo nan `public_html/`. `remote-deploy.sh` relye otomatikman
  (mix-manifest + dosye asèt public_html → laravel/public) + `config:cache`+`view:cache`.
- Zouti dyagnostik/reparasyon: `.github/workflows/diagnose.yml` (bouton, lekti log +
  vidaj cache + relye asèt, SAN DB). Deklanche via Actions (input `clear_cache`).
- ⚠️ Aksyon itilizatè toujou: woule `DB_PASSWORD` (te ekspoze), `TAGTOA_CONTACT_WHATSAPP`
  (bouton Solutions), `TAGTOA_TWILIO_*` (WhatsApp), kle NTAG424 (Faz 7).
- Login admin: `https://tagtoa.com/login` (rasin). Konekte → LandingController redirije
  otomatik sou `/tagtoa/home` (hub TAGTOA). `/sadmin` = admin platfòm Biztap (apa).
- **Event (kontinye jiyè 2026, PR #71-75)**: kreyasyon anrichi (type elaji, mode biyè
  qr/nfc/both, prix vizib depi kreyasyon, discount `compare_at_price`), vente staff
  (metòd peman TAGTOA Pay + li NFC), annuaire piblik `/events`, konfimasyon achte
  (WhatsApp/email), dat fen + partage. Landing: seksyon Solutions (Identity/Access + kontak).
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
- MENU: `demo-menu` · PAY: `demo` · LINKS: `demo-links` · EVENT: `demo-concert` · BOOKING: `demo-booking`
- LOYALTY token: `uvcudqvm9xsie6knrkhbdcok` · POS terminal id: `1`
- EVENT WALLET: tag NFC demo UID `TAGTOA-DEMO-TAG` (solde 1000 G, stand « Bar Demo ») sou
  `demo-concert` → teste sou `/tagtoa/event/{id}/wallet/terminal` (tape UID a oswa tap NFC).
- Teste lang: ajoute `?lang=ht|en|es|fr` sou nenpòt paj.

## 8. Pyèj konnen (gotchas)
- merge-plugin PA mèj composer modil sou `dump-autoload` sèl → PSR-4 enjekte nan
  composer.json rasin VPS la (remote-deploy.sh fè l idempotan).
- `role:admin|super_admin` (fondatè a se super_admin).
- `route:list` kase sou VPS akoz yon GooglePlayService Biztap (pa pwoblèm TAGTOA).
- Tès Unit = `PHPUnit\Framework\TestCase` pi (san Laravel) → klas teste yo dwe
  tolerab (gade `Money`).
- **PA JANM** mete `fn(...)=>[...]` (fonksyon flèch + tablo) andedan `@json(...)` oswa
  lòt direktiv Blade — parser la kase (« Unclosed '[' ») e paj la crash an prod (PR #41).
  Mete lojik la nan `@php ... @endphp` (san `use` — non konplè klas), pase yon varyab senp.
  AVAN chak push vue: konpile ak vrè konpilatè Blade (illuminate/view) + `php -l` rezilta a.
