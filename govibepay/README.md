# GOVIBEPAY — pwojè apa (PA TAGTOA)

> ⚠️ **GOVIBEPAY ak TAGTOA se DE PWOJÈ DIFERAN.**
>
> | | GOVIBEPAY | TAGTOA |
> |---|---|---|
> | Pwodwi | Pòtfèy mobil / MFS (script **QRPay**, AppDevs) | Platfòm NFC/QR pou machann (`Modules/Tagtoa/`) |
> | Kòd sous | **PA nan repo sa a** — li sou sèvè a sèlman | Nan repo sa a |
> | Domèn | `govibepay.com` (+ `app.govibepay.com`) | `tagtoa.com` |
> | Dosye repo | **`govibepay/`** (sa a) | `Modules/Tagtoa/` |
> | Deplwaman | Workflow `govibepay-merge.yml` (bouton sèlman) | `deploy.yml` (otomatik sou `main`) |
>
> Yo pataje sèlman **menm kont hosting** (`govibepay.com` = kont prensipal,
> `tagtoa.com` = addon domain) epi menm sekrè SSH.
>
> **PA JANM mete fichye GOVIBEPAY anba `Modules/Tagtoa/`**: `deploy.yml` deklanche
> sou `paths: ["Modules/Tagtoa/**"]`, donk yon senp chanjman dokiman GOVIBEPay ta
> deklanche yon **deplwaman pwodiksyon tagtoa.com** (down → migrate → up) san rezon.

## Fusion app.govibepay.com → govibepay.com

Objektif: fè **yon sèl sit**. Script la (ki sou app.govibepay.com) vin sèvi dirèkteman
sou `https://govibepay.com/`, epi `app.govibepay.com` vin **redireksyon 301** vè rasin lan.

Tout bagay fèt ak workflow GitHub Actions: **Actions → « GOVIBEPAY — Fusion
app.govibepay.com → govibepay.com »** (`VPS_SSH_KEY`, `VPS_HOST`, `VPS_USER`, `VPS_PORT`).

## Sa `inspect` te revele (5 out 2026) — enpòtan

Estrikti reyèl la **pa** sa nou te sipoze okòmansman:

| Kote | Sa ki ladan |
|---|---|
| `domains/govibepay.com/public_html/` | **PA gen WordPress** (`wp-config.php` absan). Genyen: `index.php` (~82 Ko = **paj akèy aktyèl la**), `app/` (yon dezyèm script MFS, ViserPay, dekonprese + `.zip` 123 Mo + `install/`), `my/` |
| `domains/app.govibepay.com/public_html/` | **Script la** — Laravel **QRPay** (AppDevs), `APP_NAME="GOVIBEPAY WALLET"`. Laravel konplè nan docroot la (artisan nan rasin docroot) |
| PHP CLI | 8.3.32 |

Sa chanje de bagay fondamantal:

1. **Pa gen WordPress pou sovgade.** Paj akèy aktyèl la se senpleman
   `public_html/index.php`. Donk workflow la **pa deplase docroot la an blòk** ankò —
   li kenbe tout fichye yo an plas (asèt paj akèy la, `app/`, `my/`) epi li jis
   ranplase `index.php`.
2. **« appdev » pa non aplikasyon an.** `APP_NAME` deja `GOVIBEPAY WALLET`. Tout
   okirans `appdev` yo se **URL founisè a**: `appdevs.cloud`, `cdn.appdevs.net`,
   `qrpay.appdevs.net`, `github.com/appdevsx/…` — plis kredi « Developed by AppDevs »
   nan pye paj admin lan. **Ranplase URL sa yo t ap kraze CSS/JS script la.**
   Mode `brand` la kounye a **pa janm touche URL** — li chanje sèlman mansyon mak
   ki vizib yo.

## Paj akèy la — konsève, pa refèt

Ou te mande yon home « menm jan ak paj home aktyèl sou govibepay.com ». Pi bon
repons lan se **kenbe egzakteman fichye a**: `merge` renome `public_html/index.php`
an `public_html/govibepay-home.php` (li rete **nan docroot la** pou asèt relatif li
yo kontinye chaje), epi li ajoute yon wout `GET /` nan fen `routes/web.php` script la
ki sèvi fichye sa a. Rezilta: menm design egzakteman, men li vin **paj akèy script la**,
epi tout lòt URL (`/login`, `/register`, dashboard) rete jan yo ye.

Wout la enskri an dènye → li pran devan wout `/` orijinal script la. Makè:
`GOVIBEPAY-LANDING-ROUTE`, backup `routes/web.php.bak-govibepay`, verifye ak `php -l`
ak restorasyon otomatik si l kase.

## Mode yo

| Mode | Sa l fè |
|---|---|
| **`inspect`** (defo) | Odit sèlman, **okenn chanjman**. Estrikti, paj akèy, script, PHP, **ekspozisyon piblik** (`.env`, `.envMM`, `install/`, zip), kòd HTTP lokal. |
| **`merge`** | Sèvi script la sou rasin lan (front controller jenere + `php -l`, asèt script la kopye **san ekrase** anyen, `storage` relye, `APP_URL`), konsève paj akèy la, mete redireksyon 301 sou sou-domèn nan, epi kouri `brand`. Idanpotan (makè `.govibepay-merge.meta`). |
| **`landing`** | Re-enstale sèlman wout `/` ki sèvi paj akèy la. |
| **`brand`** | Mak GOVIBEPay — `APP_NAME` **sèlman si** li gen « appdev » ladan, epi mansyon vizib nan `resources/views` + `lang`. **Jamè URL**, jamè `app/`, jamè `vendor/`. Backup pa fichye + `php -l` ak auto-restore. |
| **`rollback`** | Remèt paj akèy orijinal la, `.htaccess` yo, `routes/web.php`, mak la, `.env` la. |

## Lòd rekòmande

1. **`inspect`** → li rapò a (sitou seksyon ekspozisyon piblik la).
2. **`merge`**.
3. Verifye: `https://govibepay.com/` (paj akèy), `https://govibepay.com/login`,
   `https://app.govibepay.com` (dwe 301).
4. Si pwoblèm → **`rollback`**.

## ⚠️ Pwen sekirite `inspect` dekouvri

- `public_html/app/` gen yon **`.zip` 123 Mo script peye a** ak yon dosye **`install/`**,
  toude nan docroot piblik la → `https://govibepay.com/app/…` teleachajab. Sou yon app
  finansye, yon `install/` ekspoze se yon risk pran-kontwòl. **Aksyon: deplase
  `public_html/app/` deyò docroot la** (workflow la pa fè l otomatikman — se done ou).
- Script la gen `.env` **ak** `.envMM` nan rasin docroot sou-domèn nan. Si règ
  `.htaccess` la bloke sèlman `.env` egzat, `.envMM` ka telechajab (kredansyèl DB +
  `APP_KEY`). Mode `inspect` teste toude epi make yo 🔴 si yo bay 200.

## Bagay ki ka bezwen atansyon apre fusion

- `https://govibepay.com/app/` ak `/my/` p ap sèvi menm jan ankò (Laravel pran men
  sou URL yo). Fichye yo rete entak sou disk.
- Si gen cron ki pwente sou ansyen chemen, verifye yo.
- Non sit la ka soti nan **baz done** script la (panèl admin → Settings) — mode `brand`
  pa ka chanje sa; log la di w li.
